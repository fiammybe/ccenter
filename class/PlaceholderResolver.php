<?php

/**
 * Replaces {X_<USERFIELD>} placeholders (e.g. {X_UNAME}) in default values with data of the current user.
 * This is plain string replacement; nothing is ever evaluated as code.
 */
class mod_ccenter_PlaceholderResolver
{
    /**
     * @param string $text
     * @param array  $vars user field => value (empty values for guests)
     */
    public static function resolve($text, array $vars)
    {
        $map = [];
        foreach ($vars as $field => $value) {
            $map['{X_' . strtoupper($field) . '}'] = is_scalar($value) ? (string) $value : '';
        }
        return strtr((string) $text, $map);
    }

    /**
     * Field values of the logged-in user in "edit" format; every field is empty for guests.
     *
     * @return array
     */
    public static function currentUserVars()
    {
        $reference = is_object(icms::$user) ? icms::$user : new icms_member_user_Object();
        $vars = [];
        foreach (array_keys($reference->getVars()) as $field) {
            $vars[$field] = is_object(icms::$user) ? icms::$user->getVar($field, 'e') : '';
        }
        return $vars;
    }

    /** Resolve for the current user. */
    public static function resolveForCurrentUser($text)
    {
        static $vars = null;
        if ($vars === null) {
            $vars = self::currentUserVars();
        }
        return self::resolve($text, $vars);
    }
}
