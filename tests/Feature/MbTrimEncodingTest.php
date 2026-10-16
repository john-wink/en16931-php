<?php

declare(strict_types=1);

use JohnWink\En16931\Tests\Support\TrimCallScanner;

it('passes the encoding UTF-8 to every mb_trim call in src', function (): void {
    $calls = TrimCallScanner::callsInDirectory(dirname(__DIR__, 2).'/src');

    $withoutUtf8 = array_values(array_map(
        static fn (array $call): string => $call['file'].':'.$call['line'],
        array_filter($calls, static fn (array $call): bool => ! $call['utf8']),
    ));

    expect($calls)->not->toBeEmpty()
        ->and($withoutUtf8)->toBe([]);
});

it('tells an mb_trim call with UTF-8 from one without', function (string $source, array $expected): void {
    expect(array_column(TrimCallScanner::callsInSource("<?php\n".$source), 'utf8'))->toBe($expected);
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
]);
