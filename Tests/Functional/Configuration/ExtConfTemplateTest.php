<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\TypoScript\AST\CommentAwareAstBuilder;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\AST\Traverser\AstTraverser;
use TYPO3\CMS\Core\TypoScript\AST\Visitor\AstConstantCommentVisitor;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LosslessTokenizer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Parses ext_conf_template.txt the way the "Extension Configuration" settings
 * form does (install tool / admin tools) and checks that every tab and every
 * group heading has a translated label instead of a raw key.
 */
final class ExtConfTemplateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['pixelegg/pxe-composer-report'];

    /**
     * @return array{categories: array<string, array{label: string, usageCount: int}>, constants: array<string, array<string, mixed>>}
     */
    private function parse(string $language): array
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($language);
        $ast = $this->get(CommentAwareAstBuilder::class)->build(
            (new LosslessTokenizer())->tokenize((string)file_get_contents(__DIR__ . '/../../../ext_conf_template.txt')),
            new RootNode(),
        );
        $visitor = new AstConstantCommentVisitor();
        (new AstTraverser())->traverse($ast, [$visitor]);
        $categories = array_filter($visitor->getCategories(), static fn(array $c): bool => $c['usageCount'] > 0);
        return ['categories' => $categories, 'constants' => $visitor->getConstants()];
    }

    /** @return array<string, array{string, array<string, string>, list<string>}> */
    public static function languages(): array
    {
        return [
            'English' => ['default', ['basic' => 'Basic', 'privacy' => 'Privacy', 'email' => 'Email', 'ai' => 'AI summary'], ['Data collection', 'Report and history', 'External lookups', 'Delivery', 'Anthropic API']],
            'German' => ['de', ['basic' => 'Basic', 'privacy' => 'Datenschutz', 'email' => 'E-Mail', 'ai' => 'KI-Zusammenfassung'], ['Datenerfassung', 'Report und Verlauf', 'Externe Abfragen', 'Versand', 'Anthropic-API']],
        ];
    }

    /**
     * @param array<string, string> $expectedTabs
     * @param list<string> $expectedGroups
     */
    #[Test]
    #[DataProvider('languages')]
    public function tabsAndGroupsHaveTranslatedLabels(string $language, array $expectedTabs, array $expectedGroups): void
    {
        $parsed = $this->parse($language);

        self::assertSame($expectedTabs, array_map(static fn(array $c): string => $c['label'], $parsed['categories']));

        $groups = [];
        foreach ($parsed['constants'] as $name => $constant) {
            self::assertNotSame('enable', $constant['subcat_name'], $name . ' is not a feature toggle group');
            self::assertNotSame('other', $constant['subcat_name'], $name . ' has a known group');
            self::assertStringNotContainsString('LLL:', (string)$constant['label'], $name . ' label resolved');
            $groups[] = (string)$constant['subcat_label'];
        }
        self::assertSame($expectedGroups, array_values(array_unique($groups)));
        self::assertCount(13, $parsed['constants']);
    }
}
