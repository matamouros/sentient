<?php
/*
 *
 *	Copyright (c) 2013, Pedro Mata-Mouros <pedro.matamouros@gmail.com>
 *	All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without
 *	modification, are permitted provided that the following conditions are met:
 * 
 *	 . Redistributions of source code must retain the above copyright notice,
 *		 this list of conditions and the following disclaimer.
 *
 *	 . Redistributions in binary form must reproduce the above copyright notice,
 *		 this list of conditions and the following disclaimer in the documentation
 *		 and/or other materials provided with the distribution.
 *
 *	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 *	AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 *	ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 *	LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 *	CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *	POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Sentient;

/**
 * Base class with the basic object functionality.
 *
 * @package		 Sentient
 * @author			Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright	 2013, Pedro Mata-Mouros Fonseca.
 * @license		 http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 * @version		 $LastChangedRevision$
 * @link				$HeadURL$
 * Changed by	 $LastChangedBy$
 * Changed on	 $LastChangedDate$
 */
class Model extends Object
{
	/**
	 * Last known digest of the instance.
	 */
	private $_signature;

	/** */
	private $_autoSave;

	/** */
	private $_saveOnExit;

	/** Indicates if the instance was changed and not yet saved (dirty == off) */
	//private $_dirty;

	public function __construct()
	{
		$this->_signature = NULL;
		$this->_autoSave = FALSE;
		$this->_saveOnExit = TRUE;
		//$this->_dirty = FALSE;
	}

	/** */
	public function __destruct()
	{
		if ($this->_saveOnExit && (/*$this->_dirty || */$this->isDirty()))
		{
			$this->_save();
		}
	}

	/** */
	public function __call($name, $args)
	{
		if (strpos($name, 'set') === 0)
		{
			$var = '_' . lcfirst(substr($name, 3));
			if ($this->$var != $args[0])
			{
				// Just call Object's setter to deal with it
				parent::__call($name, $args);
				//$this->setDirty(TRUE);
				if ($this->_autoSave && $this->_save())
				{
					$this->sign(); // automatically update signature once saved
					//$this->setDirty(FALSE);
				}
			}
		}
	}

	public function awaken()
	{
		$this->sign();
		//$this->setDirty(FALSE);
	}

	public function sleep()
	{
		
	}

	/** */
	public function isDirty()
	{
		return ($this->_currentSignature() != $this->_signature);
	}

	/** */
	public function sign(array $moreExclusions = array())
	{
		// Use the setter to honour eventual observers
		$this->setSignature($this->_currentSignature($moreExclusions));
	}

	/** */
	private function _currentSignature($moreExclusions = array())
	{
		$defaultExclusions = array(
			'_autoSave',   // Model
			'_saveOnExit', // Model
			'_signature',  // Model
			'_delegate',   // Object
			'_outlets',    // Object
			'_observers',  // Object
		);
		$exclusions = array_merge($defaultExclusions, $moreExclusions);
		$objVars = array_diff(get_object_vars($this), $exclusions);
		return md5(serialize($objVars));
	}

	abstract protected function _save() {}
}