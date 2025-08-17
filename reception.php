<?php
// contact to member
// $Id$

include "../../mainfile.php";
include "functions.php";

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
}

$id= isset($_GET['form'])? (int)$_GET['form'] :0;
$isadmin = icms::$user->isAdmin(icms::$module->getVar('mid'));
if ($isadmin) $cond = "1";
else {
    $cond = '(priuid='.icms::$user->getVar('uid').
	' OR cgroup IN ('.join(',', icms::$user->getGroups()).'))';
}

if ($id) $cond .= ' AND formid='.$id;

$res = icms::$xoopsDB->query("SELECT f.*,count(msgid) nmsg,max(m.mtime) ltime
 FROM ".FORMS." f LEFT JOIN ".CCMES." m ON fidref=formid AND status<>".icms::$xoopsDB->quoteString(_STATUS_DEL)."
 WHERE $cond GROUP BY formid");

if (!$res || icms::$xoopsDB->getRowsNum($res)==0) {
    redirect_header('index.php', 3, _NOPERM);
    exit;
}

$breadcrumbs = new XoopsBreadcrumbs();
$breadcrumbs->set(_MD_CCENTER_RECEPTION, "reception.php");

if (icms::$xoopsDB->getRowsNum($res)>1) {
    include ICMS_ROOT_PATH."/header.php";
    $xoopsOption['template_main'] = "ccenter_reception.html";
    $breadcrumbs->assign();
    $forms = array();
    $member_handler =& xoops_gethandler('member');
    $groups = $member_handler->getGroupList(new Criteria('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    while ($form=icms::$xoopsDB->fetchArray($res)) {
	$form['title'] = htmlspecialchars($form['title']);
	$form['ltime'] = $form['ltime']?formatTimestamp($form['ltime']):"";
	if ($form['priuid']) {
	    if ($form['priuid']<0) {
		$form['contact'] = '['.$groups[-$form['priuid']].']';
	    } else {
		$form['contact'] = icms_member_user_Handler::getUserLink($form['priuid']);
	    }
	} elseif ($form['cgroup']) {
	    $form['contact'] = '['.$groups[$form['cgroup']].']';
	} else {
	    $form['contact'] = _MD_CONTACT_NOTYET;
	}
	$forms[] = $form;
    }
    $xoopsTpl->assign('forms', $forms);
    include ICMS_ROOT_PATH."/footer.php";
    exit;
}


// check access permition
$form = icms::$xoopsDB->fetchArray($res);
if (!cc_check_perm($form)) {
    redirect_header('index.php', 3, _NOPERM);
    exit;
}

include ICMS_ROOT_PATH."/header.php";

$xoopsOption['template_main'] = "ccenter_reception.html";

$id = $form['formid'];
$items = get_form_attribute($form['defs']);
$breadcrumbs->set(htmlspecialchars($form['title']), "reception.php?formid=$id");
$breadcrumbs->assign();

$start = isset($_GET['start'])? (int)$_GET['start'] :0;
if ($form['custom']) {
    $reg = array('/\\[desc\\](.*)\\[\/desc\\]/sU', '/<form[^>]*>(.*)<\\/form[^>]*>/sU', '/{CHECK_SCRIPT}/');
    $rep = array('\\1', '', '');
    $form['action'] = '';
    $form['description'] = preg_replace($reg, $rep, custom_template($form, $items));
} else {
    $form['description'] = icms_core_DataFilter::checkVar($form['description'], 'text', 'output');
}
$form['mdate'] = formatTimestamp($form['mtime']);
foreach ($items as $k=>$item) {
    if (empty($item['label'])) unset($items[$k]);
}
$max_cols = 3;
$form['items'] = array_slice($items, 0, $max_cols);
$n = $mpos = -1;
foreach ($form['items'] as $item) {
    $n++;
    if ($item['type'] == 'mail') {
	$mpos = $n;
	$mlab = $item['name'];
	break;
    }
}

include_once ICMS_ROOT_PATH.'/class/pagenav.php';

$cond = "fidref=$id AND status<>".icms::$xoopsDB->quoteString(_STATUS_DEL);
$res = icms::$xoopsDB->query('SELECT count(*) FROM '.CCMES." WHERE $cond");
list($count) = icms::$xoopsDB->fetchRow($res);
$max = icms::$module->config['max_lists'];
$args = preg_replace('/start=\\d+/', '', $_SERVER['QUERY_STRING']);
$nav = new XoopsPageNav($count, $max, $start, "start", $args);
$xoopsTpl->assign('pagenav', $count>$max?$nav->renderNav():"");

if ($form['priuid'] < 0 && !$isadmin) {
    $cond .= " AND touid=".icms::$user->getVar('uid');
    $form['description'] = str_replace('{TO_NAME}', icms::$user->getVar('name'), $form['description']);
}

$xoopsTpl->assign('form', $form);

$res = icms::$xoopsDB->query('SELECT * FROM '.CCMES." WHERE $cond ORDER BY msgid DESC", $max, $start);
$xoopsTpl->assign('export_range', $export_range);

$mlist = array();
while ($data = icms::$xoopsDB->fetchArray($res)) {
    $values = unserialize_text($data['body']);
    if ($mpos>=0 && !isset($values[$mlab])) {
	array_splice($values, $mpos, 0, array($data['email']));
    }
    $data['values'] = array_slice($values, 0, $max_cols);
    $data['uname'] = icms::$user->getUnameFromId($data['uid']);
    $data['mdate'] = formatTimestamp($data['mtime']);
    $data['cdate'] = formatTimestamp($data['ctime']);
    $data['stat'] = $msg_status[$data['status']];
    $mlist[] = $data;
}

$xoopsTpl->assign('mlist', $mlist);

include ICMS_ROOT_PATH."/footer.php";
