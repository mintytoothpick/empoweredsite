<?php
require_once 'Brigade/Db/Table/Projects.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Brigade/Db/Table/GroupMembershipRequest.php';
require_once 'Base.php';
require_once 'Program.php';
require_once 'Member.php';
require_once 'Project.php';
require_once 'Organization.php';
require_once 'Event.php';
require_once 'Contact.php';
require_once 'Lookup.php';
require_once 'BluePay.php';
require_once 'Payment.php';
require_once 'Role.php';

/**
 * Class Model Group.
 *
 * @author Matias Gonzalez
 */
class Group extends Base {

    public $id;
    public $name;
    public $urlName;
    public $description;
    public $currency;
    public $googleId  = 0;
    public $paypalId  = 0;
    public $bluePayId = 0;
    public $organizationId;
    public $programId;
    public $logoMediaId;
    public $bannerMediaId;
    public $isOpen;
    public $isActive;
    public $createdBy;
    public $createdOn;
    public $modifiedOn;
    public $modifiedBy;
    public $membershipFeeCurrency;
    public $activityRequiresMembership = 0;
    public $hasSharedSocialNetworks    = 0;
    public $hasUploadedMembers         = 0;
    public $hasMembershipFee           = false;
    public $hasAssignedAdmins          = 0;
    public $fundraiseMembershipFee     = false; //fee also as a donation

    // Lazy
    protected $_initiatives         = null;
    protected $_upcomingInitiatives = null;
    protected $_program             = null;
    protected $_organization        = null;
    protected $_activities          = null;
    protected $_campaigns           = null;
    protected $_events              = null;
    protected $_contact             = null;
    protected $_logo                = null;
    protected $_raised              = null;
    protected $_members             = null;
    protected $_activityWall        = null;
    protected $_donations           = null;
    protected $_bluePay             = null; //new payment gateway
    protected $_payments            = null; //for membership fee

    // Membership settings amounts
    protected $_membershipDonationAmounts = null; //array of amounts

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
        if (property_exists('Group', $attr)) {
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
     * Load information of the selected group.
     *
     * @param String $id Group Id.
     */
    public function load($id) {
        $Groups = new Brigade_Db_Table_Groups();
        $data   = $Groups->loadInfo($id, false);

        return $this->_populateObject($data);
    }

    /**
     * Get groups by organization Id.
     *
     * @param String $organizationId  organization id to filter group
     *
     * @return List of group objects.
     */
    static public function getListByOrganization($organizationId, $searchText = false) {
        $Groups     = new Brigade_Db_Table_Groups();
        $group_list = $Groups->getOrganizationGroups($organizationId, $searchText);
        $list       = array();
        foreach($group_list as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }
        return $list;
    }

    /**
     * Get groups by program Id.
     *
     * @param Program $program    program/s to filter group
     * @param String  $searchText filter text
     * @param Integer $limit      number of limit
     *
     * @return List of group objects.
     */
    static public function getListByProgram($program, $searchText = false,
        $limit = false
    ) {
        $Groups    = new Brigade_Db_Table_Groups();
        $groupList = $Groups->getProgramGroups(
                       ($program->coalitions) ? $program->coalitionIds : $program->id,
                       $searchText,
                       $limit);
        $list      =  array();
        foreach($groupList as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }
        return $list;
    }


    /**
     * Return all groups/chapters that charges a fee to all members.
     *
     * @return List of group objects.
     */
    public static function getByMembershipFee($org = false) {
        $Groups    = new Brigade_Db_Table_Groups();
        $groupList = $Groups->getByMembershipFee(($org) ? $org->id : false);
        $list      = array();
        foreach($groupList as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }
        return $list;
    }

    /**
     * Return all groups/chapters that not charges a fee to all members.
     *
     * @return List of group objects.
     */
    public static function getByNonMembershipFee($org = false) {
        $Groups    = new Brigade_Db_Table_Groups();
        $groupList = $Groups->getByNonMembershipFee(($org) ? $org->id : false);
        $list      = array();
        foreach($groupList as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }
        return $list;
    }

    /**
     * Return list of chapters related to membership stats.
     * Used for chapters that don't have membership fee.
     *
     * @param MembershipStat $stat
     *
     * @return List of group objects.
     */
    public static function getByMembershipStat($stat) {
        $MSC       = new Brigade_Db_Table_MembershipStatChapters();
        $groupList = $MSC->getGroups($stat->id);
        $list      = array();
        foreach($groupList as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }
        return $list;
    }

    /**
     * Count groups by programs.
     *
     * @param Program $program    program to count groups
     * @param Boolean $coalitions If true,count all groups that are in the
     *                            coalition programs.
     *
     * @return List of group objects.
     */
    static public function countByProgram(Program $program) {
        $Groups = new Brigade_Db_Table_Groups();
        if ($program->coalitions) {
            $res = $Groups->countByPrograms($program->coalitionIds);
        } else {
            $res = $Groups->countByPrograms($program->id);
        }
        return $res['Total'];
    }

    /**
     * Get groups that a user is a member of
     *
     * @param String $userId  user id to filter groups
     *
     * @return List of group objects.
     */
    static public function getUserAffiliations($userId) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $group_list   = $GroupMembers->getUserGroupAffiliations($userId);
        $list         = array();
        foreach($group_list as $group) {
            // create objects project
            $list[] = self::_populateObject($group);
        }

        return $list;
    }

