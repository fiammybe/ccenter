<?php
/**
 * PHPUnit bootstrap: a minimal stand-in for the parts of ImpressCMS that functions.php touches
 * while it is included, so the pure parsing/serialising helpers can be tested without a CMS.
 */

if (!class_exists('icms')) {
    class FakeDb
    {
        public function prefix($table = '')
        {
            return 'icms_' . $table;
        }

        public function quoteString($s)
        {
            return "'" . addslashes((string) $s) . "'";
        }
    }

    class FakeModule
    {
        public $config = ['def_attrs' => "size=60\nrows=5\ncols=50\nnotify_with_email=0", 'max_lists' => 25];

        public function getVar($name)
        {
            return ['dirname' => basename(dirname(__DIR__, 2)), 'mid' => 2, 'name' => 'Contact Center'][$name] ?? null;
        }
    }

    class icms
    {
        public static $xoopsDB;
        public static $module;
        public static $user = null;
    }

    class icms_member_user_Object
    {
        public function getVars()
        {
            return ['uid' => 1, 'uname' => 1, 'name' => 1, 'email' => 1];
        }
    }

    icms::$xoopsDB = new FakeDb();
    icms::$module = new FakeModule();
}

if (!function_exists('formatTimestamp')) {
    function formatTimestamp($time, $format = 'l')
    {
        return gmdate('Y-m-d', (int) $time);
    }
}

$GLOBALS['icmsConfig'] = ['language' => 'english'];
foreach (['_MD_REQUIRE_MARK' => '*', '_YES' => 'Yes', '_NO' => 'No'] as $k => $v) {
    defined($k) or define($k, $v);
}

require_once dirname(__DIR__, 2) . '/functions.php';
