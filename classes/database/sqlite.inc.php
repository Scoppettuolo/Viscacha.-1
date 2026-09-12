<?php
/**
 * SQLite driver for Viscacha (PHP 8 / PDO)
 * Compatibility layer only - for simple historical installs.
 */
if (defined('VISCACHA_CORE') == false) { die('Error: Hacking Attempt'); }

include_once(dirname(__FILE__)."/class.db_driver.php");

class DB extends DB_Driver {

	var $system;
	var $fieldType;
	var $dbpath;

	function __construct($host = 'localhost', $user = 'root', $pwd = '', $dbname = '', $dbprefix = '') {
		$this->system = 'sqlite';
		$this->errlogfile = 'data/errlog_'.$this->system.'.inc.php';
		// For SQLite: $dbname is the file path, $host is ignored
		$this->dbpath = $dbname;
		if (empty($this->dbpath)) {
			$this->dbpath = 'data/viscacha.db';
		}
		parent::__construct($host, $user, $pwd, $dbname, $dbprefix);
		$this->fieldType = array();
		$this->freeResult = false;
	}

	function setPersistence($persistence = false) {
		$this->persistence = false;
	}

	function version() {
		$this->open();
		return 'SQLite ' . $this->conn->query('SELECT sqlite_version()')->fetchColumn();
	}

	function affected_rows() {
		// PDO has no rowCount() on the connection object; use SQLite changes()
		if (!$this->conn) {
			return 0;
		}
		try {
			return (int) $this->conn->query('SELECT changes()')->fetchColumn();
		} catch (Exception $e) {
			return 0;
		}
	}

	function free_result($result = null) {
		return true;
	}

	function close() {
		$this->conn = null;
		$this->open = false;
		return true;
	}


