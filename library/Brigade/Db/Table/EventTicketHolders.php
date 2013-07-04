<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_EventTicketHolders extends Zend_Db_Table_Abstract {

    protected $_name = 'event_ticket_holders';

    public function getTicketHoldersById($TicketId) {
        $result = $this->fetchAll($this->select()->where('TicketId = ?', $TicketId));
        return !empty($result) ? $result->toArray() : null;
    }

    public function getTicketHoldersByEvent($EventId) {
        $result = $this->fetchAll($this->select()
            ->from(array('th' => 'event_ticket_holders'), array('th.*', 't.*'))
            ->joinInner(array('t' => 'event_tickets'), 't.EventId=th.EventId')
            ->where('t.EventId = ?', $EventId)
            ->setIntegrityCheck(false));
        return !empty($result) ? $result->toArray() : null;
    }

    public function AddTicketHolder($data) {
        $data['VerificationCode'] = sha1(uniqid('bcd', true));
        $this->insert($data);
    }

    public function deleteTicketHolder($TicketHolderId, $UserId) {
        $where = $this->getAdapter()->quoteInto("TicketHolderId = ?", $TicketHolderId);
        $this->delete($where);
    }

    /** Start SQL Refactor **/

    /**
     * Get list of users ids to populate attendees of an event.
     *
     * @param String $EventId Id of the event.
     */
    public function getListUserIdByEvent($EventId) {
        $result = $this->fetchAll($this->select()->distinct()
            ->from(array('th' => 'event_ticket_holders'), array('th.UserId', 'th.FullName'))
            ->where('th.EventId = ?', $EventId));
        return !empty($result) ? $result->toArray() : null;
    }

    /**
     * Get list of tickets holder for events RSVP.
     *
     * @param String $EventId Event id
     */
    public function getTicketHoldersByEventRSVP($EventId) {
        $result = $this->fetchAll($this->select()
            ->from(array('th' => 'event_ticket_holders'), array('th.*'))
            ->where('th.EventId = ?', $EventId));
        return $result ? $result->toArray() : null;
    }

}
?>
