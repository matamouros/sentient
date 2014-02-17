<?php
/**
 * This file defines Database.
 *
 * @package   Core
 * @author    Pedro Mata-Mouros Fonseca <matamouros@co.sapo.pt>
 * @copyright 2010 PT Multimedia.com
 * @version   $Rev$
 */


require SF_ADODB_BASE_DIR . 'adodb.inc.php';
include SF_ADODB_BASE_DIR . 'adodb-exceptions.inc.php';


//
// Use only associative arrays. Must be global scope for ADOdb to catch it
//
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;


/**
 * Database class, tightly integrated with ADOdb.
 *
 * @author    Pedro Mata-Mouros Fonseca <pfonseca@co.sapo.pt>
 * @copyright 2008 PT Multimedia.com
 * @version   $Rev$
 */
class Database
{
  static private $_errorMsg = '';

  const IL_DEFAULT      = '';
  const READ_UNCOMMITED = 'READ UNCOMMITED'; // allows dirty reads, but fastest
  const READ_COMMITED   = 'READ COMMITED';   // default postgres, mssql and oci8
  const REPEATABLE_READ = 'REPEATABLE READ'; // default mysql
  const SERIALIZABLE    = 'SERIALIZABLE';    // slowest and most restrictive


  /**
   * Instantiates a static variable with the read-only database link
   */
  private static function _singletonReadConn()
  {
    static $dbReader;
    if (!isset($dbReader) || empty($dbReader)/* || !$dbReader->IsConnected()*/) {
      try {
        $dbReader = ADONewConnection(SAPOFOTOS_DB_READER_TYPE);
        $dbReader->Connect(DATABASE_HOSTNAME_READER,DATABASE_LOGIN_READER,DATABASE_PASSWORD_READER,DATABASE_NAME_READER);
        #if DEBUG
        if (SAPOFOTOS_DEBUG_DATABASE) {
          Logger::logDebug(__METHOD__ . ': new connection opened.');
        }
        #endif
      } catch (Exception $e) {
        Logger::logError(__METHOD__ . ": error connecting to the database! [ERR={$e->getMessage()}]");
        $dbReader = null;
        unset($dbReader);
        return false;
      }
    }
    #if DEBUG
    //elseif (SAPOFOTOS_DEBUG_DATABASE) {
    //  Logger::logDebug(__METHOD__ . ': connection reused.');
    //}
    #endif
    return $dbReader;
  }


