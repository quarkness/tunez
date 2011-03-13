<?php
# playlist.php
#
# Improved playlist

require("tunez.inc.php");
$title = "Playlist";
require("header.inc.php");

$totalQueueTime = 0;

if($_SESSION['perms']['p_unvote']) {
    $allowed_unvotes = allowed_unvotes($_SESSION['user_id']);
}

if(($_SESSION['perms']['p_vote']))
{
    echo "<form name=\"playlist_songs\" action=\"" . $_CONF['url_admin'] . 
        "admin_edit_record.php\" method='get'>";
    $playlist_count = 0;
}

$votes = Array();
$who = Array();
$query = "SELECT song_id, user FROM queue, users WHERE queue.user_id = users.user_id ORDER BY user";
$result = tunez_query($query);
while($row = mysql_fetch_array($result))
{
    $i = $row['song_id'];
    if(empty($votes[$i]))
    {
        $votes[$i] = 0;
    }
    ++$votes[$i];
    if(!empty($who[$i]))
    {
        $who[$i] .= ", ";
    }
    $who[$i] .= $row['user'];
}
mysql_free_result($result);	

$query = "SELECT TIME_TO_SEC(length) AS seconds, priority_queue.song_id, artist_name, songtitle
    FROM priority_queue LEFT JOIN songs ON priority_queue.song_id=songs.song_id
    LEFT JOIN artists ON songs.artist_id = artists.artist_id";
$result = tunez_query($query);

echo "<TABLE>";
echo "<TR>";
$action = (($_SESSION['perms']['p_unvote']) OR ($_SESSION['perms']['p_vote']));
if($action)
{
    echo "<TH>Action</TH>";
}
echo "<TH>Votes</TH>";
echo "<TH>Artist</TH>";
echo "<TH>Title</TH>";
echo "<TH>Length</TH>";
echo "</TR>";
while($row = mysql_fetch_array($result)) {
    $totalQueueTime += $row['seconds'];
    $i = $row['song_id'];
    echo "<TR>";
    if($action) {
        echo "<TD>";
        if(($_SESSION['perms']['p_unvote']) AND (in_array($i, $allowed_unvotes))) {
            echo "<a href=\"" . $_CONF['url'] . "vote.php?song_id=" . $i . "&action=unvote\">
            <img border=0 src=\"" . $_CONF['url_images'] . "unvote.png\"></img></a>";
        }
        else if(($_SESSION['perms']['p_vote'])) {
            $playlist_count++;
            echo "<a href=\"" . $_CONF['url'] . "vote.php?song_id=" . $i . "&action=vote\">
            <img border=0 src=\"" . $_CONF['url_images'] . "vote.png\"></img></a>";
            echo "<input type=\"checkbox\" name=\"edit_entry_" . $playlist_count . "\" value=\"" . $i . "\">";
        }
        echo "</TD>";
    }
    echo "<TD>(" .  $votes[$i] . ")&nbsp;" . $who[$i] .  "</TD>";
    echo "<TD>" . $row['artist_name'] . "</TD>";
    echo "<TD><a href=\"" . $_CONF['url'] . "who.php?song_id=" . $i . 
        "\" class=\"menu\">" . $row['songtitle'] . "</a></TD>";
    echo "<TD>" . display_time($row['seconds']) . "</TD>";
    echo "</TR>";
}
echo "</TABLE><BR>";
echo "Total Queue Time (" . display_time($totalQueueTime) . ")<br><br>";
mysql_free_result($result);	

if($_SESSION['perms']['p_vote']) {
    echo "<input type=\"hidden\" name=\"num_entries\" value=\"" . $playlist_count . "\">";
    if($playlist_count) {
        echo "<input type=\"hidden\" name=\"checkbox_action\" value=\"vote\">";
    }
    echo "<input type=\"submit\" value=\"Vote on checked\"><br>";
    echo "</form>";
}

require("footer.inc.php");
?>
