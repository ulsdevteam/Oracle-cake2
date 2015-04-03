<?php
/**
 * Oracle layer for DBO.
 *
 * PHP version 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake
 * @subpackage    cake.cake.libs.model.datasources.dbo
 * @since         CakePHP v 1.2.0.4041
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('DboSource', 'Model/Datasource');
 
/**
 * Oracle layer for DBO.
 * Portions Copyright 2015, University of Pittsburgh
 * Contributor: Clinton Graham <ctgraham@pitt.edu> +1-412-383-1057
 *
 * This package overrides all PDO methods, using the oci module instead.
 * The Oracle PDO package is experimental and unlikely to be supported by CakePHP in the near future.
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model.datasources.dbo
 */
class Oracle extends DboSource {
 
/**
 * Configuration options
 *
 * @var array
 * @access public
 */
	var $config = array();
 
/**
 * Alias
 *
 * @var string
 */
	var $alias = '';
 
/**
 * Sequence names as introspected from the database
 */
	var $_sequences = array();
 
/**
 * Default schema, if specified
 */
	var $_defaultSchema = '';
 
/**
 * Transaction in progress flag
 *
 * @var boolean
 */
	var $__transactionStarted = false;
 
/**
 * Column definitions
 *
 * @var array
 * @access public
 */
	var $columns = array(
		'primary_key' => array('name' => ''),
		'string' => array('name' => 'varchar2', 'limit' => '255'),
		'text' => array('name' => 'varchar2'),
		'integer' => array('name' => 'number'),
		'float' => array('name' => 'float'),
		'datetime' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'timestamp' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'time' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'date' => array('name' => 'date', 'format' => 'Y-m-d H:i:s'),
		'binary' => array('name' => 'bytea'),
		'boolean' => array('name' => 'boolean'),
		'number' => array('name' => 'number'),
		'inet' => array('name' => 'inet'));
 
/**
 * Connection object
 *
 * @var mixed
 * @access protected
 */
	var $connection;
 
/**
 * Query limit
 *
 * @var int
 * @access protected
 */
	var $_limit = -1;
 
/**
 * Query offset
 *
 * @var int
 * @access protected
 */
	var $_offset = 0;
 
/**
 * Enter description here...
 *
 * @var unknown_type
 * @access protected
 */
	var $_map;
 
/**
 * Current Row
 *
 * @var mixed
 * @access protected
 */
	var $_currentRow;
 
/**
 * Number of rows
 *
 * @var int
 * @access protected
 */
	var $_numRows;
 
/**
 * Query results
 *
 * @var mixed
 * @access protected
 */
	var $_results;
 
/**
 * Base configuration settings for MySQL driver
 *
 * @var array
 */
	var $_baseConfig = array(
		'persistent' => true,
		'host' => 'localhost',
		'login' => 'system',
		'password' => '',
		'database' => 'cake',
		'nls_sort' => '',
		'nls_sort' => ''
	);
 
/**
 * Table-sequence map
 *
 * @var array
 */
	var $_sequenceMap = array();
 
/**
 * Connects to the database using options in the given configuration array.
 *
 * @return boolean True if the database could be connected, else false
 * @access public
 */
	function connect() {
		$config = $this->config;
		$this->connected = false;
		$config['charset'] = !empty($config['charset']) ? $config['charset'] : null;
 
		if (!$config['persistent']) {
			$this->connection = oci_connect($config['login'], $config['password'], $config['database'], $config['charset']);
		} else {
			$this->connection = oci_pconnect($config['login'], $config['password'], $config['database'], $config['charset']);
		}
 
		if ($this->connection) {
			$this->connected = true;
			if (!empty($config['nls_sort'])) {
				$this->execute('ALTER SESSION SET NLS_SORT='.$config['nls_sort']);
			}
 
			if (!empty($config['nls_comp'])) {
				$this->execute('ALTER SESSION SET NLS_COMP='.$config['nls_comp']);
			}
			if (!empty($config['schema'])) {
				$this->execute('ALTER SESSION SET CURRENT_SCHEMA='.$config['schema']);
				$this->_defaultSchema = strtoupper($config['schema']);
			}
			$this->execute("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'");
		} else {
			$this->connected = false;
			$this->_setError();
			return false;
		}
		return $this->connected;
	}
 
/**
 * Keeps track of the most recent Oracle error
 * @param resource $source
 * @param boolean $clear
 *
 */
	function _setError($source = null, $clear = false) {
		if ($source) {
			$e = oci_error($source);
		} else {
			$e = oci_error();
		}
		$this->_error = $e['message'];
		if ($clear) {
			$this->_error = null;
		}
	}

/**
 * Sets the encoding language of the session
 *
 * @param string $lang language constant
 * @return bool
 */
	function setEncoding($lang) {
		if (!$this->execute('ALTER SESSION SET NLS_LANGUAGE='.$lang)) {
			return false;
		}
		return true;
	}
 
/**
 * Gets the current encoding language
 *
 * @return string language constant
 */
	function getEncoding() {
		$sql = 'SELECT VALUE FROM NLS_SESSION_PARAMETERS WHERE PARAMETER=\'NLS_LANGUAGE\'';
		if (!$this->execute($sql)) {
			return false;
		}
 
		if (!$row = $this->fetchRow()) {
			return false;
		}
		return $row[0]['VALUE'];
	}
 
/**
 * Disconnects from database.
 *
 * @return boolean True if the database could be disconnected, else false
 * @access public
 */
	function disconnect() {
		if ($this->connection) {
			$this->connected = !oci_close($this->connection);
			return !$this->connected;
		}
	}

/**
 * Gets the version string of the database server
 *
 * @return string The database version
 */
	public function getVersion() {
		$sql = 'SELECT BANNER FROM SYS.V_$VERSION';
		if (!$this->execute($sql)) {
			return false;
		}
 
		if (!$row = $this->fetchRow()) {
			return false;
		}
		$this->finish();
		return $row[0]['BANNER'];
	}
 
/**
 * Scrape the incoming SQL to create the association map. This is an extremely
 * experimental method that creates the association maps since Oracle will not tell us.
 *
 * @param string $sql
 * @return false if sql is nor a SELECT
 * @access protected
 */
	function _scrapeSQL($sql) {
		$sql = str_replace("\"", '', $sql);
		$preFrom = preg_split('/\bFROM\b/', $sql);
		$preFrom = $preFrom[0];
		$find = array('SELECT');
		$replace = array('');
		$fieldList = trim(str_replace($find, $replace, $preFrom));
		$fields = preg_split('/,\s+/', $fieldList);//explode(', ', $fieldList);
		$lastTableName	= '';
 
		foreach($fields as $key => $value) {
			if ($value != 'COUNT(*) AS count') {
				if (preg_match('/\s+(\w+(\.\w+)*)$/', $value, $matches)) {
					$fields[$key]	= $matches[1];
 
					if (preg_match('/^(\w+\.)/', $value, $matches)) {
						$fields[$key]	= $matches[1] . $fields[$key];
						$lastTableName	= $matches[1];
					}
				}
				/*
				if (preg_match('/(([[:alnum:]_]+)\.[[:alnum:]_]+)(\s+AS\s+(\w+))?$/i', $value, $matches)) {
					$fields[$key]	= isset($matches[4]) ? $matches[2] . '.' . $matches[4] : $matches[1];
				}
				*/
			}
		}
		$this->_map = array();
 
		foreach($fields as $f) {
			$e = explode('.', $f);
			if (count($e) > 1) {
				$table = $e[0];
				$field = strtolower($e[1]);
			} else {
				$table = 0;
				$field = $e[0];
			}
			$this->_map[] = array($table, $field);
		}
	}
 
/**
 * Modify a SQL query to limit (and offset) the result set
 *
 * @param integer $limit Maximum number of rows to return
 * @param integer $offset Row to begin returning
 * @access public
 */
	function limit($limit = -1, $offset = 0) {
		$this->_limit = (int) $limit;
		$this->_offset = (int) $offset;
	}
 
/**
 * Returns number of rows in previous resultset. If no previous resultset exists,
 * this returns false.
 *
 * @return integer Number of rows in resultset
 * @access public
 */
	function lastNumRows() {
		return $this->_numRows;
	}
 
/**
 * Executes given SQL statement. This is an overloaded method.
 *
 * @param string $sql SQL statement
 * @return resource Result resource identifier or null
 * @access protected
 */
	function _execute($sql) {
		$this->_statementId = oci_parse($this->connection, $sql);
		if (!$this->_statementId) {
			$this->_setError($this->connection);
			return false;
		}
 
		if ($this->__transactionStarted) {
			$mode = OCI_DEFAULT;
		} else {
			$mode = OCI_COMMIT_ON_SUCCESS;
		}
 
		if (!oci_execute($this->_statementId, $mode)) {
			$this->_setError($this->_statementId);
			error_log($sql);
			return false;
		}
 
		$this->_setError(null, true);
 
		switch(oci_statement_type($this->_statementId)) {
			case 'DESCRIBE':
			case 'SELECT':
				$this->_scrapeSQL($sql);
				break;
			default:
				return $this->_statementId;
		}
 
		if ($this->_limit >= 1) {
			oci_set_prefetch($this->_statementId, $this->_limit);
		} else {
			oci_set_prefetch($this->_statementId, 3000);
		}
		$this->_numRows = oci_fetch_all($this->_statementId, $this->_results, $this->_offset, $this->_limit, OCI_NUM | OCI_FETCHSTATEMENT_BY_ROW);
		$this->_currentRow = 0;
		$this->limit();
		return $this->_statementId;
	}
 
/**
 * Fetch result row
 *
 * @return array
 * @access public
 */
	function fetchRow() {
		if ($this->_currentRow >= $this->_numRows) {
			oci_free_statement($this->_statementId);
			$this->_map = null;
			$this->_results = null;
			$this->_currentRow = null;
			$this->_numRows = null;
			return false;
		}
		$resultRow = array();
 
		foreach($this->_results[$this->_currentRow] as $index => $field) {
			list($table, $column) = $this->_map[$index];
 
			if (strpos($column, ' count')) {
				$resultRow[0]['count'] = $field;
			} else {
				$resultRow[$table][$column] = $this->_results[$this->_currentRow][$index];
			}
		}
		$this->_currentRow++;
		return $resultRow;
	}
 
/**
 * Fetches the next row from the current result set
 *
 * @return array
 */
	function fetchResult() {
		return $this->fetchRow();
	}
 
/**
 * Checks to see if a named sequence exists
 *
 * @param string $sequence
 * @param string $owner optional
 * @return bool
 * @access public
 */
	function sequenceExists($sequence, $owner = '') {
		if ($owner) {
			$sql = "SELECT SEQUENCE_NAME FROM ALL_SEQUENCES WHERE SEQUENCE_NAME = '$sequence' AND SEQUENCE_OWNER = '$owner'";
		} else {
			$sql = "SELECT SEQUENCE_NAME FROM USER_SEQUENCES WHERE SEQUENCE_NAME = '$sequence'";
		}
		if (!$this->execute($sql)) {
			return false;
		}
		return $this->fetchRow();
	}
 
/**
 * Creates a database sequence
 *
 * @param string $sequence
 * @return bool
 * @access public
 */
	function createSequence($sequence) {
		$sql = "CREATE SEQUENCE $sequence";
		return $this->execute($sql);
	}
 
/**
 * Create trigger
 *
 * @param string $table
 * @return string
 * @access public
 */
	function createTrigger($table) {
		$sql = "CREATE OR REPLACE TRIGGER pk_$table" . "_trigger BEFORE INSERT ON $table FOR EACH ROW BEGIN SELECT pk_$table.NEXTVAL INTO :NEW.ID FROM DUAL; END;";
		return $this->execute($sql);
	}
 
/**
 * Returns an array of tables in the database. If there are no tables, an error is
 * raised and the application exits.
 *
 * @return array tablenames in the database
 * @access public
 */
	function listSources() {
		$cache = parent::listSources();
		if ($cache != null) {
			return $cache;
		}
		$sql = 'SELECT view_name AS name FROM all_views'.($this->_defaultSchema ? ' WHERE owner = \''.$this->_defaultSchema.'\'' : '').' UNION SELECT table_name AS name FROM all_tables'.($this->_defaultSchema ? ' WHERE owner = \''.$this->_defaultSchema.'\'' : '');
 
		if (!$this->execute($sql)) {
			return false;
		}
		$sources = array();
 
		while($r = $this->fetchRow()) {
			$sources[] = strtolower($r[0]['name']);
		}
		parent::listSources($sources);
		return $sources;
	}
 
/**
 * Returns an array of the fields in given table name.
 *
 * @param object $model instance of a model to inspect
 * @return array Fields in table. Keys are name and type
 * @access public
 */
	public function describe(&$model) {
		$table = $this->fullTableName($model, false);
		$tableOnly = $this->fullTableName($model, false, false);
		$tableSchema = $this->tableSchema($model);
 
		// TODO: FIXME
		// DboSource::create() calls $this->lastInsertId($this->fullTableName($Model, false, false), $Model->primaryKey)
		// This means lastInsertId doesn't know the fully qualified tablename to lookup the sequence, and it depends on
		// unquilified keys in $this->_sequenceMap, which we are setting here.
		// So, we key the sequences here on the unqualified table name, which seems bad.
		if (!empty($model->sequence)) {
			$this->_sequenceMap[$tableOnly] = $model->sequence;
		} elseif (!empty($model->table)) {
			if ($this->sequenceExists($model->table . '_seq', $tableSchema)) {
				$this->_sequenceMap[$tableOnly] = $model->table . '_seq';
			} else {
				$this->_sequenceMap[$tableOnly] = false;
			}
		}


		$cache = parent::describe($model);
 
		if ($cache != null) {
			return $cache;
		}
 
		$sql = 'SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, NULLABLE FROM all_tab_columns WHERE table_name = \'';
		$sql .= strtoupper($tableOnly) . '\''.($tableSchema ? ' AND owner = \''.strtoupper($tableSchema).'\'' : '');
 
		if (!$this->execute($sql)) {
			return false;
		}
 
		$fields = array();
 
		for ($i = 0; $row = $this->fetchRow(); $i++) {
			$fields[strtolower($row[0]['COLUMN_NAME'])] = array(
				'type'=> $this->column($row[0]['DATA_TYPE']),
				'length'=> $row[0]['DATA_LENGTH'],
				'null'=> $row[0]['NULLABLE'] == 'N' ? FALSE : TRUE
			);
		}

		// Find a single-column unique index and mark it as a primary key
		$sql = 'SELECT MAX(COLUMN_NAME) COLUMN_NAME, INDEX_NAME, COUNT(*) FROM ALL_IND_COLUMNS JOIN ALL_INDEXES USING (INDEX_NAME) WHERE ALL_INDEXES.UNIQUENESS = \'UNIQUE\' AND ALL_INDEXES.TABLE_NAME =\'';
		$sql .= strtoupper($tableOnly) . '\''.($tableSchema? ' AND OWNER = \''.strtoupper($tableSchema).'\'' : '').' GROUP BY INDEX_NAME HAVING COUNT(*) = 1';
		if ($this->execute($sql)) {
			$candidateKey = '';
			for ($i = 0; $row = $this->fetchRow(); $i++) {
				// Prefer the first found
				if (!$candidateKey) {
					$candidateKey = strtolower($row[0]['COLUMN_NAME']);
				}
				// Prefer a column named "ID"
				if (strtolower($row[0]['COLUMN_NAME']) == 'id') {
					break;
				}
			}
			if ($candidateKey) {
				$fields[$candidateKey]['key'] = 'primary';
			}
		}

		#$this->__cacheDescription($this->fullTableName($model, false), $fields);

		return $fields;
	}
 
/**
 * Deletes all the records in a table and drops all associated auto-increment sequences.
 * Using DELETE instead of TRUNCATE because it causes locking problems.
 *
 * @param mixed $table A string or model class representing the table to be truncated
 * @param integer $reset If -1, sequences are dropped, if 0 (default), sequences are reset,
 *						and if 1, sequences are not modified
 * @return boolean	SQL TRUNCATE TABLE statement, false if not applicable.
 * @access public
 *
 */
	function truncate($table, $reset = 0) {
 
		if (empty($this->_sequences)) {
			$sql = "SELECT sequence_name FROM all_sequences";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_sequences[] = strtolower($row[0]['sequence_name']);
			}
		}
 
