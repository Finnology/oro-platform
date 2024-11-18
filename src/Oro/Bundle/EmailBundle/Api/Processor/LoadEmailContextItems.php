<?php

namespace Oro\Bundle\EmailBundle\Api\Processor;

use Oro\Bundle\ActivityBundle\Manager\ActivityManager;
use Oro\Bundle\ApiBundle\Processor\ListContext;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\EmailBundle\Api\Model\EmailContextItem;
use Oro\Bundle\EmailBundle\Api\SearchEntityListFilterHelper;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailRecipient;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\SearchBundle\Engine\Indexer as SearchIndexer;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Component\ChainProcessor\ContextInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Loads data for the email context API resource.
 */
class LoadEmailContextItems extends AbstractLoadEmailContextItems
{
    public function __construct(
        DoctrineHelper $doctrineHelper,
        SearchIndexer $searchIndexer,
        SearchEntityListFilterHelper $searchEntityListFilterHelper,
        ActivityManager $activityManager,
        ValueNormalizer $valueNormalizer,
        EventDispatcherInterface $eventDispatcher,
        EmailAddressHelper $emailAddressHelper,
        TokenAccessorInterface $tokenAccessor
    ) {
        parent::__construct(
            $doctrineHelper,
            $searchIndexer,
            $searchEntityListFilterHelper,
            $activityManager,
            $valueNormalizer,
            $eventDispatcher,
            $emailAddressHelper,
            $tokenAccessor
        );
    }

    /**
     * {@inheritDoc}
     */
    public function process(ContextInterface $context): void
    {
        /** @var ListContext $context */

        if ($context->hasResult()) {
            // result data are already retrieved
            return;
        }

        $criteria = $context->getCriteria();
        if (null === $criteria) {
            // something going wrong, it is expected that the criteria exists
            return;
        }

        $entities = $this->getRequestedEntities($context);
        $messageIds = $this->getRequestedMessageIds($context);
        $emailAddresses = $this->getRequestedEmailAddresses($context->getFilterValues());
        $excludeCurrentUser = $this->getRequestedExcludeCurrentUser($context);
        $isContext = $this->getRequestedIsContext($context);
        $searchText = $this->getRequestedSearchText($context, $emailAddresses, $excludeCurrentUser, $isContext);
        if ($context->hasErrors()) {
            return;
        }

        if ($entities) {
            if ($searchText) {
                $this->loadAndSetResultBySearchText(
                    $context,
                    $criteria,
                    $entities,
                    $this->findEmailIdsByMessageIds($messageIds),
                    $searchText
                );
            } else {
                [$emailIds, $existingEmailAddresses] = $this->findEmailIdsAndItsAddressesByMessageIds($messageIds);
                $this->loadAndSetResult(
                    $context,
                    $criteria,
                    $entities,
                    $emailIds,
                    $existingEmailAddresses,
                    $emailAddresses,
                    $excludeCurrentUser ?? false,
                    $isContext
                );
            }
        } else {
            $this->setEmptyResult($context);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function createResultItem(
        string $id,
        string $entityClass,
        mixed $entityId,
        ?string $entityName,
        ?string $entityUrl,
        bool $isContext
    ): EmailContextItem {
        return new EmailContextItem($id, $entityClass, $entityId, $entityName, $entityUrl, $isContext);
    }

    /**
     * @param string[] $messageIds
     *
     * @return int[]
     */
    private function findEmailIdsByMessageIds(array $messageIds): array
    {
        $rows = $this->doctrineHelper->createQueryBuilder(Email::class, 'e')
            ->select('e.id')
            ->where('e.messageId IN(:messageIds)')
            ->setParameter('messageIds', $messageIds)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'id');
    }

    /**
     * @param string[] $messageIds
     *
     * @return array [[email id, ...], [email address, ...]]
     */
    private function findEmailIdsAndItsAddressesByMessageIds(array $messageIds): array
    {
        $rows = $this->doctrineHelper->createQueryBuilder(Email::class, 'e')
            ->select('e.id, from_addr.email AS f, to_addr.email AS t')
            ->leftJoin('e.fromEmailAddress', 'from_addr')
            ->leftJoin('e.recipients', 'recipients')
            ->leftJoin('recipients.emailAddress', 'to_addr')
            ->where('e.messageId IN(:messageIds) AND recipients.type <> :bcc')
            ->setParameter('messageIds', $messageIds)
            ->setParameter('bcc', EmailRecipient::BCC)
            ->getQuery()
            ->getArrayResult();

        return $this->buildEmailIdsAndItsAddresses($rows);
    }
}
