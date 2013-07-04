<?php
/**
 * Logger profile DB.
 */

class Brigade_Util_ProfilerLog extends Zend_Db_Profiler {

    /**
     * Zend_Log instance
     * @var Zend_Log
     */
    protected $_log;

    /**
     * counter of the total elapsed time
     * @var double
     */
    protected $_totalElapsedTime;


    public function __construct($enabled = false) {
        parent::__construct($enabled);

        $this->_log = new Zend_Log();
        $writer = new Zend_Log_Writer_Stream(realpath(dirname(__FILE__) . '/../../../')
                        . '/logs/db-queries.log');
        $this->_log->addWriter($writer);
    }

    /**
     * Intercept the query end and log the profiling data.
     *
     * @param  integer $queryId
     * @throws Zend_Db_Profiler_Exception
     * @return void
     */
    public function queryEnd($queryId) {
        $state = parent::queryEnd($queryId);

        if (!$this->getEnabled() || $state == self::IGNORED) {
            return;
        }

        // get profile of the current query
        $profile = $this->getQueryProfile($queryId);

        // update totalElapsedTime counter
        $this->_totalElapsedTime += $profile->getElapsedSecs();

        // create the message to be logged
        $message = "\r\nElapsed Secs: " . round($profile->getElapsedSecs(), 5) . "\r\n";
        $message .= "Query: " . $profile->getQuery() . "\r\n";

        // log the message as INFO message
        $this->_log->info($message);
    }
}