		$this->execute('DELETE FROM ' . $this->fullTableName($table));
		if (!isset($this->_sequenceMap[$table]) || !in_array($this->_sequenceMap[$table], $this->_sequences)) {
			return true;
		}
		if ($reset === 0) {
			$this->execute("SELECT {$this->_sequenceMap[$table]}.nextval FROM dual");
			$row = $this->fetchRow();
			$currval = $row[$this->_sequenceMap[$table]]['nextval'];
 
			$this->execute("SELECT min_value FROM all_sequences WHERE sequence_name = '{$this->_sequenceMap[$table]}'");
			$row = $this->fetchRow();
			$min_value = $row[0]['min_value'];
 
			if ($min_value == 1) $min_value = 0;
			$offset = -($currval - $min_value);
 
			$this->execute("ALTER SEQUENCE {$this->_sequenceMap[$table]} INCREMENT BY $offset MINVALUE $min_value");
			$this->execute("SELECT {$this->_sequenceMap[$table]}.nextval FROM dual");
			$this->execute("ALTER SEQUENCE {$this->_sequenceMap[$table]} INCREMENT BY 1");
		} else {
			//$this->execute("DROP SEQUENCE {$this->_sequenceMap[$table]}");
		}
		return true;
	}
 
/**
 * Enables, disables, and lists table constraints
 *
 * Note: This method could have been written using a subselect for each table,
 * however the effort Oracle expends to run the constraint introspection is very high.
 * Therefore, this method caches the result once and loops through the arrays to find
 * what it needs. It reduced my query time by 50%. YMMV.
 *
 * @param string $action
 * @param string $table
 * @return mixed boolean true or array of constraints
 */
	function constraint($action, $table) {
		if (empty($table)) {
			trigger_error(__('Must specify table to operate on constraints', true));
		}
 
		$table = strtoupper($table);
 
		if (empty($this->_keyConstraints)) {
			$sql = "SELECT
					  table_name,
					  c.constraint_name
					FROM all_cons_columns cc
					LEFT JOIN all_indexes i ON (cc.constraint_name = i.index_name)
					LEFT JOIN all_constraints c ON(c.constraint_name = cc.constraint_name)";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_keyConstraints[] = array($row[0]['table_name'], $row['c']['constraint_name']);
			}
		}
 
		$relatedKeys = array();
		foreach ($this->_keyConstraints as $c) {
			if ($c[0] == $table) {
				$relatedKeys[] = $c[1];
			}
		}
 
		if (empty($this->_constraints)) {
			$sql = "SELECT
					  table_name,
					  constraint_name,
					  r_constraint_name
					FROM
					  all_constraints";
			$this->execute($sql);
			while ($row = $this->fetchRow()) {
				$this->_constraints[] = $row[0];
			}
		}
 
		$constraints = array();
		foreach ($this->_constraints as $c) {
			if (in_array($c['r_constraint_name'], $relatedKeys)) {
				$constraints[] = array($c['table_name'], $c['constraint_name']);
			}
		}
 
		foreach ($constraints as $c) {
			list($table, $constraint) = $c;
			switch ($action) {
				case 'enable':
					$this->execute("ALTER TABLE $table ENABLE CONSTRAINT $constraint");
					break;
				case 'disable':
					$this->execute("ALTER TABLE $table DISABLE CONSTRAINT $constraint");
					break;
				case 'list':
					return $constraints;
					break;
				default:
					trigger_error(__('DboOracle::constraint() accepts only enable, disable, or list', true));
			}
		}
		return true;
	}
 
