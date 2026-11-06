<?php

/** A message as the list templates show it (template contract: msgid, mdate, title, uname, status, raw). */
class mod_ccenter_ListEntry
{
    /** @param array $row message row with the title of its form */
    public static function build(array $row)
    {
        $labels = mod_ccenter_Status::labels();
        $id = (int) $row['msgid'];
        return [
            'msgid' => $id,
            'mdate' => myTimestamp($row['mtime'], 'm', _MD_TIME_UNIT),
            'title' => "<a href='message.php?id=$id'>" . htmlspecialchars((string) $row['title'], ENT_QUOTES) . '</a>',
            'uname' => icms_member_user_Handler::getUserLink($row['uid']),
            'status' => $labels[$row['status']] ?? '',
            'raw' => $row,
        ];
    }
}
