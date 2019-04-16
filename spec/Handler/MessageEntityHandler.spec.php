<?php

use \Phata\TeleCore\Session\RedisSessionFactory;
use \Phata\TeleCore\Handler\MessageEntityHandler;

require_once __DIR__ . '/../Dispatcher.spec/Container.php';
require_once __DIR__ . '/../Dispatcher.spec/DummyVar.php';
require_once __DIR__ . '/../Dispatcher.spec/DummyClass.php';

describe('MessageEntityHandler', function () {

    it('correctly dispatch bot_command', function () {
        $store = [];
        $container = new myTest\Dummy\Container();

        // declare dispatcher with at testHandler.
        $dispatcher = new MessageEntityHandler(
            $container,
            $this->logger,
            'bot_command',
            '/'
        );
        $testHandler = function ($command, $request) use (&$store) {
            $store = ['text' => $request->message->text];
        };
        $dispatcher->setHandler('/hello', $testHandler);

        // create route info from dispatcher.
        $routeInfo = $dispatcher->dispatch(
            (object) [
                // bare minimal bot command for this test to work.
                'offset' => 0,
                'length' => 6,
            ],
            json_decode(json_encode([
                'message' => [
                    // bare minimal message object for this test to work.
                    'text' => '/hello world',
                ],
            ]))
        );

        // get routing information
        list($handler, $args) = $routeInfo;
        expect($handler)->toBe($testHandler);

        // run the handler and check the side effect.
        $handler(...$args);
        expect($store['text'])->toBe('/hello world');
    });

    it('correctly dispatch mention', function () {
        $store = [];
        $container = new myTest\Dummy\Container();

        // declare dispatcher with at testHandler.
        $dispatcher = new MessageEntityHandler(
            $container,
            $this->logger,
            'mention',
            '@'
        );
        $testHandler = function ($command, $request) use (&$store) {
            $store = ['text' => $request->message->text];
        };
        $dispatcher->setHandler('@hello', $testHandler);

        // create route info from dispatcher.
        $routeInfo = $dispatcher->dispatch(
            (object) [
                // bare minimal bot command for this test to work.
                'offset' => 0,
                'length' => 6,
            ],
            json_decode(json_encode([
                'message' => [
                    // bare minimal message object for this test to work.
                    'text' => '@hello world',
                ],
            ]))
        );

        // get routing information
        list($handler, $args) = $routeInfo;
        expect($handler)->toBe($testHandler);

        // run the handler and check the side effect.
        $handler(...$args);
        expect($store['text'])->toBe('@hello world');
    });

    it('correctly dispatch mention with default handler', function () {
        $store = [];
        $container = new myTest\Dummy\Container();

        // declare dispatcher with at testHandler.
        $dispatcher = new MessageEntityHandler(
            $container,
            $this->logger,
            'mention',
            '@'
        );
        $testHandler1 = function ($command, $request) use (&$store) {
            $store['request1'] = ['text' => $request->message->text, 'command' => $command];
        };
        $testHandler2 = function ($command, $request) use (&$store) {
            $store['request2'] = ['text' => $request->message->text, 'command' => $command];
        };
        $dispatcher
            ->setDefaultHandler($testHandler1)
            ->setHandler('@foo', $testHandler2);

        // create route info from dispatcher.
        $routeInfo = $dispatcher->dispatch(
            (object) [
                // bare minimal bot command for this test to work.
                'offset' => 0,
                'length' => 4,
            ],
            json_decode(json_encode([
                'message' => [
                    // bare minimal message object for this test to work.
                    'text' => '@hey world',
                ],
            ]))
        );

        // get routing information
        list($handler, $args) = $routeInfo;
        expect($handler)->toBe($testHandler1);

        // run the handler and check the side effect.
        $handler(...$args);
        expect($store['request1']['text'])->toBe('@hey world');

        // create route info from dispatcher.
        $routeInfo = $dispatcher->dispatch(
            (object) [
                // bare minimal bot command for this test to work.
                'offset' => 0,
                'length' => 4,
            ],
            json_decode(json_encode([
                'message' => [
                    // bare minimal message object for this test to work.
                    'text' => '@foo bar',
                ],
            ]))
        );

        // get routing information
        list($handler, $args) = $routeInfo;
        expect($handler)->toBe($testHandler2);

        // run the handler and check the side effect.
        $handler(...$args);
        expect($store['request2']['text'])->toBe('@foo bar');
    });
});
