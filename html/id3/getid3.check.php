<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <getid3@users.sourceforge.net>  //
//        available at http://getid3.sourceforge.net          ///
/////////////////////////////////////////////////////////////////
//                                                             //
// getid3.zip.php - part of getID3()                           //
// Sample script for checking remote and local files and       //
// displaying information returned by getID3()                 //
// See getid3.readme.txt for more details                      //
//                                                             //
/////////////////////////////////////////////////////////////////

include_once('getid3.php');
include_once(GETID3_INCLUDEPATH.'getid3.functions.php'); // Function library

echo '<HTML><HEAD><TITLE>getID3() - getid3.check.php (sample script)</TITLE></HEAD><BODY>';

if (isset($_REQUEST['deletefile'])) {
	if (file_exists($_REQUEST['deletefile'])) {
		if (unlink($_REQUEST['deletefile'])) {
			echo '<SCRIPT LANGUAGE="JavaScript">alert("Successfully deleted '.addslashes($_REQUEST['deletefile']).'");</SCRIPT>';
		} else {
			echo '<SCRIPT LANGUAGE="JavaScript">alert("FAILED to delete '.addslashes($_REQUEST['deletefile']).'");</SCRIPT>';
		}
	} else {
		echo '<SCRIPT LANGUAGE="JavaScript">alert("'.addslashes($_REQUEST['deletefile']).' does not exist - cannot delete");</SCRIPT>';
	}
}

