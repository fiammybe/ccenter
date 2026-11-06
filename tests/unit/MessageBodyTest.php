<?php

use PHPUnit\Framework\TestCase;

final class MessageBodyTest extends TestCase
{
    private function legacy(): string
    {
        return file_get_contents(__DIR__ . '/fixtures/body_legacy.txt');
    }

    public function testLegacyBodyKeepsEveryPairAndTheLegacyArray(): void
    {
        $body = mod_ccenter_MessageBody::fromStored($this->legacy());
        $this->assertTrue($body->isLegacy());
        $this->assertSame(mod_ccenter_LegacyText::unserializeText($this->legacy()), $body->toLegacyArray());
        $this->assertSame("first line\nsecond line\nthird line", $body->toLegacyArray()['Message']);
    }

    public function testLegacyFilesAreRecognised(): void
    {
        $field = array_values(array_filter(mod_ccenter_MessageBody::fromStored($this->legacy())->fields(), fn ($f) => $f['key'] === 'Upload'))[0];
        $this->assertSame('file', $field['type']);
        $this->assertSame(['file' => 'cv.pdf'], $field['value']);
    }

    public function testConversionToJsonAndBackKeepsTheDisplayValues(): void
    {
        $legacy = mod_ccenter_MessageBody::fromStored($this->legacy());
        $json = $legacy->toJson();
        $this->assertTrue(mod_ccenter_MessageBody::isCurrent($json));
        $this->assertSame($legacy->toLegacyArray(), mod_ccenter_MessageBody::fromStored($json)->toLegacyArray());
        $this->assertSame($json, mod_ccenter_MessageBody::normalize($json));
    }

    public function testMultiSelectAndMultilineValuesAreKeptExactly(): void
    {
        $body = mod_ccenter_MessageBody::fromFields([
            ['key' => 'Topics', 'label' => 'Topics', 'type' => 'checkbox', 'value' => ['n', 'f'], 'display' => 'News, Forum'],
            ['key' => 'Message', 'label' => 'Your message', 'type' => 'textarea', 'value' => "a\n\tb: c\n", 'display' => "a\n\tb: c\n"],
        ]);
        $read = mod_ccenter_MessageBody::fromStored($body->toJson());
        $this->assertFalse($read->isLegacy());
        $this->assertSame(['n', 'f'], $read->fields()[0]['value']);
        $this->assertSame("a\n\tb: c\n", $read->fields()[1]['value']);
        $this->assertSame(['Topics' => 'Topics', 'Message' => 'Your message'], $read->labels());
    }

    public function testFilesAppearWithTheLegacyPrefix(): void
    {
        $body = mod_ccenter_MessageBody::fromFields([['key' => 'CV', 'label' => 'CV', 'type' => 'file', 'value' => ['file' => 'cv.pdf'], 'display' => 'cv.pdf']]);
        $this->assertSame(['CV' => 'file=cv.pdf'], $body->toLegacyArray());
    }

    public function testJsonIsStoredAsReadableText(): void
    {
        $json = mod_ccenter_MessageBody::fromFields([['key' => 'Naam', 'label' => 'Naam', 'type' => 'text', 'value' => 'Zoë / café', 'display' => 'Zoë / café']])->toJson();
        $this->assertStringContainsString('Zoë / café', $json);
        $this->assertStringNotContainsString('\\u', $json);
        $this->assertStringNotContainsString('\\/', $json);
    }

    public function testEmptyBodyHasNoFields(): void
    {
        $this->assertSame([], mod_ccenter_MessageBody::fromStored(null)->fields());
        $this->assertSame([], mod_ccenter_MessageBody::fromStored('')->toLegacyArray());
        $this->assertFalse(mod_ccenter_MessageBody::isCurrent(''));
    }
}
