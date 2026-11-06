<?php
// contact to member
// $Id: reception.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL . "/user.php", 3, _NOPERM);
}

$id = (int) ($_GET["form"] ?? 0);
$isadmin = icms::$user->isAdmin(icms::$module->getVar("mid"));
$form_handler = icms_getModuleHandler("form", "ccenter");
$rows = $form_handler->getReceptionRows((int) icms::$user->getVar("uid"), icms::$user->getGroups(), $isadmin, $id);

if (!$rows) {
    redirect_header("index.php", 3, _NOPERM);
    exit();
}

$breadcrumbs = mod_ccenter_Breadcrumbs::forModule();
$breadcrumbs->add(_MD_CCENTER_RECEPTION, "reception.php");

if (count($rows) > 1) {
    include ICMS_ROOT_PATH . "/header.php";
    $xoopsOption["template_main"] = "ccenter_reception.html";
    $breadcrumbs->assign($xoopsTpl);
    $forms = [];
    $member_handler = icms::handler('icms_member');
    $groups = $member_handler->getGroupList(
        new icms_db_criteria_Item("groupid", ICMS_GROUP_ANONYMOUS, "!="),
    );
    foreach ($rows as $form) {
        $form["title"] = htmlspecialchars($form["title"]);
        $form["ltime"] = $form["ltime"] ? formatTimestamp($form["ltime"]) : "";
        if ($form["priuid"]) {
            if ($form["priuid"] < 0) {
                $form["contact"] = "[" . ($groups[-$form["priuid"]] ?? "") . "]";
            } else {
                $form["contact"] = icms_member_user_Handler::getUserLink($form["priuid"]);
            }
        } elseif ($form["cgroup"]) {
            $form["contact"] = "[" . ($groups[$form["cgroup"]] ?? "") . "]";
        } else {
            $form["contact"] = _MD_CONTACT_NOTYET;
        }
        $forms[] = $form;
    }
    $xoopsTpl->assign("forms", $forms);
    include ICMS_ROOT_PATH . "/footer.php";
    exit();
}

// check access permition
$form = $rows[0];
if (!cc_check_perm($form)) {
    redirect_header("index.php", 3, _NOPERM);
    exit();
}

include ICMS_ROOT_PATH . "/header.php";

$xoopsOption["template_main"] = "ccenter_reception.html";
$xoopsTpl->assign("forms", []); // single form: the template shows its message list instead

$id = $form["formid"];
$items = get_form_attribute($form["defs"]);
$breadcrumbs->add($form["title"], "reception.php?formid=$id");
$breadcrumbs->assign($xoopsTpl);

$start = max(0, (int) ($_GET["start"] ?? 0));
if ($form["custom"]) {
    $reg = [
        "/\\[desc\\](.*)\\[\/desc\\]/sU",
        "/<form[^>]*>(.*)<\\/form[^>]*>/sU",
        "/{CHECK_SCRIPT}/",
    ];
    $rep = ['\\1', "", ""];
    $form["action"] = "";
    $form["description"] = preg_replace(
        $reg,
        $rep,
        custom_template($form, $items),
    );
} else {
    $form["description"] = icms_core_DataFilter::checkVar(
        $form["description"],
        "text",
        "output",
    );
}
$form["mdate"] = formatTimestamp($form["mtime"]);
foreach ($items as $k => $item) {
    if (empty($item["label"])) {
        unset($items[$k]);
    }
}
$max_cols = 3;
$form["items"] = array_slice($items, 0, $max_cols);
$n = $mpos = -1;
$mlab = "";
foreach ($form["items"] as $item) {
    $n++;
    if ($item["type"] == "mail") {
        $mpos = $n;
        $mlab = $item["name"];
        break;
    }
}

$message_handler = icms_getModuleHandler("message", "ccenter");
$touid = ($form["priuid"] < 0 && !$isadmin) ? (int) icms::$user->getVar("uid") : 0;
$count = $message_handler->countForForm($id, $touid);
$max = (int) icms::$module->config["max_lists"];
$args = preg_replace("/start=\\d+/", "", $_SERVER["QUERY_STRING"]);
$nav = new icms_view_PageNav($count, $max, $start, "start", $args);
$xoopsTpl->assign("pagenav", $count > $max ? $nav->renderNav() : "");

if ($touid) {
    $form["description"] = str_replace("{TO_NAME}", icms::$user->getVar("name"), $form["description"]);
}

$xoopsTpl->assign("form", $form);
$xoopsTpl->assign("export_range", $export_range);

$mlist = [];
foreach ($message_handler->listForForm($id, $touid, $max, $start) as $data) {
    $values = mod_ccenter_MessageBody::fromStored($data["body"])->toLegacyArray();
    if ($mpos >= 0 && !isset($values[$mlab])) {
        array_splice($values, $mpos, 0, [$data["email"]]);
    }
    $data["values"] = array_slice($values, 0, $max_cols);
    $data["uname"] = icms::$user->getUnameFromId($data["uid"]);
    $data["mdate"] = formatTimestamp($data["mtime"]);
    $data["cdate"] = formatTimestamp($data["ctime"]);
    $data["stat"] = $msg_status[$data["status"]] ?? "";
    $mlist[] = $data;
}

$xoopsTpl->assign("mlist", $mlist);

include ICMS_ROOT_PATH . "/footer.php";
