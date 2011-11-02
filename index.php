<?php
/**
 * ccenter is a form module
 * 
 * File: index.php
 * 
 * index-page of ccenter module
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		0.94
 * @author		Nobuhiro Yasutomi
 * @package		ccenter
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */

include_once "header.php";

$xoopsOption['template_main'] = 'ccenter_index.html';

include_once ICMS_ROOT_PATH . '/header.php';

global $icmsConfig, $ccenterConfig;

$clean_indexkey = $clean_start = $indexpageObj = $ccenter_indexpage_handler = '';
$indexpageArray = array();

/** Use a naming convention that indicates the source of the content of the variable */
$clean_indexkey = isset($_GET['indexkey']) ? intval($_GET['indexkey']) : 1 ;
$clean_start = isset($_GET['start']) ? intval($_GET['start']) : 0;

$clean_indexkey = isset($_GET['indexkey']) ? filter_input(INPUT_GET, 'indexkey', FILTER_SANITIZE_NUMBER_INT) : 1;
$clean_indexkey = ($clean_indexkey == 0 && isset($_POST['indexkey'])) ? filter_input(INPUT_POST, 'indexkey', FILTER_SANITIZE_NUMBER_INT) : $clean_indexkey;

$directory_name = basename( dirname( __FILE__ ) );
$script_name = getenv("SCRIPT_NAME");
$document_root = str_replace('modules/' . $directory_name . '/index.php', '', $script_name);

$ccenter_indexpage_handler = icms_getModuleHandler( 'indexpage', icms::$module -> getVar( 'dirname' ), 'ccenter' );
$criteria = icms_buildCriteria(array('indexkey' => '1'));
$indexpageObj = $ccenter_indexpage_handler->get($clean_indexkey, TRUE, FALSE, $criteria);


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////// MAIN HEADINGS ///////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

$indexpageArray = prepareIndexpageForDisplay($indexpageObj, true); // with DB overrides
//$indexpageArray['indexheading'] = icms_core_DataFilter::checkVar($data='indexheading', $type='html', $options1 = '', $options2 = '');
$indexpageArray['indexheading'] = isset ($_GET['indexheading']) ? filter_input(INPUT_GET, 'indexheading', icms_core_DataFilter::checkVar($data='indexheading', $type='html', $options1 = '', $options2 = '') )  : $indexpageArray['indexheading'];

if ( $indexpageArray['indeximage'] ) { 
	$ccenter_indexarray['indeximage'] = '<div class="ccenter_indeximage"><img src="' . $indexpageObj->get_indeximage_tag() . '" class="indeximage"></div>';
} 
if ( $indexpageArray['indexheading'] ) {
	$ccenter_indexarray['indexheading'] = '<div class="ccenter_indexheading">' . $indexpageArray['indexheading']  . '</div>';
}
if ( $indexpageArray['indexheader'] ) {
	$ccenter_indexarray['indexheader'] = '<div class="ccenter_indexheader">' . $indexpageArray['indexheader'] . '</div>';
}
if ( $indexpageArray['indexfooter'] ) {
	$ccenter_indexarray['indexfooter'] = '<div class="ccenter_indexfooter">' . $indexpageArray['indexfooter'] . '</div>';
}

$icmsTpl->assign('ccenter_indexarray', $ccenter_indexarray);

