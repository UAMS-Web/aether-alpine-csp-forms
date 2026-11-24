<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Statamic\Contracts\Forms\Form;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * The CSP-safe rendering shared by the add-on's form JS drivers.
 *
 * `AlpineCsp` extends Statamic's `alpine` driver and `AlpinePrecognitionCsp` extends its
 * `alpine_precognition` driver. Both emit the same attribute expressions the `@alpinejs/csp`
 * build cannot evaluate, so the parts that do not depend on the parent's `x-data` shape live
 * here: the `$showField` call, the JSON encoding, the plain `x-model`, the replicator subfield
 * binding and the `now` date default. The `x-data` rendering stays in each driver, because the
 * two parents build it differently. The reasoning for each part is in the `AlpineCsp` class
 * docblock.
 *
 * The using class extends a Statamic driver that declares `$form`, `$component` and the render
 * methods this trait overrides.
 */
trait RendersCspExpressions
{
    /**
     * `json_encode()` flags for a value embedded in a CSP-build Alpine expression.
     *
     * Chosen against the tokenizer's escape table, which handles only `\n`, `\t`,
     * `\r`, `\\` and the active quote:
     *
     *   - `JSON_UNESCAPED_UNICODE` is the load-bearing one — see the
     *     `AlpineCsp` class docblock. It keeps every non-ASCII character literal.
     *   - `JSON_UNESCAPED_LINE_TERMINATORS` finishes that job. PHP escapes
     *     U+2028 / U+2029 as `\u2028` / `\u2029` even under
     *     `JSON_UNESCAPED_UNICODE`, and those two are reachable from any
     *     textarea paste.
     *   - `JSON_UNESCAPED_SLASHES` keeps `/` literal rather than relying on the
     *     tokenizer's permissive fall-through to un-escape `\/`.
     *   - `JSON_INVALID_UTF8_SUBSTITUTE` keeps malformed submitted bytes from
     *     collapsing the whole payload to `false`.
     *
     * **Known residue.** No flag combination reaches the C0 controls: PHP emits
     * `\b` and `\f` for U+0008 / U+000C and `\u00XX` for the rest, and the
     * tokenizer's `default: value += char` turns those into the literals `b`,
     * `f` and `u00XX`. `\n`, `\t` and `\r` — the only C0 characters a form
     * field realistically carries — are handled correctly, so this is a narrow
     * gap: a value would need an embedded vertical tab, form feed or backspace
     * to hit it. Closing it needs an upstream tokenizer fix, not an encoder one.
     */
    // PCOV never reports a constant initializer as executed, so Pest classes every mutant of this
    // line as uncovered although the encoding tests run it. Each `|` to `&` mutant was applied by
    // hand and killed by `AlpineCspJsDriverTest` and `AlpineCspDriverBehaviorTest`.
    // @pest-mutate-ignore: BitwiseOrToBitwiseAnd
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_LINE_TERMINATORS
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * Matches a replicator subfield handle at any depth: a base name followed by one or more
     * `[index][child]` pairs, so `participants[0][is_minor]` and `outer[0][inner][1][deep]` both
     * match while `participants[0]` and `plain_field` do not.
     */
    private const string REPLICATOR_SUBFIELD_PATTERN = '/^[^\[\]]+(?:\[[^\[\]]*\]\[[^\[\]]+\])+$/';

    /**
     * Captures the innermost bracket group of a handle, the subfield's own bare handle.
     */
    private const string INNERMOST_GROUP_PATTERN = '/\[([^\[\]]+)\]$/';

    /**
     * The binding Statamic's driver emits in place of a plain `x-model` since statamic/cms 6.31.0.
     */
    private const string FILL_MODEL_ATTRIBUTE = 'x-model.fill';

    /**
     * The value `App\Fieldtypes\Date::preProcess()` passes through for a `default: now` field.
     */
    private const string DATE_NOW_SENTINEL = 'now';

