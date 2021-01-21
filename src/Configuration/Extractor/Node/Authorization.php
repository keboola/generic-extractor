<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration\Extractor\Node;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Authorization
{
    public static function configureNode(ArrayNodeDefinition $node): void
    {
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $node->children()
            ->arrayNode('oauth_api')
                ->children()
                    ->arrayNode('credentials')
                        ->children()
                            ->scalarNode('#data')->isRequired()->end()
                            ->scalarNode('appKey')->isRequired()->end()
                            ->scalarNode('#appSecret')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
        // @formatter:on
    }
}
