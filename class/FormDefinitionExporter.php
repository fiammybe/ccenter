<?php

/**
 * Writes a form schema in the legacy text format (the inverse of mod_ccenter_FormDefinitionImporter).
 * Used by the admin to show an editable text version and as the way back to the previous format.
 */
class mod_ccenter_FormDefinitionExporter
{
    /**
     * @param array $schema ['version' => 1, 'items' => [...]]
     * @return string
     */
    public static function export(array $schema)
    {
        $lines = [];
        foreach ($schema['items'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'comment') {
                $lines[] = '#' . str_replace(["\r", "\n"], ' ', (string) ($item['text'] ?? ''));
                continue;
            }
            $lines[] = self::line($item);
        }
        return implode("\n", $lines);
    }

    private static function line(array $item)
    {
        $type = $item['type'] ?? 'text';
        $key = (string) $item['key'];
        $label = (string) ($item['label'] ?? $key);
        $head = $key . (!empty($item['required']) ? '*' : '') . ($label !== $key ? '=' . $label : '');
        $cells = [$head];

        $attrs = $item['attrs'] ?? [];
        $options = $item['options'] ?? [];
        $default = isset($item['default']) ? (string) $item['default'] : '';

        $attrCells = self::attrCells($attrs);
        $next = $attrCells ? $attrCells[0] : ($options ? (string) $options[0]['value'] : $default);
        if ($type !== 'text' || in_array($next, mod_ccenter_FormSchema::TYPES, true)) {
            $cells[] = $type; // the type cell is optional for text, unless the next cell could be mistaken for a type
        }
        foreach ($attrCells as $cell) {
            $cells[] = $cell;
        }
        foreach ($options as $option) {
            $value = (string) $option['value'];
            $optionLabel = (string) ($option['label'] ?? $value);
            $mark = !empty($option['default']) ? '+' : '';
            $cells[] = $optionLabel === $value ? $value . $mark : $value . $mark . '=' . $optionLabel;
        }
        if ($default !== '') {
            $cells[] = $default;
        }
        if (isset($item['comment']) && $item['comment'] !== '') {
            $cells[] = '#' . $item['comment'];
        }
        return implode(',', array_map([self::class, 'quote'], $cells));
    }

    /** @return string[] */
    private static function attrCells(array $attrs)
    {
        $cells = [];
        foreach ($attrs as $name => $value) {
            $cells[] = $name . '=' . $value;
        }
        return $cells;
    }

    private static function quote($cell)
    {
        if ($cell === '' || preg_match('/[,\t"\r\n]/', $cell)) {
            return '"' . str_replace('"', '""', $cell) . '"';
        }
        return $cell;
    }
}
