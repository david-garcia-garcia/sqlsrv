<?php
/**
 * @file
 * Definition of Drupal\Driver\Database\sqlsrv\Statement
 */

namespace Drupal\Driver\Database\sqlsrv;

use Drupal\Core\Database\RowCountException;
use Drupal\Core\Database\StatementInterface;
use PDO as PDO;

class Statement extends \Drupal\Driver\Database\sqlsrv\PDO\Statement implements StatementInterface
{

  /**
   * Is rowCount() execution allowed.
   *
   * @var bool
   */
    public $allowRowCount = false;

    /**
     * Reference to the database connection object for this statement.
     *
     * The name $dbh is inherited from \PDOStatement.
     *
     * @var \Drupal\Core\Database\Connection
     */
    public $dbh;

    protected function __construct(Connection $dbh)
    {
        $this->allowRowCount = true;
        $this->dbh = $dbh;
        $this->setFetchMode(\PDO::FETCH_OBJ);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($args = [], $options = [])
    {
        if (isset($options['fetch'])) {
            if (is_string($options['fetch'])) {
                // Default to an object. Note: db fields will be added to the object
                // before the constructor is run. If you need to assign fields after
                // the constructor is run, see http://drupal.org/node/315092.
                $this->setFetchMode(PDO::FETCH_CLASS, $options['fetch']);
            } else {
                $this->setFetchMode($options['fetch']);
            }
        }
        $logger = $this->dbh->getLogger();
        $query_start = microtime(true);
        // If parameteres have already been binded
        // to the statement and we pass an empty array here
        // we will get a PDO Exception.
        if (empty($args)) {
            $args = null;
        }
        // Execute the query. Bypass parent override
        // and directly call PDOStatement implementation.
        $return = $this->doExecute($args);
        // Bind column types properly.
        $this->fixColumnBindings();
        if (!empty($logger)) {
            $query_end = microtime(true);
            $logger->log($this, $args, $query_end - $query_start);
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function doExecute($args = [], $options = [])
    {
        if (isset($options['fetch'])) {
            if (is_string($options['fetch'])) {
                // \PDO::FETCH_PROPS_LATE tells __construct() to run before properties
                // are added to the object.
                $this->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $options['fetch']);
            } else {
                $this->setFetchMode($options['fetch']);
            }
        }
        $logger = $this->dbh->getLogger();
        if (!empty($logger)) {
            $query_start = microtime(true);
        }
        $return = parent::execute($args);
        if (!empty($logger)) {
            $query_end = microtime(true);
            $logger->log($this, $args, $query_end - $query_start);
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCol($index = 0)
    {
        return $this->fetchAll(\PDO::FETCH_COLUMN, $index);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssoc($key, $fetch = null)
    {
        $return = [];
        if (isset($fetch)) {
            if (is_string($fetch)) {
                $this->setFetchMode(\PDO::FETCH_CLASS, $fetch);
            } else {
                $this->setFetchMode($fetch);
            }
        }
        foreach ($this as $record) {
            $record_key = is_object($record) ? $record->$key : $record[$key];
            $return[$record_key] = $record;
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllKeyed($key_index = 0, $value_index = 1)
    {
        $return = [];
        $this->setFetchMode(\PDO::FETCH_NUM);
        foreach ($this as $record) {
            $return[$record[$key_index]] = $record[$value_index];
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchField($index = 0)
    {
        // Call \PDOStatement::fetchColumn to fetch the field.
        return $this->fetchColumn($index);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc()
    {
        // Call \PDOStatement::fetch to fetch the row.
        return $this->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        // SELECT query should not use the method.
        if ($this->allowRowCount) {
            return parent::rowCount();
        } else {
            throw new RowCountException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($mode, $a1 = null, $a2 = [])
    {
        // Call \PDOStatement::setFetchMode to set fetch mode.
        // \PDOStatement is picky about the number of arguments in some cases so we
        // need to be pass the exact number of arguments we where given.
        switch (func_num_args()) {
      case 1:
        return parent::setFetchMode($mode);
      case 2:
        return parent::setFetchMode($mode, $a1);
      case 3:
      default:
        return parent::setFetchMode($mode, $a1, $a2);
    }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($mode = null, $column_index = null, $constructor_arguments = null)
    {
        // Call \PDOStatement::fetchAll to fetch all rows.
        // \PDOStatement is picky about the number of arguments in some cases so we
        // need to be pass the exact number of arguments we where given.
        switch (func_num_args()) {
      case 0:
        return parent::fetchAll();
      case 1:
        return parent::fetchAll($mode);
      case 2:
        return parent::fetchAll($mode, $column_index);
      case 3:
      default:
        return parent::fetchAll($mode, $column_index, $constructor_arguments);
    }
    }
}
