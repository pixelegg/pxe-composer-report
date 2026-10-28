<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pixelegg\PxeComposerReport\Service\AiSummaryService;
use Pixelegg\PxeComposerReport\Service\PackagePrivacy;
use Pixelegg\PxeComposerReport\Service\ReportConfig;
use Pixelegg\PxeComposerReport\Tests\Unit\ConfigTrait;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;

/**
 * No network: the RequestFactory is mocked.
 */
final class AiSummaryServiceTest extends TestCase
{
    use ConfigTrait;

    protected function tearDown(): void
    {
        $this->resetConfig();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function report(): array
    {
        return [
            'counts' => ['total' => 2, 'security' => 1, 'major' => 0, 'minor' => 1, 'patch' => 1],
            'security' => [
                ['name' => 'acme/secret', 'severity' => 'high', 'cve' => 'CVE-1', 'title' => 'private thing', 'link' => 'https://x'],
                ['name' => 'guzzlehttp/guzzle', 'severity' => 'medium', 'cve' => 'CVE-2', 'title' => 'public thing', 'link' => 'https://y'],
            ],
            'typo3' => [
                ['name' => 'typo3/cms-core', 'current' => '13.4.1', 'latest' => '13.4.7', 'nextMajor' => '14.3.0', 'impact' => 'patch', 'description' => 'long text that must not be sent'],
            ],
            'other' => [['name' => 'acme/secret', 'current' => '1.0.0', 'latest' => '1.1.0']],
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function requestBodyOf(MockObject&RequestFactory $factory, array $settings, string $responseJson): array
    {
        $captured = [];
        $factory->expects($this->once())->method('request')
            ->willReturnCallback(function (string $uri, string $method, array $options) use (&$captured, $responseJson): Response {
                $captured = ['uri' => $uri, 'method' => $method, 'options' => $options];
                $response = new Response();
                $response->getBody()->write($responseJson);
                $response->getBody()->rewind();
                return $response;
            });
        $config = $this->config($settings);
        $service = new AiSummaryService($config, new PackagePrivacy($config), $factory, new NullLogger());
        $captured['summary'] = $service->summarize($this->report());
        return $captured;
    }

    #[Test]
    public function doesNothingWhenDisabledOrWithoutKey(): void
    {
        $factory = $this->createMock(RequestFactory::class);
        $factory->expects($this->never())->method('request');
        foreach ([[], ['aiEnabled' => '1'], ['aiEnabled' => '0', 'anthropicApiKey' => 'k']] as $settings) {
            $config = $this->config($settings);
            $service = new AiSummaryService($config, new PackagePrivacy($config), $factory, new NullLogger());
            self::assertSame('', $service->summarize($this->report()));
        }
    }

    #[Test]
    public function sendsMessagesApiRequestAndReturnsText(): void
    {
        $captured = $this->requestBodyOf(
            $this->createMock(RequestFactory::class),
            ['aiEnabled' => '1', 'anthropicApiKey' => 'sk-test', 'anthropicModel' => 'claude-haiku-5-5', 'emailLanguage' => 'de'],
            '{"type":"message","stop_reason":"end_turn","content":[{"type":"text","text":" Alles gut. "},{"type":"text","text":"Zweiter Teil."}]}',
        );

        self::assertSame(AiSummaryService::ENDPOINT, $captured['uri']);
        self::assertSame('POST', $captured['method']);
        $options = $captured['options'];
        self::assertSame('sk-test', $options['headers']['x-api-key']);
        self::assertSame(AiSummaryService::API_VERSION, $options['headers']['anthropic-version']);
        self::assertSame('application/json', $options['headers']['content-type']);
        self::assertGreaterThan(0, $options['timeout']);
        self::assertGreaterThan(0, $options['connect_timeout']);

        $body = json_decode((string)$options['body'], true);
        self::assertIsArray($body);
        self::assertSame('claude-haiku-5-5', $body['model']);
        self::assertIsInt($body['max_tokens']);
        self::assertIsString($body['system']);
        self::assertStringContainsString('Deutsch', $body['system']);
        self::assertSame('user', $body['messages'][0]['role']);
        self::assertArrayNotHasKey('thinking', $body);
        self::assertArrayNotHasKey('budget_tokens', $body);

        self::assertSame("Alles gut.\nZweiter Teil.", $captured['summary']);
    }

    #[Test]
    public function payloadContainsOnlyCountsAdvisoriesAndTypo3PackagesWithoutPrivateVendors(): void
    {
        $config = $this->config(['aiEnabled' => '1', 'anthropicApiKey' => 'k', 'privateVendors' => 'acme']);
        $service = new AiSummaryService($config, new PackagePrivacy($config), self::createStub(RequestFactory::class), new NullLogger());
        $payload = $service->buildPayload($this->report());

        $json = (string)$payload['messages'][0]['content'];
        self::assertStringNotContainsString('acme', $json);
        self::assertStringNotContainsString('private thing', $json);
        self::assertStringContainsString('guzzlehttp/guzzle', $json);
        self::assertStringContainsString('typo3/cms-core', $json);
        self::assertStringNotContainsString('long text that must not be sent', $json);
        self::assertStringNotContainsString('https://', $json);
        self::assertSame(ReportConfig::DEFAULT_MODEL, $payload['model']);
    }

    #[Test]
    public function refusalYieldsEmptySummary(): void
    {
        $captured = $this->requestBodyOf(
            $this->createMock(RequestFactory::class),
            ['aiEnabled' => '1', 'anthropicApiKey' => 'k'],
            '{"stop_reason":"refusal","stop_details":{"type":"refusal","category":"cyber"},"content":[]}',
        );
        self::assertSame('', $captured['summary']);
    }

    #[Test]
    public function transportErrorsNeverBubbleUp(): void
    {
        $factory = self::createStub(RequestFactory::class);
        $factory->method('request')->willThrowException(new \RuntimeException('401 Unauthorized'));
        $config = $this->config(['aiEnabled' => '1', 'anthropicApiKey' => 'k']);
        $service = new AiSummaryService($config, new PackagePrivacy($config), $factory, new NullLogger());
        self::assertSame('', $service->summarize($this->report()));
    }

    #[Test]
    public function nonJsonResponseYieldsEmptySummary(): void
    {
        $captured = $this->requestBodyOf($this->createMock(RequestFactory::class), ['aiEnabled' => '1', 'anthropicApiKey' => 'k'], '<html>gateway timeout</html>');
        self::assertSame('', $captured['summary']);
    }
}
