<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * An if-then rule: when {@see $applies} holds, {@see $satisfied} must too, else
 * a fatal violation fires (e.g. reverse charge → buyer VAT id required).
 */
final readonly class ConditionalRule implements Rule
{
    /**
     * @param  Closure(Invoice): bool  $applies
     * @param  Closure(Invoice): bool  $satisfied
     */
    public function __construct(
        private string $id,
        private string $flag,
        private string $message,
        private Closure $applies,
        private Closure $satisfied,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        if (! ($this->applies)($invoice)) {
            return [];
        }

        return ($this->satisfied)($invoice)
            ? []
            : [Violation::fatal($this->id, $this->message, $this->flag)];
    }
}
