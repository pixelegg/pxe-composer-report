<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\SemverClassifier;

final class SemverClassifierTest extends TestCase
{
    private SemverClassifier $c;
    protected function setUp(): void
    {
        $this->c = new SemverClassifier();
    }

    public function testMajor(): void
    {
        self::assertSame('major', $this->c->classify('1.2.3', '2.0.0'));
    }
    public function testMinor(): void
    {
        self::assertSame('minor', $this->c->classify('1.2.3', '1.3.0'));
    }
    public function testPatch(): void
    {
        self::assertSame('patch', $this->c->classify('1.2.3', '1.2.4'));
    }
    public function testNoneWhenEqual(): void
    {
        self::assertSame('none', $this->c->classify('1.2.3', '1.2.3'));
    }
    public function testNoneWhenLatestOlder(): void
    {
        self::assertSame('none', $this->c->classify('2.0.0', '1.9.9'));
    }

    public function testStripsLeadingVAndPreRelease(): void
    {
        self::assertSame('major', $this->c->classify('v1.2.3', 'v2.0.0-beta1'));
        self::assertSame('patch', $this->c->classify('1.2.3', '1.2.4-RC1'));
    }

    public function testUnknownWhenUnparseable(): void
    {
        self::assertSame('unknown', $this->c->classify('dev-main', '1.0.0'));
        self::assertSame('unknown', $this->c->classify('1.2.3', ''));
    }

    public function testMajorNumber(): void
    {
        self::assertSame(13, $this->c->major('v13.4.27'));
        self::assertSame(14, $this->c->major('14.3.4-RC1'));
        self::assertNull($this->c->major('dev-main'));
    }
}
