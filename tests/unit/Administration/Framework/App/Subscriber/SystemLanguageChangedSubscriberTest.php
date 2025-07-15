<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(SystemLanguageChangedSubscriber::class)]
class SystemLanguageChangedSubscriberTest extends TestCase
{
    private LanguageEntity $previousLanguage;

    private LanguageEntity $newLanguage;

    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->previousLanguage = (new LanguageEntity())->assign([
            'id' => 'previous-language-id',
            'name' => 'English (United Kingdom)',
            'localeId' => 'previous-locale-id',
            'locale' => (new LocaleEntity())->assign([
                'id' => 'previous-locale-id',
                'code' => 'en-GB',
            ]),
        ]);

        $this->newLanguage = (new LanguageEntity())->assign([
            'id' => 'new-language-id',
            'name' => 'German (Germany)',
            'localeId' => 'new-locale-id',
            'locale' => (new LocaleEntity())->assign([
                'id' => 'new-locale-id',
                'code' => 'de-DE',
            ]),
        ]);

        $this->languageRepository = new StaticEntityRepository([
            new LanguageCollection([$this->previousLanguage]),
            new LanguageCollection([$this->newLanguage]),
        ]);
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [SystemLanguageChangeEvent::class => 'onSystemLanguageChanged'],
            SystemLanguageChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testDoesNotRunIfNoSnippetsExist(): void
    {
        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection()]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $languageRepository = $this->createMock(EntityRepository::class),
            $snippetRepository
        );

        $languageRepository->expects($this->never())
            ->method('search');

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'previous-locale-code',
            'new-locale-code'
        ));
    }

    public function testDoesNotCreateSnippetsIfSnippetsForPreviousLocaleAlreadyExist(): void
    {
        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            (new AppAdministrationSnippetEntity())->assign(['id' => 'id', 'appId' => 'app-id', 'localeId' => $this->previousLanguage->getLocaleId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $this->languageRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'previous-locale-code',
            'new-locale-code'
        ));

        static::assertCount(0, $snippetRepository->creates);
    }

    public function testCreatesSnippetsIfSnippetsForPreviousLocaleDoNotAlreadyExist(): void
    {
        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToClone = (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-one-id', 'appId' => 'app-one-id', 'localeId' => $this->newLanguage->getLocaleId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
            (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-two-id', 'appId' => 'app-one-id', 'localeId' => 'other-locale-id', 'name' => 'snippet-name', 'value' => 'snippet-value']),
            $snippetTwoToClone = (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-three-id', 'appId' => 'app-two-id', 'localeId' => $this->newLanguage->getLocaleId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
            (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-four-id', 'appId' => 'app-two-id', 'localeId' => 'other-locale-id', 'name' => 'snippet-name', 'value' => 'snippet-value']),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $this->languageRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'previous-locale-code',
            'new-locale-code'
        ));

        static::assertSame([
            'appId' => $snippetOneToClone->getAppId(),
            'localeId' => $this->previousLanguage->getLocaleId(),
            'value' => $snippetOneToClone->getValue(),
        ], $snippetRepository->creates[0][0]);

        static::assertSame([
            'appId' => $snippetTwoToClone->getAppId(),
            'localeId' => $this->previousLanguage->getLocaleId(),
            'value' => $snippetTwoToClone->getValue(),
        ], $snippetRepository->creates[1][0]);
    }
}
