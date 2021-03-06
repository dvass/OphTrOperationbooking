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

$(document).ready(function() {
	handleButton($('#waitingList-filter button[type="submit"]'),function(e) {
		e.preventDefault();

		if ($('#hos_num').val().length <1 || $('#hos_num').val().match(/^[0-9]+$/)) {
			$('#hos_num_error').hide();
		} else {
			$('#hos_num_error').show();
			enableButtons();
			return false;
		}

		$('#searchResults').html('<div id="waitingList" class="grid-view-waitinglist"><table><tbody><tr><th>Letters sent</th><th>Patient</th><th>Hospital number</th><th>Location</th><th>Procedure</th><th>Eye</th><th>Firm</th><th>Decision date</th><th>Priority</th><th>Book status (requires...)</th><th><input style="margin-top: 0.4em;" type="checkbox" id="checkall" value=""></th></tr><tr><td colspan="7" style="border: none; padding-top: 10px;"><img src="'+baseUrl+'/img/ajax-loader.gif" /> Searching, please wait ...</td></tr></tbody></table></div>');

		$.ajax({
			'url': baseUrl+'/OphTrOperationbooking/waitingList/search',
			'type': 'POST',
			'data': $('#waitingList-filter').serialize(),
			'success': function(data) {
				$('#searchResults').html(data);
				enableButtons();
			}
		});
	});

	handleButton($('#btn_print'),function() {
		print_items_from_selector('input[id^="operation"]:checked',false);
		enableButtons();
	});

	handleButton($('#btn_print_all'),function() {
		print_items_from_selector('input[id^="operation"]:enabled',true);
		enableButtons();
	});

	handleButton($('#btn_confirm_selected'),function(e) {
		var data = '';
		var operations = 0;
		data += "adminconfirmto=" + $('#adminconfirmto').val() + "&adminconfirmdate=" + $('#adminconfirmdate').val();
		$('input[id^="operation"]:checked').map(function() {
			if (data.length >0) {
				data += '&';
			}
			data += "operations[]=" + $(this).attr('id').replace(/operation/,'');
			operations += 1;
		});

		if (operations == 0) {
			alert('No items selected.');
		} else {
			disableButtons();

			$.ajax({
				url: baseUrl+'/OphTrOperationbooking/waitingList/confirmPrinted',
				type: "POST",
				data: data,
				success: function(html) {
					enableButtons();
					$('#waitingList-filter button[type="submit"]').click();
				}
			});
		}
		
		e.preventDefault();
	});

	$('#hos_num').focus();

	if ($('#subspecialty-id').length) {
		if ($('#subspecialty-id').val() != '') {
			var firm_id = $('#firm-id').val();
			$.ajax({
				url: baseUrl+'/OphTrOperationbooking/waitingList/filterFirms',
				type: "POST",
				data: "subspecialty_id="+$('#subspecialty-id').val(),
				success: function(data) {
					$('#firm-id').attr('disabled', false);
					$('#firm-id').html(data);
					$('#firm-id').val(firm_id);
					$('#waitingList-filter button[type="submit"]').click();
				}
			});
		} else {
			$('#waitingList-filter button[type="submit"]').click();
		}
	}

	$('#firm-id').bind('change',function() {
		$.ajax({
			url: baseUrl+'/OphTrOperationbooking/waitingList/filterSetFirm',
			type: "POST",
			data: "firm_id="+$('#firm-id').val(),
			success: function(data) {
			}
		});
	});

	$('#status').bind('change',function() {
		$.ajax({
			url: baseUrl+'/OphTrOperationbooking/waitingList/filterSetStatus',
			type: "POST",
			data: "status="+$('#status').val(),
			success: function(data) {
			}
		});
	});

	$('#site_id').bind('change',function() {
		$.ajax({
			url: baseUrl+'/OphTrOperationbooking/waitingList/filterSetSiteId',
			type: "POST",
			data: "site_id="+$('#site_id').val(),
			success: function(data) {
			}
		});
	});

	$('#hos_num').bind('keyup',function() {
		$.ajax({
			url: baseUrl+'/OphTrOperationbooking/waitingList/filterSetHosNum',
			type: "POST",
			data: "hos_num="+$('#hos_num').val(),
			success: function(data) {
			}
		});
	});
});

function print_items_from_selector(sel,all) {
	var operations = new Array();

	var nogp = 0;

	var operations = $(sel).map(function(i,n) {
		var no_gp = $(n).parent().parent().hasClass('waitinglistOrange') && $(n).parent().html().match(/>NO GP</)

		if (no_gp) nogp += 1;

		if (!no_gp) {
			return $(n).attr('id').replace(/operation/,'');
		}
	}).get();

	if (operations.length == 0) {
		if (nogp == 0) {
			alert("No items selected for printing.");
		} else {
			show_letter_warnings(nogp);
		}
	} else {
		show_letter_warnings(nogp);
		printIFrameUrl(baseUrl+'/OphTrOperationbooking/waitingList/printLetters', {'operations': operations, 'all': all});
	}
}

function show_letter_warnings(nogp) {
	var msg = '';

	if (nogp >0) {
		msg += nogp+" item"+(nogp == 1 ? '' : 's')+" could not be printed as the patient has no GP practice.";
	}

	if (msg.length >0) {
		alert(msg);
	}
}
