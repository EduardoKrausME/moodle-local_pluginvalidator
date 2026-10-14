<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

/**
 * Class PhpSourceInspector.
 */
final class PhpSourceInspector {
    /**
     * Method resolveClassFile.
     *
     * @param ValidationContext $context Parameter context.
     * @param string $classname Parameter classname.
     * @param ?string $classpath Parameter classpath.
     * @return ?string Return value.
     */
    public function resolveClassFile(ValidationContext $context, string $classname, ?string $classpath = null): ?string {
        if ($classpath !== null && $classpath !== '') {
            $path = $this->resolveFileReference($context, $classpath);
            if ($path !== null) {
                return $path;
            }
        }

        $classname = ltrim($classname, "\\");
        $prefix = $context->component . "\\";
        if (!str_starts_with($classname, $prefix)) {
            return null;
        }

        $relative = substr($classname, strlen($prefix));
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $context->pluginroot . '/classes/' . str_replace('\\', '/', $relative) . '.php';
    }

    /**
     * Method resolveImportedClass.
     *
     * @param string $file Parameter file.
     * @param string $classname Parameter classname.
     * @return string Return value.
     */
    public function resolveImportedClass(string $file, string $classname): string {
        $classname = ltrim($classname, '\\');
        if ($classname === '' || !is_file($file)) {
            return $classname;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return $classname;
        }

        [$alias, $suffix] = array_pad(explode('\\', $classname, 2), 2, null);
        if ($alias === '') {
            return $classname;
        }

        if (preg_match_all(
            '/^\s*use\s+(?!function\b|const\b)([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/mi',
            $source,
            $matches,
            PREG_SET_ORDER,
        ) !== false) {
            foreach ($matches as $match) {
                $import = ltrim($match[1], '\\');
                $importalias = $match[2] ?? '';
                if ($importalias === '') {
                    $separator = strrpos($import, '\\');
                    $importalias = $separator === false ? $import : substr($import, $separator + 1);
                }

                if (strcasecmp($alias, $importalias) !== 0) {
                    continue;
                }

                return $suffix === null || $suffix === '' ? $import : $import . '\\' . $suffix;
            }
        }

        return $classname;
    }

    /**
     * Method resolveFileReference.
     *
     * @param ValidationContext $context Parameter context.
     * @param string $reference Parameter reference.
     * @return ?string Return value.
     */
    public function resolveFileReference(ValidationContext $context, string $reference): ?string {
        $reference = trim(str_replace('\\', '/', $reference));
        if ($reference === '' || str_contains($reference, '..')) {
            return null;
        }

        $reference = preg_replace('/^\$CFG->dirroot\s*\.\s*/', '', $reference) ?? $reference;
        $reference = trim($reference, " \t\n\r\0\x0B'\"");
        $reference = ltrim($reference, '/');

        $segments = explode('/', $reference);
        for ($offset = 0, $count = count($segments); $offset < $count; $offset++) {
            $candidate = $context->pluginroot . '/' . implode('/', array_slice($segments, $offset));
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{name:string,extends:string,line:int,methods:array<string,array{line:int,code:string}>}|null */
    public function classInfo(string $file, string $classname): ?array {
        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i + 1);
                continue;
            }
            if (!is_array($token) || $token[0] !== T_CLASS || $this->isAnonymousClass($tokens, $i)) {
                continue;
            }

            $nameindex = $this->nextTokenOfType($tokens, $i + 1, T_STRING);
            if ($nameindex === null) {
                continue;
            }

            $shortname = $tokens[$nameindex][1];
            $fqcn = $namespace === '' ? $shortname : $namespace . '\\' . $shortname;
            $target = ltrim($classname, '\\');
            if (strcasecmp($target, $fqcn) !== 0 && !(strpos($target, '\\') === false && strcasecmp($target, $shortname) === 0)) {
                continue;
            }

            $open = $this->findOpeningBrace($tokens, $nameindex + 1);
            if ($open === null) {
                return null;
            }
            $close = $this->matchingToken($tokens, $open, '{', '}');
            if ($close === null) {
                return null;
            }

            $extends = $this->readExtends($tokens, $nameindex + 1, $open);
            $classcode = $this->tokensToString(array_slice($tokens, $i, $close - $i + 1));

            return [
                'name' => $fqcn,
                'extends' => $extends,
                'line' => $token[2],
                'methods' => $this->methodsFromClassCode($classcode, $token[2]),
            ];
        }

