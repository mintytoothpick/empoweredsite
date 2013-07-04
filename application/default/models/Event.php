<?php
require_once 'Brigade/Db/Table/Events.php';
require_once 'Brigade/Db/Table/EventTicketHolders.php';
require_once 'Group.php';
require_once 'TicketHolder.php';
require_once 'User.php';

/**
 * Class Model Event.
 *
 * @author Matias Gonzalez
 */
class Event {

    public $id;
    public $title;
    public $startDate;
    public $endDate;
    public $location;
    public $text;
    public $isActive;
    public $isDeleted;
    public $createdById;
    public $createdOn;
    public $modifiedById;
    public $modifiedOn;
    public $siteId;
    public $userId;
    public $isSellTickets;
    public $isRSVP;
    public $googleCheckoutAccountId;
    public $paypalAccountId;
    public $currency;
    public $groupId;

    // Lazy
    protected $_attendees = null;
    protected $_group     = null;
    protected $_entity    = null; //parent entity

    protected $_remainingTickets = null;
    protected $_tickets          = null;
    protected $_ticketsSold      = null;

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        if ($name == 'attendees') {
            if (is_null($this->_attendees)) {
                $this->_getAttendees();
            }
            return $this->_attendees;
        } elseif ($name == 'group') {
            if (is_null($this->_group)) {
                $this->_getGroup();
            }
            return $this->_group;
        } elseif ($name == 'remainingTickets') {
            if (is_null($this->_remainingTickets)) {
                $this->_getRemainingTickets();
            }
            return $this->_remainingTickets;
        } elseif ($name == 'ticketsSold') {
            if (is_null($this->_ticketsSold)) {
                $this->_getTicketsSold();
            }
            return $this->_ticketsSold;
        } elseif ($name == 'tickets') {
            if (is_null($this->_tickets) && ($this->isSellTickets)) {
                $this->_getTickets();
            }
            return $this->_ticketsSold;
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

    static public function getLastByGroup(Group $Group) {
        $Events = new Brigade_Db_Table_Events();
        $data   = $Events->getLastEventByGroupId($Group->id);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $Events = new Brigade_Db_Table_Events();
        $data   = $Events->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Get events by object group.
     *
     * @param Object $Group  Group object to filter events
     *
     * @return List of events objects.
     */
    static public function getListByGroup(Group $Group, $status = 'upcoming', $search = false) {
        $Event  = new Brigade_Db_Table_Events();
        $Events = $Event->getEventsByGroupId($Group->id, $status, $search);
        $list     = array();
        foreach($Events as $event) {
            // create objects project
            $list[] = self::_populateObject($event);
        }
        return $list;
    }

    /**
     * Get events by organizationId.
     * @TODO Change table format, split siteId into group and organization ids.
     *
     * @param String $organizationId  Id of organization to filter events
     *
     * @return List of events objects.
     */
    static public function getListByOrganization($organizationId, $status = 'All', $search = false) {
        $Event  = new Brigade_Db_Table_Events();
        $Events = $Event->getNetworkSiteEvents($organizationId, null, $status, $search);
        //$Events = $Event->getEventsByOrganizationId($organizationId, $status);

        $list     = array();
        foreach($Events as $event) {
            // create objects project
            $list[] = self::_populateObject($event);
        }
        return $list;
    }

    /**
     * Get events by user.
     * @TODO Change table format, split siteId into group and organization ids.
     *
     * @param String $organizationId  Id of organization to filter events
     *
     * @return List of events objects.
     */
    static public function getListByUser($user, $status = 'upcoming') {
        $Event  = new Brigade_Db_Table_Events();
        $Events = $Event->getEventsByUserId($user->id, $status);
        $list   = array();
        foreach($Events as $event) {
            $list[] = self::_populateObject($event);
        }
        return $list;
    }

    /**
     * Get events by organizationId.
     *
     * @param String $organizationId  Id of organization to filter events
     *
     * @return List of events objects.
     */
    static public function getListByProgram($programId, $status = 'upcoming', $search = false) {
        $Event  = new Brigade_Db_Table_Events();
        $Events = $Event->getEventsByProgramId($programId, $status, $search);
        $list   = array();
        foreach($Events as $event) {
            // create objects project
            $list[] = self::_populateObject($event);
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
            $obj->id = $data['EventId'];

            $obj->title       = $data['Title'];
            $obj->text        = $data['EventText'];
            $obj->createdById = $data['CreatedBy'];
            $obj->createdOn   = $data['CreatedOn'];
            $obj->modifiedBy  = $data['ModifiedBy'];
            $obj->modifiedOn  = $data['ModifiedOn'];
            $obj->siteId      = $data['SiteId'];
            $obj->startDate   = $data['StartDate'];
            $obj->endDate     = $data['EndDate'];
            $obj->userId      = $data['UserId'];
            $obj->location    = $data['Link'];
            $obj->isActive    = $data['Active'];
            $obj->isDeleted   = ($data['isDeleted'] == '1');
            if ($data['isSellTickets'] < 2) {
                $obj->isSellTickets = $data['isSellTickets'];
                $obj->isRSVP        = false;
            } else {
                $obj->isSellTickets = false;
                $obj->isRSVP        = true;
            }
            $obj->googleCheckoutAccountId = $data['GoogleCheckoutAccountId'];
            $obj->paypalAccountId         = $data['PaypalAccountId'];
            $obj->currency                = $data['Currency'];
            $obj->limitTickets            = $data['LimitTickets']; //for sell
            if (is_null($obj->limitTickets) && $obj->tickets) {
                foreach ($obj->tickets as $ticket) {
                    $obj->limitTickets += $ticket->quantity;
                }
            }

            // TODO: After refactor table, this will allways be setted.
            if (isset($data['GroupId'])) {
                $obj->groupId = $data['GroupId'];
            }
        }
        return $obj;
    }

    /**
     * Create
     */
    public function save() {
        $data = array(
            'Title'                   => $this->title,
            'EventText'               => $this->text,
            'CreatedBy'               => $this->createdById,
            'CreatedOn'               => $this->createdOn,
            'ModifiedBy'              => $this->modifiedBy,
            'ModifiedOn'              => $this->modifiedOn,
            'SiteId'                  => $this->siteId,
            'StartDate'               => $this->startDate,
            'EndDate'                 => $this->endDate,
            'UserId'                  => $this->userId,
            'Link'                    => $this->location,
            'Active'                  => $this->isActive,
            'isSellTickets'           => $this->isSellTickets,
            'GoogleCheckoutAccountId' => $this->googleCheckoutAccountId,
            'PaypalAccountId'         => $this->paypalAccountId,
            'Currency'                => $this->currency,
            'LimitTickets'            => $this->limitTickets
        );
        //new featrue
        if ($this->isRSVP) {
            $data['isSellTickets'] = 2;
        }

        $event = new Brigade_Db_Table_Events();
        if (!empty($this->id)) {
            $event->updateEvent($this->id, $data);
        } else {
            $this->id = $event->AddEvent($data);
        }
    }

    /**
     * Delete an event from records.
     *
     * @param String $id Event id
     */
    public function delete() {
        $Events = new Brigade_Db_Table_Events();
        $Events->deleteEvent($this->id);
    }

    /**
     * Set list of attendees.
     *
     * @return void
     */
    protected function _getAttendees() {
        $ETH   = new Brigade_Db_Table_EventTicketHolders();
        $users = $ETH->getListUserIdByEvent($this->id);
        if ($users) {
            foreach($users as $userData) {
                if ($userData['UserId']) {
                    $user = User::get($userData['UserId']);
                } else {
                    $user           = new User();
                    $user->fullName = $userData['FullName'];
                }
                $this->_attendees[] = $user;
            }
        }
    }

    /**
     * Gets the group of the event.
     *
     * @return void
     */
    protected function _getGroup() {
        $this->_group = Group::get($this->groupId);
    }

    /**
     * Gets the parent entity of the event.
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

    /**
     * Get types of tickets
     *
     * @return Array tickets types
     */
    protected function _getTickets() {
        if ($this->isSellTickets) {
            $this->_tickets = Ticket::getByEvent($this);
        }
    }

    /**
     * Get tickets sold
     *
     * @return Array tickets sold
     */
    protected function _getTicketsSold() {
        if ($this->isRSVP || $this->isSellTickets) {
            $this->_ticketsSold = TicketHolder::getListByEvent($this);
            return $this->_ticketsSold;
        }
    }

    /**
     * Get number of remaining tickets to sell
     *
     * @return Integer num of remaining tickets
     */
    protected function _getRemainingTickets() {
        $totalSold = count($this->ticketsSold);
        if(is_null($this->limitTickets)) {
            $this->_remainingTickets = 100;
        } else {
            $this->_remainingTickets = $this->limitTickets - $totalSold;
        }
    }

    public function getDaysToGo() {
        return ceil((strtotime($this->startDate)-time())/86400);
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

    /**
     * Get count of events by organization.
     *
     * @param string  $userId Id of user to filter projects
     * @param String  $status Status to filter projects by date
     *
     * @return Number of events.
     */
    public static function countByOrganization(Organization $Organization,
        $status = 'upcoming'
    ) {
        $Event = new Brigade_Db_Table_Events();
        $res   = $Event->countByOrganization($Organization->id, $status);
        if (is_null($res)) {
            return 0;
        } else {
            return $res['Total'];
        }
    }
}
