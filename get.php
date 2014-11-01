<?php

//require_once("../config.php");
require_once('fileutils.php');
require_once('mergeosm.php');

$minAllowedZoom = 11;
//$serverUrl = "http://localhost/m/";
//$serverUrl = "http://localhost/fetchtiles/";
$serverUrl = "http://localhost/fetchtiles/";
$pathToTiles = "tiles/";

$masterZoom = 12;

//*** Parse Input ****
//Split URL for processing
define('INSTALL_FOLDER_DEPTH', 1);

$pathInfo = GetRequestPath();
$urlExp = explode("/",$pathInfo);

//Log the request
$fi = fopen("log.txt","at");
/*flock($fi, LOCK_EX);
fwrite($fi,GetServerRequestMethod());
fwrite($fi,"\t");
fwrite($fi,$pathInfo);
fwrite($fi,"\t");
fwrite($fi,$_SERVER['QUERY_STRING']);
fwrite($fi,"\n");
fflush($fi);
fclose($fi);*/

$zoom = (int)$urlExp[1];
$xtile = (int)$urlExp[2];
$tilestr = explode(".",$urlExp[3]);
$ytile = (int)$tilestr[0];

//print_r(TileToLatLon($zoom,$xtile,$ytile));

if($zoom < $minAllowedZoom)
	die("Zoom in to see map");

$outputFormat = $tilestr[1];

//echo $zoom." ".$xtile." ".$ytile." ";

$topleft = (TileToLatLon($zoom,$xtile,$ytile));
$bottomright = (TileToLatLon($zoom,$xtile+1,$ytile+1));
//print_r($topleft);
//print_r($bottomright);
//echo "\n";

function ReadGzFileData($filename)
{
	$buffer = "";
	$zh = gzopen($filename,'r') or die("can't open: $php_errormsg");
	while ($line = gzgets($zh,1024)) {
		$buffer .= $line; 
	}
	return $buffer;
}

function ReadBz2FileData($filename)
{
	$buffer = "";
	$zh = bz2open($filename,'r') or die("can't open: $php_errormsg");
	while ($line = bz2gets($zh,1024)) {
		$buffer .= $line; 
	}
	return $buffer;
}


function QueryMapApi($serverUrl, $topleft, $bottomright)
{
/*	$url = $serverUrl."microcosm.php/0.6/map?bbox=".$topleft[1].",".$bottomright[0].",".$bottomright[1].",".$topleft[0];
	//print $url;
	return (file_get_contents($url));*/
}

function TileToCachedName($pathToTiles, $zoom, $xtile, $ytile)
{
	return $pathToTiles.$zoom."/".$xtile."/".$ytile.".osm.gz";
}

function CheckParentExists($pathToTiles, $checkZoom,$zoom, $xtile, $ytile)
{
	$pos = TileToLatLon($zoom,$xtile,$ytile);
	//print_r($pos);
	$parentTile = LatLonToTile($checkZoom,$pos[0],$pos[1]);
	//print_r($parentTile);
	$parentFiName = TileToCachedName($pathToTiles, $checkZoom,$parentTile[0],$parentTile[1]);
	return file_exists($parentFiName);
}

function TileFromParent($pathToTiles, $checkZoom,$zoom, $xtile, $ytile)
{
	$pos = TileToLatLon($zoom,$xtile,$ytile);
	$parentTile = LatLonToTile($checkZoom,$pos[0],$pos[1]);
	$parentFiName = TileToCachedName($pathToTiles, $checkZoom,$parentTile[0],$parentTile[1]);
	//return gzinflate(file_get_contents($parentFiName));
	return ReadGzFileData($parentFiName);
}

