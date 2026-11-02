<?php

use PHPUnit\Framework\TestCase;

/**
 * Characterisation tests for the legacy text formats (CSV-like form definitions, key=value option
 * variables and "name: value" message bodies). They pin down today's behaviour so the JSON migration
 * can prove it reads the old data identically.
 */
final class FormatsTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    public function testCsvParseSplitsCommasQuotesAndLines(): void
    {
        $rows = cc_csv_parse("a,b\n\"x, y\",plain\nlast");
        $this->assertSame([['a', 'b'], ['x, y', 'plain'], ['last']], $rows);
    }

    /**
     * KNOWN LEGACY BUG, pinned on purpose: doubled quotes inside a quoted cell are not unescaped
     * correctly ("he said ""hi""" should give: he said "hi"). The JSON importer must NOT copy this;
     * when it is written its test will expect the correct value.
     */
    public function testCsvParseMangledEmbeddedQuotesIsAKnownLegacyBug(): void
    {
        $rows = cc_csv_parse('"he said ""hi"""');
        $this->assertSame([['"he said "hi"']], $rows);
    }

    public function testCsvParseAcceptsTabsAndWindowsLineEndings(): void
    {
        $this->assertSame([['a', 'b'], ['c', 'd']], cc_csv_parse("a\tb\r\nc,d"));
    }

    public function testBodyRoundTripKeepsMultilineValues(): void
    {
        $values = unserialize_text($this->fixture('body_legacy.txt'));
        $this->assertSame('Jan Peeters', $values['Your name']);
        $this->assertSame("first line\nsecond line\nthird line", $values['Message']);
        $this->assertSame('file=cv.pdf', $values['Upload']);
        $this->assertSame(trim($this->fixture('body_legacy.txt')), trim(serialize_text($values)));
    }

    public function testBodyFlattensArraysToCommaSeparatedText(): void
    {
        $this->assertSame("Topics: News, Forum\n", serialize_text(['Topics' => ['News', 'Forum']]));
    }

    public function testBodyContinuationBeforeAnyKeyIsIgnored(): void
    {
        $this->assertSame(['a' => 'b'], unserialize_text("\torphan\na: b"));
    }

    public function testBodyAcceptsNull(): void
    {
        $this->assertSame([], unserialize_text(null));
    }

    public function testOptionVariablesAreParsedFromLines(): void
    {
        $vars = unserialize_vars($this->fixture('optvars_legacy.txt'));
        $this->assertSame('1', $vars['notify_with_email']);
        $this->assertSame('/thanks.php', $vars['redirect']);
        $this->assertSame('Thank you, we will answer soon', $vars['reply_comment']);
        $this->assertSame('a=b', $vars['others']);
    }

    public function testOptionVariablesAcceptCommaSeparatedForm(): void
    {
        $this->assertSame(['size' => '10', 'rows' => '3'], unserialize_vars('size=10,rows=3'));
    }

    public function testOptionVariablesAcceptNullAndComments(): void
    {
        $this->assertSame([], unserialize_vars(null));
        $this->assertSame(['a' => '1'], unserialize_vars("#comment=x\na=1"));
    }

    public function testFormDefinitionMatchesGoldenFile(): void
    {
        $items = get_form_attribute($this->fixture('defs_all_types.txt'));
        $actual = json_decode(json_encode($items), true);
        $goldenPath = __DIR__ . '/fixtures/defs_all_types.expected.json';
        if (!file_exists($goldenPath) && getenv('UPDATE_GOLDEN')) {
            file_put_contents($goldenPath, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $this->assertFileExists($goldenPath, 'run with UPDATE_GOLDEN=1 once to create it');
        $this->assertSame(json_decode(file_get_contents($goldenPath), true), $actual);
    }

    public function testFormDefinitionSemantics(): void
    {
        $items = get_form_attribute($this->fixture('defs_all_types.txt'));
        $this->assertSame('require', $items['Your name']['attr']['check']);
        $this->assertSame('mail', $items['Email']['type']);
        $this->assertSame('Site contents', $items['About']['default']);          // "+" marks the default radio
        $this->assertSame('Shown label', $items['Label']['label']);               // name=alternative label
        $this->assertSame('Please be precise', $items['Message']['comment']);
        $this->assertSame(['Quoted, name'], array_values(array_filter(array_keys($items), fn ($k) => str_starts_with($k, 'Quoted'))));
        $this->assertSame('textarea', $items['Message']['type']);
        $this->assertSame('50', $items['Message']['attr']['cols']);
    }

    public function testUserPlaceholdersAreReplacedWithoutEvaluatingCode(): void
    {
        $this->assertSame('Hi , {NOT_A_PLACEHOLDER} <?php echo 1; ?>', eval_user_value('Hi {X_UNAME}, {NOT_A_PLACEHOLDER} <?php echo 1; ?>'));
    }
}
