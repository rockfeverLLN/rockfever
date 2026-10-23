<?php
// Builds views/critical.css: the rules of base.css and style.css that the public pages use.
// layout.twig inlines it so the first paint doesn't wait for the stylesheets, which then load in the background.
// Also builds www/css/site.css, the one stylesheet those pages load: the part of base.css they use (with the classes
// script.js adds on them: side menu, form fields), then all of style.css. One file, so both apply at the same moment:
// loaded apart, base.css alone briefly overrode the first-paint rules and moved the page.
// Run it again after changing the CSS or the classes used in the public templates:
//
//   php bin/critical-css.php                                     # pages of https://www.rockfever.be
//   php bin/critical-css.php https://www.rockfever.be http://127.0.0.1:8000
//   php bin/critical-css.php https://www.rockfever.be event.html  # add a saved page, e.g. /event while one is open
//
// Needs PHP 8.4+ (Dom\HTMLDocument::querySelector).

$root = dirname(__DIR__);
$bases = array_slice($argv, 1) ?: ['https://www.rockfever.be'];
if (!array_filter($bases, fn ($base) => !is_file($base))) {
    array_unshift($bases, 'https://www.rockfever.be');
}
$paths = ['/', '/event', '/calendrier', '/music', '/contact', '/legal', '/login', '/page-not-found'];
// Classes that only exist once script.js runs (the side menu and its overlay, the labels of text fields), and the
// helpers of form fields
$scriptClasses = '/sidenav-overlay|\.open\b|\.active\b|\.valid\b|\.invalid\b|helper-text|\.selected\b'
    . '|\.disabled\b|optgroup/';
$stylesheets = ["$root/www/css/base.css", "$root/www/css/style.css"];

$context = stream_context_create(['http' => [
    'ignore_errors' => true,
    // The host's bot filter refuses unknown user agents
    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/150 Safari/537.36',
]]);
$documents = [];
foreach ($bases as $base) {
    if (is_file($base)) {
        $documents[] = Dom\HTMLDocument::createFromString(file_get_contents($base), LIBXML_NOERROR);
        continue;
    }
    foreach ($paths as $path) {
        $html = file_get_contents(rtrim($base, '/') . $path, false, $context);
        if ($html === false) {
            fwrite(STDERR, "Could not fetch $base$path\n");
            exit(1);
        }
        $documents[] = $document = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        // The page of one soirée (/calendrier/2026-11-11), whose date changes: the first one /calendrier links to
        $soiree = $path === '/calendrier' ? $document->querySelector('a.soiree-link')?->getAttribute('href') : null;
        if ($soiree && ($html = file_get_contents(rtrim($base, '/') . $soiree, false, $context)) !== false) {
            $documents[] = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        }
    }
}

/**
 * Splits CSS into rules ['selector', 'body'] and blocks ['at', 'prelude', 'children'|'body']
 */
