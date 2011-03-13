<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                ///
//            or http://www.getid3.org                        ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.lyrics3.php - part of getID3()                       //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

function getLyrics3Filepointer(&$fd, &$ThisFileInfo) {
	// http://www.volweb.cz/str/tags.htm

	fseek($fd, (0 - 128 - 9 - 6), SEEK_END); // end - ID3v1 - LYRICSEND - [Lyrics3size]
	$lyrics3_id3v1 = fread($fd, (128 + 9 + 6));
	$lyrics3lsz    = substr($lyrics3_id3v1,  0,   6); // Lyrics3size
	$lyrics3end    = substr($lyrics3_id3v1,  6,   9); // LYRICSEND or LYRICS200
	$id3v1tag      = substr($lyrics3_id3v1, 15, 128); // ID3v1

	if ($lyrics3end == 'LYRICSEND') {
		// Lyrics3v1, ID3v1, no APE

		$lyrics3size    = 5100;
		$lyrics3offset  = $ThisFileInfo['filesize'] - 128 - $lyrics3size;
		$lyrics3version = 1;

	} elseif ($lyrics3end == 'LYRICS200') {
		// Lyrics3v2, ID3v1, no APE

		// LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
		$lyrics3size    = $lyrics3lsz + 6 + strlen('LYRICS200');
		$lyrics3offset  = $ThisFileInfo['filesize'] - 128 - $lyrics3size;
		$lyrics3version = 2;

	} elseif (substr(strrev($lyrics3_id3v1), 0, 9) == strrev('LYRICSEND')) {
		// Lyrics3v1, no ID3v1, no APE

		$lyrics3size    = 5100;
		$lyrics3offset  = $ThisFileInfo['filesize'] - $lyrics3size;
		$lyrics3version = 1;
		$lyrics3offset  = $ThisFileInfo['filesize'] - $lyrics3size;

	} elseif (substr(strrev($lyrics3_id3v1), 0, 9) == strrev('LYRICS200')) {

		// Lyrics3v2, no ID3v1, no APE

		$lyrics3size    = strrev(substr(strrev($lyrics3_id3v1), 9, 6)) + 6 + strlen('LYRICS200'); // LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
		$lyrics3offset  = $ThisFileInfo['filesize'] - $lyrics3size;
		$lyrics3version = 2;

	} else {

		if (!isset($ThisFileInfo['ape'])) {
			require_once(GETID3_INCLUDEPATH.'getid3.ape.php');
			getAPEtagFilepointer($fd, $ThisFileInfo);
		}

		if (isset($ThisFileInfo['ape']['tag_offset_start']) && ($ThisFileInfo['ape']['tag_offset_start'] > 15)) {

			fseek($fd, $ThisFileInfo['ape']['tag_offset_start'] - 15, SEEK_SET);
			$lyrics3lsz = fread($fd, 6);
			$lyrics3end = fread($fd, 9);

			if ($lyrics3end == 'LYRICSEND') {
				// Lyrics3v1, APE, maybe ID3v1

				$lyrics3size    = 5100;
				$lyrics3offset  = $ThisFileInfo['ape']['tag_offset_start'] - $lyrics3size;
				$ThisFileInfo['avdataend'] = $lyrics3offset;
				$lyrics3version = 1;
				$ThisFileInfo['warning'] .= "\n".'APE tag located after Lyrics3, will probably break Lyrics3 compatability';

			} elseif ($lyrics3end == 'LYRICS200') {
				// Lyrics3v2, APE, maybe ID3v1

				$lyrics3size    = $lyrics3lsz + 6 + strlen('LYRICS200'); // LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
				$lyrics3offset  = $ThisFileInfo['ape']['tag_offset_start'] - $lyrics3size;
				$lyrics3version = 2;
				$ThisFileInfo['warning'] .= "\n".'APE tag located before Lyrics3, will probably break Lyrics3 compatability';

			}

		}

	}

	if (isset($lyrics3offset)) {
		$ThisFileInfo['avdataend'] = $lyrics3offset;
		getLyrics3Data($ThisFileInfo, $fd, $lyrics3offset, $lyrics3version, $lyrics3size);

		if (!isset($ThisFileInfo['ape'])) {
			require_once(GETID3_INCLUDEPATH.'getid3.ape.php');
			getAPEtagFilepointer($fd, $ThisFileInfo, $ThisFileInfo['lyrics3']['tag_offset_start']);
		}

	}

	return true;
}

