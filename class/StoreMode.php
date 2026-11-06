<?php

/**
 * How a form keeps what visitors send (ccenter_form.store).
 */
class mod_ccenter_StoreMode
{
    const LOG = 0;  // only a line in the log
    const YES = 1;  // the message is stored
    const NONE = 2; // nothing is stored, the mail is the only record
}
