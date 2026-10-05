# Moodle local_pluginvalidator

A Moodle administrator interface for validating installed third-party plugins.

## Validation engines

The plugin exposes independent validation engines through a common interface.

Currently registered engines:

- **Moodle Plugin Validate** — EduardoKrausME/moodle-plugin-validate. Loaded as a PHP library and returns structured
  results grouped by rule and individual check.
- **Runtime execution** — a built-in validator that exercises installed plugin code inside Moodle instead of only
  inspecting source files.

The runtime engine performs checks only when they apply to the selected plugin:

1. If `backup/moodle2` exists on an activity module, it locates an installed instance and executes a real
   `backup_controller` activity backup using `backup::MODE_IMPORT`. The temporary backup directory and controller
   are cleaned after the test.
2. If `db/services.php` exists, every declared external function is loaded through Moodle's own
   `external_api::external_function_info()`. This validates the implementation class and method, parameter contract,
   return contract, component registration and the record in `external_functions`.
3. For activity modules, `mod_form.php` is loaded and the expected `mod_<name>_mod_form` class is instantiated using
   the same `prepare_new_moduleinfo_data()` preparation used by Moodle's `modedit.php`, then populated with
   `set_data()`.

External service business methods are intentionally not called with invented parameters. A validator must not create
records, delete data, send messages or call third-party systems simply because an administrator clicked Validate.
The runtime check therefore executes the service metadata/contract methods that Moodle itself uses before dispatch,
while real backup execution and form construction are performed directly.

Navigation:

1. Plugin types containing installed third-party extensions.
2. Plugins of the selected type.
3. Plugin details and available validators.
4. Run a validator and inspect the structured result.

Plugins shipped with Moodle are excluded using Moodle's own `plugininfo::is_standard()` result. The plugin can validate
itself because `local_pluginvalidator` is an extension plugin.

## Moodle Plugin Validate engine

The external Moodle Plugin Validate engine manages its own upstream release and local installation. Its release ZIP is
extracted to:

    $CFG->dataroot/local_pluginvalidator/tools/moodle-plugin-validate

Downloaded versions take precedence over an optional bundled copy under `local/pluginvalidator/tools/`.

The Runtime execution engine is part of this plugin and does not require installation or updates of its own.

## Requirements

- Moodle 4.1 or newer.
- PHP 8.1 or newer for moodle-plugin-validate.
- Outbound HTTPS access to GitHub only when installing or updating Moodle Plugin Validate.

PHP CLI, `proc_open()`, `exec()`, and `$CFG->pathtophp` are not required.

## Security

Only users with `moodle/site:config` can access the interface or execute validations. Standard Moodle plugins cannot be
selected through the UI or direct component parameter.
