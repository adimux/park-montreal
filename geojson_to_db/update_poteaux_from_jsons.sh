#!/bin/bash

to_update="../conversion_coords/output/to_update"
old_dir="../conversion_coords/output/old"

ls $to_update/*.json | while read file; do

echo "File : $file"
echo -n "Exporting it to table 'poteaux'... (standard output redirected to file 'result') "
php json_to_db.php --geojson 1 --conf adimux.conf --table poteaux -f $file > result 2>&1
echo "done"
echo "Moving the file to old dir : $old_dir"
mv "$file" "$old_dir"

echo
done
