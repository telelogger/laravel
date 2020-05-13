<?php

namespace Telelogger;

use Exception;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use RuntimeException;

class EventHandler
{
    /**
     * Map event handlers to events.
     *
     * @var array
     */
    protected static $eventHandlerMap = [
        'router.matched' => 'routerMatched',                         // Until Laravel 5.1
        'Illuminate\Routing\Events\RouteMatched' => 'routeMatched',  // Since Laravel 5.2

        'illuminate.query' => 'query',                                 // Until Laravel 5.1
        'Illuminate\Database\Events\QueryExecuted' => 'queryExecuted', // Since Laravel 5.2

        'illuminate.log' => 'log',                                // Until Laravel 5.3
        'Illuminate\Log\Events\MessageLogged' => 'messageLogged', // Since Laravel 5.4

        'Illuminate\Console\Events\CommandStarting' => 'commandStarting', // Since Laravel 5.5
        'Illuminate\Console\Events\CommandFinished' => 'commandFinished', // Since Laravel 5.5
    ];

    /**
     * Map authentication event handlers to events.
     *
     * @var array
     */
    protected static $authEventHandlerMap = [
        'Illuminate\Auth\Events\Authenticated' => 'authenticated', // Since Laravel 5.3
    ];

    /**
     * Map queue event handlers to events.
     *
     * @var array
     */
    protected static $queueEventHandlerMap = [
        'Illuminate\Queue\Events\JobProcessing' => 'queueJobProcessing', // Since Laravel 5.2
        'Illuminate\Queue\Events\JobProcessed' => 'queueJobProcessed', // Since Laravel 5.2
        'Illuminate\Queue\Events\JobExceptionOccurred' => 'queueJobExceptionOccurred', // Since Laravel 5.2
        'Illuminate\Queue\Events\WorkerStopping' => 'queueWorkerStopping', // Since Laravel 5.2
    ];

    /**
     * The Laravel event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $events;

    /**
     * Indicates if we should we add SQL queries to the breadcrumbs.
     *
     * @var bool
     */
    private $recordSqlQueries;

    /**
     * Indicates if we should we add query bindings to the breadcrumbs.
     *
     * @var bool
     */
    private $recordSqlBindings;

    /**
     * Indicates if we should we add Laravel logs to the breadcrumbs.
     *
     * @var bool
     */
    private $recordLaravelLogs;

    /**
     * Indicates if we should we add queue info to the breadcrumbs.
     *
     * @var bool
     */
    private $recordQueueInfo;

