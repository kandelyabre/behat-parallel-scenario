<?php

namespace Tonic\Behat\ParallelScenarioExtension\ServiceContainer;

use Behat\Behat\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\Cli\ServiceContainer\CliExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Behat\Testwork\Specification\ServiceContainer\SpecificationExtension;
use Behat\Testwork\Suite\ServiceContainer\SuiteExtension;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tonic\Behat\ParallelScenarioExtension\Cli\ParallelScenarioController;
use Tonic\Behat\ParallelScenarioExtension\Feature\FeatureExtractor;
use Tonic\Behat\ParallelScenarioExtension\Feature\FeatureRunner;
use Tonic\Behat\ParallelScenarioExtension\Listener\OutputPrinter;
use Tonic\Behat\ParallelScenarioExtension\Listener\StopOnFailure;
use Tonic\Behat\ParallelScenarioExtension\ScenarioInfo\ScenarioInfoExtractor;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ProcessTerminator;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ScenarioProcessFactory;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ScenarioProcessProfileBalance;
use Tonic\ParallelProcessRunner\ParallelProcessRunner;

/**
 * Class ParallelScenarioExtension.
 *
 * @author kandelyabre <kandelyabre@gmail.com>
 */
class ParallelScenarioExtension implements ExtensionInterface
{
    const FEATURE_EXTRACTOR = 'parallel_scenario.feature.extractor';
    const FEATURE_RUNNER = 'parallel_scenario.feature.runner';

    const SCENARIO_INFO_EXTRACTOR = 'parallel_scenario.scenario.info.extractor';

    const PROCESS_RUNNER = 'parallel_scenario.process.runner';
    const PROCESS_TERMINATOR = 'parallel_scenario.process.terminator';
    const PROCESS_FACTORY = 'parallel_scenario.process.factory';
    const PROCESS_PROFILE_BALANCE = 'parallel_scenario.process.profile_balance';
    const OUTPUT_PRINTER = 'parallel_scenario.output.printer';
    const STOP_ON_FAILURE = 'parallel_scenario.stop_on_failure';

    const CONFIG_OPTIONS = 'options';
    const CONFIG_SKIP = 'skip';
    const CONFIG_PROFILES = 'profiles';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return 'parallel_scenario';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
            ->arrayNode(self::CONFIG_OPTIONS)
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode(self::CONFIG_SKIP)
            ->prototype('scalar')
            ->end()
            ->defaultValue([])
            ->end();

        $builder
            ->children()
            ->arrayNode(self::CONFIG_PROFILES)
            ->prototype('scalar')
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $containerBuilder, array $config)
    {
        $this->loadScenarioInfoExtractor($containerBuilder);
        $this->loadFeatureExtractor($containerBuilder);
        $this->loadFeatureRunner($containerBuilder);
        $this->loadProcessProfileBalance($containerBuilder, $config);

        $this->loadProcessRunner($containerBuilder);
        $this->loadProcessFactory($containerBuilder, $config);
        $this->loadController($containerBuilder);

        $this->loadOutputPrinter($containerBuilder);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @param array            $config
     */
    protected function loadProcessFactory(ContainerBuilder $containerBuilder, array $config)
    {
        $skipOptions = $config[self::CONFIG_OPTIONS][self::CONFIG_SKIP];

        $definition = new Definition(ScenarioProcessFactory::class);
        $definition->addMethodCall('addSkipOptions', [
            $skipOptions,
        ]);

        $containerBuilder->setDefinition(self::PROCESS_FACTORY, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @param array            $config
     */
    protected function loadProcessProfileBalance(ContainerBuilder $containerBuilder, array $config)
    {
        $profiles = $config[self::CONFIG_PROFILES];
        $definition = new Definition(ScenarioProcessProfileBalance::class, [
            $profiles,
        ]);
        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, [
            'priority' => 800,
        ]);

        $containerBuilder->setDefinition(self::PROCESS_PROFILE_BALANCE, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadController(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(ParallelScenarioController::class, [
            new Reference(self::FEATURE_RUNNER),
            new Reference(self::FEATURE_EXTRACTOR),
            new Reference(self::PROCESS_FACTORY),
            new Reference(self::OUTPUT_PRINTER),
        ]);

        $definition->addTag(CliExtension::CONTROLLER_TAG, [
            'priority' => 1,
        ]);

        $containerBuilder->setDefinition(CliExtension::CONTROLLER_TAG.'.parallel-scenario', $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadOutputPrinter(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(OutputPrinter::class);
        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, [
            'priority' => -1,
        ]);

        $containerBuilder->setDefinition(self::OUTPUT_PRINTER, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadProcessRunner(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(ParallelProcessRunner::class, [
            new Reference('event_dispatcher'),
        ]);

        $containerBuilder->setDefinition(self::PROCESS_RUNNER, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadProcessTerminator(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(ProcessTerminator::class);

        $containerBuilder->setDefinition(self::PROCESS_TERMINATOR, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadStopOnFailure(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(StopOnFailure::class, [
            new Reference(self::PROCESS_RUNNER),
            new Reference(self::PROCESS_TERMINATOR),
        ]);
        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG);

        $containerBuilder->setDefinition(self::STOP_ON_FAILURE, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadFeatureExtractor(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(FeatureExtractor::class, [
            new Reference(SuiteExtension::REGISTRY_ID),
            new Reference(SpecificationExtension::FINDER_ID),
        ]);

        $containerBuilder->setDefinition(self::FEATURE_EXTRACTOR, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadFeatureRunner(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(FeatureRunner::class, [
            new Reference('event_dispatcher'),
            new Reference(self::SCENARIO_INFO_EXTRACTOR),
            new Reference(self::PROCESS_FACTORY),
            new Reference(self::PROCESS_RUNNER),
        ]);

        $containerBuilder->setDefinition(self::FEATURE_RUNNER, $definition);
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    protected function loadScenarioInfoExtractor(ContainerBuilder $containerBuilder)
    {
        $definition = new Definition(ScenarioInfoExtractor::class);

        $containerBuilder->setDefinition(self::SCENARIO_INFO_EXTRACTOR, $definition);
    }
}