/**
 * Returns an array of the indexes in given table name.
 *
 * @param string $model Name of model to inspect
 * @return array Fields in table. Keys are column and unique
 */
	function index($model) {
		$index = array();
		$table = $this->fullTableName($model, false);
		if ($table) {
			$indexes = $this->query('SELECT
			  cc.table_name,
			  cc.column_name,
			  cc.constraint_name,
			  c.constraint_type,
			  i.index_name,
			  i.uniqueness
			FROM all_cons_columns cc
			LEFT JOIN all_indexes i ON(cc.constraint_name = i.index_name)
			LEFT JOIN all_constraints c ON(c.constraint_name = cc.constraint_name)
			WHERE cc.table_name = \'' . strtoupper($table) .'\'');
			foreach ($indexes as $i => $idx) {
				if ($idx['c']['constraint_type'] == 'P') {
					$key = 'PRIMARY';
				} else {
					continue;
				}
				if (!isset($index[$key])) {
					$index[$key]['column'] = strtolower($idx['cc']['column_name']);
					$index[$key]['unique'] = intval($idx['i']['uniqueness'] == 'UNIQUE');
				} else {
					if (!is_array($index[$key]['column'])) {
						$col[] = $index[$key]['column'];
					}
					$col[] = strtolower($idx['cc']['column_name']);
					$index[$key]['column'] = $col;
				}
			}
		}
		return $index;
	}
 
