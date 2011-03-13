<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<title>Tunez -  <?php echo $title;?></title>
	<meta http-equiv="pragma" content="no-cache" />
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
    <META http-equiv="Content-Script-Type" content="text/javascript" />
	<?php

    $reload_fuzz = 5;
    $secondsLeft = timeLeft();
    // If there is no song playing timeLeft returns -5000 which results in
    // no meta refresh being set
    if (empty($NoRefresh)) {
        $location = $_SERVER['REQUEST_URI'];
        if($secondsLeft > -5 && $secondsLeft <= 3) {
            $secs_to_refresh=5;
        }
        elseif($secondsLeft >= 3) {
            $secs_to_refresh = &$secondsLeft + $reload_fuzz;
        }
        if (isset($secs_to_refresh)) {
            echo "<meta http-equiv=\"Refresh\" content=\"$secs_to_refresh; url=$location\" />";
        }
    }
?>
    <link rel="stylesheet" type="text/css" 
        href="<?php echo $_CONF['url'] . "layout/" . $_CONF['theme'] . "/tunez.css.php\""; ?>>

 </head>
<body id="body">

<div id="tunezimage">
    <a href="<?=$_CONF['url']?>index.php"><img src="<?=$_CONF['url']?>tunez.gif" alt="Tunez!" border=0 /></a>
</div>

<div class="content">
	<h1><?php echo "$title"; ?></h1>
<?php
if(!empty($_SESSION['messageTitle']))
        showBox($_SESSION['messageTitle'], $_SESSION['messageBody']);
?>
