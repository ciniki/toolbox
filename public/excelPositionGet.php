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
// excel_id:			The excel spread ID that was uploaded to ciniki_toolbox_excels table.
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
function ciniki_toolbox_excelPositionGet($ciniki) {
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
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelPositionGet', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$strsql = "SELECT id, cur_review_row "
		. "FROM ciniki_toolbox_excel "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.toolbox', 'file');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( $rc['file']['cur_review_row'] >= 0 ) {
		return array('stat'=>'ok', 'cur_review_row'=>$rc['file']['cur_review_row']);
	}
	
	return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'407', 'msg'=>'Unable to get last position'));
}
?>
