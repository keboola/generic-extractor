<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration\Extractor\Node;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class AwsSignature
{
    public static function configureNode(ArrayNodeDefinition $node): void
    {
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $node->children()
            ->arrayNode('signature')
                ->children()
                    ->arrayNode('credentials')
                        ->children()
                            ->scalarNode('accessKeyId')->isRequired()->end()
                            ->scalarNode('#secretKey')->isRequired()->end()
                            ->scalarNode('serviceName')->isRequired()->end()
                            ->scalarNode('regionName')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
        // @formatter:on
    }
}
