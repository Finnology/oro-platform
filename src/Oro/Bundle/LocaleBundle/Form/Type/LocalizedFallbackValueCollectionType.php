<?php

namespace Oro\Bundle\LocaleBundle\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\LocaleBundle\Form\DataTransformer\LocalizedFallbackValueCollectionTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalizedFallbackValueCollectionType extends AbstractType
{
    const NAME = 'oro_locale_localized_fallback_value_collection';

    const FIELD_VALUES = 'values';
    const FIELD_IDS    = 'ids';

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
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
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            self::FIELD_VALUES,
            LocalizedPropertyType::NAME,
            ['entry_type' => $options['entry_type'], 'entry_options' => $options['entry_options']]
        )->add(
            self::FIELD_IDS,
            'collection',
            ['entry_type' => 'hidden']
        );

        $builder->addViewTransformer(
            new LocalizedFallbackValueCollectionTransformer($this->registry, $options['field'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'field' => 'string', // field used to store data - string or text
            'entry_type' => 'text',   // value form type
            'entry_options' => [],       // value form options
        ]);
    }
}
