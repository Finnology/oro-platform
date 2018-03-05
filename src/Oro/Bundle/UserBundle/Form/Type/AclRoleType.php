<?php

namespace Oro\Bundle\UserBundle\Form\Type;

use Oro\Bundle\UserBundle\Form\EventListener\ChangeRoleSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AclRoleType extends AbstractType
{
    /**
     * @var array privilege fields config
     */
    protected $privilegeConfig;

    /**
     * @param array $privilegeTypeConfig
     */
    public function __construct(array $privilegeTypeConfig)
    {
        $this->privilegeConfig = $privilegeTypeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'label',
            'text',
            [
                'required' => true,
                'label'    => 'oro.user.role.role.label'
            ]
        );

        $builder->add(
            'appendUsers',
            'oro_entity_identifier',
            [
                'class'    => 'OroUserBundle:User',
                'required' => false,
                'mapped'   => false,
                'multiple' => true,
            ]
        );

        $builder->add(
            'removeUsers',
            'oro_entity_identifier',
            [
                'class'    => 'OroUserBundle:User',
                'required' => false,
                'mapped'   => false,
                'multiple' => true,
            ]
        );
        $builder->add(
            'privileges',
            'hidden',
            [
                'mapped' => false,
            ]
        );

        $builder->addEventSubscriber(new ChangeRoleSubscriber());
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Oro\Bundle\UserBundle\Entity\Role',
                'csrf_token_id' => 'role',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_user_role_form';
    }
}
