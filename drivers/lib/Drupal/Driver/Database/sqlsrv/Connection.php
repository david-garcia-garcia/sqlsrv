<?php

/**
 * @file
 * Definition of Drupal\Driver\Database\sqlsrv\Connection
 */

namespace Drupal\Driver\Database\sqlsrv;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\DatabaseException;

use PDO as PDO;
use fastcache as fastcache;

include_once 'fastcache.inc';

/**
 * @addtogroup database
 * @{
 *
 * Temporary tables: temporary table support is done by means of global temporary tables (#)
 * to avoid the use of DIRECT QUERIES. You can enable and disable the use of direct queries
 * with $conn->directQuery = TRUE|FALSE.
 * http://blogs.msdn.com/b/brian_swan/archive/2010/06/15/ctp2-of-microsoft-driver-for-php-for-sql-server-released.aspx
 *
 */
class Connection extends DatabaseConnection {

  public $bypassQueryPreprocess = FALSE;
  
  // Wether to prepare statements with
  // SQLSRV_ATTR_DIRECT_QUERY = TRUE.
  private $directQuery = FALSE;
  
  /**
   * Override of DatabaseConnection::driver().
   *
   * @status tested
   */
  public function driver() {
    return 'sqlsrv';
  }

  /**
   * Override of DatabaseConnection::databaseType().
   *
   * @status tested
   */
  public function databaseType() {
    return 'sqlsrv';
  }
  
  /**
   * Error code for Login Failed, usually happens when
   * the database does not exist.
   */
  const DATABASE_NOT_FOUND = 28000;

  /**
   * Constructs a Connection object.
   */
  public function __construct(\PDO $connection, array $connection_options) {
    // Needs to happen before parent construct.
    $this->statementClass = Statement::class;
    
    parent::__construct($connection, $connection_options);  
    
    // This driver defaults to transaction support, except if explicitly passed FALSE.
    $this->transactionSupport = !isset($connection_options['transactions']) || $connection_options['transactions'] !== FALSE;
    $this->transactionalDDLSupport = $this->transactionSupport;

    // Store connection options for future reference.
    $this->connectionOptions = &$connection_options;
    
    // Fetch the name of the user-bound schema. It is the schema that SQL Server
    // will use for non-qualified tables.
    $this->schema()->defaultSchema = $this->schema()->GetDefaultSchema();
  }

