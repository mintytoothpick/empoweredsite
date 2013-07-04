<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Survey extends Zend_Db_Table_Abstract {

    protected $_name = 'survey';
    protected $_primary = 'SurveyId';

    public function addSurvey($data){
    try {
            $this->insert($data);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $zde) {
            throw $zde;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getProjectSurveyReport($ProjectId) {
        try {
            return $this->fetchAll($this->select()->where('ProjectId = ?', $ProjectId)->order('FirstName'))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    /**
     * Load survey information object
     *
     * @param String $SurveyId Survey id.
     *
     * @return Array Survey row info.
     */
    public function loadInfo($SurveyId) {
        $res = $this->fetchRow($this->select()->where('SurveyId = ?', $SurveyId));
        if ($res) {
            return $res->toArray();
        } else return null;
    }

    /**
     * Load survey information object by project and user
     *
     * @param String $ProjectId
     * @param String $UserId
     *
     * @return Array Survey row info.
     */
    public function getByProjectAndUser($ProjectId, $UserId) {
        $row = $this->fetchAll(
            $this->select()
                ->where('ProjectId = ?', $ProjectId)
                ->where('UserId = ?', $UserId)
        );
        if ($row) {
            return $row->toArray();
        } else {
            return null;
        }
    }

    /**
     * Update information survey
     */
    public function updateInfo($data, $id) {
        $where = $this->getAdapter()->quoteInto('SurveyId = ?', $id);
        $this->update($data, $where);
    }
}
