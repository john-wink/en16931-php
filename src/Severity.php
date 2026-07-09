<?php

declare(strict_types=1);

namespace JohnWink\En16931;

/**
 * The severity of a validation {@see Violation}, mirroring the levels the EN
 * 16931 / KoSIT Schematron rule sets emit.
 */
enum Severity: string
{
    case Fatal = 'fatal';
    case Warning = 'warning';
    case Information = 'information';
}
