<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/GroupSurveyFeedbacks.php';

class Brigade_Db_Table_GroupSurveyRespondents extends Zend_Db_Table_Abstract {

    protected $_name = 'group_survey_respondents';
    protected $_primary = 'SurveyRespondentId';

    public function AddSurveyRespondent($data) {
        $this->insert($data);
    }

    public function getRespondents($SurveyId, $did_not_fill_survey = true) {
        return $this->fetchAll($this->select()
            ->from(array('r' => 'group_survey_respondents'), array('u.FullName', 'u.UserId', 'u.URLName', 'u.Email'))
            ->joinInner(array('u' => "users"), 'r.UserId=u.UserId')
            ->where("r.SurveyId = ?", $SurveyId)
            ->where("r.UserId NOT IN (SELECT f.AnsweredBy FROM group_survey_feedbacks f INNER JOIN group_survey_questions q ON q.SurveyQuestionId=f.SurveyQuestionId WHERE q.SurveyId='$SurveyId' GROUP BY f.AnsweredBy)")
            ->setIntegrityCheck(false)
        )->toArray();
    }

    public function isRespondentExists($SurveyId, $UserId) {
        $rows = $this->fetchAll($this->select()->where("SurveyId = ?", $SurveyId)->where("UserId = ?", $UserId));
        if ($rows) {
            return $rows->toArray();
        } else {
            return false;
        }
    }

}
?>
