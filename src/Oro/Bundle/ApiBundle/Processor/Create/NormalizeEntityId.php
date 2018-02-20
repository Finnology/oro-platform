<?php

namespace Oro\Bundle\ApiBundle\Processor\Create;

use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Request\Constraint;
use Oro\Bundle\ApiBundle\Request\EntityIdTransformerInterface;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Checks whether a string representation of entity identifier exists in the Context,
 * and if so, converts it to its original type.
 */
class NormalizeEntityId implements ProcessorInterface
{
    /** @var EntityIdTransformerInterface */
    protected $entityIdTransformer;

    /**
     * @param EntityIdTransformerInterface $entityIdTransformer
     */
    public function __construct(EntityIdTransformerInterface $entityIdTransformer)
    {
        $this->entityIdTransformer = $entityIdTransformer;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var CreateContext $context */

        $entityId = $context->getId();
        if (!is_string($entityId)) {
            // an entity identifier does not exist or it is already normalized
            return;
        }

        $metadata = $context->getMetadata();
        if ($metadata->hasIdentifierGenerator()) {
            // keep an entity identifier as is if the entity has a generator for identifier value
            return;
        }

        try {
            $context->setId(
                $this->entityIdTransformer->reverseTransform($entityId, $metadata)
            );
        } catch (\Exception $e) {
            $context->addError(
                Error::createValidationError(Constraint::ENTITY_ID)->setInnerException($e)
            );
        }
    }
}
