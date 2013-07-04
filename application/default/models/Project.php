<?php
require_once 'Brigade/Db/Table/MediaSite.php';
require_once 'Brigade/Db/Table/Projects.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Activity.php';
require_once 'Base.php';
require_once 'Contact.php';
require_once 'Group.php';
require_once 'Media.php';
require_once 'Photo.php';
require_once 'Volunteer.php';
require_once 'FundraisingMessage.php';
require_once 'Organization.php';
require_once 'Survey.php';
require_once 'Lookup.php';
require_once 'Donation.php';
require_once 'BluePay.php';
//require_once 'EmpoweredStripe.php';
require_once 'MembershipFund.php';

/**
 * Class Model Project.
 *
 * @author Matias Gonzalez
 */
class Project extends Base {

    public static $NETWORKS =  array(
            'DAF7E701-4143-4636-B3A9-CB9469D44178', // Usa
            'DB04F20F-59FE-468F-8E55-AD75F60FB0CB', // Canada
            '547086E0-5456-4631-AB2A-BA781E7DB9A7'  // UK
        );

    public $id;
    public $name;
    public $urlName;
    public $requirements;
    public $how;
    public $startDate;
    public $endDate;
    public $volunteerGoal;
    public $donationGoal;
    public $description;
    public $createdById;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $status;
    public $volunteerMinGoal;
    public $isFundraising;
    public $type;
    public $isOpen;
    public $isRecurring;
    public $userId;
    public $googleId;
    public $paypalId;
    public $bluePayId;
    public $stripeId;
    public $currency;
    public $percentageFee;
    public $allowPercentageFee;
    public $hasSharedSocialNetworks;
    public $hasUploadedMembers;
    public $groupId;
    public $programId;
    public $organizationId;
    public $isDeleted;

    const ajaxLimit = 5;

