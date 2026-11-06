<?php

/**
 * Option variables of a form (and the module's default attributes): a JSON object such as
 * {"notify_with_email": "1", "redirect": "/thanks.php"}. The earlier "key=value" lines are still read.
 */
class mod_ccenter_OptionVars
{
    /**
     * @param string|null $text JSON object or legacy "key=value" text
     * @return array
     */
    public static function parse($text)
    {
        $text = mod_ccenter_LegacyText::stripFilterMarkers($text);
        $data = mod_ccenter_Json::tryDecodeArray($text);
        if ($data === null && mod_ccenter_Json::looksLikeJson($text)) {
            // a preference that went through the text filter of the configuration form has its quotes encoded
            $data = mod_ccenter_Json::tryDecodeArray(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
        }
        if ($data !== null) {
            return $data;
        }
        return mod_ccenter_LegacyText::unserializeVars($text);
    }

    /** @return string JSON object (also for an empty list) */
    public static function encode(array $vars)
    {
        return $vars ? mod_ccenter_Json::encode($vars) : '{}';
    }

    /** Convert whatever is stored to the current format. */
    public static function normalize($text)
    {
        return self::encode(self::parse($text));
    }

    /** True when the stored text is already the current format. */
    public static function isCurrent($text)
    {
        return mod_ccenter_Json::tryDecodeArray(mod_ccenter_LegacyText::stripFilterMarkers($text)) !== null;
    }
}
