<?php

declare(strict_types=1);

/**
 * Render Editor.js document JSON to safe HTML (no arbitrary tags from user).
 * Link protocol allowlist: http, https only.
 */
if (! function_exists('editorjs_to_html')) {
    function editorjs_to_html(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if ($raw[0] !== '{') {
            return esc($raw);
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return esc($raw);
        }
        $blocks = $data['blocks'] ?? null;
        if (! is_array($blocks) || $blocks === []) {
            return esc($raw);
        }

        $out = '';
        foreach ($blocks as $blk) {
            if (! is_array($blk)) {
                continue;
            }
            $type = (string) ($blk['type'] ?? '');
            $d    = $blk['data'] ?? [];
            if (! is_array($d)) {
                $d = [];
            }
            switch ($type) {
                case 'header':
                    $lvl = (int) ($d['level'] ?? 2);
                    $lvl = max(1, min(6, $lvl));
                    $out .= '<h' . $lvl . ' class="editorjs-h">' . _editorjs_linkify_esc((string) ($d['text'] ?? '')) . '</h' . $lvl . '>';
                    break;
                case 'paragraph':
                    $out .= '<p class="editorjs-p">' . _editorjs_linkify_esc((string) ($d['text'] ?? '')) . '</p>';
                    break;
                case 'quote':
                    $out .= '<blockquote class="editorjs-quote">' . _editorjs_linkify_esc((string) ($d['text'] ?? '')) . '</blockquote>';
                    break;
                case 'list':
                    $style = (string) ($d['style'] ?? $d['type'] ?? 'unordered');
                    $tag   = $style === 'ordered' ? 'ol' : 'ul';
                    $items = $d['items'] ?? [];
                    $out  .= '<' . $tag . ' class="editorjs-list">';
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $txt = '';
                            if (is_string($item)) {
                                $txt = $item;
                            } elseif (is_array($item) && isset($item['content'])) {
                                $txt = (string) $item['content'];
                            }
                            $out .= '<li>' . _editorjs_linkify_esc($txt) . '</li>';
                        }
                    }
                    $out .= '</' . $tag . '>';
                    break;
                default:
                    $text = isset($d['text']) ? (string) $d['text'] : '';
                    if ($text !== '') {
                        $out .= '<p class="editorjs-p">' . _editorjs_linkify_esc($text) . '</p>';
                    }
                    break;
            }
        }

        return $out !== '' ? $out : esc($raw);
    }
}

if (! function_exists('_editorjs_linkify_esc')) {
    function _editorjs_linkify_esc(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace_callback(
            '#(https?://[^\s<]+)#iu',
            static function (array $m): string {
                $url = $m[1];
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
                if (! in_array($scheme, ['http', 'https'], true)) {
                    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                $href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $href . '</a>';
            },
            $escaped
        );
    }
}
