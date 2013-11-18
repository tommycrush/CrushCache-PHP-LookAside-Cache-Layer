<?php
require('CrushCacheSQLWrapper.class.php');


class CrushCache extends CrushCacheSQLWrapper {

	// array of table => indexed_column
	private $indexed_columns_by_table = array(
		"user" => "user_id",
		"post" => "post_id",
	);

	private $cache, $sql_db;

	public function __construct(){
		$this->cache = null;
		$this->sql_db = null;
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




}


