<?php
namespace App\Main\Actions;

use App\Main\McpTools;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * /mcp: the Model Context Protocol server, so that AI assistants can use the tools of App\Main\McpTools.
 *
 * Written by hand over plain HTTP: POST a JSON-RPC message, get one JSON answer, nothing kept between requests, which is
 * all a shared host can do. Speaks the stateless revision (2026-07-28: each request carries its version in `_meta` and
 * the headers, server/discover) and the earlier ones, with their `initialize`, which clients such as ChatGPT may still use.
 *
 * Forms from other sites are refused before this by CsrfMiddleware, which is the check of the Origin header MCP asks for.
 */
class McpAction
{
    const VERSION = '2026-07-28';

    const OLDER_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    const META = 'io.modelcontextprotocol/';

    private const SERVER_INFO = ['name' => 'rockfever', 'title' => 'Rock Fever', 'version' => '1.0.0'];

    private const INVALID_REQUEST = -32600;
    private const METHOD_NOT_FOUND = -32601;
    private const INVALID_PARAMS = -32602;
    private const INTERNAL_ERROR = -32603;
    private const PARSE_ERROR = -32700;
    private const HEADER_MISMATCH = -32020;
    private const UNSUPPORTED_VERSION = -32022;

    // What the assistant can keep the list of tools for
    private const TTL = 3600000;

    public function __construct(private McpTools $tools)
    {
    }

    public function __invoke(ServerRequestInterface $request) : ResponseInterface
    {
        // There's nothing to stream or to close
        if ($request->getMethod() !== 'POST') {
            return new Response(405, ['Allow' => 'POST']);
        }
        $message = json_decode((string) $request->getBody(), true);
        if ($message === null) {
            return self::error(null, self::PARSE_ERROR, 'Invalid JSON', 400);
        }
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0'
            || !is_string($message['method'] ?? null)) {
            return self::error(null, self::INVALID_REQUEST, 'Expected one JSON-RPC 2.0 message', 400);
        }
        // A notification, such as notifications/initialized: nothing to answer
        if (!array_key_exists('id', $message)) {
            return new Response(202);
        }
        $id = $message['id'];
        $method = $message['method'];
        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return self::error($id, self::INVALID_PARAMS, 'params must be an object', 400);
        }
        if ($method === 'initialize') {
            return self::result($id, $this->initialize($params));
        }

        $version = $params['_meta'][self::META . 'protocolVersion'] ?? null;
        $header = $request->getHeaderLine('MCP-Protocol-Version');
        // Older clients get their errors with a 200, as JSON-RPC over HTTP did then
        $stateless = $version !== null || $header === self::VERSION;
        $requested = $version ?? $header;
        if ($requested !== '' && !in_array($requested, self::versions(), true)) {
            return self::error($id, self::UNSUPPORTED_VERSION, 'Unsupported protocol version', 400, [
                'supported' => self::versions(),
                'requested' => $requested,
            ]);
        }
        if ($stateless) {
            if ($version === null || !isset($params['_meta'][self::META . 'clientCapabilities'])) {
                return self::error($id, self::INVALID_PARAMS, 'Missing _meta fields', 400);
            }
            $mismatch = self::findHeaderMismatch($request, $version, $method, $params);
            if ($mismatch !== null) {
                return self::error($id, self::HEADER_MISMATCH, 'Header mismatch: ' . $mismatch, 400);
            }
        }

        return match ($method) {
            'ping' => self::result($id, []),
            'server/discover' => self::result($id, [
                'supportedVersions' => self::versions(),
                'capabilities' => ['tools' => new \stdClass()],
                'instructions' => McpTools::INSTRUCTIONS,
                'ttlMs' => self::TTL,
                'cacheScope' => 'public',
            ]),
            'tools/list' => self::result($id, [
                'tools' => $this->tools->definitions(),
                'ttlMs' => self::TTL,
                'cacheScope' => 'public',
            ]),
            'tools/call' => $this->callTool($id, $params, $request, $stateless),
            default => self::error($id, self::METHOD_NOT_FOUND, 'Method not found: ' . $method, $stateless ? 404 : 200),
        };
    }

    private function initialize(array $params) : array
    {
        $requested = $params['protocolVersion'] ?? null;
        return [
            'protocolVersion' => in_array($requested, self::OLDER_VERSIONS, true) ? $requested : self::OLDER_VERSIONS[0],
            'capabilities' => ['tools' => new \stdClass()],
            'serverInfo' => self::SERVER_INFO,
            'instructions' => McpTools::INSTRUCTIONS,
        ];
    }

    private function callTool($id, array $params, ServerRequestInterface $request, bool $stateless) : ResponseInterface
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];
        if (!is_string($name) || !$this->tools->has($name) || !is_array($arguments)) {
            return self::error($id, self::INVALID_PARAMS, 'Unknown tool or invalid arguments', $stateless ? 400 : 200);
        }
        try {
            $data = $this->tools->call($name, $arguments, $request);
        } catch (\DomainException $e) {
            // For the assistant to read and tell the person, rather than a failure of the protocol
            return self::result($id, ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true]);
        } catch (\Throwable $e) {
            error_log('MCP ' . $name . ': ' . $e);
            return self::error($id, self::INTERNAL_ERROR, 'Internal error', $stateless ? 500 : 200);
        }
        return self::result($id, [
            'content' => [['type' => 'text', 'text' => self::json($data)]],
            'structuredContent' => $data,
            'isError' => false,
        ]);
    }

    /**
     * What differs between the headers of a stateless request and its body, so that gateways can trust the headers
     */
    private static function findHeaderMismatch(
        ServerRequestInterface $request,
        string $version,
        string $method,
        array $params
    ) : ?string {
        if ($request->getHeaderLine('MCP-Protocol-Version') !== $version) {
            return 'MCP-Protocol-Version';
        }
        if ($request->getHeaderLine('Mcp-Method') !== $method) {
            return 'Mcp-Method';
        }
        if ($method === 'tools/call') {
            $name = $request->getHeaderLine('Mcp-Name');
            if (preg_match('/^=\?base64\?(.*)\?=$/', $name, $match)) {
                $name = (string) base64_decode($match[1]);
            }
            if ($name !== ($params['name'] ?? null)) {
                return 'Mcp-Name';
            }
        }
        return null;
    }

    private static function versions() : array
    {
        return [self::VERSION, ...self::OLDER_VERSIONS];
    }

    private static function result($id, array $result) : ResponseInterface
    {
        return self::respond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resultType' => 'complete'] + $result + [
            '_meta' => [self::META . 'serverInfo' => self::SERVER_INFO],
        ]]);
    }

    private static function error($id, int $code, string $message, int $status, ?array $data = null) : ResponseInterface
    {
        $error = ['code' => $code, 'message' => $message] + ($data === null ? [] : ['data' => $data]);
        return self::respond($status, ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error]);
    }

    private static function respond(int $status, array $body) : ResponseInterface
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], self::json($body));
    }

    private static function json(array $data) : string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
