<?php
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'User.php';
require_once 'Project.php';

/**
 * Class Model Volunteer.
 *
 * @author Matias Gonzalez
 */
class Volunteer extends Base {

    public $id;
    public $projectId;
    public $userId;
    public $programId;
    public $groupId;
    public $organizationId;
    public $userDonationGoal;
    public $userDescription;
    public $createdById;
    public $createdOn;
    public $modifiedById;
    public $modifiedOn;
    public $isDeleted       = 0;
    public $isDenied        = 0;
    public $isActive        = 0;
    public $documentsSigned = 0;
    public $dateEnded       = '0000-00-00 00:00:00';
    public $signatureName   = '';
    public $signatureAge    = '';
    public $signatureDate   = '';

    protected $_user      = null;
    protected $_raised    = null;
    protected $_project   = null;
    protected $_donations = null;

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
        if (property_exists('Volunteer', $attr)) {
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
     * Load information of the selected project.
     *
     * @param String $id Project Id.
     */
    public function load($id) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $data      = $Volunteer->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Set status active to volunteer.
     */
    public function activate() {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $Volunteer->activateVolunteer($this->id);
    }

    /**
     * Set status deleted to volunteer.
     */
    public function delete() {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $Volunteer->removeVolunteer($this->id, 1);
    }

    public function stopVolunteering() {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $Volunteer->stopVolunteering($this->id);
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
            $obj                   = new self;
            $obj->id               = $data['VolunteerId'];
            $obj->projectId        = $data['ProjectId'];
            $obj->groupId          = $data['GroupId'];
            $obj->programId        = $data['ProgramId'];
            $obj->organizationId   = $data['NetworkId'];
            $obj->userId           = $data['UserId'];
            $obj->createdById      = $data['CreatedBy'];
            $obj->createdOn        = $data['CreatedOn'];
            $obj->modifiedById     = $data['ModifiedBy'];
            $obj->modifiedOn       = $data['ModifiedOn'];
            $obj->isActive         = $data['isActive'];
            $obj->isDeleted        = $data['IsDeleted'];
            $obj->isDenied         = $data['IsDenied'];
            $obj->userDonationGoal = $data['UserDonationGoal'];
            $obj->userDescription  = $data['UserDescription'];
            $obj->dateEnded        = $data['DateEnded'];
            $obj->documentsSigned  = $data['DocumentsSigned'];
            $obj->signatureName    = $data['SignatureName'];
            $obj->signatureAge     = $data['SignatureAge'];
            $obj->signatureDate    = $data['SignatureDate'];
        }
        return $obj;
    }

    /**
     * Update data into database
     *
     * @return Group ID.
     */
    public function save() {
        $data = array(
            'ProgramId'        => $this->programId,
            'ProjectId'        => $this->projectId,
            'GroupId'          => $this->groupId,
            'NetworkId'        => $this->organizationId,
            'UserId'           => $this->userId,
            'ModifiedBy'       => $this->modifiedById,
            'ModifiedOn'       => date('Y-m-d H:i:s'),
            'CreatedBy'        => $this->createdById,
            'CreatedOn'        => $this->createdOn,
            'isActive'         => $this->isActive,
            'IsDeleted'        => $this->isDeleted,
            'IsDenied'         => $this->isDenied,
            'UserDonationGoal' => $this->userDonationGoal,
            'UserDescription'  => $this->userDescription,
            'DateEnded'        => $this->dateEnded,
            'DocumentsSigned'  => $this->documentsSigned,
            'SignatureName'    => $this->signatureName,
            'SignatureAge'     => $this->signatureAge,
            'SignatureDate'    => $this->signatureDate

        );

        $Vol = new Brigade_Db_Table_Volunteers();
        if (!empty($this->id)) {
            $Vol->updateInfo($data, $this->id);
        } else {
            $this->id = $Vol->createVolunteer($data);
        }
        return $this->id;
    }

    /**
     * Return active volunteers for an specific project.
     *
     */
    public static function getByProject(Project $Project) {
        $Volunteer  = new Brigade_Db_Table_Volunteers();
        if($Project->type == 1) {
            $volunteers = $Volunteer->getActiveFundraisersForProject($Project->id);
        } else {
            $volunteers = $Volunteer->getActiveVolunteersForProject($Project->id);
        }
        $list       = array();
        foreach($volunteers as $volunteer) {
            // create objects project
            $list[] = self::_populateObject($volunteer);
        }
        return $list;
    }

    /**
     * Return active volunteer for a specific project and user.
     *
     * @param User    $user
     * @param Project $project
     * @param Boolean $active
     *
     * @return Volunteer Instance
     */
    public static function getByUserAndProject(User $user, Project $Project,
        $active = true
    ) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $data      = $Volunteer->loadVolunteerByProjectAndUser(
                        $Project->id,
                        $user->id,
                        $active
        );

        return self::_populateObject($data);
    }

    /**
     * Return all active volunteer objects for one user
     *
     * @param User $user to filter volunteer objects
     *
     * @return Array List of volunteer objects
     */
    public static function getByUser(User $user) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $data      = $Volunteer->loadVolunteersByUser($user->id);
        $list      = array();
        foreach($data as $volunteer) {
            // create objects project
            $list[] = self::_populateObject($volunteer);
        }
        return $list;
    }

    /**
     * Return all active volunteer objects for one user
     *
     * @param User $user to filter volunteer objects
     *
     * @return Array List of volunteer objects
     */
    public static function getByUserAndGroup(User $user, Group $group) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $data      = $Volunteer->loadVolunteersByUserAndGroup($user->id, $group->id);
        $list      = array();
        foreach($data as $volunteer) {
            // create objects project
            $list[] = self::_populateObject($volunteer);
        }
        return $list;
    }

    /**
     * Return active supporters for an specific group.
     *
     */
    public static function countSupportersByGroup($groupId) {
        $Volunteer  = new Brigade_Db_Table_Volunteers();
        $supporters = $Volunteer->countActiveSupportersForGroup($groupId);
        return $supporters;
    }

    /**
     * Activate pendings requests for volunteers not deleted waiting membership
     * approval.
     *
     * @param Group $group
     * @param User  $user
     *
     * @return void
     */
    public static function activateByGroupAndUser($group, $user) {
        $Volunteer  = new Brigade_Db_Table_Volunteers();
        $Volunteer->activateByGroupAndUser($group->id, $user->id);
    }


    /**
     * Get user object.
     *
     * @return User $user Object
     */
    protected function _getUser() {
        $this->_user = User::get($this->userId);
    }

    /**
     * Get total volunteer raised
     *
     * @return Float Total raised for the project.
     */
    protected function _getRaised() {
        $PD  = new Brigade_Db_Table_ProjectDonations();
        $res = $PD->getDonationsByVolunteer($this->projectId, $this->userId);

        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }
    }

    /**
     * Get project object.
     *
     * @return void
     */
    protected function _getProject() {
        $this->_project = Project::get($this->projectId);
    }

    /**
     * Get user donations.
     *
     * @return void
     */
    protected function _getDonations() {
        $this->_donations = Donation::getListByUserAndProject($this->user, $this->project);
    }
}
