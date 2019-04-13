<?php

use \Kahlan\Filter\Filters;
use \Symfony\Component\Dotenv\Dotenv;
use \Predis\Client as RedisClient;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Monolog\ErrorHandler;

$dotenv = new Dotenv();
$dotenv->load(
    __DIR__ . '/.env.test'
);

Filters::apply($this, 'run', function ($chain) {

    // Redis client for session
    if (getenv('REDIS_URL') === false) {
        throw new \Exception(sprintf('REDIS_URL is not set to the environment'));
    }
    $redisClient = new Predis\Client(getenv('REDIS_URL'));

    // logger
    $logger = $log = new Logger('log');
    $log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    $errorHandler = new ErrorHandler($logger);
    $errorHandler->registerErrorHandler(); // register as global error handler

    // assign to the global scope
    $scope = $this->suite()->root()->scope(); // The top most describe scope.
    $scope->redisClient = $redisClient;
    $scope->logger = $logger;
    return $chain();
});
