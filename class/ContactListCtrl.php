<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactListCtrl {
    var $name;
    var $vars;
    var $combo;

    /**
     * Constructor for ListCtrl
     *
     * Initializes list control with session-based state management.
     * Sets up default values and loads configuration from module settings.
     *
     * @param string $name Unique identifier for this list control
     * @param array $init Default initialization values
     * @param string $combo Status combo configuration (uses module config if empty)
     * @since 1.0.0
     */
    function __construct($name, $init=array(), $combo='') {
        if (empty($combo)) {

            $combo = icms::$module->config['status_combo'];
        }
        $this->name = $name;
        $this->combo = unserialize_text($combo);
        if (!isset($_SESSION['listctrl'])) $_SESSION['listctrl'] = array();
        if (!isset($_SESSION['listctrl'][$name]) ||
            (isset($_GET['reset'])&&$_GET['reset']=='yes')) {
            if (!isset($init['stat'])) {
                list($init['stat']) = array_values($this->combo);
            }
            $_SESSION['listctrl'][$name] = $init;
        }
        $this->vars =& $_SESSION['listctrl'][$name];
        $this->updateVars($_REQUEST);
    }

    /**
     * Retrieves a control variable value
     *
     * @param string $name Variable name to retrieve
     * @return mixed Variable value or empty string if not set
     * @since 1.0.0
     */
    function getVar($name) {
        return $this->vars[$name] ?? "";
    }

    /**
     * Sets a control variable value
     *
     * @param string $name Variable name to set
     * @param mixed $val Value to assign
     * @since 1.0.0
     */
    function setVar($name, $val) { $this->vars[$name]=$val; }

    /**
     * Generates sortable column labels with sorting indicators
     *
     * Creates label arrays with sorting state information for table headers.
     * Includes CSS classes and next sort direction for interactive sorting.
     *
     * @param array $labels Array of column labels keyed by field name
     * @return array Enhanced label array with sorting metadata
     * @since 1.0.0
     */
    function getLabels($labels) {
        $result = array();
        $orders = $this->getVar('orders');
        foreach ($labels as $k => $v) {
            $lab = array('text'=>$v, 'name'=>$k);
            if (isset($this->vars[$k])) { // with ctrl
                $n = array_search($k, $orders, true);
                if (is_int($n)) {
                    $val = strtolower($this->getVar($k));
                    $lab['value'] = $val;
                    $lab['next'] = $val=='desc'?'asc':'desc';
                    $lab['extra'] = " class='ccord$n'";
                } else {
                    $lab['value'] = 'none';
                    $lab['next'] = 'asc';
                }
            }
            $result[] = $lab;
        }
        return $result;
    }

    /**
     * Updates control variables from request parameters
     *
     * Processes form/URL parameters to update sorting and filtering state.
     * Handles status filtering and column sorting with proper validation.
     *
     * @param array $args Request parameters ($_REQUEST, $_GET, $_POST)
     * @return array Array of changed variables
     * @since 1.0.0
     */
    function updateVars($args) {
        $changes = array();
        foreach (array_keys($this->vars) as $k) {
            if (isset($args[$k])) {
                $val = trim($args[$k]);
                if (empty($val)) continue;
                switch ($k) {
                    case 'stat':
                        $val = preg_replace('/[^a-dx\- ]/', '', trim($val));
                        break;
                    default:
                        $val = strtolower($val)=='asc'?'ASC':'DESC';
                        $orders = $this->getVar('orders');
                        if ($k != $orders[0]) {
                            $this->setVar('orders', array($k, $orders[0]));
                        }
                }
                $this->setVar($k, $val);
                $changes[$k] = $val;
            }
        }
        return $changes;
    }

    /**
     * Generates SQL WHERE condition for status filtering
     *
     * Creates appropriate SQL condition based on current status filter.
     * Handles both single status and multiple status filtering.
     *
     * @param string $fname Database field name for status (default: 'status')
     * @return string SQL WHERE condition
     * @since 1.0.0
     */
    function sqlcondition($fname='status') {
        $stat = $this->getVar('stat');
        if (preg_match('/\s+/', $stat)) {
            return "$fname IN ('".implode("','", preg_split('/\s+/',$stat))."')";
        }
        return "$fname=".icms::$xoopsDB->quoteString($stat);
    }

    /**
     * Generates SQL ORDER BY clause from current sort settings
     *
     * Creates SQL ordering clause based on current column sort configuration.
     * Supports multiple column sorting with proper direction handling.
     *
     * @return string SQL ORDER BY clause (including "ORDER BY" keywords)
     * @since 1.0.0
     */
    function sqlorder() {
        $order = array();
        foreach ($this->getVar('orders') as $name) {
            $order[] = $name." ".$this->getVar($name);
        }
        if ($order) return " ORDER BY ".implode(',', $order);
        return "";
    }

    /**
     * Renders HTML select dropdown for status filtering
     *
     * Creates an HTML select element with status options and JavaScript
     * auto-submit functionality. Shows current selection state.
     *
     * @return string HTML select element for status filtering
     * @since 1.0.0
     */
    function renderStat() {
        $ctrl = "<select name='stat' onChange='submit();'>\n";
        $stat = $this->getVar('stat');
        foreach ($this->combo as $k => $v) {
            $ck = $v == $stat?" selected='selected'":"";
            $ctrl .= "<option value='$v'$ck>$k</option>\n";
        }
        $ctrl .= "</select>";
        return $ctrl;
    }

}
