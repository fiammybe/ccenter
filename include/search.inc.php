<?php
/**
* ccenter version infomation
*
* This file holds the search information of this module
*
* @copyright	Copyright QM-B (Steffen Flohrer) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* @since		1.0
* @author		QM-B <qm-b@hotmail.de>
* @package		ccenter
* @version		$Id$
*/

if (!defined("ICMS_ROOT_PATH")) die("ICMS root path not defined");

function ccenter_search($queryarray, $andor, $limit, $offset, $userid) {
	
	global $ccenterConfig;
	
	$ccenter_form_handler = icms_getModuleHandler('form', icms::$module -> getVar( 'dirname' ),	'ccenter');
	$formsArray = $ccenter_form_handler->getFormsForSearch($queryarray, $andor, $limit,
		$offset, $userid);

	$ret = array();

	foreach ($formsArray as $formArray) {
		$item['image'] = "images/ccenter_iconsmall.png";
		$item['link'] = $formArray->getItemLink(true);
		$item['title'] = $formArray->title();
		$ret[] = $item;
		unset($item);
	}
	return $ret;
}