<?php

namespace BnpLazyListener\Mock;

use Zend\EventManager\EventInterface;

class PlainListener
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
}
