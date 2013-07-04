<?php
require_once 'Brigade/Db/Table/Files.php';

/**
 * Class Model File.
 *
 * @author Matias Gonzalez
 */
class File {

    public $id;
    public $caption;
    public $fileSize;
    public $systemFieName;
    public $uploadedFileName;
    public $active;
    public $createdBy;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $groupId;
    public $projectId;
    public $isPrivate;
    public $type;

    protected $group = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        $data  = $this->_getLimits($name);
        $attr  = '_'.$data[0];
        $param = $data[1];
        if (property_exists('Ticket', $attr)) {
            if (is_null($this->$attr)) {
                $method = '_get'.ucfirst($data[0]);
                if ($param != '') {
                    $this->$method($param);
                } else {
                    $this->$method();
                }
            }
            return $this->$attr;
        }
    }

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
     * Delete a file from db.
     *
     * @param String $id Id of the file.
     */
    static public function delete($id) {
        $Files = new Brigade_Db_Table_Files();
        $Files->deleteFile($id);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id File Id.
     */
    public function load($id) {
        $File = new Brigade_Db_Table_Files();
        $data = $File->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object File.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj                   = new self;
            $obj->id               = $data['FileId'];
            $obj->caption          = $data['Caption'];
            $obj->fileSize         = $data['FileSize'];
            $obj->systemFileName   = $data['SystemFileName'];
            $obj->uploadedFileName = $data['UploadedFileName'];
            $obj->active           = $data['Active'];
            $obj->createdBy        = $data['CreatedBy'];
            $obj->createdOn        = $data['CreatedOn'];
            $obj->modifiedBy       = $data['ModifiedBy'];
            $obj->modifiedOn       = $data['ModifiedOn'];
            $obj->groupId          = $data['GroupId'];
            $obj->isPrivate        = $data['isPrivate'];
            $obj->type             = $data['Type'];
        }
        return $obj;
    }

    /**
     * Save file into db.
     *
     * @return void
     */
    public function save() {
        $File     = new Brigade_Db_Table_Files();
        $data = array(
            'SystemFileName'   => $this->systemFieName,
            'UploadedFileName' => $this->uploadedFileName,
            'Caption'          => $this->caption,
            'Type'             => $this->type
        );
        if ($this->projectId) {
            $data['ProjectId'] = $this->projectId;
        } else {
            $data['GroupId'] = $this->groupId;
        }

        $this->id = $File->AddFile($data);
    }

    /**
     * Set group of the file
     *
     * @return void
     */
    protected function _getGroup() {
        $this->_group = Group::get($this->groupId);
    }
}
