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

    /**
     * @var array<int, true>
     */
    private array $checkedModels = [];

    /**
     * @var list<Violation>
     */
    private array $violations = [];

    private int $countedValues = 0;

    private function __construct() {}

    /**
     * @return list<Violation>
     */
    public static function violations(Invoice $invoice): array
    {
        $modelEncoding = new self;
        $modelEncoding->checkModel($invoice, '', 0, false);

        return $modelEncoding->violations;
    }

    private function checkModel(object $model, string $path, int $depth, bool $isRepeated): void
    {
        $identity = spl_object_id($model);

        if (isset($this->enclosingModels[$identity])) {
            $this->violations[] = $this->violation('The value of %s contains itself and cannot be checked for valid UTF-8.', $path);

            return;
        }

        $isRepeated = $isRepeated || isset($this->checkedModels[$identity]);

        if ($this->exceedsLimits($model, $depth, $isRepeated)) {
            $this->violations[] = $this->limitViolation($path);

            return;
        }

        $this->enclosingModels[$identity] = true;
        $this->checkedModels[$identity] = true;

        foreach (get_object_vars($model) as $name => $property) {
            if ($this->isExhausted()) {
                break;
            }

            $propertyPath = $path === '' ? $name : $path.'.'.$name;

            if (is_array($property)) {
                $this->checkList($property, $propertyPath, $depth + 1, $isRepeated);
            } else {
                $this->checkItem($property, $propertyPath, $depth + 1, $isRepeated);
            }
        }

        unset($this->enclosingModels[$identity]);
    }

    /**
     * @param  array<mixed>  $list
     */
    private function checkList(array $list, string $path, int $depth, bool $isRepeated): void
    {
        if ($this->exceedsLimits($list, $depth, $isRepeated)) {
            $this->violations[] = $this->limitViolation($path);

            return;
        }

        foreach ($list as $key => $item) {
            if ($this->isExhausted()) {
                break;
            }

            $this->checkItem($item, $path.'['.(is_int($key) ? $key : self::UNDECLARED_KEY).']', $depth + 1, $isRepeated);
        }
    }

    private function checkItem(mixed $value, string $path, int $depth, bool $isRepeated): void
    {
        if (is_object($value) && in_array($value::class, self::MODEL_CLASSES, true)) {
            $this->checkModel($value, $path, $depth, $isRepeated);

            return;
        }

        $visited = [];
        $violation = $this->violationOfValue($value, is_string($value) ? $path : $path.'['.self::UNDECLARED_KEY.']', $visited, $depth, $isRepeated);

        if ($violation instanceof Violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function violationOfValue(mixed $value, string $flag, array &$visited, int $depth, bool $isCounted): ?Violation
    {
        if ($this->exceedsLimits($value, $depth, $isCounted)) {
            return $this->limitViolation($flag);
        }

        if (is_string($value)) {
            return $this->violationOfString($value, $flag);
        }

        if (! is_array($value) && ! is_object($value)) {
            return null;
        }

        if (is_object($value)) {
            if ($this->isVisited($visited, 'object:'.spl_object_id($value))) {
                return null;
            }

            $stringViolation = $value instanceof Stringable ? $this->violationOfString((string) $value, $flag) : null;

            if ($stringViolation instanceof Violation) {
                return $stringViolation;
            }

            $value = (array) $value;
        }

        foreach (array_keys($value) as $key) {
            if ($this->exceedsBudget()) {
                return $this->limitViolation($flag);
            }

            $reference = ReflectionReference::fromArrayElement($value, $key);

            if ($reference instanceof ReflectionReference && $this->isVisited($visited, 'reference:'.$reference->getId())) {
                continue;
            }

            $violation = $this->violationOfValue($value[$key], $flag, $visited, $depth + 1, false);

            if ($violation instanceof Violation) {
                return $violation;
            }
        }

        return null;
    }

    private function exceedsLimits(mixed $value, int $depth, bool $isCounted): bool
    {
        return ($isCounted ? $this->exceedsBudget() : $this->isExhausted()) || ((is_array($value) || is_object($value)) && $depth >= self::DEPTH_LIMIT);
    }

    private function exceedsBudget(): bool
    {
        $this->countedValues++;

        return $this->isExhausted();
    }

    private function isExhausted(): bool
    {
        return $this->countedValues > self::VALUE_LIMIT;
    }

    private function violationOfString(string $value, string $flag): ?Violation
    {
        return mb_check_encoding($value, 'UTF-8') ? null : $this->violation('The value of %s is not valid UTF-8.', $flag);
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
