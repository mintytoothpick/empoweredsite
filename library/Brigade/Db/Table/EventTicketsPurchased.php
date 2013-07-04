<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_EventTicketsPurchased extends Zend_Db_Table_Abstract {

    protected $_name = 'event_tickets_purchased';
    protected $_primary = 'TicketPurchaseId';

    public function AddTicketPurchased($data) {
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "";
        return $this->insert($data);
    }

    public function updateTicket($TicketPurchaseId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = isset($_SESSION['UserId']) ? $_SESSION['UserId'] : "";
        $where = $this->getAdapter()->quoteInto('TicketPurchaseId = ?', $TicketPurchaseId);
        $this->update($data, $where);
    }

    public function updateTicketByTransactionId($TransactionId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $where = $this->getAdapter()->quoteInto('TransactionId = ?', $TransactionId);
        $this->update($data, $where);
    }
    
    public function getInfoByTransactionId($TransactionId) {
        return $this->fetchRow($this->select()
            ->from(array('tp' => 'event_tickets_purchased'), array('e.*', 'tp.*'))
            ->joinInner(array('e' => 'events'), 'e.EventId=tp.EventId')
            ->where('tp.TransactionId = ?', $TransactionId))->toArray();
    }
    
    public function deleteTicket($TicketPurchaseId) {
        $where = $this->getAdapter()->quoteInto('TicketPurchaseId = ?', $TicketPurchaseId);
        $this->delete($where);
    }
    
}
?>
