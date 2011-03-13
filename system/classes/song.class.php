<?php
# song.class.php
#
# The song class

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

class song {
    
    // SQL variables:
    var $song_id;
    var $artist_id;
    var $songtitle;
    var $filename;
    var $timesPlayed;
    var $playInRandomMode;
    var $album_id;
    var $year;
    var $genre_id;
    var $length;
    var $track;
    var $uploader_id;
    var $type;
    var $bitrate;
    var $mode;
    var $frequency;
    var $layer;
    var $status;
    var $update_id3;
    var $filesize;
    var $small_album_cover;
    var $large_album_cover;

    var $sql_song_fields = Array(
            "song_id",
            "artist_id",
            "songtitle",
            "filename",
            "timesPlayed",
            "playInRandomMode",
            "album_id",
            "year",
            "genre_id",
            "length",
            "track",
            "uploader_id",
            "type",
            "bitrate",
            "bitrate_mode",
            "mode",
            "frequency",
            "layer",
            "status",
            "update_id3",
            "filesize"
            );

    var $sql_song_fields_for_id3s = Array(
            "artist_id",
            "songtitle",
            "album_id",
            "year",
            "genre_id",
            "length",
            "track",
            "uploader_id",
            "type",
            "bitrate",
            "bitrate_mode",
            "mode",
            "frequency",
            "layer",
            "filesize"
            );


    // non-SQL variables:
    var $root_path; // this will be one of the entries in $_CONF['dirs']
    var $path;
    var $filename_no_path;

    function song($song_id, $filename) {
        if (!empty($song_id)) {
            $this->song_id = $song_id;
        }
        if (!empty($filename)) {
            $this->filename = $filename;
        }
        return;
    }

    /*
     * add_to_db($verbose=0)
     *
     * Adds a given song to the database
     *
     * If $verbose is TRUE then information is echoed about the song
     */
    function add_to_db($verbose=0) {
        if(empty($this->track))
            $this->track="NULL";
        if(empty($this->year))
            $this->year="NULL";

        if($verbose) {
            echo "<b>Artist:</b> $this->artist_name<br>";
            echo "<b>Song Title:</b> $this->songtitle<br>";
            echo "<b>Album:</b> $this->album_name<br>";
            echo "<b>Genreid:</b> $this->genre_id <br>";
            echo "<b>Year:</b>$this->year<br>";
            echo "<b>Track:</b>$this->track<br>";
        }

        $query = "INSERT INTO songs VALUES ('', $this->artist_id," .  "\"" .
            addslashes($this->songtitle) . "\"," .  "\"" . addslashes($this->filename) . "\","
            .  "0, 1, $this->album_id, \"$this->year\", $this->genre_id, SEC_TO_TIME($this->length), $this->track,\""
            .  $this->uploader_id . "\", \"$this->type\", \"$this->bitrate\", \"$this->bitrate_mode\", \"$this->mode\","
            .  "\"$this->frequency\", \"$this->layer\", \"normal\", 0, $this->filesize)";
        tunez_query($query);
        $this->song_id = mysql_insert_id();
    }

    /*
     * delete_from_db()
     *
     * Deletes a song from the database and all record of it 
     *  being played, etc.
     *
     */
    function delete_from_db() {
        global $_CONF;
        require_once($_CONF['path_html'] . "database.inc.php");
        require_once($_CONF['path_system'] . "classes/PQueue.class.php");
        if($this->artist_id==NULL OR $this->album_id==NULL) {
            $this->read_data_from_db("songs.artist_id,songs.album_id");
        }
        obsolete_artist_id($this->artist_id);
        obsolete_album_id($this->album_id);
        $kweerie = "DELETE FROM songs WHERE song_id=$this->song_id";
        tunez_query($kweerie);
        $kweerie = "DELETE from history where song_id=$this->song_id";
        tunez_query($kweerie);
        $kweerie = "DELETE from queue where song_id=$this->song_id";
        tunez_query($kweerie);
        $queue = new PQueue;
        $queue->generate_from_votes();
    }

    function get_filename_no_path() {
        if (empty($this->filename_no_path)) {
            $this->set_path();
        }
        return $this->filename_no_path;
    }

