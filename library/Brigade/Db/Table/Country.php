<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Country extends Zend_Db_Table_Abstract {

    protected $_name = 'country';
    protected $_primary = 'CountryId';

    public function listAll($all = false) {
        if (!$all) {
            $countries = "'USA', 'GBR', 'CAN', 'IRE', 'HND', 'PAN', 'AUT', 'SWI', 'GER'";
            return $this->fetchAll($this->select()->where("countryShort IN ($countries)"))->toArray();
        } else {
            return $this->fetchAll($this->select())->toArray();
        }
    }
    
}
?>
