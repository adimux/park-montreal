<!DOCTYPE html>
<html>
<head>	
<meta charset="utf8"/>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">

		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css">
</head>
<body>
		<nav class="navbar navbar-fixed-top" style="">
		<div class="container-fluid">
			<div class="navbar-inner">
				<a style="padding-top:5px; padding-bottom:5px" class="brand navbar-brand" href="#">
					<img class="img_brand" src="images/logo.png" height="50px" />
				</a>
				<!--		<span class="glyphicon glyphicon-menu-hamburger"></span>-->
		</div>
</nav>

<div class="container">
<br/>
<br/><br/><br/>
	<form method="post" action="add_pan.php">
Interdiction de : <br/>
<div class="radio">
  <label><input type="radio" name="type" value="S">Stationnement</label>
</div>
<div class="radio">
  <label><input type="radio" name="type" value="A">Arrêt</label>
</div>
<br/>
		Heures : (format Dxx:xx-xx:xx<br/>
		<input type="text" name="heures" /><br/>
		Jours de la semaine : (format Wx-x)<br/>	
		<input type="text" name="jours" /><br/>
		Mois : (format Ymm/dd-mm/dd)<br/>
	<input type="text" name="mois"><br/>
	Temps max de autorisé (si applicable)
	<input type="text" name="temps_max" value="0"><br/>
	<input type="hidden" name="id" value="<?php echo $_GET["id"] ?>" />
	<input type="submit" value="Ajouter le panneau" />
	</form>
</div>

</body>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>

			<script src="bts_datetimepicker/bootstrap-datetimepicker.min.js"></script>
		<script src="moment.min.js"></script>

		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

</html>
