<?php

class MyPDO {

    private $handler;
    private $stmt;

    public function __construct($config) {
        $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['database'];
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try {
            $this->handler = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
        	if(DEVELOPER_MODE == true){
    	 		echo $e->getMessage();
    	 	}
            exit();
        }
    }

    public function query($sql) {
        $this->stmt = $this->handler->query($sql);
    }

    public function prepare($sql) {
        $this->stmt = $this->handler->prepare($sql);
    }

    public function bind($pos, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value);
                    $type = PDO::PARAM_INT;
                    break;

                case is_bool($value);
                    $type = PDO::PARAM_BOOL;
                    break;

                case is_null($value);
                    $type = PDO::PARAM_NULL;
                    break;

                default :
                    $type = PDO::PARAM_STR;
            }
            
            $size = mb_strlen($value , '8bit');
            
            if($size > 4096) {
                $type = PDO::PARAM_LOB;
            }
        }
        
        $this->stmt->bindValue($pos, $value, $type);
    }

    public function execute() {
    	 try {
        	return $this->stmt->execute();
    	 } catch (Exception $e) {
    	 	if(DEVELOPER_MODE == true){
    	 		echo $e->getMessage();
    	 	}
    	 	return false;
    	 }
    	
    }

    public function select($sql, $data=array(), $dump = false) {
    	
    	$this->prepare($sql);
    	
    	if(!empty($data)){
    		foreach($data as $k=>$v){
	    		 $this->bind(':' . $k, $v);
	    	}
    	}
    	
     	if($dump == true) {
            $this->dumpQuery(array(), $data);
        }
    	
     }

    public function insert($table, $data = array(), $dump = false) {

    	$this->checkTable($table);
    	$columns = array_keys($data);
    	
    	$sql = "INSERT INTO ".$table." (".implode(", ", $columns).") VALUES (:".implode(", :", $columns).")";
    	$this->prepare($sql);
    	
    	foreach($data as $k=>$v){
    		 $this->bind(':' . $k, $v);
    	}
    	
    	if($dump == true) {
            $this->dumpQuery($data, array());
        }
    	
        return $this->execute();;	
    }
    
	public function replace($table, $data = array(), $dump = false) {

    	$this->checkTable($table);
    	$columns = array_keys($data);
    	
    	$sql = "REPLACE INTO ".$table." (".implode(", ", $columns).") VALUES (:".implode(", :", $columns).")";
    	$this->prepare($sql);
    	
    	foreach($data as $k=>$v){
    		 $this->bind(':' . $k, $v);
    	}
    	
    	if($dump == true) {
            $this->dumpQuery($data, array());
        }
    	
        return $this->execute();
    }
    
    public function update($table, $data = array(), $where_sql='', $where_data = array(), $dump = false) {
        
        $this->checkTable($table);
		
        $update_columns = array();
        foreach ($data as $k=>$v){
        	$update_array[] = $k.'=:'.$k;
        }
        
        $where = "";
        if(!empty($where_data)){
        	$where .= ' WHERE '.$where_sql;
        }
        
        $sql = "UPDATE ".$table." SET ".implode(" , ", $update_array).$where;
        
     	$this->prepare($sql);

     	if(!empty($where_data)){
	    	foreach($where_data as $k=>$v){
	    		 $this->bind(':'.$k, $v);
	    	}
     	}
     	
    	foreach($data as $k=>$v){
    		 $this->bind(':'.$k, $v);
    	}
    	
    	if(!empty($where_data)){
	     	if($dump == true) {
	            $this->dumpQuery($data, $where_data);
	        }
    	}
        return $this->execute();
    }
    
    public function updateTable($table, $data, $where_data = array(), $dump = false){
    	$where_str = '';
        if(!empty($where_data)){
            $where_array = array();
            foreach ($where_data as $v=>$k){
                    $where_array[] = " $v = :$v";
            }
            $where_str .= implode(" AND ", $where_array);
        }
        return $this->update($table, $data, $where_str, $where_data, $dump);
    }
    
    /*
     * $where_sql mode in PDO format
     * 
     */

    public function delete($table, $where_sql, $where_data = array(), $dump = false) {
        
        $this->checkTable($table);
        
    	if(!empty($where_sql)){
    		$where_sql = ' WHERE '.$where_sql;
    	}
        
        $sql = "DELETE FROM ".$table." ".$where_sql;
        
        $this->prepare($sql);
         
    	foreach($where_data as $k=>$v){
    		 $this->bind(':'.$k, $v);
    	}
    	
    	if($dump == true) {
            $this->dumpQuery(array(), $where_data);
        }
    	
    	return $this->execute();
    }
    
    public function fetchOne() {
        
        if($this->execute()) {
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }

    public function fetchAll() {
        
        if($this->execute()) {
            return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return false;
    }

    public function lastInsertId() {
        return $this->handler->lastInsertId();
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function begin() {
        $this->handler->beginTransaction();
    }

    public function commit() {
        $this->handler->commit();
    }

    public function rollback() {
        $this->handler->rollBack();
    }

    public function cancel() {
        $this->handler->cancelTransaction();
    }
    
    public function dumpQuery($data = array(), $where_data = array()) {
        
        $sql = $this->getQuery();
        $sql = explode('WHERE', $sql);
        $first = $sql[0];
        $second = (isset($sql[1])) ? $sql[1] : null;
        
        foreach($data as $k => $v) {
           
            $v = (is_string($v)) ? '"' . mysql_real_escape_string($v) . '"' : mysql_real_escape_string($v);
            
            $first = str_replace(':' . $k, $v, $first);
        }
        
        if($second != null) {
            
            foreach($where_data as $k => $v) {
            
                $v = (is_string($v)) ? '"' . mysql_real_escape_string($v) . '"' : mysql_real_escape_string($v);

                $second = str_replace(':' . $k, $v, $second);
            }
            
            $sql = $first . 'WHERE' . $second;
        }
        else {
            $sql = $first;
        }
        
        echo $sql;
        
        $this->terminate();
    }
    
    private function getQuery(){
    	return $this->stmt->queryString;
    }
    
    private function checkTable($table) {
        try {
            if (empty($table)) {
                throw new PDOException('Missing table name.');
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            exit();
        }
    }
    
    private function terminate() {
        if(PDO_DUMP_DIE == true) {
            die();
        }
        
        return true;
    }
    
    
    
    /*
     * 
     *  Common methods
     */
    
    public function getList($table, $key, $value){
		$this->select('SELECT * FROM ' . $table . ' ORDER BY id');
		$array = $this->fetchAll();
		if(!empty($array)){
			return getListFromHash($array, $key, $value);
		}
		return false;
	}
    

}