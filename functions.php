<?php
// ccenter common functions
// $Id: functions.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

require_once __DIR__ . "/class/autoload.php";

// using tables
define("FORMS", icms::$xoopsDB->prefix("ccenter_form"));
define("CCMES", icms::$xoopsDB->prefix("ccenter_message"));
define("CCLOG", icms::$xoopsDB->prefix("ccenter_log"));

//include_once ICMS_ROOT_PATH."/class/template.php";

define("_STATUS_NONE", mod_ccenter_Status::NONE);
define("_STATUS_ACCEPT", mod_ccenter_Status::ACCEPT);
define("_STATUS_REPLY", mod_ccenter_Status::REPLY);
define("_STATUS_CLOSE", mod_ccenter_Status::CLOSE);
define("_STATUS_DEL", mod_ccenter_Status::DEL);

define("_DB_STORE_LOG", mod_ccenter_StoreMode::LOG); // logging only in db
define("_DB_STORE_YES", mod_ccenter_StoreMode::YES); // store information in db
define("_DB_STORE_NONE", mod_ccenter_StoreMode::NONE); // query not store in db

define("_CC_WIDGET_TPL", "ccenter_form_widgets.html");

if (!defined("_CC_STATUS_NONE")) {
	$moddir = dirname(__FILE__);
	$lang = $GLOBALS["icmsConfig"]["language"];
	if (!include_once "$moddir/language/$lang/common.php") {
		include_once "$moddir/language/english/common.php";
	}
}
global $msg_status, $export_range;

$msg_status = mod_ccenter_Status::labels();

$export_range = [
	"m0" => _CC_EXPORT_THIS_MONTH,
	"m1" => _CC_EXPORT_LAST_MONTH,
	"y0" => _CC_EXPORT_THIS_YEAR,
	"y1" => _CC_EXPORT_LAST_YEAR,
	"all" => _CC_EXPORT_ALL,
];

define("_CC_TPL_NONE", 0);
define("_CC_TPL_BLOCK", 1);
define("_CC_TPL_FULL", 2);
define("_CC_TPL_FRAME", 3); // obsolete
define("_CC_TPL_NONE_HTML", 4);

define("LABEL_ETC", "*"); // radio, checkbox widget 'etc' text input.
define("OPTION_ATTRS", "size,rows,maxlength,cols,prop,notify_with_email");

// attribute config option expanding
function get_attr_value($pri, $name = null, $value = null)
{
	static $defs; // default option value

	if ($name && is_array($pri) && isset($pri[$name])) {
		return $pri[$name];
	}
	if (!isset($defs)) {
		$defs = ["numeric" => "[-+]?[0-9]+", "tel" => "\+?[0-9][0-9-,]*[0-9]"];
		foreach (explode(",", OPTION_ATTRS) as $key) {
			$defs[$key] = 0;
		}
		// override module config values
		$mydirname = basename(dirname(__FILE__));
		if (
			!empty(icms::$module) &&
			icms::$module->getVar("dirname") == $mydirname
		) {
			$configs = icms::$module->config;
		} else {
			$module = icms::handler("icms_module")->getByDirname($mydirname);
			$configs = is_object($module) ? $module->config : [];
		}
		$def_attr = $configs["def_attrs"] ?? "";
		foreach (mod_ccenter_OptionVars::parse($def_attr) as $k => $v) {
			$defs[$k] = $v;
		}
	}
	if ($name == null && !is_null($pri)) {
		// override values
		if (!is_array($pri)) {
			$pri = mod_ccenter_OptionVars::parse($pri);
		}
		foreach ($pri as $k => $v) {
			$defs[$k] = $v;
		}
	}
	if (isset($defs[$name])) {
		return $defs[$name];
	}
	return $value;
}

/**
 * Prepare the values of a message for display (HTML).
 *
 * @param array  $vals   key => text of the message body
 * @param array  $items  items of the current form definition
 * @param int    $msgid  message id (for attachments)
 * @param string $add    extra URL arguments for attachments
 * @param array  $labels key => label stored with the message; used instead of the current label of the form
 * @return array label => HTML
 */
