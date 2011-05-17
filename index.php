<?php
//-----------------------------------------------------------------------------
// BellaBook Copyright © Jem Turner 2004-2007,2008 unless otherwise noted
// http://www.jemjabella.co.uk/
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License. See README.txt
// or LICENSE.txt for more information.
//-----------------------------------------------------------------------------

include('config.php');
include('header.php');

if(!fopen(ENTRIES, "r")) { 
	echo "Could not open entries file. Please verify permissions (CHMOD - 666) and actual existence.";
} else {
	if (filesize(ENTRIES) > 0) {
			
/* Katy's hacky bit for pagination! */

		$entries = file(ENTRIES);
		$count = count($entries);

		$numpages = ceil($count/$perpage);
		if (isset($_GET['page']) && is_numeric($_GET['page'])) $pg = $_GET['page']; else $pg = 1;
		
		echo '<p class="pagination">'.$count.' entries<br />';
		if ($perpage < $count) {
			if ($pg > 1 && $pg <= $numpages) {
				$prev = $pg - 1;
				echo '<a href="index.php?page='.$prev.'">Prev</a> &middot; ';
			} else {
				echo "Prev &middot; ";
			}

			for ($x=1; $x<=$numpages; $x++) {
				if ($x == $pg) echo '<strong>'.$x.'</strong> ';
				else echo '<a href="index.php?page='.$x.'">'.$x.'</a> ';
			}
			
			if ($pg < $numpages) {
				$next = $pg + 1;
				echo ' &middot; <a href="index.php?page='.$next.'">Next</a>';
			} else {
				echo " &middot; Next";
			}
		}
		echo  "</p> \n\n ";

		$i = $perpage * ($pg - 1); 
		$end = $i + $perpage;

		if ($end > $count) $end = $count;
?>
		<table id="entries">
<?php
		while ($i<$end){
			list($name,$email,$url,$odate,$ip,$message) = preg_split("/,(?! )/",$entries[$i]);
			
			$date = date($dateformat, strtotime($odate));
			$message = trim(stripslashes($message), "\"\x00..\x1F");
			
			if ($showemail == "yes") {
				// this bit of javascript prevents the email address being picked up by bots... in theory
				$email = "<img src=\"email.gif\" alt=\"\" /> <span class=\"bold\">E-mail:</span> 
						<script type=\"text/javascript\">
						 <!--//
						document.write('<a href=\"mailto:".fixEmail($email)."\">e-mail<\/a>');
						 //-->
						</script><br />
				";
			} else {
				$email = NULL;
			}
			if (empty($url) || $url == "http://") $url = "n/a"; else $url = "<a href=\"$url\" title=\"$name's website\">www</a>";
			$rowColour = $i % 2;
?>

			<tr class="rowcolor<?php echo $rowColour; ?>">
				<td class="meta">
					<img src="user.gif" alt="" /> <span class="bold">Name:</span> <?php echo $name; ?><br />
					<?php echo $email; ?>
					<img src="www.gif" alt="" /> <span class="bold">Website:</span> <?php echo $url; ?><br />
					<img src="date.gif" alt="" /> <span class="bold">Date:</span> <?php echo $date; ?><br />
				</td>
				<td>
					<?php echo emoticonise(linebreaker($message)); ?>
				</td>
			</tr>
<?php
			$i++;
		} //end while loop
?>
		</table>
<?php
	} else { 
		echo "<p>No entries have been made yet!</p> "; 
	}
}
@include('footer.php'); ?>