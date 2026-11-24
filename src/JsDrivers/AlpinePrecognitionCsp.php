<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers;

use Illuminate\Support\MessageBag;
use Statamic\Forms\Form as FormModel;
use Statamic\Forms\JsDrivers\AlpinePrecognition;
use UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\Concerns\RendersCspExpressions;

/**
 * CSP-safe public-form JS driver for Laravel Precognition.
 *
 * Statamic's `alpine_precognition` driver emits the same `show_field` expression as the stock
 * `alpine` driver, `Statamic.$conditions.showField(...)`, which the `@alpinejs/csp` build
 * refuses to evaluate: a form renders its heading and Submit button with no inputs. This
 * subclass renders that expression, and the `x-data` payload, the way `AlpineCsp` does. It is
 * selected with `js="alpine_precognition_csp"` on `{{ form:create }}`, the handle
 * `AbstractJsDriver::handle()` derives from this class name, and is registered by
 * `UAMSWeb\Aether\Forms\AlpineCsp\ServiceProvider`.
 *
 * ## What stays as Statamic renders it
 *
 * The `x-data` object is still `{<scope>: $form('post', '<action>', {...})}`, with
 * `.setErrors({...})` appended after a failed submission, and each field still carries
 * `@change="<scope>.validate('<handle>')"`. Those are a magic call, a method call and an
 * object literal, which the CSP interpreter evaluates. The `$form` magic comes from Laravel's
 * `laravel-precognition-alpine` plugin, which the host loads as it does without a CSP.
 *
 * @see AlpinePrecognition  the parent whose render methods this narrows
 * @see AlpineCsp  the driver for forms that do not validate through Precognition
 * @see RendersCspExpressions  the rendering the two drivers share
 */
final class AlpinePrecognitionCsp extends AlpinePrecognition
{
    use RendersCspExpressions;

    /**
     * Render the form's `x-data` object literal around the Precognition `$form` call.
     *
     * Identical to the parent except that the initial data and any validation errors are
     * encoded with the CSP-safe flags: the parent's `\uXXXX` escapes read back as the literal
     * text `u00e9` in the CSP tokenizer, so `René` would not survive a failed submission.
     *
     * @param  array<string, mixed>  $xData
     * @param  bool|string|null  $alpineScope
     */
    #[\Override]
    protected function renderAlpineXData($xData, $alpineScope): string
    {
        // The parent declares `$form` untyped; a driver is always built with a form
        /** @var FormModel $form */
        $form = $this->form;
        $action = $form->actionUrl();

        // The data the Precognition form starts from, encoded as an object literal the CSP
        // tokenizer can read
        $expression = sprintf("\$form('post', '%s', %s)", $action, $this->encodeExpressionLiteral($xData));

        // Re-apply the errors of a failed submission to the form
        /** @var MessageBag|null $errors */
        $errors = $this->getInitialFormErrors();
        if ($errors?->count()) {
            $expression .= '.setErrors('.$this->encodeExpressionLiteral($errors).')';
        }

        // Key it by the form's scope, `form` unless the tag named one, as the parent's options do
        $expression = sprintf('{%s: %s}', is_string($alpineScope) ? $alpineScope : 'form', $expression);

        // Hand the object to a registered Alpine.data() component when one was named
        $component = $this->component;

        return is_string($component) && $component !== ''
            ? sprintf('%s(%s)', $component, $expression)
            : $expression;
    }
}
