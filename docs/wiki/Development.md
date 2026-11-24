Written against v0.3.3.

## The gate

```bash
composer install
npm ci
composer ci
```

`composer ci` runs, in order and stopping at the first failure: the repository guards and the Vitest suite for the `$showField` magic, the mutation-ignore, mutation-instrument and parallel-safety checks, Pint in check mode, Rector as a dry run, Larastan, and the Pest `Unit` and `Integration` suites in parallel. `composer test:serial` runs those suites in one process, which is the first thing to try when a failure looks order-dependent. `composer pint` and `composer refactor` apply formatting and the Rector profile. `npm ci` needs `vendor/statamic/cms`, because `@statamic/cms` is a `file:` link into it, so run `composer install` first. `composer mutate` runs Pest mutation testing over `src/` and needs a coverage driver (PCOV).

## Building the front-end script

`npm run build` writes the Control Panel build and the front-end script to `resources/dist`, which is committed because a Composer install does not run npm. The `Built assets` CI job fails when the committed files are stale.

## Working against a host

Behavior that depends on a real install needs a Statamic host: a stock `statamic/statamic` project with a path repository pointing at the add-on checkout.

```bash
composer create-project statamic/statamic aether-alpine-csp-forms-host
cd aether-alpine-csp-forms-host
composer config repositories.addon path "<path-to-addon-checkout>"
composer config minimum-stability dev
composer config prefer-stable true
composer require uams-web/aether-alpine-csp-forms:@dev
```

A path repository installs by symlink where the filesystem allows it, so an edit in the checkout is live in the host. Confirm the add-on loaded with `php artisan package:discover`, and by requesting `/cp/addons` as a super user.

Host-bound tests live in `tests/Feature/` and are not part of `composer ci`. Run them from the host root:

```bash
php artisan test vendor/uams-web/aether-alpine-csp-forms/tests/Feature/
```

The full procedure, with the measurements behind it, is in [`docs/DEVELOPMENT.md`](https://github.com/UAMS-Web/aether-alpine-csp-forms/blob/main/docs/DEVELOPMENT.md).