function getLyrics3Data(&$ThisFileInfo, &$fd, $endoffset, $version, $length) {
	// http://www.volweb.cz/str/tags.htm

	fseek($fd, $endoffset, SEEK_SET);
	$rawdata = fread($fd, $length);

	if (substr($rawdata, 0, 11) != 'LYRICSBEGIN') {
		if (strpos($rawdata, 'LYRICSBEGIN') !== false) {

			$ThisFileInfo['warning'] .= "\n".'"LYRICSBEGIN" expected at '.$endoffset.' but actually found at '.($endoffset + strpos($rawdata, 'LYRICSBEGIN')).' - this is invalid for Lyrics3 v'.$version;
			$ThisFileInfo['avdataend'] = $endoffset + strpos($rawdata, 'LYRICSBEGIN');
			$ThisFileInfo['lyrics3']['tag_offset_start'] = $ThisFileInfo['avdataend'];
			$rawdata = substr($rawdata, strpos($rawdata, 'LYRICSBEGIN'));
			$length = strlen($rawdata);

		} else {

			$ThisFileInfo['error'] .= "\n".'"LYRICSBEGIN" expected at '.$endoffset.' but found "'.substr($rawdata, 0, 11).'" instead';
			return false;

		}

	}

	$ThisFileInfo['lyrics3']['raw']['lyrics3version'] = $version;
	$ThisFileInfo['lyrics3']['raw']['lyrics3tagsize'] = $length;
	$ThisFileInfo['lyrics3']['tag_offset_start']      = $endoffset;
	$ThisFileInfo['lyrics3']['tag_offset_end']        = $endoffset + $length;

	switch ($version) {

		case 1:
			$ThisFileInfo['tags'][] = 'lyrics3';
			if (substr($rawdata, strlen($rawdata) - 9, 9) == 'LYRICSEND') {
				$ThisFileInfo['lyrics3']['raw']['LYR'] = trim(substr($rawdata, 11, strlen($rawdata) - 11 - 9));
				Lyrics3LyricsTimestampParse($ThisFileInfo);
			} else {
				$ThisFileInfo['error'] .= "\n".'"LYRICSEND" expected at '.(ftell($fd) - 11 + $length - 9).' but found "'.substr($rawdata, strlen($rawdata) - 9, 9).'" instead';
			}
			break;

		case 2:
			$ThisFileInfo['tags'][] = 'lyrics3';
			if (substr($rawdata, strlen($rawdata) - 9, 9) == 'LYRICS200') {
				$ThisFileInfo['lyrics3']['raw']['unparsed'] = substr($rawdata, 11, strlen($rawdata) - 11 - 9 - 6); // LYRICSBEGIN + LYRICS200 + LSZ
				$rawdata = $ThisFileInfo['lyrics3']['raw']['unparsed'];
				while (strlen($rawdata) > 0) {
					$fieldname = substr($rawdata, 0, 3);
					$fieldsize = (int) substr($rawdata, 3, 5);
					$ThisFileInfo['lyrics3']['raw']["$fieldname"] = substr($rawdata, 8, $fieldsize);
					$rawdata = substr($rawdata, 3 + 5 + $fieldsize);
				}

				if (isset($ThisFileInfo['lyrics3']['raw']['IND'])) {
					$i = 0;
					$flagnames = array('lyrics', 'timestamps', 'inhibitrandom');
					foreach ($flagnames as $flagname) {
						if (strlen($ThisFileInfo['lyrics3']['raw']['IND']) > ++$i) {
							$ThisFileInfo['lyrics3']['flags']["$flagname"] = IntString2Bool(substr($ThisFileInfo['lyrics3']['raw']['IND'], $i, 1));
						}
					}
				}

				$fieldnametranslation = array('ETT'=>'title', 'EAR'=>'artist', 'EAL'=>'album', 'INF'=>'comment', 'AUT'=>'author');
				foreach ($fieldnametranslation as $key => $value) {
					if (isset($ThisFileInfo['lyrics3']['raw']["$key"])) {
						$ThisFileInfo['lyrics3']['comments']["$value"][] = trim($ThisFileInfo['lyrics3']['raw']["$key"]);
					}
				}

				if (isset($ThisFileInfo['lyrics3']['raw']['IMG'])) {
					$imagestrings = explode("\r\n", $ThisFileInfo['lyrics3']['raw']['IMG']);
					foreach ($imagestrings as $key => $imagestring) {
						if (strpos($imagestring, '||') !== false) {
							$imagearray = explode('||', $imagestring);
							$ThisFileInfo['lyrics3']['images']["$key"]['filename']     = $imagearray[0];
							$ThisFileInfo['lyrics3']['images']["$key"]['description']  = $imagearray[1];
							$ThisFileInfo['lyrics3']['images']["$key"]['timestamp']    = Lyrics3Timestamp2Seconds($imagearray[2]);
						}
					}
				}
				if (isset($ThisFileInfo['lyrics3']['raw']['LYR'])) {
					Lyrics3LyricsTimestampParse($ThisFileInfo);
				}
			} else {
				$ThisFileInfo['error'] .= "\n".'"LYRICS200" expected at '.(ftell($fd) - 11 + $length - 9).' but found "'.substr($rawdata, strlen($rawdata) - 9, 9).'" instead';
			}
			break;

		default:
			$ThisFileInfo['error'] .= "\n".'Cannot process Lyrics3 version '.$version.' (only v1 and v2)';
			break;
	}

	return true;
}

