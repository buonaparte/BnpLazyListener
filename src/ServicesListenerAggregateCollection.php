<?php

namespace BnpLazyListener;

use BnpLazyListener\Exception\RuntimeException;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ServicesListenerAggregateCollection implements
    ListenerAggregateInterface,
    ServiceLocatorAwareInterface
{
    /**
     * @var array
     */
    protected $delegates;

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var bool
     */
    protected $frozen = false;

    public function __construct(array $delegates = array())
    {
        $this->delegates = $delegates;
    }

    protected function checkIsFrozen($method)
    {
        if ($this->frozen) {
            throw new RuntimeException(sprintf('Could not %s, because collection is frozen', $method));
        }
    }

    /**
     * @param array $delegates
     *
     * @throws RuntimeException
     */
    public function setDelegates(array $delegates)
    {
        $this->checkIsFrozen(__METHOD__);
        $this->delegates = $delegates;
    }

    public function addDelegate($delegate)
    {
        $this->checkIsFrozen(__METHOD__);
        $this->delegates[] = $delegate;
    }

    protected function freeze()
    {
        $this->frozen = true;
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
     * @throws RuntimeException
     */
    public function attach(EventManagerInterface $events)
    {
        $self = $this;
        $canCreateListenerFromFactory = function (&$listener) use ($self) {
            if (is_callable($listener)) {
                $listener = call_user_func($listener, $self->getServiceLocator());
                return true;
            }

            if (is_string($listener) && class_exists($listener)
                && in_array('Zend\EventManager\ListenerAggregateInterface', class_implements($listener))
            ) {
                $listener = new $listener;
            } elseif (is_string($listener) && class_exists($listener)
                && in_array('Zend\ServiceManager\FactoryInterface', class_implements($listener))
            ) {
                $listener = new $listener;
            }

            if ($listener instanceof FactoryInterface) {
                $listener = $listener->createService($self->getServiceLocator());
                return true;
            }

            if (is_string($listener)) {
                $listener = $self->getServiceLocator()->get($listener);
                return true;
            }

            return false;
        };

        foreach ($this->delegates as $i => $delegate) {
            // LazyListenerAggregate ?
            if (! $canCreateListenerFromFactory($delegate) && is_array($delegate) && 2 === count($delegate)) {
                $factory = array_shift($delegate);
                $subscribedEvents = array_shift($delegate);

                $delegate = new LazyListenerAggregate(
                    function () use ($canCreateListenerFromFactory, $factory, $i) {
                        if (! $canCreateListenerFromFactory($factory)) {
                            throw new RuntimeException(sprintf(
                                'Collection delegate at index %d has invalid factory for LazyListenerAggregate',
                                $i + 1
                            ));
                        }

                        return $factory;
                    },
                    $subscribedEvents
                );
            }

            if (! $delegate instanceof ListenerAggregateInterface) {
                throw new RuntimeException(sprintf(
                    'Collection delegate at index %d have not resolved to a valid Listener Aggregate',
                    $i + 1
                ));
            }

            $this->delegates[$i] = $delegate;
            $events->attachAggregate($delegate);
        }

        $this->freeze();
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
        foreach ($this->delegates as $delegate) {
            if ($delegate instanceof ListenerAggregateInterface) {
                $events->detachAggregate($delegate);
            }
        }

        $this->frozen = false;
    }

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->services;
    }
}
