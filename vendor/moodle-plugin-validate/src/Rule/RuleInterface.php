<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Interface RuleInterface.
 */
interface RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string;

    /** @return Check[] */
    public function validate(ValidationContext $context): array;
}
