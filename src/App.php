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
 * 
 *
 * @package     Sentient
 * @author      Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright   2013, Pedro Mata-Mouros Fonseca.
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
class App extends Object
{
	protected $config;

	protected $log;

	protected $mode;

	protected $modes = array(
		'ajax',
		'api',
		'cli',
		'html',
	);

	protected $router;

	const AJAX = 'ajax';
	const API  = 'api';
	const CLI  = 'cli';
	const HTML = 'html';

	private function __construct() {}

	private function __clone() {}

	static public function instance()
	{
		static $instance = NULL;
		if ($instance === NULL) {
			$instance = new App();
		}
		return $instance;
	}

	/**
	 * 
	 */
	public function setConfig($baseDir, $overrideDir = NULL)
	{
		if ($this->config instanceof Config) {
			$this->config->setBaseDir($baseDir);
			$this->config->setOverrideDir($overrideDir);
			$this->config->reload();
		} else {
			$this->config = new Config($baseDir, $overrideDir);
			$this->config->init();
		}
	}

	public function setMode($mode)
	{
		if (!in_array($mode, $this->modes)) {
			throw Exception("Trying to set unknown mode $mode.");
		}
		$this->mode = $mode;
	}

	public function init()
	{
		if (!in_array($this->mode, $this->modes)) {
			throw Exception("A running mode is required, one hasn't been provided.");
		}

		// Pull up the logging from configuration

		// Pull up routing
		if ($this->mode == self::HTML || $this->mode == self::AJAX || $this->mode == self::API) {
			$configRouting = $this->config->valueForKey('sentient.routing');

			// Apparently PHP needs the namespace when using class_exists($class) or even $class()
			$routerClass = '\Sentient\\' . $this->config->valueForKey('sentient.routing.router');

			if (!class_exists($routerClass)) {
				throw new \Exception("Valid router is required in config file for key 'sentient.routing.router'");
			}

			$this->router = new $routerClass();

			//
			// SimpleHttpRouter
			//
			if ($this->config->valueForKey('sentient.routing.router') == 'SimpleHttpRouter') {
				
				// A controller must be provided in sentient.routing.controller
				$controllerClass = $this->config->valueForKey('sentient.routing.controller');
				if (empty($controllerClass)) {
					throw new \Exception("For SimpleHttpRouter a controller is required in the config file for key 'sentient.routing.controller'");
				}

				// Apparently PHP needs the namespace when using class_exists($class) or even $class()
				$controllerClass = 'Sentient\\' . $this->config->valueForKey('sentient.routing.controller');
				if (!class_exists($controllerClass)) {
					throw new \Exception("The controller provided in key 'sentient.routing.controller' was not found.");
				}

				$this->router->setDelegate(new $controllerClass());
			}

			//
			// AdvancedHttpRouter or custom
			//
			else {
				if (!($routes = $this->config->valueForKey('sentient.routing.routes'))) {
					throw new \Exception('You must provide routes for non-SimpleHttpRouter routing.');
				}
				foreach ($routes as $route) {
					$this->router->addRoute($route[0], $route[1], $route[2], $route[3], (isset($router[4]) ? $route[4] : NULL));
				}
			}

			$this->router->init();
		}
	}

	public function run()
	{
		$this->router->run();
	}
}