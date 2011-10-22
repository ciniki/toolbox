<?php
//
// Description
// -----------
// This function will fetch a row from the database.
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The business ID to get the excel files uploaded to the toolbox.
// 
// Returns
// -------
// <files>
// 		<excel id="3" name="Temp.xls" source_name="Temp.xls" date_added="2011-01-08 12:59:00" />
// </files>
//
function ciniki_toolbox_excelGetList($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelGetList', 0);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Load the excel information
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbRspQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashIDQuery2.php');
	$strsql = "SELECT id, name, source_name, cur_review_row, date_added "
		. "FROM toolbox_excel "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND status = 10 ";
	return ciniki_core_dbRspQuery($ciniki, $strsql, 'toolbox', 'files', 'excel', array('stat'=>'ok', 'files'=>array()));
}
?>
