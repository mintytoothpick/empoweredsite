<?php
require_once 'Brigade/Db/Table/Photo.php';

/**
 * Class Model Contact Information.
 *
 * @author Eamonn Pascal
 */
class Photo {

    public $id;
    public $projectId;
    public $groupId;
    public $description;
    public $mediaSize;
    public $systemMediaName;
    public $uploadedMediaName;
    public $isAlbumCover;
    public $createdById;
    public $createdOn;

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Delete the image from DB.
     *
     * @return void.
     */
    public function delete() {
        $Photo = new Brigade_Db_Table_Photo();
        $Photo->deletePhoto($this->id);
    }

    /**
     * Load information of the selected event.
     *
     * @param Integer $id Photo Id.
     *
     * @return Photo
     */
    public function load($id) {
        $Photo  = new Brigade_Db_Table_Photo();
        $data = $Photo->loadInfo($id);

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
            $obj               = new self;
            $obj->id           = $data['PhotoId'];
            $obj->projectId    = $data['ProjectId'];
            $obj->groupId      = $data['GroupId'];
            $obj->description  = $data['Description'];
            $obj->isAlbumCover = $data['isAlbumCover'];

            $obj->systemMediaName   = $data['SystemMediaName'];
            $obj->uploadedMediaName = $data['UploadedMediaName'];
            $obj->mediaSize         = $data['MediaSize'];
            $obj->createdById       = $data['CreatedBy'];
            $obj->createdOn         = $data['CreatedOn'];

        }
        return $obj;
    }

    /**
     * Save object activity into database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'PhotoId'           => $this->id,
            'ProjectId'         => $this->projectId,
            'GroupId'           => $this->groupId,
            'Description'       => $this->description,
            'isAlbumCover'      => $this->isAlbumCover,
            'SystemMediaName'   => $this->systemMediaName,
            'UploadedMediaName' => $this->uploadedMediaName,
            'CreatedBy'         => $this->createdById,
            'CreatedOn'         => $this->createdOn,
        );

        $pho = new Brigade_Db_Table_Photo();
        $pho->addPhoto($data);
    }

    /**
     * Set the photo as the album cover.
     *
     * @return void
     */
    public function setAlbumCover() {
        $Photo = new Brigade_Db_Table_Photo();
        $Photo->SetAsPrimary($this->id, array('isAlbumCover' => 1));
    }

    /**
     * Get the photos associated with a given string project id ($projectId)
     *
     * @param String $projectId project id to filter photos by
     *
     * @return list of photo objects
     */
    static public function getPhotosByProject($projectId) {
        $Photos  = new Brigade_Db_Table_Photo();
        $photos = $Photos->getInitiativePhotos($projectId);
        $list     = array();
        foreach($photos as $photo) {
            // create objects project
            $list[] = self::_populateObject($photo);
        }
        return $list;
    }

}
