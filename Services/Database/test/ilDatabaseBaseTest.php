<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

// require_once(__DIR__."/mocks.php");

/**
 * TestCase for the ilDatabaseCommonTest
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
abstract class ilDatabaseBaseTest extends PHPUnit_Framework_TestCase {

	const INDEX_NAME = 'i1';
	const TABLE_NAME = 'il_ut_en';
	const CREATE_TABLE_ARRAY_KEY = 'create table';
	/**
	 * @var int
	 */
	protected $error_reporting_backup = 0;
	/**
	 * @var bool
	 */
	protected $backupGlobals = false;
	/**
	 * @var ilDBPdoMySQL|ilDBPdoMySQLInnoDB|ilDBInnoDB|ilDBMySQL
	 */
	protected $db;
	/**
	 * @var ilDatabaseCommonTestMockData
	 */
	protected $mock;
	/**
	 * @var ilDBInterface
	 */
	protected $ildb_backup;


	protected function setUp() {
		$this->error_reporting_backup = error_reporting();
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING & ~E_STRICT); // Due to PEAR Lib MDB2

		PHPUnit_Framework_Error_Notice::$enabled = false;
		PHPUnit_Framework_Error_Deprecated::$enabled = false;

		set_include_path("./Services/PEAR/lib" . PATH_SEPARATOR . ini_get('include_path'));

		require_once('./libs/composer/vendor/autoload.php');
		if (!defined('DEVMODE')) {
			define('DEVMODE', true);
		}
		require_once('./Services/Database/classes/class.ilDBWrapperFactory.php');
		require_once('./Services/Database/test/mock_data/class.ilDatabaseCommonTestMockData.php');
		require_once('./Services/Database/test/mock_data/class.ilDatabaseCommonTestsDataOutputs.php');
		$this->mock = new ilDatabaseCommonTestMockData();
		$this->db = $this->getDBInstance();
		$this->connect($this->db);

		// PDO
		if ($this->db->sequenceExists($this->getTableName())) {
			//			$this->db->dropSequence($this->getTableName());
		}
		if ($this->db->tableExists($this->getTableName())) {
			//			$this->db->dropTable($this->getTableName());
		}
	}


	/**
	 * @param \ilDBInterface $ilDBInterface
	 * @return bool
	 */
	protected function connect(ilDBInterface $ilDBInterface) {
		require_once('./Services/Init/classes/class.ilIniFile.php');
		require_once('./Services/Init/classes/class.ilErrorHandling.php');
		$ilClientIniFile = new ilIniFile('/var/www/ilias/data/trunk/client.ini.php');
		$ilClientIniFile->read();

		$ilDBInterface->initFromIniFile($ilClientIniFile);

		$return = $ilDBInterface->connect();

		return $return;
	}


	/**
	 * @return string
	 */
	protected function getTableName() {
		return strtolower(self::TABLE_NAME . '_' . $this->db->getDBType());
	}


	protected function tearDown() {
		error_reporting($this->error_reporting_backup);
	}


	/**
	 * @return \ilDBPdoMySQLInnoDB
	 * @throws \ilDatabaseException
	 */
	protected function getDBInstance() {
		return ilDBWrapperFactory::getWrapper('pdo-mysql-innodb');
	}


	/**
	 * Test instance implements ilDBInterface and is ilDBInnoDB
	 */
	public function testInstance() {
		$this->assertTrue($this->db instanceof ilDBInterface);
	}


	public function testConnection() {
		$this->assertTrue($this->connect(self::getDBInstance()), true);
	}


	public function testCompareCreateTableQueries() {
		/**
		 * @var $manager MDB2_Driver_Manager_mysqli
		 */
		$manager = $this->db->loadModule(ilDBConstants::MODULE_MANAGER);
		$query = $manager->getTableCreationQuery($this->getTableName(), $this->mock->getDBFields(), array());
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::getCreationQueryBuildByILIAS($this->getTableName()), $query);
	}


	/**
	 * @depends testConnection
	 */
	public function testCreateDatabase() {
		$fields = $this->mock->getDBFields();
		$this->db->createTable($this->getTableName(), $fields);
		$this->db->addPrimaryKey($this->getTableName(), array( 'id' ));
		$this->assertTrue($this->db->tableExists($this->getTableName()));

		$res = $this->db->query('SHOW CREATE TABLE ' . $this->getTableName());
		$data = $this->db->fetchAssoc($res);

		$data = array_change_key_case($data, CASE_LOWER);

		$create_table = $this->normalizeSQL($data[self::CREATE_TABLE_ARRAY_KEY]);
		$create_table_mock = $this->normalizeSQL($this->mock->getTableCreateSQL($this->getTableName(), $this->db->getStorageEngine()));

		$this->assertEquals($create_table_mock, $create_table);
	}


	public function testInsertNative() {
		$values = $this->mock->getInputArray(false, false);
		$id = $values['id'][1];

		// PDO
		$this->db->insert($this->getTableName(), $values);
		$res_pdo = $this->db->query("SELECT * FROM " . $this->getTableName() . " WHERE id = $id LIMIT 0,1");
		$data_pdo = $this->db->fetchAssoc($res_pdo);
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$output_after_native_input, $data_pdo);
	}


	public function testUpdateNative() {
		// With / without clob
		$with_clob = $this->mock->getInputArray(2222, true);
		$without_clob = $this->mock->getInputArray(2222, true, false);
		$id = $with_clob['id'][1];

		// PDO
		$this->db->update($this->getTableName(), $with_clob, array( 'id' => array( 'integer', $id ) ));
		$res_pdo = $this->db->query("SELECT * FROM " . $this->getTableName() . " WHERE id = $id LIMIT 0,1");
		$data_pdo = $this->db->fetchAssoc($res_pdo);
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$output_after_native_update, $data_pdo);

		$this->db->update($this->getTableName(), $without_clob, array( 'id' => array( 'integer', $id ) ));
		$res_pdo = $this->db->query("SELECT * FROM " . $this->getTableName() . " WHERE id = $id LIMIT 0,1");
		$data_pdo = $this->db->fetchAssoc($res_pdo);
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$output_after_native_update, $data_pdo);
	}


	public function testInsertSQL() {
		// PDO
		$this->db->manipulate($this->mock->getInsertQuery($this->getTableName()));
		$res_pdo = $this->db->query("SELECT * FROM " . $this->getTableName() . " WHERE id = 58 LIMIT 0,1");
		$data_pdo = $this->db->fetchObject($res_pdo);

		$this->assertEquals((object)ilDatabaseCommonTestsDataOutputs::$insert_sql_output, $data_pdo);
	}


	/**
	 * @throws \ilDatabaseException
	 * @depends testConnection
	 */
	public function testSelectUsrData() {
		$output = (object)ilDatabaseCommonTestsDataOutputs::$select_usr_data_output;

		$query = 'SELECT usr_id, login, is_self_registered FROM usr_data WHERE usr_id = 6 LIMIT 0,1';
		// PDO
		$res_pdo = $this->db->query($query);
		$data_pdo = $this->db->fetchObject($res_pdo);
		$this->assertEquals($output, $data_pdo);
	}


	public function testIndices() {
		// Add index
		$this->db->addIndex($this->getTableName(), array( 'init_mob_id' ), self::INDEX_NAME);
		$this->assertTrue($this->db->indexExistsByFields($this->getTableName(), array( 'init_mob_id' )));

		// Drop index
		$this->db->dropIndex($this->getTableName(), self::INDEX_NAME);
		$this->assertFalse($this->db->indexExistsByFields($this->getTableName(), array( 'init_mob_id' )));

		// FullText
		$this->db->addIndex($this->getTableName(), array( 'address' ), 'i2', true);
		if ($this->db->supportsFulltext()) {
			$this->assertTrue($this->db->indexExistsByFields($this->getTableName(), array( 'address' )));
		} else {
			$this->assertFalse($this->db->indexExistsByFields($this->getTableName(), array( 'address' )));
		}

		// Drop By Fields
		$this->db->addIndex($this->getTableName(), array( 'elevation' ), 'i3');
		$this->assertTrue($this->db->indexExistsByFields($this->getTableName(), array( 'elevation' )));

		$this->db->dropIndexByFields($this->getTableName(), array( 'elevation' ));
		$this->assertFalse($this->db->indexExistsByFields($this->getTableName(), array( 'elevation' )));
	}


	public function testTableColums() {
		$this->assertTrue($this->db->tableColumnExists($this->getTableName(), 'init_mob_id'));

		$this->db->addTableColumn($this->getTableName(), "export", array( "type" => "text", "length" => 1024 ));
		$this->assertTrue($this->db->tableColumnExists($this->getTableName(), 'export'));

		$this->db->dropTableColumn($this->getTableName(), "export");
		$this->assertFalse($this->db->tableColumnExists($this->getTableName(), 'export'));
	}


	public function testSequences() {
		if ($this->db->sequenceExists($this->getTableName())) {
			$this->db->dropSequence($this->getTableName());
		}
		$this->db->createSequence($this->getTableName(), 10);
		$this->assertTrue($this->db->nextId($this->getTableName()) == 10);
		$this->assertTrue($this->db->nextId($this->getTableName()) == 11);
	}


	public function testReverse() {
		/**
		 * @var $reverse_mdb2 MDB2_Driver_Reverse_mysqli
		 * @var $reverse_pdo  ilDBPdoReverse
		 */
		$reverse_pdo = $this->db->loadModule(ilDBConstants::MODULE_REVERSE);

		// getTableFieldDefinition
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_field_definition_output, $reverse_pdo->getTableFieldDefinition($this->getTableName(), 'comment_mob_id'));

		// getTableIndexDefinition
		$this->db->addIndex($this->getTableName(), array( 'init_mob_id' ), self::INDEX_NAME);
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_index_definition_output, $reverse_pdo->getTableIndexDefinition($this->getTableName(), self::INDEX_NAME));
		$this->db->dropIndex($this->getTableName(), self::INDEX_NAME);

		// getTableConstraintDefinition
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_constraint_definition_output, $reverse_pdo->getTableConstraintDefinition($this->getTableName(), 'primary'));
	}


	public function testManager() {
		/**
		 * @var $manager_mdb2 MDB2_Driver_Manager_mysqli
		 * @var $manager_pdo  ilDBPdomanager
		 */
		$manager_pdo = $this->db->loadModule(ilDBConstants::MODULE_MANAGER);

		// table fields
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_fields_output, $manager_pdo->listTableFields($this->getTableName()));

		// constraints
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_constraints_output, $manager_pdo->listTableConstraints($this->getTableName()));

		// Indices
		$this->db->addIndex($this->getTableName(), array( 'init_mob_id' ), self::INDEX_NAME);
		if ($this->db->supportsFulltext()) {
			$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_indices_fulltext, $manager_pdo->listTableIndexes($this->getTableName()));
		} else {
			$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$table_indices, $manager_pdo->listTableIndexes($this->getTableName()));
		}

		// listTables
		$list_tables_output = ilDatabaseCommonTestsDataOutputs::$list_tables_output;
		$list_tables_output[275] = $this->getTableName();
		$this->assertEquals($list_tables_output, $manager_pdo->listTables());

		// listSequences
		$table_sequences_output = ilDatabaseCommonTestsDataOutputs::$table_sequences_output;
		$table_sequences_output[126] = $this->getTableName();
		$this->assertEquals($table_sequences_output, $manager_pdo->listSequences());
	}


	public function testDBAnalyser() {
		require_once('./Services/Database/classes/class.ilDBAnalyzer.php');
		$analyzer = new ilDBAnalyzer($this->db);

		// Field info
		//		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$analyzer_field_info, $analyzer_pdo->getFieldInformation($this->getTableName())); // FSX

		// getBestDefinitionAlternative
		$def = $this->db->loadModule(ilDBConstants::MODULE_REVERSE)->getTableFieldDefinition($this->getTableName(), 'comment_mob_id');
		$this->assertEquals(0, $analyzer->getBestDefinitionAlternative($def)); // FSX

		// getAutoIncrementField
		$this->assertEquals(false, $analyzer->getAutoIncrementField($this->getTableName()));

		// getPrimaryKeyInformation
		$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$primary_info, $analyzer->getPrimaryKeyInformation($this->getTableName())); // TODO

		// getIndicesInformation
		if ($this->db->supportsFulltext()) {
			$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$index_info_with_fulltext, $analyzer->getIndicesInformation($this->getTableName()));
		} else {
			$this->assertEquals(ilDatabaseCommonTestsDataOutputs::$index_info, $analyzer->getIndicesInformation($this->getTableName()));
		}

		// getConstraintsInformation
		$this->assertEquals(array(), $analyzer->getConstraintsInformation($this->getTableName())); // TODO

		// hasSequence
		$this->assertEquals(59, $analyzer->hasSequence($this->getTableName()));
	}


	public function testDropSequence() {
		$this->assertTrue($this->db->sequenceExists($this->getTableName()));
		if ($this->db->sequenceExists($this->getTableName())) {
			$this->db->dropSequence($this->getTableName());
		}
		$this->assertFalse($this->db->sequenceExists($this->getTableName()));
	}


	public function testChangeTableName() {
		$this->db->dropTable($this->getTableName() . '_a', false);
		$this->db->renameTable($this->getTableName(), $this->getTableName() . '_a');
		$this->assertTrue($this->db->tableExists($this->getTableName() . '_a'));
		$this->db->renameTable($this->getTableName() . '_a', $this->getTableName());
	}


	public function testRenameTableColumn() {
		$this->changeGlobal($this->db);

		$this->db->renameTableColumn($this->getTableName(), 'comment_mob_id', 'comment_mob_id_altered');
		$res = $this->db->query('SHOW CREATE TABLE ' . $this->getTableName());
		$data = $this->db->fetchAssoc($res);
		$data = array_change_key_case($data, CASE_LOWER);

		$this->assertEquals($this->normalizeSQL($this->mock->getTableCreateSQLAfterRename($this->getTableName(), $this->db->getStorageEngine(), $this->db->supportsFulltext())), $this->normalizeSQL($data[self::CREATE_TABLE_ARRAY_KEY]));

		$this->changeBack();
	}


	public function testModifyTableColumn() {
		$changes = array(
			"type"    => "text",
			"length"  => 250,
			"notnull" => false,
			'fixed'   => false,
		);

		$this->changeGlobal($this->db);

		$this->db->modifyTableColumn($this->getTableName(), 'comment_mob_id_altered', $changes);
		$res = $this->db->query('SHOW CREATE TABLE ' . $this->getTableName());
		$data = $this->db->fetchAssoc($res);

		$data = array_change_key_case($data, CASE_LOWER);

		$this->changeBack();

		$this->assertEquals($this->normalizeSQL($this->mock->getTableCreateSQLAfterAlter($this->getTableName(), $this->db->getStorageEngine(), $this->db->supportsFulltext())), $this->normalizeSQL($data[self::CREATE_TABLE_ARRAY_KEY]));
	}


	public function testLockTables() {
		$locks = array(
			0 => array( 'name' => 'usr_data', 'type' => ilDBConstants::LOCK_WRITE ),
			//			1 => array( 'name' => 'object_data', 'type' => ilDBConstants::LOCK_READ ),
		);

		$this->db->lockTables($locks);
		$this->db->manipulate('DELETE FROM usr_data WHERE usr_id = -1');
		$this->db->unlockTables();
	}


	public function testTransactions() {
		// PDO
		//		$this->db->beginTransaction();
		//		$this->db->insert($this->getTableName(), $this->mock->getInputArrayForTransaction());
		//		$this->db->rollback();
		//		$st = $this->db->query('SELECT * FROM ' . $this->getTableName() . ' WHERE id = ' . $this->db->quote(123456, 'integer'));
		//		$this->assertEquals(0, $this->db->numRows($st));
	}


	public function testDropTable() {
		$this->db->dropTable($this->getTableName());
		$this->assertTrue(!$this->db->tableExists($this->getTableName()));
	}

	//
	// HELPERS
	//

	/**
	 * @param \ilDBInterface $ilDBInterface
	 */
	protected function changeGlobal(ilDBInterface $ilDBInterface) {
		global $ilDB;
		$this->ildb_backup = $ilDB;
		$ilDB = $ilDBInterface;
	}


	protected function changeBack() {
		global $ilDB;
		$ilDB = $this->ildb_backup;
	}


	/**
	 * @param $sql
	 * @return string
	 */
	protected function normalizeSQL($sql) {
		return preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', " ", preg_replace("/\n/", "", $sql)));
	}


	/**
	 * @param $sql
	 * @return string
	 */
	protected function normalizetableName($sql) {
		return preg_replace("/" . $this->getTableName() . "|" . $this->getTableName() . "/", "table_name_replaced", $sql);
	}
}