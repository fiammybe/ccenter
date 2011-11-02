<?php
/**
* ccenter is a form module
*
* File: /admin/help.php
*
* show language help.html
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
* @version		$Id: help.php,v 1.3 2011-03-14 13:59:16 nobu Exp $
*/

include 'admin_header.php';

global $mydirpath, $mydirname;
$mydirpath = dirname(dirname(__FILE__));
$mydirname = icms::$module -> getVar( 'dirname' );

// for compat older PHP 4.x
//if(!function_exists("file_get_contents")) {
//   function file_get_contents($filename) {
//       $fp = fopen($filename, "rb");
//       if (!$fp) return false;
//       $contents = "";
//       while (! feof($fp)) {
//	   $contents .= fread($fp, 4096);
//       }
//       return $contents;
//   }
//}

icms_cp_header();
ccenter_adminmenu( 3, _MI_CCENTER_HELP );

$file = isset($_GET['file']) ? icms_core_DataFilter::stripSlashesGPC($_GET['file']) : "help.html";
display_lang_file($file);
icms_cp_footer();
exit;

// show under language/XX/$file only <body> part.
function display_lang_file($file, $link='') {
    global $icmsConfig;
    if (empty($link)) {
	$link = preg_replace('/[&\?]?file=[^&]*|\?$/', '', $_SERVER['REQUEST_URI']);
	$link .= preg_match('/\?/', $link)?'&':'?';
	$link .= 'file=';
    }
    $file = preg_replace('/^\/+/','',preg_replace('/\/?\\.\\.?\/|\/+/', '/', $file));
    $lang = "language/".$icmsConfig['language'];
    $help = "../$lang/$file";
    if (!file_exists($help)) {
	$lang = 'language/english';
	$help = "../$lang/$file";
    }
    $content = file_get_contents($help);
    list($h, $b) = preg_split('/<\/?body>/', $content);
    if (empty($b)) $b =& $content;
    $murl = ICMS_URL.'/modules/'.icms::$module->getVar('dirname');

    if (preg_match('/<link[^>]*>/', $b, $match)) {
	foreach ($match as $item) {
	    if (preg_match('/href=[\"\']?([^\"\']+)/', $item, $d)) {
		$x = preg_replace('/'.preg_quote($d[1],'/').'/', "../$lang/".$d[1], $item);
		$b = preg_replace('/'.preg_quote($item, '/').'/', $x, $b);
	    }
	}
    }
    // link image
    // need quote! (sence has protocol)
    // follow only 1 level depth folder
    $pat = array('/\ssrc=\'([^#][^\':]*)\'/',
		 '/\ssrc="([^#][^":]*)"/',
		 '/\shref=\'([^#\\.][^\':]*)\'/',
		 '/\shref="([^#\\.][^\':]*)"/',
		 '/\shref=([\'"]?)\\.\\.\\/\\.\\.\\//',
	);
    $rep = array(" src='../$lang/\$1'",
		 " src=\"../$lang/\$1\"",
		 " href='$link\$1'",
		 " href=\"$link\$1\"",
		 " href=$1$murl/",
	);
    echo '<div class="help">'.preg_replace($pat, $rep, $b).'</div>';
}

