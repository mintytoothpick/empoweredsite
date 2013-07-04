<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_GiftAid extends Zend_Db_Table_Abstract {

    protected $_name = 'giftaid';
    protected $_primary = 'id';

    public function addDeclaration($data){
    try {
            return $this->insert($data);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $zde) {
            throw $zde;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function editDeclaration($id, $data) {
        $where = $this->getAdapter()->quoteInto("id = ?", $id);
        $this->update($data, $where);
    }

    /**
     * Return list of gift aids for an specific Organization
     *
     * @param String $NetworkId Id group
     *
     * @return Array
     */
    public function getListByOrganization($NetworkId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('ga' => 'giftaid'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = ga.ProjectId',
                                    array())
                             ->join(array('pd' => 'project_donations'),
                                    'pd.ProjectDonationId = ga.project_donation_id
                                     AND pd.OrderStatusId > 0
                                     AND pd.OrderStatusId < 3',
                                    array())
                             ->where("p.Name LIKE '%$search%' OR
                                      ga.first_name LIKE '%$search%' OR
                                      ga.last_name LIKE '%$search%' OR
                                      ga.email LIKE '%$search%' OR
                                      ga.phone LIKE '%$search%' OR
                                      ga.address LIKE '%$search%' OR
                                      ga.salutation LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("ga.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("ga.CreatedOn <= '$endDate'");
        }
        $select = $select->where("ga.NetworkId = ?", $NetworkId)
                         ->order(array('ga.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    /**
     * Return list of gift aids for an specific program
     *
     * @param String $ProgramId
     *
     * @return Array
     */
    public function getListByProgram($ProgramId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('ga' => 'giftaid'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = ga.ProjectId',
                                    array())
                             ->join(array('pd' => 'project_donations'),
                                    'pd.ProjectDonationId = ga.project_donation_id
                                     AND pd.OrderStatusId > 0
                                     AND pd.OrderStatusId < 3',
                                    array())
                             ->where("p.Name LIKE '%$search%' OR
                                      ga.first_name LIKE '%$search%' OR
                                      ga.last_name LIKE '%$search%' OR
                                      ga.email LIKE '%$search%' OR
                                      ga.phone LIKE '%$search%' OR
                                      ga.address LIKE '%$search%' OR
                                      ga.salutation LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("ga.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("ga.CreatedOn <= '$endDate'");
        }
        $select = $select->where("ga.ProgramId = ?", $ProgramId)
                         ->order(array('ga.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    /**
     * Return list of gift aids for an specific group
     *
     * @param String $ProgramId
     *
     * @return Array
     */
    public function getListByGroup($GroupId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('ga' => 'giftaid'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = ga.ProjectId',
                                    array())
                             ->join(array('pd' => 'project_donations'),
                                    'pd.ProjectDonationId = ga.project_donation_id
                                     AND pd.OrderStatusId > 0
                                     AND pd.OrderStatusId < 3',
                                    array())
                             ->where("p.Name LIKE '%$search%' OR
                                      ga.first_name LIKE '%$search%' OR
                                      ga.last_name LIKE '%$search%' OR
                                      ga.email LIKE '%$search%' OR
                                      ga.phone LIKE '%$search%' OR
                                      ga.address LIKE '%$search%' OR
                                      ga.salutation LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("ga.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("ga.CreatedOn <= '$endDate'");
        }
        $select = $select->where("ga.GroupId = ?", $GroupId)
                         ->order(array('ga.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    /**
     * Return list of gift aids for an specific project
     *
     * @param String $ProjectId
     *
     * @return Array
     */
    public function getListByProject($ProjectId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('ga' => 'giftaid'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = ga.ProjectId',
                                    array())
                             ->join(array('pd' => 'project_donations'),
                                    'pd.ProjectDonationId = ga.project_donation_id
                                     AND pd.OrderStatusId > 0
                                     AND pd.OrderStatusId < 3',
                                    array())
                             ->where("p.Name LIKE '%$search%' OR
                                      ga.first_name LIKE '%$search%' OR
                                      ga.last_name LIKE '%$search%' OR
                                      ga.email LIKE '%$search%' OR
                                      ga.phone LIKE '%$search%' OR
                                      ga.address LIKE '%$search%' OR
                                      ga.salutation LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("ga.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("ga.CreatedOn <= '$endDate'");
        }
        $select = $select->where("ga.ProjectId = ?", $ProjectId)
                         ->order(array('ga.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }


}
