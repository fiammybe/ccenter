<?php
// $Id$

$adminmenu[]=array('title' => _MI_CCENTER_HELP,
		   'link'  => "admin/help.php");
$adminmenu[]=array('title' => _MI_CCENTER_FORMADMIN,
		    'link' => "admin/index.php");
$adminmenu[]=array('title' => _MI_CCENTER_MSGADMIN,
		    'link' => "admin/msgadm.php");

$moddir = basename(dirname(dirname(__FILE__)));
$module = icms::handler("icms_module")->getByDirname($moddir);

$headermenu[] = array(
	"title" => _PREFERENCES,
	"link" => ICMS_URL."/modules/system/admin.php?fct=preferences&amp;op=showmod&amp;mod=" . $module->getVar("mid"));
$headermenu[] = array(
	"title" => _CO_ICMS_GOTOMODULE,
	"link" => ICMS_URL . "/modules/".$moddir."/");
$headermenu[] = array(
	"title" => _CO_ICMS_UPDATE_MODULE,
	"link" => ICMS_URL . "/modules/system/admin.php?fct=modulesadmin&amp;op=update&amp;module=".$moddir);
$headermenu[] = array(
	"title" => _MODABOUT_ABOUT,
	"link" => ICMS_URL . "/modules/".$moddir."/admin/about.php");
unset($module);