<?php
require_once 'Brigade/Db/Table/Cities.php';

/**
 * Class Model City.
 *
 * @author Matias Gonzalez
 */
class City {

    public $id;
    public $name;
    public $regionId;
    public $countryId;
    public $latitude;
    public $longitude;
    public $timeZone;
    public $dmaId;
    public $county;
    public $code;

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $City = new Brigade_Db_Table_Cities();
        $data = $City->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object City.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj            = new self;
            $obj->id        = $data['CityId'];
            $obj->name      = $data['City'];
            $obj->regionId  = $data['RegionId'];
            $obj->countryId = $data['CountryId'];
            $obj->latitude  = $data['Latitude'];
            $obj->longitude = $data['Longitude'];
            $obj->timeZone  = $data['TimeZone'];
            $obj->dmaId     = $data['DmaId'];
            $obj->county    = $data['County'];
            $obj->code      = $data['Code'];

        }
        return $obj;
    }
}
