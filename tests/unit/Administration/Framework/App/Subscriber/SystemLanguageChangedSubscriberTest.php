<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
    private LocaleEntity $previousLocale;

    private LocaleEntity $newLocale;

    /**
     * @var StaticEntityRepository<LocaleCollection>
     */
    private StaticEntityRepository $localeRepository;

    protected function setUp(): void
    {
        $this->previousLocale = (new LocaleEntity())->assign([
            'id' => 'previous-locale-id',
            'code' => 'en-GB',
        ]);

        $this->newLocale = (new LocaleEntity())->assign([
            'id' => 'new-locale-id',
            'code' => 'de-DE',
        ]);

        $this->localeRepository = new StaticEntityRepository([
            new LocaleCollection([$this->previousLocale]),
            new LocaleCollection([$this->newLocale]),
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
            $localeRepository = $this->createMock(EntityRepository::class),
            $snippetRepository
        );

        $localeRepository->expects($this->never())
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
            (new AppAdministrationSnippetEntity())->assign(['id' => 'id', 'appId' => 'app-id', 'localeId' => $this->previousLocale->getId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $this->localeRepository,
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
            $snippetOneToClone = (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-one-id', 'appId' => 'app-one-id', 'localeId' => $this->newLocale->getId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
            (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-two-id', 'appId' => 'app-one-id', 'localeId' => 'other-locale-id', 'name' => 'snippet-name', 'value' => 'snippet-value']),
            $snippetTwoToClone = (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-three-id', 'appId' => 'app-two-id', 'localeId' => $this->newLocale->getId(), 'name' => 'snippet-name', 'value' => 'snippet-value']),
            (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-four-id', 'appId' => 'app-two-id', 'localeId' => 'other-locale-id', 'name' => 'snippet-name', 'value' => 'snippet-value']),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $this->localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'previous-locale-code',
            'new-locale-code'
        ));

        static::assertSame([
            'appId' => $snippetOneToClone->getAppId(),
            'localeId' => $this->previousLocale->getId(),
            'value' => $snippetOneToClone->getValue(),
        ], $snippetRepository->creates[0][0]);

        static::assertSame([
            'appId' => $snippetTwoToClone->getAppId(),
            'localeId' => $this->previousLocale->getId(),
            'value' => $snippetTwoToClone->getValue(),
        ], $snippetRepository->creates[1][0]);
    }
}
