<?php

namespace Oro\Bundle\WorkflowBundle\Acl\Extension;

use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdentityFactory;
use Oro\Bundle\SecurityBundle\Acl\Extension\AbstractSimpleAccessLevelAclExtension;
use Oro\Bundle\SecurityBundle\Acl\Extension\AccessLevelOwnershipDecisionMakerInterface;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProviderInterface;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Util\ClassUtils;

/**
 * The base class for ACL extensions that works with workflows and workflow transitions.
 */
abstract class AbstractWorkflowAclExtension extends AbstractSimpleAccessLevelAclExtension
{
    /** @var WorkflowManager */
    private $workflowManager;

    public function __construct(
        ObjectIdAccessor $objectIdAccessor,
        OwnershipMetadataProviderInterface $metadataProvider,
        EntityOwnerAccessor $entityOwnerAccessor,
        AccessLevelOwnershipDecisionMakerInterface $decisionMaker,
        WorkflowManager $workflowManager
    ) {
        parent::__construct($objectIdAccessor, $metadataProvider, $entityOwnerAccessor, $decisionMaker);
        $this->workflowManager = $workflowManager;
    }

    #[\Override]
    protected function getObjectClassName($object)
    {
        if ($object instanceof ObjectIdentityInterface) {
            $workflowName = $object->getType();
        } elseif (\is_string($object)) {
            $workflowName = $id = $group = null;
            $this->parseDescriptor($object, $workflowName, $id, $group);
        } else {
            return ClassUtils::getRealClass($object);
        }

        return ObjectIdentityFactory::ROOT_IDENTITY_TYPE !== $workflowName
            ? $this->getWorkflowManager()->getWorkflow($workflowName)->getDefinition()->getRelatedEntity()
            : $workflowName;
    }

    /**
     * @return WorkflowManager
     */
    protected function getWorkflowManager()
    {
        return $this->workflowManager;
    }
}
