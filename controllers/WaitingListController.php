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

class WaitingListController extends BaseEventTypeController {
	
	public function accessRules() {
		return array(
			// Level 2 or below can't change anything
			array('deny',
				'actions' => array('confirmprinted', 'printletters'),
				'expression' => '!BaseController::checkUserLevel(3)',
			),
			// Level 2 or above can do anything else
			array('allow',
				'expression' => 'BaseController::checkUserLevel(2)',
			),
			array('deny'),
		);
	}
	
	public function printActions() {
		return array(
			'printLetters',
		);
	}

	/**
		* Lists all models.
		*/
	public function actionIndex() {
		if (empty($_POST)) {
			if (($data = YiiSession::get('waitinglist_searchoptions'))) {
				$_POST = $data;
			} else {
				$_POST = array(
					'firm-id' => YiiSession::get('selected_firm_id'),
					'subspecialty-id' => Firm::Model()->findByPk(YiiSession::get('selected_firm_id'))->serviceSubspecialtyAssignment->subspecialty_id,
				);
			}

			Audit::add('waiting list','view');
		} else {
			Audit::add('waiting list','search',serialize($_POST));
		}

		$this->render('index');
	}

	public function actionSearch()
	{
		Audit::add('waiting list','search',serialize($_POST));

		if (empty($_POST)) {
			$operations = array();
		} else {
			$subspecialty_id = !empty($_POST['subspecialty-id']) ? $_POST['subspecialty-id'] : null;
			$firm_id = !empty($_POST['firm-id']) ? $_POST['firm-id'] : null;
			$status = !empty($_POST['status']) ? $_POST['status'] : null;
			$hos_num = !empty($_POST['hos_num']) && ctype_digit($_POST['hos_num']) ? $_POST['hos_num'] : false;
			$site_id = !empty($_POST['site_id']) ? $_POST['site_id'] : false;

			YiiSession::set('waitinglist_searchoptions',array(
					'subspecialty-id' => $subspecialty_id,
					'firm-id' => $firm_id,
					'status' => $status,
					'hos_num' => $hos_num,
					'site_id' => $site_id
			));

			$operations = $this->getWaitingList($firm_id, $subspecialty_id, $status, $hos_num, $site_id);
		}

		$this->renderPartial('_list', array('operations' => $operations, 'assetPath' => Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.assets'), false, -1, YII_DEBUG)), false, true);
	}

	public function getWaitingList($firm_id, $subspecialty_id, $status, $hos_num=false, $site_id=false) {
		$whereSql = '';
		$whereParams = array();

		if ($firm_id) {
			$whereSql .= ' AND f.id = :firm_id';
			$whereParams[":firm_id"] = $firm_id;
		} elseif (!empty($subspecialty_id)) {
			$whereSql .= ' AND ssa.subspecialty_id = :subspecialty_id';
			$whereParams[":subspecialty_id"] = $subspecialty_id;
		}

		if ($hos_num && ctype_digit($hos_num)) {
			if (Yii::app()->params['pad_hos_num']) {
				$hos_num = sprintf(Yii::app()->params['pad_hos_num'],$hos_num);
			}
			$whereSql .= " AND pat.hos_num = :hos_num";
			$whereParams[":hos_num"] = $hos_num;
		}

		if ($site_id && ctype_digit($site_id)) {
			$whereSql .= " AND eo.site_id = :site_id";
			$whereParams[":site_id"] = $site_id;
		}

		return Yii::app()->db->createCommand()
			->select("eo.id AS eoid, eo.decision_date as decision_date, ev.id AS evid, ep.id AS epid, pat.id AS pid, co.first_name, co.last_name, pat.hos_num, pat.gp_id,
				pat.practice_id, pad.id AS practice_address_id, GROUP_CONCAT(p.short_format SEPARATOR \", \") AS List")
			->from("et_ophtroperationbooking_operation eo")
			->join("event ev","eo.event_id = ev.id")
			->join("episode ep","ev.episode_id = ep.id")
			->join("firm f","ep.firm_id = f.id")
			->join("service_subspecialty_assignment ssa","f.service_subspecialty_assignment_id = ssa.id")
			->join("patient pat","ep.patient_id = pat.id")
			->join("contact co","co.parent_id = pat.id AND co.parent_class = 'Patient'")
			->join("ophtroperationbooking_operation_procedures_procedures opa","opa.element_id = eo.id")
			->join("proc p","opa.proc_id = p.id")
			->leftJoin("address pad","pad.parent_id = pat.practice_id AND pad.parent_class = 'Practice'")
			->where("ep.end_date IS NULL and eo.status_id in (1,3) $whereSql and ev.deleted = 0 group by opa.element_id",$whereParams)
			->order("decision_date asc")
			->queryAll();
	}

	/**
		* Generates a firm list based on a subspecialty id provided via POST
		* echoes form option tags for display
		*/
	public function actionFilterFirms()
	{
		YiiSession::set('waitinglist_searchoptions','subspecialty-id',$_POST['subspecialty_id']);

		echo CHtml::tag('option', array('value'=>''), CHtml::encode('All firms'), true);

		if (!empty($_POST['subspecialty_id'])) {
			$firms = $this->getFilteredFirms($_POST['subspecialty_id']);

			foreach ($firms as $id => $name) {
				echo CHtml::tag('option', array('value'=>$id), CHtml::encode($name), true);
			}
		}
	}

	public function setFilter($field, $value) {
		YiiSession::set('waitinglist_searchoptions',$field,$value);
	}

	public function actionFilterSetFirm() {
		$this->setFilter('firm-id', $_POST['firm_id']);
	}

	public function actionFilterSetStatus() {
		$this->setFilter('status', $_POST['status']);
	}

	public function actionFilterSetSiteId() {
		$this->setFilter('site_id', $_POST['site_id']);
	}

	public function actionFilterSetHosNum() {
		$this->setFilter('hos_num', $_POST['hos_num']);
	}
	/**
		* Helper method to fetch firms by subspecialty ID
		*
		* @param integer $subspecialtyId
		* @return array
		*/
	protected function getFilteredFirms($subspecialtyId)
	{
		// remove any firms that aren't part of a medical specialty
		$data = Yii::app()->db->createCommand()
		->select('f.id, f.name')
		->from('firm f')
		->join('service_subspecialty_assignment ssa', 'f.service_subspecialty_assignment_id = ssa.id')
		->join('subspecialty s', 'ssa.subspecialty_id = s.id')
		->join('specialty sp', 's.specialty_id = sp.id')
		->order('f.name asc')
		->where('ssa.subspecialty_id=:id AND sp.medical = :ismedical',
				array(':id'=>$subspecialtyId, ':ismedical' => true))
				->queryAll();

		$firms = array();
		foreach ($data as $values) {
			$firms[$values['id']] = $values['name'];
		}
		

		return $firms;
	}

	/**
		* Prints next pending letter type for requested operations
		* Operation IDs are passed as an array (operations[]) via GET or POST
		* Invalid operation IDs are ignored
		* @throws CHttpException
		*/
	public function actionPrintLetters() {
		Audit::add('waiting list',(@$_REQUEST['all']=='true' ? 'print all' : 'print selected'),serialize($_POST));

		if (isset($_REQUEST['event_id'])) {
			$operations = Element_OphTrOperationbooking_Operation::model()->findAll('event_id=?',array($_REQUEST['event_id']));
			$auto_confirm = false;
		} else {
			$operation_ids = (isset($_REQUEST['operations'])) ? $_REQUEST['operations'] : null;
			$auto_confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm'] == 1);
			if (!is_array($operation_ids)) {
				throw new CHttpException('400', 'Invalid operation list');
			}
			$operations = Element_OphTrOperationbooking_Operation::model()->findAllByPk($operation_ids);
		}

		// Print letter(s) for each operation
		$this->layout = '//layouts/pdf';
		$pdf_print = new OEPDFPrint('Openeyes', 'Waiting list letters', 'Waiting list letters');
		foreach($operations as $operation) {
			$this->printLetter($pdf_print, $operation, $auto_confirm);
		}
		$pdf_print->output();
	}

	/**
		* Print the next letter for an operation
		* @param OEPDFPrint $pdf_print
		* @param Element_OphTrOperationbooking_Operation $operation
		* @param Boolean $auto_confirm
		*/
	protected function printLetter($pdf_print, $operation, $auto_confirm = false) {
		$patient = $operation->event->episode->patient;
		$letter_status = $operation->getDueLetter();
		if ($letter_status === null && $operation->getLastLetter() == Element_OphTrOperationbooking_Operation::LETTER_GP) {
			$letter_status = Element_OphTrOperationbooking_Operation::LETTER_GP;
		}
		$letter_templates = array(
				Element_OphTrOperationbooking_Operation::LETTER_INVITE => 'invitation_letter',
				Element_OphTrOperationbooking_Operation::LETTER_REMINDER_1 => 'reminder_letter',
				Element_OphTrOperationbooking_Operation::LETTER_REMINDER_2 => 'reminder_letter',
				Element_OphTrOperationbooking_Operation::LETTER_GP => 'gp_letter',
				Element_OphTrOperationbooking_Operation::LETTER_REMOVAL => false,
		);
		$letter_template = (isset($letter_templates[$letter_status])) ? $letter_templates[$letter_status] : false;

		if ($letter_template) {
			$firm = $operation->event->episode->firm;
			$site = $operation->site;
			$waitingListContact = $operation->waitingListContact;
			
			// Don't print GP letter if practice address is not defined
			if ($letter_status != Element_OphTrOperationbooking_Operation::LETTER_GP || ($patient->practice && $patient->practice->address)) {
				Yii::log("Printing letter: ".$letter_template, 'trace');

				call_user_func(array($this, 'print_'.$letter_template), $pdf_print, $operation);
				$this->print_admission_form($pdf_print, $operation);

				if ($auto_confirm) {
					$operation->confirmLetterPrinted();
				}
			} else {
				Yii::log("Patient has no practice address, printing letter supressed: ".$patient->id, 'trace');
			}
		} else if ($letter_status === null) {
			Yii::log("No letter is due: ".$patient->id, 'trace');
		} else {
			throw new CException('Undefined letter status');
		}
	}

	/**
	 * Get consultant name for letter
	 * @param Element_OphTrOperationbooking_Operation $operation
	 * @return string
	 */
	protected function getConsultantName($operation) {
		$firm = $operation->event->episode->firm;
		return $firm->getConsultantName();
	}

	/**
	 * Get letter from address for letter
	 * @param Element_OphTrOperationbooking_Operation $operation
	 * @return string
	 */
	protected function getFromAddress($operation) {
		$from_address = implode("\n",$operation->site->getLetterArray(false,false));
		$from_address .= "\nTel: " . $operation->site->telephone;
		if ($operation->site->fax) {
			$from_address .= "\nFax: " . $operation->site->fax;
		}
		return $from_address;
	}

	/**
	 * @param OEPDFPrint $pdf
	 * @param Element_OphTrOperationbooking_Operation $operation
	 */
	protected function print_admission_form($pdf, $operation) {
		$patient = $operation->event->episode->patient;
		$to_address = $patient->addressname . "\n" . implode("\n", $patient->correspondAddress->getLetterArray());
		$site = $operation->site;
		$firm = $operation->event->episode->firm;
		$body = $this->render('../letters/admission_form', array(
				'operation' => $operation,
				'site' => $site,
				'patient' => $patient,
				'firm' => $firm,
				'emergencyList' => false,
		), true);
		$letter = new OELetter();
		$letter->setBarcode('E:'.$operation->event_id);
		$letter->setFont('helvetica','10');
		$letter->addBody($body);
		$pdf->addLetter($letter);
	}

	/**
	 * @param OEPDFPrint $pdf
	 * @param Element_OphTrOperationbooking_Operation $operation
	 */
	protected function print_invitation_letter($pdf, $operation) {
		$patient = $operation->event->episode->patient;
		$to_address = $patient->addressname . "\n" . implode("\n", $patient->correspondAddress->getLetterArray());
		$body = $this->render('../letters/invitation_letter', array(
				'to' => $patient->salutationname,
				'consultantName' => $this->getConsultantName($operation),
				'overnightStay' => $operation->overnight_stay,
				'patient' => $patient,
				'changeContact' => $operation->waitingListContact,
		), true);
		$letter = new OELetter($to_address, $this->getFromAddress($operation), $body);
		$letter->setBarcode('E:'.$operation->event_id);
		$pdf->addLetter($letter);
	}

	/**
	 * @param OEPDFPrint $pdf
	 * @param Element_OphTrOperationbooking_Operation $operation
	 */
	protected function print_reminder_letter($pdf, $operation) {
		$patient = $operation->event->episode->patient;
		$to_address = $patient->addressname . "\n" . implode("\n", $patient->correspondAddress->getLetterArray());
		$body = $this->render('../letters/reminder_letter', array(
				'to' => $patient->salutationname,
				'consultantName' => $this->getConsultantName($operation),
				'overnightStay' => $operation->overnight_stay,
				'patient' => $patient,
				'changeContact' => $operation->waitingListContact,
		), true);
		$letter = new OELetter($to_address, $this->getFromAddress($operation), $body);
		$letter->setBarcode('E:'.$operation->event_id);
		$pdf->addLetter($letter);
	}

	/**
	 * @param OEPDFPrint $pdf
	 * @param Element_OphTrOperationbooking_Operation $operation
	 */
	protected function print_gp_letter($pdf, $operation) {

		// GP Letter
		$patient = $operation->event->episode->patient;
		if ($gp = $patient->gp) {
			$to_name = $gp->contact->fullname;
			$salutation = $gp->contact->salutationname;
		} else {
			$to_name = Gp::UNKNOWN_NAME;
			$salutation = Gp::UNKNOWN_SALUTATION;
		}
		if ($patient->practice && $practice_address = $patient->practice->address) {
			$to_address = $to_name . "\n" . implode("\n",$practice_address->getLetterArray());
		} else {
			throw new CException('Patient has no practice address');
		}
		$body = $this->render('../letters/gp_letter', array(
				'to' => $salutation,
				'patient' => $patient,
				'consultantName' => $this->getConsultantName($operation),
		), true);
		$letter = new OELetter($to_address, $this->getFromAddress($operation), $body);
		$letter->setBarcode('E:'.$operation->event_id);
		$pdf->addLetter($letter);

		// Patient letter
		$to_address = $patient->addressname . "\n" . implode("\n", $patient->correspondAddress->getLetterArray());
		$body = $this->render('../letters/gp_letter_patient', array(
				'to' => $patient->salutationname,
				'patient' => $patient,
				'consultantName' => $this->getConsultantName($operation),
		), true);
		$letter = new OELetter($to_address, $this->getFromAddress($operation), $body);
		$letter->setBarcode('E:'.$operation->event_id);
		$pdf->addLetter($letter);

	}

	public function actionConfirmPrinted() {
		Audit::add('waiting list','confirm',serialize($_POST));

		foreach ($_POST['operations'] as $operation_id) {
			if ($operation = Element_OphTrOperationbooking_Operation::Model()->findByPk($operation_id)) {
				if (Yii::app()->user->checkAccess('admin') and (isset($_POST['adminconfirmto'])) and ($_POST['adminconfirmto'] != 'OFF') and ($_POST['adminconfirmto'] != '')) {
					$operation->confirmLetterPrinted($_POST['adminconfirmto'], $_POST['adminconfirmdate']);
				} else {
					$operation->confirmLetterPrinted();
				}
			}
		}
	}
}
