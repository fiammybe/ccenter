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
    public static function fromStored($stored, ?mod_ccenter_FormSchema $schema = null)
    {
        $data = mod_ccenter_Json::tryDecodeArray($stored);
        if ($data !== null && isset($data['fields']) && is_array($data['fields'])) {
            return new self(array_values($data['fields']), false);
        }
        return self::fromLegacy((string) $stored, $schema);
    }

    /**
     * Read a body written by an earlier version. Those bodies hold the submitted values as flat text
     * (the keys of the chosen options, not their labels). With the definition of the form the type,
     * label and readable text of every field can be filled in.
     *
     * @param string                       $text
     * @param mod_ccenter_FormSchema|null  $schema definition of the form the message was sent with
     */
    public static function fromLegacy($text, ?mod_ccenter_FormSchema $schema = null)
    {
        $fields = [];
        foreach (mod_ccenter_LegacyText::unserializeText($text) as $name => $raw) {
            $item = $schema ? $schema->item($name) : null;
            $field = ['key' => (string) $name, 'label' => (string) $name, 'type' => null, 'value' => $raw, 'display' => $raw, 'legacy' => true];
            if (preg_match('/^file=(.+)$/', $raw, $d)) {
                $field['type'] = 'file';
                $field['value'] = ['file' => $d[1]];
                $field['display'] = $d[1];
            } elseif ($item) {
                $field['type'] = $item['type'] ?? 'text';
                $field['label'] = (string) ($item['label'] ?? $name);
                $labels = [];
                foreach ($item['options'] ?? [] as $option) {
                    $labels[(string) $option['value']] = strip_tags((string) ($option['label'] ?? $option['value']));
                }
                if ($field['type'] === 'radio' || $field['type'] === 'select') {
                    $field['display'] = isset($labels[$raw]) ? $labels[$raw] : $raw;
                } elseif ($field['type'] === 'checkbox') {
                    $keys = array_values(array_filter(preg_split('/,\s?/', $raw), 'strlen'));
                    $field['value'] = $keys;
                    $field['display'] = implode(', ', array_map(function ($k) use ($labels) {
                        return isset($labels[$k]) ? $labels[$k] : $k;
                    }, $keys));
                }
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
            // a field converted from the earlier format has a real label only when the form definition was known
            if (empty($field['legacy']) || !empty($field['type'])) {
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
    public static function normalize($stored, ?mod_ccenter_FormSchema $schema = null)
    {
        return self::fromStored($stored, $schema)->toJson();
    }

    /** True when the stored text already is the current format. */
    public static function isCurrent($stored)
    {
        $data = mod_ccenter_Json::tryDecodeArray($stored);
        return $data !== null && isset($data['fields']) && is_array($data['fields']);
    }
}
