# Moodle local_pluginvalidator

A Moodle administrator interface for validating installed third-party plugins.

## Current scope

The first version intentionally exposes only one validation: EduardoKrausME/moodle-plugin-validate.

Navigation:

1. Plugin types containing installed third-party extensions.
2. Plugins of the selected type.
3. Plugin details and available validators.
4. Run the validator and inspect its raw output.

Plugins shipped with Moodle are excluded using Moodle's own plugininfo::is_standard() result. The plugin can validate itself because local_pluginvalidator is an extension plugin.

## Validation engine

The validation engine is the latest release of EduardoKrausME/moodle-plugin-validate.

The administrator can install or update it from the plugin detail page. Because the project does not publish a PHAR asset, local_pluginvalidator downloads GitHub's release ZIP, extracts the validator project, and stores it at:

    $CFG->dataroot/local_pluginvalidator/tools/moodle-plugin-validate

The executed command is equivalent to:

    php bin/moodle-string-validate /path/to/plugin

An offline bundled fallback is also supported. Place the validator project at:

    local/pluginvalidator/tools/moodle-plugin-validate

A downloaded engine takes precedence over a bundled one.

## Requirements

- Moodle 4.1 or newer.
- PHP CLI 8.1 or newer for moodle-plugin-validate.
- PHP proc_open() enabled to execute the validator.
- Moodle's PHP CLI path configured when the web PHP binary is not a CLI executable.
- Outbound HTTPS access to GitHub only when installing/updating the engine online.

## Security

Only users with moodle/site:config can access the interface or execute actions. Standard Moodle plugins cannot be selected through the UI or direct component parameter.
