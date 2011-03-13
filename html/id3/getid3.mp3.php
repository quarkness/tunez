<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                ///
//            or http://www.getid3.org                        ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.mp3.php - part of getID3()                           //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

// number of frames to scan to determine if MPEG-audio sequence is valid
// Lower this number to 5-20 for faster scanning
// Increase this number to 50+ for most accurate detection of valid VBR/CBR
// mpeg-audio streams
define('MPEG_VALID_CHECK_FRAMES', 35);

function getMP3headerFilepointer(&$fd, &$ThisFileInfo) {

	getOnlyMPEGaudioInfo($fd, $ThisFileInfo, $ThisFileInfo['avdataoffset']);

	if (isset($ThisFileInfo['mpeg']['audio']['bitrate_mode'])) {
		$ThisFileInfo['audio']['bitrate_mode'] = strtolower($ThisFileInfo['mpeg']['audio']['bitrate_mode']);
	}

	if (((isset($ThisFileInfo['id3v2']) && ($ThisFileInfo['avdataoffset'] > $ThisFileInfo['id3v2']['headerlength'])) || (!isset($ThisFileInfo['id3v2']) && ($ThisFileInfo['avdataoffset'] > 0)))) {

		$ThisFileInfo['warning'] .= "\n".'Unknown data before synch ';
		if (isset($ThisFileInfo['id3v2']['headerlength'])) {
			$ThisFileInfo['warning'] .= '(ID3v2 header ends at '.$ThisFileInfo['id3v2']['headerlength'].', then '.($ThisFileInfo['avdataoffset'] - $ThisFileInfo['id3v2']['headerlength']).' bytes garbage, ';
		} else {
			$ThisFileInfo['warning'] .= '(should be at beginning of file, ';
		}
		$ThisFileInfo['warning'] .= 'synch detected at '.$ThisFileInfo['avdataoffset'].')';
		if ($ThisFileInfo['audio']['bitrate_mode'] == 'cbr') {
			if (!empty($ThisFileInfo['id3v2']['headerlength']) && (($ThisFileInfo['avdataoffset'] - $ThisFileInfo['id3v2']['headerlength']) == $ThisFileInfo['mpeg']['audio']['framelength'])) {
				$ThisFileInfo['warning'] .= '. This is a known problem with some versions of LAME (3.91, 3.92) DLL in CBR mode.';
				$ThisFileInfo['audio']['codec'] = 'LAME';
			} elseif (empty($ThisFileInfo['id3v2']['headerlength']) && ($ThisFileInfo['avdataoffset'] == $ThisFileInfo['mpeg']['audio']['framelength'])) {
				$ThisFileInfo['warning'] .= '. This is a known problem with some versions of LAME (3.91, 3.92) DLL in CBR mode.';
				$ThisFileInfo['audio']['codec'] = 'LAME';
			}
		}

	}

	if (isset($ThisFileInfo['mpeg']['audio']['layer']) && ($ThisFileInfo['mpeg']['audio']['layer'] == 'II')) {
		$ThisFileInfo['audio']['dataformat'] = 'mp2';
	} elseif (isset($ThisFileInfo['mpeg']['audio']['layer']) && ($ThisFileInfo['mpeg']['audio']['layer'] == 'I')) {
		$ThisFileInfo['audio']['dataformat'] = 'mp1';
	}
	if ($ThisFileInfo['fileformat'] == 'mp3') {
		switch ($ThisFileInfo['audio']['dataformat']) {
			case 'mp1':
			case 'mp2':
			case 'mp3':
				$ThisFileInfo['fileformat'] = $ThisFileInfo['audio']['dataformat'];
				break;

			default:
				$ThisFileInfo['warning'] .= "\n".'Expecting [audio][dataformat] to be mp1/mp2/mp3 when fileformat == mp3, [audio][dataformat] actually "'.$ThisFileInfo['audio']['dataformat'].'"';
				break;
		}
	}

	if (empty($ThisFileInfo['fileformat'])) {
		$ThisFileInfo['error'] .= "\n".'Synch not found';
		unset($ThisFileInfo['fileformat']);
		unset($ThisFileInfo['audio']['bitrate_mode']);
		unset($ThisFileInfo['avdataoffset']);
		unset($ThisFileInfo['avdataend']);
		return false;
	}

	$ThisFileInfo['mime_type']         = 'audio/mpeg';
	$ThisFileInfo['audio']['lossless'] = false;

	// Calculate playtime
	if (!isset($ThisFileInfo['playtime_seconds']) && isset($ThisFileInfo['audio']['bitrate']) && ($ThisFileInfo['audio']['bitrate'] > 0)) {
		$ThisFileInfo['playtime_seconds'] = ($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset']) * 8 / $ThisFileInfo['audio']['bitrate'];
	}

	if (isset($ThisFileInfo['mpeg']['audio']['LAME'])) {
		$ThisFileInfo['audio']['codec'] = 'LAME';
		if (!empty($ThisFileInfo['mpeg']['audio']['LAME']['long_version'])) {
			$ThisFileInfo['audio']['encoder'] = trim($ThisFileInfo['mpeg']['audio']['LAME']['long_version']);
		}
	}

	return true;
}


function decodeMPEGaudioHeader($fd, $offset, &$ThisFileInfo, $recursivesearch=true, $ScanAsCBR=false, $FastMPEGheaderScan=false, $allowBitrateMode15=false) {
	static $MPEGaudioVersionLookup;
	static $MPEGaudioLayerLookup;
	static $MPEGaudioBitrateLookup;
	static $MPEGaudioFrequencyLookup;
	static $MPEGaudioChannelModeLookup;
	static $MPEGaudioModeExtensionLookup;
	static $MPEGaudioEmphasisLookup;
	if (empty($MPEGaudioVersionLookup)) {
		$MPEGaudioVersionLookup       = MPEGaudioVersionArray();
		$MPEGaudioLayerLookup         = MPEGaudioLayerArray();
		$MPEGaudioBitrateLookup       = MPEGaudioBitrateArray();
		$MPEGaudioFrequencyLookup     = MPEGaudioFrequencyArray();
		$MPEGaudioChannelModeLookup   = MPEGaudioChannelModeArray();
		$MPEGaudioModeExtensionLookup = MPEGaudioModeExtensionArray();
		$MPEGaudioEmphasisLookup      = MPEGaudioEmphasisArray();
	}

	if ($offset >= $ThisFileInfo['avdataend']) {
		$ThisFileInfo['error'] .= "\n".'end of file encounter looking for MPEG synch';
		return false;
	}
	fseek($fd, $offset, SEEK_SET);
	$headerstring = fread($fd, 1441); // worse-case max length = 32kHz @ 320kbps layer 3 = 1441 bytes/frame

	// MP3 audio frame structure:
	// $aa $aa $aa $aa [$bb $bb] $cc...
	// where $aa..$aa is the four-byte mpeg-audio header (below)
	// $bb $bb is the optional 2-byte CRC
	// and $cc... is the audio data

	$head4 = substr($headerstring, 0, 4);

	static $MPEGaudioHeaderDecodeCache = array();
	if (isset($MPEGaudioHeaderDecodeCache[$head4])) {
		$MPEGheaderRawArray = $MPEGaudioHeaderDecodeCache[$head4];
	} else {
		$MPEGheaderRawArray = MPEGaudioHeaderDecode($head4);
		$MPEGaudioHeaderDecodeCache[$head4] = $MPEGheaderRawArray;
	}

	static $MPEGaudioHeaderValidCache = array();

	// Not in cache
	if (!isset($MPEGaudioHeaderValidCache[$head4])) {
		$MPEGaudioHeaderValidCache[$head4] = MPEGaudioHeaderValid($MPEGheaderRawArray);
	}

	if ($MPEGaudioHeaderValidCache[$head4]) {
		$ThisFileInfo['mpeg']['audio']['raw'] = $MPEGheaderRawArray;
	} else {
		$ThisFileInfo['error'] .= "\n".'Invalid MPEG audio header at offset '.$offset;
		return false;
	}

	if (!$FastMPEGheaderScan) {

		$ThisFileInfo['mpeg']['audio']['version']       = $MPEGaudioVersionLookup[$ThisFileInfo['mpeg']['audio']['raw']['version']];
		$ThisFileInfo['mpeg']['audio']['layer']         = $MPEGaudioLayerLookup[$ThisFileInfo['mpeg']['audio']['raw']['layer']];

		$ThisFileInfo['mpeg']['audio']['channelmode']   = $MPEGaudioChannelModeLookup[$ThisFileInfo['mpeg']['audio']['raw']['channelmode']];
		$ThisFileInfo['mpeg']['audio']['channels']      = (($ThisFileInfo['mpeg']['audio']['channelmode'] == 'mono') ? 1 : 2);
		$ThisFileInfo['mpeg']['audio']['sample_rate']   = $MPEGaudioFrequencyLookup[$ThisFileInfo['mpeg']['audio']['version']][$ThisFileInfo['mpeg']['audio']['raw']['sample_rate']];
		$ThisFileInfo['mpeg']['audio']['protection']    = !$ThisFileInfo['mpeg']['audio']['raw']['protection'];
		$ThisFileInfo['mpeg']['audio']['private']       = (bool) $ThisFileInfo['mpeg']['audio']['raw']['private'];
		$ThisFileInfo['mpeg']['audio']['modeextension'] = $MPEGaudioModeExtensionLookup[$ThisFileInfo['mpeg']['audio']['layer']][$ThisFileInfo['mpeg']['audio']['raw']['modeextension']];
		$ThisFileInfo['mpeg']['audio']['copyright']     = (bool) $ThisFileInfo['mpeg']['audio']['raw']['copyright'];
		$ThisFileInfo['mpeg']['audio']['original']      = (bool) $ThisFileInfo['mpeg']['audio']['raw']['original'];
		$ThisFileInfo['mpeg']['audio']['emphasis']      = $MPEGaudioEmphasisLookup[$ThisFileInfo['mpeg']['audio']['raw']['emphasis']];

		$ThisFileInfo['audio']['channels']    = $ThisFileInfo['mpeg']['audio']['channels'];
		$ThisFileInfo['audio']['sample_rate'] = $ThisFileInfo['mpeg']['audio']['sample_rate'];

		if ($ThisFileInfo['mpeg']['audio']['protection']) {
			$ThisFileInfo['mpeg']['audio']['crc'] = BigEndian2Int(substr($headerstring, 4, 2));
		}

	}

	if ($ThisFileInfo['mpeg']['audio']['raw']['bitrate'] == 15) {
		// http://www.hydrogenaudio.org/?act=ST&f=16&t=9682&st=0
		$ThisFileInfo['warning'] .= "\n".'Invalid bitrate index (15), this is a known bug in free-format MP3s encoded by LAME v3.90 - 3.93.1';
		if ($allowBitrateMode15) {
			$ThisFileInfo['mpeg']['audio']['raw']['bitrate'] = 0;
		} else {
			return false;
		}
	}
	$ThisFileInfo['mpeg']['audio']['padding'] = (bool) $ThisFileInfo['mpeg']['audio']['raw']['padding'];
	$ThisFileInfo['mpeg']['audio']['bitrate'] = $MPEGaudioBitrateLookup[$ThisFileInfo['mpeg']['audio']['version']][$ThisFileInfo['mpeg']['audio']['layer']][$ThisFileInfo['mpeg']['audio']['raw']['bitrate']];

	if (($ThisFileInfo['mpeg']['audio']['bitrate'] == 'free') && ($offset == $ThisFileInfo['avdataoffset'])) {
		// only skip multiple frame check if free-format bitstream found at beginning of file
		// otherwise is quite possibly simply corrupted data
		$recursivesearch = false;
	}

	// For Layer II there are some combinations of bitrate and mode which are not allowed.
	if (!$FastMPEGheaderScan && ($ThisFileInfo['mpeg']['audio']['layer'] == 'II')) {

		$ThisFileInfo['audio']['dataformat'] = 'mp2';
		switch ($ThisFileInfo['mpeg']['audio']['channelmode']) {

			case 'mono':
				if (($ThisFileInfo['mpeg']['audio']['bitrate'] == 'free') || ($ThisFileInfo['mpeg']['audio']['bitrate'] <= 192)) {
					// these are ok
				} else {
					$ThisFileInfo['error'] .= "\n".$ThisFileInfo['mpeg']['audio']['bitrate'].'kbps not allowed in Layer II, '.$ThisFileInfo['mpeg']['audio']['channelmode'].'.';
					return false;
				}
				break;

			case 'stereo':
			case 'joint stereo':
			case 'dual channel':
				if (($ThisFileInfo['mpeg']['audio']['bitrate'] == 'free') || ($ThisFileInfo['mpeg']['audio']['bitrate'] == 64) || ($ThisFileInfo['mpeg']['audio']['bitrate'] >= 96)) {
					// these are ok
				} else {
					$ThisFileInfo['error'] .= "\n".$ThisFileInfo['mpeg']['audio']['bitrate'].'kbps not allowed in Layer II, '.$ThisFileInfo['mpeg']['audio']['channelmode'].'.';
					return false;
				}
				break;

		}

	}


	if ($ThisFileInfo['audio']['sample_rate'] > 0) {
		$ThisFileInfo['mpeg']['audio']['framelength'] = MPEGaudioFrameLength($ThisFileInfo['mpeg']['audio']['bitrate'], $ThisFileInfo['mpeg']['audio']['version'], $ThisFileInfo['mpeg']['audio']['layer'], (int) $ThisFileInfo['mpeg']['audio']['padding'], $ThisFileInfo['audio']['sample_rate']);
	}

	$nextframetestoffset = $offset + 1;
	if ($ThisFileInfo['mpeg']['audio']['bitrate'] != 'free') {

		$ThisFileInfo['audio']['bitrate'] = 1000 * $ThisFileInfo['mpeg']['audio']['bitrate'];

		if (!empty($ThisFileInfo['mpeg']['audio']['framelength'])) {
			$nextframetestoffset = $offset + $ThisFileInfo['mpeg']['audio']['framelength'];
		} else {
			$ThisFileInfo['error'] .= "\n".'Frame at offset('.$offset.') is has an invalid frame length.';
			return false;
		}

	}

	$ExpectedNumberOfAudioBytes = 0;

	////////////////////////////////////////////////////////////////////////////////////
	// Variable-bitrate headers

	if (!$FastMPEGheaderScan) {
		if (substr($headerstring, 4 + 32, 4) == 'VBRI') {
			// Fraunhofer VBR header is hardcoded 'VBRI' at offset 0x24 (36)
			// specs taken from http://minnie.tuhs.org/pipermail/mp3encoder/2001-January/001800.html

			$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'vbr';
			$ThisFileInfo['mpeg']['audio']['VBR_method']   = 'Fraunhofer';
			$ThisFileInfo['audio']['codec']                = 'Fraunhofer';

			$SideInfoData = substr($headerstring, 4 + 2, 32);

			$FraunhoferVBROffset = 36;

			$ThisFileInfo['mpeg']['audio']['VBR_encoder_version']     = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset +  4, 2));
			$ThisFileInfo['mpeg']['audio']['VBR_encoder_delay']       = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset +  6, 2));
			$ThisFileInfo['mpeg']['audio']['VBR_quality']             = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset +  8, 2));
			$ThisFileInfo['mpeg']['audio']['VBR_bytes']               = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset + 10, 4));
			$ThisFileInfo['mpeg']['audio']['VBR_frames']              = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset + 14, 4));
			$ThisFileInfo['mpeg']['audio']['VBR_seek_offsets']        = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset + 18, 2));
			//$ThisFileInfo['mpeg']['audio']['reserved']              = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset + 20, 4)); // hardcoded $00 $01 $00 $02  - purpose unknown
			$ThisFileInfo['mpeg']['audio']['VBR_seek_offsets_stride'] = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset + 24, 2));

			$ExpectedNumberOfAudioBytes = $ThisFileInfo['mpeg']['audio']['VBR_bytes'];

			$previousbyteoffset = $offset;
			for ($i = 0; $i < $ThisFileInfo['mpeg']['audio']['VBR_seek_offsets']; $i++) {
				$Fraunhofer_OffsetN = BigEndian2Int(substr($headerstring, $FraunhoferVBROffset, 2));
				$FraunhoferVBROffset += 2;
				$ThisFileInfo['mpeg']['audio']['VBR_offsets_relative'][$i] = $Fraunhofer_OffsetN;
				$ThisFileInfo['mpeg']['audio']['VBR_offsets_absolute'][$i] = $Fraunhofer_OffsetN + $previousbyteoffset;
				$previousbyteoffset += $Fraunhofer_OffsetN;
			}


		} else {

			// Xing VBR header is hardcoded 'Xing' at a offset 0x0D (13), 0x15 (21) or 0x24 (36)
			// depending on MPEG layer and number of channels

			if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
				if ($ThisFileInfo['mpeg']['audio']['channelmode'] == 'mono') {
					// MPEG-1 (mono)
					$VBRidOffset  = 4 + 17; // 0x15
					$SideInfoData = substr($headerstring, 4 + 2, 17);
				} else {
					// MPEG-1 (stereo, joint-stereo, dual-channel)
					$VBRidOffset = 4 + 32; // 0x24
					$SideInfoData = substr($headerstring, 4 + 2, 32);
				}
			} else { // 2 or 2.5
				if ($ThisFileInfo['mpeg']['audio']['channelmode'] == 'mono') {
					// MPEG-2, MPEG-2.5 (mono)
					$VBRidOffset = 4 + 9;  // 0x0D
					$SideInfoData = substr($headerstring, 4 + 2, 9);
				} else {
					// MPEG-2, MPEG-2.5 (stereo, joint-stereo, dual-channel)
					$VBRidOffset = 4 + 17; // 0x15
					$SideInfoData = substr($headerstring, 4 + 2, 17);
				}
			}

			if ((substr($headerstring, $VBRidOffset, strlen('Xing')) == 'Xing') || (substr($headerstring, $VBRidOffset, strlen('Info')) == 'Info')) {
				// 'Xing' is traditional Xing VBR frame
				// 'Info' is LAME-encoded CBR (This was done to avoid CBR files to be recognized as traditional Xing VBR files by some decoders.)

				$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'vbr';
				$ThisFileInfo['mpeg']['audio']['VBR_method']   = 'Xing';

				$ThisFileInfo['mpeg']['audio']['xing_flags_raw'] = BigEndian2Int(substr($headerstring, $VBRidOffset + 4, 4));

				$ThisFileInfo['mpeg']['audio']['xing_flags']['frames']    = (bool) ($ThisFileInfo['mpeg']['audio']['xing_flags_raw'] & 0x00000001);
				$ThisFileInfo['mpeg']['audio']['xing_flags']['bytes']     = (bool) ($ThisFileInfo['mpeg']['audio']['xing_flags_raw'] & 0x00000002);
				$ThisFileInfo['mpeg']['audio']['xing_flags']['toc']       = (bool) ($ThisFileInfo['mpeg']['audio']['xing_flags_raw'] & 0x00000004);
				$ThisFileInfo['mpeg']['audio']['xing_flags']['vbr_scale'] = (bool) ($ThisFileInfo['mpeg']['audio']['xing_flags_raw'] & 0x00000008);

				if ($ThisFileInfo['mpeg']['audio']['xing_flags']['frames']) {
					$ThisFileInfo['mpeg']['audio']['VBR_frames'] = BigEndian2Int(substr($headerstring, $VBRidOffset +  8, 4));
				}
				if ($ThisFileInfo['mpeg']['audio']['xing_flags']['bytes']) {
					$ThisFileInfo['mpeg']['audio']['VBR_bytes']  = BigEndian2Int(substr($headerstring, $VBRidOffset + 12, 4));
				}

				if (($ThisFileInfo['mpeg']['audio']['bitrate'] == 'free') && !empty($ThisFileInfo['mpeg']['audio']['VBR_frames']) && !empty($ThisFileInfo['mpeg']['audio']['VBR_bytes'])) {
					$framelengthfloat = $ThisFileInfo['mpeg']['audio']['VBR_bytes'] / $ThisFileInfo['mpeg']['audio']['VBR_frames'];
					if ($ThisFileInfo['mpeg']['audio']['layer'] == 'I') {
						// BitRate = (((FrameLengthInBytes / 4) - Padding) * SampleRate) / 12
						$ThisFileInfo['audio']['bitrate'] = ((($framelengthfloat / 4) - intval($ThisFileInfo['mpeg']['audio']['padding'])) * $ThisFileInfo['mpeg']['audio']['sample_rate']) / 12;
					} else {
						// Bitrate = ((FrameLengthInBytes - Padding) * SampleRate) / 144
						$ThisFileInfo['audio']['bitrate'] = (($framelengthfloat - intval($ThisFileInfo['mpeg']['audio']['padding'])) * $ThisFileInfo['mpeg']['audio']['sample_rate']) / 144;
					}
					$ThisFileInfo['mpeg']['audio']['framelength'] = floor($framelengthfloat);
				}

				if ($ThisFileInfo['mpeg']['audio']['xing_flags']['toc']) {
					$LAMEtocData = substr($headerstring, $VBRidOffset + 16, 100);
					for ($i = 0; $i < 100; $i++) {
						$ThisFileInfo['mpeg']['audio']['toc'][$i] = ord($LAMEtocData{$i});
					}
				}
				if ($ThisFileInfo['mpeg']['audio']['xing_flags']['vbr_scale']) {
					$ThisFileInfo['mpeg']['audio']['VBR_scale'] = BigEndian2Int(substr($headerstring, $VBRidOffset + 116, 4));
				}

				// http://gabriel.mp3-tech.org/mp3infotag.html
				if (substr($headerstring, $VBRidOffset + 120, 4) == 'LAME') {
					$ThisFileInfo['mpeg']['audio']['LAME']['long_version']  = substr($headerstring, $VBRidOffset + 120, 20);
					$ThisFileInfo['mpeg']['audio']['LAME']['short_version'] = substr($ThisFileInfo['mpeg']['audio']['LAME']['long_version'], 0, 9);
					$ThisFileInfo['mpeg']['audio']['LAME']['long_version']  = rtrim($ThisFileInfo['mpeg']['audio']['LAME']['long_version'], "\x55\xAA");

					if ($ThisFileInfo['mpeg']['audio']['LAME']['short_version'] >= 'LAME3.90.') {

						// It the LAME tag was only introduced in LAME v3.90
						// http://www.hydrogenaudio.org/?act=ST&f=15&t=9933

						// Offsets of various bytes in http://gabriel.mp3-tech.org/mp3infotag.html
						// are assuming a 'Xing' identifier offset of 0x24, which is the case for
						// MPEG-1 non-mono, but not for other combinations
						$LAMEtagOffsetContant = $VBRidOffset - 0x24;

						// byte $9B  VBR Quality
						// This field is there to indicate a quality level, although the scale was not precised in the original Xing specifications.
						// Actually overwrites original Xing bytes
						unset($ThisFileInfo['mpeg']['audio']['VBR_scale']);
						$ThisFileInfo['mpeg']['audio']['LAME']['vbr_quality'] = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0x9B, 1));

						// bytes $9C-$A4  Encoder short VersionString
						$ThisFileInfo['mpeg']['audio']['LAME']['short_version'] = substr($headerstring, $LAMEtagOffsetContant + 0x9C, 9);
						$ThisFileInfo['mpeg']['audio']['LAME']['long_version']  = $ThisFileInfo['mpeg']['audio']['LAME']['short_version'];

						// byte $A5  Info Tag revision + VBR method
						$LAMEtagRevisionVBRmethod = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xA5, 1));

						$ThisFileInfo['mpeg']['audio']['LAME']['tag_revision']      = ($LAMEtagRevisionVBRmethod & 0xF0) >> 4;
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['vbr_method'] =  $LAMEtagRevisionVBRmethod & 0x0F;
						$ThisFileInfo['mpeg']['audio']['LAME']['vbr_method']        = LAMEvbrMethodLookup($ThisFileInfo['mpeg']['audio']['LAME']['raw']['vbr_method']);

						// byte $A6  Lowpass filter value
						$ThisFileInfo['mpeg']['audio']['LAME']['lowpass_frequency'] = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xA6, 1)) * 100;

						// bytes $A7-$AE  Replay Gain
						// http://privatewww.essex.ac.uk/~djmrob/replaygain/rg_data_format.html
						// bytes $A7-$AA : 32 bit floating point "Peak signal amplitude"
						$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'] = BigEndian2Float(substr($headerstring, $LAMEtagOffsetContant + 0xA7, 4));
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio']      =   BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xAB, 2));
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] =   BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xAD, 2));

						if ($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'] == 0) {
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'] = false;
						}

						if ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio'] != 0) {
							require_once(GETID3_INCLUDEPATH.'getid3.rgad.php');

							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['name']        = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio'] & 0xE000) >> 13;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['originator']  = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio'] & 0x1C00) >> 10;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['sign_bit']    = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio'] & 0x0200) >> 9;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['gain_adjust'] =  $ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_radio'] & 0x01FF;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['name']       = RGADnameLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['name']);
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['originator'] = RGADoriginatorLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['originator']);
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['gain_db']    = RGADadjustmentLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['gain_adjust'], $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['raw']['sign_bit']);

							if ($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'] !== false) {
								$ThisFileInfo['replay_gain']['radio']['peak']   = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'];
							}
							$ThisFileInfo['replay_gain']['radio']['originator'] = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['originator'];
							$ThisFileInfo['replay_gain']['radio']['adjustment'] = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['radio']['gain_db'];
						}
						if ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] != 0) {
							require_once(GETID3_INCLUDEPATH.'getid3.rgad.php');

							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['name']        = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] & 0xE000) >> 13;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['originator']  = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] & 0x1C00) >> 10;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['sign_bit']    = ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] & 0x0200) >> 9;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['gain_adjust'] = $ThisFileInfo['mpeg']['audio']['LAME']['raw']['RGAD_audiophile'] & 0x01FF;
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['name']       = RGADnameLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['name']);
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['originator'] = RGADoriginatorLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['originator']);
							$ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['gain_db']    = RGADadjustmentLookup($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['gain_adjust'], $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['raw']['sign_bit']);

							if ($ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'] !== false) {
								$ThisFileInfo['replay_gain']['audiophile']['peak']   = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['peak_amplitude'];
							}
							$ThisFileInfo['replay_gain']['audiophile']['originator'] = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['originator'];
							$ThisFileInfo['replay_gain']['audiophile']['adjustment'] = $ThisFileInfo['mpeg']['audio']['LAME']['RGAD']['audiophile']['gain_db'];
						}


						// byte $AF  Encoding flags + ATH Type
						$EncodingFlagsATHtype = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xAF, 1));
						$ThisFileInfo['mpeg']['audio']['LAME']['encoding_flags']['nspsytune']   = (bool) ($EncodingFlagsATHtype & 0x10);
						$ThisFileInfo['mpeg']['audio']['LAME']['encoding_flags']['nssafejoint'] = (bool) ($EncodingFlagsATHtype & 0x20);
						$ThisFileInfo['mpeg']['audio']['LAME']['encoding_flags']['nogap_next']  = (bool) ($EncodingFlagsATHtype & 0x40);
						$ThisFileInfo['mpeg']['audio']['LAME']['encoding_flags']['nogap_prev']  = (bool) ($EncodingFlagsATHtype & 0x80);
						$ThisFileInfo['mpeg']['audio']['LAME']['ath_type']                      =         $EncodingFlagsATHtype & 0x0F;

						// byte $B0  if ABR {specified bitrate} else {minimal bitrate}
						$ABRbitrateMinBitrate = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB0, 1));
						if ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['vbr_method'] == 2) { // Average BitRate (ABR)
							$ThisFileInfo['mpeg']['audio']['LAME']['bitrate_abr'] = $ABRbitrateMinBitrate;
						} elseif ($ABRbitrateMinBitrate > 0) { // Variable BitRate (VBR) - minimum bitrate
							$ThisFileInfo['mpeg']['audio']['LAME']['bitrate_min'] = $ABRbitrateMinBitrate;
						}

						// bytes $B1-$B3  Encoder delays
						$EncoderDelays = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB1, 3));
						$ThisFileInfo['mpeg']['audio']['LAME']['encoder_delay'] = ($EncoderDelays & 0xFFF000) >> 12;
						$ThisFileInfo['mpeg']['audio']['LAME']['end_padding']   =  $EncoderDelays & 0x000FFF;

						// byte $B4  Misc
						$MiscByte = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB4, 1));
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['noise_shaping']       = ($MiscByte & 0x03);
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['stereo_mode']         = ($MiscByte & 0x1C) >> 2;
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['not_optimal_quality'] = ($MiscByte & 0x20) >> 5;
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['source_sample_freq']  = ($MiscByte & 0xC0) >> 6;
						$ThisFileInfo['mpeg']['audio']['LAME']['noise_shaping']       = $ThisFileInfo['mpeg']['audio']['LAME']['raw']['noise_shaping'];
						$ThisFileInfo['mpeg']['audio']['LAME']['stereo_mode']         = LAMEmiscStereoModeLookup($ThisFileInfo['mpeg']['audio']['LAME']['raw']['stereo_mode']);
						$ThisFileInfo['mpeg']['audio']['LAME']['not_optimal_quality'] = (bool) $ThisFileInfo['mpeg']['audio']['LAME']['raw']['not_optimal_quality'];
						$ThisFileInfo['mpeg']['audio']['LAME']['source_sample_freq']  = LAMEmiscSourceSampleFrequencyLookup($ThisFileInfo['mpeg']['audio']['LAME']['raw']['source_sample_freq']);

						// byte $B5  MP3 Gain
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['mp3_gain'] = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB5, 1), false, true);
						$ThisFileInfo['mpeg']['audio']['LAME']['mp3_gain_db']     = 1.5 * $ThisFileInfo['mpeg']['audio']['LAME']['raw']['mp3_gain'];
						$ThisFileInfo['mpeg']['audio']['LAME']['mp3_gain_factor'] = pow(2, ($ThisFileInfo['mpeg']['audio']['LAME']['mp3_gain_db'] / 6));

						// bytes $B6-$B7  Preset and surround info
						$PresetSurroundBytes = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB6, 2));
						// Reserved                                                    = ($PresetSurroundBytes & 0xC000);
						$ThisFileInfo['mpeg']['audio']['LAME']['raw']['surround_info'] = ($PresetSurroundBytes & 0x3800);
						$ThisFileInfo['mpeg']['audio']['LAME']['surround_info']        = LAMEsurroundInfoLookup($ThisFileInfo['mpeg']['audio']['LAME']['raw']['surround_info']);
						$ThisFileInfo['mpeg']['audio']['LAME']['preset_used_id']       = ($PresetSurroundBytes & 0x07FF);

						// bytes $B8-$BB  MusicLength
						$ThisFileInfo['mpeg']['audio']['LAME']['audio_bytes'] = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xB8, 4));
						$ExpectedNumberOfAudioBytes = (($ThisFileInfo['mpeg']['audio']['LAME']['audio_bytes'] > 0) ? $ThisFileInfo['mpeg']['audio']['LAME']['audio_bytes'] : $ThisFileInfo['mpeg']['audio']['VBR_bytes']);

						// bytes $BC-$BD  MusicCRC
						$ThisFileInfo['mpeg']['audio']['LAME']['music_crc']    = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xBC, 2));

						// bytes $BE-$BF  CRC-16 of Info Tag
						$ThisFileInfo['mpeg']['audio']['LAME']['lame_tag_crc'] = BigEndian2Int(substr($headerstring, $LAMEtagOffsetContant + 0xBE, 2));


						// LAME CBR
						if ($ThisFileInfo['mpeg']['audio']['LAME']['raw']['vbr_method'] == 1) {

							$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'cbr';
							if (empty($ThisFileInfo['mpeg']['audio']['bitrate']) || ($ThisFileInfo['mpeg']['audio']['LAME']['bitrate_min'] != 255)) {
								$ThisFileInfo['mpeg']['audio']['bitrate'] = $ThisFileInfo['mpeg']['audio']['LAME']['bitrate_min'];
							}

						}

					}
				}

			} else {

				// not Fraunhofer or Xing VBR methods, most likely CBR (but could be VBR with no header)
				$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'cbr';
				if ($recursivesearch) {
					$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'vbr';
					if (RecursiveFrameScanning($fd, $ThisFileInfo, $offset, $nextframetestoffset, true)) {
						$recursivesearch = false;
						$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'cbr';
					}
					if ($ThisFileInfo['mpeg']['audio']['bitrate_mode'] == 'vbr') {
						$ThisFileInfo['warning'] .= "\n".'VBR file with no VBR header. Bitrate values calculated from actual frame bitrates.';
					}
				}
			}
		}
	}

	if (($ExpectedNumberOfAudioBytes > 0) && ($ExpectedNumberOfAudioBytes != ($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset']))) {
		if (($ExpectedNumberOfAudioBytes - ($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset'])) == 1) {
			$ThisFileInfo['warning'] .= "\n".'Last byte of data truncated (this is a known bug in Meracl ID3 Tag Writer before v1.3.5)';
		} elseif ($ExpectedNumberOfAudioBytes > ($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset'])) {
			$ThisFileInfo['warning'] .= "\n".'Probable truncated file: expecting '.$ExpectedNumberOfAudioBytes.' bytes of audio data, only found '.($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset']).' (short by '.($ExpectedNumberOfAudioBytes - ($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset'])).' bytes)';
		} else {
			$ThisFileInfo['warning'] .= "\n".'Too much data in file: expecting '.$ExpectedNumberOfAudioBytes.' bytes of audio data, found '.($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset']).' ('.(($ThisFileInfo['avdataend'] - $ThisFileInfo['avdataoffset']) - $ExpectedNumberOfAudioBytes).' bytes too many)';
		}
	}

	if (($ThisFileInfo['mpeg']['audio']['bitrate'] == 'free') && empty($ThisFileInfo['audio']['bitrate'])) {
		if (($offset == $ThisFileInfo['avdataoffset']) && empty($ThisFileInfo['mpeg']['audio']['VBR_frames'])) {
			$framebytelength = FreeFormatFrameLength($fd, $offset, $ThisFileInfo, true);
			if ($framebytelength > 0) {
				$ThisFileInfo['mpeg']['audio']['framelength'] = $framebytelength;
				if ($ThisFileInfo['mpeg']['audio']['layer'] == 'I') {
					// BitRate = (((FrameLengthInBytes / 4) - Padding) * SampleRate) / 12
					$ThisFileInfo['audio']['bitrate'] = ((($framebytelength / 4) - intval($ThisFileInfo['mpeg']['audio']['padding'])) * $ThisFileInfo['mpeg']['audio']['sample_rate']) / 12;
				} else {
					// Bitrate = ((FrameLengthInBytes - Padding) * SampleRate) / 144
					$ThisFileInfo['audio']['bitrate'] = (($framebytelength - intval($ThisFileInfo['mpeg']['audio']['padding'])) * $ThisFileInfo['mpeg']['audio']['sample_rate']) / 144;
				}
			} else {
				$ThisFileInfo['error'] .= "\n".'Error calculating frame length of free-format MP3 without Xing/LAME header';
			}
		}
	}

	if (($ThisFileInfo['mpeg']['audio']['bitrate_mode'] == 'vbr') && isset($ThisFileInfo['mpeg']['audio']['VBR_frames']) && ($ThisFileInfo['mpeg']['audio']['VBR_frames'] > 1)) {
		$ThisFileInfo['mpeg']['audio']['VBR_frames']--; // don't count the Xing / VBRI frame
		if (($ThisFileInfo['mpeg']['audio']['version'] == '1') && ($ThisFileInfo['mpeg']['audio']['layer'] == 'I')) {
			$ThisFileInfo['mpeg']['audio']['VBR_bitrate'] = ((($ThisFileInfo['mpeg']['audio']['VBR_bytes'] / $ThisFileInfo['mpeg']['audio']['VBR_frames']) * 8) * ($ThisFileInfo['audio']['sample_rate'] / 384)) / 1000;
		} elseif ((($ThisFileInfo['mpeg']['audio']['version'] == '2') || ($ThisFileInfo['mpeg']['audio']['version'] == '2.5')) && ($ThisFileInfo['mpeg']['audio']['layer'] == 'III')) {
			$ThisFileInfo['mpeg']['audio']['VBR_bitrate'] = ((($ThisFileInfo['mpeg']['audio']['VBR_bytes'] / $ThisFileInfo['mpeg']['audio']['VBR_frames']) * 8) * ($ThisFileInfo['audio']['sample_rate'] / 576)) / 1000;
		} else {
			$ThisFileInfo['mpeg']['audio']['VBR_bitrate'] = ((($ThisFileInfo['mpeg']['audio']['VBR_bytes'] / $ThisFileInfo['mpeg']['audio']['VBR_frames']) * 8) * ($ThisFileInfo['audio']['sample_rate'] / 1152)) / 1000;
		}
		if ($ThisFileInfo['mpeg']['audio']['VBR_bitrate'] > 0) {
			$ThisFileInfo['audio']['bitrate']         = 1000 * $ThisFileInfo['mpeg']['audio']['VBR_bitrate'];
			$ThisFileInfo['mpeg']['audio']['bitrate'] = $ThisFileInfo['mpeg']['audio']['VBR_bitrate']; // to avoid confusion
		}
	}

	// End variable-bitrate headers
	////////////////////////////////////////////////////////////////////////////////////

	if ($recursivesearch) {

		if (!RecursiveFrameScanning($fd, $ThisFileInfo, $offset, $nextframetestoffset, $ScanAsCBR)) {
			return false;
		}

	}


	//if (false) {
	//	// experimental side info parsing section - not returning anything useful yet
    //
	//	$SideInfoBitstream = BigEndian2Bin($SideInfoData);
	//	$SideInfoOffset = 0;
    //
	//	if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
	//		if ($ThisFileInfo['mpeg']['audio']['channelmode'] == 'mono') {
	//			// MPEG-1 (mono)
	//			$ThisFileInfo['mpeg']['audio']['side_info']['main_data_begin'] = substr($SideInfoBitstream, $SideInfoOffset, 9);
	//			$SideInfoOffset += 9;
	//			$SideInfoOffset += 5;
	//		} else {
	//			// MPEG-1 (stereo, joint-stereo, dual-channel)
	//			$ThisFileInfo['mpeg']['audio']['side_info']['main_data_begin'] = substr($SideInfoBitstream, $SideInfoOffset, 9);
	//			$SideInfoOffset += 9;
	//			$SideInfoOffset += 3;
	//		}
	//	} else { // 2 or 2.5
	//		if ($ThisFileInfo['mpeg']['audio']['channelmode'] == 'mono') {
	//			// MPEG-2, MPEG-2.5 (mono)
	//			$ThisFileInfo['mpeg']['audio']['side_info']['main_data_begin'] = substr($SideInfoBitstream, $SideInfoOffset, 8);
	//			$SideInfoOffset += 8;
	//			$SideInfoOffset += 1;
	//		} else {
	//			// MPEG-2, MPEG-2.5 (stereo, joint-stereo, dual-channel)
	//			$ThisFileInfo['mpeg']['audio']['side_info']['main_data_begin'] = substr($SideInfoBitstream, $SideInfoOffset, 8);
	//			$SideInfoOffset += 8;
	//			$SideInfoOffset += 2;
	//		}
	//	}
    //
	//	if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
	//		for ($channel = 0; $channel < $ThisFileInfo['audio']['channels']; $channel++) {
	//			for ($scfsi_band = 0; $scfsi_band < 4; $scfsi_band++) {
	//				$ThisFileInfo['mpeg']['audio']['scfsi'][$channel][$scfsi_band] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//				$SideInfoOffset += 2;
	//			}
	//		}
	//	}
	//	for ($granule = 0; $granule < (($ThisFileInfo['mpeg']['audio']['version'] == '1') ? 2 : 1); $granule++) {
	//		for ($channel = 0; $channel < $ThisFileInfo['audio']['channels']; $channel++) {
	//			$ThisFileInfo['mpeg']['audio']['part2_3_length'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 12);
	//			$SideInfoOffset += 12;
	//			$ThisFileInfo['mpeg']['audio']['big_values'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 9);
	//			$SideInfoOffset += 9;
	//			$ThisFileInfo['mpeg']['audio']['global_gain'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 8);
	//			$SideInfoOffset += 8;
	//			if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
	//				$ThisFileInfo['mpeg']['audio']['scalefac_compress'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 4);
	//				$SideInfoOffset += 4;
	//			} else {
	//				$ThisFileInfo['mpeg']['audio']['scalefac_compress'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 9);
	//				$SideInfoOffset += 9;
	//			}
	//			$ThisFileInfo['mpeg']['audio']['window_switching_flag'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//			$SideInfoOffset += 1;
    //
	//			if ($ThisFileInfo['mpeg']['audio']['window_switching_flag'][$granule][$channel] == '1') {
    //
	//				$ThisFileInfo['mpeg']['audio']['block_type'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 2);
	//				$SideInfoOffset += 2;
	//				$ThisFileInfo['mpeg']['audio']['mixed_block_flag'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//				$SideInfoOffset += 1;
    //
	//				for ($region = 0; $region < 2; $region++) {
	//					$ThisFileInfo['mpeg']['audio']['table_select'][$granule][$channel][$region] = substr($SideInfoBitstream, $SideInfoOffset, 5);
	//					$SideInfoOffset += 5;
	//				}
	//				$ThisFileInfo['mpeg']['audio']['table_select'][$granule][$channel][2] = 0;
    //
	//				for ($window = 0; $window < 3; $window++) {
	//					$ThisFileInfo['mpeg']['audio']['subblock_gain'][$granule][$channel][$window] = substr($SideInfoBitstream, $SideInfoOffset, 3);
	//					$SideInfoOffset += 3;
	//				}
    //
	//			} else {
    //
	//				for ($region = 0; $region < 3; $region++) {
	//					$ThisFileInfo['mpeg']['audio']['table_select'][$granule][$channel][$region] = substr($SideInfoBitstream, $SideInfoOffset, 5);
	//					$SideInfoOffset += 5;
	//				}
    //
	//				$ThisFileInfo['mpeg']['audio']['region0_count'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 4);
	//				$SideInfoOffset += 4;
	//				$ThisFileInfo['mpeg']['audio']['region1_count'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 3);
	//				$SideInfoOffset += 3;
	//				$ThisFileInfo['mpeg']['audio']['block_type'][$granule][$channel] = 0;
	//			}
    //
	//			if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
	//				$ThisFileInfo['mpeg']['audio']['preflag'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//				$SideInfoOffset += 1;
	//			}
	//			$ThisFileInfo['mpeg']['audio']['scalefac_scale'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//			$SideInfoOffset += 1;
	//			$ThisFileInfo['mpeg']['audio']['count1table_select'][$granule][$channel] = substr($SideInfoBitstream, $SideInfoOffset, 1);
	//			$SideInfoOffset += 1;
	//		}
	//	}
	//}

	return true;
}

function RecursiveFrameScanning(&$fd, &$ThisFileInfo, &$offset, &$nextframetestoffset, $ScanAsCBR) {
	for ($i = 0; $i < MPEG_VALID_CHECK_FRAMES; $i++) {
		// check next MPEG_VALID_CHECK_FRAMES frames for validity, to make sure we haven't run across a false synch
		if (($nextframetestoffset + 4) >= $ThisFileInfo['avdataend']) {
			// end of file
			return true;
		}

		$nextframetestarray = array('error'=>'', 'warning'=>'', 'avdataend'=>$ThisFileInfo['avdataend'], 'avdataoffset'=>$ThisFileInfo['avdataoffset']);
		if (decodeMPEGaudioHeader($fd, $nextframetestoffset, $nextframetestarray, false)) {
			if ($ScanAsCBR) {
				// force CBR mode, used for trying to pick out invalid audio streams with
				// valid(?) VBR headers, or VBR streams with no VBR header
				if (!isset($nextframetestarray['mpeg']['audio']['bitrate']) || !isset($ThisFileInfo['mpeg']['audio']['bitrate']) || ($nextframetestarray['mpeg']['audio']['bitrate'] != $ThisFileInfo['mpeg']['audio']['bitrate'])) {
					return false;
				}
			}


			// next frame is OK, get ready to check the one after that
			if (isset($nextframetestarray['mpeg']['audio']['framelength']) && ($nextframetestarray['mpeg']['audio']['framelength'] > 0)) {
				$nextframetestoffset += $nextframetestarray['mpeg']['audio']['framelength'];
			} else {
				$ThisFileInfo['error'] .= "\n".'Frame at offset ('.$offset.') is has an invalid frame length.';
				return false;
			}

		} else {

			// next frame is not valid, note the error and fail, so scanning can contiue for a valid frame sequence
			$ThisFileInfo['error'] .= "\n".'Frame at offset ('.$offset.') is valid, but the next one at ('.$nextframetestoffset.') is not.';

			return false;
		}
	}
	return true;
}

function FreeFormatFrameLength($fd, $offset, &$ThisFileInfo, $deepscan=false) {
	fseek($fd, $offset, SEEK_SET);
	$MPEGaudioData = fread($fd, 32768);

	$SyncPattern1 = substr($MPEGaudioData, 0, 4);
	// may be different pattern due to padding
	$SyncPattern2 = $SyncPattern1{0}.$SyncPattern1{1}.chr(ord($SyncPattern1{2}) | 0x02).$SyncPattern1{3};
	if ($SyncPattern2 === $SyncPattern1) {
		$SyncPattern2 = $SyncPattern1{0}.$SyncPattern1{1}.chr(ord($SyncPattern1{2}) & 0xFD).$SyncPattern1{3};
	}

	$framelength = false;
	$framelength1 = strpos($MPEGaudioData, $SyncPattern1, 4);
	$framelength2 = strpos($MPEGaudioData, $SyncPattern2, 4);
	if ($framelength1 > 4) {
		$framelength = $framelength1;
	}
	if (($framelength2 > 4) && ($framelength2 < $framelength1)) {
		$framelength = $framelength2;
	}
	if (!$framelength) {

		// LAME 3.88 has a different value for modeextension on the first frame vs the rest
		$framelength1 = strpos($MPEGaudioData, substr($SyncPattern1, 0, 3), 4);
		$framelength2 = strpos($MPEGaudioData, substr($SyncPattern2, 0, 3), 4);

		if ($framelength1 > 4) {
			$framelength = $framelength1;
		}
		if (($framelength2 > 4) && ($framelength2 < $framelength1)) {
			$framelength = $framelength2;
		}
		if (!$framelength) {
			$ThisFileInfo['error'] .= "\n".'Cannot find next free-format synch pattern ('.PrintHexBytes($SyncPattern1).' or '.PrintHexBytes($SyncPattern2).') after offset '.$offset;
			return false;
		} else {
			$ThisFileInfo['warning'] .= "\n".'ModeExtension varies between first frame and other frames (known free-format issue in LAME 3.88)';
			$ThisFileInfo['audio']['codec']   = 'LAME';
			$ThisFileInfo['audio']['encoder'] = 'LAME3.88';
			$SyncPattern1 = substr($SyncPattern1, 0, 3);
			$SyncPattern2 = substr($SyncPattern2, 0, 3);
		}
	}

	if ($deepscan) {

		$ActualFrameLengthValues = array();
		$nextoffset = $offset + $framelength;
		while ($nextoffset < ($ThisFileInfo['avdataend'] - 6)) {
			fseek($fd, $nextoffset - 1, SEEK_SET);
			$NextSyncPattern = fread($fd, 6);
			if ((substr($NextSyncPattern, 1, strlen($SyncPattern1)) == $SyncPattern1) || (substr($NextSyncPattern, 1, strlen($SyncPattern2)) == $SyncPattern2)) {
				// good - found where expected
				$ActualFrameLengthValues[] = $framelength;
			} elseif ((substr($NextSyncPattern, 0, strlen($SyncPattern1)) == $SyncPattern1) || (substr($NextSyncPattern, 0, strlen($SyncPattern2)) == $SyncPattern2)) {
				// ok - found one byte earlier than expected (last frame wasn't padded, first frame was)
				$ActualFrameLengthValues[] = ($framelength - 1);
				$nextoffset--;
			} elseif ((substr($NextSyncPattern, 2, strlen($SyncPattern1)) == $SyncPattern1) || (substr($NextSyncPattern, 2, strlen($SyncPattern2)) == $SyncPattern2)) {
				// ok - found one byte later than expected (last frame was padded, first frame wasn't)
				$ActualFrameLengthValues[] = ($framelength + 1);
				$nextoffset++;
			} else {
				$ThisFileInfo['error'] .= "\n".'Did not find expected free-format sync pattern at offset '.$nextoffset;
				return false;
			}
			$nextoffset += $framelength;
		}
		if (count($ActualFrameLengthValues) > 0) {
			$framelength = round(array_sum($ActualFrameLengthValues) / count($ActualFrameLengthValues));
		}
	}
	return $framelength;
}


function getOnlyMPEGaudioInfo($fd, &$ThisFileInfo, $avdataoffset, $BitrateHistogram=false) {
	// looks for synch, decodes MPEG audio header

	fseek($fd, $avdataoffset, SEEK_SET);
	$header = '';
	$SynchSeekOffset = 0;

	if (!defined('CONST_FF')) {
		define('CONST_FF', chr(0xFF));
		define('CONST_E0', chr(0xE0));
	}

	static $MPEGaudioVersionLookup;
	static $MPEGaudioLayerLookup;
	static $MPEGaudioBitrateLookup;
	if (empty($MPEGaudioVersionLookup)) {
		$MPEGaudioVersionLookup = MPEGaudioVersionArray();
		$MPEGaudioLayerLookup   = MPEGaudioLayerArray();
		$MPEGaudioBitrateLookup = MPEGaudioBitrateArray();
	}
	static $MPEGaudioHeaderValidCache = array();

	$header_len = strlen($header) - round(FREAD_BUFFER_SIZE / 2);
	while (true) {

		if (($SynchSeekOffset > $header_len) && (($avdataoffset + $SynchSeekOffset)  < $ThisFileInfo['avdataend']) && !feof($fd)) {

			if ($SynchSeekOffset > 131072) {
				// if a synch's not found within the first 128k bytes, then give up
				$ThisFileInfo['error'] .= "\n".'could not find valid MPEG synch within the first 131072 bytes';
				if (isset($ThisFileInfo['audio']['bitrate'])) {
					unset($ThisFileInfo['audio']['bitrate']);
				}
				if (isset($ThisFileInfo['mpeg']['audio'])) {
					unset($ThisFileInfo['mpeg']['audio']);
				}
				if (isset($ThisFileInfo['mpeg']) && (!is_array($ThisFileInfo['mpeg']) || (count($ThisFileInfo['mpeg']) == 0))) {
					unset($ThisFileInfo['mpeg']);
				}
				return false;

			} elseif ($header .= fread($fd, FREAD_BUFFER_SIZE)) {

				// great
				$header_len = strlen($header) - round(FREAD_BUFFER_SIZE / 2);

			} else {

				$ThisFileInfo['error'] .= "\n".'could not find valid MPEG synch before end of file';
				if (isset($ThisFileInfo['audio']['bitrate'])) {
					unset($ThisFileInfo['audio']['bitrate']);
				}
				if (isset($ThisFileInfo['mpeg']['audio'])) {
					unset($ThisFileInfo['mpeg']['audio']);
				}
				if (isset($ThisFileInfo['mpeg']) && (!is_array($ThisFileInfo['mpeg']) || (count($ThisFileInfo['mpeg']) == 0))) {
					unset($ThisFileInfo['mpeg']);
				}
				return false;

			}
		}

		if (($SynchSeekOffset + 1) >= strlen($header)) {
			$ThisFileInfo['error'] .= "\n".'could not find valid MPEG synch before end of file';
			return false;
		}

		if ($header{$SynchSeekOffset} == CONST_FF) {
			if ($header{($SynchSeekOffset + 1)} > CONST_E0) {
				$head4 = substr($header, $SynchSeekOffset, 4);
				if (!isset($MPEGaudioHeaderValidCache[$head4])) {
					$MPEGaudioHeaderValidCache[$head4] = MPEGaudioHeaderBytesValid($head4);
				}
				if ($MPEGaudioHeaderValidCache[$head4]) { // synch detected
					if (!isset($FirstFrameThisfileInfo) && !isset($ThisFileInfo['mpeg']['audio'])) {
						$FirstFrameThisfileInfo = $ThisFileInfo;
						$FirstFrameAVDataOffset = $avdataoffset + $SynchSeekOffset;
						if (!decodeMPEGaudioHeader($fd, $avdataoffset + $SynchSeekOffset, $FirstFrameThisfileInfo, false)) {
							// if this is the first valid MPEG-audio frame, save it in case it's a VBR header frame and there's
							// garbage between this frame and a valid sequence of MPEG-audio frames, to be restored below
							unset($FirstFrameThisfileInfo);
						}
					}
					$dummy = $ThisFileInfo; // only overwrite real data if valid header found

					if (decodeMPEGaudioHeader($fd, $avdataoffset + $SynchSeekOffset, $dummy, true)) {

						$ThisFileInfo = $dummy;
						$ThisFileInfo['avdataoffset'] = $avdataoffset + $SynchSeekOffset;
						switch ($ThisFileInfo['fileformat']) {
							case '':
							case 'id3':
							case 'ape':
							case 'mp3':
								$ThisFileInfo['fileformat']          = 'mp3';
								$ThisFileInfo['audio']['dataformat'] = 'mp3';
						}
						if (isset($FirstFrameThisfileInfo['mpeg']['audio']['bitrate_mode']) && ($FirstFrameThisfileInfo['mpeg']['audio']['bitrate_mode'] == 'vbr')) {
							if (!CloseMatch($ThisFileInfo['audio']['bitrate'], $FirstFrameThisfileInfo['audio']['bitrate'], 1)) {
								// If there is garbage data between a valid VBR header frame and a sequence
								// of valid MPEG-audio frames the VBR data is no longer discarded.
								$ThisFileInfo = $FirstFrameThisfileInfo;
								$ThisFileInfo['avdataoffset']        = $FirstFrameAVDataOffset;
								$ThisFileInfo['fileformat']          = 'mp3';
								$ThisFileInfo['audio']['dataformat'] = 'mp3';
								$dummy                               = $ThisFileInfo;
								unset($dummy['mpeg']['audio']);
								$GarbageOffsetStart = $FirstFrameAVDataOffset + $FirstFrameThisfileInfo['mpeg']['audio']['framelength'];
								$GarbageOffsetEnd   = $avdataoffset + $SynchSeekOffset;
								if (decodeMPEGaudioHeader($fd, $GarbageOffsetEnd, $dummy, true, true)) {

									$ThisFileInfo = $dummy;
									$ThisFileInfo['avdataoffset'] = $GarbageOffsetEnd;
									$ThisFileInfo['warning'] .= "\n".'apparently-valid VBR header not used because could not find '.MPEG_VALID_CHECK_FRAMES.' consecutive MPEG-audio frames immediately after VBR header (garbage data for '.($GarbageOffsetEnd - $GarbageOffsetStart).' bytes between '.$GarbageOffsetStart.' and '.$GarbageOffsetEnd.'), but did find valid CBR stream starting at '.$GarbageOffsetEnd;

								} else {

									$ThisFileInfo['warning'] .= "\n".'using data from VBR header even though could not find '.MPEG_VALID_CHECK_FRAMES.' consecutive MPEG-audio frames immediately after VBR header (garbage data for '.($GarbageOffsetEnd - $GarbageOffsetStart).' bytes between '.$GarbageOffsetStart.' and '.$GarbageOffsetEnd.')';

								}
							}
						}

						if (isset($ThisFileInfo['mpeg']['audio']['bitrate_mode']) && ($ThisFileInfo['mpeg']['audio']['bitrate_mode'] == 'vbr') && !isset($ThisFileInfo['mpeg']['audio']['VBR_method'])) {
							// VBR file with no VBR header
							$BitrateHistogram = true;
						}
						if ($BitrateHistogram) {

							$ThisFileInfo['mpeg']['audio']['stereo_distribution']  = array('stereo'=>0, 'joint stereo'=>0, 'dual channel'=>0, 'mono'=>0);
							$ThisFileInfo['mpeg']['audio']['version_distribution'] = array('1'=>0, '2'=>0, '2.5'=>0);

							if ($ThisFileInfo['mpeg']['audio']['version'] == '1') {
								if ($ThisFileInfo['mpeg']['audio']['layer'] == 'III') {
									$ThisFileInfo['mpeg']['audio']['bitrate_distribution'] = array('free'=>0, 32=>0, 40=>0, 48=>0, 56=>0, 64=>0, 80=>0, 96=>0, 112=>0, 128=>0, 160=>0, 192=>0, 224=>0, 256=>0, 320=>0);
								} elseif ($ThisFileInfo['mpeg']['audio']['layer'] == 'II') {
									$ThisFileInfo['mpeg']['audio']['bitrate_distribution'] = array('free'=>0, 32=>0, 48=>0, 56=>0, 64=>0, 80=>0, 96=>0, 112=>0, 128=>0, 160=>0, 192=>0, 224=>0, 256=>0, 320=>0, 384=>0);
								} elseif ($ThisFileInfo['mpeg']['audio']['layer'] == 'I') {
									$ThisFileInfo['mpeg']['audio']['bitrate_distribution'] = array('free'=>0, 32=>0, 64=>0, 96=>0, 128=>0, 160=>0, 192=>0, 224=>0, 256=>0, 288=>0, 320=>0, 352=>0, 384=>0, 416=>0, 448=>0);
								}
							} elseif ($ThisFileInfo['mpeg']['audio']['layer'] == 'I') {
								$ThisFileInfo['mpeg']['audio']['bitrate_distribution'] = array('free'=>0, 32=>0, 48=>0, 56=>0, 64=>0, 80=>0, 96=>0, 112=>0, 128=>0, 144=>0, 160=>0, 176=>0, 192=>0, 224=>0, 256=>0);
							} else {
								$ThisFileInfo['mpeg']['audio']['bitrate_distribution'] = array('free'=>0, 8=>0, 16=>0, 24=>0, 32=>0, 40=>0, 48=>0, 56=>0, 64=>0, 80=>0, 96=>0, 112=>0, 128=>0, 144=>0, 160=>0);
							}

							$dummy = array('error'=>$ThisFileInfo['error'], 'warning'=>$ThisFileInfo['warning'], 'avdataend'=>$ThisFileInfo['avdataend'], 'avdataoffset'=>$ThisFileInfo['avdataoffset']);
							$synchstartoffset  = $ThisFileInfo['avdataoffset'];

							$FastMode = false;
							while ($synchstartoffset < ($ThisFileInfo['avdataend'] - 12)) {
								if (decodeMPEGaudioHeader($fd, $synchstartoffset, $dummy, false, false, $FastMode, true)) {
									$thisframebitrate = $MPEGaudioBitrateLookup[$MPEGaudioVersionLookup[$dummy['mpeg']['audio']['raw']['version']]][$MPEGaudioLayerLookup[$dummy['mpeg']['audio']['raw']['layer']]][$dummy['mpeg']['audio']['raw']['bitrate']];

									safe_inc($ThisFileInfo['mpeg']['audio']['bitrate_distribution'][$thisframebitrate]);
									$ThisFileInfo['mpeg']['audio']['stereo_distribution'][$dummy['mpeg']['audio']['channelmode']]++;
									$ThisFileInfo['mpeg']['audio']['version_distribution'][$dummy['mpeg']['audio']['version']]++;
									if (empty($dummy['mpeg']['audio']['framelength'])) {
										$ThisFileInfo['warning'] .= "\n".'Invalid/missing framelength at '.$synchstartoffset.' in histogram analysis - aborting';
										$synchstartoffset++;
										$ThisFileInfo['avdataoffset'] = $synchstartoffset;
										// return false;
									} else {
										$synchstartoffset += $dummy['mpeg']['audio']['framelength'];
									}
								} else {
									//$ThisFileInfo['warning'] .= "\n".'Invalid frame header at '.$synchstartoffset;
									$synchstartoffset++;
									$ThisFileInfo['avdataoffset'] = $synchstartoffset;
								}
								$FastMode = true;
							}
							$bittotal     = 0;
							$framecounter = 0;
							foreach ($ThisFileInfo['mpeg']['audio']['bitrate_distribution'] as $bitratevalue => $bitratecount) {
								$framecounter += $bitratecount;
								if ($bitratevalue != 'free') {
									$bittotal += ($bitratevalue * $bitratecount);
								}
							}
							if ($framecounter == 0) {
								$ThisFileInfo['error'] .= "\n".'Corrupt MP3 file: framecounter == zero';
								return false;
							}
							$ThisFileInfo['mpeg']['audio']['frame_count'] = $framecounter;
							$ThisFileInfo['mpeg']['audio']['bitrate']     = 1000 * ($bittotal / $framecounter);

							$ThisFileInfo['audio']['bitrate'] = $ThisFileInfo['mpeg']['audio']['bitrate'];


							// Definitively set VBR vs CBR, even if the Xing/LAME/VBRI header says differently
							$distinct_bitrates = 0;
							foreach ($ThisFileInfo['mpeg']['audio']['bitrate_distribution'] as $bitrate_value => $bitrate_count) {
								if ($bitrate_count > 0) {
									$distinct_bitrates++;
								}
							}
							if ($distinct_bitrates > 1) {
								$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'vbr';
							} else {
								$ThisFileInfo['mpeg']['audio']['bitrate_mode'] = 'cbr';
							}
							$ThisFileInfo['audio']['bitrate_mode'] = $ThisFileInfo['mpeg']['audio']['bitrate_mode'];

						}

						break; // exit while()
					}
				}
			}
		}

		$SynchSeekOffset++;
		if (($avdataoffset + $SynchSeekOffset) >= $ThisFileInfo['avdataend']) {
			// end of file/data

			if (empty($ThisFileInfo['mpeg']['audio'])) {

				$ThisFileInfo['error'] .= "\n".'could not find valid MPEG synch before end of file';
				if (isset($ThisFileInfo['audio']['bitrate'])) {
					unset($ThisFileInfo['audio']['bitrate']);
				}
				if (isset($ThisFileInfo['mpeg']['audio'])) {
					unset($ThisFileInfo['mpeg']['audio']);
				}
				if (isset($ThisFileInfo['mpeg']) && (!is_array($ThisFileInfo['mpeg']) || empty($ThisFileInfo['mpeg']))) {
					unset($ThisFileInfo['mpeg']);
				}
				return false;

			}
			break;
		}

	}
	$ThisFileInfo['audio']['bits_per_sample'] = 16;
	$ThisFileInfo['audio']['channels']        = $ThisFileInfo['mpeg']['audio']['channels'];
	$ThisFileInfo['audio']['channelmode']     = $ThisFileInfo['mpeg']['audio']['channelmode'];
	$ThisFileInfo['audio']['sample_rate']     = $ThisFileInfo['mpeg']['audio']['sample_rate'];
	return true;
}


function MPEGaudioVersionArray() {
	static $MPEGaudioVersion = array('2.5', false, '2', '1');
	return $MPEGaudioVersion;
}

function MPEGaudioLayerArray() {
	static $MPEGaudioLayer = array(false, 'III', 'II', 'I');
	return $MPEGaudioLayer;
}

function MPEGaudioBitrateArray() {
	static $MPEGaudioBitrate;
	if (empty($MPEGaudioBitrate)) {
		$MPEGaudioBitrate['1']['I']     = array('free', 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448);
		$MPEGaudioBitrate['1']['II']    = array('free', 32, 48, 56,  64,  80,  96, 112, 128, 160, 192, 224, 256, 320, 384);
		$MPEGaudioBitrate['1']['III']   = array('free', 32, 40, 48,  56,  64,  80,  96, 112, 128, 160, 192, 224, 256, 320);
		$MPEGaudioBitrate['2']['I']     = array('free', 32, 48, 56,  64,  80,  96, 112, 128, 144, 160, 176, 192, 224, 256);
		$MPEGaudioBitrate['2']['II']    = array('free',  8, 16, 24,  32,  40,  48,  56,  64,  80,  96, 112, 128, 144, 160);
		$MPEGaudioBitrate['2']['III']   = $MPEGaudioBitrate['2']['II'];
		$MPEGaudioBitrate['2.5']['I']   = $MPEGaudioBitrate['2']['I'];
		$MPEGaudioBitrate['2.5']['II']  = $MPEGaudioBitrate['2']['II'];
		$MPEGaudioBitrate['2.5']['III'] = $MPEGaudioBitrate['2']['III'];
	}
	return $MPEGaudioBitrate;
}

function MPEGaudioFrequencyArray() {
	static $MPEGaudioFrequency;
	if (empty($MPEGaudioFrequency)) {
		$MPEGaudioFrequency['1']   = array(44100, 48000, 32000);
		$MPEGaudioFrequency['2']   = array(22050, 24000, 16000);
		$MPEGaudioFrequency['2.5'] = array(11025, 12000,  8000);
	}
	return $MPEGaudioFrequency;
}

function MPEGaudioChannelModeArray() {
	static $MPEGaudioChannelMode = array('stereo', 'joint stereo', 'dual channel', 'mono');
	return $MPEGaudioChannelMode;
}

function MPEGaudioModeExtensionArray() {
	static $MPEGaudioModeExtension;
	if (empty($MPEGaudioModeExtension)) {
		$MPEGaudioModeExtension['I']   = array('4-31', '8-31', '12-31', '16-31');
		$MPEGaudioModeExtension['II']  = array('4-31', '8-31', '12-31', '16-31');
		$MPEGaudioModeExtension['III'] = array('', 'IS', 'MS', 'IS+MS');
	}
	return $MPEGaudioModeExtension;
}

function MPEGaudioEmphasisArray() {
	static $MPEGaudioEmphasis = array('none', '50/15ms', false, 'CCIT J.17');
	return $MPEGaudioEmphasis;
}


function MPEGaudioHeaderBytesValid($head4) {
	return MPEGaudioHeaderValid(MPEGaudioHeaderDecode($head4));
}

function MPEGaudioHeaderValid($rawarray, $echoerrors=false) {
	if (($rawarray['synch'] & 0x0FFE) != 0x0FFE) {
		return false;
	}

	static $MPEGaudioVersionLookup;
	static $MPEGaudioLayerLookup;
	static $MPEGaudioBitrateLookup;
	static $MPEGaudioFrequencyLookup;
	static $MPEGaudioChannelModeLookup;
	static $MPEGaudioModeExtensionLookup;
	static $MPEGaudioEmphasisLookup;
	if (empty($MPEGaudioVersionLookup)) {
		$MPEGaudioVersionLookup       = MPEGaudioVersionArray();
		$MPEGaudioLayerLookup         = MPEGaudioLayerArray();
		$MPEGaudioBitrateLookup       = MPEGaudioBitrateArray();
		$MPEGaudioFrequencyLookup     = MPEGaudioFrequencyArray();
		$MPEGaudioChannelModeLookup   = MPEGaudioChannelModeArray();
		$MPEGaudioModeExtensionLookup = MPEGaudioModeExtensionArray();
		$MPEGaudioEmphasisLookup      = MPEGaudioEmphasisArray();
	}

	if (isset($MPEGaudioVersionLookup[$rawarray['version']])) {
		$decodedVersion = $MPEGaudioVersionLookup[$rawarray['version']];
	} else {
		if ($echoerrors) {
			echo "\n".'invalid Version ('.$rawarray['version'].')';
		}
		return false;
	}
	if (isset($MPEGaudioLayerLookup[$rawarray['layer']])) {
		$decodedLayer = $MPEGaudioLayerLookup[$rawarray['layer']];
	} else {
		if ($echoerrors) {
			echo "\n".'invalid Layer ('.$rawarray['layer'].')';
		}
		return false;
	}
	if (!isset($MPEGaudioBitrateLookup[$decodedVersion][$decodedLayer][$rawarray['bitrate']])) {
		if ($echoerrors) {
			echo "\n".'invalid Bitrate ('.$rawarray['bitrate'].')';
		}
		if ($rawarray['bitrate'] == 15) {
			// known issue in LAME 3.90 - 3.93.1 where free-format has bitrate ID of 15 instead of 0
			// let it go through here otherwise file will not be identified
		} else {
			return false;
		}
	}
	if (!isset($MPEGaudioFrequencyLookup[$decodedVersion][$rawarray['sample_rate']])) {
		if ($echoerrors) {
			echo "\n".'invalid Frequency ('.$rawarray['sample_rate'].')';
		}
		return false;
	}
	if (!isset($MPEGaudioChannelModeLookup[$rawarray['channelmode']])) {
		if ($echoerrors) {
			echo "\n".'invalid ChannelMode ('.$rawarray['channelmode'].')';
		}
		return false;
	}
	if (!isset($MPEGaudioModeExtensionLookup[$decodedLayer][$rawarray['modeextension']])) {
		if ($echoerrors) {
			echo "\n".'invalid Mode Extension ('.$rawarray['modeextension'].')';
		}
		return false;
	}
	if (!isset($MPEGaudioEmphasisLookup[$rawarray['emphasis']])) {
		if ($echoerrors) {
			echo "\n".'invalid Emphasis ('.$rawarray['emphasis'].')';
		}
		return false;
	}
	// These are just either set or not set, you can't mess that up :)
	// $rawarray['protection'];
	// $rawarray['padding'];
	// $rawarray['private'];
	// $rawarray['copyright'];
	// $rawarray['original'];

	return true;
}

function MPEGaudioHeaderDecode($Header4Bytes) {
	// AAAA AAAA  AAAB BCCD  EEEE FFGH  IIJJ KLMM
	// A - Frame sync (all bits set)
	// B - MPEG Audio version ID
	// C - Layer description
	// D - Protection bit
	// E - Bitrate index
	// F - Sampling rate frequency index
	// G - Padding bit
	// H - Private bit
	// I - Channel Mode
	// J - Mode extension (Only if Joint stereo)
	// K - Copyright
	// L - Original
	// M - Emphasis

	if (strlen($Header4Bytes) != 4) {
		return false;
	}

	$MPEGrawHeader['synch']         = (BigEndian2Int(substr($Header4Bytes, 0, 2)) & 0xFFE0) >> 4;
	$MPEGrawHeader['version']       = (ord($Header4Bytes{1}) & 0x18) >> 3; //    BB
	$MPEGrawHeader['layer']         = (ord($Header4Bytes{1}) & 0x06) >> 1; //      CC
	$MPEGrawHeader['protection']    = (ord($Header4Bytes{1}) & 0x01);      //        D
	$MPEGrawHeader['bitrate']       = (ord($Header4Bytes{2}) & 0xF0) >> 4; // EEEE
	$MPEGrawHeader['sample_rate']   = (ord($Header4Bytes{2}) & 0x0C) >> 2; //     FF
	$MPEGrawHeader['padding']       = (ord($Header4Bytes{2}) & 0x02) >> 1; //       G
	$MPEGrawHeader['private']       = (ord($Header4Bytes{2}) & 0x01);      //        H
	$MPEGrawHeader['channelmode']   = (ord($Header4Bytes{3}) & 0xC0) >> 6; // II
	$MPEGrawHeader['modeextension'] = (ord($Header4Bytes{3}) & 0x30) >> 4; //   JJ
	$MPEGrawHeader['copyright']     = (ord($Header4Bytes{3}) & 0x08) >> 3; //     K
	$MPEGrawHeader['original']      = (ord($Header4Bytes{3}) & 0x04) >> 2; //      L
	$MPEGrawHeader['emphasis']      = (ord($Header4Bytes{3}) & 0x03);      //       MM

	return $MPEGrawHeader;
}

function MPEGaudioFrameLength(&$bitrate, &$version, &$layer, $padding, &$samplerate) {
	static $AudioFrameLengthCache = array();

	if (!isset($AudioFrameLengthCache[$bitrate][$version][$layer][$padding][$samplerate])) {
		$AudioFrameLengthCache[$bitrate][$version][$layer][$padding][$samplerate] = false;
		if ($bitrate != 'free') {

			if ($version == '1') {

				if ($layer == 'I') {

					// For Layer I slot is 32 bits long
					$FrameLengthCoefficient = 48;
					$FrameLengthPadding     = ($padding ? 4 : 0);

				} else { // Layer II / III

					// for Layer II and Layer III slot is 8 bits long.
					$FrameLengthCoefficient = 144;
					$FrameLengthPadding     = ($padding ? 1 : 0);

				}

			} else { // MPEG-2 / MPEG-2.5

				if ($layer == 'I') {

					// For Layer I slot is 32 bits long
					$FrameLengthCoefficient = 24;
					$FrameLengthPadding     = ($padding ? 4 : 0);

				} else { // Layer II / III

					// for Layer II and Layer III slot is 8 bits long.
					$FrameLengthCoefficient = 72;
					$FrameLengthPadding     = ($padding ? 1 : 0);

				}

			}

			// FrameLengthInBytes = ((Coefficient * BitRate) / SampleRate) + Padding
			// http://66.96.216.160/cgi-bin/YaBB.pl?board=c&action=display&num=1018474068
			// -> [Finding the next frame synch] on www.r3mix.net forums if the above link goes dead
			if ($samplerate > 0) {
				$AudioFrameLengthCache[$bitrate][$version][$layer][$padding][$samplerate] = (int) floor(($FrameLengthCoefficient * 1000 * $bitrate) / $samplerate) + $FrameLengthPadding;
			}
		}
	}
	return $AudioFrameLengthCache[$bitrate][$version][$layer][$padding][$samplerate];
}

function LAMEvbrMethodLookup($VBRmethodID) {
	static $LAMEvbrMethodLookup = array();
	if (empty($LAMEvbrMethodLookup)) {
		$LAMEvbrMethodLookup[0x00] = 'unknown';
		$LAMEvbrMethodLookup[0x01] = 'cbr';
		$LAMEvbrMethodLookup[0x02] = 'abr';
		$LAMEvbrMethodLookup[0x03] = 'vbr-old / vbr-rh';
		$LAMEvbrMethodLookup[0x04] = 'vbr-mtrh';
		$LAMEvbrMethodLookup[0x05] = 'vbr-new / vbr-mt';
	}
	return (isset($LAMEvbrMethodLookup[$VBRmethodID]) ? $LAMEvbrMethodLookup[$VBRmethodID] : '');
}

function LAMEmiscStereoModeLookup($StereoModeID) {
	static $LAMEmiscStereoModeLookup = array();
	if (empty($LAMEmiscStereoModeLookup)) {
		$LAMEmiscStereoModeLookup[0] = 'mono';
		$LAMEmiscStereoModeLookup[1] = 'stereo';
		$LAMEmiscStereoModeLookup[2] = 'dual';
		$LAMEmiscStereoModeLookup[3] = 'joint';
		$LAMEmiscStereoModeLookup[4] = 'forced';
		$LAMEmiscStereoModeLookup[5] = 'auto';
		$LAMEmiscStereoModeLookup[6] = 'intensity';
		$LAMEmiscStereoModeLookup[7] = 'other';
	}
	return (isset($LAMEmiscStereoModeLookup[$StereoModeID]) ? $LAMEmiscStereoModeLookup[$StereoModeID] : '');
}

function LAMEmiscSourceSampleFrequencyLookup($SourceSampleFrequencyID) {
	static $LAMEmiscSourceSampleFrequencyLookup = array();
	if (empty($LAMEmiscSourceSampleFrequencyLookup)) {
		$LAMEmiscSourceSampleFrequencyLookup[0] = '<= 32 kHz';
		$LAMEmiscSourceSampleFrequencyLookup[1] = '44.1 kHz';
		$LAMEmiscSourceSampleFrequencyLookup[2] = '48 kHz';
		$LAMEmiscSourceSampleFrequencyLookup[3] = '> 48kHz';
	}
	return (isset($LAMEmiscSourceSampleFrequencyLookup[$SourceSampleFrequencyID]) ? $LAMEmiscSourceSampleFrequencyLookup[$SourceSampleFrequencyID] : '');
}

function LAMEsurroundInfoLookup($SurroundInfoID) {
	static $LAMEsurroundInfoLookup = array();
	if (empty($LAMEsurroundInfoLookup)) {
		$LAMEsurroundInfoLookup[0] = 'no surround info';
		$LAMEsurroundInfoLookup[1] = 'DPL encoding';
		$LAMEsurroundInfoLookup[2] = 'DPL2 encoding';
		$LAMEsurroundInfoLookup[3] = 'Ambisonic encoding';
	}
	return (isset($LAMEsurroundInfoLookup[$SurroundInfoID]) ? $LAMEsurroundInfoLookup[$SurroundInfoID] : 'reserved');
}

?>