<?php
# index.php
#
# The Tunez Frontpage

require("tunez.inc.php");
$title = "tunez";
require("header.inc.php");
require_once($_CONF['path_system'] . "classes/song.class.php");


echo "<h4>Some Stats</h4>";
$nrOfSongs = nrOfSongs();
echo "There are $nrOfSongs songs in the database";

if (empty($_SESSION['user_id'])) {
    echo "<h4>Who are you?</h4>";
    echo "<p>Tunez doesn't know who you are. If you haven't used Tunez before,
    this might be a good time to <a href=signup.php class=dik>Sign up!</a> to
    get an account. You need it to vote.<BR><BR>If you already have an
    account, sign in using the form on the right</p>";
}

if (preg_match("/shout/i", $_CONF['mode']) || preg_match("/ices/i", $_CONF['mode'])) {
    echo "<h4>Icecast information</h4>";
    echo "<p>This installation is set up to use streaming with icecast.<BR>";
    echo "Click here to connect your audio player to the icecast server: ";
    echo "<a href=\"" . $_CONF['url'] . "icecast.php\">" .
        $_CONF['icecast_URL'] . "</a></p>";
}

echo "<p>Here are 10 random songs for your pleasure...</p>";

$randoms = random_song_ids(10);
if (sizeof($randoms) > 0) {
    $choices = implode(" OR song_id =", $randoms);
    
    $kweerie	= "SELECT " . $_CONF['typical_song_select'] . " FROM songs
                   LEFT JOIN artists on songs.artist_id=artists.artist_id
		   LEFT JOIN albums on songs.album_id=albums.album_id
		   WHERE ( song_id=$choices ) AND $show_active
		   ORDER BY RAND() LIMIT 10";

    listSongs($kweerie);
}
?>
<p>
For updates, info etc, go to <a href="http://tunez.sourceforge.net" class="dik">tunez.sourceforge.net</a>
</p>
<?php
require("footer.inc.php");
?>
