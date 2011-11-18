<?php
//
// Description
// -----------
// This function will search the ciniki_toolbox_excel_data table for rows which are duplicates,
// or contain duplicate information
//
// Info
// ----
// Status: 				alpha
//
// Arguments
// ---------
// excel_id: 			The upload excel file ID form the ciniki_toolbox_excel table.
// columns:				Use the comma delimited list of column numbers to determine as the columns which must match.
//
// Returns
// -------
// <rsp stat="ok" matches="0" duplicates="0" />
//
function ciniki_toolbox_excelFindMatches($ciniki) {
	//
	// Find all the required and optional arguments
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'excel_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No spreadsheet specified'), 
		'columns'=>array('required'=>'yes', 'type'=>'idlist', 'blank'=>'no', 'errmsg'=>'No row specified'), 
		'match_blank'=>array('required'=>'no', 'default'=>'no', 'blank'=>'no', 'errmsg'=>'No match_blank specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id, the toolbox module is turned on, the user has access, 
	// and the excel_id belongs to the business.
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/toolbox/private/checkAccess.php');
	$ac = ciniki_toolbox_checkAccess($ciniki, $args['business_id'], 'ciniki.toolbox.excelFindMatches', $args['excel_id']);
	if( $ac['stat'] != 'ok' ) {
		return $ac;
	}

	//
	// Get the number of columns to search
	//
	$num_cols = count($args['columns']);
	if( $num_cols < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'90', 'msg'=>'No columns specified.'));
	}

	//
	// Build the SQL query to find potential matches, not all rows returned will be matches.
	//
	if( $num_cols == 1 ) {
		$strsql = "SELECT y.row as m1_row, y.col as m1_col, y.data as m1_data "
			. "FROM ( "
				. "SELECT excel_id, col, data "
				. "FROM ciniki_toolbox_excel_data "
				. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
				. "AND col = '" . ciniki_core_dbQuote($ciniki, $args['columns'][0]) . "' "
				. "AND data != '' "
				. "GROUP BY excel_id, col, data "
				. "HAVING COUNT(row) > 1 "
				. "ORDER BY excel_id, row, col "
			. ") x, ciniki_toolbox_excel_data y "
			. "WHERE y.excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
			. "AND y.col = '" . ciniki_core_dbQuote($ciniki, $args['columns'][0]) . "' "
			. "AND x.data = y.data "
			. "ORDER BY y.data, y.row "
			. "";
	} else {
		$strsql = "SELECT * "
			. "FROM ";
		$strsql_where = "";
		$strsql_order = "ORDER BY ";
		$comma = '';	
		$joiner = "WHERE ";
		for($i=0;$i<$num_cols;$i++) {
			$t = $i+1;
			$strsql .= "$comma (SELECT y.row as m{$t}_row, y.col as m{$t}_col, y.data as m{$t}_data "
				. "FROM ( "
					. "SELECT excel_id, col, data "
					. "FROM ciniki_toolbox_excel_data "
					. "WHERE excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
					. "AND col = '" . ciniki_core_dbQuote($ciniki, $args['columns'][$i]) . "' "
					. "AND data != '' "
					. "GROUP BY excel_id, col, data "
					. "HAVING COUNT(row) > 1 "
					. "ORDER BY excel_id, row, col "
				. ") x, ciniki_toolbox_excel_data y "
				. "WHERE y.excel_id = '" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
				. "AND y.col = '" . ciniki_core_dbQuote($ciniki, $args['columns'][$i]) . "' "
				. "AND x.data = y.data "
				. "ORDER BY y.data "
				. "";
			$strsql .= ") m" . ($i+1) . " ";
			if( ($i+1) < $num_cols ) {
				$strsql_where .= $joiner . "m" . ($i+1) . "_row = m" . ($i+2) . "_row ";
				$joiner = 'AND ';
			}
			$strsql_order .= $comma . " m" . ($i+1) . "_data ";
			$comma = ',';
		}
		$strsql .= $strsql_where . $strsql_order . ", m1_row";
	}

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
	// Query for the duplicates, and update the ciniki_toolbox_excel_matches table
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuery.php');
	$rc = ciniki_core_dbQuery($ciniki, $strsql, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	$result_handle = $rc['handle'];
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbFetchHashRow.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	$num_matches = 0;
	$num_dups = 0;
	$prev_result = ciniki_core_dbFetchHashRow($ciniki, $result_handle);
	$matches = array();
	while( $result = ciniki_core_dbFetchHashRow($ciniki, $result_handle) ) {
		if( !isset($result['row']) ) {
			break;
		}

		//
		// Check for any columns which do not match, and ignore
		//
		$prev_matches = 0;
		for($i=0;$i<$num_cols;$i++) {
			$t=$i+1;
			$result['row']["m{$t}_data"] = strtolower($result['row']["m{$t}_data"]);
			if( $prev_result['row']["m{$t}_data"] == $result['row']["m{$t}_data"] ) {
				//print "match: " . $prev_result['row']["m{$t}_row"] . ':' . $prev_result['row']["m{$t}_data"] . " -- " . $result['row']["m{$t}_row"] . ':' . $result['row']["m{$t}_data"] . "\n";
				$prev_matches++;
			}
		}
		//print "M: $prev_matches - $num_cols\n";
		if( $prev_matches < $num_cols ) {
			$prev_result = $result;
			// Clear the stack
			unset($matches);
			$matches = array();
			continue;
		}

		//
		// If all the columns matched, then this is a match to the previous returned result
		//

		//
		// Push the previous result row number onto the stack
		//
		array_push($matches, $prev_result);

		//
		// Go through the stack and add a match for each row to the current result row.  This will
		// add a row in ciniki_toolbox_excel_matches for each combination of matches.  if 4 rows match
		// on the same fields, then there will be 6 entries added.
		//
		foreach($matches as $row) {
			//
			// Insert a match for each column
			//
			$dup = 0;
			for($i=0;$i<$num_cols;$i++) {
				$t=$i+1;
				$strsql = "INSERT INTO ciniki_toolbox_excel_matches (excel_id, row1, col1, row2, col2, match_status, match_result)"
					. " VALUES "
					. "('" . ciniki_core_dbQuote($ciniki, $args['excel_id']) . "' "
					. ", '" . ciniki_core_dbQuote($ciniki, $row['row']["m{$t}_row"]) . "' "
					. ", '" . ciniki_core_dbQuote($ciniki, $row['row']["m{$t}_col"]) . "' "
					. ", '" . ciniki_core_dbQuote($ciniki, $result['row']["m{$t}_row"]) . "' "
					. ", '" . ciniki_core_dbQuote($ciniki, $result['row']["m{$t}_col"]) . "' "
					. ", 1, 0)";
				$rc = ciniki_core_dbInsert($ciniki, $strsql, 'toolbox');
				//
				// Only return an error for a non duplicate row entry.  If the match was already entered, we don't need to re-add.
				//
				if( $rc['stat'] != 'ok' && ($rc['err']['dberrno'] != 1062 && $rc['err']['dberrno'] != 1022 ) ) {
					ciniki_core_dbTransactionRollback($ciniki, 'toolbox');
					return $rc;
				}
				//
				// Duplicate 
				//
				if( $rc['stat'] != 'ok' ) { 
					$dup = 1;
				}
			}
			
			//
			// Keep track of the duplicate records based on duplicate key errors.  This 
			// will be the number of column duplicates, not row duplicates.  If a match
			// exists but on a different column, it will NOT be considered a duplicate.
			// 
			if( $dup == 0 ) {
				$num_matches++;
			} else {
				$num_dups++;
			}
		}

		$prev_result = $result;
	}

	//
	// Commit the updates to the database
	//
	$rc = ciniki_core_dbTransactionCommit($ciniki, 'toolbox');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok', 'matches'=>$num_matches, 'duplicates'=>$num_dups);
}
?>
