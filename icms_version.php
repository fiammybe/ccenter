<?php
/**
 * ccenter is a form module
 * 
 * File: icms_version.php
 * 
 * module informations
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////// GENERAL INFORMATION ////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion = array(
					'name'						=> _MI_CCENTER_NAME,
					'version'					=> 1.0,
					'description'				=> _MI_CCENTER_DESC,
					'author'					=> "Version developer: <a href='http://community.impresscms.org/userinfo.php?uid=1314' target='_blank'>QM-B</a> &nbsp;&nbsp;<span style='font-size: smaller;'>( qm-b [at] hotmail [dot] de )</span>';",
					'credits'					=> "Functionality is based on the legacy ccenter module (Version 0.93), but since 1.0 this is a rewrite to keep on comatibility with ImpressCMS.",
					'help'						=> "help.html",
					'license'					=> "GNU General Public License (GPL)",
					'official'					=> 0,
					'dirname'					=> basename( dirname( __FILE__ ) ),

					/**  Images information  */
					'iconsmall'					=> "images/ccenter_iconsmall.png",
					'iconbig'					=> "images/ccenter_iconbig.png",
					'image'						=> "images/ccenter_icon_big.png", /* for backward compatibility */

					/**  Development information */
					'status_version'			=> "1.0",
					'status'					=> "beta",
					'date'						=> "Unreleased",
					'author_word'				=> "",

					/** Contributors */
					'developer_website_url' 	=> "http://www.network-altenpflege.eu",
					'developer_website_name' 	=> "Network Altenpflege",
					'developer_email' 			=> "qm-b@hotmail.de");

$modversion['people']['developers'][] = "Nobuhiro Yasutomi";
$modversion['people']['developers'][] = "Version developer: <a href='http://community.impresscms.org/userinfo.php?uid=1314' target='_blank'>QM-B</a> &nbsp;&nbsp;<span style='font-size: smaller;'>( qm-b [at] hotmail [dot] de )</span>';";
$modversion['people']['testers'][] = '&middot; <a href="http://community.impresscms.org/userinfo.php?uid=10" target="_blank">sato-san</a>';


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////// WARNINGS /////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


// If beta
$modversion['warning'] 		= _CO_ICMS_WARNING_BETA;
// If RC
//$modversion['warning'] 		= _CO_ICMS_WARNING_BETA;


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////// ADMINISTRATIVE INFORMATION ////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['hasAdmin'] 	= 1;
$modversion['adminindex']	= 'admin/index.php';
$modversion['adminmenu'] 	= 'admin/menu.php';
	

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////// SUPPORT //////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['support_site_url'] = 'http://community.impresscms.org/modules/newbb/viewforum.php?forum=9';
$modversion['support_site_name']= 'ImpressCMS Community Forum';


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////// DATABASE /////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['object_items'][1] = 'form';
$modversion['object_items'][2] = 'message';
$modversion['object_items'][3] = 'log';
$modversion['object_items'][4] = 'indexpage';

$modversion['tables'] = icms_getTablesArray( $modversion['dirname'], $modversion['object_items'] );


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////// INSTALLATION / UPGRADE //////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


// OnUpdate - upgrade DATABASE 
$modversion['onUpdate'] = 'include/onupdate.inc.php';

