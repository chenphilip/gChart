<?php
/**
 * readLinks.php : Go through a list of links in CVS file
 * 2017.05.21
 *
 * @license       https://opensource.org/licenses/GPL-3.0 GNU GPL-3.0
 * @author        Philip Chen
 */

function fetchUpdate($arrayData)
{

    $url = "https://mk.vancess.ca/updateStockInfo.php";
    $jsonURL = json_encode($arrayData);

    $curlHandle = curl_init($url);
    curl_setopt($curlHandle, CURLOPT_HEADER, false);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
    curl_setopt($curlHandle, CURLOPT_POST, true);
    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $jsonURL);

    $jsonResponse = curl_exec($curlHandle);

    $curlStatus = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);

    if ($curlStatus != 200) {
        die("Error: call to URL $url failed with status $curlStatus, response $jsonResponse, curl_error " . curl_error($curlHandle) . ", curl_errno " . curl_errno($curlHandle));
    }

    curl_close($curlHandle);

    #$response = json_decode($jsonResponse, true);

}


ini_set('auto_detect_line_endings', TRUE);
$handle = fopen('/var/www/html/xfer/ae_links.csv', 'r');
$row = 0;
$ReqId = "readLinks";

header('Content-type: application/json');

while (($csvData = fgetcsv($handle)) !== FALSE) {
    $row++;
    $totalCol = count($csvData);
    #echo "<p> $totalCol fields in line $row: <br /></p>\n";

    if ($totalCol < 2) {
        echo "{}";
    } else {

        $prodName = $csvData[0];
        $linkData = base64_encode($csvData[1]);

        $webRequest = array("ReqId" => $ReqId, "ProdName" => $prodName, "Data" => $linkData);
        fetchUpdate($webRequest);
        sleep(10);
    }

}
ini_set('auto_detect_line_endings', FALSE);

?>

