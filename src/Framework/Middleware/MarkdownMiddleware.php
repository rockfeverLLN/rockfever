<?php
namespace Framework\Middleware;

use GuzzleHttp\Psr7\Utils;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers in Markdown to clients that ask for it (Accept: text/markdown), as AI agents do:
 * the page's <main>, without the menu, footer, scripts and forms
 */
class MarkdownMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        /** @var ResponseInterface $response */
        $response = $next($request);
        // Pages rendered from a string have no Content-Type yet: PHP sends text/html
        $type = $response->getHeaderLine('Content-Type') ?: 'text/html';
        if ($response->getStatusCode() !== 200 || !str_starts_with($type, 'text/html')) {
            return $response;
        }
        // The same URL answers HTML or Markdown: caches must keep them apart
        $response = $response->withAddedHeader('Vary', 'Accept');
        if (!self::prefersMarkdown($request->getHeaderLine('Accept'))) {
            return $response;
        }
        $markdown = self::toMarkdown((string) $response->getBody());
        return $response
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withBody(Utils::streamFor($markdown));
    }

    /**
     * True when text/markdown has a higher or equal q than text/html: "text/markdown, text/html;q=0.9" is Markdown,
     * a browser's "text/html,...,*\/*;q=0.8" isn't
     */
    private static function prefersMarkdown(string $accept) : bool
    {
        $markdown = 0.0;
        $html = 0.0;
        foreach (explode(',', $accept) as $range) {
            $parts = array_map('trim', explode(';', $range));
            $type = strtolower(array_shift($parts));
            $q = 1.0;
            foreach ($parts as $param) {
                if (preg_match('/^q=([0-9.]+)$/i', $param, $match)) {
                    $q = (float) $match[1];
                }
            }
            if ($type === 'text/markdown' || $type === 'text/x-markdown') {
                $markdown = max($markdown, $q);
            } elseif ($type === 'text/html') {
                $html = max($html, $q);
            }
        }
        return $markdown > 0 && $markdown >= $html;
    }

    private static function toMarkdown(string $html) : string
    {
        preg_match('~<title>(.*?)</title>~s', $html, $title);
        preg_match('~<meta name="description" content="(.*?)"~s', $html, $description);
        preg_match('~<main[^>]*>(.*)</main>~s', $html, $main);

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style form noscript nav',
            'hard_break' => true,
            'header_style' => 'atx',
        ]);
        $body = $converter->convert($main[1] ?? $html);
        // The layout's indentation leaves lines of spaces: drop them and the blank runs they make
        $body = preg_replace(['/^[ \t]+/m', '/\n{3,}/'], ['', "\n\n"], $body);

        $head = '';
        if (!empty($title[1])) {
            $head .= '# ' . html_entity_decode(trim($title[1]), ENT_QUOTES | ENT_HTML5) . "\n\n";
        }
        if (!empty($description[1])) {
            $head .= '> ' . html_entity_decode(trim($description[1]), ENT_QUOTES | ENT_HTML5) . "\n\n";
        }
        return $head . trim($body) . "\n";
    }
}
