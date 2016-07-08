<?php
$path=explode("/",__FILE__);
$p2 = "";
for($i=1;$i<count($path)-2;++$i) {
  $p2 .="/";
  $p2 .= $path[$i]; 
}
$p2 .= "/include";

set_include_path(get_include_path() . PATH_SEPARATOR . $p2 );
