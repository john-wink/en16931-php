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

    /**
     * VAT-number prefixes BR-CO-09 accepts on top of ISO 3166-1 alpha-2
     * (Greece uses EL, Northern Ireland XI).
     *
     * @var list<string>
     */
    public const array VAT_PREFIX_EXTRAS = ['EL', 'XI'];

    /**
     * The MIME codes BR-CL-24 allows for embedded attachments (BT-125).
     *
     * @var list<string>
     */
    public const array MIME_CODES = [
        'application/pdf', 'image/png', 'image/jpeg', 'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /**
     * The UNTDID 4461 payment means codes BR-CL-16 allows for BT-81, per the
     * EN 16931 validation artefacts.
     *
     * @var list<string>
     */
    public const array PAYMENT_MEANS_CODES = [
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16',
        '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32',
        '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48',
        '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64',
        '65', '66', '67', '68', '69', '70', '74', '75', '76', '77', '78', '91', '92', '93', '94', '95',
        '96', '97', '98', 'ZZZ',
    ];

    /**
     * The EAS scheme identifiers BR-CL-25 allows for electronic addresses
     * (BT-34-1 / BT-49-1), per the EN 16931 validation artefacts.
     *
     * @var list<string>
     */
    public const array ELECTRONIC_ADDRESS_SCHEMES = [
        '0002', '0007', '0009', '0037', '0060', '0088', '0096', '0097', '0106', '0130', '0135', '0142',
        '0147', '0151', '0154', '0158', '0170', '0177', '0183', '0184', '0188', '0190', '0191', '0192',
        '0193', '0194', '0195', '0196', '0198', '0199', '0200', '0201', '0202', '0203', '0204', '0205',
        '0208', '0209', '0210', '0211', '0212', '0213', '0215', '0216', '0217', '0218', '0219', '0220',
        '0221', '0225', '0230', '0235', '0240', '0244', '0242', '0245', '0246', '0248', '9910', '9913',
        '9914', '9915', '9918', '9919', '9920', '9922', '9923', '9924', '9925', '9926', '9927', '9928',
        '9929', '9930', '9931', '9932', '9933', '9934', '9935', '9936', '9937', '9938', '9939', '9940',
        '9941', '9942', '9943', '9944', '9945', '9946', '9947', '9948', '9949', '9950', '9951', '9952',
        '9953', '9957', '9959', 'AN', 'AQ', 'AS', 'AU', 'EM',
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
