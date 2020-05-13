<?php

namespace Telelogger;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;

class TeleloggerHandler extends AbstractProcessingHandler
{
    /**
     * @param int  $level  The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($level = Logger::DEBUG, bool $bubble = true)
    {
        file_put_contents(__DIR__ . '/errors.txt','> __construct' . PHP_EOL);
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        file_put_contents(__DIR__ . '/errors.txt','>> error test ' . PHP_EOL );
        $this->handle($records);
    }
    

    /**
     * {@inheritdoc}
     * @suppress PhanTypeMismatchArgument
     */
    protected function write(array $record): void
    {
        //запись в логи...
    
        file_put_contents(__DIR__ . '/errors.txt','>> error test ' . PHP_EOL );
    }
    
    
}
