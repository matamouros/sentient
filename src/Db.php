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

require_once '../vendor/adodb5/adodb.inc.php';
include_once '../vendor/adodb5/adodb-exceptions.inc.php';
//
// Use only associative arrays. Must be global scope for ADOdb to catch it
//
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

/**
 * 
 *
 * @package		Sentient
 * @author		Pedro Mata-Mouros Fonseca <pedro.matamouros@gmail.com>
 * @copyright	2013, Pedro Mata-Mouros Fonseca.
 * @license		http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 * @version		$LastChangedRevision$
 * @link		$HeadURL$
 * Changed by	$LastChangedBy$
 * Changed on	$LastChangedDate$
 */
class Db extends Object
{
	protected $readerType;
	protected $readerHost;
	protected $readerUsername;
	protected $readerPassword;
	protected $readerName;
	protected $writerType;
	protected $writerHost;
	protected $writerUsername;
	protected $writerPassword;
	protected $writerName;

	/**
	 * @throws Exception
	 */
	private function _getReader()
	{
		static $reader;
		if (empty($reader)) {
			//
			// This might throw an exception
			// 
			$reader = ADONewConnection($this->readerType);
			$reader->Connect($this->readerHost, $this->readerUsername, $this->readerPassword, $this->readerName);
		}
		return $reader;
	}

	/**
	 * @throws Exception
	 */
	private function _getWriter()
	{
		static $writer;
		if (empty($writer)) {
			//
			// This might throw an exception
			// 
			$writer = ADONewConnection($this->writerType);
			$writer->Connect($this->writerHost, $this->writerUsername, $this->writerPassword, $this->writerName);
		}
		return $writer;
	}

	/**
	 *
	 * Specifically tailored for write executions that are not inserts and thus do
	 * not need to return a result set. It is possible to use binding parameters
	 * to the sql query passed as an argument. It behaves much like if it was
	 * doing a prepared statement (and indeed it is) - however, performance-wise
	 * there's really no gain, since on every call to this method, a Prepare() is
	 * done. I.e., use this "prepared statement" functionality only for parameter
	 * binding (you gain automatic protection against SQL injections), and use the
	 * statementPrepare() and statementExecute() methods for the real prepared
	 * statements you may want to use.
	 *
	 * @param string $query The SQL to execute
	 * @param array $values Optional values array for parameter binding
	 *
	 * @return bool TRUE or FALSE.
	 */
	public function execute($o, $query, array $values = NULL)
	{
		$result = FALSE;
		$writer = $this->_getWriter();

		try
		{
			$result = (empty($values)? $writer->_query($query, FALSE): $writer->Execute($query, $values));
		}
		catch (Exception $e)
		{
			//print (__METHOD__ . ': error! [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
		}
		
		return ((bool)$result);
	}

	/**
	 * Specifically tailored for insertions, thus not needing to return a result
	 * set. It is possible to use binding parameters to the sql query passed as an
	 * argument. It behaves much like if it was doing a prepared statement (and
	 * indeed it is) - however, performance-wise there's really no gain, since on
	 * every call to this method, a Prepare() is done. I.e., use this "prepared
	 * statement" functionality only for parameter binding (you gain automatic
	 * protection against SQL injections), and use the statementPrepare() and
	 * statementExecute() methods for the real prepared statements you may want to
	 * use.
	 *
	 * @param string $query The SQL to execute
	 * @param array $values Optional values array for parameter binding
	 *
	 * @return The id of the inserted record or False. NOTE: If the table doesn't
	 * have auto-numbering on, the id string "0" is returned! Be sure to check
	 * this using the === operator.
	 */
	public function insert($o, $query, array $values = NULL)
	{
		$result = FALSE;
		$writer = $this->_getWriter();

		try
		{
			$result = (empty($values)? $writer->_query($query, FALSE): $writer->Execute($query, $values));
		}
		catch (Exception $e)
		{

		}
		
		$id = FALSE;
		
		try
		{
			$id = $writer->Insert_ID();
		}
		catch (Exception $e)
		{
			
		}

		return $id;
	}

	/**
	 * Method specifically designed for queries that return a result set. It is
	 * possible to use binding parameters to the sql query passed as an argument.
	 * It behaves much like if it was doing a prepared statement (and indeed it
	 * is) - however, performance-wise there's really no gain, since on every call
	 * to this method, a Prepare() is done. I.e., use this "prepared statement"
	 * functionality only for parameter binding (you gain automatic protection
	 * against SQL injections), and use the statementPrepare() and
	 * statementExecute() methods for the real prepared statements you may want to
	 * use.
	 *
	 * @param string $query The SQL to execute
	 * @param array $values Optional values array for parameter binding
	 *
	 * @return bool|Object FALSE or the result set of the query performed
	 */
	public function query($o, $query, array $values = NULL)
	{
		$result = FALSE;
		$reader = $this->_getReader();

		try
		{
			$result = (empty($values)? $reader->_Execute($query, FALSE): $reader->Execute($query, $values));
		}
		catch (Exception $e)
		{

		}

		return $result;
	}

	public function transactionStart()
	{

	}

	public function transactionEnd()
	{

	}

	public function transactionFail()
	{

	}
}