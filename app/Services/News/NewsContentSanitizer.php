<?php

namespace App\Services\News;

use DOMDocument;
use DOMElement;
use DOMNode;

class NewsContentSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    public function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }
        if (! class_exists(DOMDocument::class)) {
            // Attribute-level sanitization is impossible without a DOM
            // parser. Fall back to escaped plain text instead of retaining
            // allowed tags together with attacker-controlled attributes.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="news-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('news-root');
        if (! $root) {
            return null;
        }
        $this->cleanChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result) ?: null;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = mb_strtolower($node->tagName);
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'], true)) {
                        $parent->removeChild($node);

                        continue;
                    }
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);

                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = mb_strtolower($attribute->name);
                    if ($tag !== 'a' || ! in_array($name, ['href', 'title', 'target', 'rel'], true)) {
                        $node->removeAttribute($attribute->name);
                    }
                }
                if ($tag === 'a') {
                    $href = trim($node->getAttribute('href'));
                    if ($href !== '' && ! preg_match('~^(https?://|mailto:|tel:|/|#)~i', $href)) {
                        $node->removeAttribute('href');
                    }
                    if ($node->getAttribute('target') === '_blank') {
                        $node->setAttribute('rel', 'noopener noreferrer');
                    }
                }
                $this->cleanChildren($node);
            }
        }
    }
}
