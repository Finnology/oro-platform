<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Shared;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\FilterFieldConfig;
use Oro\Bundle\ApiBundle\Config\FiltersConfig;
use Oro\Bundle\ApiBundle\Filter\ComparisonFilter;
use Oro\Bundle\ApiBundle\Filter\FilterCollection;
use Oro\Bundle\ApiBundle\Filter\FilterFactoryInterface;
use Oro\Bundle\ApiBundle\Filter\SortFilter;
use Oro\Bundle\ApiBundle\Processor\Shared\RegisterConfiguredFilters;
use Oro\Bundle\ApiBundle\Tests\Unit\Filter\RequestAwareFilterStub;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetList\GetListProcessorOrmRelatedTestCase;

class RegisterConfiguredFiltersTest extends GetListProcessorOrmRelatedTestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject|FilterFactoryInterface */
    private $filterFactory;

    /** @var RegisterConfiguredFilters */
    private $processor;

    protected function setUp()
    {
        parent::setUp();

        $this->context->setAction('get_list');

        $this->filterFactory = $this->createMock(FilterFactoryInterface::class);

        $this->processor = new RegisterConfiguredFilters(
            $this->filterFactory,
            $this->doctrineHelper
        );
    }

    /**
     * @param string $dataType
     *
     * @return ComparisonFilter
     */
    private function getComparisonFilter($dataType)
    {
        $filter = new ComparisonFilter($dataType);
        $filter->setSupportedOperators([ComparisonFilter::EQ, ComparisonFilter::NEQ]);

        return $filter;
    }

    public function testProcessWithEmptyFiltersConfig()
    {
        $filtersConfig = new FiltersConfig();

        $this->filterFactory->expects(self::never())
            ->method('createFilter');

        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);
    }

    public function testProcessForComparisonFilterForNotManageableEntity()
    {
        $className = 'Test\Class';
        $this->notManageableClassNames = [$className];

        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filtersConfig->addField('someField', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName($className);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->context->setConfig(new EntityDefinitionConfig());
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('someField');
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ, ComparisonFilter::NEQ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('someField', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForManageableEntity()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filtersConfig->addField('someField', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName(Entity\Category::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('someField');
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ, ComparisonFilter::NEQ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('someField', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForFilterWithOptions()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDescription('filter description');
        $filterConfig->setType('someFilter');
        $filterConfig->setOptions(['some_option' => 'val']);
        $filterConfig->setDataType('integer');
        $filterConfig->setPropertyPath('someField');
        $filterConfig->setArrayAllowed();
        $filterConfig->setOperators([ComparisonFilter::EQ, '<', '>']);
        $filtersConfig->addField('filter', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('someFilter', ['some_option' => 'val', 'data_type' => 'integer'])
            ->willReturnCallback(
                function ($filterType, array $options) {
                    return $this->getComparisonFilter($options['data_type']);
                }
            );

        $this->context->setClassName(Entity\Category::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setDescription('filter description');
        $expectedFilter->setDataType('integer');
        $expectedFilter->setField('someField');
        $expectedFilter->setArrayAllowed(true);
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ, '<', '>']);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('filter', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForToOneAssociation()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filterConfig->setPropertyPath('category');
        $filterConfig->setOperators([ComparisonFilter::GT]);
        $filtersConfig->addField('filter', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName(Entity\User::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('category');
        $expectedFilter->setSupportedOperators([
            ComparisonFilter::EQ,
            ComparisonFilter::NEQ,
            ComparisonFilter::EXISTS,
            ComparisonFilter::NEQ_OR_NULL
        ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('filter', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForToOneAssociationField()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filterConfig->setPropertyPath('category.name');
        $filtersConfig->addField('filter', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName(Entity\User::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('category.name');
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ, ComparisonFilter::NEQ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('filter', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForToManyAssociation()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filterConfig->setPropertyPath('groups');
        $filtersConfig->addField('filter', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName(Entity\User::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('groups');
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('filter', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForToManyAssociationField()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filterConfig->setPropertyPath('groups.name');
        $filtersConfig->addField('filter', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($this->getComparisonFilter('string'));

        $this->context->setClassName(Entity\User::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('string');
        $expectedFilter->setField('groups.name');
        $expectedFilter->setSupportedOperators([ComparisonFilter::EQ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('filter', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForComparisonFilterForToOneAssociationFieldForModelInheritedFromManageableEntity()
    {
        $this->notManageableClassNames = [Entity\UserProfile::class];

        $config = new EntityDefinitionConfig();
        $config->setParentResourceClass(Entity\User::class);

        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('integer');
        $filterConfig->setOperators([
            ComparisonFilter::EQ,
            ComparisonFilter::NEQ,
            ComparisonFilter::GT,
            ComparisonFilter::LT
        ]);
        $filtersConfig->addField('owner', $filterConfig);

        $existinAssociationFilter = new ComparisonFilter('string');
        $existinAssociationFilter->setDataType('integer');

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('integer', [])
            ->willReturn($this->getComparisonFilter('integer'));

        $this->context->setClassName(Entity\UserProfile::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->context->setConfig($config);
        $this->processor->process($this->context);

        $expectedFilter = new ComparisonFilter('integer');
        $expectedFilter->setField('owner');
        $expectedFilter->setSupportedOperators([
            ComparisonFilter::EQ,
            ComparisonFilter::NEQ,
            ComparisonFilter::EXISTS,
            ComparisonFilter::NEQ_OR_NULL
        ]);
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('owner', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForSortFilter()
    {
        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filtersConfig->addField('sort', $filterConfig);

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn(new SortFilter('string'));

        $this->context->setClassName(Entity\Category::class);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->processor->process($this->context);

        $expectedFilter = new SortFilter('string');
        $expectedFilters = new FilterCollection();
        $expectedFilters->add('sort', $expectedFilter);

        self::assertEquals($expectedFilters, $this->context->getFilters());
    }

    public function testProcessForRequestTypeAwareFilter()
    {
        $className = 'Test\Class';
        $this->notManageableClassNames = [$className];

        $filtersConfig = new FiltersConfig();
        $filtersConfig->setExcludeAll();

        $filterConfig = new FilterFieldConfig();
        $filterConfig->setDataType('string');
        $filtersConfig->addField('someField', $filterConfig);

        $filter = new RequestAwareFilterStub('string');

        $this->filterFactory->expects(self::once())
            ->method('createFilter')
            ->with('string', [])
            ->willReturn($filter);

        $this->context->setClassName($className);
        $this->context->setConfigOfFilters($filtersConfig);
        $this->context->setConfig(new EntityDefinitionConfig());
        $this->processor->process($this->context);

        self::assertSame($this->context->getRequestType(), $filter->getRequestType());
    }
}
