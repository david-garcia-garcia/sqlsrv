<?php

namespace Drupal\sqlsrv\Indexes;

use Drupal\Core\File\FileSystem;
use Drupal\Driver\Database\sqlsrv\Connection;

/**
 * Default indexes to be deployed for CORE functionality.
 */
class IndexManager
{

  /**
   * Summary of $connection
   *
   * @var Connection
   */
  private $connection;


  /**
   * Path to the index folder.
   *
   * @var string
   */
  private $path;

  /**
   * Creates an instance of DefaultIndexes with a all defined indexes.
   *
   * @param Connection $connection
   *   Database connection.
   * @param string $path
   *   Path to the index folder.
   */
  public function __construct(Connection $connection, $path)
  {
    $this->connection = $connection;
    $this->path = $path;
  }

  /**
   * Deploy all missing indexes.
   *
   * @return void
   * @throws \Exception
   *
   */
  public function DeployNew()
  {

    /**
     * @var FileSystem $fileSystem
     */
    $fileSystem = \Drupal::service('file_system');

    // Scan the Implementations folder
    $dir = $this->path;
    $files = $fileSystem->scanDirectory($dir, '/.*\.sql$/');

    foreach ($files as $file) {
      $index = new Index($file->uri);
      $table = $this->connection->prefixTable($index->GetTable());
      $name = $index->GetName();

      $schema = $this->connection->Scheme();

      if (!$schema->indexExists($table, $name) && $schema->TableExists($table)) {
        try {
          // TODO: Consider the need to prefix the tables...
          $this->connection->GetConnection()->query_execute($index->GetCode());
        } catch (\Exception $e) {
          \Drupal::logger('MSSQL')->notice("Could not deploy index $name for table $table");
        }
      }
    }
  }
}
