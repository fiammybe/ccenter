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
        $this->initVar('store', XOBJ_DTYPE_INT, _DB_STORE_YES, false);
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
        $this->setVar('grpperm', $ids ? '|' . implode('|', $ids) . '|' : '');
    }
}
