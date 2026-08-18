<?php

/**
 * FormDefinition class for the ccenter module.
 *
 * Handles form definition parsing, serialisation and deserialisation.
 * Supports both the legacy CSV format and the JSON ccenter.form.v1 format.
 *
 * Using icms_core_Object as the base class is the right choice here because a
 * FormDefinition is a value/data object that is stored as a single column
 * (defs) in the ccenter_form table rather than as a row in its own table.
 * icms_ipf_Object / icms_ipf_Handler would be the better fit for entities
 * that have a dedicated database table and need the full IPF persistence
 * layer (auto-CRUD, built-in form generation, permissions, etc.).
 */
class mod_ccenter_FormDefinition extends icms_core_Object
{
	/** @var string  Raw definition string (JSON or legacy CSV). */
	private $rawDefs;

	/** @var string  Serialised label map (optional). */
	private $labels;

	/** @var string  Field-name prefix used when building field IDs. */
	private $prefix;

	/**
	 * @param string $rawDefs Raw definition string stored in the database.
	 * @param string $labels  Optional serialised label overrides.
	 * @param string $prefix  Prefix for generated field names (default "cc").
	 */
	public function __construct($rawDefs = "", $labels = "", $prefix = "cc")
	{
		parent::__construct();
		$this->rawDefs = (string) $rawDefs;
		$this->labels  = (string) $labels;
		$this->prefix  = (string) $prefix;
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the empty JSON schema skeleton for a ccenter form definition.
	 *
	 * @return array
	 */
	public static function schema()
	{
		return [
			"format"  => "ccenter.form.v1",
			"version" => 1,
			"fields"  => [],
		];
	}

	/**
	 * Tests whether a raw definition string is in JSON format.
	 *
	 * @param  mixed $defs Value to test.
	 * @return bool
	 */
	public static function isJsonDefinition($defs)
	{
		if (!is_string($defs)) {
			return false;
		}
		$defs = trim($defs);
		if ($defs === "" || !preg_match('/^[\[\{]/', $defs)) {
			return false;
		}
		$data = json_decode($defs, true);
		if (!is_array($data)) {
			return false;
		}
		if (isset($data["fields"]) && is_array($data["fields"])) {
			return true;
		}
		$i = 0;
		foreach (array_keys($data) as $k) {
			if ($k !== $i++) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Parses a legacy CSV definition string into a normalised fields array.
	 *
	 * @param  string $defs   Raw legacy definition.
	 * @param  string $labels Serialised label overrides.
	 * @param  string $prefix Field-name prefix.
	 * @return array
	 */
	public static function parseLegacy($defs, $labels = "", $prefix = "cc")
	{
		$labs = unserialize_vars($labels);
		$num  = 0;
		$result = [];
		$types = [
			"text", "checkbox", "radio", "textarea", "select",
			"hidden", "const", "mail", "file", "date",
		];

		foreach (cc_csv_parse($defs) as $opts) {
			if (empty($opts)) {
				continue;
			}
			if (preg_match("/^#/", $opts[0])) {
				$result[] = ["comment" => substr(join(",", $opts), 1)];
				continue;
			}
			$name = array_shift($opts);
			if (preg_match('/=(.*)$/', $name, $d)) {
				$label = $d[1];
				$name  = preg_replace('/=(.*)$/', "", $name);
			} else {
				$label = isset($labs[$name]) ? $labs[$name] : $name;
			}
			$type    = "text";
			$comment = "";
			$attr    = [];
			if (count($opts) && in_array($opts[0], $types)) {
				$type = array_shift($opts);
			}
			if (preg_match('/\*$/', $name)) {
				$attr["check"] = "require";
				$name = preg_replace('/\s*\*$/', "", $name);
				if (defined("_MD_REQUIRE_MARK")) {
					$label = preg_replace('/\s*\*$/', _MD_REQUIRE_MARK, $label);
				}
			}
			while (
				isset($opts[0]) &&
				(preg_match('/^(size|rows|maxlength|cols|prop)=(\d+)$/', $opts[0], $d) ||
					preg_match('/^(check)=(.+)$/', $opts[0], $d))
			) {
				array_shift($opts);
				$attr[$d[1]] = $d[2];
			}
			$options  = [];
			$defaults = [];
			if (count($opts)) {
				while (count($opts) && !preg_match("/^\s*#/", $opts[0])) {
					$v  = array_shift($opts);
					$sv = preg_split("/=/", $v, 2);
					if (count($sv) > 1) {
						$k  = strip_tags($sv[0]);
						$sk = preg_replace('/\+$/', "", $k);
						if ($k != $sk) {
							$defaults[] = $sk;
						}
						$options[$sk] = $sv[1];
					} else {
						$k  = strip_tags($v);
						$sk = preg_replace('/\+$/', "", $k);
						if ($k != $sk) {
							$defaults[] = $sk;
						}
						$options[$sk] = preg_replace('/\+$/', "", $v);
					}
				}
				if (count($opts)) {
					$opts[0] = preg_replace("/^\s*#/", "", $opts[0]);
					$comment = join(",", $opts);
				}
			}
			if ($type == "radio") {
				$defaults = $defaults ? $defaults[0] : "";
			} elseif ($type != "checkbox") {
				$defaults = eval_user_value(join(",", $options));
			}
			if ($type == "date") {
				if (empty($defaults)) {
					$defaults = formatTimestamp(time(), "Y-m-d");
				}
			} elseif ($type == "textarea") {
				$attr["rows"] = get_attr_value($attr, "rows");
				$attr["cols"] = get_attr_value($attr, "cols");
			} else {
				$attr["size"] = get_attr_value($attr, "size");
			}

			$fname          = $prefix . ++$num;
			$result[$name]  = [
				"name"    => $name,
				"label"   => $label,
				"field"   => $fname,
				"options" => $options,
				"type"    => $type,
				"comment" => $comment,
				"attr"    => $attr,
				"default" => $defaults,
			];
		}
		return $result;
	}

	/**
	 * Parses a JSON definition string into a normalised fields array.
	 * Falls back to parseLegacy() when the input is not valid JSON.
	 *
	 * @param  string $defs   Raw JSON definition.
	 * @param  string $labels Serialised label overrides.
	 * @param  string $prefix Field-name prefix.
	 * @return array
	 */
	public static function parseJson($defs, $labels = "", $prefix = "cc")
	{
		$labs = unserialize_vars($labels);
		$data = json_decode(trim($defs), true);
		if (!is_array($data)) {
			return self::parseLegacy($defs, $labels, $prefix);
		}
		$fields = isset($data["fields"]) && is_array($data["fields"]) ? $data["fields"] : $data;
		$types  = [
			"text", "checkbox", "radio", "textarea", "select",
			"hidden", "const", "mail", "file", "date",
		];
		$num    = 0;
		$result = [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}
			if (isset($field["comment_only"]) && $field["comment_only"]) {
				$result[] = ["comment" => (string) $field["comment"]];
				continue;
			}
			$name = isset($field["name"]) ? trim($field["name"]) : "";
			if ($name === "") {
				continue;
			}
			$type = isset($field["type"]) ? strtolower(trim($field["type"])) : "text";
			if (!in_array($type, $types)) {
				$type = "text";
			}
			$label = isset($field["label"]) && $field["label"] !== ""
				? $field["label"]
				: (isset($labs[$name]) ? $labs[$name] : $name);

			$attr = [];
			if (isset($field["attributes"]) && is_array($field["attributes"])) {
				foreach ($field["attributes"] as $k => $v) {
					if (in_array($k, ["size", "rows", "maxlength", "cols", "prop"])) {
						$attr[$k] = $v;
					}
				}
			}
			if (!empty($field["required"])) {
				$attr["check"] = "require";
			}
			if (!empty($field["validation"])) {
				$attr["check"] = $field["validation"];
			}
			if ($type == "textarea") {
				$attr["rows"] = get_attr_value($attr, "rows");
				$attr["cols"] = get_attr_value($attr, "cols");
			} else {
				$attr["size"] = get_attr_value($attr, "size");
			}

			$options  = [];
			$defaults = [];
			if (isset($field["options"]) && is_array($field["options"])) {
				$i = 0;
				foreach ($field["options"] as $ok => $ov) {
					if (is_array($ov)) {
						$oval = isset($ov["value"]) ? (string) $ov["value"] : (string) $ok;
						$olab = isset($ov["label"]) ? $ov["label"] : $oval;
						if (!empty($ov["default"])) {
							$defaults[] = $oval;
						}
					} else {
						$oval = ((string) $ok === (string) $i++) ? (string) $ov : (string) $ok;
						$olab = (string) $ov;
					}
					$options[$oval] = $olab;
				}
			}

			$default = isset($field["default"]) ? $field["default"] : "";
			if ($type == "checkbox") {
				if (is_array($default)) {
					$default = array_map("strval", $default);
				} elseif ($default === "" && !empty($defaults)) {
					$default = $defaults;
				} elseif ($default === "") {
					$default = [];
				} else {
					$default = [(string) $default];
				}
			} elseif ($type == "radio") {
				if ($default === "" && !empty($defaults)) {
					$default = $defaults[0];
				}
			} elseif ($default === "" && ($type == "hidden" || $type == "const")) {
				$default = eval_user_value(join(",", $options));
			}
			if ($type == "date" && empty($default)) {
				$default = formatTimestamp(time(), "Y-m-d");
			}

			$fname         = $prefix . ++$num;
			$result[$name] = [
				"name"    => $name,
				"label"   => $label,
				"field"   => $fname,
				"options" => $options,
				"type"    => $type,
				"comment" => isset($field["comment"]) ? $field["comment"] : "",
				"attr"    => $attr,
				"default" => $default,
			];
		}
		return $result;
	}

	/**
	 * Converts a normalised fields array to a JSON definition string.
	 *
	 * @param  array $items Normalised fields array (as returned by parse*()).
	 * @return string JSON-encoded form definition.
	 */
	public static function itemsToJson(array $items)
	{
		$schema           = self::schema();
		$schema["fields"] = [];

		foreach ($items as $item) {
			if (empty($item["name"])) {
				if (!empty($item["comment"])) {
					$schema["fields"][] = [
						"comment_only" => true,
						"comment"      => $item["comment"],
					];
				}
				continue;
			}
			$field = [
				"name"  => $item["name"],
				"label" => $item["label"],
				"type"  => $item["type"],
			];
			$attr = isset($item["attr"]) && is_array($item["attr"]) ? $item["attr"] : [];
			if (!empty($attr["check"])) {
				if ($attr["check"] == "require") {
					$field["required"] = true;
				} else {
					$field["validation"] = $attr["check"];
				}
			}
			$attrs = [];
			foreach (["size", "rows", "maxlength", "cols", "prop"] as $ak) {
				if (isset($attr[$ak]) && $attr[$ak] !== "") {
					$attrs[$ak] = $attr[$ak];
				}
			}
			if (!empty($attrs)) {
				$field["attributes"] = $attrs;
			}
			if (!empty($item["options"])) {
				$field["options"] = [];
				$default          = isset($item["default"]) ? $item["default"] : "";
				foreach ($item["options"] as $ok => $ov) {
					$opt = ["value" => (string) $ok, "label" => $ov];
					if (is_array($default) && in_array($ok, $default)) {
						$opt["default"] = true;
					} elseif (!is_array($default) && (string) $default === (string) $ok) {
						$opt["default"] = true;
					}
					$field["options"][] = $opt;
				}
			}
			if (array_key_exists("default", $item)) {
				$field["default"] = $item["default"];
			}
			if (!empty($item["comment"])) {
				$field["comment"] = $item["comment"];
			}
			$schema["fields"][] = $field;
		}

		return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	// -------------------------------------------------------------------------
	// Instance methods
	// -------------------------------------------------------------------------

	/**
	 * Returns true when the raw definition string is in JSON format.
	 *
	 * @return bool
	 */
	public function isJson()
	{
		return self::isJsonDefinition($this->rawDefs);
	}

	/**
	 * Returns the normalised fields array for this definition.
	 *
	 * @param  string|null $labels Optional label overrides (overrides constructor value).
	 * @param  string|null $prefix Optional field-name prefix (overrides constructor value).
	 * @return array
	 */
	public function getFields($labels = null, $prefix = null)
	{
		$labels = $labels !== null ? $labels : $this->labels;
		$prefix = $prefix !== null ? $prefix : $this->prefix;

		if ($this->isJson()) {
			return self::parseJson($this->rawDefs, $labels, $prefix);
		}
		return self::parseLegacy($this->rawDefs, $labels, $prefix);
	}

	/**
	 * Serialises this definition to the canonical JSON format (ccenter.form.v1).
	 * If the definition is already JSON it is normalised; legacy definitions are
	 * converted.
	 *
	 * @param  string|null $labels Optional label overrides.
	 * @param  string|null $prefix Optional field-name prefix.
	 * @return string JSON string.
	 */
	public function toJson($labels = null, $prefix = null)
	{
		$labels = $labels !== null ? $labels : $this->labels;
		$prefix = $prefix !== null ? $prefix : $this->prefix;
		$items  = $this->getFields($labels, $prefix);
		return self::itemsToJson($items);
	}

	/**
	 * Converts this definition to the legacy CSV format.
	 * Only meaningful when the definition is currently in JSON format.
	 *
	 * @return string Legacy CSV definition.
	 */
	public function toLegacy()
	{
		$items = self::parseJson($this->rawDefs);
		$lines = [];

		foreach ($items as $item) {
			if (empty($item["name"])) {
				$lines[] = "#" . (isset($item["comment"]) ? $item["comment"] : "");
				continue;
			}
			$name = $item["name"];
			$attr = isset($item["attr"]) ? $item["attr"] : [];
			if (!empty($attr["check"]) && $attr["check"] == "require") {
				$name .= "*";
			}
			$line = [$name];
			if (!empty($item["type"]) && $item["type"] != "text") {
				$line[] = $item["type"];
			}
			foreach (["size", "rows", "maxlength", "cols", "prop"] as $ak) {
				if (isset($attr[$ak]) && $attr[$ak] !== "") {
					$line[] = $ak . "=" . $attr[$ak];
				}
			}
			if (!empty($attr["check"]) && $attr["check"] != "require") {
				$line[] = "check=" . $attr["check"];
			}
			if (!empty($item["options"])) {
				$default = isset($item["default"]) ? $item["default"] : "";
				foreach ($item["options"] as $ok => $ov) {
					$key = $ok;
					if (is_array($default) && in_array($ok, $default)) {
						$key .= "+";
					} elseif (!is_array($default) && (string) $default === (string) $ok) {
						$key .= "+";
					}
					$line[] = $key . "=" . $ov;
				}
			}
			if (!empty($item["comment"])) {
				$line[] = "#" . $item["comment"];
			}
			$lines[] = join(",", $line);
		}
		return join("\n", $lines);
	}

	/**
	 * Returns the raw definition string.
	 *
	 * @return string
	 */
	public function getRawDefs()
	{
		return $this->rawDefs;
	}
}
