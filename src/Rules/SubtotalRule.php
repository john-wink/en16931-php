<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Violation;

/**
 * A per-VAT-breakdown-group rule (BG-23): fires once for each subtotal that
 * fails the predicate.
 */
final readonly class SubtotalRule implements Rule
{
    /**
     * @param  Closure(TaxSubtotal): bool  $ok
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
        $violations = [];

        foreach ($invoice->taxSubtotals as $subtotal) {
            if (! ($this->ok)($subtotal)) {
                $violations[] = Violation::fatal($this->id, $this->message, $this->flag);
            }
        }

        return $violations;
    }
}
