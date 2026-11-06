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
        $this->initVar('mtime', XOBJ_DTYPE_INT, 0, false, null, '', false, '', '', false, true, false);
        $this->initVar('title', XOBJ_DTYPE_OTHER, '', false, 80, '', false, self::text('_AM_FORM_TITLE', 'Form name'), '', true);
        $this->initVar('description', XOBJ_DTYPE_OTHER, '', false, null, '', false, self::text('_AM_FORM_DESCRIPTION', 'Description'));
        $this->initVar('defs', XOBJ_DTYPE_OTHER, '', false, null, '', false, self::text('_AM_FORM_DEFS', 'Form definition'), self::text('_AM_FORM_DEFS_DESC'));
        $this->initVar('grpperm', XOBJ_DTYPE_OTHER, '', false, null, '', false, '', '', false, true, false); // accepting groups |ID|ID| (kept in step with the group permission)
        $this->initVar('priuid', XOBJ_DTYPE_INT, 0, false, null, '', false, self::text('_AM_FORM_PRIM_CONTACT', 'Contact person'), self::text('_AM_FORM_PRIM_DESC'));
        $this->initVar('cgroup', XOBJ_DTYPE_INT, 0, false, null, '', false, self::text('_AM_FORM_CONTACT_GROUP', 'Contact group'));
        $this->initVar('store', XOBJ_DTYPE_INT, mod_ccenter_StoreMode::YES, false, null, '', false, self::text('_AM_FORM_STORE', 'Store in database'));
        $this->initVar('custom', XOBJ_DTYPE_INT, 0, false, null, '', false, self::text('_AM_FORM_CUSTOM', 'Description type'));
        $this->initVar('active', XOBJ_DTYPE_INT, 1, false, null, '', false, self::text('_AM_FORM_ACTIVE', 'Form active'));
        $this->initVar('weight', XOBJ_DTYPE_INT, 0, false, null, '', false, self::text('_AM_FORM_WEIGHT', 'Weight'), '', true);
        $this->initVar('optvars', XOBJ_DTYPE_OTHER, '', false, null, '', false, self::text('_AM_FORM_OPTIONS', 'Option variables'), self::text('_AM_FORM_OPTIONS_DESC'));

        // controls of the edit form (see class/form/elements for the module's own ones)
        $this->setControl('title', ['name' => 'text', 'size' => 35, 'maxlength' => 80]);
        $this->setControl('description', ['name' => 'description']);
        $this->setControl('defs', ['name' => 'defs']);
        $this->setControl('priuid', ['object' => $this, 'method' => 'contactChoices']);
        $this->setControl('cgroup', ['object' => $this, 'method' => 'contactGroupChoices', 'onSelect' => 'submit']);
        $this->setControl('store', ['object' => $this, 'method' => 'storeChoices']);
        $this->setControl('custom', ['object' => $this, 'method' => 'customChoices']);
        $this->setControl('active', ['name' => 'yesno']);
        $this->setControl('weight', ['name' => 'text', 'size' => 5, 'maxlength' => 8]);
        $this->setControl('optvars', ['name' => 'optvars']);
    }

    /** @var string|null the definition as typed in the edit form, while it could not be stored (shown again as typed) */
    public $typedDefs;

    /** @return array person in charge: nobody, a member of a group, or a member of the contact group */
    public function contactChoices()
    {
        $choices = $this->handler->contactChoices((int) $this->vars['cgroup']['value']);
        $current = (int) $this->vars['priuid']['value'];
        if ($current > 0 && !isset($choices[$current])) {
            $choices[$current] = icms_member_user_Object::getUnameFromId($current);
        }
        return $choices;
    }

    /** @return array group id => name (0: none) */
    public function contactGroupChoices()
    {
        $groups = icms::handler('icms_member')->getGroupList(new icms_db_criteria_Item('groupid', ICMS_GROUP_ANONYMOUS, '!='));
        return [0 => self::text('_AM_FORM_CGROUP_NONE', 'None')] + $groups;
    }

    /** @return array */
    public function storeChoices()
    {
        return mod_ccenter_LegacyText::unserializeVars(self::text('_CC_STORE_MODE', '0=Log only,1=Store,2=Never'), true);
    }

    /** @return array */
    public function customChoices()
    {
        return mod_ccenter_LegacyText::unserializeVars(self::text('_AM_CUSTOM_DESCRIPTION', '0=Normal,1=Part template,2=Overall template,4=HTML'));
    }

    /**
     * The definition as the edit form shows it: what was typed when that could not be stored, otherwise the stored
     * definition as lines of text or (format "json") as JSON.
     */
    public function definitionForEditing($format = 'lines')
    {
        if ($this->typedDefs !== null) {
            return $this->typedDefs;
        }
        try {
            $schema = $this->schema();
            return $format === 'json' ? $schema->toJson(true) : $schema->toLegacy();
        } catch (InvalidArgumentException $e) {
            return (string) $this->vars['defs']['value']; // so that it can be repaired
        }
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
