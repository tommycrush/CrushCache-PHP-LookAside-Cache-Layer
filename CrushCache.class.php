<?php
require('CrushCacheSQLWrapper.class.php');


class CrushCache {

	/* 	BEGIN CONFIG */

	// array of table => indexed_column
	private static $indexed_columns_by_table = array(
		'user' => 'user_id',
	);

	// array of table => cache expirations
	// the # of seconds may not exceed 2592000 (30 days).
	private static $cache_expirations_by_table = array(
		'user' => 3600,
		'*default' => 3600, // backup default value
		'*query' => 3600, // default value for getQuery() storages
	);

	// array of MySQL connection parameters
	private static $sql_params = array(
		'host' => 'localhost',
		'username' => 'my_username',
		'password' => 'my_password',
		'database' => 'my_db_name',
		'error_level' => 1,
	);

	// array of Memcache connection parameters
	private static $cache_params = array(
		'host' => 'localhost',
		'port' => '11211',
		'timeout' => 1, // don't increase! negates the point of memcache.
	);
	/*  END CONFIG */

	// variables to hold connections
	private $cache, $sql_db;

	public function __construct() {
		$this->cache = null;
		$this->sql_db = null;
	}

	public function __destruct(){
		// delete the cache and SQL objects
		// Not necessary, but good form
		unset($this->cache);
		unset($this->sql_db);
	}

	/**
	 * @function get
	 *
	 * @param $table string
	 *		MySQL table we're retrieving results from
	 * @param $indexed_column_value int/string
	 *		ID of the object you want to retrieve
	 *		Value of self::$indexed_columns_by_table
	 *
	 * @return array() [mysql row];
	 */
	public function get($table, $indexed_column_value) {
		$cache_key = $table.':'.$indexed_column_value;
		$value = $this->_getFromCache($cache_key);
		if(!$value){
			// not in cache, get from SQL and store
			$indexed_column = $this->indexed_columns_by_table[$table];
			$sql = "SELECT * FROM ".$table." WHERE `".
				$indexed_column."`= '".$indexed_column_value."' LIMIT 1";
			$value = $this->_getFromDatabase($sql);

			// save value in cache
			$expiration = $this->_defaultCacheExpiration($table);
			$this->_setCache($cache_key, $value, $expiration);
		}
		return $value;
	}

	/**
	 * @function getFromQuery
	 *
	 * @param $sql string
	 *		SQL to retrive data. This will not be cleaned!!!
	 * @param $limit_one bool
	 *		This SQL does/does not contain a "LIMIT 1" clause
	 * @param $expiration int
	 *		Number of seconds to cache the results of the query
	 *
	 * @return array() [mysql row] or array() of arrays() [mysql rows];
	 */
	public function getFromQuery($sql, $limit_one = false, $expiration = -1) {
		// composes a key such as query:d8e8fca2dc0f896fd7cb4cb0031ba249
		$cache_key = 'query:'.md5($sql);

		$value = $this->_getFromCache($cache_key);
		if(!$value){
			// not in cache, get from SQL and store
			$value = $this->_getFromDatabase($sql, $limit_one);

			if ($expiration == -1) {
				$expiration = $this->_defaultCacheExpiration('*query');
			}
			$this->setCache($cache_key, $value, $expiration);
		}
		return $value;
	}

	// @returns false on cache miss or error
	private function _getFromCache($key) {
		$this->_connectToCache();
		return $this->cache->get($key);
	}

	// @returns bool 
	private function _setCache($key, $value, $expire) {
		$this->_connectToCache();
		return $this->cache->set($key, $value, MEMCACHE_COMPRESSED, $expire);
	}

	// sets up connection to Memcache
	// safe to call multiple times
	// returns bool
	private function _connectToCache() {
		if ($this->cache !== null) {
			return true;
		}
		
		$this->cache = new Memcache;
		return $this->cache->connect(
			self::$cache_params['host'],
			self::$cache_params['port'],
			self::$cache_params['timeout'],
		);
	}

	// returns the default cache expiration for that table
	private function _defaultCacheExpiration($table) {
		if (in_array($table, self::$cache_expirations_by_table)) {
			return self::$cache_expirations_by_table[$table];
		}
		return self::$cache_expirations_by_table['*default'];
	}

	private function _getFromDatabase($sql, $multiple_rows = false) {
		if ($this->sql_db === null) {
			$this->sql_db = new CrushCacheSQLWrapper(
				self::$sql_params['host'],
				self::$sql_params['username'],
				self::$sql_params['password'],
				self::$sql_params['database'],
				self::$sql_params['error_level']
			);
		}
		if ($multiple_rows) {
			return $this->sql_db->getMultipleRows($sql);
		} else {
			return $this->sql_db->getOneRow($sql);
		}
	}

} // end CrushCache