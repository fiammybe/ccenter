<?php
// Changing message status
// $Id: status.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

$uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0;
$msgid = (int) ($_POST['id'] ?? 0);

$redirect = "message.php?id=".$msgid;
// every change needs a POST request that carries a valid security token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cc_check_token()) {
    redirect_header($redirect, 3, cc_token_error());
    exit;
}
$clean_op = (isset($_POST['op'])) ? trim(filter_input(INPUT_POST, 'op')) : '';

$message_handler = icms_getModuleHandler('message', 'ccenter');

if (!empty($_POST['eval'])) {	// evaluate at last
    $eval = max(1, min(5, (int) $_POST['eval']));
    $pass = icms_core_DataFilter::stripSlashesGPC($_POST['pass'] ?? '');
    $com = icms_core_DataFilter::stripSlashesGPC($_POST['comment'] ?? '');
    $now = time();

    $message = is_cc_evaluate($msgid, $uid, $pass) ? $message_handler->find($msgid) : null;
    if ($message) {
	$formid = (int) $message->getVar('fidref', 'n');
	$s = $message->getVar('status', 'n');
	$message_handler->updateFields($msgid, array(
	    'value' => $eval, 'comment' => $com, 'comtime' => $now, 'atime' => $now, 'mtime' => $now,
	    'status' => _STATUS_CLOSE));
	$log = _MD_EVALS." ($eval)";
	$log .= "\n".sprintf(_CC_LOG_STATUS, $msg_status[$s], $msg_status[_STATUS_CLOSE]);
	$evalmsg = _MD_EVALS." ($eval)\n$com";
	$tags = array('X_COMMENT_URL'=>ICMS_URL."/modules/".basename(dirname(__FILE__))."/message.php?id=$msgid\n\n".$evalmsg);
	$notification_handler = icms::handler('icms_data_notification');
	$notification_handler->triggerEvent('message', $msgid, 'comment', $tags);
	cc_log_message($formid, $log, $msgid);
	redirect_header($redirect, 1, _MD_EVAL_THANKYOU);
    } else {
	redirect_header($redirect, 3, _NOPERM);
    }
} elseif (!empty($_POST['status'])) {
    $stat = icms_core_DataFilter::stripSlashesGPC($_POST['status']);
    $message = $message_handler->find($msgid);
    $fid = $message ? (int) $message->getVar('fidref', 'n') : 0;
    if (change_message_status($msgid, $uid, $stat)) {
		if ($stat==_STATUS_DEL) {
			$redirect = "reception.php?form=$fid"; // delete the message
		}
		redirect_header($redirect, 1, _MD_UPDATE_STATUS);
		exit;
    }
    redirect_header($redirect, 3, _MD_UPDATE_FAILED);
} else {
    switch ($_POST['op'] ?? '') {
    case 'myself':
	// a member of the contact group (or an administrator) takes over a message nobody is in charge of
	$data = $uid ? $message_handler->getRowWithForm($msgid) : null;
	if ($data && mod_ccenter_AccessPolicy::forCurrentUser()->canTakeOver($data)) {
	    $fid = $data['fidref'];
	    $s = $data['status'];
	    $message_handler->updateFields($msgid, array(
		'mtime' => time(), 'touid' => $uid, 'status' => _STATUS_ACCEPT));
	    $log = sprintf(_CC_LOG_TOUSER, _CC_USER_NONE, icms::$user->getVar('uname'));
	    $log .= "\n".sprintf(_CC_LOG_STATUS, $msg_status[$s], $msg_status[_STATUS_ACCEPT]);
	    $notification_handler = icms::handler('icms_data_notification');
	    $notification_handler->subscribe('message', $msgid, 'comment');

	    cc_log_message($fid, $log, $msgid);
	}

	break;
    }
    redirect_header($redirect, 1, _MD_UPDATE_STATUS);
}
