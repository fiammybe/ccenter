<?php

/**
 * Takes what the edit form of a contact form posted and puts it in the object.
 *
 * icms_ipf_Controller::postDataToObject() sets *every* variable from $_POST, so a variable that is not in the
 * form becomes empty. This version only touches what the form really posts, and converts the definition and the
 * option variables (text or several inputs) to what is stored.
 */
class mod_ccenter_Controller extends icms_ipf_Controller
{
    /** Fields that are posted as they are (name => allowed values, null for any whole number). */
    private function simpleFields()
    {
        return ['title', 'description', 'priuid', 'cgroup', 'store', 'custom', 'active', 'weight'];
    }

    public function postDataToObject(&$icmsObj)
    {
        foreach ($this->simpleFields() as $key) {
            if (isset($_POST[$key]) && !is_array($_POST[$key])) {
                $icmsObj->setVar($key, icms_core_DataFilter::stripSlashesGPC($_POST[$key]));
            }
        }
        if (isset($_POST['defs']) && !is_array($_POST['defs'])) {
            $text = icms_core_DataFilter::stripSlashesGPC($_POST['defs']);
            $icmsObj->typedDefs = $text;   // shown again as typed when the form is not stored
            try {
                $icmsObj->setVar('defs', mod_ccenter_FormSchema::normalize($text));
            } catch (InvalidArgumentException $e) {
                $icmsObj->setErrors($e->getMessage());
            }
        }
        foreach (array_keys($_POST) as $name) {
            if (strpos((string) $name, 'optvar') === 0) { // the option variables are several inputs
                $icmsObj->setVar('optvars', cc_post_optvars());
                break;
            }
        }
        // values that are not offered cannot be stored
        if (!in_array((int) $icmsObj->getVar('store', 'n'), [0, 1, 2], true)) {
            $icmsObj->setErrors('Invalid value for: ' . $icmsObj->vars['store']['form_caption']);
        }
    }
}
