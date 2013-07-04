<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Regions extends Zend_Db_Table_Abstract {

    protected $_name = 'Regions';
    protected $_primary = 'RegionId';

    public function getCountryRegions($CountryId) {
    	$rows = $this->fetchAll($this->select()
    	    ->from(array('R'=>'Regions'), array('R.RegionId', 'R.CountryId', 'R.Region'))
    	    ->where('R.CountryId = ?', $CountryId))->toArray();
    	return $rows;
    }
    
    public function loadInfo($id) {
        $res = $this->fetchRow($this->select()->where('RegionId = ?', $id));
        if ($res) {
            return $res->toArray();
        } else return null;
    }
    

} ?>
