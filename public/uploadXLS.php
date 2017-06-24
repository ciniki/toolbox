<?php
//
// Description
// -----------
// This function will accept a uploaded Excel file via POST
// and will parse the Excel file into the table ciniki_toolbox_excel_data.
//
// Info
// ----
// Status:              alpha
//
// Arguments
// ---------
// api_key:
// auth_token:      
// business_id:         The business ID to create the excel file for.
// uploadfile:          The information about the file uploaded via a file form field.
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
//    ini_set("upload_max_filesize", "50M");
    ini_set('memory_limit', '4096M');


    if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.20', 'msg'=>'Upload failed, file too large.'));
    }

    if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['name'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.21', 'msg'=>'Upload failed, no file specified.', '_FILES'=>$_FILES));
    }

    if( $args['name'] == '' ) {
        $args['name'] = $_FILES['uploadfile']['name'];
    }
    

    //
    // Open Excel parsing library
    //
    require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
//    $inputFileType = 'Excel5';
    $inputFileName = $_FILES['uploadfile']['tmp_name'];
    $inputFileType = PHPExcel_IOFactory::identify($inputFileName);

    //
    // Turn off autocommit
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
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
    $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
        return $rc;
    }

    //
    // Setup memory limits to be able to process large files
    //
/*
    $args['start'] = 1;
    $args['size'] = 10000;
//    error_log("Parsing chunk: " . $args['start'] . ' - ' . $args['size']);
    //
    // Open Excel parsing library
    //
//    $inputFileName = $ciniki['config']['core']['modules_dir'] . '/toolbox/uploads/excel_' . $args['excel_id'] . '.xls';
    $inputFileName = $filename;
//    $inputFileType = PHPExcel_IOFactory::identify($inputFileName);

    //  Define a Read Filter class implementing PHPExcel_Reader_IReadFilter  
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
        //  Create an Instance of our Read Filter  
        $filterSubset = new MyReadFilter(); 
        $filterSubset->_start = $args['start'];
        $filterSubset->_size = $args['size'];

        // Create a new Reader of the type defined in $inputFileType 
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objReader->setReadFilter($filterSubset); 
        // Only read in the data, don't care about formatting
//        $objReader->setReadDataOnly(true);
        //  Load only the rows and columns that match our filter from $inputFileName to a PHPExcel Object 
        $objPHPExcel = $objReader->load($inputFileName);

        $objWorksheet = $objPHPExcel->getActiveSheet();
        $numRows = $objWorksheet->getHighestRow(); // e.g. 10
        $highestColumn = $objWorksheet->getHighestColumn(); // e.g 'F'
        $numCols = PHPExcel_Cell::columnIndexFromString($highestColumn); 
    } catch(Exception $e) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.22', 'msg'=>'Unable to understand spreadsheet data'));
    }


    //
    // Parse through the spreadsheet adding all the data
    //
    $type = 3;
    $last_row = 0;
    $count = 0;
    for($row = $args['start']; $row <= ($args['start'] + ($args['size']-1)) && $row <= $numRows; $row++) {
        $data_cols = 0;
        $strsql = "INSERT INTO ciniki_toolbox_excel_data (excel_id, type, status, row, col, data) VALUES ";
        for($col = 0; $col < $numCols; $col++) {
            if( $col > 0 ) {
                $strsql .= ",";
            }
            $cellValue = $objWorksheet->getCellByColumnAndRow($col, $row)->getValue();
            $strsql .= "("
                . "'" . ciniki_core_dbQuote($ciniki, $excel_id) . "', "
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
            $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.toolbox');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
                return $rc;
            }
            unset($rc);
        }
        $last_row = $row;
        $count++;
    }
*/
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
