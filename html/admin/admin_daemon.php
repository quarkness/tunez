<?php
# admin_daemon.php
#
# This handles starting and stopping of the Tunez perl or php daemons

/*
 * tunez
 *
 * Copyright (C) 2003, Ivo van Doesburg <idoesburg@outdare.nl>
 *  
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

$command = "";
require("../tunez.inc.php");

if(!($_SESSION['perms']['p_daemon'])) {
    header(access_denied());
	die;
}

$action = $_GET['action'];
if ($action == "start")
{
    if ($_CONF['mode'] == "local-php" || $_CONF['mode'] == "shout-php")
    {
        $command = $_CONF['detach_binary'] . ' ' . 
            $_CONF['php_binary'] . " -d \"max_execution_time=0\" " .
            $_CONF['path'] . "tunezd.php & >/dev/null 2>/dev/null";
        $_SESSION['messageTitle'] = "Starting Tunez PHP Daemon";
        $_SESSION['messageBody'] = "";
    }
    elseif ($_CONF['mode'] == "local-perl" || $mode == "shout-perl") {
        $command = $_CONF['detach_binary'] . ' ' .
            $_CONF['perl_binary'] . ' ' .
            $_CONF['path'] . "tunezd.pl & >/dev/null 2>/dev/null";
        $_SESSION['messageTitle'] = "Starting Tunez Perl Daemon";
        $_SESSION['messageBody'] = "";
    }
    elseif ($_CONF['mode'] == "ices") {
        $command = $_CONF['detach_binary'] . ' ' .
            $_CONF['ices_binary'] . " & >/dev/null 2>/dev/null";
        chdir($_CONF['path']);
        $_SESSION['messageTitle'] = "Starting ices script";
        $_SESSION['messageBody'] = "";
    }
    else {
        die("Invalid mode " . $_CONF['mode']);
    }

    exec($command);
    sleep(1); // give mysql and mpg123 some time before refreshing page with new info
}

if ($action == "stop")
{
    if ($_CONF['mode'] == "local-php" || $_CONF['mode'] == "shout-php") {
        $prog_arr[] = "tunezd.php";
        $level[] = "KILL";

        $tmp_arr = array($_CONF['mpg123_binary'],$_CONF['ogg123_binary'],$_CONF['shoutcast_binary']);
        foreach ( $tmp_arr as $progname){
            $tmp_progname = split(" ",basename($progname));
            $prog_arr[] = $tmp_progname[0];
            $level[] = "INT";
        }
    }
    elseif ($_CONF['mode'] == "local-perl" || $_CONF['mode'] == "shout-perl") {
        $prog_arr[] =  "tunezd.pl";
        $level[] = "KILL";
        
        $tmp_arr = array($_CONF['mpg123_binary'],$_CONF['ogg123_binary'],$_CONF['shoutcast_binary']);
        foreach ( $tmp_arr as $progname){
            $tmp_progname = split(" ",basename($progname));
            $prog_arr[] = $tmp_progname[0];
            $level[] = "INT";
        }
    }
    elseif ($_CONF['mode'] == "ices") {
        $prog_arr[] =  "ices";
        $level[] = "INT";
    }
    else {
        die("bad mode");
    }
    $do_i_sleep = 1;
    $killnumber = 0;
    foreach ($prog_arr as $progname){
        $cmd = "kill -" . $level[$killnumber] . " `ps auxwww |grep " . $progname . "|awk '{print $2}'`";
        system($cmd);
        if($do_i_sleep){
            sleep(1);
            $do_i_sleep = 0;
        }
        $killnumber++;
    }
    $kweerie = "DELETE FROM np";
    mysql_query($kweerie);
 
	$_SESSION['messageTitle'] = "Stopping Tunez Daemon";
	$_SESSION['messageBody'] = "Hope it worked ;)";
}

$location = $_SERVER[HTTP_REFERER];
header("Location: $location");
?>
