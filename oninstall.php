<?php
# ccenter module onInstall proceeding.
# $Id$

register_shutdown_function('ccenter_sample_form');

function ccenter_sample_form()
{
    global $msgs;

    define('FORM', icms::$xoopsDB->prefix('ccenter_form'));

    $data = array('mtime'=>time(),
		  'title'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_TITLE),
		  'description'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_DESC),
		  'grpperm'=>"'|".ICMS_GROUP_ANONYMOUS."|".ICMS_GROUP_USERS."|'",
		  'defs'=>icms::$xoopsDB->quoteString(_MI_SAMPLE_DEFS),
		  'priuid'=>icms::$user->getVar('uid'));

    icms::$xoopsDB->query('INSERT INTO '.FORM."(".implode(',', array_keys($data)).")VALUES(".implode(',', $data).")");
    $msgs[] = '&nbsp;&nbsp;<b>'._MI_SAMPLE_FORM."</b>";
}
