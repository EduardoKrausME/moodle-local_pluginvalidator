# Moodle local_pluginvalidator

A Moodle administrator interface for validating installed third-party plugins.

## Validation engines

The plugin uses a validation engine interface and can expose multiple independent validators.

Currently registered engines:

- **Moodle Plugin Validate** — EduardoKrausME/moodle-plugin-validate. Loaded as a PHP library and returns structured results grouped by rule and individual check.
- **Moodle Plugin CI** — moodlehq/moodle-plugin-ci. The official PHAR is loaded as a PHP library and only its `validate` implementation is called directly. Its native result remains textual.

The engine contract explicitly distinguishes `structured` and `text` result formats. The UI renders each engine according to its native format instead of parsing textual output into artificial structured data.

Navigation:

1. Plugin types containing installed third-party extensions.
2. Plugins of the selected type.
3. Plugin details and available validators.
4. Run the validator and inspect structured results grouped by validation rule.

Plugins shipped with Moodle are excluded using Moodle's own plugininfo::is_standard() result. The plugin can validate itself because local_pluginvalidator is an extension plugin.

## Validation engine

Each validation engine manages its own upstream release and local installation.

For Moodle Plugin Validate, the release ZIP is extracted to:

    $CFG->dataroot/local_pluginvalidator/tools/moodle-plugin-validate

For Moodle Plugin CI, the official release PHAR is stored at:

    $CFG->dataroot/local_pluginvalidator/tools/moodle-plugin-ci/moodle-plugin-ci.phar

Neither integration starts a CLI process. Moodle Plugin Validate is called through `Validator::validateResult()`. Moodle Plugin CI is loaded from its PHAR autoloader and the PHP classes behind its `validate` command are instantiated directly.

Downloaded engines take precedence over optional bundled copies under `local/pluginvalidator/tools/`.

## Requirements

- Moodle 4.1 or newer.
- PHP 8.1 or newer for moodle-plugin-validate.
- Outbound HTTPS access to GitHub only when installing/updating the engine online.

PHP CLI, `proc_open()`, `exec()`, and `$CFG->pathtophp` are not required by the Moodle integration.

## Security

Only users with moodle/site:config can access the interface or execute actions. Standard Moodle plugins cannot be selected through the UI or direct component parameter.
