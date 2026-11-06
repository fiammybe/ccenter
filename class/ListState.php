<?php

/**
 * What a visitor chose on a list page (status combination, sort column and direction), remembered in the session.
 * It produces no SQL: the pages give the values to the message handler, which only accepts known statuses and sort keys.
 */
class mod_ccenter_ListState
{
    /** @var string */
    private $name;
    /** @var array */
    private $vars;
    /** @var array label => status codes separated by spaces */
    private $combo;

    /**
     * @param string $name    key in the session
     * @param array  $init    start values: sort keys with ASC|DESC, "orders" (sort keys, most important first), optional "stat"
     * @param string $combo   the status combinations of the preferences
     * @param array  $session the session array (by reference)
     * @param array  $request the request values (reset=yes, stat, sort keys)
     */
    public function __construct($name, array $init, $combo, array &$session, array $request)
    {
        $this->name = $name;
        $this->combo = mod_ccenter_LegacyText::unserializeText($combo);
        if (!isset($session['listctrl'])) {
            $session['listctrl'] = [];
        }
        if (!isset($session['listctrl'][$name]) || (($request['reset'] ?? '') === 'yes')) {
            if (!isset($init['stat'])) {
                $init['stat'] = (string) (array_values($this->combo)[0] ?? '');
            }
            $session['listctrl'][$name] = $init;
        }
        $this->vars = &$session['listctrl'][$name];
        $this->update($request);
    }

    /** The state of the current visitor and request. */
    public static function forRequest($name, array $init)
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION)) {
            $_SESSION = [];
        }
        return new self($name, $init, icms::$module->config['status_combo'], $_SESSION, $_REQUEST);
    }

    public function get($name)
    {
        return $this->vars[$name] ?? '';
    }

    /** @return string status codes separated by spaces */
    public function statuses()
    {
        return (string) $this->get('stat');
    }

    /** @return array of [sort key, ASC|DESC], most important first */
    public function orders()
    {
        $orders = [];
        foreach ((array) $this->get('orders') as $key) {
            $orders[] = [$key, strtoupper((string) $this->get($key)) === 'DESC' ? 'DESC' : 'ASC'];
        }
        return $orders;
    }

    private function update(array $request)
    {
        foreach (array_keys($this->vars) as $k) {
            if ($k === 'orders' || !isset($request[$k]) || !is_string($request[$k])) {
                continue;
            }
            $val = trim($request[$k]);
            if ($val === '') {
                continue;
            }
            if ($k === 'stat') {
                $val = preg_replace('/[^a-dx\- ]/', '', $val);
            } else {
                $val = strtolower($val) === 'asc' ? 'ASC' : 'DESC';
                $orders = (array) $this->get('orders');
                if ($orders && $k != $orders[0]) {
                    $this->vars['orders'] = [$k, $orders[0]];
                }
            }
            $this->vars[$k] = $val;
        }
    }

    /** Column heads for the template: text, name, current direction (value), the direction a click chooses (next). */
    public function labels(array $labels)
    {
        $result = [];
        $orders = (array) $this->get('orders');
        foreach ($labels as $k => $v) {
            $lab = ['text' => $v, 'name' => $k, 'extra' => ''];
            if (isset($this->vars[$k])) {
                $n = array_search($k, $orders);
                if (is_int($n)) {
                    $val = strtolower((string) $this->get($k));
                    $lab['value'] = $val;
                    $lab['next'] = $val === 'desc' ? 'asc' : 'desc';
                    $lab['extra'] = " class='ccord$n'";
                } else {
                    $lab['value'] = 'none';
                    $lab['next'] = 'asc';
                }
            }
            $result[] = $lab;
        }
        return $result;
    }

    /** The select box with the status combinations. */
    public function renderStat()
    {
        $ctrl = "<select name='stat' onChange='submit();'>\n";
        $stat = $this->get('stat');
        foreach ($this->combo as $k => $v) {
            $ck = $v == $stat ? " selected='selected'" : '';
            $ctrl .= '<option value="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"' . $ck . '>' . htmlspecialchars((string) $k, ENT_QUOTES) . "</option>\n";
        }
        return $ctrl . '</select>';
    }
}