        return null;
    }

    /** @return array{line:int,code:string}|null */
    public function functionInfo(string $file, string $functionname): ?array {
        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }
        $tokens = token_get_all($source);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $nameindex = $this->nextTokenOfType($tokens, $i + 1, T_STRING);
            if ($nameindex === null || $tokens[$nameindex][1] !== $functionname) {
                continue;
            }
            return $this->captureFunction($tokens, $i, 0);
        }
        return null;
    }

    /**
     * Method functionExists.
     *
     * @param string $file Parameter file.
     * @param string $functionname Parameter functionname.
     * @return bool Return value.
     */
    public function functionExists(string $file, string $functionname): bool {
        return $this->functionInfo($file, $functionname) !== null;
    }

    /** @return array<int,array{line:int,code:string}> */
    public function arrayEntries(string $file, string $variablename): array {
        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $target = '$' . ltrim($variablename, '$');
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!$this->isToken($tokens[$i], T_VARIABLE, $target)) {
                continue;
            }
            $equals = $this->nextSignificant($tokens, $i + 1);
            $start = $equals === null ? null : $this->nextSignificant($tokens, $equals + 1);
            if ($equals === null || $tokens[$equals] !== '=' || $start === null) {
                continue;
            }
            $open = $this->arrayOpeningIndex($tokens, $start);
            if ($open !== null) {
                return $this->childArrayEntries($tokens, $open);
            }
        }

        return [];
    }

    /**
     * Method arraySource.
     *
     * @param string $file Parameter file.
     * @param string $variablename Parameter variablename.
     * @return ?string Return value.
     */
    public function arraySource(string $file, string $variablename): ?string {
        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $target = '$' . ltrim($variablename, '$');
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!$this->isToken($tokens[$i], T_VARIABLE, $target)) {
                continue;
            }
            $equals = $this->nextSignificant($tokens, $i + 1);
            $start = $equals === null ? null : $this->nextSignificant($tokens, $equals + 1);
            if ($equals === null || $tokens[$equals] !== '=' || $start === null) {
                continue;
            }
            $open = $this->arrayOpeningIndex($tokens, $start);
            if ($open === null) {
                continue;
            }
            $close = $this->matchingToken($tokens, $open, (string)$tokens[$open], $tokens[$open] === '[' ? ']' : ')');
            if ($close !== null) {
                return $this->tokensToString(array_slice($tokens, $open, $close - $open + 1));
            }
        }
        return null;
    }

    /**
     * Method literalValue.
     *
     * @param string $code Parameter code.
     * @param string $key Parameter key.
     * @return ?string Return value.
     */
    public function literalValue(string $code, string $key): ?string {
        $key = preg_quote($key, '/');
        if (preg_match('/[\'\"]' . $key . '[\'\"]\s*=>\s*([\'\"])((?:\\\\.|(?!\1).)*)\1/sU', $code, $match) === 1) {
            return $this->decodePhpStringLiteral($match[2], $match[1]);
        }
        if (preg_match('/[\'\"]' . $key . '[\'\"]\s*=>\s*([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)::class/', $code, $match) === 1) {
            return ltrim($match[1], '\\');
        }
        return null;
    }

    /** @return array{class:string,method:string}|null */
    public function callbackValue(string $code, string $key = 'callback'): ?array {
        $literal = $this->literalValue($code, $key);
        if ($literal !== null && str_contains($literal, '::')) {
            [$class, $method] = explode('::', $literal, 2);
            return ['class' => ltrim($class, '\\'), 'method' => $method];
        }

        $key = preg_quote($key, '/');
        if (preg_match('/[\'\"]' . $key . '[\'\"]\s*=>\s*\[\s*([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)::class\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*\]/s', $code, $match) === 1) {
            return ['class' => ltrim($match[1], '\\'), 'method' => $match[2]];
        }
        return null;
    }

    /** @return array<string,string> */
    public function literalStringMap(string $code): array {
        $map = [];
        if (preg_match_all('/([\'\"])((?:\\\\.|(?!\1).)*)\1\s*=>\s*([\'\"])((?:\\\\.|(?!\3).)*)\3/sU', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $this->decodePhpStringLiteral($match[2], $match[1]);
                $value = $this->decodePhpStringLiteral($match[4], $match[3]);
                $map[$key] = $value;
            }
        }
        return $map;
    }

    /**
     * Method decodePhpStringLiteral.
     *
     * @param string $value Parameter value.
     * @param string $quote Parameter quote.
     * @return string Return value.
     */
    private function decodePhpStringLiteral(string $value, string $quote): string {
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }

    /**
     * Method readNamespace.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @return string Return value.
     */
    private function readNamespace(array $tokens, int $start): string {
        $name = '';
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $name .= $token[1];
            }
        }
        return trim($name, '\\');
    }

    /**
     * Method readExtends.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @param int $end Parameter end.
     * @return string Return value.
     */
    private function readExtends(array $tokens, int $start, int $end): string {
        for ($i = $start; $i < $end; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_EXTENDS) {
                continue;
            }
            $name = '';
            for ($j = $i + 1; $j < $end; $j++) {
                $token = $tokens[$j];
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $name .= $token[1];
                    continue;
                }
                if (is_array($token) && $token[0] === T_WHITESPACE) {
                    if ($name !== '') {
                        break;
                    }
                    continue;
                }
                if ($name !== '') {
                    break;
                }
            }
            return ltrim($name, '\\');
        }
        return '';
    }

    /** @return array<string,array{line:int,code:string}> */
    private function methodsFromClassCode(string $source, int $classline): array {
        $tokens = token_get_all("<?php\n" . $source);
        $methods = [];
        $depth = 0;
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '{' || $this->isCurlyInterpolationOpen($token)) {
                $depth++;
                continue;
            }
            if ($token === '}') {
                $depth--;
                continue;
            }
            if ($depth !== 1 || !is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $nameindex = $this->nextTokenOfType($tokens, $i + 1, T_STRING);
            if ($nameindex !== null) {
                $info = $this->captureFunction($tokens, $i, $classline - 1);
                if ($info !== null) {
                    $methods[$tokens[$nameindex][1]] = $info;
                }
            }
        }
        return $methods;
    }

    /** @return array{line:int,code:string}|null */
    private function captureFunction(array $tokens, int $start, int $lineoffset): ?array {
        $open = $this->findOpeningBrace($tokens, $start + 1);
        if ($open === null) {
            return null;
        }
        $close = $this->matchingToken($tokens, $open, '{', '}');
        if ($close === null) {
            return null;
        }
        return [
            'line' => max(1, $tokens[$start][2] + $lineoffset),
            'code' => $this->tokensToString(array_slice($tokens, $start, $close - $start + 1)),
        ];
    }

    /** @return array<int,array{line:int,code:string}> */
    private function childArrayEntries(array $tokens, int $outeropen): array {
        $outerclose = $this->matchingToken($tokens, $outeropen, (string)$tokens[$outeropen], $tokens[$outeropen] === '[' ? ']' : ')');
        if ($outerclose === null) {
            return [];
        }

        $entries = [];
        $depth = 1;
        for ($i = $outeropen + 1; $i < $outerclose; $i++) {
            $token = $tokens[$i];
            $childopen = null;
            if ($depth === 1 && $token === '[') {
                $childopen = $i;
            } elseif ($depth === 1 && is_array($token) && $token[0] === T_ARRAY) {
                $next = $this->nextSignificant($tokens, $i + 1);
                if ($next !== null && $tokens[$next] === '(') {
                    $childopen = $next;
                }
            }

            if ($childopen !== null) {
                $childclose = $this->matchingToken($tokens, $childopen, (string)$tokens[$childopen], $tokens[$childopen] === '[' ? ']' : ')');
                if ($childclose === null) {
                    break;
                }
                $line = 1;
                for ($j = $childopen; $j >= 0; $j--) {
                    if (is_array($tokens[$j])) {
                        $line = $tokens[$j][2];
                        break;
                    }
                }
                $entries[] = ['line' => $line, 'code' => $this->tokensToString(array_slice($tokens, $childopen, $childclose - $childopen + 1))];
                $i = $childclose;
                continue;
            }

            if (in_array($token, ['[', '(', '{'], true) || $this->isCurlyInterpolationOpen($token)) {
                $depth++;
            } elseif (in_array($token, [']', ')', '}'], true)) {
                $depth--;
            }
        }
        return $entries;
    }

    /**
     * Method arrayOpeningIndex.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @return ?int Return value.
     */
    private function arrayOpeningIndex(array $tokens, int $start): ?int {
        if ($tokens[$start] === '[') {
            return $start;
        }
        if (is_array($tokens[$start]) && $tokens[$start][0] === T_ARRAY) {
            $open = $this->nextSignificant($tokens, $start + 1);
            return $open !== null && $tokens[$open] === '(' ? $open : null;
        }
        return null;
    }

    /**
     * Method isAnonymousClass.
     *
     * @param array $tokens Parameter tokens.
     * @param int $index Parameter index.
     * @return bool Return value.
     */
    private function isAnonymousClass(array $tokens, int $index): bool {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($tokens[$i]) && $tokens[$i][0] === T_NEW;
        }
        return false;
    }

    /**
     * Method findOpeningBrace.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @return ?int Return value.
     */
    private function findOpeningBrace(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i] === '{') {
                return $i;
            }
            if ($tokens[$i] === ';') {
                return null;
            }
        }
        return null;
    }

    /**
     * Method matchingToken.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @param string $open Parameter open.
     * @param string $close Parameter close.
     * @return ?int Return value.
     */
    private function matchingToken(array $tokens, int $start, string $open, string $close): ?int {
        $depth = 0;
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === $open || ($open === '{' && $this->isCurlyInterpolationOpen($token))) {
                $depth++;
            } elseif ($token === $close && --$depth === 0) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Method isCurlyInterpolationOpen.
     *
     * @param array|string $token Parameter token.
     * @return bool Return value.
     */
    private function isCurlyInterpolationOpen(array|string $token): bool {
        return is_array($token)
            && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true);
    }

    /**
     * Method nextSignificant.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @return ?int Return value.
     */
    private function nextSignificant(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Method nextTokenOfType.
     *
     * @param array $tokens Parameter tokens.
     * @param int $start Parameter start.
     * @param int $type Parameter type.
     * @return ?int Return value.
     */
    private function nextTokenOfType(array $tokens, int $start, int $type): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === $type) {
                return $i;
            }
            if ($tokens[$i] === '{' || $tokens[$i] === ';') {
                return null;
            }
        }
        return null;
    }

    /**
     * Method tokensToString.
     *
     * @param array $tokens Parameter tokens.
     * @return string Return value.
     */
    private function tokensToString(array $tokens): string {
        $code = '';
        foreach ($tokens as $token) {
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /**
     * Method isToken.
     *
     * @param array|string $token Parameter token.
     * @param int $type Parameter type.
     * @param string $value Parameter value.
     * @return bool Return value.
     */
    private function isToken(array|string $token, int $type, string $value): bool {
        return is_array($token) && $token[0] === $type && $token[1] === $value;
    }
}
