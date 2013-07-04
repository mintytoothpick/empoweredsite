<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/ContactInformation.php';

class Brigade_Db_Table_ContactSite extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'contactsite';

    public function listAll() {
        return $this->fetchAll($this->select())->toArray();
    }
    
}

?>
