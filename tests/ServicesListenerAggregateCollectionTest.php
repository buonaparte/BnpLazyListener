<?php

namespace BnpLazyListener;

use BnpLazyListener\Mock\ListenerAggregate;
use BnpLazyListener\Mock\ListenerAggregateFactory;
use BnpLazyListener\Mock\PlainListener;
use BnpLazyListener\Mock\PlainListenerFactory;
use Zend\EventManager\Event;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use BnpLazyListener\Exception\RuntimeException;

class ServicesListenerAggregateCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    protected function setUp()
    {
        $this->events = new EventManager();
    }

    public function testNotAllowsAddDelegateAfterAttach()
    {
        $listener = new ServicesListenerAggregateCollection(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('foo' => 'onFoo')
            )
        ));

        $listener->addDelegate(new LazyListenerAggregate(
            function () {
                return new PlainListener();
            },
            array('bar' => 'onBar')
        ));
        $this->events->attach($listener);

        $this->setExpectedException(
            'BnpLazyListener\Exception\RuntimeException',
            'Could not BnpLazyListener\ServicesListenerAggregateCollection::addDelegate, because collection is frozen'
        );
        $listener->addDelegate('a_delegate');
    }

    public function testNotAllowsSetDelegatesAfterAttach()
    {
        $listener = new ServicesListenerAggregateCollection(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('foo' => 'onFoo')
            )
        ));

        $listener->setDelegates(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('bar' => 'onBar')
            )
        ));
        $this->events->attach($listener);

        $this->setExpectedException(
            'BnpLazyListener\Exception\RuntimeException',
            'Could not BnpLazyListener\ServicesListenerAggregateCollection::setDelegates, because collection is frozen'
        );
        $listener->setDelegates(array('a_delegate'));
    }

    public function testIsNotFrozenAfterDetach()
    {
        $listener = new ServicesListenerAggregateCollection(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('foo' => 'onFoo')
            )
        ));

        $this->events->attach($listener);
        $this->events->detach($listener);

        $listener->addDelegate(new LazyListenerAggregate(
            function () {
                return new PlainListener();
            },
            array('bar' => 'onBar')
        ));
        $listener->setDelegates(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('baz' => 'onBaz')
            )
        ));
    }

    public function testDetachesDelegates()
    {
        $listener = new ServicesListenerAggregateCollection(array(
            new LazyListenerAggregate(
                function () {
                    return new PlainListener();
                },
                array('foo' => 'onFoo')
            )
        ));

        $event = new Event('foo');
        $this->events->attach($listener);
        $this->events->trigger($event);
        $this->assertEquals('foo', $event->getParam('foo'));

        $event->setParam('foo', null);
        $this->events->detach($listener);
        $this->events->trigger($event);

        $this->assertNull($event->getParam('foo'));
    }

    public function validDelegatesDefinitionProvider()
    {
        $plainListenerSubscribedEvents = array(
            'foo' => 'onFoo',
            'bar' => 'onBar',
            'baz' => 'onBaz'
        );

        return array(
            array(array('listener')),
            array(array('BnpLazyListener\Mock\ListenerAggregateFactory')),
            array(array(new ListenerAggregateFactory())),
            array(array(
                function () {
                    return new ListenerAggregate();
                }
            )),
            array(array(
                array('plain_listener', $plainListenerSubscribedEvents)
            )),
            array(array(
                array('BnpLazyListener\Mock\PlainListenerFactory', $plainListenerSubscribedEvents)
            )),
            array(array(
                array(new PlainListenerFactory(), $plainListenerSubscribedEvents)
            )),
            array(array(
                array(
                    function (ServiceLocatorInterface $sm) {
                        return $sm->get('plain_listener');
                    },
                    $plainListenerSubscribedEvents
                )
            ))
        );
    }

    /**
     * @param array $delegates
     * @dataProvider validDelegatesDefinitionProvider
     */
    public function testMultipleDelegatesDeclarations(array $delegates)
    {
        $services = new ServiceManager();
        $services->setService('listener', new ListenerAggregate());
        $services->setService('plain_listener', new PlainListener());

        $listener = new ServicesListenerAggregateCollection($delegates);
        $listener->setServiceLocator($services);

        $this->events->attach($listener);

        $event = new Event('foo');
        $this->events->trigger($event);
        $this->assertEquals('foo', $event->getParam('foo'));

        $event = new Event('bar');
        $this->events->trigger($event);
        $this->assertEquals('bar', $event->getParam('foo'));

        $event = new Event('baz');
        $this->events->trigger($event);
        $this->assertEquals('baz', $event->getParam('foo'));
    }

    public function invalidLazyAggregateListenerSpecificationsProvider()
    {
        return array(
            array(
                array(new PlainListenerFactory(), array())
            ),
            array(
                array(new PlainListenerFactory(), 'not_an_array')
            )
        );
    }

    /**
     * @param array $delegates
     * @dataProvider invalidLazyAggregateListenerSpecificationsProvider
     * @expectedException RuntimeException
     */
    public function testWillThrowExceptionWhenLazyAggregateDelegateHasInvalidListenerSpecifications(array $delegates)
    {
        $listener = new ServicesListenerAggregateCollection($delegates);
        $listener->setServiceLocator(new ServiceManager());

        $this->events->attach($listener);
    }

    public function testWillThrowLazyExceptionOnInvalidLazyFactory()
    {
        $listener = new ServicesListenerAggregateCollection(array(array(1, array('foo' => 'onFoo'))));
        $listener->setServiceLocator(new ServiceManager());

        $this->events->attach($listener);

        $this->setExpectedException('RuntimeException');
        $this->events->trigger(new Event('foo'));
    }
}
