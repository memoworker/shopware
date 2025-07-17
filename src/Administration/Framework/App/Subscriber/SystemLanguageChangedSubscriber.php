<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\App\Subscriber;

use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class SystemLanguageChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<AppAdministrationSnippetCollection> $snippetRepository
     */
    public function __construct(
        private EntityRepository $localeRepository,
        private EntityRepository $snippetRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemLanguageChangeEvent::class => 'onSystemLanguageChanged',
        ];
    }

    public function onSystemLanguageChanged(SystemLanguageChangeEvent $event): void
    {
        $context = Context::createDefaultContext();

        $snippets = $this->getSnippets($context);
        if ($snippets->count() === 0) {
            return;
        }

        $appsWithSnippets = array_values(array_unique($snippets->map(fn (AppAdministrationSnippetEntity $snippet) => $snippet->getAppId())));

        $previousLocale = $this->getLocale($event->previousLocaleCode, $context);
        $newLocale = $this->getLocale($event->newLocaleCode, $context);

        foreach ($appsWithSnippets as $appId) {
            $snippetToClone = $this->snippetToClone($snippets, $appId, $previousLocale, $newLocale);
            if (!$snippetToClone) {
                continue;
            }

            if (!$this->shouldCloneSnippet($snippets, $appId, $previousLocale, $newLocale)) {
                continue;
            }

            $this->snippetRepository->create([[
                'appId' => $snippetToClone->getAppId(),
                'localeId' => $this->localeToCloneTo($previousLocale, $newLocale)->getId(),
                'value' => $snippetToClone->getValue(),
            ]], $context);
        }
    }

    private function getLocale(string $code, Context $context): LocaleEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $code));

        $locale = $this->localeRepository->search($criteria, $context)->first();
        if (!$locale instanceof LocaleEntity) {
            throw LocaleException::localeDoesNotExists($code);
        }

        return $locale;
    }

    private function getSnippets(Context $context): AppAdministrationSnippetCollection
    {
        return $this->snippetRepository->search(new Criteria(), $context)->getEntities();
    }

    private function snippetToClone(
        AppAdministrationSnippetCollection $snippets,
        string $appId,
        LocaleEntity $previousLocale,
        LocaleEntity $newLocale
    ): ?AppAdministrationSnippetEntity {
        $localeIdToMatch = $newLocale->getId();

        if ($previousLocale->getCode() === 'en-GB' && $newLocale->getCode() === 'de-DE') {
            $localeIdToMatch = $previousLocale->getId();
        }

        return $snippets->filter(function (AppAdministrationSnippetEntity $snippet) use ($appId, $localeIdToMatch) {
            return $appId === $snippet->getAppId() && $snippet->getLocaleId() === $localeIdToMatch;
        })->first();
    }

    private function shouldCloneSnippet(
        AppAdministrationSnippetCollection $snippets,
        string $appId,
        LocaleEntity $previousLocale,
        LocaleEntity $newLocale
    ): bool {
        if ($previousLocale->getCode() === 'en-GB' && $newLocale->getCode() === 'de-DE') {
            return true;
        }

        return $snippets->filter(function (AppAdministrationSnippetEntity $snippet) use ($appId, $previousLocale) {
            return $appId === $snippet->getAppId() && $snippet->getLocaleId() === $previousLocale->getId();
        })->first() === null;
    }

    private function localeToCloneTo(LocaleEntity $previousLocale, LocaleEntity $newLocale): LocaleEntity
    {
        if ($previousLocale->getCode() === 'en-GB' && $newLocale->getCode() === 'de-DE') {
            return $newLocale;
        }

        return $previousLocale;
    }
}
