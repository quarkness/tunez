<?php
# vote.inc.php
#
# These are the vote functions

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

/*
 * Comments on the fields within the voting_rights table:
    $who
            Always 'user' for now
    $ug_id
            Always a 'user_id' for now
    $vote_type
            Either in 'seconds' or in 'votes'.  Determines how to charge
            for songs that the user wants to vote for.
    $mode
            Either in 'bank' or 'window' mode.  Bank mode means that the
            user accumulates $votes number of votes (or seconds) every
            $period of time.  They presently have $votes_banked votes.
            Window mode means the user can vote for $votes number of songs
            (or seconds) in a given $period.
    $period
            In windowed mode how big the window is for voting.  In bank
            mode how often to add more votes to their total
    $votes
            How many votes (or seconds) to add
    $votes_banked
             How many total votes they have banked in 'bank' mode
 *
 */


/*
dovote($song_ids, $override=0)
Paramaters:
        $song_ids
                The Song id or array of song ids the person wants to vote for
        $override       
                If we want the function to skip the checks and just perform the vote
Returns: Nothing
Notes:

This is the old style voting code which has been deprecated.

*/
function dovote($song_ids, $override=0) {
    $user_id = $_SESSION['user_id'];

    if (!(is_array($song_ids))) {
        $song_ids = Array($song_ids);
    }

    if (($user_id < 1) && (!($override)))
    {
        $_SESSION['messageTitle'] = "Oops!";
        $_SESSION['messageBody'] = "You must be logged in to vote...";
        return -1;
    }
    elseif (!($_SESSION['perms']['p_vote']) && (!($override)))
    {
        $_SESSION['messageTitle'] = "Voting not allowed!";
        $_SESSION['messageBody'] = "Your account hasn't been activated yet.";
        return -1;
    }

    $ok = 0;
    $bad = 0;
    for($i=sizeof($song_ids)-1; $i >= 0; $i--) {
        $song_id = $song_ids[$i];
        $kweerie = "SELECT * from queue WHERE song_id = '$song_id' and user_id=$user_id";
        $result = tunez_query($kweerie);

        if ((mysql_num_rows($result) > 0) && (!($override)))
        {
            $bad += 1;
        }
        else {
            $ok += 1;
            vote($song_id);
        }
    }
    if ($bad > 0 && $ok == 0 ) {
        $_SESSION['messageTitle'] = "Your voting failed";
        $_SESSION['messageBody'] = "You have already voted for the song or songs you selected, none of your votes succeeded.
            Please try again later";
        return -1;
    }
    elseif ($bad > 0 && $ok > 0) {
        $_SESSION['messageTitle'] = "Some of your votes failed";
        $_SESSION['messageBody'] = "$ok of your votes succeeded while $bad failed because you have already voted for them";
        return -1;
    }
    elseif ($bad == 0 && $ok == 1) {
        $_SESSION['messageTitle'] = "You have voted!";
        $_SESSION['messageBody'] = "Your vote has been registered";
        return 1;
    }
    elseif ($bad == 0 && $ok > 1) {
        $_SESSION['messageTitle'] = "You have voted!";
        $_SESSION['messageBody'] = "All of your votes succeeeded";
        return 1;
    }
    return -1;
}

/*
vote($song_id)
Paramaters:
    $song_id (one or array)
            The Song id the person wants to vote for
Returns: Nothing
Notes:

This is a simple function which does the basic voting.

*/
function vote($song_ids) {
    $user_id = $_SESSION['user_id'];
    if (!(is_array($song_ids))) {
        $song_ids = Array($song_ids);
    }
    foreach ($song_ids as $song_id) {
        $kweerie2 = "INSERT INTO history VALUES ('','$song_id','$user_id',NOW())";
        tunez_query($kweerie2) or die(mysql_error());
        $history_id = mysql_insert_id();

        $kweerie = "INSERT INTO queue VALUES ('','$history_id','$song_id','$user_id',NOW())";
        tunez_query($kweerie) or die(mysql_error());
    }
    $PQueue = new PQueue;
    $PQueue->generate_from_votes();
}

