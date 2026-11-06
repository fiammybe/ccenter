<?php

require_once __DIR__ . '/autoload.php';

class mod_ccenter_LogHandler extends icms_ipf_Handler
{
    public function __construct(&$db)
    {
        parent::__construct($db, 'log', 'logid', 'logid', 'comment', 'ccenter');
    }

    /**
     * Write one line to the history.
     *
     * @param int    $formId
     * @param string $comment line breaks are shown as ", "
     * @param int    $msgId
     * @param int    $uid    who did it
     * @return bool
     */
    public function record($formId, $comment, $msgId = 0, $uid = 0)
    {
        $log = $this->create();
        $log->setValues([
            'ltime' => time(),
            'fidref' => (int) $formId,
            'midref' => (int) $msgId,
            'euid' => (int) $uid,
            'comment' => preg_replace('/\n/', ', ', (string) $comment),
        ]);
        return (bool) $this->insert($log, true);
    }

    /**
     * History of one message, newest first.
     *
     * @return mod_ccenter_Log[]
     */
    public function forMessage($msgId)
    {
        $criteria = new icms_db_criteria_Compo(new icms_db_criteria_Item('midref', (int) $msgId));
        $criteria->setSort('logid');
        $criteria->setOrder('DESC');
        return $this->getObjects($criteria);
    }
}
