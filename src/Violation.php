<?php

declare(strict_types=1);

namespace JohnWink\En16931;

/**
 * A single fired rule: the business rule that failed, its severity, a
 * human-readable message and the Business Term/Group it concerns.
 */
final readonly class Violation
{
    public function __construct(
        public string $ruleId,
        public Severity $severity,
        public string $message,
        public ?string $flag = null,
    ) {}

    public static function fatal(string $ruleId, string $message, ?string $flag = null): self
    {
        return new self($ruleId, Severity::Fatal, $message, $flag);
    }

    public static function warning(string $ruleId, string $message, ?string $flag = null): self
    {
        return new self($ruleId, Severity::Warning, $message, $flag);
    }

    /**
     * @return array{rule: string, severity: string, message: string, flag: ?string}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'flag' => $this->flag,
        ];
    }
}
