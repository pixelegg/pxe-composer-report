<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Pixelegg\PxeComposerReport\Service\ReportMailer;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the real email templates through the null transport and inspects
 * the sent message (subject, HTML and plain text, language).
 */
final class ReportMailerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];
    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'null',
            'defaultMailFromAddress' => 'noreply@example.com',
            'defaultMailFromName' => 'Example',
        ],
    ];

    /** @return array<string,mixed> */
    private function report(): array
    {
        return require __DIR__ . '/../Fixtures/report.php';
    }

    private function sentEmail(): Email
    {
        $sent = $this->get(MailerInterface::class)->getSentMessage();
        self::assertNotNull($sent);
        $message = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        return $message;
    }

    #[Test]
    public function sendsEnglishHtmlAndTextMailToConfiguredRecipients(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = [
            'emailRecipients' => 'a@example.com, not-an-address, b@example.com',
            'emailLanguage' => 'en',
        ];

        self::assertTrue($this->get(ReportMailer::class)->send($this->report(), 'Everything is fine.', 'Example Project'));

        $email = $this->sentEmail();
        self::assertSame(['a@example.com', 'b@example.com'], array_map(static fn($a) => $a->getAddress(), $email->getTo()));
        self::assertSame('Composer report: Example Project (3 outdated, 1 security)', $email->getSubject());

        $html = (string)$email->getHtmlBody();
        self::assertStringContainsString('Security advisories', $html);
        self::assertStringContainsString('CVE-2026-0001', $html);
        self::assertStringContainsString('typo3/cms-core', $html);
        self::assertStringContainsString('georgringer/news', $html);
        self::assertStringContainsString('mycorp/intranet', $html);
        self::assertStringContainsString('Everything is fine.', $html);
        self::assertStringContainsString('pixelegg.de', $html);
        self::assertStringNotContainsString('FREUDWEISE', $html);

        $text = (string)$email->getTextBody();
        self::assertStringContainsString('typo3/cms-core: 13.4.1 -> 13.4.37 (patch), Major: 14.3.10', $text);
        self::assertStringContainsString('- guzzlehttp/guzzle [high] CVE-2026-0001: Example advisory https://example.org/advisory', $text);
        self::assertStringContainsString('georgringer/news: 11.4.0, Major: 12.3.0', $text);
    }

    #[Test]
    public function sendsGermanMailWhenConfigured(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = [
            'emailRecipients' => 'a@example.com',
            'emailLanguage' => 'de',
        ];

        self::assertTrue($this->get(ReportMailer::class)->send($this->report(), '', 'Beispiel'));

        $email = $this->sentEmail();
        self::assertSame('Composer-Report: Beispiel (3 veraltet, 1 Sicherheit)', $email->getSubject());
        $html = (string)$email->getHtmlBody();
        self::assertStringContainsString('Sicherheitshinweise', $html);
        self::assertStringContainsString('Neuer Major verfügbar', $html);
        self::assertStringNotContainsString('Security advisories', $html);
        self::assertStringNotContainsString('Zusammenfassung', $html, 'no summary block without a summary');
    }

    #[Test]
    public function overrideRecipientWinsAndMissingRecipientsReturnFalse(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['pxe_composer_report'] = ['emailRecipients' => ''];
        $mailer = $this->get(ReportMailer::class);

        self::assertFalse($mailer->send($this->report(), '', 'X'));
        self::assertTrue($mailer->send($this->report(), '', 'X', ['test@example.com']));
        self::assertSame('test@example.com', $this->sentEmail()->getTo()[0]->getAddress());
    }
}
