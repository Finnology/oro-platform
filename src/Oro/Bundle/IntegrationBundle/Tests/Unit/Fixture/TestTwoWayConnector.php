<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture;

use Oro\Bundle\IntegrationBundle\Provider\TwoWaySyncConnectorInterface;

class TestTwoWayConnector extends TestConnector implements TwoWaySyncConnectorInterface
{
    #[\Override]
    public function getExportJobName()
    {
        return 'tstJobName';
    }
}
