<?php
/**
 * Compiles every template into tmp/views (Twig's cache), for bin/deploy.sh to upload: since 2026-11-14, PHP on
 * OVH's hosting dies (without any error) while Twig compiles a template, which made every page a 500. With the
 * compiled templates uploaded, the server only runs them.
 *
 * Run from the project's folder, as on the server (www/index.php moves there): Twig names a compiled template
 * after the template's path from the current folder, PHP's major.minor version, Twig's version and its extensions,
 * so the server finds the ones made here. Needs the database, as some Twig extensions read it.
 */

if (php_sapi_name() !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
chdir($root);
putenv('ENV=production');
require $root . '/www/index.php';
// The app that index.php sets up (it only runs it outside the command line)
$app = $GLOBALS['app'] ?? null;
if (!$app instanceof Framework\App) {
    fwrite(STDERR, "www/index.php didn't set up the app\n");
    exit(1);
}
$container = $app->getContainer();

// The modules add their templates' folders when they start (App::run)
foreach (Closure::bind(fn () => $this->modules, $app, Framework\App::class)() as $module) {
    $container->get($module);
}

/** @var Framework\Renderer\TwigRenderer $renderer */
$renderer = $container->get(Framework\Renderer\RendererInterface::class);
$twig = Closure::bind(fn () => $this->twig, $renderer, Framework\Renderer\TwigRenderer::class)();
/** @var Twig\Loader\FilesystemLoader $loader */
$loader = $twig->getLoader();

$compiled = 0;
$skipped = [];
foreach ($loader->getNamespaces() as $namespace) {
    foreach ($loader->getPaths($namespace) as $path) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $name = substr($file->getPathname(), strlen(rtrim($path, '/')) + 1);
            if ($namespace !== Twig\Loader\FilesystemLoader::MAIN_NAMESPACE) {
                $name = "@$namespace/$name";
            }
            try {
                $twig->load($name);
                $compiled++;
            } catch (Twig\Error\Error $e) {
                // Not a template (CSS or an image next to the templates)
                $skipped[] = $name;
            }
        }
    }
}
echo "$compiled templates compiled into tmp/views", $skipped ? ', not templates: ' . implode(', ', $skipped) : '', "\n";
