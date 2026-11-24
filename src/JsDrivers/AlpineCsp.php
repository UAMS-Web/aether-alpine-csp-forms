<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers;

use Statamic\Forms\JsDrivers\Alpine;
use UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\Concerns\RendersCspExpressions;

/**
 * CSP-safe public-form JS driver.
 *
 * Statamic's stock `alpine` driver emits attribute expressions the
 * `@alpinejs/csp` build refuses to evaluate, so this subclass re-renders the
 * two that matter. It is selected with `js="alpine_csp"` on `{{ form:create }}`
 * — the handle `AbstractJsDriver::handle()` derives from this class name — and
 * is registered by `UAMSWeb\Aether\Forms\AlpineCsp\ServiceProvider`.
 *
 * ## Why the `show_field` expression is re-rendered
 *
 * The parent emits, for every field of every public form:
 *
 *     Statamic.$conditions.showField({…}, $data, '<handle>')
 *
 * `@alpinejs/csp` resolves identifiers only from the Alpine scope stack and
 * throws `Undefined variable: Statamic` — and would throw
 * `Accessing global variables is prohibited in the CSP build` even if the
 * global were injected. The observable failure is a form that renders its
 * heading and Submit button with no input fields, because every
 * `<template x-if="…">` wrapper in `fields.antlers.html` throws.
 *
 * The CSP restriction is on *attribute expressions*, not on JS modules, so the
 * global is reached from module scope instead: this driver emits a call to the
 * `$showField` Alpine magic registered in
 * `resources/js/conditions/showFieldMagic.js`, which forwards to
 * `window.Statamic.$conditions.showField` from Statamic's `helpers.js`. The
 * signature and argument order are unchanged, so scoped forms
 * (`js="alpine_csp:intake"`) keep working: only the callee moves.
 *
 * ## Why the JSON encoding is re-rendered
 *
 * The parent encodes both the `x-data` payload and the conditions object with
 * `Statamic::modify(…)->toJson()`, i.e. `json_encode($value, 0)`, which escapes
 * every non-ASCII character as `\uXXXX`. The CSP build's tokenizer implements
 * only `\n`, `\t`, `\r`, `\\` and the quote escape, and falls through to
 * `value += char` for everything else — so `"René"` is read as the string
 * `Renu00e9`. Under the standard build `new Function()` handled the escape, so
 * this only breaks once the CSP build is in play.
 *
 * That is a silent data-corruption path rather than a cosmetic one: a failed
 * validation round-trip re-populates `x-data` from `old()`, the mangled value
 * is bound back through `x-model`, and the user re-submits it. Encoding with
 * `JSON_UNESCAPED_UNICODE` keeps literal UTF-8 in the attribute, which
 * `htmlentities()` then encodes as named entities and the browser decodes back
 * before Alpine ever tokenizes it.
 *
 * @see Alpine  the parent whose render methods this narrows
 * @see RendersCspExpressions  the rendering this driver shares with `AlpinePrecognitionCsp`
 * @see https://github.com/alpinejs/alpine/tree/main/packages/csp
 */
final class AlpineCsp extends Alpine
{
    use RendersCspExpressions;

    /**
     * Render the form's `x-data` object literal.
     *
     * Identical to the parent except for the JSON flags — see the class docblock.
     *
     * @param  array<string, mixed>  $xData
     * @param  bool|string|null  $alpineScope
     */
    #[\Override]
    protected function renderAlpineXData($xData, $alpineScope): string
    {
        // Nest the form data under the scope key when the driver was given one
        if (is_string($alpineScope)) {
            $xData = [$alpineScope => $xData];
        }

        $encoded = $this->encodeExpressionLiteral($xData);

        // Hand the data to a registered Alpine.data() component when one was
        // named. The parent's `$component` is untyped and sourced from the tag's
        // `js="alpine_csp:<scope>:<component>"` options, so narrow to the string
        // case — a non-string could only come from a malformed option list, and
        // interpolating it would emit an unparsable expression.
        $component = $this->component;

        return is_string($component) && $component !== ''
            ? sprintf('%s(%s)', $component, $encoded)
            : $encoded;
    }
}
