<?php

namespace Oro\Bundle\TagBundle\Tests\Unit\Form\Type;

use Oro\Bundle\TagBundle\Form\Type\TagSelectType;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TagSelectTypeTest extends \PHPUnit_Framework_TestCase
{
    /** @var TagSelectType */
    protected $type;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $authorizationChecker;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $transformer;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $subscriber;

    protected function setUp()
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->transformer = $this->getMockBuilder('Oro\Bundle\TagBundle\Form\Transformer\TagTransformer')
            ->disableOriginalConstructor()
            ->getMock();


        $this->subscriber = $this->getMockBuilder('Oro\Bundle\TagBundle\Form\EventSubscriber\TagSubscriber')
            ->disableOriginalConstructor()
            ->getMock();

        $this->type = new TagSelectType($this->authorizationChecker, $this->transformer, $this->subscriber);
    }

    protected function tearDown()
    {
        unset($this->authorizationChecker, $this->transformer, $this->subscriber, $this->type);
    }

    public function testConfigureOptions()
    {
        $resolver = $this->createMock('Symfony\Component\OptionsResolver\OptionsResolver');
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->isType('array'));

        $this->type->configureOptions($resolver);
    }

    public function testBuildForm()
    {
        $builder = $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with($this->subscriber)
            ->will($this->returnSelf());

        $builder->expects($this->any())
            ->method('add')
            ->will($this->returnSelf());

        $builder->expects($this->any())
            ->method('create')
            ->will($this->returnSelf());

        $this->type->buildForm($builder, array());
    }

    public function testGetName()
    {
        $this->assertEquals('oro_tag_select', $this->type->getName());
    }
}
