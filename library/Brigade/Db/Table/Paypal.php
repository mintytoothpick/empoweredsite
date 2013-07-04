<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';

class Brigade_Db_Table_Paypal extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'paypal';
    protected $_primary = 'id';

    public function loadInfo($id) {
        return $this->fetchRow($this->select()->where('id = ?', $id));
    }

    public function addPaypalAccount($data) {
        return $this->insert($data);
    }

    public function editPaypalAccount($id, $data) {
        $where = $this->getAdapter()->quoteInto("id = ?", $id);
        $this->update($data, $where);
    }

    public function deletePaypalAccount($PaypalAccountId) {
        $where = $this->getAdapter()->quoteInto("id = ?", $id);
        $this->delete($where);
    }
}

?>
