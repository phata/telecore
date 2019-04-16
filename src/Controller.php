<?php

namespace Phata\TeleCore;

use Psr\Log\LoggerInterface;

class Controller
{

    private $logger;
    private $dispatcher;

    /**
     * Constructor function.
     *
     * @param LoggerInterface $logger
     * @param Dispatcher $dispatcher
     */
    public function __construct(
        LoggerInterface $logger,
        Dispatcher $dispatcher
    ) {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Execute POST requests for the webhook callback.
     *
     * @return void
     */
    public function execute()
    {
        // get input stream and log
        $stream = fopen('php://input', 'r');

        // TODO: some error handling
        if ($stream === false) {
            return;
        }
        $content = stream_get_contents($stream);
        $request = json_decode($content);

        // DEBUG: log
        $this->logger->debug('request: {request}', ['request' => json_encode($request)]);

        // dispatch decoded request
        try {
            $dispatchInfo = $this->dispatcher->dispatch($request);
        } catch (\Exception $e) {
            $this->logger->error('error: {error}', ['error' => $e->getMessage()]);
        }

        // if there is a handler, handle it.
        if ($dispatchInfo != null) {
            // TODO: rewrite this as middleware.
            $this->logger->debug('dispatch info: {info}', ['info' => json_encode($dispatchInfo)]);
            list($handler, $args) = $dispatchInfo;
            $handler(...$args);
            return;
        }

        // TODO: some kind of logging for these strange cases.
        $this->logger->debug('no handler found for dispatching info found');
        return;
    }
}