    /**
     * The format a native `<input type="date">` reads its value in.
     */
    private const string DATE_INPUT_FORMAT = 'Y-m-d';

    /**
     * Start a date field whose default is `now` at today's date (#2885).
     *
     * `App\Fieldtypes\Date::preProcess()` passes the `now` sentinel through unchanged, which the
     * Control Panel's date picker resolves itself. A public form binds the field's `<input
     * type="date">` to `x-data` with a plain `x-model` (#2800), so the sentinel would reach the
     * input as the string "now", which it cannot display. This is the public-form counterpart of
     * the Gravity Forms `{date_mdy}` default.
     *
     * Only this public driver substitutes the date, so the Control Panel keeps its handling of
     * `now`. A field present in `old()` after this form failed validation is left as the parent
     * rendered it, even when the visitor left it empty: Statamic fills an empty value with the default,
     * and refilling today would undo the visitor's choice. A range-mode field preprocesses to a
     * start/end array, never the bare sentinel, so it is untouched. Today is read in Statamic's
     * display timezone, the calendar the site's visitors use, rather than the application's UTC.
     *
     * @return array<array-key, mixed> The form's initial `x-data` values, keyed by field handle.
     */
    #[\Override]
    protected function getInitialFormData(): array
    {
        $data = parent::getInitialFormData();

        // The parent declares both untyped; a driver is always built with a form, and a form
        // always resolves a blueprint (an empty one when none is stored)
        /** @var Form $form */
        $form = $this->form;

        // The parent applies `old()` only for the form that failed validation, which holds the
        // error bag `form.<handle>`; any other request carrying old input, such as another form's
        // failure, left it unused. The bag lives in the session, so `old()` reads it and is an array.
        /** @var ViewErrorBag|null $errors */
        $errors = session('errors');
        /** @var array<array-key, mixed> $old */
        $old = $errors?->hasBag('form.'.$form->handle()) ? old() : [];

        /** @var Blueprint $blueprint */
        $blueprint = $form->blueprint();

        /** @var Field $field */
        foreach ($blueprint->fields()->all() as $handle => $field) {
            // Only a date field
            if ($field->type() !== 'date') {
                continue;
            }

            // Only one still holding the sentinel
            if (($data[$handle] ?? null) !== self::DATE_NOW_SENTINEL) {
                continue;
            }

            // Never one this form's visitor submitted, even empty
            if (array_key_exists($handle, $old)) {
                continue;
            }

            $data[$handle] = self::todayForDateInput();
        }

        return $data;
    }

    /**
     * Bind a replicator subfield's `x-model` to its bare handle rather than its bracketed one (#2056).
     *
     * `App\Fieldtypes\Replicator::preProcessTagRenderable()` rewrites each subfield's handle to
     * `parent[index][child]` so the input's `name` posts the shape `process()` consumes. The parent
     * driver reuses that same handle for `x-model`, which Alpine reads as an **expression**: the
     * index and the child handle become identifier lookups, not string keys, so the binding
     * resolves nothing and the CSP interpreter throws on it.
     *
     * Each set renders inside its own `x-data` scope (see the `replicatorSet` component), where the
     * row's values are keyed by bare handle — so binding to `child` is both valid and correct, and
     * it is what gives `$showField` a reactive source for a condition whose `if:` names a sibling
     * subfield. The `name` attribute is untouched: the partials render it from `{{ handle }}`
     * directly, so submission still posts the bracketed shape.
     *
     * A subfield inside a nested replicator (#2133) carries one `[index][child]` pair per level,
     * `outer[0][inner][1][deep]`, and renders inside the innermost set's own scope, so it binds
     * the innermost group, `deep`, by the same reasoning.
     *
     * @param  Field  $field  The field being rendered.
     * @return array<array-key, mixed> The Alpine attributes for this field.
     */
    #[\Override]
    public function addToRenderableFieldAttributes($field): array
    {
        $attributes = parent::addToRenderableFieldAttributes($field);

        // Keep a plain binding, as before statamic/cms 6.31.0 (statamic/cms#14706). Each time Alpine
        // creates the binding, `.fill` seeds an empty model from the input's markup, and always seeds
        // a checkbox group or a multi-select. So a checkbox group or multi-select that a condition
        // hides and shows again would take the server render's value and drop the user's choice.
        if (array_key_exists(self::FILL_MODEL_ATTRIBUTE, $attributes)) {
            $attributes['x-model'] = $attributes[self::FILL_MODEL_ATTRIBUTE];
            unset($attributes[self::FILL_MODEL_ATTRIBUTE]);
        }

        if (! isset($attributes['x-model'])) {
            return $attributes;
        }

        $handle = $field->handle();

        // Only a replicator subfield carries the bracketed shape; anything else keeps the parent's
        if (! is_string($handle) || preg_match(self::REPLICATOR_SUBFIELD_PATTERN, $handle) !== 1) {
            // Keep the parent's binding
            return $attributes;
        }

        // Bind the innermost group, the subfield's bare handle within its own set's scope
        if (preg_match(self::INNERMOST_GROUP_PATTERN, $handle, $matches) === 1) {
            $attributes['x-model'] = $matches[1];
        }

        return $attributes;
    }

