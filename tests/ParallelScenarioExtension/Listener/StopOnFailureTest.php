<?php

namespace Tonic\Behat\ParallelScenarioExtension\Listener;

use Tonic\Behat\ParallelScenarioExtension\Event\ParallelScenarioEventType;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ProcessTerminator;
use Tonic\Behat\ParallelScenarioExtension\ScenarioProcess\ScenarioProcess;
use Tonic\ParallelProcessRunner\Event\ProcessEvent;
use Tonic\ParallelProcessRunner\ParallelProcessRunner;

/**
 * Class StopOnFailureTest.
 *
 * @author kandelyabre <kandelyabre@gmail.com>
 */
class StopOnFailureTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @see StopOnFailure::getSubscribedEvents
     */
    public function testGetSubscribedEvents()
    {
        $this->assertEquals([
            ParallelScenarioEventType::PROCESS_AFTER_STOP => 'stopOnFailure',
        ], StopOnFailure::getSubscribedEvents());
    }

    /**
     * @see StopOnFailure::stopOnFailure
     */
    public function testStopOnFailureWithError()
    {
        $parallelProcessRunner = $this->getMock(ParallelProcessRunner::class, ['stop']);
        $parallelProcessRunner->expects($this->once())->method('stop');

        $processTerminator = $this->getMock(ProcessTerminator::class, ['terminate']);
        $processTerminator->expects($this->once())->method('terminate')->with(1);

        $process = $this->getMock(ScenarioProcess::class, ['withError'], [], '', false);
        $process->expects($this->once())->method('withError')->willReturn(true);

        $event = $this->getMock(ProcessEvent::class, null, [$process]);

        $listener = new StopOnFailure($parallelProcessRunner, $processTerminator);

        /** @var StopOnFailure $listener */
        /** @var ProcessEvent $event */
        $listener->stopOnFailure($event);
    }

    /**
     * @see StopOnFailure::stopOnFailure
     */
    public function testStopOnFailureWithoutError()
    {
        $parallelProcessRunner = $this->getMock(ParallelProcessRunner::class, ['stop']);
        $parallelProcessRunner->expects($this->never())->method('stop');

        $processTerminator = $this->getMock(ProcessTerminator::class, ['terminate']);
        $processTerminator->expects($this->never())->method('terminate');

        $process = $this->getMock(ScenarioProcess::class, ['withError'], [], '', false);
        $process->expects($this->once())->method('withError')->willReturn(false);

        $event = $this->getMock(ProcessEvent::class, null, [$process]);

        $listener = new StopOnFailure($parallelProcessRunner, $processTerminator);

        /** @var StopOnFailure $listener */
        /** @var ProcessEvent $event */
        $listener->stopOnFailure($event);
    }
}
