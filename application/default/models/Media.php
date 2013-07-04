<?php
require_once 'Brigade/Db/Table/Media.php';

/**
 * Class Model Contact Information.
 *
 * @author Eamonn Pascal
 */
class Media {

    public $id;
    public $caption;
    public $mediaSize;
    public $systemMediaName;
    public $uploadedMediaName;
    public $active;
    public $isEmbed;
    public $createdBy;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $isPrimary;


    /**
     * TODO: Implement cache layer
     * @param siteId Media is loaded by SiteId not MediaId
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
        $Media  = new Brigade_Db_Table_Media();
        $data = $Media->getSiteMediaById($id);

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
        $obj = null;
        if ($data) {
            $obj                    = new self;
            $obj->id                = $data['MediaId'];
            $obj->caption           = $data['Caption'];
            $obj->mediaSize         = $data['MediaSize'];
            $obj->systemMediaName   = $data['SystemMediaName'];
            $obj->uploadedMediaName = $data['UploadedMediaName'];
            $obj->active            = $data['Active'];
            $obj->isEmbed           = $data['IsEmbed'];
            $obj->createdBy         = $data['CreatedBy'];
            $obj->createdOn         = $data['CreatedOn'];
            $obj->modifiedBy        = $data['ModifiedBy'];
            $obj->modifiedOn        = $data['ModifiedOn'];
            $obj->isPrimary         = $data['isPrimary'];
        }
        return $obj;
    }

    /**
     * Update/Create data into database
     *
     * @return void.
     */
    public function save() {
        $data = array(
            'Caption'           => $this->caption,
            'MediaSize'         => $this->mediaSize,
            'SystemMediaName'   => $this->systemMediaName,
            'UploadedMediaName' => $this->uploadedMediaName,
            'Active'            => $this->active,
            'IsEmbed'           => $this->isEmbed,
            'CreatedBy'         => $this->createdBy,
            'CreatedOn'         => $this->createdOn,
            'ModifiedBy'        => $this->modifiedBy,
            'ModifiedOn'        => $this->modifiedOn,
            'isPrimary'         => $this->isPrimary
        );

        $media = new Brigade_Db_Table_Media();
        if ($this->id != '') {
            $media->editMedia($this->id, $data);
        } else {
            $media->addMedia($data);
        }
    }


}
