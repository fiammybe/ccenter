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

    /** Sort keys the list pages allow (request name => column). */
    private static $sortColumns = ['mtime' => 'mtime', 'formid' => 'fidref', 'uid' => 'uid', 'touid' => 'touid', 'status' => 'status', 'msgid' => 'msgid'];

    private function listBase($column, $uid, $statuses, $formId)
    {
        $criteria = $this->listCriteria($statuses);
        $criteria->add(new icms_db_criteria_Item($column === 'touid' ? 'touid' : 'uid', (int) $uid));
        if ($formId) {
            $criteria->add(new icms_db_criteria_Item('fidref', (int) $formId));
        }
        return $criteria;
    }

    /**
     * Messages of one sender (column "uid") or one person in charge (column "touid") for the list pages.
     *
     * @param string $column   uid or touid
     * @param int    $uid
     * @param string $statuses status codes separated by spaces
     * @param int    $formId   0 = every form
     * @param array  $orders   list of [sort key, ASC|DESC]; keys outside the whitelist are ignored
     * @return int
     */
    public function countListFor($column, $uid, $statuses, $formId = 0)
    {
        return (int) $this->getCount($this->listBase($column, $uid, $statuses, $formId));
    }

    /** @return array rows (with the title of the form) */
    public function listFor($column, $uid, $statuses, $formId, array $orders, $limit, $start)
    {
        $criteria = $this->listBase($column, $uid, $statuses, $formId);
        $this->applyOrders($criteria, $orders);
        $criteria->setLimit((int) $limit);
        $criteria->setStart((int) $start);
        return $this->rowsWithTitle($this->getObjects($criteria));
    }

    private function applyOrders($criteria, array $orders)
    {
        $parts = [];
        foreach ($orders as $order) {
            [$key, $dir] = $order;
            if (isset(self::$sortColumns[$key])) {
                $parts[] = self::$sortColumns[$key] . ' ' . (strtoupper((string) $dir) === 'DESC' ? 'DESC' : 'ASC');
            }
        }
        if (!$parts) {
            $parts[] = 'msgid DESC';
        }
        $last = array_pop($parts);
        [$col, $dir] = explode(' ', $last);
        $parts[] = $col;
        $criteria->setSort(implode(', ', $parts));
        $criteria->setOrder($dir);
    }

    private function rowsWithTitle(array $objects)
    {
        $titles = $this->formTitles();
        $rows = [];
        foreach ($objects as $msg) {
            $row = $msg->toRow();
            $row['title'] = $titles[(int) $row['fidref']] ?? '';
            $rows[] = $row;
        }
        return $rows;
    }

    private function formCriteria($formId, $touid)
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('fidref', (int) $formId));
        $criteria->add(new icms_db_criteria_Item('status', mod_ccenter_Status::DEL, '<>'));
        if ($touid) {
            $criteria->add(new icms_db_criteria_Item('touid', (int) $touid));
        }
        return $criteria;
    }

    /** Number of messages of a form that are not deleted (optionally only those of one person in charge). */
    public function countForForm($formId, $touid = 0)
    {
        return (int) $this->getCount($this->formCriteria($formId, $touid));
    }

    /** @return array message rows of a form, newest first */
    public function listForForm($formId, $touid, $limit, $start)
    {
        $criteria = $this->formCriteria($formId, $touid);
        $criteria->setSort('msgid');
        $criteria->setOrder('DESC');
        $criteria->setLimit((int) $limit);
        $criteria->setStart((int) $start);
        return $this->rowsWithTitle($this->getObjects($criteria));
    }

    /**
     * Messages for a person in charge of forms with the given statuses (the "receipt" block): messages of forms
     * where the user is the contact person, or where nobody is.
     *
     * @return array rows with the title of the form, ordered by status and modification time
     */
    public function receiptFor($uid, array $statuses, $dir, $limit)
    {
        $codes = array_values(array_filter($statuses, ['mod_ccenter_Status', 'isValid']));
        if (!$codes) {
            return [];
        }
        $forms = [];
        foreach (icms_getModuleHandler('form', 'ccenter')->getObjects(new icms_db_criteria_Item('priuid', '(' . (int) $uid . ',0)', 'IN')) as $form) {
            $forms[] = (int) $form->getVar('formid', 'n');
        }
        if (!$forms) {
            return [];
        }
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('fidref', '(' . implode(',', $forms) . ')', 'IN'));
        $criteria->add(new icms_db_criteria_Item('status', "('" . implode("','", $codes) . "')", 'IN'));
        $criteria->setSort('status, mtime');
        $criteria->setOrder($dir === 'asc' ? 'ASC' : 'DESC');
        $criteria->setLimit((int) $limit);
        return $this->rowsWithTitle($this->getObjects($criteria));
    }

    /** Is the message replied, and does the user (or the one-time password) belong to it? */
    public function isEvaluable($msgid, $uid, $pass)
    {
        $msg = $this->find($msgid);
        if (!$msg || $msg->getVar('status', 'n') !== mod_ccenter_Status::REPLY) {
            return false;
        }
        return $pass !== ''
            ? hash_equals((string) $msg->getVar('onepass', 'n'), (string) $pass)
            : (int) $msg->getVar('uid', 'n') === (int) $uid;
    }

    /** The message id when the comment belongs to that message (of this module), else 0. */
    public function commentItem($comId, $msgid)
    {
        $module = icms::handler('icms_module')->getByDirname('ccenter');
        $comment = icms::handler('icms_data_comment')->get((int) $comId);
        if ($comment && (int) $comment->getVar('com_modid') === (int) $module->getVar('mid') && (int) $comment->getVar('com_itemid') === (int) $msgid) {
            return (int) $msgid;
        }
        return 0;
    }
}