function cc_display_values($vals, $items, $msgid = 0, $add = "", $labels = [])
{
	$esc = function ($text) {
		return icms_core_DataFilter::checkVar($text, "text", "output");
	};
	$values = [];
	foreach ($vals as $key => $val) {
		if (isset($items[$key])) {
			$item = &$items[$key];
			$label = isset($labels[$key]) ? $labels[$key] : $item["label"];
		} else {
			$item = null;
			$label = isset($labels[$key]) ? $labels[$key] : $key;
		}
		if (preg_match('/^file=(.+)$/', $val, $d)) {
			$val = cc_attach_image($msgid, $d[1], false, $add);
		} elseif ($item) {
			$opts = &$item["options"];
			switch ($item["type"]) {
				case "radio":
				case "select":
					// an option label is written by the administrator; anything else (e.g. text typed
					// after "other") is visitor input
					$val = isset($opts[$val]) ? strip_tags($opts[$val]) : $esc($val);
					break;
				case "checkbox":
					$cvals = [];
					foreach (preg_split("/,\s?/", $val) as $v) {
						if (!empty($v)) {
							$cvals[] = isset($opts[$v]) ? strip_tags($opts[$v]) : $esc($v);
						}
					}
					$val = join(", ", $cvals);
					break;
				default:
					$val = $esc($val);
					break;
			}
		} else {
			$val = $esc($val);
		}
		$values[$label] = $val;
		unset($item);
	}
	return $values;
}

function get_form_attribute($defs, $labels = "", $prefix = "cc")
{
	try {
		return mod_ccenter_FormSchema::fromStored($defs)->items(
			mod_ccenter_OptionVars::parse($labels),
			$prefix,
		);
	} catch (InvalidArgumentException $e) {
		trigger_error("ccenter: invalid form definition: " . $e->getMessage(), E_USER_WARNING);
		return [];
	}
}

function assign_post_values(&$items)
{
	$errors = [];
	foreach ($items as $key => $item) {
		if (empty($item["field"])) {
			continue;
		}
		$name = $item["field"];
		$type = $item["type"];
		$lab = $item["label"];
		$attr = &$item["attr"];
		$check = !empty($attr["check"]) ? $attr["check"] : "";
		$val = "";
		if (isset($_POST[$name])) {
			$val = $_POST[$name];
			if (is_array($val)) {
				foreach ($val as $n => $v) {
					$val[$n] = icms_core_DataFilter::stripSlashesGPC($v);
				}
			} else {
				$val = icms_core_DataFilter::stripSlashesGPC($val);
			}
		}
		switch ($check) {
			case "":
				break;
			case "require":
				if ($val === "") {
					$errors[] = $lab . ": " . _MD_REQUIRE_ERR;
				}
				break;
			case "mail":
				if (!icms_core_DataFilter::checkVar($val, "email", 0, 0)) {
					$errors[] = $lab . ": " . _MD_ADDRESS_ERR;
				}
				break;
			case "num":
				$check = "numeric";
			default:
				$v = get_attr_value(null, $check);
				if (!empty($v)) {
					$check = $v;
				}
				if (!preg_match("/^" . $check . '$/', $val)) {
					$errors[] =
						$lab . ": " . ($val ? _MD_REGEXP_ERR : _MD_REQUIRE_ERR);
				}
				break;
		}
		switch ($type) {
			case "checkbox":
				if (empty($val)) {
					$val = [];
				}
				$idx = array_search(LABEL_ETC, $val); // etc
				if (is_int($idx)) {
					$val[$idx] =
						strip_tags($item["options"][LABEL_ETC]) .
						" " .
						icms_core_DataFilter::stripSlashesGPC(
							$_POST[$name . "_etc"],
						);
				}
				break;
			case "radio":
				if ($val == LABEL_ETC) {
					// etc
					$val =
						strip_tags($item["options"][LABEL_ETC]) .
						" " .
						icms_core_DataFilter::stripSlashesGPC(
							$_POST[$name . "_etc"],
						);
				}
				break;
			case "hidden":
			case "const":
				$val = eval_user_value(join(",", $item["options"]));
				break;
			case "file":
				$val = ""; // filename
				$upfile = isset($_FILES[$name])
					? $_FILES[$name]
					: ["name" => ""];
				if (isset($_POST[$name . "_prev"])) {
					$val = icms_core_DataFilter::stripSlashesGPC(
						$_POST[$name . "_prev"],
					);
					if (!empty($upfile["name"])) {
						unlink(ICMS_UPLOAD_PATH . cc_attach_path(0, $val));
						$val = "";
					}
				}
				if (empty($val)) {
					$val = $upfile["name"];
					if ($val) {
						move_attach_file($upfile["tmp_name"], $val);
					}
				} elseif (isset($_POST[$name])) {
					// confirm
					$val = icms_core_DataFilter::stripSlashesGPC($_POST[$name]);
				}
				break;
			case "mail":
				$name .= "_conf";
				if (!icms_core_DataFilter::checkVar($val, "email")) {
					$errors[] = $lab . ": " . _MD_ADDRESS_ERR;
				}
				if (isset($_POST[$name])) {
					if (
						$val !=
						icms_core_DataFilter::stripSlashesGPC($_POST[$name])
					) {
						$errors[] =
							sprintf(_MD_CONF_LABEL, $lab) .
							": " .
							_MD_CONFIRM_ERR;
					}
				}
				break;
		}
		$items[$key]["value"] = $val;
	}
	return $errors;
}

