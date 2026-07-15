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
     * The UNTDID 2005 subset BR-CL-06 allows for the VAT point date code (BT-8).
     *
     * @var list<string>
     */
    public const array TAX_POINT_DATE_CODES = ['3', '35', '432'];

    /**
     * The UNTDID 5189 codes BR-CL-19 allows for allowance reasons (BT-98/BT-140).
     *
     * @var list<string>
     */
    public const array ALLOWANCE_REASON_CODES = [
        '41', '42', '60', '62', '63', '64', '65', '66', '67', '68', '70', '71',
        '88', '95', '100', '102', '103', '104', '105',
    ];

    /**
     * The UNTDID 7161 codes BR-CL-20 allows for charge reasons (BT-105/BT-145).
     *
     * @var list<string>
     */
    public const array CHARGE_REASON_CODES = [
        'AA', 'AAA', 'AAC', 'AAD', 'AAE', 'AAF', 'AAH', 'AAI', 'AAS', 'AAT', 'AAV', 'AAY',
        'AAZ', 'ABA', 'ABB', 'ABC', 'ABD', 'ABF', 'ABK', 'ABL', 'ABN', 'ABR', 'ABS', 'ABT',
        'ABU', 'ACF', 'ACG', 'ACH', 'ACI', 'ACJ', 'ACK', 'ACL', 'ACM', 'ACS', 'ADC', 'ADE',
        'ADJ', 'ADK', 'ADL', 'ADM', 'ADN', 'ADO', 'ADP', 'ADQ', 'ADR', 'ADT', 'ADW', 'ADY',
        'ADZ', 'AEA', 'AEB', 'AEC', 'AED', 'AEF', 'AEH', 'AEI', 'AEJ', 'AEK', 'AEL', 'AEM',
        'AEN', 'AEO', 'AEP', 'AES', 'AET', 'AEU', 'AEV', 'AEW', 'AEX', 'AEY', 'AEZ', 'AJ',
        'AU', 'CA', 'CAB', 'CAD', 'CAE', 'CAF', 'CAI', 'CAJ', 'CAK', 'CAL', 'CAM', 'CAN',
        'CAO', 'CAP', 'CAQ', 'CAR', 'CAS', 'CAT', 'CAU', 'CAV', 'CAW', 'CAX', 'CAY', 'CAZ',
        'CD', 'CG', 'CS', 'CT', 'DAB', 'DAD', 'DAC', 'DAF', 'DAG', 'DAH', 'DAI', 'DAJ',
        'DAK', 'DAL', 'DAM', 'DAN', 'DAO', 'DAP', 'DAQ', 'DL', 'EG', 'EP', 'ER', 'FAA',
        'FAB', 'FAC', 'FC', 'FH', 'FI', 'GAA', 'HAA', 'HD', 'HH', 'IAA', 'IAB', 'ID',
        'IF', 'IR', 'IS', 'KO', 'L1', 'LA', 'LAA', 'LAB', 'LF', 'MAE', 'MI', 'ML',
        'NAA', 'OA', 'PA', 'PAA', 'PC', 'PL', 'PRV', 'RAB', 'RAC', 'RAD', 'RAF', 'RE',
        'RF', 'RH', 'RV', 'SA', 'SAA', 'SAD', 'SAE', 'SAI', 'SG', 'SH', 'SM', 'SU',
        'TAB', 'TAC', 'TT', 'TV', 'V1', 'V2', 'WH', 'XAA', 'YY', 'ZZZ',
    ];

    /**
     * The CEF VATEX codes BR-CL-22 allows for exemption reason codes (BT-121).
     *
     * @var list<string>
     */
    public const array VATEX_CODES = [
        'VATEX-EU-79-C', 'VATEX-EU-132', 'VATEX-EU-132-1A', 'VATEX-EU-132-1B', 'VATEX-EU-132-1C', 'VATEX-EU-132-1D',
        'VATEX-EU-132-1E', 'VATEX-EU-132-1F', 'VATEX-EU-132-1G', 'VATEX-EU-132-1H', 'VATEX-EU-132-1I', 'VATEX-EU-132-1J',
        'VATEX-EU-132-1K', 'VATEX-EU-132-1L', 'VATEX-EU-132-1M', 'VATEX-EU-132-1N', 'VATEX-EU-132-1O', 'VATEX-EU-132-1P',
        'VATEX-EU-132-1Q', 'VATEX-EU-135-1', 'VATEX-EU-143', 'VATEX-EU-143-1A', 'VATEX-EU-143-1B', 'VATEX-EU-143-1C',
        'VATEX-EU-143-1D', 'VATEX-EU-143-1E', 'VATEX-EU-143-1F', 'VATEX-EU-143-1FA', 'VATEX-EU-143-1G', 'VATEX-EU-143-1H',
        'VATEX-EU-143-1I', 'VATEX-EU-143-1J', 'VATEX-EU-143-1K', 'VATEX-EU-143-1L', 'VATEX-EU-144', 'VATEX-EU-146-1E',
        'VATEX-EU-159', 'VATEX-EU-309', 'VATEX-EU-148', 'VATEX-EU-148-A', 'VATEX-EU-148-B', 'VATEX-EU-148-C',
        'VATEX-EU-148-D', 'VATEX-EU-148-E', 'VATEX-EU-148-F', 'VATEX-EU-148-G', 'VATEX-EU-151', 'VATEX-EU-151-1A',
        'VATEX-EU-151-1AA', 'VATEX-EU-151-1B', 'VATEX-EU-151-1C', 'VATEX-EU-151-1D', 'VATEX-EU-151-1E', 'VATEX-EU-G',
        'VATEX-EU-O', 'VATEX-EU-IC', 'VATEX-EU-AE', 'VATEX-EU-D', 'VATEX-EU-F', 'VATEX-EU-I',
        'VATEX-EU-J', 'VATEX-FR-FRANCHISE', 'VATEX-FR-CNWVAT', 'VATEX-EU-153', 'VATEX-FR-CGI261-1', 'VATEX-FR-CGI261-2',
        'VATEX-FR-CGI261-3', 'VATEX-FR-CGI261-4', 'VATEX-FR-CGI261-5', 'VATEX-FR-CGI261-7', 'VATEX-FR-CGI261-8', 'VATEX-FR-CGI261A',
        'VATEX-FR-CGI261B', 'VATEX-FR-CGI261C-1', 'VATEX-FR-CGI261C-2', 'VATEX-FR-CGI261C-3', 'VATEX-FR-CGI261D-1', 'VATEX-FR-CGI261D-1BIS',
        'VATEX-FR-CGI261D-2', 'VATEX-FR-CGI261D-3', 'VATEX-FR-CGI261D-4', 'VATEX-FR-CGI261E-1', 'VATEX-FR-CGI261E-2', 'VATEX-FR-CGI277A',
        'VATEX-FR-CGI275', 'VATEX-FR-298SEXDECIESA', 'VATEX-FR-CGI295', 'VATEX-FR-AE',
    ];

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