/*
unvote($song_id)
Paramaters:
    $song_id
        The Song id the person wants to vote for
    $user_id (GLOBAL)
        The user ID (imported with global)
Returns: Nothing
Notes:

This function first determines if they payed in bank mode or not and if they
did refunds the cost of the song.  Next it determines the unique queue and
history id and deletes those from the history and queue tables effectively
removing any evidence that they voted at all.
*/
function unvote($song_id) {
    if(!($_SESSION['perms']['p_unvote'])) {
        $_SESSION['messageTitle'] = "Access Denied";
        $_SESSION['messageBody'] = "You are not allowed to unvote for songs";
        return;
    }
    global $user_id;

    // Find out what song they want to unvote for
    $query = "SELECT queue_id,history_id from queue where song_id=$song_id AND
        user_id=$user_id";
    $result = tunez_query($query);

    // If they have not voted for that song return
    if (mysql_num_rows($result) < 1) {
        $_SESSION['messageTitle'] = "Permission Denied";
        $_SESSION['messageBody'] = "We're sorry, you can't unvote a song you
        didn't vote for.";
        return;
    }
    
    // determine history and queue id from voting records & delete
    $row = mysql_fetch_object($result);
    $history_id = $row->history_id;
    $queue_id = $row->queue_id;
    $query = "DELETE from queue where queue_id=$queue_id";
    tunez_query($query);
    $query = "DELETE from history where history_id=$history_id";
    tunez_query($query);

    //determine if we have to refund their money (banked mode)
    $query = "SELECT vote_type,mode,period,votes,votes_banked FROM
        voting_rights where who='user' AND ug_id=$user_id";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    if($row->mode=="bank") {
        $cost = determine_cost($song_id, $row->vote_type);
        debit_account('user', $user_id, -$cost);
    }

    // regenerate the priority queue
    $PQueue = new PQueue;
    $PQueue->generate_from_votes();
}

/*
newvote($song_id, $override=0)
Paramaters:
    $song_ids
            The array of song ids the person wants to vote for
    $override
            If set to one it skips the basic checks on user_id, etc.
Returns: Nothing
Notes:

This is the function which first performs the basic checks on voting (no
multiple times, if they are a user).  Following this it looks up their voting
rights and calls enough_money_to_vote to see if they have enough 'money' to
vote.  If they do it calls deduct_money() to debit their account if their
voting rights are in banked mode.  Then it calls the vote() function.  If
anything fails it returns early setting an error message in messageTitle and
messageBody
*/
function newvote($song_ids, $override=0) {
    $user_id = $_SESSION['user_id'];

    if (($user_id < 1) && (!($override)))
    {
        $_SESSION['messageTitle'] = "Oops!";
        $_SESSION['messageBody'] = "You must be logged in to vote...";
        return;
    }
    elseif (!($_SESSION['perms']['p_vote']) && (!($override)))
    {
        $_SESSION['messageTitle'] = "Voting not allowed!";
        $_SESSION['messageBody'] = "Your account hasn't been activated yet.";
        return;
    }

    $query = "SELECT vote_type,mode,period,votes,votes_banked 
        FROM voting_rights where who='user' AND ug_id=$user_id";
    $voting_rights_result = tunez_query($query);
    if (mysql_num_rows($result) < 1) {
        $_SESSION['messageTitle'] = "Voting not allowed!";
        $_SESSION['messageBody'] = "You have not been granted any voting privileges yet.";
        return -1;
    }

    foreach ($song_ids as $song_id) {
        $query = "SELECT * from queue WHERE song_id = '$song_id' and user_id=$user_id";
        $result = tunez_query($query);
        if ((mysql_num_rows($result) > 0) && (!($override)))
        {
            $_SESSION['messageTitle'] = "Voting not allowed!";
            $_SESSION['messageBody'] = "You have already voted one of the songs you chose to vote for!<BR>Wait a little....";
            return -1;
        }
    }
    
    $row = mysql_fetch_object($voting_rights_result);
    list($ok, $message, $cost) = enough_money_to_vote($song_ids, $row
            /*, 'user', $user_id, $row->vote_type, $row->mode, $row->period, $row->votes, $row->votes_banked */ );
    if ($ok==TRUE) {
        vote($song_ids);
    }
    else {
        $_SESSION['messageTitle']="Voting Not Allowed!";
        $_SESSION['messageBody']=$message;
        return -1;
    }
}

/*
determine_cost($song_id, $vote_type)
Paramaters:
        $song_id
                The Song id the person wants to vote for
        $vote_type
                Whether we want to calculate the cost in votes or in seconds
Returns: [INT] The cost of the song in either seconds or votes
Notes:

This function looks up the number of seconds in a song if that is how the cost
is to be determined and returns that, or it simply returns 1 if we're dealing
with number of votes.
*/
function determine_cost($song_id, $vote_type) {
    if($vote_type=="votes")
        return 1;
    //elseif($vote_type=="seconds")
    $query = "SELECT TIME_TO_SEC(length) as length FROM songs where song_id=$song_id";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    return $row->length;
}



