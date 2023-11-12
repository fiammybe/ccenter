<?php

if( ! is_object( icms::$module ) ) die( 'icms::$module is not set' )  ;

// language files (modinfo.php)
$language = empty( $icmsConfig['language'] ) ? 'english' : $icmsConfig['language'] ;
$mydirpath = dirname(__FILE__, 2);
$mydirname = basename($mydirpath);
if( file_exists( "$mydirpath/language/$language/modinfo.php" ) ) {
	// user customized language file
	include_once "$mydirpath/language/$language/modinfo.php" ;
} else {
	// fallback english
	include_once "$mydirpath/language/english/modinfo.php" ;
}

include dirname(__FILE__).'/menu.php' ;

    // preferences
    $config_handler =& xoops_gethandler('config');
    if( count( $config_handler->getConfigs( new icms_db_criteria_Item( 'conf_modid' , icms::$module->getVar ('mid') ) ) ) > 0 ) {
	if (defined('ICMS_CUBE_LEGACY')) {
		// system->preferences
		array_push( $adminmenu , array( 'title' => _PREFERENCES , 'link' => ICMS_URL.'/modules/legacy/admin/index.php?action=PreferenceEdit&confmod_id='.icms::$module->getVar ('mid') ) ) ;
	} else {
		array_push( $adminmenu , array( 'title' => _PREFERENCES , 'link' => ICMS_URL.'/modules/system/admin.php?fct=preferences&op=showmod&mod='.icms::$module->getVar ('mid') ) ) ;
	}

}

$mymenu_uri = empty( $mymenu_fake_uri ) ? $_SERVER['REQUEST_URI'] : $mymenu_fake_uri ;
$mymenu_link = substr( strstr( $mymenu_uri , '/admin/' ) , 1 ) ;



// highlight
foreach( array_keys( $adminmenu ) as $i ) {
	if( $mymenu_link == $adminmenu[$i]['link'] ) {
		$adminmenu[$i]['selected'] = true ;
		$adminmenu_hilighted = true ;
	} else {
		$adminmenu[$i]['selected'] = false ;
	}
}
if( empty( $adminmenu_hilighted ) ) {
	$maxlen = 0;
	foreach( array_keys( $adminmenu ) as $i ) {
		$link = $adminmenu[$i]['link'];
		if( $maxlen < strlen($link) && stristr( $mymenu_uri , $link )) {
			if ($maxlen) $last = false;
			$maxlen = strlen($link);
			$adminmenu[$i]['selected'] = true;
			$last = &$adminmenu[$i]['selected'];
		}
	}
}

// link conversion from relative to absolute
foreach( array_keys( $adminmenu ) as $i ) {
	if( stristr( $adminmenu[$i]['link'] , ICMS_URL ) === false ) {
		$adminmenu[$i]['link'] = ICMS_URL."/modules/$mydirname/" . $adminmenu[$i]['link'] ;
	}
}


	// display (you can customize htmls)
	echo "<div style='text-align:left;width:98%;'>" ;
	foreach( $adminmenu as $menuitem ) {
		echo "<div style='float:left;height:1.5em;'><nobr><a href='".htmlspecialchars($menuitem['link'],ENT_QUOTES)."' style='background-color:".($menuitem['selected']?"#FFCCCC":"#DDDDDD").";font:normal normal bold 9pt/12pt;'>".htmlspecialchars($menuitem['title'],ENT_QUOTES)."</a> | </nobr></div>\n" ;
	}
	echo "</div>\n<hr style='clear:left;display:block;' />\n" ;