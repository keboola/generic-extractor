<?php

namespace Keboola\GenericExtractor\Configuration\Extractor;

use Keboola\GenericExtractor\Configuration\Extractor\Node\Api;
use Keboola\GenericExtractor\Configuration\Extractor\Node\Authorization;
use Keboola\GenericExtractor\Configuration\Extractor\Node\Config;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigFile implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('config-file');
        $parameters = $root->children()->arrayNode('parameters')->isRequired();
        $api = $parameters->children()->arrayNode('api')->isRequired();
        Api::configureNode($api);
        $config = $parameters->children()->arrayNode('config')->isRequired();
        Config::configureNode($config);
        $authorization = $root->children()->arrayNode('authorization');
        Authorization::configureNode($authorization);
        return $treeBuilder;
    }
}
