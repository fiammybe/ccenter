<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactMessage extends icms_ipf_Object {
    public function __construct(&$handler) {
        parent::__construct($handler);
        $this->initVar('msgid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('fidref', XOBJ_DTYPE_INT, null, false);
        $this->initVar('uid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('touid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('status', XOBJ_DTYPE_TXTBOX, null, false, 1);
        $this->initVar('mtime', XOBJ_DTYPE_INT, null, false);
        $this->initVar('title', XOBJ_DTYPE_TXTBOX, null, false);
        $this->initVar('content', XOBJ_DTYPE_TXTAREA, null, false);
        $this->initVar('onepass', XOBJ_DTYPE_TXTBOX, null, false, 8);
    }
}
