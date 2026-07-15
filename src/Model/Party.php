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
        public ?string $street = null,             // BT-35 / BT-50
        public ?string $city = null,               // BT-37 / BT-52
        public ?string $postCode = null,           // BT-38 / BT-53
        public ?string $electronicAddress = null,  // BT-34 / BT-49
        public ?string $electronicAddressScheme = null, // BT-34-1 / BT-49-1 (@schemeID)
    ) {}

    /**
     * Whether the postal address group (BG-5 / BG-8) is present — any of its
     * fields carries a value.
     */
    public function hasPostalAddress(): bool
    {
        return array_any([$this->street, $this->city, $this->postCode, $this->countryCode], fn (?string $value): bool => $value !== null && $value !== '');
    }

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
