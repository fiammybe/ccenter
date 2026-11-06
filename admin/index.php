<?php
// administration of the contact forms
include "../../../include/cp_header.php";
include "../functions.php";

$handler = icms_getModuleHandler("form", "ccenter");
$is_post = $_SERVER["REQUEST_METHOD"] === "POST";
$op = (string) preg_replace("/[^A-Za-z_]/", "", (string) ($_REQUEST["op"] ?? ""));
if ($op === "delete") {
    $op = "del"; // links of the earlier version
}
$formid = (int) ($_REQUEST["formid"] ?? 0);

// ---- changes: POST requests with a valid security token only ----
if ($is_post && $op === "del" && !empty($_POST["confirm"])) {
    if (!cc_check_token()) {
        redirect_header("index.php", 3, cc_token_error());
        exit();
    }
    // removes the form with its messages, their attachments, comments and notification subscriptions
    $handler->deleteWithMessages($formid);
    redirect_header("index.php", 1, _AM_FORM_DELETED);
    exit();
}

$editing = null;   // the form object shown in the editor
$messages = "";
$preview = false;
if ($is_post && in_array($op, ["addform", "changedField"], true)) {
    $editing = $formid ? $handler->get($formid) : $handler->create();
    if (!$editing || $editing->isNew() !== ($formid === 0)) {
        redirect_header("index.php", 3, _AM_NODATA);
        exit();
    }
    $controller = new mod_ccenter_Controller($handler);
    if ($op === "changedField" || !empty($_POST["preview"])) {
        // not stored: the typed values are shown again (with the new list of contact persons, or as preview)
        $controller->postDataToObject($editing);
        $preview = $op === "addform";
    } elseif (!cc_check_token()) {
        $controller->postDataToObject($editing);
        $messages = cc_token_error();
    } else {
        $_POST["form_view"] = $_POST["form_view"] ?? [];   // IPF cannot handle "no group checked"
        $obj = $controller->doStoreFromDefaultForm($editing, $formid, _AM_FORM_UPDATED, _AM_FORM_UPDATED, null);
        if (!$obj->hasError()) {
            $stored = (int) $obj->getVar("formid", "n");
            $handler->syncLegacyColumn($stored);
            $defs = $obj->definitionForEditing("lines");
            $incomplete = check_form_tags((int) $obj->getVar("custom", "n"), $defs, (string) $obj->getVar("description", "n"));
            redirect_header($incomplete ? "index.php?op=mod&formid=" . $stored : "index.php", 1, _AM_FORM_UPDATED);
            exit();
        }
        $messages = trim(strip_tags(str_replace("<br />", "\n", $obj->getHtmlErrors())));
        $editing = $obj;
    }
}

icms_cp_header();
cc_display_admin_menu(1);

if ($op === "del" && $formid) {
    confirm_delete($handler, $formid);
} elseif ($editing || $formid || in_array($op, ["mod", "edit", "clone"], true)) {
    edit_form($handler, $editing, $formid, $op === "clone", $messages, $preview);
} else {
    list_forms($handler);
}

icms_cp_footer();

function confirm_delete($handler, $formid)
{
    $form = $handler->find($formid);
    if (!$form) {
        echo _AM_NODATA;
        return;
    }
    icms_core_Message::confirm(
        ["op" => "del", "formid" => $formid, "confirm" => 1],
        "index.php",
        _AM_DELETE_FORM . " - " . htmlspecialchars($form->getVar("title", "n")) . " (ID:$formid)",
        _DELETE,
    );
}

