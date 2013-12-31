<?php

// forked from my previous repo, SQL Wrapper
// found at https://github.com/tommycrush/PHP-MySQL-Wrapper/

class CrushCacheSQLWrapper {
	
	//holds the connection [used for MySQLi]
	private $conn = null;

	//holds the error level, which will be used to decide how to handle an error
	//@see error()
	//@see setErrorLevel()
	private $error_level = 1;
	
	/**
	 * MySQL_Wrapper constructor
	 *
	 * establishes connection based with database on the parameteres
	 *
	 * @param string $host
	 *
	 * @param string $username
	 *
	 * @param string $password
	 *
	 * @param string $database
	 *   (optional) if set, it will connect to a particular database
	 *    @see connect()
	 * 
	 * @param int $error_level
	 *   (optionak) if set, it will set the error level
	 *   @see setErrorLevel() 
	 */ 
	public function __construct($host, $username, $password, $database = null, $error_level = null){
		
		//check, before anything, if they are setting the error level
		if(is_numeric($error_level)){
			$this->setErrorLevel($error_level);
		}
		
		//create connection
		$this->connect($host, $username, $password);
		
		//if a database was passed, then select it
		if($database !== null){
			$this->db_select($database);
		}
	}	
	
	/**
	 * MySQL_Wrapper destructor
	 * 		
	 * closes the connection to the database
	 */
	public function __destruct(){
		@mysql_close($this->conn);
	}

	/**
	 * Connects to the server
	 * @param string $host
	 * @param string $username
	 * @param string $password	 
	 */ 
	private function connect($host, $username, $password){
		//create connection
		$this->conn = @mysql_connect($host, $username, $password) or $this->error('Failure on Connection!');
	}
	
	/**
	 * Connects to a particular databse on the existing connection
	 * 
	 * @param string $database_name
	 */
	public function db_select($database_name){
		@mysql_select_db($database_name, $this->conn) or $this->error("Failure on db selection");
	}
	
	/*
	 * BEGIN CORE FUNCTIONS: [these will be used internally but can be used externally as well]
	 * 		query
	 * 		fetch_array
	 * 		num_rows
	 * 		insert_id
	 * 		escape
	 */ 
	 
	/**
	 * queries the database
	 *
	 * @param string $sql
	 * @return mysql_resource
	 */
	public function query($sql){
		$result = mysql_query($sql, $this->conn) or $this->error("Failure on Query.");
		return $result;
	}
	
   /**
	* fetchs an array from a mysql resource
	*
	* @param mysql_resource $resource
	* @return array of the data
	*/  	
	public function fetch_array($resource){
		return @mysql_fetch_array($resource);
	}
	
	/**
	 * returns the number of rows in a resource
	 *
	 * @param mysql_resource $resource
	 * @return int $num_rows
	 */
	public function num_rows($resource){
		return @mysql_num_rows($resource);
	}
	
	/**
	 * returns the last inserted id
	 *
	 * @return int 
	 */ 
	public function insert_id(){
		return @mysql_insert_id($this->conn);
	}
	
	
	/**
	 * escapes a string to make it safe for queries
	 *
	 * @param string $text
	 *
	 * @return string $escaped_text
	 */
	
	public function escape($text){
		return @mysql_real_escape_string($text, $this->conn);
	}
	
	/*
	 * END CORE FUNCTIONS
	 */ 
	

	/*
	 * BEGIN 'unneccesary' FUNCTIONS [go beyond simple implementation, used only externally]
	 * 		getOneRow
	 * 		getMultipleRows
	 * 		insertAndReturnID
	 * 		
	 */ 
	
	/**
	 * performs a query to select a single row of data
	 *
	 * useful for checking if a record exists
	 *
	 * @param string $sql
	 *   ensure that "LIMIT 1" is used in the query
	 *
	 * @return array | bool
	 *		if the row is found, it will return an array of the data
	 *		if the row is not found, it will return false
	 */ 

	public function getOneRow($sql){
		$result = $this->query($sql);
		if($this->num_rows($result) == 1){
			return $this->fetch_array($result);
		}else{
			return false;
		}
	}

