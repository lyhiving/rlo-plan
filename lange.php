<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head>
<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
<title>Online-Vertretungsplan</title>
</head><body>
<div id="header">Willkommen beim Online-Vertretungsplan der Rosa-Luxemburg-Oberschule!<br><br></div>
<?php
	// save updated data received from the form below
	if (!empty($_POST['updated_data'])) {
		file_put_contents('data.txt', stripslashes($_POST['updated_data']));
	}

	$offline_data = true;	// tell data.php that we want to see the offline data too
	include('data.php');	// show the (possibly updated) table
?>
<div id="update_form">
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
  <table border="0" cellpadding="5" cellspacing="0">
    <tr>
      <td><textarea name="updated_data" rows="20" cols="30">
<?php
	// show the raw data text
	include('data.txt');
?>
</textarea></td>
    </tr>
    <tr>
      <td><input type="submit" value=" Speichern "></td>
    </tr>
  </table>
</form>
</div>
</body></html>
