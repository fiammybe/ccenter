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
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_EDITING);
		$sform = $formObj -> build_form(_AM_CCENTER_FORM_EDIT, 'addform');
		$sform->assign($icmsAdminTpl);
	} elseif (!$formObj->isNew() && $clone) {
		$formObj->setVar('formid', 0);
		$formObj->setNew();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _AM_CCENTER_FORM_CLONE);
		$sform = $formObj->getForm(_AM_CCENTER_FORM_CLONE, 'addform');
		$sform->assign($icmsAdminTpl);
	} else {
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

$fields = array('title', 'short_url', 'description', 'defs', 'priuid', 'cgroup',
		'store', 'custom', 'weight', 'active');
		
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
			$formObj = $ccenter_form_handler->get($formid);
			$formObj->setVar('formid', 0);
			$formObj->setNew();
			icms_cp_header();
			ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_CREATINGNEW);
			build_form($formid, true);
			break;

  	case "mod":

		icms_cp_header();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_CREATINGNEW);
		build_form($clean_formid, false);
		break;
	
  	case "changedField":
		$formObj = $ccenter_form_handler->get($formid);
  		icms_cp_header();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_CREATINGNEW);
  		build_form($formid);
  		break;
  	case "addform":
		icms_cp_header();
		ccenter_adminmenu( 0, _MI_CCENTER_FORMADMIN . " > " . _CO_ICMS_CREATINGNEW);
		
        build_form();
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
		
		$criteria = '';
		
		// display a tag select filter (if the Sprockets module is installed)
		$sprocketsModule = icms_getModuleInfo('sprockets');
		
		// if no op is set, but there is a (valid) formid, display a single object
		if ($clean_formid) {
			$formObj = $ccenter_form_handler->get($clean_formid);
			if ($formObj->id()) {
				$formObj->displaySingleObject();
			}
		}
		
		if ($sprocketsModule) {
			
			$tag_select_box = '';
			$taglink_array = $tagged_item_list = array();
			$sprockets_tag_handler = icms_getModuleHandler('tag', $sprocketsModule->dirname(),
				'sprockets');
			$sprockets_taglink_handler = icms_getModuleHandler('taglink', 
					$sprocketsModule->dirname(), 'sprockets');
			$ccenterModule = icms_getModuleInfo( icms::$module -> getVar( 'dirname' ) );
			
			$tag_select_box = $sprockets_tag_handler->getTagSelectBox('index.php', $clean_tag_id,
				_AM_CCENTER_ITEM_ALL_ITEMS);
			if (!empty($tag_select_box)) {
				echo '<h3>' . _AM_CCENTER_FILTER_FORM_BY_TAG . '</h3>';
				echo $tag_select_box;
			}
			
			if ($clean_tag_id) {
				
				// get a list of item IDs belonging to this tag
				$criteria = new icms_db_criteria_Compo();
				$criteria->add(new icms_db_criteria_Item('tid', $clean_tag_id));
				$criteria->add(new icms_db_criteria_Item('mid', $ccenterModule -> getVar( 'mid' )));
				$criteria->add(new icms_db_criteria_Item('form', 'form'));
				$taglink_array = $sprockets_taglink_handler->getObjects($criteria);
				foreach ($taglink_array as $taglink) {
					$tagged_item_list[] = $taglink->getVar('iid');
				}
				$tagged_item_list = "('" . implode("','", $tagged_item_list) . "')";
				
				// use the list to filter the table
				$criteria = new icms_db_criteria_Compo();
				$criteria->add(new icms_db_criteria_Item('formid', $tagged_item_list, 'IN'));
			}
		}
		
		if (empty($criteria)) {
			$criteria = null;
		}

  		$objectTable = new icms_ipf_view_Table($ccenter_form_handler, $criteria);
		$objectTable->addColumn(new icms_ipf_view_Column('active', 'center', true));
  		$objectTable->addColumn(new icms_ipf_view_Column('title', false, false, 'getPreviewItemLink'));
		$objectTable->addColumn(new icms_ipf_view_Column('weight', 'center', true, 'getWeightControl'));
		
		$objectTable->addFilter('active', 'active_filter');
  		
		$objectTable->addIntroButton('addform', 'index.php?op=mod', _AM_CCENTER_ADD_FORM);
		$objectTable->addActionButton('changeWeight', false, _SUBMIT);
  		
		$objectTable->addCustomAction('getViewItemLink');
		$objectTable->addCustomAction('getCloneItemLink');
		
		
		$icmsAdminTpl->assign('ccenter_form_table', $objectTable->fetch());
  		$icmsAdminTpl->display('db:ccenter_admin_form.html');
  		break;
  }
  icms_cp_footer();
}

