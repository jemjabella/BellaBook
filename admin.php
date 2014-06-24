<?php
//-----------------------------------------------------------------------------
// BellaBook Copyright © Jem Turner 2004-2007,2008 unless otherwise noted
// http://www.jemjabella.co.uk/
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License. See README.txt
// or LICENSE.txt for more information.
//-----------------------------------------------------------------------------

require_once('config.php');

if (isset($_COOKIE['bellabook'])) {
	if ($_COOKIE['bellabook'] == md5($admin_pass.$secret)) {
		if (isset($_GET['p'])) $page = $_GET['p'];
		else $page = NULL;
		
		if (!isset($_GET['file']) || (isset($_GET['file']) && ($_GET['file'] != "entries.txt" && $_GET['file'] != "tempentries.txt")))
			$_GET['file'] = null;
		
		doAdminHeader();
		switch($page) {
		case "manageentries":
			echo "<p style='color: red;'><strong>Note:</strong> Do not try to delete multiple entries at once. Due to the setup of the guestbook this will cause the wrong entries to be deleted!</p> \n\n";
			if (filesize($_GET['file']) > 0) {
				/* More of Katy's hacky bit for pagination! */

				$entries = file($_GET['file']);
				$count = count($entries);

				echo '<p style="text-align: center;">'.$count.' entries | ';
				$numpages = ceil($count/$perpage);

				echo "pages: ";
				for ($x=1; $x<=$numpages; $x++) {
					if (isset($_GET['page']) && $x == $_GET['page'] || (!isset($_GET['page']) &&  $x == 1))
						echo '<strong>'.$x.'</strong>';
					else
						echo '<a href="admin.php?p=manageentries&amp;file='. $_GET['file'] .'&amp;page='.$x.'">'.$x.'</a> ';
				}
				echo  "</p> \n\n ";
	
				if (isset($_GET['page']) && is_numeric($_GET['page'])) $i = $perpage * ($_GET['page'] - 1);
				else $i = 0;

				$end = $i + $perpage;
	
				if ($end > $count) $end=$count;
?>
				<form action="admin.php?p=appentries" method="post">
				<table>
<?php
				while ($i < $end) {
					list($name,$email,$url,$date,$ip,$message) = preg_split("/,(?! )/", $entries[$i]);
					
					$email = fixEmail($email);
					$message = trim(stripslashes($message), "\"\x00..\x1F");
					$sitename = str_replace('www.', '', str_replace('http://', '', $url));
?>
					<tr>
						<td>
							<input type="hidden" name="hashy" id="hashy" value="<?php echo md5(date("H").$secret); ?>">

							<strong>Name:</strong> <?php echo $name; ?><br>
							<strong>E-mail:</strong> <a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a><br>
							<strong>www:</strong> <a href="<?php echo $url; ?>"><?php echo $sitename; ?></a><br>
							<strong>Date:</strong> <?php echo date($dateformat, strtotime($date)); ?><br>
							<strong>IP:</strong> <a href="http://www.geobytes.com/IpLocator.htm?GetLocation&amp;ipaddress=<?php echo $ip; ?>"><?php echo $ip; ?></a><br>
							<br>
							<a href="admin.php?p=editentry&amp;entry=<?php echo $i; ?>&amp;file=<?php echo $_GET['file']; ?>">Edit Entry</a><br>
							<a href="admin.php?p=delentry&amp;entry=<?php echo $i; ?>&amp;file=<?php echo $_GET['file']; ?>" onclick="javascript:return confirm('Are you sure you want to delete this entry?')">Delete Entry</a><br>
							<?php if ($_GET['file'] == "tempentries.txt") : ?>
							<input type="checkbox" class="check" name="appr[<?php echo $i; ?>]" value="<?php echo $i; ?>"> Approve
							<?php endif; ?>
						</td>
						<td>
							<?php echo $message; ?>
						</td>
					</tr>
<?php
					$i++;
				}
?>
				</table>
				<?php if ($_GET['file'] == "tempentries.txt") : ?>
					<p><input type="submit" name="submit" id="submit" value="Approve"></p>
				<?php endif; ?>
				</form>
<?php
			} else {
				echo "<p>No entries to manage!</p>";
			}
		break;
		case "appentries":
			if (!isset($_POST['hashy']) || $_POST['hashy'] != md5(date("H").$secret)) exit("<p>Invalid hashy token.</p>");
			
			if (isset($_POST['appr']) && is_array($_POST['appr'])) {
				$pending = file(TEMPENTRIES);
				$approved = array();
				
				foreach ($_POST['appr'] as $entry => $id) {
					if (is_numeric($id) && array_key_exists($id, $pending)) {
						$approved[] = $pending[$id];
						unset($pending[$id]);
					}
				}
				$pending = implode("", $pending);
				doWrite(TEMPENTRIES, $pending, "w");
				
				$newentries = implode("", $approved) . "\r\n";
				sign_gbook(ENTRIES, $newentries);
				
				echo "<p>Selected entries now 'approved'.</p>";
			}
		break;
		case "editentry":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (!isset($_POST['hashy']) || $_POST['hashy'] != md5(date("H").$secret)) exit("<p>Invalid hashy token.</p>");
				
				foreach ($_POST as $key => $val) {
					$$key = cleanUp($val);
				}
				$comments = str_replace("<br /><br /><br /><br />", "<br /><br />", preg_replace("/,(?! )/", ", ", preg_replace("([\r\n])", "<br />", $comments)));

				$editedEntry = $name . "," . breakEmail($email) . "," . $url . "," . $date . "," . $ip . "," . "\"$comments\"" . "\n";
				
				$entries = file($file);
				$entries[$gbentry] = $editedEntry;
				$entries = trim(implode($entries));

				doWrite($file, $entries, "w");

				echo '<p>Entry edited. <a href="admin.php">Return to admin</a> / <a href="admin.php?p=manageentries&amp;file='.$file.'">manage more</a>?</p>';
				exit(doAdminFooter());
			}
			echo "<p>Note: editing an entry that is in moderation will not approve it. You must do this separately.</p>";

			if (!isset($_GET['entry']) || $_GET['entry'] == "" || !is_numeric($_GET['entry'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid entry.</p>";
				exit(include('footer.php'));
			} elseif (!isset($_GET['file']) || $_GET['file'] == "" || !file_exists($_GET['file'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid file.</p>";
				exit(include('footer.php'));
			}
			$entries = file($_GET['file']);

			list($name,$email,$url,$odate,$ip,$message) = preg_split("/,(?! )/", $entries[$_GET['entry']]);
			
			$email = fixEmail($email);
			$message = str_replace("<br /><br />", "\r\n\r\n", trim(stripslashes($message), "\"\x00..\x1F"));
?>
			<form action="admin.php?p=editentry" method="post">
			<p>
				<input type="hidden" name="hashy" id="hashy" value="<?php echo md5(date("H").$secret); ?>">
				<input type="hidden" name="gbentry" id="gbentry" value="<?php echo $_GET['entry']; ?>">
				<input type="hidden" name="file" id="file" value="<?php echo $_GET['file']; ?>">
			
				<input type="text" name="name" id="name" value="<?php echo $name; ?>" /> <label for="name">Name</label><br>
				<input type="text" name="email" id="email" value="<?php echo $email; ?>" /> <label for="email">E-mail</label><br>
				<input type="text" name="url" id="url" value="<?php echo $url; ?>" /> <label for="url">Website</label><br>
				<input type="text" name="date" id="date" value="<?php echo $odate; ?>" /> <label for="date">Date/Time</label> <small>(yyyy-mm-dd)</small><br>
				<input type="text" name="ip" id="ip" value="<?php echo $ip; ?>" readonly="readonly" /> <label for="ip">IP Address</label><br>
				<textarea name="comments" id="comments"><?php echo $message; ?></textarea> <br>
				
				<input type="submit" id="submit" value="continue" />
			</p>
			</form>
<?php
		break;
		case "delentry":
			if (!isset($_GET['entry']) || $_GET['entry'] == "" || !is_numeric($_GET['entry'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid entry.</p>";
				exit(include('footer.php'));
			} elseif (!isset($_GET['file']) || $_GET['file'] == "" || !file_exists($_GET['file'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid file.</p>";
				exit(include('footer.php'));
			}
			
			$entries = file($_GET['file']);

			unset($entries[$_GET['entry']]);
			$entries = implode("", $entries);
			$entries = trim($entries);

			doWrite($_GET['file'], $entries, "w");

			echo '<p>Entry deleted. <a href="admin.php">Return to admin</a> / <a href="admin.php?p=manageentries&amp;file='.$_GET['file'].'">manage more</a>?</p>';
		break;
		case "editbadwords":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (isset($_POST['spamwd']) && is_array($_POST['spamwd'])) {
					$badwords = array();
					
					foreach ($_POST['spamwd'] as $spamword)
						if (ereg("^[A-Za-z0-9]*$", $spamword))
							$badwords[] = $spamword;
					
					$new = implode("\r\n", $badwords);
					doWrite(SPAMWDS, $new, "w");
					
					echo '<p>Spam words updated. <a href="admin.php?p=editbadwords">Manage bad words</a>?</p>';
				}
				exit(doAdminFooter());
			}
?>
			<h4>Manage Spam Words</h4>
			<p>Add each new word separately: do <strong>not</strong> use commas to separate spam words.</p>
			<form action="admin.php?p=editbadwords" method="post">
			<p>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
<?php
				$spamwords = file(SPAMWDS);
				foreach ($spamwords as $word)
					echo '<input type="text" name="spamwd[]" value="'.$word.'"><br>';
?>
				<input type="submit" name="submit" id="submit" value="Update">
			</p>
			</form>
<?php
		break;
		case "editips":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (isset($_POST['ip']) && is_array($_POST['ip'])) {
					$existing = file(IPBLOCKLST);
					
					foreach ($_POST['ip'] as $ipadd)
						if (preg_match("^((\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)(?:\.(\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)){3})$^", $ipadd))
							$existing[] = $ipadd;
					
					$new = implode("", $existing);
					doWrite(IPBLOCKLST, $new, "w");
					
					echo "<p>Blocked IPs updated.</p>";
				}
				exit(doAdminFooter());
			}
?>
			<h4>Manage Blocked IP Addresses</h4>
			<p>Add each new word separately: do <strong>not</strong> use commas to separate IPs.</p>
			<form action="admin.php?p=editips" method="post">
			<p>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
<?php
				$ipadds = file(IPBLOCKLST);
				foreach ($ipadds as $ip)
					echo '<input type="text" name="ip[]" value="'.$ip.'"><br>';
?>
				<input type="submit" name="submit" id="submit" value="Update">
			</p>
			</form>
<?php
		break;
		default:
?>
			<ul>
			<li><a href="admin.php?p=manageentries&amp;file=entries.txt">Manage Approved Entries</a> (<?php echo countcontents(ENTRIES); ?>)</li>
			<?php if ($moderate == "yes") { ?>
				<li><a href="admin.php?p=manageentries&amp;file=tempentries.txt">Manage Pending Entries</a> (<?php echo countcontents(TEMPENTRIES); ?>)</li>
			<?php } ?>
			</ul>
			
			<ul>
			<li><a href="admin.php?p=editbadwords">Manage Spam Words</a></li>
			<li><a href="admin.php?p=editips">Manage Blocked IPs</a></li>
			</ul>
<?php
		break;
		}
		doAdminFooter();
		exit;
	} else {
		exit("<p>Bad cookie. Clear 'em out and start again.</p>");
	}
}

if (isset($_GET['p']) && $_GET['p'] == "login") {
	if ($_POST['name'] != $admin_name || $_POST['pass'] != $admin_pass) {
		doAdminHeader();
?>
			<p>Sorry, that username and password combination is not valid. Try again.</p>

		    <form method="post" action="admin.php">
		    Username:<br>
		    <input type="text" name="name"><br>
		    Password:<br>
		    <input type="password" name="pass"><br>
		    <input type="submit" name="submit" value="Login">
		    </form>
<?php
		doAdminFooter();
		exit;
	} else if ($_POST['name'] == $admin_name && $_POST['pass'] == $admin_pass) {
		setcookie('bellabook', md5($_POST['pass'].$secret), time()+(31*86400));
		header("Location: admin.php");
		exit;
	} else {
		setcookie('bellabook', NULL, NULL);
		header("Location: admin.php");
		exit;
	}
}
doAdminHeader();
?>
    <form method="post" action="admin.php?p=login">
    Username:<br>
    <input type="text" name="name"><br>
    Password:<br>
    <input type="password" name="pass"><br>
    <input type="submit" name="submit" value="Login">
    </form>
<?php
doAdminFooter();
?>