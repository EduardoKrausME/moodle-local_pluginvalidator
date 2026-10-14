<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class CacheRule.
 */
final class CacheRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'cache';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/caches.php';
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        foreach ($context->extractor->extract($file, 'definitions') as $definition) {
            $key = 'cachedef_' . $definition['key'];
            $checks[] = $context->checkRequiredString(
                $this->name(),
                $key,
                $file,
                $definition['line'],
                "required by cache definition '{$definition['key']}' in db/caches.php.",
            );
        }

        return $checks;
    }
}
