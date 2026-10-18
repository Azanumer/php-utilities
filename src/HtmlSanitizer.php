<?php
/**
 * HtmlSanitizer — dependency-free allow-list HTML sanitizer for PHP 7.4+.
 *
 * Strips everything not explicitly allowed: unknown tags are unwrapped
 * (their text survives), script/style/iframe-ish tags are dropped entirely,
 * event-handler attributes and javascript:/data: URLs are removed.
 *
 *   $clean = (new HtmlSanitizer())->sanitize($userHtml);
 *
 *   $strict = (new HtmlSanitizer())
 *       ->allowTags(['p', 'br', 'strong', 'em', 'a'])
 *       ->allowAttributes('a', ['href', 'title'])
 *       ->sanitize($html);
 */
class HtmlSanitizer
{
    /** @var string[] Tags that survive (others are unwrapped or dropped). */
    private $allowedTags = [
        'p', 'br', 'hr', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'small',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'div', 'span',
    ];

    /** @var string[] Tags dropped WITH their content (never unwrapped). */
    private $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'noscript'];

    /** @var array tag => [allowed attribute names]. */
    private $allowedAttributes = [
        'a'   => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan'],
        '*'   => ['class', 'id'],
    ];

    /** @var string[] URL schemes considered safe in href/src. */
    private $allowedSchemes = ['http', 'https', 'mailto', 'tel'];

    /** Override the tag allow-list. */
    public function allowTags(array $tags): self
    {
        $this->allowedTags = array_map('strtolower', $tags);
        return $this;
    }

    /** Set allowed attributes for a tag ('*' = global). */
    public function allowAttributes(string $tag, array $attrs): self
    {
        $this->allowedAttributes[strtolower($tag)] = array_map('strtolower', $attrs);
        return $this;
    }

    /** Sanitize an HTML fragment; returns clean HTML. */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Wrap in a container so fragments parse reliably; force UTF-8.
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="__sanitize_root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $doc->getElementById('__sanitize_root__');
        if ($root === null) {
            return '';
        }

        $this->cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /** Recursively clean a node in place. */
    private function cleanNode(DOMNode $node): void
    {
        // Iterate over a snapshot: children may be removed/replaced during the walk.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, $this->dropWithContent, true)) {
                    $node->removeChild($child);
                    continue;
                }

                if (!in_array($tag, $this->allowedTags, true)) {
                    // Unwrap: keep children (text survives), drop the tag itself.
                    $this->unwrap($child);
                    continue;
                }

                $this->cleanAttributes($child, $tag);
                $this->cleanNode($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
            // Text/CDATA nodes pass through untouched.
        }
    }

    /** Replace an element with its children (used for disallowed inline tags). */
    private function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /** Strip disallowed attributes, event handlers, and dangerous URLs. */
    private function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(
            $this->allowedAttributes['*'] ?? [],
            $this->allowedAttributes[$tag] ?? []
        );

        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[] = $attr->nodeName;
        }

        foreach ($attrs as $name) {
            $lower = strtolower($name);

            // Kill all event handlers (on*) and style attributes outright.
            if (strpos($lower, 'on') === 0 || $lower === 'style' || strpos($lower, 'xmlns') === 0) {
                $el->removeAttribute($name);
                continue;
            }

            if (!in_array($lower, $allowed, true)) {
                $el->removeAttribute($name);
                continue;
            }

            // URL-bearing attributes: enforce safe schemes.
            if (in_array($lower, ['href', 'src', 'action'], true)) {
                $value = trim($el->getAttribute($name));
                if (!$this->isSafeUrl($value)) {
                    $el->removeAttribute($name);
                }
            }
        }

        // Force safe rel on external links opened in a new tab.
        if ($tag === 'a' && strtolower($el->getAttribute('target')) === '_blank') {
            $rel = $el->getAttribute('rel');
            if (stripos($rel, 'noopener') === false) {
                $el->setAttribute('rel', trim($rel . ' noopener'));
            }
        }
    }

    /** True for http(s), mailto:, tel:, protocol-relative, anchors, and relative URLs. */
    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || $url[0] === '#' || $url[0] === '/') {
            return true; // anchors, absolute/relative paths
        }
        if (strpos($url, '//') === 0) {
            return true; // protocol-relative
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === '') {
            return true; // relative URL, no scheme
        }
        return in_array($scheme, $this->allowedSchemes, true);
    }
}