function QueryMapApiCached($serverUrl, $pathToTiles, $zoom, $xtile, $ytile)
{
	global $masterZoom;
	$map = Null;
	$topleft = (TileToLatLon($zoom,$xtile,$ytile));
	$bottomright = (TileToLatLon($zoom,$xtile+1,$ytile+1));
	//echo $zoom;

	$ageThreshold = 60;	

	$cachedName = TileToCachedName($pathToTiles, $zoom, $xtile, $ytile);
	/*if(file_exists($cachedName))
	{
		//If the file is more than a threshold in age, delete it
		$age = time() - filemtime($cachedName);
		if ($age > $ageThreshold)
		{
			unlink($cachedName);
			clearstatcache(TRUE,$cachedName);
		}
	}*/

	//print $cachedName;
	//If result is cached, return it
	if(file_exists($cachedName))
	{
		//Return cached file
		//if ($age <= $ageThreshold)
		//return gzinflate(file_get_contents($cachedName));
		return ReadGzFileData($cachedName);
	}

	//Generate cached child tile filenames
	$cachedNameTl = ($zoom+1)."/".($xtile*2)."/".($ytile*2).".osm.gz";
	$cachedNameTr = ($zoom+1)."/".($xtile*2+1)."/".($ytile*2).".osm.gz";
	$cachedNameBl = ($zoom+1)."/".($xtile*2)."/".($ytile*2+1).".osm.gz";
	$cachedNameBr = ($zoom+1)."/".($xtile*2+1)."/".($ytile*2+1).".osm.gz";

	//If lower zoom than master zoom, try to trigger generation of child tiles
	if($zoom < $masterZoom)
	{
		$countTriggered = 0;
		if(!file_exists($pathToTiles.$cachedNameTl)) 
		{
			//echo "Trigger create ".($zoom + 1).",".($xtile*2).",".($ytile*2)."\n";
			QueryMapApiCached($serverUrl, $pathToTiles, $zoom + 1, $xtile*2, $ytile*2);
			$countTriggered ++;
		}
		if(!file_exists($pathToTiles.$cachedNameTr) and $countTriggered == 0) 
		{
			//echo "Trigger create ".($zoom + 1).",".($xtile*2+1).",".($ytile*2)."\n";
			QueryMapApiCached($serverUrl, $pathToTiles, $zoom + 1, $xtile*2+1, $ytile*2);
			$countTriggered ++;
		}
		if(!file_exists($pathToTiles.$cachedNameBl) and $countTriggered == 0) 
		{
			//echo "Trigger create ".($zoom + 1).",".($xtile*2).",".($ytile*2+1)."\n";
			QueryMapApiCached($serverUrl, $pathToTiles, $zoom + 1, $xtile*2, $ytile*2+1);
			$countTriggered ++;
		}
		if(!file_exists($pathToTiles.$cachedNameBr) and $countTriggered == 0) 
		{
			//echo "Trigger create ".($zoom + 1).",".($xtile*2+1).",".($ytile*2+1)."\n";
			QueryMapApiCached($serverUrl, $pathToTiles, $zoom + 1, $xtile*2+1, $ytile*2+1);
			$countTriggered ++;
		}
	}

	//Check if we can assemble it from higher level (more zoomed)
	//clearstatcache(TRUE,$pathToTiles.$cachedNameTl);
	//clearstatcache(TRUE,$pathToTiles.$cachedNameTr);
	//clearstatcache(TRUE,$pathToTiles.$cachedNameBl);
	//clearstatcache(TRUE,$pathToTiles.$cachedNameBr);
	//echo "(".file_exists($pathToTiles.$cachedNameTl)." ".file_exists($pathToTiles.$cachedNameTr)." ".file_exists($pathToTiles.$cachedNameBl)." ".file_exists($pathToTiles.$cachedNameBr).")";
	$partsExist = (file_exists($pathToTiles.$cachedNameTl) and file_exists($pathToTiles.$cachedNameTr) and file_exists($pathToTiles.$cachedNameBl) and file_exists($pathToTiles.$cachedNameBr));
	if($partsExist)
	{
		//echo "(".file_exists($pathToTiles.$cachedNameTl)." ".file_exists($pathToTiles.$cachedNameTr)." ".file_exists($pathToTiles.$cachedNameBl)." ".file_exists($pathToTiles.$cachedNameBr).")";
		$map = MergeOsm(array($pathToTiles.$cachedNameTl,$pathToTiles.$cachedNameTr,$pathToTiles.$cachedNameBl,$pathToTiles.$cachedNameBr));
		//echo $map;
	}

	//Check if we can assemble it from lower level (less zoomed)
	if($zoom > $masterZoom)
	{
		$exists = CheckParentExists($pathToTiles, $masterZoom,$zoom, $xtile, $ytile);
		if($exists)
			return TileFromParent($pathToTiles, $masterZoom,$zoom, $xtile, $ytile);
	}

	//If not at master zoom, return
	if($map === Null and $zoom == $masterZoom)
	{
		//echo $zoom;
		//Query API to get map
		/*$url = $serverUrl."microcosm.php/0.6/map?bbox=".$topleft[1].",".$bottomright[0].",".$bottomright[1].",".$topleft[0];
		//print $url;
		$map = file_get_contents($url);
		//echo $map;*/
	}

	//Save map to cache
	/*if($map !== Null and strlen($map)>0)
	{
		if(!file_exists("tiles/".$zoom)) mkdir("tiles/".$zoom);
		if(!file_exists("tiles/".$zoom."/".$xtile)) mkdir("tiles/".$zoom."/".$xtile);
		$fi = fopen($cachedName,"wb");
		fwrite($fi,bzcompress($map));
		fflush($fi);
		fclose($fi);
	}*/
	return ($map);
}

