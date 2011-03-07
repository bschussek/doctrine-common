<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Common;

use Doctrine\Common\Events\Event;

/**
 * The EventManager is the central point of Doctrine's event listener system.
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision: 3938 $
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Bernhard Schussek <bschussek@gmail.com>
 */
class EventManager
{
    /**
     * Map of registered listeners.
     * <event> => (<objecthash> => <listener>)
     *
     * @var array
     */
    private $_listeners = array();

    /**
     * Map of priorities by the object hashes of their listeners.
     * <event> => (<objecthash> => <priority>)
     *
     * This property is used for listener sorting.
     *
     * @var array
     */
    private $_priorities = array();

    /**
     * Stores which event listener lists are currently sorted.
     * <event> => <sorted>
     *
     * @var array
     */
    private $_sorted = array();

    /**
     * Dispatches an event to all registered listeners.
     *
     * @param string $eventName The name of the event to dispatch. The name of the event is
     *                          the name of the method that is invoked on listeners.
     * @param EventArgs $eventArgs The event arguments to pass to the event handlers/listeners.
     *                             If not supplied, the single empty EventArgs instance is used.
     */
    public function dispatchEvent($eventName, EventArgs $eventArgs = null)
    {
        if (isset($this->_listeners[$eventName])) {
            $eventArgs = $eventArgs === null ? new EventArgs() : $eventArgs;

            $this->sortListeners($eventName);

            foreach ($this->_listeners[$eventName] as $listener) {
                $this->triggerListener($listener, $eventName, $eventArgs);

                if ($eventArgs->isPropagationStopped()) {
                    break;
                }
            }
        }
    }

    /**
     * Triggers the listener method for an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param object $listener The event listener on which to invoke the listener method.
     * @param string $eventName The name of the event to dispatch. The name of the event is
     *                          the name of the method that is invoked on listeners.
     * @param EventArgs $eventArgs The event arguments to pass to the event handlers/listeners.
     */
    protected function triggerListener($listener, $eventName, EventArgs $eventArgs)
    {
        if ($listener instanceof \Closure) {
            $listener->__invoke($eventArgs);
        } else {
            $listener->$eventName($eventArgs);
        }
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * Calling this method multiple times will not cause overhead unless you
     * add new listeners. As long as no listener is added, the list for an
     * event name won't be sorted twice.
     *
     * @param string $event The name of the event.
     */
    private function sortListeners($eventName)
    {
        if (!$this->_sorted[$eventName]) {
            $p = $this->_priorities[$eventName];

            uasort($this->_listeners[$eventName], function ($a, $b) use ($p) {
                return $p[spl_object_hash($b)] - $p[spl_object_hash($a)];
            });

            $this->_sorted[$eventName] = true;
        }
    }

    /**
     * Gets the listeners of a specific event or all listeners.
     *
     * @param string $event The name of the event.
     * @return array The event listeners for the specified event, or all event listeners.
     */
    public function getListeners($event = null)
    {
        if ($event) {
            $this->sortListeners($event);

            return $this->_listeners[$event];
        }

        foreach ($this->_listeners as $event => $listeners) {
            $this->sortListeners($event);
        }

        return $this->_listeners;
    }

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $event
     * @return boolean TRUE if the specified event has any listeners, FALSE otherwise.
     */
    public function hasListeners($event)
    {
        return isset($this->_listeners[$event]) && $this->_listeners[$event];
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string|array $events The event(s) to listen on.
     * @param object $listener The listener object.
     * @param integer $priority The higher this value, the earlier an event listener
     *                          will be triggered in the chain. Defaults to 0.
     */
    public function addEventListener($events, $listener, $priority = 0)
    {
        // Picks the hash code related to that listener
        $hash = spl_object_hash($listener);

        foreach ((array) $events as $event) {
            if (!isset($this->_listeners[$event])) {
                $this->_listeners[$event] = array();
                $this->_priorities[$event] = array();
            }

            // Prevents duplicate listeners on same event (same instance only)
            $this->_listeners[$event][$hash] = $listener;
            $this->_priorities[$event][$hash] = $priority;
            $this->_sorted[$event] = false;
        }
    }

    /**
     * Removes an event listener from the specified events.
     *
     * @param string|array $events
     * @param object $listener
     */
    public function removeEventListener($events, $listener)
    {
        // Picks the hash code related to that listener
        $hash = spl_object_hash($listener);

        foreach ((array) $events as $event) {
            // Check if actually have this listener associated
            if (isset($this->_listeners[$event][$hash])) {
                unset($this->_listeners[$event][$hash]);
                unset($this->_priorities[$event][$hash]);
            }
        }
    }

    /**
     * Adds an EventSubscriber. The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param Doctrine\Common\EventSubscriber $subscriber The subscriber.
     * @param integer $priority The higher this value, the earlier an event listener
     *                          will be triggered in the chain. Defaults to 0.
     */
    public function addEventSubscriber(EventSubscriber $subscriber, $priority = 0)
    {
        $this->addEventListener($subscriber->getSubscribedEvents(), $subscriber, $priority);
    }
}