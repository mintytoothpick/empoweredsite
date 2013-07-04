<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_OrganizationMembersTitles extends Zend_Db_Table_Abstract {

    protected $_name = 'organization_members_titles';
    protected $_primary = 'Id';


    public function AddTitle($data) {
        if (!isset($data['CreatedOn'])) {
            $data['CreatedOn'] = date('Y-m-d H:i:s');
        }
        return $this->insert($data);
    }

    public function EditTitle($Id, $data) {
        $where = $this->getAdapter()->quoteInto("Id = ?", $Id);
        $this->update($data, $where);
    }


    /**
     * Load information.
     */
    public function loadInfo($id) {
        try {
            $row = $this->fetchRow($this->select()->where("Id = $id"));
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

    public function getByOrganization($orgId) {
        try {
            $select = $this->select()->where("OrganizationId = ?", $orgId)
                                     ->where('isDeleted = 0');
            $all    = $this->fetchAll($select);
            if ($all)
                return $all->toArray();
            else
                return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }
}
