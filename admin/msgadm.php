<?php
/**
* ccenter is a form module
*
* File: /admin/msgadmin.php
*
* adminstration messages
* 
* @copyright	Copyright QM-B (Steffen Flohrer) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
*
* ----------------------------------------------------------------------------------------------------------
* 				ccenter 
* @since		0.94
* @author		Nobuhiro Yasutomi
* @package		ccenter
* ----------------------------------------------------------------------------------------------------------
* 				ccenter
* @since		1.00
* @author		QM-B
* @version		$Id$
* @package		ccenter
* @version		$Id$
*/

include 'admin_header.php';

$clean_msgid = $clean_op = $valid_op = '';
$ccenter_message_handler = icms_getModuleHandler('message', basename(dirname(dirname(__FILE__))), "ccenter");

$op = isset($_GET['op']) ? filter_input(INPUT_GET, 'op') : '';
if (isset($_POST['op'])) $op = filter_input(INPUT_POST, 'op');
$msgid = isset($_REQUEST['msgid']) ? (int) $_REQUEST['msgid'] : 0;

$valid_op = array ('mod','changedField','addform','del','view','visible', 'changeWeight', '');

if (isset($_GET['op'])) $clean_op = htmlentities($_GET['op']);
if (isset($_POST['op'])) $clean_op = htmlentities($_POST['op']);

$clean_msgid = isset($_GET['msgid']) ? (int) $_GET['msgid'] : 0 ;
$clean_tag_id = isset($_GET['tag_id']) ? intval($_GET['tag_id']) : 0 ;

$op = isset($_REQUEST['op']) ? icms_core_DataFilter::stripSlashesGPC($_REQUEST['op']) : '';

if (isset($_POST['store'])) {
    $msgid = (int) $_POST['msgid'];
    $touid = (int) $_POST['touid'];
    $stat = icms_core_DataFilter::stripSlashesGPC($_POST['status']);
    $res = icms::$xoopsDB->query("SELECT * FROM ".CCMES." WHERE msgid=".$msgid);
    $back = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:"msgadm.php";
    if ($res && icms::$xoopsDB->getRowsNum($res)==1) {
	$data = icms::$xoopsDB->fetchArray($res);
	$sets = array();
	$log = '';
	if ($data['status'] != $stat) {
	    $sets[] = 'status='.icms::$xoopsDB->quoteString($stat);
	    $log .= sprintf(_CC_LOG_STATUS, $msg_status[$data['status']], $msg_status[$stat]);
	}
	if ($data['touid'] != $touid) {
	    $sets[] = 'touid='.$touid;
	    if ($log) $log .= "\n";
	    $log .= sprintf(_CC_LOG_TOUSER, ccUname($data['touid']), ccUname($touid));
	} else {
	    $touid = 0;		// not changed
	}
	if (count($sets)) {
	    $sets[] = 'mtime='.time();
	    $res = icms::$xoopsDB->query("UPDATE ".CCMES." SET ".join(",", $sets)." WHERE msgid=".$msgid);
	    if ($res && $touid) { // switch person in charge
		$notification_handler = icms::handler( 'icms_data_notification' );
		$notification_handler->subscribe('message', $msgid, 'comment', null, null, $touid);
		$notification_handler->subscribe('message', $msgid, 'status', null, null, $touid);
	    }
	    cc_log_message($data['fidref'], $log, $msgid);
	    redirect_header($back, 1, _AM_MSG_UPDATED);
	    exit;
	}
    }
    redirect_header($back, 3, _AM_MSG_UPDATE_FAIL);
} elseif (!empty($op)) {
    $uid = (int) icms::$user->getVar('uid');
    foreach ($_POST['ids'] as $msgid) {
	change_message_status($msgid, 0, $op);
    }
    $back = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:"msgadm.php";
    redirect_header($back, 1, _AM_MSG_UPDATED);
}




