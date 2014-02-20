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
 * This allows the use of JSON configuration files, placed inside a directory.
 * There is no restriction on the names of these files or where they are, but
 * this class will attempt to load every JSON file in that dir, on the root
 * level only, i.e., it will not go deeper into subdirectories. All the files
 * will be read in the order that the system call returns them, i.e., make no
 * assumptions as to what is read first. Subsequent files that repeat keys will
 * override existing keys.
 *
 * You can specify a baseDir for the general configuration values and you can
 * then specify an overrideDir, so that only specific keys get overriden. This
 * is tipically used for specifying different environments for your app, by
 * having all the general files in one dir (tipically the root) and then all
 * the possible different environments (DEV, QA, PRODUCTION, etc) in subdirs.
 *
 * @package     Sentient
 * @author      Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright   2013, Pedro Mata-Mouros Fonseca.
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
class Config
{
	protected $baseDir = NULL;

	protected $overrideDir = NULL;

	protected $config = array();


	public function __construct($baseDir, $overrideDir = NULL)
	{
		$this->setBaseDir($baseDir);
		
		if (!empty($overrideDir)) {
			$this->setOverrideDir($overrideDir);
		}
	}

	/**
	 * Utility method for flattening a multidimensional array into a
	 * unidimensional one, appending all the child keys with dot notation.
	 * This is a recursive function.
	 *
	 * TODO: This should go into a generic Array utility class
	 *
	 * @param  array   $array    A multidimensional array to flatten.
	 * @param  string  $backKey  New keys will be appended to this.
	 *
	 * @return array   A flattened, i.e., unidimensional, array.
	 */
	private function _flattenArray(array $array, $backKey = '')
	{
		$res = array();
		foreach ($array as $key => $value) {
			$iniKey = '';
			$iniKey .= (empty($backKey) ? '' : $backKey . '.') . $key;
			if (is_array($value)) {
				//$res[] = flattenArray($value, $iniKey);
				$res = array_merge($res, $this->_flattenArray($value, $iniKey));
			} else {
				$res[$iniKey] = $value;
			}
		}
		return $res;
	}
	
	/**
	 * Private utility method for expanding variables in configuration
	 * directives' values. It traverses the whole configuration structure
	 * searching for values that have variables of the type ${VAR} inside,
	 * and tries to expand them, refering to whatever is already loaded in
	 * the configuration. In case it can't resolve a variable, it is left
	 * untouched.
	 *
	 * This is a recursive function.
	 *
	 * @param  array   $array    Should not be used, except by the method's
	 *         own recursiveness.
	 * @param  string  $backKey  Should not be used, except by the method's
	 *         own recursiveness.
	 */
	private function _expandVarsInValues(array $array = NULL, $backKey = '')
	{
		if (!is_array($array)) {
			$array = $this->config;
		}
		foreach ($array as $key => $value) {
			$iniKey = '';
			$iniKey .= (empty($backKey) ? '' : $backKey . '.') . $key;
			if (is_array($value)) {
				$this->_expandVarsInValues($value, $iniKey);
			} else {
				// This strpos() is inexpensive compared to doing the preg_replace_callback
				// on every configuration directive...
				if (strpos($value, '${') !== FALSE) {
					// The following is some blackmagic that basically makes sure
					// multiple occurrences of ${var} get replaced by their
					// actual value.
					// @see http://stackoverflow.com/a/4643467/315285
					if ($value = preg_replace_callback('/(\$\{((?:[^\$]|\$[^{])*)\})/', function ($matches) {
						return $this->valueForKey($matches[2], $matches[1]);
					}, $value)) {
						$this->_setValueForKey($iniKey, $value);
					}
				}
			}
		}
	}

	/**
	 * Private method that loads the configuration files on the general dir
	 * first, then the override dir, and then makes sure that everything is
	 * placed in a nicely formatted dot notation unidimensional array.
	 */
	private function _load()
	{
		$this->_loadConfig($this->baseDir);
		$this->_loadConfig($this->overrideDir);
		
		// All loadConfig() should happen before expanding vars, so that there's
		// a chance for all configuration to be known.
		$this->_expandVarsInValues();
	}

