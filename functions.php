<?php
// ccenter common functions
// $Id: functions.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

require_once dirname(__FILE__) . "/class/FormDefinition.php";

// using tables
define("FORMS", icms::$xoopsDB->prefix("ccenter_form"));
define("CCMES", icms::$xoopsDB->prefix("ccenter_message"));
define("CCLOG", icms::$xoopsDB->prefix("ccenter_log"));

//include_once ICMS_ROOT_PATH."/class/template.php";

define("_STATUS_NONE", "-");
define("_STATUS_ACCEPT", "a");
define("_STATUS_REPLY", "b");
define("_STATUS_CLOSE", "c");
define("_STATUS_DEL", "x");

define("_DB_STORE_LOG", 0); // logging only in db
define("_DB_STORE_YES", 1); // store information in db
define("_DB_STORE_NONE", 2); // query not store in db

define("_CC_WIDGET_TPL", "ccenter_form_widgets.html");

if (!defined("_CC_STATUS_NONE")) {
	$moddir = dirname(__FILE__);
	$lang = $GLOBALS["icmsConfig"]["language"];
	if (!include_once "$moddir/language/$lang/common.php") {
		include_once "$moddir/language/english/common.php";
	}
}
global $msg_status, $export_range;

$msg_status = [
	_STATUS_NONE => _CC_STATUS_NONE,
	_STATUS_ACCEPT => _CC_STATUS_ACCEPT,
	_STATUS_REPLY => _CC_STATUS_REPLY,
	_STATUS_CLOSE => _CC_STATUS_CLOSE,
	_STATUS_DEL => _CC_STATUS_DEL,
];

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
			!empty($GLOBALS["icmsModule"]) &&
			$GLOBALS["icmsModule"]->getVar("dirname") == $mydirname
		) {
			$def_attr = $GLOBALS["icmsModuleConfig"]["def_attrs"];
		} else {
			$module_handler = icms::handler("icms_module");
			$module = &$module_handler->getByDirname($mydirname);
			$config_handler = icms::handler("icms_config");
			$configs = &$config_handler->getConfigsByCat(
				0,
				$module->getVar("mid"),
			);
			$def_attr = $configs["def_attrs"];
		}
		foreach (unserialize_vars($def_attr) as $k => $v) {
			$defs[$k] = $v;
		}
	}
	if ($name == null && !is_null($pri)) {
		// override values
		if (!is_array($pri)) {
			$pri = unserialize_vars($pri);
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

function cc_display_values($vals, $items, $msgid = 0, $add = "")
{
	$values = [];
	foreach ($vals as $key => $val) {
		if (isset($items[$key])) {
			$item = &$items[$key];
			$key = $item["label"]; // replace display value
		} else {
			$item = null;
		}
		if (preg_match('/^file=(.+)$/', $val, $d)) {
			$val = cc_attach_image($msgid, $d[1], false, $add);
		} else {
			if ($item) {
				$opts = &$item["options"];
				switch ($item["type"]) {
					case "radio":
					case "select":
						if (isset($opts[$val])) {
							$val = strip_tags($opts[$val]);
						}
						break;
					case "checkbox":
						$cvals = [];
						foreach (preg_split("/,\s?/", $val) as $v) {
							if (!empty($v)) {
								$cvals[] = isset($opts[$v])
									? strip_tags($opts[$v])
									: $v;
							}
						}
						$val = join(", ", $cvals);
						break;
					default:
						$val = icms_core_DataFilter::checkVar(
							$val,
							"text",
							"output",
						);
						break;
				}
			} else {
				$val = icms_core_DataFilter::checkVar($val, "text", "output");
			}
		}
		$values[$key] = $val;
	}
	return $values;
}

function cc_csv_parse($ln)
{
	$result = [];
	$rec = [];
	while (
		$ln &&
		preg_match('/^("[^"]*(?:""[^"]*)*"|[^,\t\r\n]*)[,\t]?/', $ln, $d)
	) {
		$rec[] = preg_replace(
			'/""/',
			'"',
			preg_replace('/"([^"]*)"$/s', '$1', $d[1]),
		);
		$ln = substr($ln, strlen($d[0]));
		if (preg_match("/^[\r\n]/", $ln)) {
			$result[] = $rec;
			$rec = [];
			$ln = preg_replace("/^[\r\n]\n?/", "", $ln);
		}
	}
	if (count($rec)) {
		$result[] = $rec;
	}
	return $result;
}

function cc_form_definition_schema()
{
	return mod_ccenter_FormDefinition::schema();
}

function cc_is_json_form_definition($defs)
{
	return mod_ccenter_FormDefinition::isJsonDefinition($defs);
}

function cc_parse_legacy_form_attribute($defs, $labels = "", $prefix = "cc")
{
	return mod_ccenter_FormDefinition::parseLegacy($defs, $labels, $prefix);
}

function cc_parse_json_form_attribute($defs, $labels = "", $prefix = "cc")
{
	return mod_ccenter_FormDefinition::parseJson($defs, $labels, $prefix);
}

function cc_convert_items_to_json_defs($items)
{
	return mod_ccenter_FormDefinition::itemsToJson($items);
}

function cc_defs_to_json($defs, $labels = "", $prefix = "cc")
{
	return (new mod_ccenter_FormDefinition($defs, $labels, $prefix))->toJson();
}

function cc_convert_json_defs_to_legacy($defs)
{
	return (new mod_ccenter_FormDefinition($defs))->toLegacy();
}

function get_form_attribute($defs, $labels = "", $prefix = "cc")
{
	return (new mod_ccenter_FormDefinition($defs, $labels, $prefix))->getFields();
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
					$val[$k] = $v = isset($opts[$v])
						? strip_tags($opts[$v])
						: $v;
					$v = htmlspecialchars($v, ENT_QUOTES);
					$input .= sprintf($fmt, $v);
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

function eval_user_value($str)
{
	static $defuser;
	if (empty($defuser)) {
		$defuser = [];
		$user = is_object(icms::$user)
			? icms::$user
			: new icms_member_user_Object();
		$keys = array_keys($user->getVars());
		if (is_object(icms::$user)) {
			foreach ($keys as $k) {
				$defuser["{X_" . strtoupper($k) . "}"] = icms::$user->getVar(
					$k,
					"e",
				);
			}
		} else {
			foreach ($keys as $k) {
				$defuser["{X_" . strtoupper($k) . "}"] = "";
			}
		}
	}
	return str_replace(array_keys($defuser), $defuser, $str);
}

function cc_create_ipf_form_element($item, $value = null, $caption = null)
{
	$fname = isset($item["field"]) ? $item["field"] : "";
	$type = isset($item["type"]) ? $item["type"] : "text";
	$opts = isset($item["options"]) ? $item["options"] : [];
	$attr = isset($item["attr"]) && is_array($item["attr"]) ? $item["attr"] : [];
	if ($caption === null) {
		$caption = isset($item["label"]) ? $item["label"] : "";
	}
	switch ($type) {
		case "select":
			$element = new icms_form_elements_Select($caption, $fname, $value);
			$element->addOptionArray($opts);
			return $element;
		case "radio":
			$element = new icms_form_elements_Radio($caption, $fname, $value);
			$element->addOptionArray($opts);
			return $element;
		case "checkbox":
			$element = new icms_form_elements_Checkbox($caption, $fname, $value);
			$element->addOptionArray($opts);
			return $element;
		case "textarea":
			return new icms_form_elements_TextArea(
				$caption,
				$fname,
				$value,
				get_attr_value($attr, "rows"),
				get_attr_value($attr, "cols"),
			);
		case "hidden":
			return new icms_form_elements_Hidden($fname, $value);
		case "const":
			return new icms_form_elements_Label(
				$caption,
				htmlspecialchars($value, ENT_QUOTES),
			);
		case "file":
			return new icms_form_elements_File(
				$caption,
				$fname,
				get_attr_value($attr, "size"),
			);
		case "mail":
		case "date":
		case "text":
		default:
			return new icms_form_elements_Text(
				$caption,
				$fname,
				get_attr_value($attr, "size"),
				get_attr_value($attr, "maxlength"),
				$value,
			);
	}
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
	$type = $item["type"];
	$attr = isset($item["attr"]) && is_array($item["attr"]) ? $item["attr"] : [];
	if ($type == "const") {
		return htmlspecialchars($item["value"], ENT_QUOTES);
	}
	$element = cc_create_ipf_form_element($item, $item["value"], "");
	$html = $element->render();
	if (($type == "radio" || $type == "checkbox") && isset($item["options"][LABEL_ETC])) {
		$size = get_attr_value($attr, "size");
		$extra = $size ? " size='" . (int) $size . "'" : "";
		$html .=
			" <input name='" .
			$fname .
			"_etc' value='" .
			htmlspecialchars(isset($item["etc_value"]) ? $item["etc_value"] : "", ENT_QUOTES) .
			"' onchange=\"checkedEtcText('" .
			$fname .
			"')\"$extra />";
	}
	if ($type == "file") {
		$v = htmlspecialchars($item["value"], ENT_QUOTES);
		$html .=
			$v .
			"<input name='" .
			$fname .
			"_prev' type='hidden' value='" .
			$v .
			"' />";
	}
	return $html;
}

if (!function_exists("unserialize_vars")) {
	// expand: label=value[,\n](label=value...)
	function unserialize_vars($text, $rev = false)
	{
		if (preg_match("/^\w+: /", $text)) {
			return unserialize_text($text);
		}
		$array = [];
		$text = ltrim($text);
		$pat = ['/""/', '/^"(.*)"$/'];
		$rep = ['"', '$1'];
		$delm = preg_match('/[\n\r]/', $text) ? '\n\r' : ',\n\r'; // allow comma format
		while (
			$text &&
			preg_match("/^(\"[^\"]*\"|[^\"$delm]*)*[$delm]?/", $text, $d)
		) {
			$ln = preg_replace("/[\\s$delm]\$/", "", $d[0]);
			$text = ltrim(substr($text, strlen($d[0])));
			if (preg_match('/^\s*([^=]+)\s*=\s*(.*)$/', $ln, $d)) {
				if (preg_match("/^#/", $d[1])) {
					continue;
				}
				if ($rev) {
					$k = $d[2];
					$v = $d[1];
				} else {
					$k = $d[1];
					$v = $d[2];
				}
				$array[$k] = preg_replace($pat, $rep, $v);
			}
		}
		return $array;
	}
}
if (!function_exists("serialize_text")) {
	function serialize_text($array)
	{
		$text = "";
		foreach ($array as $name => $val) {
			if (is_array($val)) {
				$val = join(", ", $val);
			}
			if (preg_match('/\n/', $val)) {
				$val = preg_replace('/\n\r?/', "\n\t", $val);
			}
			$text .= "$name: $val\n";
		}
		return $text;
	}

	function unserialize_text($text)
	{
		$array = [];
		foreach (preg_split("/\r?\n/", $text) as $ln) {
			if (preg_match("/^\s/", $ln)) {
				$val .= "\n" . substr($ln, 1);
			} elseif (preg_match('/^([^:]*):\s?(.*)$/', $ln, $d)) {
				$name = $d[1];
				$array[$name] = $d[2];
				$val = &$array[$name];
			}
		}
		return $array;
	}
}

function move_attach_file($tmp, $file, $id = 0)
{
	global $icmsConfig;

	$path = ICMS_UPLOAD_PATH . cc_attach_path($id, $file);
	$dir = dirname($path);
	$base = dirname($dir);
	if (!is_dir($base)) {
		if (!mkdir($base)) {
			die("UPLOADS permittion error");
		}
		$fp = fopen("$base/.htaccess", "w");
		fwrite($fp, "deny from all\n"); // not access direct
		fclose($fp);
	}
	if (!is_dir($dir) && !mkdir($dir)) {
		die("UPLOADS permittion error");
	}
	if (empty($tmp)) {
		$tmp = ICMS_UPLOAD_PATH . cc_attach_path(0, $file);
	}
	if (@rename($tmp, $path) || move_uploaded_file($tmp, $path)) {
		return true;
	}
	return false;
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
	$dirname = basename(dirname(__FILE__));
	$dir = $id ? sprintf("%05d", $id) : "work" . substr(session_id(), 0, 8);
	return "/$dirname/$dir" . ($file ? "/$file" : "");
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
	$cgrp = $data["cgroup"];
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
	$res = icms::$xoopsDB->query(
		"SELECT m.*, title FROM " .
			CCMES .
			" m," .
			FORMS .
			" WHERE msgid=" .
			(int) $msgid .
			" AND status<>" .
			icms::$xoopsDB->quoteString(_STATUS_DEL) .
			" AND fidref=formid",
	);

	$data = icms::$xoopsDB->fetchArray($res);
	if (!cc_check_perm($data)) {
		redirect_header(ICMS_URL . "/user.php", 3, _NOPERM);
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
	//$res = icms::$xoopsDB->query("DELETE FROM ".CCMES." WHERE msgid=".$msgid);
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
	// return: error count
	global $icmsConfig;
	$xoopsMailer = new icms_messaging_Handler();
	if (is_array($users)) {
		$err = 0;
		foreach ($users as $u) {
			$err += cc_notify_mail($tpl, $tags, $u, $from);
		}
		return $err;
	}
	if (is_object($users)) {
		switch ($users->getVar("notify_method")) {
			case ICMS_NOTIFICATION_METHOD_PM:
				$xoopsMailer->usePM();
				$sender = is_object(icms::$user)
					? icms::$user
					: new icms_member_user();
				$xoopsMailer->setFromUser($sender);
				break;
			case ICMS_NOTIFICATION_METHOD_EMAIL:
				$xoopsMailer->useMail();
				break;
			case ICMS_NOTIFICATION_METHOD_DISABLE:
				return 0;
			default:
				return 1;
		}
		$xoopsMailer->setToUsers($users);
	} else {
		if (empty($users)) {
			return 0;
		}
		$xoopsMailer->useMail();
		$xoopsMailer->setToEmails($users);
	}

	$xoopsMailer->setFromEmail($from ? $from : $icmsConfig["adminmail"]);
	$xoopsMailer->setFromName(icms::$module->getVar("name"));
	$xoopsMailer->setSubject(_CC_NOTIFY_SUBJ);
	$comment = get_attr_value(null, "reply_comment", "");
	if (get_attr_value(null, "reply_use_comtpl")) {
		$xoopsMailer->setBody($comment);
	} else {
		$xoopsMailer->assign("REPLY_COMMENT", $comment);
		$xoopsMailer->setTemplateDir(template_dir($tpl));
		$xoopsMailer->setTemplate($tpl);
	}
	$xoopsMailer->assign($tags);
	return $xoopsMailer->send() ? 0 : 1;
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
	//global $xoopsUser;
	$uid = is_object(icms::$user) ? icms::$user->getVar("uid") : 0;
	$now = time();
	icms::$xoopsDB->queryF(
		"INSERT INTO " .
			CCLOG .
			"(ltime, fidref, midref, euid, comment)VALUES($now, $formid, $msgid, $uid, " .
			icms::$xoopsDB->quoteString(preg_replace('/\n/', ", ", $comment)) .
			")",
	);
	if ($msgid) {
		$msgurl =
			ICMS_URL .
			"/modules/" .
			basename(dirname(__FILE__)) .
			"/message.php?id=$msgid";
		$res = icms::$xoopsDB->query(
			"SELECT title FROM " . FORMS . " WHERE formid=" . $formid,
		);
		[$title] = icms::$xoopsDB->fetchRow($res);
		$tags = [
			"LOG_STATUS" => $comment,
			"FORM_NAME" => $title,
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

// adhoc class - not for reuse
class ListCtrl
{
	var $name;
	var $vars;
	var $combo;

	function ListCtrl($name, $init = [], $combo = "")
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
			$lab = ["text" => $v, "name" => $k];
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
	if (empty($own_status[$stat])) {
		return false;
	} // Invalid status
	$s = icms::$xoopsDB->quoteString($stat);
	$cond = "msgid=" . $msgid;
	if ($touid) {
		$cond .= " AND touid=" . $touid;
	}
	$res = icms::$xoopsDB->query(
		"SELECT msgid,fidref,status FROM " .
			CCMES .
			" WHERE $cond AND status<>$s",
	);
	if (!$res || icms::$xoopsDB->getRowsNum($res) == 0) {
		return false;
	}
	$data = icms::$xoopsDB->fetchArray($res);
	$now = time();
	$res = icms::$xoopsDB->queryF(
		"UPDATE " . CCMES . " SET status=$s,mtime=$now WHERE msgid=$msgid",
	);
	if (!$res) {
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

function cc_render_default_form_html($form, $op)
{
	$action = $form["cc_url"] . "/" . $form["action"];
	$method = "post";
	$attrs = "";
	if (!empty($form["hasfile"])) {
		$attrs .= ' enctype="multipart/form-data"';
	}
	if ($op != "confirm" && !empty($form["check_script"])) {
		$attrs .= ' onsubmit="return xoopsFormValidate_ccenter();"';
	}
	$submit_value = $op == "confirm" ? _MD_SUBMIT_SEND : _MD_SUBMIT_CONF;
	$op_value = $op == "confirm" ? "store" : "confirm";
	$html = "";
	$html .= '<div class="evform">';
	$html .= "<h2>" . htmlspecialchars($form["title"], ENT_QUOTES) . "</h2>";
	$html .= isset($form["desc"]) ? $form["desc"] : "";
	$html .=
		"<form action=\"" .
		$action .
		"\" id=\"form\" name=\"ccenter\" class=\"ccform\" method=\"" .
		$method .
		"\"$attrs>";
	$html .=
		"<input type='hidden' name='op' value='" .
		htmlspecialchars($op_value, ENT_QUOTES) .
		"' />";
	$html .=
		'<table class="outer" align="center" cellspacing="1" border="0" summary="Contact form items">';
	$html .= '<col style="width: 20%;"/>';
	$n = 0;
	foreach ($form["items"] as $item) {
		if (empty($item["field"])) {
			$comment = isset($item["comment"]) ? $item["comment"] : "";
			if ($comment !== "") {
				$html .=
					"<tr><td colspan='2'>" .
					htmlspecialchars($comment, ENT_QUOTES) .
					"</td></tr>";
			}
			continue;
		}
		$require = !empty($item["attr"]["check"]) ? " require" : "";
		$label = isset($item["label"])
			? htmlspecialchars($item["label"], ENT_QUOTES)
			: "";
		$input = isset($item["input"]) ? $item["input"] : "";
		$comment = isset($item["comment"])
			? htmlspecialchars($item["comment"], ENT_QUOTES)
			: "";
		$rowclass = $n++ % 2 ? "odd" : "even";
		$html .= "<tr class='$rowclass$require'>";
		if ($label) {
			$html .= "<td class='head'>$label</td><td>$input";
		} else {
			$html .= "<td colspan='2'>$input";
		}
		if ($comment) {
			$html .= "<span class='note'>$comment</span>";
		}
		$html .= "</td></tr>";
	}
	$html .= "</table>";
	$html .=
		"<p style='text-align: center;'><input type='submit' value='" .
		$submit_value .
		"'";
	if (!empty($form["submit_opts"])) {
		$html .= " " . $form["submit_opts"];
	}
	$html .= " />";
	if ($op == "confirm") {
		$html .=
			" &nbsp; <input type='submit' name='edit' value='" .
			_MD_SUBMIT_EDIT .
			"' />";
	}
	$html .= "</p></form>";
	if (!empty($form["check_script"]) && $op != "confirm") {
		$html .= $form["check_script"];
	}
	if (
		(isset($form["lang_note"]) && $form["lang_note"]) ||
		(isset($form["note"]) && $form["note"])
	) {
		$html .=
			"<p align='right'>" .
			(isset($form["lang_note"]) ? $form["lang_note"] : "") .
			" " .
			(isset($form["note"]) ? $form["note"] : "") .
			"</p>";
	}
	$html .= "</div>";
	return $html;
}

function render_form(&$form, $op)
{
	global $xoopsTpl;

	$dirname = basename(dirname(__FILE__));
	$form["cc_url"] = ICMS_URL . "/modules/$dirname";
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
			$form["form_html"] = cc_render_default_form_html($form, $op);
			$template = "ccenter_form.html";
	}
	$xoopsTpl->assign("form", $form);
	return $template;
}

class XoopsBreadcrumbs
{
	var $moddir;
	var $pairs;

	function XoopsBreadcrumbs()
	{
		global $xoopsTpl;
		$this->moddir =
			ICMS_URL . "/modules/" . icms::$module->getVar("dirname") . "/";
		$this->pairs = [
			["name" => icms::$module->getVar("name"), "url" => $this->moddir],
		];
	}

	function set($name, $url)
	{
		if (preg_match("/^\w+:\/\//", $url)) {
			$url = $this->moddir . $url;
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
