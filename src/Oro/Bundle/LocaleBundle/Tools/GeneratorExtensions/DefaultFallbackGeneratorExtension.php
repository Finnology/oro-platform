<?php
declare(strict_types=1);

namespace Oro\Bundle\LocaleBundle\Tools\GeneratorExtensions;

use Oro\Bundle\EntityExtendBundle\Tools\GeneratorExtensions\AbstractEntityGeneratorExtension;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Model\ExtendFallback;
use Oro\Bundle\LocaleBundle\Provider\DefaultFallbackMethodsNamesProvider;
use Oro\Bundle\LocaleBundle\Storage\EntityFallbackFieldsStorage;
use Oro\Component\PhpUtils\ClassGenerator;

/**
 * Generates getters and setters for default fallback fields.
 */
class DefaultFallbackGeneratorExtension extends AbstractEntityGeneratorExtension
{
    private EntityFallbackFieldsStorage $storage;

    private DefaultFallbackMethodsNamesProvider $defaultFallbackMethodsNamesProvider;

    public function __construct(
        EntityFallbackFieldsStorage $storage,
        DefaultFallbackMethodsNamesProvider $defaultFallbackMethodsNamesProvider
    ) {
        $this->storage = $storage;
        $this->defaultFallbackMethodsNamesProvider = $defaultFallbackMethodsNamesProvider;
    }

    public function supports(array $schema): bool
    {
        return isset($schema['class'], $this->storage->getFieldMap()[$schema['class']]);
    }

    public function generate(array $schema, ClassGenerator $class): void
    {
        if (!$this->supports($schema)) {
            return;
        }

        $fields = $this->storage->getFieldMap()[$schema['class']];
        if (empty($fields)) {
            return;
        }

        $class->setExtends(ExtendFallback::class);

        foreach ($fields as $singularName => $fieldName) {
            $this->generateGetter($singularName, $fieldName, $class);
            $this->generateDefaultGetter($singularName, $fieldName, $class);
            $this->generateDefaultSetter($singularName, $fieldName, $class);
        }

        $this->generateCloneLocalizedFallbackValueAssociationsMethod($fields, $class);
    }

    /**
     * Generates code for a getter method
     */
    protected function generateGetter(string $singularName, string $fieldName, ClassGenerator $class): void
    {
        $class->addMethod($this->defaultFallbackMethodsNamesProvider->getGetterMethodName($singularName))
            ->addBody(\sprintf('return $this->getFallbackValue($this->%s, $localization);', $fieldName))
            ->addComment(
                $this->generateDocblock(
                    [\sprintf('\%s|null', Localization::class) =>'$localization'],
                    \sprintf('\%s|null', LocalizedFallbackValue::class)
                )
            )
            ->addParameter('localization')->setType(Localization::class)->setDefaultValue(null);
    }

    /**
     * Generates code for the default getter method
     */
    protected function generateDefaultGetter(string $singularName, string $fieldName, ClassGenerator $class): void
    {
        $class->addMethod($this->defaultFallbackMethodsNamesProvider->getDefaultGetterMethodName($singularName))
            ->addBody(\sprintf('return $this->getDefaultFallbackValue($this->%s);', $fieldName))
            ->addComment($this->generateDocblock([], \sprintf('\%s|null', LocalizedFallbackValue::class)));
    }

    /**
     * Generates code for the default setter method
     */
    protected function generateDefaultSetter(string $singularName, string $fieldName, ClassGenerator $class): void
    {
        $class->addMethod($this->defaultFallbackMethodsNamesProvider->getDefaultSetterMethodName($singularName))
            ->addBody(\sprintf('return $this->setDefaultFallbackValue($this->%s, $value);', $fieldName))
            ->addComment($this->generateDocblock(['string' => '$value'], '$this'))
            ->addParameter('value');
    }

    /**
     * Generates code for the cloneLocalizedFallbackValueAssociations method
     */
    protected function generateCloneLocalizedFallbackValueAssociationsMethod(array $fields, ClassGenerator $class): void
    {
        $fieldNames = !empty($fields) ? '["' . implode('", "', $fields) . '"]' : '[]';

        $methodBody = <<<METHOD_BODY
foreach ($fieldNames as \$propertyName) {
    \$newCollection = new \Doctrine\Common\Collections\ArrayCollection();

    foreach (\$this->\$propertyName as \$element) {
        \$newCollection->add(clone \$element);
    }

    \$this->\$propertyName = \$newCollection;
}

return \$this;
METHOD_BODY;

        $class->addMethod('cloneLocalizedFallbackValueAssociations')
            ->addBody($methodBody)
            ->addComment('Clones a collections of LocalizedFallbackValue associations.')
            ->setReturnType('self');
    }

    protected function generateDocblock(array $params, string $return = null): string
    {
        $parts = [];

        foreach ($params as $type => $param) {
            $parts[] = \sprintf('@param %s %s', $type, $param);
        }

        if ($return) {
            $parts[] = \sprintf('@return %s', $return);
        }

        return \implode("\n", $parts);
    }
}
