<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * One VAT breakdown group (BG-23). Amounts are the raw decimal strings from
 * the XML.
 */
final readonly class TaxSubtotal
{
    public function __construct(
        public ?string $category = null,             // BT-118 VAT category code
        public ?string $rate = null,                 // BT-119 VAT category rate
        public ?string $taxableAmount = null,        // BT-116 taxable amount
        public ?string $taxAmount = null,            // BT-117 tax amount
        public ?string $exemptionReason = null,      // BT-120 exemption reason text
        public ?string $exemptionReasonCode = null,  // BT-121 exemption reason code
    ) {}
}
