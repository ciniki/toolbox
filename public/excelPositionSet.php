<?php
//
// Description
// -----------
// This function will fetch a row from the database.
//
// Info
// ----
// Status:              alpha
//
// Arguments
// ---------
// api_key:
// auth_token:
// excel_id:            The excel spread ID that was uploaded to ciniki_toolbox_excels table.
// rows:                A comma delimited list of rows to fetch from the database.
// 
// Returns
// -------
// <rows>
//      <row id="44">
//          <cells>
//              <cell row="44" col="1" data="Firstname" />
//              <cell row="44" col="2" data="Middlename" />
//              <cell row="44" col="3" data="Lastname" />
//              <cell row="44" col="4" data="Suffix" />
//          </cells>
//      </row>
// </rows>
//
function ciniki_toolbox_excelPositionSet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Spreadsheet'), 
        'row'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Row'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to tnid
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
    $ac = ciniki_toolbox_checkAccess($ciniki, $args['tnid'], 'ciniki.toolbox.excelPositionSet', $args['excel_id']);
    if( $ac['stat'] != 'ok' ) {
        return $ac;
    }

    //
    // Update the current position
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
    $strsql = "UPDATE ciniki_toolbox_excel SET cur_review_row = '" . $args['row'] . "' "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
    $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.toolbox');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.toolbox');
        return $rc;
    }
    
    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'toolbox');

    return array('stat'=>'ok');
}
?>
