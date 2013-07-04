<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/ContactSite.php';

class Brigade_Db_Table_ContactInformation extends Zend_Db_Table_Abstract {

// table name
    protected $_name = 'contactinformation';

    public function getContactInfo($ID, $type = 'All') {
        try {
            $row = $this->fetchRow($this->select()
                ->where("SiteId = '$ID'"));
            if ($row) {
                $row->toArray();
            }
            return count($row) > 0 ? ($type != 'All' ? $row[$type] : $row) : "";
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function addContactInfo($data) {
        $data['ContactId'] = $this->createContactId();
        $data['CreatedBy'] = $_SESSION['UserId'];
        $data['CreatedOn' ]= date('Y-m-d H:i:s');
        $this->insert($data);

        return $data['ContactId'];
    }

    public function editContactInfo($ContactId, $data) {
        if (empty($data['ModifiedBy'])) {
            $data['ModifiedBy'] = $_SESSION['UserId'];
            $data['ModifiedOn' ]= date('Y-m-d H:i:s');
        }
        $where = $this->getAdapter()->quoteInto('ContactId = ?', $ContactId);
        $this->update($data, $where);
    }

    public function createContactId() {
        $row = $this->fetchRow($this->select()->from("contactinformation", array('UUID() as ContactId')));
        return strtoupper($row['ContactId']);
    }

    public function deleteContactInfo($SiteId) {
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function listLocations($Type, $Field = "Country", $Location = NULL) {
        $select = $this->select()->from("contactinformation", array("$Type"))->distinct()->where("$Field != ''");
        if (!empty($Location)) {
            $select = $select->where("$Field = '$Location'");
        }
        return $this->fetchAll($select)->toArray();
    }

    public function getCountries() {
    $rows = $this->fetchAll($this->select()
        ->from('contactinformation', array('CountryId'))
        ->where('CountryId != 0'))->toArray();
    return $rows;
    }

    public function getRegions($CountryId) {
    $rows = $this->fetchAll($this->select()
        ->from(array('ci'=>'contactinformation'), array('ci.RegionId', 'ci.CountryId', 'R.Region'))
        ->joinInner(array('R'=>'Regions'), 'R.RegionId = ci.RegionId')
        ->where('ci.CountryId = ?', $CountryId)
        ->group('ci.RegionId')
        ->setIntegrityCheck(false))->toArray();
    return $rows;
    }

    public function getCities($RegionId) {
    $rows = $this->fetchAll($this->select()
        ->from(array('ci'=>'contactinformation'), array('ci.CityId', 'ci.RegionId', 'Ct.City'))
        ->joinInner(array('Ct'=>'Cities'), 'Ct.CityId = ci.CityId')
        ->where('ci.RegionId = ?', $RegionId)
        ->group('ci.CityId')
        ->setIntegrityCheck(false))->toArray();
    return $rows;
    }

    public function generateLocation($SiteId) {
    $row = $this->fetchRow($this->select()
        ->from(array('ci'=>'contactinformation'), array('ci.*', 'c.City as City', 'r.Region as Region', 'co.Country as Country'))
            ->joinLeft(array('c' => 'Cities'), 'ci.CityId=c.CityId')
            ->joinLeft(array('r' => 'Regions'), 'ci.RegionId=r.RegionId')
            ->joinLeft(array('co' => 'Countries'), 'ci.CountryId=co.CountryId')
        ->where('ci.SiteId = ?', $SiteId)
        ->setIntegrityCheck(false));
        $row = !empty($row) ? $row->toArray() : NULL;
        $location = '';
        if (!empty($row)) {
            if($row['Street'] != '') { $location .= $row['Street']."<br />"; }
            if($row['City'] != '') {
                $location .= $row['City'];
                if($row['Region'] != '' || $row['Country']) { $location .= ','; }
                $location .= ' ';
            }
            if($row['Region'] != '') {
                $location .= $row['Region'];
                if($row['Country'] != '') { $location .= ','; }
                $location .= ' ';
            }
            if($row['Country'] != '') { $location .= $row['Country']; }
        }
        return $location;
    }

    public function populateNewColumns() {
        $ContactSite = new Brigade_Db_Table_ContactSite();
        $rows = $ContactSite->listAll();
        foreach($rows as $row) {
            $where = $this->getAdapter()->quoteInto('ContactId = ?', $row['ContactId']);
            $this->update(array('SiteId' => $row['SiteId']), $where);
        }
    }

} ?>
