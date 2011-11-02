<?php
/**
* ccenter is a form module
*
* File: /include/functions.php
*
* ccenter functions
* 
* @copyright	Copyright QM-B (Steffen Flohrer) 2011
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
*
* ----------------------------------------------------------------------------------------------------------
* 				ccenter 
* @since		0.94
* @author		Nobuhiro Yasutomi
* @package		ccenter
* ----------------------------------------------------------------------------------------------------------
* 				ccenter
* @since		1.00
* @author		QM-B
* @version		$Id$
* @package		ccenter
*/

// using tables
define("FORMS", icms::$xoopsDB->prefix("ccenter_form"));
define('CCMES', icms::$xoopsDB->prefix('ccenter_message'));
define('CCLOG', icms::$xoopsDB->prefix('ccenter_log'));

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
	$moddir = dirname( dirname( __FILE__ ) );
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

function ccenter_getModuleName($withLink = true, $forBreadCrumb = false, $moduleName = false) {
	
	if (!$moduleName) {
		
		$ccenterModule = icms_getModuleInfo( icms::$module -> getVar( 'dirname' ) );
		$moduleName = $ccenterModule -> getVar( 'dirname' );
	}
	$icmsModuleConfig = icms_getModuleConfig($moduleName);
	if (!isset ($ccenterModule)) {
		return '';
	}

	if (!$withLink) {
		return $ccenterModule->name();
	} else {
		$ret = ICMS_URL . '/modules/' . $moduleName . '/';
		return '<a href="' . $ret . '">' . $ccenterModule->name() . '</a>';
	}
}

function prepareIndexpageForDisplay($indexpageObj, $with_overrides = true) {

	global $ccenterConfig;	
	
	$indexpageArray = array();
	
	if ($with_overrides) {
		$indexpageArray = $indexpageObj->toArray();
	} else {
		$indexpageArray = $indexpageObj->toArrayWithoutOverrides();
	}

	
	// create an image tag for the indeximage
	$indexpageArray['indeximage'] = $indexpageObj->get_indeximage_tag();
	
	return $indexpageArray;
}

function prepareFormsForDisplay($formObj, $with_overrides = true) {

	global $ccenterConfig;	
	
	$formArray = array();
	
	if ($with_overrides) {
		$formArray = $formObj->toArray();
	} else {
		$formArray = $formObj->toArrayWithoutOverrides();
	}
	
	return $formArray;
}

