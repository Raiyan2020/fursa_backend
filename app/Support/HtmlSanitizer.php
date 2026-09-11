<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for user-authored rich text.
 *
 * Descriptions are written in the frontend's TipTap editor and stored as raw
 * HTML. They were validated as `string` only, so markup such as
 * `<img src=x onerror=...>` was stored verbatim and served to every reader of
 * the public detail endpoints — the Next.js app, the admin Blade views, and any
 * export. The frontend sanitizes on render, but that protects only that one
 * client; sanitizing on write is what makes the stored value safe for all of
 * them.
 *
 * Built on DOMDocument rather than a Composer package deliberately: the parser
 * ships with PHP, so there is no new dependency to install at deploy time.
 * Anything not on the allowlist is unwrapped (children kept, tag dropped) so
 * text survives even when its markup does not.
 */
class HtmlSanitizer
{
    /**
     * Tags the editor can emit. Everything else is unwrapped or removed.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'mark',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
    ];

    /**
     * Attributes allowed per tag. No `on*` handler appears anywhere.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start'],
        'code' => ['class'],
        'pre' => ['class'],
        'span' => ['class'],
        'div' => ['class'],
        'p' => ['class'],
    ];

    /**
     * Dropped with their contents — unwrapping these would leak code as text.
     *
     * @var list<string>
     */
    private const STRIP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'noscript'];

    /**
     * URL schemes permitted in href/src.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize a rich-text value, preserving null and blank input as-is.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        if (trim($html) === '') {
            return $html;
        }

        // No tags at all: nothing to strip, and round-tripping through the
        // parser would needlessly re-encode plain text.
        if (! str_contains($html, '<')) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        // The meta charset keeps DOMDocument from mis-reading Arabic as
        // ISO-8859-1; the wrapper gives a single predictable node to unwrap.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="fursa-sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable markup is not worth guessing at; drop tags entirely.
            return strip_tags($html);
        }

        $root = $document->getElementById('fursa-sanitizer-root');
        if (! $root) {
            return strip_tags($html);
        }

        self::sanitizeChildren($root, $document);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /**
     * Sanitize every rich-text key present in a validated payload.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function cleanFields(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && (is_string($data[$key]) || $data[$key] === null)) {
                $data[$key] = self::clean($data[$key]);
            }
        }

        return $data;
    }

    private static function sanitizeChildren(DOMNode $node, DOMDocument $document): void
    {
        // Snapshot first: the live NodeList shifts as nodes are removed.
        foreach (iterator_to_array($node->childNodes) as $child) {
            self::sanitizeNode($child, $document);
        }
    }

    private static function sanitizeNode(DOMNode $node, DOMDocument $document): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                $node->parentNode?->removeChild($node);

                return;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($node, $document);

                return;
            }

            self::filterAttributes($node, $tag);
            self::sanitizeChildren($node, $document);

            return;
        }

        // Comments can carry conditional-comment script in some parsers.
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node->nodeType === XML_PI_NODE || $node->nodeType === XML_DOCUMENT_TYPE_NODE) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Replace a disallowed element with its children, keeping the text.
     */
    private static function unwrap(DOMElement $element, DOMDocument $document): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            self::sanitizeNode($child, $document);

            // sanitizeNode may have detached it.
            if ($child->parentNode === $element) {
                $parent->insertBefore($child, $element);
            }
        }

        $parent->removeChild($element);
    }

    private static function filterAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! self::isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // A link opening in a new tab without noopener hands the opener window
        // to the destination.
        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(?string $url): bool
    {
        $value = trim((string) $url);

        if ($value === '') {
            return false;
        }

        // Strip characters a browser ignores but a naive check would not, so
        // "java\0script:" and "java\tscript:" cannot slip past.
        $normalized = strtolower(preg_replace('/[\x00-\x20]/', '', $value) ?? '');

        // Relative URLs and in-page anchors carry no scheme and are fine.
        if (str_starts_with($normalized, '/') || str_starts_with($normalized, '#')) {
            return true;
        }

        if (! str_contains($normalized, ':')) {
            return true;
        }

        // data: is excluded even for images: data:image/svg+xml can carry script.
        foreach (self::ALLOWED_SCHEMES as $scheme) {
            if (str_starts_with($normalized, $scheme.':')) {
                return true;
            }
        }

        return false;
    }
}
