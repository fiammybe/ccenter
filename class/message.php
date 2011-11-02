<?php
/**
* ccenter is a form module
*
* File: /class/message.php
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

class CcenterMessage extends icms_ipf_seo_Object {

	public function __construct( &$handler ) {
		icms_ipf_object::__construct( $handler );
		
		$this -> quickInitVar( 'msgid', XOBJ_DTYPE_INT, true );
		$this -> quickInitVar( 'uid', XOBJ_DTYPE_INT, true, false, false, 0 );
		$this -> quickInitVar( 'touid', XOBJ_DTYPE_INT, true );
		$this -> quickInitVar( 'ctime', XOBJ_DTYPE_INT, false );
		$this -> quickInitVar( 'mtime', XOBJ_DTYPE_MTIME, false );
		$this -> quickInitVar( 'atime', XOBJ_DTYPE_INT, true );
		$this -> quickInitVar( 'fidref', XOBJ_DTYPE_INT, true, false, false, 0 );
		$this -> quickInitVar( 'email', XOBJ_DTYPE_EMAIL );
		$this -> quickInitVar( 'body', XOBJ_DTYPE_TXTAREA );
		$this -> quickInitVar( 'status', XOBJ_DTYPE_SIMPLE_ARRAY, true, false, false, '-' );
		$this -> quickInitVar( 'value', XOBJ_DTYPE_INT, true, false, false, 0 );
		$this -> initCommonVar( 'comment', XOBJ_DTYPE_TXTAREA );
		$this -> quickInitVar( 'comtime', XOBJ_DTYPE_INT, true, false, false, 0 );
		$this -> quickInitVar( 'onepass', XOBJ_DTYPE_TXTBOX, true );
		$this -> initNonPersistableVar( 'tag', XOBJ_DTYPE_INT, 'tag', false, false, false, true );
		
		$this -> setControl( 'uid', 'user' );
		$this -> setControl( 'touid', 'user' );
		$this -> setControl( 'email', 'text' );
		$this -> setControl( 'body', 'textarea' );
		//$this -> setControl( 'status', array ( 'name' => 'select', 'itemHandler' => 'message', 'method' => 'getStatus', 'module' => 'ccenter' ) );
		
		// Only display the tag field if Sprockets is installed - original from Madfishs News-Module
		$sprocketsModule = icms_getModuleInfo('sprockets');
		if ($sprocketsModule) {
			$this->setControl('tag', array(
			'name' => 'select_multi',
			'itemHandler' => 'tag',
			'method' => 'getTags',
			'module' => 'sprockets'));
		} else {
			$this->hideFieldFromForm('tag');
			$this->hideFieldFromSingleView ('tag');
		}

	}
		
		//original from Madfishs News-Module
	public function loadTags() {
		
		$ret = '';
		
		$sprocketsModule = icms_getModuleInfo('sprockets');
		if ($sprocketsModule) {
			$sprockets_taglink_handler = icms_getModuleHandler('taglink',
					$sprocketsModule->dirname(), 'sprockets');
			$ret = $sprockets_taglink_handler->getTagsForObject($this->id(), $this->handler);
			$this->setVar('tag', $ret);
		}
	}
	
}

class CcenterMessageHandler extends icms_ipf_Handler {

	public function __construct( &$db ) {
		parent::__construct( $db, 'message', 'msgid', '', '', 'ccenter' );
		icms_loadLanguageFile(basename(dirname(dirname(__FILE__))), 'common');
	}
	
	public function updateComments($msgid, $total_num) {
		$messageObj = $this->get($messageObj);
		if ($messageObj && !$messageObj->isNew()) {
			$messageObj->setVar('form_comments', $total_num);
			$this->insert($messageObj, true);
		}
	}
	
	protected function afterDelete(& $obj) {
		$notification_handler = icms::handler( 'icms_data_notification' );
		$module_handler = icms::handler('icms_module');
		$module = $module_handler -> getByDirname( icms::$module -> getVar( 'dirname' ) );
		$module_id = icms::$module -> getVar( 'mid' );
		$category = 'global';
		$msgid = $obj->id();

		// delete global notifications
		$notification_handler->unsubscribeByItem($module_id, $category, $msgid);

		return true;
	}
}