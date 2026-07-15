<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * One item attribute (BG-32): name (BT-160) and value (BT-161).
 */
final readonly class ItemAttribute
{
    public function __construct(
        public ?string $name = null,  // BT-160
        public ?string $value = null, // BT-161
    ) {}
}
