<?php
//
// Description
// -----------
// This function will parse a selection of rows from an upload.  For large excel files,
// the process is divided into sections to get around the memory (512M) and time limits (30seconds).
//
// Info
// ----
// Status:              alpha
//
// Arguments
// ---------
// api_key:
// auth_token:      
// tnid:         The tenant ID to create the excel file for.
// upload_id:           The information about the file uploaded via a file form field.
// start:               The starting row, 1 or greater.
// size:                The number of records to process, starting with the start row.
//
// Returns
// -------
// <upload id="19384992" />
//
function ciniki_toolbox_uploadXLSDone($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Spreadsheet'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
    $ac = ciniki_toolbox_checkAccess($ciniki, $args['tnid'], 'ciniki.toolbox.uploadXLSDone', $args['excel_id']);
    if( $ac['stat'] != 'ok' ) {
        return $ac;
    }

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

    $inputFileName = $ciniki['config']['core']['modules_dir'] . '/toolbox/uploads/excel_' . $args['excel_id'] . '.xls';
    unlink($inputFileName);

    //
    // Delete all blank rows
    //


    //
    // Update the information in the database
    //
    $strsql = "UPDATE ciniki_toolbox_excel SET status = 10 "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
    $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
        return $rc;
    }

    //
    // Commit the update
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'toolbox');

    return array('stat'=>'ok', 'id'=>$args['excel_id']);
}
?>
