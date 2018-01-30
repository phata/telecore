<?php

use \Kahlan\Filter\Filters;
use \Symfony\Component\Dotenv\Dotenv;
use \Predis\Client as RedisClient;

$dotenv = new Dotenv();
$dotenv->load(
    __DIR__ . '/.env.test'
);

Filters::apply($this, 'run', function($chain) {

    // redis client for session
    if (getenv('REDIS_SCHEME') == 'tcp') {
        $redisClient = new Predis\Client(
            [
                'scheme' => getenv('REDIS_SCHEME'),
                'host' => getenv('REDIS_HOST'),
                'port' => getenv('REDIS_PORT'),
            ]
        );
    } else if (getenv('REDIS_SCHEME') == 'unix') {
        $redisClient = new RedisClient(
            [
                'scheme' => getenv('REDIS_SCHEME'),
                'path' => getenv('REDIS_PATH'),
            ]
        );
    } else {
        throw new \Exception(sprintf('unknown redis connection scheme "%s"', getenv('REDIS_SCHEME')));
    }

    $scope = $this->suite()->root()->scope(); // The top most describe scope.
    $scope->redisClient = $redisClient;
    return $chain();
});