function parseCss(string $css): array
{
    $items = [];
    $length = strlen($css);
    $i = 0;
    while ($i < $length) {
        $open = strpos($css, '{', $i);
        $semicolon = strpos($css, ';', $i);
        if ($open === false) {
            break;
        }
        // Statements without a block (@charset, @import)
        if ($semicolon !== false && $semicolon < $open && ltrim(substr($css, $i, $semicolon - $i))[0] === '@') {
            $i = $semicolon + 1;
            continue;
        }
        $prelude = trim(substr($css, $i, $open - $i));
        $depth = 1;
        $j = $open + 1;
        $quote = null;
        for (; $j < $length && $depth > 0; $j++) {
            $char = $css[$j];
            if ($quote) {
                if ($char === '\\') {
                    $j++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
        }
        $body = substr($css, $open + 1, $j - $open - 2);
        if (preg_match('/^@(media|supports|starting-style)\b/i', $prelude)) {
            $items[] = ['at' => true, 'prelude' => $prelude, 'children' => parseCss($body)];
        } elseif ($prelude !== '' && $prelude[0] === '@') {
            $items[] = ['at' => true, 'prelude' => $prelude, 'body' => $body];
        } else {
            $items[] = ['selector' => $prelude, 'body' => $body];
        }
        $i = $j;
    }
    return $items;
}

function splitSelectors(string $selectors): array
{
    $parts = [];
    $depth = 0;
    $current = '';
    foreach (str_split($selectors) as $char) {
        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $parts[] = trim($current);
    return $parts;
}

function isUsed(string $selector, array $documents): bool
{
    // Pseudo-elements and states don't exist in a static page: match the element they belong to
    $static = preg_replace('/::?(before|after|placeholder|selection|first-line|first-letter|marker|backdrop|-webkit-[\w-]+|-moz-[\w-]+|-ms-[\w-]+)(\([^)]*\))?/i', '', $selector);
    $static = preg_replace('/:(hover|focus|focus-within|focus-visible|active|visited|link|checked|disabled|enabled|invalid|valid|required|optional|read-only|read-write|indeterminate|target|placeholder-shown|autofill)\b/i', '', $static);
    $static = trim($static);
    if ($static === '' || preg_match('/[>+~]$/', $static)) {
        $static .= '*';
    }
    foreach ($documents as $document) {
        try {
            if ($document->querySelector($static)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Selector the parser doesn't understand: keep it to be safe
            return true;
        }
    }
    return false;
}

function minify(string $css): string
{
    $css = preg_replace('/\s+/', ' ', $css);
    return trim(preg_replace('/\s*([{};])\s*/', '$1', $css));
}

// Declarations: also without the space after each property name (base.css is written to be read)
function minifyBody(string $css): string
{
    return preg_replace('/(^|;)([-\w]+)\s*:\s*/', '$1$2:', minify($css));
}

/**
 * @param string|null $keep selectors matching this pattern are kept even when no page uses them
 * @param bool $animations keep the @keyframes too (the first paint doesn't need them)
 */
function keepUsed(array $items, array $documents, ?string $keep = null, bool $animations = false): string
{
    $css = '';
    foreach ($items as $item) {
        if (isset($item['children'])) {
            $inner = keepUsed($item['children'], $documents, $keep, $animations);
            if ($inner !== '') {
                $css .= minify($item['prelude']) . '{' . $inner . '}';
            }
        } elseif (isset($item['at'])) {
            if (stripos($item['prelude'], '@font-face') === 0) {
                $css .= '@font-face{' . minifyBody($item['body']) . '}';
            } elseif ($animations && preg_match('/^@(-webkit-)?keyframes\b/i', $item['prelude'])) {
                $css .= minify($item['prelude']) . '{' . minifyBody($item['body']) . '}';
            }
        } else {
            $used = array_filter(
                splitSelectors($item['selector']),
                fn ($s) => ($keep && preg_match($keep, $s)) || isUsed($s, $documents)
            );
            if ($used) {
                $css .= minify(implode(',', $used)) . '{' . minifyBody($item['body']) . '}';
            }
        }
    }
    return $css;
}

$output = '';
foreach ($stylesheets as $file) {
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($file));
    $output .= keepUsed(parseCss($css), $documents);
}
file_put_contents("$root/views/critical.css", $output . "\n");
printf("views/critical.css: %d KB from %d pages\n", strlen($output) / 1024, count($documents));

$base = preg_replace('#/\*.*?\*/#s', '', file_get_contents("$root/www/css/base.css"));
$site = keepUsed(parseCss($base), $documents, $scriptClasses, true)
    . minify(preg_replace('#/\*.*?\*/#s', '', file_get_contents("$root/www/css/style.css")));
file_put_contents(
    "$root/www/css/site.css",
    "/* Built by bin/critical-css.php: the part of base.css (from Materialize, MIT, materializecss.com) the public pages use, then style.css */\n"
    . $site . "\n"
);
printf("www/css/site.css: %d KB (all of base.css and style.css: %d KB)\n", strlen($site) / 1024, (strlen($base) + filesize("$root/www/css/style.css")) / 1024);
