<?php

function galerija_get_value ($sql, $field, $default = ""){
  	  $result = MYSQL_QUERY($sql);
   		 if ($result){
  			   if ($row=mysql_fetch_Array($result))
  			    return $row [$field];
	   		   else
  					return $default;
  	   }
}
function galerija_urlencode ($url){
	return str_replace ("%2F", "/", rawurlencode ($url));
}

function galerija_fix ($str){
	$str = preg_replace ("@[\d_]+@", " ", $str);
	$str = preg_replace ("@[\s]+@", " ", $str);
	return trim ($str);
}

function galerija_get_row ($sql){
  	$result = MYSQL_QUERY($sql);
   	if ($result)
  			   return mysql_fetch_Array($result);
  	else
		return false;
}


function matej_random_photo (){
   global $wpdb;
   $table_name = $wpdb->prefix . "galerija_exif";

	$found = false;
	$i = 0;
	$fs = "";
	while (!$found && $i < 100){
		$i++;
		$sql = "SELECT * FROM $table_name ORDER BY RAND() LIMIT 1";
		$row = galerija_get_row ($sql);
		$fn = $row["fn"];
		$fn = preg_replace ("@(.*)(\.jpg)$@is", "\\1.thumbnail\\2", $fn);
		if (file_exists($fn))
			$found = true;
		else{
			mysql_query ($sql = "DELETE FROM $table_name WHERE id = ".(int)$row["id"].";");
			$fs .= "$fn\n";
			//echo "$sql (fn = $fn)";
			// $found = false;
		}
	}
	if ($found){
		$base = explode ("/", dirname(__FILE__));
		$path = "";
		for ($i=0;$i<count($base)-3;$i++)
			$path .= $base[$i]."/";
		
		//echo "Dir: $dir, path: $path";
		$fn = str_replace ($path, "/", $fn);
		$base = dirname ($fn)."/";
		$base = str_replace ("/_foto/", "", $base);
		$b2 = explode ("/", $base);
		$b3 = "";
		foreach ($b2 as $val)
			$b3 .= ucfirst(trim($val, "_ ")).", ";
		$b3 = galerija_fix(trim($b3, " ,"));
//		$b3 .= " (".trim($row["exif"], ", ").")";
		echo "<p style=\"margin: 4px; text-align: center;\"><a href=\"/foto/?dir=".addslashes(galerija_urlencode($base))."\"><img style=\"border: none; max-width: 200px;\" src=\"".addslashes(galerija_urlencode($fn))."\" title=\"$b3\" /></a></p>";
		
	}
	else
		echo "<!-- \n$fs -->";
}

?>