<?php
// ccenter common functions
// $Id$

// using tables
define("FORMS", icms::$xoopsDB->prefix("ccenter_form"));
define('CCMES', icms::$xoopsDB->prefix('ccenter_message'));
define('CCLOG', icms::$xoopsDB->prefix('ccenter_log'));

//include_once ICMS_ROOT_PATH."/class/template.php";

define('_STATUS_NONE',   '-');
define('_STATUS_ACCEPT', 'a');
define('_STATUS_REPLY',  'b');
define('_STATUS_CLOSE',  'c');
define('_STATUS_DEL',    'x');

define('_DB_STORE_LOG',  0);	// logging only in db
define('_DB_STORE_YES',  1);	// store information in db
define('_DB_STORE_NONE', 2);	// query not store in db

define('_CC_WIDGET_TPL', "ccenter_form_widgets.html");

if (!defined('_CC_STATUS_NONE')) {
    $moddir = __DIR__;
    $lang = $GLOBALS['icmsConfig']['language'];
    if (!include_once("$moddir/language/$lang/common.php")) {
	include_once("$moddir/language/english/common.php");
    }
}
global $msg_status, $export_range;

$msg_status = array(
	_STATUS_NONE  =>_CC_STATUS_NONE,
	_STATUS_ACCEPT=>_CC_STATUS_ACCEPT,
	_STATUS_REPLY =>_CC_STATUS_REPLY,
	_STATUS_CLOSE =>_CC_STATUS_CLOSE,
	_STATUS_DEL   =>_CC_STATUS_DEL);

 $export_range = array(
	'm0'=>_CC_EXPORT_THIS_MONTH,
	'm1'=>_CC_EXPORT_LAST_MONTH,
	'y0'=>_CC_EXPORT_THIS_YEAR,
	'y1'=>_CC_EXPORT_LAST_YEAR,
	'all'=>_CC_EXPORT_ALL);

define('_CC_TPL_NONE',  0);
define('_CC_TPL_BLOCK', 1);
define('_CC_TPL_FULL',  2);
define('_CC_TPL_FRAME', 3);	// obsolete
define('_CC_TPL_NONE_HTML', 4);

define('LABEL_ETC', '*');	// radio, checkbox widget 'etc' text input.
define('OPTION_ATTRS', 'size,rows,maxlength,cols,prop,notify_with_email');

/**
 * Retrieves attribute configuration values with support for defaults and overrides
 *
 * This function manages form attribute configuration by providing a centralized way to
 * retrieve attribute values with fallback to defaults. It supports both static defaults
 * and module-specific configuration overrides.
 *
 * @param mixed $pri Primary configuration data - can be array or serialized string
 * @param string|null $name The attribute name to retrieve
 * @param mixed $value Default value to return if attribute not found
 * @return mixed The attribute value, default value, or null
 * @since 1.0.0
 */
