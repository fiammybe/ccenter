<?php
/**
 * ccenter is a form module
 * 
 * File: status.php
 * 
 * changing message-status
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		0.94
 * @author		Nobuhiro Yasutomi
 * @package		ccenter
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */

include "header.php";

$uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0;
$msgid = (int) $_POST['id'];

$redirect = "message.php?id=".$msgid;
$clean_op = (isset($_POST['op'])) ? trim(filter_input(INPUT_POST, 'op')) : '';

if (!empty($_POST['eval'])) {	// evaluate at last
    $eval = (int) $_POST['eval'];
    $pass = icms_core_DataFilter::stripSlashesGPC($_POST['pass']);
    $com = icms_core_DataFilter::stripSlashesGPC($_POST['comment']);
    $now = time();
	
    if (is_cc_evaluate($msgid, $uid, $pass)) {
	$res = icms::$xoopsDB->query("SELECT fidref,status FROM ".CCMES." WHERE msgid=$msgid");
	list($formid, $s) = icms::$xoopsDB->fetchRow($res);
	$values = array("value=$eval",
			"comment=".icms::$xoopsDB->quoteString($com),
			"comtime=$now", "atime=$now", "mtime=$now",
			"status=".icms::$xoopsDB->quoteString(_STATUS_CLOSE));
	icms::$xoopsDB->query("UPDATE ".CCMES." SET ".join(',',$values)." WHERE msgid=$msgid");
	$log = _MD_EVALS." ($eval)";
	$log .= "\n".sprintf(_CC_LOG_STATUS, $msg_status[$s], $msg_status[_STATUS_CLOSE]);
	$evalmsg = _MD_EVALS." ($eval)\n$com";
	$tags = array('X_COMMENT_URL'=>ICMS_URL."/modules/" . icms::$module -> getVar( 'dirname' ) . "/message.php?id=$msgid\n\n".$evalmsg);
	$notification_handler =& icms::handler('icms_data_notification');
		$notification_handler->triggerEvent('message', $msgid, 'comment', $tags);
	cc_log_message($formid, $log, $msgid);
	redirect_header($redirect, 1, _MD_EVAL_THANKYOU);
    } else {
	redirect_header($redirect, 3, _NOPERM);
    }
} elseif (!empty($_POST['status'])) {
    $stat = icms_core_DataFilter::stripSlashesGPC($_POST['status']);
    $res = icms::$xoopsDB->query("SELECT fidref FROM ".CCMES." WHERE msgid = $msgid");
    list($fid) = icms::$xoopsDB->fetchRow($res);
    if (change_message_status($msgid, $uid, $stat)) {
		if ($stat=='x') {
			$redirect = "reception.php?form=$fid"; // delete the message
		}
		redirect_header($redirect, 1, _MD_UPDATE_STATUS);
		exit;
    }
    redirect_header($redirect, 3, _MD_UPDATE_FAILED);
} else {
    switch ($_POST['op']) {
    case 'myself':
	$res = icms::$xoopsDB->query("SELECT fidref,status,title FROM ".CCMES." LEFT JOIN ".FORMS." ON formid=fidref WHERE msgid=$msgid AND touid=0");
	if ($res && icms::$xoopsDB->getRowsNum($res)) {
	    list($fid, $s, $title) = icms::$xoopsDB->fetchRow($res);
	    $now = time();
	    $set = "SET mtime=$now, touid=$uid, status=".icms::$xoopsDB->quoteString('a');
	    $res = icms::$xoopsDB->query("UPDATE ".CCMES." $set WHERE msgid=$msgid");
	    $log = sprintf(_CC_LOG_TOUSER, _CC_USER_NONE, icms::$user->getVar('uname'));
	    $log .= "\n".sprintf(_CC_LOG_STATUS, $msg_status[$s], $msg_status['a']);
	    $notification_handler =& icms::handler('icms_data_notification');
				$notification_handler->subscribe('message', $msgid, 'comment');
				//$notification_handler->subscribe('message', $msgid, 'status');

	    cc_log_message($fid, $log, $msgid);
	}
	
	break;
    }
    redirect_header($redirect, 1, _MD_UPDATE_STATUS);
}