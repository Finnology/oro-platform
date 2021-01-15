<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Provider\Filter;

use Oro\Bundle\UserBundle\Provider\Filter\ChoiceTreeUserProvider;

class ChoiceTreeUserProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var ChoiceTreeUserProvider */
    protected $choiceTreeUserProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $registry;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $aclHelper;

    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder('Doctrine\Bundle\DoctrineBundle\Registry')
            ->disableOriginalConstructor()
            ->getMock();
        $this->aclHelper = $this->getMockBuilder('Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $dqlNameFormatter = $this->getMockBuilder('Oro\Bundle\LocaleBundle\DQL\DQLNameFormatter')
            ->disableOriginalConstructor()
            ->getMock();

        $this->choiceTreeUserProvider = new ChoiceTreeUserProvider(
            $this->registry,
            $this->aclHelper,
            $dqlNameFormatter
        );
    }

    public function testGetList()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setMethods(['getArrayResult'])
            ->disableOriginalConstructor()
            ->getMock();

        $repository = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\Repository\UserRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $manager = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $this->registry->expects($this->once())
            ->method('getManager')
            ->willReturn($manager);

        $qb->expects($this->any())
            ->method('getArrayResult')
            ->willReturn($this->getExpectedData());
        $this->aclHelper->expects($this->any())
            ->method('apply')
            ->willReturn($qb);

        $result = $this->choiceTreeUserProvider->getList();
        $this->assertEquals($this->getExpectedData(), $result);
    }

    public function testGetEmptyList()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setMethods(['getArrayResult'])
            ->disableOriginalConstructor()
            ->getMock();

        $manager = $this->getMockBuilder('Doctrine\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $repository = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\Repository\UserRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);
        $this->registry->expects($this->once())
            ->method('getManager')
            ->willReturn($manager);

        $qb->expects($this->any())
            ->method('getArrayResult')
            ->willReturn([]);
        $this->aclHelper->expects($this->any())
            ->method('apply')
            ->willReturn($qb);

        $result = $this->choiceTreeUserProvider->getList();
        $this->assertEquals([], $result);
    }

    protected function getExpectedData()
    {
        return [
            [
                'name' => 'user 1',
                'id' => 1,
            ],
            [
                'name' => 'user 2',
                'id' => '2',
            ]
        ];
    }
}
