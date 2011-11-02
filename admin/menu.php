<?php
/**
 * ccenter is a form module
 * 
 * File: /admin/menu.php
 * 
 * contains menu-structure in ACP for ccenter module
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * ---------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		0.94
 * @author		Nobuhiro Yasutomi
 * @package		ccenter
 * ---------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.0
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */


$i = 0;

$adminmenu[$i]['title'] = _MI_CCENTER_FORMADMIN;
$adminmenu[$i]['link'] = 'admin/index.php';

$i++;
$adminmenu[$i]['title'] = _MI_CCENTER_MSGADMIN;
$adminmenu[$i]['link'] = 'admin/msgadm.php';

$i++;
$adminmenu[$i]['title'] = _MI_CCENTER_INDEXPAGE;
$adminmenu[$i]['link'] = 'admin/indexpage.php?op=mod&indexkey=1';

$i++;
$adminmenu[$i]['title'] = _MI_CCENTER_HELP;
$adminmenu[$i]['link'] = 'admin/help.php';

global $icmsConfig;

$ccenterModule = icms_getModuleInfo( basename( dirname( dirname( __FILE__) ) ) );
$moddir = basename( dirname( dirname( __FILE__) ) );
if (isset($ccenterModule)) {

	$i = 0;
	
	$headermenu[$i]['title'] = _CO_ICMS_GOTOMODULE;
	$headermenu[$i]['link'] = ICMS_URL . '/modules/' . $moddir;

	$i++;
	$headermenu[$i]['title'] = _PREFERENCES;
	$headermenu[$i]['link'] = '../../system/admin.php?fct=preferences&amp;op=showmod&amp;mod=' . $ccenterModule-> getVar ('mid');

	$i++;
	$headermenu[$i]['title'] = _MI_CCENTER_TEMPLATES;
	$headermenu[$i]['link'] = '../../system/admin.php?fct=tplsets&op=listtpl&tplset=' . $icmsConfig['template_set'] . '&moddir=' . $moddir;

	$i++;
	$headermenu[$i]['title'] = _CO_ICMS_UPDATE_MODULE;
	$headermenu[$i]['link'] = ICMS_URL . '/modules/system/admin.php?fct=modulesadmin&op=update&module=' . $moddir;

	$i++;
	$headermenu[$i]['title'] = _MODABOUT_ABOUT;
	$headermenu[$i]['link'] = ICMS_URL . '/modules/' . $moddir . '/admin/about.php';
}