  /**
   * {@inheritdoc}
   */
  public static function open(array &$connection_options = array()) {
  
    // Build the DSN.
    $options = array();
    $options[] = 'Server=' . $connection_options['host'] . (!empty($connection_options['port']) ? ',' . $connection_options['port'] : '');
    // We might not have a database in the
    // connection options, for example, during
    // database creation in Install.
    if (!empty($connection_options['database'])) {
      $options[] = 'Database=' . $connection_options['database'];
    }

    $dsn = 'sqlsrv:' . implode(';', $options);

    // PDO Options are set at a connection level.
    // and apply to all statements.
    $connection_options['pdo'] = array();

    // Set proper error mode for all statements
    $connection_options['pdo'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

    // Set a Statement class, unless the driver opted out.
    // $connection_options['pdo'][PDO::ATTR_STATEMENT_CLASS] = array(Statement::class, array(Statement::class));

    // Actually instantiate the PDO.
    $pdo = new \PDO($dsn, $connection_options['username'], $connection_options['password'], $connection_options['pdo']);
    
    return $pdo;
  }

  /**
   * Override of PDO::prepare(): prepare a prefetching database statement.
   *
   * @status tested
   */
  public function prepare($statement, array $driver_options = array()) {
    return new Statement($this->connection, $this, $statement, $driver_options);
  }

  /**
   * Temporary override of DatabaseConnection::prepareQuery().
   *
   * @todo: remove that when DatabaseConnection::prepareQuery() is fixed to call
   *   $this->prepare() and not parent::prepare().
   *   https://www.drupal.org/node/2345451
   * @status: tested, temporary
   */
  public function prepareQuery($query, $insecure = FALSE) {
    $query = $this->prefixTables($query);

    #region PDO Options
    
    $pdo_options = array();
    
    // Set insecure options if requested so.
    if ($insecure) {
      // We have to log this, prepared statements are a security RISK.
      // watchdog('SQL Server Driver', 'An insecure query has been executed against the database. This is not critical, but worth looking into: %query', array('%query' => $query));
      // These are defined in class Connection.
      // This PDO options are INSECURE, but will overcome the following issues:
      // (1) Duplicate placeholders
      // (2) > 2100 parameter limit
      // (3) Using expressions for group by with parameters are not detected as equal.
      // This options are not applied by default, they are just stored in the connection
      // options and applied when needed. See {Statement} class.

      // We ask PDO to perform the placeholders replacement itself because
      // SQL Server is not able to detect duplicated placeholders in
      // complex statements.
      // E.g. This query is going to fail because SQL Server cannot
      // detect that length1 and length2 are equals.
      // SELECT SUBSTRING(title, 1, :length1)
      // FROM node
      // GROUP BY SUBSTRING(title, 1, :length2
      // This is only going to work in PDO 3 but doesn't hurt in PDO 2.
      // The security of parameterized queries is not in effect when you use PDO::ATTR_EMULATE_PREPARES => true.
      // Your application should ensure that the data that is bound to the parameter(s) does not contain malicious
      // Transact-SQL code.
      // Never use this when you need special column binding.
      // THIS ONLY WORKS IF SET AT THE STATEMENT LEVEL.
      $pdo_options[PDO::ATTR_EMULATE_PREPARES] = TRUE;
    }

    // We run the statements in "direct mode" because the way PDO prepares
    // statement in non-direct mode cause temporary tables to be destroyed
    // at the end of the statement.
    // If you are using the PDO_SQLSRV driver and you want to execute a query that 
    // changes a database setting (e.g. SET NOCOUNT ON), use the PDO::query method with 
    // the PDO::SQLSRV_ATTR_DIRECT_QUERY attribute.
    // http://blogs.iis.net/bswan/archive/2010/12/09/how-to-change-database-settings-with-the-pdo-sqlsrv-driver.aspx
    // If a query requires the context that was set in a previous query, 
    // you should execute your queries with PDO::SQLSRV_ATTR_DIRECT_QUERY set to True. 
    // For example, if you use temporary tables in your queries, PDO::SQLSRV_ATTR_DIRECT_QUERY must be set 
    // to True.
    if ($this->directQuery) {
      $pdo_options[PDO::SQLSRV_ATTR_DIRECT_QUERY] = TRUE;
    }
    
    // It creates a cursor for the query, which allows you to iterate over the result set 
    // without fetching the whole result at once. A scrollable cursor, specifically, is one that allows 
    // iterating backwards.
    // https://msdn.microsoft.com/en-us/library/hh487158%28v=sql.105%29.aspx
    $pdo_options[PDO::ATTR_CURSOR] = PDO::CURSOR_SCROLL;
    
    // Lets you access rows in any order. Creates a client-side cursor query.
    $pdo_options[PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE] = PDO::SQLSRV_CURSOR_BUFFERED;
    
    #endregion

    // Call our overriden prepare.
    return $this->PDOPrepare($query, $pdo_options);
  }
  
  /**
   * Internal function: prepare a query by calling PDO directly.
   *
   * This function has to be public because it is called by other parts of the
   * database layer, but do not call it directly, as you risk locking down the
   * PHP process.
   */
  public function PDOPrepare($query, array $options = array()) {

    // Preprocess the query.
    if (!$this->bypassQueryPreprocess) {
      $query = $this->preprocessQuery($query);
    }
    
    return parent::prepare($query, $options);
  }

  /**
   * This is the original replacement regexp from Microsoft.
   *
   * We could probably simplify it a lot because queries only contain
   * placeholders when we modify them.
   *
   * NOTE: removed 'escape' from the list, because it explodes
   * with LIKE xxx ESCAPE yyy syntax.
   */
  const RESERVED_REGEXP = '/\G
    # Everything that follows a boundary that is not : or _.
    \b(?<![:\[_])(?:
      # Any reserved words, followed by a boundary that is not an opening parenthesis.
      (action|admin|alias|any|are|array|at|begin|boolean|class|commit|contains|current|data|date|day|depth|domain|external|file|full|function|get|go|host|input|language|last|less|local|map|min|module|new|no|object|old|open|operation|parameter|parameters|path|plan|prefix|proc|public|ref|result|returns|role|row|rows|rule|save|search|second|section|session|size|state|statistics|temporary|than|time|timestamp|tran|translate|translation|trim|user|value|variable|view|without)
      (?!\()
      |
      # Or a normal word.
      ([a-z]+)
    )\b
    |
    \b(
      [^a-z\'"\\\\]+
    )\b
    |
    (?=[\'"])
    (
      "  [^\\\\"] * (?: \\\\. [^\\\\"] *) * "
      |
      \' [^\\\\\']* (?: \\\\. [^\\\\\']*) * \'
    )
  /Six';

  /**
   * This method gets called between 3,000 and 10,000 times
   * on cold caches. Make sure it is simple and fast.
   *
   * @param mixed $matches 
   * @return mixed
   */
  protected function replaceReservedCallback($matches) {
    if ($matches[1] !== '') {
      // Replace reserved words. We are not calling
      // quoteIdentifier() on purpose.
      return '[' . $matches[1] . ']';
    }
    // Let other value passthru.
    // by the logic of the regex above, this will always be the last match.
    return end($matches);
  }

  public function quoteIdentifier($identifier) {
    return '[' . $identifier .']';
  }

  public function escapeField($field) {
    if ($cache = fastcache::cache_get($field, 'schema_escapeField')) {
      return $cache->data;
    }
    if (strlen($field) > 0) {
      $result = implode('.', array_map(array($this, 'quoteIdentifier'), explode('.', preg_replace('/[^A-Za-z0-9_.]+/', '', $field))));
    }
    else {
      $result = '';
    }
    fastcache::cache_set($field, $result, 'schema_escapeField');
    return $result;
  }

  public function quoteIdentifiers($identifiers) {
    return array_map(array($this, 'quoteIdentifier'), $identifiers);
  }

  /**
   * Override of DatabaseConnection::escapeLike().
   */
  public function escapeLike($string) {
    return addcslashes($string, '\%_[]');
  }

  /**
   * Override of DatabaseConnection::queryRange().
   */
  public function queryRange($query, $from, $count, array $args = array(), array $options = array()) {
    $query = $this->addRangeToQuery($query, $from, $count);
    return $this->query($query, $args, $options);
  }

  private static $temporary_tables = array();
  
  /**
   * Generates a temporary table name. Because we are using
   * global temporary tables, these are visible between
   * connections so we need to make sure that their
   * names are as unique as possible to prevent collisions.
   *
   * @return
   *   A table name.
   */
  protected function generateTemporaryTableName() {
    static $temp_key;
    if (!isset($temp_key)) {
      $temp_key = strtoupper(md5(uniqid(rand(), true)));
    }
    return "db_temp_" . $this->temporaryNameIndex++ . '_' . $temp_key;
  }
  
  /**
   * Override of DatabaseConnection::queryTemporary().
   *
   * @status tested
   */
  public function queryTemporary($query, array $args = array(), array $options = array()) {
    // Generate a new GLOBAL temporary table name and protect it from prefixing.
    // SQL Server requires that temporary tables to be non-qualified.
    $tablename = '##' . $this->generateTemporaryTableName();
    $prefixes = $this->prefixes;
    $prefixes[$tablename] = '';
    $this->setPrefix($prefixes);

    // Replace SELECT xxx FROM table by SELECT xxx INTO #table FROM table.
    $query = preg_replace('/^SELECT(.*?)FROM/is', 'SELECT$1 INTO ' . $tablename . ' FROM', $query);
    $this->query($query, $args, $options);
    
    return $tablename;
  }

  
  /**
   * {@inheritdoc}
   *
   * This method is overriden to manage the insecure (EMULATE_PREPARE)
   * behaviour to prevent some compatibility issues with SQL Server.
   */
  public function query($query, array $args = array(), $options = array()) {

    // Use default values if not already set.
    $options += $this->defaultOptions();

    try {
      // We allow either a pre-bound statement object or a literal string.
      // In either case, we want to end up with an executed statement object,
      // which we pass to PDOStatement::execute.
      if ($query instanceof StatementInterface) {
        $stmt = $query;
        $stmt->execute(NULL, $options);
      }
      else {
        $this->expandArguments($query, $args);
        $insecure = isset($options['insecure']) ? $options['insecure'] : FALSE;
        // Try to detect duplicate place holders, this check's performance
        // is not a good addition to the driver, but does a good job preventing
        // duplicate placeholder errors.
        $argcount = count($args);
        if ($insecure === TRUE || $argcount >= 2100 || ($argcount != substr_count($query, ':'))) {
          $insecure = TRUE;
        }
        $stmt = $this->prepareQuery($query, $insecure);
        $stmt->execute($args, $options);
      }

      // Depending on the type of query we may need to return a different value.
      // See DatabaseConnection::defaultOptions() for a description of each
      // value.
      switch ($options['return']) {
        case Database::RETURN_STATEMENT:
          return $stmt;
        case Database::RETURN_AFFECTED:
          $stmt->allowRowCount = TRUE;
          return $stmt->rowCount();
        case Database::RETURN_INSERT_ID:
          return $this->connection->lastInsertId();
        case Database::RETURN_NULL:
          return;
        default:
          throw new \PDOException('Invalid return directive: ' . $options['return']);
      }
    }
    catch (\PDOException $e) {
      if ($options['throw_exception']) {
        // Wrap the exception in another exception, because PHP does not allow
        // overriding Exception::getMessage(). Its message is the extra database
        // debug information.
        if ($stmt instanceof StatementInterface) {
          $query_string = $stmt->getQueryString();
        }
        else {
          $query_string = $query;
        }
        $message = $e->getMessage() . ": " . $query_string . "; " . print_r($args, TRUE);
        // Match all SQLSTATE 23xxx errors.
        if (substr($e->getCode(), -6, -3) == '23') {
          $exception = new IntegrityConstraintViolationException($message, $e->getCode(), $e);
        }
        else {
          $exception = new DatabaseExceptionWrapper($message, 0, $e);
        }

        throw $exception;
      }
      return NULL;
    }
  }

  /**
   * Internal function: massage a query to make it compliant with SQL Server.
   */
  public function preprocessQuery($query) {
    // Generate a cache signature for this query.
    $query_signature = md5($query);

    // Try to load query from query cache.
    if ($cache = fastcache::cache_get($query_signature, 'query')) {
      return $cache->data;
    }

    // Load the hit cache, we only want the query cache
    // to store the most hited queries.
    $hits = 0;
    if ($cache = fastcache::cache_get($query_signature, 'query_hits')) {
      $hits = $cache->data;
    }
    $hits++;
    fastcache::cache_set($query_signature, $hits, 'query_hits');

    // Force quotes around some SQL Server reserved keywords.
    if (preg_match('/^SELECT/i', $query)) {
      $query = preg_replace_callback(self::RESERVED_REGEXP, array($this, 'replaceReservedCallback'), $query);
    }

    // Last chance to modify some SQL Server-specific syntax.
    $replacements = array(
      // Normalize SAVEPOINT syntax to the SQL Server one.
      '/^SAVEPOINT (.*)$/' => 'SAVE TRANSACTION $1',
      '/^ROLLBACK TO SAVEPOINT (.*)$/' => 'ROLLBACK TRANSACTION $1',
      // SQL Server doesn't need an explicit RELEASE SAVEPOINT.
      // Run a non-operaiton query to avoid a fatal error
      // when no query is runned.
      '/^RELEASE SAVEPOINT (.*)$/' => 'SELECT 1 /* $0 */',
      // TODO: For improved compatiblity with MySQL
      // we should create a StoredProcedure that
      // maps sp_who to a MySQL compatible approach.
      // http://stackoverflow.com/questions/2234691/sql-server-filter-output-of-sp-who2
      '/^SHOW PROCESSLIST$/' => 'EXEC sp_who',
      // List all table names
      '/^SHOW TABLES$/' => 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = \'BASE TABLE\'',
      // List all table and view names.
      '/^SHOW FULL TABLES$/' => 'SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES',
    );
    
    // Add prefixes to Drupal-specific functions.
    foreach ($this->schema()->DrupalSpecificFunctions() as $function) {
      $replacements['/\b(?<![:.])(' . preg_quote($function) . ')\(/i'] =  $this->schema()->defaultSchema . '.$1(';
    }
    
    // Rename some functions.
    $funcs = array(
      'LENGTH' => 'LEN',
      'POW' => 'POWER',
    );
    
    foreach ($funcs as $function => $replacement) {
      $replacements['/\b(?<![:.])(' . preg_quote($function) . ')\(/i'] = $replacement . '('; 
    }
    
    // Replace the ANSI concatenation operator with SQL Server poor one.
    $replacements['/\|\|/'] =  '+';
    
    // Now do all the replacements at once.
    $query = preg_replace(array_keys($replacements), array_values($replacements), $query);

    // The minimum amount of hits for a query
    // to get stored in query cache.
    $minimum_hist = 50;
    if ($hits >= $minimum_hist) {
      fastcache::cache_set($query_signature, $query, 'query');
    } 

    return $query;
  }

  /**
   * Internal function: add range options to a query.
   *
   * This cannot be set protected because it is used in other parts of the
   * database engine.
   *
   * @status tested
   */
  public function addRangeToQuery($query, $from, $count) {
    if ($from == 0) {
      // Easy case: just use a TOP query if we don't have to skip any rows.
      $query = preg_replace('/^\s*SELECT(\s*DISTINCT)?/Dsi', 'SELECT$1 TOP(' . $count . ')', $query);
    }
    else {
      // More complex case: use a TOP query to retrieve $from + $count rows, and
      // filter out the first $from rows using a window function.
      $query = preg_replace('/^\s*SELECT(\s*DISTINCT)?/Dsi', 'SELECT$1 TOP(' . ($from + $count) . ') ', $query);
      $query = '
        SELECT * FROM (
          SELECT sub2.*, ROW_NUMBER() OVER(ORDER BY sub2.__line2) AS __line3 FROM (
            SELECT 1 AS __line2, sub1.* FROM (' . $query . ') AS sub1
          ) as sub2
        ) AS sub3
        WHERE __line3 BETWEEN ' . ($from + 1) . ' AND ' . ($from + $count);
    }

    return $query;
  }

  public function mapConditionOperator($operator) {
    // SQL Server doesn't need special escaping for the \ character in a string
    // literal, because it uses '' to escape the single quote, not \'. Sadly
    // PDO doesn't know that and interpret \' as an escaping character. We
    // use a function call here to be safe.
    static $specials = array(
    'LIKE' => array('postfix' => " ESCAPE CHAR(92)"),
    'NOT LIKE' => array('postfix' => " ESCAPE CHAR(92)"),
    );
    return isset($specials[$operator]) ? $specials[$operator] : NULL;
  }

  /**
   * Override of DatabaseConnection::nextId().
   *
   * @status tested
   */
  public function nextId($existing = 0) {
    // If an exiting value is passed, for its insertion into the sequence table.
    if ($existing > 0) {
      try {
        $this->query('SET IDENTITY_INSERT {sequences} ON; INSERT INTO {sequences} (value) VALUES(:existing); SET IDENTITY_INSERT {sequences} OFF', array(':existing' => $existing));
      }
      catch (DatabaseException $e) {
        // Doesn't matter if this fails, it just means that this value is already
        // present in the table.
      }
    }

    return $this->query('INSERT INTO {sequences} DEFAULT VALUES', array(), array('return' => Database::RETURN_INSERT_ID));
  }

  /**
   * Override DatabaseConnection::escapeTable().
   *
   * @status needswork
   */
  public function escapeTable($table) {
    if ($cache = fastcache::cache_get($table, 'schema_escapeTable')) {
      return $cache->data;
    }
    
    // Rescue the # prefix from the escaping.
    $is_temporary = $table[0] == '#';
    $is_temporary_global = $is_temporary && isset($table[1]) && $table[0] == '#';
    
    // Any temporary table prefix will be removed.
    $result = preg_replace('/[^A-Za-z0-9_.]+/', '', $table);
    
    // Restore the temporary prefix.
    if ($is_temporary) {
      if ($is_temporary_global) {
        $result = '##' . $result;
      }
      else {
        $result = '#' . $result;
      }
    }
    else {
      // Only cache this for non temporary tables.
      fastcache::cache_set($table, $result, 'schema_escapeTable');
    }

    return $result;
  }
  
  /**
   * Overrides \Drupal\Core\Database\Connection::createDatabase().
   *
   * @param string $database
   *   The name of the database to create.
   *
   * @throws \Drupal\Core\Database\DatabaseNotFoundException
   */
  public function createDatabase($database) {
    // Escape the database name.
    $database = Database::getConnection()->escapeDatabase($database);

    try {
      // Create the database and set it as active.
      $this->connection->exec("CREATE DATABASE $database COLLATE " . Schema::DEFAULT_COLLATION_CI);
    }
    catch (DatabaseException $e) {
      throw new DatabaseNotFoundException($e->getMessage());
    }
  }
}

/**
 * @} End of "addtogroup database".
 */