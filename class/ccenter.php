<?php
/**
* ccenter is a form module
*
* File: /class/ccenter.php
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

define('_CC_MAX_USERS', 100);	// users/page

class MyPageNav extends icms_view_PageNav {

    function MyPageNav($total, $items, $current, $name="start", $target='uid') {
	$this->total = (int) ($total);
		$this->perpage = (int) ($items);
		$this->current = (int) ($current);
		$this->target = (int) ($target);
    }

    function renderNav($offset = 4)
    {
        $ret = '';
	$name = $this->target;
	$fmt = '<a href="javascript:setSelectUID(\''.$name.'\',%d);">%s</a> ';
        if ( $this->total <= $this->perpage ) {
            return $ret;
        }
        $total_pages = ceil($this->total / $this->perpage);
        if ( $total_pages > 1 ) {
            $prev = $this->current - $this->perpage;
            if ( $prev >= 0 ) {
                $ret .= sprintf($fmt, $prev, '<u>&laquo;</u>');
            }
            $counter = 1;
            $current_page = intval(floor(($this->current + $this->perpage) / $this->perpage));
            while ( $counter <= $total_pages ) {
                if ( $counter == $current_page ) {
                    $ret .= '<b>('.$counter.')</b> ';
                } elseif ( ($counter > $current_page-$offset && $counter < $current_page + $offset ) || $counter == 1 || $counter == $total_pages ) {
                    if ( $counter == $total_pages && $current_page < $total_pages - $offset ) {
                        $ret .= '... ';
                    }
                    $ret .= sprintf($fmt, ($counter - 1) * $this->perpage, $counter);
                    if ( $counter == 1 && $current_page > 1 + $offset ) {
                        $ret .= '... ';
                    }
                }
                $counter++;
            }
            $next = $this->current + $this->perpage;
            if ( $this->total > $next ) {
                $ret .= sprintf($fmt, $next, '<u>&raquo;</u>');
            }
        }
        return $ret;
    }
}

function cc_group_users($group=0, $max=_CC_MAX_USERS, $start=0, $count=false) {

    $cond = empty($group)?"":" AND groupid=$group";
    if (!empty($_REQUEST['s'])) $cond .= ' AND uname LIKE '.icms::$xoopsDB->quoteString($_REQUEST['s'].'%');
    $sql0 = "FROM ".icms::$xoopsDB->prefix("groups_users_link")." l, ".icms::$xoopsDB->prefix("users")." u WHERE l.uid=u.uid".$cond;
    if ($count) {
	$res = icms::$xoopsDB->query("SELECT DISTINCT u.uid $sql0");
	$total = icms::$xoopsDB->getRowsNum($res);
	return $total;
    }
    $res = icms::$xoopsDB->query("SELECT u.uid, uname $sql0 GROUP BY u.uid ORDER BY uname", $max, $start);
    $options = array();
    while (list($uid, $uname) = icms::$xoopsDB->fetchRow($res)) {
	$options[$uid] = htmlspecialchars($uname);
    }
    return $options;
}


class CcenterBreadcrumbs extends icms_view_Breadcrumb {
    var $moddir;
    var $pairs;

    function CcenterBreadcrumbs() {
	global $icmsTpl;
	$this->moddir = ICMS_URL."/modules/".icms::$module->getVar('dirname').'/';
	$this->pairs = array(array('name'=>icms::$module->getVar('name'), 'url'=>$this->moddir));
    }

    function set($name, $url) {
	if (preg_match('/^\w+:\/\//', $url)) $url = $this->moddir.$url;
	$this->pairs[] = array('name'=>htmlspecialchars(strip_tags($name), ENT_QUOTES), 'url'=>$url);
    }

    function get() {
	$ret = $this->pairs;
	$keys = array_keys($ret);
	$ret[array_pop($keys)]['url'] = '';
	return $ret;
    }

    function assign() {
	global $icmsTpl;
	return $icmsTpl->assign('icms_breadcrumbs', $this->get());
	
	function render($fetchOnly = TRUE) {
		global $icmsTpl;
		$this->_tpl = new icms_view_Tpl();
		$this->_tpl->assign('icms_breadcrumb_items', $this->items);

		if ($fetchOnly) {
			return $this->_tpl->fetch('db:system_breadcrumb.html');
		} else {
			$this->_tpl->display('db:system_breadcrumb.html');
		}
	}
    }

}

// adhoc class - not for reuse
class ListCtrl {
    var $name;
    var $vars;
    var $combo;

    function ListCtrl($name, $init=array(), $combo='') {
	if (empty($combo)) {
	
	    $combo = icms::$module->config['status_combo'];
	}
	$this->name = $name;
	$this->combo = unserialize_text($combo);
	if (!isset($_SESSION['listctrl'])) $_SESSION['listctrl'] = array();
	if (!isset($_SESSION['listctrl'][$name]) ||
	    (isset($_GET['reset'])&&$_GET['reset']=='yes')) {
	    if (!isset($init['stat'])) {
		list($init['stat']) = array_values($this->combo);
	    }
	    $_SESSION['listctrl'][$name] = $init;
	}
	$this->vars =& $_SESSION['listctrl'][$name];
	$this->updateVars($_REQUEST);
    }

    function getVar($name) {
	return isset($this->vars[$name])?$this->vars[$name]:"";
    }

    function setVar($name, $val) { $this->vars[$name]=$val; }

    function getLabels($labels) {
	$result = array();
	$orders = $this->getVar('orders');
	foreach ($labels as $k => $v) {
	    $lab = array('text'=>$v, 'name'=>$k);
	    if (isset($this->vars[$k])) { // with ctrl
		$n = array_search($k, $orders);
		if (is_int($n)) {
		    $val = strtolower($this->getVar($k));
		    $lab['value'] = $val;
		    $lab['next'] = $val=='desc'?'asc':'desc';
		    $lab['extra'] = " class='ccord$n'";
		} else {
		    $lab['value'] = 'none';
		    $lab['next'] = 'asc';
		}
	    }
	    $result[] = $lab;
	}
	return $result;
    }

    function updateVars($args) {
	$changes = array();
	foreach (array_keys($this->vars) as $k) {
	    if (isset($args[$k])) {
		$val = trim($args[$k]);
		if (empty($val)) continue;
		switch ($k) {
		case 'stat':
		    $val = preg_replace('/[^a-dx\- ]/', '', trim($val));
		    break;
		default:
		    $val = strtolower($val)=='asc'?'ASC':'DESC';
		    $orders = $this->getVar('orders');
		    if ($k != $orders[0]) {
			$this->setVar('orders', array($k, $orders[0]));
		    }
		}
		$this->setVar($k, $val);
		$changes[$k] = $val;
	    }
	}
	return $changes;
    }

    function sqlcondition($fname='status') {
	$stat = $this->getVar('stat');
	if (preg_match('/\s+/', $stat)) {
	    return "$fname IN ('".join("','", preg_split('/\s+/',$stat))."')";
	}
	return "$fname=".icms::$xoopsDB->quoteString($stat);
    }

    function sqlorder() {
	$order = array();
	foreach ($this->getVar('orders') as $name) {
	    $order[] = $name." ".$this->getVar($name);
	}
	if ($order) return " ORDER BY ".join(',', $order);
	return "";
    }

    function renderStat() {
	$ctrl = "<select name='stat' onChange='submit();'>\n";
	$stat = $this->getVar('stat');
	foreach ($this->combo as $k => $v) {
	    $ck = $v == $stat?" selected='selected'":"";
	    $ctrl .= "<option value='$v'$ck>$k</option>\n";
	}
	$ctrl .= "</select>";
	return $ctrl;
    }
}

class MyFormSelect extends icms_form_elements_Select {

    function MyFormSelect($caption,$name,$value=null,$size=1,$multiple=false){
	$this->setCaption($caption);
		$this->setName($name);
		$this->_multiple = $multiple;
		$this->_size = (int) ($size);
		if (isset($value)) {
			$this->setValue($value);
		}
	$this->pagenav = '';
	$this->slab = _SEARCH;
    }

    function addOptionUsers($gid=0) {
	list($cuid) = $this->getValue();
	$max = _CC_MAX_USERS;
	$start = isset($_REQUEST['start'])?intval($_REQUEST['start']):0;
	$users = cc_group_users($gid, $max, $start);
	$opts = $this->getOptions();

	// force insert current if none
	if ($cuid && !isset($users[$cuid]) && !isset($opts[$cuid])) {
	    $users[$cuid]=icms_member_user_Object::getUnameFromId($cuid);
	}
	$this->addOptionArray($users);
	$this->setPageNav($gid);
    }

    function setPageNav($gid) {
	$start = isset($_REQUEST['start'])?intval($_REQUEST['start']):0;
	$max = _CC_MAX_USERS;
	$total = cc_group_users($gid, $max, $start, true);
	$nav = new MyPageNav($total, $max, $start, 'start', $this->getName());
	$this->pagenav = $nav->renderNav();
    }

    function setSearchLabel($str){
	$this->slab = $str;
    }

    function render(){
	$name = $this->getName();
	$s = htmlspecialchars(isset($_REQUEST[$name.'_s'])?$_REQUEST[$name.'_s']:"");
	$slab  = htmlspecialchars($this->slab);
	return "<table cellpadding='0'>\n<tr valign='top'>".
	    "<td align='center'>".parent::render().
	    "<div id='{$name}_page'>".$this->pagenav."</div></td>".
	    "<td width='100%'> &nbsp; <input size='8' name='{$name}_s' id='{$name}_s' value='$s' onChange='setSelectUID(\"$name\",0);'/><input type='submit' value='$slab' onClick='setSelectUID(\"$name\", 0); return false;'/></td></tr>\n</table>";
    }

    function renderSupportJS( $withtags = true ) {
	$name = $this->getName();
        $js = "";
        if ( $withtags ) {
            $js .= "\n<!-- Start UID Selection JavaScript //-->\n<script type='text/javascript'>\n<!--//\n";
        }
	$js .= '// XMLHttpRequest general handler
function createXmlHttp(){
    if (window.XMLHttpRequest) {             // Mozilla, Firefox, Safari, IE7
        return new XMLHttpRequest();
    } else if (window.ActiveXObject) {       // IE5, IE6
        try {
            return new ActiveXObject("Msxml2.XMLHTTP");    // MSXML3
        } catch(e) {
            return new ActiveXObject("Microsoft.XMLHTTP"); // until MSXML2
        }
    } else {
        return null;
    }
}
';
	$js .= '
function setSelectUID(name, start) {
    var xmlhttp = createXmlHttp();
    var search = xoopsGetElementById(name+"_s");
    var gid = xoopsGetElementById("cgroup");
    if (xmlhttp == null) return;	// XMLHttpRequest not support
    url = "getusers.php?gid=" + gid.value + "&start="+start;
    if (search) url += "&s="+search.value;
    xmlhttp.open("GET", url, false);
    xmlhttp.send(null);
    var obj = xoopsGetElementById(name);
    var opts = obj.options;
    var defs = obj.value;
    if (xmlhttp.status == 200) {
	len = 0;
	for (i=0; i<opts.length; i++) {
	    if (opts[i].value == 0) {
		len = ++i;
		break;
	    }
	}
	opts.length = len;
	F = xmlhttp.responseText.split("<!---->\n");
	lines = F[0].split("\n");
	for (i in lines) {
	    el = lines[i].split(",", 2);
	    if (el.length < 2) continue;
	    p = opts.length++;
	    opts[p].value = el[0];
	    opts[p].text = el[1];
	}
	obj.value = defs;
	page = xoopsGetElementById(name+"_page");
	page.innerHTML = F[1].replace(/\'uid\'/g, "\'"+name+"\'");
    }
}
';
        if ( $withtags ) {
            $js .= "//--></script>\n<!-- End UID Selection JavaScript //-->\n";
        }
	return $js;
    }
}