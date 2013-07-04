<?php

require_once 'Brigade/Db/Table/ContactInformation.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Zend/Db/Table/Abstract.php';

class Brigade_Db_Table_Countries extends Zend_Db_Table_Abstract {

    protected $_name = 'Countries';
    protected $_primary = 'CountryId';

    public function getAllCountries()
    {
        $rows = $this->fetchAll($this->select()
                    ->from(array('Co'=>'Countries'), array('Co.*')))
                    ->toArray();
        return $rows;
    }
    
    public function getCountries($Countries)
    {
        if ($Countries == 'preset')
        {
            $where = 'Co.CountryId IN (15, 43, 70, 91, 92, 100, 108, 117, 129, 190, 194, 234, 253, 254)';
        }
        else if ($Countries == 'find')
        {
            $ContactInformation = new Brigade_Db_Table_ContactInformation();
            $rows = $ContactInformation->getCountries();
            $Countries = '';
            foreach($rows as $row) {
                $Countries .= $row['CountryId'].', ';
            }
            $Countries = substr($Countries, 0, strlen($Countries)-2);
            $where = 'Co.CountryId IN (' . $Countries . ')';
        }
        else
        {
            $where = 'Co.CountryId IN (' . $Countries . ')';
        }
        $rows = $this->fetchAll($this->select()
                    ->from(array('Co'=>'Countries'), array('Co.CountryId', 'Co.Country', 'Co.CurrencyCode'))
                    ->where($where))
                    ->toArray();
        
        return $rows;
    }
    
    /* Start SQL Refactor */
    
    public function loadInfo($id) {
        $res = $this->fetchRow($this->select()->where('CountryId = ?', $id));
        if ($res) {
            return $res->toArray();
        } else return null;
    }
}
