<?php

namespace Telelogger;

use Monolog\Logger;
use Illuminate\Log\LogManager;
use Telelogger\TeleloggerHandler;

class LogChannel extends LogManager
{
    /**
     * @param array $config
     *
     * @return Logger
     */
    public function __invoke(array $config)
    {
        file_put_contents(__DIR__ . '/errors.txt','>> Log chanel ' . PHP_EOL );
        $handler = new TeleloggerHandler($config['level'] ?? Logger::DEBUG, $config['bubble'] ?? true);

        return new Logger($this->parseChannel($config), [$this->prepareHandler($handler, $config)]);
    }
}
