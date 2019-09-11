<html>
<head>
	<title>SPC Status</title>
	<script src="http://code.jquery.com/jquery-1.10.1.min.js"></script>
	<style>
	@import url(http://fonts.googleapis.com/css?family=Roboto:400,500,700,300,100);

body {
  background-color: #E6E6E6;
  font-family: "Roboto", helvetica, arial, sans-serif;
  font-size: 12px;
  font-weight: 400;
  text-rendering: optimizeLegibility;
}

div.table-title {
  display: block;
  margin: auto;
  max-width: 600px;
  padding:5px;
  width: 100%;
}

.table-title h3 {
   color: #3F85C6;
   font-size: 30px;
   font-weight: 400;
   font-style:normal;
   font-family: "Roboto", helvetica, arial, sans-serif;
   text-shadow: -1px -1px 1px rgba(0, 0, 0, 0.1);
   text-transform:uppercase;
}


/*** Table Styles **/

.table-fill {
  background: white;
  border-radius:3px;
  border-collapse: collapse;
  height: 320px;
  margin: auto;
  max-width: 600px;
  padding:5px;
  width: 100%;
  box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
  animation: float 5s infinite;
  border: 1px solid rgba(0,0,0,0.2);
}

th {
  color:#D5DDE5;;
  background:#1b1e24;
  border-bottom:4px solid #9ea7af;
  border-right: 1px solid #343a45;
  font-size:18px;
  font-weight: 100;
  padding:18px;
  text-align:left;
  text-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
  vertical-align:middle;
}

th:first-child {
  border-top-left-radius:3px;
}

th:last-child {
  border-top-right-radius:3px;
  border-right:none;
}

tr {
  border-top: 1px solid #C1C3D1;
  border-bottom-: 1px solid #C1C3D1;
  font-size:12px;
  font-weight:normal;
  text-shadow: 0 1px 1px rgba(256, 256, 256, 0.1);
}

tr:first-child {
  border-top:none;
}

tr:last-child {
  border-bottom:none;
}

tr:last-child td:first-child {
  border-bottom-left-radius:3px;
}

tr:last-child td:last-child {
  border-bottom-right-radius:3px;
}

td {
  background:#FFFFFF;
  padding:10px;
  text-align:left;
  vertical-align:middle;
  font-weight:300;
  font-size:18px;
  text-shadow: -1px -1px 1px rgba(0, 0, 0, 0.1);
  border-right: 1px solid #C1C3D1;
}

td a {
	color:#3a7cb9;
	text-decoration: none;
}

td a:hover {
	color:#2e6391;
}

td:last-child {
  border-right: 0px;
}

th.text-left {
  text-align: left;
}

th.text-center {
  text-align: center;
}

th.text-right {
  text-align: right;
}

td.text-left {
  text-align: left;
}

td.text-center {
  text-align: center;
}

td.text-right {
  text-align: right;
}

td.logic_0 {
  background-color:#D0384C;
}
td.logic_125 {
  background-color:#5CB7A9;
}
td.colour1 {
		background-color:#5CB7A9;
}
td.colour2 {
		background-color:#98D5A4;
}
td.colour3 {
		background-color:#D0EC9C;
}
td.colour4 {
		background-color:#F3FAAD;
}
td.colour5 {
		background-color:#FEEFA7;
}
td.colour6 {
		background-color:#FDCD7C;
}
td.colour7 {
		background-color:#FA9C57;
}
td.colour8 {
		background-color:#EE6445;
}
td.colour9 {
		background-color:#D0384C;
}
td.outofrange {
		background-color:#D6D6D6;
}
</style>
</head>
<body>

<?php

# Useful to have on whilst debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$spcAddress = '192.168.100.160:8088';

# Making up for daylight savings, I need to fix this at some point
$localTime = date('U') + 3600;

# Build an array of zones
$url = 'http://'.$spcAddress.'/spc/zone';
$output = file_get_contents($url);
$zones_array = json_decode($output);

# Extract zone ids and names from the array
$zones = array();
foreach ($zones_array->data->zone as $zone) {
  $zones[$zone->id] = $zone->zone_name;
}

# Function to build the request URL for each zone log
function createZoneUrl($zoneid) {
	global $spcAddress;
	$zoneUrl = 'http://'.$spcAddress.'/spc/zonelog/'.$zoneid;
	return $zoneUrl;
}

# Function that returns results of input array within specified time window (value in minutes)
function reduceArrayByTime($inputArray, $valueInMinutes) {
	global $localTime;
	$valueInSeconds = $valueInMinutes*60;
	$oldestTime = $localTime - $valueInSeconds;

	# Use foreach loop to remove array element
	foreach($inputArray as $key => $value) {
    	if($key < $oldestTime) {
        	unset($inputArray[$key]);
    	}
	}
	
		return $inputArray;
}

# Pull each log into a local array - store it in $movements as $key $value pairs
$movements = array();
foreach ($zones as $key=>$value) {
	$url = createZoneUrl($key);
	$return = file_get_contents($url);
	$output = json_decode($return);
	$zone_identifier = $value;

	foreach ($output->data->zonelog->event as $result) {
	$movements[$value][$result->time] = $result->input;
	}
}

# Function to count how many times the zone has logged a hit. Note a hit appears as a binary 1 and returns to 0 before going quiet.
function getHits($inputArray) {
	$hits = 0;
	foreach ($inputArray as $key => $value) {
		$hits = $hits + $value;
	}
	return $hits;
}

# Function to calculate when movement was last detected in a zone
function getLatestHit($inputArray) {
	# Let's check the most recent movement is dba_first
	krsort($inputArray);

	# Move to the first element in the array
	reset($inputArray);

	# return the first key
	return key($inputArray);
}


function convertEpoch($value) {
	$dt = new DateTime("@$value");  // convert UNIX timestamp to PHP DateTime
	return $dt->format('d/m H:i:s'); // output = 01-01 00:00:00
}

# Function that creates different CSS classes dependng on input value
function createHitCSSClass($value) {
	global $localTime;

	$difference = $localTime - $value;

	# scale 1 - 9
	# 1m, 5m, 30m, 60m (1 hour), 120m (2 hours), 360m (6 hours), 720m (12 hours), 1440m (1 day), 2880m (2 days)

	if ($difference < 60) { #1m
		return 'colour1';
	}
	elseif ($difference < 300 && $difference > 60) { #5m
		return 'colour2';
	}
	elseif ($difference < 1800 && $difference > 300) { #30m
		return 'colour3';
	}
	elseif ($difference < 3600 && $difference > 1800) { #60m
		return 'colour4';
	}
	elseif ($difference < 7200 && $difference > 3600) { #120m
		return 'colour5';
	}
	elseif ($difference < 21600 && $difference > 7200) { #360m
		return 'colour6';
	}
	elseif ($difference < 43200 && $difference > 21600) { #720m
		return 'colour7';
	}
	elseif ($difference < 86400 && $difference > 43200) { #1440m
		return 'colour8';
	}
	elseif ($difference < 172800 && $difference > 86400) { #2880m
		return 'colour9';
	}
	else {
		return 'outofrange';
	}
}

function friendlyTime($seconds) {
	global $localTime;
	$time = $localTime - $seconds;
	$t = round($time);
 	return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);
}

