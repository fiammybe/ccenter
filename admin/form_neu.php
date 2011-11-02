<?php
/**
* ccenter is a form module
*
* File: /admin/index.php
*
* adminstration messages
*
* @copyright	Copyright QM-B (Steffen Flohrer) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
*
* ----------------------------------------------------------------------------------------------------------
* 				ccenter 
* @since		0.94
* @author		Nobuhiro Yasutomi
* @package		ccenter
* ----------------------------------------------------------------------------------------------------------
* 				ccenter
* @since		1.00
* @author		QM-B
* @version		$Id$
* @package		ccenter
* @version		$Id$
*/

function editform($formid = 0, $clone = false) {

	global $ccenter_form_handler, $icmsAdminTpl;

	$formObj = $ccenter_form_handler->get($formid);

	if (!$clone && !$formObj->isNew()) {
		$data['optvars'] = post_optvars();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_EDITING);
		$sform = $formObj -> getForm(_AM_CCENTER_FORM_EDIT, 'addform');
		$sform->assign($icmsAdminTpl);
	} elseif (!$formObj->isNew() && $clone) {
		$data['optvars'] = post_optvars();
		$formObj->setVar('formid', 0);
		$formObj->setNew();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _AM_CCENTER_FORM_CLONE);
		$sform = $formObj->getForm(_AM_CCENTER_FORM_CLONE, 'addform');
		$sform->assign($icmsAdminTpl);
	} else {
		$data['optvars'] = post_optvars();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_CREATINGNEW);
		$sform = $formObj->getForm(_AM_CCENTER_FORM_CREATE, 'addform');
		$sform->assign($icmsAdminTpl);
	}
	$icmsAdminTpl->display('db:ccenter_admin_form.html');
	
}

include_once("admin_header.php");

$clean_formid = $clean_tag_id = $clean_op = $valid_op = '';
$ccenter_form_handler = icms_getModuleHandler('form', basename(dirname(dirname(__FILE__))), "ccenter");

$op = isset($_GET['op']) ? filter_input(INPUT_GET, 'op') : '';
if (isset($_POST['op'])) $op = filter_input(INPUT_POST, 'op');
$formid = isset($_REQUEST['formid']) ? (int) $_REQUEST['formid'] : 0;

$valid_op = array ('clone','mod','changedField','addform','del','view','visible', 'changeWeight', '');

if (isset($_GET['op'])) $clean_op = htmlentities($_GET['op']);
if (isset($_POST['op'])) $clean_op = htmlentities($_POST['op']);

$clean_formid = isset($_GET['formid']) ? (int) $_GET['formid'] : 0 ;
$clean_tag_id = isset($_GET['tag_id']) ? intval($_GET['tag_id']) : 0 ;

