<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class PluginNameRule.
 */
final class PluginNameRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'pluginname';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $source = is_file($context->pluginroot . '/version.php')
            ? $context->pluginroot . '/version.php'
            : $context->catalog->file();

        $checks = [$context->checkRequiredString(
            $this->name(),
            'pluginname',
            $source,
            1,
            'every Moodle plugin must define its display name in the base language file.',
        )];

        if (str_starts_with($context->component, 'mod_')) {
            $checks[] = $context->checkRequiredString(
                $this->name(),
                'pluginadministration',
                $source,
                1,
                'activity modules must define pluginadministration in the English language file.',
            );
        }

        return $checks;
    }
}
