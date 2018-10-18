<?php

namespace Oro\Bundle\EmailBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Gedmo\Translatable\TranslatableListener;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\EmailBundle\Model\EmailTemplateCriteria;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

/**
 * Provides methods for querying email templates related information such as getting localized email template or
 * getting system only email templates.
 */
class EmailTemplateRepository extends EntityRepository
{
    /**
     * Gets a template by its name
     * This method can return null if the requested template does not exist
     *
     * @param string $templateName
     * @return EmailTemplate|null
     */
    public function findByName($templateName)
    {
        return $this->findOneBy(array('name' => $templateName));
    }

    /**
     * Load templates by entity name
     *
     * @param string       $entityName
     * @param Organization $organization
     * @param bool         $includeNonEntity
     * @param bool         $includeSystemTemplates
     *
     * @return EmailTemplate[]
     */
    public function getTemplateByEntityName(
        $entityName,
        Organization $organization,
        $includeNonEntity = false,
        $includeSystemTemplates = true
    ) {
        return $this->getEntityTemplatesQueryBuilder(
            $entityName,
            $organization,
            $includeNonEntity,
            $includeSystemTemplates
        )
            ->getQuery()->getResult();
    }

    /**
     * Return templates query builder filtered by entity name
     *
     * @param string       $entityName    entity class
     * @param Organization $organization
     * @param bool         $includeNonEntity if true - system templates will be included in result set
     * @param bool         $includeSystemTemplates
     * @param bool         $visibleOnly
     *
     * @return QueryBuilder
     */
    public function getEntityTemplatesQueryBuilder(
        $entityName,
        Organization $organization,
        $includeNonEntity = false,
        $includeSystemTemplates = true,
        $visibleOnly = true
    ) {
        $qb = $this->createQueryBuilder('e')
            ->where('e.entityName = :entityName')
            ->orderBy('e.name', 'ASC')
            ->setParameter('entityName', $entityName);

        if ($includeNonEntity) {
            $qb->orWhere('e.entityName IS NULL');
        }

        if (!$includeSystemTemplates) {
            $qb->andWhere('e.isSystem = :isSystem')
                ->setParameter('isSystem', false);
        }

        if ($visibleOnly) {
            $qb->andWhere('e.visible = :visible')
                ->setParameter('visible', true);
        }

        $qb->andWhere("e.organization = :organization")
            ->setParameter('organization', $organization);

        return $qb;
    }

    /**
     * Return a query builder which can be used to get names of entities
     * which have at least one email template
     *
     * @return QueryBuilder
     */
    public function getDistinctByEntityNameQueryBuilder()
    {
        return $this->createQueryBuilder('e')
            ->select('e.entityName')
            ->distinct();
    }

    /**
     * Get templates without any related entity
     *
     * @return QueryBuilder
     */
    public function getSystemTemplatesQueryBuilder()
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.entityName IS NULL');

        return $qb;
    }

    /**
     * @param EmailTemplateCriteria $criteria
     * @param string $language
     * @return EmailTemplate|null
     * @throws NonUniqueResultException
     */
    public function findOneLocalized(EmailTemplateCriteria $criteria, string $language): ?EmailTemplate
    {
        $queryBuilder = $this->createQueryBuilder('t')->select('t');
        $this->resolveEmailTemplateCriteria($queryBuilder, $criteria);

        $query = $queryBuilder->getQuery();
        $query
            ->setHint(
                \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
                TranslationWalker::class
            )
            ->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $language)
            ->useQueryCache(false); // due to bug https://github.com/Atlantic18/DoctrineExtensions/issues/1021

        return $query->getOneOrNullResult();
    }

    /**
     * @param EmailTemplateCriteria $criteria
     * @return bool
     */
    public function isExist(EmailTemplateCriteria $criteria): bool
    {
        $queryBuilder = $this->createQueryBuilder('t')->select('1');
        $this->resolveEmailTemplateCriteria($queryBuilder, $criteria);

        return (bool) $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param EmailTemplateCriteria $criteria
     */
    private function resolveEmailTemplateCriteria(QueryBuilder $queryBuilder, EmailTemplateCriteria $criteria): void
    {
        $queryBuilder->andWhere($queryBuilder->expr()->eq('t.name', ':name'));
        $queryBuilder->setParameter('name', $criteria->getName());
        if ($criteria->getEntityName()) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('t.entityName', ':entityName'));
            $queryBuilder->setParameter('entityName', $criteria->getEntityName());
        }
    }
}
