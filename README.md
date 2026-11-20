# remember-the-milk-statistics-frontend

A server-rendered PHP page for the snapshots that
[remember-the-milk-statistics](../remember-the-milk-statistics) writes to SQLite. It shows:

- the latest open count and "completed today" count,
- a line chart of open tasks over the most recent snapshots (inline SVG, no JavaScript),
- a paged snapshot table, newest first.

The page only reads the database. It opens the file read-only.

## Requirements

- PHP 8.5 with `pdo_sqlite`
- Composer
- Git (the shared schema contract is a submodule)

Dependencies (checked on Packagist): `twig/twig` 3.30, `xida/php-localization`, and for
development `phpunit/phpunit` 13.4, `phpstan/phpstan` 2.3 and `friendsofphp/php-cs-fixer` 3.95.

## Setup

```bash
git clone --recurse-submodules <repo-url>
composer install
cp config/app.php.example config/app.php        # set db_path if the projects are not side by side
cp config/app.local.php.example config/app.local.php   # optional, local debug only
```

`config/app.local.php` turns debug on. It is gitignored and must never be deployed.

## Usage

```bash
start.bat                         # http://localhost:8080
```

On Apache, the root `.htaccess` rewrites every request into `public/`.

## Shared contract

`contract/` is the
[remember-the-milk-statistics-contract](https://github.com/BenjaminKobjolke/remember-the-milk-statistics-contract)
submodule. It holds the producer's table schema (`schema.json`). Production code never reads it.
The tests do:

- `tests/Unit/ContractTest.php`: every column in `SnapshotRepository::COLUMNS` exists in the
  contract with the same type.
- `tests/Integration/*`: the test database is created from the contract, so the frontend is
  tested against the producer's real schema.

Pick up a contract change with `git submodule update --remote contract`, run the tests, then
commit the pointer bump. See the contract README for the safe change order.

## Tests and checks

```bash
tools\run_tests.bat               # unit
tools\run_integration_tests.bat   # integration (temporary SQLite from the contract)
tools\tests.bat                   # both
vendor\bin\phpstan analyse
vendor\bin\php-cs-fixer fix --dry-run --diff
```

## Mobile UI checks

Use the [mobile UI driver](docs/MOBILE_UI_DRIVER.md) to open the page in an isolated Android
emulator with sample statistics data and save phone screenshots.

## Screenshots

`tools/create_media/rtm-statistics.json` is the config for the
automated-application-screenshots tool. It captures the statistics page from the local WAMP URL
(`http://localhost/remember-the-milk-statistics-frontend`); change `web.base_url` if the page is
served elsewhere (for example `start.bat` on `http://localhost:8080`).

```bash
uv run --project <screenshot-tool> screenshot-tool --config tools/create_media/rtm-statistics.json --full-page
uv run --project <screenshot-tool> screenshot-tool --config tools/create_media/rtm-statistics.json --device mobile --full-page
```

Images land in `tools/create_media/output/pages/` and `tools/create_media/output/pages/mobile/`.

## Layout

| Path | Purpose |
|------|---------|
| `public/index.php` | Front controller and central error handler |
| `src/Config/AppConfig.php` | Typed config from `config/app.php` + `config/app.local.php` |
| `src/SnapshotRepository.php` | Read-only queries; paging and sorting in SQL |
| `src/StatisticsPage.php` | Builds the page context and validates `?page=` |
| `src/OpenCountChart.php` | SVG polyline points |
| `src/TwigFactory.php` | Twig with `t()` translations and `TK` key constants |
| `templates/` | Twig templates |
| `lang/en.json` | Texts |
| `public/assets/css/style.css` | Design tokens, light and dark theme |
