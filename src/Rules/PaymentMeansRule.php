<?php

declare(strict_types=1);

namespace JohnWink\En16931\Rules;

use Closure;
use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Severity;
use JohnWink\En16931\Violation;

/**
 * A per-payment-instruction rule (BG-16): fires once for each payment means
 * that fails the predicate — mirroring the official cac:PaymentMeans contexts.
 */
final readonly class PaymentMeansRule implements Rule
{
    /**
     * @param  Closure(PaymentMeans): bool  $ok
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
        $violations = [];

        foreach ($invoice->paymentMeans as $paymentMeans) {
            if (! ($this->ok)($paymentMeans)) {
                $violations[] = new Violation($this->id, $this->severity, $this->message, $this->flag);
            }
        }

        return $violations;
    }
}
