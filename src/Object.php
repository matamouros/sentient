<?php
/*
 *
 *  Copyright (c) 2013, Pedro Mata-Mouros <pedro.matamouros@gmail.com>
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 
 *   . Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *   . Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 *  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 *  ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 *  LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *  CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Sentient;

/**
 * Base class with the basic object functionality.
 *
 * @package     Sentient
 * @author      Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright   2013, Pedro Mata-Mouros Fonseca.
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 * @version     $LastChangedRevision$
 * @link        $HeadURL$
 * Changed by   $LastChangedBy$
 * Changed on   $LastChangedDate$
 */
class Object
{
	/** */
	private $delegate;

	/** */
	protected $outlets;

	/** */
	protected $observers;

	/**
	 * Part of the Observer spec.
	 * Temporarily and internally holds old values of attributes for
	 * willChangeValueForKey
	 */
	private $oldValues;

	public function __construct()
	{
		$this->delegate = NULL;
		$this->outlets = array();
		$this->observers = array();
		$this->oldValues = array();
	}

	/**
	 * Magic method implementation for calling vanilla getters and setters. This
	 * is rigged to work only with private/protected non-static class variables
	 * whose nomenclature follows the Zend Coding Standard.
	 *
	 * @param $name The name of the class property to get or set
	 * @param $args Arguments passed in by the magic __call() call
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		// Objective-C style getters (name of the property as the name of the getter)
		// TODO: look at privacy qualifiers!!!
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		elseif (strpos($name, 'set') === 0)
		{
			$key = lcfirst(substr($name, 3));
			if ($this->$key != $args[0])
			{
				// automatically() is checked here only because we're using will/didChange
				// as shortcut methods to implement auto notifications. But it's still
				// auto notifications, hence the call to automatically()
				if ($this->automaticallyNotifiesObserversForKey($key))
				{
					$this->willChangeValueForKey($key);
				}
				$this->$key = $args[0];
				if ($this->automaticallyNotifiesObserversForKey($key))
				{
					$this->didChangeValueForKey($key);
				}
			}
		}

		elseif (strpos($name, 'is') === 0)
		{
			$key = lcfirst(substr($name, 2));
			return (bool)$this->$key;
		}

		elseif (strpos($name, 'has') === 0)
		{
			$key = lcfirst(substr($name, 3));
			return (bool)$this->$key;
		}

		elseif (strpos($name, 'empty') === 0)
		{
			$key = lcfirst(substr($name, 5));
			return empty($this->$key);
		}

		// Automatically call delegate, if available
		elseif ($this->delegateRespondsToSelector($name))
		{
			return $this->fireDelegateMethod($name, $args);
		}

		else {
			throw new \Exception("No valid method '{$name}' available for calling on " . __CLASS__);
		}
	}

	/**
	 * Part of the Observer spec.
	 * Just like delegates provide hooks on application behaviour, observers do
	 * the same for application state changes. If an observer is registered
	 * for a specific property, it will be notified as soon as its value changes.
	 */
	public function addObserver(Object $object, $keyPath)
	{
		if (property_exists($this, $this->$keyPath)) {
			if (empty($this->observers[$keyPath])) {
				$this->observers[$keyPath] = array();
			}
			// TODO: every object should have a unique ID in order to replace if already added previously?
			$this->observers[$keyPath][] = $object;
		}
	}

	/**
	 * Objective-C inspired. Part of the Observer spec. Override this in a child
	 * class in order to get different behaviour.
	 */
	public function automaticallyNotifiesObserversForKey($key)
	{
		return true;
	}

	/**
	 * Part of the Observer spec.
	 */
	public function willChangeValueForKey($key)
	{
		$this->oldValues[$key] = $this->$key;
	}

	/**
	 * Part of the Observer spec.
	 */
	public function didChangeValueForKey($key)
	{
		// Notify registered observers (FIFO)
		if (!empty($this->observers[$key]) && is_array($this->observers[$key])) {
			for ($i=0, $c=count($this->observers[$key]); $i<$c; $i++) {
				$this->observers[$key][$i]->observeValueForKeyPath($key, $this, array('oldValue' => $this->oldValues[$key], 'newValue' => $this->$key));
			}
			$this->oldValues[$key] = null;
			unset($this->oldValues[$key]);
		}
	}

	/**
	 * Part of the Observer spec.
	 */
	// TODO: remove only one given observer ID? Check how it is in Obj-C
	public function removeObserver($keyPath)
	{
		unset($this->observers[$keyPath]);
	}

	/**
	 * Part of the Observer spec.
	 * Will be called by an object that was just changed and told to notify this one.
	 * 
	 * @param string $keyPath The attribute that was changed
	 * @param Object $object	The object instance that was changed
	 * @param mixed	$change	The old value of the attribute
	 */
	public function observeValueForKeyPath($keyPath, Object $object, $change = array()) {}

	/**
	 * Part of the Delegate spec.
	 * Delegates are an easy way to provide hooks on a class' behaviour. Object A
	 * executes action 'a' and, by design, fires a delegate method. If Object B
	 * registers itself as delegate of Object A and implements delegate method 'a',
	 * it will be called by A in runtime.
	 */
	public function setDelegate(Object $o)
	{
		$this->delegate = $o;
	}

	/**
	 * Obj-C compliance. Part of the Delegate spec.
	 * Every object has does in order to be queried if it supports a specific selector.
	 */
	public function respondsToSelector($selector)
	{
		// method_exists ensures that __call() of delegate is not considered on the check (contrary
		// to is_callable(). @see http://www.php.net/manual/en/function.method-exists.php#101507)
		//return method_exists($this, $selector);

		// matamouros 2013.08.22: Apparently method_exists() disregards visibility qualifier of
		// methods, thus rendering private methods as apparently callable, when they are not.
		return is_callable(array($this, $selector));
	}

	/**
	 * Part of the Delegate spec.
	 *
	 * This is just a safe method, so that we can be sure to check safely if a method
	 * exists on the delegate, without having to access it directly to use its own
	 * respondsToSelector, thus risking accessing the delegate object without it even
	 * being there (don't forget that due to circular referencing, we have to set
	 * $this->delegate as NULL on the constructor, so it's best not to rely on it
	 * always being set afterwards).
	 */
	protected function delegateRespondsToSelector($method)
	{
		return ($this->delegate instanceof Object && $this->delegate->respondsToSelector($method));
	}

	/**
	 * Part of the Delegate spec.
	 * This fires a delegate method, in case the delegate actually supports it.
	 * This object calling its delegate will provide the latter with parameters
	 * and also, should the delegate want it, a reference to the calling object.
	 */
	protected function fireDelegateMethod($method, $args = NULL)
	{
		if ($this->delegateRespondsToSelector($method)) {
			// Ensure something is always passed onto the delegate, even if
			// $args doesn't exist or is not an array.
			$params = array($this);
			if (!empty($args)) {
				if (!is_array($args)) {
					$args = array($args);
				}
				$params = array_merge($params, $args);
			}
			
			return call_user_func_array(array($this->delegate, $method), $params);
			// Using call_user_func_array ensures that the delegate method is called
			// with $this as first parameter and the other args as proper parameters
			// in the function call, as opposed to just a single parameter as an array.
			//return $this->delegate->$method($this, $args);
		}
	}

	/**
	 * Basically a wrapper for the generic setter.
	 */
	public function setValueForKey($value, $key)
	{
		$attribute = "set{ucfirst($key)}";
		$this->$attribute($value);
	}

	/**
	 * Basically a wrapper for the generic getter.
	 */
	public function valueForKey($key)
	{
		return $this->$key;
	}
}
