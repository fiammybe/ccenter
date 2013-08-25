<?php
# ccenter module onUpdate proceeding.
# $Id$


// ccenter_log table add in 0.72 later
define('LOG', icms::$xoopsDB->prefix('ccenter_log'));
define('MSG', icms::$xoopsDB->prefix('ccenter_message'));
define('FORM', icms::$xoopsDB->prefix('ccenter_form'));

// add logging (after ccenter-0.80)
icms::$xoopsDB->query('SELECT * FROM '.LOG, 1);
if (icms::$xoopsDB->errno()) { // check exists
    $msgs[] = "Update Database...";
    $msgs[] = "&nbsp;&nbsp; Add new table: <b>ccenter_log</b>";
    
    icms::$xoopsDB->query("CREATE TABLE ".LOG." (
  logid  int(8) unsigned NOT NULL auto_increment,
  ltime  int(10) NOT NULL default '0',
  fidref int(8) NOT NULL default '0',
  midref int(8) NOT NULL default '0',
  euid int(8) NOT NULL default '0',
  comment tinytext,
  PRIMARY KEY  (logid)
)");
}

// add create time fields (after ccenter-0.80)
add_field(MSG, "ctime", "INT DEFAULT 0 NOT NULL", "touid");
// add access time fields (after ccenter-0.87)
if (add_field(MSG, "atime", "INT DEFAULT 0 NOT NULL", "mtime")) {
    // last access initially same as ctime
    icms::$xoopsDB->query("UPDATE ".MSG." SET atime=ctime");
}
// change redirect to optvars field (after ccenter-0.90)
if (icms::$xoopsDB->query("ALTER TABLE ".FORM." CHANGE redirect optvars TEXT")) {
    icms::$xoopsDB->query("UPDATE ".FORM." set optvars=concat('redirect=', optvars) WHERE redirect<>''");
    report_message(" Change '<b>redirect</b>' field to '<b>optvars</b>' in ccneter_form table");
}
$res = icms::$xoopsDB->query("SHOW COLUMNS FROM ".MSG." LIKE 'email'");
$data = icms::$xoopsDB->fetchArray($res);
if ($res && preg_replace('/^varchar\((\d+)\)$/', '\1', $data['Type'])<256) {
    if (icms::$xoopsDB->query("ALTER TABLE ".MSG." CHANGE email email VARCHAR(256)")) {
	report_message(" Fix '<b>email</b>' field length to <b>256</b>");
    } else {
	report_message(" Fail to change '<b>email</b>' field length");
    }
}

function add_field($table, $field, $type, $after) {

    $res = icms::$xoopsDB->query("SELECT $field FROM $table", 1);
    if (empty($res) && icms::$xoopsDB->errno()) { // check exists
	if ($after) $after = "AFTER $after";
	$res = icms::$xoopsDB->query("ALTER TABLE $table ADD $field $type $after");
    } else return false;
    report_message(" Add new field: <b>$table.$field</b>");
    if (!$res) {
	echo "<div class='errorMsg'>".icms::$xoopsDB->errno()."</div>\n";
    }
    return $res;
}

function report_message($msg) {
    global $msgs;		// module manager's variable
    static $first = true;
    if ($first) {
	$msgs[] = "Update Database...";
	$first = false;
    }
    $msgs[] = "&nbsp;&nbsp; $msg";
}
?>
