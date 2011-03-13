<?php
# database.inc.php
#
# Includes some deprecated code for database manipulation which is being moved to
# the artist and album classes

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

function obsolete_album_id($old_album_id) {
        # Check to see if album entry is no longer needed
        $query = "select song_id from songs where album_id='$old_album_id'";
        $result = tunez_query($query) or die(mysql_error());
        if (mysql_num_rows($result)==0) {
                # Delete old album entry
                $query = "delete from artists where artist_id='$old_artist_id'";
                $result = tunez_query($query) or die(mysql_error());
        }
}

function obsolete_artist_id($old_artist_id) {
        # Check to see if artist entry is no longer needed
        $query = "select song_id from songs where artist_id='$old_artist_id'";
        $result = tunez_query($query) or die(mysql_error());
        if (mysql_num_rows($result)==0) {
                # Delete old artist entry
                $query = "delete from artists where artist_id='$old_artist_id'";
                $result = tunez_query($query) or die(mysql_error());
        }
}

function fetch_artist_id($artist_name, $create=0, $output=0) {
       
        $kweerie = "SELECT artist_id from artists where artist_name=\"" . addslashes($artist_name) . "\"";
        $result = tunez_query($kweerie) or die(mysql_error());
        $row = mysql_fetch_array($result);
        if (mysql_error())
                echo mysql_error();
        
        if (($create==1) && (mysql_num_rows($result)==0)) {
                //# create a new artist
                $kweerie = "INSERT into artists values ('',\"" . addslashes($artist_name) . "\")";
                if($output==1) 
                        print "<p>Creating a new artist in the artists table:<b>$artist_name</b><br>";
                #print "\n$kweerie<br>\n";
                tunez_query($kweerie);
                if (mysql_error())
                        echo mysql_error();
                $artist_id=mysql_insert_id();
                
        }
        else if (mysql_num_rows($result)==0) {
                return -1;
        }
        else { 
                #An artist already exists
                $artist_id=$row['artist_id'];
        }

        return $artist_id;
}

function fetch_album_id($album_name, $create=0, $output=0) {

        $kweerie = "SELECT album_id from albums where album_name=\"" . addslashes($album_name) . "\"";
        $result = tunez_query($kweerie) or die(mysql_error());
        $row = mysql_fetch_array($result);
        if (mysql_error()) 
                echo mysql_error();

        if (($create==1) && (mysql_num_rows($result)==0)) {
                //# create a new album
                if ($output==1)
                        print "<p>Creating a new album in the albums table:<b>$album_name</b><br>";
                $kweerie = "INSERT into albums values ('',\"" . addslashes($album_name) . "\", NULL, NULL, NULL)";
                tunez_query($kweerie) or die(mysql_error());
                if (mysql_error())
                        echo mysql_error();
                $album_id=mysql_insert_id();
        }
        else if (mysql_num_rows($result)==0) {
                return -1;
        }
        else { 
                #An albumid already exists
                $album_id=$row['album_id'];
        }

        return $album_id;
}


?>
