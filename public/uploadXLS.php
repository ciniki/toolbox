<?php
//
// Description
// -----------
// This function will accept a uploaded Excel file via POST
// and will parse the Excel file into the table ciniki_toolbox_excel_data.
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:		
// business_id:			The business ID to create the excel file for.
// uploadfile:			The information about the file uploaded via a file form field.
//
// Returns
// -------
// <upload id="19384992" />
//
function ciniki_toolbox_uploadXLS($ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'name'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'name'=>'Name'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

	//
	// Check access to business_id
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.uploadXLS', 0);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Setup memory limits to be able to process large files
	//
	ini_set("upload_max_filesize", "50M");
	ini_set('memory_limit', '4096M');


	if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'88', 'msg'=>'Upload failed, file too large.'));
	}

	if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['name'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'87', 'msg'=>'Upload failed, no file specified.', '_FILES'=>$_FILES));
	}

	if( $args['name'] == '' ) {
		$args['name'] = $_FILES['uploadfile']['name'];
	}
	

	//
	// Open Excel parsing library
	//
	require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
	$inputFileType = 'Excel5';
	$inputFileName = $_FILES['uploadfile']['tmp_name'];

	//
	// Turn off autocommit
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Create a new upload entry in the database
	//
	$strsql = "INSERT INTO ciniki_toolbox_excel (business_id, name, source_name, date_added, last_updated) VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. ", '" . ciniki_core_dbQuote($ciniki, $args['name']) . "' "
		. ", '" . ciniki_core_dbQuote($ciniki, $_FILES['uploadfile']['name']) . "' "
		. ", UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
		return $rc;
	}

	//
	// Grab the newly created ID 
	//
	$excel_id = $rc['insert_id'];

	//
	// Copy the uploaded file
	//
	$filename = $ciniki['config']['core']['modules_dir'] . '/toolbox/uploads/excel_' . $excel_id . '.xls';
	rename($_FILES['uploadfile']['tmp_name'], $filename);

	//
	// Update the information in the database
	//
	$strsql = "UPDATE ciniki_toolbox_excel SET status = 1, cache_name = '" . ciniki_core_dbQuote($ciniki, "excel_" . $excel_id) . "' "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $excel_id) . "' ";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
		return $rc;
	}

	//
	// Commit the changes
	//
	$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'toolbox');

	return array('stat'=>'ok', 'id'=>$excel_id);
}
?>
