<?php
/**
 * ccenter module onInstall processing.
 *
 * Creates the sample contact form. This also runs from the ImpressCMS installation wizard,
 * where there is no logged-in user, so the primary contact falls back to the webmaster (uid 1).
 */

register_shutdown_function('ccenter_sample_form');

function ccenter_sample_form()
{
    global $msgs;

    $db = icms::$xoopsDB;
    $table = $db->prefix('ccenter_form');
    $uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 1;

    $data = [
        'mtime'       => time(),
        'title'       => $db->quoteString(_MI_SAMPLE_TITLE),
        'description' => $db->quoteString(_MI_SAMPLE_DESC),
        'grpperm'     => $db->quoteString('|' . ICMS_GROUP_ANONYMOUS . '|' . ICMS_GROUP_USERS . '|'),
        'defs'        => $db->quoteString(_MI_SAMPLE_DEFS),
        'priuid'      => $uid,
    ];

    $db->queryF('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', $data) . ')');
    $msgs[] = '&nbsp;&nbsp;<b>' . _MI_SAMPLE_FORM . '</b>';
}
