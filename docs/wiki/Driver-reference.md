Written against v0.3.3.

The `alpine_csp` driver extends Statamic's stock Alpine driver and changes only what the `@alpinejs/csp` build cannot evaluate. It is registered under the handle `alpine_csp`, selected with the `js` parameter of `form:create`.

## What it changes

| Area | Stock `alpine` driver | `alpine_csp` driver |
| --- | --- | --- |
| Conditional field | `Statamic.$conditions.showField(...)`, which reads a window global | `$showField(conditions, $data, 'handle')`, a magic the front-end script registers |
| Non-ASCII text, slashes and line terminators in `x-data` and conditions | `\uXXXX` and `\/` escapes | Left literal, so the CSP tokenizer reads them as written |
| Invalid UTF-8 in a value | The whole object fails to encode | The bad bytes are substituted and the rest of the object survives |
| An entity such as `&quot;` in a submitted value | Passed through, and the browser decodes it to a quote that can end the JSON string | Double-encoded, so it stays text |
| Field binding on Statamic 6.31 and later | `x-model.fill` | A plain `x-model`, so a checkbox group or multi-select that a condition hides and shows again keeps the visitor's choice |
| Replicator subfield | `x-model` on the bracketed handle `parent[0][child]`, which the CSP interpreter cannot resolve | `x-model` on the bare handle `child`, inside the set's own scope; the `name` attribute keeps the bracketed shape. A subfield of a nested replicator, `outer[0][inner][1][deep]`, binds the innermost handle, `deep` |
| Date field holding the `now` sentinel | Passes the string `now` to the date input, which cannot show it | Today's date in Statamic's display timezone (the application timezone when none is set), unless the field was submitted, even empty. A range-mode date field is untouched |
| Data that cannot be JSON encoded | Not handled | An empty object, `{}` |

## Known limit: control characters

Encoding with these flags cannot reach the C0 control characters. A value holding a backspace, form feed or vertical tab is emitted with a JSON escape (`\b`, `\f` or `\u00XX`) that the CSP tokenizer reads as the literal letters `b`, `f` or `u00XX`. Newline, tab and carriage return are handled correctly, and they are the only control characters a form field realistically carries. Closing the gap needs a change to the `@alpinejs/csp` tokenizer.

## The `$showField` magic

The front-end script, `alpine-csp.js`, registers `$showField` when Alpine dispatches `alpine:init`. It forwards each call to `Statamic.$conditions.showField`, which Statamic's front-end `helpers.js` defines, so public forms evaluate conditions with the same code as the Control Panel. A `custom` condition is looked up in `window.StatamicCustomConditions`, because the front-end evaluator has no registry of its own; an unregistered name hides the field and logs Statamic's "was not properly defined" error. See [Registering a custom condition](Registering-a-custom-condition.md). If `helpers.js` is not loaded, every conditional field is shown and the console reports the missing script once. If the evaluator throws, the field is shown rather than hidden.

## Files

| Path | What it is |
| --- | --- |
| `public/vendor/aether-alpine-csp-forms/frontend/alpine-csp.js` | The published front-end script, copied from the host's `vendor/` directory by the `aether-alpine-csp-forms` publish tag. |
| `resources/dist/frontend/alpine-csp.js` | The built script, committed to the add-on because a Composer install does not run npm. |
| `resources/js/alpine-csp.js` | The script's source, for a host that bundles it. It exports `registerShowFieldMagic`. |
| `resources/js/conditions/showFieldMagic.js` | The magic, which forwards to Statamic's evaluator. |

The add-on's other pieces are listed on [Configuration](Configuration.md).
