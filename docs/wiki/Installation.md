Written against v0.3.3.

## Requirements

PHP 8.4 or later and Statamic 6.35.1 or later. Statamic 6.35.1 is the first release whose field-conditions evaluator works without `'unsafe-eval'`, so an earlier release cannot be used under a strict Content Security Policy. The host loads Alpine itself, using the `@alpinejs/csp` build, and loads Statamic's front-end `helpers.js`, which defines the field-conditions evaluator the magic forwards to.

## Install the package

```bash
composer require uams-web/aether-alpine-csp-forms
```

The add-on's service provider is discovered by Statamic and registers the driver under the handle `alpine_csp`. After the install, Statamic publishes the front-end script to `public/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js`. To publish it again, for example after an update:

```bash
php artisan vendor:publish --tag=aether-alpine-csp-forms --force
```

The add-on also publishes an empty configuration file under the tag `aether-alpine-csp-forms-config`; see [Configuration](Configuration.md). Nothing needs it.

## Load the script

Load Statamic's `helpers.js`, then the front-end script, before the host's own Alpine bundle starts. As classic deferred scripts they run in document order:

```html
<script defer src="/vendor/statamic/frontend/js/helpers.js"></script>
<script defer src="/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js"></script>
<script defer src="/build/site.js"></script>
```

Statamic publishes `helpers.js` to `public/vendor/statamic/frontend/js/helpers.js`. Without it, `$showField` shows every conditional field and logs one console error naming the missing script.

The script registers the `$showField` magic when Alpine dispatches `alpine:init`, so the host has to set `window.Alpine` before it calls `Alpine.start()`. The script does not bundle Alpine.

A host that bundles the script instead can import `registerShowFieldMagic` from `resources/js/alpine-csp.js` in the add-on and call it with its Alpine instance.

## Remove the add-on

1. Change `js="alpine_csp"` back to `js="alpine"` on each form tag.
2. Delete the add-on's script tag. Statamic's stock driver also needs `helpers.js`, so keep that one.
3. Run `composer remove uams-web/aether-alpine-csp-forms`.

The site returns to Statamic's stock driver. If Statamic ships a CSP-safe driver of its own, this is the path to it.

The same steps, with the requirements, are in the [README](https://github.com/UAMS-Web/aether-alpine-csp-forms#installation).

## Upgrading from the renamed package

The package was renamed from `uams-web/statamic-alpine-csp-forms` to `uams-web/aether-alpine-csp-forms`. The driver handle, the magic and the form tags are unchanged, but the package name, the published script path and the PHP namespace changed. The steps are in the [README](https://github.com/UAMS-Web/aether-alpine-csp-forms#upgrading-from-statamic-alpine-csp-forms) (Installation).
