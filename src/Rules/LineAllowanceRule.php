<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Violation;

/**
 * A per-line-allowance/charge rule (BG-27 / BG-28): fires once for each line
 * allowance or charge that fails the predicate.
 */
final readonly class LineAllowanceRule implements Rule
{
    /**
     * @param  Closure(LineAllowanceCharge): bool  $ok
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

        foreach ($invoice->lines as $line) {
            foreach ($line->allowanceCharges as $allowanceCharge) {
                if (! ($this->ok)($allowanceCharge)) {
                    $violations[] = Violation::fatal($this->id, $this->message, $this->flag);
                }
            }
        }

        return $violations;
    }
}
