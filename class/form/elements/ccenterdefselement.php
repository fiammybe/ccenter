<?php
/**
 * Form control "defs": the definition of a contact form, as lines of text or (?format=json) as JSON, with a
 * helper that adds an item and a link to switch between both.
 */
class CcenterDefsElement extends icms_form_elements_Label
{
    public function __construct($object, $key)
    {
        $var = $object->vars[$key];
        $format = (isset($_GET['format']) && $_GET['format'] === 'json') ? 'json' : 'lines';
        $text = $object->definitionForEditing($format);

        $html = '<textarea name="' . $key . '" id="' . $key . '" rows="10" cols="60">' . htmlspecialchars($text, ENT_QUOTES) . '</textarea>';

        if ($format === 'lines') {
            $html .= '<div id="itemhelper" style="display:none; white-space:nowrap;">' . _AM_FORM_LAB .
                ' <input name="xelab" size="10"> <input type="checkbox" name="xereq" title="' . _AM_FORM_REQ . '"> <select name="xetype">';
            foreach (mod_ccenter_FormSchema::TYPES as $type) {
                if ($type !== 'date') {
                    $html .= "<option value=\"$type\">$type</option>";
                }
            }
            $html .= '</select> <input name="xeopt" size="30" /> <button onClick="return addFieldItem();">' . _AM_FORM_ADD . '</button></div>';
        }

        // switch between lines and JSON (only for a stored form that is not being edited after a refused save)
        if (!$object->isNew() && $object->typedDefs === null) {
            $other = $format === 'json'
                ? ['lines', defined('_AM_CC_AS_LINES') ? _AM_CC_AS_LINES : 'Show as lines']
                : ['json', defined('_AM_CC_AS_JSON') ? _AM_CC_AS_JSON : 'Show as JSON'];
            $url = 'index.php?op=mod&amp;formid=' . (int) $object->getVar('formid', 'n') . '&amp;format=' . $other[0];
            $note = defined('_AM_CC_FORMAT_NOTE') ? _AM_CC_FORMAT_NOTE : '(unsaved changes are lost; both forms can be saved)';
            $html .= '<div><a href="' . $url . '">' . htmlspecialchars($other[1]) . '</a> <small>' . htmlspecialchars($note) . '</small></div>';
        }

        $html .= '<script type="text/javascript">' . self::script($format === 'lines') . '</script>';
        parent::__construct($var['form_caption'], $html, $key);
    }

    private static function script($withHelper)
    {
        $js = 'function ccForm() { return document.getElementById("addform"); }' . "\n";
        if ($withHelper) {
            $js .= 'var ccHelper = document.getElementById("itemhelper"); if (ccHelper) { ccHelper.style.display = "block"; }' . "\n";
        }
        $js .= 'function addFieldItem() {
    var f = ccForm();
    var item = f.xelab.value;
    if (item == "") { alert("' . addslashes(_AM_FORM_LABREQ) . '"); f.xelab.focus(); return false; }
    if (f.xereq.checked) item += "*";
    var ty = f.xetype.value;
    var ov = f.xeopt.value;
    item += "," + ty;
    if (ty != "text" && ty != "textarea" && ty != "file" && ty != "mail" && ov == "") {
        alert(ty + ": ' . addslashes(_AM_FORM_OPTREQ) . '"); f.xeopt.focus(); return false;
    }
    if (ov != "") item += "," + ov;
    var opts = f.defs;
    if (opts.value != "" && !opts.value.match(/[\n\r]$/)) item = "\n" + item;
    opts.value += item;
    f.xelab.value = "";   // clear old value
    f.xeopt.value = "";
    return false;         // always return false
}
function defsToString() {
    var lines = ccForm().defs.value.split("\n");
    var conf = "' . addslashes(_MD_CONF_LABEL) . '";
    var ret = "";
    for (var i in lines) {
        var lab = lines[i].replace(/,.*$/, "");
        if (lab.match(/^\s*#/)) {
            ret += "[desc]<div>" + lines[i].replace(/^\s*#/, "") + "</div>[/desc]\n";
        } else if (lab != "") {
            ret += "<div>" + lab + ": {" + lab.replace(/\*?$/, "") + "}</div>\n";
            if (lines[i].match(/^[^,]+,\s*mail/i)) {
                lab = conf.replace(/%s/, lab);
                ret += "[desc]<div>" + lab + ": {" + lab.replace(/\*?$/, "") + "}</div>[/desc]\n";
            }
        }
    }
    return "<form {FORM_ATTR}>\n" + ret + "<p>{SUBMIT} {BACK}</p>\n</form>\n{CHECK_SCRIPT}";
}';
        return $js;
    }
}
