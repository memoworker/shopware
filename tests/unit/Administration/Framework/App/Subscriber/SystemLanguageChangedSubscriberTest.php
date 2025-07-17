<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(SystemLanguageChangedSubscriber::class)]
class SystemLanguageChangedSubscriberTest extends TestCase
{
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
            $localeRepository = $this->createMock(EntityRepository::class),
            $snippetRepository
        );

        $localeRepository->expects($this->never())
            ->method('search');

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'en-GB',
            'de-DE',
        ));
    }

    public function testDoesNotCreateSnippetsIfSnippetsForPreviousLocaleAlreadyExist(): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale('en-US')]),
        ]);

        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-id', $previousLocale->getId()),
            $this->createSnippet('app-id', $newLocale->getId()),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertCount(0, $snippetRepository->creates);
    }

    #[DataProvider('localeCodes')]
    public function testCreatesSnippetsIfSnippetsForPreviousLocaleDoNotAlreadyExist(string $locale): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale($locale)]),
        ]);

        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToClone = $this->createSnippet('app-one-id', $newLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $snippetTwoToClone = $this->createSnippet('app-two-id', $newLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertSame([
            'appId' => $snippetOneToClone->getAppId(),
            'localeId' => $previousLocale->getId(),
            'value' => $snippetOneToClone->getValue(),
        ], $snippetRepository->creates[0][0]);

        static::assertSame([
            'appId' => $snippetTwoToClone->getAppId(),
            'localeId' => $previousLocale->getId(),
            'value' => $snippetTwoToClone->getValue(),
        ], $snippetRepository->creates[1][0]);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    private function createLocale(string $code): LocaleEntity
    {
        return (new LocaleEntity())->assign([
            'id' => Uuid::randomHex(),
            'code' => $code,
        ]);
    }

    private function createSnippet(string $appId, string $localeId): AppAdministrationSnippetEntity
    {
        return (new AppAdministrationSnippetEntity())->assign([
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'localeId' => $localeId,
            'name' => 'snippet-name',
            'value' => 'snippet-value',
        ]);
    }
}
