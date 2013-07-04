<?php
require_once 'Brigade/Db/Table/LookupTable.php';

/**
 * Class Model Look up table
 *
 * @author Matias Gonzalez
 */
class Lookup {

    public $siteId;
    public $siteName;
    public $controller;
    public $fieldId;


    /**
     * TODO: Implement cache layer
     * @param $id Lookup is loaded by SiteId
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
        $Lookup = new Brigade_Db_Table_LookupTable();
        $data   = $Lookup->getBySiteId($id);

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
            $obj             = new self;
            $obj->siteId     = $data['SiteId'];
            $obj->siteName   = $data['SiteName'];
            $obj->controller = $data['Controller'];
            $obj->fieldId    = $data['FieldId'];
        }
        return $obj;
    }

    /**
     * Update/Create data into database
     *
     * @return void.
     */
    public function save() {

    }

    /**
     * Delete the dispatcher to the controller.
     *
     * @return void.
     */
    static public function delete($siteId) {
        $LookupTable = new Brigade_Db_Table_LookupTable();
        $LookupTable->deleteSite($siteId);
    }

    /**
     * Validate a string name in the look up table if is already in the database
     *
     * @param Strin $name Name to validate
     *
     * @return Boolean
     */
    static public function nameExists($name) {
        $Lookup = new Brigade_Db_Table_LookupTable();
        return $Lookup->isSiteNameExists($name);
    }

    /**
     * Create a lookup record for organization/network controller
     */
    static public function addOrganization(Organization $organization) {
        $Lookup = new Brigade_Db_Table_LookupTable();
        $Lookup->addSiteURL(array(
            'SiteName'   => $organization->urlName,
            'SiteId'     => $organization->id,
            'Controller' => 'nonprofit',
            'FieldId'    => 'NetworkId'
        ));
    }
}
