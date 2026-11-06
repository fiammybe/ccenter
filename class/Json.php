<?php

/**
 * JSON helper: one place for the encoding flags and for telling JSON from the legacy text formats.
 */
class mod_ccenter_Json
{
    /** Readable text (so SQL LIKE searches keep working) and loud failures. */
    const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @throws JsonException when the value cannot be encoded
     */
    public static function encode($value, $pretty = false)
    {
        return json_encode($value, self::FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    /**
     * @throws JsonException on invalid JSON
     */
    public static function decode($json)
    {
        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** True when the text starts like a JSON object or array (it is not necessarily valid). */
    public static function looksLikeJson($text)
    {
        $text = ltrim((string) $text);
        return $text !== '' && ($text[0] === '{' || $text[0] === '[');
    }

    /**
     * Decode text that may be JSON.
     *
     * @return array|null the decoded array/object, or null when the text is not (valid) JSON
     */
    public static function tryDecodeArray($text)
    {
        if (!self::looksLikeJson($text)) {
            return null;
        }
        try {
            $data = self::decode($text);
        } catch (JsonException $e) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
