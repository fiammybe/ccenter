<?php
/**
 * Form control "optvars": the option variables of a contact form. They are asked for with several inputs
 * (shown after a check box) and stored as one JSON object.
 */
class CcenterOptvarsElement extends icms_form_elements_Label
{
    public function __construct($object, $key)
    {
        $var = $object->vars[$key];
        $items = get_form_attribute(_CC_OPTDEFS, _AM_OPTVARS_LABEL, 'optvar');
        $vars = $object->optionVars();
        foreach ($items as $k => $item) {
            if (isset($vars[$item['name']])) {
                $items[$k]['default'] = $vars[$item['name']];
                unset($vars[$item['name']]);
            }
        }
        $others = '';
        foreach ($vars as $name => $value) {
            $others .= "$name=$value\n";
        }
        $items[array_key_last($items)]['default'] = $others; // the free-form "others" entry is the last one
        assign_form_widgets($items);

        $inputs = '';
        foreach ($items as $item) {
            $br = $item['type'] == 'textarea' ? '<br/>' : '';
            $inputs .= '<div>' . $item['label'] . ": $br" . $item['input'] . '</div>';
        }
        $stored = (string) $object->getVar($key, 'n');
        $checked = ($stored === '' || $stored === '{}') ? '' : " checked='checked'";
        $html = "<script type='text/javascript'>document.write(\"<input type='checkbox' id='optshow' onchange='ccToggleOptions(this);'$checked/> " .
            addslashes(_AM_OPTVARS_SHOW) . "\");</script><div id='optvars'>$inputs</div>" .
            "<script type='text/javascript'>
function ccToggleOptions(a) { document.getElementById('optvars').style.display = a.checked ? 'block' : 'none'; }
var ccShow = document.getElementById('optshow'); if (ccShow) { ccToggleOptions(ccShow); }
</script>";
        parent::__construct($var['form_caption'], $html, $key);
    }
}