    /**
     * EventHandler constructor.
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(Dispatcher $events)
    {
        file_put_contents(__DIR__ . '/errors.txt', 'EventHandler ' . PHP_EOL ,FILE_APPEND );
        $this->events = $events;
        $this->recordSqlQueries =true;
        $this->recordSqlBindings =true;
        $this->recordLaravelLogs =true;
        $this->recordQueueInfo =true;
    }

    /**
     * Attach all event handlers.
     */
    public function subscribe()
    {
        foreach (static::$eventHandlerMap as $eventName => $handler) {
            $this->events->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Attach all authentication event handlers.
     */
    public function subscribeAuthEvents()
    {
        foreach (static::$authEventHandlerMap as $eventName => $handler) {
            $this->events->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Attach all queue event handlers.
     */
    public function subscribeQueueEvents()
    {
        foreach (static::$queueEventHandlerMap as $eventName => $handler) {
            $this->events->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Pass through the event and capture any errors.
     *
     * @param string $method
     * @param array  $arguments
     */
    public function __call($method, $arguments)
    {
        $handlerMethod = $handlerMethod = "{$method}Handler";
        //file_put_contents(__DIR__ . '/errors.txt', '> call EventHandler@' . $handlerMethod . PHP_EOL ,FILE_APPEND );
        if (!method_exists($this, $handlerMethod)) {
            throw new RuntimeException("Missing event handler: {$handlerMethod}");
        }

        try {
            call_user_func_array([$this, $handlerMethod], $arguments);
        } catch (Exception $exception) {
            // Ignore
        }
    }

    /**
     * Until Laravel 5.1
     *
     * @param Route $route
     */
    protected function routerMatchedHandler(Route $route)
    {
        if ($route->getName()) {
            // someaction (route name/alias)
            $routeName = $route->getName();
        } elseif ($route->getActionName()) {
            // SomeController@someAction (controller action)
            $routeName = $route->getActionName();
        }
        if (empty($routeName) || $routeName === 'Closure') {
            // /someaction // Fallback to the url
            $routeName = $route->uri();
        }
        
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Routing\Events\RouteMatched $match
     */
    protected function routeMatchedHandler(RouteMatched $match)
    {
        $this->routerMatchedHandler($match->route);
    }

    /**
     * Until Laravel 5.1
     *
     * @param string $query
     * @param array  $bindings
     * @param int    $time
     * @param string $connectionName
     */
    protected function queryHandler($query, $bindings, $time, $connectionName)
    {
        if (!$this->recordSqlQueries) {
            return;
        }

        $data = ['connectionName' => $connectionName];

        if ($time !== null) {
            $data['time'] = $time;
        }
        $data['query'] = vsprintf(str_replace(array('?'), array('\'%s\''), $query), $bindings);;

        Breadcrumbs::getInstance()->add($data);
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Database\Events\QueryExecuted $query
     */
    protected function queryExecutedHandler(QueryExecuted $query)
    {
        if (!$this->recordSqlQueries) {
            return;
        }
        $data = ['connectionName' => $query->connectionName];

        if ($query->time !== null) {
            $data['time'] = $query->time;
        }
        $data['query'] = vsprintf(str_replace(array('?'), array('\'%s\''), $query->sql), $query->bindings);;
        
        Breadcrumbs::getInstance()->add($data);
    }

    /**
     * Until Laravel 5.3
     *
     * @param string     $level
     * @param string     $message
     * @param array|null $context
     */
    protected function logHandler($level, $message, $context)
    {
        $this->addLogBreadcrumb($level, $message, is_array($context) ? $context : []);
    }

    /**
     * Since Laravel 5.4
     *
     * @param \Illuminate\Log\Events\MessageLogged $logEntry
     */
    protected function messageLoggedHandler(MessageLogged $logEntry)
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> messageLoggedHandler ' . json_encode($logEntry) . PHP_EOL ,FILE_APPEND );
        //$this->addLogBreadcrumb($logEntry->level, $logEntry->message, $logEntry->context);
    }

    /**
     * Helper to add an log breadcrumb.
     *
     * @param string $level   Log level. May be any standard.
     * @param string $message Log messsage.
     * @param array  $context Log context.
     */
    private function addLogBreadcrumb(string $level, string $message, array $context = []): void
    {
    }

    /**
     * Since Laravel 5.3
     *
     * @param \Illuminate\Auth\Events\Authenticated $event
     */
    protected function authenticatedHandler(Authenticated $event)
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> authenticatedHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        /*Integration::configureScope(static function (Scope $scope) use ($event): void {
            $scope->setUser([
                'id' => $event->user->getAuthIdentifier(),
            ], true);
        });*/
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     */
    protected function queueJobProcessingHandler(JobProcessing $event)
    {
        $this->beforeQueuedJob();

        if (!$this->recordQueueInfo) {
            return;
        }

        $job = [
            'job' => $event->job->getName(),
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'connection' => $event->connectionName,
        ];

        // Resolve name exists only from Laravel 5.3+
        if (method_exists($event->job, 'resolveName')) {
            $job['resolved'] = $event->job->resolveName();
        }
    
        file_put_contents(__DIR__ . '/errors.txt', '>>> queueJobProcessingHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        /*Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'queue.job',
            'Processing queue job',
            $job
        ));*/
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     */
    protected function queueJobExceptionOccurredHandler(JobExceptionOccurred $event)
    {
        //file_put_contents(__DIR__ . '/errors.txt', '>>> queueJobExceptionOccurredHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        $this->afterQueuedJob();
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     */
    protected function queueJobProcessedHandler(JobProcessed $event)
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> queueJobProcessedHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        $this->afterQueuedJob();
    }

    /**
     * Since Laravel 5.2
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     */
    protected function queueWorkerStoppingHandler(WorkerStopping $event)
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> queueWorkerStoppingHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        // Flush any and all events that were possibly generated by queue jobs
        // Integration::flushEvents();
    }

    /**
     * Since Laravel 5.5
     *
     * @param \Illuminate\Console\Events\CommandStarting $event
     */
    protected function commandStartingHandler(CommandStarting $event)
    {
        if ($event->command) {
            file_put_contents(__DIR__ . '/errors.txt', '>>> commandStartingHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
            /*Integration::configureScope(static function (Scope $scope) use ($event): void {
                $scope->setTag('command', $event->command);
            });*/

            if (!$this->recordQueueInfo) {
                return;
            }

            /*Integration::addBreadcrumb(new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                'artisan.command',
                'Starting Artisan command: ' . $event->command,
                method_exists($event->input, '__toString') ? [
                    'input' => (string)$event->input,
                ] : []
            ));*/
        }
    }

    /**
     * Since Laravel 5.5
     *
     * @param \Illuminate\Console\Events\CommandFinished $event
     */
    protected function commandFinishedHandler(CommandFinished $event)
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> commandFinishedHandler ' . json_encode($event) . PHP_EOL ,FILE_APPEND );
        /*Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'artisan.command',
            'Finished Artisan command: ' . $event->command,
            array_merge([
                'exit' => $event->exitCode,
            ], method_exists($event->input, '__toString') ? [
                'input' => (string)$event->input,
            ] : [])
        ));

        Integration::configureScope(static function (Scope $scope) use ($event): void {
            $scope->setTag('command', '');
        });*/

        // Flush any and all events that were possibly generated by the command
        //Integration::flushEvents();
    }

    private function beforeQueuedJob()
    {
        // Когда работа начинается, создаем новую цепочку
        // SentrySdk::getCurrentHub()->pushScope();
    }

    private function afterQueuedJob()
    {
        file_put_contents(__DIR__ . '/errors.txt', '>>> afterQueuedJob ' . PHP_EOL ,FILE_APPEND );
        // Сброс всех событий, которые могли быть сгенерированы заданиями очереди
        //Integration::flushEvents();

        // We have added a scope when the job started processing
        //SentrySdk::getCurrentHub()->popScope();
    }
}
