<?php

/* Copyright (C) 2015 CompuGlobalHyperMegacom - All Rights Reserved
 *
 * You may use, distribute and modify this code under the
 * terms of the license, which unfortunately won't be
 * written for another century.
 *
 * You should have received a copy of the license with
 * this file. If not, please write to compuglobalhypermegacom@gmail.com@gmail.com
 */

namespace Drupal\Driver\Database\sqlsrv;

use Drupal\Driver\Database\sqlsrv\PDO\Connection;
use Symfony\Component\Yaml\Parser;

class Utils
{
    /**
     * Returns the spec for a MSSQL data type definition.
     *
     * @param string $type
     *
     * @return string
     */
    public static function GetMSSQLType($type)
    {
        $matches = array();
        if (preg_match('/^[a-zA-Z]*/', $type, $matches)) {
            return reset($matches);
        }
        return $type;
    }

    /**
     * Get some info about extensions...
     *
     * @param \ReflectionExtension $re
     *
     * @return ExtensionData
     */
    public static function ExtensionData($name)
    {
        $re = new \ReflectionExtension($name);
        $data = new ExtensionData();
        $data->Name($re->getName() ?: null);
        $data->Version($re->getVersion() ?: null);
        $data->ClassName(PHP_EOL . implode(", ", $re->getClassNames()) ?: null);
        $constants = '';
        foreach ($re->getConstants() as $key => $value) {
            $constants .= "\n{$key}:={$value}";
        }
        $data->Constants($constants);
        $data->Dependencies($re->getDependencies() ?: null);
        $data->Functions(PHP_EOL . implode(", ", array_keys($re->getFunctions())) ?: null);
        $data->IniEntries($re->getINIEntries() ?: null);
        $data->Persistent($re->isPersistent() ?: null);
        $data->Temporary($re->isTemporary() ?: null);
        return $data;
    }

    /**
     * Wether or not this is a Windows operating system.
     */
    public static function WindowsOS()
    {
        return strncasecmp(PHP_OS, 'WIN', 3) == 0;
    }

    /**
     * Deploy custom functions.
     *
     * @param \PDO $connection
     *   Connection used for deployment.
     *
     * @param boolean $redeploy
     *   Whether to redeploy existing functions, or only missing ones.
     */
    public static function DeployCustomFunctions(Connection $connection, $base_path, $redeploy = false)
    {
        $yaml = new Parser();
        $configuration = $yaml->parse(file_get_contents("$base_path/configuration.yml"));
        foreach ($configuration['functions'] as $function) {
            $name = $function['name'];
            $path = "$base_path/{$function['file']}";
            $exists = $connection->Scheme()->functionExists($name);
            if ($exists && !$redeploy) {
                continue;
            }
            if ($exists) {
                $connection->Scheme()->FunctionDrop($name);
            }
            $script = trim(static::removeUtf8Bom(file_get_contents($path)));
            $connection->query_execute($script);
        }
    }

    /**
     * Remove Byte Order Mark from UTF8 string.
     *
     * @param string $text
     * @return string
     */
    public static function removeUtf8Bom($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    /**
     * Gets the statement needed to convert one type to another
     *
     * @param string $reference
     * @param string $source_type
     * @param string $destination_type
     * @param string $destination_collation
     */
    public static function convertTypes($reference, $source_type, $destination_type, $destination_collation = null)
    {
        $field_old_expression = $reference;
        // If the destination column is text, but source column is not text
        // we need to do an explicit convert to text before collating.
        if (static::IsTextType($destination_type) && !static::IsTextType($source_type)) {
            $field_old_expression = "CONVERT($destination_type, $field_old_expression)";
        }
        // Add collation data if necessary.
        if (!empty($destination_collation)) {
            $field_old_expression .= " COLLATE $destination_collation";
        }
        $result = '';
        if (static::GetMSSQLType($destination_type) == 'varbinary') {
            switch (static::GetMSSQLType($source_type)) {
        case 'varchar':
        case 'char':
          $result = "CAST($field_old_expression AS $destination_type)";
          break;
        case 'nvarchar':
          $result = "CONVERT($destination_type, $field_old_expression)";
          break;
        default:
          $result = "CONVERT($destination_type, $field_old_expression, 1)";
      }
        } else {
            $result = "CONVERT($destination_type, $field_old_expression)";
        }
        return $result;
    }

    /**
     * If this data type contains text.
     *
     * @param string $type
     */
    public static function IsTextType($type)
    {
        return in_array(static::GetMSSQLType($type), array('char', 'varchar', 'text', 'nchar', 'nvarchar', 'ntext'));
    }

    /**
     * @param null|string $property_name
     *
     * @param null|string $level0_object_type
     *
     * @param null|string $level0_object_name
     *
     * @param null|string $level1_object_type
     *
     * @param null|string $level1_object_name
     *
     * @param null|string $level2_object_type
     *
     * @param null|string $level2_object_name
     */
    public static function GetExtendedProperty(
        $property_name = null,
        $level0_object_type = null,
        $level0_object_name = null,
        $level1_object_type = null,
        $level1_object_name = null,
        $level2_object_type = null,
        $level2_object_name = null
    )
    {
        $level_o = array("ASSEMBLY", "CONTRACT", "EVENT NOTIFICATION", "FILEGROUP", "MESSAGE TYPE", "PARTITION FUNCTION", "PARTITION SCHEME", "REMOTE SERVICE BINDING", "ROUTE", "SCHEMA", "SERVICE", "TRIGGER", "TYPE", "USER", "NULL");
        if (!empty($level0_object_type) && !in_array(strtoupper($level0_object_type), $level_o)) {
            throw new \Exception("Invalid Level0 Object Type.");
        }
        $level_1 = array("AGGREGATE", "DEFAULT", "FUNCTION", "LOGICAL FILE NAME", "PROCEDURE", "QUEUE", "RULE", "SYNONYM", "TABLE", "TYPE", "VIEW", "XML", "SCHEMA COLLECTION", "NULL");
        if (!empty($level1_object_type) && !in_array(strtoupper($level1_object_type), $level_1)) {
            throw new \Exception("Invalid Level1 Object Type.");
        }
        $level_2 = array("COLUMN", "CONSTRAINT", "EVENT NOTIFICATION", "INDEX", "PARAMETER", "TRIGGER", "NULL");
        if (!empty($level2_object_type) && !in_array(strtoupper($level2_object_type), $level_2)) {
            throw new \Exception("Invalid Level2 Object Type.");
        }
        $query = <<<EVB
  SELECT
    CONVERT(nvarchar(max), value) AS value,
    CONVERT(nvarchar(max), objtype) AS objtype,
    CONVERT(nvarchar(max), objname) AS objname,
    CONVERT(nvarchar(max), name) AS name
  FROM fn_listextendedproperty(:property_name, :level0_object_type, :level0_object_name,:level1_object_type, :level1_object_name,:level2_object_type, :level2_object_name)
EVB;
        return array(
      'query' => $query,
      'args' => array(
        ':property_name' => $property_name,
        ':level0_object_type' => $level0_object_type,
        ':level0_object_name' => $level0_object_name,
        ':level1_object_type' => $level1_object_type,
        ':level1_object_name' => $level1_object_name,
        ':level2_object_type' => $level2_object_type,
        ':level2_object_name' => $level2_object_name,
      )
    );
    }
}
