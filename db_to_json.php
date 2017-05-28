<?php
/**
 * db_to_json.php : Get search results from DB and send them off in JSON for Google Charts
 * 2017.05.13
 *
 * @license       https://opensource.org/licenses/GPL-3.0 GNU GPL-3.0
 * @author        Philip Chen
 */

require_once("mydb.php");

$objMyDB = new MySQLDB(MYDB_HOST, MYDB_USER, MYDB_PASSWD, MYDB_DBNAME);

# http://html.net/db_to_json.php?pd=32568807896&lkbk=14

$aeProdId = $_GET["pd"];
$nDays = $_GET["lkbk"];
/*
$aeProdId = 32568807896;
$nDays = 5;
*/

$aProdAttr  = array();
$isContinue = true;

$gChartData = array();
$gChartData['cols'] = array(
    array('id' => '', 'label' => 'Date', 'type' => 'string'),
);

$sqlCmd = "SELECT sku_id, sku_prod_attr_id, attributes FROM stock_tbl ";
$sqlCmd .= "WHERE prod_id = ( SELECT prod_id FROM product_tbl WHERE ae_prod_id = '" . $aeProdId . "') ";
$sqlCmd .= "ORDER BY sku_id ASC; ";

$queryResults = $objMyDB->query($sqlCmd);
if ($queryResults->num_rows > 0) {
    $i = 0;
    while ($row = $queryResults->fetch_assoc()) {
        $nSkuId = (int) $row["sku_id"];
        $aeSkuAttrId = $row["sku_prod_attr_id"];
        $svAttribute = $row["attributes"];
        if ($svAttribute) {
            $aProdAttr[$i] = array($nSkuId, $svAttribute);
        } else {
            $aProdAttr[$i] = array($nSkuId, $aeSkuAttrId);
        }
        array_push( $gChartData['cols'], array('id' => '', 'label' => 'Price[' . $aProdAttr[$i][1] . ']', 'type' => 'number') );
        array_push( $gChartData['cols'], array('id' => '', 'label' => 'Avail[' . $aProdAttr[$i][1] . ']', 'type' => 'number') );
        $i++;
    }

} else {
    $isContinue = false;

    # Write message into log
    $svErrMsg = "Error: Product [" . $aeProdId . "] did NOT found";
    log_msg_in_DB($objMyDB, 3, $svErrMsg);
}

$nOptions = sizeof($aProdAttr);
# echo $nOptions . "<br />";

# DATE_FORMAT(check_time, '%b %e') = May 1
$sqlCmd = "SELECT DATE_FORMAT(check_time, '%b %e'), sku_id, price, avail_no ";
$sqlCmd .= "FROM stockcheck_tbl WHERE sku_id IN ( ";
$sqlCmd .= "SELECT sku_id FROM stock_tbl WHERE prod_id = ( ";
$sqlCmd .= "SELECT prod_id FROM product_tbl WHERE ae_prod_id = '" . $aeProdId . "' ) ";
$sqlCmd .= ") AND check_time BETWEEN NOW() - INTERVAL " . (string)$nDays . " DAY AND NOW() ";
$sqlCmd .= "ORDER BY check_time ASC, sku_id ASC; ";

$allRows = array();
$aEpoch = array('v' => (string) "2000-01-01");
$queryResults = $objMyDB->query($sqlCmd);
if ($queryResults->num_rows > 0) {

    # Do NOT reset $j for each loop
    $j = 0;
    $inOneDay = array( $aEpoch );
    while ($row = $queryResults->fetch_assoc()) {
        $dateRec = $row["DATE_FORMAT(check_time, '%b %e')"];

        if ( strcmp($inOneDay[0]['v'], $dateRec) ) {
            # It's a new day!
            if ( $j != 0 ) {
                # We has some options has no data in previous day
                while ( $j < $nOptions ) {
                    # the product option no longer exist, fill in 0s
                    array_push( $inOneDay, array('v' => (float) 0), array('v' => (int) 0) );
                    $j++ ;
                }
                # save the previous day's data
                $oneRow = array('c' => $inOneDay);
                array_push($allRows, $oneRow);

                # empty the array for another day
                $inOneDay = array( $aEpoch );
            }

            # mark the new day
            $inOneDay[0] = array('v' => (string) $dateRec );
            $j = 0;
        }

        $nSkuId = (int) $row["sku_id"];
        $svPrice = (float) $row["price"];
        $nAvailNo = (int) $row["avail_no"];

        while ( $j < $nOptions ) {
            if ( $aProdAttr[$j][0] == $nSkuId ) {
                array_push( $inOneDay, array('v' => (float) $svPrice), array('v' => (int) $nAvailNo) );
                $j++ ;
                break 1; # exit while(nOption) loop
            } else {
                # the product option no longer exist, fill in 0s
                array_push( $inOneDay, array('v' => (float) 0), array('v' => (int) 0) );
                $j++ ;
            }
        }

        if ($j == $nOptions) {
            #echo "save the day when reach the last option";
            $oneRow = array('c' => $inOneDay);
            array_push($allRows, $oneRow);
            $j=0;
            $inOneDay = array( $aEpoch );
        }
    }
} else {
    $isContinue = false;

    # Write message into log
    $svErrMsg = "Error: No History Found for Product [" . $aeProdId . "]";
    log_msg_in_DB($objMyDB, 3, $svErrMsg);
}

$gChartData['rows'] = $allRows;

header('Content-type: application/json');

$jsonGoogleCharts = json_encode($gChartData, true);
echo $jsonGoogleCharts;


?>