if ( in_array( $clean_op, $valid_op, true ) ) {
  switch ($clean_op) {
	case "changedField":
  		icms_cp_header();
  		edititem($clean_msgid);
  		break;
  	case "del":
        $controller = new icms_ipf_Controller($ccenter_message_handler);
  		$controller->handleObjectDeletion();
  		break;
  	case "view":
  		$messageObj = $ccenter_message_handler->get($clean_msgid);
  		icms_cp_header();
  		$messageObj->displaySingleObject();
  		break;
  	default:
  		icms_cp_header();

  		$ccenterModule->displayAdminMenu(1, _AM_CCENTER_MESSAGES);
		
		icms_loadLanguageFile('ccenter', 'main');
		
		$criteria = '';
		
		// display a tag select filter (if the Sprockets module is installed)
		$sprocketsModule = icms_getModuleInfo('sprockets');
		
		if ($sprocketsModule) {
			$tag_select_box = '';
			$taglink_array = $tagged_item_list = array();
			$sprockets_tag_handler = icms_getModuleHandler('tag', $sprocketsModule->dirname(),
				'sprockets');
			$sprockets_taglink_handler = icms_getModuleHandler('taglink', 
					$sprocketsModule->dirname(), 'sprockets');
			$ccenterModule = icms_getModuleInfo( icms::$module -> getVar( 'dirname' ) );
			
			$tag_select_box = $sprockets_tag_handler->getTagSelectBox('index.php', $clean_tag_id,
				_AM_CCENTER_ITEM_ALL_ITEMS);
			if (!empty($tag_select_box)) {
				echo '<h3>' . _AM_CCENTER_FILTER_FORM_BY_TAG . '</h3>';
				echo $tag_select_box;
			}
			
			if ($clean_tag_id) {
				
				// get a list of item IDs belonging to this tag
				$criteria = new icms_db_criteria_Compo();
				$criteria->add(new icms_db_criteria_Item('tid', $clean_tag_id));
				$criteria->add(new icms_db_criteria_Item('mid', $ccenterModule->mid()));
				$criteria->add(new icms_db_criteria_Item('message', 'message'));
				$taglink_array = $sprockets_taglink_handler->getObjects($criteria);
				foreach ($taglink_array as $taglink) {
					$tagged_item_list[] = $taglink->getVar('iid');
				}
				$tagged_item_list = "('" . implode("','", $tagged_item_list) . "')";
				
				// use the list to filter the table
				$criteria = new icms_db_criteria_Compo();
				$criteria->add(new icms_db_criteria_Item('formid', $tagged_item_list, 'IN'));
			}
		}
		
		if (empty($criteria)) {
			$criteria = null;
		}

  		$objectTable = new icms_ipf_view_Table( $ccenter_message_handler, $criteria );
		$objectTable -> addColumn( new icms_ipf_view_Column( 'msgid' ) );
		$objectTable -> addColumn( new icms_ipf_view_Column( 'ctime' ) );
		$objectTable -> addColumn( new icms_ipf_view_Column( 'tag' ) );
		$objectTable -> addColumn( new icms_ipf_view_Column( 'fidref' ) );
  		$objectTable -> addColumn( new icms_ipf_view_Column( 'title' ) );
		$objectTable -> addColumn( new icms_ipf_view_Column( 'status' ) );
		
  		$objectTable -> addActionButton( 'changedField', 'msgadm.php?op=changedField', _AM_CCENTER_MESSAGE_MOD );
		$objectTable -> addActionButton( 'view', 'msgadm.php?op=view', _AM_CCENTER_MESSAGE_MOD );
  		
		$objectTable -> addCustomAction( 'getViewItemLink' );
		
		$icmsAdminTpl -> assign( 'ccenter_message_table', $objectTable->fetch() );
  		$icmsAdminTpl -> display( 'db:ccenter_admin_message.html' );
  		break;
  }
  icms_cp_footer();
}

function select_widget($name, $sel, $def) {
    $input = "<select name='$name' id='$name'>\n";
    foreach ($sel as $id=>$lab) {
	$ck = $def==$id?' selected="selected"':'';
	$input .= "<option value='$id'$ck>$lab</option>\n";
    }
    $input .= "</select>";
    return $input;
}