// attribute config option expanding
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
	$mydirname = icms::$module -> getVar( 'dirname' );
	if (!empty($GLOBALS['icmsModule']) && $GLOBALS['icmsModule']->getVar('dirname') == $mydirname) {
	    $def_attr = $GLOBALS['icmsModuleConfig']['def_attrs'];
	} else {
	    $module_handler = icms::handler('icms_module');
	    $module =& $module_handler->getByDirname($mydirname);
	    $config_handler = icms::handler('icms_config');
	    $configs =& $config_handler->getConfigsByCat(0, icms::$module->getVar('mid'));
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
						$val = join(', ', $cvals);
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

function get_form_attribute($defs, $labels='', $prefix="cc") {
    $labs = unserialize_vars($labels);
    $num = 0;
    $result = array();
    $types = array('text', 'checkbox', 'radio', 'textarea', 'select', 'hidden','const', 'mail', 'file', 'date');
    foreach (cc_csv_parse($defs) as $opts) {
	if (empty($opts)) continue;
	if (preg_match('/^#/', $opts[0])) {
	    $result[] = array('comment'=>substr(join(',', $opts), 1));
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
		$comment = join(',',$opts);
	    }
	}
	if ($type == 'radio') {
	    $defs = $defs?$defs[0]:'';
	} elseif ($type != 'checkbox') {
	    $defs = eval_user_value(join(',', $options));
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
		switch ($type) {
			case 'checkbox':
				if (empty($val)) $val = array();
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
				$val = eval_user_value(join(',', $item['options']));
				break;
			case 'file':
				$val = '';		// filename
				$upfile = isset($_FILES[$name])?$_FILES[$name]:array('name'=>'');
				if (isset($_POST[$name."_prev"])) {
					$val = icms_core_DataFilter::stripSlashesGPC($_POST[$name."_prev"]);
					if (!empty($upfile['name'])) {
						unlink(ICMS_UPLOAD_PATH.cc_attach_path(0, $val));
						$val = '';
					}
				}
				if (empty($val)) {
					$val = $upfile['name'];
					if ($val) move_attach_file($upfile['tmp_name'], $val);
					elseif (isset($_POST[$name])) {	// confirm
						$val = icms_core_DataFilter::stripSlashesGPC($_POST[$name]);
					}
				}
				break;
			case 'mail':
				$name .= '_conf';
	    if (!checkEmail($val)) {
		$errors[] = $lab.": "._MD_ADDRESS_ERR;
	    }
	    if (isset($_POST[$name])) {
		if ($val != icms_core_DataFilter::stripSlashesGPC($_POST[$name])) {
		    $errors[] = sprintf(_MD_CONF_LABEL, $lab).": "._MD_CONFIRM_ERR;
		}
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
					$val = eval_user_value(join(',', $item['options']));
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
		switch ($check) {
			case '':
				break;
			case 'require':
				if ($val==='') $errors[] = $lab.": "._MD_REQUIRE_ERR;
					break;
			case 'mail':
				if (!checkEmail($val)) $errors[] = $lab.": "._MD_ADDRESS_ERR;
				break;
			case 'num':
				$check='numeric';
			default:
			
			$v = get_attr_value(null, $check);
			if (!empty($v)) $check = $v;
				if (!preg_match('/^'.$check.'$/', $val)) $errors[] = $lab.": ".($val?_MD_REGEXP_ERR:_MD_REQUIRE_ERR);
				break;
		}
		$items[$key]['value'] = $val;
		}
		return $errors;

}

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
				$input .= htmlspecialchars(join(', ', $val), ENT_QUOTES);
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
	if ($type == 'file' && $value) $item['preview'] = cc_attach_image(0, $value, false);
    $item['value'] = $value;
    $tpl=new icms_view_Tpl;
    $tpl->assign('item', $item);
    if (isset($vars))
		$tpl->assign($vars);
    return $tpl->fetch('db:'._CC_WIDGET_TPL);
}


if (!function_exists("unserialize_vars")) {
    // expand: label=value[,\n](label=value...) 
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
    function serialize_text($array) {
	$text = '';
	foreach ($array as $name => $val) {
	    if (is_array($val)) $val = join(', ', $val);
	    if (preg_match('/\n/', $val)) {
		$val = preg_replace('/\n\r?/', "\n\t", $val);
	    }
	    $text .= "$name: $val\n";
	}
	return $text;
    }

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
    function template_dir($file='') {
		global $icmsConfig;
		$lang = $icmsConfig['language'];
		$dir = icms::$module -> getVar( 'dirname' ) .'/language/%s/mail_template/%s';
		$path = sprintf($dir,$lang, $file);
		if (file_exists($path)) {
	    	$path = sprintf($dir,$lang, '');
		} else {
		    $path = sprintf($dir,'english', '');
		}
		return $path;
    }
}

function cc_attach_path($id, $file) {
    $dirname = icms::$module -> getVar( 'dirname' );
    $dir = $id ? sprintf( "%05d", $id ) : "work" . substr( session_id(), 0, 8 );
    return "/$dirname/$dir" . ( $file ? "/$file" : "" );
}

function cc_attach_image($id, $file, $urlonly=false, $add='') {
    if (empty($file)) return "";
    $rurl = "file.php?".($id?"id=$id&":"")."file=".urlencode($file).($add?"&$add":"");
    if ($urlonly) return ICMS_URL."/modules/" . icms::$module -> getVar( 'dirname' ) . "/$rurl";
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

// Access allow:
//   1. onetime password matched
//   2. administrator
//   3. order from/to users
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

function cc_get_message($msgid) {
    $res = icms::$xoopsDB->query("SELECT m.*, title FROM ".CCMES." m,".FORMS." WHERE msgid=".(int)$msgid." AND status<>".icms::$xoopsDB->quoteString(_STATUS_DEL)." AND fidref=formid");

    $data = icms::$xoopsDB->fetchArray($res);
    if (!cc_check_perm($data)) {
	redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
	exit;
    }
    return $data;
}

function cc_check_comment($msgid, $com_id) {

    $res = icms::$xoopsDB->query("SELECT com_itemid FROM ".icms::$xoopsDB->prefix('xoopscomments')." WHERE com_id=".(int)$com_id." AND com_itemid=".(int)$msgid." AND com_modid=".icms::$module->getVar('mid'));
    list($com_itemid) = icms::$xoopsDB->fetchRow($res);
    return $com_itemid;
}

function cc_onetime_ticket($genseed="mypasswdbasestring") {
    return substr(preg_replace('/[^a-zA-Z0-9]/', '', base64_encode(pack("H*",md5($genseed.time())))), 0, 8);
}

function cc_delete_message($msgid) {
    //$res = icms::$xoopsDB->query("DELETE FROM ".CCMES." WHERE msgid=".$msgid);
    $dir = ICMS_UPLOAD_PATH.cc_attach_path(0,'');
    $dh = opendir($dir);
    while ($file = readdir($dh)) {
	if ($file==".." || $file==".") continue;
	$path = "$dir/$file";
	unlink($path);
    }
}

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

function is_cc_evaluate($id, $uid, $pass) {
    $cond = $pass?'onepass='.icms::$xoopsDB->quoteString($pass):"uid=$uid";
    $res = icms::$xoopsDB->query("SELECT count(uid) FROM ".CCMES." WHERE msgid=$id AND $cond AND status=".icms::$xoopsDB->quoteString(_STATUS_REPLY));
    list($ret) = icms::$xoopsDB->fetchRow($res);
    return $ret;
}

function cc_notify_mail($tpl, $tags, $users, $from="") { // return: error count
    global $icmsConfig;
    $xoopsMailer =& getMailer();
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

function check_form_tags($cust,$defs, $desc) {
    global $icmsConfig;

    switch ($cust) {		// check only custom form
    case _CC_TPL_NONE:
    case _CC_TPL_NONE_HTML:
	return '';
    }

    $base = dirname( dirname( __FILE__ ) ).'/language/';
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

function cc_log_message($formid, $comment, $msgid=0) {
    //global $xoopsUser;
    $uid = is_object(icms::$user)?icms::$user->getVar('uid'):0;
    $now = time();
    icms::$xoopsDB->queryF("INSERT INTO ".CCLOG."(ltime, fidref, midref, euid, comment)VALUES($now, $formid, $msgid, $uid, ".icms::$xoopsDB->quoteString(preg_replace('/\n/', ", ", $comment)).")");
    if ($msgid) {
	$msgurl = ICMS_URL."/modules/" . icms::$module -> getVar( 'dirname' ) . "/message.php?id=$msgid";
	$res = icms::$xoopsDB->query("SELECT title FROM ".FORMS." WHERE formid=".$formid);
	list($title) = icms::$xoopsDB->fetchRow($res);
	$tags = array('LOG_STATUS'=>$comment,
		      'FORM_NAME'=>$title,
		      'CHANGE_BY'=>icms::$user?icms::$user->getVar('uname'):"",
		      'MSG_ID'=>$msgid,
		      'MSG_URL'=>$msgurl);
	$notification_handler = icms::handler( 'icms_data_notification' );
	$notification_handler->triggerEvent('message', $msgid, 'status', $tags);
    }
    return $comment;
}

function cc_log_status($data, $nstat) {
    global $msg_status;
    $fid = empty($data['fidref'])?$data['formid']:$data['fidref'];
    $log = sprintf(_CC_LOG_STATUS, $msg_status[$data['status']], $msg_status[$nstat]);
    return cc_log_message($fid, $log, $data['msgid']);
}

define('PAST_TIME_MIN', 3600);	     // 1hour
define('PAST_TIME_HOUR', 24*3600);   // 1day
define('PAST_TIME_DAY', 14*24*3600); // 2week

function myTimestamp($t, $fmt="l", $unit="%dmin,%dhour,%dday,past %s") {
    $past = time()-$t;
    if ($past > PAST_TIME_DAY) {
	return formatTimestamp($t, $fmt);
    }
    $units = explode (',', $unit);
    if ($past < PAST_TIME_MIN) {
	$ret = sprintf($units[0], intval($past/60));
    } elseif ($past < PAST_TIME_HOUR) {
	$ret = sprintf($units[1], intval($past/3600)); // hours
	$v = intval(($past % 3600)/60);	     // min
	if ($v) $ret .= sprintf($units[0], $v);
    } else {
	$ret = sprintf($units[2], intval($past/86400)); // days
	$v = intval(($past % 86400)/3600);    // hours
	if ($v) $ret .= sprintf($units[1], $v);
    }
    return sprintf($units[3], $ret);
}

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

function checkScript($checks, $confirm, $pattern) {
    global $icmsTpl;
    $chks = array();
    foreach ($checks as $name => $msg) {
	$pat = $pattern[$name];
	$v = get_attr_value(null, $pat);
	if (!empty($v)) $pat = $v;
	$pat = htmlspecialchars(preg_replace('/([\\\\\"])/', '\\\\$1', $pat));
	$chks[$name] = array('message'=>$msg, 'pattern'=>$pat);
    }
    $tpl= new icms_view_Tpl;
    $tpl->assign('item', array("type"=>"javascript", "confirm"=>$confirm, 'checks'=>$chks));
    return $tpl->fetch('db:'._CC_WIDGET_TPL);
}

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

function render_form(&$form, $op) {
    global $icmsTpl, $icmsUser;

    set_checkvalue($form);
    $html = 0;
    $br = 1;
    switch ($form['custom']) {
    case _CC_TPL_FRAME:
	$icmsTpl->assign(array('xoops_showcblock'=>0,'xoops_showlblock'=>0,'xoops_showrblock'=>0));
    case _CC_TPL_BLOCK:
    case _CC_TPL_FULL:
	$icmsTpl->assign('content', custom_template($form, $form['items'], $op == 'confirm'));
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
	$icmsTpl->assign('op', 'confirm');
	$template = ($op=='confirm'?"ccenter_confirm.html":"ccenter_form.html");
    }
    $dirname = icms::$module -> getVar( 'dirname' );
    $form['cc_url'] = ICMS_URL."/modules/$dirname";
    $icmsTpl->assign('form', $form);
    return $template;
}

function ccenter_adminmenu( $currentoption = 0, $header = '', $menu = '', $extra = '', $scount = 5 ) {
	
	icms::$module -> displayAdminMenu( $currentoption, icms::$module -> getVar( 'name' ) . ' | ' . $header );
	
	echo '<h3 style="color: #2F5376;">' . $header . '</h3>';

}

function ccenter_nicelink( $title, $shorturl ) {
	$title = strtolower( filter_var( str_replace( ' ', '_', ccenter_charrepl( $title ) ), FILTER_SANITIZE_SPECIAL_CHARS ) );
	$shorturl = strtolower( filter_var( str_replace( ' ', '_', ccenter_charrepl( $shorturl ) ), FILTER_SANITIZE_SPECIAL_CHARS ) );
	if ( !$shorturl ) {
		$nicelink = filter_var( $title, FILTER_SANITIZE_URL );
	} else {
		$nicelink = filter_var( $shorturl, FILTER_SANITIZE_URL );
	}
	return $nicelink;
}

function ccenter_short_url( $formid, $title, $shorturl, $short_url ) {
	$dirname = icms::$module -> getVar( 'dirname' );
	if ( $short_url ) {
		$shorturl = ICMS_URL . '/modules/' . $dirname . '/form.php?formid=' . $formid . '&amp;title=' . ccenter_nicelink( $title, $shorturl );
	} else {
		$shorturl = ICMS_URL . '/modules/' . $dirname . '/form.php?formid=' . $formid;
	}
	return $shorturl;
}

function ccenter_charrepl( $string ) {
    $find = array( 'À','Á','Â','Ã','Ä','Å','Ā','Ă','Ą','Æ','Ç','Ć','Ĉ','Ċ','Č','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ń','Ņ','Ň','Ò','Ó','Ô','Õ','Ö','Œ','Ø','Ŕ','Ŗ','Ř','Ś','Ŝ','Ş','Š','Ù','Ú','Û','Ü','Ũ','Ů','Ű','Ý','Ŷ','Ÿ','à','á','â','ã','ä','å','ā','ă','ą','æ','ç','ć','ĉ','ċ','č','è','é','ê','ë','ì','í','î','ï','ð','ñ','ń','ŉ','ņ','ň','ò','ó','ô','õ','ö','œ','ø','ŕ','ŗ','ř','ś','ŝ','ş','š','ũ','ú','û','ü','ů','ű','ß','ý','ŷ','ÿ','²','³' );
	$repl = array( 'A','A','A','A','A','A','A','A','A','AE','C','C','C','C','C','E','E','E','E','I','I','I','I','D','N','N','N','N','O','O','O','O','O','O','O','R','R','R','S','S','S','S','U','U','U','U','U','U','U','Y','Y','Y','a','a','a','a','a','a','a','a','a','ae','c','c','c','c','c','e','e','e','e','i','i','i','i','d','n','n','n','n','n','o','o','o','o','o','o','o','o','r','r','r','s','s','s','s','u','u','u','u','u','u','s','y','y','y','2','3' );
	$text1 = str_replace( $find, $repl, $string );
	// Now remove unwanted characters from the title
	$search = array (
         '/\'/',
		 '/\"/',
         '/\$/',
		 '/\£/',
		 '/\¥/',
		 '/\¢/',
		 '/\¤/',
		 '/\%/',
         '/\@/',
		 '/\&/',
		 '/\#/',
		 '/\*/',
		 '/\~/',
		 '/\^/',
		 '/\`/',
		 '/\´/',
		 '/\,/',
		 '/\./',
		 '/\(/',
		 '/\)/',
		 '/\[/',
		 '/\]/',
		 '/\{/',
		 '/\}/',
		 '/\|/',
		 '/\</',
		 '/\>/',
		 '/\?/',
		 '/\!/',
		 '/\//',
		 '/\;/',
		 '/\:/',
		 '/\©/',
		 '/\®/',
		 '/\¼/',
		 '/\½/',
		 '/\¾/',
		 '/\¹/',
		 '/\²/',
		 '/\³/',
		 '/\¿/',
		 '/\×/',
		 '/\¡/',
		 '/\°/',
		 '/\µ/',
		 '/\÷/',
		 '/\+/' );
	$text = preg_replace( $search, '', $text1 );
    return $text;
}

function post_optvars() {
    $items = get_form_attribute(_CC_OPTDEFS, '', 'optvar');
    $errors = assign_post_values($items);
    $vars = array();
    foreach ($items as $item) {
	$fname = $item['name'];
	if ($fname == "others") {
	    foreach (unserialize_vars($item['value']) as $k=>$v) {
		$vars[$k] = $v;
	    }
	} else {
	    if ($item['value']) $vars[$fname] = $item['value'];
	}
    }
    return serialize_text($vars);
}


function build_form($formid=0) {
    global $fields, $icmsConfig, $icmsTpl;
    include_once dirname(dirname(__FILE__))."/language/".$icmsConfig['language'].'/main.php';

    if (isset($_POST['formid'])) {
		$data = array();
		$fields[] = 'priuid';
		$fields[] = 'cgroup';
		foreach ($fields as $name) {
			$data[$name] = icms_core_DataFilter::stripSlashesGPC($_POST[$name]);
		}
		$data['optvars'] = post_optvars();
		$data['grpperm'] = $_POST['grpperm'];
		$formid = intval($_POST['formid']);
		// form preview
		get_attr_value($data['optvars']); // set default values
		$items = get_form_attribute($data['defs']);
		assign_form_widgets($items);
		if ($_POST['preview']) {
			echo "<h2>"._PREVIEW." : ".htmlspecialchars($data['title'])."</h2>\n";
			echo "<div class='preview'>\n";
			$data['action'] = '';
			$data['check_script'] = "";
			$data['items'] =& $items;
			if (empty($icmsAdminTpl)) $icmsAdminTpl = new icms_view_Tpl();
			$out = $icmsAdminTpl->fetch('db:'.render_form($data, 'form'));
			echo preg_replace('/type=["\']submit["\']/', 'type="submit" disabled="disabled"', $out);
			echo "</div>\n<hr size='5'/>\n";
		}
    } elseif ($formid) {
		$res = icms::$xoopsDB->query('SELECT * FROM '.FORMS." WHERE formid=$formid");
		$data = icms::$xoopsDB->fetchArray($res);
		$data['grpperm'] = explode('|', trim($data['grpperm'], '|'));
    } else {
		$data = array('title'=>'', 'short_url'=>'', 'description'=>'', 'defs'=>'',
		      'store'=>1, 'custom'=>0, 'weight'=>0, 'active'=>1,
		      'priuid'=>icms::$user->getVar('uid'),
		      'cgroup'=>ICMS_GROUP_ADMIN,
		      'optvars'=>'',
		      'grpperm'=>array(ICMS_GROUP_USERS));
    }
    $form = new icms_form_Theme( $formid ? _AM_FORM_EDIT : _AM_FORM_NEW, 'myform', 'index.php');
	$form->addElement( new icms_form_elements_Hidden( 'formid', $formid ) );
	$form->addElement( new icms_form_elements_Text( _AM_FORM_TITLE, 'title', 35, 80, $data['title'] ), true );
	$form->addElement(new icms_form_elements_Text(_AM_FORM_SHORT_URL, 'short_url', 35, 80, $data['short_url']), true);
    if (!empty($data['mtime'])) $form->addElement(new icms_form_elements_Label(_AM_FORM_MTIME, formatTimestamp($data['mtime'])));
    $desc = new icms_form_elements_Tray(_AM_FORM_DESCRIPTION, "<br/>");
    $description = $data['description'];
    $editor = get_attr_value(null, 'use_fckeditor');
    if ($editor) {
		$desc->addElement(new icms_form_elements_TextArea('', 'description', $description, 10, 60));
    } else {
		$desc->addElement(new icms_form_elements_Dhtmltextarea('', 'description', $description, 10, 60));
    }
    if (!$editor) {
		$button = new icms_form_elements_Button('', 'ins_tpl', _AM_INS_TEMPLATE);
		$button->setExtra("onClick=\"myform.description.value += defsToString();\"");
		$desc->addElement($button);
    }
    $error = check_form_tags($data['custom'], $data['defs'], $description);
    if ($error) $desc->addElement(new icms_form_elements_Label('', "<div style='color:red;'>$error</div>"));
    $form->addElement($desc);
    $custom = new icms_form_elements_Select(_AM_FORM_CUSTOM, 'custom' , $data['custom']);
    $custom->setExtra(' onChange="myform.ins_tpl.disabled = (this.value==0||this.value==4);"');
    $custom_type = unserialize_vars(_AM_CUSTOM_DESCRIPTION);
    if ($editor) unset($custom_type[0]);
    $custom->addOptionArray($custom_type);
    $form->addElement($custom);
    $grpperm = new icms_form_elements_select_Group(_AM_FORM_ACCEPT_GROUPS, 'grpperm', true, $data['grpperm'], 4, true);
    $grpperm->setDescription(_AM_FORM_ACCEPT_GROUPS_DESC);
    $form->addElement($grpperm);
    $defs_tray = new icms_form_elements_Tray(_AM_FORM_DEFS);
    $defs_tray->addElement(new icms_form_elements_TextArea('', 'defs', $data['defs'], 10, 60));
    $defs_tray->addElement(new icms_form_elements_Label('', 
		'<div id="itemhelper" style="display:none; white-space:nowrap;">
			'._AM_FORM_LAB.' <input name="xelab" size="10">
			<input type="checkbox" name="xereq" title="'._AM_FORM_REQ.'">
			<select name="xetype">
				<option value="text">text</option>
				<option value="checkbox">checkbox</option>
				<option value="radio">radio</option>
				<option value="textarea">textarea</option>
				<option value="select">select</option>
				<option value="const">const</option>
				<option value="hidden">hidden</option>
				<option value="mail">mail</option>
				<option value="file">file</option>
			</select>
			<input name="xeopt" size="30" />
			<button onClick="return addFieldItem();">'._AM_FORM_ADD.'</button>
		</div>'));
    $defs_tray->setDescription(_AM_FORM_DEFS_DESC);
    $form->addElement($defs_tray);

    $member_handler =& icms::handler('icms_member');
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $options = array();
    foreach ($groups as $k=>$v) {
		$options[-$k] = sprintf(_CC_FORM_PRIM_GROUP, $v);
    }
    $options[0] = _AM_FORM_PRIM_NONE;

    $priuid = new MyFormSelect(_AM_FORM_PRIM_CONTACT, 'priuid', $data['priuid']);
    $priuid->addOptionArray($options);
    $priuid->addOptionUsers($data['cgroup']);
    $priuid->setDescription(_AM_FORM_PRIM_DESC);
    $form->addElement($priuid) ;

    $cgroup = new icms_form_elements_Select('', 'cgroup', $data['cgroup']);
    $cgroup->setExtra(' onChange="setSelectUID(\'priuid\', 0);"');
    $cgroup->addOption(0, _AM_FORM_CGROUP_NONE);
    $groups = $member_handler->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
    $cgroup->addOptionArray($groups);

    $cgroup_tray = new icms_form_elements_Tray(_AM_FORM_CONTACT_GROUP);
    $cgroup_tray->addElement($cgroup) ;
    $cgroup_tray->addElement(new icms_form_elements_Label('' , '<noscript><input type="submit" name="chggrp" id="chggrp" value="'._AM_CHANGE.'"/></noscript>'));

    $form->addElement($cgroup_tray) ;

    
    $store = new icms_form_elements_Select(_AM_FORM_STORE, 'store', $data['store']);
    $store->addOptionArray(unserialize_vars(_CC_STORE_MODE, 1));
    $form->addElement($store);
    $form->addElement(new icms_form_elements_Radioyn(_AM_FORM_ACTIVE, 'active' , $data['active']));

    $form->addElement(new icms_form_elements_Text(_AM_FORM_WEIGHT, 'weight', 2, 8, $data['weight']));
    {
		$items = get_form_attribute(_CC_OPTDEFS, _AM_OPTVARS_LABEL, 'optvar');
		$vars = unserialize_vars($data['optvars']);
		$others = "";
		foreach ($items as $k=>$item) {
			$name = $item['name'];
			if (isset($vars[$name])) {
				$items[$k]['default'] = $vars[$name];
				unset($vars[$name]);
			}
		}
		$val = "";
		foreach ($vars as $i=>$v) {
			$val .= "$i=$v\n";
		}
		$items[$k]['default'] = $val;
		assign_form_widgets($items);
		$varform="";
		foreach ($items as $item) {
			$br = ($item['type'] =="textarea")?"<br/>":"";
			$varform .= "<div>" . $item['label'] . " : $br" . $item['input'] . "</div>";
		}
    }
    $ck = empty($data['optvars']) ? " " : "checked='checked'";
    $optvars = new icms_form_elements_Label(_AM_FORM_OPTIONS, "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onChange='toggle(this);'$ck/> " . _AM_OPTVARS_SHOW . "\");</script><div id='optvars'>$varform</div>");
    $form->addElement($optvars);
    $submit = new icms_form_elements_Tray('');
    $submit->addElement(new icms_form_elements_Button('' , 'formdefs', _SUBMIT, 'submit'));
    $submit->addElement(new icms_form_elements_Button('' , 'preview', _PREVIEW, 'submit'));
    $form->addElement($submit) ;

    echo "<a name='form'></a>";
    $form->display();
    if ($editor) {
		$base = ICMS_URL."/editors/fckeditor";
		global $icmsTpl;
		echo "<script type='text/javascript' src='$base/fckeditor.js'></script>\n";
		$editor =
			"var ccFCKeditor = new FCKeditor('description', '100%', '350', '$editor');
			ccFCKeditor.BasePath = '$base/';
			ccFCKeditor.ReplaceTextarea();";
    }
    echo '<script language="JavaScript">'.
		$priuid->renderSupportJS(false).
		'
		// display only JavaScript enable
		xoopsGetElementById("itemhelper").style.display = "block";
		'.$editor.'
		function toggle(a) {
			xoopsGetElementById("optvars").style.display = a.checked?"block":"none";
		}
		togle(xoopsGetElementById("optshow"));

		function addFieldItem() {
			var myform = window.document.myform;
			var item=myform.xelab.value;
			if (item == "") {
				alert("'._AM_FORM_LABREQ.'");
				myform.xelab.focus();
				return false;
			}
			if (myform.xereq.checked) item += "*";
			var ty = myform.xetype.value;
			var ov = myform.xeopt.value;
			item += ","+ty;
			if (ty != "text" && ty != "textarea" && ty != "file" && ty != "mail" && ov == "") {
				alert(ty+": '._AM_FORM_OPTREQ.'");
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
			conf = "'._MD_CONF_LABEL.'";
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