<?php

use PHPUnit\Framework\TestCase;

final class SmallClassesTest extends TestCase
{
    public function testJsonEncodingIsReadableAndStrict(): void
    {
        $this->assertSame('{"a":"é/ü"}', mod_ccenter_Json::encode(['a' => 'é/ü']));
        $this->expectException(JsonException::class);
        mod_ccenter_Json::encode("\xB1\x31");
    }

    public function testJsonDetection(): void
    {
        $this->assertTrue(mod_ccenter_Json::looksLikeJson('  {"a":1}'));
        $this->assertTrue(mod_ccenter_Json::looksLikeJson('[1]'));
        $this->assertFalse(mod_ccenter_Json::looksLikeJson('Your name: Jan'));
        $this->assertFalse(mod_ccenter_Json::looksLikeJson(null));
        $this->assertNull(mod_ccenter_Json::tryDecodeArray('{broken'));
        $this->assertSame([], mod_ccenter_Json::tryDecodeArray('{}'));
    }

    public function testStatusValues(): void
    {
        $this->assertSame(['-', 'a', 'b', 'c', 'x'], mod_ccenter_Status::all());
        $this->assertTrue(mod_ccenter_Status::isValid('b'));
        $this->assertFalse(mod_ccenter_Status::isValid('z'));
        $this->assertFalse(mod_ccenter_Status::isValid(''));
        $this->assertFalse(mod_ccenter_Status::isValid(null));
        $this->assertSame(['-', 'a', 'b', 'c', 'x'], array_keys(mod_ccenter_Status::labels()));
    }

    public function testPlaceholdersAreReplacedWithoutCascading(): void
    {
        $out = mod_ccenter_PlaceholderResolver::resolve('{X_UNAME}/{X_NAME}/{X_NOPE}', ['uname' => '{X_NAME}', 'name' => 'Jan']);
        $this->assertSame('{X_NAME}/Jan/{X_NOPE}', $out);
    }

    public function testPlaceholdersNeverRunCode(): void
    {
        $this->assertSame('<?php echo 1; ?> ${x} `ls`', mod_ccenter_PlaceholderResolver::resolve('<?php echo 1; ?> ${x} `ls`', ['uname' => 'a']));
    }

    public function testOptionVarsReadBothFormats(): void
    {
        $this->assertSame(['a' => '1', 'b' => 'two'], mod_ccenter_OptionVars::parse("a=1\nb=two"));
        $this->assertSame(['a' => '1', 'b' => 'two'], mod_ccenter_OptionVars::parse('{"a":"1","b":"two"}'));
        $this->assertSame([], mod_ccenter_OptionVars::parse(null));
        $this->assertSame([], mod_ccenter_OptionVars::parse('{not json'));
    }

    public function testOptionVarsAreWrittenAsAnObject(): void
    {
        $this->assertSame('{}', mod_ccenter_OptionVars::encode([]));
        $this->assertSame('{"redirect":"/thanks.php"}', mod_ccenter_OptionVars::encode(['redirect' => '/thanks.php']));
        $this->assertSame('{"a":"1"}', mod_ccenter_OptionVars::normalize('a=1'));
        $this->assertTrue(mod_ccenter_OptionVars::isCurrent('{}'));
        $this->assertFalse(mod_ccenter_OptionVars::isCurrent('a=1'));
        $this->assertFalse(mod_ccenter_OptionVars::isCurrent(''));
    }

    /**
     * get_attr_value() keeps its defaults in a static variable.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFormOptionsOverrideTheModuleDefaults(): void
    {
        // module default (def_attrs) size=60, per form override 40 (see get_attr_value in functions.php)
        get_attr_value('{"size": "40"}');
        $this->assertSame('40', get_attr_value(null, 'size'));
    }
}
