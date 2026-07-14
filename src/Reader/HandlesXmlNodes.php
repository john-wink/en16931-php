<?php

declare(strict_types=1);

namespace JohnWink\En16931\Reader;

use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Shared DOM/XPath accessors for the syntax readers. Every lookup is
 * false-safe and returns only element nodes and their trimmed text.
 */
trait HandlesXmlNodes
{
    private function node(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): ?DOMElement
    {
        $list = $domNode instanceof DOMNode ? $domxPath->query($query, $domNode) : $domxPath->query($query);

        if ($list === false) {
            return null;
        }

        $node = $list->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * @return list<DOMElement>
     */
    private function nodes(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): array
    {
        $list = $domNode instanceof DOMNode ? $domxPath->query($query, $domNode) : $domxPath->query($query);

        if ($list === false) {
            return [];
        }

        $elements = [];
        foreach ($list as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function value(DOMXPath $domxPath, string $query, ?DOMNode $domNode = null): ?string
    {
        $node = $this->node($domxPath, $query, $domNode);

        return $node instanceof DOMElement ? $this->text($node) : null;
    }

    private function text(DOMElement $domElement): ?string
    {
        $text = mb_trim($domElement->nodeValue ?? '');

        return $text === '' ? null : $text;
    }
}
