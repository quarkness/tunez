<?php
# admin_updateDb.php
#
# Searches through $path array looking for new files / marking offline old files

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

$NoRefresh = true;

clearstatcache();
if(in_array("disable_session", $_SERVER['argv'])) {
    $disable_session = true;
}
else {
    $disable_session = false;
}

require("../tunez.inc.php");
if (!($_SESSION['perms']['p_updateDb']) && !($disable_session)) {
    header(access_denied());
    return 0;
}
require($_CONF['path_html'] . "database.inc.php");

$nrOfSongsBeforeUpdate = nrOfSongs();

if (empty($_POST)) {
    // display selection for updating...
    $title = "Choose directories to update";
    require("../header.inc.php");

    print "<p>Check the boxes next to directories you want to be updated or add subdirectories
    of the directories you want to be updated.  The update script will recurse down each
    directory you specify here (so be really careful)</p>";
    print "<form method=\"POST\" action=\"$_SERVER[PHP_SELF]\">\n";
    foreach ($_CONF['dirs'] as $number => $directory) {
        htmlentities($directory);
        print "<input type=\"checkbox\" name=\"dirs-$number\" checked>$directory<br>\n";
    }
    for($i=0; $i < $_CONF['number_paths_updateDb']; $i++) {
        print "<input type=\"text\" size=40 name=\"manual-dirs-$i\"><br>\n";
    }
    print "<input type=\"submit\" value=\"Update Directories\">\n";
    print "</form>";

    require("../footer.inc.php");
}

else { 
    $title = "Updating Database";
    require($_CONF['path_system'] . "classes/song.class.php");

    $paths = array();
    $paths = decode_user_paths($_POST);
    // paths is now escaped to prevent SQL injection
    $paths = safe_paths($paths);
    $paths = prevent_duplication($paths, $_POST);

    if(empty($paths)) {
        $_SESSION['messageTitle'] = "Error!";
        $_SESSION['messageBody'] = "None of the paths you entered are valid";
        $_SESSION['messageError'] = 1;
        require("../header.inc.php");
        require("../footer.inc.php");
        return;
    }
    
    require("../header.inc.php");
    
    $offline_files = array();
    $offline_filenames = array();
    $online_files = array();
    $ignore_list = array();

    list($online_files, $crap) = database_files($paths,"status!=\"offline\"");
    
    # finds every allowed audio file in each directory and merges the array
    # returned into one massive array
    $mp3_search_results = array();
    foreach ($paths as $path) {
        $mp3_search_results = array_merge($mp3_search_results, myrecurse($path));
    }
    # get the difference between the two arrays
    $new_files = array_diff($mp3_search_results, $online_files);
    $dead_files = array_diff($online_files, $mp3_search_results);

    # process songs which are no longer on the filesystem but still in db
    while($filename = array_shift($dead_files)) {
        if (in_array($filename, $_CONF['ignore_list'])) {
            # if the file is in a special ignore list, just
            # pretend that it's not there :)
            continue;
        }
        echo "<p>Trying to figure out what to do with dead mp3 $filename <BR>";
        if ($_CONF['trim_songs']) {
            print "Song no longer exists, deleting from database</p>";
            $song = new song(NULL, $filename);
            $song->read_data_from_db();
            $song->delete_from_db();
        }
        else { //default just mark songs offline...
            print "Song no longer on filesystem, setting offline</p>";
            $kweerie = "UPDATE songs set status='offline' WHERE filename='" . addslashes($filename) . "'";
            tunez_query($kweerie);
        }
    }
    echo "<hr>";

    # now that all songs that are going to be offline have been set offline (or deleted)
    # get offline file listing from database
    list($offline_files, $offline_filenames) = database_files($paths, "status='offline'", TRUE);

    # process the new files that don't have entries in the database...
    while($filename = array_shift($new_files)) {
        if (in_array($filename, $_CONF['ignore_list'])) {
            // if the file is in a special ignore list, just pretend that it's not there
            continue;
        }
        // Now we're gonna see if the file has got an ID3 tag, if not we'll make something up
        print "Found audio file $filename<br>";

        $filename_wo_path = basename($filename);
        if (in_array($filename, $offline_files)) {
            print "Song was offline... setting back online<br>";
            $kweerie = "UPDATE songs set status='normal' where filename='" . addslashes($filename) . "'";
            tunez_query($kweerie);
            continue;
        }
        elseif ($offline_file_song_id = array_search($filename_wo_path, $offline_filenames)) {
            // a new file possibly matches a file which disappeared

            // get the filesize of the new file
            $stated = stat($filename);
            $new_filesize = $stated[7];

            // get the filesize of the offline file
            $query = "SELECT filesize,filename FROM songs where song_id=$offline_file_song_id";
            $result = tunez_query($query);
            $row = mysql_fetch_object($result);
            $offline_filesize = $row->filesize;
            $offline_filename = $row->filename;

            if ($new_filesize == $offline_filesize) {
                $query = "UPDATE songs set filename='" . addslashes($filename) . "',status=\"normal\" where song_id=$offline_file_song_id";
                tunez_query($query);
                print "The file $offline_filename has been moved to $filename<br>";
                continue;
            }
        }
        else {
            print "<p>New song found: <b>$filename</b><br>";
            $song = new Song(NULL, $filename);
            $song->read_data_from_file(TRUE);
            $song->add_to_db(1);
            echo "</p>";
        }
        echo "<hr>";
    }
    $nrOfSongsAfterUpdate = nrOfSongs();
    showBox ("Result of update", "Before the update there were $nrOfSongsBeforeUpdate songs in the database.<BR>Now there are $nrOfSongsAfterUpdate songs in the database");
    require("../footer.inc.php");
}



