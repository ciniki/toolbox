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
function ciniki_toolbox_excelGetRows($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Spreadsheet'), 
        'rows'=>array('required'=>'yes', 'type'=>'idlist', 'blank'=>'no', 'name'=>'Rows'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to business_id
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'toolbox', 'private', 'checkAccess');
    $ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelGetRows', $args['excel_id']);
    if( $ac['stat'] != 'ok' ) {
        return $ac;
    }

    //
    // Load the excel information
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbRspQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery2');
    $strsql = "SELECT business_id, name, source_name "
        . "FROM ciniki_toolbox_excel "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.toolbox', 'excel');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['excel']) ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'101', 'msg'=>'A valid excel_id must be specified'));
    }
    $excel = $rc['excel'];

    //
    // Get the row information requested
    //
    $strsql = "SELECT row, col, data FROM ciniki_toolbox_excel_data "
        . "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
        . "AND row IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['rows']) . ")"
        . " ORDER BY row, col ";
    $rc = ciniki_core_dbHashIDQuery2($ciniki, $strsql, 'ciniki.toolbox', 'rows', 'row', 'cells', 'cell');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'104', 'msg'=>'A valid excel_id must be specified', 'err'=>$rc['err']));
    }
    $rows = $rc['rows'];
    
    return array('stat'=>'ok', 'rows'=>$rows);
}
?>
