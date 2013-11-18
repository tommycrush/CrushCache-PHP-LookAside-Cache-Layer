<?php
require('CrushCacheSQLWrapper.class.php');


class CrushCache {

	// array of table => indexed_column
	private $indexed_columns_by_table = array(
		"user" => "user_id",
	);

	private $cache, $sql_db;

	private $sql_params = array(
		'host' => 'localhost',
		'username' => 'username',
		'password' => 'password',
		'database' => 'my_db_name',
		'error_level' => 1,
	);

	public function __construct() {
		$this->cache = null;
		$this->sql_db = null;
	}

	public function __destruct(){
		unset($this->cache);
		unset($this->sql_db);
	}

	// for now, one at a time
	public function get($table, $columns, $indexed_column_value) {
		$cache_key = $table.":".$indexed_column_value;
		$value = $this->_getFromCache($cache_key);
		if(!$value){
			// not in cache, get from SQL and store
			$indexed_column = $this->indexed_columns_by_table[$table];
			$sql = "SELECT ".implode(", ", $columns)." FROM ".$table.
				" WHERE `".$indexed_column."`= '".$indexed_column_value.
				"' LIMIT 1";
			$value = $this->_getFromDatabase($sql);
			$this->setCache($cache_key, $value);
		}
		return $value;
	}

	// unsanitized input!! be sure to make the SQL secure before passing here!
	public function getQuery($sql) {
		// composes a key such as query:d8e8fca2dc0f896fd7cb4cb0031ba249
		$cache_key = "query:".md5($sql);

		$value = $this->_getFromCache($cache_key);
		if(!$value){
			// not in cache, get from SQL and store
			$value = $this->_getFromDatabase($sql);
			$this->setCache($cache_key, $value);
		}
		return $value;
	}

	/**
	 * @function _getFromCache
	 *
	 * @param $key string
	 */
	private function _getFromCache($key) {
		return null;
	}


	private function _getFromDatabase($sql) {
		if ($this->sql_db === null) {
			$this->sql_db = new CrushCacheSQLWrapper(
				$this->sql_params['host'],
				$this->sql_params['username'],
				$this->sql_params['password'],
				$this->sql_params['database'],
				$this->sql_params['error_level']
			);
		}

		return $this->sql_db->getOneRow($sql);
	}



}