	function connect($die = true) {
		try {
			$path = $this->dbpath;
			if ($path === null || $path === '') {
				$path = 'data/viscacha.db';
			}
			$isAbsolute = (isset($path[0]) && ($path[0] === '/' || $path[0] === '\\'))
				|| (strlen($path) >= 3 && $path[1] === ':' && ($path[2] === '/' || $path[2] === '\\'));
			if (!$isAbsolute) {
				$root = str_replace('\\', '/', getcwd());
				$path = rtrim($root, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
			}
			$path = str_replace('\\', '/', $path);
			while (strpos($path, '//') !== false) {
				$path = str_replace('//', '/', $path);
			}
			$dir = dirname($path);
			if (!is_dir($dir)) {
				@mkdir($dir, 0777, true);
			}
			$this->dbpath = $path;
			$this->database = $path;
			$this->conn = new PDO('sqlite:' . $path);
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
			$this->conn->exec('PRAGMA foreign_keys = OFF');
			// MySQL-compatible functions used by Viscacha queries
			$this->conn->sqliteCreateFunction('MD5', function($s) { return md5((string)$s); }, 1);
			$this->conn->sqliteCreateFunction('md5', function($s) { return md5((string)$s); }, 1);
			$this->conn->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); }, 0);
			$this->conn->sqliteCreateFunction('UNIX_TIMESTAMP', function($s = null) {
				if ($s === null || $s === '') return time();
				$t = strtotime($s);
				return $t === false ? 0 : $t;
			}, -1);
			$this->conn->sqliteCreateFunction('CONCAT', function() {
				return implode('', array_map('strval', func_get_args()));
			}, -1);
			$this->conn->sqliteCreateFunction('IFNULL', function($a, $b) { return $a !== null ? $a : $b; }, 2);
			$this->conn->sqliteCreateFunction('IF', function($c, $t, $f) { return $c ? $t : $f; }, 3);
			$this->conn->sqliteCreateFunction('REGEXP', function($pattern, $value) {
				if ($value === null) return 0;
				$p = str_replace('/', '\/', $pattern);
				return @preg_match('/' . $p . '/u', (string)$value) ? 1 : 0;
			}, 2);
			$this->conn->sqliteCreateFunction('RAND', function() { return mt_rand() / mt_getrandmax(); }, 0);
			$this->open = true;
			return true;
		} catch (Exception $e) {
			$this->conn = null;
			$this->open = false;
			if ($die) {
				trigger_error('Could not open SQLite database: ' . $e->getMessage(), E_USER_ERROR);
			}
			return false;
		}
	}


	function open($host=null,$user=null,$pwd=null,$dbname=null) {
		if ($dbname != null) {
			$this->database = $dbname;
			$this->dbpath = $dbname;
		}
		if (!$this->hasConnection()) {
			$this->connect(false);
		}
	}

	function hasConnection() {
		return ($this->conn instanceof PDO);
	}

	function isResultSet($result = null) {
		if ($result === null) $result = $this->result;
		return ($result instanceof PDOStatement);
	}

	function select_db($dbname = null) {
		return true; // SQLite has no select_db
	}

	function errno() {
		if ($this->conn) {
			$e = $this->conn->errorInfo();
			return isset($e[1]) ? (int)$e[1] : 0;
		}
		return 0;
	}

	function errstr() {
		if ($this->conn) {
			$e = $this->conn->errorInfo();
			return isset($e[2]) ? $e[2] : '';
		}
		return 'No connection';
	}

	/** Convert common MySQL DDL/DML bits to SQLite */
	function _adapt_sql($sql) {
		// Table options
		$sql = preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $sql);
		$sql = preg_replace('/\s*DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
		$sql = preg_replace('/\s*COLLATE\s*=\s*\w+/i', '', $sql);
		$sql = preg_replace('/\s*AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
		$sql = preg_replace('/\s*TYPE\s*=\s*\w+/i', '', $sql);
		$sql = preg_replace('/\s*PACK_KEYS\s*=\s*\d+/i', '', $sql);
		$sql = preg_replace('/\s*ROW_FORMAT\s*=\s*\w+/i', '', $sql);
		// Numeric / integer types
		$sql = preg_replace('/\bbigint\(\d+\)/i', 'INTEGER', $sql);
		$sql = preg_replace('/\bmediumint\(\d+\)/i', 'INTEGER', $sql);
		$sql = preg_replace('/\bsmallint\(\d+\)/i', 'INTEGER', $sql);
		$sql = preg_replace('/\btinyint\(\d+\)/i', 'INTEGER', $sql);
		$sql = preg_replace('/\bint\(\d+\)/i', 'INTEGER', $sql);
		$sql = preg_replace('/\s+unsigned/i', '', $sql);
		// auto_increment -> SQLite primary key
		$sql = preg_replace('/INTEGER\s+NOT\s+NULL\s+auto_increment/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
		$sql = preg_replace('/\bauto_increment/i', '', $sql);
		// Text / enum / set / char
		$sql = preg_replace('/\benum\s*\([^)]+\)/i', 'TEXT', $sql);
		$sql = preg_replace('/\bset\s*\([^)]+\)/i', 'TEXT', $sql);
		$sql = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $sql);
		$sql = preg_replace('/\b(mediumtext|longtext|tinytext)\b/i', 'TEXT', $sql);
		$sql = preg_replace('/\bchar\(\d+\)/', 'TEXT', $sql); // lowercase type only; keep CHAR() func
		$sql = preg_replace('/\b(tiny|medium|long)?blob\b/i', 'BLOB', $sql);
		// SQLite is strict: TEXT/BLOB NOT NULL without DEFAULT fails on incomplete INSERTs
		$sql = preg_replace('/\b(TEXT|BLOB|text|blob)\s+NOT\s+NULL(?!\s+[Dd][Ee][Ff][Aa][Uu][Ll][Tt])/i', 'TEXT NOT NULL default \'\'', $sql);
		// Secondary indexes (SQLite does not need them for basic install)
		$sql = preg_replace('/,\s*FULLTEXT\s+(?:KEY|INDEX)\s+[^(]*\([^)]+\)/i', '', $sql);
		$sql = preg_replace('/,\s*UNIQUE\s+(?:KEY|INDEX)\s+[^(]*\([^)]+\)/i', '', $sql);
		$sql = preg_replace('/,\s*(?:KEY|INDEX)\s+[^(]*\([^)]+\)/i', '', $sql);
		$sql = preg_replace('/\b(?:KEY|INDEX)\s+(?:`[^`]+`|"[^"]+"|\w+)\s*\([^)]+\)/i', '', $sql);
		// Drop explicit PRIMARY KEY clause when column already became PK AUTOINCREMENT
		$sql = preg_replace('/INTEGER PRIMARY KEY AUTOINCREMENT([^)]*?),\s*PRIMARY KEY\s*\([^)]+\)/is', 'INTEGER PRIMARY KEY AUTOINCREMENT$1', $sql);
		$sql = preg_replace('/,\s*PRIMARY KEY\s*\([^)]+\)/i', '', $sql);
		// Identifiers
		$sql = str_replace('`', '"', $sql);
		// Clean leftover commas / options
		$sql = preg_replace('/,\s*,/', ',', $sql);
		$sql = preg_replace('/,\s*\)/', ')', $sql);
		$sql = preg_replace('/\)\s*(?:[A-Z_]+\s*=\s*\S+\s*)+;/i', ');', $sql);
		$sql = preg_replace('/\)\s*;/', ');', $sql);


		// Quote SQLite reserved words used as column names (e.g. d.update)
		$reserved = array('update','replace','desc','order','group','table','index','plan','key','values');
		foreach ($reserved as $col) {
			// alias.column
			$sql = preg_replace('/\b([a-zA-Z_][a-zA-Z0-9_]*)\.(' . $col . ')\b/i', '$1."$2"', $sql);
		}

		// SQLite (older builds) do not allow LIMIT on UPDATE/DELETE
		if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $sql)) {
			$sql = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*$/i', '', $sql);
		}


		// MySQL: INSERT INTO t SET a=1, b=2  ->  INSERT INTO t (a, b) VALUES (1, 2)
		if (preg_match('/^\s*INSERT\s+INTO\s+([`"\[]?\w+[`"\]]?)\s+SET\s+(.+)$/is', $sql, $m)) {
			$table = $m[1];
			$assign = $m[2];
			$cols = array();
			$vals = array();
			// Split on commas not inside quotes
			$parts = preg_split('/,(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $assign);
			foreach ($parts as $part) {
				$part = trim($part);
				if ($part === '') continue;
				if (preg_match('/^([`"\[]?\w+[`"\]]?)\s*=\s*(.*)$/s', $part, $kv)) {
					$cols[] = $kv[1];
					$vals[] = trim($kv[2]);
				}
			}
			if (count($cols) > 0) {
				$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
			}
		}


		// MySQL string functions -> SQLite
		$sql = preg_replace('/\bLEFT\s*\(\s*([^,]+)\s*,\s*(\d+)\s*\)/i', 'substr($1, 1, $2)', $sql);
		$sql = preg_replace('/\bRIGHT\s*\(\s*([^,]+)\s*,\s*(\d+)\s*\)/i', 'substr($1, -$2)', $sql);
		$sql = preg_replace('/\bMID\s*\(\s*([^,]+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', 'substr($1, $2, $3)', $sql);
		$sql = preg_replace('/\bSUBSTRING\s*\(\s*([^,]+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', 'substr($1, $2, $3)', $sql);
		// REGEXP: SQLite has no REGEXP by default - approximate with GLOB/LIKE where simple
		// Keep REGEXP if UDF registered; otherwise leave (admin can avoid)


		// MySQL FULLTEXT MATCH...AGAINST -> SQLite-friendly approximation
		// MATCH (col) AGAINST ('a b') AS score  ->  (like-score) AS score
		if (stripos($sql, 'MATCH') !== false && stripos($sql, 'AGAINST') !== false) {
			$sql = preg_replace_callback(
				'/\bMATCH\s*\(([^)]+)\)\s*AGAINST\s*\(\s*\'([^\']*)\'\s*(?:IN\s+BOOLEAN\s+MODE)?\s*\)/i',
				function ($m) {
					$col = trim($m[1]);
					$words = preg_split('/\s+/', trim($m[2]));
					$parts = array();
					foreach ($words as $w) {
						$w = trim($w, "+-<>~\"'");
						if ($w === '' || strlen($w) < 2) continue;
						$w = str_replace("'", "''", $w);
						$parts[] = "({$col} LIKE '%{$w}%')";
					}
					if (count($parts) === 0) return '0';
					return '(' . implode(' OR ', $parts) . ')';
				},
				$sql
			);
			// Remove "> 0.6" style score thresholds left over
			$sql = preg_replace('/\)\s*>\s*0\.\d+/', ')', $sql);
		}

		// MySQL date functions
		$sql = preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', "CAST(strftime('%s','now') AS INTEGER)", $sql);
		$sql = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $sql);
		return $sql;
	}

	function query($sql, $die = true) {
		$this->open();
		if (!$this->hasConnection()) {
			if ($die) trigger_error($this->errstr(), E_USER_ERROR);
			return false;
		}
		# MySQL SHOW TABLES -> SQLite
		if (preg_match('/^\s*SHOW\s+TABLES/i', $sql)) {
			$sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
		}
		if (preg_match('/^\s*SHOW\s+COLUMNS\s+FROM\s+[\`\'"]?(\w+)/i', $sql, $m)) {
			$sql = 'PRAGMA table_info("' . $m[1] . '")';
		}
		$sql = $this->_adapt_sql($sql);
		$result = $this->conn->query($sql);
		if ($result === false) {
			$err = $this->errstr();
			if ($die && $err) {
				trigger_error($err . '<br /><pre>' . htmlspecialchars($sql) . '</pre>', E_USER_ERROR);
			}
			return false;
		}
		$this->result = $result;
		return $result;
	}


	/** Buffer all rows so num_rows + fetch_* stay consistent (MySQL-like) */
	function _buffer($result) {
		if (!$result) return null;
		if (!isset($result->_vc_rows)) {
			$result->_vc_rows = $result->fetchAll(PDO::FETCH_ASSOC);
			$result->_vc_idx = 0;
		}
		return $result;
	}

	function num_rows($result = null) {
		if (!$this->isResultSet($result)) $result = $this->result;
		if (!$result) return 0;
		$this->_buffer($result);
		return count($result->_vc_rows);
	}

	function insert_id() {
		return $this->conn ? (int)$this->conn->lastInsertId() : 0;
	}

	function data_seek($result = null, $pos = 0) {
		if (!$this->isResultSet($result)) $result = $this->result;
		if ($result && isset($result->_vc_rows)) {
			$result->_vc_idx = $pos;
			return true;
		}
		return false;
	}

	function fetch_object($result = null) {
		$row = $this->fetch_assoc($result);
		return $row ? (object)$row : false;
	}

	function fetch_num($result = null) {
		if (!$this->isResultSet($result)) $result = $this->result;
		if (!$result) return false;
		$this->_buffer($result);
		if ($result->_vc_idx >= count($result->_vc_rows)) return false;
		return array_values($result->_vc_rows[$result->_vc_idx++]);
	}

	function fetch_assoc($result = null) {
		if (!$this->isResultSet($result)) $result = $this->result;
		if (!$result) return false;
		$this->_buffer($result);
		if ($result->_vc_idx >= count($result->_vc_rows)) return false;
		return $result->_vc_rows[$result->_vc_idx++];
	}

	function escape_string($value) {
		$this->open();
		if ($this->conn) {
			$q = $this->conn->quote($value);
			return substr($q, 1, -1);
		}
		return str_replace("'", "''", $value);
	}

	function num_fields($result = null) {
		if (!$this->isResultSet($result)) $result = $this->result;
		return $result ? $result->columnCount() : 0;
	}

	function field_len($result = null, $k = null) { return null; }
	function field_type($result = null, $k = null) { return null; }
	function field_name($result = null, $k = null) {
		if (!$this->isResultSet($result)) $result = $this->result;
		if (!$result) return null;
		$meta = $result->getColumnMeta($k);
		return $meta ? $meta['name'] : null;
	}
	function field_table($result = null, $k = null) { return null; }

	function list_tables($db = null) {
		$result = $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
		$tables = array();
		if ($result) {
			while ($row = $this->fetch_num($result)) {
				$tables[] = $row[0];
			}
		}
		return $tables;
	}

	function list_fields($table) {
		$result = $this->query('PRAGMA table_info("' . str_replace('"', '', $table) . '")');
		$fields = array();
		if ($result) {
			while ($row = $this->fetch_assoc($result)) {
				$fields[] = $row['name'];
			}
		}
		return $fields;
	}

}
