<?php
# artist.class.php
#
# An artist class for manipulating artist data

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

class artist {
    var $artist_id;
    var $artist_name;

    function artist($artist_id) {
        $this->artist_id = $artist_id;
    }

    // lookup_name() tries to find a match for the provided name.
    // it returns FALSE when there are no matches or the appropriate artist_id
    function lookup_name($artist_name) {
        $kweerie = "SELECT artist_id from artists where artist_name=\"" . addslashes($artist_name) . "\"";
        $result = tunez_query($kweerie) or die(mysql_error());
        if (mysql_num_rows > 1) {
            die("Duplicate entry in artists table for $artist_name...!");
        }
        elseif (mysql_num_rows($result)==0) {
            return FALSE;
        }
        else {
            // there is a match, an artist already exists
            $row = mysql_fetch_array($result);
            $artist_id=$row[artist_id];
            return $artist_id;
        }
    }

    function delete_if_obsolete() {
        if ($this->obsolete())
            $this->remove();
    }
    
    function obsolete() {
        $query = "select song_id from songs where artist_id=$this->artist_id";
        $result = tunez_query($query);
        if (mysql_num_rows($result)==0)
            return TRUE;
        return FALSE; //else 
    }

    function remove() {
        $query = "delete from artists where artist_id=$this->artist_id";
        tunez_query($query);
    }

    function save() {
        $query = "replace into artists values ($this->artist_id, \"$this->artist_name\")";
        mysql_query($query) or die(mysql_error());
        return TRUE;
    }

    function load() {
        $query = "SELECT artist_name FROM artists WHERE artist_id=$this->artist_id";
        $result = tunez_query($query);
        if (mysql_num_rows($result) != 1) {
            return FALSE;
        }
        $row = mysql_fetch_object($result);
        $this->artist_name = $row->artist_name;
        return TRUE;
    }

}




