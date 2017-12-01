<?php

namespace Keboola\GenericExtractor\Configuration\Extractor\Node;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class SshProxy
{
    public static function configureNode(ArrayNodeDefinition $node)
    {
        $node->children()
            ->scalarNode('host')->isRequired()->end()
            ->scalarNode('port')->isRequired()->end()
            ->scalarNode('user')->isRequired()->end()
            ->scalarNode('#privateKey')->isRequired()->end()
            ->end();
    }
}
