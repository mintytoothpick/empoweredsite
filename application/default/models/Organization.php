<?php
require_once 'Brigade/Db/Table/Organizations.php';
require_once 'Brigade/Db/Table/GroupMembers.php';
require_once 'Group.php';
require_once 'Google.php';
require_once 'Member.php';
require_once 'Lookup.php';
require_once 'MemberTitle.php';

/**
 * Class Model Organization.
 *
 * @author Matias Gonzalez
 */
class Organization extends Base {

    public $id;
    public $name;
    public $urlName;
    public $type;
    public $description;
    public $createdById;
    public $createdOn;
    public $modifiedById;
    public $modifiedOn;
    public $logoMediaId;
    public $bannerMediaId;
    public $googleId  = 0;
    public $paypalId  = 0;
    public $bluePayId = 0;
    public $hasPrograms;
    public $hasGroups;
    public $isOpen;
    public $currency                = "$";
    public $percentageFee           = 0;
    public $allowPercentageFee      = false;
    public $nonProfitId;
    public $hasUploadedMembers      = false;
    public $hasSharedSocialNetworks = false;
    public $hasAssignedAdmins       = false;
    public $hasDownloadedReports    = false;
    public $hasSentEmails           = false;
    public $cssStyles;

    // to Enable/Disable
    public $hasActivities;
    public $hasCampaigns;
    public $hasEvents;
    public $hasMembership;

    // Custom labels
    public $groupNamingPlural     = "Chapters";
    public $groupNamingSingular   = "Chapter";
    public $programNamingPlural   = "Programs";
    public $programNamingSingular = "Program";

    // Lazy
    protected $_programs            = null;
    protected $_groups              = null;
    protected $_contact             = null;
    protected $_logo                = null;
    protected $_activities          = null;
    protected $_campaigns           = null;
    protected $_events              = null;
    protected $_upcomingInitiatives = null;
    protected $_raised              = null;
    protected $_members             = null;
    protected $_createdBy           = null;
    protected $_countActivities     = null;
    protected $_countCampaigns      = null;
    protected $_countEvents         = null;
    protected $_countMembers        = null;
    protected $_donations           = null;
    protected $_google              = null;
    protected $_bluePay             = null; //new payment gateway
    protected $_payments            = null; //for membership fee
    protected $_memberTitles        = null;

    public static $withSurvey = array(
        "DAF7E701-4143-4636-B3A9-CB9469D44178", // Usa
        "547086E0-5456-4631-AB2A-BA781E7DB9A7", // UK
        "DB04F20F-59FE-468F-8E55-AD75F60FB0CB", // Canada
        "7D428431-A7C7-4DF6-A667-F9207E14674E", // Ireland
        "69F11A6C-E582-11E1-A671-003048C5176A", // Exit West
        "47866989-6380-445C-95C0-827E55ACA9CB", // GB Germany
        "E81D930C-6034-11E2-A227-0025904EACF0"  // GB-Institutes
        //"AE6476D8-6FE0-11E2-805A-0025904EACF0", // Deloitte 4G
    );

    public static $withFlyForGood = array(
        "DAF7E701-4143-4636-B3A9-CB9469D44178", // Usa
        "E81D930C-6034-11E2-A227-0025904EACF0", // GB-Institutes (Global Brigades Association)
        "F16313CA-B638-11E1-A671-003048C5176A"  // FFG test in dev
    );


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
        if (property_exists('Organization', $attr)) {
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
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $Org  = new Brigade_Db_Table_Organizations();
        $data = $Org->load($id);

        return self::_populateObject($data);
    }

    /**
     * Get the org by object group.
     *
     * @param Object $Group  Group object to filter events
     *
     * @return Organization object.
     */
    static public function getByGroup(Group $Group) {
        $Orgs = new Brigade_Db_Table_Organizations();
        $Org  = $Orgs->getByGroup($Group->id);

        return self::_populateObject($Org);
    }

    /**
     * Get organizations that a user is a member of
     *
     * @param String $userId  user id to filter organizations
     *
     * @return List of organization objects.
     */
    static public function getUserAffiliations($userId) {
        $GroupMembers      = new Brigade_Db_Table_GroupMembers();
        $organization_list = $GroupMembers->getUserOrganizationAffiliations($userId);
        $list              = array();
        foreach($organization_list as $organization) {
            // create objects project
            $list[] = self::_populateObject($organization);
        }
        return $list;
    }

