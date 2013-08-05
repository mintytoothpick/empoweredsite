<?php
require_once 'Brigade/Db/Table/Users.php';
require_once 'Brigade/Db/Table/LookupTable.php';
require_once 'Contact.php';
require_once 'Lookup.php';
require_once 'Organization.php';
require_once 'Base.php';
require_once 'Role.php';

/**
 * Class Model User.
 *
 * @author Matias Gonzalez
 */
class User extends Base {

    public $id;
    public $firstName;
    public $lastName;
    public $fullName;
    public $urlName;
    public $password;
    public $createdOn;
    public $modifiedOn;
    public $isActive;
    public $location;
    public $email;
    public $phone;
    public $webAddress;
    public $gender;
    public $dateOfBirth;
    public $aboutMe;
    public $skills;
    public $languageId;
    public $volunteerInterestId;
    public $profileImage;
    public $faceBookId;
    public $activation_code;
    public $firstLogin;
    public $isDeleted;

    public $allowPercentageFee      = 'optional';
    public $googleCheckoutAccountId = 0;
    public $paypalAccountId         = 0;
    public $percentageFee           = 0;
    public $lastLogin               = '0000-00-00 00:00:00';
    public $currency                = '$';
    public $promptDetails           = 0;
    public $relData                 = null;

    // Lazy
    protected $_contact      = null;
    protected $_raised       = null;
    protected $_activityFeed = null;
    protected $_initiatives  = null;
    protected $_events       = null;
    protected $_volunteering = null;

    protected $_affiliationsGroup        = null;
    protected $_affiliationsOrganization = null;
    protected $_organizationsRaised      = null; //used in ffg

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
        if (property_exists('User', $attr)) {
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
     * Get a user by an email.
     *
     * @param String $email Email to find user
     *
     * @return User
     */
    static public function getByEmail($email) {
        $User = new Brigade_Db_Table_Users();
        $data = $User->getUserDataByEmail($email);

        return self::_populateObject($data);
    }

    /**
     * Get a user by url name.
     *
     * @param String $urlName Url to find user
     *
     * @return User
     */
    static public function getByUrl($urlName) {
        $User = new Brigade_Db_Table_Users();
        $data = $User->getUserDataByUrlName($urlName);

        return self::_populateObject($data);
    }

    /**
     * Get a user by facebook id.
     *
     * @param String $fbid FaceBookId to find user
     *
     * @return User
     */
    static public function getByFaceBookId($fbid) {
        $User = new Brigade_Db_Table_Users();
        $data = $User->getUserDataByFaceBookId($fbid);

        return self::_populateObject($data);
    }

    /**
     * @TODO : VER ESTO QUE NO SE QUE HACE ACA!!!
     */
    static public function getUsersVolunteersForProject($projectId) {
        $User = new Brigade_Db_Table_Users();
        $data = $User->getUsersVolunteersForProject($projectId);
        $list = array();
        foreach ($data as $value) {
            $list[] = self::_populateObject($value);
        }
        return $list;
    }


    /**
     * Get a list of members by group object
     *
     * @param Group  $group  Group instance to filter by
     * @param String $search Search text to filter.
     *
     * @return Array User
     */
    static public function getByGroup(Group $group, $search = null) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $usersList    = $GroupMembers->getGroupMembers(
                            $group->id,
                            array(0,1),
                            0,
                            false,
                            $search
        );
        $list         = array();
        foreach($usersList as $user) {
            // create objects project
            $list[] = self::_populateObject($user, array(
                'JoinedOn' => $user['JoinedOn']
            ));
        }
        return $list;
    }