// OnInstall - Insert Sample Form, create folders
$modversion['onInstall'] = 'include/onupdate.inc.php';


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// MENU FRONTEND ///////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['hasMain'] = 1;
global $icmsUser;
if (!empty(icms::$user)) {
    $modversion['sub'][] = array(
									'name'	=> _MI_CCENTER_MYCONTACT,
									'url'	=> "list.php"
								);
    $modversion['sub'][] = array(
									'name'	=> _MI_CCENTER_MYCHARGE,
									'url'	=> "charge.php"
								);
    $modversion['sub'][] = array(
									'name'	=> _MI_CCENTER_STAFFDESK,
									'url'	=> "reception.php"
								);
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// TEMPLATES /////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['templates'][1] = array(
										'file'			=> 'ccenter_index.html',
										'description'	=> _MI_CCENTER_INDEX_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_header.html',
										'description'	=> _MI_CCENTER_HEADER_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_form.html',
										'description'	=> _MI_CCENTER_FORM_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_admin_form.html',
										'description'	=> _MI_CCENTER_ADMIN_FORM_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_admin_message.html',
										'description'	=> _MI_CCENTER_ADMIN_MESSAGE_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_custom.html',
										'description'	=> _MI_CCENTER_CUST_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_confirm.html',
										'description'	=> _MI_CCENTER_CONF_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_list.html',
										'description'	=> _MI_CCENTER_LIST_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_charge.html',
										'description'	=> _MI_CCENTER_CHARGE_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_message.html',
										'description'	=> _MI_CCENTER_MSGS_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_reception.html',
										'description'	=> _MI_CCENTER_RECEPT_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_form_widgets.html',
										'description'	=> _MI_CCENTER_WIDGET_TPL
								);
$modversion['templates'][] = array(
										'file'			=> 'ccenter_requirements.html',
										'description'	=> 'Module requirement warnings.'
								);


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////// BLOCKS //////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$i=0;

// Receipt block
$i++;
$modversion['blocks'][$i]['file']			= 'ccenter_receipt.php';
$modversion['blocks'][$i]['name']			= _MI_CCENTER_BLOCK_RECEIPT;
$modversion['blocks'][$i]['description']	= _MI_CCENTER_BLOCK_RECEIPT_DSC;
$modversion['blocks'][$i]['show_func']		= 'b_ccenter_receipt_show';
$modversion['blocks'][$i]['edit_func']		= 'b_ccenter_receipt_edit';
$modversion['blocks'][$i]['options']		= '5|asc|-|a|b';
$modversion['blocks'][$i]['template']		= 'ccenter_block_receipt.html';
$modversion['blocks'][$i]['can_clone']		= true ;

// Form block
$i++;
$modversion['blocks'][$i]['file']			= 'ccenter_block_form.php';
$modversion['blocks'][$i]['name']			= _MI_CCENTER_BLOCK_FORM;
$modversion['blocks'][$i]['description']	= _MI_CCENTER_BLOCK_FORM_DSC;
$modversion['blocks'][$i]['show_func']		= 'b_ccenter_form_show';
$modversion['blocks'][$i]['edit_func']		= 'b_ccenter_form_edit';
$modversion['blocks'][$i]['options']		= '0';
$modversion['blocks'][$i]['template']		= '';
$modversion['blocks'][$i]['can_clone']		= true ;


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////// SEARCH //////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


/** Search information */
$modversion['hasSearch'] = 1;
$modversion['search'] ['file'] = 'include/search.inc.php';
$modversion['search'] ['func'] = 'ccenter_search';


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////// COMMENTS /////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


// Comments
$modversion['hasComments'] = 1;
$modversion['comments']['pageName'] = 'message.php';
$modversion['comments']['itemName'] = 'msgid';

// Comment callback functions
$modversion['comments']['callbackFile'] = 'include/comment_functions.php';
$modversion['comments']['callback']['approve'] = 'ccenter_com_approve';
$modversion['comments']['callback']['update'] = 'ccenter_com_update';


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// CONFIGURATION ///////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


//$modversion['hasconfig'] = 1;
global $icmsConfig;

$modversion['config'][1] = array(
								'name' 			=> 'use_cats',
								'title' 		=> '_MI_CCENTER_USE_CATS',
								'description' 	=> '_MI_CCENTER_USE_CATS_DESC',
								'formtype' 		=> 'yesno',
								'valuetype' 	=> 'int',
								'default' 		=>  0
							);
$modversion['config'][] = array(
								'name' 			=> 'show_breadcrumbs',
								'title' 		=> '_MI_CCENTER_SHOW_BREADCRUMBS',
								'description' 	=> '_MI_CCENTER_SHOW_BREADCRUMBS_DESC',
								'formtype' 		=> 'yesno',
								'valuetype' 	=> 'int',
								'default' 		=>  1
							);
