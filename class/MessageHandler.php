<?php

class mod_ccenter_MessageHandler extends icms_ipf_Handler
{

    public function __construct(&$db)
    {
        //parent::__construct($db, 'message', 'message_id', 'message_title', 'message_body', 'message');

        icms_loadLanguageFile(basename(dirname(__FILE__, 2)), 'common');
    }

}