    /**
     * Count the number of pending requests membership.
     *
     * @param String $groupId
     *
     * @return Integer.
     */
    static public function countPendingRequests($groupId) {
        $Requests = new Brigade_Db_Table_GroupMembershipRequest();
        return $Requests->getMembershipRequests($groupId, 0, true);
    }

    /**
     * Count the number of pending requests membership.
     *
     * @param String $groupId
     *
     * @return Arrray of Users.
     */
    static public function getMembersPendingRequests($groupId) {
        $Requests = new Brigade_Db_Table_GroupMembershipRequest();
        $req      = $Requests->getMembershipRequests($groupId, 0, false);
        $users    = array();
        foreach ($req as $user) {
            $users[] = User::get($user['UserId']);
        }

        return $users;
    }

    /**
     * Accept membership for a group page.
     *
     * @param String $userId
     * @param String $groupId
     */
    static public function acceptMembership($groupId, $userId) {
        //update request row
        $Requests = new Brigade_Db_Table_GroupMembershipRequest();
        $Requests->acceptMembershipUserGroup($userId, $groupId);

        $user   = User::get($userId);
        $group  = self::get($groupId);
        $member = $group->addMember($user, true);

        if ($group->activityRequiresMembership) {
            Volunteer::activateByGroupAndUser($group, $user);
        }

        if (!$member && $group->isMember($user)) {
            $member = $group->getMember($user);
        }

        return $member;
    }

    /**
     * Deny membership for a group page.
     *
     * @param String $userId
     * @param String $groupId
     */
    static public function denyMembership($groupId, $userId) {
        //update request row
        $Requests = new Brigade_Db_Table_GroupMembershipRequest();
        $Requests->denyMembershipUserGroup($userId, $groupId);
    }

    /**
     * Check if the user has a pending request to become a member
     *
     * @param User $user
     *
     * return bool
     */
    public function hasMembershipPendingReq($user) {
        $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
        return (is_array($GroupMembershipRequest->hasMembershipRequest($this->id, $user->id)));
    }

    /**
     * Return the list of members admins of the chapter
     * @TODO: update to use only roles
     *
     * @param String $searchString to search
     *
     * @return Array Members
     */
    public function getAdmins($search = null) {
        return Member::getListAdminsByGroup($this, $search);
    }

    /**
     * Get admins with roles credentials
     *
     * @param String $searchString to search
     *
     * @return Array Users
     */
    public function getAdminsRoles($search = null) {
        $roles = Role::getBySite($this->id, $search);
        $users = false;
        foreach ($roles as $role) {
            if ($role->user) {
                $users[] = $role->user;
            }
        }
        return $users;
    }

