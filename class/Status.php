<?php

/**
 * Message status values (stored as one character).
 */
class mod_ccenter_Status
{
    const NONE = '-';   // not yet handled
    const ACCEPT = 'a'; // accepted by the person in charge
    const REPLY = 'b';  // replied
    const CLOSE = 'c';  // closed (evaluated)
    const DEL = 'x';    // deleted

    /** @return string[] all valid status codes, in display order */
    public static function all()
    {
        return [self::NONE, self::ACCEPT, self::REPLY, self::CLOSE, self::DEL];
    }

    public static function isValid($status)
    {
        return is_string($status) && in_array($status, self::all(), true);
    }

    /**
     * @return array code => label (from the language files)
     */
    public static function labels()
    {
        $names = [
            self::NONE => '_CC_STATUS_NONE',
            self::ACCEPT => '_CC_STATUS_ACCEPT',
            self::REPLY => '_CC_STATUS_REPLY',
            self::CLOSE => '_CC_STATUS_CLOSE',
            self::DEL => '_CC_STATUS_DEL',
        ];
        $labels = [];
        foreach ($names as $code => $constant) {
            $labels[$code] = defined($constant) ? constant($constant) : $code;
        }
        return $labels;
    }
}
