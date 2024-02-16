# moodle-tool_dataflows

<a href="https://github.com/catalyst/moodle-tool_dataflows/actions">
<img src="https://github.com/catalyst/moodle-tool_dataflows/workflows/ci/badge.svg">
</a>

- [moodle-tool_dataflows](#moodle-tool_dataflows)
  - [What is this?](#what-is-this)
  - [Branches](#branches)
  - [Alternatives](#alternatives)
    - [tool_etl](#tool_etl)
    - [tool_trigger](#tool_trigger)
    - [local_webhooks](#local_webhooks)
  - [Installation](#installation)
    - [Dependencies](#dependencies)
  - [Configuration](#configuration)
    - [Best practices for workflows](#best-practices-for-workflows)
  - [Guides](#guides)
  - [Support](#support)
  - [Warm thanks](#warm-thanks)
    - [University College London](#university-college-london)
    - [NSW Department of Education](#nsw-department-of-education)

## What is this?

Dataflows is a generic workflow and processing engine which can be configured to do a large variety of tasks.


## Branches

| Moodle version    | Branch           | PHP       |
|-------------------|------------------|-----------|
| Moodle 4.1        | MOODLE_401_STABLE| 7.4 - 8.1 |
| Moodle 3.5+       | MOODLE_35_STABLE | 7.4 - 8.0 |
| Totara 10+        | MOODLE_35_STABLE | 7.1 - 7.4 |

## Alternatives

### tool_etl

This was our original plugin which was more tightly focus on just the ETL use case. Long term we expect
tool_etl to be deprecated in favor of tool_dataflows as it matures.

https://github.com/catalyst/moodle-tool_etl

### tool_trigger

Trigger is focused on a very narrow use case of handling a workflow which starts with a Moodle
event and has it's own workflow engine conceptually similar to dataflow. Dataflows has an
'event trigger' step and is super set of the tool_trigger functionality.

https://github.com/catalyst/moodle-tool_trigger/

### local_webhooks

This is very similar to tool_trigger in that can only work with moodle events and the only action
it can take is a curl call in a fairly specific shape. It does not support retries, timeouts and
cannot do asynchronous trigger so has a performance impact on pages which trigger events.

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

There are a few concepts to understand how the dataflows plugins works:

1) Dataflows, are a collections of Steps which perform a series of actions
2) There are 3 classes of steps 'Connector steps', 'Flow steps' and 'Trigger steps'
3) There are many types of steps in each class, eg curl connector, copy connector, directory read connector
4) A flow can have 0 or 1 Trigger step, and this is what starts the dataflow execution. eg you might have a 'Cron trigger', or an 'Event trigger'. If a dataflow does not have a Trigger step then it can only ever be run manually.
5) A Connector Step only ever runs once, for example a step which copies a file from A to B
6) A flow step is a step which runs in a loop over a stream of data. So you could have a flow step which make a curl call for every row in a csv file
7) Each type of step defines what inputs it accepts and what outputs it creates. It may have a 'connector' input, and a 'flow' output. For instance the various 'reader' steps are connectors that have an output of a 'flow', eg 'CSV reader', 'JSON reader', 'SQL reader'
8) Some triggers are also a flow step combined, for instance the event trigger can listen for events and buffer them and then trigger the flow to execute a series of events as a batch (it can also operate one at a time as well).
9) Almost all steps require configuration, such as the name of a file to read, or the url to curl
10) When authoring a dataflow you assemble all the steps together and link them into a graph of the execution order. Some steps can have multiple outputs like a unix 'tee' and some steps can have conditional multiple outputs like an 'if' or 'case' statement.
11) Each step can expose different variables when it executes and these are stored in its own step namespace so they don't clash.
12) When wiring steps together you can use any variable in expression written in the symphony expression language. For instance you could read a csv file which populates a flow record, and then use these values in a curl call to an api. Each step dynamically documents what variables it exposes.
13) The dataflow engine validates that the steps are all wired together in a way that makes sense, and you cannot run a dataflow if it is in an invalid state. But invalid states are allowed to ease the authoring process.
14) Dataflows can be enabled and disabled, and can be exported and imported and also locked after authoring so they cannot be tampered with.

The best way is to see some example flows in action. TBA add some fixture flows to repo


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

Thanks to various organisations for support in developing this plugin:

### University College London
![image](https://user-images.githubusercontent.com/187449/180128782-474fcdab-62c5-4848-ab6b-92ff4ece5d6f.png)

### NSW Department of Education
For sponsoring the development of the event trigger functionality.


![NSW DET](https://user-images.githubusercontent.com/17095477/201774199-aa1d2ce9-eccf-4aca-ab69-2fef75971ae1.png)

This plugin was developed by [Catalyst IT Australia](https://www.catalyst-au.net/).

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/MOODLE_39_STABLE/pix/catalyst-logo.svg" width="400">
