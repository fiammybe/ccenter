<?php
/**
 * 'ccenter' is an extended
 *
 * File: /about.php
 *
 * about page
 *
 * @copyright	Copyright QM-B (Steffen Flohrer) 2013
 * @license		http://www.gnu.org/licenses/gpl-3.0.html  GNU General Public License (GPL)
 * ----------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B <qm-b@hotmail.de>
 * @version		$Id: about.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $
 * @package		ccenter
 *
 */

include_once "../../../include/cp_header.php";
include_once "../functions.php";
icms_cp_header();
cc_display_admin_menu(0);
$aboutObj = new icms_ipf_About();
$aboutObj->render();
icms_cp_footer();