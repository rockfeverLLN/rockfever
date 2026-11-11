<?php
/**
 * Signs up through /mcp from start to finish on a running site: writes to the database, so only on a test one.
 * Run by bin/test.sh. Adds a soirée for tomorrow, and removes it with its sign-ups at the end.
 *
 * Usage: php bin/mcp-test.php [base-url]   (default http://127.0.0.1:8000)
 */
chdir(dirname(__DIR__));
$config = require dirname(__DIR__) . '/config/database.php';
if (!in_array($config['database.host'], ['127.0.0.1', 'localhost'], true)) {
    exit("config/database.php isn't a local database: not running\n");
}
$url = ($argv[1] ?? 'http://127.0.0.1:8000') . '/mcp';
$pdo = new PDO(
    'mysql:host=' . $config['database.host'] . ';dbname=' . $config['database.name'] . ';charset=utf8mb4',
    $config['database.username'],
    $config['database.password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$failed = false;
$check = function (string $label, bool $ok) use (&$failed) : void {
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . "\n";
    $failed = $failed || !$ok;
};

/**
 * @return array{0: int, 1: array} the status and the decoded answer
 */
$post = function (array $message, array $headers = []) use ($url) : array {
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'ignore_errors' => true,
        'header' => array_merge(['Content-Type: application/json', 'User-Agent: mcp-test'], $headers),
        'content' => json_encode(['jsonrpc' => '2.0'] + $message),
    ]]);
    $body = (string) file_get_contents($url, false, $context);
    preg_match('/\d{3}/', http_get_last_response_headers()[0] ?? '', $status);
    return [(int) ($status[0] ?? 0), json_decode($body, true) ?? []];
};

// As a client of the current revision does
$rpc = function (string $method, array $params = []) use ($post) : array {
    $headers = ['MCP-Protocol-Version: 2026-07-28', 'Mcp-Method: ' . $method];
    if (isset($params['name'])) {
        $headers[] = 'Mcp-Name: ' . $params['name'];
    }
    $params['_meta'] = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
    ];
    return $post(['id' => 1, 'method' => $method, 'params' => $params], $headers);
};
$tool = function (string $name, array $arguments = []) use ($rpc) : array {
    return $rpc('tools/call', ['name' => $name, 'arguments' => $arguments])[1]['result'] ?? [];
};
$dancers = function (int $eventId) use ($pdo) : int {
    return (int) $pdo->query('SELECT COUNT(*) FROM dancers WHERE event_id = ' . $eventId)->fetchColumn();
};

$day = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$pdo->prepare(
    "INSERT INTO events (title, description, date, waltz_time, rock_time, place, price, facebook_link)
     VALUES ('MCP test', '', ?, '20:30:00', '21:00:00', '', '', '')"
)->execute([$day]);
$eventId = (int) $pdo->lastInsertId();
// A soirée planned for today, still to come tonight, would be the next one rather than the test's: set it aside
$today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$setAside = $pdo->prepare('SELECT id FROM soirees WHERE date >= ? AND date < ? AND cancelled = 0');
$setAside->execute([$today, $day]);
$setAside = array_map('intval', $setAside->fetchAll(PDO::FETCH_COLUMN));
if ($setAside) {
    $pdo->exec('UPDATE soirees SET cancelled = 1 WHERE id IN (' . implode(',', $setAside) . ')');
}

