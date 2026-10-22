# Rock Fever

Website of Rock Fever: https://rockfever.be

PHP 8.5, Twig 3, PHP-DI 7, Mezzio router. Hosted on OVH web hosting.

## Layout

| Path | What it is |
| --- | --- |
| `www/` | Web root (`index.php`, CSS, JS, images) |
| `src/` | Application modules (`Main`, `Events`, `Auth`, `Admin`, `Blog`) and the small `Framework` |
| `views/` | Shared Twig layouts |
| `config/` | App config. `database.php` holds the DB credentials and is not in git |
| `src/*/db/` | Phinx migrations and seeds |

## Run it locally

```bash
brew install php composer mariadb
composer install
cp config/database.example.php config/database.php   # then fill in a local database
composer migrate
composer seed
composer serve                                       # http://127.0.0.1:8000
```

`composer serve` runs with `ENV=dev`, which shows errors with Whoops. Without `ENV`, the app runs in production mode and caches templates in `tmp/`.

## Calendar

`/calendrier` lists the dates of the soirées, with buttons to add one or all of them to a calendar app. The dates, times and place are in `calendar.soirees` and the lines under it in [`src/Main/config.php`](src/Main/config.php): add the next semester's dates there. `/calendrier.ics` serves them all, so calendars that subscribed to it get the new dates on their own.

## Home page

The home page shows the sections of the admin's "À propos" in order. The sections after the one titled **Le Staff** each present a member: they are shown side by side as cards (photo, name, then text), so keep that title, or change it in [`HomeAction`](src/Main/Actions/HomeAction.php). In a member's text, the first paragraph with a photo becomes the top of the card. Sections with no title and nothing visible (a commented-out video) are skipped.

## Photos

`/photos` is a gallery of the soirées' photos, behind one shared password (the [`Photos`](src/Photos) module).

- **Password:** set and changed on `/admin/photos`. Visitors who give it get a cookie for 30 days; changing it locks out everyone who used the old one. Until one is set, only the team (logged in) sees the photos. Five wrong passwords in 10 minutes block the form for a while. The page is `noindex` and `Disallow`ed in `robots.txt`.
- **Upload and delete:** on `/admin/photos`, for everyone logged in. Drop the photos on the page: the browser makes them smaller (2400px) and sends them one by one, so the server's upload limit isn't a problem. The server keeps 480, 960 and 1600px WebP copies for the grid and the viewer, and saves photos again without their EXIF data (GPS position).
- **Storage:** `storage/photos/`, outside the web root and not in git, so the files are only sent by [`PhotoFileAction`](src/Photos/Actions/PhotoFileAction.php) to visitors with the password. `bin/deploy.sh` never touches that folder: back it up with the database.
- **Deletion requests:** in the viewer, "Demander la suppression" sends a name, an optional email and a message. The team gets an email at `CONTACT_EMAIL`, a card on the dashboard, and the request on `/admin/photos`, where it can delete the photo or ignore the request.
- **Database:** the tables come from a Phinx migration. With no SSH on OVH, run [`src/Photos/db/create-photos-tables.sql`](src/Photos/db/create-photos-tables.sql) once in phpMyAdmin before deploying.

## Page speed

- `views/critical.css` holds the CSS rules the public pages need for their first paint: `layout.twig` inlines it and loads the full stylesheet in the background. That stylesheet, `www/css/site.css`, is the part of Materialize the public pages use (plus the classes its scripts add) followed by `style.css`, in one file so both apply at once; the admin keeps all of Materialize. Both files are built by `bin/critical-css.php` at every deploy, from this commit's pages on a test database and from the live pages, so they're not in git. Without them (a fresh checkout), the pages load all of Materialize and `style.css` the usual way. To build them locally: `php bin/critical-css.php https://www.rockfever.be http://127.0.0.1:8000`.
- jQuery, Materialize's script and `script.js` start loading once the browser reports the first paint, so on slow connections they don't delay it (the menu and forms work a moment later).
- CSS and JS URLs carry a version made from the file (`asset()` in `layout.twig`, [`AssetExtension`](src/Framework/Twig/AssetExtension.php)), so `www/.htaccess` lets browsers keep them for a year: a changed file gets a new URL.
- `www/fonts/material-icons.woff2` and `www/fonts/mdi.woff2` only contain the Material Icons and Material Design Icons (`mdi mdi-…`) used in the templates. After using a new icon, run `bin/subset-icons.sh` (needs `brew install fonttools`): it rebuilds both fonts, the `mdi-…` rules at the end of `www/css/style.css`, and the `?v=` of the font URLs.
- The public pages use Inter, self-hosted in `www/fonts/inter.woff2` (Latin characters, one variable file for every weight, license in `inter-LICENSE.txt`). `layout.twig` preloads it; if you replace the file, change its `?v=` there and in `www/css/style.css`.
- Photos uploaded in the admin are scaled down to 1600px, and smaller WebP copies (320 to 1280px) are saved in `www/images/about/sizes/`. The `lazyImages` Twig filter offers them in a `srcset` for images that have a `width`. For photos uploaded before that, run `php bin/resize-images.php`.
- The Google tag (`G-MKKLSTRX4W`) is Google's standard snippet at the top of `<head>`, loaded `async` so it doesn't block the page, and in cookieless mode (Consent Mode denied), so no consent banner is needed.

## Search engines and AI agents

- `www/robots.txt` lets every crawler in, AI training included (`Content-Signal: ai-train=yes`), except `/admin` and `/login`.
- `www/llms.txt` sums up the site and its pages for language models, and `www/AGENTS.md` tells agents what they may do (no form sent without the person's request). Update them when pages change.
- Every page answers in Markdown (its `<main>` only) when asked with `Accept: text/markdown`: [`MarkdownMiddleware`](src/Framework/Middleware/MarkdownMiddleware.php).
- `layout.twig` marks up Rock Fever as a schema.org `Organization`. The home and calendar pages list the coming soirées as `DanceEvent`s (`@main/soireesJsonLd.twig`), and `/event` marks up the next event.
- `www/.htaccess` sends the security headers (HSTS, CSP, nosniff…) on every response.

## Deploy

Every push to `main` runs [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml). It installs the Composer dependencies, checks PHP syntax, and uploads the project over SFTP to the OVH hosting root. It needs the `FTP_PASSWORD` repository secret.

The PHP version on OVH is set in [`.ovhconfig`](.ovhconfig).

## Package updates

Every Monday, [`.github/workflows/update-dependencies.yml`](.github/workflows/update-dependencies.yml) installs the newest minor and patch versions of the Composer packages, for the PHP version in `.ovhconfig`. It starts the site on a fresh database, loads the main pages with `bin/smoke-test.sh`, and only if they all work, commits `composer.lock` and deploys. New major versions can need code changes, so they are only listed in a "Breaking package updates available" issue. You can also start it by hand from the Actions tab.

To run the same page check locally: `composer migrate && composer seed`, `composer serve`, then `bin/smoke-test.sh` in another terminal.
