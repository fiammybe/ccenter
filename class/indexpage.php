<?php
/**
* ccenter is a form module
*
* File: /class/indexpage.php
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
* 
*/


defined( 'ICMS_ROOT_PATH' ) or die ( 'ICMS root path not defined' );


if(!defined("CCENTER_DIRNAME")) define("CCENTER_DIRNAME", $modversion['dirname'] = icms::$module -> getVar( 'dirname' ));

if(!defined("CCENTER_URL")) define("CCENTER_URL", ICMS_URL . '/modules/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_ROOT_PATH")) define("CCENTER_ROOT_PATH", ICMS_ROOT_PATH.'/modules/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_IMAGES_URL")) define("CCENTER_IMAGES_URL", CCENTER_URL . 'images/');

if(!defined("CCENTER_ADMIN_URL")) define("CCENTER_ADMIN_URL", CCENTER_URL . 'admin/');

if(!defined("CCENTER_TEMPLATES_URL")) define("CCENTER_TEMPLATES_URL", CCENTER_URL . 'templates/');

if(!defined("CCENTER_IMAGES_ROOT")) define("CCENTER_IMAGES_ROOT", CCENTER_ROOT_PATH . 'images/');

if(!defined("CCENTER_UPLOAD_ROOT")) define("CCENTER_UPLOAD_ROOT", ICMS_ROOT_PATH . '/uploads/' . CCENTER_DIRNAME . '/');

if(!defined("CCENTER_UPLOAD_URL")) define("CCENTER_UPLOAD_URL", ICMS_URL . '/uploads/' . CCENTER_DIRNAME . '/');



class CcenterIndexpage extends icms_ipf_seo_Object {

	public function __construct( &$handler ) {
		icms_ipf_object::__construct( $handler );

		$this -> quickInitVar( 'indexkey', XOBJ_DTYPE_INT, true );
		$this -> quickInitVar( 'indexheader', XOBJ_DTYPE_TXTBOX );
		$this -> quickInitVar( 'indexheading', XOBJ_DTYPE_TXTAREA );
		$this -> quickInitVar( 'indexfooter', XOBJ_DTYPE_TXTBOX );
		$this -> quickInitVar( 'indeximage', XOBJ_DTYPE_TXTBOX );
		$this -> initCommonVar( 'dohtml', true, true, '1' );
		$this -> initCommonVar( 'dobr', true, true, '1' );
		$this -> initCommonVar( 'doimage', true, true, '1' );
		$this -> initCommonVar( 'dosmiley', true, true, '1' );
		$this -> initCommonVar( 'doxcode', true, true, '1' );
		
		//hide static fields from form
		$this->hideFieldFromForm( array( 'indexkey', 'dohtml', 'dobr', 'doimage', 'dosmiley', 'doxcode' ) );
		$this->hideFieldFromSingleView( array( 'indexkey', 'dohtml', 'dobr', 'doimage', 'dosmiley', 'doxcode' ) );
		
		$this -> setControl( 'indexheading','dhtmltextarea' );
		$this -> setControl( 'indexheader', 'text');
		$this -> setControl( 'indexfooter', 'textarea' );
		// image path
		$this -> setControl( 'indeximage', array( 'name' => 'select', 'itemHandler' => 'indexpage', 'method' => 'getImageList', 'module' => 'ccenter' ) );
		//$this -> setControl( 'indeximage', 'image' );
		$url = CCENTER_UPLOAD_URL . 'indeximages/';
		$path = CCENTER_UPLOAD_ROOT . 'indeximages/';
		$this -> setImageDir( $url, $path );
	}

	public function get_indeximage_tag() {
		$indeximage = $image_tag = '';
		$indeximage = $this->getVar('indeximage', 'e');
		if (!empty($indeximage)) {
			$image_tag = CCENTER_UPLOAD_URL . 'indeximages/' . $indeximage;
		}
		return $image_tag;
	}
	
}

class CcenterIndexpageHandler extends icms_ipf_Handler {

	public function __construct( &$db ) {
		parent::__construct( $db, 'indexpage', 'indexkey',  '', '', basename( dirname( dirname( __FILE__ ) ) ) ); 
	}
	
	static public function getImageList() {
		$indeximages = array();
		$indeximages = icms_core_Filesystem::getFileList(ICMS_ROOT_PATH . '/uploads/' . icms::$module -> getVar( 'dirname' ) . '/indeximages/', '', array('gif', 'jpg', 'png'));
		$ret = array();
		$ret[0] = '-----------------------';
		foreach(array_keys($indeximages) as $i ) {
			$ret[$i] = $indeximages[$i];
		}
		return $ret;
	}
	
	/**
	public function getImageUrl() {
		return $this->_uploadUrl . '/indeximages/' $this->_itemname . "/";
	}
	**/
	
}