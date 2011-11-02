<?php
/**
 * ccenter is a form module
 * 
 * File: /admin/indexpage.php
 * 
 * create index-page for ccenter module. based on McDonalds Indexpage for impression/imlinks
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * ---------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */


function editform($indexkey = 1, $indeximage = true) {

	global $ccenter_indexpage_handler, $icmsAdminTpl;

	$indexpageObj = $ccenter_indexpage_handler->get($indexkey);
	$indexpageObj->setVar('indeximage', $indeximage);
	
	$sform = $indexpageObj -> getForm(_AM_CCENTER_FORM_EDIT, 'addform');
	$sform->assign($icmsAdminTpl);

	$icmsAdminTpl->display('db:ccenter_admin_form.html');
	
}

include 'admin_header.php';

$clean_indexkey = $clean_op = $valid_op = '';
$ccenter_indexpage_handler = icms_getModuleHandler('indexpage', basename(dirname(dirname(__FILE__))), "ccenter");

$op = isset($_GET['op']) ? filter_input(INPUT_GET, 'op') : '';
if (isset($_POST['op'])) $op = filter_input(INPUT_POST, 'op');
$formid = isset($_REQUEST['indexkey']) ? (int) $_REQUEST['indexkey'] : 0;

$valid_op = array ( 'mod','changedField','addform', '' );

if (isset($_GET['op'])) $clean_op = htmlentities($_GET['op']);
if (isset($_POST['op'])) $clean_op = htmlentities($_POST['op']);

$clean_indexkey = isset($_GET['indexkey']) ? (int) $_GET['indexkey'] : 0 ;

if ( in_array( $clean_op, $valid_op, true ) ) {
  switch ($clean_op) {
  	case "mod":
		icms_cp_header();
		ccenter_adminmenu( 2, _MI_CCENTER_INDEXPAGE );
		editform($indexkey=1, false);
		break;
	
  	case "changedField":
  		icms_cp_header();
		ccenter_adminmenu( 2, _MI_CCENTER_INDEXPAGE );
  		editform( $clean_indexkey );
  		break;
		
  	case "addform":
		$controller = new icms_ipf_Controller( $ccenter_indexpage_handler );
  		$controller->storeFromDefaultForm( _AM_CCENTER_FORM_CREATED, _AM_CCENTER_FORM_MODIFIED );
        //build_form($formid = 0);
  		break;
  }
  icms_cp_footer();
}