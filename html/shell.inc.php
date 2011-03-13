<?php

function skip_current_song() {
    global $_CONF;

    if ($_CONF['mode'] == "ices") {
        $tmp_progname = split(" ", basename($_CONF['ices_binary']));
        $prog_arr[] = $tmp_progname[0];
        $level = "USR1";
    }
    elseif (preg_match("/local/", $_CONF['mode'])) {
        foreach ( array($_CONF['mpg123_binary'],$_CONF['ogg123_binary']) as $progname){
            $tmp_progname = split(" ",basename($progname));
            $prog_arr[] = $tmp_progname[0];
        }
        $level = "INT";  // -KILL
    }
    else {
        return FALSE;
    }

    foreach ($prog_arr as $progname){
        $cmd = "kill -$level `ps auxwww |grep " . $progname .  "|awk '{print $2}'`";
        system($cmd);
    }
    return TRUE;
}
?>
