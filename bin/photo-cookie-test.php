<?php
// The album cookies of App\Photos\PhotoAccess: the one handed out opens the album, and nobody can work one out from a
// password they're guessing, which would skip the limit on wrong passwords of the album's form. Albums without a
// password need none. Run by bin/test.sh
use App\Photos\Entity\Album;
use App\Photos\PhotoAccess;
use Framework\Auth;
use Framework\Auth\Role;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

require dirname(__DIR__) . '/vendor/autoload.php';

$auth = new class implements Auth {
    public function getUser() : ?Role
    {
        return null;
    }
};
$access = new PhotoAccess($auth);
$album = new Album();
$album->id = 7;
$album->password = 'Swing';

$request = (new ServerRequest('GET', 'https://rockfever.be/photos'));
$header = $access->grant(new Response(), $request, $album)->getHeaderLine('Set-Cookie');
preg_match('/^' . PhotoAccess::COOKIE . '7=([0-9a-f]{64});/', $header, $match) || exit("No cookie in: $header\n");
$cookie = fn (string $value) => $request->withCookieParams([PhotoAccess::COOKIE . '7' => $value]);

$checks = [
    'the cookie handed out opens the album' => $access->canView($cookie($match[1]), $album),
    'no cookie doesn\'t' => !$access->canView($request, $album),
    'a cookie made from the password alone doesn\'t' =>
        !$access->canView($cookie(hash_hmac('sha256', 'rockfever-album-7', 'Swing')), $album),
];
$album->password = 'Valse';
$checks['changing the password locks out the old cookie'] = !$access->canView($cookie($match[1]), $album);

$album->password = null;
$checks['an album without a password opens without a cookie'] = $access->canView($request, $album);
$checks['nor hands one out'] = $access->grant(new Response(), $request, $album)->getHeaderLine('Set-Cookie') === '';
$checks['nor takes an empty password'] = !$access->checkPassword($album, '');

foreach ($checks as $check => $ok) {
    echo ($ok ? '  ok   ' : '  FAIL ') . $check . "\n";
}
exit(in_array(false, $checks, true) ? 1 : 0);
