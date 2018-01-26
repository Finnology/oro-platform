<?php

namespace Oro\Bundle\ImapBundle\Command\Cron;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;

/**
 * Cron command that runs processing the invalid email origins that was failed during sync.
 */
class SendCredentialNotificationsCommand extends ContainerAwareCommand implements CronCommandInterface
{
    /**
     * Command name
     */
    const COMMAND_NAME = 'oro:cron:imap-credential-notifications';

    /**
     * {@inheritdoc}
     */
    public function getDefaultDefinition()
    {
        return '0 * * * *';
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        $featureChecker = $this->getContainer()->get('oro_featuretoggle.checker.feature_checker');

        return $featureChecker->isResourceEnabled(self::COMMAND_NAME, 'cron_jobs');
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Send wrong email credentials notifications');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get('oro_imap.origin_credentials.issue_manager')->processInvalidOrigins();

        return 0;
    }
}
