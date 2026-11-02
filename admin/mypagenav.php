<?php
# $Id: mypagenav.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $
# page control for priuid select

// The old XoopsPageNav class has been replaced by the ImpressCMS
// View pagination class (IPF).

define("_CC_MAX_USERS", 100); // users/page

/**
 * MyPageNav extends the new IPF pagination class.
 *
 * The constructor signature matches that of `icms_view_PageNav`,
 * which expects: (total, perpage, start, name).
 * We simply forward the arguments and store the target name
 * used by the JavaScript function `setSelectUID()`.
 */
class MyPageNav extends icms_view_PageNav
{
    /** @var string Name of the select element updated by the JavaScript lookup. */
    public $target = "uid";

    public function __construct(
        $total,
        $items,
        $current,
        $name = "start",
        $target = "uid",
    ) {
        // Call the parent constructor with the same arguments.
        parent::__construct($total, $items, $current, $name);
        $this->target = $target;
    }

    /**
     * Render the navigation links.
     *
     * The output is identical to the old implementation, but it
     * relies on the IPF base class properties:
     * - $this->total
     * - $this->perpage
     * - $this->current
     *
     * @param int $offset Number of pages to display around the current page.
     * @return string HTML fragment containing the navigation links.
     */
    public function renderNav($offset = 4)
    {
        $ret = "";
        $name = $this->target;
        $fmt =
            '<a href="javascript:setSelectUID(\'' . $name . '\',%d);">%s</a> ';
        if ($this->total <= $this->perpage) {
            return $ret;
        }
        $total_pages = ceil($this->total / $this->perpage);
        if ($total_pages > 1) {
            $prev = $this->current - $this->perpage;
            if ($prev >= 0) {
                $ret .= sprintf($fmt, $prev, "<u>&laquo;</u>");
            }
            $counter = 1;
            $current_page = intval(
                floor(($this->current + $this->perpage) / $this->perpage),
            );
            while ($counter <= $total_pages) {
                if ($counter == $current_page) {
                    $ret .= "<b>(" . $counter . ")</b> ";
                } elseif (
                    ($counter > $current_page - $offset &&
                        $counter < $current_page + $offset) ||
                    $counter == 1 ||
                    $counter == $total_pages
                ) {
                    if (
                        $counter == $total_pages &&
                        $current_page < $total_pages - $offset
                    ) {
                        $ret .= "... ";
                    }
                    $ret .= sprintf(
                        $fmt,
                        ($counter - 1) * $this->perpage,
                        $counter,
                    );
                    if ($counter == 1 && $current_page > 1 + $offset) {
                        $ret .= "... ";
                    }
                }
                $counter++;
            }
            $next = $this->current + $this->perpage;
            if ($this->total > $next) {
                $ret .= sprintf($fmt, $next, "<u>&raquo;</u>");
            }
        }
        return $ret;
    }
}

/**
 * Return a list of users belonging to a group or all users.
 *
 * @param int  $group Group ID; 0 means all groups.
 * @param int  $max   Maximum number of users per page.
 * @param int  $start Offset for pagination.
 * @param bool $count If true, return only the total count.
 * @return array|int Array of uid=>name or an integer count.
 */
function cc_group_users(
    $group = 0,
    $max = _CC_MAX_USERS,
    $start = 0,
    $count = false,
) {
    $cond = empty($group) ? "" : " AND groupid=$group";
    if (!empty($_REQUEST["s"])) {
        $cond .=
            " AND uname LIKE " . icms::$xoopsDB->quoteString($_REQUEST["s"] . "%");
    }
    $sql0 =
        "FROM " .
        icms::$xoopsDB->prefix("groups_users_link") .
        " l, " .
        icms::$xoopsDB->prefix("users") .
        " u WHERE l.uid=u.uid" .
        $cond;
    if ($count) {
        $res = icms::$xoopsDB->query("SELECT DISTINCT u.uid $sql0");
        $total = icms::$xoopsDB->getRowsNum($res);
        return $total;
    }
    $res = icms::$xoopsDB->query(
        "SELECT u.uid, uname $sql0 GROUP BY u.uid ORDER BY uname",
        $max,
        $start,
    );
    $options = [];
    while ([$uid, $uname] = icms::$xoopsDB->fetchRow($res)) {
        $options[$uid] = htmlspecialchars($uname);
    }
    return $options;
}
