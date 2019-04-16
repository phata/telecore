<?php

namespace Phata\TeleCore\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Phata\TeleCore\Dispatcher;

/**
 * Handler of message type updates.
 */
class MessageEntityHandler implements MessageHandlerInterface
{
    private $container = null;
    private $logger = null;
    private $entityType = null;
    private $entityPrefix = '';
    private $defaultHandler = null;
    private $handlers = [];

    /**
     * Constructor function.
     *
     * @param ContainerInterface $container
     *     Container for dependency injection.
     * @param LoggerInterface $logger
     *     Logger for logging dispatch.
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        string $entityType,
        string $entityPrefix = ''
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->entityType = $entityType;
        $this->$entityPrefix = $entityPrefix;
    }

    /**
     * Add a handler specific message entity of the entity type.
     *
     * @param string $entityStr
     *     The message entity, with or without slash prefix, for routing.
     * @param callable $handler
     *     Handler function for the message entity. With a function signature of:
     *     function (string $entityStr, object $request)
     *
     * @return self
     */
    public function setHandler(string $entityStr, callable $handler)
    {
        $entityStr = $this->entityPrefix . ltrim($entityStr, $this->entityPrefix);
        if (isset($this->handlers[$entityStr])) {
            throw new \Exception(sprintf('handler for entity "%s" already exists.', $entityStr));
            return;
        }
        $this->handlers[$entityStr] = $handler;
        return $this;
    }

    /**
     * Add a default handler for the given message entity type.
     *
     * @param callable $handler
     *     Handler function for the message entity. With a function signature of:
     *     function (string $entityStr, object $request)
     *
     * @return self
     */
    public function setDefaultHandler(callable $handler)
    {
        $this->defaultHandler = $handler;
        return $this;
    }

    /**
     * Get message entities.
     *
     * @param string $entityType
     *     The type of the message entity.
     * @param object $message
     *     The message object.
     *
     * @return array
     *     Array of message entities.
     */
    public static function getMessageEntities($entityType, $message): array
    {
        if (!isset($message->entities) || !is_array($message->entities)) {
            return [];
        }
        return array_reduce(
            $message->entities,
            function ($carry, $item) use ($entityType) {
                if ($item->type === $entityType && ($item->offset == 0)) {
                    $carry[] = $item;
                }
                return $carry;
            },
            []
        );
    }

    /**
     * Dispatch message entities with handlers stored in this class.
     * Meant to be used by handleMessage.
     *
     * Expects container to have a variable to the
     * key 'request' for retrieving the message entity string.
     *
     * @param object $messageEntity
     *     The message entity object to dispatch.
     * @param object $request
     *     The request object to dispatch with.
     *
     * @return array
     *     The message entity handler and the message entity information
     */
    public function dispatch($messageEntity, $request): array
    {
        if ($this->container === null) {
            // should throw new exception
            throw new \Exception('Container not found.');
        }

        // Fill the container with variables about the
        // request.
        $this->container->set('messageEntity', $messageEntity);

        // parse the message entity string
        $entityStr = substr(
            $request->message->text,
            $messageEntity->offset,
            $messageEntity->length
        );
        $this->container->set('messageEntityStr', $entityStr);

        // if message entity handler found for the given message entity,
        // dispatch the handler.
        if (isset($this->handlers[$entityStr])) {
            $params = Dispatcher::reflectDependencies(
                $this->container,
                $this->handlers[$entityStr],
                ['request' => $request]
            );
            $this->logger->debug('message entity handler found');
            return [
                $this->handlers[$entityStr],
                $params,
            ];
        } elseif ($this->defaultHandler !== null) {
            $params = Dispatcher::reflectDependencies(
                $this->container,
                $this->defaultHandler,
                ['request' => $request]
            );
            $this->logger->debug('default message entity handler found');
            return [
                $this->defaultHandler,
                $params,
            ];
        }

        // no message entity handler found for the given message entity string.
        $this->logger->debug('message entity handler not found: ' . $entityStr);
        throw new \Exception('message entity handler not found: ' . $entityStr);
        return [null, []]; // return an array for list to extract anyway
    }

    /**
     * {@inheritDoc}
     */
    public function handleMessage($request): bool
    {
        // parse message entities, if any
        $entities = static::getMessageEntities($this->entityType, $request->message);
        if (!empty($entities)) {
            // dispatch message entity handler for the message, if any
            list($handler, $params) = $this->dispatch($entities[0], $request);
            if ($handler !== null) {
                $handler(...$params);
                return true;
            }
        }
        return false;
    }
}
