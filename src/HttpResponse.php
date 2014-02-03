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
class HttpResponse extends Object
{
	const HTTP_CONTINUE              = 100;
	const HTTP_OK                    = 200;
	const HTTP_CREATED               = 201;
	const HTTP_ACCEPTED              = 202;
	const HTTP_REDIRECT_PERM         = 301;
	const HTTP_REDIRECT_FOUND        = 302;
	const HTTP_BAD_REQUEST           = 400;
	const HTTP_EXPECTACTION_FAILED   = 417;
	const HTTP_INTERNAL_SERVER_ERROR = 500;

	const MODE_HTML = 'text/html';
	const MODE_JSON = 'application/json';
	
	protected $mode;

	protected $headers;

	protected $payload;

	protected $statusDesc = array(
		self::HTTP_CONTINUE              => 'Continue',
		self::HTTP_OK                    => 'OK',
		self::HTTP_CREATED               => 'Created',
		self::HTTP_ACCEPTED              => 'Accepted',
		self::HTTP_REDIRECT_PERM         => 'Moved Permanently',
		self::HTTP_REDIRECT_FOUND        => 'Found',
		self::HTTP_BAD_REQUEST           => 'Bad Request',
		self::HTTP_EXPECTACTION_FAILED   => 'Expectation Failed',
		self::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
	);

	protected $status;


	public function __construct()
	{
		$this->headers = array();
		$this->status = self::HTTP_INTERNAL_SERVER_ERROR;
	}

	public function addHeader($name, $value = '')
	{
		$this->headers[$name] = $value;
	}

	private function _statusDesc()
	{
		if (!isset($this->statusDesc[$this->status()])) {
			throw new \Exception("Trying to get a description for an unsupported HTTP status ($this->status())");
		}
		return $this->statusDesc[$this->status()];
	}

	/**
	 * Inspired by PECL HttpResponse
	 */
	public function redirect($url, array $params = array(), $session = FALSE, $status = self::HTTP_REDIRECT_PERM)
	{
		// TODO: add $params and $session information as per homonimous method
		header("Location: $url", $status);
		flush();
		exit;
	}

	public function send()
	{
		header("HTTP/1.1 {$this->status()} {$this->_statusDesc()}");
		foreach ($this->headers as $header => $value) {
			header("$header: $value");
		}
		// json_encode only if we're on JSON mode
		if (!empty($this->payload) && $this->mode() == self::MODE_JSON) {
			echo json_encode($this->payload);
		}
		exit;
	}

	public function setModeHtml()
	{
		$this->setMode(self::MODE_HTML);
		$this->addHeader('Content-type', self::MODE_HTML);
	}

	public function setModeJson()
	{
		$this->setMode(self::MODE_JSON);
		$this->addHeader('Content-type', self::MODE_JSON);
	}

	public function setStatus($status)
	{
		if (!isset($this->statusDesc[$this->status()])) {
			throw new \Exception("Trying to set an unsupported HTTP status ($status)");
		}
		$this->status = $status;
	}
}