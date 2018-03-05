<?php

namespace Oro\Bundle\EmailBundle\Form\Type;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailTemplateTranslationType extends AbstractType
{
    /** @var ConfigManager */
    protected $configManager;

    /**
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * Set labels for translation widget tabs
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['labels'] = $options['labels'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $isWysiwygEnabled = $this->configManager->get('oro_form.wysiwyg_enabled');

        $resolver->setDefaults(
            [
                'translatable_class'   => 'Oro\\Bundle\\EmailBundle\\Entity\\EmailTemplate',
                'csrf_token_id'        => 'emailtemplate_translation',
                'cascade_validation'   => true,
                'labels'               => [],
                'content_options'      => [],
                'subject_options'      => [],
                'fields'               => function (Options $options) use ($isWysiwygEnabled) {
                    return [
                        'subject' => array_merge_recursive(
                            [
                                'field_type' => 'text'
                            ],
                            $options->get('subject_options')
                        ),
                        'content' => array_merge_recursive(
                            [
                                'field_type'      => 'oro_email_template_rich_text',
                                'attr'            => [
                                    'class'                => 'template-editor',
                                    'data-wysiwyg-enabled' => $isWysiwygEnabled,
                                ],
                                'wysiwyg_options' => [
                                    'height'     => '250px'
                                ]
                            ],
                            $options->get('content_options')
                        )
                    ];
                },
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'a2lix_translations_gedmo';
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
        return 'oro_email_emailtemplate_translatation';
    }
}
