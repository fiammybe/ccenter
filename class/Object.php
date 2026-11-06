<?php

/**
 * Base class of the module's persistable objects.
 */
abstract class mod_ccenter_Object extends icms_ipf_Object
{
    /**
     * Raw column values, like a database row (no HTML escaping, no formatting).
     *
     * @return array
     */
    public function toRow()
    {
        $row = [];
        foreach ($this->vars as $key => $info) {
            if (!empty($info['persistent'])) {
                $row[$key] = $this->vars[$key]['value'];
            }
        }
        return $row;
    }

    /**
     * Set several columns at once.
     *
     * @param array $values column => value
     */
    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $this->setVar($key, $value);
        }
    }
}
