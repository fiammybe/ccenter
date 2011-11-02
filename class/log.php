<?php
/**
* ccenter is a form module
*
* File: /class/log.php
*
* classes responsible for managing ccenter form objects
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


class CcenterLog extends icms_ipf_seo_Object {

	public function __construct( &$handler ) {
		icms_ipf_object::__construct( $handler );
		
		$this->quickInitVar('logid', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('ltime', XOBJ_DTYPE_LTIME, true, false, false, 0);
		$this->quickInitVar('fidref', XOBJ_DTYPE_INT, true);
		$this->quickInitVar('midref', XOBJ_DTYPE_INT, false);
		$this->quickInitVar('euid', XOBJ_DTYPE_INT, false);
		$this->quickInitVar('comment', XOBJ_DTYPE_TXTAREA, false);
	}
}

class CcenterLogHandler extends icms_ipf_Handler {

	public function __construct( &$db ) {
		parent::__construct( $db, 'log', 'logid', '', '', 'ccenter' );
	}

}