    function valid_song_id() {
        return (bool) mysql_num_rows(mysql_query("SELECT song_id from songs where song_id=$this->song_id"));
    }

    function lookup_song_id_from_filename() {
        $query = "SELECT song_id from songs where filename='" . addslashes($this->filename) . "'";
        $result = tunez_query($query);
        if(mysql_num_rows($result) == 0) {
            return NULL;
        }
        elseif(mysql_num_rows($result) > 1) {
            die("Duplicate songs in database with filename: $this->filename");
        }
        else {
            $row = mysql_fetch_array($result);
            return $row[song_id];
        }
    }

    function lookup_genre_id_from_genre_name($genre_name) {
        $query = "SELECT * FROM genre WHERE genre_name LIKE '" . $genre_name . "'"; 
        $result = tunez_query($query); 
        if(mysql_num_rows($result) != 1)
        { 
            return NULL; 
        } 
        $row = mysql_fetch_array($result); 
        return $row['genre_id']; 
    } 

    function read_data_from_db($fields = NULL) {
        
        // if we are trying to read data with only the filename then
        // lookup the song_id
        if (empty($this->song_id)) {
            if( $result = $this->lookup_song_id_from_filename()) {
                $this->song_id = $result;
            }
            else {
                die("Unable to proceed in read_data_from_db()");
            }
        }
        
        if (empty($fields)) {
            $query = "SELECT * from songs LEFT JOIN albums on
                songs.album_id=albums.album_id LEFT JOIN artists on
                songs.artist_id=artists.artist_id LEFT JOIN genre on
                songs.genre_id = genre.genre_id WHERE song_id=$this->song_id";
        }
        else {
            $query = "SELECT $fields from songs LEFT JOIN albums on
                songs.album_id=albums.album_id LEFT JOIN artists on
                songs.artist_id=artists.artist_id LEFT JOIN genre on
                songs.genre_id = genre.genre_id WHERE song_id=$this->song_id";
        }
        if(!function_exists("tunez_query")) {
            $result = mysql_query($query);
        }
        else {
            $result = tunez_query($query);
        }
        $row = mysql_fetch_array($result);
        foreach ($row as $field => $value) {
            $this->$field = $value;
        }
        return TRUE;
    }

    function write_data_to_db($fields = NULL) {
        if ($fields == NULL) {
            $fields = $this->sql_song_fields;
        }
        elseif (!is_array($fields)) {  // if it was passed as a string
            $fields = array($fields);
        }
        $query = "UPDATE songs SET ";
        foreach ($fields as $row) {
            if ($row=="length") {
                $query .= ",$row = SEC_TO_TIME(" . $this->$row . ")";
#print $query . "<br>";
            }
            elseif ($row=="year") {
                $data = $this->$row;
                if (empty($data) || $data==0) {
                    $data = "NULL";
                }
                $query .= ", $row = $data";
            }
            elseif ($row=="track") {
                $data = $this->$row;
                if (empty($data) || $data==0 ) {
                    $data = "NULL";
                }
                $query .= ", $row = $data";
            }
            else {
                $data = $this->$row;
                $query .= ", $row = \"$data\"";
            }
        }
        // these two lines delete the first comma... nice :)
        $pos_first_comma = strpos($query, ",");
        $query = substr($query, 0, $pos_first_comma) . substr($query, $pos_first_comma+1);
        // append the song_id
        $query .= " WHERE song_id=$this->song_id";
        mysql_query($query) or die(mysql_error());
    }  


