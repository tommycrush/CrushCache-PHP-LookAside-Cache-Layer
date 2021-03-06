<?php
require('CrushCacheSQLWrapper.class.php');

error_reporting(E_ALL);

class CrushCache {
	/* 	BEGIN CONFIG */

    // table => array of key columns in table
    // The index is what you will cache by. For example,
    // comments may be stored in the cache in the following ways:
    //      comment:comment_id:5 # comment number 5
    //			[this would be a single comment]
    //      comment:post_id:23 # all the comments under the post 23
    //			[this would be an array of comments]
    //      comment:author_id:64 # all the comments written by 64
    //			[this would be an array of comments]
    // They're only put in the cache when they're demanded.
    private static $indexed_columns_by_table = array(
        'user' => array('user_id'),
        'post' => array('post_id','author_id'),
        'comment' => array('comment_id','post_id','author_id'),
    );
	// array of table => cache expirations
	// the # of seconds may not exceed 2592000 (30 days).
	private static $cache_expirations_by_table = array(
		'user' => 3600, 
        'post' => 6000,
        'comment' => 1000,
        '*default' => 3600, // backup default value
		'*query' => 3600, // default value for getQuery() storages
	);

	// array of MySQL connection parameters
	private static $sql_params = array(
		'host' => '127.0.0.1:3306',
		'username' => 'root', // my local dev enviro
		'password' => '', // my local dev enviro
		'database' => 'crush_cache', // my database name
		'error_level' => 1,
	);

	// array of Memcache connection parameters
	private static $cache_params = array(
		'host' => '127.0.0.1',
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
		// Not necessary, but let's do it for now.
		unset($this->cache);
		unset($this->sql_db);
	}

	/**
	 * @function get()
	 *		Use when you want 1 record
	 *		Example: get("comments","comment_id",60);
	 *		Example: getMultiple("comments","post_id",5);
	 *
	 * @param $table string
	 *		MySQL table we're retrieving results from
	 * @param $index_column string
     *      Column of the key
     *		Value of self::$indexed_columns_by_table
     * @param $key int
     *      Key relating to the $index_column
     * @param $multiple_rows bool (optional, default = false)
     *      Is it possible to retrieve multiple rows from this search?
     *			post, post_id, 5 => there is 1 post_id = 5
     *			comment, post_id, 5, true => there could be several comments
     *
	 * @return array()
	 */
    public function get($table, $index_column, $key, $multiple_rows = false) {
		$cache_key = $table.':'.$index_column.':'.$key;
		$value = $this->_getFromCache($cache_key);
		if(!$value){
			// not in cache, get from SQL and store
			$indexed_column = $this->indexed_columns_by_table[$table];
			$sql = "SELECT * FROM ".$table." WHERE `".
				$index_column."`= '".$index_column."'".
				($multiple_rows ? '' : ' LIMIT 1');
			$value = $this->_getFromDatabase($sql, $multiple_rows);

			// save value in cache
			$expiration = $this->_defaultCacheExpiration($table);
			$this->_setCache($cache_key, $value, $expiration);
		}
		return $value;
	}

	/**
	 * @function getMultiple()
	 *		Use when there are possibly >1 records
	 *		Example: get("comments","comment_id",60);
	 *		Example: getMultiple("comments","post_id",5);
	 *
	 * @param $table string
	 *		MySQL table we're retrieving results from
	 * @param $index_column string
     *      Column of the key
     *		Value of self::$indexed_columns_by_table
     * @param $key int
     *      Key relating to the $index_column
	 * @return array(array(),array(),...)
	 */
    public function getMultiple($table, $index_column, $key) {
		return $this->get($table, $index_column, $key, true);
	}

	/**
	 * @function insert()
	 *		- Inserts a record into the SQL DB.
	 *		- Checks if this insert invalidates other data
	 *			Ex: A new comment on post # 5 would delete
	 *			the key comment:post_id:5
	 *
	 * @param string $table
	 *		SQL table to insert
	 * @param array $data
	 *		Array of column => value pairs
	 *
	 * @return $id int (if)
	 *		result from wrapper->insert_id();
	 */
    public function insert($table, $data) {
        $this->_connectToSQL(); // ensure conneciton is good
        $id = $this->sql_db->smartInsert($table, $data);
        // does the table have indexed_columns?
        if (in_array($table, self::$indexed_columns_by_table)) {
            // clear the appropriate keys
            foreach(self::$indexed_columns_by_table[$table] as $column){
                if (in_array($column, $data)) {
                    $key = self::_composeCacheKey($table, $column, $data[$column]);
                    $this->_deleteCache($key);
                }
            }
        }        
    	return $id;
    }

	/**
	 * @function insertMultiple()
	 *		- Inserts records into the SQL DB.
	 *		- Checks if these new records invalidates other data
	 *			Ex: A new comment on post # 5 would delete
	 *			the key comment:post_id:5
	 *
	 * @param string $table
	 *		SQL table to insert
	 * @param array $data_arrays
	 *		Array of Arrays of column => value pairs
	 *
	 * @return true
	 */
    public function insertMultiple($table, $data_arrays) {
        $this->_connectToSQL(); // ensure conneciton is good
        $this->sql_db->smartInsertMultiple($table, $data_arrays);
        // does the table have indexed_columns?
        foreach($data_arrays as $data) {
	        if (in_array($table, self::$indexed_columns_by_table)) {
	            // clear the appropriate keys
	            foreach(self::$indexed_columns_by_table[$table] as $column){
	                if (in_array($column, $data)) {
	                    $key = self::_composeCacheKey($table, $column, $data[$column]);
	                    $this->_deleteCache($key);
	                }
	            }
	        }
        } 
    	return true;
    }

	/**
	 * @function update()
	 *		- Update a record into the SQL DB.
	 *		- Checks if this update invalidates other data
	 *			Ex: An edit on post # 5 would delete
	 *			the key post:post_id:5 but would also delete
	 *			the post:author_id:(id of author)
	 *
	 * @param string $table
	 *		SQL table to insert
	 * @param array $update
	 *		Array of column => value pairs
	 * @param array $where
	 *		$
	 * @return true
	 */
    public function update($table, $data, $multiple = false) {
    	//todo
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

	// @returns cool
	private function _deleteCache($key) {
		$this->_connectToCache();
		return $this->cache->delete($key);
	}

	// helper function
	private static function _composeCacheKey($table, $column, $value) {
        return $table.':'.$column.':'.$value;
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
			self::$cache_params['timeout']
		);
	}

	// returns the default cache expiration for that table
	private function _defaultCacheExpiration($table) {
		if (in_array($table, self::$cache_expirations_by_table)) {
			return self::$cache_expirations_by_table[$table];
		}
		return self::$cache_expirations_by_table['*default'];
	}

    private function _connectToSQL() {    
        if ($this->sql_db === null) {
        	print_r(self::$sql_params);

			$this->sql_db = new CrushCacheSQLWrapper(
				self::$sql_params['host'],
				self::$sql_params['username'],
				self::$sql_params['password'],
				self::$sql_params['database'],
				self::$sql_params['error_level']
			);
        }
        return true;
    }

	private function _getFromDatabase($sql, $multiple_rows = false) {
        $this->_connectToSQL();
        if ($multiple_rows) {
			return $this->sql_db->getMultipleRows($sql);
		} else {
			return $this->sql_db->getOneRow($sql);
		}
	}

} // end CrushCache