    /**
     * Get user objects for all site admins
     *
     * @param Group  $group  Group instance to filter by
     * @param String $search Search text to filter.
     *
     * @return Array User
     */
    static public function getSiteAdmin($SiteId, $searchText = null) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $members   = $UserRoles->getSiteAdmin($SiteId, false, $searchText);
        $list         =  array();
        foreach($members as $user) {
            // create objects project
            $list[] = self::_populateObject($user);
        }
        return $list;
    }


    /**
     * Get a list of members by program object
     *
     * @param Program $program Program instance to filter by
     * @param String  $search  Search text to filter.
     *
     * @return Array User
     */
    static public function getByProgram(Program $program, $search = null) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $usersList    = $GroupMembers->getProgramMembers(
                            $program->id,
                            array(0,1),
                            0,
                            false,
                            $search
        );
        $list         =  array();
        foreach($usersList as $user) {
            // create objects project
            $list[] = self::_populateObject($user, array(
                'JoinedOn' => $user['JoinedOn']
            ));
        }
        return $list;
    }

    /**
     * Get a list of members by program object
     *
     * @param Organization $org    Org instance to filter by
     * @param String       $search Search text to filter.
     *
     * @return Array User
     */
    static public function getByOrganization(Organization $org, $search = null) {
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $usersList    = $GroupMembers->getOrganizationMembers(
                            $org->id,
                            array(0,1),
                            0,
                            false,
                            $search
        );
        $list         = array();
        foreach($usersList as $user) {
            // create objects project
            $list[] = self::_populateObject($user, array(
                'JoinedOn' => $user['JoinedOn']
            ));
        }
        return $list;
    }

    /**
     * Load information of the selected project.
     *
     * @param String $id Project Id.
     */
    public function load($id) {
        $User = new Brigade_Db_Table_Users();
        $data = $User->loadInfo($id);

        return self::_populateObject($data);
    }


    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Project.
     */
    static protected function _populateObject($data, $relData = false) {
        ini_set("memory_limit", "512M");
        $obj = null;
        if ($data) {
            $obj                          = new self;
            $obj->id                      = $data['UserId'];
            $obj->firstName               = $data['FirstName'];
            $obj->lastName                = $data['LastName'];
            $obj->fullName                = $data['FullName'];
            $obj->urlName                 = $data['URLName'];
            $obj->createdOn               = $data['CreatedOn'];
            $obj->modifiedOn              = $data['ModifiedOn'];
            $obj->isActive                = $data['Active'];
            $obj->location                = $data['Location'];
            $obj->email                   = $data['Email'];
            $obj->password                = $data['Password'];
            $obj->phone                   = $data['Phone'];
            $obj->webAddress              = $data['WebAddress'];
            $obj->gender                  = $data['Gender'];
            $obj->dateOfBirth             = $data['DateOfBirth'];
            $obj->aboutMe                 = $data['AboutMe'];
            $obj->skills                  = $data['Skills'];
            $obj->languageId              = $data['LanguageId'];
            $obj->profileImage            = $data['ProfileImage'];
            $obj->faceBookId              = $data['FaceBookId'];
            $obj->activation_code         = $data['activation_code'];
            $obj->promptDetails           = $data['PromptDetails'];
            $obj->currency                = $data['Currency'];
            $obj->lastLogin               = $data['LastLogin'];
            $obj->firstLogin              = $data['FirstLogin'];
            $obj->percentageFee           = $data['PercentageFee'];
            $obj->paypalAccountId         = $data['PaypalAccountId'];
            $obj->allowPercentageFee      = $data['allowPercentageFee'];
            $obj->volunteerInterestId     = $data['VolunteerInterestId'];
            $obj->googleCheckoutAccountId = $data['GoogleCheckoutAccountId'];
            $obj->isDeleted               = (bool)$data['isDeleted'];

            if ($relData) {
                $obj->relData = $relData;
            }
        }
        return $obj;
    }

    static public function deleteRolesBySite($siteId) {
        Role::deleteRolesBySite($siteId);
    }

    /**
     * Generate url name, using full name.
     *
     * @return String Url Name
     */
    protected function _generateUrlName() {
        $SiteName = $this->firstName."-".$this->lastName;
        $SiteName = str_replace(
            array(" ", "'", ";", "\"", "\\", "/", "%", "?", "&", "@", "=", ":", "$", ","),
            array("-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-"),
            $SiteName
        );
        // replace other special chars with accents
        $specialChars = array('À', 'Â', 'Ä', 'È', 'É', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Œ', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ñ', 'à', 'â', 'ä', 'è', 'é', 'ê', 'ë', 'î', 'ï', 'ô', 'œ', 'ù', 'û', 'ü', 'ÿ', 'ñ');
        $charRepl     = array('A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'Y', 'N', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'y', 'n');
        $SiteName     = str_replace($specialChars, $charRepl, $SiteName);

        $result = Lookup::nameExists($SiteName);
        while($result) {
            $SiteName = "$SiteName-$ctr";
            $result = Lookup::nameExists($SiteName);
        }

        return $SiteName;
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
     * Get total project raised
     *
     * @return void
     */
    protected function _getRaised($organizationId = false) {
        $PD  = new Brigade_Db_Table_ProjectDonations();
        $res = $PD->getDonationsByUser($this->id, $organizationId);
        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }
    }

    /**
     * Set all projects/initatives of the user
     *
     * @return void
     */
    protected function _getInitiatives($limit = false) {
        $this->_initiatives = Project::getListByUser($this, null, null, $limit);
    }

    /**
     * Gets activity feed of the user.
     *
     * @return void
     */
    protected function _getActivityFeed($limit = null) {
        $this->_activityFeed = Activity::getByUser($this, $limit);
    }

    /**
     * Gets affiliations group of the user.
     *
     * @return void
     */
    protected function _getAffiliationsGroup() {
        $this->_affiliationsGroup = Group::getUserAffiliations($this->id);
    }

    /**
     * Gets affiliations group of the user.
     *
     * @return void
     */
    protected function _getAffiliationsOrganization() {
        $this->_affiliationsOrganization = Organization::getUserAffiliations($this->id);
    }

    /**
     * Get list of organizations that the user have funds raised.
     *
     * @return void
     */
    protected function _getOrganizationsRaised() {
        $this->_organizationsRaised = Organization::getWithUserRaised($this->id);
    }

    /**
     * Set all events of the user
     *
     * @return void
     */
    protected function _getEvents($status = 'upcoming') {
        $this->_events = Event::getListByUser($this, $status);
    }

    /**
     * Get all volunteer objects where the user is volunteering
     *
     * @return void
     */
    protected function _getVolunteering() {
        $this->_volunteering = Volunteer::getByUser($this);
    }

    /**
     * Save object into database.
     *
     * @return void
     */
    public function save() {

        if (empty($this->urlName)) {
            $this->urlName = $this->_generateUrlName();
        }

        $data = array(
            'UserId'                  => $this->id,
            'FirstName'               => $this->firstName,
            'LastName'                => $this->lastName,
            'FullName'                => $this->fullName,
            'URLName'                 => $this->urlName,
            'CreatedOn'               => $this->createdOn,
            'ModifiedOn'              => $this->modifiedOn,
            'Active'                  => $this->isActive,
            'Location'                => $this->location,
            'Email'                   => $this->email,
            'Password'                => $this->password,
            'Phone'                   => $this->phone,
            'WebAddress'              => $this->webAddress,
            'Gender'                  => $this->gender,
            'DateOfBirth'             => $this->dateOfBirth,
            'AboutMe'                 => $this->aboutMe,
            'Skills'                  => $this->skills,
            'LanguageId'              => $this->languageId,
            'ProfileImage'            => $this->profileImage,
            'FaceBookId'              => $this->faceBookId,
            'activation_code'         => $this->activation_code,
            'PromptDetails'           => $this->promptDetails,
            'Currency'                => $this->currency,
            'ModifiedOn'              => date('Y-m-d H:i:s'),
            'LastLogin'               => $this->lastLogin,
            'FirstLogin'              => $this->firstLogin,
            'PercentageFee'           => $this->percentageFee,
            'PaypalAccountId'         => $this->paypalAccountId,
            'allowPercentageFee'      => $this->allowPercentageFee,
            'VolunteerInterestId'     => $this->volunteerInterestId,
            'GoogleCheckoutAccountId' => $this->googleCheckoutAccountId,
            'isDeleted'               => $this->isDeleted
        );

        $Users = new Brigade_Db_Table_Users();
        if(!empty($this->id)) {
            $Users->save($data);
        } else {
            $this->id = $Users->addUser($data, false);
        }
    }

    /**
     * Delete user and all relations
     *
     * @return void
     */
    public function delete() {

        //delete volunteering objects
        if ($this->volunteering) {
            foreach ($this->volunteering as $volunteer) {
                $volunteer->delete();
            }
        }

        //delete affilliations
        $GroupMembers = new Brigade_Db_Table_GroupMembers();
        $GroupMembers->deleteAffiliations($this->id);

        //delete user object
        $User = new Brigade_Db_Table_Users();
        $User->delete($this->id);
    }

    /**
     * To know if user is member of chapter with fee.
     *
     * @return Array Member
     */
    public function getMembership() {
        $list = Member::getListByUser($this, true);
        if (count($list) == 0) {
            return false;
        }
        return $list;
    }
}