    //magic getters
    protected $_contact      = null;
    protected $_donations    = null;
    protected $_group        = null;
    protected $_program      = null;
    protected $_organization = null;
    protected $_volunteers   = null;
    protected $_user         = null;
    protected $_raised       = null;
    protected $_activityFeed = null;
    protected $_logo         = null;
    protected $_photos       = null;
    protected $_volunteer    = null; //to get data of one volunteer
    protected $_bluePay      = null;
    protected $_stripe       = null;
    protected $_createdBy    = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        if ($name == 'name') {
            if ($this->isDeleted) {
                return $this->name . ' [Deleted]';
            }
        }
        $data = $this->_getLimits($name);
        if ($data[0] == 'volunteers') {
            if (is_null($this->_volunteers)) {
                $this->_getVolunteers();
            }
            return $this->_volunteers;
        } elseif ($data[0] == 'donations') {
            if (is_null($this->_donations)) {
                $this->_getDonations();
            }
            return $this->_donations;
        } elseif ($data[0] == 'raised') {
            if (is_null($this->_raised)) {
                $this->_getRaised();
            }
            return $this->_raised;
        } elseif ($data[0] == 'group') {
            if (is_null($this->_group)) {
                $this->_getGroup();
            }
            return $this->_group;
        } elseif ($data[0] == 'program') {
            if (is_null($this->_program)) {
                $this->_getProgram();
            }
            return $this->_program;
        } elseif ($data[0] == 'volunteer') {
            if (is_null($this->_volunteer)) {
                $this->_getVolunteer();
            }
            return $this->_volunteer;
        } elseif ($data[0] == 'organization') {
            if (is_null($this->_organization)) {
                $this->_getOrganization();
            }
            return $this->_organization;
        } elseif ($data[0] == 'activityFeed') {
            if (is_null($this->_activityFeed)) {
                $this->_getActivityFeed($data[1]);
            }
            return $this->_activityFeed;
        } else if ($data[0] == 'contact') {
            if (is_null($this->_contact)) {
                $this->_getContact();
            }
            return $this->_contact;
        } else if ($data[0] == 'logo') {
            if (is_null($this->_logo)) {
                $this->_getLogo();
            }
            return $this->_logo;
        } elseif ($data[0] == 'photos') {
            if (is_null($this->_photos)) {
                $this->_getPhotos();
            }
            return $this->_photos;
        } elseif ($data[0] == 'bluePay') {
            if (is_null($this->_bluePay)) {
                $this->_getBluePay();
            }
            return $this->_bluePay;
        } elseif ($data[0] == 'stripe') {
            if (is_null($this->_stripe)) {
                $this->_getStripe();
            }
            return $this->_stripe;
        } elseif ($data[0] == 'createdBy') {
            if (is_null($this->_createdBy)) {
                $this->_getCreatedBy();
            }
            return $this->_createdBy;
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
        $Projects = new Brigade_Db_Table_Projects();
        $data     = $Projects->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Get projects by object group.
     *
     * @param Object $Group  Group object to filter projects
     * @param String $status Status to filter projects by date
     *
     * @return List of projects objects.
     */
    static public function getListByGroup(Group $Group,
        $status = 'upcoming', $type = null, $search = false
    ) {
        $Project  = new Brigade_Db_Table_Projects();
        $projects = $Project->getInitiatives($Group->id, $status, $type, $search);
        $list     = array();
        foreach($projects as $project) {
            // create objects project
            $list[] = self::_populateObject($project);
        }
        return $list;
    }

    /**
     * Get projects by programId.
     *
     * @param Program $Program Program to filter projects by
     * @param String  $status  Status of the project to filter
     * @param Integer $type    Type (vol act or fund camp)
     * @param String  $search  Search text to filter name and desc
     * @param Integer $limit   Limit of results needed
     *
     * @return List of projects objects.
     */
    static public function getListByProgram(Program $Program,
        $status = 'upcoming', $type = 'all', $search = false, $limit = false
    ) {
        $Project  = new Brigade_Db_Table_Projects();
        $projects = $Project->getProgramInitiatives(
                        ($Program->coalitions) ? $Program->coalitionIds : $Program->id,
                        $status,
                        $type,
                        $search,
                        $limit);
        $list     = array();
        foreach($projects as $project) {
            // create objects project
            $list[] = self::_populateObject($project);
        }
        return $list;
    }

    /**
     * Get projects by organizationId.
     *
     * @param Organization $organization Organization to filter projects by
     * @param String       $status       Status of the project to filter
     * @param Integer      $type         Type (vol act or fund camp)
     * @param String       $search       Search text to filter name and desc
     * @param Integer      $limit        Limit of results needed
     *
     * @return List of projects objects.
     */
    static public function getListByOrganization(Organization $Organization,
        $status = 'all', $type = null, $search = false, $fundraising = false
    ) {
        $Project  = new Brigade_Db_Table_Projects();
        $projects = $Project->getOrganizationInitiatives(
                        $Organization->id,
                        $status,
                        $type,
                        $search,
                        $fundraising);
        $list     = array();
        foreach($projects as $project) {
            // create objects project
            $list[] = self::_populateObject($project);
        }
        return $list;
    }

    /**
     * Get most upcoming project by string groupId.
     *
     * @param String $groupId  GroupId to filter projects
     *
     * @return List of projects objects.
     */
    static public function getFeaturedGroupInitiative($groupId) {
        $Project = new Brigade_Db_Table_Projects();
        $project = $Project->getUpcomingGroupInitiative($groupId);
        if(empty($project['ProjectId'])) {
            $project = $Project->getPastGroupInitiative($groupId);
        }
        return self::_populateObject($project);
    }

    /**
     * Get most upcoming project by object string userId.
     *
     * @param String $userId  UserId to filter projects
     *
     * @return List of projects objects.
     */
    static public function getFeaturedUserInitiative($userId) {
        $Volunteers = new Brigade_Db_Table_Volunteers();
        $project    = $Volunteers->getUpcomingUserInitiative($userId);
        if(empty($project['ProjectId'])) {
            $project = $Volunteers->getPastUserInitiative($userId);
        }
        return self::_populateObject($project);
    }

    /**
     * Get projects by user Id.
     *
     * @param string  $userId Id of user to filter projects
     * @param String  $status Status to filter projects by date
     * @param String  $type   Type to filter projects(fund campaign or vol. activity)
     * @param Integer $limit  Limit number of records
     * @param Integer $page   Number of page.
     *
     * @return List of projects objects.
     */
    static public function getListByUser(User $User, $status = 'all',
        $type = 'all', $limit = false, $page = false
    ) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $projects  = $Volunteer->getUserInitiatives($User->id, $status, $type, $limit, $page);
        $list      = array();
        foreach($projects as $project) {
            // create objects project
            $list[] = self::_populateObject($project);
        }
        return $list;
    }

    /**
     * Get projects by user Id.
     *
     * @param string  $userId Id of user to filter projects
     * @param String  $status Status to filter projects by date
     * @param String  $type   Type to filter projects(fund campaign or vol. activity)
     *
     * @return List of projects objects.
     */
    public static function countByUser(User $User, $status = 'upcoming', $type = null) {
        $Volunteer = new Brigade_Db_Table_Volunteers();
        $res       = $Volunteer->countUserInitiatives($User->id, $status, $type);
        if (is_null($res)) {
            return 0;
        } else {
            return $res['count'];
        }
    }