    /**
     * Render the `x-if` show-field expression for one field.
     *
     * Identical to the parent except that the callee is the `$showField` magic
     * rather than the `Statamic` window global.
     */
    #[\Override]
    protected function renderAlpineShowFieldJs(Field $field, ?string $alpineScope): string
    {
        // Encode the field's conditions as an object literal the CSP tokenizer can read
        $conditionsObject = $this->encodeExpressionLiteral($field->conditions());

        // Descend into the form's scope key when the driver was given one
        $dataObject = is_string($alpineScope)
            ? '$data.'.$alpineScope
            : '$data';

        // Statamic\Fields\Field::handle() is untyped; a field always carries a
        // string handle, but narrow it rather than interpolate an unknown.
        $handle = $field->handle();

        return sprintf(
            "\$showField(%s, %s, '%s')",
            $conditionsObject,
            $dataObject,
            is_string($handle) ? $handle : '',
        );
    }

    /**
     * Today's date in Statamic's display timezone, as a native date input reads it.
     */
    private static function todayForDateInput(): string
    {
        // Fall back to the application timezone when no display timezone is set
        $timezone = config('statamic.system.display_timezone') ?: config('app.timezone');

        return Carbon::now(is_string($timezone) ? $timezone : null)
            ->format(self::DATE_INPUT_FORMAT);
    }

    /**
     * Encode a value as an HTML-attribute-safe literal for a CSP-build expression.
     *
     * Escapes with `double_encode` ON, which is where this diverges from
     * `Html::entities()` (`htmlentities($value, ENT_QUOTES, 'UTF-8', false)`).
     * With double-encoding off, a field value containing the literal text
     * `&quot;` is passed through untouched, the browser decodes it to `"` while
     * parsing the attribute, and that quote closes the JSON string early —
     * turning the remainder of the value into Alpine expression AST. Under the
     * CSP build that AST cannot reach a global or `eval`, so the practical
     * damage is a corrupted `x-data` or a form whose fields all fail to render,
     * but neither is acceptable from ordinary submitted text.
     *
     * Double-encoding is unambiguously correct here because the input is always
     * freshly-encoded JSON, never pre-encoded HTML: a literal `&` in a value
     * becomes `&amp;` and the browser decodes it back to `&`. The charset is
     * pinned rather than read from `statamic.system.charset` for the same
     * reason — `json_encode()` always emits UTF-8.
     */
    private function encodeExpressionLiteral(mixed $value): string
    {
        $json = json_encode($value, self::JSON_FLAGS);

        // Degrade to an empty object rather than an empty attribute, matching
        // how the `x-data` directive itself treats an empty expression.
        return htmlentities($json === false ? '{}' : $json, ENT_QUOTES, 'UTF-8');
    }
}
