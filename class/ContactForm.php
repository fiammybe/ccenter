<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactForm extends icms_form_Theme {
    public function __construct($formConfig) {
        parent::__construct($formConfig['title'], 'ccenter', $formConfig['action']);
        $this->setExtra('enctype="multipart/form-data"');
        $this->addFormElements($formConfig['items']);
    }

    protected function addFormElements($items) {
        foreach ($items as $item) {
            switch ($item['type']) {
                case 'text':
                    $icms_form_elements_Text = new icms_form_elements_Text($item['label'], $item['field'], 50, 255, $item['value']);
                    $this->addElement($icms_form_elements_Text);
                    break;
                case 'checkbox':
                    $icms_form_elements_Checkbox = new icms_form_elements_Checkbox($item['label'], $item['field'], $item['value'], $item['options']);
                    $this->addElement($icms_form_elements_Checkbox);
                    break;
                // Add other field types
            }
        }
    }
}