function assign_form_widgets(&$items, $conf = false)
{
	$mconf = !$conf;
	$updates = [];
	foreach ($items as $item) {
		if (empty($item["field"])) {
			// comment only
			$updates[] = $item;
			continue;
		}
		if ($item["type"] == "hidden" && !$conf) {
			continue;
		}
		$val = &$item["value"];
		$fname = &$item["field"];
		$opts = $item["options"];
		if ($conf) {
			if (is_array($val)) {
				$fmt = "<input type='hidden' name='{$fname}[]' value='%s' />";
				$input = "";
				foreach ($val as $k => $v) {
					// the hidden field carries the option value (not its label), the label is shown
					$input .= sprintf($fmt, htmlspecialchars($v, ENT_QUOTES));
					$val[$k] = isset($opts[$v]) ? strip_tags($opts[$v]) : $v;
				}
				$input .= htmlspecialchars(join(", ", $val), ENT_QUOTES);
			} else {
				$v = htmlspecialchars($val, ENT_QUOTES);
				switch ($item["type"]) {
					case "hidden":
						$input = $v;
						break;
					case "radio":
					case "select":
						$input =
							(isset($opts[$val])
								? strip_tags($opts[$val])
								: $v) .
							"<input type='hidden' name='$fname' value='$v' />";
						break;
					case "file":
						$input =
							cc_attach_image(0, $val, false) .
							"<input type='hidden' name='$fname' value='$v' />";
						break;
					default:
						$input =
							nl2br($v) .
							"<input type='hidden' name='$fname' value='$v' />";
						break;
				}
			}
		} else {
			$input = cc_make_widget($item);
			if (
				$mconf &&
				isset($item["type"]) &&
				$item["type"] == "mail" &&
				isset($item["attr"]["check"]) &&
				$item["attr"]["check"] == "require"
			) {
				$cfname = $fname . "_conf";
				$citem = [
					"name" => sprintf(_MD_CONF_LABEL, $item["name"]),
					"label" => sprintf(_MD_CONF_LABEL, $item["label"]),
					"field" => $cfname,
					"type" => $item["type"],
					"comment" => _MD_CONF_DESC,
					"attr" => $item["attr"],
				];
				$item["input"] = $input;
				$updates[] = $item;
				$input = cc_make_widget($citem);
				$item = $citem;
				$mconf = false;
			}
		}
		$item["input"] = $input;
		$updates[] = $item;
	}
	$items = $updates;
	return $updates;
}

/** Replace {X_<USERFIELD>} placeholders with data of the current user (plain string replacement). */
function eval_user_value($str)
{
	return mod_ccenter_PlaceholderResolver::resolveForCurrentUser($str);
}

function cc_make_widget($item, $vars = null)
{
	$fname = $item["field"];
	$value = null;
	$type = $item["type"];
	$options = &$item["options"];
	if (isset($_POST[$fname])) {
		$value = &$_POST[$fname];
		if (!is_array($value)) {
			$value = icms_core_DataFilter::stripSlashesGPC($value);
		}
	} else {
		if (isset($item["default"])) {
			$value = $item["default"];
		}
	}
	if (isset($options)) {
		if (isset($options[LABEL_ETC])) {
			$ereg =
				"/^" .
				preg_quote(strip_tags($options[LABEL_ETC]), "/") .
				"\s+/";
			if ($type == "checkbox") {
				if (is_array($value)) {
					foreach ($value as $key => $val) {
						if (preg_match($ereg, $val)) {
							$item["etc_value"] = preg_replace($ereg, "", $val);
							$value[$key] = LABEL_ETC;
						}
					}
				}
			} else {
				if (preg_match($ereg, $value)) {
					$item["etc_value"] = preg_replace($ereg, "", $value);
					$value = LABEL_ETC;
				}
			}
		}
	}
	$item["value"] = $value;
	$tpl = new icms_view_Tpl();
	$tpl->assign("item", $item);
	if (isset($vars)) {
		$tpl->assign($vars);
	}
	return $tpl->fetch("db:" . _CC_WIDGET_TPL);
}

if (!function_exists("unserialize_vars")) {
	// expand: label=value[,\n](label=value...)
	function unserialize_vars($text, $rev = false)
	{
		return mod_ccenter_LegacyText::unserializeVars($text, $rev);
	}
}
if (!function_exists("serialize_text")) {
	function serialize_text($array)
	{
		return mod_ccenter_LegacyText::serializeText($array);
	}

	function unserialize_text($text)
	{
		return mod_ccenter_LegacyText::unserializeText($text);
	}
}