// option variables form definitions
define('_CC_OPTDEFS',"notify_with_email,radio,1="._YES.",="._NO."
redirect,text,size=60
reply_comment,textarea,cols=60,rows=10
reply_use_comtpl,radio,1="._YES.",="._NO."
others,textarea");

$fields = array('title', 'short_url', 'description', 'defs', 'priuid', 'cgroup', 'store', 'custom', 'weight', 'active');
		
if (isset($_POST['formdefs']) && !isset($_POST['preview'])) {
    $formid = intval($_POST['formid']);
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

if (in_array($clean_op,$valid_op,true)){
  switch ($clean_op) {
	case "clone" :
			icms_cp_header();
			editform($clean_formid, true);
			break;

  	case "mod":

		icms_cp_header();
		editform($clean_formid, false);
		break;
	
  	case "changedField":

  		icms_cp_header();

  		editform($clean_formid);
  		break;
  	case "addform":
		$controller = new icms_ipf_Controller($ccenter_form_handler);
  		$controller->storeFromDefaultForm(_AM_CCENTER_FORM_CREATED, _AM_CCENTER_FORM_MODIFIED);
        //build_form($formid = 0);
  		break;

  	case "del":
        $controller = new icms_ipf_Controller($ccenter_form_handler);
  		$controller->handleObjectDeletion();

  		break;

  	case "view":
  		$formObj = $ccenter_form_handler->get($clean_formid);
  		icms_cp_header();
  		$formObj->displaySingleObject();

  		break;

	case "visible":
		$visibility = $ccenter_form_handler->changeVisible($clean_formid);
		$ret = '/modules/' . CCENTER_DIRNAME . '/admin/index.php';
		if ($visibility == 0) {
			redirect_header(ICMS_URL . $ret, 2, _AM_CCENTER_FORM_OFFLINE);
		} else {
			redirect_header(ICMS_URL . $ret, 2, _AM_CCENTER_FORM_ONLINE);
		}
		
		break;
	
	case "changeWeight":
		foreach ($_POST['CcenterForm_objects'] as $key => $value) {
			$changed = false;
			$formObj = $ccenter_form_handler->get($value);

			if ($formObj->getVar('weight', 'e') != $_POST['weight'][$key]) {
				$formObj->setVar('weight', intval($_POST['weight'][$key]));
				$changed = true;
			}
			if ($changed) {
				$ccenter_form_handler->insert($formObj);
			}
		}
		$ret = '/modules/' . CCENTER_DIRNAME . '/admin/index.php';
		redirect_header(ICMS_URL . $ret, 2, _AM_CCENTER_ITEM_WEIGHTS_UPDATED);

		break;

  	default:
  		icms_cp_header();

  		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN );
		
		icms_loadLanguageFile('ccenter', 'main');
		

  }
  icms_cp_footer();
}

	function build_form($formid=0) {
		global $fields, $icmsConfig, $icmsTpl;
		include_once dirname(dirname(__FILE__))."/language/".$icmsConfig['language'].'/main.php';
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
			echo "<h2>" . _PREVIEW . " : ".htmlspecialchars($data['title'])."</h2>\n";
			echo "<div class='preview'>\n";
			$data['action'] = '';
			$data['check_script'] = "";
			$data['items'] =& $items;
			if (empty($icmsAdminTpl)) $icmsAdminTpl = new icms_view_Tpl();
			$out = $icmsAdminTpl->fetch('db:'.render_form($data, 'form'));
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
		$form = new icms_form_Theme( $formid ? _AM_FORM_EDIT : _AM_FORM_NEW, 'myform', 'index.php');
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

		$member_handler =& icms::handler('icms_member');
		$groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
		$groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
		$options = array();
		foreach ($groups as $k=>$v) {
			$options[-$k] = sprintf(_CC_FORM_PRIM_GROUP, $v);
		}
		$options[0] = _AM_FORM_PRIM_NONE;

		$priuid = new MyFormSelect(_AM_FORM_PRIM_CONTACT, 'priuid', $data['priuid']);
		$priuid -> addOptionArray( $options );
		$priuid -> addOptionUsers( $data['cgroup'] );
		$priuid -> setDescription(_AM_FORM_PRIM_DESC );
		$form -> addElement( $priuid ) ;

		$cgroup = new icms_form_elements_Select( '', 'cgroup', $data['cgroup'] );
		$cgroup->setExtra(' onChange="setSelectUID(\'priuid\', 0);"');
		$cgroup->addOption(0, _AM_FORM_CGROUP_NONE);
		$groups = $member_handler -> getGroupList( new icms_db_criteria_Item( 'groupid', ICMS_GROUP_ANONYMOUS, '!=' ) );
		$cgroup->addOptionArray( $groups );

		$cgroup_tray = new icms_form_elements_Tray( _AM_FORM_CONTACT_GROUP );
		$cgroup_tray -> addElement( $cgroup );
		$cgroup_tray -> addElement( new icms_form_elements_Label( '' , '<noscript><input type="submit" name="chggrp" id="chggrp" value="'._AM_CHANGE.'"/></noscript>' ) );

		$form -> addElement( $cgroup_tray ) ;

    
		$store = new icms_form_elements_Select( _AM_FORM_STORE, 'store', $data['store'] );
		$store -> addOptionArray( unserialize_vars( _CC_STORE_MODE, 1) );
		$form -> addElement( $store );
		$form -> addElement( new icms_form_elements_Radioyn( _AM_FORM_ACTIVE, 'active' , $data['active'] ) );
		$form -> addElement( new icms_form_elements_Text( _AM_FORM_WEIGHT, 'weight', 2, 8, $data['weight'] ) );
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
	
			$items[$k][ 'default' ] = $val;
			assign_form_widgets( $items );
			$varform = "";
			foreach ( $items as $item ) {
				$br = ($item[ 'type' ] =="textarea")?"<br/>":"";
				$varform .= "<div>" . $item[ 'label' ] . ": $br" . $item[ 'input' ] . "</div>";
			}
		}
    
		$ck = empty($data['optvars'])?"":" checked='checked'";
		$optvars = new icms_form_elements_Select(_AM_FORM_OPTIONS, "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onChange='toggle(this);'$ck/> " . _AM_OPTVARS_SHOW . "\");</script><div id='optvars'>$varform</div>");
		$form->addElement($optvars);
		$submit = new icms_form_elements_Tray('');
		$submit -> addElement( new icms_form_elements_Button( '' , 'formdefs', _SUBMIT, 'submit' ) );
		$submit -> addElement( new icms_form_elements_Button( '' , 'preview', _PREVIEW, 'submit' ) );
		$form -> addElement( $submit );

		echo "<a name='form'></a>";
		$form->display();
		if ($editor) {
			$base = ICMS_URL."/editors/fckeditor";
			global $icmsTpl;
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
			togle(xoopsGetElementById("optshow"));

			function addFieldItem() {
				var myform = window.document.myform;
				var item=myform.xelab.value;
				if (item == "") {
					alert("'. _AM_FORM_LABREQ . '");
					myform.xelab.focus();
					return false;
				}
				if (myform.xereq.checked) item += "*";
				var ty = myform.xetype.value;
				var ov = myform.xeopt.value;
				item += ","+ty;
				if (ty != "text" && ty != "textarea" && ty != "file" && ty != "mail" && ov == "") {
					alert(ty+": '. _AM_FORM_OPTREQ . '");
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
				conf = "' . _MD_CONF_LABEL . '";
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

