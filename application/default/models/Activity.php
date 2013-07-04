<?php
require_once 'Brigade/Db/Table/SiteActivities.php';
require_once 'User.php';
require_once 'Event.php';
require_once 'ActivityComment.php';

/**
 * Class Model Activity (Site Activity).
 *
 * @author Matias Gonzalez
 */
class Activity {

    public $id;
    public $siteId;
    public $siteType;
    public $type;
    public $date;
    public $createdById;
    public $link;
    public $details;
    public $recipientId;

    protected $_user      = null;
    protected $_recipient = null;
    protected $_total     = null;
    protected $_comments  = null;
    protected $_entity    = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        if ($name == 'user') {
            if (is_null($this->_user)) {
                $this->_getUser();
            }
            return $this->_user;
        } elseif ($name == 'comments') {
            if (is_null($this->_comments)) {
                $this->_getComments();
            }
            return $this->_comments;
        } elseif ($name == 'recipient') {
            if (is_null($this->_recipient)) {
                $this->_getRecipient();
            }
            return $this->_recipient;
        } elseif ($name == 'entity') {
            if (is_null($this->_entity)) {
                $this->_getEntity();
            }
            return $this->_entity;
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
        $SA   = new Brigade_Db_Table_SiteActivities();
        $data = $SA->loadInfo($id);

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
            $obj              = new self;
            $obj->id          = $data['SiteActivityId'];
            $obj->siteId      = $data['SiteId'];
            $obj->siteType    = $data['SiteType'];
            $obj->type        = $data['ActivityType'];
            $obj->date        = $data['ActivityDate'];
            $obj->createdById = $data['CreatedBy'];
            $obj->link        = $data['Link'];
            $obj->details     = $data['Details'];
            $obj->recipientId = $data['Recipient'];
        }
        return $obj;
    }

    /**
     * Return activities of an specific project.
     *
     */
    public static function getByProject(Project $Project, $limit = false) {
        $SiteActivi = new Brigade_Db_Table_SiteActivities();
        $Activities = $SiteActivi->getActivityFeedByProject($Project->id, $limit);
        $list       = array();
        foreach($Activities as $act) {
            // create objects
            $list[] = self::_populateObject($act);
        }
        return $list;
    }

    /**
     * Return activities of an specific user.
     *
     * @param User    $user  User object to get all activities.
     * @param Integer $limit Limit of objects.
     *
     * @return Array list of activities objects
     */
    public static function getByUser(User $user, $limit = null) {
        $SiteActivi = new Brigade_Db_Table_SiteActivities();
        $Activities = $SiteActivi->getActivityFeedByUser($user->id, $limit);
        $list       = array();
        foreach($Activities as $act) {
            // create objects
            $list[] = self::_populateObject($act);
        }
        return $list;
    }

    /**
     * Return activities of an specific group.
     *
     */
    public static function getByGroup(Group $Group, $limit = false) {
        $SiteActivi = new Brigade_Db_Table_SiteActivities();
        $Activities = $SiteActivi->getByGroup($Group->id, $limit);
        $list       = array();
        foreach($Activities as $act) {
            // create objects
            $list[] = self::_populateObject($act);
        }
        return $list;
    }

    /**
     * Post an user comment over an activity
     *
     */
    public function postComment($message, $user) {
        $actComment = new ActivityComment();

        $actComment->siteActivityId = $this->id;
        $actComment->comment        = $message;
        $actComment->commentedById  = $user->id;
        $actComment->date           = date('Y-m-d H:i:s');
        $actComment->save();
    }

    /**
     * Save object activity into database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'SiteId'       => $this->siteId,
            'SiteType'     => $this->siteType,
            'ActivityType' => $this->type,
            'ActivityDate' => $this->date,
            'CreatedBy'    => $this->createdById,
            'Link'         => $this->link,
            'Details'      => $this->details,
            'Recipient'    => $this->recipientId
        );

        $sa = new Brigade_Db_Table_SiteActivities();
        $sa->addSiteActivity($data);
    }

    /**
     * Delete activities and comments.
     *
     * @return void
     */
    public static function deleteBySite($siteId) {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $SiteActivities->DeleteSiteActivities($siteId);
    }

    /**
     * Get user object.
     *
     * @return void
     */
    protected function _getUser() {
        if ($this->createdById) {
            $this->_user = User::get($this->createdById);
        } else {
            $this->_user = false;
        }
    }

    /**
     * Get user recipient object.
     *
     * @return User $user Object
     */
    protected function _getRecipient() {
        if (!empty($this->recipientId)) {
            $this->_recipient = User::get($this->recipientId);
        }
    }

    /**
     * Get list of comments for this activity.
     *
     * @return void
     */
    protected function _getComments() {
        $this->_comments = ActivityComment::getByActivity($this);
    }

    /**
     * Get the final entity of the activity action, it could be a group,
     * a project, etc.
     *
     * @return void
     */
    protected function _getEntity() {
        if ($this->siteId) {
            if ($this->type == 'Events') {
                $this->_entity = Event::get($this->details);
            } else {
                $LookupTable = new Brigade_Db_Table_LookupTable();
                $result      = $LookupTable->getBySiteId($this->siteId);

                if ($result) {
                    switch ($result['FieldId']) {
                        case 'NetworkId':
                            $this->_entity = Organization::get($this->siteId);
                            break;
                        case 'ProgramId':
                            $this->_entity = Program::get($this->siteId);
                            break;
                        case 'GroupId':
                            $this->_entity = Group::get($this->siteId);
                            break;
                        case 'ProjectId':
                            $this->_entity = Project::get($this->siteId);
                            break;
                        case 'UserId':
                            $this->_entity = User::get($this->siteId);
                            break;
                        default:
                            $this->_entity = false;
                            break;
                    }
                }
            }
        } else {
            $this->_entity = false;
        }
    }
}
