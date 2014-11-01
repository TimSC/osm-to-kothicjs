<?php

function CloneElement($from,$to)
{
	//Clear destination children

	//Clear destination attributes
	
	//Copy attributes
	foreach ($from->attributes() as $k => $v)
	{
		//echo $k." ".$v."\n";
		//fwrite($debug,$type." ".$value['id']." ".$k." ".$v."\n");
		$to[$k] = $v;
	}
	//Copy children
	foreach ($from as $k => $v)
	{
		$tov = $to->addChild($k);
		CloneElement($v, $tov);
	}
}

function MergeOsm($files)
{

$nodes = array();
$ways = array();
$relations = array();


foreach($files as $fname)
{
//echo $fname;
if(!file_exists($fname)) throw new Exception("Input file ".$fname." does not exist.");
$mapStr = bzdecompress(file_get_contents($fname));
if(strlen($mapStr)==0) throw new Exception("Loaded cached map has zero size.");
$xml = new SimpleXMLElement($mapStr);

foreach($xml->node as $o)
	$nodes[(int)$o['id']] = $o;
foreach($xml->way as $o)
	$ways[(int)$o['id']] = $o;
foreach($xml->relation as $o)
	$relations[(int)$o['id']] = $o;

}

$outXml = new SimpleXMLElement('<osm version="0.6" generator="Microcosm"></osm>');
foreach($nodes as $k => $v)
{
	$newchild = $outXml->addChild("node","");
	CloneElement($v,$newchild);
}
foreach($ways as $k => $v)
{
	$newchild = $outXml->addChild("way","");
	CloneElement($v,$newchild);
}
foreach($relations as $k => $v)
{
	$newchild = $outXml->addChild("relation","");
	CloneElement($v,$newchild);
}
return $outXml->asXML();

}

?>
