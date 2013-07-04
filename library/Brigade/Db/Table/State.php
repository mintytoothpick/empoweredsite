<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Blogs.php';

class Brigade_Db_Table_State extends Zend_Db_Table_Abstract {

    protected $_name = 'state';
    protected $_primary = 'stateID';

    public function listByCountry($countryID) {
        return $this->fetchAll($this->select()->where("countryID = ?", $countryID)->order("stateShort"))->toArray();
    }
    
}
?>
