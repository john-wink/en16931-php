<?php

declare(strict_types=1);

namespace JohnWink\En16931;

use JohnWink\En16931\Model\Invoice;

final class ModelEncoding
{
    public const string RULE_ID = 'UTF-8';

    private const string MODEL_NAMESPACE = __NAMESPACE__.'\\Model\\';

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
        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8') ? [] : [Violation::fatal(self::RULE_ID, sprintf('The value of %s is not valid UTF-8.', $path), $path)];
        }

        if (is_object($value) && str_starts_with($value::class, self::MODEL_NAMESPACE)) {
            return self::violationsOfModel($value, $path);
        }

        return [];
    }
}
