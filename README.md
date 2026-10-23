<a href="https://rockfever.be"><img src=".github/assets/banner.jpg" alt="Rock Fever: the students' rock dance nights in Louvain-la-Neuve, since 2015"></a>

<p align="center">
  <a href="https://rockfever.be"><img src="https://img.shields.io/badge/live-rockfever.be-072d55?style=flat-square" alt="Live site: rockfever.be"></a>
  <a href="https://github.com/rockfeverLLN/rockfever/actions/workflows/deploy.yml"><img src="https://github.com/rockfeverLLN/rockfever/actions/workflows/deploy.yml/badge.svg?branch=main" alt="Deploy to OVH"></a>
  <a href="https://github.com/rockfeverLLN/rockfever/actions/workflows/update-dependencies.yml"><img src="https://github.com/rockfeverLLN/rockfever/actions/workflows/update-dependencies.yml/badge.svg?branch=main" alt="Package updates"></a>
  <br>
  <img src="https://img.shields.io/badge/PHP-8.5-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/Twig-3-bacf29?style=flat-square&logo=symfony&logoColor=white" alt="Twig 3">
  <img src="https://img.shields.io/badge/MariaDB-MySQL-003545?style=flat-square&logo=mariadb&logoColor=white" alt="MariaDB / MySQL">
  <img src="https://img.shields.io/badge/hosted%20on-OVH-123f6d?style=flat-square&logo=ovh&logoColor=white" alt="Hosted on OVH">
</p>

# Rock Fever

The website of **Rock Fever**, the student ASBL that has been running rock (and waltz) dance nights, the soirées, at the Hall Sainte-Barbe in Louvain-la-Neuve since 2015, open to anyone aged 18 to 35. On the site, dancers find the next soirée and sign up for it, propose songs and get the photos; the team runs everything from its admin.

PHP 8.5, Twig 3, PHP-DI 7, Mezzio router, on OVH web hosting. Every push to `main` goes live.

<p align="center">
  <img src=".github/assets/phones.webp" alt="The home page, the calendar and the photo gallery on a phone" width="900">
</p>

<details>
<summary><b>On a computer</b></summary>
<br>
<img src=".github/assets/desktop.jpg" alt="The home page of rockfever.be on a computer">
</details>

### What's on the site

