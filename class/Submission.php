<?php

/**
 * Storing what a visitor sent through a contact form: the message record, uploaded files, the mails to the sender
 * and the people in charge, and the notifications. Ends with a redirect when the message was handled; returns a list of
 * errors when it could not be stored.
 */
class mod_ccenter_Submission
{
    /**
     * @param array $items items of the form with the posted values (see assign_post_values())
     * @param array $form  row of the form
     * @return string[]|void errors
     */
    public static function store($items, $form)
    {
        global $icmsConfig;

    $uid = is_object(icms::$user)?icms::$user->getVar('uid'):0;
    $store = (int) $form['store'];
    if ($store==_DB_STORE_NONE) {
	$showaddr = true;	// no store to need show address
    } else {
	$showaddr = get_attr_value(null, 'notify_with_email');
    }
    $from = $email = "";
    $attach = array();
    $fields = array();	// what is stored in the message body
    $rtext = '';
    foreach ($items as $item) {
	if (empty($item['name'])) continue;
	$name = $item['name'];
	$val = $item['value'];
	$raw = $val;		// as submitted
	$opts = &$item['options'];
	$hidden = false;	// the first email address is not stored when it is not to be shown
	switch ($item['type']) {
		case 'mail':
		    if (empty($email)) { // save first email for contact
			$email = $val;
			if ($showaddr) {
			    $from = $email;
			} else {
			    $hidden = true;
			}
		    }
		    break;
		case 'file':
		    if ($val) {
			$raw = array('file' => $val);
			$attach[] = $val;
		    }
		    break;
		case 'radio':
		case 'select':
		    if (isset($opts[$val])) $val = strip_tags($opts[$val]);
		    break;
		case 'checkbox':
		    foreach ($val as $k=>$v) {
			$val[$k] = isset($opts[$v])?strip_tags($opts[$v]):$v;
		    }
			$val = join(', ', $val);
			break;
	}
	if ($hidden) continue;
	$label = strip_tags($item['label']);
	$fields[] = array(
	    'key' => $name,
	    'label' => preg_replace('/\s*\*$/', '', $label),	// without the "required" mark
	    'type' => $item['type'],
	    'value' => $raw,
	    'display' => is_array($val) ? join(', ', $val) : (string) $val,
	);
	if (!empty($val) && preg_match('/\n/', $val)) $val = "\n\t".preg_replace('/\n/', "\n\t", $val);
	$rtext .= $label.": $val\n";
    }
    $onepass = ($uid==0)?cc_onetime_ticket($email):"";
    if ($form['priuid'] < 0) {
	$touid = empty($form['priuser'])?0:$form['priuser']['uid'];
    } else {
	$touid = $form['priuid'];
    }
    $now = time();
    $parg = $onepass?"&p=".urlencode($onepass):"";

    if ($store!=_DB_STORE_NONE) {
	$message_handler = icms_getModuleHandler('message', 'ccenter');
	$message = $message_handler->create();
	$message->setValues(array(
	    'uid'=>$uid, 'touid'=>$touid,
	    'ctime'=>$now, 'mtime'=>$now, 'atime'=>$now,
	    'fidref'=>$form['formid'],
	    'email'=>$email,
	    'onepass'=>$onepass));
	if ($store==_DB_STORE_YES) {
	    $message->setBody(mod_ccenter_MessageBody::fromFields($fields));
	}
	if (!$message_handler->insert($message, true)) return array("Error in DATABASE insert");
	$id = (int) $message->getVar('msgid', 'n');
	if (empty($id)) return array("Internal Error in Store Message");
    } else {
	$id = 0;
    }
    $member_handler = icms::handler('icms_member');
    if ($touid) {
	$toUser = $member_handler->getUser($touid);
	$toUname = $toUser->getVar('uname');
    } else {
	$toUser = false;
	$toUname = _MD_CONTACT_NOTYET;
    }
    $atext = "";		// reply sender
    $btext = "";		// to contact and monitors
    if (count($attach)) {
	$atext = $btext = "\n"._MD_ATTACHMENT."\n";
	foreach ($attach as $i=>$file) {
	    move_attach_file('', $file, $id);
	    $a = cc_attach_image($id, $file, true);
	    $atext .= "$a$parg\n";
	    $btext .= "$a\n";
	}
	rmdir(ICMS_UPLOAD_PATH.cc_attach_path(0, ''));
    }
    $dirname = basename(dirname(__DIR__));
    $uname = icms::$user?icms::$user->getVar('uname'):$icmsConfig['anonymous'];
    $tags = array('SUBJECT'=>$form['title'],
		  'TO_USER'=>$toUname,
		  'FROM_USER'=>$uname,
		  'FROM_EMAIL'=>$email,
		  'REMOTE_ADDR'=>$_SERVER["REMOTE_ADDR"],
		  'HTTP_USER_AGENT'=>$_SERVER["HTTP_USER_AGENT"],
		  'MSGID'=>$id);
    $tpl = get_attr_value(null, 'from_confirm_tpl', 'form_confirm.tpl');
    $msgurl = ICMS_URL.($id?"/modules/$dirname/message.php?id=$id":'/');
    if ($email) {		// reply automaticaly
		$tags['VALUES'] = "$rtext$atext";
		$tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n$msgurl$parg";
		cc_notify_mail($tpl, $tags, $email, $toUser?$toUser->getVar('email'):'');
    }
    $tags['VALUES'] = "$rtext$btext";
    $tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n".$msgurl;

    $notification_handler = icms::handler('icms_data_notification');
    $notification_handler->triggerEvent('global', 0, 'new', $tags);
    $notification_handler->triggerEvent('form', $form['formid'], 'new', $tags);
    // force subscribe sender and recipient
    if ($id) $notification_handler->subscribe('message', $id, 'comment');
    if ($touid) {
		if ($id) $notification_handler->subscribe('message', $id, 'comment', null, null, $touid);
		cc_notify_mail(get_attr_value(null, 'charge_notify_tpl', $tpl), $tags, $toUser, $from);
	} elseif ($form['cgroup']) { // contact group notify
			$users = $member_handler->getUsersByGroup($form['cgroup'], true);
			cc_notify_mail(get_attr_value(null, 'group_notify_tpl', $tpl), $tags, $users, $from);
    }

    if ($id) $msgurl .= $parg;
    $redirect = get_attr_value(null, 'redirect');
    if (!empty($redirect)) {
		$msgurl = preg_match('/^\\//', $redirect)?ICMS_URL.$redirect:$redirect;
    }
    redirect_header($msgurl, 3, _MD_CONTACT_DONE);
    exit;
}
}
