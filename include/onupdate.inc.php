<?php
/**
* ccenter is a form module
*
* File: /include/onupdate.inc.php
*
* contains module update and install functions
* 
* @copyright	Copyright QM-B (Steffen Flohrer) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* ----------------------------------------------------------------------------------------------------------
* 				ccenter
* @since		1.00
* @author		QM-B
* @version		$Id$
* @package		ccenter
* @version		$Id$
*/

if (!defined("ICMS_ROOT_PATH")) die("ICMS root path not defined");


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// DEFINE SOME FOLDERS /////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////

if(!defined("CCENTER_DIRNAME")) define("CCENTER_DIRNAME", $modversion['dirname'] = icms::$module -> getVar( 'dirname' ));

if(!defined("CCENTER_ROOT_PATH")) define("CCENTER_ROOT_PATH", ICMS_ROOT_PATH .'/modules/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_IMAGES_ROOT")) define("CCENTER_IMAGES_ROOT", CCENTER_ROOT_PATH . 'images/');

if(!defined("CCENTER_UPLOAD_ROOT")) define("CCENTER_UPLOAD_ROOT", ICMS_ROOT_PATH . '/uploads/' . CCENTER_DIRNAME . '/');

// this needs to be the latest db version
define('CCENTER_DB_VERSION', 1);


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////// SOME NEEDED FUNCTIONS ////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////


// CREATES UPLOAD-PATH AND FOLDER FOR INDEX-IMAGE
function ccenter_upload_paths() {

	// create an uploads directory for ccenter
	$path = ICMS_ROOT_PATH . '/uploads/' . CCENTER_DIRNAME;
	$directory_exists = $file_exists = $writeable = true;

	if ( !is_dir( $path . '/indeximages' ) ) {
		mkdir( $path . '/indeximages', 0777, true );
		copy( ICMS_ROOT_PATH . '/uploads/index.html', $path . '/index.html' );
		copy( ICMS_ROOT_PATH . '/uploads/index.html', $path . '/indeximages/index.html' );
		$contentx =@file_get_contents( ICMS_ROOT_PATH . '/modules/' . CCENTER_DIRNAME . '/images/ccenter_indeximage.png' );
		$openedfile = fopen( $images . '/ccenter_indeximage.png', "w" );
		fwrite( $openedfile, $contentx ); 
		fclose( $openedfile );
	}
	
}

// AUTHORIZING MOST NEEDED FILETYPES IN SYSTEM
function ccenter_authorise_mimetypes() {
	$dirname = icms::$module -> getVar( 'dirname' );
	$extension_list = array(
		'png', // image formats
		'gif',
		'jpg',
		'pdf',
		'zip',
		'rar',
		'7z',
		'tar',
		'gz',
		'txt',
	);
	$system_mimetype_handler = icms_getModuleHandler('mimetype', 'system');
	foreach ($extension_list as $extension) {
		$allowed_modules = array();
		$mimetypeObj = '';

		$criteria = new icms_db_criteria_Compo;
		$criteria->add( new icms_db_criteria_Item('extension', $extension));
		$mimetypeObj = array_shift($system_mimetype_handler->getObjects($criteria));

		if ($mimetypeObj) {
			$allowed_modules = $mimetypeObj->getVar('dirname');
			if (empty($allowed_modules)) {
				$mimetypeObj->setVar('dirname', $dirname);
				$mimetypeObj->store();
			} else {
				if (!in_array($dirname, $allowed_modules)) {
					$allowed_modules[] = $dirname;
					$mimetypeObj->setVar('dirname', $allowed_modules);
					$mimetypeObj->store();
				}
			}
		}
	}
}

// CREATES AN EXAMPLE FORM
function ccenter_sample_form() {
    global $msgs;

    define('FORM', icms::$xoopsDB->prefix('ccenter_form'));

    $data = array(
		'mtime'=>time(),
		'title'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_TITLE),
		'description'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_DESC),
		'grpperm'=>"'|".ICMS_GROUP_ANONYMOUS."|".ICMS_GROUP_USERS."|'",
		'defs'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_DEFS),
		'priuid'=>icms::$user->getVar('uid'));

    icms::$xoopsDB->query('INSERT INTO '.FORM."(".join(',', array_keys($data)).")VALUES(".join(',', $data).")");
    $msgs[] = '&nbsp;&nbsp;<b>'._MI_SAMPLE_FORM."</b>";
}

// BASIC CREATION OF INDEX-PAGE
function ccenter_index_page() {
	$ccenter_indexpage_handler = icms_getModuleHandler('indexpage', basename(dirname(dirname(__FILE__))), 'ccenter');
	$gperm_handler = icms::handler('icms_member_groupperm');
	
	$indexpageObj = $ccenter_indexpage_handler->create(true);
	$indexpageObj->setVar( 'indexkey', '1' );
	$indexpageObj->setVar( 'indexheader', 'Contact Center' );
	$indexpageObj->setVar( 'indexheading', 'Here you can see our available [i][b]Contact Center[/b][/i] forms.' );
	$indexpageObj->setVar( 'indexfooter', 'Contact Center Footer' );
	$indexpageObj->setVar( 'indeximage', 'ccenter_indeximage.png' );
	$indexpageObj->setVar( 'dohtml', '1' );
	$indexpageObj->setVar( 'dobr', '1' );
	$indexpageObj->setVar( 'doimage', '1' );
	$indexpageObj->setVar( 'dosmiley', '1' );
	$indexpageObj->setVar( 'doxcode', '1' );
	$ccenter_indexpage_handler->insert($indexpageObj, true);
	
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////// UPDATE CCENTER MODULE ////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////


function icms_module_update_ccenter($module) {

	// check if upload directories exist and make them if not
	ccenter_upload_paths();
	
	$icmsDatabaseUpdater = XoopsDatabaseFactory::getDatabaseUpdater();
	$icmsDatabaseUpdater->moduleUpgrade($module); 
    return TRUE;
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////// INSTALL CCENTER MOULE ////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////


function icms_module_install_ccenter($module) {

	// check if upload directories exist and make them if not
	ccenter_upload_paths();
	
	//install sample form
	ccenter_sample_form ();
	
	// authorise some audio mimetypes for convenience
	ccenter_authorise_mimetypes();
	
	//prepare indexpage
	ccenter_index_page();

	return true;
}