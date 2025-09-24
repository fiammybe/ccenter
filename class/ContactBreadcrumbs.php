<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactBreadcrumbs extends icms_view_Breadcrumb
{
    public function __construct()
    {
        parent::__construct();
    }

    public function set($title, $url)
    {
        $this->_items[] = array('title' => $title, 'url' => $url);
    }

    public function assign()
    {
        global $icmsTpl;
        $icmsTpl->assign('contact_breadcrumbs', $this->_items);
    }

    public function render($fetchOnly = FALSE)
    {
        return parent::render();
    }

    public function get()
    {
        return $this->_items;
    }
}
