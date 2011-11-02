<?php
/**
* ccenter is a form module
*
* File: /admin/getusers.php
*
* query group users
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
* @version		$Id: getusers.php,v 1.2 2007-08-03 05:25:37 nobu Exp $
*/

include '../../../include/cp_header.php';
include_once '../class/mypagenav.php';

$start = isset($_GET['start'])?intval($_GET['start']):0;
$group = isset($_GET['gid'])?intval($_GET['gid']):0;
$max = _CC_MAX_USERS;

$total = cc_group_users($group, $max, $start, true);

foreach (cc_group_users($group, $max, $start) as $uid=>$uname) {
    echo "$uid,".htmlspecialchars($uname)."\n";
}

echo "<!---->\n";
$nav = new MyPageNav($total, $max, $start);
echo $nav->renderNav();