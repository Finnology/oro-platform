<?php

namespace Oro\Bundle\AttachmentBundle\Checker\Voter;

use Oro\Bundle\AttachmentBundle\ProcessorHelper;
use Oro\Bundle\FeatureToggleBundle\Checker\Voter\VoterInterface;

/**
 * Checks whether libraries are present in the system.
 */
class PostProcessorsVoter implements VoterInterface
{
    public const ATTACHMENT_POST_PROCESSORS = 'attachment_post_processors';

    private ProcessorHelper $processorHelper;

    private bool $isEnabled = false;

    public function __construct(ProcessorHelper $processorHelper)
    {
        $this->processorHelper = $processorHelper;
    }

    public function setEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @inhericDoc
     */
    public function vote($feature, $scopeIdentifier = null): int
    {
        if ($feature === self::ATTACHMENT_POST_PROCESSORS) {
            return $this->isEnabled ? self::FEATURE_ENABLED : self::FEATURE_DISABLED;
        }

        return self::FEATURE_ABSTAIN;
    }
}
