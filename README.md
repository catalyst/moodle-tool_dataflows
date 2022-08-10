# moodle-tool_dataflows

![GitHub Workflow Status (branch)](https://img.shields.io/github/workflow/status/catalyst/moodle-tool_dataflows/ci/MOODLE_35_STABLE)

* [What is this?](#what-is-this)
* [Branches](#branches)
* [Alternatives](#alternatives)
* [Installation](#installation)
* [Configuration](#configuration)
* [Guides](#guides)
* [Support](#support)
* [Warm thanks](#warm-thanks)

## What is this?

Dataflows is a generic workflow and processing engine which can be configured to do a large variety of tasks.


## Branches

| Moodle version    | Branch           | PHP       |
|-------------------|------------------|-----------|
| Moodle 3.5+       | MOODLE_35_STABLE | 7.1 - 7.4 |
| Totara 10+        | MOODLE_35_STABLE | 7.1 - 7.4 |

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

### local_webhooks

This is very similar to tool_trigger in that can only work with moodle events and the only action
it can take is a curl call in a fairly specific shape. It does not support retries, timeouts and
cannot do asynconous trigger so has a performance impact on pages which trigger events.

https://github.com/valentineus/moodle-webhooks


## Installation

From Moodle siteroot:

```
git clone git@github.com:catalyst/moodle-tool_dataflows.git admin/tool/dataflows
```

### Dependencies

Make sure graphviz is installed

```
apt install graphviz
```

https://graphviz.org/documentation/

## Configuration

### Best practices for workflows

## Guides

* [Creating a new step type](./NEW_STEP.md)


## Support

If you have issues please log them in
[GitHub](https://github.com/catalyst/moodle-tool_dataflows/issues).

Please note our time is limited, so if you need urgent support or want to
sponsor a new feature then please contact
[Catalyst IT Australia](https://www.catalyst-au.net/contact-us).


## Warm thanks

Thanks to various orgnisations for support in developing this plugin:

### University College London
![image](https://user-images.githubusercontent.com/187449/180128782-474fcdab-62c5-4848-ab6b-92ff4ece5d6f.png)


This plugin was developed by [Catalyst IT Australia](https://www.catalyst-au.net/).

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/MOODLE_39_STABLE/pix/catalyst-logo.svg" width="400">
