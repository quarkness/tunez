<?php
# admin_control.php
#
# This was designed to be a web interface to control Tunez from any script that
# could make HTTP requests, however it is currently not in use.

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

die("Not in use");

include("../config.inc.php");
include($_CONF['path_system'] . "classes/PQueue.class.php");
mysql_pconnect($mysql_dbhost, $mysql_dbuser, $mysql_dbpass) or die(mysql_error());
mysql_select_db($mysql_dbname);

if(!empty($_POST[auth_pw])) {
    if($_POST[auth_pw] != $control_password) {
        die("fail");
    }
    // valid.. let's handle request...
    $queue = new PQueue();
    $queue->read();
    
    if($_POST[request] == "queue") {
        list($song_id,$is_random) = $queue->top();
        $query = "SELECT song_id,filename FROM songs WHERE song_id=$song_id";
        $result = mysql_query($query) or die("$query failed!\n" . mysql_error());
        $row = mysql_fetch_object($result);
        print "random: $is_random\nfilename: $row->filename\nsong_id: $row->song_id\n";
    }
    elseif($_POST[request] == "next") {
        $blah = $queue->dequeue();
        print "ack\n";
    }
    elseif($_POST[request] == "print") {
        $queue->generate_from_votes();
        $queue->print_queue();
    }
    elseif($_POST[request] == "set_random") {
        $queue->dequeue("set_random", $_POST[song_id]);
    }
    else {
        print "ERROR";
    }
    
    
}
else {
    die("fail");
}

?>
