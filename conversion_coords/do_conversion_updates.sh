#!/bin/bash

input_updates="input/arronds/to_update"
input_old="input/arronds/old"
output="output"
output_updates="output/to_update"

echo "Do conversions on jsons file that needs to be in the db because they are updates"
no_files=true
ls $input_updates | while read file; do
	no_files=false
	echo "File : $file"
	php convert.php "$input_updates/$file" MTM_8 > /dev/null
	
	mv "$output/$file" "$output_updates"
	echo "Coords conversion output : $output_updates/$file"


	echo "Moving $input_updates/$file to $input_old"
	mv "$input_updates/$file" $input_old

done
