<?php

declare(strict_types=1);

namespace JohnWink\En16931\Model;

final readonly class CustomerRecord
{
    public function __construct(public string $customerName) {}
}
