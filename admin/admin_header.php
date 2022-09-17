<?php
/**
 * Admin header file
 *
 * This file is included in all pages of the admin side and being so, it proceeds to a few
 * common things.
 *
 * @copyright	Copyright sato-san (Rene Sato)
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @since		1.0
 * @author		sato-san (Rene Sato) <sato-san@impresscms.org>
 * @package		cms
 * @version		$sato-san$
*/

include_once "../../../include/cp_header.php";
include_once ICMS_ROOT_PATH . "/modules/" . basename(dirname(__FILE__, 2)) . "/include/common.php";
if (!defined("CMS_ADMIN_URL")) define("CMS_ADMIN_URL", CMS_URL . "admin/");
include_once CMS_ROOT_PATH . "include/requirements.php";