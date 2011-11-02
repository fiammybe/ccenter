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

$failed_requirements = array();

/* ImpressCMS Builtd needs to be at lest .. (version number) */
if (ICMS_VERSION_BUILD < 40) {
	$failed_requirements[] = _AM_CCENTER_REQUIREMENTS_ICMS_BUILD;
}

if (count($failed_requirements) > 0) {
	icms_cp_header();
	
	$icmsAdminTpl->assign('failed_requirements', $failed_requirements);
	$icmsAdminTpl->display(CCENTER_TEMPLATES_URL . 'ccenter_requirements.html');
	
	icms_cp_footer();
	exit;
}