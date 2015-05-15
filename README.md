# park-montreal
Application web (et mobile prochainement) qui permet de trouver les places de stationnement autorisées dans un moment donné dans les rues les plus proches (tient en compte les restrictions de stationnement indiquées dans les panneaux)

Pour tester l'appli : http://adam.cherti.name/stationnement

Les données que j'ai utilisé sont disponibles ici : http://donnees.ville.montreal.qc.ca/dataset/stationnement-sur-rue-signalisation-courant
Elles contiennent tous les poteaux (leur géolocalisation) et leur panneaux de chaque arrondissement de Montréal

Malheureusement, les données des parcomètres ne sont pas ouvertes pour le moment et on ne peut pas savoir si un parc de stationnement n'a plus de places ou non.

# Description des fichiers 

~~Front-end :~~
/application : c'est l'application en html5 qui affiche les places de stationnement autorisés/interdits

~Back-end :~
~/get~ :* l'application envoie ses requêtes vers les scripts de ce dossier qui retournent les poteaux, les tronçons et les informations de stationnement proches d'une position GPS

~/geojson_to_db~ : ce dossier contient le script "json_to_db.php" qui *exporte* les fichiers *geojson* (et aussi json) vers une table de *la base de données*

~/geojson_to_db/JParser~ : contient un *parseur JSON* qui parse *sans charger le fichier dans la RAM* entièrement afin de parser de gros fichiers JSON sans problème de memory limit en PHP

~/conversion_coords~ : script en php qui convertit les coordonées d'un fichier json du système de coordonées du Québec (*MTM fuseau 8* mais supporte aussi n'importe quel MTM/UTM) *vers le système international WGS-184* car les fichiers json fournis par la ville contiennent des coordonées en MTM 8

~/Signalisation descrip panneau~ : contient le fichier json (téléchargé depuis le portail données ouvertes) qui liste tous *les panneaux* affichés dans les poteaux (exemple : Stationnement interdit de 15h à 23h) et le texte inscrit ainsi que leur code d'identification (un code du type RS-AD)

~/UPDATE_SCRIPTS~ : contient les scripts qui mettent à jour la base de données mysql locale à partir des fichiers json des données ouvertes s'il y a un changement
