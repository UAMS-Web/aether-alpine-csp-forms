Written against v0.3.3.

- [A form renders with no inputs](#a-form-renders-with-no-inputs)
- [Conditional fields never appear](#conditional-fields-never-appear)
- [Every conditional field appears](#every-conditional-field-appears)
- [The script answers 404](#the-script-answers-404)
- [A non-ASCII value changes after a failed submit](#a-non-ascii-value-changes-after-a-failed-submit)
- [A field with a custom condition stays hidden](#a-field-with-a-custom-condition-stays-hidden)
- [A field shows although its condition is false](#a-field-shows-although-its-condition-is-false)

## A form renders with no inputs

The page shows the form's heading and Submit button and no inputs, and the browser console reports `Accessing global variables is prohibited in the CSP build`.

**Cause:** the form is still using Statamic's stock `alpine` driver, whose conditions read the `Statamic` window global.

**Fix:** set `js="alpine_csp"` on the `form:create` tag, or `js="alpine_precognition_csp"` when the form validates through Laravel Precognition. See [Selecting the driver on a form](Selecting-the-driver-on-a-form.md).

## Conditional fields never appear

The form renders, but a field with a condition stays hidden whatever the visitor chooses.

**Cause:** the front-end script is not loaded, or it loads after Alpine has started, so `$showField` is not registered when the page's expressions are evaluated.

**Fix:** load `alpine-csp.js` as a deferred script before the host's own Alpine bundle, and set `window.Alpine` before `Alpine.start()`. See [Installation](Installation.md#load-the-script).

## Every conditional field appears

The form shows every field whatever the visitor chooses, and the browser console reports `Statamic's field-conditions evaluator is missing`.

**Cause:** Statamic's `helpers.js` is not loaded, so `$showField` has no evaluator to forward to and shows each field rather than hiding it.

**Fix:** load `/vendor/statamic/frontend/js/helpers.js` as a deferred script before `alpine-csp.js`. See [Installation](Installation.md#load-the-script).

## The script answers 404

The page requests `/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js` and receives a 404.

**Cause:** the script was never published to the host's `public/` directory, or the directory was cleaned.

**Fix:** run `php artisan vendor:publish --tag=aether-alpine-csp-forms --force`.

## A non-ASCII value changes after a failed submit

After validation fails, a name such as `René` comes back as `Renu00e9`.

**Cause:** an `x-data` attribute carrying a `\uXXXX` escape, which some `@alpinejs/csp` versions do not decode. The `alpine_csp` driver leaves non-ASCII text literal for this reason, so the symptom points at a form still on the stock driver.

**Fix:** select `alpine_csp` on the form tag.

## A field with a custom condition stays hidden

A field whose condition is `custom <name>` never appears, and the browser console reports that the custom condition was not properly defined.

**Cause:** the front-end evaluator has no registry of its own, and `window.StatamicCustomConditions` has no entry under that name.

**Fix:** register the condition on the page. See [Registering a custom condition](Registering-a-custom-condition.md).

## A field shows although its condition is false

The console reports `Field condition evaluation failed; showing the field.` followed by the error, or `Statamic's field-conditions evaluator is missing`.

**Cause:** the magic fails open. When the evaluator throws, or `helpers.js` is not loaded, it shows the field, because a form whose fields all evaluate false renders no inputs at all.

**Fix:** read the error that follows the first message. For the missing evaluator, see [Every conditional field appears](#every-conditional-field-appears). Otherwise the condition or the data it reads is malformed.
