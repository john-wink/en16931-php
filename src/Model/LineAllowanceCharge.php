<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A line-level allowance (BG-27) or charge (BG-28). Amounts are the raw decimal
 * strings from the XML.
 */
final readonly class LineAllowanceCharge
{
    public function __construct(
        public bool $isCharge,             // BT-27-1 charge indicator (false = allowance)
        public ?string $amount = null,      // BT-136 (allowance) / BT-141 (charge)
        public ?string $reason = null,      // BT-139 / BT-144
        public ?string $reasonCode = null,  // BT-140 / BT-145
    ) {}
}
