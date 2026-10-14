<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class PrivacyRule.
 */
final class PrivacyRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'privacy';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/classes/privacy/provider.php';
        if (!is_file($file)) {
            return [new Check(true, $this->name(), 'classes/privacy/provider.php', 1, '', 'No Privacy API provider file is present.')];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return [new Check(false, $this->name(), 'classes/privacy/provider.php', 1, '', 'Unable to read classes/privacy/provider.php.')];
        }

        $checks = [];
        $seen = [];
        foreach (token_get_all($contents) as $token) {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $value = $this->decodeLiteral($token[1]);
            if (!str_starts_with($value, 'privacy:') || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $checks[] = $context->checkRequiredString(
                $this->name(),
                $value,
                $file,
                $token[2],
                'referenced by the Privacy API provider.',
            );
        }

        if ($checks === []) {
            $checks[] = new Check(
                true,
                $this->name(),
                'classes/privacy/provider.php',
                1,
                '',
                'Privacy API provider exists but contains no literal privacy:* language keys to validate.',
            );
        }

        return $checks;
    }

    /**
     * Method decodeLiteral.
     *
     * @param string $literal Parameter literal.
     * @return string Return value.
     */
    private function decodeLiteral(string $literal): string {
        $quote = $literal[0] ?? '';
        $value = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }
}