	/**
	 * Private utility method that does the dirty work of pulling all JSON
	 * files on a dir and loading them up.
	 *
	 * @param  string  $dirName  The dir to pull the config files from.
	 */
	private function _loadConfig($dirName)
	{
		if (is_dir($dirName)) {
			$dir = new \DirectoryIterator($dirName);
			$buf = array();
			foreach ($dir as $entry) {
				if ($entry->isFile() && strtolower($entry->getExtension()) == 'json') {
					$fileContents = file_get_contents($entry->getPathname());
					$configArray = json_decode($fileContents, TRUE);
					if (json_last_error() != JSON_ERROR_NONE) {
						throw new \Exception("Invalid JSON file? {$entry->getPathname()}");
					}
					// Because if a JSON file is empty, json_decode doesn't throw
					// an error and $configArray will not be an array anymore.
					if (is_array($configArray) && !empty($configArray)) {
						$buf = array_replace_recursive($buf, $configArray);
					}
				}
			}
			// The actual merge with self::$config is only done here, instead of
			// above like it would seem better at first sight, because this gives
			// us a chance of aborting the reload safely in case an error occurs
			// on a configuration file.
			$this->config = array_replace_recursive($this->config, $buf);
		}
	}
	
	/**
	 * Setter for a given key passed in in dot notation. It explodes this given
	 * key and tries to traverse the configuration structure until it finds the
	 * relevant configuration directive.
	 *
	 * @param  string  $key           The configuration key to set.
	 * @param  mixed   $value         The value to set the configuration directive to.
	 *
	 * @return boolean                Either TRUE or FALSE according to successful
	 *	       or insuccessful.
	 */
	private function _setValueForKey($key, $value)
	{
		$keyParts = explode('.', $key);
		//
		// All of the following because there is really no way of dynamically access
		// an array of unknown multilevel depthness:
		// http://thehighcastle.com/blog/38/php-dynamic-access-array-elements-arbitrary-nesting-depth
		//
		$c = count($keyParts);
		$ret =& $this->config;
		for ($i = 0; $i < $c; $i++) {
			if (!isset($ret[$keyParts[$i]])) {
				// If the key is bogus, get out now.
				return FALSE;
			}
			$ret =& $ret[$keyParts[$i]];
		}
		$ret = $value;
		return TRUE;
	}

	/**
	 * Part of the public exposed API for using Config, this method makes sure
	 * that the configuration files are loaded only when they haven't been
	 * loaded before.
	 */
	public function init()
	{
		if (empty($this->config)) {
			$this->_load();
		}
	}

	/**
	 * Part of the public exposed API for using Config, this method reloads
	 * the configuration files at request.
	 */
	public function reload()
	{
		$this->_load();
	}

	public function setBaseDir($baseDir)
	{
		if (!($this->baseDir = realpath($baseDir))) {
			throw new \Exception("Invalid dir specified {$baseDir}");
		}
	}

	public function setOverrideDir($overrideDir)
	{
		if (!($this->overrideDir = realpath($this->baseDir . DIRECTORY_SEPARATOR . $overrideDir))) {
			throw new \Exception("Invalid dir specified {$overrideDir}");
		}
	}

	/**
	 * This is the getter for any key within the configuration. A defaultValue
	 * is provided as a commodity, so that really only one line of code is
	 * required when getting something from the configuration for the case
	 * nothing exists. This makes this method always return either the actual
	 * configuration value or the user's specified default.
	 *
	 * @param  string  $key           The key from the configuration to retrieve.
	 * @param  mixed   $defaultValue  A default commodity value, so that the this
	 *         method always returns either the actual config value or the default.
	 *
	 * @return mixed   The intended configuration value or the user specified default.
	 */
	public function valueForKey($key, $defaultValue = NULL)
	{
		$keyParts = explode('.', $key);
		//
		// All of the following because there is really no way of dynamically access
		// an array of unknown multilevel depthness:
		// http://thehighcastle.com/blog/38/php-dynamic-access-array-elements-arbitrary-nesting-depth
		//
		$c = count($keyParts);
		$ret = $this->config;
		for ($i = 0; $i < $c; $i++) {
			if (!isset($ret[$keyParts[$i]])) {
				return $defaultValue;
			}
			$ret = $ret[$keyParts[$i]];
		}
		return $ret;
		// TODO: If the key doesn't exist, we always return the full config, which is wrong... What should we do? Return NULL?
	}
}