    /**
     * Post wall comment into the group wall.
     *
     * @param String $message Message to post.
     *
     * @return void
     */
    public function postWall($message, $user) {
        $activity              = new Activity();
        $activity->type        = 'Wall Post';
        $activity->createdById = $user->id;
        $activity->date        = date('Y-m-d H:i:s');
        $activity->details     = $message;
        $activity->siteId      = $this->id;
        $activity->save();
    }

    /**
     * @TODO: remove this chapu. is for the tabs
     */
    public function getPastEvents() {
        return Event::getListByGroup($this, 'past');
    }

    /**
     * @TODO: remove this chapu. is for the tabs
     */
    public function getUpcomingEvents() {
        return Event::getListByGroup($this, 'upcoming');
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Group.
     */
    protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj                             = new self;
            $obj->id                         = $data['GroupId'];
            $obj->name                       = $data['GroupName'];
            $obj->urlName                    = $data['URLName'];
            $obj->description                = $data['Description'];
            $obj->currency                   = $data['Currency'];
            $obj->googleId                   = $data['GoogleCheckoutAccountId'];
            $obj->paypalId                   = $data['PaypalAccountId'];
            $obj->bluePayId                  = $data['BluePayAccountId'];
            $obj->programId                  = $data['ProgramId'];
            $obj->organizationId             = $data['NetworkId'];
            $obj->logoMediaId                = $data['LogoMediaId'];
            $obj->bannerMediaId              = $data['BannerMediaId'];
            $obj->isOpen                     = (bool)$data['isOpen'];
            $obj->isNonProfit                = $data['isNonProfit'];
            $obj->isActive                   = $data['isActive'];
            $obj->createdBy                  = $data['CreatedBy'];
            $obj->createdOn                  = $data['CreatedOn'];
            $obj->modifiedOn                 = $data['ModifiedOn'];
            $obj->modifiedBy                 = $data['ModifiedBy'];
            $obj->hasUploadedMembers         = $data['hasUploadedMembers'];
            $obj->hasMembershipFee           = (bool)$data['hasMembershipFee'];
            $obj->fundraiseMembershipFee     = $data['fundraiseMembershipFee'];
            $obj->activityRequiresMembership = $data['activityRequiresMembership'];
            $obj->hasSharedSocialNetworks    = $data['hasSharedSocialNetworks'];
            $obj->hasAssignedAdmins          = $data['hasAssignedAdmins'];
            $obj->percentageFee              = $data['PercentageFee'];
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
            'ProgramId'                  => $this->programId,
            'GroupName'                  => $this->name,
            'URLName'                    => $this->urlName,
            'NetworkId'                  => $this->organizationId,
            'ModifiedOn'                 => date('Y-m-d H:i:s'),
            'ModifiedBy'                 => $this->modifiedBy,
            'CreatedBy'                  => $this->createdBy,
            'Description'                => $this->description,
            'isOpen'                     => $this->isOpen,
            'isNonProfit'                => $this->isNonProfit,
            'isActive'                   => $this->isActive,
            'hasUploadedMembers'         => $this->hasUploadedMembers,
            'hasSharedSocialNetworks'    => $this->hasSharedSocialNetworks,
            'hasAssignedAdmins'          => $this->hasAssignedAdmins,
            'Currency'                   => $this->currency,
            'PercentageFee'              => $this->percentageFee,
            'allowPercentageFee'         => $this->allowPercentageFee,
            'PaypalAccountId'            => $this->paypalId,
            'GoogleCheckoutAccountId'    => $this->googleId,
            'BluePayAccountId'           => $this->bluePayId,
            'LogoMediaId'                => $this->logoMediaId,
            'hasMembershipFee'           => $this->hasMembershipFee,
            'fundraiseMembershipFee'     => $this->fundraiseMembershipFee,
            'activityRequiresMembership' => $this->activityRequiresMembership,
        );

