<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * A mandatory-field rule: fatal when the given field is absent.
 */
final readonly class PresenceRule implements Rule
{
    /**
     * @param  Closure(Invoice): bool  $present
     */
    public function __construct(
        private string $id,
        private string $flag,
        private string $message,
        private Closure $present,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        return ($this->present)($invoice)
            ? []
            : [Violation::fatal($this->id, $this->message, $this->flag)];
    }
}
