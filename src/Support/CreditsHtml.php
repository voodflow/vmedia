<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

/**
 * Allowlist sanitizer for media credit strings (Unsplash-style HTML with &lt;a href&gt;).
 */
final class CreditsHtml
{
    /**
     * Keep plain text and safe http(s) links only.
     */
    public static function sanitize(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        if (! str_contains($raw, '<')) {
            return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="vb-credits-root">'.strip_tags($raw, '<a>').'</div>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('vb-credits-root');

        if (! $root instanceof \DOMElement) {
            return htmlspecialchars(strip_tags($raw), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $out = new \DOMDocument('1.0', 'UTF-8');
        $container = $out->createElement('div');
        $out->appendChild($container);
        self::copySafeNodes($root, $container, $out);

        $html = '';

        foreach ($container->childNodes as $child) {
            $html .= $out->saveHTML($child);
        }

        return $html;
    }

    private static function copySafeNodes(\DOMNode $from, \DOMElement $to, \DOMDocument $out): void
    {
        foreach ($from->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $to->appendChild($out->createTextNode((string) $child->textContent));

                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE || ! $child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'a') {
                self::copySafeNodes($child, $to, $out);

                continue;
            }

            $href = trim((string) $child->getAttribute('href'));

            if (! preg_match('#^https?://#i', $href)) {
                self::copySafeNodes($child, $to, $out);

                continue;
            }

            $anchor = $out->createElement('a');
            $anchor->setAttribute('href', $href);
            $anchor->setAttribute('target', '_blank');
            $anchor->setAttribute('rel', 'noopener noreferrer');
            self::copySafeNodes($child, $anchor, $out);
            $to->appendChild($anchor);
        }
    }
}
