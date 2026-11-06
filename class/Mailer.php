<?php

/**
 * Mails and private messages sent to the visitor, the person in charge and the contact group.
 */
class mod_ccenter_Mailer
{
    /**
     * Send a mail from a template of the module (language/<lang>/mail_template/).
     *
     * @param string                    $tpl   template file
     * @param array                     $tags  values for the template
     * @param string|array|object       $users an email address, a user object, or a list of them
     * @param string                    $from  sender address (the site address when empty)
     * @return int number of failed deliveries
     */
    public static function send($tpl, array $tags, $users, $from = '')
    {
        global $icmsConfig;

        if (is_array($users)) {
            $errors = 0;
            foreach ($users as $user) {
                $errors += self::send($tpl, $tags, $user, $from);
            }
            return $errors;
        }

        $mailer = new icms_messaging_Handler();
        if (is_object($users)) {
            switch ($users->getVar('notify_method')) {
                case XOOPS_NOTIFICATION_METHOD_PM:
                    $mailer->usePM();
                    $mailer->setFromUser(is_object(icms::$user) ? icms::$user : new icms_member_user_Object());
                    break;
                case XOOPS_NOTIFICATION_METHOD_EMAIL:
                    $mailer->useMail();
                    break;
                case XOOPS_NOTIFICATION_METHOD_DISABLE:
                    return 0;
                default:
                    return 1;
            }
            $mailer->setToUsers($users);
        } else {
            if (empty($users)) {
                return 0;
            }
            $mailer->useMail();
            $mailer->setToEmails($users);
        }

        $mailer->setFromEmail($from ? $from : $icmsConfig['adminmail']);
        $mailer->setFromName(icms::$module->getVar('name'));
        $mailer->setSubject(_CC_NOTIFY_SUBJ);
        $comment = get_attr_value(null, 'reply_comment', '');
        if (get_attr_value(null, 'reply_use_comtpl')) {
            $mailer->setBody($comment);
        } else {
            $mailer->assign('REPLY_COMMENT', $comment);
            $mailer->setTemplateDir(template_dir($tpl));
            $mailer->setTemplate($tpl);
        }
        $mailer->assign($tags);
        return $mailer->send() ? 0 : 1;
    }
}
