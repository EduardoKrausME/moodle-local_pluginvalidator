# Moodle local_pluginvalidator

A Moodle administrator interface for validating installed third-party plugins.

## Current scope

The first version intentionally exposes only one validation: `moodle-plugin-ci validate`.

Navigation:

1. Plugin types containing installed third-party extensions.
2. Plugins of the selected type.
3. Plugin details and available validators.
4. Run the validator and inspect its raw output.

Plugins shipped with Moodle are excluded using Moodle's own `plugininfo::is_standard()` result. The plugin can validate itself because `local_pluginvalidator` is an extension plugin.

## Validation engine

The preferred engine is the official `moodle-plugin-ci.phar` release from `moodlehq/moodle-plugin-ci`.

The administrator can install or update it from the plugin detail page. The PHAR is stored at:

`$CFG->dataroot/local_pluginvalidator/tools/moodle-plugin-ci.phar`

The updater reads GitHub's latest release metadata and verifies the downloaded file against the SHA-256 digest published in the release asset metadata when available.

An offline bundled fallback is also supported. Place the PHAR at:

`local/pluginvalidator/tools/moodle-plugin-ci.phar`

A downloaded engine takes precedence over a bundled one.

## Requirements

- Moodle 4.1 or newer.
- PHP `proc_open()` enabled to execute the validator.
- Moodle's PHP CLI path configured when the web PHP binary is not a CLI executable.
- Outbound HTTPS access to GitHub only when installing/updating the engine online.

## Security

Only users with `moodle/site:config` can access the interface or execute actions. Standard Moodle plugins cannot be selected through the UI or direct component parameter.
