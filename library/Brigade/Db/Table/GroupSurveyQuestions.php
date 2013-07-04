<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_GroupSurveyQuestions extends Zend_Db_Table_Abstract {

    protected $_name = 'group_survey_questions';
    protected $_primary = 'SurveyQuestionId';

    public function AddSurveyQuestion($data) {
        $this->insert($data);
    }

    public function EditSurveyQuestion($SurveyQuestionId, $data) {
        $where = $this->getAdapter()->quoteInto("SurveyQuestionId = ?", $SurveyQuestionId);
        $this->update($data, $where);
    }

    public function getSurveyQuestions($SurveyId) {
        return $this->fetchAll($this->select()->where("SurveyId = ?", $SurveyId)->where("isDeleted = 0"))->toArray();
    }

    public function deleteSurveyQuestion($SurveyQuestionId) {
        $where = $this->getAdapter()->quoteInto("SurveyQuestionId = ?", $SurveyQuestionId);
        $this->update(array('isDeleted' => 1), $where);
    }

}
?>
