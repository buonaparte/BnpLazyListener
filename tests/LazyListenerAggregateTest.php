<?php

namespace BnpLazyListener;

use BnpLazyListener\Exception\InvalidArgumentException;
use BnpLazyListener\Mock\PlainListener;
use Zend\EventManager\Event;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;

class LazyListenerAggregateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var callable
     */
    protected $plainListenerFactory;

    protected function setUp()
    {
        $this->events = new EventManager();
        $this->plainListenerFactory = function () {
            return new PlainListener();
        };
    }

    public function testFactoryMustBeValidCallable()
    {
        $this->events->attach($listener = new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => 'onFoo'
            )
        ));

        $this->assertInstanceOf('Zend\EventManager\ListenerAggregateInterface', $listener);

        $this->events->trigger($event = new Event('foo'));
        $this->assertEquals('foo', $event->getParam('foo'));

        $this->events->attach($listener = new LazyListenerAggregate('not_a_callable', array('foo' => 'aMethod')));
        $this->setExpectedException('BnpLazyListener\Exception\InvalidArgumentException');
        $this->events->trigger('foo');
    }

    public function testFactoryMustReturnAnObjectAsListener()
    {
        $this->events->attach(new LazyListenerAggregate(
            function () {
                return 'not_an_object';
            },
            array(
                'foo' => 'anObjectMethod'
            )
        ));

        $this->setExpectedException('BnpLazyListener\Exception\RuntimeException');
        $this->events->trigger('foo');
    }

    public function invalidEventSpecificationsProvider()
    {
        return array(
            array(
                array('foo' => new \ArrayObject())
            ),
            array(
                array(
                    'foo' => array(
                        new \ArrayObject()
                    )
                )
            ),
            array(
                array(
                    'foo' => array(new \ArrayObject(), 1, null)
                )
            )
        );
    }

    /**
     * @param array $specs
     * @dataProvider invalidEventSpecificationsProvider
     * @expectedException InvalidArgumentException
     */
    public function testWillThrowExceptionOnInvalidEventSpecification(array $specs)
    {
        $this->events->attach(new LazyListenerAggregate($this->plainListenerFactory, $specs));
        $this->events->trigger(new Event('foo'));
    }

    public function testDeclaredListenerMustExistAsInstanceMethodOnCreateObject()
    {
        $this->events->attach(new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => 'onFoo',
                'bar' => 'methodDoesNotExist'
            )
        ));

        $this->events->trigger('foo');

        $this->setExpectedException('BnpLazyListener\Exception\RuntimeException');
        $this->events->trigger('bar');
    }

    public function testDeclaringSingleListenerMethodWithPriority()
    {
        $this->events->attach(new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => array('onFoo', -100)
            )
        ));
        $this->events->attach(
            'foo',
            function (EventInterface $e) {
                $e->setParam('foo', 'bar');
            }
        );

        $this->events->trigger($event = new Event('foo'));
        $this->assertEquals('foo', $event->getParam('foo'));
    }

    public function testDeclaringMultipleListenerMethods()
    {
        $this->events->attach(new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => array(
                    'onFoo',
                    'onBar'
                )
            )
        ));

        $this->events->trigger($event = new Event('foo'));
        $this->assertEquals('bar', $event->getParam('foo'));
    }

    public function testDeclaringMultipleListenerMethodsWithPriority()
    {
        $this->events->attach(new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => array(
                    array('onFoo', -100),
                    array('onBar')
                )
            )
        ));

        $this->events->trigger($event = new Event('foo'));
        $this->assertEquals('foo', $event->getParam('foo'));
    }

    public function testListensToMultipleEvents()
    {
        $this->events->attach(new LazyListenerAggregate(
            $this->plainListenerFactory,
            array(
                'foo' => 'onFoo',
                'bar' => array('onBar', -100),
                'baz' => array('onBaz')
            )
        ));

        $event = new Event('foo');
        $this->events->trigger($event);

        $event->setName('bar');
        $this->events->trigger($event);

        $event->setName('baz');
        $this->events->trigger($event);

        $this->assertEquals('baz', $event->getParam('foo'));
    }

    public function testInternalListenerGetsCached()
    {
        $once = false;
        $this->events->attach(new LazyListenerAggregate(
            function () use (&$once) {
                if ($once) {
                    throw new \RuntimeException();
                }

                $once = true;
                return new PlainListener();
            },
            array(
                'foo' => 'onFoo',
                'bar' => 'onBar'
            )
        ));

        $this->events->trigger(new Event('foo'));
        $this->events->trigger(new Event('bar'));
    }
}
