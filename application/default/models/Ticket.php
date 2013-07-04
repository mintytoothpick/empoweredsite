<?php
require_once 'Brigade/Db/Table/EventTickets.php';
require_once 'Base.php';
require_once 'Event.php';

/**
 * Class Model Ticket.
 *
 * @author Matias Gonzalez
 */
class Ticket extends Base {

    public $id;
    public $eventId;
    public $name;
    public $description;
    public $startDate = '';
    public $endDate   = '';

    //nullable
    public $price    = null;
    public $quantity = null;

    // Lazy
    protected $_event = null;

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
        if (property_exists('Ticket', $attr)) {
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
     * @param String $id Ticket Id.
     *
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Load information of the selected project.
     *
     * @param String $id Ticket Id.
     */
    public function load($id) {
        $Ticket = new Brigade_Db_Table_EventTickets();
        $data = $Ticket->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Return the list of tickets created for an specific event.
     *
     * @param Event $event Event obj to get all tickets
     *
     * @return Array List of tickets
     */
    public static function getByEvent(Event $event) {
        $Ticket  = new Brigade_Db_Table_EventTickets();
        $Tickets = $Ticket->getEventTickets($event->id);
        $list    = array();
        foreach($Tickets as $ticket) {
            // create objects project
            $list[] = self::_populateObject($ticket);
        }
        return $list;
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Ticket.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj              = new self;
            $obj->id          = $data['EventTicketId'];
            $obj->eventId     = $data['EventId'];
            $obj->name        = $data['Name'];
            $obj->description = $data['Description'];
            $obj->price       = $data['Price'];
            $obj->quantity    = $data['Quantity'];
            $obj->startDate   = $data['StartDate'];
            $obj->endDate     = $data['EndDate'];
        }
        return $obj;
    }

    /**
     * Set event of the ticket
     *
     * @return void
     */
    protected function _getEvent() {
        $this->_event = Event::get($this->eventId);
    }

    /**
     * Save object into database.
     *
     * @return void
     */
    public function save() {
        $data = array(
            'EventTicketId' => $this->id,
            'EventId'       => $this->firstName,
            'Name'          => $this->lastName,
            'Description'   => $this->fullName,
            'Price'         => $this->urlName,
            'Quantity'      => $this->createdOn,
            'StartDate'     => $this->modifiedOn,
            'EndDate'       => $this->isActive
        );

        $sa = new Brigade_Db_Table_EventTickets();
        $sa->AddTicket($data);
    }
}
