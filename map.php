<?php
	ob_start("ob_gzhandler");
	$zoom = (int)$_GET["zoom"];
	
	function gps_distance ($Lat1, $Lon1, $Lat2, $Lon2){
			if (($Lat1 == $Lat2) && ($Lon1 == $Lon2))
				return 0;
			$Difference = 3958.75 * acos(  sin($Lat1/57.2958) * sin($Lat2/57.2958) + cos($Lat1/57.2958) * cos($Lat2/57.2958) * cos($Lon2/57.2958 - $Lon1/57.2958));
			return $Difference * 160.9344; // for meters
			
	}	
	
	
	if (!strlen($_GET["dir"]) || preg_match ("@\.\.@is", $_GET["dir"]))
		die ("Izberi galerijo....");
	else 
		$mdir = $_GET["dir"];
	include (realpath(dirname(__FILE__)."/../../../wp-config.php"));	
	$t = join ('', file(dirname(__FILE__)."/map.html"));
	
	$dir = realpath (dirname(__FILE__)."/../../../_foto/$mdir");
	$selected = 0;
	
	// kateri tip mape je za galerijo to primeren
	$t_str = "G_NORMAL_MAP";  // default
	$tfn = $dir."/opis.txt";
	if (!@file_exists($dir))
		die ("Izberi galerijo!");

	if (@file_exists($tfn)){
		$tmp = join ('', file ($tfn));
		if (preg_match("@map:([0-9])@mis", $tmp, $match))
			$type = (int)$match[1];
		if ($type==2)
			$t_str = "G_HYBRID_MAP";
		else if ($type==1)
			$t_str = "G_SATELLITE_MAP";
	}
	
	
	$sql = "SELECT * FROM  wp_galerija_exif WHERE fn LIKE '$dir/%' AND (lat OR 'long') ORDER BY fn;";
	//echo "$sql";
	mysql_connect (DB_HOST, DB_USER, DB_PASSWORD);
	mysql_select_db (DB_NAME);
	mysql_query ("set names utf8;");
	$result = mysql_query($sql);
	// Check result
	// This shows the actual query sent to MySQL, and the error. Useful for debugging.
	if (!$result) {
		$message  = 'Invalid query: ' . mysql_error() . "\n";
		$message .= 'Whole query: ' . $query;
		die($message);
	}
	//echo "$sql";
	
	$center = $points = "";
	$num = 0;
	$points = "\n\n";
	$path = "\n";
	$url1 = "http://".$_SERVER['HTTP_HOST']."/_foto";
	$url2 = realpath (dirname (__FILE__)."/../../../_foto");
	
	
	while ($row = mysql_fetch_array($result)) {
		$fn = $row["fn"];
		if (($sfn = substr($fn, 1 + strlen(dirname($fn)))) == $_GET["file"])
			$selected = $num;
		$sfn .= ", ";
		$onlydir = $url2."/".$mdir;
		if (dirname($fn) == $onlydir){
			$thumbfn = preg_replace ("@(.*)(\.jpg)$@is", "\\1.thumbnail\\2", $fn);
			if (!strlen($center))
				$center = $row["long"].", ".$row["lat"];
//			$caption = iconv("CP1250", "UTF-8", $row["caption"]);
			$caption = $row["caption"];
//			echo $caption;
			if (strlen($caption))
				$caption = "<p style=\"font-size: 14px; font-weight: bolder;\">$caption</p>";
			$img_url = str_replace ($url2, $url1, rawurlencodedir($thumbfn));
			$img_ur2 = str_replace ($url2, $url1, rawurlencodedir($fn));
			

			$html = "<img onclick=\"flip_picture(this);\" style=\"max-height:550px; margin: 0px 7px 7px 0px; float: left;\" src=\"$img_url\" alt=\"$img_ur2\">$caption".$sfn.wordwrap(trim ($row["exif"], ", "), 35, "<br />")."<br /><br /><em>GPS Latitude: ".$row["lat"].", longitude: ".$row["long"]."</em>";
			$points .= "ppointsy[$num] = parseFloat(".$row["lat"].");\nppointsx[$num] = parseFloat(".$row["long"].");\ninfoHtmls[$num]='$html';\n\n";
			//$path .= "points.push(new GPoint(".$row["long"].", ".$row["lat"]."));\n";
			$num++;
		}
	}
	mysql_free_result($result);	
	$title = $mdir;
	$tmp = explode ("/", $title);
	$title2 = "";
	$cd = "";
	$cc = 0;
	foreach ($tmp as $ct){
		$dodatek = $cc ? "/": "";
		$cd .= $dodatek.trim($ct, "/");
		$title2 .= "$dodatek<a href=\"/foto/?dir=".rawurlencodedir($cd)."\">$ct</a>";
		$cc++;
	}
	
	
	
	$tfn = $dir."/path.txt";

	
	
	
	$fs = array(); 
	if (is_dir($dir)) {
	   if ($dh = opendir($dir)) {
	       while (($file = readdir($dh)) !== false)
				if (preg_match ("@path.*\.txt@is", $file))
					array_push ($fs, $dir."/".$file);
	       closedir($dh);
	   }
	}
	sort ($fs);
	$i = 0;
	$colors = array ("#F62217", "#4CC417", "#342D7E", "#C35617");
	$llong = $llat = 0;
	foreach ($fs as $tfn){
		$path .= "\t\t\t\tpoints = new Array();\n";
		$tmp = file ($tfn);
		foreach ($tmp as $line){
			$line = trim ($line);
			list($long, $lat, $date) = explode (",", $line);
			if (($cdist = gps_distance($llat, $llong, $lat, $long)) > 3){ // loèjivost max 3 metre, veè ne rabim
				$path .= "\t\t\t\tpoints.push(new GPoint($long, $lat));\n";
//				$path .= "//$cdist\n";
				$llong = $long;
				$llat = $lat;
			}
		}
		$path .= "\t\t\t\tmap.addOverlay(new GPolyline(points, \"".$colors[$i]."\", 8, .75));\n";
		$i++;
	}
	
		
	$t = str_replace ("{zoom}", $zoom, $t);
	$t = str_replace ("{center}", $center, $t);
	$t = str_replace ("{points}", $points, $t);
	$t = str_replace ("{path}", $path, $t);
	$t = str_replace ("{num}", $num, $t);
	$t = str_replace ("{title}", $title, $t);
	$t = str_replace ("{title2}", $title2, $t);
	$t = str_replace ("{type}", $t_str, $t);
	$t = str_replace ("{selected}", $selected, $t);
	echo $t;
	
?>