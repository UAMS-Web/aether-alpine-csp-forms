Written against v0.3.3.

The add-on has no settings. Its configuration file exists so a host can publish and extend it later, and it returns an empty array.

## The file

| Item | Value |
| --- | --- |
| Config handle | `aether-alpine-csp-forms`, read with `config('aether-alpine-csp-forms')` |
| Source | `config/aether-alpine-csp-forms.php` in the add-on |
| Publish tag | `aether-alpine-csp-forms-config` |
| Published to | `config/aether-alpine-csp-forms.php` in the host |

```bash
php artisan vendor:publish --tag=aether-alpine-csp-forms-config
```

Publishing is optional, and nothing the add-on does reads a key from the file. A site coming from `statamic-alpine-csp-forms` that published the old file renames it to `config/aether-alpine-csp-forms.php`; see [Installation](Installation.md#upgrading-from-the-renamed-package).

## Publish tags

| Tag | What it publishes |
| --- | --- |
| `aether-alpine-csp-forms` | The front-end script, to `public/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js`. See [Installation](Installation.md#install-the-package). |
| `aether-alpine-csp-forms-config` | The empty configuration file above. |

## What the add-on does not register

The service provider registers one thing: the `alpine_csp` form JS driver, which leaves Statamic's stock `alpine` driver in place for any form that still selects it. The add-on adds no routes, Antlers tags, commands, permissions, Control Panel screens or fieldtypes. It declares a Vite build for the Control Panel, `resources/js/cp.js` and `resources/css/cp.css`, whose entry point registers no components yet.

For what the driver changes in a form's markup, see the [Driver reference](Driver-reference.md).
