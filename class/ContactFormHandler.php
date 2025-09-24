<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactFormHandler
{

    public function get_form_attribute($defs, $labels = '', $prefix = "cc")
    {

        $labs = unserialize_vars($labels);
        $num = 0;
        $result = array();
        $types = array('text', 'checkbox', 'radio', 'textarea', 'select', 'hidden', 'const', 'mail', 'file', 'date');
        foreach (cc_csv_parse($defs) as $opts) {
            if (empty($opts)) continue;
            if (preg_match('/^#/', $opts[0])) {
                $result[] = array('comment' => substr(implode(',', $opts), 1));
                continue;
            }
        }
    }

    public function assign_post_values(&$items)
    {

        $errors = array();
        foreach ($items as $key => $item) {
            if (empty($item['field'])) {
                continue;
            }
        }
    }

    public function assign_form_widgets(&$items, $conf = false)
    {

        $mconf = !$conf;
        $updates = array();
        foreach ($items as $item) {
            if (empty($item['field'])) { // comment only
                $updates[] = $item;
                continue;
            }
        }
    }

    public function cc_make_widget($item, $vars = null)
    {

        $fname = $item['field'];
        $value = null;
        $type = $item['type'];
        $options = &$item['options'];
        if (isset($_POST[$fname])) {
            $value = &$_POST[$fname];
            if (!is_array($value)) {
                $value = icms_core_DataFilter::stripSlashesGPC($value);
            }
        }
    }

    public function check_form_tags($cust, $defs, $desc)
    {

        global $icmsConfig;

        switch ($cust) {        // check only custom form
            case _CC_TPL_NONE:
            case _CC_TPL_NONE_HTML:
                return '';
        }

    }

    public function custom_template($form, $items, $conf = false)
    {

        global $icmsConfig;
        $str = $rep = array();
        $hasfile = "";
        foreach ($items as $item) {
            $value = empty($item['input']) ? "" : $item['input'];
            if (!empty($item['comment'])) {
                $value .= "<span class='note'>" . $item['comment'] . "</span>";
            }
        }
    }
}
