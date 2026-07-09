<?php

declare(strict_types=1);

namespace JohnWink\En16931;

/**
 * The outcome of validating one invoice: the fired violations. The document is
 * considered valid when no fatal violation fired (warnings do not invalidate).
 */
final readonly class ValidationResult
{
    /**
     * @param  list<Violation>  $violations
     */
    public function __construct(public array $violations) {}

    public function isValid(): bool
    {
        return $this->fatals() === [];
    }

    /**
     * @return list<Violation>
     */
    public function fatals(): array
    {
        return $this->ofSeverity(Severity::Fatal);
    }

    /**
     * @return list<Violation>
     */
    public function warnings(): array
    {
        return $this->ofSeverity(Severity::Warning);
    }

    public function hasViolation(string $ruleId): bool
    {
        return array_any($this->violations, fn (Violation $violation): bool => $violation->ruleId === $ruleId);
    }

    /**
     * @return array{valid: bool, violations: list<array{rule: string, severity: string, message: string, flag: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'violations' => array_map(static fn (Violation $violation): array => $violation->toArray(), $this->violations),
        ];
    }

    /**
     * @return list<Violation>
     */
    private function ofSeverity(Severity $severity): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (Violation $violation): bool => $violation->severity === $severity,
        ));
    }
}
