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

## Page speed

- `views/critical.css` holds the CSS rules the public pages need for their first paint. `layout.twig` inlines it and loads the full stylesheets in the background. After changing `www/css/` or the classes of the public templates, rebuild it with `php bin/critical-css.php` (reads the live pages; add a saved page such as `event.html` when the event page is closed).
- `www/fonts/material-icons.woff2` and `www/fonts/mdi.woff2` only contain the Material Icons and Material Design Icons (`mdi mdi-…`) used in the templates. After using a new icon, run `bin/subset-icons.sh` (needs `brew install fonttools`): it rebuilds both fonts, the `mdi-…` rules at the end of `www/css/style.css`, and the `?v=` of the font URLs.
- The public pages use Inter, self-hosted in `www/fonts/inter.woff2` (Latin characters, one variable file for every weight, license in `inter-LICENSE.txt`). `layout.twig` preloads it; if you replace the file, change its `?v=` there and in `www/css/style.css`.
- Photos uploaded in the admin are scaled down to 1600px, and smaller WebP copies (320 to 1280px) are saved in `www/images/about/sizes/`. The `lazyImages` Twig filter offers them in a `srcset` for images that have a `width`. For photos uploaded before that, run `php bin/resize-images.php`.
- Google Analytics loads after the page's `load` event so it doesn't compete with the page itself.

## Deploy

Every push to `main` runs [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml). It installs the Composer dependencies, checks PHP syntax, and uploads the project over SFTP to the OVH hosting root. It needs the `FTP_PASSWORD` repository secret.

The PHP version on OVH is set in [`.ovhconfig`](.ovhconfig).

## Package updates

Every Monday, [`.github/workflows/update-dependencies.yml`](.github/workflows/update-dependencies.yml) installs the newest minor and patch versions of the Composer packages, for the PHP version in `.ovhconfig`. It starts the site on a fresh database, loads the main pages with `bin/smoke-test.sh`, and only if they all work, commits `composer.lock` and deploys. New major versions can need code changes, so they are only listed in a "Breaking package updates available" issue. You can also start it by hand from the Actions tab.

To run the same page check locally: `composer migrate && composer seed`, `composer serve`, then `bin/smoke-test.sh` in another terminal.
