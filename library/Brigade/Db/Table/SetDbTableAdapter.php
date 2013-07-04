<?php

require_once 'Zend/Db/Table.php';

abstract class Brigade_Db_Table_SetDbTableAdapter extends Zend_Db_Table {
	
    public function __construct($config = null) {
    	parent::__construct($config);
        if(isset($this->_use_adapter)){
        	$dbAdapters = Zend_Registry::get('db');
            $config = ($dbAdapters[$this->_use_adapter]);
        }
        return parent::__construct($config);
    }
    
}

?>