function move_attach_file($tmp, $file, $id = 0)
{
	if (!mod_ccenter_Attachments::move($tmp, $file, $id)) {
		die("UPLOADS permittion error");
	}
	return true;
}

if (!function_exists("template_dir")) {
	function template_dir($file = "")
	{
		global $icmsConfig;
		$lang = $icmsConfig["language"];
		$dir = dirname(__FILE__) . "/language/%s/mail_template/%s";
		$path = sprintf($dir, $lang, $file);
		if (file_exists($path)) {
			$path = sprintf($dir, $lang, "");
		} else {
			$path = sprintf($dir, "english", "");
		}
		return $path;
	}
}

function cc_attach_path($id, $file)
{
	return mod_ccenter_Attachments::path($id, $file);
}

function cc_attach_image($id, $file, $urlonly = false, $add = "")
{
	if (empty($file)) {
		return "";
	}
	$rurl =
		"file.php?" .
		($id ? "id=$id&" : "") .
		"file=" .
		urlencode($file) .
		($add ? "&$add" : "");
	if ($urlonly) {
		return ICMS_URL . "/modules/" . basename(dirname(__FILE__)) . "/$rurl";
	}
	$path = ICMS_UPLOAD_PATH . cc_attach_path($id, $file);
	$xy = getimagesize($path);
	if ($xy) {
		if ($xy[0] > $xy[1] && $xy[0] > 300) {
			$extra = " width='300'";
		} elseif ($xy[1] > 300) {
			$extra = " height='300'";
		} else {
			$extra = "";
		}
		$extra .= " alt='" . htmlspecialchars($file, ENT_QUOTES) . "'";
		return "<img src='$rurl' class='myphoto' $extra />";
	} else {
		$size = return_unit_bytes(filesize($path));
		return "<a href='$rurl' class='myattach'>$file ($size)</a>";
	}
}

function return_unit_bytes($size)
{
	$unit = defined("_MD_BYTE_UNIT") ? _MD_BYTE_UNIT : "bytes";
	if ($size < 10 * 1024) {
		return number_format($size);
	}
	$size /= 1024;
	if ($size < 10 * 1024) {
		return round($size, 1) . "K" . $unit;
	}
	$size /= 1024;
	if ($size < 10 * 1024) {
		return round($size, 1) . "M" . $unit;
	}
	$size /= 1024;
	return round($size, 1) . "G" . $unit;
}

// Access allow:
//   1. onetime password matched
//   2. administrator
//   3. order from/to users
function cc_check_perm($data)
{
	$uid = is_object(icms::$user) ? icms::$user->getVar("uid") : 0;

	$pass = isset($_GET["p"]) ? $_GET["p"] : "";
	if ($pass) {
		$_SESSION["onepass"] = $pass;
	} else {
		$pass = empty($_SESSION["onepass"]) ? "" : $_SESSION["onepass"];
	}
	if (!empty($data["onepass"]) && $data["onepass"] == $pass) {
		return true;
	}

	$mid = is_object(icms::$module) ? icms::$module->getVar("mid") : 0;
	if ($uid && icms::$user->isAdmin($mid)) {
		return true;
	}
	$cgrp = $data["cgroup"] ?? 0;
	if ($cgrp && $uid && in_array($cgrp, icms::$user->getGroups())) {
		return true;
	}
	if ($uid && ($data["uid"] == $uid || $data["touid"] == $uid)) {
		return true;
	}
	return false;
}

function cc_get_message($msgid)
{
	$data = icms_getModuleHandler("message", "ccenter")->getRowWithForm($msgid);
	if (!$data || !cc_check_perm($data)) {
		// members would bounce between user.php and this page, so send them to the module index
		$back = is_object(icms::$user) ? ICMS_URL . "/modules/" . basename(__DIR__) . "/" : ICMS_URL . "/user.php";
		redirect_header($back, 3, _NOPERM);
		exit();
	}
	return $data;
}

function cc_check_comment($msgid, $com_id)
{
	$res = icms::$xoopsDB->query(
		"SELECT com_itemid FROM " .
			icms::$xoopsDB->prefix("xoopscomments") .
			" WHERE com_id=" .
			(int) $com_id .
			" AND com_itemid=" .
			(int) $msgid .
			" AND com_modid=" .
			icms::$module->getVar("mid"),
	);
	[$com_itemid] = icms::$xoopsDB->fetchRow($res);
	return $com_itemid;
}

