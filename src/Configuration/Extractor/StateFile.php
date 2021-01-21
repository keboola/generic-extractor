<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration\Extractor;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class StateFile implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('state-file');
        $root = $treeBuilder->getRootNode();
        $root->children()->arrayNode('todo')->isRequired();
        return $treeBuilder;
    }
}