    /**
     * Get count of projects by organization.
     *
     * @param string  $userId Id of user to filter projects
     * @param String  $status Status to filter projects by date
     * @param String  $type   Type to filter projects(fund campaign or vol. activity)
     *
     * @return Number of projects.
     */
    public static function countByOrganization(Organization $Organization,
        $status = 'upcoming', $type = null
    ) {
        $Project = new Brigade_Db_Table_Projects();
        $res     = $Project->countByOrganization($Organization->id, $status, $type);
        if (is_null($res)) {
            return 0;
        } else {
            return $res['Total'];
        }
    }

    /**
     * Count projects by programs.
     *
     * @param Program $program    program to count groups
     * @param Integer $type       Type of project (fund camp. or vol act.)
     *
     * @return List of group objects.
     */
    static public function countByProgram(Program $program, $type) {
        $Project = new Brigade_Db_Table_Projects();
        if ($program->coalitions) {
            $res = $Project->countByPrograms($program->coalitionIds, $type);
        } else {
            $res = $Project->countByPrograms($program->id, $type);
        }
        return $res['Total'];
    }

    /**
     * Get the total donated by user.
     *
     * @param User    $user   User instance
     * @param Boolean $active Active volunteer
     *
     * @return Float
     */
    public function getVolunteerByUser(User $user, $active = true) {
        $this->_volunteer = Volunteer::getByUserAndProject($user, $this, $active);
        return $this->_volunteer;
    }

    /**
     * If the project is from GB org.
     *
     * @return bool
     */
    public function isGlobalProject() {
        return in_array($this->organizationId, Project::$NETWORKS);
    }

    /**
     * Validate if the project has survey to fill.
     *
     * @return Boolean If has or not survey.
     */
    public function hasSurvey() {
        $has = false;
        if ($this->type === '0' &&
            in_array($this->organizationId, Project::$NETWORKS)
        ) {
            $has = true;
        }
        return $has;
    }

    /**
     * Delete project.
     * Change deleted status.
     *
     * @return void
     */
    public function delete() {
        // delete records from lookup_table
        Lookup::delete($this->id);

        //delete roles
        User::deleteRolesBySite($this->id);

        //delete from db
        $project = new Brigade_Db_Table_Projects();
        $project->deleteProject($this->id);

        if ($this->contact) {
            $this->contact->delete();
        }
    }

    /**
     * Add volunteer to project.
     *
     * @param User $user User to participate as a volunteer.
     *
     * @return void.
     */
    public function addVolunteer(User $user) {
        $added     = false;
        $volunteer = $this->getVolunteerByUser($user);
        if (is_null($volunteer)) {
            $volunteer = $this->getVolunteerByUser($user, false);
            if (is_null($volunteer)) {
                $volunteer                   = new Volunteer();
                $volunteer->userId           = $user->id;
                $volunteer->projectId        = $this->id;
                $volunteer->userDonationGoal = $this->volunteerMinGoal;
                $volunteer->dateEnded        = $this->endDate;

                $active = true;
                if ($this->status == 'Close') {
                    $active = false;
                } else {
                    if (!empty($this->groupId) && $this->group &&
                        $this->group->activityRequiresMembership &&
                        !$this->group->isMember($user)
                    ) {
                        $active = false;
                    }
                }
                $volunteer->isActive = $active;
                if (!empty($this->groupId) && $this->group) {
                    $volunteer->groupId = $this->group->id;
                }
                if (!empty($this->programId) && $this->program) {
                    $volunteer->programId = $this->program->id;
                }
                if (!empty($this->organizationId) && $this->organization) {
                    $volunteer->organizationId = $this->organization->id;
                }
                $volunteer->save();
                $added = true;
            } else {
                $volunteer->activate();
                $added = true;
            }
        }

        $this->_volunteer = $volunteer;

        return $added;
    }

    /**
     * Remove volunteer from project.
     *
     * @param User $user User to remove volunteer.
     *
     * @return void.
     */
    public function stopVolunteering(User $user) {
        $volunteer = $this->getVolunteerByUser($user);
        if (!is_null($volunteer)) {
            $volunteer->stopVolunteering();
        }
    }

    /**
     * Return volunteer fundraising message of the project.
     *
     * @param User $user User instance.
     *
     * @return FundraisingMessage fund. message
     */
    public function getMessageUser(User $user) {
        $volunteer = $this->getVolunteerByUser($user);
        return FundraisingMessage::getByProjectVolunteer($this, $volunteer);
    }

