<?php
include('data/config.inc.php');
require_once('install/classes/class.filesystem.php');
$filesystem = new filesystem($config['ftp_server'], $config['ftp_user'], $config['ftp_pw'], $config['ftp_port']);
$filesystem->set_wd($config['ftp_path'], $config['fpath']);
if (isset($_REQUEST['save']) && $_REQUEST['save'] == 1) {
	if (isset($_REQUEST['action']) && is_array($_REQUEST['action'])) {
		$action = $_REQUEST['action'];
	}
	else {
		$action = array();
	}
	require_once('install/classes/database/'.$config['dbsystem'].'.inc.php');
	$db = new DB($config['host'], $config['dbuser'], $config['dbpw'], $config['database'], $config['dbprefix']);
	$db->setPersistence($config['pconnect']);
	$db->connect(false);
	if (!$db->hasConnection()) {
		?>
	<div class="bbody">Could not connect to database! Please try again later or check the database settings!</div>
	<div class="bfoot center"><a class="submit" href="index.php?package=install&amp;step=<?php echo $step-2; ?>">Go back</a> <a class="submit" href="index.php?package=install&amp;step=<?php echo $step; ?>">Refresh</a></div>
		<?php
	}
	else {
		if (!$db->select_db()) {
			?>
	<div class="bbody">Could not find database <em><?php echo $db->database; ?></em>! Please create a new database with this name or choose another database!</div>
	<div class="bfoot center"><a class="submit" href="index.php?package=install&amp;step=<?php echo $step-2; ?>">Go back</a> <a class="submit" href="index.php?package=install&amp;step=<?php echo $step; ?>">Refresh</a></div>
			<?php
		}
		else {
			$act = array(
				3 => array('table has been deleted and recreated', 'tables have been deleted and recreated'),
				2 => array('table has not been changed', 'tables have not been changed'),
				1 => array('table has been cleared', 'tables have been cleared'),
				0 => array('table has been created', 'tables have been created')
			);
			$done = array_fill(0, 4, 0);
			$errors = array();

			// ALWAYS scan the db directory – never depend only on POST (max_input_vars / missing fields)
			$path = 'install/package/'.$package.'/db/';
			$existing = $db->list_tables();
			$dh = @opendir($path);
			$files = array();
			if ($dh) {
				while (($file = readdir($dh)) !== false) {
					if (substr($file, -4) === '.sql') {
						$files[] = $file;
					}
				}
				closedir($dh);
			}
			sort($files);

			foreach ($files as $file) {
				$basename = substr($file, 0, -4);
				$t = $db->pre.$basename;
				$full = $path.$file;

				// Determine action: POST value, or auto-create if missing
				if (isset($action[$basename])) {
					$value = intval($action[$basename]);
				}
				else {
					// Missing from POST – create if not exists, otherwise leave alone
					$value = in_array($t, $existing) ? 2 : 0;
				}

				// If table does not exist, force create regardless of "do not change"
				if (!in_array($t, $existing) && $value == 2) {
					$value = 0;
				}

				if ($value == 0 || $value == 3) {
					$sql = @file_get_contents($full);
					if ($sql === false || $sql === '') {
						$errors[] = $basename.': could not read SQL file';
						continue;
					}
					$sql = str_replace('{:=DBPREFIX=:}', $db->pre, $sql);
					if ($value == 3) {
						$db->query('DROP TABLE IF EXISTS `'.$t.'`', false);
					}
					$mq = $db->multi_query($sql, false);
					if (empty($mq['ok'])) {
						$errors[] = $basename.': SQL failed (0 statements ok)';
					}
					else {
						$done[$value]++;
					}
				}
				elseif ($value == 1) {
					$db->query('DELETE FROM `'.$t.'`', false); // TRUNCATE not always available in SQLite
					$done[1]++;
				}
				else {
					$done[2]++;
				}
			}

			if (isset($_REQUEST['sample_d1']) && $_REQUEST['sample_d1'] == 1) {
				$sql = @file_get_contents('install/package/install/db/sample1.dat');
				if ($sql) {
					$sql = str_replace('{:=DBPREFIX=:}', $db->pre, $sql);
					$db->multi_query($sql, false);
					$done[] = 'Sample Data (Forum) have been installed.';
				}
			}
			if (isset($_REQUEST['sample_d2']) && $_REQUEST['sample_d2'] == 1) {
				$sql = @file_get_contents('install/package/install/db/sample2.dat');
				if ($sql) {
					$sql = str_replace('{:=DBPREFIX=:}', $db->pre, $sql);
					$db->multi_query($sql, false);
					$done[] = 'Sample Data (CMS) have been installed.';
				}
			}

			echo '<div class="bfoot">';
			$created = 0;
			foreach ($act as $id => $name) {
				if (!empty($done[$id])) {
					$txt = $done[$id] == 1 ? $name[0] : $name[1];
					echo '<strong>'.$done[$id].' '.$txt.'.</strong><br />';
					if ($id == 0 || $id == 3) $created += $done[$id];
				}
			}
			// Verify core tables
			$existing2 = $db->list_tables();
			$required = array('groups','user','forums','topics','replies','settings','session','language','spider');
			$missing = array();
			foreach ($required as $r) {
				if (!in_array($db->pre.$r, $existing2)) {
					$missing[] = $db->pre.$r;
				}
			}
			if (count($missing) > 0) {
				echo '<strong style="color:red;">Missing tables: '.htmlspecialchars(implode(', ', $missing)).'. Go back and recreate them.</strong><br />';
			}
			else {
				echo '<strong style="color:green;">Core tables verified OK ('.count($existing2).' tables present).</strong><br />';
			}
			if (count($errors) > 0) {
				echo '<strong style="color:red;">Errors:</strong><ul>';
				foreach ($errors as $e) {
					echo '<li>'.htmlspecialchars($e).'</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	}
	$db->close();
}
?>
<div class="bbody">
	<input type="hidden" name="save" value="1" />
	<label for="name">User Name:</label>
	<input class="label" id="name" name="name" size="40" />
	<br class="newinput" /><hr class="formsep" />
	<label for="pw">Password:</label>
	<input class="label" type="password" id="pw" name="pw" size="40" />
	<br class="newinput" /><hr class="formsep" />
	<label for="pwx">Confirm Password:</label>
	<input class="label" type="password" id="pwx" name="pwx" size="40" />
	<br class="newinput" /><hr class="formsep" />
	<label for="email">E-mail address:</label>
	<input class="label" type="text" id="email" name="email" size="40" value="<?php echo $config['forenmail']; ?>" />
	<br class="newinput" /><br class="iefix_br" />
</div>
<div class="bfoot center"><input type="submit" value="Continue" /></div>
