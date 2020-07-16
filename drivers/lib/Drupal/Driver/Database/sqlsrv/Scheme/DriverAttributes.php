<?php

namespace Drupal\Driver\Database\sqlsrv\Scheme;

use Drupal\Driver\Database\sqlsrv\PDO\Connection;

/**
 * Type class for the driver attributes.
 *
 * https://msdn.microsoft.com/en-us/library/ff628181(v=sql.105).aspx
 */
class DriverAttributes
{

  /** @var Connection */
    protected $connection;

    /**
     * Flattened array of attributes.
     *
     * @var string[]
     */
    protected $attributes;

    public function __construct(Connection $cnn)
    {
        $this->connection = $cnn;
        $this->intiailize();
    }

    public function getAll()
    {
        return array_combine(array_keys($this->attributes), array_column($this->attributes, 'pretty'));
    }

    /**
     * Gets all available attributes as a flattened
     * key-value array.
     */
    private function intiailize()
    {

    // These are the native attributes...
        $atts = [
      'ATTR_ORACLE_NULLS' => ['code' => \PDO::ATTR_ORACLE_NULLS],
      'ATTR_CASE' => ['code' => \PDO::ATTR_CASE],
      'ATTR_CLIENT_VERSION' => ['code' => \PDO::ATTR_CLIENT_VERSION],
      'ATTR_SERVER_INFO' => ['code' => \PDO::ATTR_SERVER_INFO],
      'ATTR_ERRMODE' => ['code' => \PDO::ATTR_ERRMODE],
      'SQLSRV_ATTR_ENCODING' => ['code' => \PDO::SQLSRV_ATTR_ENCODING],
      'SQLSRV_ATTR_DIRECT_QUERY' => ['code' => \PDO::SQLSRV_ATTR_DIRECT_QUERY],
      'SQLSRV_ATTR_CLIENT_BUFFER_MAX_KB_SIZE' => ['code' => \PDO::SQLSRV_ATTR_CLIENT_BUFFER_MAX_KB_SIZE],
      'SQLSRV_ATTR_FETCHES_NUMERIC_TYPE' => ['code' => \PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE],
      'ATTR_STRINGIFY_FETCHES' => ['code' => \PDO::ATTR_STRINGIFY_FETCHES],
      'ATTR_STATEMENT_CLASS' => ['code' => \PDO::ATTR_STATEMENT_CLASS]
    ];

        $result = [];

        // Flatten the array....
        foreach ($atts as $name => $spec) {
            $value = $this->connection->getAttribute($spec['code']);
            if (!is_array($value)) {
                $value = ['' => $value];
            }
            foreach ($value as $key => $v) {
                if (!is_scalar($v)) {
                    continue;
                }
                $n = $key ? ($name . ".$key") : $name;
                $result[$n] =  ['value' => $v, 'pretty' => $v];
            }
        }

        // Theses are the expanded ones...
        $prettify = [
      'ATTR_CASE' => ['prettify' => [$this, 'pdoFriendlyNameCase']],
      'ATTR_ERRMODE' => ['prettify' => [$this, 'pdoFriendlyNameError']],
      'SQLSRV_ATTR_ENCODING' => ['prettify' => [$this, 'pdoFriendlyNameEncoding']],
      'ATTR_STRINGIFY_FETCHES' => ['prettify' => [$this, 'pdoFriendlyNameBoolean']],
      'ATTR_STRINGIFY_FETCHES' => ['prettify' => [$this, 'pdoFriendlyNameBoolean']],
      'SQLSRV_ATTR_DIRECT_QUERY' => ['prettify' => [$this, 'pdoFriendlyNameBoolean']],
      'SQLSRV_ATTR_FETCHES_NUMERIC_TYPE' => ['prettify' => [$this, 'pdoFriendlyNameBoolean']],
      'ATTR_ORACLE_NULLS' => ['prettify' => [$this, 'pdoFriendlyNameBoolean']],
      'SQLSRV_ATTR_CLIENT_BUFFER_MAX_KB_SIZE' => ['prettify' => [$this, 'pdoFriendlySizeKb']],

    ];

        foreach ($prettify as $key => $spec) {
            $result[$key]['pretty'] = call_user_func($spec['prettify'], $result[$key]['value']);
        }


        $this->attributes = $result;
    }

    public function pdoFriendlyNameCase($const)
    {
        switch ($const) {
      case \PDO::CASE_LOWER:
        return 'PDO::CASE_LOWER';
      case \PDO::CASE_NATURAL:
        return 'PDO::CASE_NATURAL';
      case \PDO::CASE_UPPER:
        return 'PDO::CASE_UPPER';
      default:
        return $const;
    }
    }

    public function pdoFriendlyNameError($const)
    {
        switch ($const) {
      case \PDO::ERRMODE_SILENT:
        return 'PDO::ERRMODE_SILENT';
      case \PDO::ERRMODE_WARNING:
        return 'PDO::ERRMODE_WARNING';
      case \PDO::ERRMODE_EXCEPTION:
        return 'PDO::ERRMODE_EXCEPTION';
      default:
        return $const;
    }
    }

    public function pdoFriendlyNameEncoding($const)
    {
        switch ($const) {
      case \PDO::SQLSRV_ENCODING_UTF8:
        return 'PDO::SQLSRV_ENCODING_UTF8';
      case \PDO::SQLSRV_ENCODING_SYSTEM:
        return 'PDO::SQLSRV_ENCODING_SYSTEM';
      default:
        return $const;
    }
    }

    public function pdoFriendlyNameBoolean($const)
    {
        return $const ? 'yes' : 'No';
    }

    public function pdoFriendlySizeKb($const)
    {
        return format_size($const * 1024);
    }
}
