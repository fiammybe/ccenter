<?php
// adminstration messages
include '../../../include/cp_header.php';
include '../functions.php';
include_once 'myformselect.php';

// option variables form definitions
define('_CC_OPTDEFS',"notify_with_email,radio,1="._YES.",="._NO."
redirect,text,size=60
reply_comment,textarea,cols=60,rows=10
reply_use_comtpl,radio,1="._YES.",="._NO."
others,textarea");
icms_cp_header();
$op = isset($_GET['op']) ? filter_input(INPUT_GET, 'op', FILTER_SANITIZE_STRING) : '';
if (isset($_POST['op'])) $op = filter_input(INPUT_POST, 'op', FILTER_SANITIZE_STRING);
$formid = isset($_REQUEST['formid']) ? (int) $_REQUEST['formid'] : 0;

$fields = array('title', 'description', 'defs', 'priuid', 'cgroup',
		'store', 'custom', 'weight', 'active');
$optfields = array();
if ($op == 'delform') {
    $formid = filter_input(INPUT_POST, 'formid', FILTER_SANITIZE_NUMBER_INT);
    icms::$xoopsDB->query("DELETE FROM ".FORMS." WHERE formid=".$formid);
    icms::$xoopsDB->query("DELETE FROM ".CCMES." WHERE fidref=".$formid);
    // NOTE: add function delete ICMS comments.
    // NOTE: add function delete uploads files
    // NOTE: add function delete notifications
    redirect_header('index.php', 1, _AM_FORM_DELETED);
    exit;
} elseif (isset($_POST['formdefs']) && !isset($_POST['preview'])) {
    $formid = filter_input(INPUT_POST, 'formid', FILTER_SANITIZE_NUMBER_INT);
    $data = $vals = array();
    foreach ($fields as $fname) {
	$data[$fname] = $v = icms_core_DataFilter::stripSlashesGPC($_POST[$fname]);
	$v = icms::$xoopsDB->quoteString($v);
	if ($formid) {
	    $vals[] = $fname."=".$v;
	} else {
	    $vals[$fname] = $v;
	}
    }
    $v = icms::$xoopsDB->quoteString($data['optvars'] = post_optvars());
    $fname = 'optvars';
    if ($formid) {
	$vals[] = $fname."=".$v;
    } else {
	$vals[$fname] = $v;
    }
    $v = '|';
    foreach ($_POST['grpperm'] as $gid) {
	$v .= intval($gid)."|";
    }
    $v = icms::$xoopsDB->quoteString($v);
    if ($formid) {
	$vals[] = "grpperm=".$v;
	$vals[] = "mtime=".time();
	$res = icms::$xoopsDB->query("UPDATE ".FORMS." SET ".join(',', $vals)." WHERE formid=".$formid);
    } else {
	$vals['grpperm'] = $v;
	$vals['mtime'] = time();
	$res = icms::$xoopsDB->query("INSERT INTO ".FORMS."(".join(',', array_keys($vals)).") VALUES(".join(',', $vals).")");
	$formid = icms::$xoopsDB->getInsertID();
    }
    if (check_form_tags($data['custom'], $data['defs'],$data['description'])) {
	$redirect = "index.php?formid=".$formid;
    } else {
	$redirect = "index.php";
    }
    if ($res) {
	redirect_header($redirect, 1, _AM_FORM_UPDATED);
    } else {
	redirect_header($redirect, 3, _AM_FORM_UPDATE_FAIL);
    }
    exit;
}

if( ! empty( $_GET['lib'] ) ) {
    global $mydirpath;
    $mydirpath = dirname(__FILE__, 2);
    $mydirname = basename($mydirpath);
    // common libs (eg. altsys)
    $lib = preg_replace( '/[^a-zA-Z0-9_-]/' , '' , $_GET['lib'] ) ;
    $page = preg_replace( '/[^a-zA-Z0-9_-]/' , '' , @$_GET['page'] ) ;

    if( file_exists( ICMS_TRUST_PATH.'/libs/'.$lib.'/'.$page.'.php' ) ) {
	include ICMS_TRUST_PATH.'/libs/'.$lib.'/'.$page.'.php' ;
	} else if( file_exists( ICMS_TRUST_PATH.'/libs/'.$lib.'/index.php' ) ) {
	include ICMS_TRUST_PATH.'/libs/'.$lib.'/index.php' ;
    } else {
	die( 'wrong request' ) ;
    }
    exit;
}

icms::$module->displayAdminmenu(1);

switch ($op) {
case 'delete':
    $res=icms::$xoopsDB->query("SELECT title FROM ".FORMS." WHERE formid=".$formid);
    list($title) = icms::$xoopsDB->fetchRow($res);
    xoops_confirm(array('op'=>'delform', 'formid'=>$formid), '',
		  _AM_DELETE_FORM.' - '.htmlspecialchars($title)." (ID:$formid)",
		  _DELETE);
    break;
default:
    if ($formid==0) list_forms();
    build_form($formid);
}

icms_cp_footer();

function post_optvars() {
    $items = get_form_attribute(_CC_OPTDEFS, '', 'optvar');
    $errors = assign_post_values($items);
    $vars = array();
    foreach ($items as $item) {
	$fname = $item['name'];
	if ($fname == "others") {
	    foreach (unserialize_vars($item['value']) as $k=>$v) {
		$vars[$k] = $v;
	    }
	} else {
	    if ($item['value']) $vars[$fname] = $item['value'];
	}
    }
    return serialize_text($vars);
}

function list_forms() {
    //global $xoopsUser;
    $dirname = basename(dirname(__FILE__, 2));
    $res = icms::$xoopsDB->query("SELECT formid,title,count(msgid) nmes,priuid,cgroup,
sum(if(status='-',1,0)) nwait,
sum(if(status='a',1,0)) nwork,
sum(if(status='b',1,0)) nreply,
sum(if(status='c',1,0)) nclose,
store
FROM ".FORMS." LEFT JOIN ".CCMES." ON fidref=formid AND status<>'x' GROUP BY formid");
    if (!$res || icms::$xoopsDB->getRowsNum($res)==0) return false;
    echo "<style>td.num { text-align: right; }</style>";
    echo "<table class='outer' border='0' cellspacing='1'>\n";
    echo "<tr><th>ID</th><th>"._AM_FORM_TITLE."</th><th>"._AM_FORM_PRIM_CONTACT."</th><th>"._AM_MSG_COUNT."</th><th>"._AM_MSG_WAIT."</th><th>"._AM_MSG_WORK."</th><th>"._AM_MSG_REPLY."</th><th>"._AM_MSG_CLOSE."</th><th>"._AM_OPERATION."</th></tr>\n";
    $n = 0;
    $mbase = ICMS_URL."/modules/$dirname";
    $ancfmt = "<td class='num'><a href='msgadm.php?stat=%s&formid=%d'>%d</a></td>\n";
    $nodata = "<td class='num'>--</td>";
    $msgs = array('- a b c'=>'nmes', '-'=>'nwait', 'a'=>'nwork',
		  'b'=>'nreply', 'c'=>'nclose');
    $member_handler = icms::handler('icms_member');
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    while ($data = icms::$xoopsDB->fetchArray($res)) {
	$id = $data['formid'];
	$title = htmlspecialchars($data['title']);
	$url = "$mbase?form=$id";
	$priuid = $data['priuid'];
	$form = $url;

	if ($priuid<0) {
	    $form .= "&amp;uid=".icms::$user->getVar('uid');
	    $contact = sprintf(_CC_FORM_PRIM_GROUP, $groups[-$priuid]);
	} elseif ($priuid) {
	    $contact = icms_member_user_Handler::getUserLink($priuid);
	} elseif ($form['cgroup']) {
	    $contact = '['.$groups[$data['cgroup']].']';
	} else {
	    $contact = _MD_CONTACT_NOTYET;
	}

	$bg = $n++%2?'even':'odd';
	$ope = "<a href='?formid=$id'>"._EDIT."</a>".
	    " | <a href='?op=delete&formid=$id'>"._DELETE."</a>".
	    " | <a href='$mbase/reception.php?form=$id'>"._AM_DETAIL."</a>";
	echo "<tr class='$bg'><td>$id</td>
<td><a href='$form' target='preview'>$title</a></td>
<td>$contact</td>";
	foreach ($msgs as $stat=>$name) {
	    $value=$data[$name];
	    if ($data['store']==_DB_STORE_YES) {
		$value = sprintf($ancfmt, urlencode($stat), $id, $value);
	    } else {
		$value = $value?sprintf($ancfmt, urlencode($stat), $id, $value):$nodata;
	    }
	    echo $value;
	}
	echo "<td>$ope</td></tr>\n";
    }
    echo "</table><hr/>\n";
    return true;
}

function build_form($formid=0) {
    global $fields, $icmsConfig, $xoopsTpl;
    include_once dirname(__FILE__, 2) ."/language/".$icmsConfig['language'].'/main.php';

    if (isset($_POST['formid'])) {
	$data = array();
	$fields[] = 'priuid';
	$fields[] = 'cgroup';
	foreach ($fields as $name) {
	    $data[$name] = icms_core_DataFilter::stripSlashesGPC($_POST[$name]);
	}
	$data['optvars'] = post_optvars();
	$data['grpperm'] = $_POST['grpperm'];
	$formid = intval($_POST['formid']);
	// form preview
	get_attr_value($data['optvars']); // set default values
	$items = get_form_attribute($data['defs']);
	assign_form_widgets($items);
	if ($_POST['preview']) {
	    echo "<h2>"._PREVIEW." : ".htmlspecialchars($data['title'])."</h2>\n";
	    echo "<div class='preview'>\n";
	    $data['action'] = '';
	    $data['check_script'] = "";
	    $data['items'] =& $items;
	    if (empty($xoopsTpl)) $xoopsTpl = new icms_view_Tpl();
	    $out = $xoopsTpl->fetch('db:'.render_form($data, 'form'));
	    echo preg_replace('/type=["\']submit["\']/', 'type="submit" disabled="disabled"', $out);
	    echo "</div>\n<hr size='5'/>\n";
	}
    } elseif ($formid) {
	$res = icms::$xoopsDB->query('SELECT * FROM '.FORMS." WHERE formid=$formid");
	$data = icms::$xoopsDB->fetchArray($res);
	$data['grpperm'] = explode('|', trim($data['grpperm'], '|'));
    } else {
	$data = array('title'=>'', 'description'=>'', 'defs'=>'',
		      'store'=>1, 'custom'=>0, 'weight'=>0, 'active'=>1,
		      'priuid'=>icms::$user->getVar('uid'),
		      'cgroup'=>ICMS_GROUP_ADMIN,
		      'optvars'=>'',
		      'grpperm'=>array(ICMS_GROUP_USERS));
    }
    $form = new icms_form_Theme($formid?_AM_FORM_EDIT:_AM_FORM_NEW, 'myform', 'index.php');
    $form->addElement(new icms_form_elements_Hidden('formid', $formid));
    $form->addElement(new icms_form_elements_Text(_AM_FORM_TITLE, 'title', 35, 80, $data['title']), true);
    if (!empty($data['mtime'])) $form->addElement(new icms_form_elements_Label(_AM_FORM_MTIME, formatTimestamp($data['mtime'])));
    $desc = new icms_form_elements_Tray(_AM_FORM_DESCRIPTION, "<br/>");
    $description = $data['description'];
    $editor = get_attr_value(null, 'use_fckeditor');
    if ($editor) {
	$desc->addElement(new icms_form_elements_TextArea('', 'description', $description, 10, 60));
    } else {
	$desc->addElement(new icms_form_elements_Dhtmltextarea('', 'description', $description, 10, 60));
    }
    if (!$editor) {
	$button = new icms_form_elements_Button('', 'ins_tpl', _AM_INS_TEMPLATE);
	$button->setExtra("onClick=\"myform.description.value += defsToString();\"");
	$desc->addElement($button);
    }
    $error = check_form_tags($data['custom'], $data['defs'], $description);
    if ($error) $desc->addElement(new icms_form_elements_Label('', "<div style='color:red;'>$error</div>"));
    $form->addElement($desc);
    $custom = new icms_form_elements_Select(_AM_FORM_CUSTOM, 'custom' , $data['custom']);
    $custom->setExtra(' onChange="myform.ins_tpl.disabled = (this.value==0||this.value==4);"');
    $custom_type = unserialize_vars(_AM_CUSTOM_DESCRIPTION);
    if ($editor) unset($custom_type[0]);
    $custom->addOptionArray($custom_type);
    $form->addElement($custom);
    $grpperm = new icms_form_elements_select_Group(_AM_FORM_ACCEPT_GROUPS, 'grpperm', true, $data['grpperm'], 4, true);
    $grpperm->setDescription(_AM_FORM_ACCEPT_GROUPS_DESC);
    $form->addElement($grpperm);
    $defs_tray = new icms_form_elements_Tray(_AM_FORM_DEFS);
    $defs_tray->addElement(new icms_form_elements_TextArea('', 'defs', $data['defs'], 10, 60));
    $defs_tray->addElement(new icms_form_elements_Label('',
'<div id="itemhelper" style="display:none; white-space:nowrap;">
  '._AM_FORM_LAB.' <input name="xelab" size="10">
  <input type="checkbox" name="xereq" title="'._AM_FORM_REQ.'">
  <select name="xetype">
    <option value="text">text</option>
    <option value="checkbox">checkbox</option>
    <option value="radio">radio</option>
    <option value="textarea">textarea</option>
    <option value="select">select</option>
    <option value="const">const</option>
    <option value="hidden">hidden</option>
    <option value="mail">mail</option>
    <option value="file">file</option>
  </select>
  <input name="xeopt" size="30" />
  <button onClick="return addFieldItem();">'._AM_FORM_ADD.'</button>
</div>'));
    $defs_tray->setDescription(_AM_FORM_DEFS_DESC);
    $form->addElement($defs_tray);

    $member_handler = icms::handler('icms_member');
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $options = array();
    foreach ($groups as $k=>$v) {
	$options[-$k] = sprintf(_CC_FORM_PRIM_GROUP, $v);
    }
    $options[0] = _AM_FORM_PRIM_NONE;

    $priuid = new MyFormSelect(_AM_FORM_PRIM_CONTACT, 'priuid', $data['priuid']);
    $priuid->addOptionArray($options);
    $priuid->addOptionUsers($data['cgroup']);
    $priuid->setDescription(_AM_FORM_PRIM_DESC);
    $form->addElement($priuid) ;

    $cgroup = new icms_form_elements_Select('', 'cgroup', $data['cgroup']);
    $cgroup->setExtra(' onChange="setSelectUID(\'priuid\', 0);"');
    $cgroup->addOption(0, _AM_FORM_CGROUP_NONE);
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $cgroup->addOptionArray($groups);

    $cgroup_tray = new icms_form_elements_Tray(_AM_FORM_CONTACT_GROUP);
    $cgroup_tray->addElement($cgroup) ;
    $cgroup_tray->addElement(new icms_form_elements_Label('' , '<noscript><input type="submit" name="chggrp" id="chggrp" value="'._AM_CHANGE.'"/></noscript>'));

    $form->addElement($cgroup_tray) ;


    $store = new icms_form_elements_Select(_AM_FORM_STORE, 'store', $data['store']);
    $store->addOptionArray(unserialize_vars(_CC_STORE_MODE, 1));
    $form->addElement($store);
    $form->addElement(new icms_form_elements_Radioyn(_AM_FORM_ACTIVE, 'active' , $data['active']));

    $form->addElement(new icms_form_elements_Text(_AM_FORM_WEIGHT, 'weight', 2, 8, $data['weight']));
    {
	$items = get_form_attribute(_CC_OPTDEFS, _AM_OPTVARS_LABEL, 'optvar');
	$vars = unserialize_vars($data['optvars']);
	$others = "";
	foreach ($items as $k=>$item) {
	    $name = $item['name'];
	    if (isset($vars[$name])) {
		$items[$k]['default'] = $vars[$name];
		unset($vars[$name]);
	    }
	}
	$val = "";
	foreach ($vars as $i=>$v) {
	    $val .= "$i=$v\n";
	}
	$items[$k]['default'] = $val;
	assign_form_widgets($items);
	$varform="";
	foreach ($items as $item) {
	    $br = ($item['type'] =="textarea")?"<br/>":"";
	    $varform .= "<div>".$item['label'].": $br".$item['input']."</div>";
	}
    }
    $ck = empty($data['optvars'])?"":" checked='checked'";
    $optvars = new icms_form_elements_Label(_AM_FORM_OPTIONS, "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onChange='toggle(this);'$ck/> "._AM_OPTVARS_SHOW."\");</script><div id='optvars'>$varform</div>");
    $form->addElement($optvars);
    $submit = new icms_form_elements_Tray('');
    $submit->addElement(new icms_form_elements_Button('' , 'formdefs', _SUBMIT, 'submit'));
    $submit->addElement(new icms_form_elements_Button('' , 'preview', _PREVIEW, 'submit'));
    $form->addElement($submit) ;

    echo "<a name='form'></a>";
    $form->display();
    if ($editor) {
	$base = ICMS_URL."/common/fckeditor";
	global $xoopsTpl;
	echo "<script type='text/javascript' src='$base/fckeditor.js'></script>\n";
	$editor =
"var ccFCKeditor = new FCKeditor('description', '100%', '350', '$editor');
ccFCKeditor.BasePath = '$base/';
ccFCKeditor.ReplaceTextarea();";
    }
    echo '<script language="JavaScript">'.
	$priuid->renderSupportJS(false).
'
// display only JavaScript enable
xoopsGetElementById("itemhelper").style.display = "block";
'.$editor.'
function toggle(a) {
    xoopsGetElementById("optvars").style.display = a.checked?"block":"none";
}
toggle(xoopsGetElementById("optshow"));

function addFieldItem() {
    var myform = window.document.myform;
    var item=myform.xelab.value;
    if (item == "") {
	alert("'._AM_FORM_LABREQ.'");
	myform.xelab.focus();
	return false;
    }
    if (myform.xereq.checked) item += "*";
    var ty = myform.xetype.value;
    var ov = myform.xeopt.value;
    item += ","+ty;
    if (ty != "text" && ty != "textarea" && ty != "file" && ty != "mail" && ov == "") {
	alert(ty+": '._AM_FORM_OPTREQ.'");
	myform.xeopt.focus();
	return false;
    }
    if (ov != "") item += ","+ov;
    opts = myform.defs;
    if (opts.value!="" && !opts.value.match(/[\n\r]$/)) item = "\n"+item;
    opts.value += item;
    myform.xelab.value = ""; // clear old value
    myform.xeopt.value = "";
    return false; // always return false
}
function defsToString() {
    value = window.document.myform.defs.value;
    ret = "";
    lines = value.split("\\n");
    conf = "'._MD_CONF_LABEL.'";
    for (i in lines) {
       lab = lines[i].replace(/,.*$/, "");
       if (lab.match(/^\s*#/)) {
           ret += "[desc]<div>"+lines[i].replace(/^\s*#/, "")+"</div>[/desc]\n";
       } else if (lab != "") {
           ret += "<div>"+lab+": {"+lab.replace(/\\*?$/,"")+"}</div>\n";
           if (lines[i].match(/^[^,]+,\\s*mail/i)) {
              lab = conf.replace(/%s/, lab);
              ret += "[desc]<div>"+lab+": {"+lab.replace(/\\*?$/,"")+"}</div>[/desc]\n";
           }
       }
    }
    return "<form {FORM_ATTR}>\n"+ret+
      "<p>{SUBMIT} {BACK}</p>\n</form>\n{CHECK_SCRIPT}";
}

fvalue = document.myform.custom.value;
document.myform.ins_tpl.disabled = (fvalue==0 || fvalue==4);
</script>
';
}