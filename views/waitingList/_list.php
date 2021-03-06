<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
?>
<div id="pas_warnings" class="alertBox" style="display: none;">
	<div class="no_gp" style="display: none;">One or more patients has no GP practice address, please correct in PAS before printing GP letter.</div>
	<div class="no_address" style="display: none;">One or more patients has no Address, please correct in PAS before printing a letter for them.</div>
</div>
<div id="waitingList" class="grid-view">
	<table class="waiting-list">
		<tbody>
			<tr>
				<th>Letters sent</th>
				<th style="width: 120px;">Patient</th>
				<th style="width: 53px;">Hospital number</th>
				<th style="width: 95px;">Location</th>
				<th>Procedure</th>
				<th>Eye</th>
				<th>Firm</th>
				<th style="width: 80px;">Decision date</th>
				<th>Priority</th>
				<th>Book status (requires...)</th>
				<th><input style="margin-top: 0.4em;" type="checkbox" id="checkall" value="" /> All</th>
			</tr>
			<?php if (empty($operations)) {?>
				<tr>
					<td colspan="7" style="border: none; padding-top: 10px;">
						There are no patients who match the specified criteria.
					</td>
				</tr>
			<?php }else{?>
				<?php
				$i = 0;
				foreach ($operations as $id => $operation) {
					$eo = Element_OphTrOperationbooking_Operation::model()->findByPk($operation['eoid']);
					
					$patient = NULL;
					if(isset($operation['pid'])){
						$patient = Patient::model()->noPas()->findByPk($operation['pid']);
					}
					if (isset($_POST['status']) and $_POST['status'] != '') {
						if ($eo->getNextLetter() != $_POST['status']) {
							continue;
						}
					}?>

					<?php if ($eo->getWaitingListStatus() == Element_OphTrOperationbooking_Operation::STATUS_PURPLE) {
						$tablecolour = "Purple";
					} elseif ($eo->getWaitingListStatus() == Element_OphTrOperationbooking_Operation::STATUS_GREEN1) {
						$tablecolour = "Green";
					} elseif ($eo->getWaitingListStatus() == Element_OphTrOperationbooking_Operation::STATUS_GREEN2) {
						$tablecolour = "Green";
					} elseif ($eo->getWaitingListStatus() == Element_OphTrOperationbooking_Operation::STATUS_ORANGE) {
						$tablecolour = "Orange";
					} elseif ($eo->getWaitingListStatus() == Element_OphTrOperationbooking_Operation::STATUS_RED) {
						$tablecolour = "Red";
					} else {
						$tablecolour = "White";
					}?>
					<tr class="waitinglist<?php echo ($i % 2 == 0) ? 'Even' : 'Odd'; ?>">
						<td class="letterStatus waitinglist<?php echo $tablecolour ?>">
							<?php if ($eo->sentInvitation()) {?>
								<img src="<?php echo $assetPath?>/img/letterIcons/invitation.png" alt="Invitation" width="17" height="17" />
							<?php }?>
							<?php if ($eo->sent1stReminder()) {?>
								<img src="<?php echo $assetPath?>/img/letterIcons/letter1.png" alt="1st reminder" width="17" height="17" />
							<?php }?>
							<?php if ($eo->sent2ndReminder()) {?>
								<img src="<?php echo $assetPath?>/img/letterIcons/letter2.png" alt="2nd reminder" width="17" height="17" />
							<?php }?>
							<?php if ($eo->sentGPLetter()) {?>
								<img src="<?php echo $assetPath?>/img/letterIcons/GP.png" alt="GP" width="17" height="17" />
							<?php }?>
						</td>
						<td class="patient">
							<?php echo CHtml::link("<strong>" . trim(strtoupper($operation['last_name'])) . '</strong>, ' . $operation['first_name'], Yii::app()->createUrl('/OphTrOperationbooking/default/view/'.$operation['evid']))?>
						</td>
						<td><?php echo $operation['hos_num'] ?></td>
						<td><?php echo $eo->site->short_name?></td>
						<td><?php echo $operation['List'] ?></td>
						<td><?php echo $eo->eye->name ?></td>
						<td><?php echo $eo->event->episode->firm->name ?> (<?php echo $eo->event->episode->firm->serviceSubspecialtyAssignment->subspecialty->name?>)</td>
						<td><?php echo $eo->NHSDate('decision_date') ?></td>
						<td><?php echo $eo->priority->name?></td>
						<td><?php echo ucfirst(preg_replace('/^Requires /','',$eo->status->name)) ?></td>
						<td<?php if ($tablecolour == 'White' && Yii::app()->user->checkAccess('admin')) { ?> class="admin-td"<?php } ?>>

							<?php if(($patient && $patient->address) && $operation['eoid'] && ($eo->getDueLetter() != Element_OphTrOperationbooking_Operation::LETTER_GP || ($eo->getDueLetter() == Element_OphTrOperationbooking_Operation::LETTER_GP && $operation['practice_id']))) { ?>
							<div>	
								<input<?php if ($tablecolour == 'White' && !Yii::app()->user->checkAccess('admin')) { ?> disabled="disabled"<?php } ?> type="checkbox" id="operation<?php echo $operation['eoid']?>" value="1" />
							</div>
							<?php }?>
							
							<?php if(!$operation['practice_address_id'] ) { ?>
								<script type="text/javascript">
									$('#pas_warnings').show();
									$('#pas_warnings .no_gp').show();
								</script>
								<span class="no-GP">No GP</span>
							<?php } ?>
							
							<?php if($patient && !$patient->address){ ?>
								<script type="text/javascript">
									$('#pas_warnings').show();
									$('#pas_warnings .no_address').show();
								</script>
								<span class="no-Address">No Address</span>
							<?php } ?>
						</td>
					</tr>
				<?php
					$i++;
				}

				if ($i == 0) {?>
					<tr>
						<td colspan="7" style="border: none; padding-top: 10px;">
							There are no patients who match the specified criteria.
						</td>
					</tr>
				<?php }?>
			<?php }?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="11">
					<div id="key">
						<span>Colour Key:</span>
						<div class="container" id="sendflag-invitation"><div class="color_box"></div><div class="label">Send invitation letter</div></div>
						<div class="container" id="sendflag-reminder"><div class="color_box"></div><div class="label">Send another reminder (2 weeks)</div></div>
						<div class="container" id="sendflag-GPremoval"><div class="color_box"></div><div class="label">Send GP removal letter</div></div>
						<div class="container" id="sendflag-remove"><div class="color_box"></div><div class="label">Patient is due to be removed</div></div>
					</div>
				</td>
			</tr>
			<tr>
				<td colspan="11" class="small">
					<div id="letters-key">
						<span>Letters sent out:</span>&nbsp;&nbsp;
						<img src="<?php echo $assetPath?>/img/letterIcons/invitation.png" alt="Invitation" height="17" width="17"> - Invitation
						<img src="<?php echo $assetPath?>/img/letterIcons/letter1.png" alt="1st reminder" height="17" width="17"> - 1<sup>st</sup> Reminder
						<img src="<?php echo $assetPath?>/img/letterIcons/letter2.png" alt="2nd reminder" height="17" width="17"> - 2<sup>nd</sup> Reminder
						<img src="<?php echo $assetPath?>/img/letterIcons/GP.png" alt="GP" height="17" width="17"> - GP Removal
					</div>
				</td>
			</tr>
		</tfoot>
	</table>
</div>
<script type="text/javascript">
	$('#checkall').click(function() {
		$('input[id^="operation"]:enabled').attr('checked',$('#checkall').is(':checked'));
	});

	// Row highlighting
	$(this).undelegate('.waiting-list td','click').delegate('.waiting-list td','click',function() {
		var $tr = $(this).closest("tr");
		$tr.toggleClass('hover');
	});
</script>	