/**
 * Generate a Oracle Alter Table syntax for the given Schema comparison
 *
 * @param array() $compare Schemas to compare for differences
 * @param string $table optional table name
 * @return mixed string or false
 */
	function alterSchema($compare, $table = null) {
		if (!is_array($compare)) {
			return false;
		}
		$out = '';
		$colList = array();
		foreach($compare as $curTable => $types) {
			if (!$table || $table == $curTable) {
				$out .= 'ALTER TABLE ' . $this->fullTableName($curTable) . " \n";
				foreach($types as $type => $column) {
					switch($type) {
						case 'add':
							foreach($column as $field => $col) {
								$col['name'] = $field;
								$alter = 'ADD '.$this->buildColumn($col);
								if (isset($col['after'])) {
									$alter .= ' AFTER '. $this->name($col['after']);
								}
								$colList[] = $alter;
							}
						break;
						case 'drop':
							foreach($column as $field => $col) {
								$col['name'] = $field;
								$colList[] = 'DROP '.$this->name($field);
							}
						break;
						case 'change':
							foreach($column as $field => $col) {
								if (!isset($col['name'])) {
									$col['name'] = $field;
								}
								$colList[] = 'CHANGE '. $this->name($field).' '.$this->buildColumn($col);
							}
						break;
					}
				}
				$out .= "\t" . implode(",\n\t", $colList) . ";\n\n";
			}
		}
		return $out;
	}
 
