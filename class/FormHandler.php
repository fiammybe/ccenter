<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_FormHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'form', 'formid', 'title', 'description', 'ccenter');
    }

    /**
     * Active forms that members of the given groups may use, ordered by weight.
     *
     * @param int[] $groupIds groups of the visitor
     * @param int   $formId   restrict to one form (0 = all)
     * @return mod_ccenter_Form[]
     */
    public function getAccessible(array $groupIds, $formId = 0)
    {
        $groups = new icms_db_criteria_Compo();
        foreach ($groupIds as $gid) {
            $groups->add(new icms_db_criteria_Item('grpperm', '%|' . (int) $gid . '|%', 'LIKE'), 'OR');
        }
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('active', 1));
        if ($groupIds) {
            $criteria->add($groups);
        } else {
            $criteria->add(new icms_db_criteria_Item('formid', 0)); // nobody: no match
        }
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
        $obj = $this->find($formId);
        return $obj ? (bool) $this->delete($obj, true) : true;
    }
}