$modversion['config'][] = array(
								'name'			=> 'show_forms',
								'title'			=> '_MI_CCENTER_SHOW_FORMS',
								'description' 	=> '_MI_CCENTER_SHOW_FORMS_DESC',
								'formtype' 		=> 'textbox',
								'valuetype'		=> 'text',
								'default' 		=> 15
							);
$modversion['config'][] = array(
								'name'			=> 'max_lists',
								'title'			=> '_MI_CCENTER_LISTS',
								'description' 	=> '_MI_CCENTER_LISTS_DESC',
								'formtype' 		=> 'select',
								'valuetype'		=> 'int',
								'default' 		=> 25,
								'options' 		=> array(5=>5,10=>10,25=>25,50=>50,100=>100,200=>200,500=>500,1000=>1000)
							);
$modversion['config'][] = array(
								'name'			=> 'def_attrs',
								'title'			=> '_MI_CCENTER_DEF_ATTRS',
								'description'	=> '_MI_CCENTER_DEF_ATTRS_DESC',
								'formtype'		=> 'textsarea',
								'valuetype'		=> 'text',
								'default'		=> "size=60\nrows=5\ncols=50\nnotify_with_email=0"
							);
$modversion['config'][] = array(
								'name'			=> 'status_combo',
								'title'			=> '_MI_CCENTER_STATUS_COMBO',
								'description'	=> '_MI_CCENTER_STATUS_COMBO_DESC',
								'formtype'		=> 'textsarea',
								'valuetype'		=> 'text',
								'default'		=> _MI_CCENTER_STATUS_COMBO_DEF
							);


	
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// NOTIFICATIONS ///////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


$modversion['hasNotification'] = 1;
$modversion['notification']['lookup_file'] = 'include/notification.inc.php';
$modversion['notification']['lookup_func'] = 'ccenter_notify_iteminfo';

$modversion['notification']['category'][1] = array(
													'name'				=> 'global',
													'title'				=> _MI_CCENTER_GLOBAL_NOTIFY,
													'description'		=> '',
													'subscribe_from'	=> array('reception.php')
												);
$modversion['notification']['category'][] = array(
													'name'				=> 'form',
													'title'				=> _MI_CCENTER_FORM_NOTIFY,
													'item_name'			=> 'form',
													'description'		=> '',
													'subscribe_from'	=> array('reception.php')
												);
$modversion['notification']['category'][] = array(
													'name'				=> 'message',
													'title'				=> _MI_CCENTER_MESSAGE_NOTIFY,
													'description'		=> '',
													'item_name'			=> 'id',
													'subscribe_from'	=> array('message.php')
												);
$modversion['notification']['event'][1] = array(
													'name'				=> 'new',
													'category'			=> 'global',
													'admin_only'		=> 1,
													'title'				=> _MI_CCENTER_NEWPOST_NOTIFY,
													'caption'			=> _MI_CCENTER_NEWPOST_NOTIFY_CAP,
													'description'		=> '',
													'mail_template'		=> 'notify',
													'mail_subject'		=> _MI_CCENTER_NEWPOST_SUBJECT
												);
$modversion['notification']['event'][] = array(
													'name'				=> 'new',
													'category'			=> 'form',
													'title'				=> _MI_CCENTER_NEWPOST_NOTIFY,
													'caption'			=> _MI_CCENTER_NEWPOST_NOTIFY_CAP,
													'description'		=> '',
													'mail_template'		=> 'notify',
													'mail_subject'		=> _MI_CCENTER_NEWPOST_SUBJECT
											);
$modversion['notification']['event'][] = array(
													'name'				=> 'status',
													'category'			=> 'message',
													'title'				=> _MI_CCENTER_STATUS_NOTIFY,
													'caption'			=> _MI_CCENTER_STATUS_NOTIFY_CAP,
													'description'		=> '',
													'mail_template'		=> 'status_notify',
													'mail_subject'		=> _MI_CCENTER_STATUS_SUBJECT
											);