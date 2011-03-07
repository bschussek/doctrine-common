<?php

namespace Doctrine\Tests\Common;

use Doctrine\Common\EventManager;
use Doctrine\Common\EventArgs;

require_once __DIR__ . '/../TestInit.php';

class EventManagerTest extends \Doctrine\Tests\DoctrineTestCase
{
    /* Some pseudo events */
    const preFoo = 'preFoo';
    const postFoo = 'postFoo';
    const preBar = 'preBar';
    const postBar = 'postBar';

    private $_eventManager;

    private $_listener;

    protected function setUp()
    {
        $this->_eventManager = new EventManager;
        $this->_listener = new TestEventListener;
    }

    public function testInitialState()
    {
        $this->assertEquals(array(), $this->_eventManager->getListeners());
        $this->assertFalse($this->_eventManager->hasListeners(self::preFoo));
        $this->assertFalse($this->_eventManager->hasListeners(self::postFoo));
    }

    public function testAddEventListener()
    {
        $this->_eventManager->addEventListener(array('preFoo', 'postFoo'), $this->_listener);
        $this->assertTrue($this->_eventManager->hasListeners(self::preFoo));
        $this->assertTrue($this->_eventManager->hasListeners(self::postFoo));
        $this->assertEquals(1, count($this->_eventManager->getListeners(self::preFoo)));
        $this->assertEquals(1, count($this->_eventManager->getListeners(self::postFoo)));
        $this->assertEquals(2, count($this->_eventManager->getListeners()));
    }

    public function testGetListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();

        $this->_eventManager->addEventListener('preFoo', $listener1, -10);
        $this->_eventManager->addEventListener('preFoo', $listener2);
        $this->_eventManager->addEventListener('preFoo', $listener3, 10);

        $expected = array(
            spl_object_hash($listener3) => $listener3,
            spl_object_hash($listener2) => $listener2,
            spl_object_hash($listener1) => $listener1,
        );

        $this->assertSame($expected, $this->_eventManager->getListeners('preFoo'));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener4 = new TestEventListener();
        $listener5 = new TestEventListener();
        $listener6 = new TestEventListener();

        $this->_eventManager->addEventListener('preFoo', $listener1, -10);
        $this->_eventManager->addEventListener('preFoo', $listener2);
        $this->_eventManager->addEventListener('preFoo', $listener3, 10);
        $this->_eventManager->addEventListener('postFoo', $listener4, -10);
        $this->_eventManager->addEventListener('postFoo', $listener5);
        $this->_eventManager->addEventListener('postFoo', $listener6, 10);

        $expected = array(
            'preFoo' => array(
                spl_object_hash($listener3) => $listener3,
                spl_object_hash($listener2) => $listener2,
                spl_object_hash($listener1) => $listener1,
             ),
            'postFoo' => array(
                spl_object_hash($listener6) => $listener6,
                spl_object_hash($listener5) => $listener5,
                spl_object_hash($listener4) => $listener4,
             ),
        );

        $this->assertSame($expected, $this->_eventManager->getListeners());
    }

    public function testDispatchEvent()
    {
        $this->_eventManager->addEventListener(array('preFoo', 'postFoo'), $this->_listener);
        $this->_eventManager->dispatchEvent(self::preFoo);
        $this->assertTrue($this->_listener->preFooInvoked);
        $this->assertFalse($this->_listener->postFooInvoked);
    }

    public function testDispatchEventForClosure()
    {
        $invoked = 0;
        $listener = function () use (&$invoked) {
            $invoked++;
        };
        $this->_eventManager->addEventListener(array('preFoo', 'postFoo'), $listener);
        $this->_eventManager->dispatchEvent(self::preFoo);
        $this->assertEquals(1, $invoked);
    }

    public function testStopEventPropagation()
    {
        $otherListener = new TestEventListener;

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->_listener to be called first
        $this->_eventManager->addEventListener('postFoo', $this->_listener, 10);
        $this->_eventManager->addEventListener('postFoo', $otherListener);
        $this->_eventManager->dispatchEvent(self::postFoo);
        $this->assertTrue($this->_listener->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority()
    {
        $invoked = array();
        $listener1 = function () use (&$invoked) {
            $invoked[] = '1';
        };
        $listener2 = function () use (&$invoked) {
            $invoked[] = '2';
        };
        $listener3 = function () use (&$invoked) {
            $invoked[] = '3';
        };
        $this->_eventManager->addEventListener('preFoo', $listener1, -10);
        $this->_eventManager->addEventListener('preFoo', $listener2);
        $this->_eventManager->addEventListener('preFoo', $listener3, 10);
        $this->_eventManager->dispatchEvent(self::preFoo);
        $this->assertEquals(array('3', '2', '1'), $invoked);
    }

    public function testRemoveEventListener()
    {
        $this->_eventManager->addEventListener(array('preBar'), $this->_listener);
        $this->assertTrue($this->_eventManager->hasListeners(self::preBar));
        $this->_eventManager->removeEventListener(array('preBar'), $this->_listener);
        $this->assertFalse($this->_eventManager->hasListeners(self::preBar));
    }

    public function testAddEventSubscriber()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->_eventManager->addEventSubscriber($eventSubscriber);
        $this->assertTrue($this->_eventManager->hasListeners(self::preFoo));
        $this->assertTrue($this->_eventManager->hasListeners(self::postFoo));
    }
}

class TestEventListener
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo(EventArgs $e)
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(EventArgs $e)
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }
}

class TestEventSubscriber implements \Doctrine\Common\EventSubscriber
{
    public static function getSubscribedEvents()
    {
        return array('preFoo', 'postFoo');
    }
}