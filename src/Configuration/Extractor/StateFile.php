<?php

namespace Keboola\GenericExtractor\Configuration\Extractor;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class StateFile implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('state-file');
        $root = $treeBuilder->getRootNode();
        $root->children()->arrayNode('todo')->isRequired();
        return $treeBuilder;
    }
}
