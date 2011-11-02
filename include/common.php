<?php
/**
* ccenter is a form module
*
* File: /include/common.php
*
* Common file of the module included on all pages of the module
*
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

if(!defined("CCENTER_DIRNAME")) define("CCENTER_DIRNAME", $modversion['dirname'] = icms::$module -> getVar( 'dirname' ));

if(!defined("CCENTER_URL")) define("CCENTER_URL", ICMS_URL . '/modules/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_ROOT_PATH")) define("CCENTER_ROOT_PATH", ICMS_ROOT_PATH.'/modules/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_IMAGES_URL")) define("CCENTER_IMAGES_URL", CCENTER_URL . 'images/');

if(!defined("CCENTER_ADMIN_URL")) define("CCENTER_ADMIN_URL", CCENTER_URL . 'admin/');

if(!defined("CCENTER_TEMPLATES_URL")) define("CCENTER_TEMPLATES_URL", CCENTER_URL . 'templates/');

if(!defined("CCENTER_IMAGES_ROOT")) define("CCENTER_IMAGES_ROOT", CCENTER_ROOT_PATH . 'images/');

if(!defined("CCENTER_UPLOAD_ROOT")) define("CCENTER_UPLOAD_ROOT", ICMS_ROOT_PATH . '/uploads/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_UPLOAD_URL")) define("CCENTER_UPLOAD_URL", ICMS_URL . '/uploads/' . CCENTER_DIRNAME . '/');

// Include the common language file of the module
icms_loadLanguageFile('ccenter', 'common');

include_once CCENTER_ROOT_PATH . '/include/functions.php';
include_once CCENTER_ROOT_PATH . '/class/ccenter.php';

// Creating the module object to make it available throughout the module
$ccenterModule = icms_getModuleInfo(CCENTER_DIRNAME);
if (is_object($ccenterModule)) {
	$ccenter_moduleName = $ccenterModule->getVar('name');
}

// Find if the user is admin of the module and make this info available throughout the module
$ccenter_isAdmin = icms_userIsAdmin(CCENTER_DIRNAME);

// Creating the module config array to make it available throughout the module
$ccenterConfig = icms_getModuleConfig(CCENTER_DIRNAME);

$ccenterRegistry = icms_ipf_registry_Handler::getInstance();