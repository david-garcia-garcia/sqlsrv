<?php

namespace Drupal\Driver\Database\sqlsrv\Scheme;

use Drupal\Driver\Database\sqlsrv\Component\SettingsManager;
use Drupal\Driver\Database\sqlsrv\PDO\Connection;

class UserOptions extends SettingsManager
{

  /**
   * Get an instance of UserOptions
   *
   * @param Connection $connection
   *
   * @return UserOptions
   */
    public static function Get(Connection $connection)
    {
        $data = new UserOptions();

        $result = [];

        switch ($connection->Scheme()->EngineVersion()->Edition()) {

      case 'SQL Azure':

        $result['textsize'] = $connection->query_execute("SELECT @@TEXTSIZE AS [textsize]")->fetchColumn();
        $result['language'] = $connection->query_execute("SELECT @@LANGUAGE AS [language]")->fetchColumn();
        $result['dateformat'] = $connection->query_execute("SELECT [dateformat] FROM [sys].[syslanguages] WHERE [langid] = @@LANGID")->fetchColumn();
        $result['datefirst'] = $connection->query_execute("SELECT @@DATEFIRST AS [datefirst]")->fetchColumn();
        $result['lock_timeout'] = $connection->query_execute("SELECT @@lock_timeout AS [lock_timeout]")->fetchColumn();

        $query = <<<EOT
        SELECT CASE transaction_isolation_level
        WHEN 0 THEN 'Unspecified'
        WHEN 1 THEN 'Read Uncomitted'
        WHEN 2 THEN 'Read comitted'
        WHEN 3 THEN 'Repeatable'
        WHEN 4 THEN 'Serializable'
        WHEN 5 THEN 'Snapshot' END AS TRANSACTION_ISOLATION_LEVEL
        FROM sys.dm_exec_sessions
        where session_id = @@SPID
EOT;

        $result['isolation level'] = $connection->query_execute($query)->fetchColumn();

        break;

      default:

        $result = $connection->query_execute('DBCC UserOptions')->fetchAllKeyed();

        // These are not available on AZURE ?
        $data->QuotedIdentifier($result['quoted_identifier']);
        $data->AnsiNullDefaultOn($result['ansi_null_dflt_on']);
        $data->AnsiWarnings($result['ansi_warnings']);
        $data->AnsiPadding($result['ansi_padding']);
        $data->AnsiNulls($result['ansi_nulls']);
        $data->ConcatNullYieldsNull($result['concat_null_yields_null']);
        break;
    }

        // These are common to both MSSQL and Azure
        $data->TextSize($result['textsize']);
        $data->Language($result['language']);
        $data->DateFormat($result['dateformat']);
        $data->DateFirst($result['datefirst']);
        $data->LockTimeout($result['lock_timeout']);
        $data->IsolationLevel($result['isolation level']);

        return $data;
    }

    /**
     * @return string
     */
    public function TextSize()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args());
    }

    /**
     * @return string
     */
    public function Language()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args());
    }

    /**
     * @return string
     */
    public function DateFormat()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args());
    }

    /**
     * @return string
     */
    public function DateFirst()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function LockTimeout()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function QuotedIdentifier()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function Arithabort()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function AnsiNullDefaultOn()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function AnsiWarnings()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function AnsiPadding()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function AnsiNulls()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function ConcatNullYieldsNull()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }

    public function IsolationLevel()
    {
        return parent::CallMethod(__FUNCTION__, array(), func_get_args(), null);
    }
}