    /**
     *
     */
    public function updateMessageUser(User $user, $message) {
        $msg = $this->getMessageUser($user);
        if (!$msg) {
            $volunteer = $this->getVolunteerByUser($user);
            $msg       = new FundraisingMessage();

            $msg->volunteerId = $volunteer->id;
            $msg->projectId   = $this->id;
        }
        $msg->text = $message;
        $msg->save();
    }

    /**
     * Get a list of projects merged for programs coalitions.
     *
     * @param Array $programIds List of ids of programs coalitions
     *
     * @return Array Group
     */
    static public function getByProgramIds($programIds) {
        $projects     = new Brigade_Db_Table_Projects();
        $projectsList = $projects->getByCoalitionProgram($programIds);
        $list         = array();
        foreach($projectsList as $project) {
            // create objects project
            $list[] = self::_populateObject($project);
        }

        return $list;
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Project.
     */
    static protected function _populateObject($data) {
        $objProject = null;
        if ($data) {
            $objProject     = new self;
            $objProject->id = $data['ProjectId'];

            $objProject->groupId          = $data['GroupId'];
            $objProject->programId        = $data['ProgramId'];
            $objProject->organizationId   = $data['NetworkId'];
            $objProject->createdById      = $data['CreatedBy'];
            $objProject->createdOn        = $data['CreatedOn'];
            $objProject->modifiedBy       = $data['ModifiedBy'];
            $objProject->modifiedOn       = $data['ModifiedOn'];
            $objProject->isDeleted        = $data['isDeleted'];

            $objProject->name                    = $data['Name'];
            $objProject->urlName                 = $data['URLName'];
            $objProject->requirements            = $data['Requirements'];
            $objProject->how                     = $data['How'];
            $objProject->startDate               = $data['StartDate'];
            $objProject->endDate                 = $data['EndDate'];
            $objProject->volunteerGoal           = $data['VolunteerGoal'];
            $objProject->donationGoal            = $data['DonationGoal'];
            $objProject->description             = $data['Description'];
            $objProject->status                  = $data['Status'];
            $objProject->volunteerMinGoal        = $data['VolunteerMinimumGoal'];
            $objProject->isFundraising           = $data['isFundraising'];
            $objProject->type                    = $data['Type'];
            $objProject->isOpen                  = $data['isOpen'];
            $objProject->isRecurring             = $data['isRecurring'];
            $objProject->userId                  = $data['UserId'];
            $objProject->googleId                = $data['GoogleCheckoutAccountId'];
            $objProject->paypalId                = $data['PaypalAccountId'];
            $objProject->bluePayId               = $data['BluePayAccountId'];
            //$objProject->stripeId                = $data['StripeId'];
            $objProject->currency                = $data['Currency'];
            $objProject->percentageFee           = $data['PercentageFee'];
            $objProject->allowPercentageFee      = $data['allowPercentageFee'];
            $objProject->hasSharedSocialNetworks = $data['hasSharedSocialNetworks'];
            $objProject->hasUploadedMembers      = $data['hasUploadedMembers'];
        }
        return $objProject;
    }

    public function save() {

        $data = array(
            'ProjectId'     => $this->id,
            'GroupId'       => $this->groupId,
            'ProgramId'     => $this->programId,
            'NetworkId'     => $this->organizationId,
            'CreatedBy'     => $this->createdById,
            'CreatedOn'     => $this->createdOn,
            'ModifiedBy'    => $this->modifiedBy,
            'ModifiedOn'    => $this->modifiedOn,
            'Name'          => $this->name,
            'URLName'       => $this->urlName,
            'Requirements'  => $this->requirements,
            'How'           => $this->how,
            'StartDate'     => $this->startDate,
            'EndDate'       => $this->endDate,
            'VolunteerGoal' => $this->volunteerGoal,
            'DonationGoal'  => $this->donationGoal,
            'Description'   => $this->description,
            'Status'        => $this->status,
            'isFundraising' => $this->isFundraising,
            'Type'          => $this->type,
            'isOpen'        => $this->isOpen,
            'isRecurring'   => $this->isRecurring,
            'UserId'        => $this->userId,
            'GoogleCheckoutAccountId' => $this->googleId,
            'PaypalAccountId'         => $this->paypalId,
            'BluePayAccountId'        => $this->bluePayId,
            //'StripeId'                => $this->stripeId,
            'Currency'                => $this->currency,
            'VolunteerMinimumGoal'    => $this->volunteerMinGoal,
            'PercentageFee'           => $this->percentageFee,
            'allowPercentageFee'      => $this->allowPercentageFee,
            'hasSharedSocialNetworks' => $this->hasSharedSocialNetworks,
            'hasUploadedMembers'      => $this->hasUploadedMembers
        );

        $Project = new Brigade_Db_Table_Projects();
        if ($this->id != '') {
            $Project->edit($this->id, $data);
        } else {
            $Project->add($data);
        }
    }

    /**
     * Gets the list of volunteers of the project.
     *
     * @return void
     */
    protected function _getVolunteers() {
        $this->_volunteers = Volunteer::getByProject($this);
    }

    /**
     * Get last volunteer used for project.
     *
     * @return void
     */
    protected function _getVolunteer() {
        return $this->_volunteer;
    }

    /**
     * Get total project raised
     *
     * @return void
     */
    protected function _getRaised() {
        $PD  = new Brigade_Db_Table_ProjectDonations();
        $res = $PD->getDonationsByProject($this->id);
        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }

        //Get funds transfered from membership chapter
        $this->_raised += $this->getMembershipFunds();
    }

