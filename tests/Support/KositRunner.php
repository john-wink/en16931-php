<?php

declare(strict_types=1);

namespace JohnWink\En16931\Tests\Support;

/**
 * Dev-only wrapper around the official KoSIT validator (Java). Used purely as a
 * conformance oracle in the parity test — it is never part of the shipped
 * library. Set it up with tools/kosit-setup.sh (downloads the jar + the
 * XRechnung configuration into build/kosit/).
 */
final class KositRunner
{
    public function __construct(
        private string $jar,
        private string $configDir,
    ) {}

    public static function fromBuildDir(): ?self
    {
        $base = dirname(__DIR__, 2).'/build/kosit';
        // Prefer the modern standalone jar; the "-java8-" build fails on new JREs.
        $jars = array_values(array_filter(
            glob($base.'/validator/validationtool-*-standalone.jar') ?: [],
            static fn (string $path): bool => ! str_contains($path, 'java8'),
        ));
        $jar = $jars[0] ?? null;
        $configDir = $base.'/config';

        if ($jar === null || ! is_file($configDir.'/scenarios.xml')) {
            return null;
        }

        return new self($jar, $configDir);
    }

    public static function javaAvailable(): bool
    {
        exec('java -version 2>/dev/null', $output, $code);

        return $code === 0;
    }

    /**
     * Validate the payload with KoSIT and return its verdict plus the set of
     * fired business-rule codes.
     *
     * @return array{accept: bool, codes: list<string>}
     */
    public function validate(string $xml): array
    {
        $dir = sys_get_temp_dir().'/kosit-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $input = $dir.'/input.xml';
        file_put_contents($input, $xml);

        $command = sprintf(
            'java -jar %s -r %s -s %s -o %s %s 2>/dev/null',
            escapeshellarg($this->jar),
            escapeshellarg($this->configDir),
            escapeshellarg($this->configDir.'/scenarios.xml'),
            escapeshellarg($dir),
            escapeshellarg($input),
        );
        exec($command);

        $report = (string) @file_get_contents($dir.'/input-report.xml');

        $this->cleanup($dir);

        preg_match_all('/code="(BR-[A-Z0-9-]+)"/', $report, $matches);

        return [
            'accept' => $report !== '' && ! str_contains($report, '<rep:reject'),
            'codes' => array_values(array_unique($matches[1])),
        ];
    }

    private function cleanup(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