/*
enough_money_to_vote($song_ids, $row) {
Paramaters:
    $song_ids (array)
            The Song ids the person wants to vote for
    $rights
            The voting_rights row which the song_ids are going to be spent off of
    This is the magic function which determines if the user has enough voting
power to vote for the song.  See above variables.

*/
function enough_money_to_vote($song_ids, $rights) {
    $who = &$rights->who;
    $mode = &$rights->mode;
    $vote_type = &$rights->vote_type;
    $ug_id = &$rights->ug_id;
    $period = &$rights->period;
    $votes = &$rights->votes;
    $votes_banked = &$rights->votes_banked;
    $time_added = &$rights->time_added;
   
    // This sums up all of the songs they want to vote for.
    $cost = 0;
    foreach ($song_ids as $id) {
        $cost += determine_cost($id, $vote_type);
    }

    // First calculate if they can vote or not...
    if ($mode=="window") {
        // In windowed mode:
        
        if ($who == "user") {
            $spendable_money_per_period = &$row->votes;
            if ($rights->vote_type=="seconds")
                $query = "SELECT sum(TIME_TO_SEC(length)) AS
                    spent_money, SEC_TO_TIME(sum(TIME_TO_SEC(length))) as spent_money_formatted";
            elseif($rights->vote_type=="votes")
                $query = "SELECT sum(*) AS spent_money";
            $query .= " from history LEFT
                JOIN songs on history.song_id=songs.song_id LEFT JOIN voting_rights on
                HISTORY.User_id=voting_rights.ug_id where
                user_id=$rights->ug_id AND timestamp > (NOW()-period) AND
                who='user'";
            $result = tunez_query($query);
            $row = mysql_fetch_object($result);
            if($row->spent_money + $cost > $spendable_money_per_period) {
                if($mode=="window") {
                    if($vote_type=="seconds") {
                        $explanation="You have already purchased
                            $row->spent_money_formatted of playing time in the
                            last $rights->period.<br>The additional amount you wish to
                            purchase, $cost, would put you over balance as you
                            are only allowed to purchase $rights->votes every
                            $rights->period.";
                    }
                    elseif($vote_type=="votes") {
                        if ($row->spent_money == 1) $songs = "song";
                        else $songs = "songs";
                        $explanation="You have already puchased
                            $row->spent_money $songs in the last
                            $period.<br>The additional number you wish to
                            purchase, $cost, would put you over balance as you
                            are only allowed to purchase $votes $songs every
                            $period.";
                    }
                }
                return Array(FALSE, $explanation, $cost);
            }
            else
                return Array(TRUE, NULL, $cost);
        }
    }

    // Banked mode...
    elseif ($mode=="bank") {
        if ($who == "user") {
            $available_cash = &$votes_banked;
            if($cost < $available_cash) {
                debit_account('user', $ug_id, $cost);
                return Array(TRUE, "Your vote has succeeded", $cost);
            }
            else {
                $available_cash = retabulate_account('user', $ug_id);
                if ($cost < $available_cash) {
                    debit_account('user', $ug_id, $cost);
                    return Array(TRUE, "Your vote has succeeded", $cost);
                }
                else {
                    if($vote_type=="seconds") {
                        $explanation="You currently
                            have $available_cash in your
                            bank.  The song you chose cost $cost.";
                    }
                    elseif($vote_type=="votes") {
                        if ($cost > 1) $songs = "songs";
                        else $songs = "song";
                        $explanation="You wanted to vote for $cost $songs but
                            currently only have $available_cash votes in your
                            bank.  You will have to wait until you get more
                            votes before you can play songs.";
                    }
                    return Array(FALSE, $explanation, $cost);
                }
            }
        }
    }        
}

/*
retabulate_account($who, $ug_id)
Paramaters:
    $who
        Currently only 'user'
    $ug_id
        Currently only 'user_id'
Returns: New balance of account
Notes:

This recalculates a user's bottom line based on how much is in their account
*/
function retabulate_account($who, $ug_id) {
    $query = "SELECT vr_id, TIME_TO_SEC(time_added) as time_added, TIME_TO_SEC(period) as period  
                from voting_rights where who='$who' AND ug_id='$ug_id' AND mode='banked'";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    $vr_id = $row->vr_id;
    $number_of_paydays = (int) (time() - $row->time_added) / $row->period; 
    if ($number_of_paydays) {
        $new_votes_banked = $number_of_paydays * $row->votes;
        $new_votes_banked += $row->votes_banked;  // add their old banked votes
        // TODO make this better
        if ($new_votes_banked > $row->votes_max) {
            $new_votes_banked = $row->votes_max;
        }

        $query = "UPDATE voting_rights set votes_banked = $new_votes_banked, 
            time_added = NOW() WHERE vr_id = $row->vr_id";
        $result = tunez_query($query);
    }
}

/*
debit_account($who, $ug_id, $cost)
Paramaters:
    $who
        Currently only 'user'
    $ug_id
        Currently only 'user_id'
    $cost
        The cost that we're deducting (or negative to refund)
Returns: Nothing
Notes:

This debits or credits user's bank accounts with seconds or minutes if they
vote or unvote for a song.

*/
function debit_account($who, $ug_id, $cost) {
    if ($who=='user')
        $query = "UPDATE voting_rights set votes_banked=votes_banked-$cost where who='user' AND ug_id=$ug_id";
    else die("deduct_money() not fully implemented");
    tunez_query($query);
}

?>
