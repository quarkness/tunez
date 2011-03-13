<?php
# download.php
# 
# Allows users to download song files

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
 
require("tunez.inc.php");
require_once($_CONF['path_system'] . "classes/song.class.php");

if (empty($_SESSION['perms']['p_download'])) {
    die("You do not have permission to download, fool");
}
if (empty($_GET['song_id'])) {
    die("fatal error");
}
$song_id = (int) $_GET['song_id'];

$song = new song($song_id, NULL);
$song->read_data_from_db("filename");
$client_filename = $song->get_filename_no_path();

header("Content-type: audio/mpeg");
header("Content-Disposition: attachment; filename=$client_filename");
readfile($song->filename);

?>