    function read_data_from_file($usetags, $fields=NULL, $alternate_filename = NULL) {
        global $_CONF;
        require_once($_CONF['path_html'] . "id3/getid3.php");
        require_once($_CONF['path_html'] . "database.inc.php");

        if(empty($this->filename)) { 
            $this->read_data_from_db("filename");
        }
        
        //if(empty($this->type)) {
        //    $this->type = "mp3";
        //}
        
        $info = GetAllFileInfo($this->filename, $this->type);
        if (!empty($info['error'])) {
            return $info['error'];
        }

        /*
         * commented out so that one-time actions don't break on a mere warning...
        if (!empty($info[warning])) {
            echo "<b>Warning: $info[warning]</b><br>";
        }
        */

        // determine common information
        
        // determines length of file in seconds
        $length = (int) $info['playtime_seconds'];
        
        // determines the owner and filesize of file
        $type = $info['fileformat'];
        if (empty($type)) {
            // If the type is not detected properly from the getid3 class
            // set it to the file extension...
            $type = substr($this->filename, -3);
        }
        $stated = stat($this->filename) or die("File is not accessible!");
        $pwuid = posix_getpwuid($stated[4]);
        $uploader_id = $pwuid['name'];
        $filesize = $stated[7];

        $bitrate = $info['audio']['bitrate'];
        $bitrate_mode = $info['audio']['bitrate_mode'];
        $frequency = $info['audio']['sample_rate'];
        $mode = $info['audio']['channelmode'];

        if (!$usetags) {
            // Don't use tags
            if (!empty($alternate_filename)) {
                $our_guesses = $this->guess_song_data($alternate_filename);
            }
            else {
                $our_guesses = $this->guess_song_data();
            }
            $artist_name = $our_guesses{'artist_name'};
            $songtitle = $our_guesses{'songtitle'};
        }
        else {
            //retrieve information based on the type of tag it has

            //pray($info);
            //
            // common to id3v1, id3v2, ogg (getid3 defaults to id3v2 
            // except for comment field (unused at this time)
            $artist_name = $info['comments']['artist'][0];
            $album_name = $info['comments']['album'][0];
            $songtitle = $info['comments']['title'][0];
            
            if ($info['fileformat']=="mp3" || $info['fileformat']=="id3") {
                //echo "it's an MP3";
                $track = $info['comments']['track'][0];
                $year = $info['comments']['year'][0];
                $genre_id = $info['comments']['genreid'][0];

                //determine layer
                $layer = $info['mpeg']['audio']['layer'];
                if ($layer="III") $layer=3;
                elseif ($layer='II') $layer=2;
                elseif ($layer='I') $layer=1;
                else $layer="NULL";

            }
            elseif($info['fileformat']=="ogg") {
                $track = $info['comments']['tracknumber'][0];
                $year = "NULL";
                $layer = "NULL";
                $genre_name = $info['comments']['genre'][0];
                if (!empty($genre_name))
                    $genre_id = $this->lookup_genre_id_from_genre_name($genre_name);
            }

            if (empty($album_name)) 
                $album_name="Unknown";
            if (empty($genre_id))
                $genre_id=255;

            if(empty($artist_name) || empty($songtitle)) {
                // if there is no Artist or Song Title, guess...
                $our_guesses = $this->guess_song_data();
                $artist_name = $our_guesses{'artist_name'};
                $songtitle = $our_guesses{'songtitle'};
            }
        }

        $artist_id = fetch_artist_id($artist_name, 1, 0);
        $album_id = fetch_album_id($album_name, 1, 0);

        if ($fields == NULL) {
            $fields = $this->sql_song_fields_for_id3s;
            // we don't want to forget about these two
            $this->artist_name = $artist_name;
            $this->album_name = $album_name;
        }
        foreach ($fields as $row) {
            $this->$row = $$row;
        }
        return 0;
    }