function cc_onetime_ticket($genseed = "mypasswdbasestring")
{
	return substr(
		preg_replace(
			"/[^a-zA-Z0-9]/",
			"",
			base64_encode(pack("H*", md5($genseed . time()))),
		),
		0,
		8,
	);
}

function cc_delete_message($msgid)
{
	//$res = icms::$xoopsDB->queryF("DELETE FROM ".CCMES." WHERE msgid=".$msgid);
	$dir = ICMS_UPLOAD_PATH . cc_attach_path(0, "");
	$dh = opendir($dir);
	while ($file = readdir($dh)) {
		if ($file == ".." || $file == ".") {
			continue;
		}
		$path = "$dir/$file";
		unlink($path);
	}
}

function cc_message_entry($data, $link = "message.php")
{
	global $msg_status;
	$id = $data["msgid"];
	return [
		"msgid" => $id,
		"mdate" => myTimestamp($data["mtime"], "m", _MD_TIME_UNIT),
		"title" => "<a href='message.php?id=$id'>" . $data["title"] . "</a>",
		"uname" => icms_member_user_Handler::getUserLink($data["uid"]),
		"status" => $msg_status[$data["status"]],
		"raw" => $data,
	];
}

function is_cc_evaluate($id, $uid, $pass)
{
	$cond = $pass
		? "onepass=" . icms::$xoopsDB->quoteString($pass)
		: "uid=$uid";
	$res = icms::$xoopsDB->query(
		"SELECT count(uid) FROM " .
			CCMES .
			" WHERE msgid=$id AND $cond AND status=" .
			icms::$xoopsDB->quoteString(_STATUS_REPLY),
	);
	[$ret] = icms::$xoopsDB->fetchRow($res);
	return $ret;
}

function cc_notify_mail($tpl, $tags, $users, $from = "")
{
	return mod_ccenter_Mailer::send($tpl, $tags, $users, $from);
}

function check_form_tags($cust, $defs, $desc)
{
	global $icmsConfig;

	switch ($cust) {
		// check only custom form
		case _CC_TPL_NONE:
		case _CC_TPL_NONE_HTML:
			return "";
	}

	$base = dirname(__FILE__) . "/language/";
	$path = $base . $icmsConfig["language"] . "/main.php";
	if (file_exists($path)) {
		include_once $path;
	} else {
		include_once "$base/english/main.php";
	}
	$items = get_form_attribute($defs);
	assign_form_widgets($items);
	$checks = ["{FORM_ATTR}", "{SUBMIT}", "{BACK}", "{CHECK_SCRIPT}"];
	foreach ($items as $item) {
		if (empty($item["type"])) {
			continue;
		}
		$checks[] = "{" . $item["name"] . "}";
	}
	$error = "";
	foreach ($checks as $check) {
		$n = substr_count($desc, $check);
		if ($n != 1) {
			$error .=
				$check .
				": " .
				($n ? _AM_CHECK_DUPLICATE : _AM_CHECK_NOEXIST) .
				"<br />\n";
		}
	}
	return $error;
}

function custom_template($form, $items, $conf = false)
{
	global $icmsConfig;
	$str = $rep = [];
	$hasfile = "";
	foreach ($items as $item) {
		$value = empty($item["input"]) ? "" : $item["input"];
		if (!empty($item["comment"])) {
			$value .= "<span class='note'>" . $item["comment"] . "</span>";
		}
		if (empty($item["name"])) {
			continue;
		}
		$str[] = "{" . $item["name"] . "}";
		$rep[] = $value;
		$fname = $item["field"];
		if ($item["type"] == "file") {
			$hasfile = ' enctype="multipart/form-data"';
		}
	}
	$action = $form["action"];
	if (!empty($form["priuser"])) {
		$priuser = &$form["priuser"];
		$action .= "&amp;" . $priuser["uid"];
		$str[] = "{TO_UNAME}";
		$rep[] = $priuser["uname"];
		$str[] = "{TO_NAME}";
		$rep[] = $priuser["name"];
	}
	$str[] = "{SUBMIT}";
	$str[] = "{BACK}";
	$str[] = "{FORM_ATTR}";
	if ($conf) {
		$out = preg_replace(
			"/\\[desc\\](.*)\\[\\/desc\\]/sU",
			"",
			$form["description"],
		);
		$rep[] =
			"<input type='hidden' name='op' value='store' />" .
			"<input type='submit' value='" .
			_MD_SUBMIT_SEND .
			"' />";
		$rep[] =
			"<input type='submit' name='edit' value='" .
			_MD_SUBMIT_EDIT .
			"' />";
		$rep[] = " action='$action' method='post' name='ccenter'";
		$checkscript = "";
	} else {
		$out = preg_replace(
			"/\\[desc\\](.*)\\[\\/desc\]/sU",
			'\\1',
			$form["description"],
		);
		$rep[] =
			"<input type='hidden' name='op' value='confirm' />" .
			"<input type='submit' value='" .
			_MD_SUBMIT_CONF .
			"' />";
		$rep[] = ""; // back
		$rep[] =
			" action='$action' method='post' name='ccenter' onsubmit='return xoopsFormValidate_ccenter();'" .
			$hasfile;
		$checkscript = empty($form["check_script"])
			? ""
			: $form["check_script"];
	}
	$str[] = "{CHECK_SCRIPT}";
	$rep[] = $checkscript;
	$str[] = "{ICMS_URL}";
	$rep[] = ICMS_URL;
	$str[] = "{ICMS_SITENAME}";
	$rep[] = $icmsConfig["sitename"];
	$str[] = "{TITLE}";
	$rep[] = $form["title"];
	return str_replace($str, $rep, $out);
}

