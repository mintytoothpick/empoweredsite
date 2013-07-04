<?php
require_once 'Brigade/Db/Table/OrganizationMembersTitles.php';
require_once 'Base.php';

/**
 * Class Model for Members Titles.
 * This are all titles for the organization members
 *
 * @author Matias Gonzalez
 */
class MemberTitle extends Base {

    public $id;
    public $title;
    public $groupId;
    public $isDeleted = 0;
    public $organizationId;
    public $createdOn;
    public $modifiedOn;
    public $modifiedById;
    public $createdById;

    //Lazy
    protected $_group         = null;
    protected $_organization  = null;
    protected $_assignedCount = null;
    protected $_members       = null; //list of members assigned title

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
        if (property_exists('MemberTitle', $attr)) {
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
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Get members titles by organization
     *
     * @param Organization $org Organization
     *
     * @return List of members objects.
     */
    static public function getListByOrganization($org) {
        $OMT       = new Brigade_Db_Table_OrganizationMembersTitles();
        $titleList = $OMT->getByOrganization($org->id);
        $list      = array();
        if ($titleList) {
            foreach($titleList as $title) {
                // create objects project
                $list[] = self::_populateObject($title);
            }
        }
        return $list;
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $OMT = new Brigade_Db_Table_OrganizationMembersTitles();
        $data = $OMT->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Member.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj                 = new self;
            $obj->id             = $data['Id'];
            $obj->groupId        = $data['GroupId'];
            $obj->organizationId = $data['OrganizationId'];
            $obj->modifiedOn     = $data['ModifiedOn'];
            $obj->createdOn      = $data['CreatedOn'];
            $obj->title          = $data['Title'];
            $obj->createdById    = $data['CreatedBy'];
            $obj->modifiedById   = $data['ModifiedBy'];
            $obj->isDeleted      = $data['isDeleted'];
        }
        return $obj;
    }

    /**
     * Stores data into db. If already exists create new record.
     *
     * @return void
     */
    public function save() {
        $MT = new Brigade_Db_Table_OrganizationMembersTitles();
        $data = array(
            'GroupId'        => $this->groupId,
            'OrganizationId' => $this->organizationId,
            'ModifiedOn'     => date('Y-m-d H:i:s'),
            'Title'          => $this->title,
            'ModifiedOn'     => $this->modifiedOn,
            'ModifiedBy'     => $this->modifiedById,
            'CreatedBy'      => $this->createdById,
            'CreatedOn'      => $this->createdOn,
            'isDeleted'      => $this->isDeleted,
        );
        if ($this->id != '') {
            $MT->EditTitle($this->id, $data);
        } else {
            $this->id = $MT->AddTitle($data);
        }
    }

    /**
     * Get organization lazy attr
     */
    protected function _getOrganization() {
       $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Get group lazy attr
     */
    protected function _getGroup() {
       $this->_group = Group::get($this->groupId);
    }

    /**
     * Get assigned number of members with the title
     */
    protected function _getAssignedCount() {
        $this->_members       = Member::getByMemberTitle($this);
        $this->_assignedCount = count($this->_members);
    }
}
