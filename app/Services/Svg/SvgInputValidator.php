<?php

namespace App\Services\Svg;

use DOMDocument;
use DOMElement;
use DOMNode;

class SvgInputValidator
{
    /**
     * Validate an SVG string for basic correctness, size limits, and security hazards.
     *
     * @param string $svg
     * @param array{max_bytes?:int,max_width?:int,max_height?:int,allow_remote_refs?:bool} $options
     * @return array{valid:bool, errors:array<int,string>, sanitized?:string}
     */
    public function validate(string $svg, array $options = []): array
    {
        $errors = [];

        // Read config keys defensively because some tests stub a minimal config() or Laravel helpers may exist without a booted container
        $hasContainer = class_exists(\Illuminate\Container\Container::class) && \Illuminate\Container\Container::getInstance();
        $cfgMaxBytes = null;
        $cfgMaxWidth = null;
        $cfgMaxHeight = null;
        $cfgAllowRemote = null;
        if ($hasContainer && function_exists('config')) {
            try {
                $cfgMaxBytes = config('svg.max_bytes');
                $cfgMaxWidth = config('svg.max_width');
                $cfgMaxHeight = config('svg.max_height');
                $cfgAllowRemote = config('svg.allow_remote_refs');
            } catch (\Throwable $e) {
                // fall back to defaults below
            }
        }

        $maxBytes = $options['max_bytes'] ?? (is_numeric($cfgMaxBytes) ? (int) $cfgMaxBytes : (512 * 1024)); // 512KB default
        $maxWidth = $options['max_width'] ?? (is_numeric($cfgMaxWidth) ? (int) $cfgMaxWidth : 4096);
        $maxHeight = $options['max_height'] ?? (is_numeric($cfgMaxHeight) ? (int) $cfgMaxHeight : 4096);
        $allowRemote = $options['allow_remote_refs'] ?? (is_bool($cfgAllowRemote) ? $cfgAllowRemote : false);

        if ($svg === '' || trim($svg) === '') {
            $errors[] = 'SVG content is empty';
            return ['valid' => false, 'errors' => $errors];
        }

        if (strlen($svg) > $maxBytes) {
            $errors[] = 'SVG exceeds maximum allowed size';
        }

        $doc = new DOMDocument();
        // Security-related libxml flags
        $prev = libxml_use_internal_errors(true);
        $loadOk = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loadOk || !$doc->documentElement || strtolower($doc->documentElement->nodeName) !== 'svg') {
            $errors[] = 'Invalid SVG XML structure or missing <svg> root element';
            return ['valid' => false, 'errors' => $errors];
        }

        /** @var DOMElement $root */
        $root = $doc->documentElement;

        // Basic size checks (width/height attributes if present)
        $width = $this->extractPositiveNumber($root->getAttribute('width'));
        $height = $this->extractPositiveNumber($root->getAttribute('height'));
        if ($width !== null && $width > $maxWidth) {
            $errors[] = 'SVG width exceeds limit';
        }
        if ($height !== null && $height > $maxHeight) {
            $errors[] = 'SVG height exceeds limit';
        }

        // Security: detect disallowed elements/attributes
        $dangerFound = $this->findDanger($root, $allowRemote);
        $errors = array_merge($errors, $dangerFound);

        $sanitized = $this->sanitize($svg, $options);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * Sanitize an SVG by removing dangerous elements and attributes.
     *
     * @param string $svg
     * @param array{allow_remote_refs?:bool} $options
     * @return string
     */
    public function sanitize(string $svg, array $options = []): string
    {
        $allowRemote = $options['allow_remote_refs'] ?? (function_exists('config') ? (bool) config('svg.allow_remote_refs', false) : false);

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$doc->documentElement) {
            return '';
        }

        $root = $doc->documentElement;

        $this->sanitizeNode($root, $allowRemote);

        // Remove DOCTYPE if any (prevents entity expansion vectors)
        if ($doc->doctype) {
            $doc->removeChild($doc->doctype);
        }

        return $doc->saveXML($root);
    }

    private function sanitizeNode(DOMNode $node, bool $allowRemote): void
    {
        if ($node instanceof DOMElement) {
            $name = strtolower($node->nodeName);

            // Drop outright dangerous elements
            $blockedElements = [
                'script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'image', // image handled via <img> in foreignObject; <image> in SVG can embed external
                'link', 'style' // style could embed urls/javascript in some cases; conservative choice
            ];
            if (in_array($name, $blockedElements, true)) {
                $node->parentNode?->removeChild($node);
                return;
            }

            // Remove event handler attributes and javascript/data URLs
            if ($node->hasAttributes()) {
                $toRemove = [];
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $attrName = strtolower($attr->nodeName);
                    $attrVal = trim($attr->nodeValue ?? '');

                    if (str_starts_with($attrName, 'on')) {
                        $toRemove[] = $attrName;
                        continue;
                    }

                    // xlink:href or href pointing to remote or javascript
                    if (in_array($attrName, ['href', 'xlink:href'], true)) {
                        $lower = strtolower($attrVal);
                        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:text/html') || str_starts_with($lower, 'vbscript:')) {
                            $toRemove[] = $attrName;
                            continue;
                        }
                        if (!$allowRemote && (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://'))) {
                            $toRemove[] = $attrName;
                            continue;
                        }
                    }
                }
                foreach ($toRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Recurse for children (use snapshot to avoid issues when removing)
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $this->sanitizeNode($child, $allowRemote);
        }
    }

    private function extractPositiveNumber(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Extract leading numeric part
        if (preg_match('/^(\d+(?:\.\d+)?)/', $value, $m)) {
            $n = (float)$m[1];
            return $n > 0 ? (int) ceil($n) : null;
        }
        return null;
    }

    private function findDanger(DOMElement $root, bool $allowRemote): array
    {
        $errors = [];
        $walker = function (DOMNode $node) use (&$walker, &$errors, $allowRemote) {
            if ($node instanceof DOMElement) {
                $name = strtolower($node->nodeName);
                $blocked = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'image', 'link', 'style'];
                if (in_array($name, $blocked, true)) {
                    $errors[] = "Disallowed element: <$name>";
                }
                if ($node->hasAttributes()) {
                    foreach (iterator_to_array($node->attributes) as $attr) {
                        $attrName = strtolower($attr->nodeName);
                        $attrVal = trim($attr->nodeValue ?? '');
                        // Event handler attributes (on*) will be sanitized but do not mark as validation errors
                        if (str_starts_with($attrName, 'on')) {
                            continue;
                        }
                        if (in_array($attrName, ['href', 'xlink:href'], true)) {
                            $lower = strtolower($attrVal);
                            if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:')) {
                                $errors[] = 'JavaScript URLs are not allowed';
                            }
                            if (!$allowRemote && (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://'))) {
                                $errors[] = 'Remote URLs are not allowed';
                            }
                        }
                    }
                }
            }
            foreach ($node->childNodes as $child) {
                $walker($child);
            }
        };

        $walker($root);
        return $errors;
    }
}
