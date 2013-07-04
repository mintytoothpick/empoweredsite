<?php

/**
 * Get coalitions programs.
 *
 * @author Matias Gonzalez
 */
class Brigade_Db_Table_CoalitionPrograms extends Zend_Db_Table_Abstract {

    protected $_name = 'coalition_programs';
    protected $_primary = 'ProgramId';

    /**
     * Get list of Coalitions Programs Ids.
     *
     * @return Array List of coalition program id.
     */
    public function getCoalition($ProgramId) {
        $row = $this->fetchRow(
            $this->select()->where('ProgramId = ?', $ProgramId)
                )->toArray();
        if(!empty($row)) {
            $rows = $this->fetchAll(
                $this->select()->where('CoalitionProgramId = ?', $row['CoalitionProgramId'])
            );
            return $rows->toArray();
        } else {
            return NULL;
        }
    }
}