function cc_log_message($formid, $comment, $msgid = 0)
{
	$uid = is_object(icms::$user) ? (int) icms::$user->getVar("uid") : 0;
	icms_getModuleHandler("log", "ccenter")->record($formid, $comment, $msgid, $uid);
	if ($msgid) {
		$msgurl = ICMS_URL . "/modules/" . basename(__DIR__) . "/message.php?id=$msgid";
		$form = icms_getModuleHandler("form", "ccenter")->find($formid);
		$tags = [
			"LOG_STATUS" => $comment,
			"FORM_NAME" => $form ? $form->getVar("title", "n") : "",
			"CHANGE_BY" => icms::$user ? icms::$user->getVar("uname") : "",
			"MSG_ID" => $msgid,
			"MSG_URL" => $msgurl,
		];
		$notification_handler = icms::handler("icms_data_notification");
		$notification_handler->triggerEvent("message", $msgid, "status", $tags);
	}
	return $comment;
}

function cc_log_status($data, $nstat)
{
	global $msg_status;
	$fid = empty($data["fidref"]) ? $data["formid"] : $data["fidref"];
	$log = sprintf(
		_CC_LOG_STATUS,
		$msg_status[$data["status"]],
		$msg_status[$nstat],
	);
	return cc_log_message($fid, $log, $data["msgid"]);
}

define("PAST_TIME_MIN", 3600); // 1hour
define("PAST_TIME_HOUR", 24 * 3600); // 1day
define("PAST_TIME_DAY", 14 * 24 * 3600); // 2week

function myTimestamp($t, $fmt = "l", $unit = "%dmin,%dhour,%dday,past %s")
{
	$past = time() - $t;
	if ($past > PAST_TIME_DAY) {
		return formatTimestamp($t, $fmt);
	}
	$units = explode(",", $unit);
	if ($past < PAST_TIME_MIN) {
		$ret = sprintf($units[0], intval($past / 60));
	} elseif ($past < PAST_TIME_HOUR) {
		$ret = sprintf($units[1], intval($past / 3600)); // hours
		$v = intval(($past % 3600) / 60); // min
		if ($v) {
			$ret .= sprintf($units[0], $v);
		}
	} else {
		$ret = sprintf($units[2], intval($past / 86400)); // days
		$v = intval(($past % 86400) / 3600); // hours
		if ($v) {
			$ret .= sprintf($units[1], $v);
		}
	}
	return sprintf($units[3], $ret);
}

/**
 * Display the module's admin menu.
 *
 * ImpressCMS loads admin/menu.php with include_once. When the system builds its admin menu cache
 * in the same request (cold cache) the file has already been included for another module object,
 * so the current module ends up without menu data and displayAdminMenu() fails on PHP 8
 * (count(null)). Load the menu data ourselves in that case.
 *
 * @param int    $current    index of the active menu entry
 * @param string $breadcrumb breadcrumb trail
 */
function cc_display_admin_menu($current = 0, $breadcrumb = "")
{
	$module = icms::$module;
	if (!is_array($module->getAdminMenu()) || !is_array($module->getAdminHeaderMenu())) {
		// include in an isolated scope: menu.php uses (and unsets) its own $module variable
		$load = static function () {
			$adminmenu = [];
			$headermenu = [];
			include __DIR__ . "/admin/menu.php";
			return [$adminmenu, $headermenu];
		};
		[$module->adminmenu, $module->adminheadermenu] = $load();
	}
	$module->displayAdminMenu($current, $breadcrumb);
}

// adhoc class - not for reuse
class ListCtrl
{
	public $name;
	public $vars;
	public $combo;

