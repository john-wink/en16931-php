<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * One invoice line (BG-25). Amounts are the raw decimal strings from the XML.
 */
final readonly class InvoiceLine
{
    /**
     * @param  list<LineAllowanceCharge>  $allowanceCharges
     */
    public function __construct(
        public ?string $id = null,          // BT-126 line identifier
        public ?string $name = null,        // BT-153 item name
        public ?string $netAmount = null,   // BT-131 line net amount
        public ?string $netPrice = null,    // BT-146 item net price
        public ?string $quantity = null,    // BT-129 invoiced quantity
        public ?string $unitCode = null,    // BT-130 unit of measure
        public ?string $taxCategory = null, // BT-151 line VAT category code
        public ?string $taxRate = null,     // BT-152 line VAT rate
        public array $allowanceCharges = [], // BG-27 / BG-28
        public bool $hasPeriod = false,     // BG-26 group present
        public ?string $periodStart = null, // BT-134 (normalized to Y-m-d)
        public ?string $periodEnd = null,   // BT-135 (normalized to Y-m-d)
        public ?string $grossPrice = null,  // BT-148 item gross price
        public ?string $itemStandardId = null,       // BT-157
        public ?string $itemStandardIdScheme = null, // BT-157-1 (@schemeID)
        /** @var list<ItemClassification> */
        public array $itemClassifications = [],      // BT-158
        /** @var list<ItemAttribute> */
        public array $attributes = [],               // BG-32
        public ?string $originCountryCode = null,    // BT-159
    ) {}
}
