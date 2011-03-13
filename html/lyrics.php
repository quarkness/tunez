<?php
# lyrics.php
#
# If this still works, i'll be amazed

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
die("Not supported at this time...");
include ("tunez.inc.php");

$kweerie	= "SELECT * from songs WHERE song_id=$song_id";
$result		= mysql_query($kweerie);
$row		= mysql_fetch_array($result);
$song_id	= $row[song_id];
$artist		= $row[artist];
$songtitle	= $row[songtitle];

$title = "Lyrics to $artist - $songtitle";
include ("header.inc.php");

echo "<a href=\"vote.php?action=vote&song_id=$song_id\">Vote for this song</a><br>";
// check if song exists in local database

$kweerie	= "SELECT * from lyrics WHERE song_id=$song_id";
$result		= mysql_query($kweerie);
$row		= mysql_fetch_array($result);
$lyrics		= $row[lyrics];



if (mysql_num_rows($result) > 0)
{
	echo "<!--Song lyrics exists in database\n\n-->";
	echo $lyrics;
}
else
{
	$query = "$artist - $songtitle";
	$query = ereg_replace (" ", "+", $query);
	$query = ereg_replace ("'", "+", $query);

	$lyrics_url = "http://www.purelyrics.com/plugin/en/plugin.php?title=1.+$query+-+Winamp&userlyrics=1";
	$fp_lyrics = fopen($lyrics_url,"r");
	echo "\n\n<!-- $lyrics_url -->\n\n";
//	echo "<br><Br>$lyrics_url<br><Br>";

	$x = 0;

	while (!feof ($fp_lyrics)) 
	{
		$line[$x] = fgets($fp_lyrics, 4096);
		$x++;
	}

	$start_of_lyrics = findinarray($line, "<font class=\"lyrics\">");
	$end_of_lyrics = findinarray($line, "javascript:MM_openBrWindow");

	for ($i=$start_of_lyrics ; $i < $end_of_lyrics ; $i++)
	{
		$lyrics .= $line[$i];
	}

//	$contents = fread ($fp_lyrics, 60000);

//	$lyrics = substr($contents, 1297, (strlen($contents)-1471));
	echo $lyrics;
	$lyrics = addslashes($lyrics);
	$sql = "insert into lyrics values ($song_id,'$lyrics')";
	if (!stristr($lyrics, "no match"))
	{
		mysql_query($sql);
	}
	else
	{
		echo "Not found.";
	}
}
include ("footer.inc.php");

?>
