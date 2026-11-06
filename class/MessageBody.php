<?php

/**
 * What a visitor submitted, stored as JSON in ccenter_message.body:
 *
 *   {"version": 1, "fields": [
 *     {"key": "Your name", "label": "Your name", "type": "text", "value": "Jan", "display": "Jan"},
 *     {"key": "Topics", "label": "Topics", "type": "checkbox", "value": ["n", "f"], "display": "News, Forum"},
 *     {"key": "CV", "label": "CV", "type": "file", "value": {"file": "cv.pdf"}, "display": "cv.pdf"}
 *   ]}
 *
 * "value" is what was submitted, "display" the text to show, mail and export. The label is the one
 * that applied when the message was sent, so renaming a form item later does not rewrite old messages.
 * Bodies written by earlier versions ("name: value" lines) are read as well; they only have display text.
 */
class mod_ccenter_MessageBody
{
    const VERSION = 1;

    /** @var array[] */
    private $fields;
    /** @var bool */
    private $legacy;

    private function __construct(array $fields, $legacy)
    {
        $this->fields = $fields;
        $this->legacy = $legacy;
    }

    /**
     * @param string|null $stored JSON or legacy text
     */
    public static function fromStored($stored)
    {
        $data = mod_ccenter_Json::tryDecodeArray($stored);
        if ($data !== null && isset($data['fields']) && is_array($data['fields'])) {
            return new self(array_values($data['fields']), false);
        }
        return self::fromLegacy((string) $stored);
    }

    public static function fromLegacy($text)
    {
        $fields = [];
        foreach (mod_ccenter_LegacyText::unserializeText($text) as $name => $display) {
            $field = ['key' => (string) $name, 'label' => (string) $name, 'type' => null, 'value' => $display, 'display' => $display, 'legacy' => true];
            if (preg_match('/^file=(.+)$/', $display, $d)) {
                $field['type'] = 'file';
                $field['value'] = ['file' => $d[1]];
            }
            $fields[] = $field;
        }
        return new self($fields, true);
    }

    /**
     * @param array[] $fields each with key, label, type, value, display
     */
    public static function fromFields(array $fields)
    {
        return new self(array_values($fields), false);
    }

    /** @return array[] */
    public function fields()
    {
        return $this->fields;
    }

    public function isLegacy()
    {
        return $this->legacy;
    }

    public function toJson()
    {
        return mod_ccenter_Json::encode(['version' => self::VERSION, 'fields' => $this->fields]);
    }

    /**
     * The shape the display and export code has always worked with: key => text.
     * Uploaded files appear as "file=<name>".
     *
     * @return array
     */
    public function toLegacyArray()
    {
        $values = [];
        foreach ($this->fields as $field) {
            $display = (string) ($field['display'] ?? '');
            if (($field['type'] ?? null) === 'file' && is_array($field['value'] ?? null) && isset($field['value']['file'])) {
                $display = 'file=' . $field['value']['file'];
            }
            $values[(string) $field['key']] = $display;
        }
        return $values;
    }

    /**
     * Labels that were stored with the message (not available for bodies written by earlier versions,
     * which only know the name of the item).
     *
     * @return array key => label
     */
    public function displayLabels()
    {
        $labels = [];
        foreach ($this->fields as $field) {
            if (empty($field['legacy'])) {
                $labels[(string) $field['key']] = (string) ($field['label'] ?? $field['key']);
            }
        }
        return $labels;
    }

    /** @return array key => label as it was when the message was sent */
    public function labels()
    {
        $labels = [];
        foreach ($this->fields as $field) {
            $labels[(string) $field['key']] = (string) ($field['label'] ?? $field['key']);
        }
        return $labels;
    }

    /** Convert whatever is stored to JSON text. */
    public static function normalize($stored)
    {
        return self::fromStored($stored)->toJson();
    }

    /** True when the stored text already is the current format. */
    public static function isCurrent($stored)
    {
        $data = mod_ccenter_Json::tryDecodeArray($stored);
        return $data !== null && isset($data['fields']) && is_array($data['fields']);
    }
}
