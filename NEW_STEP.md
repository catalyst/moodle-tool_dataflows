# Creating a new step type

This section will summarise the actions involved in creating a new step type and useful APIs you can use. This is mainly described in a checklist fashion.

<!-- #### Before creating the step -->
<!-- - [ ] Check to ensure the step type you want doesn't already exist. -->
<!-- - [ ] Next, consider if the step type belongs in the core dataflows plugin, or belongs in a separate plugin. -->

#### Creating the step class

Each new step type requires at least the following:
- [ ] Config fields `form_define_fields` - lists all the fields used as configuration and their properties
- [ ] Validation `validate_config` - called to validate the configuration stored, and on fail prevents the dataflow from running
- [ ] Execution method `execute` - the heart of the step. This performs the action of the step.
- [ ] Custom form inputs method `form_add_custom_inputs` - allows you to define and customise the configuration UI as Moodle form inputs (with tooltips and help text).
- [ ] Unit tests - e.g. demonstrating what it should be able to do.
- [ ] Relevant lang strings added in `lang/en/tool_dataflows.php`

#### Wiring together the action in `execute()`

Several convenience methods have been added to make this process a bit easier. You have:

- `$this->get_config()` which returns the configuration of the step, as an **object**, with all the expressions already evaluated.
- `$this->is_dry_run()` which returns whether the dataflow was executed under dry run mode.
- `$this->log()` which allows you to log a message during execution which if run directly would print the message to the user.
- Variables can be exposed when your step gets some new data which you want to make available in other steps. - See [variables](./VARIABLES.md) 

For the general gist of how things are structured, please look at the existing examples:
- [connector_wait step](./classes/local/step/connector_wait.php)
- [connector_curl step](./classes/local/step/connector_curl.php)

There are some differences between flow, reader and connector steps that may need to be taken into account.

