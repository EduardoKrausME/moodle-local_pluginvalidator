<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Class Issue.
 */
final class Issue {
    /**
     * Method __construct.
     *
     * @param string $rule Parameter rule.
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $key Parameter key.
     * @param string $message Parameter message.
     */
    public function __construct(
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $key,
        public readonly string $message,
    ) {
    }
}
