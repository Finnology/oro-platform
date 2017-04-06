<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use Oro\Bundle\SearchBundle\EventListener\IndexListener;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider;
use Oro\Bundle\SearchBundle\Tests\Unit\Fixture\Entity\Product;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class IndexListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $doctrineHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $searchEngine;

    /**
     * @var PropertyAccessorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $propertyAccessor;

    /**
     * @var array
     */
    protected $entitiesMapping = [
        'Oro\Bundle\SearchBundle\Tests\Unit\Fixture\Entity\Product' => [
            'fields' => [
                [
                    'name' => 'field',
                ],
            ],
        ],
    ];

    protected function setUp()
    {
        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->searchEngine = $this->createMock('Oro\Bundle\SearchBundle\Engine\EngineInterface');

        $this->propertyAccessor = $this->createMock('Symfony\Component\PropertyAccess\PropertyAccessorInterface');
    }

    public function testOnFlush()
    {
        $insertedEntity = $this->createTestEntity('inserted');
        $updatedEntity = $this->createTestEntity('updated');
        $deletedEntity = $this->createTestEntity('deleted');
        $notSupportedEntity = new \stdClass();

        $entityClass = 'Product';
        $entityId = 1;
        $deletedEntityReference = new \stdClass();
        $deletedEntityReference->class = $entityClass;
        $deletedEntityReference->id = $entityId;

        $meta = $this->createClassMetadata();

        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
        $unitOfWork->expects($this->once())->method('getScheduledEntityInsertions')
            ->will($this->returnValue([
                'inserted' => $insertedEntity,
                'not_supported' => $notSupportedEntity,
            ]));
        $unitOfWork->expects($this->exactly(2))->method('getScheduledEntityUpdates')
            ->will($this->returnValue([
                'updated' => $updatedEntity,
                'not_supported' => $notSupportedEntity,
            ]));
        $unitOfWork->expects($this->once())->method('getScheduledEntityDeletions')
            ->will($this->returnValue([
                'deleted' => $deletedEntity,
                'not_supported' => $notSupportedEntity,
            ]));
        $unitOfWork->expects($this->once())->method('getScheduledCollectionUpdates')
            ->will($this->returnValue([]));
        $unitOfWork->expects($this->exactly(2))
            ->method('getEntityChangeSet')
            ->will($this->returnValueMap(
                [
                    [$updatedEntity, 'field' => ['val1', 'val2']],
                    [$notSupportedEntity, []],
                ]
            ));

        $this->doctrineHelper->expects($this->once())->method('getEntityClass')->with($deletedEntity)
            ->will($this->returnValue($entityClass));
        $this->doctrineHelper->expects($this->once())->method('getSingleEntityIdentifier')->with($deletedEntity)
            ->will($this->returnValue($entityId));

        $entityManager = $this->createEntityManager();
        $entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));
        $entityManager->expects($this->once())->method('getReference')->with($entityClass, $entityId)
            ->will($this->returnValue($deletedEntityReference));
        $entityManager->expects($this->any())->method('getClassMetadata')
            ->will($this->returnValue($meta));

        $listener = $this->createListener();
        $listener->onFlush(new OnFlushEventArgs($entityManager));

        $this->assertAttributeEquals(
            [spl_object_hash($insertedEntity) => $insertedEntity],
            'savedEntities',
            $listener
        );
        $this->assertAttributeEquals(
            ['deleted' => $deletedEntityReference],
            'deletedEntities',
            $listener
        );
    }

    public function testPostFlushNoEntities()
    {
        $this->searchEngine->expects($this->never())->method('save');
        $this->searchEngine->expects($this->never())->method('delete');

        $listener = $this->createListener();
        $listener->postFlush(new PostFlushEventArgs($this->createEntityManager()));
    }

    /**
     * @param bool $realTime
     * @dataProvider postFlushDataProvider
     */
    public function testPostFlush($realTime)
    {
        $insertedEntity = $this->createTestEntity('inserted');
        $insertedEntities = [spl_object_hash($insertedEntity) => $insertedEntity];
        $deletedEntity = $this->createTestEntity('deleted');
        $deletedEntities = ['deleted' => $deletedEntity];

        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
        $unitOfWork->expects($this->once())->method('getScheduledEntityInsertions')
            ->will($this->returnValue($insertedEntities));
        $unitOfWork->expects($this->exactly(2))->method('getScheduledEntityUpdates')
            ->will($this->returnValue([]));
        $unitOfWork->expects($this->once())->method('getScheduledEntityDeletions')
            ->will($this->returnValue($deletedEntities));
        $unitOfWork->expects($this->once())->method('getScheduledCollectionUpdates')
            ->will($this->returnValue([]));

        $meta = $this->createClassMetadata();

        $entityManager = $this->createEntityManager();
        $entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));
        $entityManager->expects($this->once())->method('getReference')
            ->will($this->returnValue($deletedEntity));
        $entityManager->expects($this->any())->method('getClassMetadata')
            ->will($this->returnValue($meta));

        $this->searchEngine->expects($this->once())->method('save')->with($insertedEntities);
        $this->searchEngine->expects($this->once())->method('delete')->with($deletedEntities);

        $listener = $this->createListener($realTime);
        $listener->onFlush(new OnFlushEventArgs($entityManager));
        $listener->postFlush(new PostFlushEventArgs($entityManager));

        $this->assertAttributeEmpty('savedEntities', $listener);
        $this->assertAttributeEmpty('deletedEntities', $listener);
    }

    public function testOnClear()
    {
        $insertedEntity = $this->createTestEntity('inserted');
        $insertedEntities = ['inserted' => $insertedEntity];
        $deletedEntity = $this->createTestEntity('deleted');
        $deletedEntities = ['deleted' => $deletedEntity];

        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()->getMock();
        $unitOfWork->expects($this->once())->method('getScheduledEntityInsertions')
            ->will($this->returnValue($insertedEntities));
        $unitOfWork->expects($this->exactly(2))->method('getScheduledEntityUpdates')
            ->will($this->returnValue([]));
        $unitOfWork->expects($this->once())->method('getScheduledEntityDeletions')
            ->will($this->returnValue($deletedEntities));
        $unitOfWork->expects($this->once())->method('getScheduledCollectionUpdates')
            ->will($this->returnValue([]));

        $meta = $this->createClassMetadata();

        $entityManager = $this->createEntityManager();
        $entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));
        $entityManager->expects($this->once())->method('getReference')
            ->will($this->returnValue($deletedEntity));
        $entityManager->expects($this->any())->method('getClassMetadata')
            ->will($this->returnValue($meta));

        $listener = $this->createListener();
        $listener->onFlush(new OnFlushEventArgs($entityManager));
        $listener->onClear(new OnClearEventArgs($entityManager));

        $this->assertAttributeEmpty('savedEntities', $listener);
        $this->assertAttributeEmpty('deletedEntities', $listener);
    }

    /**
     * @return array
     */
    public function postFlushDataProvider()
    {
        return [
            'realtime' => [true],
            'queued'   => [false],
        ];
    }

    public function testSetRealTimeUpdate()
    {
        $listener = $this->createListener();

        $this->assertAttributeEquals(true, 'realTimeUpdate', $listener);
        $listener->setRealTimeUpdate(false);
        $this->assertAttributeEquals(false, 'realTimeUpdate', $listener);
        $listener->setRealTimeUpdate(true);
        $this->assertAttributeEquals(true, 'realTimeUpdate', $listener);
    }

    public function testSetEntitiesConfig()
    {
        $listener = $this->createListener();
        $config = ['key' => 'value'];

        $this->assertAttributeEquals($this->entitiesMapping, 'entitiesConfig', $listener);
        $listener->setEntitiesConfig($config);
        $this->assertAttributeEquals($config, 'entitiesConfig', $listener);
    }

    /**
     * @param bool $realTime
     * @return IndexListener
     */
    protected function createListener($realTime = true)
    {
        $listener = new IndexListener($this->doctrineHelper, $this->searchEngine, $this->propertyAccessor);
        $listener->setRealTimeUpdate($realTime);
        $listener->setEntitiesConfig($this->entitiesMapping);

        $eventDispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()->getMock();
        $mapperProvider = new SearchMappingProvider($eventDispatcher);
        $mapperProvider->setMappingConfig($this->entitiesMapping);
        $listener->setMappingProvider($mapperProvider);

        return $listener;
    }

    /**
     * @param  string  $name
     * @return Product
     */
    protected function createTestEntity($name)
    {
        $result = new Product();
        $result->setName($name);

        return $result;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createEntityManager()
    {
        return $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createClassMetadata()
    {
        $metaProperties = [
            [
                'inversedBy' => 'products',
                'targetEntity' => 'Oro\Bundle\SearchBundle\Tests\Unit\Fixture\Entity\Product',
                'type' => ClassMetadataInfo::MANY_TO_ONE,
                'fieldName' => 'manufacturer',
            ]
        ];
        $meta = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();
        $meta
            ->expects($this->any())
            ->method('getAssociationMappings')
            ->will($this->onConsecutiveCalls($metaProperties, [], [], [], $metaProperties, [], [], []));

        return $meta;
    }
}
