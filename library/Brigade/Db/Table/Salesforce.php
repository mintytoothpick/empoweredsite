<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Salesforce extends Zend_Db_Table_Abstract {

    protected $_name = 'salesforce';
    protected $_primary = 'id';

    /**
     * Load stripe information account.
     */
    public function loadByOrganization($organizationId) {
        try {
            $row = $this->fetchRow(
                $this->select()->where("OrganizationId = '$organizationId'")
            );
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
