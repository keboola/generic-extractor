<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration\Extractor;

use Keboola\GenericExtractor\Configuration\Extractor\Node\Api;
use Keboola\GenericExtractor\Configuration\Extractor\Node\Authorization;
use Keboola\GenericExtractor\Configuration\Extractor\Node\Config;
use Keboola\GenericExtractor\Configuration\Extractor\Node\SshProxy;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigFile implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config-file');
        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();
        $parameters = $root->children()->arrayNode('parameters')->isRequired();
        $api = $parameters->children()->arrayNode('api')->isRequired();
        Api::configureNode($api);
        $config = $parameters->children()->arrayNode('config')->isRequired();
        Config::configureNode($config);
        $authorization = $root->children()->arrayNode('authorization');
        Authorization::configureNode($authorization);
        $sshProxy = $root->children()->arrayNode('sshProxy');
        SshProxy::configureNode($sshProxy);
        return $treeBuilder;
    }
}