	/**
	 * Performs a query and returns a 2D array of results
	 * 
	 * @param string @sql
	 *
	 * @return 2D Array | bool
	 *		if the row is found, it will return an array containing arrays of the data
	 *		if the row is not found, it will return false
	 *  	example : Array ( Array("name" => "Tommy", "twitter" => "ThomasTommyTom"), Array("name" => "Ruby", "twitter" => null) )
	 */ 
	public function getMultipleRows($sql){
		$result = $this->query($sql);
		if($this->num_rows($result) > 0){
			$data = array();
			while($row = $this->fetch_array($result)){
				$data[] = $row;
			}
			return $data;
		}else{
			return false;
		}
	}



	/**
	 * Executes the SQL, then returns the insert_id of the record
	 *
	 * useful for single insertions
	 * 
	 * @param string $sql
	 *
	 * @return int $insert_id
	 */ 
	public function insertAndReturnID($sql){
		$this->query($sql);
		return $this->insert_id();
	}	

	/**
	 * Performs a insertion query based on the parameters
	 *
	 * useful because it escapes all input for safer queries
	 * 
	 * @param string $table
	 *
	 * @param array $columns
	 *		example: array ("name","user_id")
	 *
	 * @param array 
	 *
	 */ 
	public function smartInsert($table, $data, $multiple = false){
        //compose SQL
        $sql = "INSERT INTO `".$table."` (".
            implode(',',array_keys($data)).") VALUES ";

			//build values
			$row_sql = "(";
			$x = 0;
			foreach($data as $value){

				if($x > 0){
					$row_sql .= ",";
				}
				
				if($value == "NOW()"){
					$row_sql .= "NOW()";
				}else{
					$row_sql .= "'".$this->escape($value)."'";
				}
				
				$x++;
			}
		
			$row_sql .= ")";		
			
			$sql .= $row_sql;
			
			//because its only 1 row, lets just go ahead and return the insert_id
			return $this->insertAndReturnID($sql);
	}


	/**
	 * Performs a insertion query based on the parameters
	 *
	 * useful because it escapes all input for safer queries
	 * 
	 * @param string $table
	 *
	 * @param  2D array $data
	 *		example: array(array(name => "tommy"),array("name" => "sean"))
	 *		All internal arrays must have same keys present!
	 *
	 */ 
	public function smartInsertMultiple($table, $data_arrays){
        //compose SQL
        $sql = "INSERT INTO `".$table."` (".
            implode(',',array_keys($data_arrays[0])).") VALUES ";

		$rows = array();
		foreach($data_arrays as $data){
			//build values
			$row_sql = "(";
			$x = 0;
			foreach($data as $key => $value){
				if($x > 0){
					$row_sql .= ",";
				}
				if($value == "NOW()"){
					$row_sql .= "NOW()";
				}else{
					$row_sql .= "'".$this->escape($value)."'";
				}
				$x++;
			}
		
			$row_sql .= ")";		
			$rows[] = $row_sql;
		}

		$sql .= implode(',',$rows);
		return true;
	}

	/*
	 * END 'extras' FUNCTIONS
	 */ 

	
	/*
	 * ERROR HANDLING FUNCTIONS:
	 * 		error
	 * 		setErrorLevel
	 */ 

	/**
	 * handles the errors of the wrapper
	 *
	 * @param string $msg
	 * @see setErrorLevel()
	 */ 
	public function error($msg){
		switch($this->error_level){
			case 1:
				die($msg." [".mysql_error($this->conn)."]");
				break;

			case 2:
				die($msg);
				break;
			
			case 3:
				die();
				break;

			case 4:
				throw new Exception("MySQL error");
				break;

			default:
				die("ERROR HANDLING NOT SET");
				break;
		}
	}
	
	/**
	 * sets the error level [1 = die with all mesage data, 2 = die with basic message, 3 = die with no message, 4 = continue];
	 *
	 * @param int $level
	 *    1 = die with all mesage data, 
	 *    2 = die with basic message,
	 *    3 = die with no message
	 *    4 = throw exception
	 *    5 = continue without handling the error (not recommended)
	 */ 
	public function setErrorLevel($level){
		$this->error_level = $level;	
	}
	
	/*
	 * END ERROR HANDLING FUNCTIONS
	 * 
	 */
	
}
