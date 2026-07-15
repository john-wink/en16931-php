<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A sub invoice line (BG-DEX-01, XRechnung extension) — only the fields the
 * BR-DEX rules inspect.
 */
final readonly class SubInvoiceLine
{
    public function __construct(
        public ?string $netAmount = null, // sub line net amount (BT-131 equivalent)
        public int $vatCategoryCount = 0, // occurrences of the sub line VAT information (BG-DEX-06)
    ) {}
}
