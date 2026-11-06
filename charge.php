<?php
// the messages a person is in charge of
include "../../mainfile.php";
include "functions.php";

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL . "/user.php", 3, _NOPERM);
}

$xoopsOption["template_main"] = "ccenter_charge.html";
include ICMS_ROOT_PATH . "/header.php";

// administrators may look at the messages of another person in charge
$uid = (int) icms::$user->getVar("uid");
if (icms::$user->isAdmin(icms::$module->getVar("mid")) && isset($_GET["touid"])) {
    $uid = (int) $_GET["touid"];
}

$labels = [
    "mtime" => _MD_MODDATE,
    "formid" => _MD_CONTACT_FORM,
    "uid" => _MD_CONTACT_FROM,
    "status" => _CC_STATUS,
];
$state = mod_ccenter_ListState::forRequest("charge", [
    "mtime" => "ASC",
    "formid" => "ASC",
    "uid" => "ASC",
    "status" => "ASC",
    "stat" => "- a",
    "orders" => ["status", "mtime"],
]);
$formId = (int) ($_GET["form"] ?? 0);
$handler = icms_getModuleHandler("message", "ccenter");

$total = $handler->countListFor("touid", $uid, $state->statuses(), $formId);
$max = (int) icms::$module->config["max_lists"];
$start = max(0, (int) ($_GET["start"] ?? 0));

$nav = new icms_view_PageNav($total, $max, $start, "start");
$xoopsTpl->assign("pagenav", $total > $max ? $nav->renderNav() : "");
$xoopsTpl->assign("statctrl", $state->renderStat());
$xoopsTpl->assign("total", $total);
$xoopsTpl->assign("xoops_pagetitle", htmlspecialchars(icms::$module->getVar("name") . " - " . _MD_CCENTER_CHARGE));
$xoopsTpl->assign("labels", $state->labels($labels));

$qlist = [];
foreach ($handler->listFor("touid", $uid, $state->statuses(), $formId, $state->orders(), $max, $start) as $row) {
    $qlist[] = mod_ccenter_ListEntry::build($row);
}
$xoopsTpl->assign("qlist", $qlist);

include ICMS_ROOT_PATH . "/footer.php";