	public function __construct($name, $init = [], $combo = "")
	{
		if (empty($combo)) {
			$combo = icms::$module->config["status_combo"];
		}
		$this->name = $name;
		$this->combo = unserialize_text($combo);
		if (!isset($_SESSION["listctrl"])) {
			$_SESSION["listctrl"] = [];
		}
		if (
			!isset($_SESSION["listctrl"][$name]) ||
			(isset($_GET["reset"]) && $_GET["reset"] == "yes")
		) {
			if (!isset($init["stat"])) {
				[$init["stat"]] = array_values($this->combo);
			}
			$_SESSION["listctrl"][$name] = $init;
		}
		$this->vars = &$_SESSION["listctrl"][$name];
		$this->updateVars($_REQUEST);
	}

	function getVar($name)
	{
		return isset($this->vars[$name]) ? $this->vars[$name] : "";
	}

	function setVar($name, $val)
	{
		$this->vars[$name] = $val;
	}

	function getLabels($labels)
	{
		$result = [];
		$orders = $this->getVar("orders");
		foreach ($labels as $k => $v) {
			$lab = ["text" => $v, "name" => $k, "extra" => ""];
			if (isset($this->vars[$k])) {
				// with ctrl
				$n = array_search($k, $orders);
				if (is_int($n)) {
					$val = strtolower($this->getVar($k));
					$lab["value"] = $val;
					$lab["next"] = $val == "desc" ? "asc" : "desc";
					$lab["extra"] = " class='ccord$n'";
				} else {
					$lab["value"] = "none";
					$lab["next"] = "asc";
				}
			}
			$result[] = $lab;
		}
		return $result;
	}

	function updateVars($args)
	{
		$changes = [];
		foreach (array_keys($this->vars) as $k) {
			if (isset($args[$k])) {
				$val = trim($args[$k]);
				if (empty($val)) {
					continue;
				}
				switch ($k) {
					case "stat":
						$val = preg_replace("/[^a-dx\- ]/", "", trim($val));
						break;
					default:
						$val = strtolower($val) == "asc" ? "ASC" : "DESC";
						$orders = $this->getVar("orders");
						if ($k != $orders[0]) {
							$this->setVar("orders", [$k, $orders[0]]);
						}
				}
				$this->setVar($k, $val);
				$changes[$k] = $val;
			}
		}
		return $changes;
	}

	function sqlcondition($fname = "status")
	{
		$stat = $this->getVar("stat");
		if (preg_match("/\s+/", $stat)) {
			return "$fname IN ('" .
				join("','", preg_split("/\s+/", $stat)) .
				"')";
		}
		return "$fname=" . icms::$xoopsDB->quoteString($stat);
	}

	function sqlorder()
	{
		$order = [];
		foreach ($this->getVar("orders") as $name) {
			$order[] = $name . " " . $this->getVar($name);
		}
		if ($order) {
			return " ORDER BY " . join(",", $order);
		}
		return "";
	}

	function renderStat()
	{
		$ctrl = "<select name='stat' onChange='submit();'>\n";
		$stat = $this->getVar("stat");
		foreach ($this->combo as $k => $v) {
			$ck = $v == $stat ? " selected='selected'" : "";
			$ctrl .= "<option value='$v'$ck>$k</option>\n";
		}
		$ctrl .= "</select>";
		return $ctrl;
	}
}

function change_message_status($msgid, $touid, $stat)
{
	global $msg_status;

	$isadmin =
		is_object(icms::$user) &&
		icms::$user->isAdmin(icms::$module->getVar("mid"));
	$own_status = array_slice($msg_status, $isadmin ? 0 : 1, $isadmin ? 5 : 3);
	if (!mod_ccenter_Status::isValid($stat) || empty($own_status[$stat])) {
		return false;
	} // Invalid status
	$handler = icms_getModuleHandler("message", "ccenter");
	$message = $handler->find((int) $msgid);
	if (
		!$message ||
		($touid && (int) $message->getVar("touid", "n") !== (int) $touid) ||
		$message->getVar("status", "n") === $stat
	) {
		return false;
	}
	$data = $message->toRow(); // status before the change, for the log
	if (!$handler->updateFields($msgid, ["status" => $stat, "mtime" => time()])) {
		die("DATABASE error");
	} // unknown error?
	cc_log_status($data, $stat);
	return true;
}

