<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Severity;
use JohnWink\En16931\Violation;

/**
 * A general document-level rule: a violation (fatal by default) when the
 * predicate does not hold.
 */
final readonly class InvoiceRule implements Rule
{
    /**
     * @param  Closure(Invoice): bool  $ok
     */
    public function __construct(
        private string $id,
        private string $flag,
        private string $message,
        private Closure $ok,
        private Severity $severity = Severity::Fatal,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        return ($this->ok)($invoice)
            ? []
            : [new Violation($this->id, $this->severity, $this->message, $this->flag)];
    }
}
