<?php

/**
 * Parsers and writers of the text formats used before the module stored JSON:
 *   "name: value" blocks (message body) and "key=value" lines (option variables).
 * Kept for reading old data and for migrating it.
 */
class mod_ccenter_LegacyText
{
    /**
     * Parse "name: value" lines; lines starting with whitespace continue the previous value.
     *
     * @return array name => value
     */
    public static function unserializeText($text)
    {
        $array = [];
        $last = null;
        foreach (preg_split("/\r?\n/", (string) $text) as $line) {
            if (preg_match("/^\s/", $line)) {
                if ($last !== null) {
                    $array[$last] .= "\n" . substr($line, 1);
                }
            } elseif (preg_match('/^([^:]*):\s?(.*)$/', $line, $d)) {
                $last = $d[1];
                $array[$last] = $d[2];
            }
        }
        return $array;
    }

    /** Inverse of unserializeText(); array values are joined with ", ". */
    public static function serializeText(array $array)
    {
        $text = '';
        foreach ($array as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = (string) $value;
            if (strpos($value, "\n") !== false) {
                $value = preg_replace('/\n\r?/', "\n\t", $value);
            }
            $text .= "$name: $value\n";
        }
        return $text;
    }

    /**
     * Parse "key=value" lines (or the comma separated form). A text that looks like a message body
     * ("word: value") is parsed as such, as the previous implementation did.
     *
     * @return array key => value
     */
    public static function unserializeVars($text, $reverse = false)
    {
        $text = (string) $text;
        if (preg_match("/^\w+: /", $text)) {
            return self::unserializeText($text);
        }
        $array = [];
        $text = ltrim($text);
        $pat = ['/""/', '/^"(.*)"$/'];
        $rep = ['"', '$1'];
        $delimiters = preg_match('/[\n\r]/', $text) ? '\n\r' : ',\n\r'; // the comma form is allowed on one line
        while ($text !== '' && preg_match("/^(\"[^\"]*\"|[^\"$delimiters]*)*[$delimiters]?/", $text, $d)) {
            $line = preg_replace("/[\\s$delimiters]\$/", '', $d[0]);
            $text = ltrim(substr($text, strlen($d[0])));
            if (preg_match('/^\s*([^=]+)\s*=\s*(.*)$/', $line, $d)) {
                if (preg_match('/^#/', $d[1])) {
                    continue;
                }
                $key = $reverse ? $d[2] : $d[1];
                $value = $reverse ? $d[1] : $d[2];
                $array[$key] = preg_replace($pat, $rep, $value);
            }
        }
        return $array;
    }
}
