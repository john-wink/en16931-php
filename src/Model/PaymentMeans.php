<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * One payment instruction (BG-16) with its credit transfer (BG-17), payment
 * card (BG-18) and direct debit (BG-19) details. The group flags mirror the
 * official exists() checks — a group may be present yet carry no values.
 */
final readonly class PaymentMeans
{
    public function __construct(
        public ?string $typeCode = null,          // BT-81 (UNTDID 4461)
        public ?string $accountId = null,         // BT-84 payment account identifier (IBAN)
        public bool $hasCreditTransfer = false,   // BG-17 group present
        public bool $hasCardInformation = false,  // BG-18 group present
        public ?string $cardNumber = null,        // BT-87 primary account number
        public bool $hasDirectDebit = false,      // BG-19 group present
        public ?string $debitedAccountId = null,  // BT-91 debited account identifier (IBAN)
    ) {}
}
