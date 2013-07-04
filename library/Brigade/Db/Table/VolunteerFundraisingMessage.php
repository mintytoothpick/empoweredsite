<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_VolunteerFundraisingMessage extends Zend_Db_Table_Abstract {

    protected $_name = 'volunteerfundraisingmessage';
    protected $_primary = 'FundraisingMessageId';

    public function getFundraisingMessage($ProjectId, $UserId) {
        $row = $this->fetchRow($this->select()->where('BrigadeId = ?', $ProjectId)->where('VolunteerId = ?', $UserId));
        return !empty($row) ? $row->toArray() : null;
    }

    public function addFundRaisingMessage($data) {
        $data['FundraisingMessageId'] = $this->createFundraisingMessageId();
        $this->insert($data);
        return $data['FundraisingMessageId'];
    }

    private function createFundraisingMessageId() {
        $row = $this->fetchRow($this->select()->from("volunteerfundraisingmessage", array('UUID() as FundraisingMessageId')));
        return strtoupper($row['FundraisingMessageId']);
    }
    
    public function updateFundraisingMessage($FundraisingMessageId, $data) {
        $where = $this->getAdapter()->quoteInto('FundraisingMessageId = ?', $FundraisingMessageId);
        $this->update($data, $where);
    }

    public function loadInfo($FundraisingMessageId) {
        return $this->fetchRow($this->select()->where('FundraisingMessageId = ?', $FundraisingMessageId))->toArray();
    }

}
?>
