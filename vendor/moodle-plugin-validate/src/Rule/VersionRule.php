<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpPluginMetadata;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class VersionRule.
 */
final class VersionRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'version';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/version.php';
        if (!is_file($file)) {
            return [new Check(false, $this->name(), 'version.php', 1, '', 'Missing required version.php in project root.')];
        }

        $metadata = new PhpPluginMetadata($file);
        $checks = [new Check(true, $this->name(), 'version.php', 1, '', 'version.php exists in project root.')];

        $component = $metadata->component();
        if ($component === null) {
            $checks[] = new Check(false, $this->name(), 'version.php', 1, '', 'Missing $plugin->component in version.php.');
        } elseif ($component !== $context->component) {
            $checks[] = new Check(
                false,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->component'),
                '',
                "Invalid \$plugin->component '{$component}'. Expected '{$context->component}'.",
            );
        } else {
            $checks[] = new Check(
                true,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->component'),
                '',
                "Plugin component is correctly configured as '{$component}'.",
            );
        }

        $version = $metadata->version();
        if ($version === null || $version <= 0) {
            $checks[] = new Check(false, $this->name(), 'version.php', 1, '', 'Missing or invalid numeric $plugin->version in version.php.');
        } else {
            $checks[] = new Check(
                true,
                $this->name(),
                'version.php',
                $metadata->lineFor('$plugin->version'),
                '',
                "Plugin version is configured: {$version}.",
            );
        }

        return $checks;
    }
}
