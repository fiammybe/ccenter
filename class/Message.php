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

    // ---- columns and actions of the administration table (called by icms_ipf_view_Table) ----

    public function getDate()
    {
        return htmlspecialchars(formatTimestamp((int) $this->vars['mtime']['value']));
    }

    public function getStatusText()
    {
        $labels = mod_ccenter_Status::labels();
        $status = (string) $this->vars['status']['value'];
        $read = $this->vars['mtime']['value'] < $this->vars['atime']['value'] ? _CC_MARK_READIT : '';
        return '<span class="stat' . htmlspecialchars($status) . '">' . htmlspecialchars($labels[$status] ?? $status) . '</span>' . $read;
    }

    /** @return string the form and the first thing the sender wrote, shortened */
    public function getSummary()
    {
        $forms = $this->handler->formTitles();
        $title = htmlspecialchars($forms[(int) $this->vars['fidref']['value']] ?? '');
        $values = $this->body()->toLegacyArray();
        $first = $values ? (string) reset($values) : '';
        $first = preg_replace('/[\n\r].*$/s', '...', $first);
        if (mb_strlen($first) > 30) {
            $first = mb_substr(preg_replace('/\.\.\.$/', '', $first), 0, 30) . '...';
        }
        $link = ICMS_URL . '/modules/' . basename(dirname(__DIR__)) . '/message.php?id=' . (int) $this->vars['msgid']['value'];
        return '<a href="' . $link . '">' . $title . '</a>' . ($first !== '' ? ': ' . htmlspecialchars($first) : '');
    }

    public function getSender()
    {
        $uid = (int) $this->vars['uid']['value'];
        if ($uid > 0) {
            return icms_member_user_Handler::getUserLink($uid);
        }
        return htmlspecialchars((string) $this->vars['email']['value']);
    }

    public function getCharge()
    {
        $touid = (int) $this->vars['touid']['value'];
        return $touid > 0 ? icms_member_user_Handler::getUserLink($touid) : htmlspecialchars(_CC_USER_NONE);
    }

    public function getCommentCount()
    {
        $counts = $this->handler->commentCounts();
        return (string) ($counts[(int) $this->vars['msgid']['value']] ?? 0);
    }

    public function getDetailLink()
    {
        $id = (int) $this->vars['msgid']['value'];
        return '<a href="msgadm.php?msgid=' . $id . '">' . htmlspecialchars(_AM_DETAIL) . '</a>';
    }
}
