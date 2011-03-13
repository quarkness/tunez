<?php
# browse_artist.php
#
# Lists artists.
require("tunez.inc.php");
$typical_song_select = $_CONF['typical_song_select'];
$title = "Browse by Artist";
require("header.inc.php");

if (empty($_GET['artist_id'])) {
    $query = "SELECT * from artists order by artist_name";
    $result = tunez_query($query);
    while($row = mysql_fetch_object($result)) {
        if ($_SESSION['perms']['p_select_edit']) {
            print "<a href=\"edit_artistalbum.php?artist_id=$row->artist_id\">(Edit)</a>&nbsp;&nbsp;";
        }
        print "<a href=\"browse_artist.php?artist_id=$row->artist_id\">$row->artist_name</a><br>";
    }
}
else {
    $artist_id = (int) $_GET['artist_id'];
    
    if ($_SESSION['perms']['p_select_edit']) {
        print "<b>Edit this artist name by clicking 
            <a href=\"edit_artistalbum.php?artist_id=$artist_id\">here</a></b><br>";
    }
    $query = "SELECT $typical_song_select FROM songs LEFT JOIN 
        artists ON songs.artist_id=artists.artist_id LEFT JOIN 
        albums ON songs.album_id=albums.album_id WHERE 
        artists.artist_id=$artist_id";
    $query .= sql_order($_GET['order_by'],$_GET['sort_dir']);
    listSongs($query);
}

require("footer.inc.php");
?>
