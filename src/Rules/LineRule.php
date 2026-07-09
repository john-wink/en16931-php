<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Violation;

/**
 * A per-line rule: fires once for each line that fails the predicate.
 */
final readonly class LineRule implements Rule
{
    /**
     * @param  Closure(InvoiceLine): bool  $ok
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
            if (! ($this->ok)($line)) {
                $violations[] = Violation::fatal($this->id, $this->message, $this->flag);
            }
        }

        return $violations;
    }
}
