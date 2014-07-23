<?php

namespace BnpLazyListener\Mock;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;

class ListenerAggregate extends AbstractListenerAggregate
{
    public function onFoo(EventInterface $e)
    {
        $e->setParam('foo', 'foo');
    }

    public function onBar(EventInterface $e)
    {
        $e->setParam('foo', 'bar');
    }

    public function onBaz(EventInterface $e)
    {
        $e->setParam('foo', 'baz');
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
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('foo', array($this, 'onFoo'));
        $this->listeners[] = $events->attach('bar', array($this, 'onBar'));
        $this->listeners[] = $events->attach('baz', array($this, 'onBaz'));
    }
}
