# Variables in Dataflows

## Inside of a step
### Setting a variable
```
$variables = $this->get_variables();
$variables->set('event', $input->eventdata);
```

### Caveats
- Variables can only be written inside of a step's `execute` function. You can't 'set' variables before the step is running such as in the step definition.

## Usage in a dataflow
After exposing a variable inside of a step's `execute function`, it **will not** automatically display inside the step form UI or debugging output, as the data could vary at runtime.

Because of the iterator setup, variables will only show up if requested further down the chain.

For examples, If I had a event trigger step called `moodle_event_trigger` that called `$variables->set('event', $data);`

I **must** add `courseid: ${{ event.courseid }}` to the `Variables` field (under extra settings) for it to show up in the variable tree. Then any other step can access it via either:

`{{steps.moodle_event_trigger.vars.courseid}}`

OR

`{{steps.moodle_event_trigger.event.courseid}}`

The latter option is only available when the variable if asked for by some step, since then the config 'knows' what the shape of the data will be.

### Example
A step has the following execute function to expose the `event` variable
```
public function execute($input = null) {
    $variables = $this->get_variables();
    $variables->set('event', $input->eventdata);
    return true;
}
```
We then create a dataflow and configure the step called `course_viewed` with the following `Variables` config:

![image](https://user-images.githubusercontent.com/17095477/201774207-c478076f-c43c-4781-ad52-2d20ced6a356.png)

Then in a later step, we can access it either from the `{{steps.course_viewed.event.courseid}}` or `{{steps.course_viewed.vars.courseid}}`

![image](https://user-images.githubusercontent.com/17095477/201774176-5c347567-2362-4397-aebe-00924ea0b6dc.png)

This is because when we specify `courseid: ${{ event.courseid }}`, the dataflows engine then assumes that a variable `event` is exposed with the property `courseid` from that step, because we have told it so in the `course_viewed` step definition.