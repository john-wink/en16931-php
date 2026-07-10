<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * A per-document-level-allowance/charge rule (BG-20 / BG-21): fires once for
 * each allowance or charge that fails the predicate.
 */
final readonly class AllowanceRule implements Rule
{
    /**
     * @param  Closure(DocumentAllowanceCharge): bool  $ok
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

        foreach ($invoice->allowanceCharges as $allowanceCharge) {
            if (! ($this->ok)($allowanceCharge)) {
                $violations[] = Violation::fatal($this->id, $this->message, $this->flag);
            }
        }

        return $violations;
    }
}
