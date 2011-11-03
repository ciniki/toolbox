<?php
//
// Description
// -----------
// This function will generate an Excel file from the data in toolbox_excel_data;
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// api_key:
// auth_token:		
// excel_id:			The excel ID from the table toolbox_excel;
//
// Returns
// -------
//
function ciniki_toolbox_downloadXLS($ciniki) {
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
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.downloadXLS', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Load the excel information
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$strsql = "SELECT business_id, name, source_name "
		. "FROM toolbox_excel "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'toolbox', 'excel');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['excel']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'117', 'msg'=>'A valid excel_id must be specified'));
	}
	$excel = $rc['excel'];

	//
	// Open Excel parsing library
	//
//	require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
//	$objPHPExcel = new PHPExcel();

	$strsql = "SELECT row, col, data FROM toolbox_excel_data "
		. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
		. "AND status = 1 "
		. "ORDER BY row, col ";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbFetchHashRow.php');
	$rc = ciniki_core_dbQuery($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$result_handle = $rc['handle'];

//	$objPHPExcelWorksheet = $objPHPExcel->setActiveSheetIndex(0);
	// Keep track of new row counter, to avoid deleted rows.
	$result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
	$cur_excel_row = 0;
	$prev_db_row = $result['row']['row'];

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
	header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT"); 
	header('Cache-Control: no-cache, must-revalidate');
	header('Pragma: no-cache');
	header('Content-Type: application/vnd.ms-excel');
	header('Content-Disposition: attachment;filename="export.xls"');
	header('Cache-Control: max-age=0');

	//
	// Excel streaming code found at: http://px.sklar.com/code.html/id=488
	//

	echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);

	while( isset($result['row']) ) {
		//
		// Check if the new row counter needs advancement
		//
		if( $prev_db_row != $result['row']['row'] ) {
			$cur_excel_row++;
		}
		$prev_db_row = $result['row']['row'];
		if( $result['row']['data'] != '' ) {
			$str = iconv("UTF-8", "UTF-16LE//IGNORE", $result['row']['data']);
			$len = strlen($str);
			// $str = $result['row']['data'];
			echo pack("ssssss", 0x204, 8 + $len, $cur_excel_row, ($result['row']['col'] - 1), 0x0, $len);
			echo $str;
			// echo chr(255).chr(254).
//			$objPHPExcelWorksheet->setCellValueByColumnAndRow(($result['row']['col'])-1, $cur_excel_row, $result['row']['data'], false);
		}
		$result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
	}

	//
	// End the excel file
	//
	echo pack("ss", 0x0A, 0x00);

	//
	// Redirect output to a client’s web browser (Excel5)
	//
//	header('Content-Type: application/vnd.ms-excel');
//	header('Content-Disposition: attachment;filename="export.xls"');
//	header('Cache-Control: max-age=0');

//	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
//	$objWriter->save('php://output');
	exit;

	return array('stat'=>'ok');
}
?>
