<?php
//-----------------------------------------------------------------------------
// BellaBook Copyright © Jem Turner 2004-2007,2008 unless otherwise noted
// http://www.jemjabella.co.uk/
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License. See README.txt
// or LICENSE.txt for more information.
//-----------------------------------------------------------------------------

require_once('prefs.php');


define("ENTRIES", "entries.txt");
define("TEMPENTRIES", "tempentries.txt");
define("IPBLOCKLST", "iplist.txt");
define("SPAMWDS", "spamwords.txt");


function cleanUp($text) {
	$text = trim(htmlentities(strip_tags(urldecode($text))));
	return $text;
}

// break big words every 50 characters for layout preservation.
function linebreaker($text) {
	$new_text = '';
	$text_1 = explode('>',$text);
	$sizeof = sizeof($text_1);
	for ($i=0; $i<$sizeof; ++$i) {
		$text_2 = explode('<',$text_1[$i]);
		if (!empty($text_2[0])) {
			$new_text .= preg_replace('#([^\s .]{50})#i', '\\1  ', $text_2[0]);
		}
		if (!empty($text_2[1])) {
			$new_text .= '<' . $text_2[1] . '>';   
		}
	}
	return $new_text;
}

function doAdminHeader() {
	global $stylecolor;
?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
	"http://www.w3.org/TR/html4/strict.dtd">
	<html>
	<head>
		<title>BellaBook Control Panel</title>
		<link href="<?php echo $stylecolor; ?>-stylesheet.css" rel="stylesheet" type="text/css">
	</head>
	<body>

	<div id="container">
	<p id="topnav"><a href="index.php">View the Guestbook</a> &middot; <a href="admin.php">Admin Main</a> &middot; <a href="logout.php">Logout</a></p>
<?php
}
function doAdminFooter() {
	echo "\r\n</div>\r\n</body>\r\n</html>";
}


// fix the blank lines in iplist/badwords/entries files
function blanklinefix($inputfile) {
	ignore_user_abort(true);
	$content = file($inputfile);

	if (count($content) > 0) {
		$content = array_diff(array_diff($content, array("")), array("\r\n"));

		$newContent = array();
		foreach ($content as $line) {
			$newContent[] = trim($line);
		}
		$newContent = implode("\r\n", $newContent);
	
		$fl = fopen($inputfile, "w+");
		if (flock($fl, LOCK_EX)) {
			stream_set_write_buffer($fl, 0);
			fwrite($fl, $newContent);
			flock($fl, LOCK_UN);
		} else {
			echo 'The file: '.$inputfile.' could not be locked for writing; the blanklinefix function could not be applied at this time.';
		}
		fclose($fl);
	}
	ignore_user_abort(false);
}

function doWrite($file2open, $data, $writetype) {
	$file = fopen($file2open, $writetype) or die("ERROR: could not open ".$file2open);
	if (flock($file, LOCK_EX)) {
		stream_set_write_buffer($file, 0);
		fwrite($file, $data);
		flock($file, LOCK_UN);
	} else {
		exit("ERROR: could not lock ".$file2open);
	}
	fclose($file);
}

function sign_gbook($file, $entry) {
	// one of these days I really should really should re-write the whole lot and just use sorting functions
	
	$oldData = file_get_contents($file);
	doWrite($file, $entry, "w"); // write the new data
	doWrite($file, $oldData, "a"); // append the old data

	echo "<p>Thank you for signing the guestbook.</p>";

	if ($file === TEMPENTRIES)
		echo "<p>Moderation is enabled, the guestbook owner will have to approve your message before it appears.</p>";
}

function emoticonise($message) {
	global $smilies;
		$path_to_smilies = "smilies/";

	if (isset($smilies) && $smilies == "yes") {
		$smiliesA = array(
			':)'      => 'smile.gif',
			':D'      => 'biggrin.gif',
			':('      => 'sad.gif',
			':P'      => 'tongue.gif',
			':o'      => 'shocked.gif',
			';)'      => 'wink.gif',
		);
		foreach ($smiliesA as $key => $value) {
			$message = str_replace($key, " <img src='".$path_to_smilies.$value."' alt='$key' title='$key' />", $message);
		}
		return $message;
	} else {
		return $message;
	}
}

function checkBots() {
	$isbot = false;
	
	$bots = array("Indy", "Blaiz", "Java", "libwww-perl", "Python", "OutfoxBot", "User-Agent", "PycURL", "AlphaServer", "T8Abot", "Syntryx", "WinHttp", "WebBandit", "nicebot");
	foreach ($bots as $bot)
		if (strpos($_SERVER['HTTP_USER_AGENT'], $bot) !== false)
			$isbot = true;

	if (empty($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] == " ")
		$isbot = true;
	
	if ($isbot === true) {
		echo "<p>Safety checks indicate there's a high probability that you're a bot, and bots aren't allowed to submit links.</p>";
		exit(include('footer.php'));
	}
}
function breakEmail($email) {
	$email = str_replace('.', 'DOTTY', $email);
	$email = str_replace('@', 'ATTIE', $email);
	$email = str_replace('-', 'DASHY', $email);
	$email = str_replace('_', 'SCORE', $email);

	return $email;
}
function fixEmail($email) {
	$email = str_replace('DOTTY', '.', $email);
	$email = str_replace('ATTIE', '@', $email);
	$email = str_replace('DASHY', '-', $email);
	$email = str_replace('SCORE', '_', $email);

	return $email;
}


if ($emailrequired == "yes") $req = "<small>(req.)</small>"; else $req = "<small>(not req.)</small>";
if ($showemail == "yes") $disp = " <small>(displayed)</small>"; else $disp = " <small>(not shown)</small>";

function countcontents($fileloc) {
	// why did I put this in? it's so redundant...
	if (filesize($fileloc) > 0) return count(file($fileloc));
	else return 0;
}
blanklinefix(ENTRIES);
blanklinefix(TEMPENTRIES);
blanklinefix(IPBLOCKLST);
blanklinefix(SPAMWDS);

error_reporting(0);
?>