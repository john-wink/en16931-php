<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * The document totals (BG-22). Amounts are the raw decimal strings from the XML;
 * a null means the element was absent.
 */
final readonly class Totals
{
    public function __construct(
        public ?string $lineTotal = null,       // BT-106 sum of line net amounts
        public ?string $allowanceTotal = null,  // BT-107 document allowances
        public ?string $chargeTotal = null,     // BT-108 document charges
        public ?string $taxBasisTotal = null,   // BT-109 total without VAT
        public ?string $taxTotal = null,        // BT-110 total VAT
        public ?string $grandTotal = null,      // BT-112 total with VAT
        public ?string $paidAmount = null,      // BT-113 prepaid amount
        public ?string $roundingAmount = null,  // BT-114 rounding amount
        public ?string $payableAmount = null,   // BT-115 amount due for payment
    ) {}
}
