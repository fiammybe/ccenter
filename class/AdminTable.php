<?php

/**
 * Helpers for the tables of the administration (icms_ipf_view_Table).
 */
class mod_ccenter_AdminTable
{
    /**
     * icms_ipf_view_Table builds its ORDER BY from the request without checking the column name, so the
     * request is cleaned before the table reads it: only the given columns can be sorted on, the direction
     * is ASC or DESC, and filters on a numeric column accept numbers only.
     *
     * @param string   $itemName   item name of the handler (prefix of the sort parameters)
     * @param string[] $sortable   columns that may be used to sort
     * @param string[] $numericFilters filter keys whose value must be a number (or "default")
     */
    public static function guardRequest($itemName, array $sortable, array $numericFilters = [])
    {
        foreach ([&$_GET, &$_POST, &$_COOKIE] as &$source) {
            $sort = $itemName . '_sortsel';
            if (isset($source[$sort]) && !in_array($source[$sort], $sortable, true)) {
                unset($source[$sort]);
            }
            $order = $itemName . '_ordersel';
            if (isset($source[$order]) && !in_array(strtoupper((string) $source[$order]), ['ASC', 'DESC'], true)) {
                unset($source[$order]);
            }
            foreach (['filtersel2'] as $name) {
                if (isset($source[$name]) && isset($source['filtersel']) && in_array($source['filtersel'], $numericFilters, true)
                    && !preg_match('/^(default|\d+)$/', (string) $source[$name])) {
                    unset($source[$name]);
                }
            }
            if (isset($source['limitsel']) && !preg_match('/^(all|\d{1,3})$/', (string) $source['limitsel'])) {
                unset($source['limitsel']);
            }
        }
        unset($source);
    }

    /** Name of the template with the security token for the bulk actions. */
    const TEMPLATE = 'ccenter_admin_table.html';

    /**
     * Controls for "with the selected": a list of actions and a button, inside the table form (with the token).
     *
     * @param array  $actions code => text
     * @param string $caption text in front of the list
     * @return string HTML
     */
    public static function bulkControls(array $actions, $caption)
    {
        $html = icms::$security->getTokenHTML();
        $html .= '<div style="padding: 5px; border: 1px solid silver; border-top: 0;">' . htmlspecialchars($caption) . ' ';
        $html .= '<select name="bulk_action"><option value="">---</option>';
        foreach ($actions as $code => $text) {
            $html .= '<option value="' . htmlspecialchars((string) $code) . '">' . htmlspecialchars((string) $text) . '</option>';
        }
        $html .= '</select> <input type="submit" name="bulk" value="' . htmlspecialchars(_AM_SUBMIT) . '" /></div>';
        return $html;
    }

    /** @return int[] ids ticked in the table (checkboxes of icms_ipf_view_Table) */
    public static function selectedIds()
    {
        $ids = [];
        foreach ((array) ($_POST['selected_icms_persistableobjects'] ?? []) as $id) {
            $ids[] = (int) $id;
        }
        return array_values(array_unique(array_filter($ids)));
    }
}
