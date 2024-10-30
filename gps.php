<html>
<body>
<h1>EXIF</h1>
<pre>
<?php
	$fn = dirname(__FILE__)."/gps.jpg";
	if (!file_exists($fn))
		die ("Datoteka $fn ne obstaja da bi iz nje prebral EXIF podatke!");
	
// poglejmo EXIF podatke	
	$exif = exif_read_data ($fn, ANY_TAG, true);
	//print_r ($exif);
	// izraèunajmo
	$lat =  $exif["GPS"]["GPSLatitude"];
	$la2  =  (int)$lat[0] + ((int)$lat[1]/60) + ((int)$lat[2]/360000);
	$long =  $exif["GPS"]["GPSLongitude"];
	$lo2  =  (int)$long[0] + ((int)$long[1]/60) + ((int)$long[2]/360000);
	echo "<a href=\"http://maps.google.com/maps?q=$la2+$lo2\">Latitude: $la2, longitude: $lo2</a>";

// poglejmo še IPTC podatke èe rabiš
	$size = getimagesize($fn, $info);
	if (isset($info["APP13"])) {
		$iptc = iptcparse($info["APP13"]);
//		echo "</pre><h1>IPTC</h1><pre>";
//		print_r($iptc);
	}
?>
</pre>
</body>
</html>
