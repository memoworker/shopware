<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\App\Subscriber;

use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class SystemLanguageChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<AppAdministrationSnippetCollection> $snippetRepository
     */
    public function __construct(
        private EntityRepository $languageRepository,
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

        $previousLocale = $this->getLocale($event->previousLanguageId, $context);
        $newLocale = $this->getLocale(Defaults::LANGUAGE_SYSTEM, $context);

        foreach ($appsWithSnippets as $appId) {
            $snippetToClone = $snippets->filter(fn (AppAdministrationSnippetEntity $snippet) => $appId === $snippet->getAppId() && $snippet->getLocaleId() === $newLocale->getId())->first();
            \assert($snippetToClone instanceof AppAdministrationSnippetEntity);

            $snippetWithPreviousLocaleExists = $snippets->filter(fn (AppAdministrationSnippetEntity $snippet) => $appId === $snippet->getAppId() && $snippet->getLocaleId() === $previousLocale->getId())->first();
            if ($snippetWithPreviousLocaleExists) {
                continue;
            }

            $this->snippetRepository->create([[
                'appId' => $snippetToClone->getAppId(),
                'localeId' => $previousLocale->getId(),
                'value' => $snippetToClone->getValue(),
            ]], $context);
        }
    }

    private function getLocale(string $languageId, Context $context): LocaleEntity
    {
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        $language = $this->languageRepository->search($criteria, $context)->first();
        \assert($language instanceof LanguageEntity);

        $locale = $language->getLocale();
        \assert($locale instanceof LocaleEntity);

        return $locale;
    }

    private function getSnippets(Context $context): AppAdministrationSnippetCollection
    {
        return $this->snippetRepository->search(new Criteria(), $context)->getEntities();
    }
}
