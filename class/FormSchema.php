<?php

/**
 * The definition of a contact form, stored as JSON in ccenter_form.defs:
 *
 *   {"version": 1, "items": [
 *     {"key": "Your name", "type": "text", "label": "Your name", "required": true, "attrs": {"size": 40}},
 *     {"key": "About", "type": "radio", "label": "About", "required": true,
 *      "options": [{"value": "a", "label": "Site contents", "default": true}, {"value": "b", "label": "Others"}]},
 *     {"type": "comment", "text": "Fields marked * are required"}
 *   ]}
 *
 * Text in the earlier line based format is still accepted (fromStored) and can be exported (toLegacy).
 */
class mod_ccenter_FormSchema
{
    const VERSION = 1;
    const TYPES = ['text', 'checkbox', 'radio', 'textarea', 'select', 'hidden', 'const', 'mail', 'file', 'date'];
    /** Types whose value is picked from a list of options. */
    const OPTION_TYPES = ['radio', 'select', 'checkbox'];
    const NUMERIC_ATTRS = ['size', 'rows', 'maxlength', 'cols', 'prop'];

    /** @var array */
    private $data;
    /** @var bool whether the data came from the previous text format */
    private $legacy = false;

    private function __construct(array $data, $legacy = false)
    {
        $this->data = $data;
        $this->legacy = $legacy;
    }

    /**
     * Read what is stored in the database: JSON, or the previous line based text.
     *
     * @param string|null $stored
     * @return self
     * @throws InvalidArgumentException when JSON is present but not a valid schema
     */
    public static function fromStored($stored)
    {
        if (mod_ccenter_Json::looksLikeJson($stored)) {
            try {
                $data = mod_ccenter_Json::decode($stored);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Form definition is not valid JSON: ' . $e->getMessage());
            }
            return self::fromArray(is_array($data) ? $data : []);
        }
        return self::fromLegacy((string) $stored);
    }

    public static function fromLegacy($text)
    {
        return new self(mod_ccenter_FormDefinitionImporter::import($text), true);
    }

