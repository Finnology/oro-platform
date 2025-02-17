<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Unit\Fixture\__CG__;

use Doctrine\Persistence\Proxy;
use Oro\Bundle\DataAuditBundle\Tests\Unit\Fixture\LoggableClass as BaseLoggableClas;

class LoggableClass extends BaseLoggableClas implements Proxy
{
    #[\Override]
    public function __load()
    {
    }

    #[\Override]
    public function __isInitialized()
    {
        return false;
    }
}
