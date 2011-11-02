<?php
/**
* ccenter is a form module
*
* File: /class/form.php
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

class CcenterForm extends icms_ipf_seo_Object {

	public function __construct( &$handler ) {
		icms_ipf_object::__construct( $handler );
		
		$this -> quickInitVar( 'formid', XOBJ_DTYPE_INT, true );
		$this -> quickInitVar( 'mtime', XOBJ_DTYPE_MTIME, true );
		$this -> quickInitVar( 'title', XOBJ_DTYPE_TXTBOX );
		$this -> initCommonVar( 'short_url' );
		$this -> quickInitVar( 'description', XOBJ_DTYPE_TXTAREA);
				$this -> setControl( 'description', 'dhtmltextarea');
		$this -> quickInitVar( 'custom', XOBJ_DTYPE_INT, true, false, false, 0 );
				$this -> setControl( 'custom', array ( 'name' => 'select', 'itemHandler' => 'form', 'method' => 'getCustom', 'module' => 'ccenter' ) );
		$this -> quickInitVar( 'defs', XOBJ_DTYPE_TXTBOX );
				$this -> setControl( 'defs', 'textarea' );
		$this -> quickInitVar( 'priuid', XOBJ_DTYPE_INT, true, false, false, 0 );
				$this -> setControl( 'priuid','user_multi' );
		$this -> quickInitVar( 'cgroup', XOBJ_DTYPE_INT, true );
				$this -> setControl( 'cgroup','group_multi' );
		$this -> quickInitVar( 'store', XOBJ_DTYPE_INT, true, false, false, 1 );
				$this -> setControl( 'store', array ( 'name' => 'select', 'itemHandler' => 'form', 'method' => 'getStore', 'module' => 'ccenter' ) );
		$this -> quickInitVar( 'active', XOBJ_DTYPE_INT, true, false, false, 1 );
				$this -> setControl( 'active', 'yesno' );
		$this -> quickInitVar( 'grpperm', XOBJ_DTYPE_TXTBOX, true );
				$this -> setControl( 'grpperm', array ( 'name' => 'select_multi', 'itemHandler' => 'form', 'method' => 'getGroups', 'module' => 'ccenter' ) );
		$this -> initCommonVar( 'weight' );
		$this -> quickInitVar( 'optvars', XOBJ_DTYPE_TXTBOX );
		
		/** Preparing for later use of IPF instead of build_form()
		$this->initVar('optvars', XOBJ_DTYPE_FORM_SECTION, FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
			$this -> quickInitVar( 'notify_with_email', XOBJ_DTYPE_INT, false );
					$this -> setControl( 'notify_with_email', 'yesno' );
			$this -> quickInitVar( 'redirect', XOBJ_DTYPE_TXTBOX );
					$this -> setControl( 'redirect', 'textarea' ); //@TODO remove this later for clean install
			$this -> quickInitVar( 'reply_comment', XOBJ_DTYPE_TXTBOX );
					$this -> setControl( 'reply_comment', 'textarea' );
			$this -> quickInitVar( 'reply_use_comtpl', XOBJ_DTYPE_INT );
					$this -> setControl( 'reply_use_comtpl', 'yesno' );
			$this -> quickInitVar( 'others', XOBJ_DTYPE_TXTBOX );
					$this -> setControl( 'others', 'textarea' );
		$this->initVar('close_section_optvars', XOBJ_DTYPE_FORM_SECTION_CLOSE, '', FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
		**/
		
		$this -> initNonPersistableVar( 'tag', XOBJ_DTYPE_INT, 'tag', false, false, false, true );		
		
		// Only display the tag field if Sprockets is installed - original from Madfishs News-Module
		$sprocketsModule = icms_getModuleInfo('sprockets');
		if ($sprocketsModule) {
			$this -> setControl( 'tag', array ( 'name' => 'select_multi', 'itemHandler' => 'tag', 'method' => 'getTags', 'module' => 'sprockets' ) );
		} else {
			$this -> hideFieldFromForm( 'tag' );
			$this -> hideFieldFromSingleView ( 'tag' );
		}
		
		// Hide static fields from Form
		$this -> hideFieldFromForm('formid' );
		$this -> hideFieldFromSingleView ( 'formid' );
		
		$this -> doShowFieldOnForm( 'optvars' );
		
		parent::initiateSEO();
	}
	
	public function openFormSection($section_name, $value = FALSE) {
		$this->initVar('description', XOBJ_DTYPE_FORM_SECTION, $value, FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
																	//$maxlength = null, $options = '',$multilingual = false, $form_caption = '', $form_dsc = '', $sortby = false, $persistent = true, $displayOnForm = true
		$this->initVar('optvars', XOBJ_DTYPE_FORM_SECTION, $value, FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
	}
	
	public function closeFormSection($section_name) {
		$this->initVar('close_section_description', XOBJ_DTYPE_FORM_SECTION_CLOSE, '', FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
		$this->initVar('close_section_optvars', XOBJ_DTYPE_FORM_SECTION_CLOSE, '', FALSE, NULL, '', FALSE, '', '', FALSE, FALSE, TRUE);
	}
	
	function getVar( $key, $format = 's' ) {
		if ($format == 's' && in_array( $key, array ( 'active' ) ) ) {
			return call_user_func(array ( $this, $key ) );
		}
		return parent :: getVar( $key, $format );
	}

	public function toArrayWithoutOverrides() {
		$vars = $this -> getVars();
		$do_not_override = array(0 => 'tag');
		$ret = array();
		foreach ($vars as $key => $var) {
			if (in_array($key, $do_not_override)) {
				$value = $this->getVar($key, 'e');
			} else {
				$value = $this->getVar($key);
			}
			$ret[$key] = $value;
		}
		if ($this->handler->identifierName != "") {
			$controller = new icms_ipf_Controller($this->handler);
			$ret['itemLink'] = $controller->getItemLink($this);
			$ret['itemUrl'] = $controller->getItemLink($this, true);
			$ret['editItemLink'] = $controller->getEditItemLink($this, false, true);
			$ret['deleteItemLink'] = $controller->getDeleteItemLink($this, false, true);
		}
		return $ret;
	}

	public function active() {
		$active = $this->getVar('active', 'e');
		if ($active == false) {
			return '<a href="' . CCENTER_ADMIN_URL . 'index.php?formid=' . $this->getVar('formid') . '&amp;op=visible">
				<img src="' . CCENTER_IMAGES_URL . 'hidden.png" alt="Offline" /></a>';
		} else {
			return '<a href="' . CCENTER_ADMIN_URL . 'index.php?formid=' . $this->getVar('formid') . '&amp;op=visible">
				<img src="' . CCENTER_IMAGES_URL . 'visible.png" alt="Online" /></a>';
		}
	}

	public function getWeightControl() {
		$control = new icms_form_elements_Text( '', 'weight[]', 5, 7,$this -> getVar( 'weight', 'e' ) );
		$control->setExtra( 'style="text-align:center;"' );
		return $control->render();
	}
	
	//original from Madfishs News-Module
	public function loadTags() {
		$ret = '';
		$sprocketsModule = icms_getModuleInfo( 'sprockets' );
		if ($sprocketsModule) {
			$sprockets_taglink_handler = icms_getModuleHandler('taglink', $sprocketsModule -> getVar( 'dirname' ), 'sprockets' );
			$ret = $sprockets_taglink_handler -> getTagsForObject( $this -> id(), $this -> handler);
			$this -> setVar( 'tag', $ret );
		}
	}
	
	function getItemLink($onlyUrl = false) {
		$seo = $this -> handler -> makelink( $this );
		$url = $this -> handler -> _moduleUrl . 'form.php?formid=' . $this -> getVar( 'formid' ) . '&amp;form=' . $seo;
		if ($onlyUrl) return $url;
		return '<a href="' . $url . '" title="">' . $this -> getVar( 'title' ) . '</a>';
	}
	
	public function short_url() {
		return $this->getVar('short_url');
	}
	
	public function getDeleteButtonForDisplay() {
		static $controller = null;
		if ($this->getVar('system') == 1) return;
		if ($controller === null) $controller = new icms_ipf_Controller($this->handler);
		return $controller->getDeleteItemLink($this, false, true, false);
	}
	
	// @TODO work on SEO
	function getPreviewItemLink() {
		$seo = $this->handler->makelink($this);
		$ret = '<a href="' . CCENTER_URL . 'form.php?formid=' . $this->getVar('formid', 'e') . '&amp;form=' . $seo . '" title="' . _AM_CCENTER_PREVIEW . '" target="_blank">' . $this->getVar('title') . '</a>';
		return $ret;
	}

	function getCloneItemLink() {
		$ret = '<a href="' . CCENTER_ADMIN_URL . 'index.php?op=clone&amp;formid=' . $this->getVar('formid', 'e') . '" title="' . _AM_CCENTER_FORM_CLONE . '"><img src="' . ICMS_IMAGES_SET_URL . '/actions/editcopy.png" /></a>';

		return $ret;
	}

	function getViewItemLink() {
		$ret = '<a href="' . CCENTER_URL . 'index.php?op=view&amp;formid=' . $this->getVar('formid', 'e') . '" title="' . _AM_CCENTER_FORM_VIEW . '"><img src="' . ICMS_IMAGES_SET_URL . '/actions/viewmag.png" /></a>';
		return $ret;
	}
	
	function getEditItemLink() {
			$ret = '<a href="' . CCENTER_ADMIN_URL . 'index.php?op=changedField&amp;formid=' . $this->getVar('formid', 'e') . '" title="' . _AM_CCENTER_FORM_EDIT . '"><img src="' . ICMS_IMAGES_SET_URL . '/actions/edit.png" /></a>';
		return $ret;
	
	
	}
	
}


class CcenterFormHandler extends icms_ipf_Handler {

	public function __construct( &$db ) {
		parent::__construct( $db, 'form', 'formid', 'title', '', 'ccenter' );
		icms_loadLanguageFile(basename(dirname(dirname(__FILE__))), 'common');
	}
	
	function getGroups($criteria = null) {
		$member_handler =& icms::handler('icms_member');
		$groups = $member_handler->getGroupList($criteria, true);
		//$ret = array();
		//foreach (array_keys($groups) as $i) {
		//	$ret[$i] = $groups[$i] ['name'];
		//}
		//$ret = explode('|', trim($ret, '|'));
		return $groups;
	}
	
	function getOptvarsSection() {
		$formid = (int)($_GET['formid']);
		$res = icms::$xoopsDB->query('SELECT * FROM '. FORMS );
		$data = icms::$xoopsDB->fetchArray($res);
		$objects = get_form_attribute(_CC_OPTDEFS, _AM_OPTVARS_LABEL, 'optvars');
		$vars = unserialize_vars($data['optvars']);
		$others = "";
		foreach ($objects as $k=>$object) {
			$name = $object['name'];
			if (isset($vars[$name])) {
				$objects[$k]['default'] = $vars[$name];
				unset($vars[$name]);
			}
		}
		$val = "";
		foreach ($vars as $i=>$v) {
			$val .= "$i=$v\n";
		}
		$objects[$k]['default'] = $val;
		//assign_form_widgets($objects);
		$varform="";
		foreach ($objects as $object) {
			//$br = ($object['type'] =="textarea")?"<br/>":"";
			$varform = $this->setControl( $object['name'], 'textarea' ); //"<div>".$object['label'].": $br".$object['input']."</div>";
		}
	
		//$ck = empty( $data[ 'optvars' ] ) ? "" : " checked='checked'";
		//$optvars = new icms_form_elements_Label(_AM_FORM_OPTIONS, "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onChange='toggle(this);'$ck/> "._AM_OPTVARS_SHOW."\");</script><div id='optvars'>$varform</div>");
		return $varform;
		
	}
	
	function post_optvars() {
		$items = get_form_attribute(_CC_OPTDEFS, '', 'optvar');
		$errors = assign_post_values($items);
		$vars = array();
		foreach ($items as $item) {
			$fname = $item['name'];
			if ($fname == "others") {
				foreach (unserialize_vars($item['value']) as $k=>$v) {
					$vars[$k] = $v;
				}
			} else {
				if ($item['value']) $vars[$fname] = $item['value'];
			}
		}
		return serialize_text($vars);
	}
	
	function getDefsSection() {
	
		$defs_tray = new icms_form_elements_Tray(_AM_FORM_DEFS);
		$defs_tray->addElement(new icms_form_elements_TextArea('', 'defs', $data['defs'], 10, 60));
		$defs_tray->addElement(new icms_form_elements_Label('', 
			'<div id="itemhelper" style="display:none; white-space:nowrap;">'
				. _AM_FORM_LAB . 
				'<input name="xelab" size="10">
				<input type="checkbox" name="xereq" title="' . _AM_FORM_REQ . '">
				<select name="xetype">
					<option value="text">text</option>
					<option value="checkbox">checkbox</option>
					<option value="radio">radio</option>
					<option value="textarea">textarea</option>
					<option value="select">select</option>
					<option value="const">const</option>
					<option value="hidden">hidden</option>
					<option value="mail">mail</option>
					<option value="file">file</option>
				</select>
				<input name="xeopt" size="30" />
				<button onClick="return addFieldItem();">' . _AM_FORM_ADD . '</button>
			</div>'));
	
	}
	
	function getDescriptions() {
		
		//$desc= new 
		
		
		//$formid = intval($_POST['formid']);
		//$res = icms::$xoopsDB->query('SELECT * FROM '.FORMS." WHERE formid=$formid");
		//$data = icms::$xoopsDB->fetchArray($res);
		//$desc = array();
		//$description = $data['description'];
		//$desc = new icms_form_elements_Dhtmltextarea('', 'description', $description, 10, 60);
		
		//$button = new icms_form_elements_Button('', 'ins_tpl', _AM_INS_TEMPLATE);
		//	$button->setExtra("onClick=\"myform.description.value += defsToString();\"");
		//$i++;
		//$desc[$i] = $button;
		
		//$error = check_form_tags($data['custom'], $data['defs'], $description);
		//	if ($error) {
		//		$i++;
		//		$desc[$i] = new icms_form_elements_Label('', "<div style='color:red;'>$error</div>");
		//	}
		//$form-> addElement($desc);
		//return $descript;
		
		
		/*	
		$desc = new icms_form_elements_Tray(_AM_FORM_DESCRIPTION, "<br/>");
		$description = $data['description'];
		$editor = get_attr_value(null, 'use_fckeditor');
		if ($editor) {
			$desc->addElement(new icms_form_elements_TextArea('', 'description', $description, 10, 60));
		} else {
			$desc->addElement(new icms_form_elements_Dhtmltextarea('', 'description', $description, 10, 60));
		}
		if (!$editor) {
			$button = new icms_form_elements_Button('', 'ins_tpl', _AM_INS_TEMPLATE);
			$button->setExtra("onClick=\"myform.description.value += defsToString();\"");
			$desc->addElement($button);
		}
		$error = check_form_tags($data['custom'], $data['defs'], $description);
		if ($error) $desc->addElement(new icms_form_elements_Label('', "<div style='color:red;'>$error</div>"));
		//$form-> addElement($desc);
		return $desc;
		**/
	}
	
	function getStore() {
		$store = unserialize_vars(_CC_STORE_MODE, 1);
		return $store;
	}
	
	function getCustom() {
		//$custom->setExtra(' onChange="myform.ins_tpl.disabled = (this.value==0||this.value==4);"');
		$custom_type = unserialize_vars( _AM_CUSTOM_DESCRIPTION );
		return $custom_type;
	}
	
	public function changeVisible($formid) {
		$visibility = '';
		$formObj = $this->get($formid);
		if ($formObj->getVar('active', 'e') == true) {
			$formObj->setVar('active', 0);
			$visibility = 0;
		} else {
			$formObj->setVar('active', 1);
			$visibility = 1;
		}
		$this->insert($formObj, true);
		return $visibility;
	}
	
	public function addPermission($perm_name, $caption, $description = false) {
		$this -> addPermission( 'view', _AM_CCENTER_FORM_PERM_READ, _AM_CCENTER_FORM_PERM_READ_DSC );
	}

	public function active_filter() {
		return array(0 => 'Offline', 1 => 'Online');
	}
	
	public function updateComments($formid, $total_num) {
		$formObj = $this->get($formObj);
		if ($formObj && !$formObj->isNew()) {
			$formObj->setVar('form_comments', $total_num);
			$this->insert($formObj, true);
		}
	}
	
	public function makeLink($form) {
		$count = $this->getCount(new icms_db_criteria_Item("short_url", $form->getVar("short_url")));
		if ($count > 1) {
			return $form->getVar('formid');
		} else {
			$seo = str_replace(" ", "-", $form->getVar('short_url'));
			return $seo;
		}
	}

	protected function afterSave(& $obj) {
		$sprockets_taglink_handler = '';
		// storing tags
		$sprocketsModule = icms_getModuleInfo('sprockets');
		if ($sprocketsModule) {
			$sprockets_taglink_handler = icms_getModuleHandler('taglink', 
					$sprocketsModule->dirname(), 'sprockets');
			$sprockets_taglink_handler->storeTagsForObject($obj);
		}
		return true;
	}

	protected function afterDelete(& $obj) {
		$notification_handler = icms::handler( 'icms_data_notification' );
		$module_handler = icms::handler('icms_module');
		$module = $module_handler->getByDirname( icms::$module -> getVar( 'dirname' ) );
		$module_id = icms::$module->getVar('mid');
		$category = 'global';
		$formid = $obj->id();
		// delete global notifications
		$notification_handler->unsubscribeByItem($module_id, $category, $formid);
		// delete taglinks
		$sprocketsModule = icms_getModuleInfo('sprockets');
		if ($sprocketsModule) {
			$sprockets_taglink_handler = icms_getModuleHandler('taglink',
					$sprocketsModule->dirname(), 'sprockets');
			$sprockets_taglink_handler->deleteAllForObject($obj);
		}
		return true;
	}
}