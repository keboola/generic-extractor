<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration\Extractor\Node;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Api
{
    public static function configureNode(ArrayNodeDefinition $node): void
    {
        $node
            ->ignoreExtraKeys() // TODO add missing sub-nodes
            ->children()
            ->scalarNode('caCertificate')->cannotBeEmpty()->end()
            ->scalarNode('clientCertificate')->cannotBeEmpty()->end()
            ->end();
    }
}
