<?php
require_once 'Zend/Validate/Abstract.php';   

class Brigade_Lib_Validate_DbUnique extends Zend_Validate_Abstract
{
    const NOT_UNIQUE = 'dbUniqueNotUnique';
    
    protected $_messageTemplates = array(
        self::NOT_UNIQUE => "'%column%' '%value' already exists"
    );
    
    protected $_messageVariables = array(
        'column'  => '_column',
    );
    
    protected $_dbTable = NULL;
    
    protected $_column = '';
    
    protected $_rowPrimaryKey = NULL;
    
    public function __construct(Zend_Db_Table_Abstract $table, $column, $rowPrimaryKey = NULL) {
        $this->_dbTable = $table;
        $this->_column = $column;
        $this->_rowPrimaryKey = $rowPrimaryKey;
    }
    
    public function isValid($value) {
        $this->_setValue($value);
        
        $select = $this->_dbTable->select();
        $select->where($this->_dbTable->getAdapter()->quoteInto($this->_column . ' = ?', $value));
        if (isset($this->_rowPrimaryKey)) {
            $rowPrimaryKey = (array) $this->_rowPrimaryKey;
            $info = $this->_dbTable->info();
       
            foreach ($info['primary'] as $key => $column) {
                $select->where($this->_dbTable->getAdapter()->quoteInto($column . ' != ?', $rowPrimaryKey[$key - 1]));                
            }
        }

        $row = $this->_dbTable->fetchAll($select);
        if ($row->count()) {
            // $this->_error();
            return false;
        }
               
        return true;
    }
}