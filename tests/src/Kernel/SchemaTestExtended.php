<?php

namespace Drupal\Tests\sqlsrv\Kernel;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Driver\Database\sqlsrv\PDO\DoomedTransactionException;
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

  /**
   * Performs a count query over the predefined result set
   * and verifies that the number of results matches.
   *
   * @param mixed[] $results
   *
   * @param string $type
   *   Can either be:
   *     "CS_AS" -> Case sensitive / Accent sensitive
   *     "CI_AI" -> Case insensitive / Accent insesitive
   *     "CI_AS" -> Case insensitive / Accent sensitive
   */
  private function AddChangeWithBinarySearchHelper(array $results, string $type, string $fieldtype)
  {
    foreach ($results as $search => $result) {
      // By default, datase collation
      // should be case insensitive returning both rows.
      $count = $this->connection->query('SELECT COUNT(*) FROM {test_table_binary} WHERE name = :name', [':name' => $search])->fetchField();
      $this->assertEqual($count, $result[$type], "Returned the correct number of total rows for a {$type} search on fieldtype {$fieldtype}");
    }
  }

  /**
   * Test db_add_field() and db_change_field() with binary spec.
   */
  /*public function testAddChangeWithBinary()
  {
    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'serial',
          'not null' => TRUE,
        ),
        'name' => array(
          'type' => 'varchar',
          'length' => 255,
          'binary' => false
        ),
      ),
      'primary key' => array('id'),
    );

    $schema = $this->connection->schema();

    $schema->createTable('test_table_binary', $table_spec);

    $samples = ["Sandra", "sandra", "sÁndra"];

    foreach ($samples as $sample) {
      $this->connection->insert('test_table_binary')->fields(['name' => $sample])->execute();
    }

    // Strings to be tested.
    $results = [
      "SaNDRa" => ["CS_AS" => 0, "CI_AI" => 3, "CI_AS" => 2],
      "SÁNdRA" => ["CS_AS" => 0, "CI_AI" => 3, "CI_AS" => 1],
      "SANDRA" => ["CS_AS" => 0, "CI_AI" => 3, "CI_AS" => 2],
      "sandra" => ["CS_AS" => 1, "CI_AI" => 3, "CI_AS" => 2],
      "Sandra" => ["CS_AS" => 1, "CI_AI" => 3, "CI_AS" => 2],
      "sÁndra" => ["CS_AS" => 1, "CI_AI" => 3, "CI_AS" => 1],
      "pedro" => ["CS_AS" => 0, "CI_AI" => 0, "CI_AS" => 0],
    ];

    // Test case insensitive.
    $this->AddChangeWithBinarySearchHelper($results, "CI_AI", "varchar");

    // Now let's change the field
    // to case sensistive / accent sensitive.
    $schema->changeField('test_table_binary', 'name', 'name', [
      'type' => 'varchar',
      'length' => 255,
      'binary' => true
    ]);

    // Test case sensitive.
    $this->AddChangeWithBinarySearchHelper($results, "CS_AS", "varchar:binary");

    // Let's make this even harder, convert to BLOB and back to text.
    // Blob is binary so works like CS/AS
    $schema->changeField('test_table_binary', 'name', 'name', [
      'type' => 'blob',
    ]);

    // Test case sensitive. Varbinary behaves as Case Insensitive / Accent Sensitive.
    // NEVER store text as blob, it behaves as CI_AI!!!
    $this->AddChangeWithBinarySearchHelper($results, "CI_AI", "blob");

    // Back to Case Insensitive / Accent Insensitive
    $schema->changeField('test_table_binary', 'name', 'name', [
      'type' => 'varchar',
      'length' => 255,
    ]);

    // Test case insensitive.
    $this->AddChangeWithBinarySearchHelper($results, "CI_AI", "varchar");

    // Test varchar_ascii support
    $schema->changeField('test_table_binary', 'name', 'name', [
      'type' => 'varchar_ascii'
    ]);

    // Test case insensitive.
    $this->AddChangeWithBinarySearchHelper($results, "CS_AS", "varchar_ascii");
  }*/

  /**
   * Test numeric field precision.
   */
  public function testNumericFieldPrecision()
  {
    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'serial',
          'not null' => TRUE,
        ),
        'name' => array(
          'type' => 'numeric',
          'precision' => 400,
          'scale' => 2
        ),
      ),
      'primary key' => array('id'),
    );

    $schema = $this->connection->schema();

    $success = FALSE;
    try {
      $schema->createTable('test_table_binary', $table_spec);
      $success = TRUE;
    } catch (Exception $error) {
      $success = FALSE;
    }

    $this->assertTrue($success, t('Able to create a numeric field with an out of bounds precision.'));
  }

  /**
   * Tests that inserting non UTF8 strings
   * on a table that does not exists triggers
   * the proper error and not a string conversion
   * error.
   */
  public function testInsertBadCharsIntoNonExistingTable()
  {

    $schema = $this->connection->schema();

    try {
      $query = $this->connection->insert('GHOST_TABLE');
      $query->fields(array('FIELD' => gzcompress('compresing this string into zip!')));
      $query->execute();
    } catch (\Exception $e) {
      if (!($e instanceof \Drupal\Core\Database\SchemaObjectDoesNotExistException)) {
        $this->fail('Inserting into a non existent table does not trigger the right type of Exception.');
      } else {
        $this->pass('Proper exception type thrown.');
      }
    }

    try {
      $query = $this->connection->update('GHOST_TABLE');
      $query->fields(array('FIELD' => gzcompress('compresing this string into zip!')));
      $query->execute();
    } catch (\Exception $e) {
      if (!($e instanceof \Drupal\Core\Database\SchemaObjectDoesNotExistException)) {
        $this->fail('Updating into a non existent table does not trigger the right type of Exception.');
      } else {
        $this->pass('Proper exception type thrown.');
      }
    }
  }

  /**
   * @ee https://github.com/Azure/msphpsql/issues/50
   *
   * Some transactions will get DOOMED if an exception is thrown
   * and the PDO driver will internally rollback and issue
   * a new transaction. That is a BIG bug.
   *
   * One of the most usual cases is when trying to query
   * with a string against an integer column.
   *
   */
  public function testTransactionDoomed()
  {

    $table_spec = array(
      'fields' => array(
        'id' => array(
          'type' => 'serial',
          'not null' => TRUE,
        ),
        'name' => array(
          'type' => 'varchar',
          'length' => 255,
          'binary' => false
        ),
      ),
      'primary key' => array('id'),
    );

    $schema = $this->connection->schema();

    $schema->createTable('test_table', $table_spec);

    // Let's do it!
    $query = $this->connection->insert('test_table');
    $query->fields(array('name' => 'JUAN'));
    $id = $query->execute();

    // Change the name
    $transaction = $this->connection->startTransaction();

    $this->connection->query('UPDATE {test_table} SET name = :p0 WHERE id = :p1', array(':p0' => 'DAVID', ':p1' => $id));

    $name = $this->connection->query('SELECT TOP(1) NAME from {test_table}')->fetchField();
    $this->assertEqual($name, 'DAVID');

    // Let's throw an exception that DOES NOT doom the transaction
    try {
      $name = $this->connection->query('SELECT COUNT(*) FROM THIS_TABLE_DOES_NOT_EXIST')->fetchField();
    } catch (\Exception $e) {

    }

    $name = $this->connection->query('SELECT TOP(1) NAME from {test_table}')->fetchField();
    $this->assertEqual($name, 'DAVID');

    // Lets doom this transaction.
    try {
      $this->connection->query('UPDATE {test_table} SET name = :p0 WHERE id = :p1', array(':p0' => 'DAVID', ':p1' => 'THIS IS NOT AND WILL NEVER BE A NUMBER'));
    } catch (\Exception $e) {

    }

    // What should happen here is that
    // any further attempt to do something inside the
    // scope of this transaction MUST throw an exception.
    $failed = FALSE;
    try {
      $name = $this->connection->query('SELECT TOP(1) NAME from {test_table}')->fetchField();
      $this->assertEqual($name, 'DAVID');
    } catch (\Exception $e) {
      if (!($e instanceof DoomedTransactionException)) {
        $this->fail('Wrong exception when testing doomed transactions.');
      }
      $failed = TRUE;
    }

    $this->assertTrue($failed, 'Operating on the database after the transaction is doomed throws an exception.');

    // Trying to unset the transaction without an explicit rollback should trigger
    // an exception.
    $failed = FALSE;
    try {
      unset($transaction);
    } catch (\Exception $e) {
      if (!($e instanceof DoomedTransactionException)) {
        $this->fail('Wrong exception when testing doomed transactions.');
      }
      $failed = TRUE;
    }

    $this->assertTrue($failed, 'Trying to commit a doomed transaction throws an Exception.');

    //$query = db_select('test_table', 't');
    //$query->addField('t', 'name');
    //$name = $query->execute()->fetchField();
    //$this->assertEqual($name, 'DAVID');
    //unset($transaction);
  }
}
