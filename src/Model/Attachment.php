<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

/**
 * An additional supporting document (BG-24) — or any other document reference
 * on the invoice, since BR-52 covers every reference alike.
 */
final readonly class Attachment
{
    public function __construct(
        public ?string $reference = null, // BT-122 supporting document reference
        public ?string $filename = null,  // BT-125 @filename of the embedded binary
        public ?string $mimeCode = null,  // BT-125 @mimeCode of the embedded binary
    ) {}
}
