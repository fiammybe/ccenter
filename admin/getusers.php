<?php
// query group users
// $Id: getusers.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../../include/cp_header.php";
include_once "mypagenav.php";

$start = isset($_GET["start"]) ? intval($_GET["start"]) : 0;
$group = isset($_GET["gid"]) ? intval($_GET["gid"]) : 0;
$max = _CC_MAX_USERS;

$total = cc_group_users($group, $max, $start, true);

foreach (cc_group_users($group, $max, $start) as $uid => $uname) {
    echo "$uid," . htmlspecialchars($uname) . "\n";
}

echo "<!---->\n";
$nav = new MyPageNav($total, $max, $start);
echo $nav->renderNav();
?>
