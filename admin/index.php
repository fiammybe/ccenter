<?php
// administration of the contact forms
include "../../../include/cp_header.php";
include "../functions.php";

// option variables form definitions
define(
    "_CC_OPTDEFS",
    "notify_with_email,radio,1=" .
        _YES .
        ",=" .
        _NO .
        "
redirect,text,size=60
reply_comment,textarea,cols=60,rows=10
reply_use_comtpl,radio,1=" .
        _YES .
        ",=" .
        _NO .
        "
others,textarea",
);

$handler = icms_getModuleHandler("form", "ccenter");
$is_post = $_SERVER["REQUEST_METHOD"] === "POST";
$op = (string) preg_replace("/[^a-z_]/", "", (string) ($_REQUEST["op"] ?? ""));
if ($op === "delete") {
    $op = "del"; // links of the earlier version
}
$formid = (int) ($_REQUEST["formid"] ?? 0);
$fields = ["title", "description", "defs", "priuid", "cgroup", "store", "custom", "weight", "active"];

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
if ($is_post && isset($_POST["formdefs"]) && !isset($_POST["preview"]) && !isset($_POST["chggrp"])) {
    $save_error = save_form($handler, $fields);   // returns only when nothing was stored
}

icms_cp_header();
cc_display_admin_menu(1);
if (isset($save_error)) {
    echo '<div class="errorMsg">' . nl2br(htmlspecialchars($save_error)) . "</div>\n";
}

if ($op === "del" && $formid) {
    confirm_delete($handler, $formid);
} elseif (in_array($op, ["mod", "edit", "clone"], true) || $formid || ($is_post && isset($_POST["formid"]))) {
    edit_form($handler, $formid, $op === "clone");
} else {
    list_forms($handler);
}

icms_cp_footer();

/**
 * Store the form that was posted. Returns a message when it could not be stored; on success it redirects.
 */