| Page | For | What it does |
| --- | --- | --- |
| [`/`](https://rockfever.be) | Everyone | Who we are, the next soirée, the coming dates and the staff |
| [`/calendrier`](https://rockfever.be/calendrier) | Everyone | Every soirée of the quadrimestre, to add to a calendar in one click (`.ics` feed) |
| [`/event`](https://rockfever.be/event) | Dancers | Sign up for the next soirée (3 € instead of 4 € at the door), from a week before |
| [`/photos`](https://rockfever.be/photos) | Dancers | The soirées' photo albums, each behind its own password |
| [`/music`](https://rockfever.be/music) | Dancers | Propose a song for the soirées |
| [`/contact`](https://rockfever.be/contact) | Everyone | Write to the team |
| `/admin` | The team | Events and the sign-up list at the door, home page sections, songs, photos, users |

### Contents

[Layout](#layout) · [Run it locally](#run-it-locally) · [Calendar](#calendar) · [Home page](#home-page) · [Photos](#photos) · [Page speed](#page-speed) · [Search engines and AI agents](#search-engines-and-ai-agents) · [Deploy](#deploy) · [Package updates](#package-updates)

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

`/calendrier` lists the dates of the soirées, with buttons to add one or all of them to a calendar app. The dates, times, place and prices are in `calendar.soirees` and the lines under it in [`src/Main/config.php`](src/Main/config.php): add the next semester's dates there, and give a soirée that won't happen `'cancelled' => true` rather than removing it. `/calendrier.ics` serves them all, so calendars that subscribed to it get the new dates on their own.

**One source for every soirée:** [`Calendar`](src/Main/Calendar.php) builds each soirée from its date in the config and its event in the admin (same day): the admin's title (its theme), description, waltz and rock times and place win when they're set, the config gives the rest (`calendar.start` and `calendar.rock` are the usual waltz and rock times, so every soirée has both). Before the team publishes it, a soirée's `themeNote` says when its theme will be announced, so agents read the date, times, place and prices as confirmed rather than guesses. The home page, `/calendrier`, `/event` and its form, the `.ics` files, the schema.org markup, the sitemap and `/api/events` all read these records, so they can't disagree. Each soirée has its own page, `/calendrier/2026-11-11`, its canonical URL. The admin's "Prix" field only shows for old events: listed and coming soirées show the config's prices.

The same dates feed the home page, so nothing there needs editing when a soirée is over:

- **Sign-ups open on their own** at midnight, 7 days before the soirée (`Event::REGISTRATION_DAYS`) but not before the previous soirée is over, once its event exists in admin > Événements: the team can create it as early as it likes. They **close when the soirée starts**, and `/event` then refuses sign-ups. Only the next soirée can have them open. Each soirée's state is `open`, `upcoming`, `pending` (opening day reached, no event in the admin yet), `closed` or `cancelled`. The lock in the admin still closes them by hand (and reopens them), and the dashboard says whether they're open, closed or when they open. Before the opening, `/event` shows the soirée without the form and refuses sign-ups.
- **Next soirée card** under the header of the home page, which follows the sign-ups: "Inscris-toi à la soirée du …" while they're open; before that, "Les inscriptions ne sont pas encore ouvertes, mais voici la prochaine soirée : Mercredi 11 novembre à 20h30", with the opening day (to `/event` if the event exists, else `/calendrier`); "Prochaine soirée : …" once they're closed. Past soirées never show. On `/calendrier`, the card only appears while sign-ups are open, and the next soirée says when they open.
- **Date list:** a paragraph containing only `[dates]` in an "À propos" section (the "Dates de nos soirées" one) is replaced by the coming soirées, grouped by quadrimestre, with the next one marked. When they're all past, it says the next dates are coming. Don't type dates by hand in that section.

## Home page

The home page shows the sections of the admin's "À propos" in order. The sections after the one titled **Le Staff** each present a member: they are shown side by side as cards (photo, name, then text), so keep that title, or change it in [`HomeAction`](src/Main/Actions/HomeAction.php). In a member's text, the first paragraph with a photo becomes the top of the card. Sections with no title and nothing visible (a commented-out video) are skipped.

Text written in the admin (these sections, the events' title and description) goes through the `typography` Twig filter ([`TextExtension`](src/Framework/Twig/TextExtension.php)), so it follows French typography whatever was typed: no-break spaces before `:` and inside `« »`, narrow ones before `; ! ?`, and `’` apostrophes. Use it on any new admin text (`|e|typography` for plain text).

**Emojis:** the production tables were created in `utf8` (utf8mb3), which saves emojis as `?`. [`src/Main/db/fix-home-copy.sql`](src/Main/db/fix-home-copy.sql) converts `aboutSections` and `events` to utf8mb4, puts `[dates]` in the dates section and fixes the spelling of the home page. Run it once in phpMyAdmin after deploying; running it again changes nothing.

## Photos

`/photos` is a gallery of the soirées' photos, each album behind its soirée's password (the [`Photos`](src/Photos) module). It's dark, like a photographer's gallery: `/photos` lists the albums by collection, and each album (`/photos/semaine-5-26-27`) has a breadcrumb, its photographer, a button to download it all as a .zip, tabs, and the photos in columns, uncropped.

- **Albums:** one per soirée ("Semaine 5 26-27"), in a collection (the quadrimestre: "Q1-26-27"), with an optional photographer, linked to its event. They're listed by their soirée's date, newest first (albums without a soirée, by when they were made), and albums without photos aren't shown. The cover is the first photo, or the one picked with ☆ on the album's admin page. When uploading, an optional **tab** ("Photobooth") puts the photos in a tab of the album, next to "Toutes les photos".

- **Passwords:** each event has a "Mot de passe des photos" (admin > Événements), given to its dancers: it opens the album linked to that event, and only that one. It's kept readable so the team can find it again, and typed case-insensitively. Visitors who give it get a cookie for that album for 30 days; changing it locks out everyone who used the old one. Albums without an event, or whose event has no password, are only for the team (logged in) and aren't listed. Until an album is opened, `/photos` shows a lock in place of its cover. Five wrong passwords from one address in 10 minutes block the form there for a while (counted in `tmp/throttle/`, like the logins: five in 15 minutes). The pages are `noindex` and `Disallow`ed in `robots.txt`.
- **Upload and delete:** on `/admin/photos`, for everyone logged in: make an album, then drop the photos on its page: the browser makes them smaller (2400px) and sends them one by one, so the server's upload limit isn't a problem. The server keeps 480, 960 and 1600px WebP copies for the grid and the viewer, and saves photos again without their EXIF data (GPS position).
- **Storage:** `storage/photos/`, outside the web root and not in git, so the files are only sent by [`PhotoFileAction`](src/Photos/Actions/PhotoFileAction.php) to visitors with the password. `bin/deploy.sh` never touches that folder: back it up with the database.
- **Photographers:** the **Photographe** login (pick it on `/login`) only opens `/admin/photos`: albums, uploads and deletes. Every other admin page sends it back there, and it never shows the soirées' passwords or the deletion requests. Logged in, photographers see every album on `/photos` without a password. Give it a password, and change it, in Paramètres > Utilisateurs: until then nobody can log in with it. The role comes from a migration; on OVH, run [`src/Photos/db/add-photographer-role.sql`](src/Photos/db/add-photographer-role.sql) in phpMyAdmin.
- **Deletion requests:** in the viewer, "Demander la suppression" sends a name, an optional email and a message. The team gets an email at `CONTACT_EMAIL`, a card on the dashboard, and the request on `/admin/photos`, where it can delete the photo or ignore the request.
- **Database:** the tables (and the events' `photos_password` column) come from a Phinx migration. With no SSH on OVH, run [`src/Photos/db/create-photos-tables.sql`](src/Photos/db/create-photos-tables.sql) once in phpMyAdmin before deploying.

## Page speed

- No CSS or JavaScript framework. `www/css/base.css` holds the base styles (grid, helpers, colors, cards, buttons, top bar, side menu, forms, tables): the rules of Materialize 1.0 (MIT, its project stopped in 2018) that the public pages and the admin use, kept readable and now ours to change; `style.css` comes after it with the site's own design. `www/js/script.js` drives the widgets with plain JavaScript and the classes Materialize's CSS styles: side menu, the admin's dropdown, parallax headers, tooltips, modals (`<dialog class="modal">`, opened by a `.modal-trigger` link to them), collapsibles, card reveals, floating labels and growing text areas. Dates and times use the browser's own pickers (`field(…, {type: 'date'})`), selects are native. `www/js/guests.js` runs the sign-up list at the door. There's no jQuery: write new scripts the same way, without inline `onclick=` attributes.
- `views/critical.css` holds the CSS rules the public pages need for their first paint: `layout.twig` inlines it and loads the full stylesheet in the background. That stylesheet, `www/css/site.css`, is the part of `base.css` the public pages use (plus the classes `script.js` adds) followed by `style.css`, in one file so both apply at once (about 37 KB, against 94 KB for both); the admin loads all of `base.css`. Both files are built by `bin/critical-css.php` at every deploy, from this commit's pages on a test database and from the live pages, so they're not in git. Without them (a fresh checkout), the pages load all of `base.css` and `style.css` the usual way. To build them locally: `php bin/critical-css.php https://www.rockfever.be http://127.0.0.1:8000`. A class of `base.css` that no page used when it was made isn't in it: copy the rule from Materialize 1.0.0's `materialize.css` if a page needs it.
- `script.js` starts loading once the browser reports the first paint, so on slow connections it doesn't delay it (the menu and forms work a moment later).
- CSS and JS URLs carry a version made from the file (`asset()` in `layout.twig`, [`AssetExtension`](src/Framework/Twig/AssetExtension.php)), so `www/.htaccess` lets browsers keep them for a year: a changed file gets a new URL.
- `www/fonts/material-icons.woff2` and `www/fonts/mdi.woff2` only contain the Material Icons and Material Design Icons (`mdi mdi-…`) used in the templates. After using a new icon, run `bin/subset-icons.sh` (needs `brew install fonttools`): it rebuilds both fonts, the `mdi-…` rules at the end of `www/css/style.css`, and the `?v=` of the font URLs.
- The public pages use Inter, self-hosted in `www/fonts/inter.woff2` (Latin characters, one variable file for every weight, license in `inter-LICENSE.txt`). `layout.twig` preloads it; if you replace the file, change its `?v=` there and in `www/css/style.css`.
- Photos uploaded in the admin are scaled down to 1600px, and smaller WebP copies (320 to 1280px) are saved in `www/images/about/sizes/`. The `lazyImages` Twig filter offers them in a `srcset` for images that have a `width`. For photos uploaded before that, run `php bin/resize-images.php`.
- The Google tag (`G-MKKLSTRX4W`) is Google's standard snippet at the top of `<head>`, loaded `async` so it doesn't block the page, and in cookieless mode (Consent Mode denied), so no consent banner is needed.

## Search engines and AI agents

- `www/robots.txt` lets every crawler in, AI training included (`Content-Signal: ai-train=yes`), except `/admin` and `/login`.
- `www/llms.txt` sums up the site and its pages for language models, and `www/AGENTS.md` tells agents what they may do (no form sent without the person's request). Update them when pages change.
- Every page answers in Markdown (its `<main>` only) when asked with `Accept: text/markdown`: [`MarkdownMiddleware`](src/Framework/Middleware/MarkdownMiddleware.php).
- `layout.twig` marks up Rock Fever as a schema.org `Organization`. The home and calendar pages list the coming soirées as `DanceEvent`s, and `/event` and each soirée's page mark up theirs, all from `Calendar::toSchema` (dates with their offset, address, both prices with the sign-up's availability, `EventCancelled` for a cancelled one).
- `/api/events` gives every soirée as JSON, past and to come (`Calendar::toApi`), with `next`, `nextSummary` (the next soirée in one sentence) and `suggestions` (what an assistant can offer next: add it to a calendar, a reminder when its sign-ups open, subscribe to every date; `Calendar::toSuggestions`), each one's `summary`, `theme` and `themeNote`, sign-up state, opening and closing times, and the fields the form needs.
- `/sitemap.xml` is built by [`SitemapAction`](src/Main/Actions/SitemapAction.php): the public pages and each soirée's page.
- `www/.htaccess` sends the security headers (HSTS, CSP, nosniff…) on every response.

## Deploy

Every push to `main` runs [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml). It installs the Composer dependencies, checks PHP syntax, runs PHPStan and `bin/smoke-test.sh` on a copy of the site with a test database, and only then uploads the project over SFTP to the OVH hosting root: a push that fails a check doesn't go live. It needs the `FTP_PASSWORD` repository secret.

The PHP version on OVH is set in [`.ovhconfig`](.ovhconfig).

## Package updates

Updates install themselves within a day of their release, major versions included, with no one needing to do anything. Every night, [`.github/workflows/update-dependencies.yml`](.github/workflows/update-dependencies.yml) looks for new versions of the Composer packages (for the PHP version in `.ovhconfig`) and of the JavaScript libraries (`bin/update-js.sh`: TinyMCE, its French translation, clipboard.js and Masonry, loaded from jsDelivr; the site's own scripts and styles use no library, so there's no jQuery or Materialize to update). When there's one, `bin/auto-update.sh` tests it on a copy of the site before it goes live: PHP syntax, PHPStan, every main page answering (`bin/smoke-test.sh`) and their scripts working in Chrome (`bin/browser-test.mjs`: the side menu, dropdown, tooltips, collapsibles, modals, the date fields, the list at the door, TinyMCE, the gallery). Minor and patch releases are tested together, each major version on its own. What passes is committed and deployed; what fails stays on the previous version and is listed in an "Automatic package update failed" issue, then tried again when a newer version comes out. You can also start it by hand from the Actions tab.

To run the same checks locally: `composer migrate && composer seed`, `composer serve`, then in another terminal `bin/smoke-test.sh`, `node --experimental-websocket bin/browser-test.mjs` (Node 20; plain `node` from 22) and `composer analyse`.
