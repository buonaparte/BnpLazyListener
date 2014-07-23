BnpLazyListener
===============

[![Build Status](https://travis-ci.org/buonaparte/BnpLazyListener.svg?branch=master)](https://travis-ci.org/buonaparte/BnpLazyListener)
[![Coverage Status](https://img.shields.io/coveralls/buonaparte/BnpLazyListener.svg)](https://coveralls.io/r/buonaparte/BnpLazyListener?branch=master)
[![Latest Stable Version](https://poser.pugx.org/buonaparte/bnp-lazy-listener/v/stable.svg)](https://packagist.org/packages/buonaparte/bnp-lazy-listener)
[![Latest Unstable Version](https://poser.pugx.org/buonaparte/bnp-lazy-listener/v/unstable.svg)](https://packagist.org/packages/buonaparte/bnp-lazy-listener)

This library allows you to attach Lazy Listeners to a ZF2 EventManager.

Installation
------------

### Setup
1. Add this project to your composer.json:

    ```json
    "require": {
        "buonaparte/bnp-lazy-listener": "0.9.*"
    }
    ```

2. Now tell composer to download BnpServiceDefinition by running the command:

    ```bash
    $ php composer.phar update
    ```

**!!! Notice** this is not a Module, so you do not have to enable it in a ZF2 Application, moreover, you can use it
with the `EventManager` decoupled component.

LazyListenerAggregate
---------------------

There are many cases when instantiating a listener aggregate may be too expensive, especially when that listener will
be never triggered, `LazyListenerAggregate` makes it possible to define a factory for your real listener, as well as
specifications for which events the future listener is subscribed, `LazyListenerAggregate` will then instantiate your
listener when the first of subscribed is being triggered.

```php
class PlainPhpObjectWithDependencies
{
    // Your Dependencies

    public function __construct($aDependency, $anotherDependency)
    {
        // ...
    }

    public function setExtraDependency($dependency)
    {
        // ...
    }

    public function onFoo(Zend\EventManager\EventInterface $e)
    {
        // do some complex stuff ...
        $e->setParam('foo', 'bar');
    }

    public function onBar(Zend\EventManager\EventInterface $e)
    {
        // do some complex stuff ...
        $e->setParam('bar', 'baz');
    }

    public function onBaz(Zend\EventManager\EventInterface $e)
    {
        // do some complex stuff ...
        $e->setParam('baz', array_merge($e->getParam('baz', array()), array('element'));
    }
}
```

Now you can attach this PPO to the `EventManager`:

```php
use Zend\EventManager\EventManager;
use Zend\EventManager\Event;
use BnpLazyListener\LazyListenerAggregate;

$events = new EventManager();

$events->attach(new LazyListenerAggregate(
    function () use ($aDependency, $anotherDependency, $dependency) {
        $listener = new PlainPhpObjectWithDependencies($aDependency, $anotherDependency);
        $listener->setExtraDependency($dependency);

        return $listener;
    },
    array(
        'foo' => 'onFoo',
        'bar' => array('onBar', 1000),
        'baz' => array(
            'onBaz',
            array('onBaz', -99)
        )
    )
));

$events->trigger(new Event('an_event'));

// PlainPhpObjectWithDependencies gets instantiated only now
$events->trigger(new Event('foo'));
```

We've used `\Closure` here as factory, `LazyListenerAggregate` however, accepts any valid `callable`. As you can already
see this does not bring much flexibility, as we wrap statically all our listener dependencies, here is where the second
utility listener comes in handy.

ServicesListenerAggregateCollection
-----------------------------------

This is a `ServiceLocatorAware` service that is able to aggregate a collection of other `ListenerAggregate`s, from which,
the most important one is that described above.
All atom listeners are called delegates and can be represented as:

* `callable` - a callable to invoke to instantiate the delegate, optionally a `ServiceLocatorInterface`
is passed as the only argument
* `string` (a valid FQ class name implementing `Zend\EventManager\ListenerAggregateInterface`)
* `string` (a valid FQ class name implementing `Zend\ServiceManager\FactoryInterface`)
* `Zend\ServiceManager\FactoryInterface` instance
* `string` - not matching a factory class name - will make `ServicesListenerAggregateCollection` to pull the delegate
as a service from currently injected locator
* `array` - containing 2 elements, the first one - any of above, to use as a lazy factory for the `LazyListenerAggregate`
and the second - an array defining the events being listened to, the second constructor argument for the `LazyListenerAggregate`

We can now define a bunch of listeners at a time using only a configuration array:

```php
use Zend\ServiceManager\ServiceManager;
use Zend\EventManager\EventManager;
use BnpLazyListener\ServicesListenerAggregateCollection;

$delegates = array(
    'a_listener_aggregate_service',
    'MyApp\Factory\ListenerAggregateFactoryService',
    array(
        'PlainPhpObjectWithDependencies',
        array(
            'foo' => 'onFoo',
            'bar' => array('onBar', 1000),
            'baz' => array(
                'onBaz',
                array('onBaz', -99)
            )
        )
    )
);

$services = new ServiceManager();
// declare your a_listener_aggregate_service and PlainPhpObjectWithDependencies services in the container

$listener = new ServicesListenerAggregateCollection($delegates);
$listener->setServiceLocator($services);

$events = new EventManager();
$events->attach($listener);

$events->trigger(new Event('an_event'));

// PlainPhpObjectWithDependencies gets pulled from the locator only now
$events->trigger(new Event('bar'));
```