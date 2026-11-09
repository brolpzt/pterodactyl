<?php

namespace Pterodactyl\Tests\Unit\Models;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\PanelAlert;
use PHPUnit\Framework\Attributes\DataProvider;

class PanelAlertTest extends TestCase
{
    #[DataProvider('lineBreakProvider')]
    public function testLineBreaksAreKept(string $message, string $expected): void
    {
        $html = (new PanelAlert())->forceFill(['message' => $message])->renderedMessage();

        $this->assertSame($expected, str_replace("\n", '', $html));
    }

    public static function lineBreakProvider(): array
    {
        return [
            'single line' => ['um', 'um'],
            'unix newline' => ["um\ndois", 'um<br>dois'],
            'textarea newline' => ["um\r\ndois", 'um<br>dois'],
            'blank line' => ["um\r\n\r\ndois", 'um<br><br>dois'],
            'several blank lines' => ["um\r\n\r\n\r\n\r\ndois", 'um<br><br><br><br>dois'],
            'whitespace-only line' => ["um\n   \ndois", 'um<br><br>dois'],
            'surrounding blank lines are ignored' => ["\r\n\r\num\r\n\r\n", 'um'],
            'markdown spans single newlines' => ["**um\ndois**\n\n*tres*", '<strong>um<br>dois</strong><br><br><em>tres</em>'],
        ];
    }

    public function testHtmlIsStillEscapedAfterABlankLine(): void
    {
        $html = (new PanelAlert())->forceFill(['message' => "um\n\n<script>alert(1)</script>"])->renderedMessage();

        $this->assertStringNotContainsString('<script', $html);
    }
}
