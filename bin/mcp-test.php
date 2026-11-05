<?php
/**
 * Signs up through /mcp from start to finish on a running site: writes to the database, so only on a test one.
 * Run by bin/test.sh. Adds a soirée for tomorrow, and removes it with all its sign-ups at the end.
 *
 * Usage: php bin/mcp-test.php [base-url]   (default http://127.0.0.1:8000)
 */
chdir(dirname(__DIR__));
$config = require 'config/database.php';
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

try {
    // The protocol
    [, $answer] = $post(['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
    $check('initialize, for older clients', ($answer['result']['protocolVersion'] ?? '') === '2025-06-18');
    [$status] = $post(['method' => 'notifications/initialized']);
    $check('a notification gets 202', $status === 202);
    [, $answer] = $post(['id' => 1, 'method' => 'tools/list']);
    $check('tools/list without a version', count($answer['result']['tools'] ?? []) === 6);
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
    $again = $tool('confirm_registration', $token)['structuredContent'] ?? [];
    $check('the same token signs up once', ($again['registration_id'] ?? '') === $confirmed['registration_id'] && $dancers($eventId) === 1);
    $id = ['registration_id' => $confirmed['registration_id'] ?? ''];
    $check('get_registration', ($tool('get_registration', $id)['structuredContent']['cancellable'] ?? false) === true);
    $check('a wrong id is refused', ($tool('get_registration', ['registration_id' => str_repeat('0', 32)])['isError'] ?? false) === true);

    // Signing someone up again must neither give a way to remove them, nor show that they were on the list
    $signUp = function (string $firstname, string $lastname) use ($tool) : array {
        $preview = $tool('prepare_registration', compact('firstname', 'lastname'))['structuredContent'] ?? [];
        return $tool('confirm_registration', ['confirmation_token' => $preview['confirmation_token'] ?? ''])['structuredContent'] ?? [];
    };
    // What's the same for everyone, once the name and the reference are taken out
    $shape = fn (array $answer) => array_diff_key($answer, ['participant' => 0, 'registration_id' => 0]);
    $second = $signUp('ZOÉ', 'test');
    $third = $signUp('Zoé', 'TEST');
    $check('a name already on the list is added once', ($second['status'] ?? '') === 'confirmed' && $dancers($eventId) === 1);
    $fresh = $signUp('Nouveau', 'Test');
    $check('and looks like a new one', $shape($second) === $shape($fresh) && $dancers($eventId) === 2);
    $againId = ['registration_id' => $second['registration_id'] ?? ''];
    $freshId = ['registration_id' => $fresh['registration_id'] ?? ''];
    $check('also when read', $shape($tool('get_registration', $againId)['structuredContent'] ?? []) === $shape($tool('get_registration', $freshId)['structuredContent'] ?? []));
    $doubled = $tool('cancel_registration', $againId)['structuredContent'] ?? [];
    $check('cancelling it leaves the dancer', ($doubled['status'] ?? '') === 'cancelled' && $dancers($eventId) === 2);
    $cancelled = $tool('cancel_registration', $freshId)['structuredContent'] ?? [];
    $check('and looks like any other cancelling', $shape($doubled) === $shape($cancelled) && $dancers($eventId) === 1);

    // At the door
    $pdo->exec("UPDATE dancers SET status = 1 WHERE event_id = $eventId");
    $check('not once arrived', ($tool('cancel_registration', $id)['isError'] ?? false) === true && $dancers($eventId) === 1);
    $pdo->exec("UPDATE dancers SET status = 0 WHERE event_id = $eventId");

    $check('cancel_registration', ($tool('cancel_registration', $id)['structuredContent']['status'] ?? '') === 'cancelled' && $dancers($eventId) === 0);
    // Cancelling the first sign-up can't be used to learn what a later one found
    $check('a sign-up that added nothing stays the same', ($tool('get_registration', ['registration_id' => $third['registration_id'] ?? ''])['structuredContent']['status'] ?? '') === 'confirmed');
    $check('cancelling twice is fine', ($tool('cancel_registration', $id)['structuredContent']['status'] ?? '') === 'cancelled');
    $check('its token doesn\'t sign up again', ($tool('confirm_registration', $token)['structuredContent']['status'] ?? '') === 'cancelled' && $dancers($eventId) === 0);
    $check('the log keeps it', (int) $pdo->query("SELECT COUNT(*) FROM agent_registrations WHERE event_id = $eventId AND client = 'mcp-test'")->fetchColumn() === 4);

    // Removed by the team
    $removed = $signUp('Retiré', 'Test');
    $pdo->exec("DELETE FROM dancers WHERE event_id = $eventId");
    $removedId = ['registration_id' => $removed['registration_id'] ?? ''];
    $check('a dancer the team removed', ($tool('get_registration', $removedId)['structuredContent']['status'] ?? '') === 'removed');
    $check('can\'t be cancelled', ($tool('cancel_registration', $removedId)['isError'] ?? false) === true);

    // Names and tokens that aren't right
    $check('no control characters in a name', ($tool('prepare_registration', ['firstname' => "A\nB", 'lastname' => 'Test'])['isError'] ?? false) === true);
    $check('no name over 100 characters', ($tool('prepare_registration', ['firstname' => str_repeat('é', 101), 'lastname' => 'Test'])['isError'] ?? false) === true);
    $data = json_decode(base64_decode(strtr(substr($token['confirmation_token'], 4), '-_', '+/')), true);
    $forge = fn (array $changes) => 'rf1.' . rtrim(strtr(base64_encode(json_encode($changes + $data)), '+/', '-_'), '=');
    $nonce = fn () => bin2hex(random_bytes(16));
    $check('an expired token is refused', ($tool('confirm_registration', ['confirmation_token' => $forge(['expires' => time() - 1, 'nonce' => $nonce()])])['isError'] ?? false) === true);
    $check('a token isn\'t a way around the name rules', ($tool('confirm_registration', ['confirmation_token' => $forge(['firstname' => '', 'nonce' => $nonce()])])['isError'] ?? false) === true);
    $check('nor around the dates', ($tool('confirm_registration', ['confirmation_token' => $forge(['event' => '2000-01-01', 'nonce' => $nonce()])])['isError'] ?? false) === true && $dancers($eventId) === 0);

    // Closed by the team, after the preview
    $late = $tool('prepare_registration', $names)['structuredContent'] ?? [];
    $pdo->exec("UPDATE events SET closed = 1 WHERE id = $eventId");
    $check('closing refuses a preview made before', ($tool('confirm_registration', ['confirmation_token' => $late['confirmation_token'] ?? ''])['isError'] ?? false) === true && $dancers($eventId) === 0);
    $check('and new ones', ($tool('prepare_registration', $names)['isError'] ?? false) === true);
} finally {
    $pdo->exec("DELETE FROM events WHERE id = $eventId");
}
exit($failed ? 1 : 0);
