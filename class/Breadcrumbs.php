<?php

/** The trail of links above a page, as the array the themes read (xoops_breadcrumbs: name and url). */
class mod_ccenter_Breadcrumbs
{
    /** @var string */
    private $base;
    /** @var array */
    private $pairs;

    public function __construct($moduleName, $moduleUrl)
    {
        $this->base = rtrim($moduleUrl, '/') . '/';
        $this->pairs = [['name' => $moduleName, 'url' => $this->base]];
    }

    public static function forModule()
    {
        return new self(icms::$module->getVar('name'), ICMS_URL . '/modules/' . icms::$module->getVar('dirname') . '/');
    }

    /** @param string $url module-relative, or absolute; empty for the current page */
    public function add($name, $url)
    {
        if ($url !== '' && !preg_match('/^\w+:\/\//', $url)) {
            $url = $this->base . $url;
        }
        $this->pairs[] = ['name' => htmlspecialchars(strip_tags((string) $name), ENT_QUOTES), 'url' => $url];
        return $this;
    }

    /** @return array the last entry (the current page) has no link */
    public function get()
    {
        $ret = $this->pairs;
        $ret[array_key_last($ret)]['url'] = '';
        return $ret;
    }

    public function assign($tpl)
    {
        $tpl->assign('xoops_breadcrumbs', $this->get());
    }
}
