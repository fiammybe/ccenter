<?php
// $Id$
//  ------------------------------------------------------------------------ //
//                ICMS - PHP Content Management System                      //
//                    Copyright (c) 2000 ICMS.org                           //
//                       <http://www.xoops.org/>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //


function ccenter_notify_iteminfo($category, $item_id)
{

    $item = array('name'=>'','url'=>'');
    $dirname = basename(dirname(__FILE__));
    if ($category=='message' && $item_id!=0) {
	// Assume we have a valid story id
	$sql = "SELECT fidref, touid, title FROM ".
	    icms::$xoopsDB->prefix('ccenter_message').','.
	    icms::$xoopsDB->prefix('ccenter_form').
	    " WHERE status<>'x' AND msgid=$item_id AND fidref=formid";
	$result = icms::$xoopsDB->query($sql); // TODO: error check
	list($fid, $touid, $title) = icms::$xoopsDB->fetchRow($result);
	$item['name'] = $title;
	$item['url'] = ICMS_URL."/modules/$dirname/message.php?id=".$item_id;
    }
    return $item;
}
?>