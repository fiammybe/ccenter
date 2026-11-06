<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_MessageHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'message', 'msgid', 'msgid', 'body', 'ccenter');
        $this->_page = 'msgadm.php'; // the message administration (edit and delete links of the table)
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

    /** @var array|null */
    private $titles;
    /** @var array|null */
    private $comments;

    /** @return array form id => title of every form */
    public function formTitles()
    {
        if ($this->titles === null) {
            $this->titles = [];
            foreach (icms_getModuleHandler('form', 'ccenter')->getObjects() as $form) {
                $this->titles[(int) $form->getVar('formid', 'n')] = (string) $form->getVar('title', 'n');
            }
        }
        return $this->titles;
    }

    /** @return array message id => number of comments */
    public function commentCounts()
    {
        if ($this->comments === null) {
            $db = icms::$xoopsDB;
            $this->comments = [];
            $module = icms::handler('icms_module')->getByDirname('ccenter');
            $res = $db->query(
                'SELECT com_itemid, COUNT(*) FROM ' . $db->prefix('xoopscomments') . ' WHERE com_modid=' .
                (int) $module->getVar('mid') . ' GROUP BY com_itemid'
            );
            while ($res && ($row = $db->fetchRow($res))) {
                $this->comments[(int) $row[0]] = (int) $row[1];
            }
        }
        return $this->comments;
    }

    /** @return array option list for the "form" filter of the table: id => title */
    public function getFormChoices()
    {
        return ['default' => defined('_CO_ICMS_NONE') ? _CO_ICMS_NONE : '---'] + $this->formTitles();
    }

    /**
     * Conditions for the message list.
     *
     * @param string $statuses status codes separated by spaces (unknown codes are ignored)
     * @return icms_db_criteria_Compo
     */
    public function listCriteria($statuses)
    {
        $codes = array_values(array_filter(preg_split('/\s+/', trim((string) $statuses)), ['mod_ccenter_Status', 'isValid']));
        $criteria = new icms_db_criteria_Compo();
        $list = $codes ? "('" . implode("','", $codes) . "')" : "('')";
        $criteria->add(new icms_db_criteria_Item('status', $list, 'IN'));
        return $criteria;
    }

    /**
     * Messages of a form for the export, oldest first.
     *
     * @param int    $formId
     * @param string $range  m0 this month, m1 last month, y0 this year, y1 last year, anything else: all
     * @param int    $offset seconds the visitor's clock is ahead of UTC
     * @return mod_ccenter_Message[]
     */
    public function getForExport($formId, $range, $offset = 0)
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('fidref', (int) $formId));
        $criteria->add(new icms_db_criteria_Item('status', mod_ccenter_Status::DEL, '<>'));
        [$from, $to] = mod_ccenter_Period::bounds($range, time(), $offset);
        if ($from !== null) {
            $criteria->add(new icms_db_criteria_Item('ctime', (int) $from, '>='));
            $criteria->add(new icms_db_criteria_Item('ctime', (int) $to, '<'));
        }
        $criteria->setSort('msgid');
        $criteria->setOrder('ASC');
        return $this->getObjects($criteria);
    }

    /** Permanently deleting a message (e.g. from the administration table) also removes what belongs to it. */
    public function delete(&$obj, $force = false)
    {
        $msgid = (int) $obj->getVar('msgid', 'n');
        $module = icms::handler('icms_module')->getByDirname('ccenter');
        $mid = (int) $module->getVar('mid');
        $db = icms::$xoopsDB;
        $db->queryF('DELETE FROM ' . $db->prefix('xoopscomments') . ' WHERE com_modid=' . $mid . ' AND com_itemid=' . $msgid);
        $db->queryF('DELETE FROM ' . $db->prefix('xoopsnotifications') . ' WHERE not_modid=' . $mid . " AND not_category='message' AND not_itemid=" . $msgid);
        mod_ccenter_Attachments::removeAll($msgid);
        return parent::delete($obj, $force);
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
        $msg = $this->find((int) $msgid);
        return $msg ? (bool) $this->delete($msg, true) : true;
    }
}
