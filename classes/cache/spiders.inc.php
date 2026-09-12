<?php
class cache_spiders extends CacheItem {

	function load() {
		global $db;
		if ($this->exists() == true) {
		    $this->import();
		}
		else {
			$this->data = array();
			// Table may be missing on incomplete installs – do not fatal the whole site
			$result = $db->query("SELECT id, user_agent, bot_ip, name, type FROM {$db->pre}spider ORDER BY bot_visits DESC", false);
			if ($result) {
			    while ($row = $db->fetch_assoc($result)) {
			        $this->data[$row['id']] = $row;
			    }
			}
		    $this->export();
		}
	}

}
?>
