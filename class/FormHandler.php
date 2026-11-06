<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_FormHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'form', 'formid', 'title', 'description', 'ccenter');
        $this->addPermission(
            self::PERM_VIEW,
            defined('_AM_CCENTER_PERM_VIEW') ? _AM_CCENTER_PERM_VIEW : 'Who may use the form',
            defined('_AM_CCENTER_PERM_VIEW_DSC') ? _AM_CCENTER_PERM_VIEW_DSC : 'Groups that may fill in the form'
        );
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

    /**
     * Delete a form with its messages and log lines. Uploaded files, comments and notification
     * subscriptions of the messages are cleaned up as well.
     *
     * @return bool
     */
    public function deleteWithMessages($formId)
    {
        $formId = (int) $formId;
        $messages = icms_getModuleHandler('message', 'ccenter');
        foreach ($messages->getIdsOfForm($formId) as $msgid) {
            $messages->purge($msgid);
        }
        $log = icms_getModuleHandler('log', 'ccenter');
        $log->deleteAll(new icms_db_criteria_Item('fidref', $formId), true);
        icms::handler('icms_member_groupperm')->deleteByModule($this->moduleId(), self::PERM_VIEW, $formId);
        $obj = $this->find($formId);
        return $obj ? (bool) $this->delete($obj, true) : true;
    }
}
