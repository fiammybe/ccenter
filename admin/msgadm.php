<?php
// administration of the messages
include "../../../include/cp_header.php";
include "../functions.php";

$handler = icms_getModuleHandler("message", "ccenter");
$is_post = $_SERVER["REQUEST_METHOD"] === "POST";
$op = (string) preg_replace("/[^a-z_]/", "", (string) ($_REQUEST["op"] ?? ""));
$back = isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "msgadm.php";

// ---- changes: POST requests with a valid security token only ----
if ($is_post && (isset($_POST["store"]) || isset($_POST["bulk"]) || ($op === "del" && !empty($_POST["confirm"])))) {
    if (!cc_check_token()) {
        redirect_header($back, 3, cc_token_error());
        exit();
    }
    if (isset($_POST["store"])) {
        save_message($handler, $back);
    } elseif (isset($_POST["bulk"])) {
        bulk_action($handler, $back);
    } else {
        $message = $handler->find((int) ($_POST["msgid"] ?? 0));
        $ok = $message ? $handler->delete($message, true) : false;
        redirect_header("msgadm.php", $ok ? 1 : 3, $ok ? _AM_MSG_UPDATED : _AM_MSG_UPDATE_FAIL);
        exit();
    }
}

icms_cp_header();
cc_display_admin_menu(1);

$msgid = (int) ($_GET["msgid"] ?? 0);
if ($op === "del" && $msgid) {
    confirm_delete($handler, $msgid);
} elseif ($msgid) {
    msg_detail($msgid);
} else {
    msg_list($handler);
}

icms_cp_footer();

/** Change status and person in charge of one message. */
function save_message($handler, $back)
{
    global $msg_status;
    $msgid = (int) ($_POST["msgid"] ?? 0);
    $touid = (int) ($_POST["touid"] ?? 0);
    $stat = (string) icms_core_DataFilter::stripSlashesGPC($_POST["status"] ?? "");
    $message = $handler->find($msgid);
    // the status must be a real one, the person in charge a real user (or nobody)
    if (
        $message &&
        mod_ccenter_Status::isValid($stat) &&
        ($touid === 0 || icms::handler("icms_member")->getUser($touid))
    ) {
        $data = $message->toRow();
        $sets = [];
        $log = "";
        if ($data["status"] != $stat) {
            $sets["status"] = $stat;
            $log .= sprintf(_CC_LOG_STATUS, $msg_status[$data["status"]], $msg_status[$stat]);
        }
        if ($data["touid"] != $touid) {
            $sets["touid"] = $touid;
            $log .= ($log ? "\n" : "") . sprintf(_CC_LOG_TOUSER, ccUname($data["touid"]), ccUname($touid));
        } else {
            $touid = 0; // not changed
        }
        if ($sets) {
            $sets["mtime"] = time();
            $res = $handler->updateFields($msgid, $sets);
            if ($res && $touid) {
                // switch person in charge
                $notification_handler = icms::handler("icms_data_notification");
                $notification_handler->subscribe("message", $msgid, "comment", null, null, $touid);
                $notification_handler->subscribe("message", $msgid, "status", null, null, $touid);
            }
            cc_log_message($data["fidref"], $log, $msgid);
            redirect_header($back, 1, _AM_MSG_UPDATED);
            exit();
        }
    }
    redirect_header($back, 3, _AM_MSG_UPDATE_FAIL);
    exit();
}

/** "With the selected": change the status of, or delete, the ticked messages. */
function bulk_action($handler, $back)
{
    $action = (string) ($_POST["bulk_action"] ?? "");
    $ids = mod_ccenter_AdminTable::selectedIds();
    if ($action === "purge") {
        foreach ($ids as $id) {
            $handler->purge($id);
        }
    } elseif (mod_ccenter_Status::isValid($action)) {
        foreach ($ids as $id) {
            change_message_status($id, 0, $action); // administrators may change any message
        }
    }
    redirect_header($back, 1, _AM_MSG_UPDATED);
    exit();
}