function list_forms($handler)
{
    mod_ccenter_AdminTable::guardRequest("form", ["formid", "title", "weight", "active"]);
    $table = new icms_ipf_view_Table($handler, false, ["edit", "delete"]);
    $table->setCustomTemplate(mod_ccenter_AdminTable::TEMPLATE);
    $table->setDefaultSort("weight");
    $table->setDefaultOrder("ASC");
    $table->addColumn(new icms_ipf_view_Column("formid", "right", 40, false, false, "ID"));
    $table->addColumn(new icms_ipf_view_Column("title", "left", false, "getTitleLink", false, _AM_FORM_TITLE));
    $table->addColumn(new icms_ipf_view_Column("priuid", "left", 160, "getContact", false, _AM_FORM_PRIM_CONTACT, false));
    $table->addColumn(new icms_ipf_view_Column("count_all", "right", 50, "getCount", false, _AM_MSG_COUNT, false));
    $table->addColumn(new icms_ipf_view_Column("count_wait", "right", 50, "getWaiting", false, _AM_MSG_WAIT, false));
    $table->addColumn(new icms_ipf_view_Column("count_work", "right", 50, "getWorking", false, _AM_MSG_WORK, false));
    $table->addColumn(new icms_ipf_view_Column("count_reply", "right", 50, "getReplied", false, _AM_MSG_REPLY, false));
    $table->addColumn(new icms_ipf_view_Column("count_close", "right", 50, "getClosed", false, _AM_MSG_CLOSE, false));
    $table->addColumn(new icms_ipf_view_Column("active", "center", 50, "getActiveMark", false, _AM_FORM_ACTIVE));
    $table->addCustomAction("getMessagesLink");
    $table->addCustomAction("getCloneLink");
    $table->addIntroButton("addform", "index.php?op=mod", _AM_FORM_NEW);
    $table->addQuickSearch(["title"], _SEARCH);
    echo $table->fetch();
}

/**
 * The form for creating, changing or copying a contact form: the IPF form of the object.
 *
 * @param mod_ccenter_FormHandler $handler
 * @param mod_ccenter_Form|null   $obj      the object with the values to show (null: load it)
 */
function edit_form($handler, $obj, $formid, $clone, $messages, $preview)
{
    global $xoopsTpl;
    if ($obj === null) {
        $source = $formid ? $handler->get($formid) : null;
        if ($formid && !$source) {
            echo _AM_NODATA;
            return;
        }
        if ($clone) {
            $obj = $handler->copyOf($source);
            $obj->setVar("formid", 0);
            $handler->defaultGroups = $handler->getAllowedGroups($formid);
        } else {
            $obj = $source ?: $handler->create();
        }
    }
    if ($messages !== "") {
        echo '<div class="errorMsg">' . nl2br(htmlspecialchars($messages)) . "</div>\n";
    }

    if ($preview) {
        $defs = $obj->definitionForEditing("lines");
        if (cc_definition_valid($defs)) {
            $data = $obj->toRow();
            $data["optvars"] = (string) $obj->getVar("optvars", "n");
            get_attr_value($data["optvars"]); // set default values
            $items = get_form_attribute($defs);
            assign_form_widgets($items);
            echo "<h2>" . _PREVIEW . " : " . htmlspecialchars($obj->getVar("title", "n")) . "</h2>\n<div class='preview'>\n";
            $data["action"] = "";
            $data["check_script"] = "";
            $data["items"] = &$items;
            if (empty($xoopsTpl)) {
                $xoopsTpl = new icms_view_Tpl();
            }
            $xoopsTpl->assign("errors", []);
            $out = $xoopsTpl->fetch("db:" . render_form($data, "form"));
            echo preg_replace('/type=["\']submit["\']/', 'type="submit" disabled="disabled"', $out);
            echo "</div>\n<hr size='5'/>\n";
        }
    }

    $caption = $obj->isNew() ? _AM_FORM_NEW : _AM_FORM_EDIT;
    if (!$obj->isNew() && (int) $obj->getVar("mtime", "n")) {
        $caption .= " (" . formatTimestamp((int) $obj->getVar("mtime", "n"), "m") . ")";
    }
    $form = $obj->getSecureForm($caption, "addform", "index.php");
    $form->addCustomButton("preview", _PREVIEW);
    echo "<a name='form'></a>";
    $form->display();
}
