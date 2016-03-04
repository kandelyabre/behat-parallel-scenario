<?php


namespace Tonic\Behat\ParallelScenarioExtension\ScenarioProcess;

/**
 * Class ProcessTerminator.
 * @codeCoverageIgnore
 */
class ProcessTerminator
{
    /**
     * @param int $code
     */
    public function terminate($code)
    {
        exit($code);
    }
}