<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';

class Brigade_Db_Table_GoogleCheckoutAccounts extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'googlecheckoutaccounts';
    protected $_primary = 'GoogleCheckoutAccountId';

    public function loadInfo($GoogleCheckoutAccountId) {
        return $this->fetchRow($this->select()->where('GoogleCheckoutAccountId = ?', $GoogleCheckoutAccountId));
    }

    public function addGoogleCheckoutAccount($data) {
        return $this->insert($data);
    }

    public function editGoogleCheckoutAccount($GoogleCheckoutAccountId, $data) {
        $where = $this->getAdapter()->quoteInto("GoogleCheckoutAccountId = ?", $GoogleCheckoutAccountId);
        $this->update($data, $where);
    }

    public function deleteGoogleCheckoutAccount($GoogleCheckoutAccountId) {
        $where = $this->getAdapter()->quoteInto("GoogleCheckoutAccountId = ?", $GoogleCheckoutAccountId);
        $this->delete($where);
    }

    public function getMaxCheckoutId() {
        return $this->fetchRow($this->select()->order('GoogleCheckoutAccountId DESC'))->toArray();
    }

}

?>