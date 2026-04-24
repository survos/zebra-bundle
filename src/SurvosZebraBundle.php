<?php

declare(strict_types=1);

namespace Survos\ZebraBundle;

use Survos\ZebraBundle\Preview\LabelaryClient;
use Survos\ZebraBundle\Preview\PreviewService;
use Survos\ZebraBundle\Preview\PreviewServiceInterface;
use Survos\ZebraBundle\Twig\Components\PreviewComponent;
use Survos\ZebraBundle\Twig\ZebraExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosZebraBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('labelary')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('endpoint')->defaultValue('https://api.labelary.com/v1')->end()
                        ->scalarNode('api_key')->defaultNull()->end()
                        ->floatNode('timeout')->defaultValue(10.0)->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('pool')->defaultValue('cache.app')->end()
                        ->integerNode('ttl')->defaultValue(86400)->end()
                    ->end()
                ->end()
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('dpmm')->defaultValue(8)->end()
                        ->floatNode('width_inches')->defaultValue(4.0)->end()
                        ->floatNode('height_inches')->defaultValue(2.0)->end()
                        ->enumNode('format')->values(['png', 'pdf', 'json'])->defaultValue('png')->end()
                    ->end()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'paths' => [
                    \dirname(__DIR__) . '/templates' => 'SurvosZebra',
                ],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $defaults = $config['defaults'];

        $services
            ->set(LabelaryClient::class)
            ->args([
                new Reference('http_client'),
                $config['labelary']['endpoint'],
                $config['labelary']['api_key'],
                $config['labelary']['timeout'],
            ])
            ->autowire()
            ->autoconfigure();

        $services
            ->set(PreviewService::class)
            ->args([
                new Reference(LabelaryClient::class),
                new Reference($config['cache']['pool']),
                $config['cache']['enabled'],
                $config['cache']['ttl'],
            ])
            ->autowire()
            ->autoconfigure();

        $services
            ->alias(PreviewServiceInterface::class, PreviewService::class);

        $services
            ->set(ZebraExtension::class)
            ->args([
                new Reference(PreviewServiceInterface::class),
                $defaults['dpmm'],
                $defaults['width_inches'],
                $defaults['height_inches'],
            ])
            ->autowire()
            ->autoconfigure()
            ->tag('twig.extension');

        if (class_exists(\Symfony\UX\TwigComponent\TwigComponentBundle::class)) {
            if ($builder->hasExtension('ux_twig_component')) {
                $builder->prependExtensionConfig('ux_twig_component', [
                    'defaults' => [
                        'Survos\\ZebraBundle\\Twig\\Components\\' => 'components/',
                    ],
                ]);
            }

            $services
                ->set(PreviewComponent::class)
                ->args([
                    new Reference(PreviewServiceInterface::class),
                    $defaults['dpmm'],
                    $defaults['width_inches'],
                    $defaults['height_inches'],
                ])
                ->autowire()
                ->autoconfigure()
                ->tag('twig.component', [
                    'key' => 'Zebra:Preview',
                    'template' => '@SurvosZebra/components/Zebra/Preview.html.twig',
                ]);
        }
    }
}
