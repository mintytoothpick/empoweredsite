<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Cities extends Zend_Db_Table_Abstract {

    protected $_name = 'Cities';
    protected $_primary = 'CityId';

    public function getRegionCities($RegionId) {
    	if($RegionId == 'all') {
    	    $where = 1;
    	} else {
    	    $where = 'Ci.RegionId = '.$RegionId;
    	}
    	$rows = $this->fetchAll($this->select()
    	    ->from(array('Ci'=>'Cities'), array('Ci.CityId', 'Ci.RegionId', 'Ci.City'))
    	    ->where($where))->toArray();
    	return $rows;
    }

    /* Start SQL Refactor */
    
    public function loadInfo($id) {
        $res = $this->fetchRow($this->select()->where('CityId = ?', $id));
        if ($res) {
            return $res->toArray();
        } else return null;
    }
}
