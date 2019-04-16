<?php

use \Phata\TeleCore\Session\RedisSessionFactory;
use \Phata\TeleCore\Dispatcher as UpdateDispatcher;

require_once __DIR__ . '/' . basename(__FILE__, '.php') . '/Container.php';
require_once __DIR__ . '/' . basename(__FILE__, '.php') . '/DummyVar.php';
require_once __DIR__ . '/' . basename(__FILE__, '.php') . '/DummyClass.php';

describe('Dispatcher', function () {
    it('correctly dispatch handler', function () {
        $store = (object) [];
        $updateDispatcher = new UpdateDispatcher(
            new myTest\Dummy\Container(),
            $this->logger,
            new RedisSessionFactory($this->redisClient)
        );
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
        if (isset($store->request)
            && isset($store->request->inline_query)
            && isset($store->request->inline_query->hello)
        ) {
            expect($store->request->inline_query->hello)->toBe('world');
        }
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

    it('correctly reflects dependencies of object instance method callables', function () {

        $container = new myTest\Dummy\Container([
            'foo' => 'Foo',
            'bar' => 'Bar',
            myTest\Dummy\DummyVar::class => new myTest\Dummy\DummyVar('Dummy'),
        ]);
        $obj = new class{
            public function method($foo, $bar, myTest\Dummy\DummyVar $dummy)
            {
                $vars['foo'] = $foo;
                $vars['bar'] = $bar;
                $vars['dummy'] = $dummy;
            }
        };
        $params = UpdateDispatcher::reflectDependencies($container, [$obj, 'method']);

        expect($params[0])->toBe('Foo');
        expect($params[1])->toBe('Bar');
        expect($params[2]->get())->toBe('Dummy');
    });

    it('correctly reflects dependencies of class static method callables', function () {

        $container = new myTest\Dummy\Container([
            'foo' => 'Foo',
            'bar' => 'Bar',
            myTest\Dummy\DummyVar::class => new myTest\Dummy\DummyVar('Dummy'),
        ]);
        $params = UpdateDispatcher::reflectDependencies($container, [myTest\Dummy\DummyClass::class, 'method']);

        expect($params[0])->toBe('Foo');
        expect($params[1])->toBe('Bar');
        expect($params[2]->get())->toBe('Dummy');
    });

    it('correctly reflects dependencies of class static method callables with overrides', function () {

        $params = UpdateDispatcher::reflectDependencies(
            new myTest\Dummy\Container(),
            [myTest\Dummy\DummyClass::class, 'method'],
            [
                'foo' => 'Foo',
                'bar' => 'Bar',
                myTest\Dummy\DummyVar::class => new myTest\Dummy\DummyVar('Dummy'),
            ]
        );

        expect($params[0])->toBe('Foo');
        expect($params[1])->toBe('Bar');
        expect($params[2]->get())->toBe('Dummy');
    });
});