/**
 * This method should quote Oracle identifiers. Well it doesn't.
 * It would break all scaffolding and all of Cake's default assumptions.
 *
 * @param string $name
 * @return string
 * @access public
 */
	function name($name) {
		if (strpos($name, '.') !== false && strpos($name, '"') === false) {
			list($model, $field) = explode('.', $name);
			if ($field[0] == "_") {
				$name = "$model.\"$field\"";
			}
		} else {
			if ($name[0] == "_") {
				$name = "\"$name\"";
			}
		}
		return $name;
	}
 
/**
 * Begin a transaction
 *
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions).
 */
	function begin() {
		$this->__transactionStarted = true;
		return true;
	}
 
/**
 * Rollback a transaction
 *
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 */
	function rollback() {
		return oci_rollback($this->connection);
	}
 
/**
 * Commit a transaction
 *
 * @return boolean True on success, false on fail
 * (i.e. if the database/model does not support transactions,
 * or a transaction has not started).
 */
	function commit() {
		$this->__transactionStarted = false;
		return oci_commit($this->connection);
	}
 
/**
 * Converts database-layer column types to basic types
 *
 * @param string $real Real database-layer column type (i.e. "varchar(255)")
 * @return string Abstract column type (i.e. "string")
 * @access public
 */
	function column($real) {
		if (is_array($real)) {
			$col = $real['name'];
 
			if (isset($real['limit'])) {
				$col .= '('.$real['limit'].')';
			}
			return $col;
		} else {
			$real = strtolower($real);
		}
		$col = str_replace(')', '', $real);
		$limit = null;
		if (strpos($col, '(') !== false) {
			list($col, $limit) = explode('(', $col);
		}
 
		if (in_array($col, array('date', 'timestamp'))) {
			return $col;
		}
		if (strpos($col, 'number') !== false) {
			return 'integer';
		}
		if (strpos($col, 'integer') !== false) {
			return 'integer';
		}
		if (strpos($col, 'char') !== false) {
			return 'string';
		}
		if (strpos($col, 'text') !== false) {
			return 'text';
		}
		if (strpos($col, 'blob') !== false) {
			return 'binary';
		}
		if (in_array($col, array('float', 'double', 'decimal'))) {
			return 'float';
		}
		if ($col == 'boolean') {
			return $col;
		}
		return 'text';
	}
 
