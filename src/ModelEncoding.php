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

final class ModelEncoding
{
    public const string RULE_ID = 'UTF-8';

    private const int FOREIGN_DEPTH_LIMIT = 64;

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
     * @return list<Violation>
     */
    public static function violations(Invoice $invoice): array
    {
        return self::violationsOfModel($invoice, '');
    }

    /**
     * @return list<Violation>
     */
    private static function violationsOfModel(object $model, string $path): array
    {
        $violations = [];

        foreach (get_object_vars($model) as $name => $property) {
            $propertyPath = $path === '' ? $name : $path.'.'.$name;
            $violations = [...$violations, ...(is_array($property) ? self::violationsOfList($property, $propertyPath) : self::violationsOfItem($property, $propertyPath))];
        }

        return $violations;
    }

    /**
     * @param  array<mixed>  $list
     * @return list<Violation>
     */
    private static function violationsOfList(array $list, string $path): array
    {
        $violations = [];

        foreach ($list as $key => $item) {
            $violations = [...$violations, ...self::violationsOfItem($item, $path.'['.(is_int($key) ? $key : self::UNDECLARED_KEY).']')];
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private static function violationsOfItem(mixed $value, string $path): array
    {
        if (is_object($value) && in_array($value::class, self::MODEL_CLASSES, true)) {
            return self::violationsOfModel($value, $path);
        }

        $visited = [];

        if (self::isValidUtf8($value, $visited, 0)) {
            return [];
        }

        $flag = is_string($value) ? $path : $path.'['.self::UNDECLARED_KEY.']';

        return [Violation::fatal(self::RULE_ID, sprintf('The value of %s is not valid UTF-8.', $flag), $flag)];
    }

    /**
     * @param  array<string, true>  $visited
     */
    private static function isValidUtf8(mixed $value, array &$visited, int $depth): bool
    {
        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8');
        }

        if (! is_array($value) && ! is_object($value)) {
            return true;
        }

        if ($depth >= self::FOREIGN_DEPTH_LIMIT) {
            return false;
        }

        if (is_object($value)) {
            if (self::isVisited($visited, 'object:'.spl_object_id($value))) {
                return true;
            }

            $value = get_object_vars($value);
        }

        foreach (array_keys($value) as $key) {
            $reference = ReflectionReference::fromArrayElement($value, $key);

            if ($reference instanceof ReflectionReference && self::isVisited($visited, 'reference:'.$reference->getId())) {
                continue;
            }

            if (! self::isValidUtf8($value[$key], $visited, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, true>  $visited
     */
    private static function isVisited(array &$visited, string $identity): bool
    {
        if (isset($visited[$identity])) {
            return true;
        }

        $visited[$identity] = true;

        return false;
    }
}
