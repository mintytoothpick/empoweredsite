<?php
require_once 'Brigade/Db/Table/Regions.php';

/**
 * Class Model State.
 * 
 * @author Matias Gonzalez
 */
class State {
    
    public $id;
    public $name;
    public $countryId;
    public $code;
    public $ADM1Code;
    
    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($siteId) {
        $obj = new self;
        return $obj->load($siteId);
    }
    
    /**
     * Load information of the selected event.
     * 
     * @param String $id Event Id.
     */
    public function load($id) {
        $Regions  = new Brigade_Db_Table_Regions();
        $data = $Regions->loadInfo($id);

        return self::_populateObject($data);
    }
        
    /**
     * Create a object with the database array data.
     * 
     * @param Array $data Data in array format of the database
     * 
     * @return Object State.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj            = new self;
            $obj->id        = $data['RegionId'];
            $obj->name      = $data['Region'];
            $obj->countryId = $data['CountryId'];
            $obj->code      = $data['Code'];
            $obj->ADM1Code  = $data['ADM1Code'];

        }
        return $obj;
    }
}