function confirm_delete($handler, $msgid)
{
    $message = $handler->find($msgid);
    if (!$message) {
        echo _AM_NODATA;
        return;
    }
    icms_core_Message::confirm(
        ["op" => "del", "msgid" => $msgid, "confirm" => 1],
        "msgadm.php",
        (defined("_AM_CC_DELETE_MSG") ? _AM_CC_DELETE_MSG : "Delete this message for good?") . " (ID:$msgid)",
        _DELETE,
    );
}

function msg_list($handler)
{
    global $msg_status;

    // status filter: the combinations of the preferences ("All", "Open"...), remembered in the session
    $listctrl = mod_ccenter_ListState::forRequest("msgadm", ["orders" => ["mtime"]]);
    $criteria = $handler->listCriteria($listctrl->statuses());

    // links from the form list: ?formid=N&stat=...
    if (isset($_GET["formid"]) && !isset($_GET["filtersel2"])) {
        $_GET["filtersel"] = "fidref";
        $_GET["filtersel2"] = (string) (int) $_GET["formid"];
    }
    mod_ccenter_AdminTable::guardRequest("message", ["msgid", "mtime", "status", "fidref", "uid", "touid"], ["fidref"]);
    if (!isset($_GET["limitsel"]) && !isset($_POST["limitsel"])) {
        $_GET["limitsel"] = (string) (int) icms::$module->config["max_lists"];
    }

    $table = new icms_ipf_view_Table($handler, $criteria, ["delete"]);
    $table->setCustomTemplate(mod_ccenter_AdminTable::TEMPLATE);
    $table->setDefaultSort("mtime");
    $table->setDefaultOrder("DESC");
    $table->addWithSelectedActions([]);   // adds the column with the check boxes
    $table->addColumn(new icms_ipf_view_Column("mtime", "left", 140, "getDate", false, _AM_FORM_MTIME));
    $table->addColumn(new icms_ipf_view_Column("status", "left", 90, "getStatusText", false, _AM_MSG_STATUS));
    $table->addColumn(new icms_ipf_view_Column("fidref", "left", false, "getSummary", false, _AM_FORM_TITLE));
    $table->addColumn(new icms_ipf_view_Column("uid", "left", 140, "getSender", false, _AM_MSG_FROM));
    $table->addColumn(new icms_ipf_view_Column("touid", "left", 120, "getCharge", false, _AM_MSG_CHARGE));
    $table->addColumn(new icms_ipf_view_Column("comms", "right", 60, "getCommentCount", false, _AM_MSG_COMMS, false));
    $table->addCustomAction("getDetailLink");
    $table->addFilter("fidref", "getFormChoices");
    $table->addQuickSearch(["body", "email"], _SEARCH);

    $actions = [];
    foreach ($msg_status as $code => $label) {
        $actions[$code] = _AM_MSG_CHANGESTATUS . ": " . $label;
    }
    $actions["purge"] = _DELETE;
    $table->addFooter(mod_ccenter_AdminTable::bulkControls($actions, defined("_CO_ICMS_WITH_SELECTED") ? _CO_ICMS_WITH_SELECTED : "With selected:"));

    echo "<h2>" . _AM_MSG_ADMIN . "</h2>\n";
    echo "<form method='get' action='msgadm.php' style='margin-bottom: 8px;'>" . _CC_STATUS . " " . $listctrl->renderStat() .
        "<noscript> <input type='submit' value='" . _AM_SUBMIT_VIEW . "'/></noscript></form>\n";
    echo $table->fetch();
}

