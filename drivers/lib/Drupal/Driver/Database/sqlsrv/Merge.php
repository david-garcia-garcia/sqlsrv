<?php

/**
 * @file
 * Definition of Drupal\Driver\Database\sqlsrv\Merge
 */

namespace Drupal\Driver\Database\sqlsrv;

use Drupal\Core\Database\Query\Merge as QueryMerge;

use Drupal\Driver\Database\sqlsrv\Utils as DatabaseUtils;

use Drupal\Driver\Database\sqlsrv\TransactionIsolationLevel as DatabaseTransactionIsolationLevel;
use Drupal\Driver\Database\sqlsrv\TransactionScopeOption as DatabaseTransactionScopeOption;
use Drupal\Driver\Database\sqlsrv\TransactionSettings as DatabaseTransactionSettings;

use Drupal\Core\Database\Query\InvalidMergeQueryException;

use PDO as PDO;
use Exception as Exception;
use PDOStatement as PDOStatement;

class Merge extends QueryMerge {

  public function execute() {

    if (!count($this->condition)) {
      throw new InvalidMergeQueryException(t('Invalid merge query: no conditions'));
    }

    // Keep a reference to the blobs.
    $blobs = array();

    // Fetch the list of blobs and sequences used on that table.
    $columnInformation = $this->connection->schema()->queryColumnInformation($this->table);

    // Find out if there is an identity field set in this insert.
    $this->setIdentity = !empty($columnInformation['identity']) && in_array($columnInformation['identity'], array_keys($this->insertFields));

    // Initialize placeholder count.
    $max_placeholder = 0;
    
    // Build the query.
    $stmt = $this->connection->prepareQuery((string)$this);

    // Build the arguments: 1. condition.
    $arguments = $this->condition->arguments();
    DatabaseUtils::BindArguments($stmt, $arguments);

    // 2. When matched part.
    $fields = $this->updateFields;
    DatabaseUtils::BindExpressions($stmt, $this->expressionFields, $fields);
    DatabaseUtils::BindValues($stmt, $fields, $blobs, ':db_merge_placeholder_', $columnInformation, $max_placeholder);

    // 3. When not matched part.
    DatabaseUtils::BindValues($stmt, $this->insertFields, $blobs, ':db_merge_placeholder_', $columnInformation, $max_placeholder);

    // 4. Run the query, this will return UPDATE or INSERT
    $stmt->execute();
    $result = NULL;
    foreach ($stmt as $value) {
      $result = $value->{'$action'};
    }

    switch($result) {
      case 'UPDATE':
        return static::STATUS_UPDATE;
      case 'INSERT':
        return static::STATUS_INSERT;
      default:
        throw new InvalidMergeQueryException(t('Invalid merge query: no results.'));
    } 

  }


  public function __toString() {
    // Initialize placeholder count.
    $max_placeholder = 0;
    $max_placeholder_conditions =  0;

    $query = array();

    // Enable direct insertion to identity columns if necessary.
    if (!empty($this->setIdentity)) {
      $query[] = 'SET IDENTITY_INSERT {' . $this->table . '} ON;';
    }

    $query[] = 'MERGE INTO {' . $this->table . '} _target';

    // 1. Condition part.
    $this->condition->compile($this->connection, $this);
    $key_conditions = array();
    $template_item = array();
    $conditions = $this->conditions();
    unset($conditions['#conjunction']);
    foreach ($conditions as $condition) {
      $key_conditions[] = '_target.' . $this->connection->escapeField($condition['field']) . ' = ' . '_source.' . $this->connection->escapeField($condition['field']);
      $template_item[] = ':db_condition_placeholder_' . $max_placeholder_conditions++ . ' AS ' . $this->connection->escapeField($condition['field']);
    }
    $query[] = 'USING (SELECT ' . implode(', ', $template_item) . ') _source ' . PHP_EOL . 'ON ' . implode(' AND ', $key_conditions);

    // 2. "When matched" part.
    // Expressions take priority over literal fields, so we process those first
    // and remove any literal fields that conflict.
    $fields = $this->updateFields;
    $update_fields = array();
    foreach ($this->expressionFields as $field => $data) {
      $update_fields[] = $field . '=' . $data['expression'];
      unset($fields[$field]);
    }

    foreach ($fields as $field => $value) {
      $update_fields[] = $field . '=:db_merge_placeholder_' . ($max_placeholder++);
    }

    if (!empty($update_fields)) {
      $query[] = 'WHEN MATCHED THEN UPDATE SET ' . implode(', ', $update_fields);
    }

    // 3. "When not matched" part.
    if ($this->insertFields) {
      // Build the list of placeholders.
      $placeholders = array();
      for ($i = 0; $i < count($this->insertFields); ++$i) {
        $placeholders[] = ':db_merge_placeholder_' . ($max_placeholder++);
      }

      $query[] = 'WHEN NOT MATCHED THEN INSERT (' . implode(', ', $this->connection->quoteIdentifiers(array_keys($this->insertFields))) . ') VALUES (' . implode(', ', $placeholders) . ')';
    }
    else {
      $query[] = 'WHEN NOT MATCHED THEN INSERT DEFAULT VALUES';
    }

    // Return information about the query.
    $query[] = 'OUTPUT $action;';

    return implode(PHP_EOL, $query);

  }

}
