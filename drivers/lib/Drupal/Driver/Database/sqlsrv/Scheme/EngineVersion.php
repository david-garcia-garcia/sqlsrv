<?php

namespace Drupal\Driver\Database\sqlsrv\Scheme;

use Drupal\Driver\Database\sqlsrv\Component\SettingsManager;

use Drupal\Driver\Database\sqlsrv\PDO\Connection;

class EngineVersion extends SettingsManager {

  /**
   * Get an instance of EngineVersion
   *
   * @param Connection $cnn
   *   The connection to use
   *
   * @return EngineVersion
   */
  public static function Get(Connection $cnn) {
    $data = $cnn
    ->query_execute(<<< EOF
    SELECT CONVERT (varchar,SERVERPROPERTY('productversion')) AS VERSION,
    CONVERT (varchar,SERVERPROPERTY('productlevel')) AS LEVEL,
    CONVERT (varchar,SERVERPROPERTY('edition')) AS EDITION,
    CONVERT (varchar,SERVERPROPERTY('EngineEdition')) AS ENGINEEDITION
EOF
    )->fetchAssoc();

    $result = new EngineVersion();
    $result->Version($data['VERSION']);
    $result->Level($data['LEVEL']);
    $result->Edition($data['EDITION']);
    $result->EngineEdition($data['ENGINEEDITION']);

    return $result;
  }

  public function Version() {
    return parent::CallMethod(__FUNCTION__, [], func_get_args());
  }

  public function Level() {
    return parent::CallMethod(__FUNCTION__, [], func_get_args());
  }

  public function Edition() {
    return parent::CallMethod(__FUNCTION__, [], func_get_args());
  }

  public function EngineEdition() {
    return parent::CallMethod(__FUNCTION__, [], func_get_args());
  }
}
