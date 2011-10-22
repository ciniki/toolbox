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
// excel_id:			The excel spread ID that was uploaded to toolbox_excels table.
// rows:				A comma delimited list of rows to fetch from the database.
// 
// Returns
// -------
// <rows>
// 		<row id="44">
//			<cells>
//				<cell row="44" col="1" data="Firstname" />
//				<cell row="44" col="2" data="Middlename" />
//				<cell row="44" col="3" data="Lastname" />
//				<cell row="44" col="4" data="Suffix" />
//			</cells>
//		</row>
// </rows>
//
function ciniki_toolbox_excelGetRows($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		'rows'=>array('required'=>'yes', 'type'=>'idlist', 'blank'=>'no', 'errmsg'=>'No rows specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelGetRows', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Load the excel information
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuoteIDs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbRspQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashIDQuery2.php');
	$strsql = "SELECT business_id, name, source_name "
		. "FROM toolbox_excel "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'toolbox', 'excel');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['excel']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'101', 'msg'=>'A valid excel_id must be specified'));
	}
	$excel = $rc['excel'];

	//
	// Get the row information requested
	//
	$strsql = "SELECT row, col, data FROM toolbox_excel_data "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND row IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['rows']) . ")"
		. " ORDER BY row, col ";
	$rc = ciniki_core_dbHashIDQuery2($ciniki, $strsql, 'toolbox', 'rows', 'row', 'cells', 'cell');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'104', 'msg'=>'A valid excel_id must be specified', 'err'=>$rc['err']));
	}
	$rows = $rc['rows'];
	
	return array('stat'=>'ok', 'rows'=>$rows);
}
?>
