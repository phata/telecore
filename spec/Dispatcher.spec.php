<?php

use \Phata\TeleCore\Session\RedisSessionFactory;
use \Phata\TeleCore\Dispatcher as UpdateDispatcher;

require_once __DIR__ . '/' . basename(__FILE__, '.spec.php') . '.dependencies.php';

describe('Dispatcher', function () {
    it('correctly dispatch handler', function () {
        $store = (object) [];
        $updateDispatcher = new UpdateDispatcher();
        $updateDispatcher->setLogger($this->logger);
        $updateDispatcher->setSessionFactory(new RedisSessionFactory($this->redisClient));
        $testHandler = function ($type, $request) use ($store) {
            $store->type = $type;
            $store->request = $request;
        };
        $updateDispatcher->addHandler('inline_query', $testHandler);
        $routeInfo = $updateDispatcher->dispatch(json_decode(json_encode([
            'inline_query' => [
                'hello' => 'world',
            ],
        ])));

        // get routing information
        list($handler, $args) = $routeInfo;

        // test route result
        expect($handler)->toBe($testHandler);
        expect($args[0])->toBe('inline_query');
        expect(isset($args[1]->inline_query))->toBeTruthy();
        expect(isset($args[1]->inline_query->hello))->toBeTruthy();
        expect($args[1]->inline_query->hello)->toBe('world');

        // test run result
        $handler(...$args);
        expect($store->type)->toBe('inline_query');
        expect(isset($store->request))->toBeTruthy();
        expect(isset($store->request->inline_query))->toBeTruthy();
        expect(isset($store->request->inline_query->hello))->toBeTruthy();
        if (
            isset($store->request)
            && isset($store->request->inline_query)
            && isset($store->request->inline_query->hello)
        ) {
            expect($store->request->inline_query->hello)->toBe('world');
        }
    });

    it('correctly dispatch command handler', function () {
        $store = (object) [];
        $updateDispatcher = new UpdateDispatcher();
        $updateDispatcher->setLogger($this->logger);
        $updateDispatcher->setSessionFactory(new RedisSessionFactory($this->redisClient));
        $testHandler = function ($command, $request) use ($store) {
        };
        $updateDispatcher->addCommand('/hello', $testHandler);
        $routeInfo = $updateDispatcher->dispatch(json_decode(json_encode([
            'message' => [
                'from' => ['id' => 123],
                'chat' => ['id' => 456],
                'entities' => [
                    'type' => 'bot_command',
                    'offset' => 0,
                    'length' => 6,
                ],
                'text' => '/hello world',
            ],
        ])));

        // get routing information
        list($handler, $args) = $routeInfo;

        // inspect callabke
        expect(is_array($handler))->toBeTruthy();
        if (!is_array($handler)) {
            return;
        }

        expect(sizeof($handler))->toBe(2);
        if (sizeof($handler) !== 2) {
            return;
        }

        expect($handler[0])->toBe($updateDispatcher);
        if ($handler[0] != $updateDispatcher) {
            return;
        }

        expect($handler[1])->toBe('handleCommandMessage');
        $handler(...$args);

    });

    it('correctly reflects dependencies of function callables', function () {

        $container = new myTest\Dummy\Container([
            'foo' => 'Foo',
            'bar' => 'Bar',
            myTest\Dummy\DummyVar::class => new myTest\Dummy\DummyVar('Dummy'),
        ]);
        $params = UpdateDispatcher::reflectDependencies($container, function ($foo, $bar, myTest\Dummy\DummyVar $dummy) {
            $vars['foo'] = $foo;
            $vars['bar'] = $bar;
            $vars['dummy'] = $dummy;
        });

        expect($params[0])->toBe('Foo');
        expect($params[1])->toBe('Bar');
        expect($params[2]->get())->toBe('Dummy');

    });

});