/**
 * Returns a quoted and escaped string of $data for use in an SQL statement.
 *
 * @param string $data String to be prepared for use in an SQL statement
 * @param string $column optional column data type
 * @return string Quoted and escaped
 * @access public
 */
	function value($data, $column = null) {
		if (is_array($data) && !empty($data)) {
			return array_map(
				array(&$this, 'value'),
				$data, array_fill(0, count($data), $column)
			);
		} elseif (is_object($data) && isset($data->type, $data->value)) {
			if ($data->type === 'identifier') {
				return $this->name($data->value);
			} elseif ($data->type === 'expression') {
				return $data->value;
			}
		} elseif (in_array($data, array('{$__cakeID__$}', '{$__cakeForeignKey__$}'), true)) {
			return $data;
		}

		if (empty($column)) {
			$column = $this->introspectType($data);
		}
 
		if ($data === null) {
			return 'NULL';
		}
 
		if ($data === '') {
			return  "''";
		}
 
		switch($column) {
			case 'date':
				$data = date('Y-m-d H:i:s', strtotime($data));
				$data = "TO_DATE('$data', 'YYYY-MM-DD HH24:MI:SS')";
			break;
			case 'integer' :
			case 'float' :
			case null :
				if (is_numeric($data)) {
					break;
				}
			default:
				$data = str_replace("'", "''", $data);
				$data = "'$data'";
			break;
		}
		return $data;
	}
 
