<?php

include __DIR__ . '/../../vendor/autoload.php';

use \Phata\TeleCore\Session\FactoryInterface;
use \Phata\TeleCore\Session\RedisSessionFactory;

describe('Session\RedisSessionFactory', function () {
    it('to implement FactoryInterface::getChatSession', function () {
        $func = function (FactoryInterface $factory) {
        };
        $sessionFactory = new RedisSessionFactory($this->redisClient);
        $session = $sessionFactory->getChatSession((object) [
            'id' => 12345678,
        ]);
        $session->set('hello', 'world');

        $session = $sessionFactory->getChatSession((object) [
            'id' => 12345678,
        ]);
        $result = $session->get('hello');

        expect(!empty($session->getNamespace()))->toBeTruthy();
        expect($result)->toBe('world');
    });

    it('to implement FactoryInterface::getChatUserSession', function () {
        $func = function (FactoryInterface $factory) {
        };
        $sessionFactory = new RedisSessionFactory($this->redisClient);
        $session = $sessionFactory->getChatUserSession(
            (object) ['id' => 12345678],
            (object) ['id' => 23456789]
        );
        $session->set('hello', 'bar');

        $session = $sessionFactory->getChatUserSession(
            (object) ['id' => 12345678],
            (object) ['id' => 23456789]
        );
        $result = $session->get('hello');

        expect(!empty($session->getNamespace()))->toBeTruthy();
        expect($result)->toBe('bar');
    });
});
