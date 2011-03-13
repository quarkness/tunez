<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                ///
//            or http://www.getid3.org                        ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.real.php - part of getID3()                          //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

function getRealHeaderFilepointer(&$fd, &$ThisFileInfo) {
	$ThisFileInfo['fileformat']       = 'real';
	$ThisFileInfo['bitrate']          = 0;
	$ThisFileInfo['playtime_seconds'] = 0;

	fseek($fd, $ThisFileInfo['avdataoffset'], SEEK_SET);
	$ChunkCounter = 0;
	while (ftell($fd) < $ThisFileInfo['avdataend']) {
		$ChunkData  = fread($fd, 8);
		$ChunkName  =               substr($ChunkData, 0, 4);
		$ChunkSize  = BigEndian2Int(substr($ChunkData, 4, 4));

		if (ereg('^\.ra', $ChunkName)) {
			$ThisFileInfo['error'] .= "\n".'This is a headerless RealAudio file that cannot be parsed by this version of getID3() - if you have documentation for this format, please send it to info@getid3.org';
			unset($ThisFileInfo['bitrate']);
			unset($ThisFileInfo['playtime_seconds']);
			return false;
		}

		$ThisFileInfo['real']['chunks'][$ChunkCounter]['name']   = $ChunkName;
		$ThisFileInfo['real']['chunks'][$ChunkCounter]['offset'] = ftell($fd) - 8;
		$ThisFileInfo['real']['chunks'][$ChunkCounter]['length'] = $ChunkSize;
		if (($ThisFileInfo['real']['chunks'][$ChunkCounter]['offset'] + $ThisFileInfo['real']['chunks'][$ChunkCounter]['length']) > $ThisFileInfo['avdataend']) {
			$ThisFileInfo['error'] .= "\n".'Chunk "'.$ThisFileInfo['real']['chunks'][$ChunkCounter]['name'].'" at offset '.$ThisFileInfo['real']['chunks'][$ChunkCounter]['offset'].' claims to be '.$ThisFileInfo['real']['chunks'][$ChunkCounter]['length'].' bytes long, which is beyond end of file';
			return false;
		}

		if ($ChunkSize > (FREAD_BUFFER_SIZE + 8)) {

			$ChunkData .= fread($fd, FREAD_BUFFER_SIZE - 8);
			fseek($fd, $ThisFileInfo['real']['chunks'][$ChunkCounter]['offset'] + $ChunkSize, SEEK_SET);

		} else {

			$ChunkData .= fread($fd, $ChunkSize - 8);

		}
		$offset = 8;

		switch ($ChunkName) {

			case '.RMF': // RealMedia File Header
				$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'] = BigEndian2Int(substr($ChunkData, $offset, 2));
				$offset += 2;
				switch ($ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version']) {

					case 0:
						$ThisFileInfo['real']['chunks'][$ChunkCounter]['file_version']  = BigEndian2Int(substr($ChunkData, $offset, 4));
						$offset += 4;
						$ThisFileInfo['real']['chunks'][$ChunkCounter]['headers_count'] = BigEndian2Int(substr($ChunkData, $offset, 4));
						$offset += 4;
						break;

					default:
						//$ThisFileInfo['warning'] .= "\n".'Expected .RMF-object_version to be "0", actual value is "'.$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'].'" (should not be a problem)';
						break;

				}
				break;


			case 'PROP': // Properties Header
				$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version']              = BigEndian2Int(substr($ChunkData, $offset, 2));
				$offset += 2;
				if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'] == 0) {
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['max_bit_rate']            = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_bit_rate']            = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['max_packet_size']         = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_packet_size']         = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['num_packets']             = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['duration']                = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['preroll']                 = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['index_offset']            = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['data_offset']             = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['num_streams']             = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['flags_raw']               = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['playtime_seconds'] = $ThisFileInfo['real']['chunks'][$ChunkCounter]['duration'] / 1000;
					if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['duration'] > 0) {
						$ThisFileInfo['bitrate'] += $ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_bit_rate'];
					}
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['flags']['save_enabled']   = (bool) ($ThisFileInfo['real']['chunks'][$ChunkCounter]['flags_raw'] & 0x0001);
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['flags']['perfect_play']   = (bool) ($ThisFileInfo['real']['chunks'][$ChunkCounter]['flags_raw'] & 0x0002);
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['flags']['live_broadcast'] = (bool) ($ThisFileInfo['real']['chunks'][$ChunkCounter]['flags_raw'] & 0x0004);
				}
				break;

			case 'MDPR': // Media Properties Header
				$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version']         = BigEndian2Int(substr($ChunkData, $offset, 2));
				$offset += 2;
				if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'] == 0) {
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_number']      = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['max_bit_rate']       = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_bit_rate']       = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['max_packet_size']    = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_packet_size']    = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['start_time']         = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['preroll']            = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['duration']           = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_name_size']   = BigEndian2Int(substr($ChunkData, $offset, 1));
					$offset += 1;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_name']        = substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_name_size']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_name_size'];
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type_size']     = BigEndian2Int(substr($ChunkData, $offset, 1));
					$offset += 1;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type']          = substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type_size']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type_size'];
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['type_specific_len']  = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['type_specific_data'] = substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['type_specific_len']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['type_specific_len'];

					if (empty($ThisFileInfo['playtime_seconds'])) {
						$ThisFileInfo['playtime_seconds'] = max($ThisFileInfo['playtime_seconds'], ($ThisFileInfo['real']['chunks'][$ChunkCounter]['duration'] + $ThisFileInfo['real']['chunks'][$ChunkCounter]['start_time']) / 1000);
					}
					if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['duration'] > 0) {
						if (strstr($ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type'], 'audio')) {
							$ThisFileInfo['audio']['bitrate']    = (isset($ThisFileInfo['audio']['bitrate']) ? $ThisFileInfo['audio']['bitrate'] : 0) + $ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_bit_rate'];
							$ThisFileInfo['audio']['dataformat'] = 'real';
							$ThisFileInfo['audio']['lossless']   = false;
						} elseif (strstr($ThisFileInfo['real']['chunks'][$ChunkCounter]['mime_type'], 'video')) {
							$ThisFileInfo['video']['bitrate']            = (isset($ThisFileInfo['video']['bitrate']) ? $ThisFileInfo['video']['bitrate'] : 0) + $ThisFileInfo['real']['chunks'][$ChunkCounter]['avg_bit_rate'];
							$ThisFileInfo['video']['dataformat']         = 'real';
							$ThisFileInfo['video']['lossless']           = false;
							$ThisFileInfo['video']['pixel_aspect_ratio'] = (float) 1;
						}
						$ThisFileInfo['bitrate'] = (isset($ThisFileInfo['video']['bitrate']) ? $ThisFileInfo['video']['bitrate'] : 0) + (isset($ThisFileInfo['audio']['bitrate']) ? $ThisFileInfo['audio']['bitrate'] : 0);
					}
				}
				break;

			case 'CONT': // Content Description Header
				$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version']           = BigEndian2Int(substr($ChunkData, $offset, 2));
				$offset += 2;
				if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'] == 0) {
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['title_len']     = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['title']                =      (string) substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['title_len']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['title_len'];

					$ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['artist_len']    = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['artist']               =      (string) substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['artist_len']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['artist_len'];

					$ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['copyright_len'] = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['copyright']            =      (string) substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['copyright_len']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['copyright_len'];

					$ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['comment_len']   = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['comment']              =      (string) substr($ChunkData, $offset, $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['comment_len']);
					$offset += $ThisFileInfo['real']['chunks'][$ChunkCounter]['raw']['comment_len'];


					$commentkeystocopy = array('title'=>'title', 'artist'=>'artist', 'copyright'=>'copyright', 'comment'=>'comment');
					foreach ($commentkeystocopy as $key => $val) {
						if ($ThisFileInfo['real']['chunks'][$ChunkCounter]["$key"]) {
							$ThisFileInfo['real']['comments']["$val"][] = trim($ThisFileInfo['real']['chunks'][$ChunkCounter]["$key"]);
						}
					}

				}
				break;


			case 'DATA': // Data Chunk Header
				// do nothing
				break;

			case 'INDX': // Index Section Header
				$ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version']        = BigEndian2Int(substr($ChunkData, $offset, 2));
				$offset += 2;
				if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['object_version'] == 0) {
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['num_indices']       = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['stream_number']     = BigEndian2Int(substr($ChunkData, $offset, 2));
					$offset += 2;
					$ThisFileInfo['real']['chunks'][$ChunkCounter]['next_index_header'] = BigEndian2Int(substr($ChunkData, $offset, 4));
					$offset += 4;

					if ($ThisFileInfo['real']['chunks'][$ChunkCounter]['next_index_header'] == 0) {
						// last index chunk found, ignore rest of file
						return true;
					} else {
						// non-last index chunk, seek to next index chunk (skipping actual index data)
						fseek($fd, $ThisFileInfo['real']['chunks'][$ChunkCounter]['next_index_header'], SEEK_SET);
					}
				}
				break;

			default:
				$ThisFileInfo['warning'] .= "\n".'Unhandled RealMedia chunk "'.$ChunkName.'" at offset '.$ThisFileInfo['real']['chunks'][$ChunkCounter]['offset'];
				break;
		}
		$ChunkCounter++;
	}

	return true;
}

?>