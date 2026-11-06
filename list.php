<?php
// the messages a visitor sent
include "../../mainfile.php";
include "functions.php";

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL . "/user.php", 3, _NOPERM);
}

$xoopsOption["template_main"] = "ccenter_list.html";
include ICMS_ROOT_PATH . "/header.php";

// administrators may look at the messages of another user
$uid = (int) icms::$user->getVar("uid");
if (icms::$user->isAdmin(icms::$module->getVar("mid")) && isset($_GET["uid"])) {
    $uid = (int) $_GET["uid"];
}

$labels = [
    "mtime" => _MD_POSTDATE,
    "formid" => _MD_CONTACT_FORM,
    "touid" => _MD_CONTACT_FROM,
    "status" => _CC_STATUS,
];
$state = mod_ccenter_ListState::forRequest("mylist", [
    "mtime" => "ASC",
    "formid" => "ASC",
    "touid" => "ASC",
    "status" => "ASC",
    "orders" => ["mtime"],
]);
$formId = (int) ($_GET["form"] ?? 0);
$handler = icms_getModuleHandler("message", "ccenter");

$total = $handler->countListFor("uid", $uid, $state->statuses(), $formId);
$max = (int) icms::$module->config["max_lists"];
$start = max(0, (int) ($_GET["start"] ?? 0));

$nav = new icms_view_PageNav($total, $max, $start, "start");
$xoopsTpl->assign("pagenav", $total > $max ? $nav->renderNav() : "");
$xoopsTpl->assign("statctrl", $state->renderStat());
$xoopsTpl->assign("total", $total);
$xoopsTpl->assign("xoops_pagetitle", htmlspecialchars(icms::$module->getVar("name") . " - " . _MD_CCENTER_QUERY));
$xoopsTpl->assign("labels", $state->labels($labels));

$list = [];
foreach ($handler->listFor("uid", $uid, $state->statuses(), $formId, $state->orders(), $max, $start) as $row) {
    $list[] = mod_ccenter_ListEntry::build($row);
}
$xoopsTpl->assign("list", $list);

include ICMS_ROOT_PATH . "/footer.php";