  /**
   * Instantiates a static variable with the write-only database link
   */
  private static function _singletonWriteConn()
  {
    static $dbWriter;
    if (!isset($dbWriter) || empty($dbWriter)/* || !$dbWriter->IsConnected()*/) {
      try {
        $dbWriter = ADONewConnection(SAPOFOTOS_DB_WRITER_TYPE);
        $dbWriter->Connect(DATABASE_HOSTNAME_WRITER,DATABASE_LOGIN_WRITER,DATABASE_PASSWORD_WRITER,DATABASE_NAME_WRITER);
        #if DEBUG
        if (SAPOFOTOS_DEBUG_DATABASE) {
          Logger::logDebug(__METHOD__ . ': new connection opened.');
        }
        #endif
      } catch (Exception $e) {
        Logger::logError(__METHOD__ . ": error connecting to the database! [ERR={$e->getMessage()}]");
        $dbWriter = null;
        unset($dbWriter);
        return false;
      }
    }
    #if DEBUG
    //elseif (SAPOFOTOS_DEBUG_DATABASE) {
    //  Logger::logDebug(__METHOD__ . ': connection reused.');
    //}
    #endif
    return $dbWriter;
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
   * @return bool True or False.
   */
  public static function execute($query, $values=null)
  {
    $proctime = 0;
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = empty($values)?$dbWriter->_query($query,false):$dbWriter->Execute($query,$values);
    } catch (Exception $e) {
      $result = false;
    }
    $proctime = microtime(true)-$starttime;
    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError(__METHOD__ . ': error! [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbWriter->ErrorMsg());
      return false;
    }
    #if DEBUG
    elseif (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': executed query [SQL='.$query.']'.(!empty($values)?' [VALUES='.implode(' | ',$values).']':''));
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn(__METHOD__ . ': slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [ID='.$result.'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    return true;
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
  public static function insert($query, $values=null)
  {
    $proctime = 0;
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = empty($values)?$dbWriter->_query($query,false):$dbWriter->Execute($query,$values);
    } catch (Exception $e) {
      $result = false;
    }
    $proctime = microtime(true)-$starttime;
    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError(__METHOD__ . ': error! [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbWriter->ErrorMsg());
      return false;
    }
    #if DEBUG
    elseif (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': inserted sql [SQL='.$query.']'.(!empty($values)?' [VALUES='.implode(' | ',$values).']':''));
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn(__METHOD__ . ': slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [ID='.$result.'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    $id = false;
    try {
      $id = $dbWriter->Insert_ID();
    } catch(Exception $e) {
      Logger::logError(__METHOD__ . ': error getting last inserted id [ERRNO='.$dbWriter->ErrorNo().']');
      $id = false;
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
   * @param bool $useWriterConn Uses the writer connection instead of the reader
   * one
   *
   * @return bool|Object False or the result set of the query performed
   */
  public static function query($query, $values=null, $useWriteConn=false)
  {
    $proctime = 0;
    $dbReader = null;
    if ($useWriteConn) {
      $dbReader = self::_singletonWriteConn();
    } else {
      $dbReader = self::_singletonReadConn();
    }
    if (!$dbReader) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = empty($values)?$dbReader->_Execute($query,false):$dbReader->Execute($query,$values);
    } catch (Exception $e) {
      $result = false;
    }
    $proctime  = microtime(true)-$starttime;
    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError(__METHOD__ . ': error! [ERRNO='.$dbReader->ErrorNo().'] [ERRMSG='.$dbReader->ErrorMsg().'] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbReader->ErrorMsg());
      return false;
    }
    #if DEBUG
    elseif (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': executed query [SQL='.$query.']'.(!empty($values)?' [VALUES='.implode(' | ',$values).']':''));
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn(__METHOD__ . ': slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [QUERY='.$query.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    return $result;
  }


  /**
   *
   * Prepares a statement for execution with statementExecute(). Sets the
   * internal variable, which will be used by this later method.
   *
   * @param $sql The SQL statement to prepare
   *
   * @return Array An array with the SQL statement on the first position and a
   * mysqli prepared statement on the second; or false on error.
   *
   */
  public static function &statementPrepare($sql)
  {
    //
    // It's really irrelevant if we're using the writer or reader - only the
    // prepared statement matters.
    //
    // pfonseca 2009.03.02:
    // Not only is the above comment false, it is the origin of a severe bug,
    // in which the statement being prepared in the reader connection, and then
    // executed in the writer connection, the connection used always refers back
    // to the prepared statement's original. So, trying to statementExecute or
    // statementInsert after a statementPrepare, would execute it in the reader
    // connection. Not seen in development, because there we only have 1 db.
    //
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    $ret = false;
    try {
      $ret = $dbWriter->Prepare($sql);
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error! [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [SQL='.$sql.']');
      self::_setErrorMsg($dbWriter->ErrorMsg());
      $ret = false;
    }
    return $ret;
  }


  /**
   * Executes a prior prepared statement with statementPrepare(). Use this for
   * everything that is not an INSERT or a SELECT.
   *
   * @param Array An array with the SQL statement on the first position and a
   * mysqli prepared statement on the second.
   *
   * @param Array $values Values array for parameter binding
   *
   * @return bool|Object False or the result set of the query performed
   */
  public static function statementExecute(&$stmt, $values)
  {
    $proctime = 0;
    $dbWriter = self::_singletonWriteConn();

    if (!$dbWriter || empty($stmt)) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = $dbWriter->_query($stmt, $values);
    } catch (Exception $e) {
      $result = false;
    }
    $proctime = microtime(true)-$starttime;

    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError(__METHOD__ . ': error! [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [PRPDSTMT='.Utility::removeWhitespaces(print_r($stmt,true)).']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbWriter->ErrorMsg());
      return false;
    }
    #if DEBUG
    elseif (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': executed prepared statement [STMT='.Utility::removeWhitespaces(print_r($stmt, true)).'] [VALUES='.implode(' | ',$values).']');
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn(__METHOD__ . ': slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [PRPDSTMT='.$stmt.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    //
    // TODO: can _query return a result id in case it executes an INSERT????
    //
    return $result;
  }


  /**
   * Executes a prior prepared statement with statementPrepare(), and returns
   * the insertion ID. This is specifically tailored for INSERTs.
   *
   * @param Array An array with the SQL statement on the first position and a
   * mysqli prepared statement on the second.
   *
   * @param Array $values Values array for parameter binding
   *
   */
  public static function statementInsert(&$stmt, $values)
  {
    /*
    $proctime = 0;
    $dbWriter = self::_singletonWriteConn();

    if (!$dbWriter || empty($stmt)) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = $dbWriter->_query($stmt, $values);
    } catch (Exception $e) {
      $result = false;
    }
    $proctime = microtime(true)-$starttime;

    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError(__METHOD__ . ': [ERRNO='.$dbWriter->ErrorNo().'] [ERRMSG='.$dbWriter->ErrorMsg().'] [PRPDSTMT='.$stmt.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbWriter->ErrorMsg());
      return false;
    }
    #if DEBUG
    elseif (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': inserted statement [VALUES='.implode(' | ',$values).']');
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn(__METHOD__ . ': slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [PRPDSTMT='.$stmt.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    $id = false;
    try {
      $id = $dbWriter->Insert_ID();
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error getting last inserted id [ERRNO='.$dbWriter->ErrorNo().']');
      $id = false;
    }
    return $id;
    */
    return false;
  }


  /**
   *
   * Executes a prior prepared statement with statementPrepare() and returns a
   * result set.
   *
   */
  public function statementQuery($values, $useWriterConn=false)
  {
    /*
    $proctime = 0;
    if ($useWriterConn) {  $dbReader = self::_singletonWriteConn(); }
    else                 { $dbReader = self::_singletonReadConn(); }

    if(!$dbReader || empty($this->prpdStmt)) {
      return false;
    }

    $starttime = microtime(true);
    try {
      $result = $dbReader->_Execute($this->prpdStmt, $values);
    } catch(exception $e) {
      $result = false;
    }
    $proctime  = microtime(true)-$starttime;

    $tmp = '';
    if (!$result) {
      if (!empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logError('Database::statementQuery: [ERRNO='.$dbReader->ErrorNo().'] [ERRMSG='.$dbReader->ErrorMsg().'] [PRPDSTMT='.$this->prpdStmt.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
      self::_setErrorMsg($dbReader->ErrorMsg());
      return false;
    }
    #if DEBUG
    else if (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug('Database::statementQuery: executed statement query [VALUES='.implode(' | ',$values).']');
    }
    #endif
    //
    // Alerts for slow queries
    //
    if ($proctime >= DATABASE_ALERT_TIME) {
      if (empty($tmp) && !empty($values)) {
        $tmp = implode(' | ',$values);
      }
      Logger::logWarn('Database::statementQuery: Slowquery! [TIME='.sprintf('%.3f',$proctime).'s] [PRPDSTMT='.$this->prpdStmt.']'.(!empty($values)?' [VALUES='.$tmp.']':''));
    }
    return $result;
    */
    //
    // pfonseca 2009.03.02
    // Check the comment on statementPrepare. There is a severe bug in action
    // here, so statementQuery is disabled until further notice. It's not being
    // used anywhere, so should pose no harm.
    //
    return false;
  }


  /**
   *
   */
  public static function setIsolationLevel($level='')
  {
    if ( $level != self::READ_UNCOMMITED ||
         $level != self::READ_COMMITED ||
         $level != self::REPEATABLE_READ ||
         $level != self::SERIALIZABLE ||
         $level != self::IL_DEFAULT )
    {
      $level = self::IL_DEFAULT;
    }
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    try {
      $dbWriter->SetTransactionMode($level);
      return true;
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error setting isolation level [VARDUMP='.print_r($e, true).']');
      return false;
    }
  }


  /**
   *
   */
  public static function transactionStart()
  {
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    try {
      $dbWriter->StartTrans();
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error starting transaction [VARDUMP='.print_r($e, true).']');
      return false;
    }
    #if DEBUG
    if (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': started transaction.');
    }
    #endif
    return true;
  }


  /**
   *
   */
  public static function transactionEnd()
  {
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    try {
      $dbWriter->CompleteTrans();
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error ending transaction [VARDUMP='.print_r($e, true).']');
      return false;
    }
    #if DEBUG
    if (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': ended transaction.');
    }
    #endif
    return true;
  }


  /**
   *
   */
  public static function transactionFail()
  {
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    try {
      $dbWriter->FailTrans();
    } catch (Exception $e) {
      Logger::logError(__METHOD__ . ': error failing transaction [VARDUMP='.print_r($e, true).']');
      return false;
    }
    #if DEBUG
    if (SAPOFOTOS_DEBUG_DATABASE) {
      Logger::logDebug(__METHOD__ . ': failed transaction.');
    }
    #endif
    return true;
  }


  /**
   *
   */
  public static function transactionHasFailed()
  {
    $dbWriter = self::_singletonWriteConn();
    if (!$dbWriter) {
      return false;
    }
    return $dbWriter->HasFailedTrans();
  }


  /**
   *
   */
  private static function _setErrorMsg($errorMsg)
  {
    self::$_errorMsg = $errorMsg;
  }


  /**
   *
   */
  public static function getErrorMsg()
  {
    return self::$_errorMsg;
  }


  /**
   *
   */
  private static function _isReaderOnline()
  {
    $dbReader = self::_singletonReadConn();
    if (empty($dbReader)) {
      return false;
    } else {
      return true;
    }
  }


  /**
   *
   */
  private static function _isWriterOnline()
  {
    $dbWriter = self::_singletonWriteConn();
    if (empty($dbWriter)) {
      return false;
    } else {
      return true;
    }
  }


  /**
   *
   */
  public static function isConnected()
  {
    return (self::_isReaderOnline() && self::_isWriterOnline());
  }
}

?>
