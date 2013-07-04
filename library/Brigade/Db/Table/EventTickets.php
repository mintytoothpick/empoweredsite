<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_EventTickets extends Zend_Db_Table_Abstract {

    protected $_name = 'event_tickets';
    protected $_primary = 'EventTicketId';

    public function getEventTickets($EventId) {
        $result = $this->fetchAll($this->select()->where('EventId = ?', $EventId));
        return !empty($result) ? $result->toArray() : null;
    }

    public function loadInfo($EventTicketId) {
        return $this->fetchRow($this->select()->where('EventTicketId = ?', $EventTicketId))->toArray();
    }
    
    public function AddTicket($data) {
        $this->insert($data);
    }

    public function updateTicket($EventTicketId, $data) {
        $where = $this->getAdapter()->quoteInto('EventTicketId = ?', $EventTicketId);
        $this->update($data, $where);
    }

	public function lowerQuantity($EventTicketId, $purchased) {
		$ticketInfo = $this->fetchRow($this->select()->where('EventTicketId = ?', $EventTicketId))->toArray();
		$newQuantity = $ticketInfo['Quantity'] - $purchased;
		$where = $this->getAdapter()->quoteInto('EventTicketId = ?', $EventTicketId);
        $this->update(array('Quantity' => $newQuantity), $where);
	}

    public function deleteTicket($EventTicketId) {
        $where = $this->getAdapter()->quoteInto('EventTicketId = ?', $EventTicketId);
        $this->delete($where);
    }
    
}
?>
