<?php

declare(strict_types=1);

namespace JohnWink\En16931\Syntax;

use DOMNode;

/**
 * The handful of XPath 2.0 functions the KoSIT syntax rules use that XPath 1.0
 * (PHP's DOMXPath) lacks. Registered on the {@see SchematronEngine}'s DOMXPath
 * via registerPhpFunctions and called from rewritten `php:function(...)` tests.
 */
final class XPathFunctions
{
    /**
     * XPath 2.0 matches($input, $pattern): whether the string contains a match
     * for the (XSD) regular expression. The KoSIT patterns are PCRE-compatible.
     */
    public static function matches(string $value, string $pattern): bool
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/u';

        return @preg_match($delimited, $value) === 1;
    }

    /**
     * The count of distinct string values in a node set — count(distinct-values(X)).
     *
     * @param  list<DOMNode>|DOMNode  $nodes
     */
    public static function distinctCount(array|DOMNode $nodes): int
    {
        $nodes = is_array($nodes) ? $nodes : [$nodes];
        $values = [];

        foreach ($nodes as $node) {
            $values[trim($node->nodeValue ?? '')] = true;
        }

        return count($values);
    }
}
