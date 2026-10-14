<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class MessageProviderRule.
 */
final class MessageProviderRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'messageprovider';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/messages.php';
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        foreach ($context->extractor->extract($file, 'messageproviders') as $provider) {
            $key = 'messageprovider:' . $provider['key'];
            $checks[] = $context->checkRequiredString(
                $this->name(),
                $key,
                $file,
                $provider['line'],
                "required by message provider '{$provider['key']}' in db/messages.php.",
            );
        }

        return $checks;
    }
}
