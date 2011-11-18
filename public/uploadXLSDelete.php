<?php
//
// Description
// -----------
// This function will parse a selection of rows from an upload.  For large excel files,
// the process is divided into sections to get around the memory (512M) and time limits (30seconds).
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
// upload_id:			The information about the file uploaded via a file form field.
// start:				The starting row, 1 or greater.
// size:				The number of records to process, starting with the start row.
//
// Returns
// -------
// <upload id="19384992" />
//
function ciniki_toolbox_uploadXLSDelete($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'upload_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No upload specified'), 
		'start'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No start specified'), 
		'size'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No size specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

	//
	// Check access to business_id
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.uploadXLSDelete', 0);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Setup memory limits to be able to process large files
	//
	ini_set("upload_max_filesize", "10M");
	ini_set('memory_limit', '1024M');


	if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'92', 'msg'=>'Upload failed, file too large.'));
	}

	if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['name'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'91', 'msg'=>'Upload failed, no file specified.', '_FILES'=>$_FILES));
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
	// Load the excel information from the database
	//
	$strsql = "SELECT cache_name, status FROM

	//
	// Parse the specified selection of rows
	//





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

	/**  Define a Read Filter class implementing PHPExcel_Reader_IReadFilter  */ 
	try {
		class MyReadFilter implements PHPExcel_Reader_IReadFilter 
		{ 
			// Defaults for start and size
			public $_start = 1;
			public $_size = 1000;
			public function readCell($column, $row, $worksheetName = '') { 
				if( $row >= $this->_start && $row < ($this->_start + $this->_size)) {
					return true;
				}
				return false; 
			} 
		} 
		/**  Create an Instance of our Read Filter  **/ 
		$filterSubset = new MyReadFilter(); 
		$filterSubset->_start = $args['start'];
		$filterSubset->_size = $args['size'];

		/** Create a new Reader of the type defined in $inputFileType **/
		$objReader = PHPExcel_IOFactory::createReader($inputFileType);
		/**  Tell the Reader that we want to use the Read Filter that we've Instantiated  **/ 
		$objReader->setReadFilter($filterSubset); 
		/**  Load only the rows and columns that match our filter from $inputFileName to a PHPExcel Object  **/
		$objPHPExcel = $objReader->load($inputFileName);

		$objWorksheet = $objPHPExcel->getActiveSheet();
		$numRows = $objWorksheet->getHighestRow(); // e.g. 10
		$highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
		$numCols = PHPExcel_Cell::columnIndexFromString($highestColumn); 
	} catch(Exception $e) {
		ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'94', 'msg'=>'Unable to understand spreadsheet data'));
	}


	//
	// Parse through the spreadsheet adding all the data
	//
	$type = 3;
	$last_row = 0;
	for($row = $args['start']; $row <= ($args['start'] + $args['size']) && $row <= $numRows; $row++) {
		$strsql = "INSERT INTO ciniki_toolbox_excel_data (excel_id, type, status, row, col, data) VALUES ";
		for($col = 0; $col < $numCols; $col++) {
			if( $col > 0 ) {
				$strsql .= ",";
			}
			$strsql .= "("
				. "'" . ciniki_core_dbQuote($ciniki, $excel_id) . "', "
				// $type, $row and $col are integers defined in the code
				. "$type, 1, $row, $col+1, "
				. "'" . ciniki_core_dbQuote($ciniki, $objWorksheet->getCellByColumnAndRow($col, $row)->getValue() ) . "' "
				. ")";
		}

		$rc = ciniki_core_dbInsert($ciniki, $strsql, 'toolbox');
		if( $rc['stat'] != 'ok' ) {
			ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
			return $rc;
		}
		unset($rc);
		$last_row = $row;
	}

	$rc = ciniki_core_dbTransactionCommit($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok', 'id'=>$args['excel_id'], 'last_row'=>$last_row, 'rows'=>$num_rows);
}
?>