    function write_data_to_file() {
        global $_CONF;
        require_once($_CONF['path_html'] . "id3/getid3.php");
        require_once($_CONF['path_html'] . "id3/getid3.id3v1.php");
        require_once($_CONF['path_html'] . "id3/getid3.id3v2.php");
        require_once($_CONF['path_html'] . "database.inc.php");
        if (empty($this->type)) {
            $this->read_data_from_db("type");
        }
        
        $error=FALSE;
        if ($this->type == "mp3") {
            // MP3 writing directions
            if(!WriteID3v1($this->filename, $this->songtitle, $this->artist_name, 
                        $this->album, $this->year, "", $this->genre_id, $this->track, FALSE)) {
                $error=TRUE;
            }
            // Title
            $id3['TIT2']['encodingid'] = 0;
            $id3['TIT2']['data'] = $this->songtitle;
            // Artist
            $id3['TPE1']['encodingid'] = 0;
            $id3['TPE1']['data'] = $this->artist_name;
            // Album
            $id3['TALB']['encodingid'] = 0;
            $id3['TALB']['data'] = $this->album_name;
            //Year
            $id3['TYER']['encodingid'] = 0;
            $id3['TYER']['data'] = $this->year;
            //Track
            $id3['TRCK']['encodingid'] = 0;
            $id3['TRCK']['data'] = $this->track;
            //Genre
            $id3['TCON']['encodingid'] = 0;
            $id3['TCON']['data'] = '(' . $this->genre_id . ')';

            if(!WriteID3v2($this->filename, $id3, 3, 0, FALSE, 0, FALSE)) {
                $error=TRUE;
            }
        }
        elseif ($this->type == "ogg") {
            require_once($_CONF['path_html'] . "id3/getid3.ogg.php");
            require_once($_CONF['path_html'] . "id3/getid3.ogginfo.php");
            $comments = Array (
                    artist => $this->artist_name,
                    album => $this->album_name,
                    title => $this->songtitle,
                    tracknumber => $this->tracknumber);
            if(!OggWrite($this->filename, $comments)) {
                $error=TRUE;
            }
        }
        else {
            die("unknown file type<br>");
            $error=TRUE;
        }
        return !$error;
    }

    function guess_song_data($filename=NULL) {
        if (empty($filename)) {
            $filename = $this->filename;
        }
        $filenameSplitted = split("/", $filename);
        $filenameWithoutPath = $filenameSplitted[ sizeOf($filenameSplitted)-1];
        $splittedSong = split(" - ", $filenameWithoutPath);

        if (sizeOf($splittedSong) == 1)
        {
            // This happens when the filename is not properly formatted, try to split song with just -
            $splittedSong = split("-", $filenameWithoutPath);
        }
        $artist = $splittedSong[0];
        $songtitle = substr($splittedSong[sizeOf($splittedSong)-1], 0, -4);
        return Array(artist_name=>$artist, songtitle=>$songtitle);
    }