function decode_user_paths($post) {
    global $_CONF;
    $paths = array();
    for($i=0; $i < $_CONF['number_paths_updateDb']; $i++) {
        if (!empty($post["manual-dirs-$i"])) {
            if (!get_magic_quotes_gpc()) {
                $paths[] = addslashes($post["manual-dirs-$i"]);
            }
            else {
                $paths[] = $post["manual-dirs-$i"];
            }
        }
    }
    return $paths;
}

function safe_paths($user_paths) {
    global $_CONF;
    if (!$_CONF['number_paths_updateDb']) {
        // They shouldn't be able to do this...
        return array();
    }
    $ok_paths = array();
    foreach ($user_paths as $user_path) {
        foreach ($_CONF['dirs'] as $config_path) {
            //print "checking strpos($user_path, $config_path)<br>";
            if (strpos($user_path, $config_path) !== FALSE) {
                $ok_paths[] = $user_path;
                //print "safe<br>";
                break;
            }
        }
    }
    return $ok_paths;
}

function prevent_duplication($user_paths, $post) {
    global $_CONF;
    $config_paths = array();
    foreach ($_CONF['dirs'] as $number => $directory) {
        if ($post["dirs-" . $number]=="on") {
            $config_paths[] = $directory;
        }
    }
    // make sure that the user specified paths aren't subsets of a checked configed path
    foreach ($user_paths as $user_path) {
        foreach ($config_paths as $config_path) {
            if (strpos($user_path, $config_path) !== FALSE) { // if the config'd path is part of a user path
                $key = array_search($user_path, $user_paths);
                unset($user_paths[$key]);
                break;
            }
        }
    }
    return array_merge($user_paths, $config_paths);
}
            
function database_files($paths, $where_conditions, $save_filenames=FALSE) {
    global $_CONF;
    $files = array();
    $filenames = array();
    $sql_option = "AND (";
    $firstoption = true;

    foreach ($paths as $path) {
        if (!$firstoption) {
            $sql_option .= " OR ";
        }
        else {
            $firstoption = false;
        }
        $sql_option .= " filename like \"$path%\" ";
    }
    $sql_option .= " )";

    # get online file listing from database
    $kweerie = "SELECT filename from songs WHERE $where_conditions $sql_option ORDER BY filename";
    $result = tunez_query($kweerie);
    while ($row = mysql_fetch_object($result))
    {
        array_push($files, $row->filename);
        if ($save_filenames) {
            $filenames[$row->song_id] = basename($row->filename);
        }
    }
    mysql_free_result($result);
    if ($save_filenames) {
        return array($files, $filenames);
    }
    else {
        return array($files, NULL);
    }
    
}

# myrecurse($path)
# Takes a path and recurses through file list and looks for all files with
# .mp3 or .ogg at the end and returns them as an array
function myrecurse($path) {
    global $_CONF;
    $filelist = array();
    if($handle = opendir($path)) {
        while (false !== ($file = readdir($handle))) {
            if ($file=="." OR $file=="..") continue;
            elseif (is_dir($path . "/" . $file)) {
                //print "is directory: $path/$file<br>\n";
                $filelist = array_merge($filelist, myrecurse($path . "/" .  $file));
            }
            elseif (is_file($path . "/" . $file)) {
                //print "we have file: $path/$file<br>\n";
                foreach ($_CONF['valid_extensions'] as $extension) {
                    if(eregi("\.$extension", substr($path . "/" . $file, -1 - strlen($extension) )))
                    {
                        // the file has a valid extension
                        //print "<b>VALID FILE</b><br>";
                        array_push($filelist, $path . '/' . $file);
                    }
                    else {
                        //print "<b>Invalid FILE!!!</b><br>";
                    }
                }
            }
        }
        closedir($handle);
    }
    else {
        print "Unable to read directory $path<br>";
    }
    return $filelist;
}

?>