function Project($lat,$lon,$bbox,$granuality)
{
	//echo $lat.",".$lon."<br/>\n";
	//print_r($bbox);
	//echo "<br/>\n";
	$xfrac = ($lon - $bbox[0]) / ($bbox[2] - $bbox[0]);
	$yfrac = ($lat - $bbox[1]) / ($bbox[3] - $bbox[1]);
	//echo $xfrac.",".$yfrac."<br/>\n";
	return array(round($xfrac * $granuality),round($yfrac * $granuality));
}

function OsmToJosn($mapStr,$bbox,$granuality,&$featureList)
{
$xml = new SimpleXMLElement($mapStr);
$nodePositions = array();
//Get positions of each node
foreach($xml->node as $node)
{
	$id = (int)$node['id'];
	$nodePositions[$id] = array((float)$node['lat'],(float)$node['lon']);
}
//print_r($nodePositions);

//Process ways and create JOSN objects
foreach($xml->way as $way)
{
	$properties = array();
	foreach($way->tag as $tag)
		$properties[(string)$tag['k']] = (string)$tag['v'];
	if(count($properties)==0) continue;
	$coordinates = array();
	$sumx = 0.;
	$sumy = 0.;
	$count = 0;
	$firstRef = Null;
	foreach($way->nd as $nd)
	{
		$ref = (int)$nd['ref'];
		//echo $ref."<br/>\n";
		if($firstRef === Null) $firstRef = $ref;
		if (!isset($nodePositions[$ref])) continue;
		$node = $nodePositions[$ref];
		list($x,$y) = Project($node[0],$node[1],$bbox,$granuality);
		//echo $x.",".$y."<br/>\n";
		array_push($coordinates,array($x,$y));
		$sumx += $x;
		$sumy += $y;
		$count ++;
	}
	//echo ($ref === $firstRef)."<br/>\n";
	$isArea = ($ref === $firstRef);
	if(isset($properties['area']) and $properties['area'] == "yes") $isArea = 1;
	if(isset($properties['area']) and $properties['area'] == "no") $isArea = 0;

	if (isset($properties['natural']) and $properties['natural'] == 'coast')
	{
		
		//$coordinates = array_reverse($coordinates); 
	}

	//Create JSON object
	$obj = array();
	$obj['reprpoint'] = array(round($sumx/$count),round($sumy/$count));
	if($isArea)
	{
		$obj['type'] = "Polygon";
		$obj['coordinates'] = array($coordinates);
	}
	else 
	{
		$obj['type'] = "LineString";
		$obj['coordinates'] = $coordinates;
	}
	$obj['properties'] = $properties;
	//$obj['properties'] = array();
	
	array_push($featureList,$obj);

}

//Process nodes and create JOSN objects
foreach($xml->node as $node)
{
	$properties = array();
	foreach($node->tag as $tag)
		$properties[(string)$tag['k']] = (string)$tag['v'];
	if(count($properties)==0) continue;

	//Create JSON object
	$obj = array();
	$obj['type'] = "Point";
	$obj['properties'] = $properties;
	//$obj['properties'] = array();
	list($x,$y) = Project((float)$node['lat'],(float)$node['lon'],$bbox,$granuality);
	$obj['coordinates'] = array((int)$x,(int)$y);
	//$obj['reprpoint'] = array((float)$node['lat'],(float)$node['lon']);

	array_push($featureList,$obj);

}
}

