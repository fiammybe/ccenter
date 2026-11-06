<?php

use PHPUnit\Framework\TestCase;

final class FormSchemaTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    public function testImportCreatesTheDocumentedStructure(): void
    {
        $schema = mod_ccenter_FormDefinitionImporter::import("# Hello\nYour name*,size=40\nAbout*,radio,Site+,Others=Other things\nNote,textarea,rows=3,#Be brief");
        $this->assertSame(1, $schema['version']);
        $this->assertSame(['type' => 'comment', 'text' => ' Hello'], $schema['items'][0]);
        $this->assertSame(
            ['key' => 'Your name', 'type' => 'text', 'label' => 'Your name', 'required' => true, 'attrs' => ['size' => 40]],
            $schema['items'][1]
        );
        $this->assertSame(
            [
                'key' => 'About', 'type' => 'radio', 'label' => 'About', 'required' => true,
                'options' => [['value' => 'Site', 'label' => 'Site', 'default' => true], ['value' => 'Others', 'label' => 'Other things']],
            ],
            $schema['items'][2]
        );
        $this->assertSame('Be brief', $schema['items'][3]['comment']);
        $this->assertSame(['rows' => 3], $schema['items'][3]['attrs']);
    }

    public function testQuotedCellsKeepDelimitersAndEmbeddedQuotes(): void
    {
        $schema = mod_ccenter_FormDefinitionImporter::import('"Say ""hi"", please",text');
        $this->assertSame('Say "hi", please', $schema['items'][0]['key']);
    }

    public function testRowsAcceptTabsWindowsLineEndingsAndBlankLines(): void
    {
        $rows = mod_ccenter_FormDefinitionImporter::rows("a\tb\r\n\r\nc,d,\n");
        $this->assertSame([['a', 'b'], ['c', 'd']], array_column($rows, 'cells'));
    }

    public function testExportThenImportGivesTheSameSchema(): void
    {
        $schema = mod_ccenter_FormDefinitionImporter::import($this->fixture('defs_all_types.txt'));
        $text = mod_ccenter_FormDefinitionExporter::export($schema);
        $this->assertSame($schema, mod_ccenter_FormDefinitionImporter::import($text));
    }

    public function testExportQuotesCellsThatNeedIt(): void
    {
        $schema = ['version' => 1, 'items' => [['key' => 'A, B', 'type' => 'text', 'label' => 'A, B', 'default' => 'say "x"']]];
        $this->assertSame('"A, B","say ""x"""', mod_ccenter_FormDefinitionExporter::export($schema));
        $this->assertSame($schema['items'], mod_ccenter_FormDefinitionImporter::import(mod_ccenter_FormDefinitionExporter::export($schema))['items']);
    }

    public function testTextFieldNamedLikeATypeKeepsItsTypeCell(): void
    {
        $schema = ['version' => 1, 'items' => [['key' => 'X', 'type' => 'text', 'label' => 'X', 'default' => 'radio']]];
        $this->assertSame($schema['items'], mod_ccenter_FormDefinitionImporter::import(mod_ccenter_FormDefinitionExporter::export($schema))['items']);
    }

    public function testItemsOfLegacyTextEqualTheOldParser(): void
    {
        // the golden file was produced by the previous parser (see FormatsTest)
        $golden = json_decode(file_get_contents(__DIR__ . '/fixtures/defs_all_types.expected.json'), true);
        $golden['Visit date']['default'] = gmdate('Y-m-d');
        $items = mod_ccenter_FormSchema::fromLegacy($this->fixture('defs_all_types.txt'))->items();
        $this->assertSame($golden, json_decode(json_encode($items), true));
    }

    public function testJsonAndLegacyGiveTheSameItems(): void
    {
        $legacy = mod_ccenter_FormSchema::fromLegacy($this->fixture('defs_all_types.txt'));
        $fromJson = mod_ccenter_FormSchema::fromStored($legacy->toJson());
        $this->assertFalse($fromJson->wasLegacy());
        $this->assertSame($legacy->items(), $fromJson->items());
    }

    public function testFromStoredDetectsTheFormat(): void
    {
        $this->assertTrue(mod_ccenter_FormSchema::fromStored("A,text\nB")->wasLegacy());
        $this->assertFalse(mod_ccenter_FormSchema::fromStored('{"version":1,"items":[]}')->wasLegacy());
        $this->assertSame([], mod_ccenter_FormSchema::fromStored('')->items());
        $this->assertSame([], mod_ccenter_FormSchema::fromStored(null)->items());
    }

    public function testNormalizeProducesJsonAndIsIdempotent(): void
    {
        $json = mod_ccenter_FormSchema::normalize($this->fixture('defs_all_types.txt'));
        $this->assertTrue(mod_ccenter_Json::looksLikeJson($json));
        $this->assertSame($json, mod_ccenter_FormSchema::normalize($json));
    }

    public function testRequiredLabelGetsTheMark(): void
    {
        $items = mod_ccenter_FormSchema::fromLegacy('Name*')->items();
        $this->assertSame('Name' . _MD_REQUIRE_MARK, $items['Name']['label']);
        $this->assertSame('require', $items['Name']['attr']['check']);
    }

    public function testLabelsCanBeOverridden(): void
    {
        $items = mod_ccenter_FormSchema::fromLegacy('redirect,text')->items(['redirect' => 'Go to']);
        $this->assertSame('Go to', $items['redirect']['label']);
    }

    public function testCheckboxDefaultsAreAList(): void
    {
        $items = mod_ccenter_FormSchema::fromLegacy('T,checkbox,a+,b,c+')->items();
        $this->assertSame(['a', 'c'], $items['T']['default']);
        $this->assertSame(['a' => 'a', 'b' => 'b', 'c' => 'c'], $items['T']['options']);
    }

    public function testPlaceholdersInDefaultsAreReplacedByUserData(): void
    {
        $items = mod_ccenter_FormSchema::fromLegacy('Greeting,const,Hi {X_UNAME}')->items();
        $this->assertSame('Hi ', $items['Greeting']['default']); // guest: empty
    }

    /** @dataProvider invalidSchemas */
    public function testValidateReportsProblems(array $schema, string $expected): void
    {
        $errors = mod_ccenter_FormSchema::validate($schema);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString($expected, implode("\n", $errors));
    }

    public function invalidSchemas(): array
    {
        return [
            'duplicate key' => [['version' => 1, 'items' => [['key' => 'A'], ['key' => 'A']]], 'used twice'],
            'unknown type' => [['version' => 1, 'items' => [['key' => 'A', 'type' => 'banana']]], 'unknown type'],
            'missing key' => [['version' => 1, 'items' => [['type' => 'text']]], 'key is missing'],
            'hash key' => [['version' => 1, 'items' => [['key' => '#A']]], 'cannot start with #'],
            'bad attribute' => [['version' => 1, 'items' => [['key' => 'A', 'attrs' => ['size' => 'x']]]], 'must be a number'],
            'unknown attribute' => [['version' => 1, 'items' => [['key' => 'A', 'attrs' => ['color' => 'red']]]], 'unknown attribute'],
            'options on text' => [['version' => 1, 'items' => [['key' => 'A', 'type' => 'text', 'options' => [['value' => 'x']]]]], 'only for radio'],
            'wrong version' => [['version' => 2, 'items' => []], 'version'],
        ];
    }

    public function testInvalidJsonIsRefusedWithAMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mod_ccenter_FormSchema::fromStored('{"version":1,"items":[');
    }

    public function testInvalidSchemaIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('used twice');
        mod_ccenter_FormSchema::fromStored('{"version":1,"items":[{"key":"A"},{"key":"A"}]}');
    }

    public function testToLegacyGivesBackAnEquivalentText(): void
    {
        $original = mod_ccenter_FormSchema::fromLegacy($this->fixture('defs_all_types.txt'));
        $again = mod_ccenter_FormSchema::fromLegacy($original->toLegacy());
        $this->assertSame($original->toArray(), $again->toArray());
    }
}
