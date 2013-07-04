<?php
require_once 'MembershipFrequency.php';

/**
 * Helper for membership payments list
 *
 * @author Matias Gonzalez
 */
class Layout_Helper_MembershipPaymentsHelper extends Zend_View_Helper_Abstract
{
    /**
     * Generate list to edit membership payments chapter settings
     *
     * @public
     * @return Array of Payments selected or not.
     */
    public function membershipPaymentsHelper($group, $org) {
        $list   = '';
        $gFreq  = null;
        $ids    = Payment::getAllIds();
        $list .= '<li class="field-label freqAmnts">Membership Required</li>';
        $list .= '<li class="field-input freqAmnts">';
        $list .= '<input name="activityRequiresMembership" type="checkbox" '.
                 'id="activityRequiresMembership" value="1" '.
                 ((!empty($group) && $group->activityRequiresMembership) ? 'checked' : '').' />';
        $list .= '&nbsp;Only members can volunteer on initiatives. '.
                 '<a href="javascript:;" class="tooltip" title="This restricts '.
                 'all volunteer activities to just members of a group. Non '.
                 'members can still attend events and fundraise on behalf of '.
                 'the group">?</a>';
        $list .= '</li>';

        if (count($ids) > 0) {
            $list .= '<div class="clear freqAmnts"></div><br />'.
                     '<li class="freqAmnts"><b>Select one or more frequencies '.
                     'for membership donation amount:</b><br /><br /></li>';
        }
        foreach($ids as $freq) {
            if ($freq['id'] == Payment::ONETIME) continue; //Skip onetime for membership
            if ($freq['id'] == Payment::ONEDAY) continue; //Skip oneday for membership
            if ($group) {
                $gFreq = $group->getMembershipFrequency($freq['id']);
            }
            $list  .= "<li class='field-label freqAmnts'><input type='checkbox'".
                      ((!empty($gFreq)) ? ' checked' : '')." value='".
                      $freq['id']."' name='feeFreq[]' class='feeFreq' onchange='toggleInp(".
                      $freq['id'].")'/> ". $freq['name']."</li>";
            $list  .= "<li class='field-input freqAmnts'>".((isset($org->currency)) ? $org->currency : '$').
                      "<input name='feeAmnt_".$freq['id'].
                      "' type='text' id='feeAmnt_".$freq['id']."' class='feeFreqAmnts' value='".
                      ((!empty($gFreq)) ? $gFreq->amount : '')."' ".
                      ((!empty($gFreq)) ? "" : "disabled")."/></li><div class='clear freqAmnts'></div>";
        }
        if ($list != '') {
            $list .= '<div class="clear freqAmnts"></div><br />';
            $list .= '<li class="freqAmnts"><label id="freqError" '.
                     'style="color:red; display:none;">Please select a type of '.
                     'membership donation.</label></li>';
            $list .= '<li class="freqAmnts"><label id="freqErrorAmnts"'.
                     ' style="color:red; display:none;">The donation amount'.
                     ' must be numeric and $1 minimum.</label></li>';
        }
        return $list;
    }
}
