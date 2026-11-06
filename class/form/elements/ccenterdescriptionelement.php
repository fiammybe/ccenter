<?php
/**
 * Form control "description": the description of a contact form with an editor, the button that inserts a form
 * template made from the definition, and the check of the template tags.
 */
class CcenterDescriptionElement extends icms_form_elements_Label
{
    public function __construct($object, $key)
    {
        $var = $object->vars[$key];
        $editor = new icms_form_elements_Dhtmltextarea('', $key, (string) $object->getVar($key, 'n'), 10, 60);
        $html = $editor->render();
        $html .= '<br /><input type="button" name="ins_tpl" id="ins_tpl" value="' . htmlspecialchars(_AM_INS_TEMPLATE) .
            '" onclick="ccForm().description.value += defsToString();" />';

        // are the {tags} of a template description complete?
        $defs = $object->definitionForEditing('lines');
        if (cc_definition_valid($defs)) {
            $error = check_form_tags((int) $object->getVar('custom', 'n'), $defs, (string) $object->getVar($key, 'n'));
            if ($error) {
                $html .= "<div style='color:red;'>$error</div>";
            }
        }

        // the button only makes sense for a template description
        $html .= '<script type="text/javascript">
(function () {
  var f = document.getElementById("addform");
  function toggle() { document.getElementById("ins_tpl").disabled = (f.custom.value == 0 || f.custom.value == 4); }
  f.custom.addEventListener("change", toggle);
  toggle();
})();
</script>';
        parent::__construct($var['form_caption'], $html, $key);
    }
}