function get_attr_value($pri, $name=null, $value=null) {
    static $defs;		// default option value

    if ($name && is_array($pri) && isset($pri[$name])) {
		return $pri[$name];
	}
    if (!isset($defs)) {
		$defs = array('numeric' => '[-+]?[0-9]+', 'tel' => '\+?[0-9][0-9-,]*[0-9]');
		foreach (explode(',', OPTION_ATTRS) as $key) {
			$defs[$key] = 0;
		}
	// override module config values
	$mydirname = basename(__DIR__);
	if (!empty($GLOBALS['icmsModule']) && $GLOBALS['icmsModule']->getVar('dirname') == $mydirname) {
	    $def_attr = $GLOBALS['icmsModuleConfig']['def_attrs'];
	} else {
	    $module_handler = icms::handler('icms_module');
	    $module =& $module_handler->getByDirname($mydirname);
	    $config_handler = icms::handler('icms_config');
	    $configs =& $config_handler->getConfigsByCat(0, $module->getVar('mid'));
	    $def_attr = $configs['def_attrs'];
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
    if (isset($defs[$name])) return $defs[$name];
    return $value;
}

/**
 * Formats and displays form field values for output
 *
 * Processes form field values and converts them to display-ready format based on
 * field types. Handles special cases like file attachments, radio buttons, checkboxes,
 * and select fields by applying appropriate formatting and escaping.
 *
 * @param array $vals Array of field values keyed by field name
 * @param array $items Array of form field definitions with options and types
 * @param int $msgid Message ID for file attachment handling (default: 0)
 * @param string $add Additional parameters for file URLs (default: "")
 * @return array Formatted values array with display-ready content
 * @since 1.0.0
 */
function cc_display_values($vals, $items, $msgid=0, $add="") {
    $values=array();
    foreach ($vals as $key=>$val) {
		if (isset($items[$key])) {
			$item = &$items[$key];
			$key = $item['label'];	// replace display value
		} else {
			$item = null;
		}
		if (preg_match('/^file=(.+)$/', $val, $d)) {
			$val = cc_attach_image($msgid, $d[1], false, $add);
		} else {
			if ($item) {
				$opts = &$item['options'];
				switch ($item['type']) {
					case 'radio':
						case 'select':
							if (isset($opts[$val])) {
								$val = strip_tags($opts[$val]);
							}
						break;
					case 'checkbox':
						$cvals = array();
						foreach (preg_split('/,\s?/', $val) as $v) {
							if (!empty($v)) {
								$cvals[] = isset($opts[$v]) ? strip_tags($opts[$v]) : $v;
							}
						}
						$val = implode(', ', $cvals);
						break;
					default:
						$val = icms_core_DataFilter::checkVar($val, 'text', 'output');
						break;
				}
			} else {
				$val = icms_core_DataFilter::checkVar($val, 'text', 'output');
			}
		}
		$values[$key] = $val;
    }
    return $values;
}

/**
 * Parses CSV-formatted text into a multidimensional array
 *
 * Handles CSV parsing with support for quoted fields, embedded quotes (doubled),
 * and both comma and tab delimiters. Properly handles multiline records and
 * escaped quotes within quoted fields.
 *
 * @param string $ln CSV text to parse
 * @return array Multidimensional array where each sub-array represents a CSV row
 * @since 1.0.0
 */
function cc_csv_parse($ln) {
    $result = array();
    $rec = array();
    while ($ln && preg_match('/^("[^"]*(?:""[^"]*)*"|[^,\t\r\n]*)[,\t]?/', $ln, $d)) {
		$rec[] = preg_replace('/""/', '"', preg_replace('/"([^"]*)"$/s', '$1', $d[1]));
		$ln = substr($ln, strlen($d[0]));
		if (preg_match("/^[\r\n]/", $ln)) {
			$result[] = $rec;
			$rec = array();
			$ln = preg_replace("/^[\r\n]\n?/", '', $ln);
		}
    }
    if (count($rec)) {
		$result[] = $rec;
	}
    return $result;
}

/**
 * Parses form field definitions and creates structured form attribute arrays
 *
 * Converts CSV-formatted form field definitions into structured arrays containing
 * field metadata including types, options, validation rules, and display attributes.
 * Supports various field types like text, checkbox, radio, textarea, select, etc.
 *
 * @param string $defs CSV-formatted field definitions
 * @param string $labels Serialized label mappings (default: '')
 * @param string $prefix Field name prefix for HTML form elements (default: "cc")
 * @return array Structured array of form field definitions
 * @since 1.0.0
 */
function get_form_attribute($defs, $labels='', $prefix="cc") {
    $labs = unserialize_vars($labels);
    $num = 0;
    $result = array();
    $types = array('text', 'checkbox', 'radio', 'textarea', 'select', 'hidden','const', 'mail', 'file', 'date');
    foreach (cc_csv_parse($defs) as $opts) {
	if (empty($opts)) continue;
	if (preg_match('/^#/', $opts[0])) {
	    $result[] = array('comment'=>substr(implode(',', $opts), 1));
	    continue;
	}
	$name = array_shift($opts);
	if (preg_match('/=(.*)$/', $name, $d)) { // use alternative label
	    $label = $d[1];
	    $name = preg_replace('/=(.*)$/', '', $name);
	} else {
	    $label = isset($labs[$name])?$labs[$name]:$name;
	}
	$type='text';
	$comment='';
	$attr = array();
	if (count($opts) && in_array($opts[0], $types)) {
	    $type = array_shift($opts);
	}
	if (preg_match('/\*$/', $name)) { // syntax convention
	    $attr['check'] = 'require';
	    $name = preg_replace('/\s*\*$/', '', $name);
	    if (defined('_MD_REQUIRE_MARK')) $label = preg_replace('/\s*\*$/', _MD_REQUIRE_MARK, $label);
	}
	while (isset($opts[0]) && (preg_match('/^(size|rows|maxlength|cols|prop)=(\d+)$/', $opts[0], $d) || preg_match('/^(check)=(.+)$/', $opts[0], $d))) {
	    array_shift($opts);
	    $attr[$d[1]] = $d[2];
	}
	$options = array();
	$defs = array();
	if (count($opts)) {
	    while(count($opts) && !preg_match('/^\s*#/', $opts[0])) {
		$v = array_shift($opts);
		$sv = preg_split('/=/', $v, 2);
		if (count($sv)>1) {
		    $k = strip_tags($sv[0]);
		    $sk = preg_replace('/\+$/', '', $k);  // real value
		    if ($k != $sk) $defs[] = $sk;	  // defaults
		    $options[$sk] = $sv[1];
		} else {
		    $k = strip_tags($v);
		    $sk = preg_replace('/\+$/', '', $k);  // real value
		    if ($k != $sk) $defs[] = $sk;	  // defaults
		    $options[$sk] = preg_replace('/\+$/', '', $v);
		}
	    }
	    if (count($opts)) {
		$opts[0] = preg_replace('/^\s*#/','', $opts[0]);
		$comment = implode(',',$opts);
	    }
	}
	if ($type == 'radio') {
	    $defs = $defs?$defs[0]:'';
	} elseif ($type != 'checkbox') {
	    $defs = eval_user_value(implode(',', $options));
	}
	if ($type=='date') {
	    if (empty($defs)) $defs = formatTimestamp(time(), 'Y-m-d');
	} elseif ($type=='textarea') {
	    $attr['rows'] = get_attr_value($attr, 'rows');
	    $attr['cols'] = get_attr_value($attr, 'cols');
	} else {
	    $attr['size'] = get_attr_value($attr, 'size');
	}

	$fname = $prefix.++$num;
	$result[$name] = array(
	    'name'=>$name, 'label'=>$label, 'field'=>$fname,
	    'options'=>$options, 'type'=>$type, 'comment'=>$comment,
	    'attr'=>$attr, 'default'=>$defs);
    }
    return $result;
}

/**
 * Processes and validates POST data for form fields
 *
 * Extracts values from $_POST, applies validation rules, handles special field types
 * (file uploads, checkboxes, radio buttons), and returns validation errors.
 * Modifies the items array by reference to include processed values.
 *
 * @param array &$items Form field definitions array (modified by reference)
 * @return array Array of validation error messages
 * @since 1.0.0
 */
function assign_post_values(&$items) {
    $errors = array();
    foreach ($items as $key => $item) {
		if (empty($item['field'])) continue;
		$name = $item['field'];
		$type = $item['type'];
		$lab = $item['label'];
		$attr = &$item['attr'];
		$check = !empty($attr['check']) ? $attr['check'] : "";
		$val = '';
		if (isset($_POST[$name])) {
			$val = $_POST[$name];
			if (is_array($val)) {
				foreach ($val as $n=>$v) {
					$val[$n] = icms_core_DataFilter::stripSlashesGPC($v);
				}
			} else {
				$val = icms_core_DataFilter::stripSlashesGPC($val);
			}
		}
		switch ($check) {
			case '':
				break;
			case 'require':
				if ($val === '') {
					$errors[] = $lab.": "._MD_REQUIRE_ERR;
				}
				break;
			case 'mail':
				if (!icms_core_DataFilter::checkVar($val, 'email', 0,0)) {
					$errors[] = $lab.": "._MD_ADDRESS_ERR;
				}
				break;
			case 'num':
				$check='numeric';
				default:
					$v = get_attr_value(null, $check);
					if (!empty($v)) {
						$check = $v;
					}
					if (!preg_match('/^'.$check.'$/', $val)) {
						$errors[] = $lab.": ".($val?_MD_REGEXP_ERR:_MD_REQUIRE_ERR);
					}
				break;
		}
		switch ($type) {
			case 'checkbox':
				if (empty($val)) {
					$val = array();
				}
				$idx = array_search(LABEL_ETC, $val);	 // etc
				if (is_int($idx)) {
					$val[$idx] = strip_tags($item['options'][LABEL_ETC])." ".icms_core_DataFilter::stripSlashesGPC($_POST[$name."_etc"]);
				}
				break;
			case 'radio':
				if ($val == LABEL_ETC) {			// etc
					$val = strip_tags($item['options'][LABEL_ETC])." ".icms_core_DataFilter::stripSlashesGPC($_POST[$name."_etc"]);
				}
				break;
			case 'hidden':
				case 'const':
					$val = eval_user_value(implode(',', $item['options']));
				break;
			case 'file':
				$val = '';		// filename
				$upfile = isset($_FILES[$name]) ? $_FILES[$name] : array('name' => '');
				if (isset($_POST[$name."_prev"])) {
					$val = icms_core_DataFilter::stripSlashesGPC($_POST[$name."_prev"]);
					if (!empty($upfile['name'])) {
						unlink(ICMS_UPLOAD_PATH.cc_attach_path(0, $val));
						$val = '';
					}
				}
				if (empty($val)) {
					$val = $upfile['name'];
					if ($val) {
						move_attach_file($upfile['tmp_name'], $val);
					}
				} elseif (isset($_POST[$name])) {	// confirm
					$val = icms_core_DataFilter::stripSlashesGPC($_POST[$name]);
				}
				break;
			case 'mail':
				$name .= '_conf';
				if (!icms_core_DataFilter::checkVar($val, 'email')) {
					$errors[] = $lab.": "._MD_ADDRESS_ERR;
				}
				if (isset($_POST[$name])) {
					if ($val != icms_core_DataFilter::stripSlashesGPC($_POST[$name])) {
						$errors[] = sprintf(_MD_CONF_LABEL, $lab).": "._MD_CONFIRM_ERR;
					}
				}
				break;
		}
		$items[$key]['value'] = $val;
	}
	return $errors;
}

/**
 * Generates HTML widgets for form fields
 *
 * Creates HTML form elements for each field based on field type and configuration.
 * Handles both display mode (confirmation) and input mode. Supports special handling
 * for email confirmation fields and various widget types.
 *
 * @param array &$items Form field definitions array (modified by reference)
 * @param bool $conf Whether to generate confirmation display (true) or input widgets (false)
 * @return array Updated items array with HTML widgets
 * @since 1.0.0
 */
function assign_form_widgets(&$items, $conf=false) {
    $mconf = !$conf;
    $updates = array();
    foreach ($items as $item) {
		if (empty($item['field'])) { // comment only
			$updates[] = $item;
			continue;
		}
		if ($item['type']=='hidden' && !$conf) continue;
		$val =& $item['value'];
		$fname =& $item['field'];
		$opts = $item['options'];
		if ($conf) {
			if (is_array($val)) {
				$fmt = "<input type='hidden' name='{$fname}[]' value='%s' />";
				$input = "";
				foreach ($val as $k=>$v) {
					$val[$k] = $v = isset($opts[$v]) ? strip_tags($opts[$v]) : $v;
					$v = htmlspecialchars($v, ENT_QUOTES);
					$input .= sprintf($fmt, $v);
				}
				$input .= htmlspecialchars(implode(', ', $val), ENT_QUOTES);
			} else {
				$v = htmlspecialchars($val, ENT_QUOTES);
				switch ($item['type']) {
					case 'hidden':
						$input = $v;
						break;
					case 'radio':
						case 'select':
							$input = (isset($opts[$val]) ? strip_tags($opts[$val]) : $v).
							"<input type='hidden' name='$fname' value='$v' />";
						break;
					case 'file':
						$input = cc_attach_image(0, $val, false).
						"<input type='hidden' name='$fname' value='$v' />";
						break;
					default:
						$input = nl2br($v)."<input type='hidden' name='$fname' value='$v' />";
						break;
				}
			}
		} else {
			$input = cc_make_widget($item);
			if ($mconf && isset($item['type']) && $item['type']=='mail'
				&& isset($item['attr']['check']) && $item['attr']['check']=='require') {
				$cfname = $fname.'_conf';
				$citem = array(
					'name'=>sprintf(_MD_CONF_LABEL, $item['name']),
					'label'=>sprintf(_MD_CONF_LABEL, $item['label']),
					'field'=>$cfname, 'type'=>$item['type'],
					'comment'=>_MD_CONF_DESC, 'attr'=>$item['attr']);
				$item['input'] = $input;
				$updates[] = $item;
				$input = cc_make_widget($citem);
				$item = $citem;
				$mconf = false;
			}
		}
		$item['input'] = $input;
		$updates[] = $item;
    }
    $items = $updates;
    return $updates;
}

/**
 * Replaces user placeholder tokens with actual user data
 *
 * Substitutes placeholder tokens like {X_UNAME}, {X_EMAIL} etc. with corresponding
 * values from the current user object. Creates a static cache of user data for
 * performance. Returns empty strings for placeholders when no user is logged in.
 *
 * @param string $str String containing user placeholder tokens
 * @return string String with tokens replaced by actual user values
 * @since 1.0.0
 */
function eval_user_value($str) {
    static $defuser;
    if (empty($defuser)) {
		$defuser = array();
		$user = is_object(icms::$user) ? icms::$user : new icms_member_user_Object;
		$keys = array_keys($user->getVars());
		if (is_object(icms::$user)) {
			foreach ($keys as $k) {
				$defuser['{X_'.strtoupper($k).'}'] = icms::$user->getVar($k, 'e');
			}
		} else {
			foreach ($keys as $k) {
				$defuser['{X_'.strtoupper($k).'}'] = '';
			}
		}
    }
    return str_replace(array_keys($defuser), $defuser, $str);
}

/**
 * Creates HTML widget for a single form field
 *
 * Generates the appropriate HTML form element based on field type and configuration.
 * Handles value assignment from POST data or defaults, processes special "etc" options
 * for radio and checkbox fields, and renders using template system.
 *
 * @param array $item Form field definition array
 * @param array|null $vars Additional template variables (default: null)
 * @return string Rendered HTML widget
 * @since 1.0.0
 */
function cc_make_widget($item, $vars=null) {
    $fname = $item['field'];
    $value = null;
    $type = $item['type'];
    $options = &$item['options'];
    if (isset($_POST[$fname])) {
		$value = &$_POST[$fname];
		if (!is_array($value)) {
			$value = icms_core_DataFilter::stripSlashesGPC($value);
		}
    } else {
		if (isset($item['default'])) {
			$value = $item['default'];
		}
    }
    if (isset($options)) {
		if (isset($options[LABEL_ETC])) {
			$ereg = '/^'.preg_quote(strip_tags($options[LABEL_ETC]), '/').'\s+/';
			if ($type == 'checkbox') {
				if (is_array($value)) {
					foreach ($value as $key => $val) {
						if (preg_match($ereg, $val)) {
							$item['etc_value'] = preg_replace($ereg, '', $val);
							$value[$key] = LABEL_ETC;
						}
					}
				}
			} else {
				if (preg_match($ereg, $value)) {
					$item['etc_value'] = preg_replace($ereg, '', $value);
					$value = LABEL_ETC;
				}
			}
		}
    }
    $item['value'] = $value;
    $tpl = new icms_view_Tpl;
    $tpl->assign('item', $item);
    if (isset($vars)) {
		$tpl->assign($vars);
	}
    return $tpl->fetch('db:'._CC_WIDGET_TPL);
}


if (!function_exists("unserialize_vars")) {
    /**
     * Parses serialized key-value pairs into an associative array
     *
     * Converts text in format "label=value[,\n](label=value...)" into an associative array.
     * Supports both comma-separated and newline-separated formats. Handles quoted values
     * and can reverse key-value pairs when $rev is true.
     *
     * @param string $text Serialized text to parse
     * @param bool $rev Whether to reverse key-value pairs (default: false)
     * @return array Associative array of parsed key-value pairs
     * @since 1.0.0
     */
    function unserialize_vars($text,$rev=false) {
	if (preg_match("/^\w+: /", $text)) return unserialize_text($text);
	$array = array();
	$text = ltrim($text);
	$pat = array('/""/', '/^"(.*)"$/');
	$rep = array('"', '$1');
	$delm = preg_match('/[\n\r]/', $text)?'\n\r':',\n\r'; // allow comma format
	while ($text && preg_match("/^(\"[^\"]*\"|[^\"$delm]*)*[$delm]?/", $text, $d)) {
	    $ln = preg_replace("/[\\s$delm]\$/", '', $d[0]);
	    $text = ltrim(substr($text, strlen($d[0])));
	    if (preg_match('/^\s*([^=]+)\s*=\s*(.*)$/', $ln, $d)) {
		if (preg_match('/^#/', $d[1])) continue;
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
    /**
     * Converts an associative array to serialized text format
     *
     * Creates text in "name: value" format with each pair on a new line.
     * Handles array values by joining with commas and preserves multiline
     * values with proper indentation.
     *
     * @param array $array Associative array to serialize
     * @return string Serialized text representation
     * @since 1.0.0
     */
    function serialize_text($array) {
	$text = '';
	foreach ($array as $name => $val) {
	    if (is_array($val)) $val = implode(', ', $val);
	    if (preg_match('/\n/', $val)) {
		$val = preg_replace('/\n\r?/', "\n\t", $val);
	    }
	    $text .= "$name: $val\n";
	}
	return $text;
    }

    /**
     * Parses text in "name: value" format into an associative array
     *
     * Converts serialized text format back to an associative array.
     * Handles multiline values with proper indentation and maintains
     * the structure created by serialize_text().
     *
     * @param string $text Serialized text to parse
     * @return array Associative array of parsed data
     * @since 1.0.0
     */
    function unserialize_text($text) {
	$array = array();
	foreach (preg_split("/\r?\n/", $text) as $ln) {
	    if (preg_match('/^\s/', $ln)) {
		$val .= "\n".substr($ln, 1);
	    } elseif (preg_match('/^([^:]*):\s?(.*)$/', $ln, $d)) {
		$name = $d[1];
		$array[$name] = $d[2];
		$val =& $array[$name];
	    }
	}
	return $array;
    }
}

/**
 * Moves uploaded file to the appropriate attachment directory
 *
 * Creates necessary directory structure and moves uploaded files to the correct
 * location. Sets up .htaccess protection for upload directories and handles
 * both temporary uploads and permanent file storage.
 *
 * @param string $tmp Temporary file path or source file path
 * @param string $file Target filename
 * @param int $id Message ID for directory structure (default: 0)
 * @return bool True on success, false on failure
 * @since 1.0.0
 */
function move_attach_file($tmp, $file, $id=0) {
    global $icmsConfig;

    $path = ICMS_UPLOAD_PATH.cc_attach_path($id, $file);
    $dir = dirname($path);
    $base = dirname($dir);
    if (!is_dir($base)) {
	if (!mkdir($base)) die("UPLOADS permittion error");
	$fp = fopen("$base/.htaccess", "w");
	fwrite($fp, "deny from all\n");	// not access direct
	fclose($fp);
    }
    if (!is_dir($dir) && !mkdir($dir)) die("UPLOADS permittion error");
    if (empty($tmp)) $tmp = ICMS_UPLOAD_PATH.cc_attach_path(0, $file);
    if (@rename($tmp, $path) || move_uploaded_file($tmp, $path)) return true;
    return false;
}

if (!function_exists("template_dir")) {
    /**
     * Returns the appropriate template directory path based on language
     *
     * Determines the correct mail template directory path based on the current
     * language setting. Falls back to English if the language-specific directory
     * doesn't exist.
     *
     * @param string $file Optional filename to append to path (default: '')
     * @return string Template directory path
     * @since 1.0.0
     */
    function template_dir($file='') {
	global $icmsConfig;
	$lang = $icmsConfig['language'];
	$dir = dirname(__FILE__).'/language/%s/mail_template/%s';
	$path = sprintf($dir,$lang, $file);
	if (file_exists($path)) {
	    $path = sprintf($dir,$lang, '');
	} else {
	    $path = sprintf($dir,'english', '');
	}
	return $path;
    }
}

/**
 * Generates the relative path for attachment files
 *
 * Creates a standardized path structure for file attachments based on message ID
 * or session ID for temporary files. Uses zero-padded message IDs for organization.
 *
 * @param int $id Message ID (0 for temporary files)
 * @param string $file Filename to append to path
 * @return string Relative path for the attachment
 * @since 1.0.0
 */
function cc_attach_path($id, $file) {
    $dirname = basename(dirname(__FILE__));
    $dir = $id?sprintf("%05d", $id):"work".substr(session_id(), 0, 8);
    return "/$dirname/$dir".($file?"/$file":"");
}

/**
 * Generates HTML for displaying attached files (images or download links)
 *
 * Creates appropriate HTML output for file attachments. For images, generates
 * img tags with size constraints. For other files, creates download links with
 * file size information.
 *
 * @param int $id Message ID for the attachment
 * @param string $file Filename of the attachment
 * @param bool $urlonly Whether to return only the URL (default: false)
 * @param string $add Additional URL parameters (default: '')
 * @return string HTML for image display or download link, or just URL if $urlonly is true
 * @since 1.0.0
 */
function cc_attach_image($id, $file, $urlonly=false, $add='') {
    if (empty($file)) return "";
    $rurl = "file.php?".($id?"id=$id&":"")."file=".urlencode($file).($add?"&$add":"");
    if ($urlonly) return ICMS_URL."/modules/".basename(dirname(__FILE__))."/$rurl";
    $path = ICMS_UPLOAD_PATH.cc_attach_path($id, $file);
    $xy = getimagesize($path);
    if ($xy) {
	if ($xy[0]>$xy[1] && $xy[0]>300) $extra = " width='300'";
	elseif ($xy[1]>300) $extra = " height='300'";
	else $extra = "";
	$extra .= " alt='".htmlspecialchars($file, ENT_QUOTES)."'";
	return "<img src='$rurl' class='myphoto' $extra />";
    } else {
	$size = return_unit_bytes(filesize($path));
	return "<a href='$rurl' class='myattach'>$file ($size)</a>";
    }
}

/**
 * Formats file size in human-readable units
 *
 * Converts byte values to appropriate units (bytes, KB, MB, GB) with
 * proper formatting and localization support. Uses 1024-based calculations
 * for binary file sizes.
 *
 * @param int $size File size in bytes
 * @return string Formatted file size with appropriate unit
 * @since 1.0.0
 */
function return_unit_bytes($size) {
    $unit = defined('_MD_BYTE_UNIT')?_MD_BYTE_UNIT:"bytes";
    if ($size<10*1024) return number_format($size);
    $size /= 1024;
    if ($size<10*1024) return round($size, 1).'K'.$unit;
    $size /= 1024;
    if ($size<10*1024) return round($size, 1).'M'.$unit;
    $size /= 1024;
    return round($size, 1).'G'.$unit;
}

/**
 * Checks user permissions for accessing message data
 *
 * Validates user access rights based on multiple criteria:
 * 1. One-time password match
 * 2. Administrator privileges
 * 3. User group membership
 * 4. Message ownership (sender or recipient)
 *
 * @param array $data Message data containing permission fields
 * @return bool True if user has access permission, false otherwise
 * @since 1.0.0
 */
function cc_check_perm($data) {

    $uid = is_object(icms::$user)?icms::$user->getVar('uid'):0;

    $pass = isset($_GET['p'])?$_GET['p']:"";
    if ($pass) {
	$_SESSION['onepass'] = $pass;
    } else {
	$pass = (empty($_SESSION['onepass'])?"":$_SESSION['onepass']);
    }
    if (!empty($data['onepass']) && $data['onepass']==$pass) return true;

    $mid = is_object(icms::$module)?icms::$module->getVar('mid'):0;
    if ($uid && icms::$user->isAdmin($mid)) return true;
    $cgrp = $data['cgroup'];
    if ($cgrp && $uid && in_array($cgrp, icms::$user->getGroups())) return true;
    if ($uid && ($data['uid']==$uid || $data['touid'] == $uid)) return true;
    return false;
}

/**
 * Retrieves message data with permission checking
 *
 * Fetches message data from database and validates user permissions.
 * Redirects to login page if user lacks access rights. Joins message
 * and form tables to get complete message information.
 *
 * @param int $msgid Message ID to retrieve
 * @return array Message data array with form title
 * @throws Exception Redirects to user.php if permission denied
 * @since 1.0.0
 */
function cc_get_message($msgid) {
    $res = icms::$xoopsDB->query("SELECT m.*, title FROM ".CCMES." m,".FORMS." WHERE msgid=".(int)$msgid." AND status<>".icms::$xoopsDB->quoteString(_STATUS_DEL)." AND fidref=formid");

    $data = icms::$xoopsDB->fetchArray($res);
    if (!cc_check_perm($data)) {
	redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
	exit;
    }
    return $data;
}

/**
 * Validates comment association with a message
 *
 * Verifies that a comment belongs to the specified message and module.
 * Used for security validation before allowing comment operations.
 *
 * @param int $msgid Message ID to check against
 * @param int $com_id Comment ID to validate
 * @return int|null Comment item ID if valid, null if invalid
 * @since 1.0.0
 */
function cc_check_comment($msgid, $com_id) {

    $res = icms::$xoopsDB->query("SELECT com_itemid FROM ".icms::$xoopsDB->prefix('xoopscomments')." WHERE com_id=".(int)$com_id." AND com_itemid=".(int)$msgid." AND com_modid=".icms::$module->getVar('mid'));
    list($com_itemid) = icms::$xoopsDB->fetchRow($res);
    return $com_itemid;
}

/**
 * Generates a one-time access ticket/password
 *
 * Creates a time-based, single-use access token using MD5 hashing
 * and base64 encoding. Used for secure, temporary access to messages
 * without requiring user authentication.
 *
 * @param string $genseed Seed string for ticket generation (default: "mypasswdbasestring")
 * @return string 8-character alphanumeric access ticket
 * @since 1.0.0
 */
function cc_onetime_ticket($genseed="mypasswdbasestring") {
    return substr(preg_replace('/[^a-zA-Z0-9]/', '', base64_encode(pack("H*",md5($genseed.time())))), 0, 8);
}

//function cc_delete_message($msgid) {
//    //$res = icms::$xoopsDB->query("DELETE FROM ".CCMES." WHERE msgid=".$msgid);
//    $dir = ICMS_UPLOAD_PATH.cc_attach_path(0,'');
//    $dh = opendir($dir);
//    while ($file = readdir($dh)) {
//	if ($file==".." || $file==".") continue;
//	$path = "$dir/$file";
//	unlink($path);
//    }
//}

/**
 * Formats message data for display in lists
 *
 * Converts raw message data into a standardized format for display
 * in message lists. Includes formatted timestamps, user links,
 * status information, and clickable titles.
 *
 * @param array $data Raw message data from database
 * @param string $link Base link for message URLs (default: "message.php")
 * @return array Formatted message entry with display-ready fields
 * @since 1.0.0
 */
function cc_message_entry($data, $link="message.php") {
    global $msg_status;
    $id = $data['msgid'];
    return  array(
	'msgid'=>$id,
	'mdate'=>myTimestamp($data['mtime'], 'm', _MD_TIME_UNIT),
	'title'=>"<a href='message.php?id=$id'>".$data['title']."</a>",
	'uname'=> icms_member_user_Handler::getUserLink($data['uid']),
	'status'=>$msg_status[$data['status']],
	'raw'=>$data);
}

/**
 * Checks if a message can be evaluated by the specified user
 *
 * Determines whether a user has permission to evaluate/rate a message
 * based on user ID or one-time password. Only messages with reply status
 * can be evaluated.
 *
 * @param int $id Message ID to check
 * @param int $uid User ID for permission check
 * @param string $pass One-time password for access
 * @return int Count of matching records (1 if can evaluate, 0 if cannot)
 * @since 1.0.0
 */
function is_cc_evaluate($id, $uid, $pass) {
    $cond = $pass?'onepass='.icms::$xoopsDB->quoteString($pass):"uid=$uid";
    $res = icms::$xoopsDB->query("SELECT count(uid) FROM ".CCMES." WHERE msgid=$id AND $cond AND status=".icms::$xoopsDB->quoteString(_STATUS_REPLY));
    list($ret) = icms::$xoopsDB->fetchRow($res);
    return $ret;
}

/**
 * Sends notification emails to users
 *
 * Handles sending notification emails using the ICMS messaging system.
 * Supports both email and private message delivery based on user preferences.
 * Can send to individual users, arrays of users, or email addresses.
 *
 * @param string $tpl Email template filename
 * @param array $tags Template variables for email content
 * @param mixed $users User object, array of users, or email address string
 * @param string $from Sender email address (default: uses admin email)
 * @return int Number of failed email sends (0 = all successful)
 * @since 1.0.0
 */
function cc_notify_mail($tpl, $tags, $users, $from="") {
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
		switch ($users->getVar('notify_method')) {
	        case ICMS_NOTIFICATION_METHOD_PM:
	            $xoopsMailer->usePM();
		    	$sender = is_object(icms::$user)?icms::$user:new icms_member_user;
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
	if (empty($users)) return 0;
	$xoopsMailer->useMail();
	$xoopsMailer->setToEmails($users);
    }

    $xoopsMailer->setFromEmail($from?$from:$icmsConfig['adminmail']);
    $xoopsMailer->setFromName(icms::$module->getVar('name'));
    $xoopsMailer->setSubject(_CC_NOTIFY_SUBJ);
    $comment = get_attr_value(null, 'reply_comment', '');
    if (get_attr_value(null, 'reply_use_comtpl')) {
	$xoopsMailer->setBody($comment);
    } else {
	$xoopsMailer->assign('REPLY_COMMENT', $comment);
	$xoopsMailer->setTemplateDir(template_dir($tpl));
	$xoopsMailer->setTemplate($tpl);
    }
    $xoopsMailer->assign($tags);
    return $xoopsMailer->send()?0:1;
}

/**
 * Validates form template tags for completeness and correctness
 *
 * Checks custom form templates to ensure all required tags are present
 * exactly once. Validates that form attribute tags, field tags, and
 * control tags are properly included in the template description.
 *
 * @param int $cust Custom template type constant
 * @param string $defs Form field definitions
 * @param string $desc Template description to validate
 * @return string Error message string (empty if no errors)
 * @since 1.0.0
 */
function check_form_tags($cust,$defs, $desc) {
    global $icmsConfig;

    switch ($cust) {		// check only custom form
    case _CC_TPL_NONE:
    case _CC_TPL_NONE_HTML:
	return '';
    }

    $base = dirname(__FILE__).'/language/';
    $path = $base.$icmsConfig['language'].'/main.php';
    if (file_exists($path)) include_once($path);
    else include_once("$base/english/main.php");
    $items = get_form_attribute($defs);
    assign_form_widgets($items);
    $checks = array('{FORM_ATTR}', '{SUBMIT}', '{BACK}', '{CHECK_SCRIPT}');
    foreach ($items as $item) {
	if (empty($item['type'])) continue;
	$checks[] = '{'.$item['name'].'}';
    }
    $error = "";
    foreach ($checks as $check) {
	$n = substr_count($desc, $check);
	if ($n!=1) {
	    $error .= $check.": ".($n?_AM_CHECK_DUPLICATE:_AM_CHECK_NOEXIST)."<br />\n";
	}
    }
    return $error;
}

/**
 * Processes custom form templates with dynamic content replacement
 *
 * Replaces template placeholders with actual form content, handles
 * confirmation vs. input modes, and manages form attributes like
 * action URLs, file upload settings, and JavaScript validation.
 *
 * @param array $form Form configuration array
 * @param array $items Form field items with generated widgets
 * @param bool $conf Whether in confirmation mode (default: false)
 * @return string Processed template with all placeholders replaced
 * @since 1.0.0
 */
function custom_template($form, $items, $conf=false) {
    global $icmsConfig;
    $str = $rep = array();
    $hasfile = "";
    foreach ($items as $item) {
	$value = empty($item['input'])?"":$item['input'];
	if (!empty($item['comment'])) {
	    $value .= "<span class='note'>".$item['comment']."</span>";
	}
	if (empty($item['name'])) continue;
	$str[] = '{'.$item['name'].'}';
	$rep[] = $value;
	$fname = $item['field'];
	if ($item['type']=='file') {
	    $hasfile = ' enctype="multipart/form-data"';
	}
    }
    $action = $form['action'];
    if (!empty($form['priuser'])) {
	$priuser =& $form['priuser'];
	$action .= '&amp;'.$priuser['uid'];
	$str[] = "{TO_UNAME}";
	$rep[] = $priuser['uname'];
	$str[] = "{TO_NAME}";
	$rep[] = $priuser['name'];
    }
    $str[] = "{SUBMIT}";
    $str[] = "{BACK}";
    $str[] = "{FORM_ATTR}";
    if ($conf) {
	$out = preg_replace('/\\[desc\\](.*)\\[\\/desc\\]/sU', '', $form['description']);
	$rep[] = "<input type='hidden' name='op' value='store' />".
	    "<input type='submit' value='"._MD_SUBMIT_SEND."' />";
	$rep[] = "<input type='submit' name='edit' value='"._MD_SUBMIT_EDIT."' />";
	$rep[] = " action='$action' method='post' name='ccenter'";
	$checkscript = "";
    } else {
	$out = preg_replace('/\\[desc\\](.*)\\[\\/desc\]/sU', '\\1', $form['description']);
	$rep[] = "<input type='hidden' name='op' value='confirm' />".
	    "<input type='submit' value='"._MD_SUBMIT_CONF."' />";
	$rep[] = "";		// back
	$rep[] = " action='$action' method='post' name='ccenter' onsubmit='return xoopsFormValidate_ccenter();'".$hasfile;
	$checkscript = empty($form['check_script'])?"":$form['check_script'];
    }
    $str[] = "{CHECK_SCRIPT}";
    $rep[] = $checkscript;
    $str[] = "{ICMS_URL}";
    $rep[] = ICMS_URL;
    $str[] = "{ICMS_SITENAME}";
    $rep[] = $icmsConfig['sitename'];
    $str[] = "{TITLE}";
    $rep[] = $form['title'];
    return str_replace($str, $rep, $out);
}

/**
 * Logs message activity and triggers notifications
 *
 * Records message-related activities in the log table and triggers
 * notification events for status changes. Handles both general logging
 * and message-specific notifications with proper tag substitution.
 *
 * @param int $formid Form ID associated with the message
 * @param string $comment Log comment/description
 * @param int $msgid Message ID (0 for general form logs)
 * @return string The logged comment
 * @since 1.0.0
 */
function cc_log_message($formid, $comment, $msgid=0) {
    //global $xoopsUser;
    $uid = is_object(icms::$user)?icms::$user->getVar('uid'):0;
    $now = time();
    icms::$xoopsDB->queryF("INSERT INTO ".CCLOG."(ltime, fidref, midref, euid, comment)VALUES($now, $formid, $msgid, $uid, ".icms::$xoopsDB->quoteString(preg_replace('/\n/', ", ", $comment)).")");
    if ($msgid) {
	$msgurl = ICMS_URL."/modules/".basename(dirname(__FILE__))."/message.php?id=$msgid";
	$res = icms::$xoopsDB->query("SELECT title FROM ".FORMS." WHERE formid=".$formid);
	list($title) = icms::$xoopsDB->fetchRow($res);
	$tags = array('LOG_STATUS'=>$comment,
		      'FORM_NAME'=>$title,
		      'CHANGE_BY'=>icms::$user?icms::$user->getVar('uname'):"",
		      'MSG_ID'=>$msgid,
		      'MSG_URL'=>$msgurl);
	$notification_handler = icms::handler('icms_data_notification');
	$notification_handler->triggerEvent('message', $msgid, 'status', $tags);
    }
    return $comment;
}

/**
 * Logs message status changes
 *
 * Creates a formatted log entry for message status transitions.
 * Uses global status message mappings to create human-readable
 * status change descriptions.
 *
 * @param array $data Message data containing current status and IDs
 * @param string $nstat New status code
 * @return string The logged status change message
 * @since 1.0.0
 */
function cc_log_status($data, $nstat) {
    global $msg_status;
    $fid = empty($data['fidref'])?$data['formid']:$data['fidref'];
    $log = sprintf(_CC_LOG_STATUS, $msg_status[$data['status']], $msg_status[$nstat]);
    return cc_log_message($fid, $log, $data['msgid']);
}

define('PAST_TIME_MIN', 3600);	     // 1hour
define('PAST_TIME_HOUR', 24*3600);   // 1day
define('PAST_TIME_DAY', 14*24*3600); // 2week

/**
 * Formats timestamps with relative time display
 *
 * Converts timestamps to human-readable relative time format (e.g., "2 hours ago")
 * for recent times, or absolute format for older times. Supports customizable
 * time units and format strings.
 *
 * @param int $t Unix timestamp to format
 * @param string $fmt Date format for old timestamps (default: "l")
 * @param string $unit Comma-separated format strings for time units (default: "%dmin,%dhour,%dday,past %s")
 * @return string Formatted time string
 * @since 1.0.0
 */
function myTimestamp($t, $fmt="l", $unit="%dmin,%dhour,%dday,past %s") {
    $past = time()-$t;
    if ($past > PAST_TIME_DAY) {
	return formatTimestamp($t, $fmt);
    }
    $units = explode(',', $unit);
    if ($past < PAST_TIME_MIN) {
	$ret = sprintf($units[0], (int)($past / 60));
    } elseif ($past < PAST_TIME_HOUR) {
	$ret = sprintf($units[1], (int)($past / 3600)); // hours
	$v = (int)(($past % 3600) / 60);	     // min
	if ($v) $ret .= sprintf($units[0], $v);
    } else {
	$ret = sprintf($units[2], (int)($past / 86400)); // days
	$v = (int)(($past % 86400) / 3600);    // hours
	if ($v) $ret .= sprintf($units[1], $v);
    }
    return sprintf($units[3], $ret);
}

/**
 * List control class for managing sortable, filterable message lists
 *
 * Provides functionality for managing list display controls including
 * status filtering, column sorting, and session-based state persistence.
 * Designed specifically for message list management in the ccenter module.
 *
 * @since 1.0.0
 */
class ListCtrl {
    var $name;
    var $vars;
    var $combo;

    /**
     * Constructor for ListCtrl
     *
     * Initializes list control with session-based state management.
     * Sets up default values and loads configuration from module settings.
     *
     * @param string $name Unique identifier for this list control
     * @param array $init Default initialization values
     * @param string $combo Status combo configuration (uses module config if empty)
     * @since 1.0.0
     */
    function ListCtrl($name, $init=array(), $combo='') {
	if (empty($combo)) {

	    $combo = icms::$module->config['status_combo'];
	}
	$this->name = $name;
	$this->combo = unserialize_text($combo);
	if (!isset($_SESSION['listctrl'])) $_SESSION['listctrl'] = array();
	if (!isset($_SESSION['listctrl'][$name]) ||
	    (isset($_GET['reset'])&&$_GET['reset']=='yes')) {
	    if (!isset($init['stat'])) {
		list($init['stat']) = array_values($this->combo);
	    }
	    $_SESSION['listctrl'][$name] = $init;
	}
	$this->vars =& $_SESSION['listctrl'][$name];
	$this->updateVars($_REQUEST);
    }

    /**
     * Retrieves a control variable value
     *
     * @param string $name Variable name to retrieve
     * @return mixed Variable value or empty string if not set
     * @since 1.0.0
     */
    function getVar($name) {
	return isset($this->vars[$name])?$this->vars[$name]:"";
    }

    /**
     * Sets a control variable value
     *
     * @param string $name Variable name to set
     * @param mixed $val Value to assign
     * @since 1.0.0
     */
    function setVar($name, $val) { $this->vars[$name]=$val; }

    /**
     * Generates sortable column labels with sorting indicators
     *
     * Creates label arrays with sorting state information for table headers.
     * Includes CSS classes and next sort direction for interactive sorting.
     *
     * @param array $labels Array of column labels keyed by field name
     * @return array Enhanced label array with sorting metadata
     * @since 1.0.0
     */
    function getLabels($labels) {
	$result = array();
	$orders = $this->getVar('orders');
	foreach ($labels as $k => $v) {
	    $lab = array('text'=>$v, 'name'=>$k);
	    if (isset($this->vars[$k])) { // with ctrl
		$n = array_search($k, $orders);
		if (is_int($n)) {
		    $val = strtolower($this->getVar($k));
		    $lab['value'] = $val;
		    $lab['next'] = $val=='desc'?'asc':'desc';
		    $lab['extra'] = " class='ccord$n'";
		} else {
		    $lab['value'] = 'none';
		    $lab['next'] = 'asc';
		}
	    }
	    $result[] = $lab;
	}
	return $result;
    }

    /**
     * Updates control variables from request parameters
     *
     * Processes form/URL parameters to update sorting and filtering state.
     * Handles status filtering and column sorting with proper validation.
     *
     * @param array $args Request parameters ($_REQUEST, $_GET, $_POST)
     * @return array Array of changed variables
     * @since 1.0.0
     */
    function updateVars($args) {
	$changes = array();
	foreach (array_keys($this->vars) as $k) {
	    if (isset($args[$k])) {
		$val = trim($args[$k]);
		if (empty($val)) continue;
		switch ($k) {
		case 'stat':
		    $val = preg_replace('/[^a-dx\- ]/', '', trim($val));
		    break;
		default:
		    $val = strtolower($val)=='asc'?'ASC':'DESC';
		    $orders = $this->getVar('orders');
		    if ($k != $orders[0]) {
			$this->setVar('orders', array($k, $orders[0]));
		    }
		}
		$this->setVar($k, $val);
		$changes[$k] = $val;
	    }
	}
	return $changes;
    }

    /**
     * Generates SQL WHERE condition for status filtering
     *
     * Creates appropriate SQL condition based on current status filter.
     * Handles both single status and multiple status filtering.
     *
     * @param string $fname Database field name for status (default: 'status')
     * @return string SQL WHERE condition
     * @since 1.0.0
     */
    function sqlcondition($fname='status') {
	$stat = $this->getVar('stat');
	if (preg_match('/\s+/', $stat)) {
	    return "$fname IN ('".implode("','", preg_split('/\s+/',$stat))."')";
	}
	return "$fname=".icms::$xoopsDB->quoteString($stat);
    }

    /**
     * Generates SQL ORDER BY clause from current sort settings
     *
     * Creates SQL ordering clause based on current column sort configuration.
     * Supports multiple column sorting with proper direction handling.
     *
     * @return string SQL ORDER BY clause (including "ORDER BY" keywords)
     * @since 1.0.0
     */
    function sqlorder() {
	$order = array();
	foreach ($this->getVar('orders') as $name) {
	    $order[] = $name." ".$this->getVar($name);
	}
	if ($order) return " ORDER BY ".implode(',', $order);
	return "";
    }

    /**
     * Renders HTML select dropdown for status filtering
     *
     * Creates an HTML select element with status options and JavaScript
     * auto-submit functionality. Shows current selection state.
     *
     * @return string HTML select element for status filtering
     * @since 1.0.0
     */
    function renderStat() {
	$ctrl = "<select name='stat' onChange='submit();'>\n";
	$stat = $this->getVar('stat');
	foreach ($this->combo as $k => $v) {
	    $ck = $v == $stat?" selected='selected'":"";
	    $ctrl .= "<option value='$v'$ck>$k</option>\n";
	}
	$ctrl .= "</select>";
	return $ctrl;
    }
}

/**
 * Changes the status of a message with permission checking
 *
 * Updates message status in database after validating user permissions
 * and status validity. Logs the status change and updates modification time.
 *
 * @param int $msgid Message ID to update
 * @param int $touid Target user ID for permission check
 * @param string $stat New status code
 * @return bool True if status was changed, false if failed or no change needed
 * @since 1.0.0
 */
function change_message_status($msgid, $touid, $stat) {
    global $msg_status;

    $isadmin = is_object(icms::$user) && icms::$user->isAdmin(icms::$module->getVar('mid'));
    $own_status = array_slice($msg_status, $isadmin?0:1, $isadmin?5:3);
    if (empty($own_status[$stat])) return false; // Invalid status
    $s = icms::$xoopsDB->quoteString($stat);
    $cond = "msgid=".$msgid;
    if ($touid) $cond .= " AND touid=".$touid;
    $res = icms::$xoopsDB->query("SELECT msgid,fidref,status FROM ".CCMES." WHERE $cond AND status<>$s");
    if (!$res || icms::$xoopsDB->getRowsNum($res)==0) return false;
    $data = icms::$xoopsDB->fetchArray($res);
    $now = time();
    $res = icms::$xoopsDB->queryF("UPDATE ".CCMES." SET status=$s,mtime=$now WHERE msgid=$msgid");
    if (!$res) die('DATABASE error');	// unknown error?
    cc_log_status($data, $stat);
    return true;
}

/**
 * Generates JavaScript validation code for form fields
 *
 * Creates client-side validation JavaScript based on field validation rules.
 * Handles required field checking, pattern matching, and confirmation field
 * validation using template system.
 *
 * @param array $checks Array of field validation rules
 * @param array $confirm Array of confirmation field mappings
 * @param array $pattern Array of validation patterns
 * @return string Generated JavaScript validation code
 * @since 1.0.0
 */
function checkScript($checks, $confirm, $pattern) {
    global $xoopsTpl;
    $chks = array();
    foreach ($checks as $name => $msg) {
	$pat = $pattern[$name];
	$v = get_attr_value(null, $pat);
	if (!empty($v)) $pat = $v;
	$pat = htmlspecialchars(preg_replace('/([\\\\\"])/', '\\\\$1', $pat));
	$chks[$name] = array('message'=>$msg, 'pattern'=>$pat);
    }
    $tpl=new icms_view_Tpl;
    $tpl->assign('item', array("type"=>"javascript", "confirm"=>$confirm, 'checks'=>$chks));
    return $tpl->fetch('db:'._CC_WIDGET_TPL);
}

/**
 * Configures form validation settings and generates validation scripts
 *
 * Analyzes form fields to determine validation requirements, generates
 * JavaScript validation code, and sets up confirmation field mappings.
 * Modifies the form array by reference to include validation settings.
 *
 * @param array &$form Form configuration array (modified by reference)
 * @since 1.0.0
 */
function set_checkvalue(&$form) {
    $hasfile = false;
    $require = $confirm = $pattern = array();
    foreach ($form['items'] as $item) {
	if (empty($item['field'])) continue;
	$fname = $item['field'];
	$type = $item['type'];
	$lab = htmlspecialchars(strip_tags($item['label']));
	$check = isset($item['attr']['check'])?$item['attr']['check']:'';
	if ($type == 'file') {
	    $hasfile=true;
	} elseif (preg_match('/_conf$/', $fname)) {
	    $confirm[preg_replace('/_conf$/', '', $fname)] = $lab;
	} elseif (!empty($check)) {
	    if ($type == 'checkbox') $fname .= '[]';
	    $require[$fname] = $lab;
	    $pattern[$fname] = ($check=='require')?'.+':$check;
	}
    }

    $form['check_script'] = checkScript($require, $confirm, $pattern);
    $form['confirm'] = $confirm;
    $form['hasfile'] = $hasfile;
}

/**
 * Renders complete form output based on template type and operation mode
 *
 * Handles different form rendering modes (custom templates, standard forms)
 * and operation states (input, confirmation). Sets up template variables
 * and returns appropriate template filename for rendering.
 *
 * @param array &$form Form configuration array (modified by reference)
 * @param string $op Current operation mode ('confirm' or other)
 * @return string Template filename to use for rendering
 * @since 1.0.0
 */
function render_form(&$form, $op) {
    global $xoopsTpl;

    set_checkvalue($form);
    $html = 0;
    $br = 1;
    switch ($form['custom']) {
    case _CC_TPL_FRAME:
	$xoopsTpl->assign(array('xoops_showcblock'=>0,'xoops_showlblock'=>0,'xoops_showrblock'=>0));
    case _CC_TPL_BLOCK:
    case _CC_TPL_FULL:
	$xoopsTpl->assign('content', custom_template($form, $form['items'], $op == 'confirm'));
	$template = "ccenter_custom.html";
	break;
    case _CC_TPL_NONE_HTML:
	$html = 1;
	$br = 0;
    case _CC_TPL_NONE:
	$str = $rep = array();
	if (!empty($form['priuser'])) {
	    $priuser =& $form['priuser'];
	    $str[] = "{TO_UNAME}";
	    $rep[] = $priuser['uname'];
	    $str[] = "{TO_NAME}";
	    $rep[] = $priuser['name'];
	}
	$str[] = "{ICMS_URL}";
	$rep[] = ICMS_URL;
	if ($html == 0) {
		$form['desc'] = icms_core_DataFilter::checkVar(str_replace($str, $rep, $form['description']), 'text', 'input');
	} else {
		$form['desc'] = icms_core_DataFilter::checkVar(str_replace($str, $rep, $form['description']), 'html', 'input');
	}
	$xoopsTpl->assign('op', 'confirm');
	$template = ($op=='confirm'?"ccenter_confirm.html":"ccenter_form.html");
    }
    $dirname = basename(dirname(__FILE__));
    $form['cc_url'] = ICMS_URL."/modules/$dirname";
    $xoopsTpl->assign('form', $form);
    return $template;
}


/**
 * Breadcrumb navigation management class
 *
 * Provides functionality for creating and managing breadcrumb navigation
 * trails in the ICMS/XOOPS environment. Handles URL construction and
 * template assignment for navigation display.
 *
 * @since 1.0.0
 */
class XoopsBreadcrumbs {
    var $moddir;
    var $pairs;

    /**
     * Constructor for XoopsBreadcrumbs
     *
     * Initializes breadcrumb trail with module root as the first item.
     * Sets up base module URL and name for navigation.
     *
     * @since 1.0.0
     */
    function __constructor() {
	global $xoopsTpl;
	$this->moddir = ICMS_URL."/modules/".icms::$module->getVar('dirname').'/';
	$this->pairs = array(array('name'=>icms::$module->getVar('name'), 'url'=>$this->moddir));
    }

    /**
     * Adds a breadcrumb item to the navigation trail
     *
     * Appends a new navigation item with name and URL. Handles both
     * relative and absolute URLs, converting relative URLs to module-relative.
     *
     * @param string $name Display name for the breadcrumb item
     * @param string $url URL for the breadcrumb link
     * @since 1.0.0
     */
    function set($name, $url) {
	if (preg_match('/^\w+:\/\//', $url)) $url = $this->moddir.$url;
	$this->pairs[] = array('name'=>htmlspecialchars(strip_tags($name), ENT_QUOTES), 'url'=>$url);
    }

    /**
     * Retrieves the complete breadcrumb trail array
     *
     * Returns the breadcrumb items with the last item having an empty URL
     * to indicate it's the current page (non-clickable).
     *
     * @return array Array of breadcrumb items with 'name' and 'url' keys
     * @since 1.0.0
     */
    function get() {
	$ret = $this->pairs;
	$keys = array_keys($ret);
	$ret[array_pop($keys)]['url'] = '';
	return $ret;
    }

    /**
     * Assigns breadcrumb data to the global template
     *
     * Sets the breadcrumb trail in the global template system for
     * display in the theme. Uses the standard XOOPS breadcrumb variable.
     *
     * @return mixed Result of template assignment
     * @since 1.0.0
     */
    function assign() {
	global $xoopsTpl;
	return $xoopsTpl->assign('xoops_breadcrumbs', $this->get());
    }

}
