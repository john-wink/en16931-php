<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\Model\Attachment;
use JohnWink\En16931\Model\DocumentAllowanceCharge;
use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\ItemAttribute;
use JohnWink\En16931\Model\ItemClassification;
use JohnWink\En16931\Model\LineAllowanceCharge;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\PaymentMeans;
use JohnWink\En16931\Model\SubInvoiceLine;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\ThirdPartyPayment;
use JohnWink\En16931\Model\Totals;
use ReflectionReference;
use Stringable;

final class ModelEncoding
{
    public const string RULE_ID = 'UTF-8';

    private const int DEPTH_LIMIT = 64;

    private const int VALUE_LIMIT = 1_000_000;

    private const array MODEL_CLASSES = [
        Attachment::class,
        DocumentAllowanceCharge::class,
        Invoice::class,
        InvoiceLine::class,
        ItemAttribute::class,
        ItemClassification::class,
        LineAllowanceCharge::class,
        Party::class,
        PaymentMeans::class,
        SubInvoiceLine::class,
        TaxSubtotal::class,
        ThirdPartyPayment::class,
        Totals::class,
    ];

    private const string UNDECLARED_KEY = '?';

    /**
     * @var array<int, true>
     */
    private array $enclosingModels = [];

    private int $visitedValues = 0;

    private function __construct() {}

    /**
     * @return list<Violation>
     */
    public static function violations(Invoice $invoice): array
    {
        return new self()->violationsOfModel($invoice, '', 0);
    }

    /**
     * @return list<Violation>
     */
    private function violationsOfModel(object $model, string $path, int $depth): array
    {
        $identity = spl_object_id($model);

        if (isset($this->enclosingModels[$identity])) {
            return [$this->violation('The value of %s contains itself and cannot be checked for valid UTF-8.', $path)];
        }

        if ($this->exceedsLimits($model, $depth)) {
            return [$this->limitViolation($path)];
        }

        $this->enclosingModels[$identity] = true;
        $violations = [];

        foreach (get_object_vars($model) as $name => $property) {
            if ($this->isExhausted()) {
                break;
            }

            $propertyPath = $path === '' ? $name : $path.'.'.$name;
            $violations = [...$violations, ...(is_array($property) ? $this->violationsOfList($property, $propertyPath, $depth + 1) : $this->violationsOfItem($property, $propertyPath, $depth + 1))];
        }

        unset($this->enclosingModels[$identity]);

        return $violations;
    }

    /**
     * @param  array<mixed>  $list
     * @return list<Violation>
     */
    private function violationsOfList(array $list, string $path, int $depth): array
    {
        if ($this->exceedsLimits($list, $depth)) {
            return [$this->limitViolation($path)];
        }

        $violations = [];

        foreach ($list as $key => $item) {
            if ($this->isExhausted()) {
                break;
            }

            $violations = [...$violations, ...$this->violationsOfItem($item, $path.'['.(is_int($key) ? $key : self::UNDECLARED_KEY).']', $depth + 1)];
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function violationsOfItem(mixed $value, string $path, int $depth): array
    {
        if (is_object($value) && in_array($value::class, self::MODEL_CLASSES, true)) {
            return $this->violationsOfModel($value, $path, $depth);
        }

        $visited = [];

        return $this->violationsOfValue($value, is_string($value) ? $path : $path.'['.self::UNDECLARED_KEY.']', $visited, $depth);
    }

    /**
     * @param  array<string, true>  $visited
     * @return list<Violation>
     */
    private function violationsOfValue(mixed $value, string $flag, array &$visited, int $depth): array
    {
        if ($this->exceedsLimits($value, $depth)) {
            return [$this->limitViolation($flag)];
        }

        if (is_string($value)) {
            return $this->violationsOfString($value, $flag);
        }

        if (! is_array($value) && ! is_object($value)) {
            return [];
        }

        if (is_object($value)) {
            if ($this->isVisited($visited, 'object:'.spl_object_id($value))) {
                return [];
            }

            $stringViolations = $value instanceof Stringable ? $this->violationsOfString((string) $value, $flag) : [];

            if ($stringViolations !== []) {
                return $stringViolations;
            }

            $value = (array) $value;
        }

        foreach (array_keys($value) as $key) {
            $reference = ReflectionReference::fromArrayElement($value, $key);

            if ($reference instanceof ReflectionReference && $this->isVisited($visited, 'reference:'.$reference->getId())) {
                continue;
            }

            $violations = $this->violationsOfValue($value[$key], $flag, $visited, $depth + 1);

            if ($violations !== []) {
                return $violations;
            }
        }

        return [];
    }

    private function exceedsLimits(mixed $value, int $depth): bool
    {
        $this->visitedValues++;

        return $this->isExhausted() || ((is_array($value) || is_object($value)) && $depth >= self::DEPTH_LIMIT);
    }

    private function isExhausted(): bool
    {
        return $this->visitedValues > self::VALUE_LIMIT;
    }

    /**
     * @return list<Violation>
     */
    private function violationsOfString(string $value, string $flag): array
    {
        return mb_check_encoding($value, 'UTF-8') ? [] : [$this->violation('The value of %s is not valid UTF-8.', $flag)];
    }

    private function limitViolation(string $flag): Violation
    {
        return $this->violation('The value of %s is nested too deep or too large to be checked for valid UTF-8.', $flag);
    }

    private function violation(string $message, string $flag): Violation
    {
        return Violation::fatal(self::RULE_ID, sprintf($message, $flag), $flag);
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function isVisited(array &$visited, string $identity): bool
    {
        if (isset($visited[$identity])) {
            return true;
        }

        $visited[$identity] = true;

        return false;
    }
}
