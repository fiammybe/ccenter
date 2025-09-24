<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactMessageHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'ccenter_message', 'ContactMessage', 'msgid', 'title');
    }

    public function cc_check_perm($data)
    {

        $uid = is_object(icms::$user) ? icms::$user->getVar('uid') : 0;

        $pass = $_GET['p'] ?? "";
        if ($pass) {
            $_SESSION['onepass'] = $pass;
        }
    }

    public function cc_get_message($msgid)
    {

        $res = icms::$xoopsDB->query("SELECT m.*, title FROM " . CCMES . " m," . FORMS . " WHERE msgid=" . (int)$msgid . " AND status<>" . icms::$xoopsDB->quoteString(_STATUS_DEL) . " AND fidref=formid");

        $data = icms::$xoopsDB->fetchArray($res);
        if (!cc_check_perm($data)) {
            redirect_header(ICMS_URL . '/user.php', 3, _NOPERM);
            exit;
        }
    }

    public function cc_log_message($formid, $comment, $msgid = 0)
    {

        $uid = is_object(icms::$user) ? icms::$user->getVar('uid') : 0;
        $now = time();
        icms::$xoopsDB->queryF("INSERT INTO " . CCLOG . "(ltime, fidref, midref, euid, comment)VALUES($now, $formid, $msgid, $uid, " . icms::$xoopsDB->quoteString(preg_replace('/\n/', ", ", $comment)) . ")");
        if ($msgid) {
            $msgurl = ICMS_URL . "/modules/" . basename(__DIR__) . "/message.php?id=$msgid";
            $res = icms::$xoopsDB->query("SELECT title FROM " . FORMS . " WHERE formid=" . $formid);
            list($title) = icms::$xoopsDB->fetchRow($res);
            $tags = array('LOG_STATUS' => $comment,
                'FORM_NAME' => $title,
                'CHANGE_BY' => icms::$user ? icms::$user->getVar('uname') : "",
                'MSG_ID' => $msgid,
                'MSG_URL' => $msgurl);
            $notification_handler = icms::handler('icms_data_notification');
            $notification_handler->triggerEvent('message', $msgid, 'status', $tags);
        }
    }

    public function cc_log_status($data, $nstat)
    {

        global $msg_status;
        $fid = empty($data['fidref']) ? $data['formid'] : $data['fidref'];
        $log = sprintf(_CC_LOG_STATUS, $msg_status[$data['status']], $msg_status[$nstat]);
        return cc_log_message($fid, $log, $data['msgid']);

    }

    public function change_message_status($msgid, $touid, $stat)
    {
        global $msg_status;

        $isadmin = is_object(icms::$user) && icms::$user->isAdmin(icms::$module->getVar('mid'));
        $own_status = array_slice($msg_status, $isadmin ? 0 : 1, $isadmin ? 5 : 3);
        if (empty($own_status[$stat])) return false; // Invalid status
        $s = icms::$xoopsDB->quoteString($stat);
        $cond = "msgid=" . $msgid;
        if ($touid) $cond .= " AND touid=" . $touid;
        $res = icms::$xoopsDB->query("SELECT msgid,fidref,status FROM " . CCMES . " WHERE $cond AND status<>$s");
        if (!$res || icms::$xoopsDB->getRowsNum($res) == 0) return false;
        $data = icms::$xoopsDB->fetchArray($res);
        $now = time();
        $res = icms::$xoopsDB->queryF("UPDATE " . CCMES . " SET status=$s,mtime=$now WHERE msgid=$msgid");
        if (!$res) die('DATABASE error');    // unknown error?
        cc_log_status($data, $stat);
        return true;

    }
}
