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
// deleted:             (optional) Can be set to 'only' - only return deleted rows, or 'include' to include in main export.
//
// Returns
// -------
//
function ciniki_toolbox_downloadCSV($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Spreadsheet'), 
        'deleted'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Flags'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to business_id
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
    $ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.downloadCSV', $args['excel_id']);
    if( $ac['stat'] != 'ok' ) {
        return $ac;
    }

    //
    // Load the excel information
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $strsql = "SELECT business_id, name, source_name "
        . "FROM ciniki_toolbox_excel "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.toolbox', 'excel');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['excel']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.12', 'msg'=>'A valid excel_id must be specified'));
    }
    $excel = $rc['excel'];

    //
    // Open Excel parsing library
    //

    $strsql = "SELECT row, col, data FROM ciniki_toolbox_excel_data "
        . "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' ";
    if( isset($args['deleted']) && $args['deleted'] == 'only' ) {
        $strsql .= "AND (status = 2 OR row = 1) ";
    } elseif( isset($args['deleted']) && $args['deleted'] == 'include' ) {
        $strsql .= "AND (status = 1 OR status = 2 OR status = 3) ";
    } else {
        $strsql .= "AND (status = 1 OR status = 3) ";
    }
    $strsql .= "ORDER BY row, col ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $result_handle = $rc['handle'];

    // Keep track of new row counter, to avoid deleted rows.
    $result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
    $cur_excel_row = 0;
    $cur_excel_col = 0;
    $prev_excel_col = 0;
    $prev_db_row = $result['row']['row'];

    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
    header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT"); 
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Cache-Control: max-age=0');
    //header('Content-Type: application/vnd.ms-excel');
    //header('Content-Encoding: UTF-8');
    //header('Content-Type: text/csv; charset=UTF-8');
    // header('Content-Type: text/csv');
    header('Content-Type: application/vnd.ms-excel');
    $filename = preg_replace('/(\....)$/','_export', $excel['source_name']);
    header('Content-Disposition: attachment; filename=' . $filename . '.csv;');
    //echo "\xEF\xBB\xBF"; // UTF-8 BOM

    while( isset($result['row']) ) {
        //
        // Check if the new row counter needs advancement
        //
        if( $prev_db_row != $result['row']['row'] ) {
            print "\r\n";
            $cur_excel_row++;
            $cur_excel_col = 0;
            $prev_excel_col = 0;
        }
        $prev_db_row = $result['row']['row'];
        // Convert the string so french and other special characters are preserved
        $str = iconv("UTF-8", "UTF-16LE//IGNORE", "\"" . $result['row']['data'] . "\"");
        $len = strlen($str);
        $cur_excel_col = $result['row']['col'] - 1;
        // If necessary, fill the gaps in the column numbers, so everything lines up.
        if( $cur_excel_col > ($prev_excel_col + 1) ) {
            for($i=($prev_excel_col+1);$i<$cur_excel_col;$i++) {
                print ",";
            }
        }
        print "$str,";
        $prev_excel_col = $cur_excel_col;
        $cur_excel_col++;

        // Get the next cell
        $result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
    }
    print "\r\n";

    exit;

    return array('stat'=>'ok');
}
?>
