<?php
require_once 'Brigade/Db/Table/CoalitionPrograms.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Organization.php';
require_once 'SupporterFrequency.php';
require_once 'Supporter.php';

/**
 * Class Model Program.
 *
 * @author Matias Gonzalez
 */
class Program extends Base {

    public $id;
    public $organizationId;
    public $name;
    public $urlName;
    public $description;
    public $isOpen;
    public $logoMediaId;
    public $bannerMediaId;
    public $createdById;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $hasSupporters  = false;
    public $supporterText  = '';
    public $showCoalitions = false;
    public $coalitionIds   = null; //lazy

    // Lazy
    protected $_organization    = null;
    protected $_groups          = null;
    protected $_countGroups     = null;
    protected $_activities      = null;
    protected $_countActivities = null;
    protected $_campaigns       = null;
    protected $_countCampaigns  = null;
    protected $_events          = null;
    protected $_contact         = null;
    protected $_logo            = null;
    protected $_raised          = null;
    protected $_coalitions      = null;

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
        if (property_exists('Program', $attr)) {
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
        $Programs = new Brigade_Db_Table_Programs();
        $data     = $Programs->load($id);

        return $this->_populateObject($data);
    }

    /**
     * Get programs by organization Id.
     *
     * @param String $organizationId  organization id to filter programs
     *
     * @return List of program objects.
     */
    static public function getListByOrganization($organizationId) {
        $Programs     = new Brigade_Db_Table_Programs();
        $program_list = $Programs->getPrograms($organizationId);
        $list         = array();
        foreach($program_list as $program) {
            $list[] = self::_populateObject($program);
        }
        return $list;
    }


    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Program.
     */
    protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj                 = new self;
            $obj->id             = $data['ProgramId'];
            $obj->organizationId = $data['NetworkId'];
            $obj->name           = $data['ProgramName'];
            $obj->urlName        = $data['URLName'];
            $obj->description    = $data['Description'];
            $obj->isOpen         = $data['isOpen'];
            $obj->logoMediaId    = $data['LogoMediaId'];
            $obj->bannerMediaId  = $data['BannerMediaId'];
            $obj->createdById    = $data['CreatedBy'];
            $obj->createdOn      = $data['CreatedOn'];
            $obj->modifiedBy     = $data['ModifiedBy'];
            $obj->modifiedOn     = $data['ModifiedOn'];
            $obj->hasSupporters  = (bool)$data['hasSupporters'];
            $obj->supporterText  = $data['supporterText'];
        }
        return $obj;
    }

    /**
     * Save info to database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'NetworkId'      => $this->organizationId,
            'Description'    => $this->description,
            'isOpen'         => $this->isOpen,
            'ProgramName'    => $this->name,
            'URLName'        => $this->urlName,
            'ModifiedBy'     => $this->modifiedBy,
            'CreatedBy'      => $this->createdById,
            'CreatedOn'      => $this->createdOn,
            'ModifiedOn'     => $this->modifiedOn,
            'supportersText' => $this->supportersText,
            'hasSupporters'  => $this->hasSupporters
        );

        $Programs = new Brigade_Db_Table_Programs();
        $Programs->editProgram($this->id, $data);
    }

    /**
     * Delete program.
     * Also delete all group childs.
     */
    public function delete() {
        if (count($this->groups) > 0) {
            foreach($this->groups as $group) {
                $group->delete();
            }
        }

        if (count($this->events) > 0) {
            foreach($this->events as $event) {
                $event->delete();
            }
        }

        if ($this->contact) {
            $this->contact->delete();
        }

        $Programs = new Brigade_Db_Table_Programs();
        $Programs->deleteProgram($this->id);

        User::deleteRolesBySite($this->id);

        // delete records from lookup_table
        Lookup::delete($this->id);
    }

    /**
     * Get list of programs filtering by a text. Used in search actions results.
     *
     * @param String       $searchText   Search user box
     * @param Organization $organization Specify organization id.
     *
     * @return Array List of porgrams.
     */
    public static function getSearchList($searchText,Organization $organization = null) {
        $organizationId = null;
        if ($organization) {
            $organizationId = $organization->id;
        }
        $Programs    = new Brigade_Db_Table_Programs();
        $programList = $Programs->searchProgramsInOrg($searchText, $organizationId);
        $list        =  array();
        foreach($programList as $program) {
            $list[] = self::_populateObject($program);
        }
        return $list;
    }

    /**
     * Get list of frequencies set up with amounts.
     *
     * @return array SupporterFrequency
     */
    public function getSupportersFrequencies() {
        return SupporterFrequency::getList($this);
    }

    /**
     * Add supporter to program.
     *
     * @param User $user
     *
     * @return Supporter
     */
    public function addSupporter($user = null) {
        $supporter = false;
        if ($user) {
            $supporter = Supporter::getByProgramUser($this, $user);
        }
        if (!$supporter) {
            $supporter                 = new Supporter();
            $supporter->programId      = $this->id;
            $supporter->organizationId = $this->organizationId;
            $supporter->userId         = ($user) ? $user->id : null;
            $supporter->joinedOn       = date('Y-m-d');
            $supporter->save();
        }

        return $supporter;
    }

    /**
     * Add supporter to program.
     *
     * @param User $user
     *
     * @return Supporter
     */
    public function canSupport($user = null) {
        $canSupport = false;
        if ($user) {
            $supporter  = Supporter::getByProgramUser($this, $user);
            $canSupport = ($this->hasSupporters && (!$supporter || !$supporter->paid));
        } else {
            $canSupport = $this->hasSupporters;
        }
        return $canSupport;
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
     * Set all groups of the organization
     *
     * @return void
     */
    protected function _getGroups($limit = false) {
        $this->_groups = Group::getListByProgram($this, false, $limit);
    }

    /**
     * Get count of related groups
     *
     * @return void
     */
    protected function _getCountGroups() {
        $this->_countGroups = Group::countByProgram($this);
    }

    /**
     * Set all events of the organization
     *
     * @return void
     */
    protected function _getEvents() {
        $this->_events = Event::getListByProgram($this->id);
    }

    /**
     * Set all activities of the organization
     *
     * @return void
     */
    protected function _getActivities($limit = false) {
        $this->_activities = Project::getListByProgram($this, 'all', 0, false, $limit);
    }

    /**
     * Get count of related activities
     *
     * @return void
     */
    protected function _getCountActivities() {
        $this->_countActivities = Project::countByProgram($this, 0);
    }

    /**
     * Get count of related projects
     *
     * @return void
     */
    protected function _getCountCampaigns() {
        $this->_countCampaigns = Project::countByProgram($this, 1);
    }

    /**
     * Set all campaigns of the organization
     *
     * @return void
     */
    protected function _getCampaigns($limit = false) {
        $this->_campaigns = Project::getListByProgram($this, 'all', 1, false, $limit);
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
        $res = $PD->getDonationsByProgram($this->id);
        if (is_null($res)) {
            $this->_raised = 0;
        } else {
            $this->_raised = $res['Amount'];
        }
    }

    /**
     * Get coalition programs
     *
     * @return Boolean Coalition exists
     */
    protected function _getCoalitions() {
        $this->_coalitions = false;
        if ($this->showCoalitions) {
            $Coalition = new Brigade_Db_Table_CoalitionPrograms();
            $res       = $Coalition->getCoalition($this->id);
            if (is_null($res)) {
                $this->_coalitions = false;
            } else {
                $this->_coalitions = true;
                foreach($res as $row) {
                    $this->coalitionIds[] = $row['ProgramId'];
                }
            }
        }
        return $this->_coalitions;
    }
}
