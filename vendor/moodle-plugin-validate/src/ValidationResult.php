<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use JsonSerializable;

/**
 * Structured validation result shared by CLI and library consumers.
 */
final class ValidationResult implements JsonSerializable {
    /**
     * @param string $component Moodle Frankenstyle component name.
     * @param Check[] $checks Validation checks.
     */
    public function __construct(
        private readonly string $component,
        private readonly array $checks,
    ) {
    }

    /**
     * @return Check[]
     */
    public function checks(): array {
        return $this->checks;
    }

    /**
     * Method isSuccessful.
     *
     * @return bool Return value.
     */
    public function isSuccessful(): bool {
        foreach ($this->checks as $check) {
            if ($check->isError()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a stable structured representation suitable for JSON or direct library use.
     *
     * @return array{
     *     schema: int,
     *     component: string,
     *     success: bool,
     *     status: string,
     *     summary: array{total: int, ok: int, warnings: int, errors: int},
     *     groups: array<int, array{
     *         rule: string,
     *         status: string,
     *         summary: array{total: int, ok: int, warnings: int, errors: int},
     *         checks: array<int, array<string, mixed>>
     *     }>
     * }
     */
    public function toArray(): array {
        $summary = [
            'total' => 0,
            'ok' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];
        $groups = [];

        foreach ($this->checks as $check) {
            $rule = $this->normaliseRule($check);
            $status = $this->statusFor($check);

            if (!isset($groups[$rule])) {
                $groups[$rule] = [
                    'rule' => $rule,
                    'status' => 'ok',
                    'summary' => [
                        'total' => 0,
                        'ok' => 0,
                        'warnings' => 0,
                        'errors' => 0,
                    ],
                    'checks' => [],
                ];
            }

            $summary['total']++;
            $groups[$rule]['summary']['total']++;

            if ($status === 'error') {
                $summary['errors']++;
                $groups[$rule]['summary']['errors']++;
            } elseif ($status === 'warning') {
                $summary['warnings']++;
                $groups[$rule]['summary']['warnings']++;
            } else {
                $summary['ok']++;
                $groups[$rule]['summary']['ok']++;
            }

            $guidance = Guidance::for($check);

            $groups[$rule]['checks'][] = [
                'status' => $status,
                'rule' => $rule,
                'file' => $check->file,
                'line' => $check->line,
                'key' => $check->key,
                'target' => $check->target(),
                'message' => $check->message,
                'explanation' => $guidance['explanation'],
                'howToFix' => $guidance['howToFix'],
                'languageString' => $check->languageString,
            ];
        }

        foreach ($groups as &$group) {
            if ($group['summary']['errors'] > 0) {
                $group['status'] = 'error';
            } elseif ($group['summary']['warnings'] > 0) {
                $group['status'] = 'warning';
            }
        }
        unset($group);

        $status = 'ok';
        if ($summary['errors'] > 0) {
            $status = 'error';
        } elseif ($summary['warnings'] > 0) {
            $status = 'warning';
        }

        return [
            'schema' => 1,
            'component' => $this->component,
            'success' => $summary['errors'] === 0,
            'status' => $status,
            'summary' => $summary,
            'groups' => array_values($groups),
        ];
    }

    /**
     * Method jsonSerialize.
     *
     * @return array Return value.
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }

    /**
     * Method normaliseRule.
     *
     * @param Check $check Parameter check.
     * @return string Return value.
     */
    private function normaliseRule(Check $check): string {
        if ($check->rule !== '') {
            return $check->rule;
        }

        if (str_starts_with($check->key, 'xmldb:')) {
            return 'installxml';
        }

        return 'general';
    }

    /**
     * Method statusFor.
     *
     * @param Check $check Parameter check.
     * @return string Return value.
     */
    private function statusFor(Check $check): string {
        if ($check->isError()) {
            return 'error';
        }

        if ($check->isWarning()) {
            return 'warning';
        }

        return 'ok';
    }
}
