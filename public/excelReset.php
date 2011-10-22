<?php
//
// Description
// -----------
// This function will remove all matches, and reset all deleted rows.
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:
// excel_id:			The excel spread ID that was uploaded to toolbox_excels table.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_toolbox_excelReset($ciniki) {
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
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelReset', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Turn off autocommit
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbDelete.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Remove the deleted/keep flags from all rows
	//
	$strsql = "UPDATE toolbox_excel_data SET status = 1 "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
		return $rc;
	}

	//
	// Delete all matches for a file
	//
	$strsql = "DELETE FROM toolbox_excel_matches "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
		return $rc;
	}

	//
	// Update the position counter
	//
	$strsql = "UPDATE toolbox_excel SET cur_review_row = 0 "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
		return $rc;
	}

	//
	// Commit the changes
	//
	$rc = ciniki_core_dbTransactionCommit($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok');
}
?>
