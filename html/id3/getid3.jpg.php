<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                ///
//            or http://www.getid3.org                        ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.jpg.php - part of getID3()                           //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

function getJPGHeaderFilepointer(&$fd, &$ThisFileInfo) {
	$ThisFileInfo['fileformat']                  = 'jpg';
	$ThisFileInfo['video']['dataformat']         = 'jpg';
	$ThisFileInfo['video']['lossless']           = false;
	$ThisFileInfo['video']['bits_per_sample']    = 24;
	$ThisFileInfo['video']['pixel_aspect_ratio'] = (float) 1;

	fseek($fd, $ThisFileInfo['avdataoffset'], SEEK_SET);
	require_once(GETID3_INCLUDEPATH.'getid3.getimagesize.php');

	list($width, $height, $type) = GetDataImageSize(fread($fd, $ThisFileInfo['filesize']));
	if ($type == 2) {

		$ThisFileInfo['video']['resolution_x'] = $width;
		$ThisFileInfo['video']['resolution_y'] = $height;

		if (version_compare(phpversion(), '4.2.0', '>=')) {

			if (function_exists('exif_read_data')) {

				ob_start();
				$ThisFileInfo['jpg']['exif'] = exif_read_data($ThisFileInfo['filenamepath'], '', true, false);
				$errors = ob_get_contents();
				if ($errors) {
					$ThisFileInfo['error'] .= "\n".strip_tags($errors);
					unset($ThisFileInfo['jpg']['exif']);
				}
				ob_end_clean();

			} else {

				$ThisFileInfo['warning'] .= "\n".'EXIF parsing only available when compiled with --enable-exif (or php_exif.dll enabled for Windows)';

			}

		} else {

			$ThisFileInfo['warning'] .= "\n".'EXIF parsing only available in PHP v4.2.0 and higher (you are using PHP v'.phpversion().') compiled with --enable-exif (or php_exif.dll enabled for Windows)';

		}

		return true;

	}

	unset($ThisFileInfo['fileformat']);
	return false;
}

?>