function msg_detail($msgid) {
    global $msg_status;
    $users = icms::$xoopsDB->prefix('users');
    $res = icms::$xoopsDB->query("SELECT m.*,title,priuid,u.uname,cgroup,f.uname cfrom FROM ".CCMES." m LEFT JOIN ".FORMS." ON fidref=formid LEFT JOIN $users u ON touid=u.uid LEFT JOIN $users f ON m.uid=f.uid WHERE msgid=$msgid");
    $data = icms::$xoopsDB->fetchArray($res);
    $data['stat'] = $msg_status[$data['status']];
    $data['cdate'] = formatTimestamp($data['ctime'], 'm');
    $data['mdate'] = myTimestamp($data['mtime'], 'm', _AM_TIME_UNIT);
    $labs = array('title'=>_AM_FORM_TITLE, 'uid'=>_AM_MSG_FROM,
		  'stat'=>_AM_MSG_STATUS, 'cdate'=>_AM_MSG_CTIME, 
		  'mdate'=>_AM_MSG_MTIME, 'uname'=>_AM_MSG_CHARGE);
    $touid = false;
    echo "<h2>"._AM_MSG_ADMIN."</h2>\n";
    echo "<form method='post'>\n";
    echo "<input type='hidden' name='msgid' value='$msgid'/>\n";
    echo "<table class='ccinfo' cellspacing='1' width='100%'>\n";
    $n = 0;
    $upage = "../message.php?id=$msgid";
    foreach ($labs as $k=>$lab) {
	$bg = ($n++%2)?'even':'odd';
	$val = htmlspecialchars($data[$k]);
	switch($k) {
	case 'title':
	    $val = "<a href='$upage'>$val</a>\n";
	    break;
	case 'uid':
	    if ($val>0) {
		$val = "<a href='".ICMS_URL."/userinfo.php?uid=$val'>".htmlspecialchars($data['cfrom'])."</a>";
	    } else {
		if ($data['email']) {
		    $val = htmlspecialchars($data['email']);
		    $val = "<a href='mailto:$val'>$val</a>";
		} else {
		    $val = _CC_USER_NONE;
		}
	    }
	    break;
	case 'stat':
	    $val = select_widget('status', $msg_status, $data['status']);
	    break;
	case 'uname':
	    $touid = new MyFormSelect(_AM_FORM_PRIM_CONTACT, 'touid', $data['touid']);
	    $gid = ($data['priuid']<0)?-$data['priuid']:$data['cgroup'];
	    $touid->addOption('0', _AM_FORM_PRIM_NONE);
	    $touid->addOptionUsers($gid);
	    $val = $touid->render()."\n<input type='hidden' name='cgroup' id='cgroup' value='$gid'/>\n";
	    break;
	default:
	}
	echo "<tr><th>$lab</th><td>$val</td></tr>\n";
    }
    echo "<tr><th></th><td><input type='submit' name='store' value='"._AM_SUBMIT."'/></td></tr>\n";
    echo "</table>\n";
    echo "</from><br/>\n";
    if (!empty($touid)) {
	echo $touid->renderSupportJS();
    }
    echo "<table class='outer' cellspacing='1'>\n";
    $n = 0;
    foreach (unserialize_text($data['body']) as $k=>$v) {
	$bg = $n++%2?'even':'odd';
	$k = htmlspecialchars($k);
	$v = nl2br(htmlspecialchars($v));
	echo "<tr><td class='head'>$k</td><td class='$bg'>$v</td></tr>\n";
    }
    echo "</table>\n";

    $res = icms::$xoopsDB->query("SELECT l.*,uname FROM ".CCLOG." l LEFT JOIN ".icms::$xoopsDB->prefix('users')." ON euid=uid WHERE midref=$msgid ORDER BY logid DESC");
    $log = array();
    echo '<a id="logging"></a><h3>'._AM_LOGGING."</h3>\n";

    if (icms::$xoopsDB->getRowsNum($res)) {
	$anon = $GLOBALS['icmsConfig']['anonymous'];
	echo "<table>\n";
	$reg = array('/\(comid=(\d+)\)/');
	$rep = array('(<a href="'.$upage.'#comment\1">comid=\1</a>)');
	while ($data = icms::$xoopsDB->fetchArray($res)) {
	    $uname = htmlspecialchars(empty($data['uname'])?$anon:$data['uname']);
	    $comment = preg_replace($reg, $rep,
				    icms_core_DataFilter::checkVar($data['comment'], 'text', 'output'));
	    echo "<tr><td nowrap>".formatTimestamp($data['ltime'])."</td><td nowrap>".
		"[$uname]</td><td width='100%'>$comment</td></tr>\n";
	}
	echo "</table>\n";
    } else {
	echo _AM_NODATA;
    }
}

function ccUname($uid) {
    if ($uid<=0) return _CC_USER_NONE;
    return icms_member_user_Object::getUnameFromId($uid);
}