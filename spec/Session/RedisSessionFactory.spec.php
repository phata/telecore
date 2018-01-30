<?php

include __DIR__ . '/../../vendor/autoload.php';

use \Phata\TeleCore\Session\Factory;
use \Phata\TeleCore\Session\RedisSessionFactory;

describe('Session\RedisSessionFactory', function () {
    it('to implement SessionFactory', function () {
        $func = function (Factory $factory){};
        $sessionFactory = new RedisSessionFactory($this->redisClient);
        $message = json_decode(json_encode([
            'from' => [
                'id' => 1234567,
            ],
            'chat' => [
                'id' => 6789012,
            ],
        ]));

        $session = $sessionFactory->fromMessage($message);
        expect(!empty($session->getNamespace()))->toBeTruthy();

        $session->set('hello', 'world');
        $result = $session->get('hello');
        expect($result)->toBe('world');
    });
});
