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
class SimpleHttpRouter extends Object
{
	protected $method;
	
	protected $url;

	protected $urlParts;


	public function __construct()
	{
		$this->method = NULL; // Default method that every provided delegate should implement
		$this->url = NULL;
		$this->urlParts = array();
	}

	/**
	 * Private utility method for loading all the URL parts.
	 */
	private function _load()
	{
		// strtok() on '?' because we don't want the query string.
		$this->url = strtok($_SERVER['REQUEST_URI'], '?');

		// The following trim() makes sure that there will be no empty values
		// within the array due to beginning '/' (and eventually ending also)
		$this->urlParts = explode('/', trim($this->url, '/'));
		
		$newParts = array();
		foreach ($this->urlParts as $urlPart) {
			if (strpos($urlPart, '-') !== FALSE) {
				$urlPartPieces = explode('-', $urlPart);
				array_walk($urlPartPieces, function(&$value) {
					$value = ucfirst($value);
				});
				$newParts[] = lcfirst(implode($urlPartPieces));
			} else {
				$newParts[] = $urlPart;
			}
		}
		$this->method = implode('_', $newParts);
		if (empty($this->method)) {
			$this->method = 'index';
		}
	}

	/**
	 * Part of the initialisation process of SimpleHttpRouter, call this right
	 * after the constructor to make sure everything is setup and bootstrapped
	 * before calling run().
	 */
	public function init()
	{
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

		// Here we actually need to know beforehand if the delegate implements
		// this method, because we need to error out in case it doesn't (404)
		if (!$this->delegateRespondsToSelector($method)) {
			// The delegate should have full control over this response, so we're
			// refraining from spitting an HTTP 404 header down the wire here.
			$method = 'http404';
		
			if (!$this->delegateRespondsToSelector($method)) {
				header('HTTP/1.1 500 Internal Server Error');
				flush();
				throw new \Exception("No available method to serve the requested URL.");
				exit;
			}
		}
		
		// The only reason why on here we don't just blindly invoke the delegate is
		// that we really want to do different things on the case no http404 exists.
		return $this->fireDelegateMethod($method);
	}
}