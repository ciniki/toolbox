<?php
//
// Description
// -----------
// This function will return info about the file and the stats
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:		
// business_id:			The business ID the excel file is connected to.
// excel_id:			The excel ID from the table ciniki_toolbox_excel.
//
// Returns
// -------
// <stats rows=0 matches=0 reviewed=0 deleted=0 />
//
function ciniki_toolbox_excelGetStats($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelGetStats', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	$stats = array(
		'rows'=>0,
		'matches'=>0,
		'reviewed'=>0,
		'deleted'=>0,
		);

	//
	// Get the number of rows in data
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbCount.php');
	$strsql = "SELECT status, COUNT(DISTINCT row) "
		. "FROM ciniki_toolbox_excel_data "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "GROUP BY status ";
	$rc = ciniki_core_dbCount($ciniki, $strsql, 'toolbox', 'excel');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	foreach($rc['excel'] as $status => $count) {
		$stats['rows'] += $count;
		if( $status == 2 ) {
			$stats['deleted'] = $count;
		}
	}

	//
	// Get the number of rows in matches
	//
	$strsql = "SELECT match_status, COUNT(DISTINCT row1) "
		. "FROM ciniki_toolbox_excel_matches "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "GROUP BY match_status ";
	$rc = ciniki_core_dbCount($ciniki, $strsql, 'toolbox', 'excel');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	foreach($rc['excel'] as $status => $count) {
		$stats['matches'] += $count;
		if( $status > 1 ) {
			$stats['reviewed'] = $count;
		}
	}

	return array('stat'=>'ok', 'stats'=>$stats);
}
?>
