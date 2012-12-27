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
// row:					The row number of the cell to update.
// col:					The col number of the cell to update.
// data:				The string to put into the contents of the cell.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_toolbox_excelUpdateCell($ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		'row'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		'col'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No column specified'), 
		'data'=>array('required'=>'yes', 'blank'=>'yes', 'errmsg'=>'No data specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelUpdateCell', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Don't need the transaction here, it's only one update.
	//

	//
	// Mark the row delete in the excel_data
	//
	$strsql = "UPDATE ciniki_toolbox_excel_data SET data = '" . ciniki_core_dbQuote($ciniki, $args['data']) . "' "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND row = '" . ciniki_core_dbQuote($ciniki, $args['row']) . "' "
		. "AND col = '" . ciniki_core_dbQuote($ciniki, $args['col']) . "'";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'toolbox');

	return array('stat'=>'ok');
}
?>
