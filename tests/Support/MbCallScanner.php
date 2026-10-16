<?php

declare(strict_types=1);

namespace JohnWink\En16931\Tests\Support;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionFunction;
use ReflectionParameter;
use SplFileInfo;

final class MbCallScanner
{
    private const array SKIPPED_PREDECESSORS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW];

    private const array ENCODING_PARAMETERS = ['encoding', 'from_encoding', 'encodings'];

    private const array FORBIDDEN_PREFIXES = ['mb_ereg', 'mb_split', 'mb_regex_'];

    private const array ENCODING_FREE_FUNCTIONS = ['mb_get_info', 'mb_language', 'mb_list_encodings', 'mb_substitute_character'];

    private const array CLASS_KEYWORDS = [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM];

    private const array IMPORT_LIST_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_AS];

    /**
     * @return list<array{file: string, line: int, utf8: bool}>
     */
    public static function callsInDirectory(string $directory): array
    {
        $calls = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = mb_substr($file->getPathname(), mb_strlen($directory) + 1);

            foreach (self::callsInSource((string) file_get_contents($file->getPathname())) as $call) {
                $calls[] = ['file' => $relativePath, ...$call];
            }
        }

        return $calls;
    }

    /**
     * @return list<array{line: int, utf8: bool}>
     */
    public static function callsInSource(string $source): array
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($source), static fn (PhpToken $phpToken): bool => ! $phpToken->isIgnorable()));
        $calls = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(T_CONSTANT_ENCAPSED_STRING) && self::isChecked(mb_substr($token->text, 1, -1))) {
                $calls[] = ['line' => $token->line, 'utf8' => false];

                continue;
            }

            if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $next = $tokens[$index + 1] ?? null;

            if (! self::isChecked($token->text) || $previous?->is(self::SKIPPED_PREDECESSORS) === true || self::isPlainImport($tokens, $index) || self::isMethodDeclaration($tokens, $index)) {
                continue;
            }

            $encodingParameter = self::encodingParameter($token->text);

            $utf8 = $encodingParameter instanceof ReflectionParameter
                && $previous?->is(T_FUNCTION) !== true
                && $next instanceof PhpToken
                && $next->text === '('
                && self::passesUtf8(self::arguments($tokens, $index + 1), $encodingParameter);

            $calls[] = ['line' => $token->line, 'utf8' => $utf8];
        }

        return $calls;
    }

    private static function isChecked(string $name): bool
    {
        $function = self::mbFunction($name);

        return $function !== null && ! in_array($function, self::ENCODING_FREE_FUNCTIONS, true);
    }

    private static function isForbidden(string $name): bool
    {
        $function = self::mbFunction($name);

        return $function !== null && array_any(self::FORBIDDEN_PREFIXES, static fn (string $prefix): bool => str_starts_with($function, $prefix));
    }

    private static function encodingParameter(string $name): ?ReflectionParameter
    {
        $function = self::mbFunction($name);

        if ($function === null || self::isForbidden($function)) {
            return null;
        }

        return array_find(new ReflectionFunction($function)->getParameters(), static fn (ReflectionParameter $reflectionParameter): bool => in_array($reflectionParameter->getName(), self::ENCODING_PARAMETERS, true));
    }

    private static function mbFunction(string $name): ?string
    {
        $function = mb_strtolower(ltrim($name, '\\'));

        return str_starts_with($function, 'mb_') && function_exists($function) ? $function : null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function isPlainImport(array $tokens, int $index): bool
    {
        if (($tokens[$index + 1] ?? null)?->is(T_AS) === true) {
            return false;
        }

        $position = $index - 1;

        while ($position > 1 && ($tokens[$position]->text === ',' || $tokens[$position]->is(self::IMPORT_LIST_TOKENS))) {
            $position--;
        }

        return $position > 0
            && $tokens[$position]->is(T_FUNCTION)
            && $tokens[$position - 1]->is(T_USE);
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function isMethodDeclaration(array $tokens, int $index): bool
    {
        if (($tokens[$index - 1] ?? null)?->is(T_FUNCTION) !== true) {
            return false;
        }

        $openings = [];
        $classPending = false;

        foreach (array_slice($tokens, 0, $index) as $position => $phpToken) {
            if ($phpToken->is(self::CLASS_KEYWORDS) && ($tokens[$position - 1] ?? null)?->is(T_DOUBLE_COLON) !== true) {
                $classPending = true;
            } elseif ($phpToken->text === '{' || $phpToken->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
                $openings[] = $classPending;
                $classPending = false;
            } elseif ($phpToken->text === '}') {
                array_pop($openings);
            }
        }

        return $openings !== [] && $openings[array_key_last($openings)];
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return list<list<PhpToken>>
     */
    private static function arguments(array $tokens, int $openingParenthesis): array
    {
        $arguments = [];
        $current = [];
        $depth = 0;

        foreach (array_slice($tokens, $openingParenthesis + 1) as $phpToken) {
            if (in_array($phpToken->text, [')', ']', '}'], true) && $depth === 0) {
                break;
            }

            if ($phpToken->text === ',' && $depth === 0) {
                $arguments[] = $current;
                $current = [];

                continue;
            }

            if (in_array($phpToken->text, ['(', '[', '{'], true) || $phpToken->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif (in_array($phpToken->text, [')', ']', '}'], true)) {
                $depth--;
            }

            $current[] = $phpToken;
        }

        $arguments[] = $current;

        return array_values(array_filter($arguments, static fn (array $argument): bool => $argument !== []));
    }

    /**
     * @param  list<list<PhpToken>>  $arguments
     */
    private static function passesUtf8(array $arguments, ReflectionParameter $reflectionParameter): bool
    {
        foreach ($arguments as $argument) {
            if ($argument[0]->is(T_ELLIPSIS)) {
                return false;
            }

            if (count($argument) > 2 && $argument[0]->is(T_STRING) && $argument[0]->text === $reflectionParameter->getName() && $argument[1]->text === ':') {
                return self::isUtf8Literal(array_slice($argument, 2));
            }
        }

        $position = $reflectionParameter->getPosition();

        return isset($arguments[$position]) && self::isUtf8Literal($arguments[$position]);
    }

    /**
     * @param  list<PhpToken>  $argument
     */
    private static function isUtf8Literal(array $argument): bool
    {
        return count($argument) === 1
            && $argument[0]->is(T_CONSTANT_ENCAPSED_STRING)
            && mb_substr($argument[0]->text, 1, -1) === 'UTF-8';
    }
}