if (isset($_REQUEST['filename'])) {
	$starttime = getmicrotime();
	if (isset($_REQUEST['assumeFormat'])) {
		$MP3fileInfo = GetAllMP3info($_REQUEST['filename'], $_REQUEST['assumeFormat']);
	} else {
		$MP3fileInfo = GetAllMP3info($_REQUEST['filename'], '');
		if (!isset($MP3fileInfo['fileformat']) || ($MP3fileInfo['fileformat'] == '')) {
			$formatExtensions = array('mp3'=>'mp3', 'ogg'=>'ogg', 'zip'=>'zip', 'wav'=>'riff', 'avi'=>'riff', 'mid'=>'midi', 'mpg'=>'mpeg', 'jpg'=>'image', 'gif'=>'image', 'png'=>'image');
			if (isset($formatExtensions[fileextension($_REQUEST['filename'])])) {
				$MP3fileInfo = GetAllMP3info($_REQUEST['filename'], $formatExtensions[fileextension($_REQUEST['filename'])]);
			}
		}
	}

	$listdirectory = dirname($_REQUEST['filename']);
	if (!is_dir($listdirectory)) {
		// Directory names with single quotes or double quotes in them will likely come out addslashes()'d
		// so this will replace \' with ' (can't use stripslashes(), that would get rid of all slashes!)
		$listdirectory = str_replace(chr(92).chr(92), chr(92), $listdirectory); // \\ -> \
		$listdirectory = str_replace(chr(92).chr(39), chr(39), $listdirectory); // \' -> '
		$listdirectory = str_replace(chr(92).chr(34), chr(34), $listdirectory); // \" -> "
	}
	$listdirectory = realpath($listdirectory); // get rid of /../../ references
	if (is_dir(str_replace('\\', '/', $listdirectory))) {
		// this mostly just gives a consistant look to Windows and *nix filesystems
		// (windows uses \ as directory seperator, *nix uses /)
		$listdirectory = str_replace('\\', '/', $listdirectory.'/');
	}
	if (strstr($_REQUEST['filename'], 'http://') || strstr($_REQUEST['filename'], 'ftp://')) {
		echo '<I>Cannot browse remote filesystems</I><BR>';
	} else {
		echo 'Browse: <A HREF="'.$_SERVER['PHP_SELF'].'?listdirectory='.urlencode($listdirectory).'">'.$listdirectory.'</A><BR>';
	}

	echo 'Parse this file as: ';
	$allowedFormats = array('zip', 'ogg', 'riff', 'mpeg', 'midi', 'aac', 'mp3');
	foreach ($allowedFormats as $possibleFormat) {
		if (isset($_REQUEST['assumeFormat']) && ($_REQUEST['assumeFormat'] == $possibleFormat)) {
			echo '<B>'.$possibleFormat.'</B> | ';
		} else {
			echo '<A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($_REQUEST['filename']).'&assumeFormat='.$possibleFormat.'">'.$possibleFormat.'</A> | ';
		}
	}
	if (isset($_REQUEST['assumeFormat'])) {
		echo '<A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($_REQUEST['filename']).'">default</A><BR>';
	} else {
		echo '<B>default</B><BR>';
	}

	echo table_var_dump($MP3fileInfo);
	$endtime = getmicrotime();
	echo 'File parsed in '.number_format($endtime - $starttime, 3).' seconds.<BR>';

} else {

	$listdirectory = (isset($_REQUEST['listdirectory']) ? $_REQUEST['listdirectory'] : '.');
	if (!is_dir($listdirectory)) {
		// Directory names with single quotes or double quotes in them will likely come out addslashes()'d
		// so this will replace \' with ' (can't use stripslashes(), that would get rid of all slashes!)
		$listdirectory = str_replace(chr(92).chr(92), chr(92), $listdirectory); // \\ -> \
		$listdirectory = str_replace(chr(92).chr(39), chr(39), $listdirectory); // \' -> '
		$listdirectory = str_replace(chr(92).chr(34), chr(34), $listdirectory); // \" -> "
	}
	$listdirectory = realpath($listdirectory); // get rid of /../../ references
	$currentfulldir = $listdirectory.'/';
	if (is_dir(str_replace('\\', '/', $listdirectory))) {
		// this mostly just gives a consistant look to Windows and *nix filesystems
		// (windows uses \ as directory seperator, *nix uses /)
		$currentfulldir = str_replace('\\', '/', $listdirectory.'/');
	}
	if ($handle = @opendir($listdirectory)) {

		echo str_repeat(' ', 300); // IE buffers the first 300 or so chars, making this progressive display useless - fill the buffer with spaces
		echo 'Processing';

		$starttime = getmicrotime();
		while ($file = readdir($handle)) {
			set_time_limit(30); // allocate another 30 seconds to process this file - should go much quicker than this unless intense processing (like bitrate histogram analysis) is enabled
			echo '.'; // progress indicator dot
//echo $file.'<BR>';
			flush();  // make sure the dot is show, otherwise it's useless
			if (is_dir(str_replace('//', '/', $currentfulldir))) {
				// if the directory name contains "weird" things like
				// " or ' or \" or \' etc then this might cause problems
				// in which case just use un-manipulated $listdirectory
				$currentfilename = str_replace('//', '/', $currentfulldir).$file;
			} else {
				$currentfilename = $listdirectory.'/'.$file;
			}

			// symbolic-link-resolution enhancements by davidbullock@tech-center.com
			$TargetObject     = realpath($currentfilename);  // Find actual file path, resolve if it's a symbolic link
			$TargetObjectType = filetype($TargetObject);     // Check file type without examining extension

			if($TargetObjectType == 'dir') {
				switch ($file) {
					case '..':
						$ParentDir = str_replace('\\', '/', realpath($file.'/..')).'/';
						$DirectoryContents["$currentfulldir"]['dir']["$file"]['filename'] = $ParentDir;
						break;

					case '.':
						// ignore
						break;

					default:
						$DirectoryContents["$currentfulldir"]['dir']["$file"]['filename'] = $file;
						break;
				}
			} else if ($TargetObjectType == 'file') {
				$fileinformation = GetAllMP3info($currentfilename, FALSE);


				if (!isset($fileinformation['fileformat']) || ($fileinformation['fileformat'] == '')) {
					// auto-detect couldn't find the file format (probably corrupt header?), re-scan based on extension, if applicable
					$formatExtensions = array('mp3'=>'mp3', 'ogg'=>'ogg', 'zip'=>'zip', 'wav'=>'riff', 'avi'=>'riff', 'mid'=>'midi', 'mpg'=>'mpeg', 'jpg'=>'image', 'gif'=>'image', 'png'=>'image');
					if (isset($formatExtensions[fileextension($currentfilename)])) {
						$fileinformation = GetAllMP3info($currentfilename, $formatExtensions[fileextension($currentfilename)]);
					}
				}




				if (isset($fileinformation['fileformat']) && $fileinformation['fileformat']) {
					$DirectoryContents["$currentfulldir"]['known']["$file"] = $fileinformation;
				} else {
					$DirectoryContents["$currentfulldir"]['other']["$file"]['filesize'] = filesize($currentfilename);
					$DirectoryContents["$currentfulldir"]['other']["$file"]['playtime_string'] = '-';
				}
			}
		}
		$endtime = getmicrotime();
		closedir($handle);
		echo 'done<BR>';
		echo 'Directory scanned in '.number_format($endtime - $starttime, 2).' seconds.<BR>';
		flush();

		$columnsintable = 12;
		echo '<TABLE BORDER="1" CELLSPACING="0" CELLPADDING="3" STYLE="font-family: sans-serif; font-size: 9pt;">';
		echo '<TR BGCOLOR="#CCCCDD"><TH COLSPAN="'.$columnsintable.'">Files in '.$currentfulldir.'</TH></TR>';
		$rowcounter = 0;
		foreach ($DirectoryContents as $dirname => $val) {
			if (is_array($DirectoryContents["$dirname"]['dir'])) {
				uksort($DirectoryContents["$dirname"]['dir'], 'MoreNaturalSort');
				foreach ($DirectoryContents["$dirname"]['dir'] as $filename => $fileinfo) {
					echo '<TR BGCOLOR="#'.(($rowcounter++ % 2) ? 'FFCCCC' : 'EEBBBB').'">';
					if ($filename == '..') {
						echo '<TD COLSPAN="'.$columnsintable.'">Parent directory: <A HREF="'.$_SERVER['PHP_SELF'].'?listdirectory='.urlencode($dirname.$filename).'"><B>'.str_replace('\\', '/', realpath($dirname.$filename)).'/</B></A></TD>';
					} else {
						echo '<TD COLSPAN="'.$columnsintable.'"><A HREF="'.$_SERVER['PHP_SELF'].'?listdirectory='.urlencode($dirname.$filename).'"><B>'.$filename.'</B></A></TD>';
					}
					echo '</TR>';
				}
			}
			echo '<TR BGCOLOR="#CCCCEE"><TH>Filename</TH><TH>File Size</TH><TH>Format</TH><TH>Playtime</TH><TH>Bitrate</TH><TH>Artist</TH><TH>Title</TH><TH>ID3v1</TH><TH>ID3v2</TH><TH>Lyrics3</TH><TH>Edit</TH><TH>Delete</TH></TR>';
			if (isset($DirectoryContents["$dirname"]['known']) && is_array($DirectoryContents["$dirname"]['known'])) {
				uksort($DirectoryContents["$dirname"]['known'], 'MoreNaturalSort');
				foreach ($DirectoryContents["$dirname"]['known'] as $filename => $fileinfo) {
					echo '<TR BGCOLOR="#'.(($rowcounter++ % 2) ? 'DDDDDD' : 'EEEEEE').'">';
					echo '<TD><A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($dirname.$filename).'" TITLE="View detailed analysis">'.$filename.'</A></TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.number_format($fileinfo['filesize']).'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.$fileinfo['fileformat'].'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.(isset($fileinfo['playtime_string']) ? $fileinfo['playtime_string'] : '-').'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.(isset($fileinfo['bitrate']) ? BitrateText($fileinfo['bitrate'] / 1000) : '-').'</TD>';
					echo '<TD ALIGN="LEFT">&nbsp;'.(isset($fileinfo['artist']) ? $fileinfo['artist'] : '').'</TD>';
					echo '<TD ALIGN="LEFT">&nbsp;'.(isset($fileinfo['title']) ? $fileinfo['title'] : '').'</TD>';
					if (isset($fileinfo['id3']['id3v1'])) {
						if (isset($fileinfo['id3']['id3v2'])) {
							echo '<TD ALIGN="LEFT">&nbsp;'.(ID3v1matchesID3v2($fileinfo['id3']['id3v1'], $fileinfo['id3']['id3v2']) ? 'matches ID3v2' : 'differs from ID3v2').'</TD>';
							echo '<TD ALIGN="LEFT">&nbsp;'.(ID3v1matchesID3v2($fileinfo['id3']['id3v1'], $fileinfo['id3']['id3v2']) ? 'matches ID3v1' : 'differs from ID3v1').'</TD>';
						} else {
							echo '<TD ALIGN="LEFT">&nbsp;Y</TD>';
							echo '<TD ALIGN="LEFT">&nbsp;</TD>';
						}
					} else {
						if (isset($fileinfo['id3']['id3v2'])) {
							echo '<TD ALIGN="LEFT">&nbsp;</TD>';
							echo '<TD ALIGN="LEFT">&nbsp;Y</TD>';
						} else {
							echo '<TD ALIGN="LEFT">&nbsp;</TD>';
							echo '<TD ALIGN="LEFT">&nbsp;</TD>';
						}
					}
					echo '<TD ALIGN="LEFT">&nbsp;'.(isset($fileinfo['lyrics3']) ? 'Y' : '').'</TD>';
					echo '<TD ALIGN="LEFT">&nbsp;';
					if ($fileinfo['fileformat'] == 'mp3') {
						echo '<A HREF="getid3.write.php?EditorFilename='.urlencode($dirname.$filename).'" TITLE="Edit ID3 tag">edit&nbsp;ID3';
					} else if ($fileinfo['fileformat'] == 'ogg') {
						echo '<A HREF="getid3.write.php?EditorFilename='.urlencode($dirname.$filename).'" TITLE="Edit Ogg comment tags">edit&nbsp;tags';
					}
					echo '</TD>';
					echo '<TD ALIGN="LEFT">&nbsp;<A HREF="'.$_SERVER['PHP_SELF'].'?listdirectory='.urlencode($listdirectory).'&deletefile='.urlencode($dirname.$filename).'" onClick="return confirm(\'Are you sure you want to delete '.addslashes($dirname.$filename).'? \n(this action cannot be un-done)\');" TITLE="Permanently delete '."\n".FixTextFields($filename)."\n".' from'."\n".' '.FixTextFields($dirname).'">delete</A></TD>';
					echo '</TR>';
				}
			}
			if (isset($DirectoryContents["$dirname"]['other']) && is_array($DirectoryContents["$dirname"]['other'])) {
				uksort($DirectoryContents["$dirname"]['other'], 'MoreNaturalSort');
				foreach ($DirectoryContents["$dirname"]['other'] as $filename => $fileinfo) {
					echo '<TR BGCOLOR="#'.(($rowcounter++ % 2) ? 'BBBBDD' : 'CCCCFF').'">';
					echo '<TD><A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($dirname.$filename).'"><I>'.$filename.'</I></A></TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.number_format($fileinfo['filesize']).'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.(isset($fileinfo['fileformat']) ? $fileinfo['fileformat'] : '').'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.(isset($fileinfo['playtime_string']) ? $fileinfo['playtime_string'] : '-').'</TD>';
					echo '<TD ALIGN="RIGHT">&nbsp;'.(isset($fileinfo['bitrate']) ? BitrateText($fileinfo['bitrate'] / 1000) : '-').'</TD>';
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // Artist
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // Title
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // ID3v1
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // ID3v2
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // Lyrics3
					echo '<TD ALIGN="LEFT">&nbsp;</TD>'; // Edit
					echo '<TD ALIGN="LEFT">&nbsp;<A HREF="'.$_SERVER['PHP_SELF'].'?listdirectory='.urlencode($listdirectory).'&deletefile='.urlencode($dirname.$filename).'" onClick="return confirm(\'Are you sure you want to delete '.addslashes($dirname.$filename).'? \n(this action cannot be un-done)\');" TITLE="Permanently delete '.addslashes($dirname.$filename).'">delete</A></TD>';
					echo '</TR>';
				}
			}
		}
		echo '</TABLE>';
	} else {
		echo '<B>ERROR: Could not open directory: <U>'.$currentfulldir.'</U></B><BR>';
	}
}
echo '<BR><HR NOSHADE><DIV STYLE="font-family: sans-serif; font-size: 8pt;">Analyzed by <A HREF="http://getid3.sourceforge.net" TARGET="_blank"><B>getID3()</B><BR>http://getid3.sourceforge.net</A></DIV>';
echo '</BODY></HTML>';

?>