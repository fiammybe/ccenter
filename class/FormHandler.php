<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_FormHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'form', 'formid', 'title', 'description', 'ccenter');
        $this->_page = 'index.php'; // the form administration (edit and delete links of the table)
        $this->addPermission(
            self::PERM_VIEW,
            defined('_AM_CCENTER_PERM_VIEW') ? _AM_CCENTER_PERM_VIEW : 'Who may use the form',
            defined('_AM_CCENTER_PERM_VIEW_DSC') ? _AM_CCENTER_PERM_VIEW_DSC : 'Groups that may fill in the form'
        );
    }

    /** @var int[] groups that are preselected for a new form (the "form_view" permission control reads this) */
    public $defaultGroups = [ICMS_GROUP_USERS];

    /** The IPF permission control takes the groups of a new object from "def_perm_<permission>" of the module configuration. */
    public function getModuleConfig()
    {
        $config = parent::getModuleConfig();
        $config = is_array($config) ? $config : [];
        $config['def_perm_' . self::PERM_VIEW] = $this->defaultGroups;
        return $config;
    }

    /** IPF event: before a form is stored. */
    public function beforeSave(&$obj)
    {
        if ($obj->hasError()) {
            return false;   // the posted values were refused (see mod_ccenter_Controller); nothing is stored
        }
        $obj->setVar('mtime', time());
        return true;
    }

    /**
     * Copy the groups of the group permission into the "grpperm" column. The column is only kept so that the
     * earlier version still works after a downgrade; IPF stores the permission itself after the form.
     */
    public function syncLegacyColumn($formId)
    {
        $form = $this->find((int) $formId);
        if ($form) {
            $form->setAllowedGroups($this->getAllowedGroups($formId));
            $this->insert($form, true);
        }
    }

    /** Name of the permission that lets a group use a form. */
    const PERM_VIEW = 'form_view';

    /** @var int|null */
    private $moduleId;

    /** @return int id of this module */
    public function moduleId()
    {
        if ($this->moduleId === null) {
            $module = icms::handler('icms_module')->getByDirname('ccenter');
            $this->moduleId = is_object($module) ? (int) $module->getVar('mid') : 0;
        }
        return $this->moduleId;
    }

    /** @return int[] ids of the forms for which at least one group is listed */
    private function formsWithPermissionRows()
    {
        $db = icms::$xoopsDB;
        $res = $db->query(
            'SELECT DISTINCT gperm_itemid FROM ' . $db->prefix('group_permission') . ' WHERE gperm_name=' .
            $db->quoteString(self::PERM_VIEW) . ' AND gperm_modid=' . $this->moduleId()
        );
        $ids = [];
        while ($res && ($row = $db->fetchRow($res))) {
            $ids[] = (int) $row[0];
        }
        return $ids;
    }

    /**
     * Groups that may use a form. A form that has not been converted from the earlier "grpperm" text
     * yet is read from that text.
     *
     * @return int[]
     */
    public function getAllowedGroups($formId)
    {
        $formId = (int) $formId;
        $db = icms::$xoopsDB;
        $res = $db->query(
            'SELECT gperm_groupid FROM ' . $db->prefix('group_permission') . ' WHERE gperm_name=' .
            $db->quoteString(self::PERM_VIEW) . ' AND gperm_modid=' . $this->moduleId() . ' AND gperm_itemid=' . $formId
        );
        $groups = [];
        while ($res && ($row = $db->fetchRow($res))) {
            $groups[] = (int) $row[0];
        }
        if (!$groups) {
            $form = $this->find($formId);
            $groups = $form ? $form->allowedGroups() : [];
        }
        sort($groups);
        return array_values(array_unique($groups));
    }

    /**
     * Set the groups that may use a form. The permission is kept in the ImpressCMS group permissions; the
     * "grpperm" text of the form is kept in step so that the earlier version still works after a downgrade.
     *
     * @param int   $formId
     * @param int[] $groupIds
     */
    public function setAllowedGroups($formId, array $groupIds)
    {
        $formId = (int) $formId;
        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
        $perm = new icms_ipf_permission_Handler($this);
        $perm->saveItem_Permissions($groupIds, $formId, self::PERM_VIEW);
        $form = $this->find($formId);
        if ($form) {
            $form->setAllowedGroups($groupIds);
            $this->insert($form, true);
        }
    }

    /**
     * Active forms that members of the given groups may use, ordered by weight.
     *
     * A form is available when one of the groups is listed in its "form_view" permission. Forms without any
     * permission entry (the earlier "grpperm" text has not been converted yet) fall back to that text.
     *
     * @param int[] $groupIds groups of the visitor
     * @param int   $formId   restrict to one form (0 = all)
     * @return mod_ccenter_Form[]
     */
    public function getAccessible(array $groupIds, $formId = 0)
    {
        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('active', 1));
        if (!$groupIds) {
            return [];
        }
        $granted = icms::handler('icms_member_groupperm')->getItemIds(self::PERM_VIEW, $groupIds, $this->moduleId());
        $granted = array_map('intval', $granted) ?: [0];
        $converted = $this->formsWithPermissionRows() ?: [0];

        $legacy = new icms_db_criteria_Compo(new icms_db_criteria_Item('formid', '(' . implode(',', $converted) . ')', 'NOT IN'));
        $groupsLike = new icms_db_criteria_Compo();
        foreach ($groupIds as $gid) {
            $groupsLike->add(new icms_db_criteria_Item('grpperm', '%|' . $gid . '|%', 'LIKE'), 'OR');
        }
        $legacy->add($groupsLike);

        $either = new icms_db_criteria_Compo(new icms_db_criteria_Item('formid', '(' . implode(',', $granted) . ')', 'IN'));
        $either->add($legacy, 'OR');
        $criteria->add($either);
        if ($formId) {
            $criteria->add(new icms_db_criteria_Item('formid', (int) $formId));
        }
        $criteria->setSort('weight, formid');
        $criteria->setOrder('ASC');
        return $this->getObjects($criteria);
    }

    /**
     * @param int $formId
     * @return mod_ccenter_Form|null
     */
    public function find($formId)
    {
        $obj = $this->get((int) $formId);
        return ($obj && !$obj->isNew()) ? $obj : null;
    }

    /** @var array|null formid => [status => number of messages] */
    private $counts;
    /** @var array|null formid => time of the newest message that is not deleted */
    private $lastTimes;

    /**
     * Number of messages of every form per status (without the deleted ones), in one query.
     *
     * @return array
     */
    public function messageCounts()
    {
        if ($this->counts === null) {
            $db = icms::$xoopsDB;
            $this->counts = [];
            $this->lastTimes = [];
            $res = $db->query(
                'SELECT fidref, status, COUNT(*), MAX(mtime) FROM ' . $db->prefix('ccenter_message') .
                ' WHERE status<>' . $db->quoteString(mod_ccenter_Status::DEL) . ' GROUP BY fidref, status'
            );
            while ($res && ($row = $db->fetchRow($res))) {
                $this->counts[(int) $row[0]][$row[1]] = (int) $row[2];
                $this->lastTimes[(int) $row[0]] = max($this->lastTimes[(int) $row[0]] ?? 0, (int) $row[3]);
            }
        }
        return $this->counts;
    }

    /**
     * Choices for "contact person": nobody, a member of a group (chosen by the link of the form), or one
     * of the members of the contact group.
     *
     * @param int $contactGroup the contact group of the form (0 = none)
     * @return array value => text
     */
    public function contactChoices($contactGroup)
    {
        $member = icms::handler('icms_member');
        $groups = $member->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
        $choices = [0 => defined('_AM_FORM_PRIM_NONE') ? _AM_FORM_PRIM_NONE : 'None'];
        foreach ($groups as $id => $name) {
            $choices[-$id] = sprintf(_CC_FORM_PRIM_GROUP, $name);
        }
        if ($contactGroup) {
            $users = $member->getUsersByGroup((int) $contactGroup, true, 200);
            foreach ($users as $user) {
                $choices[(int) $user->getVar('uid')] = $user->getVar('uname');
            }
        }
        return $choices;
    }

    /**
     * A new, not yet stored form with the settings of another one.
     *
     * @return mod_ccenter_Form
     */
    public function copyOf(mod_ccenter_Form $source)
    {
        $copy = $this->create();
        $row = $source->toRow();
        unset($row['formid']);
        $row['title'] = (defined('_AM_CC_COPY_OF') ? _AM_CC_COPY_OF : 'Copy of') . ' ' . $row['title'];
        $row['mtime'] = time();
        $copy->setValues($row);
        return $copy;
    }

    /**
     * Delete a form with its messages, log lines and permission. Uploaded files, comments and notification
     * subscriptions of the messages are cleaned up as well.
     *
     * @param mod_ccenter_Form $obj
     * @param bool             $force
     * @return bool
     */
    public function delete(&$obj, $force = false)
    {
        $formId = (int) $obj->getVar('formid', 'n');
        $messages = icms_getModuleHandler('message', 'ccenter');
        foreach ($messages->getIdsOfForm($formId) as $msgid) {
            $messages->purge($msgid);
        }
        icms_getModuleHandler('log', 'ccenter')->deleteAll(new icms_db_criteria_Item('fidref', $formId), true);
        icms::handler('icms_member_groupperm')->deleteByModule($this->moduleId(), self::PERM_VIEW, $formId);
        return parent::delete($obj, $force);
    }

    /**
     * Delete a form by id (with everything that belongs to it).
     *
     * @return bool
     */
    public function deleteWithMessages($formId)
    {
        $obj = $this->find((int) $formId);
        return $obj ? (bool) $this->delete($obj, true) : true;
    }

    /**
     * Forms a user takes care of (the reception page): all for an administrator, else those with the user as contact
     * person or a contact group the user is in. Each row has the number of messages (not deleted) and the time of the
     * newest one.
     *
     * @param int   $uid
     * @param int[] $groups
     * @param bool  $isAdmin
     * @param int   $formId 0 = every form
     * @return array rows of forms
     */
    public function getReceptionRows($uid, array $groups, $isAdmin, $formId = 0)
    {
        $criteria = new icms_db_criteria_Compo();
        if (!$isAdmin) {
            $groups = array_map('intval', $groups) ?: [0];
            $mine = new icms_db_criteria_Compo(new icms_db_criteria_Item('priuid', (int) $uid));
            $mine->add(new icms_db_criteria_Item('cgroup', '(' . implode(',', $groups) . ')', 'IN'), 'OR');
            $criteria->add($mine);
        }
        if ($formId) {
            $criteria->add(new icms_db_criteria_Item('formid', (int) $formId));
        }
        $criteria->setSort('weight, formid');
        $criteria->setOrder('ASC');
        $counts = $this->messageCounts();
        $rows = [];
        foreach ($this->getObjects($criteria) as $form) {
            $row = $form->toRow();
            $id = (int) $row['formid'];
            $row['nmsg'] = array_sum($counts[$id] ?? []);
            $row['ltime'] = $this->lastTimes[$id] ?? 0;
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array id => title of the active forms, in display order (choices of the form block) */
    public function activeChoices()
    {
        $choices = [];
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('active', 1));
        $criteria->setSort('weight, formid');
        $criteria->setOrder('ASC');
        foreach ($this->getObjects($criteria) as $form) {
            $choices[(int) $form->getVar('formid', 'n')] = (string) $form->getVar('title', 'n');
        }
        return $choices;
    }
}
