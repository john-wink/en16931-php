<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * A code-list rule (BR-CL-*): the value, when present, must be in the allowed
 * set. Absence is a presence rule's concern, not this one's.
 */
final readonly class CodeListRule implements Rule
{
    /**
     * @param  Closure(Invoice): ?string  $value
     * @param  list<string>  $allowed
     */
    public function __construct(
        private string $id,
        private string $flag,
        private string $message,
        private Closure $value,
        private array $allowed,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        $value = ($this->value)($invoice);

        if ($value === null || in_array($value, $this->allowed, true)) {
            return [];
        }

        return [Violation::fatal($this->id, "{$this->message} [{$value}].", $this->flag)];
    }
}
