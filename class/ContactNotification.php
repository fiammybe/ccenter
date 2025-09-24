<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

interface NotificationInterface {
    public function send($tpl, $tags, $users, $from);
}

class ContactNotification implements NotificationInterface {

    public function __construct() {
        // Constructor logic
    }

    public function send($tpl, $tags, $users, $from="") {

            global $icmsConfig;
            $xoopsMailer = new icms_messaging_Handler();
            if (is_array($users)) {
                $err = 0;
                foreach ($users as $u) {
                    $err += cc_notify_mail($tpl, $tags, $u, $from);
                }
            }
    }
}