function checkScript($checks, $confirm, $pattern)
{
	global $xoopsTpl;
	$chks = [];
	foreach ($checks as $name => $msg) {
		$pat = $pattern[$name];
		$v = get_attr_value(null, $pat);
		if (!empty($v)) {
			$pat = $v;
		}
		$pat = htmlspecialchars(preg_replace('/([\\\\\"])/', '\\\\$1', $pat));
		$chks[$name] = ["message" => $msg, "pattern" => $pat];
	}
	$tpl = new icms_view_Tpl();
	$tpl->assign("item", [
		"type" => "javascript",
		"confirm" => $confirm,
		"checks" => $chks,
	]);
	return $tpl->fetch("db:" . _CC_WIDGET_TPL);
}

function set_checkvalue(&$form)
{
	$hasfile = false;
	$require = $confirm = $pattern = [];
	foreach ($form["items"] as $item) {
		if (empty($item["field"])) {
			continue;
		}
		$fname = $item["field"];
		$type = $item["type"];
		$lab = htmlspecialchars(strip_tags($item["label"]));
		$check = isset($item["attr"]["check"]) ? $item["attr"]["check"] : "";
		if ($type == "file") {
			$hasfile = true;
		} elseif (preg_match('/_conf$/', $fname)) {
			$confirm[preg_replace('/_conf$/', "", $fname)] = $lab;
		} elseif (!empty($check)) {
			if ($type == "checkbox") {
				$fname .= "[]";
			}
			$require[$fname] = $lab;
			$pattern[$fname] = $check == "require" ? ".+" : $check;
		}
	}

	$form["check_script"] = checkScript($require, $confirm, $pattern);
	$form["confirm"] = $confirm;
	$form["hasfile"] = $hasfile;
}

function render_form(&$form, $op)
{
	global $xoopsTpl;

	$template = "ccenter_form.html";

	set_checkvalue($form);
	$html = 0;
	$br = 1;
	switch ($form["custom"]) {
		case _CC_TPL_FRAME:
			$xoopsTpl->assign([
				"xoops_showcblock" => 0,
				"xoops_showlblock" => 0,
				"xoops_showrblock" => 0,
			]);
		case _CC_TPL_BLOCK:
		case _CC_TPL_FULL:
			$xoopsTpl->assign(
				"content",
				custom_template($form, $form["items"], $op == "confirm"),
			);
			$template = "ccenter_custom.html";
			break;
		case _CC_TPL_NONE_HTML:
			$html = 1;
			$br = 0;
		case _CC_TPL_NONE:
			$str = $rep = [];
			if (!empty($form["priuser"])) {
				$priuser = &$form["priuser"];
				$str[] = "{TO_UNAME}";
				$rep[] = $priuser["uname"];
				$str[] = "{TO_NAME}";
				$rep[] = $priuser["name"];
			}
			$str[] = "{ICMS_URL}";
			$rep[] = ICMS_URL;
			if ($html == 0) {
				$form["desc"] = icms_core_DataFilter::checkVar(
					str_replace($str, $rep, $form["description"]),
					"text",
					"input",
				);
			} else {
				$form["desc"] = icms_core_DataFilter::checkVar(
					str_replace($str, $rep, $form["description"]),
					"html",
					"input",
				);
			}
			$xoopsTpl->assign("op", "confirm");
			$template =
				$op == "confirm" ? "ccenter_confirm.html" : "ccenter_form.html";
	}
	$dirname = basename(dirname(__FILE__));
	$form["cc_url"] = ICMS_URL . "/modules/$dirname";
	// keys the (user overridable) templates read unconditionally
	$form += [
		"desc" => "",
		"submit_opts" => "",
		"note" => "",
		"lang_note" => "",
		"check_script" => "",
		"hasfile" => false,
		"priuser" => ["uid" => 0, "uname" => "", "name" => ""],
	];
	$xoopsTpl->assign("heading", "");
	$xoopsTpl->assign("form", $form);
	return $template;
}

class CcBreadcrumbs
{
	public $moddir;
	public $pairs;

	public function __construct()
	{
		$this->moddir =
			ICMS_URL . "/modules/" . icms::$module->getVar("dirname") . "/";
		$this->pairs = [
			["name" => icms::$module->getVar("name"), "url" => $this->moddir],
		];
	}

	function set($name, $url)
	{
		if ($url !== "" && !preg_match("/^\w+:\/\//", $url)) {
			$url = $this->moddir . $url; // make module-relative links absolute
		}
		$this->pairs[] = [
			"name" => htmlspecialchars(strip_tags($name), ENT_QUOTES),
			"url" => $url,
		];
	}

	function get()
	{
		$ret = $this->pairs;
		$keys = array_keys($ret);
		$ret[array_pop($keys)]["url"] = "";
		return $ret;
	}

	function assign()
	{
		global $xoopsTpl;
		return $xoopsTpl->assign("xoops_breadcrumbs", $this->get());
	}
}
?>