    /**
     * List All Organizations
     *
     * @return List of organization objects.
     */
    static public function listAll() {
        $Organizations     = new Brigade_Db_Table_Organizations();
        $organization_list = $Organizations->listAll();
        $list              = array();
        foreach($organization_list as $organization) {
            // create objects project
            $list[] = self::_populateObject($organization);
        }
        return $list;
    }

    /**
     * Get list of the organizations where user raised money.
     */
    static public function getWithUserRaised($userId) {
        $Organizations     = new Brigade_Db_Table_Organizations();
        $organization_list = $Organizations->getWithUserRaised($userId);
        $list              = array();
        foreach($organization_list as $organization) {
            // create objects project
            $list[] = self::_populateObject($organization);
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
        $obj = null;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['NetworkId'];

            $obj->name               = $data['NetworkName'];
            $obj->urlName            = $data['URLName'];
            $obj->type               = $data['Tagline'];
            $obj->description        = $data['AboutUs'];
            $obj->logoMediaId        = $data['LogoMediaId'];
            $obj->bannerMediaId      = $data['BannerMediaId'];
            $obj->hasPrograms        = $data['hasPrograms'];
            $obj->hasGroups          = $data['hasGroups'];
            $obj->isOpen             = $data['isOpen'];
            $obj->percentageFee      = $data['PercentageFee'];
            $obj->allowPercentageFee = $data['allowPercentageFee'];
            $obj->nonProfitId        = $data['NonProfitId'];
            $obj->cssStyles          = $data['cssStyles'];

            $obj->googleId                = $data['GoogleCheckoutAccountId'];
            $obj->paypalId                = $data['PaypalAccountId'];
            $obj->bluePayId               = $data['BluePayAccountId'];
            $obj->currency                = $data['Currency'];
            $obj->hasUploadedMembers      = $data['hasUploadedMembers'];
            $obj->hasSharedSocialNetworks = $data['hasSharedSocialNetworks'];
            $obj->hasAssignedAdmins       = $data['hasAssignedAdmins'];
            $obj->hasDownloadedReports    = $data['hasDownloadedReports'];
            $obj->hasSentEmails           = $data['hasSentEmails'];

            $obj->createdById = $data['CreatedBy'];
            $obj->createdOn   = $data['CreatedOn'];
            $obj->modifiedBy  = $data['ModifiedBy'];
            $obj->modifiedOn  = $data['ModifiedOn'];

            $obj->hasEvents     = $data['hasEvents'];
            $obj->hasActivities = $data['hasActivities'];
            $obj->hasCampaigns  = $data['hasCampaigns'];
            $obj->hasMembership = $data['hasMembership'];

            //custom labels
            $obj->groupNamingPlural     = $data['groupNamingPlural'];
            $obj->groupNamingSingular   = $data['groupNamingSingular'];
            $obj->programNamingPlural   = $data['programNamingPlural'];
            $obj->programNamingSingular = $data['programNamingSingular'];
        }
        return $obj;
    }

