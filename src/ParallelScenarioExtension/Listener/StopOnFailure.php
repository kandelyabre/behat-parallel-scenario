<?php

namespace Tonic\Behat\ParallelScenarioExtension\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tonic\Behat\ParallelScenarioExtension\Event\ParallelScenarioEventType;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ProcessTerminator;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ScenarioProcess;
use Tonic\ParallelProcessRunner\Event\ProcessEvent;
use Tonic\ParallelProcessRunner\ParallelProcessRunner;

/**
 * Class StopOnFailure.
 *
 * @author kandelyabre <kandelyabre@gmail.com>
 */
class StopOnFailure implements EventSubscriberInterface
{
    /**
     * @var ParallelProcessRunner
     */
    private $parallelProcessRunner;
    /**
     * @var ProcessTerminator
     */
    private $processTerminator;

    /**
     * StopOnFailureListener constructor.
     *
     * @param ParallelProcessRunner $processRunner
     */
    public function __construct(ParallelProcessRunner $processRunner, ProcessTerminator $processTerminator)
    {
        $this->parallelProcessRunner = $processRunner;
        $this->processTerminator = $processTerminator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ParallelScenarioEventType::PROCESS_AFTER_STOP => 'stopOnFailure',
        ];
    }

    /**
     * @param ProcessEvent $event
     */
    public function stopOnFailure(ProcessEvent $event)
    {
        /** @var ScenarioProcess $process */
        $process = $event->getProcess();
        if ($process->withError()) {
            $this->parallelProcessRunner->stop();
            $this->processTerminator->terminate(1);
        }
    }
}
