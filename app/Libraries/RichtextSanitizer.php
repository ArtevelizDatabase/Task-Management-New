<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Sanitize Editor.js JSON output: allow limited inline markup, http(s) links only.
 */
final class RichtextSanitizer
{
    private const ALLOWED_BLOCK_TYPES = ['paragraph', 'header', 'list', 'quote', 'delimiter'];

    public static function sanitizeEditorJsJson(string $json): string
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE);
        }
        $blocks = $data['blocks'] ?? [];
        if (! is_array($blocks)) {
            $blocks = [];
        }
        $out = [];
        foreach ($blocks as $blk) {
            if (! is_array($blk)) {
                continue;
            }
            $type = (string) ($blk['type'] ?? 'paragraph');
            if (! in_array($type, self::ALLOWED_BLOCK_TYPES, true)) {
                $type = 'paragraph';
            }
            $bdata = is_array($blk['data'] ?? null) ? $blk['data'] : [];
            if ($type === 'header') {
                $lvl = (int) ($bdata['level'] ?? 2);
                $lvl = max(1, min(6, $lvl));
                $out[] = [
                    'type' => 'header',
                    'data' => [
                        'level' => $lvl,
                        'text'  => self::sanitizeInlineHtml((string) ($bdata['text'] ?? '')),
                    ],
                ];
                continue;
            }
            if ($type === 'list') {
                $items = $bdata['items'] ?? [];
                $items = is_array($items) ? $items : [];
                $cleanItems = [];
                foreach ($items as $it) {
                    $cleanItems[] = is_string($it) ? self::sanitizeInlineHtml($it) : '';
                }
                $style = (string) ($bdata['style'] ?? $bdata['type'] ?? 'unordered');
                if (! in_array($style, ['ordered', 'unordered'], true)) {
                    $style = 'unordered';
                }
                $out[] = [
                    'type' => 'list',
                    'data' => ['style' => $style, 'items' => $cleanItems],
                ];
                continue;
            }
            if ($type === 'quote') {
                $out[] = [
                    'type' => 'quote',
                    'data' => ['text' => self::sanitizeInlineHtml((string) ($bdata['text'] ?? ''))],
                ];
                continue;
            }
            if ($type === 'delimiter') {
                $out[] = ['type' => 'delimiter', 'data' => new \stdClass()];
                continue;
            }
            $out[] = [
                'type' => 'paragraph',
                'data' => ['text' => self::sanitizeInlineHtml((string) ($bdata['text'] ?? ''))],
            ];
        }

        return json_encode(['blocks' => $out], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Strip dangerous markup; keep limited inline tags. Anchor tags are stripped to inner text
     * so stored JSON never carries executable attributes; viewer can linkify plain URLs safely.
     */
    public static function sanitizeInlineHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\bon\w+\s*=/i', '', $html) ?? $html;
        $html = preg_replace('/\sjavascript:/i', '', $html) ?? $html;
        $allowed = '<b><strong><i><em><mark><code><br>';
        $html = preg_replace('#<a\b[^>]*>(.*?)</a>#is', '$1', $html);

        $html = strip_tags($html, $allowed);

        return self::stripAttributesFromAllowedInlineTags($html);
    }

    /**
     * strip_tags() keeps attributes on allowed tags; remove them so e.g. <b onmouseover="..."> cannot persist.
     */
    private static function stripAttributesFromAllowedInlineTags(string $html): string
    {
        foreach (['b', 'strong', 'i', 'em', 'mark', 'code'] as $tag) {
            $html = preg_replace('#<' . $tag . '\b[^>]*>#iu', '<' . $tag . '>', $html) ?? $html;
        }
        $html = preg_replace('#<br\b[^>]*>#iu', '<br>', $html) ?? $html;

        return $html;
    }
}