    /**
     * Update data into database
     *
     * @return void.
     */
    public function save() {
        $data = array(
            'NetworkName'             => $this->name,
            'URLName'                 => $this->urlName,
            'ModifiedOn'              => date('Y-m-d H:i:s'),
            'Tagline'                 => $this->type,
            'AboutUs'                 => $this->description,
            'LogoMediaId'             => $this->logoMediaId,
            'BannerMediaId'           => $this->bannerMediaId,
            'hasPrograms'             => $this->hasPrograms,
            'hasGroups'               => $this->hasGroups,
            'isOpen'                  => $this->isOpen,
            'Currency'                => $this->currency,
            'NonProfitId'             => $this->nonProfitId,
            'hasUploadedMembers'      => $this->hasUploadedMembers,
            'hasSharedSocialNetworks' => $this->hasSharedSocialNetworks,
            'hasAssignedAdmins'       => $this->hasAssignedAdmins,
            'hasDownloadedReports'    => $this->hasDownloadedReports,
            'hasSentEmails'           => $this->hasSentEmails,
            'PercentageFee'           => $this->percentageFee,
            'allowPercentageFee'      => $this->allowPercentageFee,
            'PaypalAccountId'         => $this->paypalId,
            'GoogleCheckoutAccountId' => $this->googleCheckoutAccountId,
            'BluePayAccountId'        => $this->bluePayId,
            'cssStyles'               => $this->cssStyles,
            'hasEvents'               => $this->hasEvents,
            'hasActivities'           => $this->hasActivities,
            'hasCampaigns'            => $this->hasCampaigns,
            'hasMembership'           => $this->hasMembership,
            'groupNamingPlural'       => $this->groupNamingPlural,
            'groupNamingSingular'     => $this->groupNamingSingular,
            'programNamingPlural'     => $this->programNamingPlural,
            'programNamingSingular'   => $this->programNamingSingular,
        );

        $org = new Brigade_Db_Table_Organizations();
        if (!empty($this->id)) {
            return $org->updateInfo($this->id, $data);
        } else {
            $this->id = $org->addNetwork($data);
            return $this->id;
        }

    }

    /**
     * Delete organization.
     *
     */
    public function delete() {
        if ($this->hasPrograms && count($this->programs) > 0) {
            foreach($this->programs as $program) {
                $program->delete();
            }
        }
        if (!$this->hasPrograms && $this->hasGroups && count($this->groups) > 0) {
            foreach($this->groups as $group) {
                $group->delete();
            }
        }
        if (!$this->hasPrograms && !$this->hasGroups) {
            if (count($this->campaigns) > 0) {
                foreach($this->campaigns as $campaign) {
                    $campaign->delete();
                }
            }
            if (count($this->activities) > 0) {
                foreach($this->activities as $activity) {
                    $activity->delete();
                }
            }
        }

        if (count($this->events) > 0) {
            foreach($this->events as $event) {
                $event->delete();
            }
        }
        $org = new Brigade_Db_Table_Organizations();
        $org->deleteNetwork($this->id);

        User::deleteRolesBySite($this->id);

        // delete records from lookup_table
        Lookup::delete($this->id);

        if ($this->contact) {
            $this->contact->delete();
        }
    }

    /**
     * Create url of the organization. Used to create new instance.
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


    /**
     * Get boolean status if is administrator of site id object.
     *
     * @param User $user User
     *
     * @return bool.
     */
    public function isAdmin($user) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $role      = $UserRoles->getRoleByUserAndSite($user->id, $this->id);