function save_form($handler, $fields)
{
    $formid = (int) ($_POST["formid"] ?? 0);
    $form = $formid ? $handler->find($formid) : null;
    if (!$form) {
        $form = $handler->create();
    }
    $values = [];
    foreach ($fields as $name) {
        $values[$name] = icms_core_DataFilter::stripSlashesGPC($_POST[$name] ?? "");
    }
    if (!cc_check_token()) {
        return cc_token_error();
    }
    $text = $values["defs"]; // the definition as typed (lines, or JSON)
    try {
        $values["defs"] = mod_ccenter_FormSchema::normalize($text);
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
    $values["optvars"] = post_optvars();
    $form->setValues($values);
    $groups = array_map("intval", (array) ($_POST["grpperm"] ?? []));
    $form->setAllowedGroups($groups);
    $form->setVar("mtime", time());
    $res = $handler->insert($form, true);
    $formid = (int) $form->getVar("formid", "n");
    if ($res) {
        $handler->setAllowedGroups($formid, $groups);
    }
    $redirect = check_form_tags($values["custom"], $text, $values["description"]) ? "index.php?op=mod&formid=" . $formid : "index.php";
    redirect_header($redirect, $res ? 1 : 3, $res ? _AM_FORM_UPDATED : _AM_FORM_UPDATE_FAIL);
    exit();
}

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

/** The option variables of the form as posted (JSON object). */
function post_optvars()
{
    $items = get_form_attribute(_CC_OPTDEFS, "", "optvar");
    assign_post_values($items);
    $vars = [];
    foreach ($items as $item) {
        $fname = $item["name"];
        if ($fname == "others") {
            foreach (mod_ccenter_OptionVars::parse($item["value"]) as $k => $v) {
                $vars[$k] = $v;
            }
        } elseif ($item["value"]) {
            $vars[$fname] = $item["value"];
        }
    }
    return mod_ccenter_OptionVars::encode($vars);
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
 * The form for creating, changing or copying a contact form.
 */
function edit_form($handler, $formid, $clone)
{
    global $fields, $xoopsTpl;
    $format = ($_GET["format"] ?? "") === "json" ? "json" : "lines";
    $previewOnly = false;

    if (isset($_POST["formid"])) {
        // shown again after "preview", "change group" or a refused save: what was typed
        $data = [];
        foreach ($fields as $name) {
            $data[$name] = icms_core_DataFilter::stripSlashesGPC($_POST[$name] ?? "");
        }
        $data["optvars"] = post_optvars();
        $data["grpperm"] = (array) ($_POST["grpperm"] ?? []);
        $formid = (int) $_POST["formid"];
        $format = null;
        get_attr_value($data["optvars"]); // set default values
        $valid = cc_definition_valid($data["defs"]);   // what was typed may be refused by the save
        $items = $valid ? get_form_attribute($data["defs"]) : [];
        assign_form_widgets($items);
        if (!empty($_POST["preview"]) && $valid) {
            echo "<h2>" . _PREVIEW . " : " . htmlspecialchars($data["title"]) . "</h2>\n<div class='preview'>\n";
            $data["action"] = "";
            $data["check_script"] = "";
            $data["formid"] = $formid;
            $data["items"] = &$items;
            if (empty($xoopsTpl)) {
                $xoopsTpl = new icms_view_Tpl();
            }
            $xoopsTpl->assign("errors", []);
            $out = $xoopsTpl->fetch("db:" . render_form($data, "form"));
            echo preg_replace('/type=["\']submit["\']/', 'type="submit" disabled="disabled"', $out);
            echo "</div>\n<hr size='5'/>\n";
        }
    } elseif ($formid && ($source = $handler->find($formid))) {
        if ($clone) {
            $data = $handler->copyOf($source)->toRow();
            $formid = 0;
        } else {
            $data = $source->toRow();
        }
        $data["grpperm"] = $handler->getAllowedGroups($source->getVar("formid", "n"));
        // the definition is edited as lines of text or, with ?format=json, as JSON; saving accepts both
        try {
            $schema = $source->schema();
            $data["defs"] = $format === "json" ? $schema->toJson(true) : $schema->toLegacy();
        } catch (InvalidArgumentException $e) {
            // keep the stored text so that it can be repaired
        }
    } else {
        $formid = 0;
        $data = [
            "title" => "", "description" => "", "defs" => "", "store" => 1, "custom" => 0, "weight" => 0, "active" => 1,
            "priuid" => icms::$user->getVar("uid"), "cgroup" => ICMS_GROUP_ADMIN, "optvars" => "", "grpperm" => [ICMS_GROUP_USERS],
        ];
    }

    $form = new icms_form_Theme($formid ? _AM_FORM_EDIT : _AM_FORM_NEW, "myform", "index.php", "post", true);
    $hidden = new icms_form_elements_Hidden("formid", $formid);
    $form->addElement($hidden);
    $title = new icms_form_elements_Text(_AM_FORM_TITLE, "title", 35, 80, $data["title"]);
    $form->addElement($title, true);
    if (!empty($data["mtime"]) && $formid) {
        $updated = new icms_form_elements_Label(_AM_FORM_MTIME, formatTimestamp($data["mtime"]));
        $form->addElement($updated);
    }

    // description (with the form template when it is a template) and its checks
    $desc = new icms_form_elements_Tray(_AM_FORM_DESCRIPTION, "<br/>");
    $editor = get_attr_value(null, "use_fckeditor");
    $description = $data["description"];
    if ($editor) {
        $box = new icms_form_elements_Textarea("", "description", $description, 10, 60);
        $desc->addElement($box);
    } else {
        $box = new icms_form_elements_Dhtmltextarea("", "description", $description, 10, 60);
        $desc->addElement($box);
        $button = new icms_form_elements_Button("", "ins_tpl", _AM_INS_TEMPLATE);
        $button->setExtra("onClick=\"myform.description.value += defsToString();\"");
        $desc->addElement($button);
    }
    $error = cc_definition_valid($data["defs"]) ? check_form_tags($data["custom"], $data["defs"], $description) : "";
    if ($error) {
        $note = new icms_form_elements_Label("", "<div style='color:red;'>$error</div>");
        $desc->addElement($note);
    }
    $form->addElement($desc);

    $custom = new icms_form_elements_Select(_AM_FORM_CUSTOM, "custom", $data["custom"]);
    $custom->setExtra(' onChange="myform.ins_tpl.disabled = (this.value==0||this.value==4);"');
    $custom_type = unserialize_vars(_AM_CUSTOM_DESCRIPTION);
    if ($editor) {
        unset($custom_type[0]);
    }
    $custom->addOptionArray($custom_type);
    $form->addElement($custom);

    $grpperm = new icms_form_elements_select_Group(_AM_FORM_ACCEPT_GROUPS, "grpperm", true, $data["grpperm"], 4, true);
    $grpperm->setDescription(_AM_FORM_ACCEPT_GROUPS_DESC);
    $form->addElement($grpperm);

    // the definition of the items
    $defs_tray = new icms_form_elements_Tray(_AM_FORM_DEFS);
    $defs = new icms_form_elements_Textarea("", "defs", $data["defs"], 10, 60);
    $defs_tray->addElement($defs);
    $helper = '<div id="itemhelper" style="display:none; white-space:nowrap;">' . _AM_FORM_LAB . ' <input name="xelab" size="10">
  <input type="checkbox" name="xereq" title="' . _AM_FORM_REQ . '">
  <select name="xetype">';
    foreach (mod_ccenter_FormSchema::TYPES as $type) {
        if ($type !== "date") {
            $helper .= "<option value=\"$type\">$type</option>";
        }
    }
    $helper .= '</select> <input name="xeopt" size="30" /> <button onClick="return addFieldItem();">' . _AM_FORM_ADD . '</button></div>';
    $tools = new icms_form_elements_Label("", $helper);
    $defs_tray->addElement($tools);
    if ($format !== null && $formid) {
        $other = $format === "json" ? ["lines", defined("_AM_CC_AS_LINES") ? _AM_CC_AS_LINES : "Show as lines"] : ["json", defined("_AM_CC_AS_JSON") ? _AM_CC_AS_JSON : "Show as JSON"];
        $url = "index.php?op=mod&amp;formid=$formid&amp;format=" . $other[0];
        $switch = new icms_form_elements_Label("", "<div><a href=\"$url\">" . htmlspecialchars($other[1]) . "</a> <small>" .
            htmlspecialchars(defined("_AM_CC_FORMAT_NOTE") ? _AM_CC_FORMAT_NOTE : "(unsaved changes are lost; both forms can be saved)") . "</small></div>");
        $defs_tray->addElement($switch);
    }
    $defs_tray->setDescription(_AM_FORM_DEFS_DESC);
    $form->addElement($defs_tray);

    // who handles the messages
    $choices = $handler->contactChoices((int) $data["cgroup"]);
    if (!isset($choices[(int) $data["priuid"]]) && (int) $data["priuid"] > 0) {
        $choices[(int) $data["priuid"]] = icms_member_user_Object::getUnameFromId((int) $data["priuid"]);
    }
    $priuid = new icms_form_elements_Select(_AM_FORM_PRIM_CONTACT, "priuid", $data["priuid"]);
    $priuid->addOptionArray($choices);
    $priuid->setDescription(_AM_FORM_PRIM_DESC);
    $form->addElement($priuid);

    $groups = icms::handler("icms_member")->getGroupList(new icms_db_criteria_Item("groupid", ICMS_GROUP_ANONYMOUS, "!="));
    $cgroup = new icms_form_elements_Select("", "cgroup", $data["cgroup"]);
    $cgroup->addOption(0, _AM_FORM_CGROUP_NONE);
    $cgroup->addOptionArray($groups);
    $cgroup_tray = new icms_form_elements_Tray(_AM_FORM_CONTACT_GROUP);
    $cgroup_tray->addElement($cgroup);
    // the list of contact persons shows the members of this group: "change" shows the form again with the new list
    $refresh = new icms_form_elements_Button("", "chggrp", _AM_CHANGE, "submit");
    $cgroup_tray->addElement($refresh);
    $form->addElement($cgroup_tray);

    $store = new icms_form_elements_Select(_AM_FORM_STORE, "store", $data["store"]);
    $store->addOptionArray(unserialize_vars(_CC_STORE_MODE, 1));
    $form->addElement($store);
    $active = new icms_form_elements_Radioyn(_AM_FORM_ACTIVE, "active", $data["active"]);
    $form->addElement($active);
    $weight = new icms_form_elements_Text(_AM_FORM_WEIGHT, "weight", 2, 8, $data["weight"]);
    $form->addElement($weight);

    // option variables
    $items = get_form_attribute(_CC_OPTDEFS, _AM_OPTVARS_LABEL, "optvar");
    $vars = mod_ccenter_OptionVars::parse($data["optvars"]);
    foreach ($items as $k => $item) {
        if (isset($vars[$item["name"]])) {
            $items[$k]["default"] = $vars[$item["name"]];
            unset($vars[$item["name"]]);
        }
    }
    $others = "";
    foreach ($vars as $i => $v) {
        $others .= "$i=$v\n";
    }
    $items[array_key_last($items)]["default"] = $others; // the free-form "others" entry is last
    assign_form_widgets($items);
    $varform = "";
    foreach ($items as $item) {
        $br = $item["type"] == "textarea" ? "<br/>" : "";
        $varform .= "<div>" . $item["label"] . ": $br" . $item["input"] . "</div>";
    }
    $ck = empty($data["optvars"]) || $data["optvars"] === "{}" ? "" : " checked='checked'";
    $optvars = new icms_form_elements_Label(
        _AM_FORM_OPTIONS,
        "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onChange='toggle(this);'$ck/> " .
            _AM_OPTVARS_SHOW . "\");</script><div id='optvars'>$varform</div>",
    );
    $form->addElement($optvars);

    $submit = new icms_form_elements_Tray("");
    $save = new icms_form_elements_Button("", "formdefs", _SUBMIT, "submit");
    $submit->addElement($save);
    $preview = new icms_form_elements_Button("", "preview", _PREVIEW, "submit");
    $submit->addElement($preview);
    $form->addElement($submit);

    echo "<a name='form'></a>";
    $form->display();
    $fck = "";
    if ($editor) {
        $base = ICMS_URL . "/common/fckeditor";
        echo "<script type='text/javascript' src='$base/fckeditor.js'></script>\n";
        $fck = "var ccFCKeditor = new FCKeditor('description', '100%', '350', '$editor');
ccFCKeditor.BasePath = '$base/';
ccFCKeditor.ReplaceTextarea();";
    }
    echo editor_script($fck, $format === "json");
}

/** JavaScript of the edit page: toggles and the helper that appends an item to the definition. */
function editor_script($fck, $json)
{
    $helpers = $json ? "" : "xoopsGetElementById(\"itemhelper\").style.display = \"block\";";
    return '<script language="JavaScript">
// display only JavaScript enable
' . $helpers . '
' . $fck . '
function toggle(a) {
    xoopsGetElementById("optvars").style.display = a.checked?"block":"none";
}
toggle(xoopsGetElementById("optshow"));

function addFieldItem() {
    var myform = window.document.myform;
    var item=myform.xelab.value;
    if (item == "") {
	alert("' . _AM_FORM_LABREQ . '");
	myform.xelab.focus();
	return false;
    }
    if (myform.xereq.checked) item += "*";
    var ty = myform.xetype.value;
    var ov = myform.xeopt.value;
    item += ","+ty;
    if (ty != "text" && ty != "textarea" && ty != "file" && ty != "mail" && ov == "") {
	alert(ty+": ' . _AM_FORM_OPTREQ . '");
	myform.xeopt.focus();
	return false;
    }
    if (ov != "") item += ","+ov;
    opts = myform.defs;
    if (opts.value!="" && !opts.value.match(/[\n\r]$/)) item = "\n"+item;
    opts.value += item;
    myform.xelab.value = ""; // clear old value
    myform.xeopt.value = "";
    return false; // always return false
}
function defsToString() {
    value = window.document.myform.defs.value;
    ret = "";
    lines = value.split("\\n");
    conf = "' . _MD_CONF_LABEL . '";
    for (i in lines) {
       lab = lines[i].replace(/,.*$/, "");
       if (lab.match(/^\s*#/)) {
           ret += "[desc]<div>"+lines[i].replace(/^\s*#/, "")+"</div>[/desc]\n";
       } else if (lab != "") {
           ret += "<div>"+lab+": {"+lab.replace(/\\*?$/,"")+"}</div>\n";
           if (lines[i].match(/^[^,]+,\\s*mail/i)) {
              lab = conf.replace(/%s/, lab);
              ret += "[desc]<div>"+lab+": {"+lab.replace(/\\*?$/,"")+"}</div>[/desc]\n";
           }
       }
    }
    return "<form {FORM_ATTR}>\n"+ret+
      "<p>{SUBMIT} {BACK}</p>\n</form>\n{CHECK_SCRIPT}";
}

fvalue = document.myform.custom.value;
document.myform.ins_tpl.disabled = (fvalue==0 || fvalue==4);
</script>
';
}
