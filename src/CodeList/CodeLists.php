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
     * The UNCL5305 codes EN 16931 allows for VAT category fields
     * (BT-95 / BT-102 / BT-118 / BT-151), per the official BR-CL-17 assert.
     *
     * @var list<string>
     */
    public const array VAT_CATEGORIES = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O', 'L', 'M', 'B'];

    /**
     * The UNTDID 1001 codes accepted for BT-3 — the union of the official
     * BR-CL-01 lists (UBL invoice + credit note, CII) from the EN 16931
     * validation artefacts 1.3.16.
     *
     * @var list<string>
     */
    public const array INVOICE_TYPES = [
        '71', '80', '81', '82', '83', '84', '102', '130', '202', '203', '204',
        '211', '218', '219', '261', '262', '295', '296', '308', '325', '326',
        '331', '380', '381', '382', '383', '384', '385', '386', '387', '388',
        '389', '390', '393', '394', '395', '396', '420', '456', '457', '458',
        '471', '472', '473', '500', '501', '502', '503', '527', '532', '553',
        '575', '623', '633', '751', '780', '817', '870', '875', '876', '877',
        '935',
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
