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
 * The AdvancedHttpRouter is a more complex version of the SimpleHttpRouter,
 * offering the possibility of specifying exactly what routes there are and
 * their respective constraints, what controllers are associated with each
 * one and also extracting parameters from them.
 *
 * Routes are evaluated against a set of HTTP verbs and a request URL, the latter
 * by the order in which they are added. Once a match is found, the flow is passed
 * onto its controller, andthe route evaluation stops there. The AdvancedHttpRouter
 * instance is passed along to the used controller for convenience.
 * 
 * USAGE EXAMPLE #1:
 *     $router = new Sentient\AdvancedHttpRouter();
 *     $router->addRoute("PUT, GET", /^\\/project\\/(\\w*)\\/(\\w*)/", "ProjectHttpController", "->$1", "$2");
 *     $router->addRoute("*", "/^\\/$/", "HomeHttpController", "->index"); // Allows all HTTP verbs, same as ""
 *     $router->init();
 *     $router->run();
 *
 * USAGE EXAMPLE #2 (using Config for convenience):
 *     $config = new Sentient\Config('../config/');
 *     $config->init();
 *     $routes = $config->valueForKey('routes');
 *     $router = new Sentient\AdvancedHttpRouter();
 *     foreach ($routes as $route) {
 *         $router->addRoute($route[0], $route[1], $route[2], $route[3], (isset($route[4]) ? $route[4] : NULL));
 *     }
 *     $router->init();
 *     $router->run();
 *
 * In EXAMPLE #2 Config is used for convenience, so that the routes and their
 * respective configurations lie in a proper config file. Notice that whatever
 * structure is used for this config file is completely up to the programmer,
 * so long that the AdvancedHttpRouter API is respected, namely in using the
 * addRoute() method for adding the routes.
 *
 * On addRoute(), the actual format of each of the parameters must however
 * respect the following:
 *
 * $verbs        A comma separated list of allowed HTTP verbs. You can specify an
 *               empty string or "*" to allow all verbs.
 * 
 * $url          PCRE compatible regular expression, exactly like it is used with
 *               preg_match, for instance.
 * 
 * $controller   The name of the controller that will be invoked to handle the
 *               route. This controller should be autoloadable.
 *
 * $method       The method name to call on the controller. If static, it should
 *               be preceeded by "::", e.g., "::index". If non-static, it can
 *               be preceeded by "->", e.g., "->index", although it is not
 *               mandatory.
 *
 * $args         Arguments to pass to the called method, separated by commas,
 *               e.g., "foo, bar, $2" (these are three arguments that will be
 *               passed onto the called method as the 2nd, 3rd and 4th parameters
 *               respectively, and the AdvancedHttpRouter instance will be the
 *               first).
 *
 * Note that on the controller, method and args, you are free to use PCRE style
 * substitution placeholders, e.g., $1, $2, etc. These will be replaced by
 * whatever gets captured in the url.
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
		$this->method = NULL;
		$this->args = array();
		$this->url = NULL;
		$this->routes = array();
	}

	/**
	 * Evaluate the current request URL and resolve the controller that will handle it.
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

			// Enforce HTTP verb matching with what's specified on config (leave this iteration if no match)
			if (!empty($route[0]) && strpos($route[0], '*') === FALSE && stripos($route[0], $_SERVER['REQUEST_METHOD']) === FALSE) {
				// If there is a specific list of verbs, then we must enforce those
				continue;
			}
			
			// Not particularly pretty, but it's possibly the most efficient
			// way of populating at once all the eventually possible placeholders
			// present in controller, method and args. Since these are separate
			// fields, we join them together for a preg_replace and then explode
			// them again to their original state (already populate, if that's
			// the case).
			$toReplace = implode('<>', array($route[2], $route[3], $route[4]));

			// preg_replace returns the subject unchanged, if no match was
			// found (instead of something like FALSE)
			if (($replaced = preg_replace($route[1], $toReplace, $this->url)) != $this->url) {

				list ($this->controller, $this->method, $args) = explode('<>', $replaced);
				if (!empty($args)) {
					$this->args = explode(',', str_replace(' ', '', $args)); // Remove all whitespace from inside the string and then explode by comma
				}
				
				// This was a match, no need to evaluate any more routes.
				break;
			}
		}
	}

	/**
	 * Allows a route to be added, along with its respective controller, method
	 * and optional arguments.
	 *
	 * @param string verbs        A comma separated list of allowed HTTP verbs. You can specify an
	 *                            empty string or "*" to allow all verbs.
	 *
	 * @param string $url         PCRE compatible regular expression, exactly like it is used with
	 *                            preg_match, for instance.
	 *  
	 * @param string $controller  The name of the controller that will be invoked to handle the
	 *                            route. This controller should be autoloadable.
	 *
	 * @param string $method      The method name to call on the controller. If static, it should
	 *                            be preceeded by "::", e.g., "::index". If non-static, it can
	 *                            be preceeded by "->", e.g., "->index", although it is not
	 *                            mandatory.
	 *
	 * @param array $args         Arguments to pass to the called method, separated by commas,
	 *                            e.g., "foo, bar, $2" (these are three arguments that will be
	 *                            passed onto the called method as the 2nd, 3rd and 4th parameters
	 *                            respectively, and the AdvancedHttpRouter instance will be the
	 *                            first).
	 */
	public function addRoute($verbs, $url, $controller, $method, $args = array())
	{
		$this->routes[] = array(
			$verbs,
			$url,
			$controller,
			$method,
			$args,
		);
	}

	/**
	 * Part of the initialisation process of AdvancedHttpRouter, call this right
	 * after the constructor to make sure everything is setup and bootstrapped
	 * before calling run().
	 */
	public function init()
	{
		$this->_load();
	}

	/**
	 * This method dispatches the request to the resolved controller. If the
	 * resolved method for the resolved controller is not callable, it will
	 * degrade to call an http404 method, and if that is not callable too, it
	 * will degrade to reply back an HTTP 500 to the client.
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
		if (strpos($this->method, '::') === FALSE && class_exists($controller)) {
			$controller = new $this->controller();
			method_exists($controller, 'init'); // Run init() methods automatically
		}

		// We need method_exists here instead of is_callable, so as to not consider Object's __call()
		if (!method_exists($controller, $method)) {
			// The controller should have full control over this response, so we're
			// refraining from spitting an HTTP 404 header down the wire here.
			$method = 'http404';

			if (!method_exists($controller, $method)) {
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