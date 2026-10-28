<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\PackagePrivacy;
use Pixelegg\PxeComposerReport\Tests\Unit\ConfigTrait;

final class PackagePrivacyTest extends TestCase
{
    use ConfigTrait;

    protected function tearDown(): void
    {
        $this->resetConfig();
        parent::tearDown();
    }

    #[Test]
    public function nothingIsPrivateByDefault(): void
    {
        $p = new PackagePrivacy($this->config([]));
        self::assertFalse($p->isPrivate('acme/foo'));
        self::assertTrue($p->packagistAllowed());
        self::assertSame([], $p->privateVendors());
    }

    #[Test]
    public function vendorPrefixesMatchCaseInsensitively(): void
    {
        $p = new PackagePrivacy($this->config(['privateVendors' => ' Acme , pixelegg/ ']));
        self::assertSame(['acme', 'pixelegg'], $p->privateVendors());
        self::assertTrue($p->isPrivate('acme/intranet'));
        self::assertTrue($p->isPrivate('ACME/Other'));
        self::assertTrue($p->isPrivate('pixelegg/pxe-hub'));
        self::assertFalse($p->isPrivate('acme-public/foo'));
        self::assertFalse($p->isPrivate('typo3/cms-core'));
    }

    #[Test]
    public function fullPackageNamesMatchOnlyThatPackage(): void
    {
        $p = new PackagePrivacy($this->config(['privateVendors' => 'mycorp/intranet']));
        self::assertTrue($p->isPrivate('mycorp/intranet'));
        self::assertFalse($p->isPrivate('mycorp/public'));
    }

    #[Test]
    public function filterPublicDropsPrivatePackages(): void
    {
        $p = new PackagePrivacy($this->config(['privateVendors' => 'acme']));
        self::assertSame(['typo3/cms-core'], $p->filterPublic(['acme/a', 'typo3/cms-core', 'acme/b']));
    }

    #[Test]
    public function packagistCanBeDisabled(): void
    {
        self::assertFalse((new PackagePrivacy($this->config(['resolveLatestViaPackagist' => '0'])))->packagistAllowed());
        self::assertTrue((new PackagePrivacy($this->config(['resolveLatestViaPackagist' => '1'])))->packagistAllowed());
    }
}
