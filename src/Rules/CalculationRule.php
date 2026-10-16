<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Decimal;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Severity;
use JohnWink\En16931\Violation;

/**
 * A tolerance-free calculation rule (BR-CO-* etc.): the declared total must
 * equal the computed one once both are rounded to two decimals. Skipped when
 * the declared value is absent (a presence rule owns that). A filled declared
 * amount or operand that is not an xs:decimal fails it fatally. The severity is
 * configurable to mirror the KoSIT levels (e.g. BR-CO-16 is informational).
 */
final readonly class CalculationRule implements Rule
{
    /**
     * @param  Closure(Invoice): string  $computed
     * @param  Closure(Invoice): ?string  $declared
     * @param  (Closure(Invoice): list<?string>)|null  $operands
     */
    public function __construct(
        private string $id,
        private string $flag,
        private string $message,
        private Closure $computed,
        private Closure $declared,
        private Severity $severity = Severity::Fatal,
        private ?Closure $operands = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function evaluate(Invoice $invoice): array
    {
        $amounts = [($this->declared)($invoice), ...($this->operands instanceof Closure ? ($this->operands)($invoice) : [])];

        if (array_any($amounts, static fn (?string $amount): bool => Decimal::isFilledButNotXsDecimal($amount))) {
            return [Violation::fatal($this->id, "{$this->message} (an amount is not an xs:decimal).", $this->flag)];
        }

        $declared = Decimal::fromXsDecimal(($this->declared)($invoice));

        if ($declared === null) {
            return [];
        }

        $computed = ($this->computed)($invoice);

        if (! Decimal::isNumeric($computed)) {
            return [];
        }

        if (Decimal::equals($computed, $declared)) {
            return [];
        }

        return [new Violation($this->id, $this->severity, "{$this->message} (declared {$declared}, computed {$computed}).", $this->flag)];
    }
}