    function print_info($skip_detail=NULL) {
        global $_CONF;
        echo    "\n<p>\n";
        echo    "<table style=\"border: 1px solid black;\">\n";
        if (empty($skip_detail) && (!empty($this->large_album_cover) || $_SESSION['perms']['p_select_edit'])) {
            echo    "\t<tr><th>Album Art</th><td>\n";
            if (!empty($this->large_album_cover)) {
                if (!empty($this->amazon_url)) {
                    echo "\t\t<a href=\"" . htmlentities($this->amazon_url) . "\">\n";
                    echo "\t\t<img src=\"$this->large_album_cover\"></img>\n";
                    echo "\t\t</a>\n";
                }
                else {
                    echo "\t\t<img src=\"$this->large_album_cover\"></img>\n";
                }
            }
            elseif ($_SESSION['perms']['p_select_edit']) {
		if($this->album_name != "Unknown") {
                    echo "\t\t<a href=\"" . $_CONF['url'] . "albumpics.php?song_id=$this->song_id\">Get album art</a>\n";
		}
		else {
		    echo "N/A (make sure Album field is correct)";
		}
            }
            echo    "\t</td></tr>\n";
        }
        echo    "\t<tr><th>Artist</th><td>\n";
        echo    "\t\t<a href=\"" . $_CONF['url'] . "search.php?search_type=artist_name&searchFor=" .  
            urlencode($this->artist_name) . "\">$this->artist_name</a></td></tr>";
        echo    "\t<tr><th>Title</th><td>\n";
        echo    "\t\t<a href=\"" . $_CONF['url'] . 
            "songinfo.php?song_id=$this->song_id\">$this->songtitle</a></td></tr>\n";
        if ($_SESSION['perms']['p_select_edit'] && empty($skip_detail)) {
            echo "\t\t<tr><th>Filename</th><td>$this->filename</td></tr>\n";
        }
        echo "\t<tr><th>Album</th><td>\n";
        echo "\t\t<a href=\"" . $_CONF['url'] . "browse_album.php?selected_album=" . 
            urlencode($this->album_id) ."\">$this->album_name</a></td></tr>";
        echo    "\t<tr><th>Length</th>\n";
        echo    "\t\t<td>" . $this->length . "</td></tr>\n";
        if (empty($skip_detail)) {
            echo    "<tr><th>Stats</th><td>$this->type file, $this->bitrate_mode @ $this->bitrate" . 
                "bps sampled at $this->frequency" . "hz</td></tr>\n" ;
            echo    "<tr><th>Uploaded by</th><td>$this->uploader_id</td></tr>\n";
            if ($_SESSION['perms']['p_select_edit']) {
                echo    "<tr><th>Status</th><td>$this->status</td></tr>\n";
                if($this->update_id3)
                    $update_id3="Yes";
                else
                    $update_id3="No";
                echo    "<tr><th>ID3 Tag Out of Date</th><td>$update_id3</td></tr>";
            }
        }
        echo    "<tr><th>Action</th><td>";
        if ($_SESSION['perms']['p_vote']) {
            echo    "<a title=\"$this->filename\" href=\"" . $_CONF['url'] . 
                "vote.php?action=vote&song_id=$this->song_id\">" .
                "<img src=\"" . $_CONF['url_images'] . "vote.png\" border=\"0\"></a>";
        }
        if ($_SESSION['perms']['p_select_edit']) {
            echo    "<a href=\"" . $_CONF['url_admin'] . 
            "admin_edit_record.php?checkbox_action=edit&song_id=$this->song_id\" title=\"Edit\">" . 
            "<img src=\"" . $_CONF['url_images'] . "edit.png\" border=\"0\"></a> ";
        }
        echo    " <a href=\"" . $_CONF['url'] . 
            "history.php?song_id=$this->song_id\" title=\"Play History\">H </a>";
        if ($_SESSION['perms']['p_download']) {
            echo    "<a href=\"" . $_CONF['url'] . 
                "download.php?song_id=$this->song_id\" title=\"Download\"><img src=\"" . 
                $_CONF['url_images'] . "save.png\" border=\"0\"></a> ";
        }
        if ($_SESSION['perms']['p_select_edit']) {
            echo "&nbsp;&nbsp;&nbsp;<a href=\"" . $_CONF['url_admin'] . 
                "admin_db_action.php?song_id=$this->song_id&action=delete\" title=\"Delete\">" .
                "<img src=\"images/delete.png\" border=\"0\"></a>";
            echo    "| <a href=\"" . $_CONF['url_admin'] . 
                "admin_db_action.php?song_id=$this->song_id&action=hide\" title=\"Hide\">H </a>";
            echo    "| <a href=\"" . $_CONF['url_admin'] . 
                "admin_db_action.php?song_id=$this->song_id&action=readtag\" title=\"Read from ID3 tags on file\">R </a>";
            echo    "| <a href=\"" . $_CONF['url_admin'] . 
                "admin_db_action.php?song_id=$this->song_id&action=writetag\" title=\"Write ID3 tag back to file\">W </a>";
        }
        echo "</td></tr>";
        echo "</table>";
    }

    function set_path() {
        $paths = $this->determine_path();
        $this->path = $paths[path];
        $this->filename_no_path = $paths[filename_no_path];
    }

    // I think this actually exists as a standard library function in the filesystem module.. woops
    function determine_path($passed_in_filename=NULL) {
        if (empty($passed_in_filename)) {
            if (empty($this->filename)) {
                $this->get_song_data_from_db("filename");
            }
            $da_filename = $this->filename;
        }
        else {
            $da_filename = $passed_in_filename;
        }
        $matches = preg_split("/\//", $da_filename);
        for($i=0; $i < (sizeof($matches) - 1); $i++) {
            $path .= $matches[$i] . '/';
        }
        $filename_no_path = $matches[sizeof($matches)-1];
        return Array(path => $path, filename_no_path => $filename_no_path);
    }

    function determine_root_path() {
        global $_CONF;
        if(empty($this->filename)) {
            get_song_data_from_db("filename");
        }
        print "looking for $this->filename<br>";
        foreach ($_CONF['dirs'] as $directory) {
            // safe_directory is ok in regular expressions (escape the '/' which would otherwise screw up the regexp)
            $safe_directory = preg_replace("/\//", "\/", $directory);
            if (preg_match("/$safe_directory/", $this->filename)) {
                return $directory;
            }
        }
        die("audio file not found in directory arrays!");
    }

