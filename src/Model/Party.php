<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * A trade party (seller BG-4 / buyer BG-7) as read from the invoice.
 */
final readonly class Party
{
    public function __construct(
        public ?string $name = null,               // BT-27 / BT-44
        public ?string $countryCode = null,        // BT-40 / BT-55
        public ?string $vatId = null,              // BT-31 / BT-48
        public ?string $identifier = null,         // BT-29 / BT-46
        public ?string $legalRegistrationId = null, // BT-30 / BT-47
        public ?string $contactName = null,        // BT-41
        public ?string $contactPhone = null,       // BT-42
        public ?string $contactEmail = null,        // BT-43
        public ?string $taxRegistrationId = null,  // BT-32 (seller tax registration, e.g. FC scheme)
    ) {}

    public function hasVatId(): bool
    {
        return $this->vatId !== null && $this->vatId !== '';
    }

    /**
     * Whether the party can be identified — a scheme identifier (BT-29/46), a
     * legal registration (BT-30/47) or a VAT identifier (BT-31/48).
     */
    public function hasAnyIdentifier(): bool
    {
        return array_any([$this->identifier, $this->legalRegistrationId, $this->vatId], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
