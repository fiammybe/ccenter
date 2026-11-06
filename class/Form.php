<?php

/**
 * A contact form: definition, who may use it and who handles the messages.
 * Table ccenter_form.
 */
class mod_ccenter_Form extends mod_ccenter_Object
{
    public function __construct(&$handler)
    {
        parent::__construct($handler);
        $this->initVar('formid', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('mtime', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('title', XOBJ_DTYPE_OTHER, '', false);
        $this->initVar('description', XOBJ_DTYPE_OTHER, '', false);
        $this->initVar('defs', XOBJ_DTYPE_OTHER, '', false);        // JSON form schema (or the earlier text)
        $this->initVar('grpperm', XOBJ_DTYPE_OTHER, '', false);     // accepting groups |ID|ID|
        $this->initVar('priuid', XOBJ_DTYPE_INT, 0, false);         // person in charge (negative: member of group -id)
        $this->initVar('cgroup', XOBJ_DTYPE_INT, 0, false);         // contact group
        $this->initVar('store', XOBJ_DTYPE_INT, mod_ccenter_StoreMode::YES, false);
        $this->initVar('custom', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('active', XOBJ_DTYPE_INT, 1, false);
        $this->initVar('weight', XOBJ_DTYPE_INT, 0, false);
        $this->initVar('optvars', XOBJ_DTYPE_OTHER, '', false);     // JSON object (or the earlier key=value text)
    }

    /** @return mod_ccenter_FormSchema */
    public function schema()
    {
        return mod_ccenter_FormSchema::fromStored($this->vars['defs']['value']);
    }

    public function setSchema(mod_ccenter_FormSchema $schema)
    {
        $this->setVar('defs', $schema->toJson());
    }

    /** @return array option variables */
    public function optionVars()
    {
        return mod_ccenter_OptionVars::parse($this->vars['optvars']['value']);
    }

    public function setOptionVars(array $vars)
    {
        $this->setVar('optvars', mod_ccenter_OptionVars::encode($vars));
    }

    /** @return int[] ids of the groups that may use the form */
    public function allowedGroups()
    {
        preg_match_all('/\d+/', (string) $this->vars['grpperm']['value'], $m);
        return array_map('intval', $m[0]);
    }

    public function setAllowedGroups(array $groupIds)
    {
        $ids = array_unique(array_map('intval', $groupIds));
        sort($ids);
        $this->setVar('grpperm', $ids ? '|' . implode('|', $ids) . '|' : '');
    }

    // ---- columns and actions of the administration table (called by icms_ipf_view_Table) ----

    /** @return string link to the form on the site, opens in the preview window */
    public function getTitleLink()
    {
        $url = ICMS_URL . '/modules/' . basename(dirname(__DIR__)) . '/index.php?form=' . (int) $this->vars['formid']['value'];
        return '<a href="' . $url . '" target="preview">' . htmlspecialchars((string) $this->vars['title']['value']) . '</a>';
    }

    /** @return string who handles the messages of this form */
    public function getContact()
    {
        $priuid = (int) $this->vars['priuid']['value'];
        if ($priuid < 0) {
            $groups = icms::handler('icms_member')->getGroupList();
            return htmlspecialchars(sprintf(_CC_FORM_PRIM_GROUP, $groups[-$priuid] ?? (string) -$priuid));
        }
        if ($priuid) {
            return icms_member_user_Handler::getUserLink($priuid);
        }
        $cgroup = (int) $this->vars['cgroup']['value'];
        if ($cgroup) {
            $groups = icms::handler('icms_member')->getGroupList();
            return '[' . htmlspecialchars($groups[$cgroup] ?? (string) $cgroup) . ']';
        }
        return _MD_CONTACT_NOTYET;
    }

    /**
     * Number of messages with the given status codes, as a link to the message list.
     *
     * @param string $statuses codes separated by spaces ("-", "a b c"...); "all" for every message
     */
    public function getCount($statuses = 'all')
    {
        $id = (int) $this->vars['formid']['value'];
        if ((int) $this->vars['store']['value'] !== mod_ccenter_StoreMode::YES) {
            return '--';
        }
        $counts = $this->handler->messageCounts();
        $codes = $statuses === 'all' ? mod_ccenter_Status::all() : explode(' ', $statuses);
        $n = 0;
        foreach ($codes as $code) {
            if ($code !== mod_ccenter_Status::DEL) {
                $n += $counts[$id][$code] ?? 0;
            }
        }
        $stat = $statuses === 'all' ? '- a b c' : $statuses;
        return '<a href="msgadm.php?stat=' . urlencode($stat) . '&amp;formid=' . $id . '">' . $n . '</a>';
    }

    public function getWaiting()
    {
        return $this->getCount('-');
    }

    public function getWorking()
    {
        return $this->getCount('a');
    }

    public function getReplied()
    {
        return $this->getCount('b');
    }

    public function getClosed()
    {
        return $this->getCount('c');
    }

    public function getActiveMark()
    {
        return $this->vars['active']['value'] ? '&#10003;' : '&ndash;';
    }

    public function getCloneLink()
    {
        $id = (int) $this->vars['formid']['value'];
        $label = defined('_AM_CC_CLONE') ? _AM_CC_CLONE : 'Clone';
        return '<a href="?op=clone&amp;formid=' . $id . '" title="' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
    }

    public function getMessagesLink()
    {
        $id = (int) $this->vars['formid']['value'];
        return '<a href="' . ICMS_URL . '/modules/' . basename(dirname(__DIR__)) . '/reception.php?form=' . $id . '">' . _AM_DETAIL . '</a>';
    }
}
