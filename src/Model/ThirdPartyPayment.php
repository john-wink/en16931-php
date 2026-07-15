<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A third party payment (BG-DEX-09, XRechnung extension): an amount already
 * paid to a third party that reduces the amount due.
 */
final readonly class ThirdPartyPayment
{
    public function __construct(
        public ?string $id = null,          // BT-DEX-001 third party payment type
        public ?string $amount = null,      // BT-DEX-002 third party payment amount
        public ?string $description = null, // BT-DEX-003 third party payment description
        public ?string $currency = null,    // @currencyID of the amount
    ) {}
}
