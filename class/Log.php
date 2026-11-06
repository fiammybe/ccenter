<?php

/**
 * One line of the history of a form or message. Table ccenter_log.
 */
class mod_ccenter_Log extends mod_ccenter_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);
        $this->initVar('logid', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('ltime', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('fidref', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('midref', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('euid', XOBJ_DTYPE_INT, 0, false);       // who did it
        $this->initVar('comment', XOBJ_DTYPE_OTHER, '', false);
    }
}
