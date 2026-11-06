<?php

/**
 * Converts data stored by earlier versions to the current formats (JSON), safely and repeatedly:
 *  - ccenter_form.defs     line based definition  -> JSON form schema
 *  - ccenter_form.optvars  key=value lines        -> JSON object
 *  - ccenter_message.body  "name: value" text     -> JSON message body
 *  - the module preference def_attrs              -> JSON object
 * Rows that are already converted are skipped, so running it again changes nothing and a run that
 * stopped early (time limit) is simply continued by the next one. Until everything is converted the
 * module reads both formats.
 */
class mod_ccenter_Migration
{
    /** Messages converted per query. */
    const BATCH = 200;

    /** @var string[] report lines */
    private $report = [];
    /** @var array formid => mod_ccenter_FormSchema|null */
    private $schemas = [];

    /**
     * @param int $seconds time budget for the conversion of messages
     * @return string[] report lines (for the module update screen)
     */
    public static function run($seconds = 20)
    {
        $m = new self();
        $m->convertPreference();
        $m->convertForms();
        $m->convertPermissions();
        $m->convertMessages(time() + max(1, (int) $seconds));
        return $m->report;
    }

    private function say($line)
    {
        $this->report[] = '&nbsp;&nbsp;' . $line;
    }

    public function convertPreference()
    {
        $db = icms::$xoopsDB;
        $module = icms::handler('icms_module')->getByDirname('ccenter');
        if (!is_object($module)) {
            return;
        }
        $table = $db->prefix('config');
        $res = $db->query("SELECT conf_id, conf_value FROM $table WHERE conf_modid=" . (int) $module->getVar('mid') . " AND conf_name='def_attrs'");
        $row = $res ? $db->fetchArray($res) : false;
        if (!$row || mod_ccenter_OptionVars::isCurrent($row['conf_value'])) {
            return;
        }
        $json = mod_ccenter_OptionVars::normalize($row['conf_value']);
        $db->queryF("UPDATE $table SET conf_value=" . $db->quoteString($json) . ' WHERE conf_id=' . (int) $row['conf_id']);
        $this->say('Converted preference <b>def_attrs</b> to JSON');
    }

    public function convertForms()
    {
        $handler = icms_getModuleHandler('form', 'ccenter');
        $count = 0;
        foreach ($handler->getObjects() as $form) {
            $defs = (string) $form->getVar('defs', 'n');
            $optvars = (string) $form->getVar('optvars', 'n');
            $changed = false;
            if (!self::isSchema($defs)) {
                try {
                    $form->setVar('defs', mod_ccenter_FormSchema::normalize($defs));
                    $changed = true;
                } catch (InvalidArgumentException $e) {
                    $this->say('Form <b>' . (int) $form->getVar('formid', 'n') . '</b> was left as it was: ' . htmlspecialchars($e->getMessage()));
                }
            }
            if (!mod_ccenter_OptionVars::isCurrent($optvars)) {
                $form->setVar('optvars', mod_ccenter_OptionVars::normalize($optvars));
                $changed = true;
            }
            if ($changed && $handler->insert($form, true)) {
                $count++;
            }
        }
        if ($count) {
            $this->say("Converted the definition of <b>$count</b> form(s) to JSON");
        }
    }

    /**
     * The groups that may use a form were kept as text ("|1|2|") in the form; they become ImpressCMS group
     * permissions. The text stays in the form (and is kept in step) so that a downgrade keeps working.
     */
    public function convertPermissions()
    {
        $handler = icms_getModuleHandler('form', 'ccenter');
        $count = 0;
        foreach ($handler->getObjects() as $form) {
            $id = (int) $form->getVar('formid', 'n');
            $groups = $form->allowedGroups();
            if (!$groups || $this->hasPermissionRows($handler, $id)) {
                continue;
            }
            $handler->setAllowedGroups($id, $groups);
            $count++;
        }
        if ($count) {
            $this->say("Converted the access groups of <b>$count</b> form(s) to ImpressCMS group permissions");
        }
    }

    private function hasPermissionRows($handler, $formId)
    {
        $db = icms::$xoopsDB;
        $res = $db->query(
            'SELECT COUNT(*) FROM ' . $db->prefix('group_permission') . ' WHERE gperm_name=' . $db->quoteString($handler::PERM_VIEW) .
            ' AND gperm_modid=' . $handler->moduleId() . ' AND gperm_itemid=' . (int) $formId
        );
        $row = $res ? $db->fetchRow($res) : [0];
        return (int) $row[0] > 0;
    }

    /** True when the text is a valid JSON form schema. */
    private static function isSchema($text)
    {
        $data = mod_ccenter_Json::tryDecodeArray($text);
        return $data !== null && isset($data['items']) && !mod_ccenter_FormSchema::validate($data);
    }

    /**
     * @param int $deadline timestamp after which no new batch is started
     */
    public function convertMessages($deadline)
    {
        $db = icms::$xoopsDB;
        $table = $db->prefix('ccenter_message');
        $last = 0;
        $done = 0;
        do {
            $res = $db->query(
                "SELECT msgid, fidref, body FROM $table WHERE msgid > $last AND body IS NOT NULL AND body <> '' " .
                "AND body NOT LIKE '{%' ORDER BY msgid",
                self::BATCH
            );
            $rows = [];
            while ($res && ($row = $db->fetchArray($res))) {
                $rows[] = $row;
            }
            foreach ($rows as $row) {
                $last = (int) $row['msgid'];
                $json = mod_ccenter_MessageBody::normalize($row['body'], $this->schemaOf((int) $row['fidref']));
                $db->queryF("UPDATE $table SET body=" . $db->quoteString($json) . ' WHERE msgid=' . $last);
                $done++;
            }
        } while ($rows && time() < $deadline);

        if ($done) {
            $this->say("Converted <b>$done</b> message(s) to JSON");
        }
        if ($rows) {
            $this->say('The time limit was reached: run the module update again to convert the remaining messages (they can be read meanwhile).');
        }
    }

    /** @return mod_ccenter_FormSchema|null */
    private function schemaOf($formId)
    {
        if (!array_key_exists($formId, $this->schemas)) {
            $form = icms_getModuleHandler('form', 'ccenter')->find($formId);
            try {
                $this->schemas[$formId] = $form ? $form->schema() : null;
            } catch (InvalidArgumentException $e) {
                $this->schemas[$formId] = null;
            }
        }
        return $this->schemas[$formId];
    }
}
