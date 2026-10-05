# Moodle local_pluginvalidator

A Moodle administrator interface for validating installed third-party plugins.

## Validation engines

The plugin exposes independent validation engines through a common interface.

Currently registered engines:

- **Moodle Plugin Validate** — EduardoKrausME/moodle-plugin-validate. Loaded as a PHP library and returns structured
  results grouped by rule and individual check.
- **Runtime execution** — a built-in validator that exercises installed plugin code inside Moodle instead of only
  inspecting source files.

The runtime engine now separates every result into three execution levels:

- **Executed** — the callback or Moodle API was actually invoked.
- **Contract validated** — class, callback, inheritance and signature were validated, but execution was intentionally skipped because it could write data, call external systems or trigger large side effects.
- **Not applicable** — the check does not apply or no realistic installed context exists.

Runtime coverage includes:

1. Activity backup plus a real backup/restore round-trip when `backup/moodle2` exists. The restored course module is temporary and is removed in cleanup.
2. External functions from `db/services.php`, including real execution of read-only calls that need no invented required parameters.
3. Activity `mod_form.php` instantiation through Moodle's normal module preparation path.
4. Standard `lib.php` callbacks such as `*_supports()`, `*_get_coursemodule_info()` and `*_get_file_areas()`; destructive callbacks and `pluginfile`/navigation callbacks receive contract validation only.
5. A small File API create/read/delete round-trip in a declared file area when a real module context exists.
6. Scheduled tasks from `db/tasks.php`: class loading, inheritance, `get_name()` and `execute()` signature. `execute()` is never called automatically.
7. Adhoc tasks: discovery, inheritance, instantiation and custom-data serialization without executing the task.
8. Event observers from `db/events.php` and hooks from `db/hooks.php`, validating event/hook classes and callback signatures without dispatching synthetic events.
9. `settings.php` inclusion against an isolated administration tree.
10. Privacy providers, including real `get_metadata()` execution for metadata providers.
11. Blocks and filters: block initialization/applicable formats, installed block `get_content()` when a real instance exists, and real text-filter execution.
12. Grade API for activity modules, including `*_grade_item_update()` and targeted `*_update_grades()` when an existing graded user makes the call safe.
13. Completion API, covering `FEATURE_COMPLETION_HAS_RULES`, modern `classes/completion/custom_completion.php`, custom rule definitions/descriptions/sort order/state evaluation, and legacy `*_get_completion_state()`.
14. Renderer instantiation, course-format smoke tests and lightweight authentication, enrolment, repository and question-type checks.

Operations with obvious production blast radius are deliberately not automatic: scheduled/adhoc task `execute()`, synthetic event or hook dispatch, enrolment mutations, repository listings that may contact remote services and other write-heavy callbacks are reported as **Contract validated** instead of pretending they were executed.

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
