<?php
require_once 'Brigade/Db/Table/EventTicketHolders.php';
require_once 'Base.php';
require_once 'Event.php';
require_once 'Ticket.php';
require_once 'User.php';

/**
 * Class Model TicketHolder.
 *
 * @author Matias Gonzalez
 */
class TicketHolder extends Base {

    public $id;
    public $eventId;
    public $ticketId;
    public $userId;
    public $fullName;
    public $email;
    public $verificationCode = '';

    // Lazy
    protected $_event = null;
    protected $_user  = null;

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
        if (property_exists('TicketHolder', $attr)) {
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
     * Get instance object.
     * TODO: Implement cache layer.
     *
     * @param String $id TicketHolder Id.
     *
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Get list of tickets holders by event.
     *
     * @return Array
     */
    static public function getListByEvent(Event $event) {
        $eth     = new Brigade_Db_Table_EventTicketHolders();
        $ethObjs = $eth->getTicketHoldersByEventRSVP($event->id);
        $list    = array();
        foreach($ethObjs as $ticket) {
            // create objects project
            $list[] = self::_populateObject($ticket);
        }
        return $list;
    }

    /**
     * Load information of the selected project.
     *
     * @param String $id TicketHolder Id.
     */
    public function load($id) {
        $User = new Brigade_Db_Table_EventTicketHolders();
        $data = $User->loadInfo($id);

        return self::_populateObject($data);
    }


    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object TicketHolder.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj            = new self;
            $obj->id        = $data['TicketHolderId'];
            $obj->eventId   = $data['EventId'];
            $obj->ticketdId = $data['TicketId'];
            $obj->userId    = $data['UserId'];
            $obj->fullName  = $data['FullName'];
            $obj->email     = $data['Email'];

            $obj->verificationCode = $data['VerificationCode'];
        }
        return $obj;
    }

    /**
     * Set event of the ticket holder
     *
     * @return void
     */
    protected function _getEvent() {
        $this->_event = Event::get($this->eventId);
    }

    /**
     * Set user of the ticket holder
     *
     * @return void
     */
    protected function _getUser() {
        $this->_user = User::get($this->userId);
    }

    /**
     * Save object into database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'TicketHolderId'   => $this->id,
            'EventId'          => $this->eventId,
            'TicketId'         => $this->ticketdId,
            'UserId'           => $this->userId,
            'FullName'         => $this->fullName,
            'Email'            => $this->email,
            'VerificationCode' => $this->verificationCode
        );

        $sa = new Brigade_Db_Table_EventTicketHolders();
        $sa->AddTicketHolder($data);
    }
}
