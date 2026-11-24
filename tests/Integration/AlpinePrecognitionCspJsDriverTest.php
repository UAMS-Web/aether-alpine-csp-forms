<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Fields\BlueprintRepository;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Date;
use Statamic\Forms\Form as FormModel;
use Statamic\Forms\JsDrivers\AlpinePrecognition;
use Statamic\Tags\Parameters;
use UAMSWeb\Aether\Forms\AlpineCsp\JsDrivers\AlpinePrecognitionCsp;

/**
 * Coverage for `AlpinePrecognitionCsp`, the CSP-safe counterpart of Statamic's
 * `alpine_precognition` driver (#91).
 *
 * Statamic's driver emits the stock `Statamic.$conditions.showField(...)` expression, which the
 * `@alpinejs/csp` build refuses to evaluate. Each case drives the real driver over an unsaved
 * form, the way `AlpineCspDriverBehaviorTest` drives `AlpineCsp`, and reads what a template
 * would print.
 *
 * @command  vendor/bin/pest tests/Integration/AlpinePrecognitionCspJsDriverTest.php
 */

/**
 * A date fieldtype that preprocesses a `now` default to the `now` sentinel, as the application
 * the add-on came from does.
 */
final class PrecognitionSentinelDateFieldtype extends Date
{
    /**
     * @param  mixed  $data  The stored value.
     */
    #[Override]
    public function preProcess($data): mixed
    {
        return $this->config('default') === 'now' ? 'now' : parent::preProcess($data);
    }
}

/**
 * Build an unsaved form carrying the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields  Blueprint fields keyed by handle.
 */
function precognitionForm(array $fields, string $handle): FormModel
{
    app(BlueprintRepository::class)->setFallback(
        'forms.'.$handle,
        fn () => Blueprint::makeFromFields($fields),
    );

    /** @var FormModel $form */
    $form = Form::make($handle);
    $form->title('Precognition');

    return $form;
}

/**
 * Build the CSP-safe precognition driver over an unsaved form carrying the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields  Blueprint fields keyed by handle.
 * @param  list<string>  $options  The driver's scope and component options.
 */
function precognitionCspDriver(array $fields, array $options = [], string $handle = 'precognition_form'): AlpinePrecognitionCsp
{
    return new AlpinePrecognitionCsp(precognitionForm($fields, $handle), $options, new Parameters([]));
}

/**
 * Render the form's `x-data` attribute as the string a template would print.
 */
function precognitionXData(AlpinePrecognitionCsp $driver): string
{
    $attribute = $driver->addToFormAttributes()['x-data'] ?? null;

    if (! is_string($attribute)) {
        throw new RuntimeException('expected the driver to render an x-data attribute');
    }

    return $attribute;
}

/**
 * Read the `show_field` expression the driver adds to a field's render data.
 */
function precognitionShowField(AlpinePrecognitionCsp $driver, Field $field): string
{
    $expression = $driver->addToRenderableFieldData($field, [])['show_field'] ?? null;

    if (! is_string($expression)) {
        throw new RuntimeException('expected the driver to add a show_field expression');
    }

    return $expression;
}

/**
 * Flash a failed submission of one form into the session: its old input and its error bag.
 *
 * @param  array<string, mixed>  $input  The submitted values.
 * @param  array<string, list<string>>  $errors  The validation messages by field.
 */
function flashPrecognitionFailure(string $handle, array $input, array $errors): void
{
    request()->setLaravelSession(app('session.store'));
    session()->flashInput($input);
    session()->put('errors', (new ViewErrorBag)->put('form.'.$handle, new MessageBag($errors)));
}

it('registers the driver under the alpine_precognition_csp handle', function (): void {
    expect(AlpinePrecognitionCsp::handle())->toBe('alpine_precognition_csp')
        ->and(app('statamic.form-js-drivers')->get('alpine_precognition_csp'))->toBe(AlpinePrecognitionCsp::class);
});

it('leaves the stock precognition driver registered', function (): void {
    expect(app('statamic.form-js-drivers')->get('alpine_precognition'))->toBe(AlpinePrecognition::class);
});

it('emits the $showField call and no Statamic global in a field show-field expression', function (): void {
    $driver = precognitionCspDriver(['referrer' => ['type' => 'text'], 'note' => ['type' => 'text']]);

    $field = new Field('note', ['type' => 'text', 'if' => ['referrer' => 'equals yes']]);

    $expression = precognitionShowField($driver, $field);

    // The magic the front-end script registers, scoped to the form's `form` key, with the
    // field path the evaluator needs
    expect($expression)->toStartWith('$showField(')
        ->and($expression)->toEndWith(", \$data.form, 'note')")
        ->and($expression)->not->toContain('Statamic');
});

it('names the form scope given as the first option in the show-field call', function (): void {
    $driver = precognitionCspDriver(['note' => ['type' => 'text']], ['intake']);

    $field = new Field('note', ['type' => 'text']);

    expect(precognitionShowField($driver, $field))
        ->toEndWith(", \$data.intake, 'note')");
});

