<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use RuntimeException;

/**
 * Class ComponentResolver.
 */
final class ComponentResolver {
    /**
     * Method resolve.
     *
     * @param string $pluginroot Parameter pluginroot.
     * @return string Return value.
     */
    public function resolve(string $pluginroot): string {
        $versionfile = $pluginroot . '/version.php';
        if (is_file($versionfile)) {
            $contents = file_get_contents($versionfile);
            if ($contents === false) {
                throw new RuntimeException("Unable to read {$versionfile}");
            }

            if (preg_match('/\$plugin->component\s*=\s*([\'\"])([a-z][a-z0-9_]+)\1\s*;/', $contents, $match)) {
                return $match[2];
            }
        }

        $languagefiles = glob($pluginroot . '/lang/en/*.php') ?: [];
        if (count($languagefiles) === 1) {
            return basename($languagefiles[0], '.php');
        }

        throw new RuntimeException(
            'Unable to determine the Moodle component. Define $plugin->component in version.php ' .
            'or provide exactly one lang/en/*.php file.'
        );
    }
}
