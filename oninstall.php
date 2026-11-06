<?php
/**
 * ccenter module onInstall processing.
 *
 * Creates the sample contact form. This also runs from the ImpressCMS installation wizard,
 * where there is no logged-in user, so the primary contact falls back to the webmaster (uid 1).
 */

register_shutdown_function('ccenter_sample_form');

function ccenter_sample_form()
{
    global $msgs;

    require_once dirname(__FILE__) . '/class/autoload.php';

    $handler = icms_getModuleHandler('form', 'ccenter');
    $form = $handler->create();
    $form->setValues([
        'mtime' => time(),
        'title' => _MI_SAMPLE_TITLE,
        'description' => _MI_SAMPLE_DESC,
        'defs' => mod_ccenter_FormSchema::normalize(_MI_SAMPLE_DEFS),
        'optvars' => '{}',
        'priuid' => is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 1,
    ]);
    $form->setAllowedGroups([ICMS_GROUP_ANONYMOUS, ICMS_GROUP_USERS]);
    $handler->insert($form, true);
    $handler->setAllowedGroups((int) $form->getVar('formid', 'n'), [ICMS_GROUP_ANONYMOUS, ICMS_GROUP_USERS]);
    $msgs[] = '&nbsp;&nbsp;<b>' . _MI_SAMPLE_FORM . '</b>';
}