// check if the Sprockets module is installed to be sure there are tags
$sprocketsModule = icms_getModuleInfo('sprockets');
if ($sprocketsModule) {

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////// TAG LISTING ////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	// initialise
	$tagList = '';
	$have_tags = false;
	$form_list = $formids = $tag_list = $tag_ids = $tag_array = $taglink_object_array
		= $taglink_iid_list = array();
	
	$ccenterModule = icms_getModuleInfo( icms::$module -> getVar( 'dirname' ) );
	$ccenter_form_handler = icms_getModuleHandler('form', icms::$module -> getVar( 'dirname' ), 'ccenter');
	$sprockets_tag_handler = icms_getModuleHandler('tag', $sprocketsModule->getVar( 'dirname' ), 'sprockets');
	$sprockets_taglink_handler = icms_getModuleHandler('taglink', $sprocketsModule->getVar( 'dirname' ), 'sprockets');

	// get a list of tags containing online forms using a JOIN between form and taglink tables
	$query_tags = $rows = $tag_form_count = '';

	$query_tags = "SELECT DISTINCT `tid` FROM " . $ccenter_form_handler->table . ", " . $sprockets_taglink_handler->table
			. " WHERE `formid` = `iid`"
			. " AND `active` = '1'"
			. " AND `mid` = '" . $ccenterModule -> getVar( 'mid' ) . "'"
			. " AND `item` = 'form'";

	$result = icms::$xoopsDB->query($query_tags);

	if (!$result) {
		echo 'Error';
		exit;

	} else {

		$rows = $sprockets_taglink_handler->convertResultSet($result);
		foreach ($rows as $key => $row) {
			$tag_ids[] = $row->getVar('tid');
		}
	}
	
	if (count($tag_ids) > 0) {

		// convert tag_ids to string for use as search criteria
		$tag_ids = "('" . implode("','", $tag_ids) . "')";

		// retrieve relevant tags
		$criteria = new icms_db_criteria_Compo();
		$criteria->add(new icms_db_criteria_Item('tag_id', $tag_ids, 'IN'));
		$criteria->setSort('title');
		$criteria->setOrder('ASC');

		// create a list of tags with links
		$tag_array = $sprockets_tag_handler->getList($criteria);

		foreach($tag_array as $key => $tag) {
			$tag_list[] = '<a href="' . CCENTER_URL . '/index.php?tag_id=' . $key . '" title="' . $tag . '">' . $tag . '</a>';
		}
		$have_tags = true;
	}
	
	// assign the results to the template
	if ($have_tags) {
		$icmsTpl->assign('ccenter_cat_list', $tag_list);
	} else {
		$icmsTpl->assign('ccenter_cat_list', false);
	}
	if ($sprocketsmodule) {
		$icmsTpl->assign('ccenter_sprockets', $sprocketsmodule);
	} else {
		$icmsTpl->assign('ccenter_sprockets', false);
	}
	
	
	// list of forms, filtered by tags (if any), pagination and preferences
		$form_object_array = array();

		if ($clean_tag_id && $sprocketsModule) {

			//Retrieve a list of forms JOINED to taglinks by formid/tag_id/module_id/item
			$query = $rows = $tag_form_count = '';
			$linked_form_ids = array();
			$ccenterModule = icms_getModuleInfo( icms::$module -> getVar( ' dirname' ) );
			
			// first, count the number of forms for the pagination control
			$group_query = "SELECT count(*) FROM " . $ccenter_form_handler->table . ", "
					. $sprockets_taglink_handler->table
					. " WHERE `formid` = `iid`"
					. " AND `active` = '1'"
					. " AND `tid` = '" . $clean_tag_id . "'"
					. " AND `mid` = '" . $ccenterModule -> getVar( 'mid' ) . "'"
					. " AND `item` = 'form'";
			
			$result = icms::$xoopsDB->query($group_query);

			if (!$result) {
				echo 'Error';
				exit;
				
			} else {
				while ($row = icms::$xoopsDB->fetchArray($result)) {
					foreach ($row as $key => $count) {
						$form_count = $count;
					}
					
				}
			}

			// second, get the forms
			$query = "SELECT * FROM " . $ccenter_form_handler->table . ", "
					. $sprockets_taglink_handler->table
					. " WHERE `formid` = `iid`"
					. " AND `active` = '1'"
					. " AND `tid` = '" . $clean_tag_id . "'"
					. " AND `mid` = '" . $ccenterModule -> getVar( 'mid' ) . "'"
					. " AND `item` = 'form'"
					. " ORDER BY `title` ASC"
					. " LIMIT " . $clean_start . ", " . $ccenterConfig['number_of_forms_per_page'];

			$result = icms::$xoopsDB->query($query);

			if (!$result) {
				echo 'Error';
				exit;
				
			} else {

				$rows = $ccenter_form_handler->convertResultSet($result);
				foreach ($rows as $key => $row) {
					$form_object_array[$row->getVar('formid')] = $row;
				}
			}
			
		} else {

			$criteria = new icms_db_criteria_Compo();

			$criteria->setStart($clean_start);
			$criteria->setLimit($ccenterConfig['number_of_forms_per_page']);
			$criteria->setSort('title');
			$criteria->setOrder('ASC');
			$criteria->add(new icms_db_criteria_Item('active', true));
			$criteria->add(new icms_db_criteria_Item('date', time(), '<'));
			
			$form_object_array = $ccenter_form_handler->getObjects($criteria, true, true);
		}

		unset($criteria);
		
		if ($sprocketsModule && (count($form_object_array) > 0)) {

			// prepare a list of formids, this will be used to create a taglink buffer
			// that is used to create tag links for each form
			foreach ($form_object_array as $key => $value) {
				$linked_formids[] = $value->id();
			}
			
			$linked_formids = '(' . implode(',', $linked_formids) . ')';
			
			// prepare multidimensional array of tag_ids with formid (iid) as key
			$taglink_buffer = $form_tag_id_buffer = array();
			$criteria = new icms_db_criteria_Compo();
			$criteria->add(new icms_db_criteria_Item('mid', $ccenterModule -> getVar( 'mid' )));
			$criteria->add(new icms_db_criteria_Item('item', 'form'));
			$criteria->add(new icms_db_criteria_Item('iid', $linked_formids, 'IN'));
			$taglink_buffer = $sprockets_taglink_handler->getObjects($criteria, true, true);
			unset($criteria);

			foreach ($taglink_buffer as $key => $taglink) {

				if (!array_key_exists($taglink->getItemId(), $form_tag_id_buffer)) {
					$form_tag_id_buffer[$taglink->getItemId()] = array();
				}
				$form_tag_id_buffer[$taglink->getItemId()][] = $taglink->getTagId();
			}
			
			// assign each subarray of tags to the matching form, using the item id as marker
			foreach ($form_tag_id_buffer as $key => $value) {
				$form_object_array[$key]->setVar('tag', $value);
			}
		}
		
		// prepare forms for display
		if (!empty($form_object_array)) {

			foreach($form_object_array as &$form) {
				
				$tag_icons = $edit_item_link = $delete_item_link = '';

				$edit_item_link = $form->getEditItemLink(false, true, false);
				$delete_item_link = $form->getDeleteItemLink(false, true, false);

				$form = prepareFormForDisplay($form, false); // without DB overrides

				$form['editItemLink'] = $edit_item_link;
				$form['deleteItemLink'] = $delete_item_link;

				// only if sprockets installed
				if ($sprocketsModule && !empty($form['tag'])) {
					
					// get tag links and icons, if available
					$formTags = array_flip($form['tag']);
					
					foreach ($formTags as $key => &$value) {
						$value = '<a href="' . ICMS_URL . '/modules/' . icms::$module -> getVar( 'dirname' )
						. '/index.php?tag_id=' . $tag_buffer[$key]['tag_id'] . '">'
						. $tag_buffer[$key]['title'] . '</a>';
						
						if (!empty($tag_buffer[$key]['icon'])) {
							$tag_icons[] = '<a href="' . ICMS_URL . '/modules/'
							. icms::$module -> getVar( 'dirname' ) . '/index.php?tag_id='
							. $tag_buffer[$key]['tag_id'] . '">' . $tag_buffer[$key]['icon']
							. '</a>';
						}
					}
					$form['tag'] = implode(', ', $formTags);
					$form['icon'] = $tag_icons;
				}
			}
		}

		$icmsTpl->assign('ccenter_forms_array', $form_object_array);

} else {

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// FORM LIST /////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


	$clean_formid = $clean_start = $formObj = $ccenter_form_handler = '';
	$formArray = array();

	/** Use a naming convention that indicates the source of the content of the variable */
	$clean_formid = isset($_GET['formid']) ? intval($_GET['formid']) : 0 ;
	$clean_start = isset($_GET['start']) ? intval($_GET['start']) : 0;

	$ccenter_form_handler = icms_getModuleHandler('form', basename(dirname(__FILE__)), 'ccenter');
	
	$criteria = icms_buildCriteria(array('active' => '1'));
	$formObj = $ccenter_form_handler->get($clean_formid, TRUE, FALSE, $criteria);

	
	$form_object_array = array();
	
	$criteria = new icms_db_criteria_Compo();

	$criteria->setStart($clean_start);
	$criteria->setLimit($ccenterConfig['show_forms']);
	$criteria->setSort('title');
	$criteria->setOrder('ASC');
	$criteria->add(new icms_db_criteria_Item('active', true));
			
	$form_object_array = $ccenter_form_handler->getObjects($criteria, true, true);


	unset($criteria);
	
	// prepare articles for display
		if (!empty($form_object_array)) {

			foreach($form_object_array as &$form) {
				$form = prepareFormsForDisplay($form, false); // without DB overrides
				
			}
		}

		$icmsTpl->assign('ccenter_forms_array', $form_object_array);
		$icmsTpl -> assign( 'dirname', icms::$module -> getVar( 'dirname' ) );

}

	
//////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// PAGINATION ////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

	
$criteria = new icms_db_criteria_Compo();
$criteria->add(new icms_db_criteria_Item('active', true));
// adjust for tag, if present
if (!empty($clean_tag_id)) {
	$extra_arg = 'tag_id=' . $clean_tag_id;
} else {
	$extra_arg = false;
	$form_count = $ccenter_form_handler->getCount($criteria);
}
	
$pagenav = new icms_view_PageNav($form_count, $ccenterConfig['show_forms'], $clean_start, 'start', $extra_arg);
	
$icmsTpl->assign('ccenter_pagenav', $pagenav->renderNav());


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////// BREADCRUMBS ////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////


// check if the module's breadcrumb should be displayed
if ($ccenterConfig['show_breadcrumbs'] == true) {
	$icmsTpl->assign('ccenter_show_breadcrumb', $ccenterConfig['show_breadcrumbs']);
} else {
	$icmsTpl->assign('ccenter_show_breadcrumb', false);
}

$icmsTpl->assign('ccenter_module_home', ccenter_getModuleName(true, true));
$icmsTpl->assign('ccenter_cat_path', _CO_CCENTER_CATS);

include_once ICMS_ROOT_PATH . '/footer.php';