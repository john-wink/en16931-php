<?php

declare(strict_types=1);

use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Model\InvoiceLine;
use JohnWink\En16931\Model\Party;
use JohnWink\En16931\Model\TaxSubtotal;
use JohnWink\En16931\Model\Totals;

/**
 * Build a fully EN-16931-and-XRechnung-valid invoice model. Named arguments
 * land in $overrides (variadic), so a test can set any field — including null —
 * with array_key_exists semantics.
 */
function makeInvoice(mixed ...$overrides): Invoice
{
    $pick = static fn (string $key, mixed $default): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $default;

    return new Invoice(
        number: $pick('number', 'R-2026-1'),
        typeCode: $pick('typeCode', '380'),
        issueDate: $pick('issueDate', '2026-01-15'),
        currency: $pick('currency', 'EUR'),
        taxCurrency: $pick('taxCurrency', null),
        buyerReference: $pick('buyerReference', '04011000-12345-06'),
        customizationId: $pick('customizationId', 'urn:cen.eu:en16931:2017'),
        seller: $pick('seller', new Party(
            name: 'Seller GmbH',
            countryCode: 'DE',
            vatId: 'DE123456789',
            contactName: 'Max Muster',
            contactPhone: '+49 30 000000',
            contactEmail: 'billing@seller.de',
        )),
        buyer: $pick('buyer', new Party(name: 'Buyer AG', countryCode: 'DE', vatId: 'DE987654321')),
        totals: $pick('totals', new Totals(
            lineTotal: '100.00',
            taxBasisTotal: '100.00',
            taxTotal: '19.00',
            grandTotal: '119.00',
            paidAmount: '0.00',
            payableAmount: '119.00',
        )),
        lines: $pick('lines', [new InvoiceLine(
            id: '1',
            name: 'Beratung',
            netAmount: '100.00',
            netPrice: '100.00',
            quantity: '1',
            unitCode: 'C62',
            taxCategory: 'S',
            taxRate: '19.00',
        )]),
        taxSubtotals: $pick('taxSubtotals', [new TaxSubtotal(
            category: 'S',
            rate: '19.00',
            taxableAmount: '100.00',
            taxAmount: '19.00',
        )]),
        notes: $pick('notes', []),
        allowanceCharges: $pick('allowanceCharges', []),
    );
}
