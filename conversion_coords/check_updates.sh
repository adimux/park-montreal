#!/bin/bash

tmp_dir="tmp"
input_old="input/arronds/old"
input_updates="input/arronds/to_update"
output_updates="input/arronds/to_update"

function clean_tmp
{
	rm $tmp_dir/*
}

if test ! -d $tmp_dir ;then
	mkdir $tmp_dir
	if [ "$?" != "0" ]; then
		echo "erreur dans la création du dossier tmp"
		exit 1
	fi
fi

cat poteaux_geoloc_liens | while read line; do
arrond=$(echo $line | cut -d^ -f 1 )
lien=$(echo $line | cut -d^ -f 2 )

clean_tmp

echo "Quartier : $arrond"
echo "Téléchargement de $lien vers $tmp_dir/geoloc.zip"
wget --quiet -S "$lien" -O $tmp_dir/geoloc.zip > /dev/null 2>&1
echo "Extraction de l'archive dans $tmp_dir"
unzip -u $tmp_dir/geoloc.zip  -d $tmp_dir
file=$(basename $(ls $tmp_dir/*.json | head) )
#$file est le fichier json qui a été extracté dans tmp/

echo "Fichier json : $file"
declare -i taille_old
declare -i taille_new

if test -f "$input_old/$file"; then 
	taille_old=$(du "$input_old/$file" | cut -f 1)
else
	taille_old=0
fi
echo "Taille (old) : $taille_old"

taille_new=$(du $tmp_dir/$file | cut -f 1)
echo "Taille new : $taille_new"

if [ $taille_new != $taille_old ]; then
	echo "needs an update"
	echo "Copie dans $input_updates"
	cp "$tmp_dir/$file" "$input_updates/"
fi
done

