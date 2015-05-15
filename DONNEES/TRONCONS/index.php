<?php

$d = json_decode(file_get_contents("vcpogg.json"),true);
echo count($d["features"])."\n";
echo memory_get_usage()."\n";
?>
