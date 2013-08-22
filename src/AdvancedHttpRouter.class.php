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
 * The SimpleHttpRouter is a very simple and bare basics Http router for an
 * application. It directly maps URLs (actions) into methods on the controller
 * that you provide as a delegate. Every parameter needs to be accessed by the
 * delegate itself, either via GET or POST vars. It is bare basics in the sense
 * that you basically just get to have one single controller for defining all
 * the available actions on your app. Should you require a more complex
 * structure of controllers for your actions, say for modularity and code reuse
 * reasons and because your app's scale so justifies it, you should instead use
 * AdvancedHttpRouter.
 *
 * Mapping rules of SimpleHttpRouter:
 *
 *   URI          => method name  => template filename:
 *
 * . /foo         => foo()        => foo.tpl         (default section)
 * . /foo-bar     => fooBar()     => foo-bar.tpl     (default section)
 * . /foo/bar     => foo_bar()    => foo/bar.tpl     (foo section)
 * . /foo/foo-bar => foo_fooBar() => foo/foo-bar.tpl (foo section)
 *
 * @package     Sentient
 * @author      Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright   2013, Pedro Mata-Mouros Fonseca.
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
class AdvancedHttpRouter extends Object
{
	protected $controller;

	protected $method;

	protected $args;
	
	protected $url;

	protected $routes;


	public function __construct()
	{
		$this->controller = NULL;
		$this->method = NULL; // Default method that every provided delegate should implement
		$this->args = array();
		$this->url = NULL;
		$this->routes = array();
	}

	/**
	 * Private utility method for loading all the URL parts.
	 */
	private function _load()
	{
		// strtok() on '?' because we don't want the query string.
		$this->url = strtok($_SERVER['REQUEST_URI'], '?');
		
		// The following iterates each route, in the order they are added, and
		// try to match it with the REQUEST_URL. If there's a match, we try to
		// populate any placeholders and finally populate all our internal
		// attributes. After the first match is found, route evaluation stops.
		foreach ($this->routes as $route) {

			// Not particularly pretty, but it's possibly the most efficient
			// way of populating at once all the eventually possible placeholders
			// present in controller, method and args. Since these are separate
			// fields, we join them together for a preg_replace and then explode
			// them again to their original state (already populate, if that's
			// the case).
			$toReplace = implode('<>', array($route[1], $route[2], $route[3]));

			if (($replaced = preg_replace($route[0], $toReplace, $this->url)) != $this->url) {
				// preg_replace returns the subject unchanged, if no match was
				// found (instead of something like FALSE)

				list ($this->controller, $this->method, $args) = explode('<>', $replaced);
				if (!empty($args)) {
					$this->args = explode(',', str_replace(' ', '', $args)); // Remove all whitespace from inside the string and then explode by comma
				}
				
				// This was a match, no need to evaluate any more routes.
				break;
			}
		}
		// TODO: if no route found, 500!!
	}

	/**
	 *
	 */
	public function addRoute($url, $controller, $method, $args = array())
	{
		$this->routes[] = array(
			$url,
			$controller,
			$method,
			$args,
		);
	}

	/**
	 * Part of the initialisation process of SimpleHttpRouter, call this right
	 * after the constructor to make sure everything is setup and bootstrapped
	 * before calling run().
	 */
	public function init()
	{
		// Load all the 
		$this->_load();
	}

	/**
	 * This method triggers the flow for the application, dispatching the request.
	 * It relies on some sort of previously added HttpController delegate, that
	 * will handle dispatches for the URL methods extracted here. Should it fail
	 * implementing any, the SimpleHttpRouter will trigger the delegate's http404
	 * method, which it should be responsible for properly implementing, and if
	 * that also fails, the SimpleHttpRouter will take the reins and throw a 500
	 * back to the client.
	 */
	public function run()
	{
		$method = $this->method; // So that $this->method always holds the original method,
		                         // because the delegate controller might want to query it.

		// Cleanup the local $method of :: and ->, since this is what will be used
		// for calling the controller
		if (($pos = strpos($this->method, '::')) !== FALSE || ($pos = strpos($this->method, '->')) !== FALSE) {
			$method = substr($method, $pos+2);
		}

		$controller = $this->controller;
		// Instantiate the controller if it is not static
		if (strpos($this->method, '::') === FALSE) {
			$controller = new $this->controller();
		}

		if (!is_callable(array($controller, $method))) {
			// The controller should have full control over this response, so we're
			// refraining from spitting an HTTP 404 header down the wire here.
			$method = 'http404';

			if (!is_callable(array($controller, $method))) {
				header('HTTP/1.1 500 Internal Server Error');
				flush();
				throw new \Exception("No available method to serve the requested URL.");
				exit;
			}
		}

		// This router object is always passed onto the final controller so that
		// control can be passed back here or so that other non-linear flow can
		// be accomplished.
		call_user_func_array(array($controller, $method), array_merge(array($this), $this->args));
	}
}