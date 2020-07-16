<?php

namespace Drupal\Tests\sqlsrv\Kernel;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\Core\Database\DatabaseTestBase;

/**
 * Tests table creation and modification via the schema API.
 *
 * @group Database
 */
class SchemaTestExtended extends DatabaseTestBase
{

  /**
   * The table definition.
   *
   * @var array
   */
  protected $table = [];

  /**
   * The sqlsrv schema.
   *
   * @var \Drupal\Driver\Database\sqlsrv\Schema
   */
  protected $schema;

  /**
   * {@inheritdoc}
   */
  protected function setUp()
  {
    parent::setUp();
    /** @var \Drupal\Driver\Database\sqlsrv\Schema $schema */
    $schema = $this->connection->schema();
    $this->schema = $schema;
    $this->table = [
      'description' => 'New Comment',
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'name' => [
          'description' => "A person's name",
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'binary' => TRUE,
        ],
        'age' => [
          'description' => "The person's age",
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'job' => [
          'description' => "The person's job",
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'Undefined',
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'name' => ['name'],
      ],
      'indexes' => [
        'ages' => ['age'],
      ],
    ];
  }

  /**
   * Test adding / removing / readding a primary key to a table.
   */
  public function testPrimaryKeyHandling()
  {
    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'int',
          'not null' => TRUE,
        ),
      ),
    );

    $database = \Drupal::database();

    $database->schema()->createTable('test_table', $table_spec);
    $this->assertTrue($database->schema()->tableExists('test_table'), t('Creating a table without a primary key works.'));

    $database->schema()->addPrimaryKey('test_table', array('id'));
    $this->pass(t('Adding a primary key should work when the table has no data.'));

    // Try adding a row.
    $database->insert('test_table')->fields(array('id' => 1))->execute();
    // The second row with the same value should conflict.
    try {
      $database->insert('test_table')->fields(array('id' => 1))->execute();
      $this->fail(t('Duplicate values in the table should not be allowed when the primary key is there.'));
    } catch (IntegrityConstraintViolationException $e) {
    }

    // Drop the primary key and retry.
    $database->schema()->dropPrimaryKey('test_table');
    $this->pass(t('Removing a primary key should work.'));

    $database->insert('test_table')->fields(array('id' => 1))->execute();
    $this->pass(t('Adding a duplicate row should work without the primary key.'));

    try {
      $database->schema()->addPrimaryKey('test_table', array('id'));
      $this->fail(t('Trying to add a primary key should fail with duplicate rows in the table.'));
    } catch (IntegrityConstraintViolationException $e) {
    }
  }

  /**
   * Test altering a primary key.
   */
  public function testPrimaryKeyAlter()
  {
    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'int',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('id'),
    );

    $this->connection->schema()->createTable('test_table', $table_spec);
    $this->assertTrue($this->connection->schema()->tableExists('test_table'));

    // Add a default value.
    $this->connection->schema()->changeField('test_table', 'id', 'id', array(
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
    ));
  }

  /**
   * Test adding / modifying an unsigned column.
   */
  public function testUnsignedField()
  {

    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'int',
          'not null' => TRUE,
          'unsigned' => TRUE,
        ),
      ),
    );

    $schema = $this->connection->schema();

    $schema->createTable('test_table', $table_spec);

    try {
      $this->connection->insert('test_table')->fields(array('id' => -1))->execute();
      $failed = FALSE;
    } catch (DatabaseException $e) {
      $failed = TRUE;
    }
    $this->assertTrue($failed, t('Inserting a negative value in an unsigned field failed.'));

    $this->assertUnsignedField('test_table', 'id');

    try {
      $this->connection->insert('test_table')->fields(array('id' => 1))->execute();
      $failed = FALSE;
    } catch (DatabaseException $e) {
      $failed = TRUE;
    }
    $this->assertFalse($failed, t('Inserting a positive value in an unsigned field succeeded.'));

    // Change the field to signed.
    $schema->changeField('test_table', 'id', 'id', array(
      'type' => 'int',
      'not null' => TRUE,
    ));

    $this->assertSignedField('test_table', 'id');

    // Change the field back to unsigned.
    $schema->changeField('test_table', 'id', 'id', array(
      'type' => 'int',
      'not null' => TRUE,
      'unsigned' => TRUE,
    ));

    $this->assertUnsignedField('test_table', 'id');
  }

  /**
   * Summary of assertUnsignedField
   *
   * @param string $table
   * @param string $field_name
   */
  protected function assertUnsignedField($table, $field_name)
  {
    try {
      $this->connection->insert($table)->fields(array($field_name => -1))->execute();
      $success = TRUE;
    } catch (DatabaseException $e) {
      $success = FALSE;
    }
    $this->assertFalse($success, t('Inserting a negative value in an unsigned field failed.'));

    try {
      $this->connection->insert($table)->fields(array($field_name => 1))->execute();
      $success = TRUE;
    } catch (DatabaseException $e) {
      $success = FALSE;
    }
    $this->assertTrue($success, t('Inserting a positive value in an unsigned field succeeded.'));

    $this->connection->delete($table)->execute();
  }

  /**
   * Summary of assertSignedField
   *
   * @param string $table
   * @param string $field_name
   */
  protected function assertSignedField($table, $field_name)
  {
    try {
      $this->connection->insert($table)->fields(array($field_name => -1))->execute();
      $success = TRUE;
    } catch (DatabaseException $e) {
      $success = FALSE;
    }
    $this->assertTrue($success, t('Inserting a negative value in a signed field succeeded.'));

    try {
      $this->connection->insert($table)->fields(array($field_name => 1))->execute();
      $success = TRUE;
    } catch (DatabaseException $e) {
      $success = FALSE;
    }
    $this->assertTrue($success, t('Inserting a positive value in a signed field succeeded.'));

    $this->connection->delete($table)->execute();
  }

  /**
   * Test db_add_field() and db_change_field() with indexes.
   */
  public function testAddChangeWithIndex()
  {
    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'int',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('id'),
    );

    $this->connection->schema()->createTable('test_table', $table_spec);

    // Add a default value.
    $this->connection->schema()->addField('test_table', 'test', array(
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
    ), array(
      'indexes' => array(
        'id_test' => array('id, test'),
      ),
    ));

    $this->assertTrue($this->connection->schema()->indexExists('test_table', 'id_test'), t('The index has been created by db_add_field().'));

    // Change the definition, we have by contract to remove the indexes before.
    $this->connection->schema()->dropIndex('test_table', 'id_test');
    $this->assertFalse($this->connection->schema()->indexExists('test_table', 'id_test'), t('The index has been dropped.'));

    $this->connection->schema()->changeField('test_table', 'test', 'test', array(
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
    ), array(
      'indexes' => array(
        'id_test' => array('id, test'),
      ),
    ));

    $this->assertTrue($this->connection->schema()->indexExists('test_table', 'id_test'), t('The index has been recreated by db_change_field().'));
  }
}