    /**
     * Get amount raised with general destination (not for volunteers)
     *
     * @return double amount
     */
    public function getGeneralDonations() {
        $PD = new Brigade_Db_Table_ProjectDonations();
        return $PD->getGeneralDonations($this->id);
    }

    /**
     * Get amount transfered manually from membership funds
     *
     * @return double amount
     */
    public function getMembershipFunds() {
        $config       = Zend_Registry::get('configuration');
        $configMember = $config->chapter->membership;
        if(!empty($this->organizationId) &&
           in_array($this->organizationId, $configMember->active->toArray())
        ) {
            //Get funds transfered from membership chapter
            return MembershipFund::getProjectFunds($this);
        }
        return 0;
    }

    /**
     * Return list of donations
     *
     * @return Array Donation
     */
    protected function _getDonations() {
        $this->_donations = Donation::getListByProject($this);
    }

    /**
     * Gets the group of the project.
     *
     * @return void
     */
    protected function _getGroup() {
        $this->_group = Group::get($this->groupId);
    }

    /**
     * Gets the program of the project.
     *
     * @return void
     */
    protected function _getProgram() {
        if ($this->programId) {
            $this->_program = Program::get($this->programId);
        } else {
            $this->_program = false;
        }
    }

    /**
     * Gets the survey of the project for a user
     *
     * @return void
     */
    public function getSurvey($user) {
        if ($this->hasSurvey()) {
            $this->_survey = Survey::getByProjectAndUser($this, $user);
        } else {
            $this->_survey = false;
        }
    }

    /**
     * Set organization lazy attr
     *
     * @return void.
     */
    protected function _getOrganization() {
        $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Gets activity feed of the project.
     *
     * @return void
     */
    protected function _getActivityFeed($limit = false) {
        $this->_activityFeed = Activity::getByProject($this, $limit);
    }

    /**
     * Set contact lazy attr
     *
     * @return void.
     */
    protected function _getContact() {
        $this->_contact = Contact::get($this->id);
    }

    /**
     * Set logo lazy attr
     *
     * @return void.
     */
    protected function _getLogo() {
        $MS  =  new Brigade_Db_Table_MediaSite();
        $mediaId  =  $MS->getMediaIdBySiteId($this->id);
        $this->_logo = Media::get($mediaId);
    }

    /**
     * Set contact lazy attr
     *
     * @return void.
     */
    protected function _getPhotos() {
        $this->_photos = Photo::getPhotosByProject($this->id);
    }

    /**
     * Set contact lazy attr CreatedBy
     *
     * @return void.
     */
    protected function _getCreatedBy() {
        $this->_createdBy = User::get($this->createdById);
    }

    /**
     * Set bluepay lazy attr
     *
     * @return void.
     */
    protected function _getStripe() {
        /*if ($this->stripeId && $this->stripeId > 0) {
            $this->_stripe = EmpoweredStripe::get($this->stripeId);
        } else {
            $this->_stripe = false;
        }*/
        $this->_stripe = false;
    }

    protected function _getBluePay() {
        $this->_bluePay = BluePay::get($this->bluePayId);
    }

    public function getDaysToGo() {
        return round((strtotime($this->startDate)-time())/86400);
    }

    public function isUpcoming() {
        return (strtotime($this->startDate) - time()) >= 0;
    }

    public function isInProgress() {
        return (strtotime($this->startDate) - time()) < 0 && ( (strtotime($this->endDate) - time()) > 0 || $this->endDate == '0000-00-00 00:00:00' );
    }

    public function isFinished() {
        return $this->endDate != '0000-00-00 00:00:00' && (strtotime($this->endDate)-time()) < 0;
    }

    public function getProjectStatus() {
        if ($this->isUpcoming()) {
             return 'Upcoming';
        } else {
            return 'Recent';
        }
    }

}
