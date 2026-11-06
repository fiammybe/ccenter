<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_MessageHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'message', 'msgid', 'msgid', 'body', 'ccenter');
    }

    /**
     * @param int $msgid
     * @return mod_ccenter_Message|null null when it does not exist
     */
    public function find($msgid)
    {
        $obj = $this->get((int) $msgid);
        return ($obj && !$obj->isNew()) ? $obj : null;
    }

    /**
     * A message that is not deleted, as a row together with the title and contact group of its form.
     *
     * @return array|null
     */
    public function getRowWithForm($msgid)
    {
        $msg = $this->find($msgid);
        if (!$msg || $msg->getVar('status', 'n') === mod_ccenter_Status::DEL) {
            return null;
        }
        $form = icms_getModuleHandler('form', 'ccenter')->find($msg->getVar('fidref', 'n'));
        if (!$form) {
            return null;
        }
        $row = $msg->toRow();
        $row['title'] = $form->getVar('title', 'n');
        $row['cgroup'] = $form->getVar('cgroup', 'n');
        return $row;
    }

    /**
     * Change columns of a message.
     *
     * @param int   $msgid
     * @param array $values column => value
     * @return bool
     */
    public function updateFields($msgid, array $values)
    {
        $msg = $this->find($msgid);
        if (!$msg) {
            return false;
        }
        $msg->setValues($values);
        return (bool) $this->insert($msg, true);
    }

    /** @return int[] */
    public function getIdsOfForm($formId)
    {
        $ids = [];
        foreach ($this->getObjects(new icms_db_criteria_Item('fidref', (int) $formId)) as $msg) {
            $ids[] = (int) $msg->getVar('msgid', 'n');
        }
        return $ids;
    }

    /**
     * Remove a message completely: record, uploaded files, comments and notification subscriptions.
     *
     * @return bool
     */
    public function purge($msgid)
    {
        $msgid = (int) $msgid;
        $msg = $this->find($msgid);
        if (!$msg) {
            return true;
        }
        $module = icms::$module;
        $mid = is_object($module) ? (int) $module->getVar('mid') : (int) icms::handler('icms_module')->getByDirname('ccenter')->getVar('mid');
        $db = icms::$xoopsDB;
        $db->queryF('DELETE FROM ' . $db->prefix('xoopscomments') . ' WHERE com_modid=' . $mid . ' AND com_itemid=' . $msgid);
        $db->queryF('DELETE FROM ' . $db->prefix('xoopsnotifications') . ' WHERE not_modid=' . $mid . " AND not_category='message' AND not_itemid=" . $msgid);
        mod_ccenter_Attachments::removeAll($msgid);
        return (bool) $this->delete($msg, true);
    }
}