        return ($role == 'ADMIN' || $role == 'GLOB-ADMIN');
    }

    /**
     * Set admin access for a user
     *
     * @param User $user User
     *
     * @return bool.
     */
    public function setAdmin($user) {
        if (!$this->isAdmin($user)) {
            $UserRoles  = new Brigade_Db_Table_UserRoles();
            $UserRoleId = $UserRoles->addUserRole(array(
                'UserId' => $user->id,
                'RoleId' => 'ADMIN',
                'Level'  => 'Organization',
                'SiteId' => $this->id
            ));
        }
    }

    /**
     * Remove admin access
     *
     * @param User $user User.
     *
     * @return bool.
     */
    public function removeAdmin($user) {
        if ($this->isAdmin($user)) {
            $UserRoles = new Brigade_Db_Table_UserRoles();
            $UserRoles->deleteUserRole($user->id, $this->id);
        }
    }

    /**
     * Remove admin access
     *
     * @param User $user User.
     *
     * @return bool.
     */
    public function removeMember($user) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRole($user->id, $this->id);

        $GroupMember = new Brigade_Db_Table_GroupMembers();
        $GroupMember->deleteOrganizationMember($user->id, $this->id);
    }

    /**
     * Return bool condition if the organization has survey to fill.
     *
     * @return bool.
     */
    public function hasSurvey() {
        return (in_array($this->id, self::$withSurvey));
    }

    /**
     * Return bool condition if the organization has gift aid to fill.
     *
     * @return bool.
     */
    public function hasGiftAid() {
        return ($this->id == "547086E0-5456-4631-AB2A-BA781E7DB9A7");
    }

    /**
     * Set all programs of the organization
     *
     * @return void
     */
    protected function _getPrograms() {
        if($this->hasPrograms) {
            $this->_programs = Program::getListByOrganization($this->id);
        } else {
            $this->_programs = null;
        }
    }

    /**
     * Set all groups of the organization
     *
     * @return void
     */
    protected function _getGroups() {
        if($this->hasGroups) {
            $this->_groups = Group::getListByOrganization($this->id);
        } else {
            $this->_groups = null;
        }
    }

    /**
     * Return all titles for members in the chapters
     *
     * @return array OrganizationMemberTitle
     */
    protected function _getMemberTitles() {
        $this->_memberTitles = MemberTitle::getListByOrganization($this);
    }

    /**
     * Set all events of the organization
     *
     * @return void
     */
    protected function _getEvents($status = 'All') {
        $this->_events = Event::getListByOrganization($this->id, $status);
    }

    /**
     * Get count of related events
     *
     * @return void
     */
    protected function _getCountEvents($status = 'all') {
        $this->_countEvents = Event::countByOrganization($this, $status);
    }

    /**
     * Set created by user
     *
     * @return void
     */
    protected function _getCreatedBy() {
        $this->_createdBy = User::get($this->createdById);
    }

    /**
     * Set all activities of the organization
     *
     * @return void
     */
    protected function _getActivities($status = 'all') {
        $this->_activities = Project::getListByOrganization($this, $status, 0);
    }

    /**
     * Get count of related activities
     *
     * @return void
     */
    protected function _getCountActivities($status = 'all') {
        $this->_countActivities = Project::countByOrganization($this, $status, 0);
    }

    /**
     * Set all campaigns of the organization
     *
     * @return void
     */
    protected function _getCampaigns($status = 'all') {
        $this->_campaigns = Project::getListByOrganization($this, $status, 1);
    }

    /**
     * Get count of related campaigns
     *
     * @return void
     */
    protected function _getCountCampaigns($status = 'all') {
        $this->_countCampaigns = Project::countByOrganization($this, $status, 1);
    }

    /**
     * Get count of active members
     *
     * @return void
     */
    protected function _getCountMembers() {
        $this->_countMembers = Member::countByOrganization($this);
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
        $this->_logo = Media::get($this->logoMediaId);
    }

    /**
     * Get total raised for organization
     *
     * @return void
     */
    protected function _getRaised() {
        $PD  = new Brigade_Db_Table_ProjectDonations();
        $res = $PD->getDonationsByOrganization($this->id);
        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }

        //Membership raised percentage (50% to org)
        $raised  = Payment::getRaisedByOrganization($this);
        $percent = ($raised * 50) / 100;

        $this->_raised += $percent;
    }

    /**
     * Set members lazy attr
     *
     * @return void.
     */
    protected function _getMembers() {
        $GM  =  new Brigade_Db_Table_GroupMembers();
        $this->_members = $GM->getOrganizationMembers($this->id);
    }

    /**
     * Set all initiatives that are upcoming of the organization
     *
     * @return void
     */
    protected function _getUpcomingInitiatives() {
        $this->_upcomingInitiatives = Project::getListByOrganization($this, 'upcoming');
    }

    /**
     * Return list of donations
     *
     * @return Array Donation
     */
    protected function _getDonations($month = false) {
        if ($month) {
            $this->_donations = Donation::getListByOrganization(
                $this, false, date('Y-m-01 00:00:00'), date('Y-m-31 23:59:59')
            );
        } else {
            $this->_donations = Donation::getListByOrganization($this);
        }
    }

    /**
     * get google checkout account
     *
     * @return void
     */
    protected function _getGoogle() {
        $this->_google = Google::get($this->googleId);
    }

    /**
     * get bluepay lazy attr
     *
     * @return void.
     */
    protected function _getBluePay() {
        $this->_bluePay = BluePay::get($this->bluePayId);
    }

    /**
     * Return list of membership payments of the groups
     *
     * @return Array Payment
     */
    protected function _getPayments() {
        $this->_payments = Payment::getListByOrganization($this);
    }

    /**
     * Used for organizations without groups
     *
     * @return void
     */
    public function addMember($user) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupMembers->AddGroupMember(array(
           'UserId'    => $user->id,
           'NetworkId' => $this->id
        ));
    }
}
