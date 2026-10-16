<?php

declare(strict_types=1);

use JohnWink\En16931\Tests\Support\MbCallScanner;

it('passes the encoding UTF-8 to every mb_* call in src that takes an encoding', function (): void {
    $calls = MbCallScanner::callsInDirectory(dirname(__DIR__, 2).'/src');

    $withoutUtf8 = array_values(array_map(
        static fn (array $call): string => $call['file'].':'.$call['line'],
        array_filter($calls, static fn (array $call): bool => ! $call['utf8']),
    ));

    expect($calls)->not->toBeEmpty()
        ->and($withoutUtf8)->toBe([]);
});

it('tells an mb_* call with UTF-8 from one without', function (string $source, array $expected): void {
    expect(array_column(MbCallScanner::callsInSource("<?php\n".$source), 'utf8'))->toBe($expected);
})->with([
    'positional UTF-8' => ["mb_trim(\$value, null, 'UTF-8');", [true]],
    'double-quoted UTF-8' => ['mb_trim($value, null, "UTF-8");', [true]],
    'named UTF-8' => ["mb_trim(\$value, encoding: 'UTF-8');", [true]],
    'fully qualified with UTF-8' => ["\\mb_trim(\$value, null, 'UTF-8');", [true]],
    'nested call inside the arguments' => ["mb_trim((string) preg_replace('/a/', 'b', \$value), null, 'UTF-8');", [true]],
    'mb_ltrim and mb_rtrim with UTF-8' => ["mb_ltrim(\$value, null, 'UTF-8'); mb_rtrim(\$value, null, 'UTF-8');", [true, true]],
    'value only' => ['mb_trim($value);', [false]],
    'characters without encoding' => ["mb_trim(\$value, ' ');", [false]],
    'null encoding' => ['mb_trim($value, null, null);', [false]],
    'other encoding' => ["mb_trim(\$value, null, 'ISO-8859-1');", [false]],
    'encoding from a variable' => ['mb_trim($value, null, $encoding);', [false]],
    'named characters only' => ["mb_trim(\$value, characters: ' ');", [false]],
    'spread arguments' => ['mb_trim(...$arguments);', [false]],
    'first-class callable' => ['$trim = mb_trim(...);', [false]],
    'string callable' => ["array_map('mb_trim', \$values);", [false]],
    'mb_rtrim without encoding' => ['mb_rtrim($value);', [false]],
    'method of the same name' => ['$string->mb_trim($value); Text::mb_trim($value);', []],
    'mb_strlen with UTF-8' => ["mb_strlen(\$value, 'UTF-8');", [true]],
    'mb_strtoupper with UTF-8' => ["mb_strtoupper(\$value, 'UTF-8');", [true]],
    'mb_substr with UTF-8' => ["mb_substr(\$value, 0, 2, 'UTF-8');", [true]],
    'mb_substr with named UTF-8' => ["mb_substr(\$value, 0, encoding: 'UTF-8');", [true]],
    'mb_strlen without encoding' => ['mb_strlen($value);', [false]],
    'mb_strtoupper without encoding' => ['mb_strtoupper($value);', [false]],
    'mb_substr with a length only' => ['mb_substr($value, 0, 2);', [false]],
    'mb_substr with UTF-8 in the place of the length' => ["mb_substr(\$value, 0, 'UTF-8');", [false]],
    'mb_strlen as a string callable' => ["array_map('mb_strlen', \$values);", [false]],
    'mb_* function without an encoding parameter' => ['mb_list_encodings();', []],
    'import with an alias' => ['use function mb_trim as trimValue;', [false]],
    'fully qualified import with an alias' => ['use function \\mb_substr as cut;', [false]],
    'import without an alias' => ['use function mb_trim;', []],
    'declaration of the same name in the package namespace' => ["namespace JohnWink\\En16931;\nfunction mb_trim(string \$string): string { return \$string; }", [false]],
]);
