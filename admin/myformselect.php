<?php
/**
 * @file
 * MyFormSelect – a select field with AJAX‑powered user lookup.
 *
 * Replaces the legacy XoopsFormSelect/XoopsPageNav classes with the
 * current IPF classes (`icms_view_FormSelect` and `icms_view_PageNav`).
 *
 * @package ccenter
 * @since 2013
 */

# $Id: myformselect.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

class MyFormSelect extends icms_view_FormSelect
{
    /**
     * @var string  Placeholder for the page‑navigation markup.
     */
    public $pagenav = '';

    /**
     * @var string  Label used for the search button.
     */
    public $slab;

    /**
     * Constructor.
     *
     * @param string $caption  The field caption.
     * @param string $name     The field name.
     * @param mixed  $value    The pre‑selected value (user ID).
     * @param int    $size     Number of visible rows.
     * @param bool   $multiple Whether the select accepts multiple values.
     */
    public function __construct(
        $caption,
        $name,
        $value = null,
        $size = 1,
        $multiple = false
    ) {
        parent::__construct($caption, $name, $value, $size, $multiple);
        $this->pagenav = '';
        $this->slab    = _SEARCH;
    }

    /**
     * Populate the <select> with users from a group.
     *
     * @param int $gid Group ID to pull users from.
     */
    public function addOptionUsers($gid = 0)
    {
        [$cuid] = $this->getValue();
        $max    = _CC_MAX_USERS;
        $start  = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
        $users  = cc_group_users($gid, $max, $start);
        $opts   = $this->getOptions();

        // Ensure the currently selected user is present even if not in the current page.
        if ($cuid && !isset($users[$cuid]) && !isset($opts[$cuid])) {
            $users[$cuid] = icms_member_user_Object::getUnameFromId($cuid);
        }
        $this->addOptionArray($users);
        $this->setPageNav($gid);
    }

    /**
     * Create the pagination controls.
     *
     * @param int $gid Group ID used to calculate the total number of users.
     */
    public function setPageNav($gid)
    {
        $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
        $max   = _CC_MAX_USERS;
        $total = cc_group_users($gid, $max, $start, true);

        // Instantiate the IPF PageNav class.
        $nav        = new icms_view_PageNav(
            $total,
            $max,
            $start,
            'start',
            $this->getName()
        );
        $this->pagenav = $nav->render();
    }

    /**
     * Override the search button label.
     *
     * @param string $str New label.
     */
    public function setSearchLabel($str)
    {
        $this->slab = $str;
    }

    /**
     * Render the field with the search box and pagination.
     *
     * @return string The generated HTML.
     */
    public function render()
    {
        $name = $this->getName();
        $s    = htmlspecialchars(
            isset($_REQUEST[$name . '_s']) ? $_REQUEST[$name . '_s'] : '',
            ENT_QUOTES,
            'UTF-8'
        );
        $slab = htmlspecialchars($this->slab, ENT_QUOTES, 'UTF-8');
        return "<table cellpadding='0'>\n<tr valign='top'>"
            . "<td align='center'>"
            . parent::render()
            . "<div id='{$name}_page'>{$this->pagenav}</div>"
            . "</td>"
            . "<td width='100%'> &nbsp; "
            . "<input size='8' name='{$name}_s' id='{$name}_s' value='$s' "
            . "onChange='setSelectUID(\"$name\",0);'/><input type='submit' "
            . "value='$slab' onClick='setSelectUID(\"$name\", 0); return false;'/>"
            . "</td></tr>\n</table>";
    }

    /**
     * Return the JavaScript needed for AJAX‑based user lookup.
     *
     * @param bool $withtags Wrap the script with HTML comments.
     *
     * @return string The script tag content.
     */
    public function renderSupportJS($withtags = true)
    {
        $name = $this->getName();
        $js   = '';
        if ($withtags) {
            $js .= "\n<!-- Start UID Selection JavaScript //-->\n"
                . "<script type='text/javascript'>\n<!--//\n";
        }
        $js .=
            '// XMLHttpRequest general handler
function createXmlHttp(){
    if (window.XMLHttpRequest) {             // Mozilla, Firefox, Safari, IE7
        return new XMLHttpRequest();
    } else if (window.ActiveXObject) {       // IE5, IE6
        try {
            return new ActiveXObject("Msxml2.XMLHTTP");    // MSXML3
        } catch(e) {
            return new ActiveXObject("Microsoft.XMLHTTP"); // until MSXML2
        }
    } else {
        return null;
    }
}
';
        $js .= '
function setSelectUID(name, start) {
    var xmlhttp = createXmlHttp();
    var search = xoopsGetElementById(name+"_s");
    var gid    = xoopsGetElementById("cgroup");
    if (xmlhttp == null) return;	// XMLHttpRequest not support
    var url = "getusers.php?gid=" + gid.value + "&start="+start;
    if (search) url += "&s="+search.value;
    xmlhttp.open("GET", url, false);
    xmlhttp.send(null);
    var obj = xoopsGetElementById(name);
    var opts = obj.options;
    var defs = obj.value;
    if (xmlhttp.status == 200) {
        var len = 0;
        for (var i=0; i<opts.length; i++) {
            if (opts[i].value == 0) {
                len = ++i;
                break;
            }
        }
        opts.length = len;
        var F = xmlhttp.responseText.split("<!---->\\n");
        var lines = F[0].split("\\n");
        for (var i in lines) {
            var el = lines[i].split(",", 2);
            if (el.length < 2) continue;
            var p = opts.length++;
            opts[p].value = el[0];
            opts[p].text  = el[1];
        }
        obj.value = defs;
        var page = xoopsGetElementById(name+"_page");
        page.innerHTML = F[1].replace(/\\'uid\\'/g, "\\'"+name+"\\'");
    }
}
';
        if ($withtags) {
            $js .= "//--></script>\n<!-- End UID Selection JavaScript //-->\n";
        }
        return $js;
    }
}
