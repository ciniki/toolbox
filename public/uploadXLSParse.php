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
function ciniki_toolbox_uploadXLSParse($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No upload specified'), 
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
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.uploadXLSParse', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Setup memory limits to be able to process large files
	//
	ini_set('memory_limit', '1024M');

	//
	// Open Excel parsing library
	//
	require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
	$inputFileType = 'Excel5';
	$inputFileName = $ciniki['config']['core']['modules_dir'] . '/toolbox/uploads/excel_' . $args['excel_id'] . '.xls';

	//
	// Turn off autocommit
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

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
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'89', 'msg'=>'Unable to understand spreadsheet data'));
	}


	//
	// Parse through the spreadsheet adding all the data
	//
	$type = 3;
	$last_row = 0;
	for($row = $args['start']; $row <= ($args['start'] + ($args['size']-1)) && $row <= $numRows; $row++) {
		$data_cols = 0;
		$strsql = "INSERT INTO toolbox_excel_data (excel_id, type, status, row, col, data) VALUES ";
		for($col = 0; $col < $numCols; $col++) {
			if( $col > 0 ) {
				$strsql .= ",";
			}
			$cellValue = $objWorksheet->getCellByColumnAndRow($col, $row)->getValue();
			$strsql .= "("
				. "'" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "', "
				// $type, $row and $col are integers defined in the code
				. "$type, 1, $row, $col+1, "
				. "'" . ciniki_core_dbQuote($ciniki, $cellValue) . "' "
				. ")";
			if( $cellValue != '' ) {
				$data_cols++;
			}
		}

		//
		// Only insert rows which have at least one column of data
		//
		if( $data_cols > 0 ) {
			$rc = ciniki_core_dbInsert($ciniki, $strsql, 'toolbox');
			if( $rc['stat'] != 'ok' ) {
				ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
				return $rc;
			}
			unset($rc);
		}
		$last_row = $row;
	}

	$rc = ciniki_core_dbTransactionCommit($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok', 'id'=>$args['excel_id'], 'last_row'=>$last_row, 'rows'=>$numRows);
}
?>