    /**
     * @throws InvalidArgumentException when the data is not a valid schema
     */
    public static function fromArray(array $data)
    {
        $data += ['version' => self::VERSION, 'items' => []];
        $errors = self::validate($data);
        if ($errors) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }
        return new self($data);
    }

    /**
     * @return string[] problems found, empty when the schema is valid
     */
    public static function validate(array $data)
    {
        $errors = [];
        if (!isset($data['version']) || (int) $data['version'] !== self::VERSION) {
            $errors[] = 'Unsupported schema version';
        }
        if (!isset($data['items']) || !is_array($data['items']) || (array_keys($data['items']) !== range(0, count($data['items']) - 1) && $data['items'])) {
            return array_merge($errors, ['"items" must be a list']);
        }
        $seen = [];
        foreach ($data['items'] as $index => $item) {
            $where = 'Item ' . ($index + 1);
            if (!is_array($item)) {
                $errors[] = "$where is not an object";
                continue;
            }
            $type = $item['type'] ?? 'text';
            if ($type === 'comment') {
                continue;
            }
            if (!in_array($type, self::TYPES, true)) {
                $errors[] = "$where: unknown type " . (is_scalar($type) ? '"' . $type . '"' : '');
                continue;
            }
            $key = $item['key'] ?? '';
            if (!is_string($key) || trim($key) === '') {
                $errors[] = "$where: key is missing";
                continue;
            }
            $where = 'Item "' . $key . '"';
            if ($key[0] === '#') {
                $errors[] = "$where: a key cannot start with #";
            }
            if (isset($seen[$key])) {
                $errors[] = "$where: key is used twice";
            }
            $seen[$key] = true;
            foreach ($item['attrs'] ?? [] as $name => $value) {
                if (in_array($name, self::NUMERIC_ATTRS, true)) {
                    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                        $errors[] = "$where: attribute $name must be a number";
                    }
                } elseif ($name !== 'check') {
                    $errors[] = "$where: unknown attribute $name";
                }
            }
            if (isset($item['options'])) {
                if (!in_array($type, self::OPTION_TYPES, true)) {
                    $errors[] = "$where: options are only for radio, select and checkbox";
                } else {
                    foreach ($item['options'] as $option) {
                        if (!is_array($option) || !isset($option['value']) || !is_scalar($option['value'])) {
                            $errors[] = "$where: every option needs a value";
                            break;
                        }
                    }
                }
            }
        }
        return $errors;
    }

    public function toArray()
    {
        return $this->data;
    }

    public function toJson($pretty = false)
    {
        return mod_ccenter_Json::encode($this->data, $pretty);
    }

    /** The definition in the previous line based text format. */
    public function toLegacy()
    {
        return mod_ccenter_FormDefinitionExporter::export($this->data);
    }

    /** True when this object was read from the previous text format (i.e. not converted yet). */
    public function wasLegacy()
    {
        return $this->legacy;
    }

    /**
     * Convert whatever is stored (JSON or legacy text) to JSON text for storage.
     *
     * @param string|null $stored
     * @return string
     */
    public static function normalize($stored)
    {
        return self::fromStored($stored)->toJson();
    }

    /**
     * The items in the form the templates and the posting code work with: an array keyed by item key
     * (comments get numeric keys), every field with name, label, field (cc1, cc2...), options (value => label),
     * type, comment, attr and default.
     *
     * @param array  $labels labels overriding the item labels (key => label)
     * @param string $prefix prefix of the generated input names
     * @param callable|null $attribute resolver for default attributes: function (array $attr, string $name) => mixed
     * @return array
     */
    public function items(array $labels = [], $prefix = 'cc', $attribute = null)
    {
        $attribute = $attribute ?: 'get_attr_value';
        $mark = defined('_MD_REQUIRE_MARK') ? _MD_REQUIRE_MARK : '*';
        $result = [];
        $number = 0;
        foreach ($this->data['items'] as $item) {
            $type = $item['type'] ?? 'text';
            if ($type === 'comment') {
                $result[] = ['comment' => (string) ($item['text'] ?? '')];
                continue;
            }
            $key = (string) $item['key'];
            $required = !empty($item['required']);
            $label = isset($labels[$key]) ? $labels[$key] : (string) ($item['label'] ?? $key);
            if ($required) {
                $label .= $mark;
            }

            $attr = [];
            if ($required) {
                $attr['check'] = 'require';
            }
            foreach ($item['attrs'] ?? [] as $name => $value) {
                $attr[$name] = (string) $value;
            }

            $options = [];
            $defaults = [];
            if (in_array($type, self::OPTION_TYPES, true)) {
                foreach ($item['options'] ?? [] as $option) {
                    $value = (string) $option['value'];
                    $options[$value] = (string) ($option['label'] ?? $value);
                    if (!empty($option['default'])) {
                        $defaults[] = $value;
                    }
                }
                if ($type === 'radio') {
                    $default = $defaults ? $defaults[0] : '';
                } elseif ($type === 'checkbox') {
                    $default = $defaults;
                } else {
                    // select: the previous implementation used the joined labels as default
                    $default = mod_ccenter_PlaceholderResolver::resolveForCurrentUser(implode(',', $options));
                }
            } else {
                $text = isset($item['default']) ? (string) $item['default'] : '';
                if ($text !== '') {
                    $options[$text] = $text;
                }
                $default = mod_ccenter_PlaceholderResolver::resolveForCurrentUser($text);
            }

            if ($type === 'date') {
                if ($default === '' || $default === null) {
                    $default = formatTimestamp(time(), 'Y-m-d');
                }
            } elseif ($type === 'textarea') {
                $attr['rows'] = call_user_func($attribute, $attr, 'rows');
                $attr['cols'] = call_user_func($attribute, $attr, 'cols');
            } else {
                $attr['size'] = call_user_func($attribute, $attr, 'size');
                $attr['maxlength'] = call_user_func($attribute, $attr, 'maxlength');
            }
            $attr += ['check' => ''];

            $number++;
            $result[$key] = [
                'name' => $key,
                'label' => $label,
                'field' => $prefix . $number,
                'options' => $options,
                'type' => $type,
                'comment' => (string) ($item['comment'] ?? ''),
                'attr' => $attr,
                'default' => $default,
            ];
        }
        return $result;
    }
}