//**** Output in OSM format ******

if($outputFormat=="osm")
{
$map = QueryMapApiCached($serverUrl, $pathToTiles, $zoom, $xtile, $ytile);

if($map === Null)
{
	header('HTTP/1.1 413 Request Entity Too Large');
}
else
{
	header ("Content-Type:text/xml");
	echo $map;
}
}


function GetTileWithMessage($bbox,$granuality)
{
$featureList = array();
/*$obj = array();
$obj['type'] = "Polygon";
$obj['coordinates'] = array(array(array(0,$granuality),array($granuality,$granuality),array($granuality,0),array(0,0)));
$obj['properties'] = array('natural'=>'coastline');
array_push($featureList,$obj);*/

$obj = array();
$obj['type'] = "LineString";
$obj['coordinates'] = array(array(0,$granuality),array($granuality,0));
$obj['properties'] = array('highway'=>'primary');
array_push($featureList,$obj);

$obj['coordinates'] = array(array(0,0),array($granuality,$granuality));
array_push($featureList,$obj);

return $featureList;
}

//**** Output in Kothic JS Josn format ******

if($outputFormat=="js")
{
$featureList = array();
$bbox = array($topleft[1],$bottomright[0],$bottomright[1],$topleft[0]);
$granuality = 10000;

//Adding coastline object

#{"type":"Polygon","properties":{"natural":"coastline"},"coordinates":[[[0,0],[0,10000],[10000,10000],[10000,0],[0,0]]]}
$obj = array();
$obj['type'] = "Polygon";
$obj['coordinates'] = array(array(array(0,$granuality),array($granuality,$granuality),array($granuality,0),array(0,0)));
$obj['properties'] = array('natural'=>'coastline');
array_push($featureList,$obj);

//$mapStr = gzinflate(file_get_contents("sharm.osm.bz2"));
$mapStr = QueryMapApiCached($serverUrl, $pathToTiles, $zoom, $xtile, $ytile);

if($mapStr === Null)
{
	//header('HTTP/1.1 413 Request Entity Too Large');

	$featureList = GetTileWithMessage($bbox,$granuality);
	#header('Content-type: application/json');
	header('Content-type: text/plain');
	$base = array("features" => $featureList, "bbox" => $bbox, "granularity"=> $granuality);
	echo "onKothicDataResponse(".json_encode($base).",".$zoom.",".$xtile.",".$ytile.");\n";
	exit(0);
}

OsmToJosn($mapStr,$bbox,$granuality,$featureList);

#header('Content-type: application/json');
header('Content-type: text/plain');

$base = array("features" => $featureList, "bbox" => $bbox, "granularity"=> $granuality);

echo "onKothicDataResponse(".json_encode($base).",".$zoom.",".$xtile.",".$ytile.");\n";
//$test = json_decode(file_get_contents("test2.js"));
//$ret = "onKothicDataResponse(".json_encode($test).",".$zoom.",".$xtile.",".$ytile.");";
//echo $ret;

}

?>