/**
 * Returns the ID generated from the previous INSERT operation.
 *
 * @param string $source tablename
 * @param string $key (optional) primary key
 * @return integer
 * @access public
 */
	function lastInsertId($source, $key = '') {
		$sequence = $this->_sequenceMap[$source];
		if ($sequence) {
			$sql = "SELECT $sequence.currval FROM dual";
 
			if (!$this->execute($sql)) {
				return false;
			}
 
			while($row = $this->fetchRow()) {
				return $row[$sequence]['currval'];
			}
		}
		return false;
	}
 
/**
 * Returns a formatted error message from previous database operation.
 *
 * @return string Error message with error number
 * @access public
 */
	function lastError() {
		return $this->_error;
	}
 
/**
 * Returns number of affected rows in previous database operation. If no previous operation exists, this returns false.
 *
 * @return int Number of affected rows
 * @access public
 */
	function lastAffected() {
		return $this->_statementId ? oci_num_rows($this->_statementId): false;
	}
 
/**
 * Renders a final SQL statement by putting together the component parts in the correct order
 *
 * @param string $type
 * @param array $data
 * @return string
 */
	function renderStatement($type, $data) {
		extract($data);
		$aliases = null;
 
		switch (strtolower($type)) {
			case 'select':
				return "SELECT {$fields} FROM {$table} {$alias} {$joins} {$conditions} {$group} {$order} {$limit}";
			case 'create':
				return "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
			case 'update':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} ";
				}
				return "UPDATE {$table} {$aliases}SET {$fields} {$conditions}";
			case 'delete':
				if (!empty($alias)) {
					$aliases = "{$this->alias}{$alias} ";
				}
				return "DELETE FROM {$table} {$aliases}{$conditions}";
			case 'schema':
				foreach (array('columns', 'indexes') as $var) {
					if (is_array(${$var})) {
						${$var} = "\t" . implode(",\n\t", array_filter(${$var}));
					}
				}
				if (trim($indexes) != '') {
					$columns .= ',';
				}
				return "CREATE TABLE {$table} (\n{$columns}{$indexes})";
			case 'alter':
				break;
		}
	}
 
/**
 * Generate a "drop table" statement for the given Schema object
 *
 * @param object $schema An instance of a subclass of CakeSchema
 * @param string $table Optional.  If specified only the table name given will be generated.
 *						Otherwise, all tables defined in the schema are generated.
 * @return string
 */
		function dropSchema($schema, $table = null) {
			if (!is_a($schema, 'CakeSchema')) {
				trigger_error(__('Invalid schema object', true), E_USER_WARNING);
				return null;
			}
			$out = '';
 
			foreach ($schema->tables as $curTable => $columns) {
				if (!$table || $table == $curTable) {
					$out .= 'DROP TABLE ' . $this->fullTableName($curTable) . "\n";
				}
			}
			return $out;
		}

/**
 * Override DboSource::hasResult, because it references PDO and we're not PDO
 * @see http://www.hassanbakar.com/2012/01/09/using-oracle-in-cakephp-2-0/
 * 
 * @return boolean
 */
	function hasResult() {
		return ($this->lastAffected() > 0);
	}
	
/**
 * Gets table schema
 *
 * @param Model|string $model Either a Model object or a string table name.
 * @return string schema name
 */
	public function tableSchema($model) {
		if (is_object($model)) {
			$schemaName = $model->schemaName;
		} elseif (strstr(strval($model), '.') !== FALSE) {
			$schemaName = array_shift(explode('.', strval($model)));
		} else {
			$schemaName = $this->getSchemaName();
		}
		return $schemaName;
	}

/**
 * Finish an open cursor
 *
 */
	public function finish() {
		if ($this->_statementId) {
			oci_cancel($this->_statementId);
		}
	}
	
/**
 * Get the default schema, if applicable
 * @return string
 *
 */
	public function getSchemaName() {
		return $this->_defaultSchema;
	}
}
