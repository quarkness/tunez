<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                ///
//            or http://www.getid3.org                        ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.id3v2.php - part of getID3()                         //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

require_once(GETID3_INCLUDEPATH.'getid3.id3v1.php');

function getID3v2Filepointer(&$fd, &$ThisFileInfo, $StartingOffset=0) {
	//    Overall tag structure:
	//        +-----------------------------+
	//        |      Header (10 bytes)      |
	//        +-----------------------------+
	//        |       Extended Header       |
	//        | (variable length, OPTIONAL) |
	//        +-----------------------------+
	//        |   Frames (variable length)  |
	//        +-----------------------------+
	//        |           Padding           |
	//        | (variable length, OPTIONAL) |
	//        +-----------------------------+
	//        | Footer (10 bytes, OPTIONAL) |
	//        +-----------------------------+

	//    Header
	//        ID3v2/file identifier      "ID3"
	//        ID3v2 version              $04 00
	//        ID3v2 flags                (%ab000000 in v2.2, %abc00000 in v2.3, %abcd0000 in v2.4.x)
	//        ID3v2 size             4 * %0xxxxxxx

	fseek($fd, $StartingOffset, SEEK_SET);
	$header = fread($fd, 10);
	if (substr($header, 0, 3) == 'ID3') {

		$ThisFileInfo['id3v2']['header'] = true;
		$ThisFileInfo['id3v2']['majorversion'] = ord($header{3});
		$ThisFileInfo['id3v2']['minorversion'] = ord($header{4});

	} else {

		return false;

	}

	if ($ThisFileInfo['id3v2']['majorversion'] > 4) { // this script probably won't correctly parse ID3v2.5.x and above.

		$ThisFileInfo['error'] .= "\n".'this script only parses up to ID3v2.4.x - this tag is ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].'.'.$ThisFileInfo['id3v2']['minorversion'];
		return false;

	}

	$id3_flags = BigEndian2Bin($header{5});
	switch ($ThisFileInfo['id3v2']['majorversion']) {
		case 2:
			// %ab000000 in v2.2
			$ThisFileInfo['id3v2']['flags']['unsynch']     = $id3_flags{0}; // a - Unsynchronisation
			$ThisFileInfo['id3v2']['flags']['compression'] = $id3_flags{1}; // b - Compression
			break;

		case 3:
			// %abc00000 in v2.3
			$ThisFileInfo['id3v2']['flags']['unsynch']     = $id3_flags{0}; // a - Unsynchronisation
			$ThisFileInfo['id3v2']['flags']['exthead']     = $id3_flags{1}; // b - Extended header
			$ThisFileInfo['id3v2']['flags']['experim']     = $id3_flags{2}; // c - Experimental indicator
			break;

		case 4:
			// %abcd0000 in v2.4
			$ThisFileInfo['id3v2']['flags']['unsynch']     = $id3_flags{0}; // a - Unsynchronisation
			$ThisFileInfo['id3v2']['flags']['exthead']     = $id3_flags{1}; // b - Extended header
			$ThisFileInfo['id3v2']['flags']['experim']     = $id3_flags{2}; // c - Experimental indicator
			$ThisFileInfo['id3v2']['flags']['isfooter']    = $id3_flags{3}; // d - Footer present
			break;
	}

	$ThisFileInfo['id3v2']['headerlength'] = BigEndian2Int(substr($header, 6, 4), 1) + 10; // length of ID3v2 tag in 10-byte header doesn't include 10-byte header length

	$ThisFileInfo['id3v2']['tag_offset_start'] = $StartingOffset;
	$ThisFileInfo['id3v2']['tag_offset_end']   = $ThisFileInfo['id3v2']['tag_offset_start'] + $ThisFileInfo['id3v2']['headerlength'];

//    Extended Header
	if (isset($ThisFileInfo['id3v2']['flags']['exthead']) && $ThisFileInfo['id3v2']['flags']['exthead']) {
//            Extended header size   4 * %0xxxxxxx
//            Number of flag bytes       $01
//            Extended Flags             $xx
//            Where the 'Extended header size' is the size of the whole extended header, stored as a 32 bit synchsafe integer.
		$extheader = fread ($fd, 4);
		$ThisFileInfo['id3v2']['extheaderlength'] = BigEndian2Int($extheader, 1);

//            The extended flags field, with its size described by 'number of flag  bytes', is defined as:
//                %0bcd0000
//            b - Tag is an update
//                Flag data length       $00
//            c - CRC data present
//                Flag data length       $05
//                Total frame CRC    5 * %0xxxxxxx
//            d - Tag restrictions
//                Flag data length       $01
		$extheaderflagbytes = fread ($fd, 1);
		$extheaderflags     = fread ($fd, $extheaderflagbytes);
		$id3_exthead_flags = BigEndian2Bin(substr($header, 5, 1));
		$ThisFileInfo['id3v2']['exthead_flags']['update']       = substr($id3_exthead_flags, 1, 1);
		$ThisFileInfo['id3v2']['exthead_flags']['CRC']          = substr($id3_exthead_flags, 2, 1);
		if ($ThisFileInfo['id3v2']['exthead_flags']['CRC']) {
			$extheaderrawCRC = fread ($fd, 5);
			$ThisFileInfo['id3v2']['exthead_flags']['CRC'] = BigEndian2Int($extheaderrawCRC, 1);
		}
		$ThisFileInfo['id3v2']['exthead_flags']['restrictions'] = substr($id3_exthead_flags, 3, 1);
		if ($ThisFileInfo['id3v2']['exthead_flags']['restrictions']) {
			// Restrictions           %ppqrrstt
			$extheaderrawrestrictions = fread ($fd, 1);
			$ThisFileInfo['id3v2']['exthead_flags']['restrictions_tagsize']  = (bindec('11000000') & ord($extheaderrawrestrictions)) >> 6; // p - Tag size restrictions
			$ThisFileInfo['id3v2']['exthead_flags']['restrictions_textenc']  = (bindec('00100000') & ord($extheaderrawrestrictions)) >> 5; // q - Text encoding restrictions
			$ThisFileInfo['id3v2']['exthead_flags']['restrictions_textsize'] = (bindec('00011000') & ord($extheaderrawrestrictions)) >> 3; // r - Text fields size restrictions
			$ThisFileInfo['id3v2']['exthead_flags']['restrictions_imgenc']   = (bindec('00000100') & ord($extheaderrawrestrictions)) >> 2; // s - Image encoding restrictions
			$ThisFileInfo['id3v2']['exthead_flags']['restrictions_imgsize']  = (bindec('00000011') & ord($extheaderrawrestrictions)) >> 0; // t - Image size restrictions
		}
	} // end extended header

//    Frames

//        All ID3v2 frames consists of one frame header followed by one or more
//        fields containing the actual information. The header is always 10
//        bytes and laid out as follows:
//
//        Frame ID      $xx xx xx xx  (four characters)
//        Size      4 * %0xxxxxxx
//        Flags         $xx xx

	$sizeofframes = $ThisFileInfo['id3v2']['headerlength'] - 10; // not including 10-byte initial header
	if (isset($ThisFileInfo['id3v2']['extheaderlength'])) {
		$sizeofframes -= $ThisFileInfo['id3v2']['extheaderlength'];
	}
	if (isset($ThisFileInfo['id3v2']['flags']['isfooter']) && $ThisFileInfo['id3v2']['flags']['isfooter']) {
		$sizeofframes -= 10; // footer takes last 10 bytes of ID3v2 header, after frame data, before audio
	}
	if ($sizeofframes > 0) {
		$framedata = fread($fd, $sizeofframes); // read all frames from file into $framedata variable

		//    if entire frame data is unsynched, de-unsynch it now (ID3v2.3.x)
		if (isset($ThisFileInfo['id3v2']['flags']['unsynch']) && $ThisFileInfo['id3v2']['flags']['unsynch'] && ($ThisFileInfo['id3v2']['majorversion'] <= 3)) {
			$framedata = DeUnSynchronise($framedata);
		}
		//        [in ID3v2.4.0] Unsynchronisation [S:6.1] is done on frame level, instead
		//        of on tag level, making it easier to skip frames, increasing the streamability
		//        of the tag. The unsynchronisation flag in the header [S:3.1] indicates that
		//        there exists an unsynchronised frame, while the new unsynchronisation flag in
		//        the frame header [S:4.1.2] indicates unsynchronisation.

		$framedataoffset = 10; // how many bytes into the stream - start from after the 10-byte header
		while (isset($framedata) && (strlen($framedata) > 0)) { // cycle through until no more frame data is left to parse
			if (strlen($framedata) <= ID3v2HeaderLength($ThisFileInfo['id3v2']['majorversion'])) {
				// insufficient room left in ID3v2 header for actual data - must be padding
				$ThisFileInfo['id3v2']['padding']['start']  = $framedataoffset;
				$ThisFileInfo['id3v2']['padding']['length'] = strlen($framedata);
				$ThisFileInfo['id3v2']['padding']['valid']  = true;
				for ($i = 0; $i < $ThisFileInfo['id3v2']['padding']['length']; $i++) {
					if ($framedata{$i} != "\x00") {
						$ThisFileInfo['id3v2']['padding']['valid'] = false;
						$ThisFileInfo['id3v2']['padding']['errorpos'] = $ThisFileInfo['id3v2']['padding']['start'] + $i;
						$ThisFileInfo['warning'] .= "\n".'Invalid ID3v2 padding found at offset '.$ThisFileInfo['id3v2']['padding']['errorpos'].' (the remaining '.($ThisFileInfo['id3v2']['padding']['length'] - $i).' bytes are considered invalid)';
						break;
					}
				}
				break; // skip rest of ID3v2 header
			}
			if ($ThisFileInfo['id3v2']['majorversion'] == 2) {
				// Frame ID  $xx xx xx (three characters)
				// Size      $xx xx xx (24-bit integer)
				// Flags     $xx xx

				$frame_header = substr($framedata, 0, 6); // take next 6 bytes for header
				$framedata    = substr($framedata, 6);    // and leave the rest in $framedata
				$frame_name   = substr($frame_header, 0, 3);
				$frame_size   = BigEndian2Int(substr($frame_header, 3, 3), 0);
				$frame_flags  = ''; // not used for anything, just to avoid E_NOTICEs

			} elseif ($ThisFileInfo['id3v2']['majorversion'] > 2) {

				// Frame ID  $xx xx xx xx (four characters)
				// Size      $xx xx xx xx (32-bit integer in v2.3, 28-bit synchsafe in v2.4+)
				// Flags     $xx xx

				$frame_header = substr($framedata, 0, 10); // take next 10 bytes for header
				$framedata    = substr($framedata, 10);    // and leave the rest in $framedata

				$frame_name = substr($frame_header, 0, 4);
				if ($ThisFileInfo['id3v2']['majorversion'] == 3) {
					$frame_size = BigEndian2Int(substr($frame_header, 4, 4), 0); // 32-bit integer
				} else { // ID3v2.4+
					$frame_size = BigEndian2Int(substr($frame_header, 4, 4), 1); // 32-bit synchsafe integer (28-bit value)
				}

				if ($frame_size < (strlen($framedata) + 4)) {
					$nextFrameID = substr($framedata, $frame_size, 4);
					if (IsValidID3v2FrameName($nextFrameID, $ThisFileInfo['id3v2']['majorversion'])) {
						// next frame is OK
					} elseif (($frame_name == chr(0).'MP3') || ($frame_name == chr(0).chr(0).'MP') || ($frame_name == ' MP3') || ($frame_name == 'MP3e')) {
						// MP3ext known broken frames - "ok" for the purposes of this test
					} elseif (($ThisFileInfo['id3v2']['majorversion'] == 4) && (IsValidID3v2FrameName(substr($framedata, BigEndian2Int(substr($frame_header, 4, 4), 0), 4), 3))) {
						$ThisFileInfo['warning'] .= "\n".'ID3v2 tag written as ID3v2.4, but with non-synchsafe integers (ID3v2.3 style). Older versions of Helium2 (www.helium2.com) is a known culprit of this. Tag has been parsed as ID3v2.3';
						$ThisFileInfo['id3v2']['majorversion'] = 3;
						$frame_size = BigEndian2Int(substr($frame_header, 4, 4), 0); // 32-bit integer
					}
				}


				$frame_flags = BigEndian2Bin(substr($frame_header, 8, 2));
			}

			if ((($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == "\x00\x00\x00")) || ($frame_name == "\x00\x00\x00\x00")) {
				// padding encountered

				$ThisFileInfo['id3v2']['padding']['start']  = $framedataoffset;
				$ThisFileInfo['id3v2']['padding']['length'] = strlen($framedata);
				$ThisFileInfo['id3v2']['padding']['valid']  = true;
				for ($i = 0; $i < $ThisFileInfo['id3v2']['padding']['length']; $i++) {
					if ($framedata{$i} != "\x00") {
						$ThisFileInfo['id3v2']['padding']['valid'] = false;
						$ThisFileInfo['id3v2']['padding']['errorpos'] = $ThisFileInfo['id3v2']['padding']['start'] + $i;
						$ThisFileInfo['warning'] .= "\n".'Invalid ID3v2 padding found at offset '.$ThisFileInfo['id3v2']['padding']['errorpos'].' (the remaining '.($ThisFileInfo['id3v2']['padding']['length'] - $i).' bytes are considered invalid)';
						break;
					}
				}
				break; // skip rest of ID3v2 header
			}

			if ($frame_name == 'COM ') {
				$ThisFileInfo['warning'] .= "\n".'error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].' tag). (ERROR: !IsValidID3v2FrameName("'.str_replace(chr(0), ' ', $frame_name).'", '.$ThisFileInfo['id3v2']['majorversion'].'))). [Note: this particular error has been known to happen with tags edited by iTunes (versions "X v2.0.3", "v3.0.1" are known-guilty, probably others too)]';
				$frame_name = 'COMM';
			}
			if (($frame_size <= strlen($framedata)) && (IsValidID3v2FrameName($frame_name, $ThisFileInfo['id3v2']['majorversion']))) {

				$ThisFileInfo['id3v2']["$frame_name"]['data']       = substr($framedata, 0, $frame_size);
				$ThisFileInfo['id3v2']["$frame_name"]['datalength'] = CastAsInt($frame_size);
				$ThisFileInfo['id3v2']["$frame_name"]['dataoffset'] = $framedataoffset;
				$framedata = substr($framedata, $frame_size);

				ID3v2FrameProcessing($frame_name, $frame_flags, $ThisFileInfo);

			} else { // invalid frame length or FrameID

				if ($frame_size <= strlen($framedata)) {

					if (IsValidID3v2FrameName(substr($framedata, $frame_size, 4), $ThisFileInfo['id3v2']['majorversion'])) {

						// next frame is valid, just skip the current frame
						$framedata = substr($framedata, $frame_size);
						$InvalidFrameMessageType = 'warning';
						$InvalidFrameMessageText = ' Next frame is valid, skipping current frame.';

					} else {

						// next frame is invalid too, abort processing
						unset($framedata);
						$InvalidFrameMessageType = 'error';
						$InvalidFrameMessageText = ' Next frame is also invalid, aborting processing.';

					}

				} elseif ($frame_size == strlen($framedata)) {

					// this is the last frame, just skip
					$InvalidFrameMessageType = 'warning';
					$InvalidFrameMessageText = ' This was the last frame.';

				} else {

					// next frame is invalid too, abort processing
					unset($framedata);
					$InvalidFrameMessageType = 'error';
					$InvalidFrameMessageText = ' Invalid frame size, aborting.';

				}
				if (!IsValidID3v2FrameName($frame_name, $ThisFileInfo['id3v2']['majorversion'])) {

					switch ($frame_name) {
						case chr(0).chr(0).'MP':
						case chr(0).'MP3':
						case ' MP3':
						case 'MP3e':
						case chr(0).'MP':
						case ' MP':
						case 'MP3':
							$InvalidFrameMessageType = 'warning';
							$ThisFileInfo["$InvalidFrameMessageType"] .= "\n".'error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].' tag). (ERROR: !IsValidID3v2FrameName("'.str_replace(chr(0), ' ', $frame_name).'", '.$ThisFileInfo['id3v2']['majorversion'].'))). [Note: this particular error has been known to happen with tags edited by "MP3ext (www.mutschler.de/mp3ext/)"]';
							break;

						default:
							$ThisFileInfo["$InvalidFrameMessageType"] .= "\n".'error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].' tag). (ERROR: !IsValidID3v2FrameName("'.str_replace(chr(0), ' ', $frame_name).'", '.$ThisFileInfo['id3v2']['majorversion'].'))).';
							break;
					}

				} elseif ($frame_size > strlen($framedata)){

					$ThisFileInfo["$InvalidFrameMessageType"] .= "\n".'error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].' tag). (ERROR: $frame_size ('.$frame_size.') > strlen($framedata) ('.strlen($framedata).')).';

				} else {

					$ThisFileInfo["$InvalidFrameMessageType"] .= "\n".'error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$ThisFileInfo['id3v2']['majorversion'].' tag).';

				}
				$ThisFileInfo["$InvalidFrameMessageType"] .= $InvalidFrameMessageText;

			}
			$framedataoffset += ($frame_size + ID3v2HeaderLength($ThisFileInfo['id3v2']['majorversion']));

		}

	}

//    Footer

//    The footer is a copy of the header, but with a different identifier.
//        ID3v2 identifier           "3DI"
//        ID3v2 version              $04 00
//        ID3v2 flags                %abcd0000
//        ID3v2 size             4 * %0xxxxxxx

	if (isset($ThisFileInfo['id3v2']['flags']['isfooter']) && $ThisFileInfo['id3v2']['flags']['isfooter']) {
		$footer = fread ($fd, 10);
		if (substr($footer, 0, 3) == '3DI') {
			$ThisFileInfo['id3v2']['footer'] = true;
			$ThisFileInfo['id3v2']['majorversion_footer'] = ord(substr($footer, 3, 1));
			$ThisFileInfo['id3v2']['minorversion_footer'] = ord(substr($footer, 4, 1));
		}
		if ($ThisFileInfo['id3v2']['majorversion_footer'] <= 4) {
			$id3_flags = BigEndian2Bin(substr($footer, 5, 1));
			$ThisFileInfo['id3v2']['flags']['unsynch_footer']  = substr($id3_flags, 0, 1);
			$ThisFileInfo['id3v2']['flags']['extfoot_footer']  = substr($id3_flags, 1, 1);
			$ThisFileInfo['id3v2']['flags']['experim_footer']  = substr($id3_flags, 2, 1);
			$ThisFileInfo['id3v2']['flags']['isfooter_footer'] = substr($id3_flags, 3, 1);

			$ThisFileInfo['id3v2']['footerlength'] = BigEndian2Int(substr($footer, 6, 4), 1);
		}
	} // end footer


	if (isset($ThisFileInfo['id3v2']['comments']['genre'])) {
		foreach ($ThisFileInfo['id3v2']['comments']['genre'] as $key => $value) {
			unset($ThisFileInfo['id3v2']['comments']['genre'][$key]);
			$ThisFileInfo['id3v2']['comments'] = array_merge_noclobber($ThisFileInfo['id3v2']['comments'], ParseID3v2GenreString($value));
		}
	}

	if (isset($ThisFileInfo['id3v2']['comments']['track'])) {
		foreach ($ThisFileInfo['id3v2']['comments']['track'] as $key => $value) {
			if (strstr($value, '/')) {
				list($ThisFileInfo['id3v2']['comments']['track'][$key], $ThisFileInfo['id3v2']['comments']['totaltracks'][$key]) = explode('/', $ThisFileInfo['id3v2']['comments']['track'][$key]);
			}
			// Convert track number to integer (ID3v2 track numbers could be returned as a
			// string ('03' for example) - this will ensure all track numbers are integers

			// Disabled as of v1.6.5 - suggestion by Plamen
			//$ThisFileInfo['id3v2']['comments']['track'][$key] = intval($ThisFileInfo['id3v2']['comments']['track'][$key]);
		}
	}

	// Do not change fileformat if already set
	//if (empty($ThisFileInfo['fileformat'])) {
	//	$ThisFileInfo['fileformat'] = 'id3';
	//}

	// Set avdataoffset
	$ThisFileInfo['avdataoffset'] = $ThisFileInfo['id3v2']['headerlength'];
	if (isset($ThisFileInfo['id3v2']['footer'])) {
		$ThisFileInfo['avdataoffset'] += 10;
	}

	return true;
}


function ParseID3v2GenreString($genrestring) {
	// Parse genres into arrays of genreName and genreID
	// ID3v2.2.x, ID3v2.3.x: '(21)' or '(4)Eurodisco' or '(51)(39)' or '(55)((I think...)'
	// ID3v2.4.x: '21' $00 'Eurodisco' $00

	$returnarray = null;
	if (strpos($genrestring, chr(0)) !== false) {
		$unprocessed = trim($genrestring); // trailing nulls will cause an infinite loop.
		$genrestring = '';
		while (strpos($unprocessed, chr(0)) !== false) {
			// convert null-seperated v2.4-format into v2.3 ()-seperated format
			$endpos = strpos($unprocessed, chr(0));
			$genrestring .= '('.substr($unprocessed, 0, $endpos).')';
			$unprocessed = substr($unprocessed, $endpos + 1);
		}
		unset($unprocessed);
	}
	if (LookupGenre($genrestring, true) !== '') {

		$returnarray['genreid'][] = LookupGenre($genrestring, true);
		$returnarray['genre'][]   = $genrestring;

	} else {

		while (strpos($genrestring, '(') !== false) {

			$startpos = strpos($genrestring, '(');
			$endpos   = strpos($genrestring, ')');
			if (substr($genrestring, $startpos + 1, 1) == '(') {
				$genrestring = substr($genrestring, 0, $startpos).substr($genrestring, $startpos + 1);
				$endpos--;
			}
			$element     = substr($genrestring, $startpos + 1, $endpos - ($startpos + 1));
			$genrestring = substr($genrestring, 0, $startpos).substr($genrestring, $endpos + 1);
			if (LookupGenre($element) !== '') { // $element is a valid genre id/abbreviation

				if (!is_array($returnarray['genre']) || !in_array(LookupGenre($element), $returnarray['genre'])) { // avoid duplicate entires
					if (($element == 'CR') && ($element == 'RX')) {
						$returnarray['genreid'][] = $element;
					} else {
						$returnarray['genreid'][] = (int) $element;
					}
					$returnarray['genre'][]   = LookupGenre($element);
				}

			} else {

				if (!is_array($returnarray['genre']) || !in_array($element, $returnarray['genre'])) { // avoid duplicate entires
					$returnarray['genreid'][] = '';
					$returnarray['genre'][]   = $element;
				}

			}
		}
	}
	if ($genrestring) {
		if (!is_array($returnarray['genre']) || !in_array($genrestring, $returnarray['genre'])) { // avoid duplicate entires
			$returnarray['genreid'][] = '';
			$returnarray['genre'][]   = $genrestring;
		}
	}

	return $returnarray;
}

