<?php

namespace BnpLazyListener;

use BnpLazyListener\Exception\InvalidArgumentException;
use BnpLazyListener\Exception\RuntimeException;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Stdlib\CallbackHandler;

class LazyListenerAggregate implements ListenerAggregateInterface
{
    /**
     * @var callable
     */
    protected $listenerFactory;

    /**
     * @var array
     */
    protected $subscribedEvents;

    /**
     * @var array
     */
    protected $listeners = array();

    /**
     * @var object
     */
    protected $listener;

    public function __construct($listenerFactory, array $subscribedEvents)
    {
        $this->listenerFactory = $listenerFactory;
        $this->subscribedEvents = $subscribedEvents;
    }

    protected function getSubscribedEvents()
    {
        return $this->subscribedEvents;
    }

    /**
     * @return object
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     */
    protected function getListener()
    {
        if (null === $this->listener) {
            if (! is_callable($this->listenerFactory)) {
                throw new InvalidArgumentException(sprintf(
                    '%s must receive a valid callable as listener factory, %s provided',
                    get_class($this),
                    gettype($this->listenerFactory)
                ));
            }

            $this->listener = call_user_func($this->listenerFactory);
        }

        if (! is_object($this->listener)) {
            throw new RuntimeException('Factory has not returned a valid object listener');
        }

        return $this->listener;
    }

    public function handleEvent(array $eventArgs, $listenerMethod)
    {
        $listener = $this->getListener();

        if (! method_exists($listener, $listenerMethod)) {
            throw new RuntimeException(sprintf(
                'Invalid listener %s:%s (method does not exist)',
                get_class($listener),
                $listenerMethod
            ));
        }

        return call_user_func_array(array($listener, $listenerMethod), $eventArgs);
    }

    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param EventManagerInterface $events
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function attach(EventManagerInterface $events)
    {
        foreach ($this->getSubscribedEvents() as $eventName => $listeners) {
            // 'event' => 'listener'
            if (is_string($listeners)) {
                $listeners = array($listeners);
            }

            if (! is_array($listeners) || empty($listeners)) {
                throw new InvalidArgumentException(sprintf(
                    '%s:%s must return either an not empty array or string as listener, %s provided',
                    get_class($this),
                    'getSubscribedEvents',
                    is_object($listeners) ? get_class((object) $listeners) : gettype($listeners)
                ));
            }

            // 'event' => ['listener']
            // 'event' => ['listener', $priority]
            $keys = array_keys($listeners);
            if (1 == count($listeners) || 2 == count($listeners) && is_numeric($listeners[$keys[1]])) {
                $listeners = array($listeners);
            }

            // now we have normalized listeners
            $self = $this;
            foreach ($listeners as $listener) {
                if (is_string($listener)) {
                    $listener = array($listener);
                }

                if (! is_array($listener)) {
                    throw new InvalidArgumentException(sprintf(
                        'Each %s:%s listener must be either an not empty array or string as listener, %s provided',
                        get_class($this),
                        'getSubscribedEvents',
                        is_object($listener) ? get_class((object) $listener) : gettype($listener)
                    ));
                }

                $method = array_shift($listener);
                $priority = array_shift($listener);

                $callback = function () use ($self, $method) {
                    $args = func_get_args();
                    return $self->handleEvent($args, $method);
                };

                $this->listeners[] = $events->attach(
                    $eventName,
                    $callback,
                    null === $priority ? 1 : (int) $priority
                );
            }
        }
    }

    /**
     * Detach all previously attached listeners
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $i => $listener) {
            if ($listener instanceof CallbackHandler) {
                $events->detach($listener);
                unset($this->listeners[$i]);
            }
        }
    }
}
