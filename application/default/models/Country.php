<?php
require_once 'Brigade/Db/Table/Countries.php';

/**
 * Class Model Contact Information.
 *
 * @author Matias Gonzalez
 */
class Country {
    
    public $id;
    public $name;
    public $FIPS104;
    public $ISO2;
    public $ISO3;
    public $ISON;
    public $internet;
    public $capital;
    public $mapReference;
    public $nationalitySingular;
    public $nationalityPlural;
    public $currency;
    public $currencyCode;
    public $population;
    public $title;
    public $comment;
    
    /**
     * @return Class Object
     */
    static public function get($siteId) {
        $cache = Zend_Registry::get('cache');
        if( ($return = $cache->load('models_Country_get_' . $siteId)) === false)
        {
            $obj = new self;
            $return = $obj->load($siteId);
            
            $cache->save($return, 'models_Country_get_'. $siteId);
        }
        
        return $return;
    }
    
    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $ContactInfo  = new Brigade_Db_Table_Countries();
        $data = $ContactInfo->loadInfo($id);

        return self::_populateObject($data);
    }
        
    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Country.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj             = new self;
            $obj->id         = $data['CountryId'];
            $obj->name       = $data['Country'];
            $obj->FIPS104    = $data['FIPS104'];
            $obj->ISO2       = $data['ISO2'];
            $obj->ISO3       = $data['ISO3'];
            $obj->ISON       = $data['ISON'];
            $obj->internet   = $data['Internet'];
            $obj->capital    = $data['Capital'];
            $obj->currency   = $data['Currency'];
            $obj->population = $data['Population'];
            $obj->title      = $data['Title'];
            $obj->comment    = $data['Comment'];
            
            $obj->currencyCode        = $data['CurrencyCode'];
            $obj->mapReference        = $data['MapReference'];
            $obj->nationalitySingular = $data['NationalitySingular'];
            $obj->nationalityPlural   = $data['NationalityPlural'];

        }
        return $obj;
    }
}