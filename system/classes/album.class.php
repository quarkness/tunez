<?php
# album.class.php
# 
# An album class for manipulating albums

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

class album {
    var $album_id;
    var $album_name;
    var $small_album_cover;
    var $large_album_cover;
    var $amazon_url;

    function album($album_id) {
        $this->album_id = $album_id;
    }

    // lookup_name() tries to find a match for the provided name.
    // it returns FALSE when there are no matches or the appropriate album_id
    function lookup_name($album_name) {
        $kweerie = "SELECT album_id from albums where artist_name=\"" . addslashes($artist_name) . "\"";
        $result = tunez_query($kweerie) or die(mysql_error());
        if (mysql_num_rows > 1) {
            die("Duplicate entry in albums table for $album_name...!");
        }
        elseif (mysql_num_rows($result)==0) {
            return FALSE;
        }
        else {
            // there is a match, an album already exists
            $row = mysql_fetch_array($result);
            return $row[album_id];
        }
    }

    function delete_if_obsolete() {
        if ($this->obsolete())
            $this->remove();
    }
    
    function obsolete() {
        $query = "select song_id from songs where album_id=$this->album_id";
        $result = tunez_query($query);
        if (mysql_num_rows($result)==0)
            return TRUE;
        return FALSE; //else 
    }

    function remove() {
        $query = "delete from albums where album_id=$this->album_id";
        tunez_query($query);
    }

    function save() {
        $query = "replace into albums values ($this->album_id, \"" . addslashes($this->album_name) . "\",
            \"$this->small_album_cover\", \"$this->large_album_cover\", \"$this->amazon_url\")";
        mysql_query($query) or die(mysql_error());
        return TRUE;
    }

    function load() {
        $query = "SELECT * FROM albums WHERE album_id=$this->album_id";
        $result = tunez_query($query);
        if (mysql_num_rows($result) != 1) {
            //return FALSE;
        }
        $row = mysql_fetch_object($result);
        $this->album_name = $row->album_name;
        $this->small_album_cover = $row->small_album_cover;
        $this->large_album_cover = $row->large_album_cover;
        $this->amazon_url = $row->amazon_url;
        return TRUE;
    }

}




