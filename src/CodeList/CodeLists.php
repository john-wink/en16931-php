<?php

declare(strict_types=1);

namespace JohnWink\En16931\CodeList;

/**
 * The small, stable code lists EN 16931 restricts certain fields to. The large
 * ISO 4217 / ISO 3166 tables (BT-5 / BT-40) are intentionally deferred to a
 * later slice; this covers the VAT category (BT-118) and invoice type (BT-3).
 */
final class CodeLists
{
    /**
     * UNCL5305 subset used by EN 16931 (BT-118 / BT-151).
     *
     * @var list<string>
     */
    public const array VAT_CATEGORIES = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O', 'L', 'M'];

    /**
     * UNTDID 1001 subset accepted for BT-3 (invoices and credit notes).
     *
     * @var list<string>
     */
    public const array INVOICE_TYPES = [
        '71', '80', '82', '84', '102', '130', '202', '203', '204', '211', '295',
        '325', '326', '380', '381', '383', '384', '386', '387', '388', '389',
        '390', '393', '394', '395', '456', '457', '527', '575', '623', '633',
        '751', '780', '935',
    ];

    public static function isVatCategory(string $code): bool
    {
        return in_array($code, self::VAT_CATEGORIES, true);
    }

    public static function isInvoiceType(string $code): bool
    {
        return in_array($code, self::INVOICE_TYPES, true);
    }
}
