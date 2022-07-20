# moodle-tool_dataflows

![GitHub Workflow Status (branch)](https://img.shields.io/github/workflow/status/catalyst/moodle-tool_dataflows/ci/MOODLE_35_STABLE)

## What is this?

Dataflows is a generic workflow and processing engine which can be configured to do a large variety of tasks.





## Branches

| Moodle version    | Branch           | PHP  |
|-------------------|------------------|------|
| Moodle 3.5+       | MOODLE_35_STABLE | 7.1+ |
| Totara 10+        | MOODLE_35_STABLE | 7.1+ |

## Alternatives

### tool_etl

This was our original plugin which was more tightly focus on just the ETL use case. Long term we expect
tool_etl to be deprecated in favor of tool_dataflows as it matures.

https://github.com/catalyst/moodle-tool_etl

### tool_trigger

Trigger is focused on a very narrow use case of handling a workflow which starts with a Moodle
event and has it's own workflow engine conceptually similar to dataflow. Dataflows will eventually
have an 'event trigger' step and so should be a complete super set of the tool_trigger functionality.

https://github.com/catalyst/moodle-tool_trigger/


## Best practices for workflows




## Installation

From Moodle siteroot:

```
git clone git@github.com:catalyst/moodle-tool_dataflows.git admin/tool/dataflows
```

## Dependencies

Make sure graphviz is installed

```
apt install graphviz
```

https://graphviz.org/documentation/
