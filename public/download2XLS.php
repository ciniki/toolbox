<?php
//
// Description
// -----------
// This function will generate an Excel file from the data in ciniki_toolbox_excel_data;
//
// Info
// ----
// Status:              alpha
//
// Arguments
// ---------
// api_key:
// auth_token:      
// excel_id:            The excel ID from the table ciniki_toolbox_excel;
//
// Returns
// -------
//
function ciniki_toolbox_download2XLS($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Spreadsheet'), 
        'status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Status'), 
        'status_list'=>array('required'=>'no', 'blank'=>'no', 'type'=>'idlist', 'name'=>'Status'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to tnid
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
    $ac = ciniki_toolbox_checkAccess($ciniki, $args['tnid'], 'ciniki.toolbox.download2XLS', $args['excel_id']);
    if( $ac['stat'] != 'ok' ) {
        return $ac;
    }

    //
    // Load the excel information
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    $strsql = "SELECT tnid, name, source_name "
        . "FROM ciniki_toolbox_excel "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.toolbox', 'excel');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['excel']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.11', 'msg'=>'A valid excel_id must be specified'));
    }
    $excel = $rc['excel'];
    
    //
    // Increase memory limits to be able to create entire file
    //
    // error_log("Memory limit: " . ini_get('memory_limi'));
    // error_log("Set limit: " . ini_set('memory_limit', '10024M'));
    ini_set('memory_limit', '8192M');
    // error_log("Memory limit: " . ini_get('memory_limi'));

    //
    // Open Excel parsing library
    //
    require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
    $objPHPExcel = new PHPExcel();

    $strsql = "SELECT row, col, data FROM ciniki_toolbox_excel_data "
        . "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "";
    if( isset($args['status']) && $args['status'] > 0 ) {
        $strsql .= "AND status = '" . ciniki_core_dbQuote($ciniki, $args['status']) . "' ";
    } elseif( isset($args['status_list']) ) {
        $strsql .= "AND status IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['status_list']) . ") ";
    }

    $strsql .= "ORDER BY row, col ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $result_handle = $rc['handle'];

    $objPHPExcelWorksheet = $objPHPExcel->setActiveSheetIndex(0);
    // Keep track of new row counter, to avoid deleted rows.
    $result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
    $cur_excel_row = 1;
    $prev_db_row = $result['row']['row'];

    while( isset($result['row']) ) {
        //
        // Check if the new row counter needs advancement
        //
        if( $prev_db_row != $result['row']['row'] ) {
            $cur_excel_row++;
        }
        $prev_db_row = $result['row']['row'];
        if( $result['row']['data'] != '' ) {
            $objPHPExcelWorksheet->setCellValueByColumnAndRow(($result['row']['col'])-1, $cur_excel_row, $result['row']['data'], false);
        }
        $result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
    }

    //
    // Redirect output to a client’s web browser (Excel5)
    //
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="export.xls"');
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    exit;

    return array('stat'=>'ok');
}
?>
