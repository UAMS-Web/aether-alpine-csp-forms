Written against v0.3.3.

You have a field whose condition is `custom <name>`, and you want it to evaluate on a public form. By the end the field shows and hides on the page as it does in the Control Panel.

Before you start, finish [Installation](Installation.md): both scripts are loaded before Alpine.

## Why a public page needs this

Statamic's front-end evaluator looks a `custom` condition up with `Statamic.$conditions.get()`, but the front-end `$conditions` object defines only `showField()`. The add-on's magic supplies a `get()` that reads `window.StatamicCustomConditions`, so the registry is a plain object on `window`, keyed by condition name. A `get()` the evaluator already has is left alone.

## Steps

1. Define the registry in a script the page loads before the first conditional field is evaluated:

   ```html
   <script>
       window.StatamicCustomConditions = {
           isRene: ({ target }) => target === 'René',
       };
   </script>
   ```

   The function receives an object whose `target` is the current value of the field the condition reads, and returns a boolean.
2. In the blueprint, give the field a condition that names it, for example `custom isRene` on the `referrer` field.
3. Open the page. The conditional field shows when the function returns `true` and hides when it returns `false`.

**Note:** the registry is read each time a condition is evaluated, so it can be defined after `alpine-csp.js` loads.

**Warning:** a name with no entry in the registry hides the field, and the console reports that the condition was not properly defined. To show the field in that case, register a function that returns `true`.

## Reference

- [Driver reference](Driver-reference.md) for the magic and its failure modes.
- [Troubleshooting](Troubleshooting.md#a-field-with-a-custom-condition-stays-hidden) if the field stays hidden.