function ID3v2FrameProcessing($frame_name, $frame_flags, &$ThisFileInfo) {

	// define $frame_arrayindex once here (used for many frames), override or ignore as neccesary
	$frame_arrayindex = count($ThisFileInfo['id3v2']["$frame_name"]); // 'data', 'datalength'
	if (isset($ThisFileInfo['id3v2']["$frame_name"]['data'])) {
		$frame_arrayindex--;
	}
	if (isset($ThisFileInfo['id3v2']["$frame_name"]['datalength'])) {
		$frame_arrayindex--;
	}
	if (isset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset'])) {
		$frame_arrayindex--;
	}
	if (isset($ThisFileInfo['id3v2']["$frame_name"]['flags'])) {
		$frame_arrayindex--;
	}
	if (isset($ThisFileInfo['id3v2']["$frame_name"]['timestampformat'])) {
		$frame_arrayindex--;
	}

	if ($ThisFileInfo['id3v2']['majorversion'] >= 3) { // frame flags are not part of the ID3v2.2 standard
		if ($ThisFileInfo['id3v2']['majorversion'] == 3) {
			//    Frame Header Flags
			//    %abc00000 %ijk00000
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['TagAlterPreservation']  = (bool) substr($frame_flags,  0, 1); // a - Tag alter preservation
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['FileAlterPreservation'] = (bool) substr($frame_flags,  1, 1); // b - File alter preservation
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['ReadOnly']              = (bool) substr($frame_flags,  2, 1); // c - Read only
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['compression']           = (bool) substr($frame_flags,  8, 1); // i - Compression
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['Encryption']            = (bool) substr($frame_flags,  9, 1); // j - Encryption
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['GroupingIdentity']      = (bool) substr($frame_flags, 10, 1); // k - Grouping identity
		} elseif ($ThisFileInfo['id3v2']['majorversion'] == 4) {
			//    Frame Header Flags
			//    %0abc0000 %0h00kmnp
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['TagAlterPreservation']  = (bool) substr($frame_flags,  1, 1); // a - Tag alter preservation
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['FileAlterPreservation'] = (bool) substr($frame_flags,  2, 1); // b - File alter preservation
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['ReadOnly']              = (bool) substr($frame_flags,  3, 1); // c - Read only
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['GroupingIdentity']      = (bool) substr($frame_flags,  9, 1); // h - Grouping identity
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['compression']           = (bool) substr($frame_flags, 12, 1); // k - Compression
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['Encryption']            = (bool) substr($frame_flags, 13, 1); // m - Encryption
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['Unsynchronisation']     = (bool) substr($frame_flags, 14, 1); // n - Unsynchronisation
			$ThisFileInfo['id3v2']["$frame_name"]['flags']['DataLengthIndicator']   = (bool) substr($frame_flags, 15, 1); // p - Data length indicator
		}

		//    Frame-level de-unsynchronization - ID3v2.4
		if (isset($ThisFileInfo['id3v2']["$frame_name"]['flags']['Unsynchronisation'])) {
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = DeUnSynchronise($ThisFileInfo['id3v2']["$frame_name"]['data']);
		}

		//    Frame-level de-compression
		if (isset($ThisFileInfo['id3v2']["$frame_name"]['flags']['compression'])) {
			// it's on the wishlist :)
		}

	}

	if (isset($ThisFileInfo['id3v2']["$frame_name"]['datalength']) && ($ThisFileInfo['id3v2']["$frame_name"]['datalength'] == 0)) {

		$ThisFileInfo['warning'] .= "\n".'Frame "'.$frame_name.'" at offset '.$ThisFileInfo['id3v2']["$frame_name"]['dataoffset'].' has no data portion';
		switch ($frame_name) {
			case 'WCOM':
				$ThisFileInfo['warning'] .= ' (this is known to happen with files tagged by RioPort)';
				break;

			default:
				break;
		}

	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'UFID')) || // 4.1   UFID Unique file identifier
		(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'UFI'))) {  // 4.1   UFI  Unique file identifier
		//   There may be more than one 'UFID' frame in a tag,
		//   but only one with the same 'Owner identifier'.
		// <Header for 'Unique file identifier', ID: 'UFID'>
		// Owner identifier        <text string> $00
		// Identifier              <up to 64 bytes binary data>

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0));
		$frame_idstring = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 0, $frame_terminatorpos);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid'] = $frame_idstring;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(chr(0)));
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'TXXX')) || // 4.2.2 TXXX User defined text information frame
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'TXX'))) {     // 4.2.2 TXX  User defined text information frame
		//   There may be more than one 'TXXX' frame in each tag,
		//   but only one with the same description.
		// <Header for 'User defined text information frame', ID: 'TXXX'>
		// Text encoding     $xx
		// Description       <text string according to encoding> $00 (00)
		// Value             <text string according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']  = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']    = TextEncodingLookup('encoding', $frame_textencoding);
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']   = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description'] = $frame_description;
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'], $frame_textencoding);
		}
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && !empty($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'])) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = rtrim($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'], ' ');
		}


	} elseif ($frame_name{0} == 'T') { // 4.2. T??[?] Text information frame
		//   There may only be one text information frame of its kind in an tag.
		// <Header for 'Text information frame', ID: 'T000' - 'TZZZ',
		// excluding 'TXXX' described in 4.2.6.>
		// Text encoding                $xx
		// Information                  <text string(s) according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);

		// $ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		// this one-line method should work, but as a safeguard against null-padded data, do it the safe way
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		if ($frame_terminatorpos) {
			// there are null bytes after the data - this is not according to spec
			// only use data up to first null byte
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		} else {
			// no null bytes following data, just use all data
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		}

		if (!isset($ThisFileInfo['id3v2']["$frame_name"]['flags']['compression']) || !$ThisFileInfo['id3v2']["$frame_name"]['flags']['compression']) {
			$ThisFileInfo['id3v2']["$frame_name"]['asciidata'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"]['encodingid']    = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"]['encoding']      = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		if (FrameNameShortLookup($frame_name) && !empty($ThisFileInfo['id3v2']["$frame_name"]['asciidata'])) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"]['asciidata'];
		}

	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'WXXX')) || // 4.3.2 WXXX User defined URL link frame
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'WXX'))) {     // 4.3.2 WXX  User defined URL link frame
		//   There may be more than one 'WXXX' frame in each tag,
		//   but only one with the same description
		// <Header for 'User defined URL link frame', ID: 'WXXX'>
		// Text encoding     $xx
		// Description       <text string according to encoding> $00 (00)
		// URL               <text string>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);

		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding));
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		if ($frame_terminatorpos) {
			// there are null bytes after the data - this is not according to spec
			// only use data up to first null byte
			$frame_urldata = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 0, $frame_terminatorpos);
		} else {
			// no null bytes following data, just use all data
			$frame_urldata = $ThisFileInfo['id3v2']["$frame_name"]['data'];
		}

		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']   = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']  = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']    = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url']         = $frame_urldata;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description'] = $frame_description;
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url'];
		}


	} elseif ($frame_name{0} == 'W') { // 4.3. W??? URL link frames
		//   There may only be one URL link frame of its kind in a tag,
		//   except when stated otherwise in the frame description
		// <Header for 'URL link frame', ID: 'W000' - 'WZZZ', excluding 'WXXX'
		// described in 4.3.2.>
		// URL              <text string>

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url'] = trim($ThisFileInfo['id3v2']["$frame_name"]['data']);
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url'];
		}


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] == 3) && ($frame_name == 'IPLS')) || // 4.4  IPLS Involved people list (ID3v2.3 only)
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'IPL'))) {     // 4.4  IPL  Involved people list (ID3v2.2 only)
		//   There may only be one 'IPL' frame in each tag
		// <Header for 'User defined URL link frame', ID: 'IPL'>
		// Text encoding     $xx
		// People list strings    <textstrings>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$ThisFileInfo['id3v2']["$frame_name"]['encodingid']    = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"]['encoding']      = TextEncodingLookup('encoding', $ThisFileInfo['id3v2']["$frame_name"]['encodingid']);
		$ThisFileInfo['id3v2']["$frame_name"]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"]['asciidata']     = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"]['asciidata']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"]['asciidata'];
		}


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'MCDI')) || // 4.4   MCDI Music CD identifier
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'MCI'))) {     // 4.5   MCI  Music CD identifier
		//   There may only be one 'MCDI' frame in each tag
		// <Header for 'Music CD identifier', ID: 'MCDI'>
		// CD TOC                <binary data>

		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"]['data']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"]['data'];
		}


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'ETCO')) || // 4.5   ETCO Event timing codes
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'ETC'))) {     // 4.6   ETC  Event timing codes
		//   There may only be one 'ETCO' frame in each tag
		// <Header for 'Event timing codes', ID: 'ETCO'>
		// Time stamp format    $xx
		//   Where time stamp format is:
		// $01  (32-bit value) MPEG frames from beginning of file
		// $02  (32-bit value) milliseconds from beginning of file
		//   Followed by a list of key events in the following format:
		// Type of event   $xx
		// Time stamp      $xx (xx ...)
		//   The 'Time stamp' is set to zero if directly at the beginning of the sound
		//   or after the previous event. All events MUST be sorted in chronological order.

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['timestampformat'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));

		while ($frame_offset < strlen($ThisFileInfo['id3v2']["$frame_name"]['data'])) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['typeid']    = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1);
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['type']      = ETCOEventLookup($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['typeid']);
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['timestamp'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
			$frame_offset += 4;
		}
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'MLLT')) || // 4.6   MLLT MPEG location lookup table
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'MLL'))) {     // 4.7   MLL MPEG location lookup table
		//   There may only be one 'MLLT' frame in each tag
		// <Header for 'Location lookup table', ID: 'MLLT'>
		// MPEG frames between reference  $xx xx
		// Bytes between reference        $xx xx xx
		// Milliseconds between reference $xx xx xx
		// Bits for bytes deviation       $xx
		// Bits for milliseconds dev.     $xx
		//   Then for every reference the following data is included;
		// Deviation in bytes         %xxx....
		// Deviation in milliseconds  %xxx....

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['framesbetweenreferences'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 0, 2));
		$ThisFileInfo['id3v2']["$frame_name"]['bytesbetweenreferences']  = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 2, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['msbetweenreferences']     = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 5, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['bitsforbytesdeviation']   = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 8, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['bitsformsdeviation']      = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 9, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 10);
		while ($frame_offset < strlen($ThisFileInfo['id3v2']["$frame_name"]['data'])) {
			$deviationbitstream .= BigEndian2Bin(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		}
		while (strlen($deviationbitstream)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['bytedeviation'] = bindec(substr($deviationbitstream, 0, $ThisFileInfo['id3v2']["$frame_name"]['bitsforbytesdeviation']));
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['msdeviation']   = bindec(substr($deviationbitstream, $ThisFileInfo['id3v2']["$frame_name"]['bitsforbytesdeviation'], $ThisFileInfo['id3v2']["$frame_name"]['bitsformsdeviation']));
			$deviationbitstream = substr($deviationbitstream, $ThisFileInfo['id3v2']["$frame_name"]['bitsforbytesdeviation'] + $ThisFileInfo['id3v2']["$frame_name"]['bitsformsdeviation']);
			$frame_arrayindex++;
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'SYTC')) || // 4.7   SYTC Synchronised tempo codes
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'STC'))) {     // 4.8   STC  Synchronised tempo codes
		//   There may only be one 'SYTC' frame in each tag
		// <Header for 'Synchronised tempo codes', ID: 'SYTC'>
		// Time stamp format   $xx
		// Tempo data          <binary data>
		//   Where time stamp format is:
		// $01  (32-bit value) MPEG frames from beginning of file
		// $02  (32-bit value) milliseconds from beginning of file

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['timestampformat'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		while ($frame_offset < strlen($ThisFileInfo['id3v2']["$frame_name"]['data'])) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['tempo'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
			if ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['tempo'] == 255) {
				$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['tempo'] += ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
			}
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['timestamp'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
			$frame_offset += 4;
			$frame_arrayindex++;
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'USLT')) || // 4.8   USLT Unsynchronised lyric/text transcription
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'ULT'))) {     // 4.9   ULT  Unsynchronised lyric/text transcription
		//   There may be more than one 'Unsynchronised lyrics/text transcription' frame
		//   in each tag, but only one with the same language and content descriptor.
		// <Header for 'Unsynchronised lyrics/text transcription', ID: 'USLT'>
		// Text encoding        $xx
		// Language             $xx xx xx
		// Content descriptor   <text string according to encoding> $00 (00)
		// Lyrics/text          <full text string according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_language = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
		$frame_offset += 3;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']   = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']     = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']         = $ThisFileInfo['id3v2']["$frame_name"]['data'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['language']     = $frame_language;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['languagename'] = LanguageLookup($frame_language, false);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']  = $frame_description;
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description'], $frame_textencoding);
		}
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']    = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'], $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'];
		}


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'SYLT')) || // 4.9   SYLT Synchronised lyric/text
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'SLT'))) {     // 4.10  SLT  Synchronised lyric/text
		//   There may be more than one 'SYLT' frame in each tag,
		//   but only one with the same language and content descriptor.
		// <Header for 'Synchronised lyrics/text', ID: 'SYLT'>
		// Text encoding        $xx
		// Language             $xx xx xx
		// Time stamp format    $xx
		//   $01  (32-bit value) MPEG frames from beginning of file
		//   $02  (32-bit value) milliseconds from beginning of file
		// Content type         $xx
		// Content descriptor   <text string according to encoding> $00 (00)
		//   Terminated text to be synced (typically a syllable)
		//   Sync identifier (terminator to above string)   $00 (00)
		//   Time stamp                                     $xx (xx ...)

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_language = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
		$frame_offset += 3;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['timestampformat'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['contenttypeid']   = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['contenttype']     = SYTLContentTypeLookup($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['contenttypeid']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']      = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']        = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['language']        = $frame_language;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['languagename']    = LanguageLookup($frame_language, false);
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']       = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}

		$timestampindex = 0;
		$frame_remainingdata = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		while (strlen($frame_remainingdata)) {
			$frame_offset = 0;
			$frame_terminatorpos = strpos($frame_remainingdata, TextEncodingLookup('terminator', $frame_textencoding));
			if ($frame_terminatorpos === false) {
				$frame_remainingdata = '';
			} else {
				if (ord(substr($frame_remainingdata, $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
					$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
				}
				$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'][$timestampindex]['data'] = substr($frame_remainingdata, $frame_offset, $frame_terminatorpos - $frame_offset);
				if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
					$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'][$timestampindex]['asciidata'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'][$timestampindex]['data'], $frame_textencoding);
				}

				$frame_remainingdata = substr($frame_remainingdata, $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));
				if (($timestampindex == 0) && (ord($frame_remainingdata{0}) != 0)) {
					// timestamp probably omitted for first data item
				} else {
					$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'][$timestampindex]['timestamp'] = BigEndian2Int(substr($frame_remainingdata, 0, 4));
					$frame_remainingdata = substr($frame_remainingdata, 4);
				}
				$timestampindex++;
			}
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'COMM')) || // 4.10  COMM Comments
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'COM'))) {     // 4.11  COM  Comments
		//   There may be more than one comment frame in each tag,
		//   but only one with the same language and content descriptor.
		// <Header for 'Comment', ID: 'COMM'>
		// Text encoding          $xx
		// Language               $xx xx xx
		// Short content descrip. <text string according to encoding> $00 (00)
		// The actual text        <full text string according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_language = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
		$frame_offset += 3;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$frame_text = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']   = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']     = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['language']     = $frame_language;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['languagename'] = LanguageLookup($frame_language, false);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']  = $frame_description;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']         = $frame_text;
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']    = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
		}
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata']        = RoughTranslateUnicodeToASCII($frame_text, $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidata'];
		}


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 4) && ($frame_name == 'RVA2')) { // 4.11  RVA2 Relative volume adjustment (2) (ID3v2.4+ only)
		//   There may be more than one 'RVA2' frame in each tag,
		//   but only one with the same identification string
		// <Header for 'Relative volume adjustment (2)', ID: 'RVA2'>
		// Identification          <text string> $00
		//   The 'identification' string is used to identify the situation and/or
		//   device where this adjustment should apply. The following is then
		//   repeated for every channel:
		// Type of channel         $xx
		// Volume adjustment       $xx xx
		// Bits representing peak  $xx
		// Peak volume             $xx (xx ...)

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0));
		$frame_idstring = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], 0, $frame_terminatorpos);
		if (ord($frame_idstring) === 0) {
			$frame_idstring = '';
		}
		$frame_remainingdata = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(chr(0)));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description'] = $frame_idstring;
		while (strlen($frame_remainingdata)) {
			$frame_offset = 0;
			$frame_channeltypeid = ord(substr($frame_remainingdata, $frame_offset++, 1));
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['channeltypeid']  = $frame_channeltypeid;
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['channeltype']    = RVA2ChannelTypeLookup($frame_channeltypeid);
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['volumeadjust']   = BigEndian2Int(substr($frame_remainingdata, $frame_offset, 2), false, true); // 16-bit signed
			$frame_offset += 2;
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['bitspeakvolume'] = ord(substr($frame_remainingdata, $frame_offset++, 1));
			$frame_bytespeakvolume = ceil($ThisFileInfo['id3v2']["$frame_name"][$frame_channeltypeid]['bitspeakvolume'] / 8);
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['peakvolume']     = BigEndian2Int(substr($frame_remainingdata, $frame_offset, $frame_bytespeakvolume));
			$frame_remainingdata = substr($frame_remainingdata, $frame_offset + $frame_bytespeakvolume);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex][$frame_channeltypeid]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] == 3) && ($frame_name == 'RVAD')) || // 4.12  RVAD Relative volume adjustment (ID3v2.3 only)
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'RVA'))) {     // 4.12  RVA  Relative volume adjustment (ID3v2.2 only)
		//   There may only be one 'RVA' frame in each tag
		// <Header for 'Relative volume adjustment', ID: 'RVA'>
		// ID3v2.2 => Increment/decrement     %000000ba
		// ID3v2.3 => Increment/decrement     %00fedcba
		// Bits used for volume descr.        $xx
		// Relative volume change, right      $xx xx (xx ...) // a
		// Relative volume change, left       $xx xx (xx ...) // b
		// Peak volume right                  $xx xx (xx ...)
		// Peak volume left                   $xx xx (xx ...)
		//   ID3v2.3 only, optional (not present in ID3v2.2):
		// Relative volume change, right back $xx xx (xx ...) // c
		// Relative volume change, left back  $xx xx (xx ...) // d
		// Peak volume right back             $xx xx (xx ...)
		// Peak volume left back              $xx xx (xx ...)
		//   ID3v2.3 only, optional (not present in ID3v2.2):
		// Relative volume change, center     $xx xx (xx ...) // e
		// Peak volume center                 $xx xx (xx ...)
		//   ID3v2.3 only, optional (not present in ID3v2.2):
		// Relative volume change, bass       $xx xx (xx ...) // f
		// Peak volume bass                   $xx xx (xx ...)

		$frame_offset = 0;
		$frame_incrdecrflags = BigEndian2Bin(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['incdec']['right'] = (bool) substr($frame_incrdecrflags, 6, 1);
		$ThisFileInfo['id3v2']["$frame_name"]['incdec']['left']  = (bool) substr($frame_incrdecrflags, 7, 1);
		$ThisFileInfo['id3v2']["$frame_name"]['bitsvolume'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$frame_bytesvolume = ceil($ThisFileInfo['id3v2']["$frame_name"]['bitsvolume'] / 8);
		$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['right'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
		if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['right'] === false) {
			$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['right'] *= -1;
		}
		$frame_offset += $frame_bytesvolume;
		$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['left'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
		if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['left'] === false) {
			$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['left'] *= -1;
		}
		$frame_offset += $frame_bytesvolume;
		$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['right'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
		$frame_offset += $frame_bytesvolume;
		$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['left']  = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
		$frame_offset += $frame_bytesvolume;
		if ($ThisFileInfo['id3v2']['majorversion'] == 3) {
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
			if (strlen($ThisFileInfo['id3v2']["$frame_name"]['data']) > 0) {
				$ThisFileInfo['id3v2']["$frame_name"]['incdec']['rightrear'] = (bool) substr($frame_incrdecrflags, 4, 1);
				$ThisFileInfo['id3v2']["$frame_name"]['incdec']['leftrear']  = (bool) substr($frame_incrdecrflags, 5, 1);
				$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['rightrear'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['rightrear'] === false) {
					$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['rightrear'] *= -1;
				}
				$frame_offset += $frame_bytesvolume;
				$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['leftrear'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['leftrear'] === false) {
					$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['leftrear'] *= -1;
				}
				$frame_offset += $frame_bytesvolume;
				$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['rightrear'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				$frame_offset += $frame_bytesvolume;
				$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['leftrear']  = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				$frame_offset += $frame_bytesvolume;
			}
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
			if (strlen($ThisFileInfo['id3v2']["$frame_name"]['data']) > 0) {
				$ThisFileInfo['id3v2']["$frame_name"]['incdec']['center'] = (bool) substr($frame_incrdecrflags, 3, 1);
				$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['center'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['center'] === false) {
					$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['center'] *= -1;
				}
				$frame_offset += $frame_bytesvolume;
				$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['center'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				$frame_offset += $frame_bytesvolume;
			}
			$ThisFileInfo['id3v2']["$frame_name"]['data'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
			if (strlen($ThisFileInfo['id3v2']["$frame_name"]['data']) > 0) {
				$ThisFileInfo['id3v2']["$frame_name"]['incdec']['bass'] = (bool) substr($frame_incrdecrflags, 2, 1);
				$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['bass'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				if ($ThisFileInfo['id3v2']["$frame_name"]['incdec']['bass'] === false) {
					$ThisFileInfo['id3v2']["$frame_name"]['volumechange']['bass'] *= -1;
				}
				$frame_offset += $frame_bytesvolume;
				$ThisFileInfo['id3v2']["$frame_name"]['peakvolume']['bass'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesvolume));
				$frame_offset += $frame_bytesvolume;
			}
		}
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 4) && ($frame_name == 'EQU2')) { // 4.12  EQU2 Equalisation (2) (ID3v2.4+ only)
		//   There may be more than one 'EQU2' frame in each tag,
		//   but only one with the same identification string
		// <Header of 'Equalisation (2)', ID: 'EQU2'>
		// Interpolation method  $xx
		//   $00  Band
		//   $01  Linear
		// Identification        <text string> $00
		//   The following is then repeated for every adjustment point
		// Frequency          $xx xx
		// Volume adjustment  $xx xx

		$frame_offset = 0;
		$frame_interpolationmethod = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_idstring = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_idstring) === 0) {
			$frame_idstring = '';
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description'] = $frame_idstring;
		$frame_remainingdata = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(chr(0)));
		while (strlen($frame_remainingdata)) {
			$frame_frequency = BigEndian2Int(substr($frame_remainingdata, 0, 2)) / 2;
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data'][$frame_frequency] = BigEndian2Int(substr($frame_remainingdata, 2, 2), false, true);
			$frame_remainingdata = substr($frame_remainingdata, 4);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['interpolationmethod'] = $frame_interpolationmethod;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] == 3) && ($frame_name == 'EQUA')) || // 4.12  EQUA Equalisation (ID3v2.3 only)
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'EQU'))) {     // 4.13  EQU  Equalisation (ID3v2.2 only)
		//   There may only be one 'EQUA' frame in each tag
		// <Header for 'Relative volume adjustment', ID: 'EQU'>
		// Adjustment bits    $xx
		//   This is followed by 2 bytes + ('adjustment bits' rounded up to the
		//   nearest byte) for every equalisation band in the following format,
		//   giving a frequency range of 0 - 32767Hz:
		// Increment/decrement   %x (MSB of the Frequency)
		// Frequency             (lower 15 bits)
		// Adjustment            $xx (xx ...)

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['adjustmentbits'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1);
		$frame_adjustmentbytes = ceil($ThisFileInfo['id3v2']["$frame_name"]['adjustmentbits'] / 8);

		$frame_remainingdata = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		while (strlen($frame_remainingdata)) {
			$frame_frequencystr = BigEndian2Bin(substr($frame_remainingdata, 0, 2));
			$frame_incdec    = (bool) substr($frame_frequencystr, 0, 1);
			$frame_frequency = bindec(substr($frame_frequencystr, 1, 15));
			$ThisFileInfo['id3v2']["$frame_name"][$frame_frequency]['incdec'] = $frame_incdec;
			$ThisFileInfo['id3v2']["$frame_name"][$frame_frequency]['adjustment'] = BigEndian2Int(substr($frame_remainingdata, 2, $frame_adjustmentbytes));
			if ($ThisFileInfo['id3v2']["$frame_name"][$frame_frequency]['incdec'] === false) {
				$ThisFileInfo['id3v2']["$frame_name"][$frame_frequency]['adjustment'] *= -1;
			}
			$frame_remainingdata = substr($frame_remainingdata, 2 + $frame_adjustmentbytes);
		}
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'RVRB')) || // 4.13  RVRB Reverb
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'REV'))) {     // 4.14  REV  Reverb
		//   There may only be one 'RVRB' frame in each tag.
		// <Header for 'Reverb', ID: 'RVRB'>
		// Reverb left (ms)                 $xx xx
		// Reverb right (ms)                $xx xx
		// Reverb bounces, left             $xx
		// Reverb bounces, right            $xx
		// Reverb feedback, left to left    $xx
		// Reverb feedback, left to right   $xx
		// Reverb feedback, right to right  $xx
		// Reverb feedback, right to left   $xx
		// Premix left to right             $xx
		// Premix right to left             $xx

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['left']  = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"]['right'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"]['bouncesL']      = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['bouncesR']      = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['feedbackLL']    = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['feedbackLR']    = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['feedbackRR']    = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['feedbackRL']    = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['premixLR']      = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['premixRL']      = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'APIC')) || // 4.14  APIC Attached picture
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'PIC'))) {     // 4.15  PIC  Attached picture
		//   There may be several pictures attached to one file,
		//   each in their individual 'APIC' frame, but only one
		//   with the same content descriptor
		// <Header for 'Attached picture', ID: 'APIC'>
		// Text encoding      $xx
		// ID3v2.3+ => MIME type          <text string> $00
		// ID3v2.2  => Image format       $xx xx xx
		// Picture type       $xx
		// Description        <text string according to encoding> $00 (00)
		// Picture data       <binary data>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);

		if ($ThisFileInfo['id3v2']['majorversion'] == 2) {
			$frame_imagetype = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
			if (strtolower($frame_imagetype) == 'ima') {
				// complete hack for mp3Rage (www.chaoticsoftware.com) that puts ID3v2.3-formatted
				// MIME type instead of 3-char ID3v2.2-format image type  (thanks xbhoff@pacbell.net)
				$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
				$frame_mimetype = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
				if (ord($frame_mimetype) === 0) {
					$frame_mimetype = '';
				}
				$frame_imagetype = strtoupper(str_replace('image/', '', strtolower($frame_mimetype)));
				if ($frame_imagetype == 'JPEG') {
					$frame_imagetype = 'JPG';
				}
				$frame_offset = $frame_terminatorpos + strlen(chr(0));
			} else {
				$frame_offset += 3;
			}
		}
		if ($ThisFileInfo['id3v2']['majorversion'] > 2) {
			$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
			$frame_mimetype = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
			if (ord($frame_mimetype) === 0) {
				$frame_mimetype = '';
			}
			$frame_offset = $frame_terminatorpos + strlen(chr(0));
		}

		$frame_picturetype = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']        = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']       = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']         = TextEncodingLookup('encoding', $frame_textencoding);
		if ($ThisFileInfo['id3v2']['majorversion'] == 2) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['imagetype']    = $frame_imagetype;
		} else {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['mime']         = $frame_mimetype;
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['picturetypeid']    = $frame_picturetype;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['picturetype']      = APICPictureTypeLookup($frame_picturetype);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']      = $frame_description;
		if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']             = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)));

		require_once(GETID3_INCLUDEPATH.'getid3.getimagesize.php');
		$imagechunkcheck = GetDataImageSize($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']);
		if (($imagechunkcheck[2] >= 1) && ($imagechunkcheck[2] <= 3)) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['image_mime']       = ImageTypesLookup($imagechunkcheck[2]);
			if ($imagechunkcheck[0]) {
				$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['image_width']  = $imagechunkcheck[0];
			}
			if ($imagechunkcheck[1]) {
				$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['image_height'] = $imagechunkcheck[1];
			}
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['image_bytes']      = strlen($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']);
		}

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong']    = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		if (isset($ThisFileInfo['id3v2']["$frame_name"]['datalength'])) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		}
		if (isset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset'])) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		}


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'GEOB')) || // 4.15  GEOB General encapsulated object
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'GEO'))) {     // 4.16  GEO  General encapsulated object
		//   There may be more than one 'GEOB' frame in each tag,
		//   but only one with the same content descriptor
		// <Header for 'General encapsulated object', ID: 'GEOB'>
		// Text encoding          $xx
		// MIME type              <text string> $00
		// Filename               <text string according to encoding> $00 (00)
		// Content description    <text string according to encoding> $00 (00)
		// Encapsulated object    <binary data>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_mimetype = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_mimetype) === 0) {
			$frame_mimetype = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_filename = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_filename) === 0) {
			$frame_filename = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['objectdata']       = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']       = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']         = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['mime']             = $frame_mimetype;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['filename']         = $frame_filename;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']      = $frame_description;
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']        = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			if (!isset($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression']) || ($ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']['compression'] === false)) {
				$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription'] = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
			}
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'PCNT')) || // 4.16  PCNT Play counter
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'CNT'))) {     // 4.17  CNT  Play counter
		//   There may only be one 'PCNT' frame in each tag.
		//   When the counter reaches all one's, one byte is inserted in
		//   front of the counter thus making the counter eight bits bigger
		// <Header for 'Play counter', ID: 'PCNT'>
		// Counter        $xx xx xx xx (xx ...)

		$ThisFileInfo['id3v2']["$frame_name"]['data']          = BigEndian2Int($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'POPM')) || // 4.17  POPM Popularimeter
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'POP'))) {     // 4.18  POP  Popularimeter
		//   There may be more than one 'POPM' frame in each tag,
		//   but only one with the same email address
		// <Header for 'Popularimeter', ID: 'POPM'>
		// Email to user   <text string> $00
		// Rating          $xx
		// Counter         $xx xx xx xx (xx ...)

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_emailaddress = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_emailaddress) === 0) {
			$frame_emailaddress = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));
		$frame_rating = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['data'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['email']  = $frame_emailaddress;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['rating'] = $frame_rating;
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'RBUF')) || // 4.18  RBUF Recommended buffer size
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'BUF'))) {     // 4.19  BUF  Recommended buffer size
		//   There may only be one 'RBUF' frame in each tag
		// <Header for 'Recommended buffer size', ID: 'RBUF'>
		// Buffer size               $xx xx xx
		// Embedded info flag        %0000000x
		// Offset to next tag        $xx xx xx xx

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['buffersize'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3));
		$frame_offset += 3;

		$frame_embeddedinfoflags = BigEndian2Bin(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['flags']['embededinfo'] = (bool) substr($frame_embeddedinfoflags, 7, 1);
		$ThisFileInfo['id3v2']["$frame_name"]['nexttagoffset'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'CRM')) { // 4.20  Encrypted meta frame (ID3v2.2 only)
		//   There may be more than one 'CRM' frame in a tag,
		//   but only one with the same 'owner identifier'
		// <Header for 'Encrypted meta frame', ID: 'CRM'>
		// Owner identifier      <textstring> $00 (00)
		// Content/explanation   <textstring> $00 (00)
		// Encrypted datablock   <binary data>

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_ownerid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_ownerid) === 0) {
			$frame_ownerid = count($ThisFileInfo['id3v2']["$frame_name"]) - 1;
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid']       = $frame_ownerid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']   = $frame_description;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'AENC')) || // 4.19  AENC Audio encryption
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'CRA'))) {     // 4.21  CRA  Audio encryption
		//   There may be more than one 'AENC' frames in a tag,
		//   but only one with the same 'Owner identifier'
		// <Header for 'Audio encryption', ID: 'AENC'>
		// Owner identifier   <text string> $00
		// Preview start      $xx xx
		// Preview length     $xx xx
		// Encryption info    <binary data>

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_ownerid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_ownerid) === 0) {
			$frame_ownerid == '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid'] = $frame_ownerid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['previewstart'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['previewlength'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encryptioninfo'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"]["$frame_ownerid"]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif ((($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'LINK')) || // 4.20  LINK Linked information
			(($ThisFileInfo['id3v2']['majorversion'] == 2) && ($frame_name == 'LNK'))) {     // 4.22  LNK  Linked information
		//   There may be more than one 'LINK' frame in a tag,
		//   but only one with the same contents
		// <Header for 'Linked information', ID: 'LINK'>
		// ID3v2.3+ => Frame identifier   $xx xx xx xx
		// ID3v2.2  => Frame identifier   $xx xx xx
		// URL                            <text string> $00
		// ID and additional data         <text string(s)>

		$frame_offset = 0;
		if ($ThisFileInfo['id3v2']['majorversion'] == 2) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['frameid'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
			$frame_offset += 3;
		} else {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['frameid'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4);
			$frame_offset += 4;
		}

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_url = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_url) === 0) {
			$frame_url = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url'] = $frame_url;

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['additionaldata'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		if ($ThisFileInfo['id3v2']['majorversion'] >= 3) {
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags'] = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
			unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		}
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['url'];
		}


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'POSS')) { // 4.21  POSS Position synchronisation frame (ID3v2.3+ only)
		//   There may only be one 'POSS' frame in each tag
		// <Head for 'Position synchronisation', ID: 'POSS'>
		// Time stamp format         $xx
		// Position                  $xx (xx ...)

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['timestampformat'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['position']        = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset));
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong']   = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'USER')) { // 4.22  USER Terms of use (ID3v2.3+ only)
		//   There may be more than one 'Terms of use' frame in a tag,
		//   but only one with the same 'Language'
		// <Header for 'Terms of use frame', ID: 'USER'>
		// Text encoding        $xx
		// Language             $xx xx xx
		// The actual text      <text string according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$frame_language = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 3);
		$frame_offset += 3;
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['language']      = $frame_language;
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['languagename']  = LanguageLookup($frame_language, false);
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['encodingid']    = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['encoding']      = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		if (!$ThisFileInfo['id3v2']["$frame_name"]['flags']['compression']) {
			$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['asciidata'] = RoughTranslateUnicodeToASCII($ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['data'], $frame_textencoding);
		}
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['flags']         = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);
		if (FrameNameShortLookup($frame_name) && $ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['asciidata']) {
			$ThisFileInfo['id3v2']['comments'][FrameNameShortLookup($frame_name)][] = $ThisFileInfo['id3v2']["$frame_name"]["$frame_language"]['asciidata'];
		}


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'OWNE')) { // 4.23  OWNE Ownership frame (ID3v2.3+ only)
		//   There may only be one 'OWNE' frame in a tag
		// <Header for 'Ownership frame', ID: 'OWNE'>
		// Text encoding     $xx
		// Price paid        <text string> $00
		// Date of purch.    <text string>
		// Seller            <text string according to encoding>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);
		$ThisFileInfo['id3v2']["$frame_name"]['encodingid'] = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"]['encoding']   = TextEncodingLookup('encoding', $frame_textencoding);

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_pricepaid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$ThisFileInfo['id3v2']["$frame_name"]['pricepaid']['currencyid'] = substr($frame_pricepaid, 0, 3);
		$ThisFileInfo['id3v2']["$frame_name"]['pricepaid']['currency']   = LookupCurrency($ThisFileInfo['id3v2']["$frame_name"]['pricepaid']['currencyid'], 'units');
		$ThisFileInfo['id3v2']["$frame_name"]['pricepaid']['value']      = substr($frame_pricepaid, 3);

		$ThisFileInfo['id3v2']["$frame_name"]['purchasedate'] = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 8);
		if (!IsValidDateStampString($ThisFileInfo['id3v2']["$frame_name"]['purchasedate'])) {
			$ThisFileInfo['id3v2']["$frame_name"]['purchasedateunix'] = mktime (0, 0, 0, substr($ThisFileInfo['id3v2']["$frame_name"]['purchasedate'], 4, 2), substr($ThisFileInfo['id3v2']["$frame_name"]['purchasedate'], 6, 2), substr($ThisFileInfo['id3v2']["$frame_name"]['purchasedate'], 0, 4));
		}
		$frame_offset += 8;

		$ThisFileInfo['id3v2']["$frame_name"]['seller']        = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'COMR')) { // 4.24  COMR Commercial frame (ID3v2.3+ only)
		//   There may be more than one 'commercial frame' in a tag,
		//   but no two may be identical
		// <Header for 'Commercial frame', ID: 'COMR'>
		// Text encoding      $xx
		// Price string       <text string> $00
		// Valid until        <text string>
		// Contact URL        <text string> $00
		// Received as        $xx
		// Name of seller     <text string according to encoding> $00 (00)
		// Description        <text string according to encoding> $00 (00)
		// Picture MIME type  <string> $00
		// Seller logo        <binary data>

		$frame_offset = 0;
		$frame_textencoding = TextEncodingVerified(ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1)), $ThisFileInfo, $frame_name);

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_pricestring = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		$frame_offset = $frame_terminatorpos + strlen(chr(0));
		$frame_rawpricearray = explode('/', $frame_pricestring);
		foreach ($frame_rawpricearray as $key => $val) {
			$frame_currencyid = substr($val, 0, 3);
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['price']["$frame_currencyid"]['currency'] = LookupCurrency($frame_currencyid, 'units');
			$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['price']["$frame_currencyid"]['value']    = substr($val, 3);
		}

		$frame_datestring = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 8);
		$frame_offset += 8;

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_contacturl = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$frame_receivedasid = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_sellername = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_sellername) === 0) {
			$frame_sellername = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], TextEncodingLookup('terminator', $frame_textencoding), $frame_offset);
		if (ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding)), 1)) === 0) {
			$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
		}
		$frame_description = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_description) === 0) {
			$frame_description = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(TextEncodingLookup('terminator', $frame_textencoding));

		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_mimetype = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$frame_sellerlogo = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encodingid']        = $frame_textencoding;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['encoding']          = TextEncodingLookup('encoding', $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['pricevaliduntil']   = $frame_datestring;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['contacturl']        = $frame_contacturl;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['receivedasid']      = $frame_receivedasid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['receivedas']        = COMRReceivedAsLookup($frame_receivedasid);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['sellername']        = $frame_sellername;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciisellername']   = RoughTranslateUnicodeToASCII($frame_sellername, $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['description']       = $frame_description;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['asciidescription']  = RoughTranslateUnicodeToASCII($frame_description, $frame_textencoding);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['mime']              = $frame_mimetype;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['logo']              = $frame_sellerlogo;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']             = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong']     = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'ENCR')) { // 4.25  ENCR Encryption method registration (ID3v2.3+ only)
		//   There may be several 'ENCR' frames in a tag,
		//   but only one containing the same symbol
		//   and only one containing the same owner identifier
		// <Header for 'Encryption method registration', ID: 'ENCR'>
		// Owner identifier    <text string> $00
		// Method symbol       $xx
		// Encryption data     <binary data>

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_ownerid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_ownerid) === 0) {
			$frame_ownerid = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid']       = $frame_ownerid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['methodsymbol']  = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']         = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'GRID')) { // 4.26  GRID Group identification registration (ID3v2.3+ only)

		//   There may be several 'GRID' frames in a tag,
		//   but only one containing the same symbol
		//   and only one containing the same owner identifier
		// <Header for 'Group ID registration', ID: 'GRID'>
		// Owner identifier      <text string> $00
		// Group symbol          $xx
		// Group dependent data  <binary data>

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_ownerid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_ownerid) === 0) {
			$frame_ownerid = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid']       = $frame_ownerid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['groupsymbol']   = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']         = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'PRIV')) { // 4.27  PRIV Private frame (ID3v2.3+ only)
		//   The tag may contain more than one 'PRIV' frame
		//   but only with different contents
		// <Header for 'Private frame', ID: 'PRIV'>
		// Owner identifier      <text string> $00
		// The private data      <binary data>

		$frame_offset = 0;
		$frame_terminatorpos = strpos($ThisFileInfo['id3v2']["$frame_name"]['data'], chr(0), $frame_offset);
		$frame_ownerid = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
		if (ord($frame_ownerid) === 0) {
			$frame_ownerid = '';
		}
		$frame_offset = $frame_terminatorpos + strlen(chr(0));

		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['ownerid']       = $frame_ownerid;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']         = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 4) && ($frame_name == 'SIGN')) { // 4.28  SIGN Signature frame (ID3v2.4+ only)
		//   There may be more than one 'signature frame' in a tag,
		//   but no two may be identical
		// <Header for 'Signature frame', ID: 'SIGN'>
		// Group symbol      $xx
		// Signature         <binary data>

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['groupsymbol']   = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['data']          = substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['flags']         = $ThisFileInfo['id3v2']["$frame_name"]['flags'];
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['flags']);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['datalength'] = $ThisFileInfo['id3v2']["$frame_name"]['datalength'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['datalength']);
		$ThisFileInfo['id3v2']["$frame_name"][$frame_arrayindex]['dataoffset'] = $ThisFileInfo['id3v2']["$frame_name"]['dataoffset'];
		unset($ThisFileInfo['id3v2']["$frame_name"]['dataoffset']);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 4) && ($frame_name == 'SEEK')) { // 4.29  SEEK Seek frame (ID3v2.4+ only)
		//   There may only be one 'seek frame' in a tag
		// <Header for 'Seek frame', ID: 'SEEK'>
		// Minimum offset to next tag       $xx xx xx xx

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['data']          = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);


	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 4) && ($frame_name == 'ASPI')) { // 4.30  ASPI Audio seek point index (ID3v2.4+ only)
		//   There may only be one 'audio seek point index' frame in a tag
		// <Header for 'Seek Point Index', ID: 'ASPI'>
		// Indexed data start (S)         $xx xx xx xx
		// Indexed data length (L)        $xx xx xx xx
		// Number of index points (N)     $xx xx
		// Bits per index point (b)       $xx
		//   Then for every index point the following data is included:
		// Fraction at index (Fi)          $xx (xx)

		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['datastart'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
		$frame_offset += 4;
		$ThisFileInfo['id3v2']["$frame_name"]['indexeddatalength'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
		$frame_offset += 4;
		$ThisFileInfo['id3v2']["$frame_name"]['indexpoints'] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"]['bitsperpoint'] = ord(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset++, 1));
		$frame_bytesperpoint = ceil($ThisFileInfo['id3v2']["$frame_name"]['bitsperpoint'] / 8);
		for ($i = 0; $i < $frame_indexpoints; $i++) {
			$ThisFileInfo['id3v2']["$frame_name"]['indexes'][$i] = BigEndian2Int(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, $frame_bytesperpoint));
			$frame_offset += $frame_bytesperpoint;
		}
		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);

	} elseif (($ThisFileInfo['id3v2']['majorversion'] >= 3) && ($frame_name == 'RGAD')) { // Replay Gain Adjustment
		// http://privatewww.essex.ac.uk/~djmrob/replaygain/file_format_id3v2.html
		//   There may only be one 'RGAD' frame in a tag
		// <Header for 'Replay Gain Adjustment', ID: 'RGAD'>
		// Peak Amplitude                      $xx $xx $xx $xx
		// Radio Replay Gain Adjustment        %aaabbbcd %dddddddd
		// Audiophile Replay Gain Adjustment   %aaabbbcd %dddddddd
		//   a - name code
		//   b - originator code
		//   c - sign bit
		//   d - replay gain adjustment

		require_once(GETID3_INCLUDEPATH.'getid3.rgad.php');
		$frame_offset = 0;
		$ThisFileInfo['id3v2']["$frame_name"]['peakamplitude'] = BigEndian2Float(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 4));
		$frame_offset += 4;
		$radioadjustment = Dec2Bin(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$audiophileadjustment = Dec2Bin(substr($ThisFileInfo['id3v2']["$frame_name"]['data'], $frame_offset, 2));
		$frame_offset += 2;
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['name']            = Bin2Dec(substr($radioadjustment, 0, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['originator']      = Bin2Dec(substr($radioadjustment, 3, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['signbit']         = Bin2Dec(substr($radioadjustment, 6, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['adjustment']      = Bin2Dec(substr($radioadjustment, 7, 9));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['name']       = Bin2Dec(substr($audiophileadjustment, 0, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['originator'] = Bin2Dec(substr($audiophileadjustment, 3, 3));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['signbit']    = Bin2Dec(substr($audiophileadjustment, 6, 1));
		$ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['adjustment'] = Bin2Dec(substr($audiophileadjustment, 7, 9));
		$ThisFileInfo['id3v2']["$frame_name"]['radio']['name']       = RGADnameLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['name']);
		$ThisFileInfo['id3v2']["$frame_name"]['radio']['originator'] = RGADoriginatorLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['originator']);
		$ThisFileInfo['id3v2']["$frame_name"]['radio']['adjustment'] = RGADadjustmentLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['adjustment'], $ThisFileInfo['id3v2']["$frame_name"]['raw']['radio']['signbit']);
		$ThisFileInfo['id3v2']["$frame_name"]['audiophile']['name']       = RGADnameLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['name']);
		$ThisFileInfo['id3v2']["$frame_name"]['audiophile']['originator'] = RGADoriginatorLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['originator']);
		$ThisFileInfo['id3v2']["$frame_name"]['audiophile']['adjustment'] = RGADadjustmentLookup($ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['adjustment'], $ThisFileInfo['id3v2']["$frame_name"]['raw']['audiophile']['signbit']);

		$ThisFileInfo['replay_gain']['radio']['peak']            = $ThisFileInfo['id3v2']["$frame_name"]['peakamplitude'];
		$ThisFileInfo['replay_gain']['radio']['originator']      = $ThisFileInfo['id3v2']["$frame_name"]['radio']['originator'];
		$ThisFileInfo['replay_gain']['radio']['adjustment']      = $ThisFileInfo['id3v2']["$frame_name"]['radio']['adjustment'];
		$ThisFileInfo['replay_gain']['audiophile']['originator'] = $ThisFileInfo['id3v2']["$frame_name"]['audiophile']['originator'];
		$ThisFileInfo['replay_gain']['audiophile']['adjustment'] = $ThisFileInfo['id3v2']["$frame_name"]['audiophile']['adjustment'];

		$ThisFileInfo['id3v2']["$frame_name"]['framenamelong'] = FrameNameLongLookup($frame_name);
		unset($ThisFileInfo['id3v2']["$frame_name"]['data']);

	}

	return true;
}

function LookupCurrency($currencyid, $item) {
	static $CurrencyLookup = array();
	if (empty($CurrencyLookup)) {
		$CurrencyLookup['AED']['country'] = 'United Arab Emirates';
		$CurrencyLookup['AFA']['country'] = 'Afghanistan';
		$CurrencyLookup['ALL']['country'] = 'Albania';
		$CurrencyLookup['AMD']['country'] = 'Armenia';
		$CurrencyLookup['ANG']['country'] = 'Netherlands Antilles';
		$CurrencyLookup['AOA']['country'] = 'Angola';
		$CurrencyLookup['ARS']['country'] = 'Argentina';
		$CurrencyLookup['ATS']['country'] = 'Austria';
		$CurrencyLookup['AUD']['country'] = 'Australia';
		$CurrencyLookup['AWG']['country'] = 'Aruba';
		$CurrencyLookup['AZM']['country'] = 'Azerbaijan';
		$CurrencyLookup['BAM']['country'] = 'Bosnia and Herzegovina';
		$CurrencyLookup['BBD']['country'] = 'Barbados';
		$CurrencyLookup['BDT']['country'] = 'Bangladesh';
		$CurrencyLookup['BEF']['country'] = 'Belgium';
		$CurrencyLookup['BGL']['country'] = 'Bulgaria';
		$CurrencyLookup['BHD']['country'] = 'Bahrain';
		$CurrencyLookup['BIF']['country'] = 'Burundi';
		$CurrencyLookup['BMD']['country'] = 'Bermuda';
		$CurrencyLookup['BND']['country'] = 'Brunei Darussalam';
		$CurrencyLookup['BOB']['country'] = 'Bolivia';
		$CurrencyLookup['BRL']['country'] = 'Brazil';
		$CurrencyLookup['BSD']['country'] = 'Bahamas';
		$CurrencyLookup['BTN']['country'] = 'Bhutan';
		$CurrencyLookup['BWP']['country'] = 'Botswana';
		$CurrencyLookup['BYR']['country'] = 'Belarus';
		$CurrencyLookup['BZD']['country'] = 'Belize';
		$CurrencyLookup['CAD']['country'] = 'Canada';
		$CurrencyLookup['CDF']['country'] = 'Congo/Kinshasa';
		$CurrencyLookup['CHF']['country'] = 'Switzerland';
		$CurrencyLookup['CLP']['country'] = 'Chile';
		$CurrencyLookup['CNY']['country'] = 'China';
		$CurrencyLookup['COP']['country'] = 'Colombia';
		$CurrencyLookup['CRC']['country'] = 'Costa Rica';
		$CurrencyLookup['CUP']['country'] = 'Cuba';
		$CurrencyLookup['CVE']['country'] = 'Cape Verde';
		$CurrencyLookup['CYP']['country'] = 'Cyprus';
		$CurrencyLookup['CZK']['country'] = 'Czech Republic';
		$CurrencyLookup['DEM']['country'] = 'Germany';
		$CurrencyLookup['DJF']['country'] = 'Djibouti';
		$CurrencyLookup['DKK']['country'] = 'Denmark';
		$CurrencyLookup['DOP']['country'] = 'Dominican Republic';
		$CurrencyLookup['DZD']['country'] = 'Algeria';
		$CurrencyLookup['EEK']['country'] = 'Estonia';
		$CurrencyLookup['EGP']['country'] = 'Egypt';
		$CurrencyLookup['ERN']['country'] = 'Eritrea';
		$CurrencyLookup['ESP']['country'] = 'Spain';
		$CurrencyLookup['ETB']['country'] = 'Ethiopia';
		$CurrencyLookup['EUR']['country'] = 'Euro Member Countries';
		$CurrencyLookup['FIM']['country'] = 'Finland';
		$CurrencyLookup['FJD']['country'] = 'Fiji';
		$CurrencyLookup['FKP']['country'] = 'Falkland Islands (Malvinas)';
		$CurrencyLookup['FRF']['country'] = 'France';
		$CurrencyLookup['GBP']['country'] = 'United Kingdom';
		$CurrencyLookup['GEL']['country'] = 'Georgia';
		$CurrencyLookup['GGP']['country'] = 'Guernsey';
		$CurrencyLookup['GHC']['country'] = 'Ghana';
		$CurrencyLookup['GIP']['country'] = 'Gibraltar';
		$CurrencyLookup['GMD']['country'] = 'Gambia';
		$CurrencyLookup['GNF']['country'] = 'Guinea';
		$CurrencyLookup['GRD']['country'] = 'Greece';
		$CurrencyLookup['GTQ']['country'] = 'Guatemala';
		$CurrencyLookup['GYD']['country'] = 'Guyana';
		$CurrencyLookup['HKD']['country'] = 'Hong Kong';
		$CurrencyLookup['HNL']['country'] = 'Honduras';
		$CurrencyLookup['HRK']['country'] = 'Croatia';
		$CurrencyLookup['HTG']['country'] = 'Haiti';
		$CurrencyLookup['HUF']['country'] = 'Hungary';
		$CurrencyLookup['IDR']['country'] = 'Indonesia';
		$CurrencyLookup['IEP']['country'] = 'Ireland (Eire)';
		$CurrencyLookup['ILS']['country'] = 'Israel';
		$CurrencyLookup['IMP']['country'] = 'Isle of Man';
		$CurrencyLookup['INR']['country'] = 'India';
		$CurrencyLookup['IQD']['country'] = 'Iraq';
		$CurrencyLookup['IRR']['country'] = 'Iran';
		$CurrencyLookup['ISK']['country'] = 'Iceland';
		$CurrencyLookup['ITL']['country'] = 'Italy';
		$CurrencyLookup['JEP']['country'] = 'Jersey';
		$CurrencyLookup['JMD']['country'] = 'Jamaica';
		$CurrencyLookup['JOD']['country'] = 'Jordan';
		$CurrencyLookup['JPY']['country'] = 'Japan';
		$CurrencyLookup['KES']['country'] = 'Kenya';
		$CurrencyLookup['KGS']['country'] = 'Kyrgyzstan';
		$CurrencyLookup['KHR']['country'] = 'Cambodia';
		$CurrencyLookup['KMF']['country'] = 'Comoros';
		$CurrencyLookup['KPW']['country'] = 'Korea';
		$CurrencyLookup['KWD']['country'] = 'Kuwait';
		$CurrencyLookup['KYD']['country'] = 'Cayman Islands';
		$CurrencyLookup['KZT']['country'] = 'Kazakstan';
		$CurrencyLookup['LAK']['country'] = 'Laos';
		$CurrencyLookup['LBP']['country'] = 'Lebanon';
		$CurrencyLookup['LKR']['country'] = 'Sri Lanka';
		$CurrencyLookup['LRD']['country'] = 'Liberia';
		$CurrencyLookup['LSL']['country'] = 'Lesotho';
		$CurrencyLookup['LTL']['country'] = 'Lithuania';
		$CurrencyLookup['LUF']['country'] = 'Luxembourg';
		$CurrencyLookup['LVL']['country'] = 'Latvia';
		$CurrencyLookup['LYD']['country'] = 'Libya';
		$CurrencyLookup['MAD']['country'] = 'Morocco';
		$CurrencyLookup['MDL']['country'] = 'Moldova';
		$CurrencyLookup['MGF']['country'] = 'Madagascar';
		$CurrencyLookup['MKD']['country'] = 'Macedonia';
		$CurrencyLookup['MMK']['country'] = 'Myanmar (Burma)';
		$CurrencyLookup['MNT']['country'] = 'Mongolia';
		$CurrencyLookup['MOP']['country'] = 'Macau';
		$CurrencyLookup['MRO']['country'] = 'Mauritania';
		$CurrencyLookup['MTL']['country'] = 'Malta';
		$CurrencyLookup['MUR']['country'] = 'Mauritius';
		$CurrencyLookup['MVR']['country'] = 'Maldives (Maldive Islands)';
		$CurrencyLookup['MWK']['country'] = 'Malawi';
		$CurrencyLookup['MXN']['country'] = 'Mexico';
		$CurrencyLookup['MYR']['country'] = 'Malaysia';
		$CurrencyLookup['MZM']['country'] = 'Mozambique';
		$CurrencyLookup['NAD']['country'] = 'Namibia';
		$CurrencyLookup['NGN']['country'] = 'Nigeria';
		$CurrencyLookup['NIO']['country'] = 'Nicaragua';
		$CurrencyLookup['NLG']['country'] = 'Netherlands (Holland)';
		$CurrencyLookup['NOK']['country'] = 'Norway';
		$CurrencyLookup['NPR']['country'] = 'Nepal';
		$CurrencyLookup['NZD']['country'] = 'New Zealand';
		$CurrencyLookup['OMR']['country'] = 'Oman';
		$CurrencyLookup['PAB']['country'] = 'Panama';
		$CurrencyLookup['PEN']['country'] = 'Peru';
		$CurrencyLookup['PGK']['country'] = 'Papua New Guinea';
		$CurrencyLookup['PHP']['country'] = 'Philippines';
		$CurrencyLookup['PKR']['country'] = 'Pakistan';
		$CurrencyLookup['PLN']['country'] = 'Poland';
		$CurrencyLookup['PTE']['country'] = 'Portugal';
		$CurrencyLookup['PYG']['country'] = 'Paraguay';
		$CurrencyLookup['QAR']['country'] = 'Qatar';
		$CurrencyLookup['ROL']['country'] = 'Romania';
		$CurrencyLookup['RUR']['country'] = 'Russia';
		$CurrencyLookup['RWF']['country'] = 'Rwanda';
		$CurrencyLookup['SAR']['country'] = 'Saudi Arabia';
		$CurrencyLookup['SBD']['country'] = 'Solomon Islands';
		$CurrencyLookup['SCR']['country'] = 'Seychelles';
		$CurrencyLookup['SDD']['country'] = 'Sudan';
		$CurrencyLookup['SEK']['country'] = 'Sweden';
		$CurrencyLookup['SGD']['country'] = 'Singapore';
		$CurrencyLookup['SHP']['country'] = 'Saint Helena';
		$CurrencyLookup['SIT']['country'] = 'Slovenia';
		$CurrencyLookup['SKK']['country'] = 'Slovakia';
		$CurrencyLookup['SLL']['country'] = 'Sierra Leone';
		$CurrencyLookup['SOS']['country'] = 'Somalia';
		$CurrencyLookup['SPL']['country'] = 'Seborga';
		$CurrencyLookup['SRG']['country'] = 'Suriname';
		$CurrencyLookup['STD']['country'] = 'São Tome and Principe';
		$CurrencyLookup['SVC']['country'] = 'El Salvador';
		$CurrencyLookup['SYP']['country'] = 'Syria';
		$CurrencyLookup['SZL']['country'] = 'Swaziland';
		$CurrencyLookup['THB']['country'] = 'Thailand';
		$CurrencyLookup['TJR']['country'] = 'Tajikistan';
		$CurrencyLookup['TMM']['country'] = 'Turkmenistan';
		$CurrencyLookup['TND']['country'] = 'Tunisia';
		$CurrencyLookup['TOP']['country'] = 'Tonga';
		$CurrencyLookup['TRL']['country'] = 'Turkey';
		$CurrencyLookup['TTD']['country'] = 'Trinidad and Tobago';
		$CurrencyLookup['TVD']['country'] = 'Tuvalu';
		$CurrencyLookup['TWD']['country'] = 'Taiwan';
		$CurrencyLookup['TZS']['country'] = 'Tanzania';
		$CurrencyLookup['UAH']['country'] = 'Ukraine';
		$CurrencyLookup['UGX']['country'] = 'Uganda';
		$CurrencyLookup['USD']['country'] = 'United States of America';
		$CurrencyLookup['UYU']['country'] = 'Uruguay';
		$CurrencyLookup['UZS']['country'] = 'Uzbekistan';
		$CurrencyLookup['VAL']['country'] = 'Vatican City';
		$CurrencyLookup['VEB']['country'] = 'Venezuela';
		$CurrencyLookup['VND']['country'] = 'Viet Nam';
		$CurrencyLookup['VUV']['country'] = 'Vanuatu';
		$CurrencyLookup['WST']['country'] = 'Samoa';
		$CurrencyLookup['XAF']['country'] = 'Communauté Financière Africaine';
		$CurrencyLookup['XAG']['country'] = 'Silver';
		$CurrencyLookup['XAU']['country'] = 'Gold';
		$CurrencyLookup['XCD']['country'] = 'East Caribbean';
		$CurrencyLookup['XDR']['country'] = 'International Monetary Fund';
		$CurrencyLookup['XPD']['country'] = 'Palladium';
		$CurrencyLookup['XPF']['country'] = 'Comptoirs Français du Pacifique';
		$CurrencyLookup['XPT']['country'] = 'Platinum';
		$CurrencyLookup['YER']['country'] = 'Yemen';
		$CurrencyLookup['YUM']['country'] = 'Yugoslavia';
		$CurrencyLookup['ZAR']['country'] = 'South Africa';
		$CurrencyLookup['ZMK']['country'] = 'Zambia';
		$CurrencyLookup['ZWD']['country'] = 'Zimbabwe';
		$CurrencyLookup['AED']['units']   = 'Dirhams';
		$CurrencyLookup['AFA']['units']   = 'Afghanis';
		$CurrencyLookup['ALL']['units']   = 'Leke';
		$CurrencyLookup['AMD']['units']   = 'Drams';
		$CurrencyLookup['ANG']['units']   = 'Guilders';
		$CurrencyLookup['AOA']['units']   = 'Kwanza';
		$CurrencyLookup['ARS']['units']   = 'Pesos';
		$CurrencyLookup['ATS']['units']   = 'Schillings';
		$CurrencyLookup['AUD']['units']   = 'Dollars';
		$CurrencyLookup['AWG']['units']   = 'Guilders';
		$CurrencyLookup['AZM']['units']   = 'Manats';
		$CurrencyLookup['BAM']['units']   = 'Convertible Marka';
		$CurrencyLookup['BBD']['units']   = 'Dollars';
		$CurrencyLookup['BDT']['units']   = 'Taka';
		$CurrencyLookup['BEF']['units']   = 'Francs';
		$CurrencyLookup['BGL']['units']   = 'Leva';
		$CurrencyLookup['BHD']['units']   = 'Dinars';
		$CurrencyLookup['BIF']['units']   = 'Francs';
		$CurrencyLookup['BMD']['units']   = 'Dollars';
		$CurrencyLookup['BND']['units']   = 'Dollars';
		$CurrencyLookup['BOB']['units']   = 'Bolivianos';
		$CurrencyLookup['BRL']['units']   = 'Brazil Real';
		$CurrencyLookup['BSD']['units']   = 'Dollars';
		$CurrencyLookup['BTN']['units']   = 'Ngultrum';
		$CurrencyLookup['BWP']['units']   = 'Pulas';
		$CurrencyLookup['BYR']['units']   = 'Rubles';
		$CurrencyLookup['BZD']['units']   = 'Dollars';
		$CurrencyLookup['CAD']['units']   = 'Dollars';
		$CurrencyLookup['CDF']['units']   = 'Congolese Francs';
		$CurrencyLookup['CHF']['units']   = 'Francs';
		$CurrencyLookup['CLP']['units']   = 'Pesos';
		$CurrencyLookup['CNY']['units']   = 'Yuan Renminbi';
		$CurrencyLookup['COP']['units']   = 'Pesos';
		$CurrencyLookup['CRC']['units']   = 'Colones';
		$CurrencyLookup['CUP']['units']   = 'Pesos';
		$CurrencyLookup['CVE']['units']   = 'Escudos';
		$CurrencyLookup['CYP']['units']   = 'Pounds';
		$CurrencyLookup['CZK']['units']   = 'Koruny';
		$CurrencyLookup['DEM']['units']   = 'Deutsche Marks';
		$CurrencyLookup['DJF']['units']   = 'Francs';
		$CurrencyLookup['DKK']['units']   = 'Kroner';
		$CurrencyLookup['DOP']['units']   = 'Pesos';
		$CurrencyLookup['DZD']['units']   = 'Algeria Dinars';
		$CurrencyLookup['EEK']['units']   = 'Krooni';
		$CurrencyLookup['EGP']['units']   = 'Pounds';
		$CurrencyLookup['ERN']['units']   = 'Nakfa';
		$CurrencyLookup['ESP']['units']   = 'Pesetas';
		$CurrencyLookup['ETB']['units']   = 'Birr';
		$CurrencyLookup['EUR']['units']   = 'Euro';
		$CurrencyLookup['FIM']['units']   = 'Markkaa';
		$CurrencyLookup['FJD']['units']   = 'Dollars';
		$CurrencyLookup['FKP']['units']   = 'Pounds';
		$CurrencyLookup['FRF']['units']   = 'Francs';
		$CurrencyLookup['GBP']['units']   = 'Pounds';
		$CurrencyLookup['GEL']['units']   = 'Lari';
		$CurrencyLookup['GGP']['units']   = 'Pounds';
		$CurrencyLookup['GHC']['units']   = 'Cedis';
		$CurrencyLookup['GIP']['units']   = 'Pounds';
		$CurrencyLookup['GMD']['units']   = 'Dalasi';
		$CurrencyLookup['GNF']['units']   = 'Francs';
		$CurrencyLookup['GRD']['units']   = 'Drachmae';
		$CurrencyLookup['GTQ']['units']   = 'Quetzales';
		$CurrencyLookup['GYD']['units']   = 'Dollars';
		$CurrencyLookup['HKD']['units']   = 'Dollars';
		$CurrencyLookup['HNL']['units']   = 'Lempiras';
		$CurrencyLookup['HRK']['units']   = 'Kuna';
		$CurrencyLookup['HTG']['units']   = 'Gourdes';
		$CurrencyLookup['HUF']['units']   = 'Forints';
		$CurrencyLookup['IDR']['units']   = 'Rupiahs';
		$CurrencyLookup['IEP']['units']   = 'Pounds';
		$CurrencyLookup['ILS']['units']   = 'New Shekels';
		$CurrencyLookup['IMP']['units']   = 'Pounds';
		$CurrencyLookup['INR']['units']   = 'Rupees';
		$CurrencyLookup['IQD']['units']   = 'Dinars';
		$CurrencyLookup['IRR']['units']   = 'Rials';
		$CurrencyLookup['ISK']['units']   = 'Kronur';
		$CurrencyLookup['ITL']['units']   = 'Lire';
		$CurrencyLookup['JEP']['units']   = 'Pounds';
		$CurrencyLookup['JMD']['units']   = 'Dollars';
		$CurrencyLookup['JOD']['units']   = 'Dinars';
		$CurrencyLookup['JPY']['units']   = 'Yen';
		$CurrencyLookup['KES']['units']   = 'Shillings';
		$CurrencyLookup['KGS']['units']   = 'Soms';
		$CurrencyLookup['KHR']['units']   = 'Riels';
		$CurrencyLookup['KMF']['units']   = 'Francs';
		$CurrencyLookup['KPW']['units']   = 'Won';
		$CurrencyLookup['KWD']['units']   = 'Dinars';
		$CurrencyLookup['KYD']['units']   = 'Dollars';
		$CurrencyLookup['KZT']['units']   = 'Tenge';
		$CurrencyLookup['LAK']['units']   = 'Kips';
		$CurrencyLookup['LBP']['units']   = 'Pounds';
		$CurrencyLookup['LKR']['units']   = 'Rupees';
		$CurrencyLookup['LRD']['units']   = 'Dollars';
		$CurrencyLookup['LSL']['units']   = 'Maloti';
		$CurrencyLookup['LTL']['units']   = 'Litai';
		$CurrencyLookup['LUF']['units']   = 'Francs';
		$CurrencyLookup['LVL']['units']   = 'Lati';
		$CurrencyLookup['LYD']['units']   = 'Dinars';
		$CurrencyLookup['MAD']['units']   = 'Dirhams';
		$CurrencyLookup['MDL']['units']   = 'Lei';
		$CurrencyLookup['MGF']['units']   = 'Malagasy Francs';
		$CurrencyLookup['MKD']['units']   = 'Denars';
		$CurrencyLookup['MMK']['units']   = 'Kyats';
		$CurrencyLookup['MNT']['units']   = 'Tugriks';
		$CurrencyLookup['MOP']['units']   = 'Patacas';
		$CurrencyLookup['MRO']['units']   = 'Ouguiyas';
		$CurrencyLookup['MTL']['units']   = 'Liri';
		$CurrencyLookup['MUR']['units']   = 'Rupees';
		$CurrencyLookup['MVR']['units']   = 'Rufiyaa';
		$CurrencyLookup['MWK']['units']   = 'Kwachas';
		$CurrencyLookup['MXN']['units']   = 'Pesos';
		$CurrencyLookup['MYR']['units']   = 'Ringgits';
		$CurrencyLookup['MZM']['units']   = 'Meticais';
		$CurrencyLookup['NAD']['units']   = 'Dollars';
		$CurrencyLookup['NGN']['units']   = 'Nairas';
		$CurrencyLookup['NIO']['units']   = 'Gold Cordobas';
		$CurrencyLookup['NLG']['units']   = 'Guilders';
		$CurrencyLookup['NOK']['units']   = 'Krone';
		$CurrencyLookup['NPR']['units']   = 'Nepal Rupees';
		$CurrencyLookup['NZD']['units']   = 'Dollars';
		$CurrencyLookup['OMR']['units']   = 'Rials';
		$CurrencyLookup['PAB']['units']   = 'Balboa';
		$CurrencyLookup['PEN']['units']   = 'Nuevos Soles';
		$CurrencyLookup['PGK']['units']   = 'Kina';
		$CurrencyLookup['PHP']['units']   = 'Pesos';
		$CurrencyLookup['PKR']['units']   = 'Rupees';
		$CurrencyLookup['PLN']['units']   = 'Zlotych';
		$CurrencyLookup['PTE']['units']   = 'Escudos';
		$CurrencyLookup['PYG']['units']   = 'Guarani';
		$CurrencyLookup['QAR']['units']   = 'Rials';
		$CurrencyLookup['ROL']['units']   = 'Lei';
		$CurrencyLookup['RUR']['units']   = 'Rubles';
		$CurrencyLookup['RWF']['units']   = 'Rwanda Francs';
		$CurrencyLookup['SAR']['units']   = 'Riyals';
		$CurrencyLookup['SBD']['units']   = 'Dollars';
		$CurrencyLookup['SCR']['units']   = 'Rupees';
		$CurrencyLookup['SDD']['units']   = 'Dinars';
		$CurrencyLookup['SEK']['units']   = 'Kronor';
		$CurrencyLookup['SGD']['units']   = 'Dollars';
		$CurrencyLookup['SHP']['units']   = 'Pounds';
		$CurrencyLookup['SIT']['units']   = 'Tolars';
		$CurrencyLookup['SKK']['units']   = 'Koruny';
		$CurrencyLookup['SLL']['units']   = 'Leones';
		$CurrencyLookup['SOS']['units']   = 'Shillings';
		$CurrencyLookup['SPL']['units']   = 'Luigini';
		$CurrencyLookup['SRG']['units']   = 'Guilders';
		$CurrencyLookup['STD']['units']   = 'Dobras';
		$CurrencyLookup['SVC']['units']   = 'Colones';
		$CurrencyLookup['SYP']['units']   = 'Pounds';
		$CurrencyLookup['SZL']['units']   = 'Emalangeni';
		$CurrencyLookup['THB']['units']   = 'Baht';
		$CurrencyLookup['TJR']['units']   = 'Rubles';
		$CurrencyLookup['TMM']['units']   = 'Manats';
		$CurrencyLookup['TND']['units']   = 'Dinars';
		$CurrencyLookup['TOP']['units']   = 'Pa\'anga';
		$CurrencyLookup['TRL']['units']   = 'Liras';
		$CurrencyLookup['TTD']['units']   = 'Dollars';
		$CurrencyLookup['TVD']['units']   = 'Tuvalu Dollars';
		$CurrencyLookup['TWD']['units']   = 'New Dollars';
		$CurrencyLookup['TZS']['units']   = 'Shillings';
		$CurrencyLookup['UAH']['units']   = 'Hryvnia';
		$CurrencyLookup['UGX']['units']   = 'Shillings';
		$CurrencyLookup['USD']['units']   = 'Dollars';
		$CurrencyLookup['UYU']['units']   = 'Pesos';
		$CurrencyLookup['UZS']['units']   = 'Sums';
		$CurrencyLookup['VAL']['units']   = 'Lire';
		$CurrencyLookup['VEB']['units']   = 'Bolivares';
		$CurrencyLookup['VND']['units']   = 'Dong';
		$CurrencyLookup['VUV']['units']   = 'Vatu';
		$CurrencyLookup['WST']['units']   = 'Tala';
		$CurrencyLookup['XAF']['units']   = 'Francs';
		$CurrencyLookup['XAG']['units']   = 'Ounces';
		$CurrencyLookup['XAU']['units']   = 'Ounces';
		$CurrencyLookup['XCD']['units']   = 'Dollars';
		$CurrencyLookup['XDR']['units']   = 'Special Drawing Rights';
		$CurrencyLookup['XPD']['units']   = 'Ounces';
		$CurrencyLookup['XPF']['units']   = 'Francs';
		$CurrencyLookup['XPT']['units']   = 'Ounces';
		$CurrencyLookup['YER']['units']   = 'Rials';
		$CurrencyLookup['YUM']['units']   = 'New Dinars';
		$CurrencyLookup['ZAR']['units']   = 'Rand';
		$CurrencyLookup['ZMK']['units']   = 'Kwacha';
		$CurrencyLookup['ZWD']['units']   = 'Zimbabwe Dollars';
	}

	return (isset($CurrencyLookup["$currencyid"]["$item"]) ? $CurrencyLookup["$currencyid"]["$item"] : '');
}

function LanguageLookup($languagecode, $casesensitive=false) {
	// http://www.id3.org/id3v2.4.0-structure.txt
	// [4.   ID3v2 frame overview]
	// The three byte language field, present in several frames, is used to
	// describe the language of the frame's content, according to ISO-639-2
	// [ISO-639-2]. The language should be represented in lower case. If the
	// language is not known the string "XXX" should be used.
	switch ($languagecode) {
		case 'XXX':
		case 'xxx':
			return 'unknown';
			break;
	}

	if (!$casesensitive) {
		$languagecode = strtolower($languagecode);
	}

	// ISO 639-2 - http://www.id3.org/iso639-2.html
	static $LanguageLookup = array();
	if (empty($LanguageLookup)) {
		$LanguageLookup['aar'] = 'Afar';
		$LanguageLookup['abk'] = 'Abkhazian';
		$LanguageLookup['ace'] = 'Achinese';
		$LanguageLookup['ach'] = 'Acoli';
		$LanguageLookup['ada'] = 'Adangme';
		$LanguageLookup['afa'] = 'Afro-Asiatic (Other)';
		$LanguageLookup['afh'] = 'Afrihili';
		$LanguageLookup['afr'] = 'Afrikaans';
		$LanguageLookup['aka'] = 'Akan';
		$LanguageLookup['akk'] = 'Akkadian';
		$LanguageLookup['alb'] = 'Albanian';
		$LanguageLookup['ale'] = 'Aleut';
		$LanguageLookup['alg'] = 'Algonquian Languages';
		$LanguageLookup['amh'] = 'Amharic';
		$LanguageLookup['ang'] = 'English, Old (ca. 450-1100)';
		$LanguageLookup['apa'] = 'Apache Languages';
		$LanguageLookup['ara'] = 'Arabic';
		$LanguageLookup['arc'] = 'Aramaic';
		$LanguageLookup['arm'] = 'Armenian';
		$LanguageLookup['arn'] = 'Araucanian';
		$LanguageLookup['arp'] = 'Arapaho';
		$LanguageLookup['art'] = 'Artificial (Other)';
		$LanguageLookup['arw'] = 'Arawak';
		$LanguageLookup['asm'] = 'Assamese';
		$LanguageLookup['ath'] = 'Athapascan Languages';
		$LanguageLookup['ava'] = 'Avaric';
		$LanguageLookup['ave'] = 'Avestan';
		$LanguageLookup['awa'] = 'Awadhi';
		$LanguageLookup['aym'] = 'Aymara';
		$LanguageLookup['aze'] = 'Azerbaijani';
		$LanguageLookup['bad'] = 'Banda';
		$LanguageLookup['bai'] = 'Bamileke Languages';
		$LanguageLookup['bak'] = 'Bashkir';
		$LanguageLookup['bal'] = 'Baluchi';
		$LanguageLookup['bam'] = 'Bambara';
		$LanguageLookup['ban'] = 'Balinese';
		$LanguageLookup['baq'] = 'Basque';
		$LanguageLookup['bas'] = 'Basa';
		$LanguageLookup['bat'] = 'Baltic (Other)';
		$LanguageLookup['bej'] = 'Beja';
		$LanguageLookup['bel'] = 'Byelorussian';
		$LanguageLookup['bem'] = 'Bemba';
		$LanguageLookup['ben'] = 'Bengali';
		$LanguageLookup['ber'] = 'Berber (Other)';
		$LanguageLookup['bho'] = 'Bhojpuri';
		$LanguageLookup['bih'] = 'Bihari';
		$LanguageLookup['bik'] = 'Bikol';
		$LanguageLookup['bin'] = 'Bini';
		$LanguageLookup['bis'] = 'Bislama';
		$LanguageLookup['bla'] = 'Siksika';
		$LanguageLookup['bnt'] = 'Bantu (Other)';
		$LanguageLookup['bod'] = 'Tibetan';
		$LanguageLookup['bra'] = 'Braj';
		$LanguageLookup['bre'] = 'Breton';
		$LanguageLookup['bua'] = 'Buriat';
		$LanguageLookup['bug'] = 'Buginese';
		$LanguageLookup['bul'] = 'Bulgarian';
		$LanguageLookup['bur'] = 'Burmese';
		$LanguageLookup['cad'] = 'Caddo';
		$LanguageLookup['cai'] = 'Central American Indian (Other)';
		$LanguageLookup['car'] = 'Carib';
		$LanguageLookup['cat'] = 'Catalan';
		$LanguageLookup['cau'] = 'Caucasian (Other)';
		$LanguageLookup['ceb'] = 'Cebuano';
		$LanguageLookup['cel'] = 'Celtic (Other)';
		$LanguageLookup['ces'] = 'Czech';
		$LanguageLookup['cha'] = 'Chamorro';
		$LanguageLookup['chb'] = 'Chibcha';
		$LanguageLookup['che'] = 'Chechen';
		$LanguageLookup['chg'] = 'Chagatai';
		$LanguageLookup['chi'] = 'Chinese';
		$LanguageLookup['chm'] = 'Mari';
		$LanguageLookup['chn'] = 'Chinook jargon';
		$LanguageLookup['cho'] = 'Choctaw';
		$LanguageLookup['chr'] = 'Cherokee';
		$LanguageLookup['chu'] = 'Church Slavic';
		$LanguageLookup['chv'] = 'Chuvash';
		$LanguageLookup['chy'] = 'Cheyenne';
		$LanguageLookup['cop'] = 'Coptic';
		$LanguageLookup['cor'] = 'Cornish';
		$LanguageLookup['cos'] = 'Corsican';
		$LanguageLookup['cpe'] = 'Creoles and Pidgins, English-based (Other)';
		$LanguageLookup['cpf'] = 'Creoles and Pidgins, French-based (Other)';
		$LanguageLookup['cpp'] = 'Creoles and Pidgins, Portuguese-based (Other)';
		$LanguageLookup['cre'] = 'Cree';
		$LanguageLookup['crp'] = 'Creoles and Pidgins (Other)';
		$LanguageLookup['cus'] = 'Cushitic (Other)';
		$LanguageLookup['cym'] = 'Welsh';
		$LanguageLookup['cze'] = 'Czech';
		$LanguageLookup['dak'] = 'Dakota';
		$LanguageLookup['dan'] = 'Danish';
		$LanguageLookup['del'] = 'Delaware';
		$LanguageLookup['deu'] = 'German';
		$LanguageLookup['din'] = 'Dinka';
		$LanguageLookup['div'] = 'Divehi';
		$LanguageLookup['doi'] = 'Dogri';
		$LanguageLookup['dra'] = 'Dravidian (Other)';
		$LanguageLookup['dua'] = 'Duala';
		$LanguageLookup['dum'] = 'Dutch, Middle (ca. 1050-1350)';
		$LanguageLookup['dut'] = 'Dutch';
		$LanguageLookup['dyu'] = 'Dyula';
		$LanguageLookup['dzo'] = 'Dzongkha';
		$LanguageLookup['efi'] = 'Efik';
		$LanguageLookup['egy'] = 'Egyptian (Ancient)';
		$LanguageLookup['eka'] = 'Ekajuk';
		$LanguageLookup['ell'] = 'Greek, Modern (1453-)';
		$LanguageLookup['elx'] = 'Elamite';
		$LanguageLookup['eng'] = 'English';
		$LanguageLookup['enm'] = 'English, Middle (ca. 1100-1500)';
		$LanguageLookup['epo'] = 'Esperanto';
		$LanguageLookup['esk'] = 'Eskimo (Other)';
		$LanguageLookup['esl'] = 'Spanish';
		$LanguageLookup['est'] = 'Estonian';
		$LanguageLookup['eus'] = 'Basque';
		$LanguageLookup['ewe'] = 'Ewe';
		$LanguageLookup['ewo'] = 'Ewondo';
		$LanguageLookup['fan'] = 'Fang';
		$LanguageLookup['fao'] = 'Faroese';
		$LanguageLookup['fas'] = 'Persian';
		$LanguageLookup['fat'] = 'Fanti';
		$LanguageLookup['fij'] = 'Fijian';
		$LanguageLookup['fin'] = 'Finnish';
		$LanguageLookup['fiu'] = 'Finno-Ugrian (Other)';
		$LanguageLookup['fon'] = 'Fon';
		$LanguageLookup['fra'] = 'French';
		$LanguageLookup['fre'] = 'French';
		$LanguageLookup['frm'] = 'French, Middle (ca. 1400-1600)';
		$LanguageLookup['fro'] = 'French, Old (842- ca. 1400)';
		$LanguageLookup['fry'] = 'Frisian';
		$LanguageLookup['ful'] = 'Fulah';
		$LanguageLookup['gaa'] = 'Ga';
		$LanguageLookup['gae'] = 'Gaelic (Scots)';
		$LanguageLookup['gai'] = 'Irish';
		$LanguageLookup['gay'] = 'Gayo';
		$LanguageLookup['gdh'] = 'Gaelic (Scots)';
		$LanguageLookup['gem'] = 'Germanic (Other)';
		$LanguageLookup['geo'] = 'Georgian';
		$LanguageLookup['ger'] = 'German';
		$LanguageLookup['gez'] = 'Geez';
		$LanguageLookup['gil'] = 'Gilbertese';
		$LanguageLookup['glg'] = 'Gallegan';
		$LanguageLookup['gmh'] = 'German, Middle High (ca. 1050-1500)';
		$LanguageLookup['goh'] = 'German, Old High (ca. 750-1050)';
		$LanguageLookup['gon'] = 'Gondi';
		$LanguageLookup['got'] = 'Gothic';
		$LanguageLookup['grb'] = 'Grebo';
		$LanguageLookup['grc'] = 'Greek, Ancient (to 1453)';
		$LanguageLookup['gre'] = 'Greek, Modern (1453-)';
		$LanguageLookup['grn'] = 'Guarani';
		$LanguageLookup['guj'] = 'Gujarati';
		$LanguageLookup['hai'] = 'Haida';
		$LanguageLookup['hau'] = 'Hausa';
		$LanguageLookup['haw'] = 'Hawaiian';
		$LanguageLookup['heb'] = 'Hebrew';
		$LanguageLookup['her'] = 'Herero';
		$LanguageLookup['hil'] = 'Hiligaynon';
		$LanguageLookup['him'] = 'Himachali';
		$LanguageLookup['hin'] = 'Hindi';
		$LanguageLookup['hmo'] = 'Hiri Motu';
		$LanguageLookup['hun'] = 'Hungarian';
		$LanguageLookup['hup'] = 'Hupa';
		$LanguageLookup['hye'] = 'Armenian';
		$LanguageLookup['iba'] = 'Iban';
		$LanguageLookup['ibo'] = 'Igbo';
		$LanguageLookup['ice'] = 'Icelandic';
		$LanguageLookup['ijo'] = 'Ijo';
		$LanguageLookup['iku'] = 'Inuktitut';
		$LanguageLookup['ilo'] = 'Iloko';
		$LanguageLookup['ina'] = 'Interlingua (International Auxiliary language Association)';
		$LanguageLookup['inc'] = 'Indic (Other)';
		$LanguageLookup['ind'] = 'Indonesian';
		$LanguageLookup['ine'] = 'Indo-European (Other)';
		$LanguageLookup['ine'] = 'Interlingue';
		$LanguageLookup['ipk'] = 'Inupiak';
		$LanguageLookup['ira'] = 'Iranian (Other)';
		$LanguageLookup['iri'] = 'Irish';
		$LanguageLookup['iro'] = 'Iroquoian uages';
		$LanguageLookup['isl'] = 'Icelandic';
		$LanguageLookup['ita'] = 'Italian';
		$LanguageLookup['jav'] = 'Javanese';
		$LanguageLookup['jaw'] = 'Javanese';
		$LanguageLookup['jpn'] = 'Japanese';
		$LanguageLookup['jpr'] = 'Judeo-Persian';
		$LanguageLookup['jrb'] = 'Judeo-Arabic';
		$LanguageLookup['kaa'] = 'Kara-Kalpak';
		$LanguageLookup['kab'] = 'Kabyle';
		$LanguageLookup['kac'] = 'Kachin';
		$LanguageLookup['kal'] = 'Greenlandic';
		$LanguageLookup['kam'] = 'Kamba';
		$LanguageLookup['kan'] = 'Kannada';
		$LanguageLookup['kar'] = 'Karen';
		$LanguageLookup['kas'] = 'Kashmiri';
		$LanguageLookup['kat'] = 'Georgian';
		$LanguageLookup['kau'] = 'Kanuri';
		$LanguageLookup['kaw'] = 'Kawi';
		$LanguageLookup['kaz'] = 'Kazakh';
		$LanguageLookup['kha'] = 'Khasi';
		$LanguageLookup['khi'] = 'Khoisan (Other)';
		$LanguageLookup['khm'] = 'Khmer';
		$LanguageLookup['kho'] = 'Khotanese';
		$LanguageLookup['kik'] = 'Kikuyu';
		$LanguageLookup['kin'] = 'Kinyarwanda';
		$LanguageLookup['kir'] = 'Kirghiz';
		$LanguageLookup['kok'] = 'Konkani';
		$LanguageLookup['kom'] = 'Komi';
		$LanguageLookup['kon'] = 'Kongo';
		$LanguageLookup['kor'] = 'Korean';
		$LanguageLookup['kpe'] = 'Kpelle';
		$LanguageLookup['kro'] = 'Kru';
		$LanguageLookup['kru'] = 'Kurukh';
		$LanguageLookup['kua'] = 'Kuanyama';
		$LanguageLookup['kum'] = 'Kumyk';
		$LanguageLookup['kur'] = 'Kurdish';
		$LanguageLookup['kus'] = 'Kusaie';
		$LanguageLookup['kut'] = 'Kutenai';
		$LanguageLookup['lad'] = 'Ladino';
		$LanguageLookup['lah'] = 'Lahnda';
		$LanguageLookup['lam'] = 'Lamba';
		$LanguageLookup['lao'] = 'Lao';
		$LanguageLookup['lat'] = 'Latin';
		$LanguageLookup['lav'] = 'Latvian';
		$LanguageLookup['lez'] = 'Lezghian';
		$LanguageLookup['lin'] = 'Lingala';
		$LanguageLookup['lit'] = 'Lithuanian';
		$LanguageLookup['lol'] = 'Mongo';
		$LanguageLookup['loz'] = 'Lozi';
		$LanguageLookup['ltz'] = 'Letzeburgesch';
		$LanguageLookup['lub'] = 'Luba-Katanga';
		$LanguageLookup['lug'] = 'Ganda';
		$LanguageLookup['lui'] = 'Luiseno';
		$LanguageLookup['lun'] = 'Lunda';
		$LanguageLookup['luo'] = 'Luo (Kenya and Tanzania)';
		$LanguageLookup['mac'] = 'Macedonian';
		$LanguageLookup['mad'] = 'Madurese';
		$LanguageLookup['mag'] = 'Magahi';
		$LanguageLookup['mah'] = 'Marshall';
		$LanguageLookup['mai'] = 'Maithili';
		$LanguageLookup['mak'] = 'Macedonian';
		$LanguageLookup['mak'] = 'Makasar';
		$LanguageLookup['mal'] = 'Malayalam';
		$LanguageLookup['man'] = 'Mandingo';
		$LanguageLookup['mao'] = 'Maori';
		$LanguageLookup['map'] = 'Austronesian (Other)';
		$LanguageLookup['mar'] = 'Marathi';
		$LanguageLookup['mas'] = 'Masai';
		$LanguageLookup['max'] = 'Manx';
		$LanguageLookup['may'] = 'Malay';
		$LanguageLookup['men'] = 'Mende';
		$LanguageLookup['mga'] = 'Irish, Middle (900 - 1200)';
		$LanguageLookup['mic'] = 'Micmac';
		$LanguageLookup['min'] = 'Minangkabau';
		$LanguageLookup['mis'] = 'Miscellaneous (Other)';
		$LanguageLookup['mkh'] = 'Mon-Kmer (Other)';
		$LanguageLookup['mlg'] = 'Malagasy';
		$LanguageLookup['mlt'] = 'Maltese';
		$LanguageLookup['mni'] = 'Manipuri';
		$LanguageLookup['mno'] = 'Manobo Languages';
		$LanguageLookup['moh'] = 'Mohawk';
		$LanguageLookup['mol'] = 'Moldavian';
		$LanguageLookup['mon'] = 'Mongolian';
		$LanguageLookup['mos'] = 'Mossi';
		$LanguageLookup['mri'] = 'Maori';
		$LanguageLookup['msa'] = 'Malay';
		$LanguageLookup['mul'] = 'Multiple Languages';
		$LanguageLookup['mun'] = 'Munda Languages';
		$LanguageLookup['mus'] = 'Creek';
		$LanguageLookup['mwr'] = 'Marwari';
		$LanguageLookup['mya'] = 'Burmese';
		$LanguageLookup['myn'] = 'Mayan Languages';
		$LanguageLookup['nah'] = 'Aztec';
		$LanguageLookup['nai'] = 'North American Indian (Other)';
		$LanguageLookup['nau'] = 'Nauru';
		$LanguageLookup['nav'] = 'Navajo';
		$LanguageLookup['nbl'] = 'Ndebele, South';
		$LanguageLookup['nde'] = 'Ndebele, North';
		$LanguageLookup['ndo'] = 'Ndongo';
		$LanguageLookup['nep'] = 'Nepali';
		$LanguageLookup['new'] = 'Newari';
		$LanguageLookup['nic'] = 'Niger-Kordofanian (Other)';
		$LanguageLookup['niu'] = 'Niuean';
		$LanguageLookup['nla'] = 'Dutch';
		$LanguageLookup['nno'] = 'Norwegian (Nynorsk)';
		$LanguageLookup['non'] = 'Norse, Old';
		$LanguageLookup['nor'] = 'Norwegian';
		$LanguageLookup['nso'] = 'Sotho, Northern';
		$LanguageLookup['nub'] = 'Nubian Languages';
		$LanguageLookup['nya'] = 'Nyanja';
		$LanguageLookup['nym'] = 'Nyamwezi';
		$LanguageLookup['nyn'] = 'Nyankole';
		$LanguageLookup['nyo'] = 'Nyoro';
		$LanguageLookup['nzi'] = 'Nzima';
		$LanguageLookup['oci'] = 'Langue d\'Oc (post 1500)';
		$LanguageLookup['oji'] = 'Ojibwa';
		$LanguageLookup['ori'] = 'Oriya';
		$LanguageLookup['orm'] = 'Oromo';
		$LanguageLookup['osa'] = 'Osage';
		$LanguageLookup['oss'] = 'Ossetic';
		$LanguageLookup['ota'] = 'Turkish, Ottoman (1500 - 1928)';
		$LanguageLookup['oto'] = 'Otomian Languages';
		$LanguageLookup['paa'] = 'Papuan-Australian (Other)';
		$LanguageLookup['pag'] = 'Pangasinan';
		$LanguageLookup['pal'] = 'Pahlavi';
		$LanguageLookup['pam'] = 'Pampanga';
		$LanguageLookup['pan'] = 'Panjabi';
		$LanguageLookup['pap'] = 'Papiamento';
		$LanguageLookup['pau'] = 'Palauan';
		$LanguageLookup['peo'] = 'Persian, Old (ca 600 - 400 B.C.)';
		$LanguageLookup['per'] = 'Persian';
		$LanguageLookup['phn'] = 'Phoenician';
		$LanguageLookup['pli'] = 'Pali';
		$LanguageLookup['pol'] = 'Polish';
		$LanguageLookup['pon'] = 'Ponape';
		$LanguageLookup['por'] = 'Portuguese';
		$LanguageLookup['pra'] = 'Prakrit uages';
		$LanguageLookup['pro'] = 'Provencal, Old (to 1500)';
		$LanguageLookup['pus'] = 'Pushto';
		$LanguageLookup['que'] = 'Quechua';
		$LanguageLookup['raj'] = 'Rajasthani';
		$LanguageLookup['rar'] = 'Rarotongan';
		$LanguageLookup['roa'] = 'Romance (Other)';
		$LanguageLookup['roh'] = 'Rhaeto-Romance';
		$LanguageLookup['rom'] = 'Romany';
		$LanguageLookup['ron'] = 'Romanian';
		$LanguageLookup['rum'] = 'Romanian';
		$LanguageLookup['run'] = 'Rundi';
		$LanguageLookup['rus'] = 'Russian';
		$LanguageLookup['sad'] = 'Sandawe';
		$LanguageLookup['sag'] = 'Sango';
		$LanguageLookup['sah'] = 'Yakut';
		$LanguageLookup['sai'] = 'South American Indian (Other)';
		$LanguageLookup['sal'] = 'Salishan Languages';
		$LanguageLookup['sam'] = 'Samaritan Aramaic';
		$LanguageLookup['san'] = 'Sanskrit';
		$LanguageLookup['sco'] = 'Scots';
		$LanguageLookup['scr'] = 'Serbo-Croatian';
		$LanguageLookup['sel'] = 'Selkup';
		$LanguageLookup['sem'] = 'Semitic (Other)';
		$LanguageLookup['sga'] = 'Irish, Old (to 900)';
		$LanguageLookup['shn'] = 'Shan';
		$LanguageLookup['sid'] = 'Sidamo';
		$LanguageLookup['sin'] = 'Singhalese';
		$LanguageLookup['sio'] = 'Siouan Languages';
		$LanguageLookup['sit'] = 'Sino-Tibetan (Other)';
		$LanguageLookup['sla'] = 'Slavic (Other)';
		$LanguageLookup['slk'] = 'Slovak';
		$LanguageLookup['slo'] = 'Slovak';
		$LanguageLookup['slv'] = 'Slovenian';
		$LanguageLookup['smi'] = 'Sami Languages';
		$LanguageLookup['smo'] = 'Samoan';
		$LanguageLookup['sna'] = 'Shona';
		$LanguageLookup['snd'] = 'Sindhi';
		$LanguageLookup['sog'] = 'Sogdian';
		$LanguageLookup['som'] = 'Somali';
		$LanguageLookup['son'] = 'Songhai';
		$LanguageLookup['sot'] = 'Sotho, Southern';
		$LanguageLookup['spa'] = 'Spanish';
		$LanguageLookup['sqi'] = 'Albanian';
		$LanguageLookup['srd'] = 'Sardinian';
		$LanguageLookup['srr'] = 'Serer';
		$LanguageLookup['ssa'] = 'Nilo-Saharan (Other)';
		$LanguageLookup['ssw'] = 'Siswant';
		$LanguageLookup['ssw'] = 'Swazi';
		$LanguageLookup['suk'] = 'Sukuma';
		$LanguageLookup['sun'] = 'Sudanese';
		$LanguageLookup['sus'] = 'Susu';
		$LanguageLookup['sux'] = 'Sumerian';
		$LanguageLookup['sve'] = 'Swedish';
		$LanguageLookup['swa'] = 'Swahili';
		$LanguageLookup['swe'] = 'Swedish';
		$LanguageLookup['syr'] = 'Syriac';
		$LanguageLookup['tah'] = 'Tahitian';
		$LanguageLookup['tam'] = 'Tamil';
		$LanguageLookup['tat'] = 'Tatar';
		$LanguageLookup['tel'] = 'Telugu';
		$LanguageLookup['tem'] = 'Timne';
		$LanguageLookup['ter'] = 'Tereno';
		$LanguageLookup['tgk'] = 'Tajik';
		$LanguageLookup['tgl'] = 'Tagalog';
		$LanguageLookup['tha'] = 'Thai';
		$LanguageLookup['tib'] = 'Tibetan';
		$LanguageLookup['tig'] = 'Tigre';
		$LanguageLookup['tir'] = 'Tigrinya';
		$LanguageLookup['tiv'] = 'Tivi';
		$LanguageLookup['tli'] = 'Tlingit';
		$LanguageLookup['tmh'] = 'Tamashek';
		$LanguageLookup['tog'] = 'Tonga (Nyasa)';
		$LanguageLookup['ton'] = 'Tonga (Tonga Islands)';
		$LanguageLookup['tru'] = 'Truk';
		$LanguageLookup['tsi'] = 'Tsimshian';
		$LanguageLookup['tsn'] = 'Tswana';
		$LanguageLookup['tso'] = 'Tsonga';
		$LanguageLookup['tuk'] = 'Turkmen';
		$LanguageLookup['tum'] = 'Tumbuka';
		$LanguageLookup['tur'] = 'Turkish';
		$LanguageLookup['tut'] = 'Altaic (Other)';
		$LanguageLookup['twi'] = 'Twi';
		$LanguageLookup['tyv'] = 'Tuvinian';
		$LanguageLookup['uga'] = 'Ugaritic';
		$LanguageLookup['uig'] = 'Uighur';
		$LanguageLookup['ukr'] = 'Ukrainian';
		$LanguageLookup['umb'] = 'Umbundu';
		$LanguageLookup['und'] = 'Undetermined';
		$LanguageLookup['urd'] = 'Urdu';
		$LanguageLookup['uzb'] = 'Uzbek';
		$LanguageLookup['vai'] = 'Vai';
		$LanguageLookup['ven'] = 'Venda';
		$LanguageLookup['vie'] = 'Vietnamese';
		$LanguageLookup['vol'] = 'Volapük';
		$LanguageLookup['vot'] = 'Votic';
		$LanguageLookup['wak'] = 'Wakashan Languages';
		$LanguageLookup['wal'] = 'Walamo';
		$LanguageLookup['war'] = 'Waray';
		$LanguageLookup['was'] = 'Washo';
		$LanguageLookup['wel'] = 'Welsh';
		$LanguageLookup['wen'] = 'Sorbian Languages';
		$LanguageLookup['wol'] = 'Wolof';
		$LanguageLookup['xho'] = 'Xhosa';
		$LanguageLookup['yao'] = 'Yao';
		$LanguageLookup['yap'] = 'Yap';
		$LanguageLookup['yid'] = 'Yiddish';
		$LanguageLookup['yor'] = 'Yoruba';
		$LanguageLookup['zap'] = 'Zapotec';
		$LanguageLookup['zen'] = 'Zenaga';
		$LanguageLookup['zha'] = 'Zhuang';
		$LanguageLookup['zho'] = 'Chinese';
		$LanguageLookup['zul'] = 'Zulu';
		$LanguageLookup['zun'] = 'Zuni';
	}

	return (isset($LanguageLookup["$languagecode"]) ? $LanguageLookup["$languagecode"] : '');
}

function ETCOEventLookup($index) {
	static $EventLookup = array();
	if (empty($EventLookup)) {
		$EventLookup[0x00] = 'padding (has no meaning)';
		$EventLookup[0x01] = 'end of initial silence';
		$EventLookup[0x02] = 'intro start';
		$EventLookup[0x03] = 'main part start';
		$EventLookup[0x04] = 'outro start';
		$EventLookup[0x05] = 'outro end';
		$EventLookup[0x06] = 'verse start';
		$EventLookup[0x07] = 'refrain start';
		$EventLookup[0x08] = 'interlude start';
		$EventLookup[0x09] = 'theme start';
		$EventLookup[0x0A] = 'variation start';
		$EventLookup[0x0B] = 'key change';
		$EventLookup[0x0C] = 'time change';
		$EventLookup[0x0D] = 'momentary unwanted noise (Snap, Crackle & Pop)';
		$EventLookup[0x0E] = 'sustained noise';
		$EventLookup[0x0F] = 'sustained noise end';
		$EventLookup[0x10] = 'intro end';
		$EventLookup[0x11] = 'main part end';
		$EventLookup[0x12] = 'verse end';
		$EventLookup[0x13] = 'refrain end';
		$EventLookup[0x14] = 'theme end';
		$EventLookup[0x15] = 'profanity';
		$EventLookup[0x16] = 'profanity end';
		for ($i = 0x17; $i <= 0xDF; $i++) {
			$EventLookup[$i] = 'reserved for future use';
		}
		for ($i = 0xE0; $i <= 0xEF; $i++) {
			$EventLookup[$i] = 'not predefined synch 0-F';
		}
		for ($i = 0xF0; $i <= 0xFC; $i++) {
			$EventLookup[$i] = 'reserved for future use';
		}
		$EventLookup[0xFD] = 'audio end (start of silence)';
		$EventLookup[0xFE] = 'audio file ends';
		$EventLookup[0xFF] = 'one more byte of events follows';
	}

	return (isset($EventLookup[$index]) ? $EventLookup[$index] : '');
}

function SYTLContentTypeLookup($index) {
	static $SYTLContentTypeLookup = array();
	if (empty($SYTLContentTypeLookup)) {
		$SYTLContentTypeLookup[0x00] = 'other';
		$SYTLContentTypeLookup[0x01] = 'lyrics';
		$SYTLContentTypeLookup[0x02] = 'text transcription';
		$SYTLContentTypeLookup[0x03] = 'movement/part name'; // (e.g. 'Adagio')
		$SYTLContentTypeLookup[0x04] = 'events';             // (e.g. 'Don Quijote enters the stage')
		$SYTLContentTypeLookup[0x05] = 'chord';              // (e.g. 'Bb F Fsus')
		$SYTLContentTypeLookup[0x06] = 'trivia/\'pop up\' information';
		$SYTLContentTypeLookup[0x07] = 'URLs to webpages';
		$SYTLContentTypeLookup[0x08] = 'URLs to images';
	}

	return (isset($SYTLContentTypeLookup[$index]) ? $SYTLContentTypeLookup[$index] : '');
}

function APICPictureTypeLookup($index) {
	static $APICPictureTypeLookup = array();
	if (empty($APICPictureTypeLookup)) {
		$APICPictureTypeLookup[0x00] = 'Other';
		$APICPictureTypeLookup[0x01] = '32x32 pixels \'file icon\' (PNG only)';
		$APICPictureTypeLookup[0x02] = 'Other file icon';
		$APICPictureTypeLookup[0x03] = 'Cover (front)';
		$APICPictureTypeLookup[0x04] = 'Cover (back)';
		$APICPictureTypeLookup[0x05] = 'Leaflet page';
		$APICPictureTypeLookup[0x06] = 'Media (e.g. label side of CD)';
		$APICPictureTypeLookup[0x07] = 'Lead artist/lead performer/soloist';
		$APICPictureTypeLookup[0x08] = 'Artist/performer';
		$APICPictureTypeLookup[0x09] = 'Conductor';
		$APICPictureTypeLookup[0x0A] = 'Band/Orchestra';
		$APICPictureTypeLookup[0x0B] = 'Composer';
		$APICPictureTypeLookup[0x0C] = 'Lyricist/text writer';
		$APICPictureTypeLookup[0x0D] = 'Recording Location';
		$APICPictureTypeLookup[0x0E] = 'During recording';
		$APICPictureTypeLookup[0x0F] = 'During performance';
		$APICPictureTypeLookup[0x10] = 'Movie/video screen capture';
		$APICPictureTypeLookup[0x11] = 'A bright coloured fish';
		$APICPictureTypeLookup[0x12] = 'Illustration';
		$APICPictureTypeLookup[0x13] = 'Band/artist logotype';
		$APICPictureTypeLookup[0x14] = 'Publisher/Studio logotype';
	}

	return (isset($APICPictureTypeLookup[$index]) ? $APICPictureTypeLookup[$index] : '');
}

function COMRReceivedAsLookup($index) {
	static $COMRReceivedAsLookup = array();
	if (empty($COMRReceivedAsLookup)) {
		$COMRReceivedAsLookup[0x00] = 'Other';
		$COMRReceivedAsLookup[0x01] = 'Standard CD album with other songs';
		$COMRReceivedAsLookup[0x02] = 'Compressed audio on CD';
		$COMRReceivedAsLookup[0x03] = 'File over the Internet';
		$COMRReceivedAsLookup[0x04] = 'Stream over the Internet';
		$COMRReceivedAsLookup[0x05] = 'As note sheets';
		$COMRReceivedAsLookup[0x06] = 'As note sheets in a book with other sheets';
		$COMRReceivedAsLookup[0x07] = 'Music on other media';
		$COMRReceivedAsLookup[0x08] = 'Non-musical merchandise';
	}

	return (isset($COMRReceivedAsLookup[$index]) ? $COMRReceivedAsLookup[$index] : '');
}

function RVA2ChannelTypeLookup($index) {
	static $RVA2ChannelTypeLookup = array();
	if (empty($RVA2ChannelTypeLookup)) {
		$RVA2ChannelTypeLookup[0x00] = 'Other';
		$RVA2ChannelTypeLookup[0x01] = 'Master volume';
		$RVA2ChannelTypeLookup[0x02] = 'Front right';
		$RVA2ChannelTypeLookup[0x03] = 'Front left';
		$RVA2ChannelTypeLookup[0x04] = 'Back right';
		$RVA2ChannelTypeLookup[0x05] = 'Back left';
		$RVA2ChannelTypeLookup[0x06] = 'Front centre';
		$RVA2ChannelTypeLookup[0x07] = 'Back centre';
		$RVA2ChannelTypeLookup[0x08] = 'Subwoofer';
	}

	return (isset($RVA2ChannelTypeLookup[$index]) ? $RVA2ChannelTypeLookup[$index] : '');
}

function FrameNameLongLookup($framename) {
	static $FrameNameLongLookup = array();
	if (empty($FrameNameLongLookup)) {
		$FrameNameLongLookup['AENC'] = 'Audio encryption';
		$FrameNameLongLookup['APIC'] = 'Attached picture';
		$FrameNameLongLookup['ASPI'] = 'Audio seek point index';
		$FrameNameLongLookup['BUF']  = 'Recommended buffer size';
		$FrameNameLongLookup['CNT']  = 'Play counter';
		$FrameNameLongLookup['COM']  = 'Comments';
		$FrameNameLongLookup['COMM'] = 'Comments';
		$FrameNameLongLookup['COMR'] = 'Commercial frame';
		$FrameNameLongLookup['CRA']  = 'Audio encryption';
		$FrameNameLongLookup['CRM']  = 'Encrypted meta frame';
		$FrameNameLongLookup['ENCR'] = 'Encryption method registration';
		$FrameNameLongLookup['EQU']  = 'Equalization';
		$FrameNameLongLookup['EQU2'] = 'Equalisation (2)';
		$FrameNameLongLookup['EQUA'] = 'Equalization';
		$FrameNameLongLookup['ETC']  = 'Event timing codes';
		$FrameNameLongLookup['ETCO'] = 'Event timing codes';
		$FrameNameLongLookup['GEO']  = 'General encapsulated object';
		$FrameNameLongLookup['GEOB'] = 'General encapsulated object';
		$FrameNameLongLookup['GRID'] = 'Group identification registration';
		$FrameNameLongLookup['IPL']  = 'Involved people list';
		$FrameNameLongLookup['IPLS'] = 'Involved people list';
		$FrameNameLongLookup['LINK'] = 'Linked information';
		$FrameNameLongLookup['LNK']  = 'Linked information';
		$FrameNameLongLookup['MCDI'] = 'Music CD identifier';
		$FrameNameLongLookup['MCI']  = 'Music CD Identifier';
		$FrameNameLongLookup['MLL']  = 'MPEG location lookup table';
		$FrameNameLongLookup['MLLT'] = 'MPEG location lookup table';
		$FrameNameLongLookup['OWNE'] = 'Ownership frame';
		$FrameNameLongLookup['PCNT'] = 'Play counter';
		$FrameNameLongLookup['PIC']  = 'Attached picture';
		$FrameNameLongLookup['POP']  = 'Popularimeter';
		$FrameNameLongLookup['POPM'] = 'Popularimeter';
		$FrameNameLongLookup['POSS'] = 'Position synchronisation frame';
		$FrameNameLongLookup['PRIV'] = 'Private frame';
		$FrameNameLongLookup['RBUF'] = 'Recommended buffer size';
		$FrameNameLongLookup['REV']  = 'Reverb';
		$FrameNameLongLookup['RVA']  = 'Relative volume adjustment';
		$FrameNameLongLookup['RVA2'] = 'Relative volume adjustment (2)';
		$FrameNameLongLookup['RVAD'] = 'Relative volume adjustment';
		$FrameNameLongLookup['RVRB'] = 'Reverb';
		$FrameNameLongLookup['SEEK'] = 'Seek frame';
		$FrameNameLongLookup['SIGN'] = 'Signature frame';
		$FrameNameLongLookup['SLT']  = 'Synchronized lyric/text';
		$FrameNameLongLookup['STC']  = 'Synced tempo codes';
		$FrameNameLongLookup['SYLT'] = 'Synchronised lyric/text';
		$FrameNameLongLookup['SYTC'] = 'Synchronised tempo codes';
		$FrameNameLongLookup['TAL']  = 'Album/Movie/Show title';
		$FrameNameLongLookup['TALB'] = 'Album/Movie/Show title';
		$FrameNameLongLookup['TBP']  = 'BPM (Beats Per Minute)';
		$FrameNameLongLookup['TBPM'] = 'BPM (beats per minute)';
		$FrameNameLongLookup['TCM']  = 'Composer';
		$FrameNameLongLookup['TCO']  = 'Content type';
		$FrameNameLongLookup['TCOM'] = 'Composer';
		$FrameNameLongLookup['TCON'] = 'Content type';
		$FrameNameLongLookup['TCOP'] = 'Copyright message';
		$FrameNameLongLookup['TCR']  = 'Copyright message';
		$FrameNameLongLookup['TDA']  = 'Date';
		$FrameNameLongLookup['TDAT'] = 'Date';
		$FrameNameLongLookup['TDEN'] = 'Encoding time';
		$FrameNameLongLookup['TDLY'] = 'Playlist delay';
		$FrameNameLongLookup['TDOR'] = 'Original release time';
		$FrameNameLongLookup['TDRC'] = 'Recording time';
		$FrameNameLongLookup['TDRL'] = 'Release time';
		$FrameNameLongLookup['TDTG'] = 'Tagging time';
		$FrameNameLongLookup['TDY']  = 'Playlist delay';
		$FrameNameLongLookup['TEN']  = 'Encoded by';
		$FrameNameLongLookup['TENC'] = 'Encoded by';
		$FrameNameLongLookup['TEXT'] = 'Lyricist/Text writer';
		$FrameNameLongLookup['TFLT'] = 'File type';
		$FrameNameLongLookup['TFT']  = 'File type';
		$FrameNameLongLookup['TIM']  = 'Time';
		$FrameNameLongLookup['TIME'] = 'Time';
		$FrameNameLongLookup['TIPL'] = 'Involved people list';
		$FrameNameLongLookup['TIT1'] = 'Content group description';
		$FrameNameLongLookup['TIT2'] = 'Title/songname/content description';
		$FrameNameLongLookup['TIT3'] = 'Subtitle/Description refinement';
		$FrameNameLongLookup['TKE']  = 'Initial key';
		$FrameNameLongLookup['TKEY'] = 'Initial key';
		$FrameNameLongLookup['TLA']  = 'Language(s)';
		$FrameNameLongLookup['TLAN'] = 'Language(s)';
		$FrameNameLongLookup['TLE']  = 'Length';
		$FrameNameLongLookup['TLEN'] = 'Length';
		$FrameNameLongLookup['TMCL'] = 'Musician credits list';
		$FrameNameLongLookup['TMED'] = 'Media type';
		$FrameNameLongLookup['TMOO'] = 'Mood';
		$FrameNameLongLookup['TMT']  = 'Media type';
		$FrameNameLongLookup['TOA']  = 'Original artist(s)/performer(s)';
		$FrameNameLongLookup['TOAL'] = 'Original album/movie/show title';
		$FrameNameLongLookup['TOF']  = 'Original filename';
		$FrameNameLongLookup['TOFN'] = 'Original filename';
		$FrameNameLongLookup['TOL']  = 'Original Lyricist(s)/text writer(s)';
		$FrameNameLongLookup['TOLY'] = 'Original lyricist(s)/text writer(s)';
		$FrameNameLongLookup['TOPE'] = 'Original artist(s)/performer(s)';
		$FrameNameLongLookup['TOR']  = 'Original release year';
		$FrameNameLongLookup['TORY'] = 'Original release year';
		$FrameNameLongLookup['TOT']  = 'Original album/Movie/Show title';
		$FrameNameLongLookup['TOWN'] = 'File owner/licensee';
		$FrameNameLongLookup['TP1']  = 'Lead artist(s)/Lead performer(s)/Soloist(s)/Performing group';
		$FrameNameLongLookup['TP2']  = 'Band/Orchestra/Accompaniment';
		$FrameNameLongLookup['TP3']  = 'Conductor/Performer refinement';
		$FrameNameLongLookup['TP4']  = 'Interpreted, remixed, or otherwise modified by';
		$FrameNameLongLookup['TPA']  = 'Part of a set';
		$FrameNameLongLookup['TPB']  = 'Publisher';
		$FrameNameLongLookup['TPE1'] = 'Lead performer(s)/Soloist(s)';
		$FrameNameLongLookup['TPE2'] = 'Band/orchestra/accompaniment';
		$FrameNameLongLookup['TPE3'] = 'Conductor/performer refinement';
		$FrameNameLongLookup['TPE4'] = 'Interpreted, remixed, or otherwise modified by';
		$FrameNameLongLookup['TPOS'] = 'Part of a set';
		$FrameNameLongLookup['TPRO'] = 'Produced notice';
		$FrameNameLongLookup['TPUB'] = 'Publisher';
		$FrameNameLongLookup['TRC']  = 'ISRC (International Standard Recording Code)';
		$FrameNameLongLookup['TRCK'] = 'Track number/Position in set';
		$FrameNameLongLookup['TRD']  = 'Recording dates';
		$FrameNameLongLookup['TRDA'] = 'Recording dates';
		$FrameNameLongLookup['TRK']  = 'Track number/Position in set';
		$FrameNameLongLookup['TRSN'] = 'Internet radio station name';
		$FrameNameLongLookup['TRSO'] = 'Internet radio station owner';
		$FrameNameLongLookup['TSI']  = 'Size';
		$FrameNameLongLookup['TSIZ'] = 'Size';
		$FrameNameLongLookup['TSOA'] = 'Album sort order';
		$FrameNameLongLookup['TSOP'] = 'Performer sort order';
		$FrameNameLongLookup['TSOT'] = 'Title sort order';
		$FrameNameLongLookup['TSRC'] = 'ISRC (international standard recording code)';
		$FrameNameLongLookup['TSS']  = 'Software/hardware and settings used for encoding';
		$FrameNameLongLookup['TSSE'] = 'Software/Hardware and settings used for encoding';
		$FrameNameLongLookup['TSST'] = 'Set subtitle';
		$FrameNameLongLookup['TT1']  = 'Content group description';
		$FrameNameLongLookup['TT2']  = 'Title/Songname/Content description';
		$FrameNameLongLookup['TT3']  = 'Subtitle/Description refinement';
		$FrameNameLongLookup['TXT']  = 'Lyricist/text writer';
		$FrameNameLongLookup['TXX']  = 'User defined text information frame';
		$FrameNameLongLookup['TXXX'] = 'User defined text information frame';
		$FrameNameLongLookup['TYE']  = 'Year';
		$FrameNameLongLookup['TYER'] = 'Year';
		$FrameNameLongLookup['UFI']  = 'Unique file identifier';
		$FrameNameLongLookup['UFID'] = 'Unique file identifier';
		$FrameNameLongLookup['ULT']  = 'Unsychronized lyric/text transcription';
		$FrameNameLongLookup['USER'] = 'Terms of use';
		$FrameNameLongLookup['USLT'] = 'Unsynchronised lyric/text transcription';
		$FrameNameLongLookup['WAF']  = 'Official audio file webpage';
		$FrameNameLongLookup['WAR']  = 'Official artist/performer webpage';
		$FrameNameLongLookup['WAS']  = 'Official audio source webpage';
		$FrameNameLongLookup['WCM']  = 'Commercial information';
		$FrameNameLongLookup['WCOM'] = 'Commercial information';
		$FrameNameLongLookup['WCOP'] = 'Copyright/Legal information';
		$FrameNameLongLookup['WCP']  = 'Copyright/Legal information';
		$FrameNameLongLookup['WOAF'] = 'Official audio file webpage';
		$FrameNameLongLookup['WOAR'] = 'Official artist/performer webpage';
		$FrameNameLongLookup['WOAS'] = 'Official audio source webpage';
		$FrameNameLongLookup['WORS'] = 'Official Internet radio station homepage';
		$FrameNameLongLookup['WPAY'] = 'Payment';
		$FrameNameLongLookup['WPB']  = 'Publishers official webpage';
		$FrameNameLongLookup['WPUB'] = 'Publishers official webpage';
		$FrameNameLongLookup['WXX']  = 'User defined URL link frame';
		$FrameNameLongLookup['WXXX'] = 'User defined URL link frame';

		$FrameNameLongLookup['TFEA'] = 'Featured Artist';        // from Helium2 [www.helium2.com]
		$FrameNameLongLookup['TSTU'] = 'Recording Studio';       // from Helium2 [www.helium2.com]
		$FrameNameLongLookup['rgad'] = 'Replay Gain Adjustment'; // from http://privatewww.essex.ac.uk/~djmrob/replaygain/file_format_id3v2.html
	}

	return (isset($FrameNameLongLookup["$framename"]) ? $FrameNameLongLookup["$framename"] : '');
}

function FrameNameShortLookup($framename) {
	static $FrameNameShortLookup = array();
	if (empty($FrameNameShortLookup)) {
		$FrameNameShortLookup['COM']  = 'comment';
		$FrameNameShortLookup['COMM'] = 'comment';
		$FrameNameShortLookup['TAL']  = 'album';
		$FrameNameShortLookup['TALB'] = 'album';
		$FrameNameShortLookup['TBP']  = 'bpm';
		$FrameNameShortLookup['TBPM'] = 'bpm';
		$FrameNameShortLookup['TCM']  = 'composer';
		$FrameNameShortLookup['TCO']  = 'genre';
		$FrameNameShortLookup['TCOM'] = 'composer';
		$FrameNameShortLookup['TCON'] = 'genre';
		$FrameNameShortLookup['TCOP'] = 'copyright';
		$FrameNameShortLookup['TCR']  = 'copyright';
		$FrameNameShortLookup['TEN']  = 'encoded_by';
		$FrameNameShortLookup['TENC'] = 'encoded_by';
		$FrameNameShortLookup['TEXT'] = 'lyricist';
		$FrameNameShortLookup['TIT1'] = 'description';
		$FrameNameShortLookup['TIT2'] = 'title';
		$FrameNameShortLookup['TIT3'] = 'subtitle';
		$FrameNameShortLookup['TLA']  = 'language';
		$FrameNameShortLookup['TLAN'] = 'language';
		$FrameNameShortLookup['TLE']  = 'length';
		$FrameNameShortLookup['TLEN'] = 'length';
		$FrameNameShortLookup['TMOO'] = 'mood';
		$FrameNameShortLookup['TOA']  = 'original_artist';
		$FrameNameShortLookup['TOAL'] = 'original_album';
		$FrameNameShortLookup['TOF']  = 'original_filename';
		$FrameNameShortLookup['TOFN'] = 'original_filename';
		$FrameNameShortLookup['TOL']  = 'original_lyricist';
		$FrameNameShortLookup['TOLY'] = 'original_lyricist';
		$FrameNameShortLookup['TOPE'] = 'original_artist';
		$FrameNameShortLookup['TOT']  = 'original_album';
		$FrameNameShortLookup['TP1']  = 'artist';
		$FrameNameShortLookup['TP2']  = 'band';
		$FrameNameShortLookup['TP3']  = 'conductor';
		$FrameNameShortLookup['TP4']  = 'remixer';
		$FrameNameShortLookup['TPB']  = 'publisher';
		$FrameNameShortLookup['TPE1'] = 'artist';
		$FrameNameShortLookup['TPE2'] = 'band';
		$FrameNameShortLookup['TPE3'] = 'conductor';
		$FrameNameShortLookup['TPE4'] = 'remixer';
		$FrameNameShortLookup['TPUB'] = 'publisher';
		$FrameNameShortLookup['TRC']  = 'isrc';
		$FrameNameShortLookup['TRCK'] = 'track';
		$FrameNameShortLookup['TRK']  = 'track';
		$FrameNameShortLookup['TSI']  = 'size';
		$FrameNameShortLookup['TSIZ'] = 'size';
		$FrameNameShortLookup['TSRC'] = 'isrc';
		$FrameNameShortLookup['TSS']  = 'encoder_settings';
		$FrameNameShortLookup['TSSE'] = 'encoder_settings';
		$FrameNameShortLookup['TSST'] = 'subtitle';
		$FrameNameShortLookup['TT1']  = 'description';
		$FrameNameShortLookup['TT2']  = 'title';
		$FrameNameShortLookup['TT3']  = 'subtitle';
		$FrameNameShortLookup['TXT']  = 'lyricist';
		$FrameNameShortLookup['TXX']  = 'text';
		$FrameNameShortLookup['TXXX'] = 'text';
		$FrameNameShortLookup['TYE']  = 'year';
		$FrameNameShortLookup['TYER'] = 'year';
		$FrameNameShortLookup['UFI']  = 'unique_file_identifier';
		$FrameNameShortLookup['UFID'] = 'unique_file_identifier';
		$FrameNameShortLookup['ULT']  = 'unsychronized_lyric';
		$FrameNameShortLookup['USER'] = 'terms_of_use';
		$FrameNameShortLookup['USLT'] = 'unsynchronised lyric';
		$FrameNameShortLookup['WAF']  = 'url_file';
		$FrameNameShortLookup['WAR']  = 'url_artist';
		$FrameNameShortLookup['WAS']  = 'url_source';
		$FrameNameShortLookup['WCOP'] = 'copyright';
		$FrameNameShortLookup['WCP']  = 'copyright';
		$FrameNameShortLookup['WOAF'] = 'url_file';
		$FrameNameShortLookup['WOAR'] = 'url_artist';
		$FrameNameShortLookup['WOAS'] = 'url_souce';
		$FrameNameShortLookup['WORS'] = 'url_station';
		$FrameNameShortLookup['WPB']  = 'url_publisher';
		$FrameNameShortLookup['WPUB'] = 'url_publisher';
		$FrameNameShortLookup['WXX']  = 'url_user';
		$FrameNameShortLookup['WXXX'] = 'url_user';

		$FrameNameShortLookup['TFEA'] = 'featured_artist';
		$FrameNameShortLookup['TSTU'] = 'studio';
	}

	return (isset($FrameNameShortLookup["$framename"]) ? $FrameNameShortLookup["$framename"] : '');
}

function TextEncodingLookup($type, $encoding) {
	// http://www.id3.org/id3v2.4.0-structure.txt
	// Frames that allow different types of text encoding contains a text encoding description byte. Possible encodings:
	// $00  ISO-8859-1. Terminated with $00.
	// $01  UTF-16 encoded Unicode with BOM. All strings in the same frame SHALL have the same byteorder. Terminated with $00 00.
	// $02  UTF-16BE encoded Unicode without BOM. Terminated with $00 00.
	// $03  UTF-8 encoded Unicode. Terminated with $00.

	$TextEncodingLookup['encoding']   = array('ISO-8859-1', 'UTF-16', 'UTF-16BE', 'UTF-8');
	$TextEncodingLookup['terminator'] = array(chr(0), chr(0).chr(0), chr(0).chr(0), chr(0));

	return (isset($TextEncodingLookup["$type"][$encoding]) ? $TextEncodingLookup["$type"][$encoding] : '');
}

function TextEncodingVerified($text_encoding, &$ThisFileInfo, $frame_name) {
	switch ($text_encoding) {
		case 0:
		case 1:
		case 2:
		case 3:
			return $text_encoding;
			break;

		default:
			$ThisFileInfo['warning'] .= "\n".'Invalid text encoding byte ('.$text_encoding.') in frame "'.$frame_name.'", defaulting to ASCII encoding';
			return 0;
			break;
	}
}

function IsValidID3v2FrameName($framename, $id3v2majorversion) {
	switch ($id3v2majorversion) {
		case 2:
			return ereg('[A-Z][A-Z0-9]{2}', $framename);
			break;

		case 3:
		case 4:
			return ereg('[A-Z][A-Z0-9]{3}', $framename);
			break;
	}
	return false;
}

function IsANumber($numberstring, $allowdecimal=false, $allownegative=false) {
	for ($i = 0; $i < strlen($numberstring); $i++) {
		if ((chr($numberstring{$i}) < chr('0')) || (chr($numberstring{$i}) > chr('9'))) {
			if (($numberstring{$i} == '.') && $allowdecimal) {
				// allowed
			} elseif (($numberstring{$i} == '-') && $allownegative && ($i == 0)) {
				// allowed
			} else {
				return false;
			}
		}
	}
	return true;
}

function IsValidDateStampString($datestamp) {
	if (strlen($datestamp) != 8) {
		return false;
	}
	if (!IsANumber($datestamp, false)) {
		return false;
	}
	$year  = substr($datestamp, 0, 4);
	$month = substr($datestamp, 4, 2);
	$day   = substr($datestamp, 6, 2);
	if (($year == 0) || ($month == 0) || ($day == 0)) {
		return false;
	}
	if ($month > 12) {
		return false;
	}
	if ($day > 31) {
		return false;
	}
	if (($day > 30) && (($month == 4) || ($month == 6) || ($month == 9) || ($month == 11))) {
		return false;
	}
	if (($day > 29) && ($month == 2)) {
		return false;
	}
	return true;
}

function ID3v2HeaderLength($majorversion) {
	if ($majorversion == 2) {
		return 6;
	} else {
		return 10;
	}
}

function GenerateID3v2TagFlags($majorversion=4, $Unsynchronisation=false, $Compression=false, $ExtendedHeader=false, $Experimental=false, $Footer=false) {
    if ($majorversion == 4) {
		// %abcd0000
		$flag  = Bool2IntString($Unsynchronisation); // a - Unsynchronisation
		$flag .= Bool2IntString($ExtendedHeader);    // b - Extended header
		$flag .= Bool2IntString($Experimental);      // c - Experimental indicator
		$flag .= Bool2IntString($Footer);            // d - Footer present
		$flag .= '0000';
    } elseif ($majorversion == 3) {
		// %abc00000
		$flag  = Bool2IntString($Unsynchronisation); // a - Unsynchronisation
		$flag .= Bool2IntString($ExtendedHeader);    // b - Extended header
		$flag .= Bool2IntString($Experimental);      // c - Experimental indicator
		$flag .= '00000';
    } elseif ($majorversion == 2) {
		// %ab000000
		$flag  = Bool2IntString($Unsynchronisation); // a - Unsynchronisation
		$flag .= Bool2IntString($Compression);       // b - Compression
		$flag .= '000000';
    } else {
		return false;
    }
    return chr(bindec($flag));
}


function GenerateID3v2FrameFlags($majorversion=4, $TagAlter=false, $FileAlter=false, $ReadOnly=false, $Compression=false, $Encryption=false, $GroupingIdentity=false, $Unsynchronisation=false, $DataLengthIndicator=false) {
    if ($majorversion == 4) {
		// %0abc0000 %0h00kmnp

		$flag1  = '0';
		$flag1 .= Bool2IntString($TagAlter);  // a - Tag alter preservation (true == discard)
		$flag1 .= Bool2IntString($FileAlter); // b - File alter preservation (true == discard)
		$flag1 .= Bool2IntString($ReadOnly);  // c - Read only (true == read only)
		$flag1 .= '0000';

		$flag2  = '0';
		$flag2 .= Bool2IntString($GroupingIdentity);    // h - Grouping identity (true == contains group information)
		$flag2 .= '00';
		$flag2 .= Bool2IntString($Compression);         // k - Compression (true == compressed)
		$flag2 .= Bool2IntString($Encryption);          // m - Encryption (true == encrypted)
		$flag2 .= Bool2IntString($Unsynchronisation);   // n - Unsynchronisation (true == unsynchronised)
		$flag2 .= Bool2IntString($DataLengthIndicator); // p - Data length indicator (true == data length indicator added)

    } elseif ($majorversion == 3) {
		// %abc00000 %ijk00000

		$flag1  = Bool2IntString($TagAlter);  // a - Tag alter preservation (true == discard)
		$flag1 .= Bool2IntString($FileAlter); // b - File alter preservation (true == discard)
		$flag1 .= Bool2IntString($ReadOnly);  // c - Read only (true == read only)
		$flag1 .= '00000';

		$flag2  = Bool2IntString($Compression);      // i - Compression (true == compressed)
		$flag2 .= Bool2IntString($Encryption);       // j - Encryption (true == encrypted)
		$flag2 .= Bool2IntString($GroupingIdentity); // k - Grouping identity (true == contains group information)
		$flag2 .= '00000';

    } else {

		return false;

    }
    return chr(bindec($flag1)).chr(bindec($flag2));
}

function GenerateID3v2FrameData($frame_name, $frame_data, $majorversion=4, $showerrors=false) {
    if (!IsValidID3v2FrameName($frame_name, $majorversion)) {
		return false;
    }
    $error     = '';
    $framedata = '';

    if (($majorversion < 3) || ($majorversion > 4)) {

		$error .= 'Only ID3v2.3 and ID3v2.4 are supported in GenerateID3v2FrameData()<BR>';

    } else { // $majorversion 3 or 4

		switch ($frame_name) {
			case 'UFID':
				// 4.1   UFID Unique file identifier
				// Owner identifier        <text string> $00
				// Identifier              <up to 64 bytes binary data>
				if (strlen($frame_data['data']) > 64) {
					$error .= 'Identifier not allowed to be longer than 64 bytes in '.$frame_name.' (supplied data was '.strlen($frame_data['data']).' bytes long)<BR>';
				} else {
					$framedata .= str_replace(chr(0), '', $frame_data['ownerid']).chr(0);
					$framedata .= substr($frame_data['data'], 0, 64); // max 64 bytes - truncate anything longer
				}
				break;

			case 'TXXX':
				// 4.2.2 TXXX User defined text information frame
				// Text encoding     $xx
				// Description       <text string according to encoding> $00 (00)
				// Value             <text string according to encoding>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'WXXX':
				// 4.3.2 WXXX User defined URL link frame
				// Text encoding     $xx
				// Description       <text string according to encoding> $00 (00)
				// URL               <text string>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (!isset($frame_data['url']) || !IsValidURL($frame_data['url'], false, false)) {
					$error .= 'Invalid URL in '.$frame_name.' ('.$frame_data['url'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['url'];
				}
				break;

			case 'IPLS':
				// 4.4  IPLS Involved people list (ID3v2.3 only)
				// Text encoding     $xx
				// People list strings    <textstrings>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'MCDI':
				// 4.4   MCDI Music CD identifier
				// CD TOC                <binary data>
				$framedata .= $frame_data['data'];
				break;

			case 'ETCO':
				// 4.5   ETCO Event timing codes
				// Time stamp format    $xx
				//   Where time stamp format is:
				// $01  (32-bit value) MPEG frames from beginning of file
				// $02  (32-bit value) milliseconds from beginning of file
				//   Followed by a list of key events in the following format:
				// Type of event   $xx
				// Time stamp      $xx (xx ...)
				//   The 'Time stamp' is set to zero if directly at the beginning of the sound
				//   or after the previous event. All events MUST be sorted in chronological order.
				if (($frame_data['timestampformat'] > 2) || ($frame_data['timestampformat'] < 1)) {
					$error .= 'Invalid Time Stamp Format byte in '.$frame_name.' ('.$frame_data['timestampformat'].')<BR>';
				} else {
					$framedata .= chr($frame_data['timestampformat']);
					foreach ($frame_data as $key => $val) {
						if (!ID3v2IsValidETCOevent($val['typeid'], $majorversion)) {
							$error .= 'Invalid Event Type byte in '.$frame_name.' ('.$val['typeid'].')<BR>';
						} elseif (($key != 'timestampformat') && ($key != 'flags')) {
							if (($val['timestamp'] > 0) && ($previousETCOtimestamp >= $val['timestamp'])) {
								//   The 'Time stamp' is set to zero if directly at the beginning of the sound
								//   or after the previous event. All events MUST be sorted in chronological order.
								$error .= 'Out-of-order timestamp in '.$frame_name.' ('.$val['timestamp'].') for Event Type ('.$val['typeid'].')<BR>';
							} else {
								$framedata .= chr($val['typeid']);
								$framedata .= BigEndian2String($val['timestamp'], 4, false);
							}
						}
					}
				}
				break;

			case 'MLLT':
				// 4.6   MLLT MPEG location lookup table
				// MPEG frames between reference  $xx xx
				// Bytes between reference        $xx xx xx
				// Milliseconds between reference $xx xx xx
				// Bits for bytes deviation       $xx
				// Bits for milliseconds dev.     $xx
				//   Then for every reference the following data is included;
				// Deviation in bytes         %xxx....
				// Deviation in milliseconds  %xxx....
				if (($frame_data['framesbetweenreferences'] > 0) && ($frame_data['framesbetweenreferences'] <= 65535)) {
					$framedata .= BigEndian2String($frame_data['framesbetweenreferences'], 2, false);
				} else {
					$error .= 'Invalid MPEG Frames Between References in '.$frame_name.' ('.$frame_data['framesbetweenreferences'].')<BR>';
				}
				if (($frame_data['bytesbetweenreferences'] > 0) && ($frame_data['bytesbetweenreferences'] <= 16777215)) {
					$framedata .= BigEndian2String($frame_data['bytesbetweenreferences'], 3, false);
				} else {
					$error .= 'Invalid bytes Between References in '.$frame_name.' ('.$frame_data['bytesbetweenreferences'].')<BR>';
				}
				if (($frame_data['msbetweenreferences'] > 0) && ($frame_data['msbetweenreferences'] <= 16777215)) {
					$framedata .= BigEndian2String($frame_data['msbetweenreferences'], 3, false);
				} else {
					$error .= 'Invalid Milliseconds Between References in '.$frame_name.' ('.$frame_data['msbetweenreferences'].')<BR>';
				}
				if (!IsWithinBitRange($frame_data['bitsforbytesdeviation'], 8, false)) {
					if (($frame_data['bitsforbytesdeviation'] % 4) == 0) {
						$framedata .= chr($frame_data['bitsforbytesdeviation']);
					} else {
						$error .= 'Bits For Bytes Deviation in '.$frame_name.' ('.$frame_data['bitsforbytesdeviation'].') must be a multiple of 4.<BR>';
					}
				} else {
					$error .= 'Invalid Bits For Bytes Deviation in '.$frame_name.' ('.$frame_data['bitsforbytesdeviation'].')<BR>';
				}
				if (!IsWithinBitRange($frame_data['bitsformsdeviation'], 8, false)) {
					if (($frame_data['bitsformsdeviation'] % 4) == 0) {
						$framedata .= chr($frame_data['bitsformsdeviation']);
					} else {
						$error .= 'Bits For Milliseconds Deviation in '.$frame_name.' ('.$frame_data['bitsforbytesdeviation'].') must be a multiple of 4.<BR>';
					}
				} else {
					$error .= 'Invalid Bits For Milliseconds Deviation in '.$frame_name.' ('.$frame_data['bitsformsdeviation'].')<BR>';
				}
				foreach ($frame_data as $key => $val) {
					if (($key != 'framesbetweenreferences') && ($key != 'bytesbetweenreferences') && ($key != 'msbetweenreferences') && ($key != 'bitsforbytesdeviation') && ($key != 'bitsformsdeviation') && ($key != 'flags')) {
						$unwrittenbitstream .= str_pad(Dec2Bin($val['bytedeviation']), $frame_data['bitsforbytesdeviation'], '0', STR_PAD_LEFT);
						$unwrittenbitstream .= str_pad(Dec2Bin($val['msdeviation']),   $frame_data['bitsformsdeviation'],    '0', STR_PAD_LEFT);
					}
				}
				for ($i=0;$i<strlen($unwrittenbitstream);$i+=8) {
					$highnibble = bindec(substr($unwrittenbitstream, $i, 4)) << 4;
					$lownibble  = bindec(substr($unwrittenbitstream, $i + 4, 4));
					$framedata .= chr($highnibble & $lownibble);
				}
				break;

			case 'SYTC':
				// 4.7   SYTC Synchronised tempo codes
				// Time stamp format   $xx
				// Tempo data          <binary data>
				//   Where time stamp format is:
				// $01  (32-bit value) MPEG frames from beginning of file
				// $02  (32-bit value) milliseconds from beginning of file
				if (($frame_data['timestampformat'] > 2) || ($frame_data['timestampformat'] < 1)) {
					$error .= 'Invalid Time Stamp Format byte in '.$frame_name.' ('.$frame_data['timestampformat'].')<BR>';
				} else {
					$framedata .= chr($frame_data['timestampformat']);
					foreach ($frame_data as $key => $val) {
						if (!ID3v2IsValidETCOevent($val['typeid'], $majorversion)) {
							$error .= 'Invalid Event Type byte in '.$frame_name.' ('.$val['typeid'].')<BR>';
						} elseif (($key != 'timestampformat') && ($key != 'flags')) {
							if (($val['tempo'] < 0) || ($val['tempo'] > 510)) {
								$error .= 'Invalid Tempo (max = 510) in '.$frame_name.' ('.$val['tempo'].') at timestamp ('.$val['timestamp'].')<BR>';
							} else {
								if ($val['tempo'] > 255) {
									$framedata .= chr(255);
									$val['tempo'] -= 255;
								}
								$framedata .= chr($val['tempo']);
								$framedata .= BigEndian2String($val['timestamp'], 4, false);
							}
						}
					}
				}
				break;

			case 'USLT':
				// 4.8   USLT Unsynchronised lyric/text transcription
				// Text encoding        $xx
				// Language             $xx xx xx
				// Content descriptor   <text string according to encoding> $00 (00)
				// Lyrics/text          <full text string according to encoding>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (LanguageLookup($frame_data['language'], true) == '') {
					$error .= 'Invalid Language in '.$frame_name.' ('.$frame_data['language'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= strtolower($frame_data['language']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'SYLT':
				// 4.9   SYLT Synchronised lyric/text
				// Text encoding        $xx
				// Language             $xx xx xx
				// Time stamp format    $xx
				//   $01  (32-bit value) MPEG frames from beginning of file
				//   $02  (32-bit value) milliseconds from beginning of file
				// Content type         $xx
				// Content descriptor   <text string according to encoding> $00 (00)
				//   Terminated text to be synced (typically a syllable)
				//   Sync identifier (terminator to above string)   $00 (00)
				//   Time stamp                                     $xx (xx ...)
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (LanguageLookup($frame_data['language'], true) == '') {
					$error .= 'Invalid Language in '.$frame_name.' ('.$frame_data['language'].')<BR>';
				} elseif (($frame_data['timestampformat'] > 2) || ($frame_data['timestampformat'] < 1)) {
					$error .= 'Invalid Time Stamp Format byte in '.$frame_name.' ('.$frame_data['timestampformat'].')<BR>';
				} elseif (!ID3v2IsValidSYLTtype($frame_data['contenttypeid'], $majorversion)) {
					$error .= 'Invalid Content Type byte in '.$frame_name.' ('.$frame_data['contenttypeid'].')<BR>';
				} elseif (!is_array($frame_data['data'])) {
					$error .= 'Invalid Lyric/Timestamp data in '.$frame_name.' (must be an array)<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= strtolower($frame_data['language']);
					$framedata .= chr($frame_data['timestampformat']);
					$framedata .= chr($frame_data['contenttypeid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					ksort($frame_data['data']);
					foreach ($frame_data['data'] as $key => $val) {
						$framedata .= $val['data'].TextEncodingLookup('terminator', $frame_data['encodingid']);
						$framedata .= BigEndian2String($val['timestamp'], 4, false);
					}
				}
				break;

			case 'COMM':
				// 4.10  COMM Comments
				// Text encoding          $xx
				// Language               $xx xx xx
				// Short content descrip. <text string according to encoding> $00 (00)
				// The actual text        <full text string according to encoding>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (LanguageLookup($frame_data['language'], true) == '') {
					$error .= 'Invalid Language in '.$frame_name.' ('.$frame_data['language'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= strtolower($frame_data['language']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'RVA2':
				// 4.11  RVA2 Relative volume adjustment (2) (ID3v2.4+ only)
				// Identification          <text string> $00
				//   The 'identification' string is used to identify the situation and/or
				//   device where this adjustment should apply. The following is then
				//   repeated for every channel:
				// Type of channel         $xx
				// Volume adjustment       $xx xx
				// Bits representing peak  $xx
				// Peak volume             $xx (xx ...)
				$framedata .= str_replace(chr(0), '', $frame_data['description']).chr(0);
				foreach ($frame_data as $key => $val) {
					if ($key != 'description') {
						$framedata .= chr($val['channeltypeid']);
						$framedata .= BigEndian2String($val['volumeadjust'], 2, false, true); // signed 16-bit
						if (!IsWithinBitRange($frame_data['bitspeakvolume'], 8, false)) {
							$framedata .= chr($val['bitspeakvolume']);
							if ($val['bitspeakvolume'] > 0) {
								$framedata .= BigEndian2String($val['peakvolume'], ceil($val['bitspeakvolume'] / 8), false, false);
							}
						} else {
							$error .= 'Invalid Bits Representing Peak Volume in '.$frame_name.' ('.$val['bitspeakvolume'].') (range = 0 to 255)<BR>';
						}
					}
				}
				break;

			case 'RVAD':
				// 4.12  RVAD Relative volume adjustment (ID3v2.3 only)
				// Increment/decrement     %00fedcba
				// Bits used for volume descr.        $xx
				// Relative volume change, right      $xx xx (xx ...) // a
				// Relative volume change, left       $xx xx (xx ...) // b
				// Peak volume right                  $xx xx (xx ...)
				// Peak volume left                   $xx xx (xx ...)
				// Relative volume change, right back $xx xx (xx ...) // c
				// Relative volume change, left back  $xx xx (xx ...) // d
				// Peak volume right back             $xx xx (xx ...)
				// Peak volume left back              $xx xx (xx ...)
				// Relative volume change, center     $xx xx (xx ...) // e
				// Peak volume center                 $xx xx (xx ...)
				// Relative volume change, bass       $xx xx (xx ...) // f
				// Peak volume bass                   $xx xx (xx ...)
				if (!IsWithinBitRange($frame_data['bitsvolume'], 8, false)) {
					$error .= 'Invalid Bits For Volume Description byte in '.$frame_name.' ('.$frame_data['bitsvolume'].') (range = 1 to 255)<BR>';
				} else {
					$incdecflag .= '00';
					$incdecflag .= Bool2IntString($frame_data['incdec']['right']);     // a - Relative volume change, right
					$incdecflag .= Bool2IntString($frame_data['incdec']['left']);      // b - Relative volume change, left
					$incdecflag .= Bool2IntString($frame_data['incdec']['rightrear']); // c - Relative volume change, right back
					$incdecflag .= Bool2IntString($frame_data['incdec']['leftrear']);  // d - Relative volume change, left back
					$incdecflag .= Bool2IntString($frame_data['incdec']['center']);    // e - Relative volume change, center
					$incdecflag .= Bool2IntString($frame_data['incdec']['bass']);      // f - Relative volume change, bass
					$framedata .= chr(bindec($incdecflag));
					$framedata .= chr($frame_data['bitsvolume']);
					$framedata .= BigEndian2String($frame_data['volumechange']['right'], ceil($frame_data['bitsvolume'] / 8), false);
					$framedata .= BigEndian2String($frame_data['volumechange']['left'],  ceil($frame_data['bitsvolume'] / 8), false);
					$framedata .= BigEndian2String($frame_data['peakvolume']['right'], ceil($frame_data['bitsvolume'] / 8), false);
					$framedata .= BigEndian2String($frame_data['peakvolume']['left'],  ceil($frame_data['bitsvolume'] / 8), false);
					if ($frame_data['volumechange']['rightrear'] || $frame_data['volumechange']['leftrear'] ||
						$frame_data['peakvolume']['rightrear'] || $frame_data['peakvolume']['leftrear'] ||
						$frame_data['volumechange']['center'] || $frame_data['peakvolume']['center'] ||
						$frame_data['volumechange']['bass'] || $frame_data['peakvolume']['bass']) {
							$framedata .= BigEndian2String($frame_data['volumechange']['rightrear'], ceil($frame_data['bitsvolume']/8), false);
							$framedata .= BigEndian2String($frame_data['volumechange']['leftrear'],  ceil($frame_data['bitsvolume']/8), false);
							$framedata .= BigEndian2String($frame_data['peakvolume']['rightrear'], ceil($frame_data['bitsvolume']/8), false);
							$framedata .= BigEndian2String($frame_data['peakvolume']['leftrear'],  ceil($frame_data['bitsvolume']/8), false);
					}
					if ($frame_data['volumechange']['center'] || $frame_data['peakvolume']['center'] ||
						$frame_data['volumechange']['bass'] || $frame_data['peakvolume']['bass']) {
							$framedata .= BigEndian2String($frame_data['volumechange']['center'], ceil($frame_data['bitsvolume']/8), false);
							$framedata .= BigEndian2String($frame_data['peakvolume']['center'], ceil($frame_data['bitsvolume']/8), false);
					}
					if ($frame_data['volumechange']['bass'] || $frame_data['peakvolume']['bass']) {
							$framedata .= BigEndian2String($frame_data['volumechange']['bass'], ceil($frame_data['bitsvolume']/8), false);
							$framedata .= BigEndian2String($frame_data['peakvolume']['bass'], ceil($frame_data['bitsvolume']/8), false);
					}
				}
				break;

			case 'EQU2':
				// 4.12  EQU2 Equalisation (2) (ID3v2.4+ only)
				// Interpolation method  $xx
				//   $00  Band
				//   $01  Linear
				// Identification        <text string> $00
				//   The following is then repeated for every adjustment point
				// Frequency          $xx xx
				// Volume adjustment  $xx xx
				if (($frame_data['interpolationmethod'] < 0) || ($frame_data['interpolationmethod'] > 1)) {
					$error .= 'Invalid Interpolation Method byte in '.$frame_name.' ('.$frame_data['interpolationmethod'].') (valid = 0 or 1)<BR>';
				} else {
					$framedata .= chr($frame_data['interpolationmethod']);
					$framedata .= str_replace(chr(0), '', $frame_data['description']).chr(0);
					foreach ($frame_data['data'] as $key => $val) {
						$framedata .= BigEndian2String(round($key * 2), 2, false);
						$framedata .= BigEndian2String($val, 2, false, true); // signed 16-bit
					}
				}
				break;

			case 'EQUA':
				// 4.12  EQUA Equalisation (ID3v2.3 only)
				// Adjustment bits    $xx
				//   This is followed by 2 bytes + ('adjustment bits' rounded up to the
				//   nearest byte) for every equalisation band in the following format,
				//   giving a frequency range of 0 - 32767Hz:
				// Increment/decrement   %x (MSB of the Frequency)
				// Frequency             (lower 15 bits)
				// Adjustment            $xx (xx ...)
				if (!IsWithinBitRange($frame_data['bitsvolume'], 8, false)) {
					$error .= 'Invalid Adjustment Bits byte in '.$frame_name.' ('.$frame_data['bitsvolume'].') (range = 1 to 255)<BR>';
				} else {
					$framedata .= chr($frame_data['adjustmentbits']);
					foreach ($frame_data as $key => $val) {
						if ($key != 'bitsvolume') {
							if (($key > 32767) || ($key < 0)) {
								$error .= 'Invalid Frequency in '.$frame_name.' ('.$key.') (range = 0 to 32767)<BR>';
							} else {
								if ($val >= 0) {
									// put MSB of frequency to 1 if increment, 0 if decrement
									$key |= 0x8000;
								}
								$framedata .= BigEndian2String($key, 2, false);
								$framedata .= BigEndian2String($val, ceil($frame_data['adjustmentbits'] / 8), false);
							}
						}
					}
				}
				break;

			case 'RVRB':
				// 4.13  RVRB Reverb
				// Reverb left (ms)                 $xx xx
				// Reverb right (ms)                $xx xx
				// Reverb bounces, left             $xx
				// Reverb bounces, right            $xx
				// Reverb feedback, left to left    $xx
				// Reverb feedback, left to right   $xx
				// Reverb feedback, right to right  $xx
				// Reverb feedback, right to left   $xx
				// Premix left to right             $xx
				// Premix right to left             $xx
				if (!IsWithinBitRange($frame_data['left'], 16, false)) {
					$error .= 'Invalid Reverb Left in '.$frame_name.' ('.$frame_data['left'].') (range = 0 to 65535)<BR>';
				} elseif (!IsWithinBitRange($frame_data['right'], 16, false)) {
					$error .= 'Invalid Reverb Left in '.$frame_name.' ('.$frame_data['right'].') (range = 0 to 65535)<BR>';
				} elseif (!IsWithinBitRange($frame_data['bouncesL'], 8, false)) {
					$error .= 'Invalid Reverb Bounces, Left in '.$frame_name.' ('.$frame_data['bouncesL'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['bouncesR'], 8, false)) {
					$error .= 'Invalid Reverb Bounces, Right in '.$frame_name.' ('.$frame_data['bouncesR'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['feedbackLL'], 8, false)) {
					$error .= 'Invalid Reverb Feedback, Left-To-Left in '.$frame_name.' ('.$frame_data['feedbackLL'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['feedbackLR'], 8, false)) {
					$error .= 'Invalid Reverb Feedback, Left-To-Right in '.$frame_name.' ('.$frame_data['feedbackLR'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['feedbackRR'], 8, false)) {
					$error .= 'Invalid Reverb Feedback, Right-To-Right in '.$frame_name.' ('.$frame_data['feedbackRR'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['feedbackRL'], 8, false)) {
					$error .= 'Invalid Reverb Feedback, Right-To-Left in '.$frame_name.' ('.$frame_data['feedbackRL'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['premixLR'], 8, false)) {
					$error .= 'Invalid Premix, Left-To-Right in '.$frame_name.' ('.$frame_data['premixLR'].') (range = 0 to 255)<BR>';
				} elseif (!IsWithinBitRange($frame_data['premixRL'], 8, false)) {
					$error .= 'Invalid Premix, Right-To-Left in '.$frame_name.' ('.$frame_data['premixRL'].') (range = 0 to 255)<BR>';
				} else {
					$framedata .= BigEndian2String($frame_data['left'], 2, false);
					$framedata .= BigEndian2String($frame_data['right'], 2, false);
					$framedata .= chr($frame_data['bouncesL']);
					$framedata .= chr($frame_data['bouncesR']);
					$framedata .= chr($frame_data['feedbackLL']);
					$framedata .= chr($frame_data['feedbackLR']);
					$framedata .= chr($frame_data['feedbackRR']);
					$framedata .= chr($frame_data['feedbackRL']);
					$framedata .= chr($frame_data['premixLR']);
					$framedata .= chr($frame_data['premixRL']);
				}
				break;

			case 'APIC':
				// 4.14  APIC Attached picture
				// Text encoding      $xx
				// MIME type          <text string> $00
				// Picture type       $xx
				// Description        <text string according to encoding> $00 (00)
				// Picture data       <binary data>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (!ID3v2IsValidAPICpicturetype($frame_data['picturetypeid'], $majorversion)) {
					$error .= 'Invalid Picture Type byte in '.$frame_name.' ('.$frame_data['picturetypeid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (($majorversion >= 3) && (!ID3v2IsValidAPICimageformat($frame_data['mime'], $majorversion))) {
					$error .= 'Invalid MIME Type in '.$frame_name.' ('.$frame_data['mime'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (($frame_data['mime'] == '-->') && (!IsValidURL($frame_data['data'], false, false))) {
					$error .= 'Invalid URL in '.$frame_name.' ('.$frame_data['data'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= str_replace(chr(0), '', $frame_data['mime']).chr(0);
					$framedata .= chr($frame_data['picturetypeid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'GEOB':
				// 4.15  GEOB General encapsulated object
				// Text encoding          $xx
				// MIME type              <text string> $00
				// Filename               <text string according to encoding> $00 (00)
				// Content description    <text string according to encoding> $00 (00)
				// Encapsulated object    <binary data>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
				} elseif (!IsValidMIMEstring($frame_data['mime'])) {
					$error .= 'Invalid MIME Type in '.$frame_name.' ('.$frame_data['mime'].')<BR>';
				} elseif (!$frame_data['description']) {
					$error .= 'Missing Description in '.$frame_name.'<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= str_replace(chr(0), '', $frame_data['mime']).chr(0);
					$framedata .= $frame_data['filename'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'PCNT':
				// 4.16  PCNT Play counter
				//   When the counter reaches all one's, one byte is inserted in
				//   front of the counter thus making the counter eight bits bigger
				// Counter        $xx xx xx xx (xx ...)
				$framedata .= BigEndian2String($frame_data['data'], 4, false);
				break;

			case 'POPM':
				// 4.17  POPM Popularimeter
				//   When the counter reaches all one's, one byte is inserted in
				//   front of the counter thus making the counter eight bits bigger
				// Email to user   <text string> $00
				// Rating          $xx
				// Counter         $xx xx xx xx (xx ...)
				if (!IsWithinBitRange($frame_data['rating'], 8, false)) {
					$error .= 'Invalid Rating byte in '.$frame_name.' ('.$frame_data['rating'].') (range = 0 to 255)<BR>';
				} elseif (!IsValidEmail($frame_data['email'])) {
					$error .= 'Invalid Email in '.$frame_name.' ('.$frame_data['email'].')<BR>';
				} else {
					$framedata .= str_replace(chr(0), '', $frame_data['email']).chr(0);
					$framedata .= chr($frame_data['rating']);
					$framedata .= BigEndian2String($frame_data['data'], 4, false);
				}
				break;

			case 'RBUF':
				// 4.18  RBUF Recommended buffer size
				// Buffer size               $xx xx xx
				// Embedded info flag        %0000000x
				// Offset to next tag        $xx xx xx xx
				if (!IsWithinBitRange($frame_data['buffersize'], 24, false)) {
					$error .= 'Invalid Buffer Size in '.$frame_name.'<BR>';
				} elseif (!IsWithinBitRange($frame_data['nexttagoffset'], 32, false)) {
					$error .= 'Invalid Offset To Next Tag in '.$frame_name.'<BR>';
				} else {
					$framedata .= BigEndian2String($frame_data['buffersize'], 3, false);
					$flag .= '0000000';
					$flag .= Bool2IntString($frame_data['flags']['embededinfo']);
					$framedata .= chr(bindec($flag));
					$framedata .= BigEndian2String($frame_data['nexttagoffset'], 4, false);
				}
				break;

			case 'AENC':
				// 4.19  AENC Audio encryption
				// Owner identifier   <text string> $00
				// Preview start      $xx xx
				// Preview length     $xx xx
				// Encryption info    <binary data>
				if (!IsWithinBitRange($frame_data['previewstart'], 16, false)) {
					$error .= 'Invalid Preview Start in '.$frame_name.' ('.$frame_data['previewstart'].')<BR>';
				} elseif (!IsWithinBitRange($frame_data['previewlength'], 16, false)) {
					$error .= 'Invalid Preview Length in '.$frame_name.' ('.$frame_data['previewlength'].')<BR>';
				} else {
					$framedata .= str_replace(chr(0), '', $frame_data['ownerid']).chr(0);
					$framedata .= BigEndian2String($frame_data['previewstart'], 2, false);
					$framedata .= BigEndian2String($frame_data['previewlength'], 2, false);
					$framedata .= $frame_data['encryptioninfo'];
				}
				break;

			case 'LINK':
				// 4.20  LINK Linked information
				// Frame identifier               $xx xx xx xx
				// URL                            <text string> $00
				// ID and additional data         <text string(s)>
				if (!IsValidID3v2FrameName($frame_data['frameid'], $majorversion)) {
					$error .= 'Invalid Frame Identifier in '.$frame_name.' ('.$frame_data['frameid'].')<BR>';
				} elseif (!IsValidURL($frame_data['url'], true, false)) {
					$error .= 'Invalid URL in '.$frame_name.' ('.$frame_data['url'].')<BR>';
				} elseif ((($frame_data['frameid'] == 'AENC') || ($frame_data['frameid'] == 'APIC') || ($frame_data['frameid'] == 'GEOB') || ($frame_data['frameid'] == 'TXXX')) && ($frame_data['additionaldata'] == '')) {
					$error .= 'Content Descriptor must be specified as additional data for Frame Identifier of '.$frame_data['frameid'].' in '.$frame_name.'<BR>';
				} elseif (($frame_data['frameid'] == 'USER') && (LanguageLookup($frame_data['additionaldata'], true) == '')) {
					$error .= 'Language must be specified as additional data for Frame Identifier of '.$frame_data['frameid'].' in '.$frame_name.'<BR>';
				} elseif (($frame_data['frameid'] == 'PRIV') && ($frame_data['additionaldata'] == '')) {
					$error .= 'Owner Identifier must be specified as additional data for Frame Identifier of '.$frame_data['frameid'].' in '.$frame_name.'<BR>';
				} elseif ((($frame_data['frameid'] == 'COMM') || ($frame_data['frameid'] == 'SYLT') || ($frame_data['frameid'] == 'USLT')) && ((LanguageLookup(substr($frame_data['additionaldata'], 0, 3), true) == '') || (substr($frame_data['additionaldata'], 3) == ''))) {
					$error .= 'Language followed by Content Descriptor must be specified as additional data for Frame Identifier of '.$frame_data['frameid'].' in '.$frame_name.'<BR>';
				} else {
					$framedata .= $frame_data['frameid'];
					$framedata .= str_replace(chr(0), '', $frame_data['url']).chr(0);
					switch ($frame_data['frameid']) {
						case 'COMM':
						case 'SYLT':
						case 'USLT':
						case 'PRIV':
						case 'USER':
						case 'AENC':
						case 'APIC':
						case 'GEOB':
						case 'TXXX':
							$framedata .= $frame_data['additionaldata'];
							break;
						case 'ASPI':
						case 'ETCO':
						case 'EQU2':
						case 'MCID':
						case 'MLLT':
						case 'OWNE':
						case 'RVA2':
						case 'RVRB':
						case 'SYTC':
						case 'IPLS':
						case 'RVAD':
						case 'EQUA':
							// no additional data required
							break;
						case 'RBUF':
							if ($majorversion == 3) {
								// no additional data required
							} else {
								$error .= $frame_data['frameid'].' is not a valid Frame Identifier in '.$frame_name.' (in ID3v2.'.$majorversion.')<BR>';
							}

						default:
							if ((substr($frame_data['frameid'], 0, 1) == 'T') || (substr($frame_data['frameid'], 0, 1) == 'W')) {
								// no additional data required
							} else {
								$error .= $frame_data['frameid'].' is not a valid Frame Identifier in '.$frame_name.' (in ID3v2.'.$majorversion.')<BR>';
							}
							break;
					}
				}
				break;

			case 'POSS':
				// 4.21  POSS Position synchronisation frame (ID3v2.3+ only)
				// Time stamp format         $xx
				// Position                  $xx (xx ...)
				if (($frame_data['timestampformat'] < 1) || ($frame_data['timestampformat'] > 2)) {
					$error .= 'Invalid Time Stamp Format in '.$frame_name.' ('.$frame_data['timestampformat'].') (valid = 1 or 2)<BR>';
				} elseif (!IsWithinBitRange($frame_data['position'], 32, false)) {
					$error .= 'Invalid Position in '.$frame_name.' ('.$frame_data['position'].') (range = 0 to 4294967295)<BR>';
				} else {
					$framedata .= chr($frame_data['timestampformat']);
					$framedata .= BigEndian2String($frame_data['position'], 4, false);
				}
				break;

			case 'USER':
				// 4.22  USER Terms of use (ID3v2.3+ only)
				// Text encoding        $xx
				// Language             $xx xx xx
				// The actual text      <text string according to encoding>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].')<BR>';
				} elseif (LanguageLookup($frame_data['language'], true) == '') {
					$error .= 'Invalid Language in '.$frame_name.' ('.$frame_data['language'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= strtolower($frame_data['language']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'OWNE':
				// 4.23  OWNE Ownership frame (ID3v2.3+ only)
				// Text encoding     $xx
				// Price paid        <text string> $00
				// Date of purch.    <text string>
				// Seller            <text string according to encoding>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].')<BR>';
				} elseif (!IsANumber($frame_data['pricepaid']['value'], false)) {
					$error .= 'Invalid Price Paid in '.$frame_name.' ('.$frame_data['pricepaid']['value'].')<BR>';
				} elseif (!IsValidDateStampString($frame_data['purchasedate'])) {
					$error .= 'Invalid Date Of Purchase in '.$frame_name.' ('.$frame_data['purchasedate'].') (format = YYYYMMDD)<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					$framedata .= str_replace(chr(0), '', $frame_data['pricepaid']['value']).chr(0);
					$framedata .= $frame_data['purchasedate'];
					$framedata .= $frame_data['seller'];
				}
				break;

			case 'COMR':
				// 4.24  COMR Commercial frame (ID3v2.3+ only)
				// Text encoding      $xx
				// Price string       <text string> $00
				// Valid until        <text string>
				// Contact URL        <text string> $00
				// Received as        $xx
				// Name of seller     <text string according to encoding> $00 (00)
				// Description        <text string according to encoding> $00 (00)
				// Picture MIME type  <string> $00
				// Seller logo        <binary data>
				if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
					$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].')<BR>';
				} elseif (!IsValidDateStampString($frame_data['pricevaliduntil'])) {
					$error .= 'Invalid Valid Until date in '.$frame_name.' ('.$frame_data['pricevaliduntil'].') (format = YYYYMMDD)<BR>';
				} elseif (!IsValidURL($frame_data['contacturl'], false, true)) {
					$error .= 'Invalid Contact URL in '.$frame_name.' ('.$frame_data['contacturl'].') (allowed schemes: http, https, ftp, mailto)<BR>';
				} elseif (!ID3v2IsValidCOMRreceivedAs($frame_data['receivedasid'], $majorversion)) {
					$error .= 'Invalid Received As byte in '.$frame_name.' ('.$frame_data['contacturl'].') (range = 0 to 8)<BR>';
				} elseif (!IsValidMIMEstring($frame_data['mime'])) {
					$error .= 'Invalid MIME Type in '.$frame_name.' ('.$frame_data['mime'].')<BR>';
				} else {
					$framedata .= chr($frame_data['encodingid']);
					unset($pricestring);
					foreach ($frame_data['price'] as $key => $val) {
						if (ID3v2IsValidPriceString($key.$val['value'])) {
							$pricestrings[] = $key.$val['value'];
						} else {
							$error .= 'Invalid Price String in '.$frame_name.' ('.$key.$val['value'].')<BR>';
						}
					}
					$framedata .= implode('/', $pricestrings);
					$framedata .= $frame_data['pricevaliduntil'];
					$framedata .= str_replace(chr(0), '', $frame_data['contacturl']).chr(0);
					$framedata .= chr($frame_data['receivedasid']);
					$framedata .= $frame_data['sellername'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['description'].TextEncodingLookup('terminator', $frame_data['encodingid']);
					$framedata .= $frame_data['mime'].chr(0);
					$framedata .= $frame_data['logo'];
				}
				break;

			case 'ENCR':
				// 4.25  ENCR Encryption method registration (ID3v2.3+ only)
				// Owner identifier    <text string> $00
				// Method symbol       $xx
				// Encryption data     <binary data>
				if (!IsWithinBitRange($frame_data['methodsymbol'], 8, false)) {
					$error .= 'Invalid Group Symbol in '.$frame_name.' ('.$frame_data['methodsymbol'].') (range = 0 to 255)<BR>';
				} else {
					$framedata .= str_replace(chr(0), '', $frame_data['ownerid']).chr(0);
					$framedata .= ord($frame_data['methodsymbol']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'GRID':
				// 4.26  GRID Group identification registration (ID3v2.3+ only)
				// Owner identifier      <text string> $00
				// Group symbol          $xx
				// Group dependent data  <binary data>
				if (!IsWithinBitRange($frame_data['groupsymbol'], 8, false)) {
					$error .= 'Invalid Group Symbol in '.$frame_name.' ('.$frame_data['groupsymbol'].') (range = 0 to 255)<BR>';
				} else {
					$framedata .= str_replace(chr(0), '', $frame_data['ownerid']).chr(0);
					$framedata .= ord($frame_data['groupsymbol']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'PRIV':
				// 4.27  PRIV Private frame (ID3v2.3+ only)
				// Owner identifier      <text string> $00
				// The private data      <binary data>
				$framedata .= str_replace(chr(0), '', $frame_data['ownerid']).chr(0);
				$framedata .= $frame_data['data'];
				break;

			case 'SIGN':
				// 4.28  SIGN Signature frame (ID3v2.4+ only)
				// Group symbol      $xx
				// Signature         <binary data>
				if (!IsWithinBitRange($frame_data['groupsymbol'], 8, false)) {
					$error .= 'Invalid Group Symbol in '.$frame_name.' ('.$frame_data['groupsymbol'].') (range = 0 to 255)<BR>';
				} else {
					$framedata .= ord($frame_data['groupsymbol']);
					$framedata .= $frame_data['data'];
				}
				break;

			case 'SEEK':
				// 4.29  SEEK Seek frame (ID3v2.4+ only)
				// Minimum offset to next tag       $xx xx xx xx
				if (!IsWithinBitRange($frame_data['data'], 32, false)) {
					$error .= 'Invalid Minimum Offset in '.$frame_name.' ('.$frame_data['data'].') (range = 0 to 4294967295)<BR>';
				} else {
					$framedata .= BigEndian2String($frame_data['data'], 4, false);
				}
				break;

			case 'ASPI':
				// 4.30  ASPI Audio seek point index (ID3v2.4+ only)
				// Indexed data start (S)         $xx xx xx xx
				// Indexed data length (L)        $xx xx xx xx
				// Number of index points (N)     $xx xx
				// Bits per index point (b)       $xx
				//   Then for every index point the following data is included:
				// Fraction at index (Fi)          $xx (xx)
				if (!IsWithinBitRange($frame_data['datastart'], 32, false)) {
					$error .= 'Invalid Indexed Data Start in '.$frame_name.' ('.$frame_data['datastart'].') (range = 0 to 4294967295)<BR>';
				} elseif (!IsWithinBitRange($frame_data['datalength'], 32, false)) {
					$error .= 'Invalid Indexed Data Length in '.$frame_name.' ('.$frame_data['datalength'].') (range = 0 to 4294967295)<BR>';
				} elseif (!IsWithinBitRange($frame_data['indexpoints'], 16, false)) {
					$error .= 'Invalid Number Of Index Points in '.$frame_name.' ('.$frame_data['indexpoints'].') (range = 0 to 65535)<BR>';
				} elseif (!IsWithinBitRange($frame_data['bitsperpoint'], 8, false)) {
					$error .= 'Invalid Bits Per Index Point in '.$frame_name.' ('.$frame_data['bitsperpoint'].') (range = 0 to 255)<BR>';
				} elseif ($frame_data['indexpoints'] != count($frame_data['indexes'])) {
					$error .= 'Number Of Index Points does not match actual supplied data in '.$frame_name.'<BR>';
				} else {
					$framedata .= BigEndian2String($frame_data['datastart'], 4, false);
					$framedata .= BigEndian2String($frame_data['datalength'], 4, false);
					$framedata .= BigEndian2String($frame_data['indexpoints'], 2, false);
					$framedata .= BigEndian2String($frame_data['bitsperpoint'], 1, false);
					foreach ($frame_data['indexes'] as $key => $val) {
						$framedata .= BigEndian2String($val, ceil($frame_data['bitsperpoint'] / 8), false);
					}
				}
				break;

			case 'RGAD':
				//   RGAD Replay Gain Adjustment
				//   http://privatewww.essex.ac.uk/~djmrob/replaygain/
				// Peak Amplitude                     $xx $xx $xx $xx
				// Radio Replay Gain Adjustment        %aaabbbcd %dddddddd
				// Audiophile Replay Gain Adjustment   %aaabbbcd %dddddddd
				//   a - name code
				//   b - originator code
				//   c - sign bit
				//   d - replay gain adjustment

				if (($frame_data['radio_adjustment'] > 51) || ($frame_data['radio_adjustment'] < -51)) {
					$error .= 'Invalid Radio Adjustment in '.$frame_name.' ('.$frame_data['radio_adjustment'].') (range = -51.0 to +51.0)<BR>';
				} elseif (($frame_data['audiophile_adjustment'] > 51) || ($frame_data['audiophile_adjustment'] < -51)) {
					$error .= 'Invalid Audiophile Adjustment in '.$frame_name.' ('.$frame_data['audiophile_adjustment'].') (range = -51.0 to +51.0)<BR>';
				} elseif (!ID3v2IsValidRGADname($frame_data['raw']['radio_name'], $majorversion)) {
					$error .= 'Invalid Radio Name Code in '.$frame_name.' ('.$frame_data['raw']['radio_name'].') (range = 0 to 2)<BR>';
				} elseif (!ID3v2IsValidRGADname($frame_data['raw']['audiophile_name'], $majorversion)) {
					$error .= 'Invalid Audiophile Name Code in '.$frame_name.' ('.$frame_data['raw']['audiophile_name'].') (range = 0 to 2)<BR>';
				} elseif (!ID3v2IsValidRGADoriginator($frame_data['raw']['radio_originator'], $majorversion)) {
					$error .= 'Invalid Radio Originator Code in '.$frame_name.' ('.$frame_data['raw']['radio_originator'].') (range = 0 to 3)<BR>';
				} elseif (!ID3v2IsValidRGADoriginator($frame_data['raw']['audiophile_originator'], $majorversion)) {
					$error .= 'Invalid Audiophile Originator Code in '.$frame_name.' ('.$frame_data['raw']['audiophile_originator'].') (range = 0 to 3)<BR>';
				} else {
					$framedata .= Float2String($frame_data['peakamplitude'], 32);
					$framedata .= RGADgainString($frame_data['raw']['radio_name'], $frame_data['raw']['radio_originator'], $frame_data['radio_adjustment']);
					$framedata .= RGADgainString($frame_data['raw']['audiophile_name'], $frame_data['raw']['audiophile_originator'], $frame_data['audiophile_adjustment']);
				}
				break;

			default:
				if ($frame_name{0} == 'T') {
					// 4.2. T???  Text information frames
					// Text encoding                $xx
					// Information                  <text string(s) according to encoding>
					if (!ID3v2IsValidTextEncoding($frame_data['encodingid'], $majorversion)) {
						$error .= 'Invalid Text Encoding in '.$frame_name.' ('.$frame_data['encodingid'].') for ID3v2.'.$majorversion.'<BR>';
					} else {
						$framedata .= chr($frame_data['encodingid']);
						$framedata .= $frame_data['data'];
					}
				} elseif ($frame_name{0} == 'W') {
					// 4.3. W???  URL link frames
					// URL              <text string>
					if (!IsValidURL($frame_data['url'], false, false)) {
						$error .= 'Invalid URL in '.$frame_name.' ('.$frame_data['url'].')<BR>';
					} else {
						$framedata .= $frame_data['url'];
					}
				} else {
					$error .= $frame_name.' not yet supported in GenerateID3v2FrameData()<BR>';
				}
				break;
		}
    }
    if ($error) {
		if ($showerrors) {
			echo $error;
		}
		return false;
    }
	return $framedata;
}

function ID3v2FrameIsAllowed($frame_name, $frame_data, $majorversion, $showerrors=false) {
    static $PreviousFrames = array();
    $error = '';

    if ($frame_name === null) {
		// if the writing functions are called multiple times, the static array needs to be
		// cleared - this can be done by calling ID3v2FrameIsAllowed(null, '', '')
		$PreviousFrames = array();
		return true;
    }

    if ($majorversion == 4) {
		switch ($frame_name) {
			case 'UFID':
			case 'AENC':
			case 'ENCR':
			case 'GRID':
				if (!isset($frame_data['ownerid'])) {
					$error .= '[ownerid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['ownerid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same OwnerID ('.$frame_data['ownerid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['ownerid'];
				}
				break;

			case 'TXXX':
			case 'WXXX':
			case 'RVA2':
			case 'EQU2':
			case 'APIC':
			case 'GEOB':
				if (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Description ('.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['description'];
				}
				break;

			case 'USER':
				if (!isset($frame_data['language'])) {
					$error .= '[language] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['language'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Language ('.$frame_data['language'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['language'];
				}
				break;

			case 'USLT':
			case 'SYLT':
			case 'COMM':
				if (!isset($frame_data['language'])) {
					$error .= '[language] not specified for '.$frame_name.'<BR>';
				} elseif (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['language'].$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Language + Description ('.$frame_data['language'].' + '.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['language'].$frame_data['description'];
				}
				break;

			case 'POPM':
				if (!isset($frame_data['email'])) {
					$error .= '[email] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['email'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Email ('.$frame_data['email'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['email'];
				}
				break;

			case 'IPLS':
			case 'MCDI':
			case 'ETCO':
			case 'MLLT':
			case 'SYTC':
			case 'RVRB':
			case 'PCNT':
			case 'RBUF':
			case 'POSS':
			case 'OWNE':
			case 'SEEK':
			case 'ASPI':
			case 'RGAD':
				if (in_array($frame_name, $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed<BR>';
				} else {
					$PreviousFrames[] = $frame_name;
				}
				break;

			case 'LINK':
				// this isn't implemented quite right (yet) - it should check the target frame data for compliance
				// but right now it just allows one linked frame of each type, to be safe.
				if (!isset($frame_data['frameid'])) {
					$error .= '[frameid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['frameid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same FrameID ('.$frame_data['frameid'].')<BR>';
				} elseif (in_array($frame_data['frameid'], $PreviousFrames)) {
					// no links to singleton tags
					$error .= 'Cannot specify a '.$frame_name.' tag to a singleton tag that already exists ('.$frame_data['frameid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['frameid']; // only one linked tag of this type
					$PreviousFrames[] = $frame_data['frameid'];             // no non-linked singleton tags of this type
				}
				break;

			case 'COMR':
				//   There may be more than one 'commercial frame' in a tag, but no two may be identical
				// Checking isn't implemented at all (yet) - just assumes that it's OK.
				break;

			case 'PRIV':
			case 'SIGN':
				if (!isset($frame_data['ownerid'])) {
					$error .= '[ownerid] not specified for '.$frame_name.'<BR>';
				} elseif (!isset($frame_data['data'])) {
					$error .= '[data] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['ownerid'].$frame_data['data'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same OwnerID + Data ('.$frame_data['ownerid'].' + '.$frame_data['data'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['ownerid'].$frame_data['data'];
				}
				break;

			default:
				if (($frame_name{0} != 'T') && ($frame_name{0} != 'W')) {
					$error .= 'Frame not allowed in ID3v2.'.$majorversion.': '.$frame_name.'<BR>';
				}
				break;
		}

    } elseif ($majorversion == 3) {

		switch ($frame_name) {
			case 'UFID':
			case 'AENC':
			case 'ENCR':
			case 'GRID':
				if (!isset($frame_data['ownerid'])) {
					$error .= '[ownerid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['ownerid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same OwnerID ('.$frame_data['ownerid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['ownerid'];
				}
				break;

			case 'TXXX':
			case 'WXXX':
			case 'APIC':
			case 'GEOB':
				if (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Description ('.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['description'];
				}
				break;

			case 'USER':
				if (!isset($frame_data['language'])) {
					$error .= '[language] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['language'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Language ('.$frame_data['language'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['language'];
				}
				break;

			case 'USLT':
			case 'SYLT':
			case 'COMM':
				if (!isset($frame_data['language'])) {
					$error .= '[language] not specified for '.$frame_name.'<BR>';
				} elseif (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['language'].$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Language + Description ('.$frame_data['language'].' + '.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['language'].$frame_data['description'];
				}
				break;

			case 'POPM':
				if (!isset($frame_data['email'])) {
					$error .= '[email] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['email'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Email ('.$frame_data['email'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['email'];
				}
				break;

			case 'IPLS':
			case 'MCDI':
			case 'ETCO':
			case 'MLLT':
			case 'SYTC':
			case 'RVAD':
			case 'EQUA':
			case 'RVRB':
			case 'PCNT':
			case 'RBUF':
			case 'POSS':
			case 'OWNE':
			case 'RGAD':
				if (in_array($frame_name, $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed<BR>';
				} else {
					$PreviousFrames[] = $frame_name;
				}
				break;

			case 'LINK':
				// this isn't implemented quite right (yet) - it should check the target frame data for compliance
				// but right now it just allows one linked frame of each type, to be safe.
				if (!isset($frame_data['frameid'])) {
					$error .= '[frameid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['frameid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same FrameID ('.$frame_data['frameid'].')<BR>';
				} elseif (in_array($frame_data['frameid'], $PreviousFrames)) {
					// no links to singleton tags
					$error .= 'Cannot specify a '.$frame_name.' tag to a singleton tag that already exists ('.$frame_data['frameid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['frameid']; // only one linked tag of this type
					$PreviousFrames[] = $frame_data['frameid'];             // no non-linked singleton tags of this type
				}
				break;

			case 'COMR':
				//   There may be more than one 'commercial frame' in a tag, but no two may be identical
				// Checking isn't implemented at all (yet) - just assumes that it's OK.
				break;

			case 'PRIV':
				if (!isset($frame_data['ownerid'])) {
					$error .= '[ownerid] not specified for '.$frame_name.'<BR>';
				} elseif (!isset($frame_data['data'])) {
					$error .= '[data] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['ownerid'].$frame_data['data'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same OwnerID + Data ('.$frame_data['ownerid'].' + '.$frame_data['data'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['ownerid'].$frame_data['data'];
				}
				break;

			default:
				if (($frame_name{0} != 'T') && ($frame_name{0} != 'W')) {
					$error .= 'Frame not allowed in ID3v2.'.$majorversion.': '.$frame_name.'<BR>';
				}
				break;
		}

    } elseif ($majorversion == 2) {

		switch ($frame_name) {
			case 'UFI':
			case 'CRM':
			case 'CRA':
				if (!isset($frame_data['ownerid'])) {
					$error .= '[ownerid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['ownerid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same OwnerID ('.$frame_data['ownerid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['ownerid'];
				}
				break;

			case 'TXX':
			case 'WXX':
			case 'PIC':
			case 'GEO':
				if (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Description ('.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['description'];
				}
				break;

			case 'ULT':
			case 'SLT':
			case 'COM':
				if (!isset($frame_data['language'])) {
					$error .= '[language] not specified for '.$frame_name.'<BR>';
				} elseif (!isset($frame_data['description'])) {
					$error .= '[description] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['language'].$frame_data['description'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Language + Description ('.$frame_data['language'].' + '.$frame_data['description'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['language'].$frame_data['description'];
				}
				break;

			case 'POP':
				if (!isset($frame_data['email'])) {
					$error .= '[email] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['email'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same Email ('.$frame_data['email'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['email'];
				}
				break;

			case 'IPL':
			case 'MCI':
			case 'ETC':
			case 'MLL':
			case 'STC':
			case 'RVA':
			case 'EQU':
			case 'REV':
			case 'CNT':
			case 'BUF':
				if (in_array($frame_name, $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed<BR>';
				} else {
					$PreviousFrames[] = $frame_name;
				}
				break;

			case 'LNK':
				// this isn't implemented quite right (yet) - it should check the target frame data for compliance
				// but right now it just allows one linked frame of each type, to be safe.
				if (!isset($frame_data['frameid'])) {
					$error .= '[frameid] not specified for '.$frame_name.'<BR>';
				} elseif (in_array($frame_name.$frame_data['frameid'], $PreviousFrames)) {
					$error .= 'Only one '.$frame_name.' tag allowed with the same FrameID ('.$frame_data['frameid'].')<BR>';
				} elseif (in_array($frame_data['frameid'], $PreviousFrames)) {
					// no links to singleton tags
					$error .= 'Cannot specify a '.$frame_name.' tag to a singleton tag that already exists ('.$frame_data['frameid'].')<BR>';
				} else {
					$PreviousFrames[] = $frame_name.$frame_data['frameid']; // only one linked tag of this type
					$PreviousFrames[] = $frame_data['frameid'];             // no non-linked singleton tags of this type
				}
				break;

			default:
				if (($frame_name{0} != 'T') && ($frame_name{0} != 'W')) {
					$error .= 'Frame not allowed in ID3v2.'.$majorversion.': '.$frame_name.'<BR>';
				}
				break;
		}
    }

    if ($error) {
		if ($showerrors) {
			echo $error;
		}
		return false;
	}
	return true;
}

function GenerateID3v2Tag($data, $majorversion=4, $minorversion=0, $paddedlength=0, $extendedheader='', $footer=false, $showerrors=true, $noerrorsonly=true) {
    ID3v2FrameIsAllowed(null, '', ''); // clear static array in case this isn't the first call to GenerateID3v2Tag()

    $tagstring = '';
    if (is_array($data)) {
		if (is_array($extendedheader)) {
			// not supported yet
		}
		foreach ($data as $frame_name => $frame_rawinputdata) {
			if (!is_array($frame_rawinputdata) || !isset($frame_rawinputdata[0]) || !is_array($frame_rawinputdata[0])) {
				// force everything to be arrayed so only one processing loop
				$frame_rawinputdata = array($frame_rawinputdata);
			}
			foreach ($frame_rawinputdata as $irrelevantindex => $frame_inputdata) {
				if (IsValidID3v2FrameName($frame_name, $majorversion)) {
					unset($frame_length);
					unset($frame_flags);
					$frame_data = false;
					if (ID3v2FrameIsAllowed($frame_name, $frame_inputdata, $majorversion, $showerrors)) {
						if ($frame_data = GenerateID3v2FrameData($frame_name, $frame_inputdata, $majorversion, $showerrors)) {
							$FrameUnsynchronisation = false;
							if ($majorversion >= 4) {
								// frame-level unsynchronization
								$unsynchdata = Unsynchronise($frame_data);
								if (strlen($unsynchdata) != strlen($frame_data)) {
									// unsynchronization needed
									$FrameUnsynchronisation = true;
									$frame_data = $unsynchdata;
									if (isset($TagUnsynchronisation) && $TagUnsynchronisation === false) {
										// only set to true if ALL frames are unsynchronised
									} else {
										$TagUnsynchronisation = true;
									}
								} else {
									if (isset($TagUnsynchronisation)) {
										$TagUnsynchronisation = false;
									}
								}
								unset($unsynchdata);

								$frame_length = BigEndian2String(strlen($frame_data), 4, true);
							} else {
								$frame_length = BigEndian2String(strlen($frame_data), 4, false);
							}
							$frame_flags  = GenerateID3v2FrameFlags($majorversion, ID3v2FrameFlagsLookupTagAlter($frame_name, $majorversion), ID3v2FrameFlagsLookupFileAlter($frame_name, $majorversion), false, false, false, false, $FrameUnsynchronisation, false);
						}
					} else {
						if ($showerrors) {
							echo 'Frame "'.$frame_name.'" is NOT allowed<BR>';
						}
					}
					if ($frame_data === false) {
						if ($showerrors) {
							echo 'GenerateID3v2FrameData() failed for "'.$frame_name.'"<BR>';
							echo 'Error generated in getID3() v'.GETID3VERSION.'<BR>';
						}
						if ($noerrorsonly) {
							return false;
						} else {
							unset($frame_name);
						}
					}
				} else {
					// ignore any invalid frame names, including 'title', 'header', etc
					unset($frame_name);
					unset($frame_length);
					unset($frame_flags);
					unset($frame_data);
				}
				if (isset($frame_name) && isset($frame_length) && isset($frame_flags) && isset($frame_data)) {
					$tagstring .= $frame_name.$frame_length.$frame_flags.$frame_data;
				}
			}
		}
		if ($footer) {
			if ($showerrors) {
				echo 'Footer not supported (yet)<BR>';
			}
			return false;
		}

		if (!isset($TagUnsynchronisation)) {
			$TagUnsynchronisation = true;
		}
		if ($majorversion <= 3) {
			// tag-level unsynchronization
			$unsynchdata = Unsynchronise($tagstring);
			if (strlen($unsynchdata) != strlen($tagstring)) {
				// unsynchronization needed
				$TagUnsynchronisation = true;
				$tagstring = $unsynchdata;
			}
		}

		if (!$footer && ($paddedlength > (strlen($tagstring) + ID3v2HeaderLength($majorversion)))) {
			// pad up to $paddedlength bytes if unpadded tag is shorter than $paddedlength
			// "Furthermore it MUST NOT have any padding when a tag footer is added to the tag."
			$tagstring .= @str_repeat(chr(0), $paddedlength - strlen($tagstring));
		}
		if (substr($tagstring, strlen($tagstring) - 1, 1) == chr(255)) {
			// special unsynchronization case:
			// if last byte == $FF then appended a $00
			$TagUnsynchronisation = true;
			$tagstring .= chr(0);
		}

		$tagheader  = 'ID3';
		$tagheader .= chr($majorversion);
		$tagheader .= chr($minorversion);
		$tagheader .= GenerateID3v2TagFlags($majorversion, $TagUnsynchronisation, false, (bool) $extendedheader, false, $footer);
		$tagheader .= BigEndian2String(strlen($tagstring), 4, true);

		return $tagheader.$tagstring;
    }
	return false;
}

function WriteID3v2($filename, $data, $majorversion=3, $minorversion=0, $overwrite=false, $paddedlength=4096, $showerrors=true) {
    // File MUST be writeable - CHMOD(646) at least. It's best if the
    // directory is also writeable, because that method is both faster and less susceptible to errors.
	$error = '';
    if (isset($data['id3v2'])) {
    	echo 'Pass only ID3v2 data (not whole GetAllFileInfo() array) to WriteID3v2()';
    	return false;
    }
    if (is_writeable($filename) || (!file_exists($filename) && is_writeable(dirname($filename)))) {
		$OldThisfileInfo = GetAllFileInfo($filename);
		if ($overwrite) {
			// ignore previous data
		} else {
			// merge with existing data
			$data = array_join_merge($OldThisfileInfo['id3v2'], $data);
			$paddedlength = max($OldThisfileInfo['id3v2']['headerlength'], $paddedlength);
		}

		if ($NewID3v2Tag = GenerateID3v2Tag($data, $majorversion, $minorversion, $paddedlength, '', false, $showerrors, true)) {
			if ((!file_exists($filename) && is_writeable(dirname($filename))) || (is_writeable($filename) && isset($OldThisfileInfo['id3v2']['headerlength']) && ($OldThisfileInfo['id3v2']['headerlength'] == strlen($NewID3v2Tag)))) {

				// best and fastest method - insert-overwrite existing tag (padded to length of old tag if neccesary)
				if (file_exists($filename)) {

					ob_start();
					if ($fp = fopen($filename, 'r+b')) {
						rewind($fp);
						fwrite($fp, $NewID3v2Tag, strlen($NewID3v2Tag));
						fclose($fp);
					} else {
						$error .= 'Could not open '.$filename.' mode "r+b" - '.strip_tags(ob_get_contents()).'<BR>';
					}
					ob_end_clean();

				} else {

					ob_start();
					if ($fp = fopen($filename, 'wb')) {
						rewind($fp);
						fwrite($fp, $NewID3v2Tag, strlen($NewID3v2Tag));
						fclose($fp);
					} else {
						$error .= 'Could not open '.$filename.' mode "wb" - '.strip_tags(ob_get_contents()).'<BR>';
					}
					ob_end_clean();

				}

			} else {

				if ($tempfilename = tempnam('/tmp', 'getID3')) {
					ob_start();
					if ($fp_source = fopen($filename, 'rb')) {
						if ($fp_temp = fopen($tempfilename, 'wb')) {

							fwrite($fp_temp, $NewID3v2Tag, strlen($NewID3v2Tag));

							rewind($fp_source);
							if (!empty($OldThisfileInfo['avdataoffset'])) {
								fseek($fp_source, $OldThisfileInfo['avdataoffset'], SEEK_SET);
							}

							while ($buffer = fread($fp_source, FREAD_BUFFER_SIZE)) {
								fwrite($fp_temp, $buffer, strlen($buffer));
							}

							fclose($fp_temp);
							fclose($fp_source);
							copy($tempfilename, $filename);
							unlink($tempfilename);
							return true;

						} else {

							$error .= 'Could not open '.$tempfilename.' mode "wb" - '.strip_tags(ob_get_contents()).'<BR>';

						}
						fclose($fp_source);

					} else {

						$error .= 'Could not open '.$filename.' mode "rb" - '.strip_tags(ob_get_contents()).'<BR>';

					}
					ob_end_clean();
				}
				if ($error) {
					if ($showerrors) {
						echo $error;
					}
				}
				return false;

			}

		} else {

			$error .= 'GenerateID3v2Tag() failed<BR>';

		}

		if ($error) {
			if ($showerrors) {
				echo $error;
			}
			return false;
		}
		return true;
    }
    return false;
}

function RemoveID3v2($filename, $showerrors=false) {
	$error = '';

    // File MUST be writeable - CHMOD(646) at least. It's best if the
    // directory is also writeable, because that method is both faster and less susceptible to errors.
    if (is_writeable(dirname($filename))) {

		// preferred method - only one copying operation, minimal chance of corrupting
		// original file if script is interrupted, but required directory to be writeable
		if ($fp_source = @fopen($filename, 'rb')) {
			$OldThisfileInfo = GetAllFileInfo($filename);
			rewind($fp_source);
			if ($OldThisfileInfo['avdataoffset'] !== false) {
				fseek($fp_source, $OldThisfileInfo['avdataoffset'], SEEK_SET);
			}
			if ($fp_temp = @fopen($filename.'getid3tmp', 'w+b')) {
				// while (($buffer = fread($fp_source, FREAD_BUFFER_SIZE)) !== false) {
				while ($buffer = fread($fp_source, FREAD_BUFFER_SIZE)) {
					fwrite($fp_temp, $buffer, strlen($buffer));
				}
				fclose($fp_temp);
			} else {
				$error .= 'Could not open '.$filename.'getid3tmp mode "w+b"<BR>';
			}
			fclose($fp_source);
		} else {
			$error .= 'Could not open '.$filename.' mode "rb"<BR>';
		}
		if (file_exists($filename)) {
			unlink($filename);
		}
		rename($filename.'getid3tmp', $filename);

    } elseif (is_writable($filename)) {

		// less desirable alternate method - double-copies the file, overwrites original file
		// and could corrupt source file if the script is interrupted or an error occurs.
		if ($fp_source = @fopen($filename, 'rb')) {
			$OldThisfileInfo = GetAllFileInfo($filename);
			rewind($fp_source);
			if ($OldThisfileInfo['avdataoffset'] !== false) {
				fseek($fp_source, $OldThisfileInfo['avdataoffset'], SEEK_SET);
			}
			if ($fp_temp = tmpfile()) {
				// while (($buffer = fread($fp_source, FREAD_BUFFER_SIZE)) !== false) {
				while ($buffer = fread($fp_source, FREAD_BUFFER_SIZE)) {
					fwrite($fp_temp, $buffer, strlen($buffer));
				}
				fclose($fp_source);
				if ($fp_source = @fopen($filename, 'wb')) {
					rewind($fp_temp);
					// while (($buffer = fread($fp_temp, FREAD_BUFFER_SIZE)) !== false) {
					while ($buffer = fread($fp_temp, FREAD_BUFFER_SIZE)) {
						fwrite($fp_source, $buffer, strlen($buffer));
					}
					fseek($fp_temp, -128, SEEK_END);
					fclose($fp_source);
				} else {
					$error .= 'Could not open '.$filename.' mode "wb"<BR>';
				}
				fclose($fp_temp);
			} else {
				$error .= 'Could not create tmpfile()<BR>';
			}
		} else {
			$error .= 'Could not open '.$filename.' mode "rb"<BR>';
		}

    } else {

		$error .= 'Directory and file both not writeable<BR>';

    }

    if ($error) {
		if ($showerrors) {
			echo $error;
		}
		return false;
	}
	return true;
}

function ID3v2IsValidPriceString($pricestring) {
    if (LanguageLookup(substr($pricestring, 0, 3), true) == '') {
		return false;
    } elseif (!IsANumber(substr($pricestring, 3), true)) {
		return false;
    }
    return true;
}

function ID3v2FrameFlagsLookupTagAlter($framename, $majorversion) {
    // unfinished
    switch ($framename) {
		case 'RGAD':
			$allow = true;
		default:
			$allow = false;
			break;
    }
    return $allow;
}

function ID3v2FrameFlagsLookupFileAlter($framename, $majorversion) {
    // unfinished
    switch ($framename) {
		case 'RGAD':
			return false;
			break;

		default:
			return false;
			break;
    }
}

function ID3v2IsValidETCOevent($eventid, $majorversion) {
    if (($eventid < 0) || ($eventid > 0xFF)) {
		// outside range of 1 byte
		return false;
    } elseif (($eventid >= 0xF0) && ($eventid <= 0xFC)) {
		// reserved for future use
		return false;
    } elseif (($eventid >= 0x17) && ($eventid <= 0xDF)) {
		// reserved for future use
		return false;
    } elseif (($eventid >= 0x0E) && ($eventid <= 0x16) && ($majorversion == 2)) {
		// not defined in ID3v2.2
		return false;
    } elseif (($eventid >= 0x15) && ($eventid <= 0x16) && ($majorversion == 3)) {
		// not defined in ID3v2.3
		return false;
    }
    return true;
}

function ID3v2IsValidSYLTtype($contenttype, $majorversion) {
    if (($contenttype >= 0) && ($contenttype <= 8) && ($majorversion == 4)) {
		return true;
    } elseif (($contenttype >= 0) && ($contenttype <= 6) && ($majorversion == 3)) {
		return true;
    }
    return false;
}

function ID3v2IsValidRVA2channeltype($channeltype, $majorversion) {
    if (($channeltype >= 0) && ($channeltype <= 8) && ($majorversion == 4)) {
		return true;
    }
    return false;
}

function ID3v2IsValidAPICpicturetype($picturetype, $majorversion) {
    if (($picturetype >= 0) && ($picturetype <= 0x14) && ($majorversion >= 2) && ($majorversion <= 4)) {
		return true;
    }
    return false;
}

function ID3v2IsValidAPICimageformat($imageformat, $majorversion) {
    if ($imageformat == '-->') {
		return true;
    } elseif ($majorversion == 2) {
		if ((strlen($imageformat) == 3) && ($imageformat == strtoupper($imageformat))) {
			return true;
		}
    } elseif (($majorversion == 3) || ($majorversion == 4)) {
		if (IsValidMIMEstring($imageformat)) {
			return true;
		}
    }
    return false;
}

function ID3v2IsValidCOMRreceivedAs($receivedas, $majorversion) {
    if (($majorversion >= 3) && ($receivedas >= 0) && ($receivedas <= 8)) {
		return true;
    }
    return false;
}

function ID3v2IsValidRGADname($RGADname, $majorversion) {
    if (($RGADname >= 0) && ($RGADname <= 2)) {
		return true;
    }
    return false;
}

function ID3v2IsValidRGADoriginator($RGADoriginator, $majorversion) {
    if (($RGADoriginator >= 0) && ($RGADoriginator <= 3)) {
		return true;
    }
    return false;
}

function ID3v2IsValidTextEncoding($textencodingbyte, $majorversion) {
    $textencodingintval = chr($textencodingbyte);
    if (($textencodingintval >= 0) && ($textencodingintval <= 3) && ($majorversion == 4)) {
		return true;
    } elseif (($textencodingintval >= 0) && ($textencodingintval <= 1) && ($majorversion == 3)) {
		return true;
    } elseif (($textencodingintval >= 0) && ($textencodingintval <= 1) && ($majorversion == 2)) {
		return true;
    }
    return false;
}

?>