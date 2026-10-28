<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\LatestVersionResolver;
use Pixelegg\PxeComposerReport\Service\PackagePrivacy;
use Pixelegg\PxeComposerReport\Tests\Unit\ConfigTrait;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;

final class LatestVersionResolverTest extends TestCase
{
    use ConfigTrait;

    protected function tearDown(): void
    {
        $this->resetConfig();
        parent::tearDown();
    }

    #[Test]
    public function picksLatestStableWithinMajorAndOverall(): void
    {
        $versions = ['v13.4.27', 'v13.4.28', '14.0.0', '14.3.4', '14.4.0-rc1', 'dev-main', '13.5.0-beta1'];
        $r = LatestVersionResolver::pickVersions($versions, '13.4.27');
        self::assertSame('13.4.28', $r['latestInMajor']);
        self::assertSame('14.3.4', $r['latestOverall']);
    }

    #[Test]
    public function ignoresPreReleasesAndDevVersions(): void
    {
        $r = LatestVersionResolver::pickVersions(['2.0.0-alpha1', '2.0.0-RC2', 'dev-feature', '1.9.9'], '1.9.0');
        self::assertSame('1.9.9', $r['latestInMajor']);
        self::assertSame('1.9.9', $r['latestOverall']);
    }

    #[Test]
    public function returnsEmptyStringsWhenNoStableVersionExists(): void
    {
        $r = LatestVersionResolver::pickVersions(['dev-main', '3.0.0-beta1'], '2.1.0');
        self::assertSame('', $r['latestInMajor']);
        self::assertSame('', $r['latestOverall']);
    }

    #[Test]
    public function inMajorIsEmptyWhenCurrentMajorHasNoStableReleases(): void
    {
        $r = LatestVersionResolver::pickVersions(['14.0.0', '14.3.4'], '13.4.27');
        self::assertSame('', $r['latestInMajor']);
        self::assertSame('14.3.4', $r['latestOverall']);
    }

    #[Test]
    public function handlesUnparseableCurrentVersion(): void
    {
        $r = LatestVersionResolver::pickVersions(['1.0.0', '2.0.0'], 'dev-main');
        self::assertSame('', $r['latestInMajor']);
        self::assertSame('2.0.0', $r['latestOverall']);
    }

    #[Test]
    public function privatePackagesAreNeverRequested(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects($this->never())->method('request');
        $config = $this->config(['privateVendors' => 'acme']);
        $resolver = new LatestVersionResolver($factory, new PackagePrivacy($config), new NullLogger());
        self::assertSame(['latestInMajor' => '', 'latestOverall' => ''], $resolver->resolve('acme/intranet', '1.0.0'));
    }

    #[Test]
    public function nothingIsRequestedWhenPackagistIsDisabled(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects($this->never())->method('request');
        $config = $this->config(['resolveLatestViaPackagist' => '0']);
        $resolver = new LatestVersionResolver($factory, new PackagePrivacy($config), new NullLogger());
        self::assertSame(['latestInMajor' => '', 'latestOverall' => ''], $resolver->resolve('typo3/cms-core', '13.4.0'));
    }

    #[Test]
    public function publicPackagesAreResolvedFromPackagistMetadata(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects($this->once())->method('request')
            ->with('https://repo.packagist.org/p2/typo3/cms-core.json', 'GET', self::isArray())
            ->willReturnCallback(static function (): Response {
                $response = new Response();
                $response->getBody()->write('{"packages":{"typo3/cms-core":[{"version":"v14.3.4"},{"version":"v13.4.28"},{"version":"v13.4.27"}]}}');
                $response->getBody()->rewind();
                return $response;
            });
        $resolver = new LatestVersionResolver($factory, new PackagePrivacy($this->config([])), new NullLogger());
        self::assertSame(['latestInMajor' => '13.4.28', 'latestOverall' => '14.3.4'], $resolver->resolve('typo3/cms-core', '13.4.27'));
    }

    #[Test]
    public function networkErrorsYieldEmptyStrings(): void
    {
        $factory = self::createStub(RequestFactory::class);
        $factory->method('request')->willThrowException(new \RuntimeException('timeout'));
        $resolver = new LatestVersionResolver($factory, new PackagePrivacy($this->config([])), new NullLogger());
        self::assertSame(['latestInMajor' => '', 'latestOverall' => ''], $resolver->resolve('typo3/cms-core', '13.4.27'));
    }
}