function msg_detail($msgid)
{
    global $msg_status;
    $handler = icms_getModuleHandler("message", "ccenter");
    $data = $handler->getRowWithForm($msgid);
    if (!$data) {
        echo _AM_NODATA;
        return;
    }
    $form = icms_getModuleHandler("form", "ccenter")->find($data["fidref"]);
    $page = "../message.php?id=$msgid";
    echo "<h2>" . _AM_MSG_ADMIN . "</h2>\n";

    // who can be in charge: the members of the group the messages of this form go to
    $group = $form && $form->getVar("priuid", "n") < 0 ? -$form->getVar("priuid", "n") : (int) $data["cgroup"];
    $choices = [0 => _AM_FORM_PRIM_NONE];
    if ($group) {
        foreach (icms::handler("icms_member")->getUsersByGroup($group, true, 200) as $user) {
            $choices[(int) $user->getVar("uid")] = $user->getVar("uname");
        }
    }
    if ($data["touid"] && !isset($choices[$data["touid"]])) {
        $choices[(int) $data["touid"]] = ccUname($data["touid"]);
    }
    $statusSelect = new icms_form_elements_Select("", "status", $data["status"]);
    $statusSelect->addOptionArray($msg_status);
    $chargeSelect = new icms_form_elements_Select("", "touid", $data["touid"]);
    $chargeSelect->addOptionArray($choices);

    $sender = $data["uid"] > 0
        ? icms_member_user_Handler::getUserLink($data["uid"])
        : ($data["email"] ? "<a href='mailto:" . htmlspecialchars($data["email"]) . "'>" . htmlspecialchars($data["email"]) . "</a>" : htmlspecialchars(_CC_USER_NONE));
    $rows = [
        _AM_FORM_TITLE => "<a href='" . htmlspecialchars($page) . "'>" . htmlspecialchars($data["title"]) . "</a>",
        _AM_MSG_FROM => $sender,
        _AM_MSG_STATUS => $statusSelect->render(),
        _AM_MSG_CTIME => htmlspecialchars(formatTimestamp($data["ctime"], "m")),
        _AM_MSG_MTIME => htmlspecialchars(myTimestamp($data["mtime"], "m", _AM_TIME_UNIT)),
        _AM_MSG_CHARGE => $chargeSelect->render(),
    ];
    echo "<form method='post' action='msgadm.php'>\n" . icms::$security->getTokenHTML();
    echo "<input type='hidden' name='msgid' value='" . (int) $msgid . "'/>\n";
    echo "<table class='ccinfo' cellspacing='1' width='100%'>\n";
    foreach ($rows as $label => $value) {
        echo "<tr><th>" . htmlspecialchars($label) . "</th><td>$value</td></tr>\n";
    }
    echo "<tr><th></th><td><input type='submit' name='store' value='" . htmlspecialchars(_AM_SUBMIT) . "'/></td></tr>\n";
    echo "</table>\n</form><br/>\n";

    echo "<table class='outer' cellspacing='1'>\n";
    $n = 0;
    foreach (mod_ccenter_MessageBody::fromStored($data["body"])->toLegacyArray() as $k => $v) {
        $bg = $n++ % 2 ? "even" : "odd";
        echo "<tr><td class='head'>" . htmlspecialchars($k) . "</td><td class='$bg'>" . nl2br(htmlspecialchars($v)) . "</td></tr>\n";
    }
    echo "</table>\n";

    echo '<a id="logging"></a><h3>' . _AM_LOGGING . "</h3>\n";
    $lines = icms_getModuleHandler("log", "ccenter")->forMessage($msgid);
    if ($lines) {
        $anon = $GLOBALS["icmsConfig"]["anonymous"];
        $link = '(<a href="' . htmlspecialchars($page) . '#comment\1">comid=\1</a>)';
        echo "<table>\n";
        foreach ($lines as $line) {
            $uid = (int) $line->getVar("euid", "n");
            $uname = htmlspecialchars($uid ? icms_member_user_Object::getUnameFromId($uid) : $anon);
            $comment = preg_replace(
                "/\(comid=(\d+)\)/",
                $link,
                icms_core_DataFilter::checkVar((string) $line->getVar("comment", "n"), "text", "output"),
            );
            echo "<tr><td nowrap>" . htmlspecialchars(formatTimestamp($line->getVar("ltime", "n"))) .
                "</td><td nowrap>[$uname]</td><td width='100%'>$comment</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo _AM_NODATA;
    }
}

function ccUname($uid)
{
    if ($uid <= 0) {
        return _CC_USER_NONE;
    }
    return icms_member_user_Object::getUnameFromId($uid);
}