function Lyrics3Timestamp2Seconds($rawtimestamp) {
	if (ereg('^\\[([0-9]{2}):([0-9]{2})\\]$', $rawtimestamp, $regs)) {
		return (int) (($regs[1] * 60) + $regs[2]);
	}
	return false;
}

function Lyrics3LyricsTimestampParse(&$ThisFileInfo) {
	$lyricsarray = explode("\r\n", $ThisFileInfo['lyrics3']['raw']['LYR']);
	foreach ($lyricsarray as $key => $lyricline) {
		$regs = array();
		unset($thislinetimestamps);
		while (ereg('^(\\[[0-9]{2}:[0-9]{2}\\])', $lyricline, $regs)) {
			$thislinetimestamps[] = Lyrics3Timestamp2Seconds($regs[0]);
			$lyricline = str_replace($regs[0], '', $lyricline);
		}
		$notimestamplyricsarray["$key"] = $lyricline;
		if (isset($thislinetimestamps) && is_array($thislinetimestamps)) {
			sort($thislinetimestamps);
			foreach ($thislinetimestamps as $timestampkey => $timestamp) {
				if (isset($ThisFileInfo['lyrics3']['synchedlyrics'][$timestamp])) {
					// timestamps only have a 1-second resolution, it's possible that multiple lines
					// could have the same timestamp, if so, append
					$ThisFileInfo['lyrics3']['synchedlyrics'][$timestamp] .= "\r\n".$lyricline;
				} else {
					$ThisFileInfo['lyrics3']['synchedlyrics'][$timestamp] = $lyricline;
				}
			}
		}
	}
	$ThisFileInfo['lyrics3']['unsynchedlyrics'] = implode("\r\n", $notimestamplyricsarray);
	if (isset($ThisFileInfo['lyrics3']['synchedlyrics']) && is_array($ThisFileInfo['lyrics3']['synchedlyrics'])) {
		ksort($ThisFileInfo['lyrics3']['synchedlyrics']);
	}
	return true;
}


function DeleteLyrics3($filename) {
	$ThisFileInfo = GetAllFileInfo($filename);
	if (isset($ThisFileInfo['lyrics3']['tag_offset_start']) && isset($ThisFileInfo['lyrics3']['tag_offset_end'])) {

		if ($fp = @fopen($filename, 'a+b')) {

			flock($fp, LOCK_EX);
			$oldignoreuserabort = ignore_user_abort(true);

			fseek($fp, $ThisFileInfo['lyrics3']['tag_offset_end'], SEEK_SET);
			$DataAfterLyrics3 = fread($fp, $ThisFileInfo['filesize'] - $ThisFileInfo['lyrics3']['tag_offset_end']);

			ftruncate($fp, $ThisFileInfo['lyrics3']['tag_offset_start']);
			fseek($fp, $ThisFileInfo['lyrics3']['tag_offset_start'], SEEK_SET);

			fwrite($fp, $DataAfterLyrics3, strlen($DataAfterLyrics3));

			flock($fp, LOCK_UN);
			fclose($fp);
			ignore_user_abort($oldignoreuserabort);

			return true;

		}
	}
	return false;
}

?>