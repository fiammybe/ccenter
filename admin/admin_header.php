<?php
/**
* ccenter is a form module
*
* File: /admin/admin_header.php
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
 
include '../../../include/cp_header.php';

$moddir = icms::$module -> getVar( 'dirname' );

include_once ICMS_ROOT_PATH . '/modules/' . icms::$module -> getVar( 'dirname' ) . '/include/common.php';

global $icmsConfig;
if ( file_exists( ICMS_ROOT_PATH . '/modules/' . $moddir . '/language/' . $icmsConfig['language'] . '/modinfo.php' ) ) {
	include_once ICMS_ROOT_PATH . '/modules/' . $moddir . '/language/' . $icmsConfig['language'] . '/modinfo.php';
} else { include_once ICMS_ROOT_PATH . '/language/english/modinfo.php'; }

if(!defined("CCENTER_ADMIN_URL")) define("CCENTER_ADMIN_URL", CCENTER_URL . 'admin/');

include_once(CCENTER_ROOT_PATH . 'include/requirements.php');