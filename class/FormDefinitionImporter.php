<?php

/**
 * Reads the legacy text definition of a form (one item per line, comma/tab separated cells, e.g.
 * `Your name*,size=40` or `About*,radio,Site contents+,Others`) and returns the JSON form schema
 * (see mod_ccenter_FormSchema).
 */
class mod_ccenter_FormDefinitionImporter
{
    /**
     * @param string $dsl legacy definition text
     * @return array schema: ['version' => 1, 'items' => [...]]
     */
    public static function import($dsl)
    {
        $items = [];
        $positions = []; // field key => index in $items (a repeated key overwrites, like the previous parser)
        foreach (self::rows((string) $dsl) as $row) {
            if ($row['comment'] !== null) {
                $items[] = ['type' => 'comment', 'text' => $row['comment']];
                continue;
            }
            $item = self::field($row['cells']);
            if ($item === null) {
                continue;
            }
            if (isset($positions[$item['key']])) {
                $items[$positions[$item['key']]] = $item;
            } else {
                $positions[$item['key']] = count($items);
                $items[] = $item;
            }
        }
        return ['version' => mod_ccenter_FormSchema::VERSION, 'items' => $items];
    }

    /**
     * Split the text into rows of cells. Cells may be quoted ("a, b", "say ""hi""") and may then
     * contain delimiters and line breaks. A row whose first character is '#' is a comment.
     *
     * @return array list of ['cells' => string[], 'comment' => string|null]
     */
    public static function rows($text)
    {
        $rows = [];
        $cells = [];
        $cell = '';
        $quoted = false;     // the current cell started with a quote
        $inQuote = false;
        $rowStart = 0;
        $length = strlen($text);

        $finishRow = function ($end) use (&$rows, &$cells, &$cell, &$quoted, $text, &$rowStart) {
            $cells[] = $cell;
            $raw = substr($text, $rowStart, $end - $rowStart);
            if ($raw !== '' && $raw[0] === '#') {
                $rows[] = ['cells' => [], 'comment' => substr($raw, 1)];
            } else {
                while ($cells && end($cells) === '') {
                    array_pop($cells); // trailing delimiters do not make empty cells
                }
                if ($cells) {
                    $rows[] = ['cells' => $cells, 'comment' => null];
                }
            }
            $cells = [];
            $cell = '';
            $quoted = false;
        };

        for ($i = 0; $i < $length; $i++) {
            $c = $text[$i];
            if ($inQuote) {
                if ($c === '"') {
                    if ($i + 1 < $length && $text[$i + 1] === '"') {
                        $cell .= '"';
                        $i++;
                    } else {
                        $inQuote = false;
                    }
                } else {
                    $cell .= $c;
                }
                continue;
            }
            if ($c === '"' && $cell === '' && !$quoted) {
                $inQuote = true;
                $quoted = true;
            } elseif ($c === ',' || $c === "\t") {
                $cells[] = $cell;
                $cell = '';
                $quoted = false;
            } elseif ($c === "\r" || $c === "\n") {
                $finishRow($i);
                if ($c === "\r" && $i + 1 < $length && $text[$i + 1] === "\n") {
                    $i++;
                }
                $rowStart = $i + 1;
            } else {
                $cell .= $c;
            }
        }
        if ($cell !== '' || $cells || $quoted) {
            $finishRow($length);
        }
        return $rows;
    }

    /**
     * Turn the cells of one row into a schema item.
     *
     * @param string[] $cells
     * @return array|null
     */
    private static function field(array $cells)
    {
        if (!$cells) {
            return null;
        }
        $name = array_shift($cells);
        $label = null;
        if (preg_match('/=(.*)$/', $name, $d)) { // "name=alternative label"
            $label = $d[1];
            $name = preg_replace('/=(.*)$/', '', $name);
        }
        $type = 'text';
        if ($cells && in_array($cells[0], mod_ccenter_FormSchema::TYPES, true)) {
            $type = array_shift($cells);
        }
        $required = false;
        if (preg_match('/\*$/', $name)) {
            $required = true;
            $name = preg_replace('/\s*\*$/', '', $name);
        }
        $attrs = [];
        while (
            isset($cells[0]) &&
            (preg_match('/^(size|rows|maxlength|cols|prop)=(\d+)$/', $cells[0], $d) ||
                preg_match('/^(check)=(.+)$/', $cells[0], $d))
        ) {
            array_shift($cells);
            $attrs[$d[1]] = $d[1] === 'check' ? $d[2] : (int) $d[2];
        }

        $options = [];
        $texts = [];
        while ($cells && !preg_match('/^\s*#/', $cells[0])) {
            $cell = array_shift($cells);
            $parts = explode('=', $cell, 2);
            if (count($parts) > 1) {
                $value = strip_tags($parts[0]);
                $optionLabel = $parts[1];
            } else {
                $value = strip_tags($cell);
                $optionLabel = preg_replace('/\+$/', '', $cell);
            }
            $plain = preg_replace('/\+$/', '', $value);
            $options[] = ['value' => $plain, 'label' => $optionLabel, 'default' => $plain !== $value];
            $texts[] = $optionLabel;
        }
        $comment = '';
        if ($cells) {
            $cells[0] = preg_replace('/^\s*#/', '', $cells[0]);
            $comment = implode(',', $cells);
        }

        $item = ['key' => $name, 'type' => $type, 'label' => $label === null ? $name : $label];
        if ($required) {
            $item['required'] = true;
        }
        if ($attrs) {
            $item['attrs'] = $attrs;
        }
        if (in_array($type, mod_ccenter_FormSchema::OPTION_TYPES, true)) {
            $item['options'] = self::mergeOptions($options);
        } else {
            $default = implode(',', $texts);
            if ($default !== '') {
                $item['default'] = $default;
            }
        }
        if ($comment !== '') {
            $item['comment'] = $comment;
        }
        return $item;
    }

    /** A repeated option value overwrites the earlier label but keeps its position. */
    private static function mergeOptions(array $options)
    {
        $merged = [];
        $positions = [];
        foreach ($options as $option) {
            $entry = ['value' => $option['value'], 'label' => $option['label']];
            if ($option['default']) {
                $entry['default'] = true;
            }
            if (isset($positions[$option['value']])) {
                $previous = $merged[$positions[$option['value']]];
                if (!empty($previous['default']) && !isset($entry['default'])) {
                    $entry['default'] = true;
                }
                $merged[$positions[$option['value']]] = $entry;
            } else {
                $positions[$option['value']] = count($merged);
                $merged[] = $entry;
            }
        }
        return $merged;
    }
}
