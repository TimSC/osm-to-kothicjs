<?php

function ReadGzFileData($filename)
{
$buffer = "";
$zh = gzopen($filename,'r') or die("can't open: $php_errormsg");
while ($line = gzgets($zh,1024)) {
	$buffer .= $line; 
}
return $buffer;
}

print ReadGzFileData('tiles/12/2040/1360.osm.gz');

?>
