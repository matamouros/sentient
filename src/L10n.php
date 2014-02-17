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
class L10n extends Object
{
	protected $localeId;

	protected $localeIds = array(
		'en_GB',
		'pt_PT',
	);

	protected $locales;

	protected $localesDir;


	public function __construct($localeId)
	{
		if (!in_array($localeId, $this->localeIds)) {
			throw new Exception("Locale ID '$localeId' unknown.");
		}
		$this->localeId = $localeId;
		$this->locales[$localeId] = array();
	}

	private function _t($id, $args)
	{

	}

	public function config()
	{
		
	}

	public function t($id)
	{
		$args = array();
		// Get the extra args, in case they were provided
		if (func_num_args() > 1) {
			$args = func_get_args();
			unset($args[0]); // Remove the first arg, it's already on $id
		}
		return vsprintf($this->_t($id, $args), $args);
	}
}