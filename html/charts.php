<?php
# charts.php
# 
# Displays a variety of charts for the user

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
$title = "Charts";
require("header.inc.php");

$variablesToSet = array ("useDate", "selectedUser", "startDay", "startMonth", "startYear", "endDay", "endMonth", "endYear");

foreach($variablesToSet as $variable)
{
        if(isset($_GET[$variable]))
        {
                $$variable = $_GET[$variable];
                $_SESSION[$variable] = $_GET[$variable];
        }
        elseif(isset($_SESSION[$variable]))
                $$variable = $_SESSION[$variable];
        else
                $$variable = "";
}
?>
<h4>:: Show chart for this user:</h4>

<form name="users">
<?php
dropdown_users($selectedUser);
?>
</form>


<BR><BR>
<p>
<span id="toggleLink" onClick='toggleForm()'>Show dates form</span>
</p>

<div id="chartform">
<form action="<?php echo $PHP_SELF; ?>" method="get">
<h4>:: Use these dates: </h4><br>

<p>
<input class="radiobutton" type="radio" name="useDate" value="true"<?php if($useDate == "true") echo " CHECKED"; ?>>Yes
<input class="radiobutton" type="radio" name="useDate" value="false"<?php if($useDate == "false") echo " CHECKED"; ?>>No
</p>

<h4>:: Get chart with data between these dates:</h4>
<table>
<tr>
<th>Start</th>

<td class="white">
<?php
	dropDownNumbers ("startDay", "day", 1, 31, $startDay);
	dropDownNumbers ("startMonth", "month", 1, 12, $startMonth);
	dropDownNumbers ("startYear", "year", 1999, 2005, $startYear);
?>
</td>
<th>End</th>

<td class="white">
<?php
	dropDownNumbers ("endDay", "day", 1, 31, $endDay);
	dropDownNumbers ("endMonth", "month", 1, 12, $endMonth);
	dropDownNumbers ("endYear", "year", 1999, 2005, $endYear);
?>
</td>
</tr>
<th>Quick</th>
<td colspan="3">

<?php
$tomorrow = strtotime ("+1 day");
$lastweek = strtotime ("-1 week");
$lastmonth = strtotime ("-1 month");
$lastthreemonths = strtotime ("-3 months");
$lastyear = strtotime ("-1 year");

$args = "?useDate=true";
$args .= "&startYear=" . date('Y') . "&endYear="  . date('Y', $tomorrow);
$args .= "&startMonth=" . date('m') . "&endMonth="  . date('m', $tomorrow);
$args .= "&startDay=" . date('d') . "&endDay="  . date('d', $tomorrow);
echo "<a href=\"$_SERVER[PHP_SELF]$args\">Today</a> | ";

$args = "?useDate=true";
$args .= "&startYear="	. date('Y', $lastweek) . "&endYear="	. date('Y', $tomorrow);
$args .= "&startMonth="	. date('m', $lastweek) . "&endMonth="	. date('m', $tomorrow);
$args .= "&startDay="	. date('d', $lastweek) . "&endDay="		. date('d', $tomorrow);
echo "<a href=\"$_SERVER[PHP_SELF]$args\">Last Week</a> | ";

$args = "?useDate=true";
$args .= "&startYear="	. date('Y', $lastmonth) . "&endYear="	. date('Y', $tomorrow);
$args .= "&startMonth="	. date('m', $lastmonth) . "&endMonth="	. date('m', $tomorrow);
$args .= "&startDay="	. date('d', $lastmonth) . "&endDay="	. date('d', $tomorrow);
echo "<a href=\"$_SERVER[PHP_SELF]$args\">Last Month</a> | ";

$args = "?useDate=true";
$args .= "&startYear="	. date('Y', $lastthreemonths) . "&endYear="		. date('Y', $tomorrow);
$args .= "&startMonth="	. date('m', $lastthreemonths) . "&endMonth="	. date('m', $tomorrow);
$args .= "&startDay="	. date('d', $lastthreemonths) . "&endDay="		. date('d', $tomorrow);
echo "<a href=\"$_SERVER[PHP_SELF]$args\">Last Three Months</a> | ";

$args = "?useDate=true";
$args .= "&startYear="	. date('Y', $lastyear) . "&endYear="		. date('Y', $tomorrow);
$args .= "&startMonth="	. date('m', $lastyear) . "&endMonth="	. date('m', $tomorrow);
$args .= "&startDay="	. date('d', $lastyear) . "&endDay="		. date('d', $tomorrow);
echo "<a href=\"$_SERVER[PHP_SELF]$args\">Last Year</a>";
?>
</td>
</tr>
</table>
<p>
<input class="button" type=submit value="Get List"></p>
</form>
</div>


<?php

$selectedUser = (int) $_GET['selectedUser'];

$kweerie = "select status, s.song_id, history_id, songtitle, artist_name,
album_name, count(*) as nrOfVotes, timesPlayed from history h, songs s LEFT
JOIN artists on s.artist_id=artists.artist_id LEFT JOIN albums on
s.album_id=albums.album_id WHERE s.song_id = h.song_id";

if($selectedUser > "0")
{
	$kweerie .= " AND user_id='$selectedUser'";
}
if($useDate=="true")
{
	$kweerie .= " AND timestamp > ";
	$kweerie .= date('Ymd000000', mktime(0,0,0, $startMonth, $startDay, $startYear));
	$kweerie .= " AND timestamp < ";
	$kweerie .= date('Ymd000000', mktime(0,0,0, $endMonth, $endDay, $endYear));

}
$kweerie .= " GROUP BY s.song_id ORDER BY nrOfVotes DESC, artist_name";
#echo $kweerie;
listSongs($kweerie);

?>
<script type="text/javascript">

function toggleForm()
{
	var toggleLink = document.getElementById('toggleLink')
	var textObj = toggleLink.childNodes[0];
	var el = document.getElementById("chartform");
	
	if (el.style.visibility == "visible")
	{
		el.style.visibility	= "hidden";
		el.style.display	= "none";
		textObj.data = 'Show dates form';
	}
	else
	{
		el.style.visibility	= "visible";
		el.style.display	= "block";
		textObj.data = 'Hide dates form';
	}
}
//toggleForm();
                

</script>

<?php
require("footer.inc.php");


?>
