<?php

/**
 * One submission of a form. Table ccenter_message.
 */
class mod_ccenter_Message extends mod_ccenter_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);
        $this->initVar('msgid', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('uid', XOBJ_DTYPE_INT, 0, false);        // sender, 0 for a guest
        $this->initVar('touid', XOBJ_DTYPE_INT, 0, false);      // person in charge
        $this->initVar('ctime', XOBJ_DTYPE_INT, 0, false);      // created
        $this->initVar('mtime', XOBJ_DTYPE_INT, 0, false);      // modified
        $this->initVar('atime', XOBJ_DTYPE_INT, 0, false);      // last access by the sender
        $this->initVar('fidref', XOBJ_DTYPE_INT, 0, false);     // form
        $this->initVar('email', XOBJ_DTYPE_OTHER, '', false);
        $this->initVar('body', XOBJ_DTYPE_OTHER, '', false);    // JSON (or the earlier "name: value" text)
        $this->initVar('status', XOBJ_DTYPE_OTHER, mod_ccenter_Status::NONE, false);
        $this->initVar('value', XOBJ_DTYPE_INT, 0, false);      // evaluation
        $this->initVar('comment', XOBJ_DTYPE_OTHER, '', false); // evaluation comment
        $this->initVar('comtime', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('onepass', XOBJ_DTYPE_OTHER, '', false); // one-time password for guests
    }

    /** @return mod_ccenter_MessageBody */
    public function body()
    {
        return mod_ccenter_MessageBody::fromStored($this->vars['body']['value']);
    }

    public function setBody(mod_ccenter_MessageBody $body)
    {
        $this->setVar('body', $body->toJson());
    }
}