        $Groups = new Brigade_Db_Table_Groups();
        if (!empty($this->id)) {
            $Groups->editGroup($this->id, $data);
        } else {
            $this->id = $Groups->addGroup($data);
        }
        return $this->id;
    }

    /**
     * Delete chapter from db.
     *
     * @return void
     */
    public function delete() {
        if (count($this->initiatives) > 0) {
            foreach($this->initiatives as $initiative) {
                $initiative->delete();
            }
        }

        if (count($this->events) > 0) {
            foreach($this->events as $event) {
                $event->delete();
            }
        }

        $Groups = new Brigade_Db_Table_Groups();
        $Groups->deleteGroup($this->id);

        User::deleteRolesBySite($this->id);

        // delete records from lookup_table
        Lookup::delete($this->id);

        if ($this->contact) {
            $this->contact->delete();
        }
    }


    /**
     * Set all projects/initatives of the group
     *
     * @return void
     */
    protected function _getInitiatives($status = null) {
        $this->_initiatives = Project::getListByGroup($this, $status, null);
    }

    /**
     * Set all projects/initatives of the group
     *
     * @return void
     */
    protected function _getUpcomingInitiatives() {
        $this->_upcomingInitiatives = Project::getListByGroup($this, 'upcoming', null);
    }

    /**
     * Set all activities of the group
     *
     * @return void
     */
    protected function _getActivities($status = 'upcoming') {
        $this->_activities = Project::getListByGroup($this, $status, 0);
    }

    /**
     * Set all campaigns of the group
     *
     * @return void
     */
    protected function _getCampaigns($status = 'upcoming') {
        $this->_campaigns = Project::getListByGroup($this, $status, 1);
    }

    /**
     * Set all events of the group
     *
     * @return void
     */
    protected function _getEvents($status = 'upcoming') {
        $this->_events = Event::getListByGroup($this, $status);
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
     * Set program lazy attr
     *
     * @return void.
     */
    protected function _getProgram() {
        $this->_program = Program::get($this->programId);
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
     * Set contact lazy attr
     *
     * @return void.
     */
    protected function _getLogo() {
        $this->_logo = Media::get($this->logoMediaId);
    }


    /**
     * Get total project raised
     *
     * @return void
     */
    protected function _getRaised() {
        $PD  = new Brigade_Db_Table_ProjectDonations();
        $res = $PD->getDonationsByGroup($this->id);
        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }

        $config       = Zend_Registry::get('configuration');
        $configMember = $config->chapter->membership;
        if(!empty($this->organizationId) &&
           in_array($this->organizationId, $configMember->active->toArray())
        ) {
            //Membership raised percentage (50% to group)
            $raised  = MembershipFund::getRaisedByGroup($this);
            $percent = ($raised * 50) / 100;

            $this->_raised += $percent;
        }
    }

    /**
     * Get all members, active and inactive
     *
     * @return Array of database structure
     */
    protected function _getMembers() {
        $this->_members = Member::getListByGroup($this, array(1), null);
    }

    /**
     * Get all members of the group with active email status
     *
     * @return Array of Members Objects
     */
    public function getActiveEmailMembers() {
        $this->_members = Member::getListByGroup($this);

        return $this->_members;
    }

    /**
     * Return number of members active.
     *
     * @return Integer
     */
    public function countMembers() {
        return Member::countByGroup($this, array(1), null);
    }

    /**
     * Gets wall of the group.
     *
     * @return void
     */
    protected function _getActivityWall($limit = false) {
        $this->_activityWall = Activity::getByGroup($this, $limit);
    }

    /**
     * Return list of donations
     *
     * @return Array Donation
     */
    protected function _getDonations() {
        $this->_donations = Donation::getListByGroup($this);
    }

    /**
     * Return list of membership payments
     *
     * @return Array Payment
     */
    protected function _getPayments() {
        $this->_payments = Payment::getListByGroup($this);
    }

    /**
     * Set bluepay lazy attr
     *
     * @return void.
     */
    protected function _getBluePay() {
        $this->_bluePay = BluePay::get($this->bluePayId);
    }

    /**
     * Get all amounts for different ways of freq payments.
     *
     * @return void
     */
    protected function _getMembershipDonationAmounts() {
        $this->_membershipDonationAmounts = MembershipFrequency::getList($this);
    }

    /**
     * Return the frequency donation amount for a specific frequncy.
     * @TODO: change to getter of MembershipFrequency Model
     *
     * @param Int Frequency id
     *
     * @return Float Amount
     */
    public function getMembershipFrequency($frequencyId) {
        foreach($this->membershipDonationAmounts as $amnt) {
            if ($amnt->id == $frequencyId) {
                return $amnt;
            }
        }
    }


    /**
     * Determines if user object belongs to this group as a member.
     * It checks for payments on membership fees if needed.
     *
     * @param $usr User object.
     *
     * @return boolean True if the user provided is a member of this group.
     */
    public function isMember($user) {
        $member = Member::getByGroupUser($this, $user);

        if (!empty($member) && $member->isDeleted) {
            return false;
        }

        if(!empty($member) && $member->activateEmail) {
            $config       = Zend_Registry::get('configuration');
            $configMember = $config->chapter->membership;
            if($this->hasMembershipFee && !empty($this->organizationId) &&
               in_array($this->organizationId, $configMember->active->toArray())
            ) {
                if($member->paid) {
                    if ($member->paidUntil != '0000-00-00') {
                        if(strtotime($member->paidUntil) > time()) {
                            return true;
                        }
                    } else {
                        return true;
                    }
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Add member to group, if the group is closed, it will create a membership
     * request that need to be approved.
     *
     * @return void
     */
    public function addMember($user, $reqApproved = false) {
        if ($this->isMember($user)) {
            return false;
        }

        //if is a membership approval with fee
        $member = $this->getMember($user);
        if (!empty($member)) {
            if ($this->hasMembershipFee) {
                $member->activateEmail = true;
            }
            $member->isDeleted = false;
            $member->save();

            return $member;
        }

        $data = array(
           'UserId'  => $user->id,
           'GroupId' => $this->id
        );
        if (!empty($this->organizationId)) {
           $data['NetworkId'] = $this->organizationId;
        }
        $isAdmin = false;
        if (Role::isAdmin($user->id, $this->id)) {
            $isAdmin = true;
        }
        if (($this->isOpen || $this->hasMembershipPendingReq($user) || $reqApproved)
            && empty($member)
        ) {
            if ($isAdmin) {
                $data['isAdmin'] = true;
            }
            $GroupMembers = new Brigade_Db_Table_GroupMembers();
            $GroupMembers->AddGroupMember($data);
        } else {
            $GroupMembershipRequest = new Brigade_Db_Table_GroupMembershipRequest();
            $GroupMembershipRequest->AddMembershipRequest($data);
            // for members with admin approval and membership fee
            if ($this->hasMembershipFee) {
                $data['ActivateEmail'] = 0;
                if ($isAdmin) {
                    $data['isAdmin'] = true;
                }
                $GroupMembers = new Brigade_Db_Table_GroupMembers();
                $GroupMembers->AddGroupMember($data);
            }
        }

        return $this->getMember($user);
    }

    /**
     * Return member of the group.
     *
     * @param User $user Member
     *
     * @return Member
     */
    public function getMember($user) {
        $member = Member::getByGroupUser($this, $user);
        if (!empty($member)) {
            return $member;
        }
        return null;
    }


    /**
     * Create url of the group. Used to create new instance.
     *
     * @return String new url
     */
    public function makeUrl() {
        $URLName = str_replace(
            array(
                " ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":",
                "$", "`", "#", "^", "<", ">", "[", "]", "{", "}"
            ),
            array(
                "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-",
                "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"
            ),
            trim($this->name)
        );

        // replace other special chars with accents
        $otherSpecialChars = array(
            'À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û',
            'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô',
            'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ'
        );
        $charReplacement = array(
            'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U',
            'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u',
            'u', 'u', 'y', 'n'
        );
        $URLName = str_replace($otherSpecialChars, $charReplacement, $URLName);
        $Taken   = Lookup::nameExists($URLName);
        $counter = 1;
        while($Taken) {
            $NewURLName = "$URLName-$counter";
            $counter++;
            $Taken = Lookup::nameExists($NewURLName);
        }
        if($counter > 1) {
            $URLName = $NewURLName;
        }

        $this->urlName = $URLName;

        return $this->urlName;
    }
}
