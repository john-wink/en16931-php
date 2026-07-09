<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A trade party (seller BG-4 / buyer BG-7) as read from the invoice.
 */
final readonly class Party
{
    public function __construct(
        public ?string $name = null,          // BT-27 / BT-44
        public ?string $countryCode = null,   // BT-40 / BT-55
        public ?string $vatId = null,          // BT-31 / BT-48
        public ?string $contactName = null,    // BT-41
        public ?string $contactPhone = null,   // BT-42
        public ?string $contactEmail = null,   // BT-43
    ) {}

    public function hasVatId(): bool
    {
        return $this->vatId !== null && $this->vatId !== '';
    }
}
