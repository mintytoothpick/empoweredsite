<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_FlyForGood extends Zend_Db_Table_Abstract {

    protected $_name = 'fly_for_good';
    protected $_primary = 'id';

    /* Start SQL Refactor */

    public function getMoneySpent($userId, $organizationId) {
        $res = $this->fetchRow(
            $this->select()
                 ->from(array('ffg' => 'fly_for_good'),
                        array(
                            'SUM(ffg.Amount) as TotalSpent',
                            'SUM(ffg.Fee) as TotalFee'
                        ))
                 ->where('UserId = ?', $userId)
                 ->where('Status = 2')
                 ->where('OrganizationId = ?', $organizationId)
        );
        if ($res) {
            return $res->toArray();
        } else return null;
    }

    /**
     * Return list of transactions for an specific Organization
     *
     * @param String $NetworkId Id org
     *
     * @return Array
     */
    public function getListByOrganization($NetworkId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('ffg' => 'fly_for_good'));
        if ($search) {
            $select = $select->join(array('u' => 'users'),
                                    'u.UserId = ffg.UserId',
                                    array())
                             ->join(array('n' => 'networks'),
                                    'n.NetworkId = ffg.NetworkId',
                                    array())
                             ->where("id LIKE '%$search%' OR
                                      FlyForGoodId LIKE '%$search%' OR
                                      Amount LIKE '%$search%' OR
                                      u.FullName LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("ffg.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("ffg.CreatedOn <= '$endDate'");
        }
        $select = $select->where("ffg.NetworkId = ?", $NetworkId)
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }
}
