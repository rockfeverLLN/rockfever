# Rock Fever

Website of Rock Fever: https://www.rockfever.be

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

## Deploy

Every push to `main` runs [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml). It installs the Composer dependencies, checks PHP syntax, and uploads the project over SFTP to the OVH hosting root. It needs the `FTP_PASSWORD` repository secret.

The PHP version on OVH is set in [`.ovhconfig`](.ovhconfig).
