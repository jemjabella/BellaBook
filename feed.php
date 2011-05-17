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
header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="utf-8"?>'; ?>
	<rss version="2.0">
		<channel>
			<title><?php echo $title; ?></title>
			<link><?php echo $admin_url; ?></link>
			<description><?php echo $admin_sitename; ?>'s guestbook feed</description>
			<language>en-gb</language>
<?php
			$blah = file($entriesfile);
			$i = 0;
			$limit = (count($blah) > 10) ? 10 : count($blah);
			while ($i<$limit){
				list($name,$email,$url,$date,$ip,$message) = preg_split("/,(?! )/",$blah[$i]);

				$date = date("D, d M Y H:i:s T", strtotime($date));
				$email = fixEmail($email);
				$message = trim(stripslashes($message), "\"\x00..\x1F");
		
				echo "<item>\n";
					echo "<title>guestbook entry</title>\n";
					echo "<link>$admin_url</link>\n";
					echo "<pubDate>$date</pubDate>\n";
					echo "<description><![CDATA[$message]]></description>\n";
				echo "</item>\n";
				$i++;
			}
?>
		</channel>
	</rss>