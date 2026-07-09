<?php

declare(strict_types=1);

namespace JohnWink\En16931\Contracts;

use JohnWink\En16931\Model\Invoice;
use JohnWink\En16931\Violation;

/**
 * One EN 16931 (or XRechnung CIUS) business rule. A rule inspects the normalized
 * {@see Invoice} model and returns the violations it found (empty when it holds).
 */
interface Rule
{
    /**
     * The rule identifier as used by the EN 16931 / KoSIT rule sets (e.g. "BR-27",
     * "BR-CO-10", "BR-DE-1"), so messages line up with the official validator.
     */
    public function id(): string;

    /**
     * @return list<Violation>
     */
    public function evaluate(Invoice $invoice): array;
}
