<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A document-level allowance (BG-20) or charge (BG-21). Amounts are the raw
 * decimal strings from the XML.
 */
final readonly class DocumentAllowanceCharge
{
    public function __construct(
        public bool $isCharge,               // BT-20-1 charge indicator (false = allowance)
        public ?string $amount = null,        // BT-92 (allowance) / BT-99 (charge)
        public ?string $taxCategory = null,   // BT-95 / BT-102
        public ?string $taxRate = null,       // BT-96 / BT-103
        public ?string $reason = null,        // BT-97 / BT-104
    ) {}
}
