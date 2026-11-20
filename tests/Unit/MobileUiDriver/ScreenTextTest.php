<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUiDriver;

use PHPUnit\Framework\TestCase;
use Tools\MobileUiDriver\ScreenText;

final class ScreenTextTest extends TestCase
{
    public function testParsesVisibleTextAndContentDescriptionsInOrder(): void
    {
        $xml = <<<'XML'
            <hierarchy>
              <node text="Today" content-desc="" bounds="[0,0][100,30]">
                <node text="" content-desc="Open tasks" bounds="[0,30][100,60]"/>
                <node text="Today" content-desc="Today" bounds="[0,60][100,90]"/>
                <node text="   " content-desc="" bounds="[0,90][100,120]"/>
                <node text="Completed" content-desc="" bounds="[0,120][100,150]"/>
              </node>
            </hierarchy>
            XML;

        self::assertSame([
            'Today [0,0][100,30]',
            'Open tasks [0,30][100,60]',
            'Completed [0,120][100,150]',
        ], ScreenText::parse($xml));
    }

    public function testInvalidXmlFailsClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        ScreenText::parse('<hierarchy><node>');
    }
}