# Let's bring this all together and display a table of the results

echo '<table id="tableID" class="sortable table-fill"><thead><tr>';
echo '<th class="text-left">Time Window</th><th class="text-left">48H</th><th class="text-left">24H</th><th class="text-left">12H</th><th class="text-left">6H</th><th class="text-left">2H</th><th class="text-left">1H</th><th class="text-left">30M</th><th class="text-left">5M</th><th class="text-left">Last Hit</th><th class="text-left">Elapsed</th>';
echo '</tr></thead>';
echo '<tbody>';


# Cycle through each of the zones identified and display something interesting

foreach ($zones_array->data->zone as $zone) {
	$zoneName = $zone->zone_name;
  echo '<tr>';
  echo '<td>'.$zone->zone_name.'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],2880)).'">'.getHits(reduceArrayByTime($movements[$zoneName],2880)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],1440)).'">'.getHits(reduceArrayByTime($movements[$zoneName],1440)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],720)).'">'.getHits(reduceArrayByTime($movements[$zoneName],720)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],360)).'">'.getHits(reduceArrayByTime($movements[$zoneName],360)).'</td>';
  echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],120)).'">'.getHits(reduceArrayByTime($movements[$zoneName],120)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],60)).'">'.getHits(reduceArrayByTime($movements[$zoneName],60)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],30)).'">'.getHits(reduceArrayByTime($movements[$zoneName],30)).'</td>';
	echo '<td class="logic_'.getHits(reduceArrayByTime($movements[$zoneName],5)).'">'.getHits(reduceArrayByTime($movements[$zoneName],5)).'</td>';
	echo '<td class="'.createHitCSSClass(getLatestHit($movements[$zoneName])).'">'.convertEpoch(getLatestHit($movements[$zoneName])).'</td>';
	echo '<td class="'.createHitCSSClass(getLatestHit($movements[$zoneName])).'">'.friendlyTime(getLatestHit($movements[$zoneName])).'</td>';

  echo '</tr>';
}

echo '</tbody>';
echo '</table>';

?>
</body>
</html>
