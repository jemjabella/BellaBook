<?php
//-----------------------------------------------------------------------------
// BellaBook Copyright © Jem Turner 2004-2007,2008 unless otherwise noted
// http://www.jemjabella.co.uk/
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License. See README.txt
// or LICENSE.txt for more information.
//-----------------------------------------------------------------------------

$show_form = true;
$error_msg = NULL;

if (isset($_POST['submit']) || $_SERVER['REQUEST_METHOD'] == "POST") {
	require_once('config.php');
	if (isset($captcha) && $captcha == "yes") {
		session_start();
		if(md5($_POST['captcha']) != $_SESSION['key']) {
			setcookie(session_name(), '', time()-36000, '/');
			$_SESSION = array();
			session_destroy();

			include('header.php');
			echo "<p>The text you entered didn't match the image, please <a href='sign.php'>try again</a>.</p>";
			include('footer.php');
			exit;
		}
		if (isset($_SESSION['key']) && isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-36000, '/');
			$_SESSION = array();
			session_destroy();
		}
	}
	include('header.php');

	// let's do some pattern matching on the IP to make sure this visitor is legit, not banned and not flooding
	$ipPattern = '/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/i';
	
	if (filesize(IPBLOCKLST) > 0) {
		$BlockedIPs = file(IPBLOCKLST);
		
		// loop through and trim, otherwise the IP filter doesn't work
		foreach($BlockedIPs as $key => $ip)
			$BlockedIPs[$key] = trim($ip);
		
		$iplist = '/(' . implode('|', $BlockedIPs) . ')/';
	}

	if ($floodcontrol == "yes" && filesize(ENTRIES) > 0) {
		$open2check = file(ENTRIES);
		$expodelineone = explode(",", $open2check['0']);
			if ($_SERVER['REMOTE_ADDR'] == $expodelineone['4'])	{
				echo "<p>Sorry, you can't sign the guestbook twice in a row.</p>";
				exit(include('footer.php'));
			}
	}
	
	#if (!preg_match($ipPattern, $_SERVER['REMOTE_ADDR']) || (isset($iplist) && preg_match($iplist, $_SERVER['REMOTE_ADDR']))) {
	#	echo "<p>Your IP ({$_SERVER['REMOTE_ADDR']}) is not valid or it has been banned, you cannot sign the guestbook.</p>\n\n";
	#	exit(include('footer.php'));
	#}

	// check to make sure it's not a known bot 
	checkBots();
	
	// check for links before we clean up so they don't get removed with strip_tags
	if (isset($allowlinks) && $allowlinks == "no" && (substr_count($_POST['comments'], 'http://') > 0 || substr_count($_POST['comments'], 'URL=') > 0)) {
		echo "<p>Your message contains URLs. To cut down on spam, the posting of URLs/links has been disabled. \n</p>";
		exit(include('footer.php'));
	}
	
	// prepare spam words
	if (filesize(SPAMWDS) > 0) {
		$spamlist = file(SPAMWDS);
		
		// loop through and trim, otherwise the spam filter doesn't work
		foreach($spamlist as $key => $spamword)
			$spamlist[$key] = trim($spamword);
	}
	
	# begin point based spam checks
	$points = (int)0;
	
	if ( isset($spamlist) )
		foreach ($spamlist as $word)
			if (
				strpos(strtolower($_POST['comments']), $word) !== false ||
				strpos(strtolower($_POST['name']), $word) !== false
			)
				$points += 2;
	

	// check for javascript exploits/spam and clean up the data 
	$exploits = array( 'content-type', 'bcc:', 'cc:', 'document.cookie', 'onclick', 'onload', 'javascript', 'alert' );
	foreach ($exploits as $exploit)
		if (
			strpos(strtolower($_POST['email']), $exploit) !== false ||
			strpos(strtolower($_POST['comments']), $exploit) !== false ||
			strpos(strtolower($_POST['name']), $exploit) !== false
		)
			$points += 2;
	
	
	if (strpos($_POST['comments'], "http://") !== false || strpos($_POST['comments'], "www.") !== false) # even if links are enabled we don't want too many
		$points += 2;
	if (isset($_POST['human']))
		$points += 2;
	if (preg_match("/(<.*>)/i", $_POST['comments'])) # html in a comment is a good indicator of spam
		$points += 2;
	if (strlen($_POST['name']) < 3 || strlen($_POST['name']) > 12)
		$points += 1;
	if (strlen($_POST['comments']) < 15 || strlen($_POST['comments'] > 1500))
		$points += 2;
	if (preg_match("/[bcdfghjklmnpqrstvwxyz]{7,}/i", $_POST['comments'])) # comments containing 7 or more consonants in a row is normally gibberish spam
		$points += 1;
	// end score assignments

	// do some final checks 
	if (empty($_POST['name']) || !preg_match("/^[a-zA-Z-'\s]*$/", stripslashes($_POST['name'])))
		$error_msg .= "The name field must not be blank, must not contain special characters.\r\n";
	if (!empty($_POST['email']) && !preg_match('/^([a-z0-9])(([-a-z0-9._])*([a-z0-9]))*\@([a-z0-9])(([a-z0-9-])*([a-z0-9]))+' . '(\.([a-z0-9])([-a-z0-9_-])?([a-z0-9])+)+$/i', strtolower($_POST['email'])))
		$error_msg .= "That is not a valid e-mail address.\r\n";
	if (!empty($_POST['url']) && $_POST['url'] != 'http://' && !preg_match('/^(http|https):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?\/?/i', $_POST['url']))
		$error_msg .= "Invalid website url.\r\n";
	if (empty($_POST['comments']) || strlen($_POST['comments']) < 10)
		$error_msg .= "Your comment is too short.";
	
	if ($error_msg == NULL && $points <= $maxPoints) {
		$show_form = false;
		
		# assign clean data to new array
		foreach($_POST as $key => $value)
			$c[$key] = $value;

		// let's make the data look nice and pretty
		$c['name'] = ucwords(strtolower($c['name']));
		$c['email'] = strtolower($c['email']);
		$c['comments'] = str_replace("<br /><br /><br /><br />", "<br /><br />", preg_replace("/,(?! )/", ", ", preg_replace("([\r\n])", "<br />", $c['comments'])));
		
		$signdate = date("Y-m-d H:i:s");

		if ($emailentries == "yes") {
			$subject = "New entry in guestbook ($title)";

			$message  = "Name: ".$c['name']." \r\n";
			$message .= "E-mail: ".$c['email']." \r\n";
			$message .= "Website: ".$c['url']." \r\n";
			$message .= "Comments: ".$c['comments']." \r\n";
			$message .= "Signed: ".date($dateformat, strtotime($signdate))." \r\n\r\n";
			$message .= "-- ADMIN INFO -- \r\n";
			$message .= "IP: ".$_SERVER['REMOTE_ADDR']." \r\n";
			$message .= "Browser: ".$_SERVER['HTTP_USER_AGENT']." \r\n";
			$message .= "Referrer: ".$_SERVER['HTTP_REFERER']." \r\n";
			$message .= "Admin Panel: ".$admin_gburl."/admin.php \r\n";

			if ($moderate == "yes") $message .= "\r\nYou will need to approve this entry for it to appear in your guestbook.";

			$headers = "From: ".$title." <$admin_email> \r\nReply-To: <$email>";
			mail($admin_email,$subject,$message,$headers);
		}

		$entryformat = $c['name'].",".breakEmail($c['email']).",".$c['url'].",".$signdate.",".$_SERVER['REMOTE_ADDR'].',"'.$c['comments'].'"'."\r\n";

		if ($moderate == "yes") sign_gbook(TEMPENTRIES, $entryformat);
		else sign_gbook(ENTRIES, $entryformat);
	}
}
if (!isset($_POST['submit']) || $show_form == true) {
	require_once('config.php');
	include_once('header.php');

	function get_data($var) {
		if (isset($_POST[$var])) echo cleanUp($_POST[$var]);
	}
?>

<p>Fill in your details in the form below. No HTML allowed.</p>

<?php
	if ($error_msg != NULL) {
		echo '<p><strong style="color: red;">ERROR:</strong><br />'.$error_msg.'</p>';
	}
?>

<form action="sign.php" method="post">
<p class="hidden">
	<input type="checkbox" name="human" id="human" /> <label for="human">Leave this unticked if you're human :)</label>
</p>
<p>
	<input type="text" name="name" id="name" value="<?php get_data("name"); ?>" /> <label for="name">Name</label> <br />
	<input type="text" name="email" id="email" value="<?php get_data("email"); ?>" /> <label for="email">E-mail</label> <?php echo $req . $disp; ?><br />
	<input type="text" name="url" id="url" value="http://" /> <label for="url">Website URL</label> <br />
	<textarea name="comments" id="comments"><?php get_data("comments"); ?></textarea> <label for="comments">Comments</label> <br />
	<?php if (isset($captcha) && $captcha == "yes") { ?>
	<img src="captcha.php" alt="" style="margin-bottom: 2px;" /><br />
	<input type="text" name="captcha" id="captcha" /> <label for="captcha">Numbers in Image</label> <br />
	<?php } ?>
	<input type="submit" id="submit" name="submit" value="Submit" />
</p>
</form>

<?php
}
include('footer.php'); ?>