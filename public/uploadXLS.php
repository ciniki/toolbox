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
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'name'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'errmsg'=>'No name specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.uploadXLS', 0);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Setup memory limits to be able to process large files
	//
	ini_set("upload_max_filesize", "10M");
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
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'toolbox');
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
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
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

	return array('stat'=>'ok', 'id'=>$excel_id);
}
?>
