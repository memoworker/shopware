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
            $snippetToClone = $snippets->filter(fn (AppAdministrationSnippetEntity $snippet) => $appId === $snippet->getAppId() && $snippet->getLocaleId() === $newLocale->getId())->first();
            if (!$snippetToClone) {
                continue;
            }

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
}
