<?php

use \Phata\TeleCore\Session\RedisSessionFactory;
use \Phata\TeleCore\Handler\MessageHandlerChain;
use \Phata\TeleCore\Handler\MessageHandlerInterface;

describe('MessageHandlerChain', function () {

    it('correctly handleMessage with the inner handlers', function () {
        $handler1 = new class implements MessageHandlerInterface
        {
            public function handleMessage($request): bool
            {
                if (isset($request->type) && ($request->type === 'type1')) {
                    $this->text = $request->text;
                    return true;
                }
                return false;
            }
        };
        $handler2 = new class implements MessageHandlerInterface
        {
            public function handleMessage($request): bool
            {
                if (isset($request->type) && ($request->type === 'type2')) {
                    $this->text = $request->text;
                    return true;
                }
                return false;
            }
        };

        $chain = (new MessageHandlerChain)
            ->addHandler($handler1)
            ->addHandler($handler2);

        $chain->handleMessage((object) [
            'type' => 'type1',
            'text' => 'result 1',
        ]);
        $chain->handleMessage((object) [
            'type' => 'type2',
            'text' => 'result 2',
        ]);

        expect($handler1->text)->toBe('result 1');
        expect($handler2->text)->toBe('result 2');
    });
});
