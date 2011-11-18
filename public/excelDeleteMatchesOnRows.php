<?php
//
// Description
// -----------
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:
// excel_id:			The excel spread ID that was uploaded to ciniki_toolbox_excels table.
// row1:				The row1 number to mark in the ciniki_toolbox_excel_matches table.
// row2:				The row2 number to mark in the ciniki_toolbox_excel_matches table.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_toolbox_excelDeleteMatchesOnRows($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		'row1'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		'row2'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelDeleteMatchesOnRows', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Turn off autocommit
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Mark any matches for this row as "handled"
	//
	$strsql = "UPDATE ciniki_toolbox_excel_matches SET match_status = 2, match_result = 21 "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND row1 = '" . ciniki_core_dbQuote($ciniki, $args['row1']) . "' "
		. "AND row2 = '" . ciniki_core_dbQuote($ciniki, $args['row2']) . "'";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
		return $rc;
	}

	//
	// Mark the row as "keep"
	//
	$strsql = "UPDATE ciniki_toolbox_excel_data SET status = 3 "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND row = '" . ciniki_core_dbQuote($ciniki, $args['row']) . "'";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
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
