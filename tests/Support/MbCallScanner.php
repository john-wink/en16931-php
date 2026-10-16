<?php

declare(strict_types=1);

namespace JohnWink\En16931\Tests\Support;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MbCallScanner
{
    private const array TRIM_FUNCTIONS = ['mb_trim', 'mb_ltrim', 'mb_rtrim'];

    private const array SKIPPED_PREDECESSORS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW];

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
            if ($token->is(T_CONSTANT_ENCAPSED_STRING) && self::isTrimFunction(mb_substr($token->text, 1, -1))) {
                $calls[] = ['line' => $token->line, 'utf8' => false];

                continue;
            }

            if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || ! self::isTrimFunction($token->text)) {
                continue;
            }

            if ($index > 0 && $tokens[$index - 1]->is(self::SKIPPED_PREDECESSORS)) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            $utf8 = $next instanceof PhpToken && $next->text === '(' && self::passesUtf8(self::arguments($tokens, $index + 1));

            $calls[] = ['line' => $token->line, 'utf8' => $utf8];
        }

        return $calls;
    }

    private static function isTrimFunction(string $name): bool
    {
        return in_array(mb_strtolower(ltrim($name, '\\')), self::TRIM_FUNCTIONS, true);
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
    private static function passesUtf8(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument[0]->is(T_ELLIPSIS)) {
                return false;
            }

            if (count($argument) > 2 && $argument[0]->is(T_STRING) && $argument[0]->text === 'encoding' && $argument[1]->text === ':') {
                return self::isUtf8Literal(array_slice($argument, 2));
            }
        }

        return isset($arguments[2]) && self::isUtf8Literal($arguments[2]);
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
