<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Stripe extends Zend_Db_Table_Abstract {

    protected $_name = 'stripe';
    protected $_primary = 'id';

    /**
     * Load stripe information account.
     */
    public function loadInfo($id) {
        try {
            $row = $this->fetchRow($this->select()->where("id = $id"));
            if (count($row)){
                $row = $row->toArray();
                return $row;
            } else{
                return null;
            }

        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }
}
