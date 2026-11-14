# Contributing

Thank you for your help. This guide explains how to set up the project, run the checks and open a pull request.

## Before you start

- For a bug, open an issue with the bug report template.
- For a new feature or a change of behaviour, open an issue first and wait for an answer before you write code.
- For a documentation issue, open a pull request on [lomkit/laravel-access-control-doc](https://github.com/Lomkit/laravel-access-control-doc).
- Do not report a security issue in a public issue. See [Security issues](#security-issues).

## Branches

- The `1.x` branch holds the development of version 1, the current major version.
- Open your pull request against `1.x`.

## Setup

You need PHP 8.3 or later and Composer.

```bash
composer install
```

## Tests

The suite runs on an in-memory SQLite database by default:

```bash
composer test
```

The CI also runs the suite on MySQL 8 and PostgreSQL 16. To run it on these databases, start them with Docker:

```bash
docker run -d --rm --name lac-mysql -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=access -p 33061:3306 mysql:8
docker run -d --rm --name lac-pg -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=access -p 54321:5432 postgres:16
```

Then run the suite on each database:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=access DB_USERNAME=root composer test
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=54321 DB_DATABASE=access DB_USERNAME=postgres DB_PASSWORD=postgres composer test
```

Stop the containers when you are done:

```bash
docker stop lac-mysql lac-pg
```

The CI also tests the lowest versions allowed by `composer.json`. Composer skips releases with security advisories, which would raise these versions, so allow them first:

```bash
composer config audit.block-insecure false
composer update --prefer-lowest --prefer-stable
composer test
git checkout composer.json
composer update
```

Laravel Scout is a development dependency, and the package must work without it. The CI runs the suite without Scout, leaving out the tests that need it:

```bash
composer remove --dev laravel/scout --no-update
composer update
vendor/bin/phpunit --exclude-group scout --exclude-group meilisearch --exclude-group typesense
git checkout composer.json
composer update
```

The Meilisearch and Typesense tests are in the `meilisearch` and `typesense` groups, which `phpunit.xml` leaves out. They run against real servers. To run them, start the servers and add the engine clients:

```bash
docker run -d --rm --name lac-meilisearch -e MEILI_MASTER_KEY=masterKey123456 -e MEILI_NO_ANALYTICS=true -p 7700:7700 getmeili/meilisearch:v1.55.0
docker run -d --rm --name lac-typesense -e TYPESENSE_API_KEY=xyz123 -e TYPESENSE_DATA_DIR=/tmp -p 8108:8108 typesense/typesense:30.1
composer config allow-plugins.php-http/discovery true
composer require --dev --no-update "meilisearch/meilisearch-php:^1.17" "typesense/typesense-php:^4.9.3"
composer update
```

Then run the groups. Without `MEILISEARCH_HOST` or `TYPESENSE_HOST`, the tests of that engine are skipped:

```bash
MEILISEARCH_HOST=http://127.0.0.1:7700 MEILISEARCH_KEY=masterKey123456 \
TYPESENSE_HOST=127.0.0.1 TYPESENSE_PORT=8108 TYPESENSE_PROTOCOL=http TYPESENSE_API_KEY=xyz123 \
vendor/bin/phpunit --group meilisearch --group typesense
```

Stop the servers and restore `composer.json` when you are done:

```bash
docker stop lac-meilisearch lac-typesense
git checkout composer.json
composer update
```

The tests run in a random order. A test that passes alone and fails in the suite depends on state that another test left behind: fix the test, not the order. PHPUnit prints the seed of each run, and `vendor/bin/phpunit --order-by=random --random-order-seed=<seed>` replays it.

Add a test for each bug fix and each new feature.

### Writing tests

Write new tests in `tests/Feature` or `tests/Unit`:

- Feature tests extend `Lomkit\Access\Tests\PackageTestCase` and declare the fixtures they need in `fixtures()`, with `#[\Override]`.
- A fixture is a small domain in `tests/Fixtures/<Domain>`: models, factories, migrations, controls, perimeters and policies. It implements `Lomkit\Access\Tests\Fixtures\Fixture`, which gives its migrations and its controls. The existing fixtures are `Blog` (clients, users, projects, posts, comments and the `post_shares` pivot), `Agencies` (agencies and their members) and `Search` (searchable articles, their `article_shares` pivot, and an in-memory Scout engine).
- `PackageTestCase` turns off the discovery of controls and registers only the controls of the declared fixtures. It flushes the registry before and after each test.
- `PackageTestCase` calls `Model::preventAccessingMissingAttributes()`: a test fails when the package reads an attribute that its query did not load.
- Test methods use the `#[Test]` attribute and are named `it_...`.
- Use fixed values, not Faker, and write the expected results by hand. Factories give fixed defaults, and a test passes the values it asserts on.
- A test class that touches Laravel Scout carries `#[Group('scout')]` on the class. Each concrete class needs the attribute, because PHPUnit does not inherit it. A search test extends `tests/Fixtures/Search/SearchTestCase.php`, which adds `ScoutServiceProvider` and the in-memory engine, whose `searches` lists the searches that reached it. A test of the `database` engine sets `#[WithConfig('scout.driver', 'database')]`; the `collection` driver that `PackageTestCase` sets refuses controlled searches.
- A search engine test carries `#[Group('scout')]` and the group of its engine, and extends `tests/Fixtures/Search/EngineTestCase.php`.
- A test class that does not touch Scout never loads the `Search` fixture, so that it runs in the job without Scout.

Pure unit tests that need neither the container nor the database extend PHPUnit's `TestCase`.

## Code style

[Pint](https://laravel.com/docs/pint) checks the code style with the `laravel` preset. To fix the style:

```bash
composer format
```

To check the style without changes, as the CI does:

```bash
composer lint
```

New code follows these rules:

- Declare the type of every parameter, return value and property.
- Use early returns instead of nested conditions.
- Add `#[\Override]` to every method that overrides a parent method or implements an interface method. Larastan reports a missing attribute in `src`.
- Leave out `#[\Override]` when the parent method does not exist in the lowest version that `composer.json` allows, for example a hook that a later Laravel release added: the attribute is a fatal error on that version. Ignore the Larastan error for this method only in `phpstan.neon`, with the reason in the message pattern.
- Do not write comments that describe the code. Use PHPDoc only for types that PHP cannot declare, for example `array<string, mixed>` or `class-string<Control>`.
- Keep each file of `src` at 200 lines or fewer. Split a longer file along a responsibility it already has.

## Static analysis

[Larastan](https://github.com/larastan/larastan) analyses `src` at level 10 for PHP 8.3, the lowest version that `composer.json` allows:

```bash
composer analyse
```

The analysis also requires native type coverage of 100 % in `src` for returns, parameters, properties and constants, with [type-coverage](https://github.com/TomasVotruba/type-coverage). A PHPDoc type does not count.

The analysis has no baseline: fix an error instead of ignoring it.

## Commits

Write commit messages with [Conventional Commits](https://www.conventionalcommits.org): `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `build:`, `ci:`, `chore:`. Add `!` after the type for a breaking change, for example `feat!:`.

## Pull requests

- Keep one subject per pull request.
- Write `Closes #123` in the description when the pull request fixes an issue.
- Make sure that the tests, Pint and Larastan pass. The CI runs them on each pull request.
- Update the README when the change affects how the package is used.

## Security issues

Do not open a public issue for a security issue. Report it in private from the [Security tab](https://github.com/Lomkit/laravel-access-control/security) of the repository, with "Report a vulnerability".
