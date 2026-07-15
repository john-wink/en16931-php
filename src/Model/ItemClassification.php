<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * One item classification (BT-158) with its scheme identifier (@listID).
 */
final readonly class ItemClassification
{
    public function __construct(
        public ?string $code = null,   // BT-158
        public ?string $scheme = null, // BT-158-1 (@listID)
    ) {}
}