it('renders the x-data as a Precognition $form call under the default form scope', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text', 'default' => 'Ada']]);

    $xData = precognitionXData($driver);

    expect($xData)->toStartWith("{form: \$form('post', '")
        ->and($xData)->toContain("/!/forms/precognition_form', {&quot;name&quot;:&quot;Ada&quot;,&quot;honeypot&quot;:null})}")
        ->and($xData)->not->toContain('setErrors');
});

it('renders the x-data under the scope given as the first option', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']], ['intake']);

    expect(precognitionXData($driver))->toStartWith("{intake: \$form('post', '");
});

it('wraps the x-data in the component given as the second option', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']], ['intake', 'intakeForm']);

    expect(precognitionXData($driver))->toStartWith("intakeForm({intake: \$form('post', '")
        ->and(precognitionXData(precognitionCspDriver(['name' => ['type' => 'text']], ['intake', ''])))
        ->toStartWith('{intake: ');
});

it('keeps a non-ASCII value literal in the x-data rather than escaping it', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text', 'default' => "Ren\u{E9}"]]);

    $xData = precognitionXData($driver);

    // The CSP tokenizer reads a JSON `u` escape as literal text; a named entity is decoded by
    // the browser before Alpine tokenizes the attribute
    expect($xData)->toContain('Ren&eacute;')
        ->and($xData)->not->toContain('u00e9');
});

it('double-encodes an entity in a value so it stays text', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text', 'default' => '&quot;x']]);

    expect(precognitionXData($driver))->toContain('&amp;quot;x');
});

it('appends the errors of a failed submission to the $form call, with non-ASCII text literal', function (): void {
    flashPrecognitionFailure('precognition_failed', ['name' => 'Ren'], ['name' => ["Name must be Ren\u{E9}"]]);

    $driver = precognitionCspDriver(['name' => ['type' => 'text']], [], 'precognition_failed');

    $xData = precognitionXData($driver);

    expect($xData)->toContain(').setErrors({&quot;name&quot;:[&quot;Name must be Ren&eacute;&quot;]})}')
        ->and($xData)->not->toContain('u00e9');
});

it('appends no errors when only another form failed', function (): void {
    flashPrecognitionFailure('some_other_form', ['name' => 'x'], ['name' => ['Wrong']]);

    $driver = precognitionCspDriver(['name' => ['type' => 'text']]);

    expect(precognitionXData($driver))->not->toContain('setErrors');
});

it('appends no errors when the form failed with an empty bag', function (): void {
    flashPrecognitionFailure('precognition_empty', ['name' => 'x'], []);

    $driver = precognitionCspDriver(['name' => ['type' => 'text']], [], 'precognition_empty');

    expect(precognitionXData($driver))->not->toContain('setErrors');
});

it('validates each field on change through the form scope', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']]);

    $attributes = $driver->addToRenderableFieldAttributes(new Field('name', ['type' => 'text']));

    expect($attributes['@change'])->toBe("form.validate('name')");
});

it('validates each field on change through the named scope', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']], ['intake']);

    $attributes = $driver->addToRenderableFieldAttributes(new Field('name', ['type' => 'text']));

    expect($attributes['@change'])->toBe("intake.validate('name')");
});

it('binds a field with a plain x-model rather than the fill binding', function (): void {
    $driver = precognitionCspDriver(['tags' => ['type' => 'checkboxes', 'options' => ['a', 'b']]]);

    $attributes = $driver->addToRenderableFieldAttributes(new Field('tags', ['type' => 'checkboxes', 'options' => ['a', 'b']]));

    expect($attributes['x-model'])->toBe('form.tags')
        ->and($attributes)->not->toHaveKey('x-model.fill');
});

it('binds a replicator subfield to its bare handle', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']]);

    $attributes = $driver->addToRenderableFieldAttributes(new Field('participants[0][is_minor]', ['type' => 'toggle']));

    expect($attributes['x-model'])->toBe('is_minor');
});

it('starts a date field holding the now sentinel at today', function (): void {
    app('statamic.fieldtypes')->put('date', PrecognitionSentinelDateFieldtype::class);
    Carbon::setTestNow(Carbon::parse('2026-03-05 12:00:00', 'UTC'));
    config(['statamic.system.display_timezone' => 'UTC']);

    $xData = precognitionXData(precognitionCspDriver(['when' => ['type' => 'date', 'default' => 'now']]));

    expect($xData)->toContain('&quot;when&quot;:&quot;2026-03-05&quot;');

    Carbon::setTestNow();
});

it('keys the x-data by the form scope when the driver was given no scope', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']]);

    $expression = new ReflectionMethod($driver, 'renderAlpineXData')->invoke($driver, ['a' => 1], null);

    expect($expression)->toStartWith("{form: \$form('post', '");
});

it('renders an empty object when the x-data cannot be encoded as JSON', function (): void {
    $driver = precognitionCspDriver(['name' => ['type' => 'text']]);

    $expression = new ReflectionMethod($driver, 'renderAlpineXData')->invoke($driver, ['a' => INF], 'form');

    expect($expression)->toEndWith(', {})}');
});
