<?php
require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Country.php';
require_once 'State.php';
require_once 'City.php';

/**
 * Class Model Contact Information.
 *
 * @author Eamonn Pascal
 */
class Contact {

    public $id;
    public $siteId;
    public $email;
    public $phone;
    public $website;
    public $street;
    public $cityId;
    public $stateId;
    public $countryId;
    public $modifiedById;
    public $modifiedOn;
    public $createdById;
    public $createdOn;

    // @TODO: Remove this from DB. Use lazy attr.
    public $cityName;
    public $countryName;
    public $reginName;

    //Lazy
    protected $_user    = null;
    protected $_address = null;
    protected $_city    = null;
    protected $_state   = null;
    protected $_country = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        if ($name == 'user') {
            if (is_null($this->_user)) {
                $this->_getUser();
            }
            return $this->_user;
        } elseif ($name == 'address') {
            if (is_null($this->_address)) {
                $this->_getAddress();
            }
            return $this->_address;
        } elseif ($name == 'country') {
            if (is_null($this->_country)) {
                $this->_getCountry();
            }
            return $this->_country;
        } elseif ($name == 'state') {
            if (is_null($this->_state)) {
                $this->_getState();
            }
            return $this->_state;
        } elseif ($name == 'city') {
            if (is_null($this->_city)) {
                $this->_getCity();
            }
            return $this->_city;
        }
    }

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
    public function load($siteId) {
        $ContactInfo  = new Brigade_Db_Table_ContactInformation();
        $data = $ContactInfo->getContactInfo($siteId);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Project.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj            = new self;
            $obj->id        = $data['ContactId'];
            $obj->siteId    = $data['SiteId'];
            $obj->email     = $data['Email'];
            $obj->phone     = $data['Phone'];
            $obj->website   = $data['WebAddress'];
            $obj->street    = $data['Street'];
            $obj->cityId    = $data['CityId'];
            $obj->stateId   = $data['RegionId'];
            $obj->countryId = $data['CountryId'];

            $obj->cityName    = $data['City'];
            $obj->regionName  = $data['Region'];
            $obj->countryName = $data['Country'];

        }
        return $obj;
    }

    /**
     * Stores data into db. If already exists create new record.
     *
     * @return void
     */
    public function save() {
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $data = array(
            'Street'     => trim($this->street),
            'CityId'     => $this->cityId,
            'City'       => $this->cityName,
            'RegionId'   => $this->stateId,
            'Region'     => $this->regionName,
            'CountryId'  => $this->countryId,
            'Country'    => $this->countryName,
            'SiteId'     => $this->siteId,
            'WebAddress' => $this->website,
            'Email'      => $this->email,
            'CreatedBy'  => $this->createdById,
            'CreatedOn'  => $this->createdOn,
            'ModifiedBy' => $this->modifiedById,
            'ModifiedOn' => date('Y-m-d H:i:s'),
        );
        if ($this->id != '') {
            $ContactInfo->editContactInfo($this->id, $data);
        } else {
            $this->id = $ContactInfo->addContactInfo($data);
        }
    }

    public function delete() {
        $ContactInfo = new Brigade_Db_Table_ContactInformation();
        $ContactInfo->deleteContactInfo($this->siteId);
    }

    /**
     * Get user data by the email attr contact info.
     */
    protected function _getUser() {
       $this->_user = User::getByEmail($this->email);
    }

    /**
     * Get contact country
     */
    protected function _getCountry() {
       $this->_country = Country::get($this->countryId);
    }

    /**
     * Get contact state
     */
    protected function _getState() {
       $this->_state = State::get($this->stateId);
    }

    /**
     * Get contact city
     */
    protected function _getCity() {
       $this->_city = City::get($this->cityId);
    }

    /**
     * Get user data by the email attr contact info.
     */
    protected function _getAddress() {
        //generate address
        $location = '';
        if ($this->street != '') {
            $location .= $this->street . "<br />";
        }
        if ($this->city) {
            $location .= $this->city->name;
            if($this->state || $this->country) {
                $location .= ', ';
            }
        }
        if ($this->state) {
            $location .= $this->state->name;
            if ($this->country) {
                $location .= ', ';
            }
        }
        if ($this->country) {
            $location .= $this->country->name;
        }

        $this->_address = $location;
    }
}
