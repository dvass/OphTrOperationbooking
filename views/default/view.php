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
<?php $this->header() ?>

<h3 class="withEventIcon"><?php  echo $this->event_type->name ?> (<?php echo Element_OphTrOperationbooking_Operation::model()->find('event_id=?',array($this->event->id))->status->name?>)</h3>

<?php $this->renderPartial('//base/_messages'); ?>

<?php if (!$operation->has_gp) {?>
	<div class="alertBox">
		Patient has no GP practice address, please correct in PAS before printing GP letter.
	</div>
<?php } ?>
<?php if (!$operation->has_address) { ?>
	<div class="alertBox">
		Patient has no address, please correct in PAS before printing letter.
	</div>
<?php } ?>

<?php if ($operation->event->hasIssue()) {?>
	<div class="issueBox">
		<?php echo $operation->event->getIssueText()?>
	</div>
<?php }?>

<div>
	<?php
	$this->renderDefaultElements($this->action->id);
	$this->renderOptionalElements($this->action->id);
	?>
	<div class="cleartall"></div>
</div>

<?php
	// Event actions
	$this->renderPartial('//patient/event_actions');
?>

<?php  $this->footer() ?>
