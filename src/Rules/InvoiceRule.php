<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * A general document-level rule: fatal when the predicate does not hold.
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
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        return ($this->ok)($invoice)
            ? []
            : [Violation::fatal($this->id, $this->message, $this->flag)];
    }
}