    function guess_best_path($origfilename, $override_root_path=NULL) {
        global $_CONF;
        if($override_root_path) {
            $this->root_path = $override_root_path;
        }
        if(empty($this->root_path)) {
            $this->root_path = $this->determine_root_path();
            //print "root path is $this->root_path<br>";
        }
        $new_path = $this->root_path;  // first start with the root path this is part of...
        
        if ($_CONF['distribute_with_full_paths']) {
            $new_path .= $_CONF['distribute_style'];
            $new_path = preg_replace("/%a/", $this->artist_name, $new_path);
            $new_path = preg_replace("/%n/", $this->album_name, $new_path);
            $new_path = preg_replace("/%s/", $this->songtitle, $new_path);
            $new_path = preg_replace("/%t/", $this->track, $new_path);
            $new_path = preg_replace("/%g/", $this->genre_name, $new_path);
            $new_path .= ".$this->type";
        }
        else {
            $new_path .= "/" . $origfilename;
        }
        return $new_path;
    }

    function up_to_date_id3($uptodate) {
        if($uptodate)
            $this->update_id3=0;
        else
            $this->update_id3=1;
        $this->write_data_to_db(Array("update_id3"));
    }

    function move_file($new_path, $perform_database_mods=1, $perform_delete_empty_dir=1) {
        // determine proper paths...
        if (empty($this->path) || empty($this->filename_no_path)) {
            $this->set_path();
        }
        if (is_dir($new_path)) {
            while($new_path[strlen($new_path)-1] == '/') {
                // gets rid of any trailing slashes if they specify /path/ as copy location.
                $new_path = substr("$new_path", 0, -1);
            }
            $new_path .= "/$this->filename_no_path";
        }

        // do the actual move...
        if (!(is_writable($this->filename))) {
            die("Unable to write to $this->filename so procedure has been aborted");
        }
        $this->create_directory($new_path);
        if (!(copy($this->filename, $new_path))) {
            die("Copy failed.  Cannot write to $new_path");
        }
        $old_filename = $this->filename;
        if (!(unlink($old_filename))) {
            die("couldn't delete old filename $old_filename!!");
        }
        $old_path = $this->path;  // save a copy of the old path for later...

        // clean up variables
        if (is_dir($new_path)) {
            $this->filename = $new_path . $this->filename_no_path;
            $this->path = $new_path;
        }
        else {
            $paths = $this->determine_path($new_path);
            $this->filename_no_path = $paths[filename_no_path];
            $this->path = $paths[path];
            $this->filename = $this->path . $this->filename_no_path;
        }
        //print "filename = $this->filename<br>";

        // reset information in database
        if($perform_database_mods) {
            $this->set_song_data_to_db(array("filename"));
        }

        // see if we need the old path anymore
        if($perform_delete_empty_dir) {
            $query = "SELECT song_id,filename FROM songs WHERE filename like \"$old_path%\"";
            $result = tunez_query($query);
            pray(mysql_fetch_row($result));
            if (mysql_num_rows($result) < 1) {
                // safeguard:
                delete_directory($old_path);
            }
        }
        return true;
    }

    function delete_directory($path) {
        if($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if ($file=="." OR $file=="..") continue;
                else {
                    die("There are files that still exist in $path!!!");
                }
            }
        }
        if (!(rmdir($path))) {
            die("Unable to delete directory $path");
        }
    }

    function create_directory($path,$mode = 0755) {
        if (is_dir($path)) {
            return;
        }
        $array = $this->determine_path($path);
        //print "mkpath($array[path])";
        $this->mkpath($array[path], $mode);
    }

    function mkpath($path,$mode) {
        $dirs = explode("/",$path);
        $path = $dirs[0];
        for($i = 1;$i < count($dirs);$i++) {
            $path .= "/".$dirs[$i];
            if(!is_dir($path))
                mkdir($path,$mode);
        }
    } 
}

?>
