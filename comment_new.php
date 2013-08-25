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
include '../../mainfile.php';
include 'functions.php';

$com_itemid = isset($_GET['com_itemid']) ? (int) $_GET['com_itemid'] : 0;

$data = cc_get_message($com_itemid);

$com_replytext = _POSTEDBY.'&nbsp;<b>'.
    icms_member_user_Handler::getUserLink($data['uid']).'</b>&nbsp;'.
    _DATE.'&nbsp;<b>'.formatTimestamp($data['mtime']).'</b>
<br /><br />'.icms_core_DataFilter::checkVar($data['body'], 'text', 'input')."<br/><br/>".

$com_replytitle = $data['title'];

include ICMS_ROOT_PATH.'/include/comment_new.php';