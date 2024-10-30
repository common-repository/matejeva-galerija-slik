<?php
/*
Plugin Name: Matejeva galerija slik
Plugin URI: http://wordpress.org/extend/plugins/matejeva-galerija-slik/
Description: Preprosta galerija slik, naložiš na FTP in se prikaže na strani - <a href="http://matej.nastran.net/foto/">primer</a>. Podpira tudi LightBox plugin in izpiše EXIF informacije o sliki
Version: 0.7
Author: Matej Nastran
Author URI: http://profiles.wordpress.org/matejn/
*/

/*  
	Author Matej Nastran, matej@nastran.net, 2010

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (file_exists(dirname(__FILE__)."/random_photo.php"))
	include (dirname(__FILE__)."/random_photo.php");

function galerija_watermark ($fn){
	$host =" ".$_SERVER["HTTP_HOST"]." ";
//	$cmd = "convert '$fn'  -resize 600x600\> -font AvantGarde-Demi  -fill white -undercolor '#00000050' -gravity SouthEast -pointsize 16 -annotate +5+5 ' $host '   '$fn'";
	$cmd = "convert '$fn' -font AvantGarde-Demi  -fill white -undercolor '#00000050' -gravity SouthEast -pointsize 16 -annotate +5+5 ' $host ' '$fn'";
	system ($cmd);
	
}





function matej_get_caption ($fn){
	$size = getimagesize($fn, $info);
	if (isset($info["APP13"])) {
		$iptc = iptcparse($info["APP13"]);
		$cap = trim($iptc["2#120"][0]);
//		echo "caption1: $cap, ";
		$cap = iconv("CP1250", "UTF-8", $cap);
//		echo "captionCONV: $cap";
		return $cap;
	}
	return "";
}

$time_diff = 0; // razlika v urah

if (!function_exists('add_filter'))
	die ("Hello World!");

function pomanjsaj ($dir, $file){
//	echo "pomanjsaj: $dir, $file<br />\n";
	$fn1 = $dir.$file;
	$fn2 = $dir.strtolower($file);
	if (!($fn1 == $fn2))
		rename ($fn1, $fn2);
}	


function naredi_thumbnail ($file, $sizex, $sizey){
	echo "<!-- naredi_thumbnail ($file, $sizex, $sizey); -->\n";
	$thumbfn = preg_replace ("@(.*)(\.jpg)$@is", "\\1.thumbnail\\2", $file);
	$tmp = image_resize( $file, $sizex, $sizey);
// to se zgodi z verzijo 2.5, ki ima več velikosti thumbnailov
//echo "file = $file, tmp = $tmp, thumbfn = $thumbfn<br />\n";
    if ($tmp == ""){
		//echo "slika je tako majhna da thumbnail = slika<br />";
		copy ($file, $thumbfn);
	}
	if (is_wp_error($tmp))
		echo nl2br (print_r ($tmp));
	else{
		if (($tmp != $thumbfn) && file_exists ($tmp))
			rename ($tmp, $thumbfn);
		copy_exif ($file, $thumbfn);
	}
	//echo "<br />";
	return $thumbfn;
}
	
function resize_picture ($file, $sizex, $sizey){
	$tmp = naredi_thumbnail ($file, $sizex, $sizey);
	unlink ($file);
	rename ($tmp, $file);
}

require_once (dirname(__FILE__)."/browser.php");
$browser = new browser ();
$ie7 = $browser->Name == "MSIE" && (int)$browser->Version <= 7;
$ie7 = false; // ne vem, prej očitno nekaj ni delalo v IE7. 
	
if (((int)get_option ('galerija_num')) == 0)
	update_option('galerija_num', "7");
$galerija_num = (int) get_option ('galerija_num');			

if (((int)get_option ('galerija_maxx')) == 0)
	update_option('galerija_maxx', "700");
$galerija_maxx = (int) get_option ('galerija_maxx');			

if (((int)get_option ('galerija_maxy')) == 0)
	update_option('galerija_maxy', "500");
$galerija_maxy = (int) get_option ('galerija_maxy');			


if (strlen(get_option ('galerija_str')) == 0)
	update_option('galerija_str', "Oglej si vse slike &#187;");
$galerija_str = get_option ('galerija_str');			

$galerija_watermark = (int) get_option ('galerija_watermark');		


	
add_action('admin_menu', 'galerija_add_plugin_to_admin_menu');
add_filter('the_content', 'matej_gallery');
	
	function rawurlencodedir ($str){
			//zakodira direktorij kot URL, le znak / spusti
			$tmp = "aaaaaaaajkjkkcdjhgkjfdghkfjh";
			return str_replace ($tmp, "/", rawurlencode(str_replace ("/", $tmp, $str)));
			
	}
	
	function copy_exif ($from, $to){
		//$dir = realpath(dirname(__FILE__)."/../../../../../exiftool")." -m -q -overwrite_original \"$to\" -TagsFromFile \"$from\"";
		$dir = "exiftool -m -q -overwrite_original \"$to\" -TagsFromFile \"$from\"";
		system ($dir);
	}



function my_exif ($exif, $tag1, $tag2, $key1, $key2){
			$ret = "";
			if (!is_array($exif))
				return "";
			$a1 = $exif[$key1];
			if (strlen($key2) && is_array ($a1)){
				$ret = strlen ($a1[$key2]) ? $tag1.$a1[$key2].$tag2 : ""; 
			} else if (!strlen($key2))
			  $ret = strlen($a1) ? $tag1.$a1.$tag2 : "";
	      $ret = preg_replace ("@[^a-z0-9_ \.,:/]@is", "", $ret);
	      return $ret;
}

if (!function_exists('wp_create_thumbnail'))
				require_once (ABSPATH."wp-admin/includes/image.php");
require_once (dirname(__FILE__)."/exif.php");

//kolikokrat je bila galerija zagnana
$matej_num = 1;
$matej_gps = 0;

function matej_gallery ($content) {
			global $matej_num, $matej_gps;
			global $post, $galerija_maxx, $galerija_maxy, $galerija_num, $galerija_str, $ie7;
				//echo "<!-- test2 -->\n";
			
			$matej_script = <<<EOB
<script type="text/javascript">
		var matej_src{num} = new Array ({url});
		var matej_cs{num}=-1;
		var matej_count{num}=0;
		var matej_slika{num} = new Array();
		function matej_preload{num}() {
					matej_count{num}++;
					//alert ("CS: " + matej_cs{num} + ", count: " + matej_count{num}+"<br>");
					if (matej_cs{num} == -1 || matej_slika{num} [matej_cs{num}].complete){
						matej_cs{num}++;
						//alert ("Nova slika "+matej_src{num}[matej_cs{num}]);
						matej_slika{num}[matej_cs{num}] = new Image;
						matej_slika{num}[matej_cs{num}].src = matej_src{num}[matej_cs{num}];
					}
					if (matej_cs{num} + 2 <= matej_src{num}.length)
						setTimeout("matej_preload{num}()",50);
					// else alert ("Končal prenalaganje<br>");
		}
		matej_preload{num}();
</script>
EOB;
			
			$i = 0;
			$dodatek = "";
			//$dodatek .= "<div style=\"clear:left;\"></div>";
         $siteurl = get_option ("siteurl");
         //$purl = $siteurl."/".$post->guid."/";
         // $purl = $siteurl."/".$post->post_name."/";
		 $purl = get_permalink($post->ID);
//         print_r ($post);
         $znak = strstr ($purl, "?") ? "&amp;" : "?";
         while (preg_match ("@\[galerija [\-\.a-zčČšŠžžŽ0-9,_/\(\) ]+\]@ims", $content)){
         		 $ret = "";
                preg_match_all ("@\[galerija ([\-\.a-z0-9čČšŠžžŽ,_/\(\) ]+)\]@ims", $content, $match);
                $what = $match [1][0];
                $what2 = $what == "." ? "" : $what."/";
                $what2 = iconv("UTF-8", "CP1250", $what2);
                $dir = realpath(dirname(__FILE__)."/../../../_foto/$what2");
                if ($dir === false){
                   //echo "$dir ($what2) ne obstaja!";
                	 return $content;
              	 }
              	 else
              	 	  $dir .= "/";
                $basedir = $dir;
                $add = "";
                $addurl = "";
                if (preg_match ("@[a-z/0-9]{0,100}[\-a-z0-9čČšŠžžŽ,\(\) ]+@mis", $_GET["dir"]) && is_dir($dir.$_GET["dir"])){
                	 $add = $_GET["dir"]."/";
                	 $addurl = rawurlencodedir ($_GET["dir"])."/";
              	 }
              	 $dir .= $add;
                $base = "$siteurl/_foto/$what2".$add;
                $baseurl = "$siteurl/_foto/".rawurlencodedir($what2).$addurl;
                //echo "what2: $what2, addurl: $addurl<br>\n";
                //$ret .= "Parameter dir pravi ".$_GET["dir"]."<br />\n";
                $lightbox = $ie7 ? "" : "rel=\"lightbox[$what]\"";
                $descfn = $dir."opis.txt";
                $password_fn = $dir."geslo.txt";
                $opisi = array ();
                $sdirs = array();
                $counter = 0;
				$opis_dir = "";
				$fn_sort = false;
				//echo "<!-- test1 -->\n";
                if (file_exists($descfn)){
		                $desc = file ($descfn);
		                foreach ($desc as $cdesc){
		             			list($fn, $opis) = split ("[:;]", trim($cdesc), 2);
								if (trim($fn) == "dir")
									$opis_dir = trim ($opis);
								if (trim($fn) == "sort")
									$fn_sort = true;
								else
									$opisi [trim($fn)] = trim ($opis);
							 }
					 }
			    //echo "opis_dir: $opis_dir";
                $geslo = file_exists($password_fn) ? trim(join ('', file ($password_fn))) : "";
                $pass = $_GET["geslo"] == $geslo;
				$my_files = array ();
				$my_urls = array();
                if (is_dir($dir)) {
				         $subdir = "";
				         $updir = $subdir = "";
				         if (!$pass){
				         	$ret = "<form action=\"$purl\" method=\"GET\"><br />Za dostop do te galerije potrebujete geslo!<br /><br /> <input name=\"geslo\" value=\"\"><input type=\"hidden\" name=\"dir\" value=\"".$_GET["dir"]."\">&nbsp;<input type=\"submit\" value=\"OK\"></form>";
							}
						   else if ($dh = opendir($dir)) {
						      $konec = false;
						      $mybase = 120;
							  $mybas2 = (int)(3*$mybase/5);
								$mytotal = $mybase + 4;
							   $tmpCounter = 0;
						       while (($tmpCounter < 1000) && (($file = readdir($dh)) !== false)) {
								$tmpCounter++;
								
//								echo "<!-- $tmpCounter: dir = $dir, dh = $dh, file = $file -->\n";
						           if (!$konec && preg_match("@\.jpg$@is", $file) && !strstr ($file, "thumbnail") && !(preg_match ("@naslovna\.jpg@is", $file)))
						           {
						           	   $thumbfn = preg_replace ("@(.*)(\.jpg)$@is", "\\1.thumbnail\\2", $file);
						           	   $fthumbfn = $dir.$thumbfn;
						           	   
				           	   		$imagesize = getimagesize($dir.$file);
							  				$cx  =  $imagesize['0'];
											$cy  =  $imagesize['1'];
											$pomanjsaj = ($cx > $galerija_maxx) || ($cy > $galerija_maxy) ? true : false; 
											$mysize = (int)( $cx > $cy ? $mybase * $cx / $cy : $mybase);  

						           	   // pomanjšaj original če je potrebno
						           	   if ($pomanjsaj){
										
										//echo "1pomanjsujem $dir$file, cx = $cx, max = $galerija_maxx in cy = $cy, max = $galerija_maxy, side: $krneki<br />\n";
						           	    naredi_thumbnail( $dir.$file, $galerija_maxx, $galerija_maxy);
										copy_exif ($dir.$file, $fthumbfn);
						           	   	unlink ($dir.$file);
						           	   	rename ($fthumbfn, $dir.$file);
											}
											if (!file_exists($fthumbfn)){
											  //echo "kreiram: $fthumbfn<br />\n";
											   naredi_thumbnail( $dir.$file, $galerija_maxx, $mybase);
											   galerija_watermark($dir.$file);
											}
										// zaradi smotanega IE6 moram to ročno čekirati, saj se IE6 ne obnaša lepo pri CSS width: auto; nastavitvi
				           	   		$imagesize = getimagesize($fthumbfn);
							  				$tx  =  (int) $imagesize['0'] + 8;
											
						           	   $url1 = $baseurl.rawurlencodedir($file);
						           	   $url2 =  file_exists($fthumbfn) ? $baseurl.rawurlencodedir($thumbfn) : $url1;
						           	   // echo "$thumbfn : $url2<br />\n";
						           	   //$url2 =  $baseurl.rawurlencodedir($thumbfn);

						           	   $opis = array_key_exists ($file, $opisi) ? $opisi[$file] : preg_replace ("@(.*).jpg$@is", "\\1", $file);
						           	   $opis = iconv("CP1250", "UTF-8", $opis);
						           	   $opis = str_replace ("_", " ", $opis);
						           	   
						           	   $dod =  matej_get_exif ($dir.$file, $lat_val, $long_val, $caption);
									   if (strlen($caption))
										$opis = "$caption, $opis";
						           	   if (!strlen($dod.$caption)){
											$exif = read_exif_data_raw ($dir.$file, 0);
											$caption = matej_get_caption ($dir.$file);
							           	   $dod = my_exif ($exif, ", ", "", "IFD0", "Model");
							           	   $ccc = my_exif ($exif, "", "", "SubIFD", "DateTimeOriginal");
										   $lat_type = my_exif ($exif, "", "", "GPS", "Latitude Reference");
										   $lat_val = (float) my_exif ($exif, "", "", "GPS", "Latitude");
										   $long_type = my_exif ($exif, "", "", "GPS", "Longitude Reference");
										   $long_val = (float) my_exif ($exif, "", "", "GPS", "Longitude");
											if (!(substr($lat_type, 0, 1)== "N"))
											$lat_val = 0 - $lat_val;
										   if (!(substr($long_type, 0, 1)== "E"))
											$long_val = 0 - $long_val;
										    

										   
							           	   if (strlen($ccc)){
												  list ($cleto, $cmesec, $cdan, $cura, $cmin) = split ("[: ]", $ccc);
												  $dod .= ", ".(int)$cdan.".".(int)$cmesec.".".$cleto." ".$cura.":".$cmin;
										      }
							           	   $dod .= my_exif ($exif, ", ", "", "SubIFD", "ExposureTime");
							           	   $dod .= my_exif ($exif, ", ", "", "SubIFD", "FNumber");
							           	   $dod .= my_exif ($exif, ", ", "", "SubIFD", "FocalLength");
							           	   $dod .= my_exif ($exif, ", flash: ", "", "SubIFD", "Flash");
						           	   	if (!strlen($dod))
												  $dod = " ";
										      matej_put_exif ($dir.$file, $dod, $lat_val, $long_val, $caption);
	           	                  }else {
													// echo "<!-- exif - baza -->";
											if ($lat_val || $long_val)
												$matej_gps++;
											//echo "long_val: $long_val, lat_val: $lat_val<br />";
													
											  }
										if ($lat_val || $long_val){
											$gps_link = "<a target='_blank' href='".$siteurl."/wp-content/plugins/galerija/map.php?dir=".rawurlencodedir($_GET["dir"])."&amp;file=".rawurlencodedir($file)."'><img style='display: inline; float:left; margin: 0px 5px 0px 0px; border: 0;' src='".$siteurl."/wp-content/plugins/galerija/markers/small.png'</a>";
										} else
											$gps_link = "";
										$opis .= trim ($dod);
						           	   $i++;
									   $my_urls [$file] = $url1;
									   $my_files [$file] = "\n<div style=\"height: ".$mytotal."px; width: ".$tx."px; padding: 5px; float: left;\"><a title=\"$opis$gps_link\" $lightbox href=\"$url1\"><img onmouseover=\"window.status='$opis';\" style=\"vertical-align: bottom;\" src=\"$url2\" /></a></div>";
						           	   if ($i>= $galerija_num && (is_home() || is_archive())){
						           	   	$konec = true;
						           	      $dodatek .= "<p><b><a href=\"$purl#galerija\" class=\"more-link\">$galerija_str</a></b></p>\n";
				           	         }
									  }else if (is_dir($dir.$file) && substr ($file, 0, 1) != "."){
									  		  $cwd = substr ($dir.$file, strlen($basedir));
											  if (strlen($opis_dir))
												$nice = $opis_dir;
											  else
												$nice = strstr($cwd, "/") ? substr(strrchr($cwd, "/"), 1) : $cwd;
									  		  $nice = iconv ("WINDOWS-1250", "UTF-8", $nice);
									  		  $cwd = rawurlencode($cwd);
									  		  $stat = stat ($dir.$file);
									  		  $short = false;
									  		  if (preg_match ("@\([0-9]*\.[0-9]*\.[0-9]*\)@is", $file, $out)){
									  		     list ($qd, $qm, $ql, $xx) = explode (".", trim ($out[0], "()"));
									  		     //echo $out[0];
									  		     $fdatum = mktime (0,0,0,$qm,$qd, $ql);
									  		     $short = true;
                          }
                          else
  									  		  $fdatum = $stat["mtime"];
									  		  $sthfn = $dir.$file."/naslovna.jpg";
											  
									  		  //echo $sthfn."<br />\n";
									  		  if (file_exists ($sthfn)){
											    $imagesize = getimagesize($sthfn);
												$sx  =  (int) $imagesize['0'];
												$sy  =  (int) $imagesize['1'];
												if ($sy > $mybas2 || $sx > $mybas2){
													resize_picture ($sthfn, $mybas2, $mybas2);
												}
									  		    $sthurl = $baseurl.rawurlencodedir($file)."/naslovna.jpg";
											   }
									  		  else
									  		   $sthurl = "$siteurl/wp-content/plugins/galerija/folder.gif";
									  		   $counter++;
									  		  $tshort = $short ? "" : "<small>(".date ("j.n.Y", $fdatum).")</small>";     
									  		  $nice = str_replace ("_", " ", $nice);
											  $title_nice = str_replace (array("<small>", "</small>"), array ("", ""), $nice);
											  if (preg_match ("@\([0-9]*\.[0-9]*\.[0-9]*\)@is", $nice, $out))
												$nice = str_replace ($out[0], "<small>".$out[0]."</small>", $nice);
											  $sort_key = $fn_sort ? $sthfn : date ("Ymd", $fdatum).sprintf("%4d", $counter);
												//echo "sort_key: $sort_key ";
									  		  $sdirs [$sort_key] = "<div style=\"width: 80px; margin: 5px; padding: 5px; float: left;\"><a title=\"$title_nice\" href=\"$purl".$znak."dir=$cwd&amp;geslo=$geslo\"><img src=\"$sthurl\"><br />$nice<br />$tshort</a></div>\n";
									  } else 
									  if (preg_match ("@naslovna\.jpg@is", $file))
									  {
										//echo "mam $file<br />\n";
										pomanjsaj ($dir, $file);
									  }
						       }
						       
						       closedir($dh);
						   } 
                 }
				 ksort ($my_files);
				 ksort ($my_urls);
				 foreach ($my_files as $key => $value)
					$ret .= $value;
				 $matej_urls = "";
				 foreach ($my_urls as $key => $value)
					$matej_urls .= "\"$value\", ";
				 $matej_urls = trim ($matej_urls, ", ");
				 if ($fn_sort)
					ksort ($sdirs);
				 else
					krsort ($sdirs);
				 foreach ($sdirs as $key => $value)
					$subdir .= $value;
					
				if (strlen($subdir))
					$subdir .= "<div style=\"clear:xleft;\"></div>";
                 $ret = "<div id=\"empty\">$subdir</div><div style=\"clear:xleft;\"></div>$ret\n";
                 if (strlen($dir) - strlen($basedir)){
                 	  $ostanek = substr ($dir, strlen($basedir));
                 	  $ostanek = trim ($ostanek, "/");
                 	  //$ostanek = substr ($ostanek, 0, strrpos ($ostanek, "/"));
                 	  do {
                 	     $nice = $ostanek;
                 	     $nice = strstr($ostanek, "/") ? substr(strrchr($ostanek, "/"), 1) : $ostanek;
                 	     $nice = iconv("CP1250", "UTF-8", $nice);
                 	     $updir = " &raquo; <a href=\"$purl".$znak."dir=".rawurlencodedir($ostanek)."&amp;geslo=$geslo\">$nice</a> $updir";
                 	     $ostanek = substr ($ostanek, 0, strrpos ($ostanek, "/"));
 						  } while (strlen($ostanek));
 						  $updir = "<a href=\"$purl\">Vrh</a> $updir";
					  } 
					if ($matej_gps)
						$gps_txt = "<a target=\"_blank\" style=\"border: 0;\" href=\"$siteurl/wp-content/plugins/galerija/map.php?dir=".rawurlencodedir($_GET["dir"])."\"><img src=\"$siteurl/wp-content/plugins/galerija/marker.png\" style=\"float: right; display: inline; margin: 0 0px 0px 0px; border: 0;\" title=\"$matej_gps slik v galeriji vsebuje GPS informacije, klikni tu, da is ogledaš na zemljevidu kje so bile slike posnete.\">";
					else
						$gps_txt = "";
					  if (strlen($updir))
					  	  $updir = "<div style=\"border: 1px dashed; padding: 4px; margin: 0px 0px 7px 0px;\">$gps_txt$updir</div>\n";
					  //$ret = "<span>$updir<div id=\"galerija\">$ret</div>$dodatek</span>";

					  $ret = "$updir<div id=\"galerija\">$ret</div>$dodatek";
					  $matej_script = str_replace ("{url}", $matej_urls, $matej_script);
					  $matej_script = str_replace ("{num}", $matej_num, $matej_script);
					  //$ret .= $matej_script;
					  //izključim preload
					  $matej_num++;
				
                $content = str_replace ("[galerija $what]", $ret, $content);
				
//                $ret .= "What: $what<br />\n";
//                $content .= $ret;
         }
         return $content;
}


function matej_get_exif ($fn, &$lat, &$long, &$caption){
   global $wpdb;
   $fn = iconv("CP1250", "UTF-8", $fn);
   $table_name = $wpdb->prefix . "galerija_exif";
	$res = $wpdb->get_col("SELECT lat FROM $table_name WHERE fn LIKE '".$wpdb->escape($fn)."';");
	$lat = $res[0];
	$res = $wpdb->get_col("SELECT `long` FROM $table_name WHERE fn LIKE '".$wpdb->escape($fn)."';");
	$long = $res[0];
	$res = $wpdb->get_col("SELECT `caption` FROM $table_name WHERE fn LIKE '".$wpdb->escape($fn)."';");
	$caption = $res[0];

	$res = $wpdb->get_col("SELECT exif FROM $table_name WHERE fn LIKE '".$wpdb->escape($fn)."';");
   return $res[0];
}
function matej_put_exif ($fn, $exif, $lat, $long, $caption){
   global $wpdb, $time_diff;
   $fn = iconv("CP1250", "UTF-8", $fn);
   $table_name = $wpdb->prefix . "galerija_exif";
    $wpdb->query($sql = "INSERT INTO $table_name VALUES (0, NOW() + INTERVAL $time_diff HOUR, '".$wpdb->escape($fn). "','" . $wpdb->escape($exif) . "', '$lat', '$long', '".$wpdb->escape($caption)."')");
//    echo "$fn (".mysql_error().", $sql)";
//  echo "caption: $caption, ".iconv("CP1250", "UTF-8", $caption);
}

function galerija_check_table (){
   global $wpdb;
   
   $table_name = $wpdb->prefix . "galerija_exif";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE " . $table_name . " (
      	  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  `when` datetime NOT NULL,
	       fn VARCHAR(255) NOT NULL,
	       exif text NOT NULL,
		    `lat` float NOT NULL default '0',
			`long` float NOT NULL default '0',
			`caption` VARCHAR(255) NOT NULL,
	       UNIQUE KEY id (id)
	       );";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql); 
	  //echo "$sql   ".mysql_error();
	}
 }

galerija_check_table ();

function galerija_add_plugin_to_admin_menu()   
{  
   add_submenu_page('options-general.php', 'Galerija', 'Galerija', 10, __FILE__, 'galerija_admin_action');  
}  

function galerija_admin_action()
{  
	 global $galerija_num, $galerija_maxx, $galerija_maxy, $galerija_str, $galerija_watermark;
	 $updated = false;
	 
    if (isset($_POST['galerija_num'])) {
    	  if ((int)$_POST["galerija_num"]){
				  $galerija_num = (int)$_POST["galerija_num"];
  				  update_option("galerija_num", "$galerija_num");
				  $updated = true;
        }
   }
    if (isset($_POST['galerija_maxx'])) {
    	  if ((int)$_POST["galerija_maxx"]){
				  $galerija_maxx = (int)$_POST["galerija_maxx"];
  				  update_option("galerija_maxx", "$galerija_maxx");
				  $updated = true;
        }
   }
    if (isset($_POST['galerija_maxy'])) {
    	  if ((int)$_POST["galerija_maxy"]){
				  $galerija_maxy = (int)$_POST["galerija_maxy"];
  				  update_option("galerija_maxy", "$galerija_maxy");
				  $updated = true;
        }
		$galerija_watermark = (int)$_POST["galerija_watermark"];
  		update_option("galerija_watermark", "$galerija_watermark");
	    $updated = true;
   }
    if (isset($_POST['galerija_str'])) {
    	  if (strlen($_POST["galerija_str"])){
				  $galerija_str = $_POST["galerija_str"];
  				  update_option("galerija_str", "$galerija_str");
				  $updated = true;
        }
   }
   if ($updated)
	echo '<div class="updated"><p><strong>'.__('Nove nastavitve so shranjene!', 'galerija')."</strong></p></div>";
   ?>
   <div class="wrap">
    <h2><?php _e('Galerija - možnosti', 'galerija'); ?></h2>
        <form method="post">
<br />
			<br /> 
			<label for="galerija_num"><?php _e("Koliko slik naj izpišem na osnovni strani", "galerija"); ?>:&nbsp;
				  <input type="text" size="5" name="galerija_num" value="<?php echo $galerija_num; ?>" />
			</label><br /><br />
			<label for="galerija_maxx"><?php _e("Maksimalna dolžina slike (x)", "galerija"); ?>:&nbsp;
				  <input type="text" size="5" name="galerija_maxx" value="<?php echo $galerija_maxx; ?>" />
			</label><br /><br />
			<label for="galerija_maxy"><?php _e("Maksimalna višina slike (y)", "galerija"); ?>:&nbsp;
				  <input type="text" size="5" name="galerija_maxy" value="<?php echo $galerija_maxy; ?>" />
			</label><br /><br />
			<label for="galerija_str"><?php _e("Besedilo povezave za izpis vseh slik", "galerija"); ?>:&nbsp;
				  <input type="text" size="50" name="galerija_str" value="<?php echo $galerija_str; ?>" />
			</label><br /><br />
			<label for="galerija_watermark"><input type="checkbox" <?php echo $galerija_watermark ? "checked" : "" ?> name="galerija_watermark" value="1" />
&nbsp;<?php _e("v desni spodnji kot napiši naslov strani, kateri pripada slika", "galerija"); ?>			
			</label><br /><br />
			<em>Slike, ki so večje od maksimalne velikosti, bodo pomanjšane.</em>
    <p class="submit">
        <input type="submit" name="biblija_update" value="<?php _e('Posodobi nastavitve', 'galerija') ?>" />
    </p>
    </form>
   </div>

<?php

}



?>