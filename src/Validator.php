<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\Contracts\Rule;
use JohnWink\En16931\Model\Invoice;

/**
 * Runs a set of {@see Rule}s over a normalized {@see Invoice} and collects the
 * violations into a {@see ValidationResult}.
 */
final readonly class Validator
{
    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(private array $rules) {}

    public function validate(Invoice $invoice): ValidationResult
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->evaluate($invoice) as $violation) {
                $violations[] = $violation;
            }
        }

        return new ValidationResult($violations);
    }

    /**
     * @return list<Rule>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
