<?php
# PQueue.class.php
# Implementation of a priority queue for controlling playback

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

class PQueue {
    var $queue; 
    var $random_query;
    
    function PQueue($random_query = "unweighted",
                    $valid_extensions = array("mp3","ogg")) {

        # Assigns limits on which filetypes are allowed into the random song
        # selection SQL
        $filetype_limit = "";
        $filetype_sql1 = "type = '";
        $filetype_sql2 = "' OR ";
        foreach ($valid_extensions as $filetype) {
            $filetype_limit .= $filetype_sql1 . $filetype . $filetype_sql2;
            # "type == 'mp3' OR " for example
        }
        $filetype_limit .= "0";
        # ends "type == 'mp3' OR ... 0"
       
        if ($random_query == "weighted") {
            $this->random_query = "SELECT * FROM songs WHERE playInRandomMode=1
            AND status != 'offline' AND status != 'delete' AND ($filetype_limit)
            ORDER BY sqrt(SQRT(timesPlayed+100))*RAND() DESC LIMIT 1";
        }
        else {
            $this->random_query = "SELECT * FROM songs WHERE playInRandomMode=1
            AND status != 'offline' AND status != 'delete' AND ($filetype_limit)
            ORDER BY rand() LIMIT 1";
        }
        $this->queue = array();
    }

    function clear_SQL() {
        mysql_query("DELETE from priority_queue");
    }

    function clear() {
        unset($this->queue);
        $this->queue = array();
    }

    function insert($song_id) {
        array_push($this->queue, $song_id);
    }
    
    function lock($write) {
        if ($write) {
            mysql_query("LOCK TABLES priority_queue WRITE");
        }
        else
            mysql_query("LOCK TABLES priority_queue READ");
    }

    function unlock() {
        mysql_query("UNLOCK TABLES");
    }
    
    function read() {
        //FIXME should we lock the table here???
        $query = "SELECT priority,song_id from priority_queue ORDER BY priority";
        $result = mysql_query($query);
        $this->clear();
        if(mysql_num_rows($result) < 1)
            return;
        while ($row = mysql_fetch_object($result)) {
            array_push($this->queue, $row->song_id);
        }
    }
    
    function dequeue($options=NULL, $song_id=NULL) {
        if ($options=="set_random") {
            $is_random = 1;
        }
        else {
            list($song_id,$is_random) = $this->top();
        }

        $query = "DELETE from priority_queue WHERE priority=1";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        
        $query = "UPDATE priority_queue SET priority=priority-1";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        
        $query = "DELETE from np";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        
        $query = "INSERT INTO play (song_id, timestamp) VALUES ('$song_id', NOW())";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        $play_id = mysql_insert_id();
        $time = time();
        
        $query = "INSERT INTO np (song_id, play_id, started, wasrandom) VALUES('$song_id', '$play_id', '$time', '$is_random')";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        
        $query = "INSERT INTO caused (history_id, play_id) SELECT history_id, '$play_id' FROM queue WHERE song_id='$song_id'";
        mysql_query($query) or die("query = $query\n" . mysql_error());
        
        if (!($is_random)) {
            $query = "DELETE FROM queue WHERE song_id = '$song_id'";
            mysql_query($query) or die("query = $query\n" . mysql_error());
            $query = "UPDATE songs SET timesPlayed=timesPlayed + 1 WHERE song_id = $song_id";
            mysql_query($query) or die("query = $query\n" . mysql_error());
        }
        return $song_id;
    }

    function size() {
        return sizeof($this->queue);
    }

    function top() {
        if(empty($this->queue[0])) {
            $is_random = 1;
            $result = mysql_query($this->random_query) or die("mysql error: $query\n" . mysql_error());
            $row = mysql_fetch_object($result);
            $this->insert($row->song_id);
            // random song...
        }
        else {
            $is_random=0;
        }
        return Array($this->queue[0], $is_random);
    }

    function print_queue() {
    }
    
    function generate_from_votes() {
        global $_CONF;
        // locks table for write, gets current queue snapshot
        // deletes existing queue and replaces it with new snapshot
        // TODO: This could be optimized (but is it worth it?)
        $count = 1;
        $result = mysql_query($_CONF['government']);
        $this->lock(1);
        $this->clear_SQL();
        while ($row = mysql_fetch_object($result)) {
            $query = "INSERT into priority_queue VALUES ($count, $row->song_id)";
            mysql_query($query);
            $count++;
        }
        $this->unlock();
    } 
}


?>
