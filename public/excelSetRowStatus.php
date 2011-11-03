<?php
//
// Description
// -----------
// This function will mark a row deleted in the excel data, but will not remove it from
// the list of matches.  
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
// rows					The row number to mark deleted in the toolbox_excel_data table.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_toolbox_excelSetRowStatus($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		'row'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		'status'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelSetRowStatus', $args['excel_id']);
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
	// Mark the row delete in the excel_data
	//
	if( $args['status'] == 'delete' ) {
		$strsql = "UPDATE toolbox_excel_data SET status = 2 "
			. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
			. "AND row = '" . ciniki_core_dbQuote($ciniki, $args['row']) . "'";
	} else if( $args['status'] == 'keep' ) {
		$strsql = "UPDATE toolbox_excel_data SET status = 3 "
			. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
			. "AND row = '" . ciniki_core_dbQuote($ciniki, $args['row']) . "'";
	} else {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'406', 'msg'=>'Invalid status specified'));
	}
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
