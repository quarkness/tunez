<?php
# amazon.inc.php
#
# Contains functions necessary to discover links to album cover and other stuff off of Amazon(tm)'s website.

/*
 * tunez
 *
 * Copyright (C) 2004, Philip Lowman <lowman@uiuc.edu>
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

$search_url = "http://www.amazon.com/exec/obidos/search-handle-form";
$user_agent = "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.6) Gecko/20040227 Firefox/0.8";

/*
 * get_html($url, $postopts=NULL)
 * Paramaters:
 *  $url
 *      This is the URL we would like to return
 *  $postopts
 *      If this is not null we do a post to the $url with these options
 * Returns:
 *  html of webpage
 * Notes:
 *
 * This is simply a wrapper for the curl code to avoid duplicating code everywhere.
 */
function get_html($url, $postopts=NULL) {
    global $user_agent;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent); 
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
    if (isset($postopts)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postopts);
    }
    $page = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array(page => $page, info => $info);
}

/*
 * get_album_covers($detail)
 * Paramaters:
 *  $detail
 *      Detail is an array with an 'artist' and 'album' entry
 * Returns: Nothing
 * Notes:
 * 
 * This calls the other functions and determines how exactly to best parse Amazon's website.
 */
function get_album_covers($detail) {
    // first we do a search for artist & album...
    // if we hit page with mini icons, grab first one, zoom in and grab big one -> done
    // if we hit zoomed in page grab picture and do a search for artist only, parse album name from results and save mini pic
    global $search_url;

    //print "get_album_covers() called";

    if (!empty($detail['artist']))
        $artist = $detail["artist"];
    if (!empty($detail['album'])) 
        $album = $detail['album'];

    $postfields = "page=1&index=music&field-artist=$artist&field-title=$album&field-label=&field-binding=&field-is-available-used=1";
    $curl_result = get_html($search_url, $postfields);
    $page = $curl_result['page'];

    $results = parse_page($page, 10);
    if(sizeof($results) == 1) {
        // exact match so we must include the last effective Amazon page
        $results[0]['amazon_url'] = neuter_amazon_url($curl_result['info']['url']);
    }
    return $results;
}

function neuter_amazon_url($url) {
    if (preg_match('/tg\/detail/', $url)) {
        $slashes = 9;
    }
    else {
        $slashes = 7;
    }
    $split = preg_split('/\//', $url);
    for($i=0; $i < $slashes; $i++) {
        $newurl .= $split[$i] . '/';
    }
    return $newurl;
}

    

function parse_zoomed_page($html) {
    // This function parses pages when we are looking at detail...
    
    $detail_URL = Array();

    if(preg_match("/Amazon\.com: Music: (.*?)\n<\/title>/", $html, $foo)) {
        $detail_URL['album_name'] = $foo[1];
    }
    if(preg_match("/<br \/>\n<span class=\"small\">\n<a href=(.*?)>(.*?)<\/a>/", $html, $foo)) {
        $detail_URL['artist_name'] = $foo[2];
    }

    if(preg_match("/<table border=0 align=left><tr><td valign=top align=center>\n<a href=\"(.*?)\"/", $html, $foo)) {
        $detail_URL['large_cover_img'] = $foo[1];
        $detail_URL['small_cover_img'] = preg_replace("/LZZZZZZZ/", "THUMBZZZ", $detail_URL[large_cover_img]);
    }
    elseif(preg_match("/<table border=0 align=left><tr><td valign=top align=center>\n<img src=\"(.*?)\"/", $html, $foo)) {
        // if large cover art is not available grab the medium sized one...
        $detail_URL['large_cover_img'] = $foo[1];
        $detail_URL['small_cover_img'] = preg_replace("/MZZZZZZZ/", "THUMBZZZ", $detail_URL[large_cover_img]);
    }

    if(preg_match("/Original Release Date: \w+\s\d+,\s+(\d+?)\n/", $html, $foo)) {
        $detail_URL['year'] = $foo[1];
    }

    $tracks = Array();
    $songtitles = Array();
    
    preg_match_all("/(\d+)\. (.*?)\s+<\/span><\/td>\n/", $html, $foo);
    $tracks = $foo[1];
    $songtitles = $foo[2];
    
    $detail_URL['tracks'] = Array(
            numbers => $tracks, 
            songtitles => $songtitles);
    return $detail_URL;

}

function parse_page($html, $num=5) {
    //print "in parse_page()<br>";
    //print $html;
    if (preg_match("/we\s+were\s+unable\s+to\s+find\s+exact\s+matches\s+for\s+your\s+search/i", $html)) {
        //print "UNABLE TO FIND EXACT MATCHES!!!";
        return Array();
    }
    elseif (preg_match("/<b>Sort by:<\/b>/", $html)) {
        //print("we are at a list of albums page...<br>");
        $type = "list";
        
        $detail_URL = Array();
        $items = Array();
        
        $aftersplit = preg_split("/<td><font size=-1><b>\d+\.<\/b><\/font><\/td>/", $html);
        //print "there are " . sizeof($aftersplit) . " entries on this page...<br>";

        for($i=1; $i<sizeof($aftersplit); $i++) {
            
            preg_match("/<a href=(.*?)>(.*?)<\/a><\/b>/", $aftersplit[$i], $foo);
            $zoom_page = "http://www.amazon.com" . $foo[1];
            $curl_result = get_html($zoom_page);
            $page = $curl_result['page'];
            $items[] = parse_zoomed_page($page);
            $items[$i-1]['amazon_url'] = neuter_amazon_url($zoom_page);
            
            if ($i == $num) {
                // We have reached the maximum number of entries desired
                break;
            }
            
        }
        return $items;
    }
    elseif(preg_match("/Availability:/", $html, $foo)) {
        // we think we're at a detail page...
        $detail_URL = parse_zoomed_page($html);
        return Array($detail_URL);
    }

    elseif(preg_match("/<b class=sans>(.*?)<\/b><br \/>/", $html, $foo)) {
        // we think we're at a detail page...?
        $detail_URL = parse_zoomed_page($html);
        return Array($detail_URL);
    }
    else {
        die("error in parse_page!!!");
    }
}
?>