try {
    // The protocol
    [, $answer] = $post(['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
    $check('initialize, for older clients', ($answer['result']['protocolVersion'] ?? '') === '2025-06-18');
    [$status] = $post(['method' => 'notifications/initialized']);
    $check('a notification gets 202', $status === 202);
    [, $answer] = $post(['id' => 1, 'method' => 'tools/list']);
    $check('tools/list without a version', count($answer['result']['tools'] ?? []) === 4);
    [, $answer] = $rpc('server/discover');
    $check('server/discover', in_array('2026-07-28', $answer['result']['supportedVersions'] ?? [], true));
    [, $answer] = $rpc('tools/list');
    $check('tools/list with ttlMs', isset($answer['result']['ttlMs'], $answer['result']['cacheScope']));
    [$status, $answer] = $rpc('nothing/here');
    $check('an unknown method gets 404', $status === 404 && ($answer['error']['code'] ?? 0) === -32601);
    [$status, $answer] = $post(['id' => 1, 'method' => 'tools/list', 'params' => ['_meta' => [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
    ]]], ['MCP-Protocol-Version: 2026-07-28', 'Mcp-Method: tools/call']);
    $check('headers that differ from the body get 400', $status === 400 && ($answer['error']['code'] ?? 0) === -32020);
    [$status, $answer] = $post(['id' => 1, 'method' => 'tools/list'], ['MCP-Protocol-Version: 1900-01-01']);
    $check('an unknown version gets 400', $status === 400 && ($answer['error']['code'] ?? 0) === -32022);

    // As ChatGPT needs them
    $body = ['id' => 1, 'method' => 'tools/list'];
    foreach (['https://chatgpt.com', 'https://claude.ai'] as $origin) {
        [$status] = $post($body, ['Origin: ' . $origin]);
        $check('the site of an assistant: ' . $origin, $status === 200);
    }
    [$status] = $post($body, ['Origin: https://elsewhere.example', 'Sec-Fetch-Site: cross-site']);
    $check('another site gets 403', $status === 403);
    [$status] = $post($body, ['Origin: null']);
    $check('and so does a sandboxed page', $status === 403);
    $preflight = stream_context_create(['http' => ['method' => 'OPTIONS', 'ignore_errors' => true, 'header' => "Origin: https://chatgpt.com\r\nUser-Agent: mcp-test"]]);
    file_get_contents($url, false, $preflight);
    $headers = implode("\n", http_get_last_response_headers() ?? []);
    $check('the browser may ask first', str_contains($headers, ' 204') && stripos($headers, 'Access-Control-Allow-Origin: https://chatgpt.com') !== false);
    foreach ($post($body)[1]['result']['tools'] ?? [] as $definition) {
        $annotations = $definition['annotations'] ?? [];
        $check($definition['name'] . ' says what it does and gives', is_bool($annotations['readOnlyHint'] ?? null)
            && is_bool($annotations['destructiveHint'] ?? null) && is_bool($annotations['openWorldHint'] ?? null)
            && isset($definition['outputSchema']) && ($definition['securitySchemes'][0]['type'] ?? '') === 'noauth');
    }

    // Reading
    $events = $tool('list_events')['structuredContent'] ?? [];
    $check('list_events has the soirée', ($events['next'] ?? '') === $day);
    $check('get_event', ($tool('get_event', ['event_id' => $day])['structuredContent']['registration']['status'] ?? '') === 'open');
    $check('get_event of no soirée is an error', ($tool('get_event', ['event_id' => '2000-01-01'])['isError'] ?? false) === true);

    // Signing up
    $names = ['firstname' => 'Zoé', 'lastname' => 'Test'];
    $preview = $tool('prepare_registration', $names)['structuredContent'] ?? [];
    $check('prepare_registration saves nothing', ($preview['status'] ?? '') === 'preview' && $dancers($eventId) === 0);
    $check('a name is required', ($tool('prepare_registration', ['firstname' => ' '])['isError'] ?? false) === true);
    $check('a wrong token is refused', ($tool('confirm_registration', ['confirmation_token' => 'x'])['isError'] ?? false) === true);
    $token = ['confirmation_token' => $preview['confirmation_token'] ?? ''];
    $confirmed = $tool('confirm_registration', $token)['structuredContent'] ?? [];
    $check('confirm_registration signs up', ($confirmed['status'] ?? '') === 'confirmed' && $dancers($eventId) === 1);
    $check('with the accents', ($pdo->query("SELECT firstname FROM dancers WHERE event_id = $eventId")->fetchColumn()) === 'Zoé');
    $check('the same token signs up once', ($tool('confirm_registration', $token)['structuredContent'] ?? []) === $confirmed && $dancers($eventId) === 1);

    // As on the form, nothing shows whether a name was on the list
    $signUp = function (string $firstname, string $lastname) use ($tool) : array {
        $preview = $tool('prepare_registration', compact('firstname', 'lastname'))['structuredContent'] ?? [];
        return $tool('confirm_registration', ['confirmation_token' => $preview['confirmation_token'] ?? ''])['structuredContent'] ?? [];
    };
    $withoutName = fn (array $answer) => array_diff_key($answer, ['participant' => 0]);
    $known = $signUp('ZOÉ', 'test');
    $check('a name already on the list is added once', ($known['status'] ?? '') === 'confirmed' && $dancers($eventId) === 1);
    $check('and looks like a new one', $withoutName($known) === $withoutName($signUp('Nouveau', 'Test')) && $dancers($eventId) === 2);

    // Nothing kept but the door list
    $check('no way to cancel', !isset($confirmed['registration_id']) && ($rpc('tools/call', ['name' => 'cancel_registration', 'arguments' => []])[0] === 400));

    // Names and tokens that aren't right
    $check('no control characters in a name', ($tool('prepare_registration', ['firstname' => "A\nB", 'lastname' => 'Test'])['isError'] ?? false) === true);
    $check('no name over 100 characters', ($tool('prepare_registration', ['firstname' => str_repeat('é', 101), 'lastname' => 'Test'])['isError'] ?? false) === true);
    $data = json_decode(base64_decode(strtr(substr($token['confirmation_token'], 4), '-_', '+/')), true);
    $forge = fn (array $changes) => 'rf1.' . rtrim(strtr(base64_encode(json_encode($changes + $data)), '+/', '-_'), '=');
    $check('an expired token is refused', ($tool('confirm_registration', ['confirmation_token' => $forge(['expires' => time() - 1])])['isError'] ?? false) === true);
    $check('a token isn\'t a way around the name rules', ($tool('confirm_registration', ['confirmation_token' => $forge(['firstname' => ''])])['isError'] ?? false) === true);
    $check('nor around the dates', ($tool('confirm_registration', ['confirmation_token' => $forge(['event' => '2000-01-01'])])['isError'] ?? false) === true && $dancers($eventId) === 2);

    // Closed by the team, after the preview
    $late = $tool('prepare_registration', ['firstname' => 'Tard', 'lastname' => 'Test'])['structuredContent'] ?? [];
    $pdo->exec("UPDATE events SET closed = 1 WHERE id = $eventId");
    $check('closing refuses a preview made before', ($tool('confirm_registration', ['confirmation_token' => $late['confirmation_token'] ?? ''])['isError'] ?? false) === true && $dancers($eventId) === 2);
    $check('and new ones', ($tool('prepare_registration', $names)['isError'] ?? false) === true);
} finally {
    $pdo->exec("DELETE FROM events WHERE id = $eventId");
    if ($setAside) {
        $pdo->exec('UPDATE soirees SET cancelled = 0 WHERE id IN (' . implode(',', $setAside) . ')');
    }
}
exit($failed ? 1 : 0);
