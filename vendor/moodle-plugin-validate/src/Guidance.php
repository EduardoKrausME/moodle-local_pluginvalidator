<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Provides human-readable guidance for validation findings.
 *
 * Guidance is intentionally separated from rule execution so older rules can
 * immediately produce useful structured explanations without duplicating text
 * across every rule implementation.
 */
final class Guidance {
    /**
     * @return array{explanation: string, howToFix: string}
     */
    public static function for(Check $check): array {
        if (!$check->isError() && !$check->isWarning()) {
            return [
                'explanation' => '',
                'howToFix' => '',
            ];
        }

        $message = $check->message;

        if ($check->rule === 'amd') {
            return [
                'explanation' =>
                    'Moodle loads plugin AMD modules from the compiled files in amd/build. A missing build, a '
                    . 'duplicated .min.min.js name, or a literal PHP/Mustache reference to a module that does not '
                    . 'exist can turn into a RequireJS load failure at runtime.',
                'howToFix' =>
                    'Keep every amd/src/<module>.js paired with amd/build/<module>.min.js, remove any accidental '
                    . '*.min.min.js files, and correct js_call_amd() or Mustache require() references so they point '
                    . 'to an AMD module provided by this plugin. Rebuild AMD output after changing source files.',
            ];
        }

        if ($check->rule === 'mustacheurl') {
            return [
                'explanation' =>
                    'Moodle URL values passed to Mustache are already intended for direct URL output. '
                    . 'Rendering an href or src with double braces applies Mustache HTML escaping and can '
                    . 'change URL query separators such as "&". Moodle templates therefore use triple braces '
                    . 'for URL values in href and src attributes.',
                'howToFix' =>
                    'Change the URL expression in the reported attribute from double braces to triple braces. '
                    . 'For example, use href="{{{url}}}" instead of href="{{{url}}}".',
            ];
        }

        if ($check->rule === 'languagefile' || str_contains($message, 'Missing base language file')) {
            return [
                'explanation' =>
                    'Every Moodle plugin needs a base English language file. Moodle uses it as the canonical '
                    . 'source for plugin strings even when the site is displayed in another language.',
                'howToFix' =>
                    'Create the reported lang/en/*.php file and define at least $string[\'pluginname\']. '
                    . 'For activity modules, the file name is the module name without the "mod_" prefix.',
            ];
        }

        if ($check->rule === 'version') {
            if (str_contains($message, 'Missing required version.php')) {
                return [
                    'explanation' =>
                        'Moodle uses version.php to identify the plugin, determine its installed version and '
                        . 'decide whether installation or upgrade steps are required.',
                    'howToFix' =>
                        'Create version.php in the plugin root and define $plugin->component and a positive '
                        . 'numeric $plugin->version. Also define the supported Moodle version metadata used by '
                        . 'your plugin.',
                ];
            }

            if (str_contains($message, '$plugin->component')) {
                return [
                    'explanation' =>
                        'The Frankenstyle component in version.php must match the plugin type and directory. '
                        . 'Moodle uses this identifier for installation, strings, capabilities and dependencies.',
                    'howToFix' =>
                        'Set $plugin->component in version.php to the expected component shown in the error. '
                        . 'Do not rename only the directory or only the component; both must describe the same plugin.',
                ];
            }

            if (str_contains($message, '$plugin->version')) {
                return [
                    'explanation' =>
                        'Moodle compares $plugin->version with the version stored in the database to determine '
                        . 'whether upgrade.php steps and other installation changes must run.',
                    'howToFix' =>
                        'Define $plugin->version as a positive integer in version.php, normally using a value '
                        . 'such as YYYYMMDDXX, and increment it whenever an upgrade is required.',
                ];
            }
        }

        if ($check->rule === 'pluginname') {
            return [
                'explanation' =>
                    'The pluginname language string is the canonical human-readable name Moodle uses in plugin '
                    . 'administration and other interfaces.',
                'howToFix' =>
                    'Add a non-empty $string[\'pluginname\'] entry to the base English language file reported '
                    . 'by the validator.',
            ];
        }

        if ($check->rule === 'get_string') {
            return [
                'explanation' =>
                    'The source code calls get_string() with a literal key for this plugin, but that key is not '
                    . 'available in the base English language file. At runtime Moodle can display a missing-string '
                    . 'placeholder instead of the intended text.',
                'howToFix' =>
                    'Add the reported $string key to the plugin base English language file, or correct the '
                    . 'get_string() key/component if the call points to the wrong string.',
            ];
        }

        if ($check->rule === 'translationplaceholder') {
            return [
                'explanation' =>
                    'Translated strings must preserve the same {$a} or {$a->property} placeholders as the base '
                    . 'English string. A mismatch can display incorrect text or leave substitution placeholders '
                    . 'unresolved at runtime.',
                'howToFix' =>
                    'Compare the reported translation with the English string and make their placeholders match '
                    . 'exactly. The surrounding translated text may differ, but placeholder names must not.',
            ];
        }

        if ($check->rule === 'moodle_exception') {
            return [
                'explanation' =>
                    'Moodle exceptions reference language-string keys. If the corresponding string is missing, '
                    . 'users see an invalid or missing error message instead of a useful exception description.',
                'howToFix' =>
                    'Create the reported language string in the plugin base English language file, or correct the '
                    . 'exception string key/component to reference an existing string.',
            ];
        }

        if ($check->rule === 'installxml' || str_starts_with($check->key, 'xmldb:')) {
            return [
                'explanation' =>
                    'db/install.xml defines the database schema Moodle creates when the plugin is installed. '
                    . 'Invalid fields, keys, indexes or activity-module columns can make installation fail or '
                    . 'produce a schema that Moodle APIs do not expect.',
                'howToFix' =>
                    'Correct the exact XMLDB item named in the error. Prefer Moodle\'s XMLDB editor to modify or '
                    . 'regenerate db/install.xml, and keep required id, primary key, field types and module table '
                    . 'fields consistent with Moodle XMLDB conventions.',
            ];
        }

        if ($check->rule === 'installxmlheader') {
            return [
                'explanation' =>
                    'The XMLDB header carries schema metadata required for Moodle to recognise the install.xml '
                    . 'definition consistently.',
                'howToFix' =>
                    'Open db/install.xml in Moodle\'s XMLDB editor and save it again, or correct the reported '
                    . 'XMLDB header attribute to the format expected by Moodle.',
            ];
        }

        if ($check->rule === 'mod_course_contents' || $check->rule === 'mod_supports') {
            if (str_contains($message, 'get_coursemodule_info')) {
                return [
                    'explanation' =>
                        'get_coursemodule_info() is cached by Moodle and accepts only a defined set of fields and '
                        . 'types. Unsupported properties or values may be ignored or break course-module rendering.',
                    'howToFix' =>
                        'Change or remove the reported property so the returned cached_cm_info/stdClass contains '
                        . 'only supported fields with the expected type. Declare module purpose through '
                        . 'FEATURE_MOD_PURPOSE rather than as an arbitrary cached field.',
                ];
            }

            if (str_contains($message, 'FEATURE_GROUPMEMBERSONLY')) {
                return [
                    'explanation' =>
                        'FEATURE_GROUPMEMBERSONLY is deprecated and Moodle no longer accepts it as a supported '
                        . 'module feature.',
                    'howToFix' =>
                        'Remove the FEATURE_GROUPMEMBERSONLY branch from the module supports callback and use '
                        . 'current Moodle availability/group mechanisms instead.',
                ];
            }

            if (str_contains($message, 'default return') || str_contains($message, 'fallback return')) {
                return [
                    'explanation' =>
                        'The module supports callback receives features with different expected return types. A '
                        . 'generic non-null fallback can accidentally return a boolean where Moodle expects an '
                        . 'archetype or purpose value.',
                    'howToFix' =>
                        'Return explicit values for known FEATURE_* cases and return null for unknown or unmatched '
                        . 'features.',
                ];
            }

            if (str_contains($message, 'FEATURE_')) {
                return [
                    'explanation' =>
                        'Each Moodle FEATURE_* constant has a defined return type. Returning a value of another '
                        . 'type can make core interpret the module capability incorrectly.',
                    'howToFix' =>
                        'Change the reported FEATURE_* branch to return the type or Moodle constant stated in the '
                        . 'error message, or null when the plugin does not declare support.',
                ];
            }
        }

        if ($check->rule === 'mod_backup_restore') {
            return [
                'explanation' =>
                    'Activity modules that support Moodle backup must implement the expected backup and restore '
                    . 'classes, naming and structure so courses can be exported and restored safely.',
                'howToFix' =>
                    'Correct the reported backup/restore class, file, method or structure to match the Moodle '
                    . 'backup API conventions for the module. If the activity does not support backup, ensure the '
                    . 'plugin does not incorrectly advertise that support.',
            ];
        }

        if ($check->rule === 'dbreferences') {
            return [
                'explanation' =>
                    'The validator found a database reference that does not match the plugin schema or Moodle DB '
                    . 'API conventions. This can cause runtime database errors or invalid cross-table assumptions.',
                'howToFix' =>
                    'Review the reported table, field or database call and make it match db/install.xml and the '
                    . 'Moodle database API. Avoid hard-coded table prefixes and references to fields that do not exist.',
            ];
        }

        if ($check->rule === 'externalapi') {
            return [
                'explanation' =>
                    'Moodle external functions have a strict API contract for parameters, return values and the '
                    . 'implementing class. Incorrect declarations can break AJAX/mobile/web-service calls.',
                'howToFix' =>
                    'Make the reported external function declaration match Moodle\'s external API conventions, '
                    . 'including parameters(), returns() and execute() definitions and their declared data types.',
            ];
        }

        if ($check->rule === 'subplugin') {
            return [
                'explanation' =>
                    'Subplugin declarations determine where Moodle discovers nested plugin types and how their '
                    . 'components and language strings are resolved. Inconsistent declarations prevent correct discovery.',
                'howToFix' =>
                    'Correct db/subplugins.json and the reported subplugin directory/component so legacy and modern '
                    . 'paths, component names and required subplugintype strings all describe the same structure.',
            ];
        }

        if ($check->rule === 'access') {
            return [
                'explanation' =>
                    'Capabilities declared in db/access.php must have matching language strings so Moodle can show '
                    . 'meaningful capability names in role and permission administration.',
                'howToFix' =>
                    'Add the reported capability language string to lang/en, or correct the capability name if '
                    . 'db/access.php contains the wrong component/capability identifier.',
            ];
        }

        if ($check->rule === 'messageprovider') {
            return [
                'explanation' =>
                    'Message providers declared by the plugin require language strings so Moodle can identify them '
                    . 'in notification preferences and messaging administration.',
                'howToFix' =>
                    'Add the required messageprovider:* language string for the provider reported by the validator.',
            ];
        }

        if ($check->rule === 'cache') {
            return [
                'explanation' =>
                    'Cache definitions exposed by the plugin require matching language strings for administration '
                    . 'and diagnostics.',
                'howToFix' =>
                    'Add the reported cachedef_* language string to the base English language file or correct the '
                    . 'cache definition name.',
            ];
        }

        if ($check->rule === 'privacy') {
            return [
                'explanation' =>
                    'Moodle privacy providers must expose the metadata language strings they reference. Missing '
                    . 'strings make privacy/export descriptions incomplete or invalid.',
                'howToFix' =>
                    'Add the reported privacy:* string to the base English language file or update the privacy '
                    . 'provider to reference the correct existing key.',
            ];
        }

        if ($check->rule === 'repository') {
            return [
                'explanation' =>
                    'Repository-level metadata such as LICENSE and README is part of a distributable Moodle plugin '
                    . 'package and is expected by validation and publication workflows.',
                'howToFix' =>
                    'Add or correct the missing file in the plugin repository root using a supported standard file name.',
            ];
        }

        if ($check->rule === 'ajax') {
            return [
                'explanation' =>
                    'Direct PHP AJAX endpoints bypass Moodle\'s standard external-function/AJAX service layer and '
                    . 'can make capability checks, parameter validation and maintenance less consistent.',
                'howToFix' =>
                    'Where practical, replace the direct endpoint with a Moodle external function called through '
                    . 'core/ajax. Keep dedicated PHP endpoints only when the use case genuinely requires one, such '
                    . 'as multipart uploads.',
            ];
        }

        if ($check->rule === 'javascript') {
            return [
                'explanation' =>
                    'Large literal HTML fragments in JavaScript are harder to translate, maintain and review, and '
                    . 'bypass Moodle\'s Mustache rendering conventions.',
                'howToFix' =>
                    'Move the reported HTML fragment into a Mustache template and render it from the AMD module '
                    . 'using Moodle\'s template APIs.',
            ];
        }

        return [
            'explanation' =>
                'This validation rule detected code or metadata that does not match the Moodle plugin contract it checks. '
                . 'Leaving it unchanged may cause installation, runtime, compatibility or maintenance problems.',
            'howToFix' =>
                'Open the reported file and line, use the error message and target as the exact failing condition, '
                . 'and change the implementation so it satisfies the Moodle convention checked by this rule.',
        ];
    }
}
