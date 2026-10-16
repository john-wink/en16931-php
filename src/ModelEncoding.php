<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\Model\Invoice;

final class ModelEncoding
{
    public const string RULE_ID = 'UTF-8';

    /**
     * @return list<Violation>
     */
    public static function violations(Invoice $invoice): array
    {
        return self::violationsOf($invoice, '');
    }

    /**
     * @return list<Violation>
     */
    private static function violationsOf(mixed $value, string $path): array
    {
        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8') ? [] : [Violation::fatal(self::RULE_ID, sprintf('The value of %s is not valid UTF-8.', $path), $path)];
        }

        $violations = [];

        if (is_object($value)) {
            foreach (get_object_vars($value) as $name => $property) {
                $violations = [...$violations, ...self::violationsOf($property, $path === '' ? $name : $path.'.'.$name)];
            }
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $violations = [...$violations, ...self::violationsOf($item, $path.'['.$key.']')];
            }
        }

        return $violations;
    }
}
