<?php

namespace Oro\Bundle\AttachmentBundle\DependencyInjection;

use Oro\Bundle\AttachmentBundle\Tools\MimeTypesConverter;
use Oro\Bundle\AttachmentBundle\Tools\WebpConfiguration;
use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Maximum upload file size default value.
     */
    private const MAX_FILESIZE_MB = 10;

    public const JPEG_QUALITY = 85;
    public const PNG_QUALITY = 100;
    public const WEBP_QUALITY = 85;

    /**
     * Bytes in one MB. Used to calculate exact bytes in certain MB amount.
     */
    public const BYTES_MULTIPLIER = 1048576;

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('oro_attachment');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('debug_images')
                    ->defaultTrue()
                ->end()
                ->integerNode('maxsize')
                    ->min(1)
                    ->defaultValue(self::MAX_FILESIZE_MB)
                ->end()
                ->arrayNode('upload_file_mime_types')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('upload_image_mime_types')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->booleanNode('processors_allowed')
                    ->defaultTrue()
                ->end()
                ->integerNode('png_quality')
                    ->min(1)
                    ->max(100)
                    ->defaultValue(self::PNG_QUALITY)
                ->end()
                ->integerNode('jpeg_quality')
                    ->min(30)
                    ->max(100)
                    ->defaultValue(self::JPEG_QUALITY)
                ->end()
                ->enumNode('webp_strategy')
                    ->info('Strategy for converting uploaded images to WebP format.')
                    ->values([
                        WebpConfiguration::ENABLED_FOR_ALL,
                        WebpConfiguration::ENABLED_IF_SUPPORTED,
                        WebpConfiguration::DISABLED,
                    ])
                    ->defaultValue(WebpConfiguration::ENABLED_IF_SUPPORTED)
                ->end()
            ->end();

        SettingsBuilder::append(
            $rootNode,
            [
                'maxsize' => ['value' => self::MAX_FILESIZE_MB],
                'upload_file_mime_types' => ['value' => null],
                'upload_image_mime_types' => ['value' => null],
                'processors_allowed' => ['value' => true],
                'jpeg_quality' => ['value' => self::JPEG_QUALITY],
                'png_quality' => ['value' => self::PNG_QUALITY],
                'webp_quality' => ['value' => self::WEBP_QUALITY],
                'external_file_allowed_urls_regexp' => ['value' => '', 'type'  => 'string'],
                'original_file_names_enabled' => ['type' => 'boolean', 'value' => true],
            ]
        );

        $rootNode
            ->validate()
                ->always(function ($v) {
                    if (null === $v['settings']['upload_file_mime_types']['value']) {
                        $v['settings']['upload_file_mime_types']['value'] = MimeTypesConverter::convertToString(
                            $v['upload_file_mime_types']
                        );
                    }
                    if (null === $v['settings']['upload_image_mime_types']['value']) {
                        $v['settings']['upload_image_mime_types']['value'] = MimeTypesConverter::convertToString(
                            $v['upload_image_mime_types']
                        );
                    }

                    return $v;
                })
            ->end();

        return $treeBuilder;
    }
}
