<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/


function adminer_errors($errno, $errstr) {
	return !!preg_match('~^(Trying to access array offset on value of type null|Undefined array key)~', $errstr);
}

error_reporting(6135); // errors and warnings
set_error_handler('adminer_errors', E_WARNING);



// disable filter.default
$filter = !preg_match('~^(unsafe_raw)?$~', ini_get("filter.default"));
if ($filter || ini_get("filter.default_flags")) {
	foreach (array('_GET', '_POST', '_COOKIE', '_SERVER') as $val) {
		$unsafe = filter_input_array(constant("INPUT$val"), FILTER_UNSAFE_RAW);
		if ($unsafe) {
			$$val = $unsafe;
		}
	}
}

if (function_exists("mb_internal_encoding")) {
	mb_internal_encoding("8bit");
}


/** Get database connection
* @return Min_DB
*/
function connection() {
	// can be used in customization, $connection is minified
	global $connection;
	return $connection;
}

/** Get Adminer object
* @return Adminer
*/
function adminer() {
	global $adminer;
	return $adminer;
}

/** Get Adminer version
* @return string
*/
function version() {
	global $VERSION;
	return $VERSION;
}

/** Unescape database identifier
* @param string text inside ``
* @return string
*/
function idf_unescape($idf) {
	if (!preg_match('~^[`\'"]~', $idf)) {
		return $idf;
	}
	$last = substr($idf, -1);
	return str_replace($last . $last, $last, substr($idf, 1, -1));
}

/** Escape string to use inside ''
* @param string
* @return string
*/
function escape_string($val) {
	return substr(q($val), 1, -1);
}

/** Remove non-digits from a string
* @param string
* @return string
*/
function number($val) {
	return preg_replace('~[^0-9]+~', '', $val);
}

/** Get regular expression to match numeric types
* @return string
*/
function number_type() {
	return '((?<!o)int(?!er)|numeric|real|float|double|decimal|money)'; // not point, not interval
}

/** Disable magic_quotes_gpc
* @param array e.g. (&$_GET, &$_POST, &$_COOKIE)
* @param bool whether to leave values as is
* @return null modified in place
*/
function remove_slashes($process, $filter = false) {
	if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {
		while (list($key, $val) = each($process)) {
			foreach ($val as $k => $v) {
				unset($process[$key][$k]);
				if (is_array($v)) {
					$process[$key][stripslashes($k)] = $v;
					$process[] = &$process[$key][stripslashes($k)];
				} else {
					$process[$key][stripslashes($k)] = ($filter ? $v : stripslashes($v));
				}
			}
		}
	}
}

/** Escape or unescape string to use inside form []
* @param string
* @param bool
* @return string
*/
function bracket_escape($idf, $back = false) {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3', '"' => ':4');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Check if connection has at least the given version
* @param string required version
* @param string required MariaDB version
* @param Min_DB defaults to $connection
* @return bool
*/
function min_version($version, $maria_db = "", $connection2 = null) {
	global $connection;
	if (!$connection2) {
		$connection2 = $connection;
	}
	$server_info = $connection2->server_info;
	if ($maria_db && preg_match('~([\d.]+)-MariaDB~', $server_info, $match)) {
		$server_info = $match[1];
		$version = $maria_db;
	}
	return (version_compare($server_info, $version) >= 0);
}

/** Get connection charset
* @param Min_DB
* @return string
*/
function charset($connection) {
	return (min_version("5.5.3", 0, $connection) ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
}

/** Return <script> element
* @param string
* @param string
* @return string
*/
function script($source, $trailing = "\n") {
	return "<script" . nonce() . ">$source</script>$trailing";
}

/** Return <script src> element
* @param string
* @return string
*/
function script_src($url) {
	return "<script src='" . h($url) . "'" . nonce() . "></script>\n";
}

/** Get a nonce="" attribute with CSP nonce
* @return string
*/
function nonce() {
	return ' nonce="' . get_nonce() . '"';
}

/** Get a target="_blank" attribute
* @return string
*/
function target_blank() {
	return ' target="_blank" rel="noreferrer noopener"';
}

/** Escape for HTML
* @param string
* @return string
*/
function h($string) {
	return str_replace("\0", "&#0;", htmlspecialchars($string, ENT_QUOTES, 'utf-8'));
}

/** Convert \n to <br>
* @param string
* @return string
*/
function nl_br($string) {
	return str_replace("\n", "<br>", $string); // nl2br() uses XHTML before PHP 5.3
}

/** Generate HTML checkbox
* @param string
* @param string
* @param bool
* @param string
* @param string
* @param string
* @param string
* @return string
*/
function checkbox($name, $value, $checked, $label = "", $onclick = "", $class = "", $labelled_by = "") {
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'"
		. ($checked ? " checked" : "")
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">"
		. ($onclick ? script("qsl('input').onclick = function () { $onclick };", "") : "")
	;
	return ($label != "" || $class ? "<label" . ($class ? " class='$class'" : "") . ">$return" . h($label) . "</label>" : $return);
}

/** Generate list of HTML options
* @param array array of strings or arrays (creates optgroup)
* @param mixed
* @param bool always use array keys for value="", otherwise only string keys are used
* @return string
*/
function optionlist($options, $selected = null, $use_keys = false) {
	$return = "";
	foreach ($options as $k => $v) {
		$opts = array($k => $v);
		if (is_array($v)) {
			$return .= '<optgroup label="' . h($k) . '">';
			$opts = $v;
		}
		foreach ($opts as $key => $val) {
			$return .= '<option' . ($use_keys || is_string($key) ? ' value="' . h($key) . '"' : '') . (($use_keys || is_string($key) ? (string) $key : $val) === $selected ? ' selected' : '') . '>' . h($val);
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

/** Generate HTML radio list
* @param string
* @param array
* @param string
* @param string true for no onchange, false for radio
* @param string
* @return string
*/
function html_select($name, $options, $value = "", $onchange = true, $labelled_by = "") {
	if ($onchange) {
		return "<select name='" . h($name) . "'"
			. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
			. ">" . optionlist($options, $value) . "</select>"
			. (is_string($onchange) ? script("qsl('select').onchange = function () { $onchange };", "") : "")
		;
	}
	$return = "";
	foreach ($options as $key => $val) {
		$return .= "<label><input type='radio' name='" . h($name) . "' value='" . h($key) . "'" . ($key == $value ? " checked" : "") . ">" . h($val) . "</label>";
	}
	return $return;
}

/** Generate HTML <select> or <input> if $options are empty
* @param string
* @param array
* @param string
* @param string
* @param string
* @return string
*/
function select_input($attrs, $options, $value = "", $onchange = "", $placeholder = "") {
	$tag = ($options ? "select" : "input");
	return "<$tag$attrs" . ($options
		? "><option value=''>$placeholder" . optionlist($options, $value, true) . "</select>"
		: " size='10' value='" . h($value) . "' placeholder='$placeholder'>"
	) . ($onchange ? script("qsl('$tag').onchange = $onchange;", "") : ""); //! use oninput for input
}

/** Get onclick confirmation
* @param string
* @param string
* @return string
*/
function confirm($message = "", $selector = "qsl('input')") {
	return script("$selector.onclick = function () { return confirm('" . ($message ? js_escape($message) : lang(0)) . "'); };", "");
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param string
* @param string
* @param bool
* @return null
*/
function print_fieldset($id, $legend, $visible = false) {
	echo "<fieldset><legend>";
	echo "<a href='#fieldset-$id'>$legend</a>";
	echo script("qsl('a').onclick = partial(toggle, 'fieldset-$id');", "");
	echo "</legend>";
	echo "<div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}

/** Return class='active' if $bold is true
* @param bool
* @param string
* @return string
*/
function bold($bold, $class = "") {
	return ($bold ? " class='active $class'" : ($class ? " class='$class'" : ""));
}

/** Generate class for odd rows
* @param string return this for odd rows, empty to reset counter
* @return string
*/
function odd($return = ' class="odd"') {
	static $i = 0;
	if (!$return) { // reset counter
		$i = -1;
	}
	return ($i++ % 2 ? $return : '');
}

/** Escape string for JavaScript apostrophes
* @param string
* @return string
*/
function js_escape($string) {
	return addcslashes($string, "\r\n'\\/"); // slash for <script>
}

/** Print one row in JSON object
* @param string or "" to close the object
* @param string
* @return null
*/
function json_row($key, $val = null) {
	static $first = true;
	if ($first) {
		echo "{";
	}
	if ($key != "") {
		echo ($first ? "" : ",") . "\n\t\"" . addcslashes($key, "\r\n\t\"\\/") . '": ' . ($val !== null ? '"' . addcslashes($val, "\r\n\"\\/") . '"' : 'null');
		$first = false;
	} else {
		echo "\n}\n";
		$first = true;
	}
}

/** Get INI boolean value
* @param string
* @return bool
*/
function ini_bool($ini) {
	$val = ini_get($ini);
	return (preg_match('~^(on|true|yes)$~i', $val) || (int) $val); // boolean values set by php_value are strings
}

/** Check if SID is neccessary
* @return bool
*/
function sid() {
	static $return;
	if ($return === null) { // restart_session() defines SID
		$return = (SID && !($_COOKIE && ini_bool("session.use_cookies"))); // $_COOKIE - don't pass SID with permanent login
	}
	return $return;
}

/** Set password to session
* @param string
* @param string
* @param string
* @param string
* @return null
*/
function set_password($vendor, $server, $username, $password) {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

/** Get password from session
* @return string or null for missing password or false for expired password
*/
function get_password() {
	$return = get_session("pwds");
	if (is_array($return)) {
		$return = ($_COOKIE["adminer_key"]
			? decrypt_string($return[0], $_COOKIE["adminer_key"])
			: false
		);
	}
	return $return;
}

/** Shortcut for $connection->quote($string)
* @param string
* @return string
*/
function q($string) {
	global $connection;
	return $connection->quote($string);
}

/** Get list of values from database
* @param string
* @param mixed
* @return array
*/
function get_vals($query, $column = 0) {
	global $connection;
	$return = array();
	$result = $connection->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Get keys from first column and values from second
* @param string
* @param Min_DB
* @param bool
* @return array
*/
function get_key_vals($query, $connection2 = null, $set_keys = true) {
	global $connection;
	if (!is_object($connection2)) {
		$connection2 = $connection;
	}
	$return = array();
	$result = $connection2->query($query);
	if (is_object($result)) {
		while ($row = $result->fetch_row()) {
			if ($set_keys) {
				$return[$row[0]] = $row[1];
			} else {
				$return[] = $row[0];
			}
		}
	}
	return $return;
}

/** Get all rows of result
* @param string
* @param Min_DB
* @param string
* @return array of associative arrays
*/
function get_rows($query, $connection2 = null, $error = "<p class='error'>") {
	global $connection;
	$conn = (is_object($connection2) ? $connection2 : $connection);
	$return = array();
	$result = $conn->query($query);
	if (is_object($result)) { // can return true
		while ($row = $result->fetch_assoc()) {
			$return[] = $row;
		}
	} elseif (!$result && !is_object($connection2) && $error && defined("PAGE_HEADER")) {
		echo $error . error() . "\n";
	}
	return $return;
}

/** Find unique identifier of a row
* @param array
* @param array result of indexes()
* @return array or null if there is no unique identifier
*/
function unique_array($row, $indexes) {
	foreach ($indexes as $index) {
		if (preg_match("~PRIMARY|UNIQUE~", $index["type"])) {
			$return = array();
			foreach ($index["columns"] as $key) {
				if (!isset($row[$key])) { // NULL is ambiguous
					continue 2;
				}
				$return[$key] = $row[$key];
			}
			return $return;
		}
	}
}

/** Escape column key used in where()
* @param string
* @return string
*/
function escape_key($key) {
	if (preg_match('(^([\w(]+)(' . str_replace("_", ".*", preg_quote(idf_escape("_"))) . ')([ \w)]+)$)', $key, $match)) { //! columns looking like functions
		return $match[1] . idf_escape(idf_unescape($match[2])) . $match[3]; //! SQL injection
	}
	return idf_escape($key);
}

/** Create SQL condition from parsed query string
* @param array parsed query string
* @param array
* @return string
*/
function where($where, $fields = array()) {
	global $connection, $jush;
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, 1); // 1 - back
		$column = escape_key($key);
		$return[] = $column
			. ($jush == "sql" && is_numeric($val) && preg_match('~\.~', $val) ? " LIKE " . q($val) // LIKE because of floats but slow with ints
				: ($jush == "mssql" ? " LIKE " . q(preg_replace('~[_%[]~', '[\0]', $val)) // LIKE because of text
				: " = " . unconvert_field($fields[$key], q($val))
			))
		; //! enum and set
		if ($jush == "sql" && preg_match('~char|text~', $fields[$key]["type"]) && preg_match("~[^ -@]~", $val)) { // not just [a-z] to catch non-ASCII characters
			$return[] = "$column = " . q($val) . " COLLATE " . charset($connection) . "_bin";
		}
	}
	foreach ((array) $where["null"] as $key) {
		$return[] = escape_key($key) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param string
* @param array
* @return string
*/
function where_check($val, $fields = array()) {
	parse_str($val, $check);
	remove_slashes(array(&$check));
	return where($check, $fields);
}

/** Create query string where condition from value
* @param int condition order
* @param string column identifier
* @param string
* @param string
* @return string
*/
function where_link($i, $column, $value, $operator = "=") {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=" . urlencode(($value !== null ? $operator : "IS NULL")) . "&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Get select clause for convertible fields
* @param array
* @param array
* @param array
* @return string
*/
function convert_fields($columns, $fields, $select = array()) {
	$return = "";
	foreach ($columns as $key => $val) {
		if ($select && !in_array(idf_escape($key), $select)) {
			continue;
		}
		$as = convert_field($fields[$key]);
		if ($as) {
			$return .= ", $as AS " . idf_escape($key);
		}
	}
	return $return;
}

/** Set cookie valid on current path
* @param string
* @param string
* @param int number of seconds, 0 for session cookie
* @return bool
*/
function cookie($name, $value, $lifetime = 2592000) { // 2592000 - 30 days
	global $HTTPS;
	return header("Set-Cookie: $name=" . urlencode($value)
		. ($lifetime ? "; expires=" . gmdate("D, d M Y H:i:s", time() + $lifetime) . " GMT" : "")
		. "; path=" . preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"])
		. ($HTTPS ? "; secure" : "")
		. "; HttpOnly; SameSite=lax",
		false);
}

/** Restart stopped session
* @return null
*/
function restart_session() {
	if (!ini_bool("session.use_cookies")) {
		session_start();
	}
}

/** Stop session if possible
* @param bool
* @return null
*/
function stop_session($force = false) {
	$use_cookies = ini_bool("session.use_cookies");
	if (!$use_cookies || $force) {
		session_write_close(); // improves concurrency if a user opens several pages at once, may be restarted later
		if ($use_cookies && @ini_set("session.use_cookies", false) === false) { // @ - may be disabled
			session_start();
		}
	}
}

/** Get session variable for current server
* @param string
* @return mixed
*/
function &get_session($key) {
	return $_SESSION[$key][DRIVER][SERVER][$_GET["username"]];
}

/** Set session variable for current server
* @param string
* @param mixed
* @return mixed
*/
function set_session($key, $val) {
	$_SESSION[$key][DRIVER][SERVER][$_GET["username"]] = $val; // used also in auth.inc.php
}

/** Get authenticated URL
* @param string
* @param string
* @param string
* @param string
* @return string
*/
function auth_url($vendor, $server, $username, $db = null) {
	global $drivers;
	preg_match('~([^?]*)\??(.*)~', remove_from_uri(implode("|", array_keys($drivers)) . "|username|" . ($db !== null ? "db|" : "") . session_name()), $match);
	return "$match[1]?"
		. (sid() ? SID . "&" : "")
		. ($vendor != "server" || $server != "" ? urlencode($vendor) . "=" . urlencode($server) . "&" : "")
		. "username=" . urlencode($username)
		. ($db != "" ? "&db=" . urlencode($db) : "")
		. ($match[2] ? "&$match[2]" : "")
	;
}

/** Find whether it is an AJAX request
* @return bool
*/
function is_ajax() {
	return ($_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest");
}

/** Send Location header and exit
* @param string null to only set a message
* @param string
* @return null
*/
function redirect($location, $message = null) {
	if ($message !== null) {
		restart_session();
		$_SESSION["messages"][preg_replace('~^[^?]*~', '', ($location !== null ? $location : $_SERVER["REQUEST_URI"]))][] = $message;
	}
	if ($location !== null) {
		if ($location == "") {
			$location = ".";
		}
		header("Location: $location");
		exit;
	}
}

/** Execute query and redirect if successful
* @param string
* @param string
* @param string
* @param bool
* @param bool
* @param bool
* @param string
* @return bool
*/
function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false, $time = "") {
	global $connection, $error, $adminer;
	if ($execute) {
		$start = microtime(true);
		$failed = !$connection->query($query);
		$time = format_time($start);
	}
	$sql = "";
	if ($query) {
		$sql = $adminer->messageQuery($query, $time, $failed);
	}
	if ($failed) {
		$error = error() . $sql . script("messagesPrint();");
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

/** Execute and remember query
* @param string or null to return remembered queries, end with ';' to use DELIMITER
* @return Min_Result or array($queries, $time) if $query = null
*/
function queries($query) {
	global $connection;
	static $queries = array();
	static $start;
	if (!$start) {
		$start = microtime(true);
	}
	if ($query === null) {
		// return executed queries
		return array(implode("\n", $queries), format_time($start));
	}
	$queries[] = (preg_match('~;$~', $query) ? "DELIMITER ;;\n$query;\nDELIMITER " : $query) . ";";
	return $connection->query($query);
}

/** Apply command to all array items
* @param string
* @param array
* @param callback
* @return bool
*/
function apply_queries($query, $tables, $escape = 'table') {
	foreach ($tables as $table) {
		if (!queries("$query " . $escape($table))) {
			return false;
		}
	}
	return true;
}

/** Redirect by remembered queries
* @param string
* @param string
* @param bool
* @return bool
*/
function queries_redirect($location, $message, $redirect) {
	list($queries, $time) = queries(null);
	return query_redirect($queries, $location, $message, $redirect, false, !$redirect, $time);
}

/** Format elapsed time
* @param float output of microtime(true)
* @return string HTML code
*/
function format_time($start) {
	return lang(1, max(0, microtime(true) - $start));
}

/** Get relative REQUEST_URI
* @return string
*/
function relative_uri() {
	return str_replace(":", "%3a", preg_replace('~^[^?]*/([^?]*)~', '\1', $_SERVER["REQUEST_URI"]));
}

/** Remove parameter from query string
* @param string
* @return string
*/
function remove_from_uri($param = "") {
	return substr(preg_replace("~(?<=[?&])($param" . (SID ? "" : "|" . session_name()) . ")=[^&]*&~", '', relative_uri() . "&"), 0, -1);
}

/** Generate page number for pagination
* @param int
* @param int
* @return string
*/
function pagination($page, $current) {
	return " " . ($page == $current
		? $page + 1
		: '<a href="' . h(remove_from_uri("page") . ($page ? "&page=$page" . ($_GET["next"] ? "&next=" . urlencode($_GET["next"]) : "") : "")) . '">' . ($page + 1) . "</a>"
	);
}

/** Get file contents from $_FILES
* @param string
* @param bool
* @return mixed int for error, string otherwise
*/
function get_file($key, $decompress = false) {
	$file = $_FILES[$key];
	if (!$file) {
		return null;
	}
	foreach ($file as $key => $val) {
		$file[$key] = (array) $val;
	}
	$return = '';
	foreach ($file["error"] as $key => $error) {
		if ($error) {
			return $error;
		}
		$name = $file["name"][$key];
		$tmp_name = $file["tmp_name"][$key];
		$content = file_get_contents($decompress && preg_match('~\.gz$~', $name)
			? "compress.zlib://$tmp_name"
			: $tmp_name
		); //! may not be reachable because of open_basedir
		if ($decompress) {
			$start = substr($content, 0, 3);
			if (function_exists("iconv") && preg_match("~^\xFE\xFF|^\xFF\xFE~", $start, $regs)) { // not ternary operator to save memory
				$content = iconv("utf-16", "utf-8", $content);
			} elseif ($start == "\xEF\xBB\xBF") { // UTF-8 BOM
				$content = substr($content, 3);
			}
			$return .= $content . "\n\n";
		} else {
			$return .= $content;
		}
	}
	//! support SQL files not ending with semicolon
	return $return;
}

/** Determine upload error
* @param int
* @return string
*/
function upload_error($error) {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : 0); // post_max_size is checked in index.php
	return ($error ? lang(2) . ($max_size ? " " . lang(3, $max_size) : "") : lang(4));
}

/** Create repeat pattern for preg
* @param string
* @param int
* @return string
*/
function repeat_pattern($pattern, $length) {
	// fix for Compilation failed: number too big in {} quantifier
	return str_repeat("$pattern{0,65535}", $length / 65535) . "$pattern{0," . ($length % 65535) . "}"; // can create {0,0} which is OK
}

/** Check whether the string is in UTF-8
* @param string
* @return bool
*/
function is_utf8($val) {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\0-\x8\xB\xC\xE-\x1F]~', $val));
}

/** Shorten UTF-8 string
* @param string
* @param int
* @param string
* @return string escaped string with appended ...
*/
function shorten_utf8($string, $length = 80, $suffix = "") {
	if (!preg_match("(^(" . repeat_pattern("[\t\r\n -\x{10FFFF}]", $length) . ")($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^(" . repeat_pattern("[\t\r\n -~]", $length) . ")($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>…</i>");
}

/** Format decimal number
* @param int
* @return string
*/
function format_number($val) {
	return strtr(number_format($val, 0, ".", lang(5)), preg_split('~~u', lang(6), -1, PREG_SPLIT_NO_EMPTY));
}

/** Generate friendly URL
* @param string
* @return string
*/
function friendly_url($val) {
	// used for blobs and export
	return preg_replace('~[^a-z0-9_]~i', '-', $val);
}

/** Print hidden fields
* @param array
* @param array
* @param string
* @return bool
*/
function hidden_fields($process, $ignore = array(), $prefix = '') {
	$return = false;
	foreach ($process as $key => $val) {
		if (!in_array($key, $ignore)) {
			if (is_array($val)) {
				hidden_fields($val, array(), $key);
			} else {
				$return = true;
				echo '<input type="hidden" name="' . h($prefix ? $prefix . "[$key]" : $key) . '" value="' . h($val) . '">';
			}
		}
	}
	return $return;
}

/** Print hidden fields for GET forms
* @return null
*/
function hidden_fields_get() {
	echo (sid() ? '<input type="hidden" name="' . session_name() . '" value="' . h(session_id()) . '">' : '');
	echo (SERVER !== null ? '<input type="hidden" name="' . DRIVER . '" value="' . h(SERVER) . '">' : "");
	echo '<input type="hidden" name="username" value="' . h($_GET["username"]) . '">';
}

/** Get status of a single table and fall back to name on error
* @param string
* @param bool
* @return array
*/
function table_status1($table, $fast = false) {
	$return = table_status($table, $fast);
	return ($return ? $return : array("Name" => $table));
}

/** Find out foreign keys for each column
* @param string
* @return array array($col => array())
*/
function column_foreign_keys($table) {
	global $adminer;
	$return = array();
	foreach ($adminer->foreignKeys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Print enum input field
* @param string "radio"|"checkbox"
* @param string
* @param array
* @param mixed int|string|array
* @param string
* @return null
*/
function enum_input($type, $attrs, $field, $value, $empty = null) {
	global $adminer;
	preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
	$return = ($empty !== null ? "<label><input type='$type'$attrs value='$empty'" . ((is_array($value) ? in_array($empty, $value) : $value === 0) ? " checked" : "") . "><i>" . lang(7) . "</i></label>" : "");
	foreach ($matches[1] as $i => $val) {
		$val = stripcslashes(str_replace("''", "'", $val));
		$checked = (is_int($value) ? $value == $i+1 : (is_array($value) ? in_array($i+1, $value) : $value === $val));
		$return .= " <label><input type='$type'$attrs value='" . ($i+1) . "'" . ($checked ? ' checked' : '') . '>' . h($adminer->editVal($val, $field)) . '</label>';
	}
	return $return;
}

/** Print edit input field
* @param array one field from fields()
* @param mixed
* @param string
* @return null
*/
function input($field, $value, $function) {
	global $types, $adminer, $jush;
	$name = h(bracket_escape($field["field"]));
	echo "<td class='function'>";
	if (is_array($value) && !$function) {
		$args = array($value);
		if (version_compare(PHP_VERSION, 5.4) >= 0) {
			$args[] = JSON_PRETTY_PRINT;
		}
		$value = call_user_func_array('json_encode', $args); //! requires PHP 5.2
		$function = "json";
	}
	$reset = ($jush == "mssql" && $field["auto_increment"]);
	if ($reset && !$_POST["save"]) {
		$function = null;
	}
	$functions = (isset($_GET["select"]) || $reset ? array("orig" => lang(8)) : array()) + $adminer->editFunctions($field);
	$attrs = " name='fields[$name]'";
	if ($field["type"] == "enum") {
		echo h($functions[""]) . "<td>" . $adminer->editInput($_GET["edit"], $field, $attrs, $value);
	} else {
		$has_function = (in_array($function, $functions) || isset($functions[$function]));
		echo (count($functions) > 1
			? "<select name='function[$name]'>" . optionlist($functions, $function === null || $has_function ? $function : "") . "</select>"
				. on_help("getTarget(event).value.replace(/^SQL\$/, '')", 1)
				. script("qsl('select').onchange = functionChange;", "")
			: h(reset($functions))
		) . '<td>';
		$input = $adminer->editInput($_GET["edit"], $field, $attrs, $value); // usage in call is without a table
		if ($input != "") {
			echo $input;
		} elseif (preg_match('~bool~', $field["type"])) {
			echo "<input type='hidden'$attrs value='0'>" .
				"<input type='checkbox'" . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? " checked='checked'" : "") . "$attrs value='1'>";
		} elseif ($field["type"] == "set") { //! 64 bits
			preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $i => $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$checked = (is_int($value) ? ($value >> $i) & 1 : in_array($val, explode(",", $value), true));
				echo " <label><input type='checkbox' name='fields[$name][$i]' value='" . (1 << $i) . "'" . ($checked ? ' checked' : '') . ">" . h($adminer->editVal($val, $field)) . '</label>';
			}
		} elseif (preg_match('~blob|bytea|raw|file~', $field["type"]) && ini_bool("file_uploads")) {
			echo "<input type='file' name='fields-$name'>";
		} elseif (($text = preg_match('~text|lob|memo~i', $field["type"])) || preg_match("~\n~", $value)) {
			if ($text && $jush != "sqlite") {
				$attrs .= " cols='50' rows='12'";
			} else {
				$rows = min(12, substr_count($value, "\n") + 1);
				$attrs .= " cols='30' rows='$rows'" . ($rows == 1 ? " style='height: 1.2em;'" : ""); // 1.2em - line-height
			}
			echo "<textarea$attrs>" . h($value) . '</textarea>';
		} elseif ($function == "json" || preg_match('~^jsonb?$~', $field["type"])) {
			echo "<textarea$attrs cols='50' rows='12' class='jush-js'>" . h($value) . '</textarea>';
		} else {
			// int(3) is only a display hint
			$maxlength = (!preg_match('~int~', $field["type"]) && preg_match('~^(\d+)(,(\d+))?$~', $field["length"], $match) ? ((preg_match("~binary~", $field["type"]) ? 2 : 1) * $match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0)) : ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0));
			if ($jush == 'sql' && min_version(5.6) && preg_match('~time~', $field["type"])) {
				$maxlength += 7; // microtime
			}
			// type='date' and type='time' display localized value which may be confusing, type='datetime' uses 'T' as date and time separator
			echo "<input"
				. ((!$has_function || $function === "") && preg_match('~(?<!o)int(?!er)~', $field["type"]) && !preg_match('~\[\]~', $field["full_type"]) ? " type='number'" : "")
				. " value='" . h($value) . "'" . ($maxlength ? " data-maxlength='$maxlength'" : "")
				. (preg_match('~char|binary~', $field["type"]) && $maxlength > 20 ? " size='40'" : "")
				. "$attrs>"
			;
		}
		echo $adminer->editHint($_GET["edit"], $field, $value);
		// skip 'original'
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		if ($first) {
			echo script("mixin(qsl('td'), {onchange: partial(skipOriginal, $first), oninput: function () { this.onchange(); }});");
		}
	}
}

/** Process edit input field
* @param one field from fields()
* @return string or false to leave the original value
*/
function process_input($field) {
	global $adminer, $driver;
	$idf = bracket_escape($field["field"]);
	$function = $_POST["function"][$idf];
	$value = $_POST["fields"][$idf];
	if ($field["type"] == "enum") {
		if ($value == -1) {
			return false;
		}
		if ($value == "") {
			return "NULL";
		}
		return +$value;
	}
	if ($field["auto_increment"] && $value == "") {
		return null;
	}
	if ($function == "orig") {
		return (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? idf_escape($field["field"]) : false);
	}
	if ($function == "NULL") {
		return "NULL";
	}
	if ($field["type"] == "set") {
		return array_sum((array) $value);
	}
	if ($function == "json") {
		$function = "";
		$value = json_decode($value, true);
		if (!is_array($value)) {
			return false; //! report errors
		}
		return $value;
	}
	if (preg_match('~blob|bytea|raw|file~', $field["type"]) && ini_bool("file_uploads")) {
		$file = get_file("fields-$idf");
		if (!is_string($file)) {
			return false; //! report errors
		}
		return $driver->quoteBinary($file);
	}
	return $adminer->processInput($field, $value, $function);
}

/** Compute fields() from $_POST edit data
* @return array
*/
function fields_from_edit() {
	global $driver;
	$return = array();
	foreach ((array) $_POST["field_keys"] as $key => $val) {
		if ($val != "") {
			$val = bracket_escape($val);
			$_POST["function"][$val] = $_POST["field_funs"][$key];
			$_POST["fields"][$val] = $_POST["field_vals"][$key];
		}
	}
	foreach ((array) $_POST["fields"] as $key => $val) {
		$name = bracket_escape($key, 1); // 1 - back
		$return[$name] = array(
			"field" => $name,
			"privileges" => array("insert" => 1, "update" => 1),
			"null" => 1,
			"auto_increment" => ($key == $driver->primary),
		);
	}
	return $return;
}

/** Print results of search in all tables
* @uses $_GET["where"][0]
* @uses $_POST["tables"]
* @return null
*/
function search_tables() {
	global $adminer, $connection;
	$_GET["where"][0]["val"] = $_POST["query"];
	$sep = "<ul>\n";
	foreach (table_status('', true) as $table => $table_status) {
		$name = $adminer->tableName($table_status);
		if (isset($table_status["Engine"]) && $name != "" && (!$_POST["tables"] || in_array($table, $_POST["tables"]))) {
			$result = $connection->query("SELECT" . limit("1 FROM " . table($table), " WHERE " . implode(" AND ", $adminer->selectSearchProcess(fields($table), array())), 1));
			if (!$result || $result->fetch_row()) {
				$print = "<a href='" . h(ME . "select=" . urlencode($table) . "&where[0][op]=" . urlencode($_GET["where"][0]["op"]) . "&where[0][val]=" . urlencode($_GET["where"][0]["val"])) . "'>$name</a>";
				echo "$sep<li>" . ($result ? $print : "<p class='error'>$print: " . error()) . "\n";
				$sep = "";
			}
		}
	}
	echo ($sep ? "<p class='message'>" . lang(9) : "</ul>") . "\n";
}

/** Send headers for export
* @param string
* @param bool
* @return string extension
*/
function dump_headers($identifier, $multi_table = false) {
	global $adminer;
	$return = $adminer->dumpHeaders($identifier, $multi_table);
	$output = $_POST["output"];
	if ($output != "text") {
		header("Content-Disposition: attachment; filename=" . $adminer->dumpFilename($identifier) . ".$return" . ($output != "file" && preg_match('~^[0-9a-z]+$~', $output) ? ".$output" : ""));
	}
	session_write_close();
	ob_flush();
	flush();
	return $return;
}

/** Print CSV row
* @param array
* @return null
*/
function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match('~["\n,;\t]|^0|\.\d*0$~', $val) || $val === "") {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
}

/** Apply SQL function
* @param string
* @param string escaped column identifier
* @return string
*/
function apply_sql_function($function, $column) {
	return ($function ? ($function == "unixepoch" ? "DATETIME($column, '$function')" : ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)") : $column);
}

/** Get path of the temporary directory
* @return string
*/
function get_temp_dir() {
	$return = ini_get("upload_tmp_dir"); // session_save_path() may contain other storage path
	if (!$return) {
		if (function_exists('sys_get_temp_dir')) {
			$return = sys_get_temp_dir();
		} else {
			$filename = @tempnam("", ""); // @ - temp directory can be disabled by open_basedir
			if (!$filename) {
				return false;
			}
			$return = dirname($filename);
			unlink($filename);
		}
	}
	return $return;
}

/** Open and exclusively lock a file
* @param string
* @return resource or null for error
*/
function file_open_lock($filename) {
	$fp = @fopen($filename, "r+"); // @ - may not exist
	if (!$fp) { // c+ is available since PHP 5.2.6
		$fp = @fopen($filename, "w"); // @ - may not be writable
		if (!$fp) {
			return;
		}
		chmod($filename, 0660);
	}
	flock($fp, LOCK_EX);
	return $fp;
}

/** Write and unlock a file
* @param resource
* @param string
*/
function file_write_unlock($fp, $data) {
	rewind($fp);
	fwrite($fp, $data);
	ftruncate($fp, strlen($data));
	flock($fp, LOCK_UN);
	fclose($fp);
}

/** Read password from file adminer.key in temporary directory or create one
* @param bool
* @return string or false if the file can not be created
*/
function password_file($create) {
	$filename = get_temp_dir() . "/adminer.key";
	$return = @file_get_contents($filename); // @ - may not exist
	if ($return || !$create) {
		return $return;
	}
	$fp = @fopen($filename, "w"); // @ - can have insufficient rights //! is not atomic
	if ($fp) {
		chmod($filename, 0660);
		$return = rand_string();
		fwrite($fp, $return);
		fclose($fp);
	}
	return $return;
}

/** Get a random string
* @return string 32 hexadecimal characters
*/
function rand_string() {
	return md5(uniqid(mt_rand(), true));
}

/** Format value to use in select
* @param string
* @param string
* @param array
* @param int
* @return string HTML
*/
function select_value($val, $link, $field, $text_length) {
	global $adminer;
	if (is_array($val)) {
		$return = "";
		foreach ($val as $k => $v) {
			$return .= "<tr>"
				. ($val != array_values($val) ? "<th>" . h($k) : "")
				. "<td>" . select_value($v, $link, $field, $text_length)
			;
		}
		return "<table cellspacing='0'>$return</table>";
	}
	if (!$link) {
		$link = $adminer->selectLink($val, $field);
	}
	if ($link === null) {
		if (is_mail($val)) {
			$link = "mailto:$val";
		}
		if (is_url($val)) {
			$link = $val; // IE 11 and all modern browsers hide referrer
		}
	}
	$return = $adminer->editVal($val, $field);
	if ($return !== null) {
		if (!is_utf8($return)) {
			$return = "\0"; // htmlspecialchars of binary data returns an empty string
		} elseif ($text_length != "" && is_shortable($field)) {
			$return = shorten_utf8($return, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
		} else {
			$return = h($return);
		}
	}
	return $adminer->selectVal($return, $link, $field, $val);
}

/** Check whether the string is e-mail address
* @param string
* @return bool
*/
function is_mail($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	$pattern = "$atom+(\\.$atom+)*@($domain?\\.)+$domain";
	return is_string($email) && preg_match("(^$pattern(,\\s*$pattern)*\$)i", $email);
}

/** Check whether the string is URL address
* @param string
* @return bool
*/
function is_url($string) {
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component //! IDN
	return preg_match("~^(https?)://($domain?\\.)+$domain(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i", $string); //! restrict path, query and fragment characters
}

/** Check if field should be shortened
* @param array
* @return bool
*/
function is_shortable($field) {
	return preg_match('~char|text|json|lob|geometry|point|linestring|polygon|string|bytea~', $field["type"]);
}

/** Get query to compute number of found rows
* @param string
* @param array
* @param bool
* @param array
* @return string
*/
function count_rows($table, $where, $is_group, $group) {
	global $jush;
	$query = " FROM " . table($table) . ($where ? " WHERE " . implode(" AND ", $where) : "");
	return ($is_group && ($jush == "sql" || count($group) == 1)
		? "SELECT COUNT(DISTINCT " . implode(", ", $group) . ")$query"
		: "SELECT COUNT(*)" . ($is_group ? " FROM (SELECT 1$query GROUP BY " . implode(", ", $group) . ") x" : $query)
	);
}

/** Run query which can be killed by AJAX call after timing out
* @param string
* @return array of strings
*/
function slow_query($query) {
	global $adminer, $token, $driver;
	$db = $adminer->database();
	$timeout = $adminer->queryTimeout();
	$slow_query = $driver->slowQuery($query, $timeout);
	if (!$slow_query && support("kill") && is_object($connection2 = connect()) && ($db == "" || $connection2->select_db($db))) {
		$kill = $connection2->result(connection_id()); // MySQL and MySQLi can use thread_id but it's not in PDO_MySQL
		?>
<script<?php echo nonce(); ?>>
var timeout = setTimeout(function () {
	ajax('<?php echo js_escape(ME); ?>script=kill', function () {
	}, 'kill=<?php echo $kill; ?>&token=<?php echo $token; ?>');
}, <?php echo 1000 * $timeout; ?>);
</script>
<?php
	} else {
		$connection2 = null;
	}
	ob_flush();
	flush();
	$return = @get_key_vals(($slow_query ? $slow_query : $query), $connection2, false); // @ - may be killed
	if ($connection2) {
		echo script("clearTimeout(timeout);");
		ob_flush();
		flush();
	}
	return $return;
}

/** Generate BREACH resistant CSRF token
* @return string
*/
function get_token() {
	$rand = rand(1, 1e6);
	return ($rand ^ $_SESSION["token"]) . ":$rand";
}

/** Verify if supplied CSRF token is valid
* @return bool
*/
function verify_token() {
	list($token, $rand) = explode(":", $_POST["token"]);
	return ($rand ^ $_SESSION["token"]) == $token;
}

// used in compiled version
function lzw_decompress($binary) {
	// convert binary string to codes
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$codes = array();
	$rest = 0;
	$rest_length = 0;
	for ($i=0; $i < strlen($binary); $i++) {
		$rest = ($rest << 8) + ord($binary[$i]);
		$rest_length += 8;
		if ($rest_length >= $bits) {
			$rest_length -= $bits;
			$codes[] = $rest >> $rest_length;
			$rest &= (1 << $rest_length) - 1;
			$dictionary_count++;
			if ($dictionary_count >> $bits) {
				$bits++;
			}
		}
	}
	// decompression
	$dictionary = range("\0", "\xFF");
	$return = "";
	foreach ($codes as $i => $code) {
		$element = $dictionary[$code];
		if (!isset($element)) {
			$element = $word . $word[0];
		}
		$return .= $element;
		if ($i) {
			$dictionary[] = $word . $element[0];
		}
		$word = $element;
	}
	return $return;
}

/** Return events to display help on mouse over
* @param string JS expression
* @param bool JS expression
* @return string
*/
function on_help($command, $side = 0) {
	return script("mixin(qsl('select, input'), {onmouseover: function (event) { helpMouseover.call(this, event, $command, $side) }, onmouseout: helpMouseout});", "");
}

/** Print edit data form
* @param string
* @param array
* @param mixed
* @param bool
* @return null
*/
function edit_form($table, $fields, $row, $update) {
	global $adminer, $jush, $token, $error;
	$table_name = $adminer->tableName(table_status1($table, true));
	page_header(
		($update ? lang(10) : lang(11)),
		$error,
		array("select" => array($table, $table_name)),
		$table_name
	);
	$adminer->editRowPrint($table, $fields, $row, $update);
	if ($row === false) {
		echo "<p class='error'>" . lang(12) . "\n";
	}
	?>
<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
	if (!$fields) {
		echo "<p class='error'>" . lang(13) . "\n";
	} else {
		echo "<table cellspacing='0' class='layout'>" . script("qsl('table').onkeydown = editingKeydown;");

		foreach ($fields as $name => $field) {
			echo "<tr><th>" . $adminer->fieldName($field);
			$default = $_GET["set"][bracket_escape($name)];
			if ($default === null) {
				$default = $field["default"];
				if ($field["type"] == "bit" && preg_match("~^b'([01]*)'\$~", $default, $regs)) {
					$default = $regs[1];
				}
			}
			$value = ($row !== null
				? ($row[$name] != "" && $jush == "sql" && preg_match("~enum|set~", $field["type"])
					? (is_array($row[$name]) ? array_sum($row[$name]) : +$row[$name])
					: (is_bool($row[$name]) ? +$row[$name] : $row[$name])
				)
				: (!$update && $field["auto_increment"]
					? ""
					: (isset($_GET["select"]) ? false : $default)
				)
			);
			if (!$_POST["save"] && is_string($value)) {
				$value = $adminer->editVal($value, $field);
			}
			$function = ($_POST["save"]
				? (string) $_POST["function"][$name]
				: ($update && preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"])
					? "now"
					: ($value === false ? null : ($value !== null ? '' : 'NULL'))
				)
			);
			if (!$_POST && !$update && $value == $field["default"] && preg_match('~^[\w.]+\(~', $value)) {
				$function = "SQL";
			}
			if (preg_match("~time~", $field["type"]) && preg_match('~^CURRENT_TIMESTAMP~i', $value)) {
				$value = "";
				$function = "now";
			}
			input($field, $value, $function);
			echo "\n";
		}
		if (!support("table")) {
			echo "<tr>"
				. "<th><input name='field_keys[]'>"
				. script("qsl('input').oninput = fieldChange;")
				. "<td class='function'>" . html_select("field_funs[]", $adminer->editFunctions(array("null" => isset($_GET["select"]))))
				. "<td><input name='field_vals[]'>"
				. "\n"
			;
		}
		echo "</table>\n";
	}
	echo "<p>\n";
	if ($fields) {
		echo "<input type='submit' value='" . lang(14) . "'>\n";
		if (!isset($_GET["select"])) {
			echo "<input type='submit' name='insert' value='" . ($update
				? lang(15)
				: lang(16)
			) . "' title='Ctrl+Shift+Enter'>\n";
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . lang(17) . "…', this); };") : "");
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . lang(18) . "'>" . confirm() . "\n"
		: ($_POST || !$fields ? "" : script("focus(qsa('td', qs('#form'))[1].firstChild);"))
	);
	if (isset($_GET["select"])) {
		hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
	}
	?>
<input type="hidden" name="referer" value="<?php echo h(isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]); ?>">
<input type="hidden" name="save" value="1">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
}


// used only in compiled file
if (isset($_GET["file"])) {
	
if ($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
	header("HTTP/1.1 304 Not Modified");
	exit;
}

header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: immutable");


if ($_GET["file"] == "favicon.ico") {
	header("Content-Type: image/x-icon");
	echo lzw_decompress("\0\0\0` \0�\0\n @\0�C��\"\0`E�Q����?�tvM'�Jd�d\\�b0\0�\"��fӈ��s5����A�XPaJ�0���8�#R�T��z`�#.��c�X��Ȁ?�-\0�Im?�.�M��\0ȯ(̉��/(%�\0");
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("\n1̇�ٌ�l7��B1�4vb0��fs���n2B�ѱ٘�n:�#(�b.\rDc)��a7E����l�ñ��i1̎s���-4��f�	��i7�����t4���y�Zf4��i�AT�VV��f:Ϧ,:1�Qݼ�b2`�#�>:7G�1���s��L�XD*bv<܌#�e@�:4�!fo���t:<��咾�o��\ni���',�a_�:�i�Bv�|N�4.5Nf�i�vp�h��l��֚�O����= �OFQ��k\$��i����d2T�p��6�����-�Z�����6����h:�a�,����2�#8А�#��6n����J��h�t�����4O42��ok��*r���@p@�!������?�6��r[��L���:2B�j�!Hb��P�=!1V�\"��0��\nS���D7��Dڛ�C!�!��Gʌ� �+�=tC�.C��:+��=�������%�c�1MR/�EȒ4���2�䱠�`�8(�ӹ[W��=�yS�b�=�-ܹBS+ɯ�����@pL4Yd��q�����6�3Ĭ��Ac܌�Ψ�k�[&>���Z�pkm]�u-c:���Nt�δpҝ��8�=�#��[.��ޯ�~���m�y�PP�|I֛���Q�9v[�Q��\n��r�'g�+��T�2��V��z�4��8��(	�Ey*#j�2]��R����)��[N�R\$�<>:�>\$;�>��\r���H��T�\nw�N �wأ��<��Gw����\\Y�_�Rt^�>�\r}��S\rz�4=�\nL�%J��\",Z�8����i�0u�?�����s3#�ى�:���㽖��E]x���s^8��K^��*0��w����~���:��i���v2w����^7���7�c��u+U%�{P�*4̼�LX./!��1C��qx!H��Fd��L���Ġ�`6��5��f��Ć�=H�l �V1��\0a2�;��6����_ه�\0&�Z�S�d)KE'��n��[X��\0ZɊ�F[P�ޘ@��!��Y�,`�\"ڷ��0Ee9yF>��9b����F5:���\0}Ĵ��(\$����37H��� M�A��6R��{Mq�7G��C�C�m2�(�Ct>[�-t�/&C�]�etG�̬4@r>���<�Sq�/���Q�hm���������L��#��K�|���6fKP�\r%t��V=\"�SH\$�} ��)w�,W\0F��u@�b�9�\rr�2�#�D��X���yOI�>��n��Ǣ%���'��_��t\rτz�\\1�hl�]Q5Mp6k���qh�\$�H~�|��!*4����`S���S t�PP\\g��7�\n-�:袪p����l�B���7Өc�(wO0\\:��w���p4���{T��jO�6HÊ�r���q\n��%%�y']\$��a�Z�.fc�q*-�FW��k��z���j���lg�:�\$\"�N�\r#�d�Â���sc�̠��\"j�\r�����Ւ�Ph�1/��DA)���[�kn�p76�Y��R{�M�P���@\n-�a�6��[�zJH,�dl�B�h�o�����+�#Dr^�^��e��E��� ĜaP���JG�z��t�2�X�����V�����ȳ��B_%K=E��b弾�§kU(.!ܮ8����I.@�K�xn���:�P�32��m�H		C*�:v�T�\nR�����0u�����ҧ]�����P/�JQd�{L�޳:Y��2b��T ��3�4���c�V=���L4��r�!�B�Y�6��MeL������i�o�9< G��ƕЙMhm^�U�N����Tr5HiM�/�n�흳T��[-<__�3/Xr(<���������uҖGNX20�\r\$^��:'9�O��;�k����f��N'a����b�,�V��1��HI!%6@��\$�EGڜ�1�(mU��rս���`��iN+Ü�)���0l��f0��[U��V��-:I^��\$�s�b\re��ug�h�~9�߈�b�����f�+0�� hXrݬ�!\$�e,�w+����3��_�A�k��\nk�r�ʛcuWdY�\\�={.�č���g��p8�t\rRZ�v�J:�>��Y|+�@����C�t\r��jt��6��%�?��ǎ�>�/�����9F`ו��v~K�����R�W��z��lm�wL�9Y�*q�x�z��Se�ݛ����~�D�����x���ɟi7�2���Oݻ��_{��53��t���_��z�3�d)�C��\$?KӪP�%��T&��&\0P�NA�^�~���p� �Ϝ���\r\$�����b*+D6궦ψ��J\$(�ol��h&��KBS>���;z��x�oz>��o�Z�\nʋ[�v���Ȝ��2�OxِV�0f�����2Bl�bk�6Zk�hXcd�0*�KT�H=��π�p0�lV����\r���n�m��)(�(�:#����E��:C�C���\r�G\ré0��i����:`Z1Q\n:��\r\0���q���:`�-�M#}1;����q�#|�S���hl�D�\0fiDp�L��``����0y��1���\r�=�MQ\\��%oq��\0��1�21�1�� ���ќbi:��\r�/Ѣ� `)��0��@���I1�N�C�����O��Z��1���q1 ����,�\rdI�Ǧv�j�1 t�B���⁒0:�0��1�A2V���0���%�fi3!&Q�Rc%�q&w%��\r��V�#���Qw`�% ���m*r��y&i�+r{*��(rg(�#(2�(��)R@i�-�� ���1\"\0��R���.e.r��,�ry(2�C��b�!Bޏ3%ҵ,R�1��&��t��b�a\rL��-3�����\0��Bp�1�94�O'R�3*��=\$�[�^iI;/3i�5�&�}17�# ѹ8��\"�7��8�9*�23�!�!1\\\0�8��rk9�;S�23��ړ*�:q]5S<��#3�83�#e�=�>~9S螳�r�)��T*a�@і�bes���:-���*;,�ؙ3!i���LҲ�#1 �+n� �*��@�3i7�1���_�F�S;3�F�\rA��3�>�x:� \r�0��@�-�/��w��7��S�J3� �.F�\$O�B���%4�+t�'g�Lq\rJt�J��M2\r��7��T@���)ⓣd��2�P>ΰ��Fi಴�\nr\0��b�k(�D���KQ����1�\"2t����P�\r��,\$KCt�5��#��)��P#Pi.�U2�C�~�\"�");
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("f:��gCI��\n8��3)��7���81��x:\nOg#)��r7\n\"��`�|2�gSi�H)N�S��\r��\"0��@�)�`(\$s6O!��V/=��' T4�=��iS��6IO�G#�X�VC��s��Z1.�hp8,�[�H�~Cz���2�l�c3���s���I�b�4\n�F8T��I���U*fz��r0�E����y���f�Y.:��I��(�c��΋!�_l��^�^(��N{S��)r�q�Y��l٦3�3�\n�+G���y���i���xV3w�uh�^r����a۔���c��\r���(.��Ch�<\r)�ѣ�`�7���43'm5���\n�P�:2�P����q ���C�}ī�����38�B�0�hR��r(�0��b\\0�Hr44��B�!�p�\$�rZZ�2܉.Ƀ(\\�5�|\nC(�\"��P���.��N�RT�Γ��>�HN��8HP�\\�7Jp~���2%��OC�1�.��C8·H��*�j����S(�/��6KU����<2�pOI���`���ⳈdO�H��5�-��4��pX25-Ң�ۈ�z7��\"(�P�\\32:]U����߅!]�<�A�ۤ���iڰ�l\r�\0v��#J8��wm��ɤ�<�ɠ��%m;p#�`X�D���iZ��N0����9��占��`��wJ�D��2�9t��*��y��NiIh\\9����:����xﭵyl*�Ȉ��Y�����8�W��?���ޛ3���!\"6�n[��\r�*\$�Ƨ�nzx�9\r�|*3ףp�ﻶ�:(p\\;��mz���9����8N���j2����\r�H�H&��(�z��7i�k� ����c��e���t���2:SH�Ƞ�/)�x�@��t�ri9����8����yҷ���V�+^Wڦ��kZ�Y�l�ʣ���4��Ƌ������\\E�{�7\0�p���D��i�-T����0l�%=���˃9(�5�\n\n�n,4�\0�a}܃.��Rs\02B\\�b1�S�\0003,�XPHJsp�d�K� CA!�2*W����2\$�+�f^\n�1����zE� Iv�\\�2��.*A���E(d���b��܄��9����Dh�&��?�H�s�Q�2�x~nÁJ�T2�&��eR���G�Q��Tw�ݑ��P���\\�)6�����sh\\3�\0R	�'\r+*;R�H�.�!�[�'~�%t< �p�K#�!�l���Le����,���&�\$	��`��CX��ӆ0֭����:M�h	�ڜG��!&3�D�<!�23��?h�J�e ��h�\r�m���Ni�������N�Hl7��v��WI�.��-�5֧ey�\rEJ\ni*�\$@�RU0,\$U�E����ªu)@(t�SJk�p!�~���d`�>��\n�;#\rp9�jɹ�]&Nc(r���TQU��S��\08n`��y�b���L�O5��,��>���x���f䴒���+��\"�I�{kM�[\r%�[	�e�a�1! ���Ԯ�F@�b)R��72��0�\nW���L�ܜҮtd�+���0wgl�0n@��ɢ�i�M��\nA�M5n�\$E�ױN��l�����%�1 A������k�r�iFB���ol,muNx-�_�֤C( ��f�l\r1p[9x(i�BҖ��zQl��8C�	��XU Tb��I�`�p+V\0��;�Cb��X�+ϒ�s��]H��[�k�x�G*�]�awn�!�6�����mS�I��K�~/�ӥ7��eeN��S�/;d�A�>}l~��� �%^�f�آpڜDE��a��t\nx=�kЎ�*d���T����j2��j��\n��� ,�e=��M84���a�j@�T�s���nf��\n�6�\rd��0���Y�'%ԓ��~	�Ҩ�<���AH�G��8���΃\$z��{���u2*��a��>�(w�K.bP�{��o��´�z�#�2�8=�8>���A,�e���+�C�x�*���-b=m���,�a��lzk���\$W�,�m�Ji�ʧ���+���0�[��.R�sK���X��ZL��2�`�(�C�vZ������\$�׹,�D?H��NxX��)��M��\$�,��*\nѣ\$<q�şh!��S����xsA!�:�K��}�������R��A2k�X�p\n<�����l���3�����VV�}�g&Yݍ!�+�;<�Y��YE3r�َ��C�o5����ճ�kk�����ۣ��t��U���)�[����}��u��l�:D��+Ϗ _o��h140���0��b�K�㬒�����lG��#��������|Ud�IK���7�^��@��O\0H��Hi�6\r����\\cg\0���2�B�*e��\n��	�zr�!�nWz&� {H��'\$X �w@�8�DGr*���H�'p#�Į���\nd���,���,�;g~�\0�#����E��\r�I`��'��%E�.�]`�Л��%&��m��\r��%4S�v�#\n��fH\$%�-�#���qB�����Q-�c2���&���]�� �qh\r�l]�s���h�7�n#����-�jE�Fr�l&d����z�F6����\"���|���s@����z)0rpڏ\0�X\0���|DL<!��o�*�D�{.B<E���0nB(� �|\r\n�^���� h�!���r\$��(^�~����/p�q��B��O����,\\��#RR��%���d�Hj�`����̭ V� bS�d�i�E���oh�r<i/k\$-�\$o��+�ŋ��l��O�&evƒ�i�jMPA'u'���( M(h/+��WD�So�.n�.�n���(�(\"���h�&p��/�/1D̊�j娸E��&⦀�,'l\$/.,�d���W�bbO3�B�sH�:J`!�.���������,F��7(��Կ��1�l�s �Ҏ���Ţq�X\r����~R鰱`�Ҟ�Y*�:R��rJ��%L�+n�\"��\r��͇H!qb�2�Li�%����Wj#9��ObE.I:�6�7\0�6+�%�.����a7E8VS�?(DG�ӳB�%;���/<�����\r ��>�M��@���H�Ds��Z[tH�Enx(���R�x��@��GkjW�>���#T/8�c8�Q0��_�IIGII�!���YEd�E�^�td�th�`DV!C�8��\r���b�3�!3�@�33N}�ZB�3	�3�30��M(�>��}�\\�t�f�f���I\r���337 X�\"td�,\nbtNO`P�;�ܕҭ���\$\n����Zѭ5U5WU�^ho���t�PM/5K4Ej�KQ&53GX�Xx)�<5D��\r�V�\n�r�5b܀\\J\">��1S\r[-��Du�\r���)00�Y��ˢ�k{\n��#��\r�^��|�uܻU�_n�U4�U�~Yt�\rI��@䏳�R �3:�uePMS�0T�wW�X���D��KOU����;U�\n�OY��Y�Q,M[\0�_�D���W��J*�\rg(]�\r\"ZC��6u�+�Y��Y6ô�0�q�(��8}��3AX3T�h9j�j�f�Mt�PJbqMP5>������Y�k%&\\�1d��E4� �Yn���\$<�U]Ӊ1�mbֶ�^�����\"NV��p��p��eM���W�ܢ�\\�)\n �\nf7\n�2��r8��=Ek7tV����7P��L��a6��v@'�6i��j&>��;��`��a	\0pڨ(�J��)�\\��n��Ĭm\0��2��eqJ��P��t��fj��\"[\0����X,<\\������+md��~�����s%o��mn�),ׄ�ԇ�\r4��8\r����mE�H]�����HW�M0D�߀��~�ˁ�K��E}����|f�^���\r>�-z]2s�xD�d[s�t�S��\0Qf-K`���t���wT�9��Z��	�\nB�9 Nb��<�B�I5o�oJ�p��JNd��\r�hލ��2�\"�x�HC�ݍ�:���9Yn16��zr+z���\\�����m ��T ���@Y2lQ<2O+�%��.Ӄh�0A���Z��2R��1��/�hH\r�X��aNB&� �M@�[x��ʮ���8&L�V͜v�*�j�ۚGH��\\ٮ	���&s�\0Q��\\\"�b��	��\rBs��w��	���BN`�7�Co(���\nè���1�9�*E� �S��U�0U� t�'|�m���?h[�\$.#�5	 �	p��yB�@R�]���@|��{���P\0x�/� w�%�EsBd���CU�~O׷�P�@X�]����Z3��1��{�eLY���ڐ�\\�(*R`�	�\n������QCF�*�����霬�p�X|`N���\$�[���@�U������Z�`Zd\"\\\"����)��I�:�t��oD�\0[�����-���g���*`hu%�,����I�7ī�H�m�6�}��N�ͳ\$�M�UYf&1����e]pz���I��m�G/� �w �!�\\#5�4I�d�E�hq���Ѭk�x|�k�qD�b�z?���>���:��[�L�ƬZ�X��:�������j�w5	�Y��0 ���\$\0C��dSg����{�@�\n`�	���C ���M�����# t}x�N����{�۰)��C��FKZ�j��\0PFY�B�pFk��0<�>�D<JE��g\r�.�2��8�U@*�5fk��JD���4��TDU76�/��@��K+���J�����@�=��WIOD�85M��N�\$R�\0�5�\r��_���E���I�ϳN�l���y\\����qU��Q���\n@���ۺ�p���P۱�7ԽN\r�R{*�qm�\$\0R��ԓ���q�È+U@�B��Of*�Cˬ�MC��`_ ���˵N��T�5٦C׻� ��\\W�e&_X�_؍h���B�3���%�FW���|�Gޛ'�[�ł����V��#^\r��GR����P��Fg�����Yi ���z\n��+�^/�������\\�6��b�dmh��@q���Ah�),J��W��cm�em]�ӏe�kZb0�����Y�]ym��f�e�B;���O��w�apDW�����{�\0��-2/bN�sֽ޾Ra�Ϯh&qt\n\"�i�Rm�hz�e����FS7��PP�䖤��:B����sm��Y d���7}3?*�t����lT�}�~�����=c������	��3�;T�L�5*	�~#�A����s�x-7��f5`�#\"N�b��G����@�e�[�����s����-��M6��qq� h�e5�\0Ң���*�b�IS���Fή9}�p�-��`{��ɖkP�0T<��Z9�0<՚\r��;!��g�\r\nK�\n��\0��*�\nb7(�_�@,�e2\r�]�K�+\0��p C\\Ѣ,0�^�MЧ����@�;X\r��?\$\r�j�+�/��B��P�����J{\"a�6�䉜�|�\n\0��\\5���	156�� .�[�Uد\0d��8Y�:!���=��X.�uC����!S���o�p�B���7��ů�Rh�\\h�E=�y:< :u��2�80�si��TsB�@\$ ��@�u	�Q���.��T0M\\/�d+ƃ\n��=��d���A���)\r@@�h3���8.eZa|.�7�Yk�c���'D#��Y�@X�q�=M��44�B AM��dU\"�Hw4�(>��8���C�?e_`��X:�A9ø���p�G��Gy6��F�Xr��l�1��ػ�B�Å9Rz��hB�{����\0��^��-�0�%D�5F\"\"�����i�`��nAf� \"tDZ\"_�V\$��!/�D�ᚆ������٦�̀F,25�j�T��y\0�N�x\r�Yl��#��Eq\n��B2�\n��6���4���!/�\n��Q��*�;)bR�Z0\0�CDo�˞�48������e�\n�S%\\�PIk��(0��u/��G������\\�}�4Fp��G�_�G?)g�ot��[v��\0��?b�;��`(�ی�NS)\n�x=��+@��7��j�0��,�1Åz����>0��Gc��L�VX�����%����Q+���o�F���ܶ�>Q-�c���l����w��z5G��@(h�c�H��r?��Nb�@�������lx3�U`�rw���U���t�8�=�l#���l�䨉8�E\"����O6\n��1e�`\\hKf�V/зPaYK�O�� ��x�	�Oj���r7�F;��B����̒��>�Ц�V\rĖ�|�'J�z����#�PB��Y5\0NC�^\n~LrR��[̟Rì�g�eZ\0x�^�i<Q�/)�%@ʐ��fB�Hf�{%P�\"\"���@���)���DE(iM2�S�*�y�S�\"���e̒1��ט\n4`ʩ>��Q*��y�n����T�u�����~%�+W��XK���Q�[ʔ��l�PYy#D٬D<�FL���@�6']Ƌ��\rF�`�!�%\n�0�c���˩%c8WrpG�.T�Do�UL2�*�|\$�:�Xt5�XY�I�p#� �^\n��:�#D�@�1\r*�K7�@D\0��C�C�xBh�EnK�,1\"�*y[�#!�י�ٙ���l_�/��x�\0���5�Z��4\0005J�h\"2���%Y���a�a1S�O�4��%ni��P��ߴq�_ʽ6���~��I\\���d���d������D�����3g^��@^6����_�HD�.ksL��@��Ɉ�n�I���~�\r�b�@�Ӏ�N�t\0s���]:u��X�b@^�1\0���2?�T��6dLNe��+�\0�:�Ё�l��z6q=̺x���N6��O,%@s�0\n�\\)�L<�C�|���P��b����A>I���\"	��^K4��gIX�i@P�jE�&/1@�f�	�N�x0coaߧ����,C'�y#6F@�Р��H0�{z3t�|cXMJ.*B�)ZDQ���\0��T-v�X�a*��,*�<b���#xј�d�P��KG8�� y�K	\\#=�)�gȑh�&�8])�C�\nô��9�z�W\\�g�M 7��!��������,��9���\$T\"�,��%.F!˚ A�-�����-�g��\0002R>KE�'�U�_I���9�˼�j(�Q��@�@�4/�7���'J.�RT�\0]KS�D���Ap5�\r�H0!�´e	d@Rҝ�ิ�9�S�;7�H�B�bx�J��_�vi�U`@���SAM��X��G�Xi��U*��������'��:V�WJv�D���N'\$�zh\$d_y���Z]����Y���8ؔ���]�P�*h���֧e;��pe��\$k�w��*7N�DTx_�ԧ�Gi�&P�Ԇ�t͆�b�\\E�H\$i�E\"cr��0l�?>��C(�W@3���22a���I����{�B`�ڳiŸGo^6E\r��G�M�p1i�I��X�\0003�2�K�����zl&ֆ�'IL�\\�\"�7�>�j(>�j�FG_��& 10I�A31=h q\0�F����ķ��_�J���ԳVΖ��܆q�՚��	��(/�dOC�_sm�<g�x\0��\"��\n@EkH\0�J���8�(���km[����S4�\nY40��+L\n������#Bӫb��%R֖��׭��R:�<\$!ۥr�;���	%|ʨ�(�|�H�\0�������]�cҡ=0��Z�\"\"=�X��)�f�N��6V}F��=[���ৢhu�-��\0t��bW~��Q��iJ���L�5׭q#kb���Wn���Q�T�!���e�nc�S�[+ִE�<-��a]Ń��Yb�\n\nJ~�|JɃ8� �Lp����o� �N�ܨ�J.��ŃS��2c9�j�y�-`a\0��*�ֈ@\0+��mg��6�1��Me\0��Q �_�}!I��GL�f)�X�o,�Shx�\0000\"h�+L�M�� �ј��Z	j�\0���/��\$��>u*�Z9��Z�e��+J����tz������R�Kԯ���Dy���q�0C�-f��m����BI�|��HB��sQl�X��.����|�c���[��ZhZ��l���x�@'��ml�KrQ�26��]�ҷn�d[��񎩇d���\"GJ9u��B�o��Zߖ�a��n@��n�lW|*gX�\nn2�F�|x`Dk��uPP�!Q\rr��`W/���	1�[-o,71bUs����N�7����Gq�.\\Q\"CCT\"�����*?u�ts�����]�٩Pz[�[YFϹ��FD3�\"����]�u۝)wz�:#���Iiw��pɛ��{�o�0n��;��\\�x���\0q��m���&�~��7����9[�H�qdL�O�2�v�|B�t��\\Ƥ�Hd���H�\" ��N\n\0��G�g�F��F�}\"�&QEK��{}\ryǎ��rכt������7�Nuó[A�gh;S�.Ҡ���¥|y��[Ն_b�Ȩ�!+R��ZX�@0N����P���%�jD�¯z	���[�U\"�{e�8��>�EL4Jн�0����7 ��d�� �Q^`0`�����]c�<g@��hy8��p.ef\n��eh��aX����mS��jBژQ\"�\r���K3�=>ǪAX�[,,\"'<���%�a��Ӵ��.\$�\0�%\0�sV���p�M\$�@j���>���}Ve�\$@�̈́#���(3:�`�U�Y��u�������@�V#E�G/��XD\$�h��av��xS\"]k18a�я�9dJROӊs�`EJ����Uo�m{l�B8���(\n}ei�b��, �;�N��͇�Q�\\�ǸI5yR�\$!>\\ʉ�g�uj*?n�M�޲h��\r%���U(d��N�d#}�pA:����-\\�A�*�4�2I���\r�֣�� 0h@\\Ե��8�3�rq]���d8\"�Q����ƙ:c��y�4	�ᑚda�Π6>U�A����:��@�2���\$�eh2���F��əN�+���\r�Ԁ(�Ar��d*�\0[�#cj����>!(�S���L�e�T��M	9\0W:�BD���3J���_@s��rue������ +�'B��}\"B\"�z2��r��l�xF[�L�˲Ea9��cdb��^,�UC=/2�����/\$�C�#��8�}D���6�`^;6B0U7�_=	,�1�j1V[�.	H9(1���ҏLz�C�	�\$.A�fh㖫����DrY	�H�e~o�r19��م\\�߄P�)\"�Q��,�e��L��w0�\0������;w�X�ǝ���qo���~�����>9�>}��dc�\0��g��f��q�&9���-�J#����3^4m/���\0\0006��n8��>䈴.ӗ��cph��������_A@[��7�|9\$pMh�>���5�K���E=h��A�t�^�V�	�\"�	c�B;���i��QҠt����@,\n�)���s�`����;�4����I������y��-�0yeʨ�U��B�v��3H�P�G�5��s|��\r���\$0����1��l3��(*oF~PK��.�,'�J/�Ӳ�t���d�:��n�\n��j��Y�z�(����w���Z�#Z�	Io�@1�λ\$��=VWz�	n�B�a���A��q�@��I�p	@�5Ӗ�lH{U��oX��f��ӿ\\z��.���,-\\ڗ^y n^���Bq����zX㉡�\$�*J72�D4.����!�M0��D��F����G��L�m�c*m�cI��5Ɍ�^�t���jl�7替S�Q��.i����h��L�ڱB6Ԅh�&�J��l\\��We�c�f%kj�� �p�R=��i�@.��(�2�klHUW\"�o�j���p!S5��pL'`\0�O *�Q3X��lJ\08\n�\r���*�a��떞��r�`<�&�XBh�8!x��&�Bht�\$���]�n߆���cL��[Ƶ�d��<`���\0���ς�aw�O%;���BC��Q�\r̭�����p����PQ�Z���Z�Au=N&�ia\n�mK6I}��n	��t\nd)����bp��\"��g'�0�7�u�&@�7�8X�N��x������\$B��ZB/�M�gB�i��ѧ�\\�m�mI�Ā��;5=#&4����P�Ս����q�A��\\�,q�cޟ\nc�B�����w\0BgjD�@;�=0m�k��\rĲ�`��'5���k-�{��\0�_�Mu����2��׆����q����>)9�W\n�d+��ԧ�G\r��n4���O�:5���8��1�:Κ?��(yGgWK�\r�7����m5.��e�H�hJ�Ak#��L�..�\\�=��U�Є����:�>7�W+^yD���b��G��OZ�4�r�(|x���Pr��,y���8qaܩO2��k�n��#p2��ǈ�ؔ.��c��U�c����łj�\$��8Ĭ~��7ZR:�׆8�9Ψw(a�L�%�-,��쿌#�f�%8��|�c������%X�W�\n}6��H����˞��#�&J,'z�M�M�����ຑ܆� ���/y6YQ���ںdәd����:����E��p2g�g�/�,����Ո'8�^;�UWN�����{�OC�����z�iKX��ڔN�dG�RCJY����i���y#>zS�MUc�������RORԾ�0�)�0��]:=Ϟ�t�����'\$�s�rF���67	=\$B��!qs	1\"���v��%��I�l<�b!ۮ6(Cd-�^<H`~2�K��zK�ٜ�Ա���y,qA�*�\0}��C�pb�\\�S�5����'(����|�M����W��5;\$5�T|��;k���t���@��;9�)��;i�.�;���_����F�=�D�M`H���\0�	 N @�%w��d��Pb�\$H|k�[��dCI!:l��,���<��u�t���NeϝW^�w�'6���D��f�u �ihI�Z:��~��ϣ�r���z�3�+�uoC�s2�b�ua�X��wWK�	HԶ27>�W���y����M�J��rpT��L��|`f��:���A�t�d|i��[w��j���W� 7���au�����e��A5�Q' ʐ\0��3�Ҿ\$����\rk)�a;���H=��֐~�IG�I�<���\"���I1'蠙�Gcm\0P\n�w��#�>���xB\"��Em|��2�\$}<3P�YX�go�d߶�<�����qE\"`���4�g�8r�]\n����:��qVb�T��m���9K&ғĤ�m�7)@��Qz���=��ߵű�H\n���}O�i}�\r٣.��v��p�JW&�u�55�0	�5��P�I��\n�����l\0O5*=��	�P-���H\0�f�%��tぺ*�S:�tϛ���?�ȂH����q4��K���@�Ԭ�܂.O(����Z�\$���]���o��n�z�A�!�t85<W�R2[�8���n5\$I��浕Z����]'}ET\n�����.��&�7��V�@�_�D�o��&J6��4i�j\$��EL���u��t����+I�Т���أ~�S�SZTX���PYz��\"\$V�_]�M(��7���������t_��S�����/��t���Ă���mH�:\0�5�- _Z'#���1�P��,�}(��~�\0��!Җ`-�P\ne�y (����`9O��!��;5�\n�\$�{������UA��7��!���[� �Y���F�濴�����>�8&����!CL���H����(�\0'Ǐ2��d\r%�;�k抐4��_O�>�5���@D�Ҽ��\0V�A�6' AY�����S�����rԾ�4�+h@b��������O�M\0���r̛�@�\rJ��m0\08�O���;k�Ӡ���A(6�|	`8 �\0��&��E�V��\0V�����wk�N��K����xdp���s�AL��A�X�k���u\0�����t �Ԣ�.�>(N��K'fld�A���?++��N��~������k�����PR\0��x������ʑ���BK]�bU��\\̛���d\0S@��Q��͉�b�\0\0b���\0_\\�@\nN���O�A��Pf��������ԏAj ��M4<�9���+�����`S�� ����w3T���7�X���T!\0e�PAI�b 1!\0��4���'� @�!�8\0��/���!:K�,�CAS�X�f�e��M��.:��:��t������._�d����81v`�B\"��!.^�*��N.^��\n�&\r(��.����O0��@��P��nj���ڗ#������&��rH�<��� �!��3��(i @�Aa��{� ¬#�S���6𨘶F@�����Y[O��(��.��/�B�����)L02B؈�-�ƀ��qp��J<�.Б\0\n��\0��/@8C�4P��\r	P�)��F���\$q.]�\"B#��	�#\\��84\$�s:.(*Oi>�|#T'`�Bu�a/���C��T�Ka�X8�`p�����\0`�\0");
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("v0��F����==��FS	��_6MƳ���r:�E�CI��o:�C��Xc��\r�؄J(:=�E���a28�x�?�'�i�SANN���xs�NB��Vl0���S	��Ul�(D|҄��P��>�E�㩶yHch��-3Eb�� �b��pE�p�9.����~\n�?Kb�iw|�`��d.�x8EN��!��2��3���\r���Y���y6GFmY�8o7\n\r�0��\0�Dbc�!�Q7Шd8���~��N)�Eг`�Ns��`�S)�O���/�<�x�9�o�����3n��2�!r�:;�+�9�CȨ���\n<�`��b�\\�?�`�4\r#`�<�Be�B#�N ��\r.D`��j�4���p�ar��㢺�>�8�\$�c��1�c���c����{n7����A�N�RLi\r1���!�(�j´�+��62�X�8+����.\r����!x���h�'��6S�\0R����O�\n��1(W0���7q��:N�E:68n+��մ5_(�s�\r��/m�6P�@�EQ���9\n�V-���\"�.:�J��8we�q�|؇�X�]��Y X�e�zW�� �7��Z1��hQf��u�j�4Z{p\\AU�J<��k��@�ɍ��@�}&���L7U�wuYh��2��@�u� P�7�A�h����3Û��XEͅZ�]�l�@Mplv�)� ��HW���y>�Y�-�Y��/�������hC�[*��F�#~�!�`�\r#0P�C˝�f������\\���^�%B<�\\�f�ޱ�����&/�O��L\\jF��jZ�1�\\:ƴ>�N��XaF�A�������f�h{\"s\n�64������?�8�^p�\"띰�ȸ\\�e(�P�N��q[g��r�&�}Ph���W��*��r_s�P�h���\n���om������#���.�\0@�pdW �\$Һ�Q۽Tl0� ��HdH�)��ۏ��)P���H�g��U����B�e\r�t:��\0)\"�t�,�����[�(D�O\nR8!�Ƭ֚��lA�V��4�h��Sq<��@}���gK�]���]�=90��'����wA<����a�~��W��D|A���2�X�U2��yŊ��=�p)�\0P	�s��n�3�r�f\0�F���v��G��I@�%���+��_I`����\r.��N���KI�[�ʖSJ���aUf�Sz���M��%��\"Q|9��Bc�a�q\0�8�#�<a��:z1Uf��>�Z�l������e5#U@iUG��n�%Ұs���;gxL�pP�?B��Q�\\�b��龒Q�=7�:��ݡQ�\r:�t�:y(� �\n�d)���\n�X;����CaA�\r���P�GH�!���@�9\n\nAl~H���V\ns��ի�Ư�bBr���������3�\r�P�%�ф\r}b/�Α\$�5�P�C�\"w�B_��U�gAt��夅�^Q��U���j���Bvh졄4�)��+�)<�j^�<L��4U*���Bg�����*n�ʖ�-����	9O\$��طzyM�3�\\9���.o�����E(i������7	tߚ�-&�\nj!\r��y�y�D1g���]��yR�7\"������~����)TZ0E9M�YZtXe!�f�@�{Ȭyl	8�;���R{��8�Į�e�+UL�'�F�1���8PE5-	�_!�7��[2�J��;�HR��ǹ�8p痲݇@��0,ծpsK0\r�4��\$sJ���4�DZ��I��'\$cL�R��MpY&����i�z3G�zҚJ%��P�-��[�/x�T�{p��z�C�v���:�V'�\\��KJa��M�&���Ӿ\"�e�o^Q+h^��iT��1�OR�l�,5[ݘ\$��)��jLƁU`�S�`Z^�|��r�=��n登��TU	1Hyk��t+\0v�D�\r	<��ƙ��jG���t�*3%k�YܲT*�|\"C��lhE�(�\r�8r��{��0����D�_��.6и�;����rBj�O'ۜ���>\$��`^6��9�#����4X��mh8:��c��0��;�/ԉ����;�\\'(��t�'+�����̷�^�]��N�v��#�,�v���O�i�ϖ�>��<S�A\\�\\��!�3*tl`�u�\0p'�7�P�9�bs�{�v�{��7�\"{��r�a�(�^��E����g��/���U�9g���/��`�\nL\n�)���(A�a�\" ���	�&�P��@O\n師0�(M&�FJ'�! �0�<�H�������*�|��*�OZ�m*n/b�/�������.��o\0��dn�)����i�:R���P2�m�\0/v�OX���Fʳψ���\"�����0�0�����0b��gj��\$�n�0}�	�@�=MƂ0n�P�/p�ot������.�̽�g\0�)o�\n0���\rF����b�i��o}\n�̯�	NQ�'�x�Fa�J���L������\r��\r����0��'��d	oep��4D��ʐ�q(~�� �\r�E��pr�QVFH�l��Kj���N&�j!�H`�_bh\r1���n!�Ɏ�z�����\\��\r���`V_k��\"\\ׂ'V��\0ʾ`AC������V�`\r%�����\r����k@N����B�횙� �!�\n�\0Z�6�\$d��,%�%la�H�\n�#�S\$!\$@��2���I\$r�{!��J�2H�ZM\\��hb,�'||cj~g�r�`�ļ�\$���+�A1�E���� <�L��\$�Y%-FD��d�L焳��\n@�bVf�;2_(��L�п��<%@ڜ,\"�d��N�er�\0�`��Z��4�'ld9-�#`��Ŗ����j6�ƣ�v���N�͐f��@܆�&�B\$�(�Z&���278I ��P\rk\\���2`�\rdLb@E��2`P( B'�����0�&��{���:��dB�1�^؉*\r\0c<K�|�5sZ�`���O3�5=@�5�C>@�W*	=\0N<g�6s67Sm7u?	{<&L�.3~D��\rŚ�x��),r�in�/��O\0o{0k�]3>m��1\0�I@�9T34+ԙ@e�GFMC�\rE3�Etm!�#1�D @�H(��n ��<g,V`R]@����3Cr7s~�GI�i@\0v��5\rV�'������P��\r�\$<b�%(�Dd��PW����b�fO �x\0�} ��lb�&�vj4�LS��ִԶ5&dsF M�4��\".H�M0�1uL�\"��/J`�{�����xǐYu*\"U.I53Q�3Q��J��g��5�s���&jь��u�٭ЪGQMTmGB�tl-c�*��\r��Z7���*hs/RUV����B�Nˈ�����Ԋ�i�Lk�.���t�龩�rYi���-S��3�\\�T�OM^�G>�ZQj���\"���i��MsS�S\$Ib	f���u����:�SB|i��Y¦��8	v�#�D�4`��.��^�H�M�_ռ�u��U�z`Z�J	e��@Ce��a�\"m�b�6ԯJR���T�?ԣXMZ��І��p����Qv�j�jV�{���C�\r��7�Tʞ� ��5{P��]�\r�?Q�AA������2񾠓V)Ji��-N99f�l Jm��;u�@�<F�Ѡ�e�j��Ħ�I�<+CW@�����Z�l�1�<2�iF�7`KG�~L&+N��YtWH飑w	����l��s'g��q+L�zbiz���Ţ�.Њ�zW�� �zd�W����(�y)v�E4,\0�\"d��\$B�{��!)1U�5bp#�}m=��@�w�	P\0�\r�����`O|���	�ɍ����Y��JՂ�E��Ou�_�\n`F`�}M�.#1��f�*�ա��  �z�uc���� xf�8kZR�s2ʂ-���Z2�+�ʷ�(�sU�cD�ѷ���X!��u�&-vP�ر\0'L�X �L����o	��>�Վ�\r@�P�\rxF��E��ȭ�%����=5N֜��?�7�N�Å�w�`�hX�98 �����q��z��d%6̂t�/������L��l��,�Ka�N~�����,�'�ǀM\rf9�w��!x��x[�ϑ�G�8;�xA��-I�&5\$�D\$���%��xѬ���´���]����&o�-3�9�L��z���y6�;u�zZ ��8�_�ɐx\0D?�X7����y�OY.#3�8��ǀ�e�Q�=؀*��G�wm ���Y�����]YOY�F���)�z#\$e��)�/�z?�z;����^��F�Zg�����������`^�e����#�������?��e��M��3u�偃0�>�\"?��@חXv�\"������*Ԣ\r6v~��OV~�&ר�^g���đٞ�'��f6:-Z~��O6;zx��;&!�+{9M�ٳd� \r,9���W��ݭ:�\r�ٜ��@睂+��]��-�[g��ۇ[s�[i��i�q��y��x�+�|7�{7�|w�}����E��W��Wk�|J؁��xm��q xwyj���#��e��(�������ߞþ��� {��ڏ�y���M���@��ɂ��Y�(g͚-��������J(���@�;�y�#S���Y��p@�%�s��o�9;�������+��	�;����ZNٯº��� k�V��u�[�x��|q��ON?���	�`u��6�|�|X����س|O�x!�:���ϗY]�����c���\r�h�9n�������8'������\rS.1��USȸ��X��+��z]ɵ��?����C�\r��\\����\$�`��)U�|ˤ|Ѩx'՜����<�̙e�|�ͳ����L���M�y�(ۧ�l�к�O]{Ѿ�FD���}�yu��Ē�,XL\\�x��;U��Wt�v��\\OxWJ9Ȓ�R5�WiMi[�K��f(\0�dĚ�迩�\r�M����7�;��������6�KʦI�\r���xv\r�V3���ɱ.��R������|��^2�^0߾\$�Q��[�D��ܣ�>1'^X~t�1\"6L���+��A��e�����I��~����@����pM>�m<��SK��-H���T76�SMfg�=��GPʰ�P�\r��>�����2Sb\$�C[���(�)��%Q#G`u��Gwp\rk�Ke�zhj��zi(��rO�������T=�7���~�4\"ef�~�d���V�Z���U�-�b'V�J�Z7���)T��8.<�RM�\$�����'�by�\n5����_��w����U�`ei޿J�b�g�u�S��?��`���+��� M�g�7`���\0�_�-���_��?�F�\0����X���[��J�8&~D#��{P���4ܗ��\"�\0��������@ғ��\0F ?*��^��w�О:���u��3xK�^�w���߯�y[Ԟ(���#�/zr_�g��?�\0?�1wMR&M���?�St�T]ݴG�:I����)��B�� v����1�<�t��6�:�W{���x:=��ޚ��:�!!\0x�����q&��0}z\"]��o�z���j�w�����6��J�P۞[\\ }��`S�\0�qHM�/7B��P���]FT��8S5�/I�\r�\n ��O�0aQ\n�>�2�j�;=ڬ�dA=�p�VL)X�\n¦`e\$�TƦQJ��k�7�*O�� .����ġ�\r���\$#p�WT>!��v|��}�נ.%��,;�������f*?�焘��\0��pD��! ��#:MRc��B/06���	7@\0V�vg����hZ\nR\"@��F	����+ʚ�E�I�\n8&2�bX�PĬ�ͤ=h[���+�ʉ\r:��F�\0:*��\r}#��!\"�c;hŦ/0��ޒ�Ej�����]�Z�����\0�@iW_���h�;�V��Rb��P%!��b]SB����Ul	����r��\r�-\0��\"�Q=�Ih����	 F���L��FxR�э@�\0*�j5���k\0�0'�	@El�O���H�Cx�@\"G41�`ϼP(G91��\0��\"f:Qʍ�@�`'�>7�Ȏ�d�����R41�>�rI�H�Gt\n�R�H	��bҏ��71���f�h)D��8�B`���(�V<Q�8c? 2���E�4j\0�9��\r�͐�@�\0'F�D��,�!��H�=�*��E�(���?Ѫ&xd_H�ǢE�6�~�u��G\0R�X��Z~P'U=���@����l+A�\n�h�IiƔ���PG�Z`\$�P������.�;�E�\0�}� ��Q�����%���jA�W�إ\$�!��3r1� {Ӊ%i=IfK�!�e\$���8�0!�h#\\�HF|�i8�tl\$���l����l�i*(�G���L	 �\$��x�.�q\"�Wzs{8d`&�W��\0&E����15�jW�b��ć��V�R����-#{\0�Xi���g*��7�VF3�`妏�p@��#7�	�0��[Ү���[�éh˖\\�o{���T���]��Ŧᑀ8l`f@�reh��\n��W2�*@\0�`K(�L�̷\0vT��\0�c'L����:�� 0��@L1�T0b��h�W�|\\�-���DN��\ns3��\"����`Ǣ�肒�2��&��\r�U+�^��R�eS�n�i0�u˚b	J����2s��p�s^n<���♱�Fl�a�\0���\0�mA2�`|؟6	��nr���\0Dټ��7�&m�ߧ-)���\\���݌\n=���;*���b��蓈�T��y7c��|o�/����:���t�P�<��Y:��K�&C��'G/�@��Q�*�8�v�/��&���W�6p.\0�u3����Bq:(eOP�p	�駲���\r���0�(ac>�N�|��	�t��\n6v�_��e�;y���6f���gQ;y�β[S�	��g�ǰ�O�ud�dH�H�=�Z\r�'���qC*�)����g��E�O�� \"��!k�('�`�\nkhT��*�s��5R�E�a\n#�!1�����\0�;��S�iȼ@(�l���I� �v\r�nj~��63��Έ�I:h����\n.��2pl�9Bt�0\$b��p+�ǀ*�tJ����s�JQ8;4P(��ҧѶ!��.Ppk@�)6�5��!�(��\n+��{`=��H,Ɂ\\Ѵ�4�\"[�C���1���-���luo��4�[���E�%�\"��w] �(� ʏTe��)�K�A�E={ \n�`;?���-�G�5I���.%�����q%E���s���gF��s	�����K�G��n4i/,�i0�u�x)73�Szg���V[��h�Dp'�L<TM��jP*o�≴�\nH���\n�4�M-W�N�A/@�8mH��Rp�t�p�V�=h*0��	�1;\0uG��T6�@s�\0)�6��ƣT�\\�(\"���U,�C:��5i�K�l���ۧ�E*�\"�r����.@jR�J�Q��/��L@�SZ���P�)(jj�J������L*���\0���\r�-��Q*�Qڜg��9�~P@���H���\n-e�\0�Qw%^ ET�< 2H�@޴�e�\0� e#;��I�T�l���+A+C*�Y���h/�D\\�!鬚8�»3�AЙ��E��E�/}0t�J|���1Qm��n%(�p��!\n��±U�)\rsEX���5u%B- ��w]�*��E�)<+��qyV�@�mFH ���BN#�]�YQ1��:��V#�\$������<&�X������x��t�@]G��Զ��j)-@�q��L\nc�I�Y?qC�\r�v(@��X\0Ov�<�R�3X���Q�J����9�9�lxCuīd�� vT�Zkl\r�J��\\o�&?�o6E�q������\r���'3��ɪ�J�6�'Y@�6�FZ50�V�T�y���C`\0��VS!���&�6�6���rD�f`ꛨJvqz���F�����@�ݵ��҅Z.\$kXkJ�\\�\"�\"�֝i��:�E���\roX�\0>P��P�mi]\0�����aV��=���I6�����jK3���Z�Q�m�E���b�0:�32�V4N6����!�l�^ڦ�@h�hU��>:�	��E�>j�����0g�\\|�Sh�7y�ބ�\$��,5aė7&��:[WX4��q� ���J���ׂ�c8!�H���VD�Ď�+�D�:����9,DUa!�X\$��Я�ڋG�܌�B�t9-+o�t��L��}ĭ�qK��x6&��%x��tR�����\"�π�R�IWA`c���}l6��~�*�0vk�p���6��8z+�q�X��w*�E��IN�����*qPKFO\0�,�(��|�����k *YF5���;�<6�@�QU�\"��\rb�OAXÎv��v�)H��o`ST�pbj1+ŋ�e��� ʀQx8@�����5\\Q�,���ĉN��ޘb#Y�H��p1����kB�8N�o�X3,#Uک�'�\"�销�eeH#z��q^rG[��:�\r�m�ng����5��V�]��-(�W�0���~kh\\��Z��`��l����k �o�j�W�!�.�hF���[t�A�w�e�M૫��3!����nK_SF�j���-S�[r�̀w��0^�h�f�-����?���X�5�/������IY �V7�a�d �8�bq��b�n\n1YR�vT���,�+!����N�T��2I�߷�����������K`K\"�����O)\nY��4!}K�^����D@��na�\$@� ��\$A��j����\\�D[=�	bHp�SOAG�ho!F@l�U��`Xn\$\\�͈_��˘`���HB��]�2���\"z0i1�\\�����w�.�fy޻K)����� p�0���X�S>1	*,]��\r\"���<cQ��\$t��q��.��	<��+t,�]L�!�{�g���X��\$��6v����� ����%G�H������E����X��*��0ۊ)q�nC�)I���\"�����툳�`�KF����@�d�5��A��p�{�\\���pɾN�r�'�S(+5�Њ+�\"�Ā�U0�iː����!nM��brK���6ú�r���|a����@�x|��ka�9WR4\"?�5��p�ۓ��k�rĘ����ߒ����7Hp��5�YpW���G#�rʶAWD+`��=�\"�}�@H�\\�p���Ѐ�ߋ�)C3�!�sO:)��_F/\r4���<A��\nn�/T�3f7P1�6����OYлϲ���q��;�؁���a�XtS<��9�nws�x@1Ξxs�?��3Ş@���54��o�ȃ0����pR\0���������yq��L&S^:��Q�>\\4OIn��Z�n��v�3�3�+P��L(�������.x�\$�«C��Cn�A�k�c:L�6���r�w���h����nr�Z��=�=j�ђ���6}M�G�u~�3���bg4���s6s�Q��#:�3g~v3���<�+�<���a}ϧ=�e�8�'n)ӞcC�z��4L=h��{i����J�^~��wg�D�jL���^����=6ΧN�Ӕ����\\��D���N���E�?h�:S�*>��+�u�hh҅�W�E1j�x����t�'�t�[��wS���9��T��[�,�j�v����t��A#T���枂9��j�K-��ޠ���Y�i�Qe?��4Ӟ���_Wz����@JkWY�h��pu����j|z4���	�i��m�	�O5�\0>�|�9�ז��轠��gVy��u���=}gs_���V�sծ{�k�@r�^���(�w����H'��a�=i��N�4����_{�6�tϨ��ϗe�[�h-��Ul?J��0O\0^�Hl�\0.��Z������xu���\"<	�/7���� ���i:��\nǠ���;��!�3���_0�`�\0H`���2\0��H�#h�[�P<��עg����m@~�(��\0ߵk�Y�v���#>���\nz\n�@�Q�\n(�G��\n����'k����5�n�5ۨ�@_`Ї_l�1���wp�P�w���\0��c��oEl{�ݾ�7����o0����Ibϝ�n�z����﷛� ���{�8�w�=��|�/y�3a�߼#xq����@��ka�!�\08d�m��R[wvǋRGp8���v�\$Z���m��t��������������ǽ����u�o�p�`2��m|;#x�m�n�~;��V�E�������3O�\r�,~o�w[��N��}�� �cly��O����;��?�~�^j\"�Wz�:�'xW��.�	�u�(��Ý�q��<g��v�hWq��\\;ߟ8��)M\\��5vڷx=h�i�b-���|b���py�DЕHh\rce��y7�p��x��G�@D=� ����1��!4Ra\r�9�!\0'�Y����@>iS>����o��o��fsO 9�.����\"�F��l��20��E!Q���ːD9d�BW4��\0��y`RoF>F�a��0�����0	�2�<�I�P'�\\���I�\0\$��\n R�aU�.�sЄ��\"���1І�e�Y砢�Z�q��1�|��#�G!�P�P\0|�H�Fnp>W�:��`YP%�ď�\n�a8��P>�����`]��4�`<�r\0�Î������z�4����8�����4�`m�h:�Ϊ�HD���j�+p>*����8�ՠ0�8�A��:���с�]w�ú�z>9\n+�������:����ii�PoG0���1��)�Z�ږ�n�����eR֖��g�M�����gs�LC�r�8Ѐ�!�����3R)��0�0��s�I��J�VPpK\n|9e[���ˑ��D0����z4ϑ�o������,N8n��s�#{蓷z3�>�BS�\";�e5VD0���[\$7z0������=8�	T 3���Q�'R������n��L�yŋ��'�\0o��,��\0:[}(���|���X�>xvqW�?tB�E1wG;�!�݋5΀|�0��JI@��#���uņI��\\p8�!'�]߮��l-�l�S�B��,ӗ���]��1�ԕH��N�8%%�	��/�;�FGS���h�\\ل�c�t����2|�W�\$t��<�h�O��+#�B�aN1��{��y�w���2�\\Z&)�d�b'��,Xxm�~�H��@:d	>=-��lK��܏�J�\0���́�@�rϥ�@\"�(A����Z�7�h>����\\����#>���\0��Xr�Y��Yxŝ�q=:��Թ�\rl�o�m�gb��������D_�Tx�C���0.��y��R]�_���Z�ǻW�I��G��	Mɪ(��|@\0SO��s� {��@k}��FXS�b8��=��_����l�\0�=�g��{�H��yG���� s�_�J\$hk�F�q������d4ω����'���>vϏ��!_7�Vq��@1z�uSe��jKdyu���S�.�2�\"�{��K���?�s��˦h��R�d��`:y����Gھ\nQ�����ow��'��hS��>���L�X}��e���G��@9��퟈�W�|��Ϲ�@�_��uZ=��,���!}���\0�I@��#��\"�'�Y`��\\?��p��,G����ל_��'�G����	�T��#�o��H\r��\"���o�}��?��O鼔7�|'���=8�M��Q�y�a�H�?��߮� ���\0���bUd�67���I O����\"-�2_�0�\r�?�������hO׿�t\0\0002�~�° 4���K,��oh��	Pc���z`@��\"�����H; ,=��'S�.b��S����Cc���욌�R,~��X�@ '��8Z0�&�(np<pȣ�32(��.@R3��@^\r�+�@�,���\$	ϟ��E���t�B,���⪀ʰh\r�><6]#���;��C�.Ҏ����8�P�3��;@��L,+>���p(#�-�f1�z���,8�ߠ��ƐP�:9����R�۳����)e\0ڢR��!�\nr{��e����GA@*��n�D��6��������N�\r�R���8QK�0��颽��>PN���IQ=r<�;&��f�NGJ;�UA�����A�P�&������`�����);��!�s\0���p�p\r�����n(��@�%&	S�dY����uC�,��8O�#�����o���R�v,��#�|7�\"Cp����B�`�j�X3�~R�@��v�����9B#���@\n�0�>T�����-�5��/�=� ���E����\n��d\"!�;��p*n��Z�\08/�jX�\r��>F	Pϐe>��O��L����O0�\0�)�k���㦃[	��ϳ���'L��	����1 1\0��C�1T�`����Rʐz�Ě����p��������< .�>�5��\0���>� Bnˊ<\"he�>к�î��s�!�H�{ܐ�!\r�\r�\"��|��>R�1d���\"U@�D6����3���>o\r����v�L:K�2�+�0쾁�>��\0�� ���B�{!r*H��y;�`8\0��د��d����\r�0���2A����?��+�\0�Å\0A����wS��l����\r[ԡ�6�co�=����0�z/J+�ꆌ�W[��~C0��e�30HQP�DPY�}�4#YD���p)	�|�@���&�-��/F�	�T�	����aH5�#��H.�A>��0;.���Y�ġ	�*�D2�=3�	pBnuDw\n�!�z�C�Q \0��HQ4D�*��7\0�J��%ıp�uD�(�O=!�>�u,7��1��TM��+�3�1:\"P�����RQ?���P���+�11= �M\$Z��lT7�,Nq%E!�S�2�&��U*>GDS&����ozh8881\\:��Z0h���T �C+#ʱA%��D!\0�����XDA�3\0�!\\�#�h���9b��T�!d�����Y�j2��S����\nA+ͽ��H�wD`�(AB*��+%�E��X.ˠB�#��ȿ��&��Xe�Eo�\"��|�r��8�W�2�@8Da�|�������N�h����J8[�۳����W�z�{Z\"L\0�\0��Ȇ8�x�۶X@�� �E����h;�af��1��;n��hZ3�E����0|� 옑��A���t�B,~�W�8^�Ǡ׃��<2/	�8�+��۔���O+�%P#ή\n?�߉?��e˔�O\\]�7(#��D۾�(!c)�N����MF�E�#DX�g�)�0�A�\0�:�rB��``  ��Q��H>!\rB��\0��V%ce�HFH��m2�B�2I����`#���D>���n\n:L���9C���0��\0��x(ޏ�(\n����L�\"G�\n@���`[���\ni'\0��)������y)&��(p\0�N�	�\"��N:8��.\r!��'4|ל~����ʀ���\"�c��Dlt����0c��5kQQר+�Z��Gk�!F��c�4��Rx@�&>z=��\$(?���(\n쀨>�	�ҵ���Cqی��t-}�G,t�GW �xq�Hf�b\0�\0z���T9zwЅ�Dmn'�ccb�H\0z���3�!����� H��Hz׀�Iy\",�-�\0�\"<�2���'�#H`�d-�#cl�jĞ`��i(�_���dgȎ�ǂ*�j\r�\0�>� 6���6�2�kj�<�Cq��9�Đ��I\r\$C�AI\$x\r�H��7�8 ܀Z�pZrR����_�U\0�l\r��IR�Xi\0<����r�~�x�S��%��^�%j@^��T3�3ɀGH�z��&\$�(��q\0��f&8+�\rɗ%�2hC�x���I��lbɀ�(h�S�Y&��B������`�f��x�v�n.L+��/\"=I�0�d�\$4�7r����A���(4�2gJ(D��=F�����(����-'Ġ�XG�2�9Z=���,��r`);x\"��8;��>�&�����',�@��2�pl���:0�lI��\rr�JD���������hA�z22p�`O2h��8H��Ąwt�BF���g`7���2{�,Kl���߰%C%�om���������+X����41򹸎\n�2p��	ZB!�=V�ܨ�Ȁ�+H6���*��\0�k���%<� �K',3�r�I�;��8\0Z�+Eܭ�`������+l����W+�Yҵ-t��f�b�Q��_-Ӏޅ�+�� 95�LjJ.Gʩ,\\��ԅ.\$�2�J�\\�-��1�-c���ˇ.l�f�xBqK�,d��ˀ�8�A�Ko-��������3K��r��/|����/\\�r���,��HϤ�!�Y�1�0�@�.�&|����+��J\0�0P3J�-ZQ�	�\r&����\n�L�*���j�ĉ|�����#Ծ�\"˺���A��/���8�)1#�7\$\"�6\n>\n���7L�1���h9�\0�B�Z�d�#�b:\0+A���22��'̕\nt���̜�O��2lʳ.L��HC\0��2���+L�\\��r�Kk+���˳.ꌒ�;(Dƀ���1s����d�s9�����P4�쌜��@�.���A��nhJ�1�3�K�0��3J\$\0��2�Lk3��Q�;3��n\0\0�,�sI�@��u/VA�1���UM�<�Le4D�2��V�% �Ap\nȬ2��35���A-��T�u5�3�۹1+fL~�\n���	��->�� �ҡM�4XL�S��dٲ�͟*\\�@ͨ��Y�k����SDM�5 Xf����D�s���Us%	�̱p+K�6��/���ݒ�8X�ނ=K�6pH����%�3�ͫ7l�I�K0���L��D��u���`��P\r��SO͙&(;�L@��ψN>S��2��8(���`J�E��r�F	2��SE��M��M��\$q�E��\$�ã/I\$\\���ID�\"��\n䱺�w.t�S	���ђP��#\nW��-\0Cҵ�:j�R��^S���8;d�`���5Ԫ�aʖ��E��+(Xr�M�;��3�;���B,��*1&����2X�S���)<� �L9;�RSN����gIs+��ӰK�<��s�LY-Z�:A<���OO*��2v�W7��+|���˻<T���9�h����y\$<��#ρ;����v�\$��O�\0� �,Hk��-���Ϛ\r����ϣ;���O�>�����7>��3@O{.4�pO�?T�b���.�.~O�4��S���>1SS��*4�Pȣ�>�����3�\0�W�>��2��><���P?4��@��t\nN����A�xp��%=P@��C�@�R�˟?x��\n���0N�w�O?�TJC@��#�	.d���M��t�&=�\\�4��A��:L����\$���N��:��\r��I'���A�rግ;\r�/��C���B�Ӯ�i>L��7:9�����|�C\$��)�����z@�tl�:>��C�\n�Bi0G��,\0�FD%p)�o\0����\n>��`)QZI�KG�%M\0#\0�D���Q.H�'\$�E\n �\$ܐ%4I�D�3o�:L�\$��m ��0�	�B�\\(����8��通�h��D��C�sDX4TK���{��x�`\n�,��\nE��:�p\n�'��>��o\0���tI��` -\0�D��/��KP�`/���H�\$\n=���>��U�FP0���UG}4B\$?E����%�T�WD} *�H0�T�\0t������\"!o\0�E�7��R.���tfRFu!ԐD�\n�\0�F-4V�QH�%4��0uN\0�D�QRuE�	)��I\n�&Q�m�)ǚ�m �#\\����D��(\$̓x4��WFM&ԜR5H�%q��[F�+���IF \nT�R3D�L�o���y4TQ/E��[ў<�t^��F��)Q��+4�Q�I�#���IF�'TiѪX��!ѱF�*�nR�>�5�p��Km+�s��������I���R�E�+ԩ��M\0��(R�?�+HҀ�J�\"T�D���\$���	4wQ�}Tz\0�G�8|�x���R��6�R�	4XR6\n�4y�mN��Q�NM�&R�H&�2Q/�7#�қ�{�'�ҍ,|����\n�	.�\0�>�{�o#1D�;��?U��ҕJ�9�*����j����F�N��щJ� #�~%-?C���L�3�@EP�{`>Q�Ȕ��%O�)4�R%I�@��%,�\"���I�<�����\$ԉTP>�\n�\0QP5D��kOF�TY�<�o�Q�=T�\0��x	5�D�,�0?�i�?x�  �mE}>�|����[��\0����&RL���H�S9�G�I��1䀖��M4V�H�oT-S�)Q�G�F [��TQRjN��#x]N(�U�8\nuU\n?5,TmԞ?����?��@�U\n�u-��R�9��U/S \nU3�IESt�QYJu.�Q��F�o\$&���i	��KPC�6�>�5�G\0uR��u)U'R�0�Ѐ�DuIU�J@	��:�V8*�Rf%&�\\�R��MU9R��fUAU[T�UQSe[��\0�KeZUa��Uh��mS<���,R�s�`&Tj@��G�!\\x�^�0>��\0&��p�΂Q�Q�)T�U�Ps�@%\0�W�	`\$��(1�Q?�\$C�Qp\n�O�J��X�#��V7X�u;�!YB��S�c��+V����#MU�W�H��U�R�ǅU-+��VmY}\\���OK�M��\$�S�eToV���HT��!!<{�R��ZA5�R�!=3U��(�{@*Ratz\0)Q�P5H؏���հ�N5+���P�[��9�V%\"����\n����G�SL�����9�����l����\rV�ؤ�[�ou�UIY�R_T�Y�p5O֧\\�q`�U�[�Bu'Uw\\mRU�ԭ\\Es5�K\\���V�\\�S�{�AZ%O��\$��F���>�5E�WVm`��Wd]& \$�Ό����!R�Z}ԅ]}v5���ZUg��Q^y` �!^=F��R�^�v�U�Kex@+��r5�#�@?=�u�Γs���ץY�N�sS!^c�5�\$.�u`��\0�XE~1�9��J�UZ�@�#1_[�4J�2�\n�\$VI�4n�\0�?�4a�R�!U~)&��B>t�R�I�0��_EkTUS��|��Uk_�8�&��E��(‘?�@���J�5���JU�BQT}HV��j��Qx\ne�VsU=���V�N�4ղؗ\\x����R34�G�D\":	KQ�>�[�\r�Y_�#!�#][j<6خX	���c���#KL}>`'\0��5�X�cU�[\0��(���Wt|t�R]p�/�]H2I�QO��1�S�Qj�Z����H���m���)d�^SXCY\r�tu@J�p��%��M������?�UQ�\n�=R�ar:ԿE���-G�\0\$��d���]�meh*��Q�Wt��c��`��A�Y=S\r���	m-���=Mw�H�]J�\"䴏������f�\"�{#9Te����M�c��N�I����D������U�6��g��2��ݝ�e�a�L��Q&&uT�X�51Y�>����S�֊Q#�I���j�\0����W�P��?ub5FU�Ln�)V5R�@��\$!%o��P��'��E�U��P�-����B�p\n�F\$�S4�t�UF|{�q�ȓ0���Umjs�������\$�ڛj��c�ڐ��֫��aZI5X��j�26��&>v��\n\r)2�_k�G��TJ��eQ-c�Z�VM�ֽ�z>�]�a�c��c��`t��H��j�6��+k�M�\0�>���##3l=�'���^6�\0�èv�Z9Se��\"���bΡ�B>�)�/T�=�9\0�`P�\$\0�]�/0ڪ��䵏�k-�6��{k���[�F\r|�SѿJ��MQ�D=�/�WX���V�a�'���a�to��l冶�Xj}C@\"�KP����om�3\0#HV���v��~�{���?gx	n|[�?U��[r�h��G�`�3#Gk%L��\0�I�`C�D��	 \"\0��ŧ��#cN�6�ڹf���zێ�;Ѥ�eeF�7�/N\r:��Q�G�9	\$��I�ռ��]��T��WGs��dW�M�I����f�Bc�ۤ����!#cnu&(�S�_�w��Sf�&T�Z:��0C�S�LN`ܳYj=��>Ų��Z!=�rV]g��	ӣr���Xl��-.�U�'uJuJ\0�s�J�'W%���\\>?�B��V�j4���J}I/-ҝrRL�S�3\0,Rgqӭ��Tf>�1��\0�_���\\V8��Z�t��c耆�<^\\�ll�j\0���T�]C��w�ΓzI��ZwN���pVW�jv�Y�>�2�	o\$|U�W�L%{toX3_���R�J5~6\"��Zl}�`�kc����eR=^UԎ��1�ѽw7e�d��v��b�=��\0�f��,��m�)��Gp��-Ӽ�)9L���>|�� \"�@���5�`�:��\0�,��t@��x���l�J���b�6������a��A\0ػAR�[A���0\$qo�A��S��@���<@�y��\"as.����V^��讥^�����\0��H���[H@�bK����)z�\r����=��^�z�B\0�����N�o<̇t<�x�\0ڬ0*R��I{��^�E�:�{KՐ�1E�0��Y����/��c��\"\0��4���F�7'���\n�0��`U�T��?MP���l��4��r(	��Z�|���&��t\"I����L�w+�m}����Wi\r>�U__u��63�y[�8�T-��V�}�x��_~�%�7��{jM�o_�E�����~]�P\$�J�CaXG�9�\0007Ń5�A#�\0.���\r˴��_������%����\n�\r#<M�x�J���|��2�\0��;o�^a+F���笀Lk��;�_���#��M\\����pr@��õ�����OR���~z��A�NE�Y�O	(1N׉�R��8��C�����n?O)��1�A�Do\0�\r�Ǣ?�kJ��\"�,�OF��a����-b�6]PS�)ƙ�5xC�=@j����L�����L�:\"胻Ί�l#���B�k��������@��N��:�>�|B����9�	���:N��\$��S� �CB:j6����ΉJk��uK�_�W�͢ØI�=@Tv��\n0^o�\\�Ӡ?/��&u�.��_��\r��C��+��c�~�J�b�6���e\0�y�ѡ\0wx�h��8j%S���VH@N'�\\ۯ��N�`n\r��u�n�K�qU�B�+�f>G��\r���=@G���d���\n�)��FO� hʷ��ÈfC�ɅX|��I�]��3auy�Ui^�9y�\no^rt\r8��͇#����N	V��Y�;�c*�%V�<��#�h9r�\rxc�v(\ra���(xja�`g�0�V̼���Q��x(���glհ{��gh`sW<Kj�'�;)�Gnq\$�p�+�Ɍ_��d��^& ���D�x�!b�v�!EjPV�'����(�=�b�\r�\"�b��L�\0���bt�\n>J���1;�����ۈ�4^s�Q�p`�fr`7���x��E<l���	8s��'PT��ֺ�˃��z_�T[>��:��`�1.���;7�@��[��>��6!�*\$`��\0���`,�������@����?�m�>�>\0�LCǸ�R��n��/+�`;C����\0�*�<F���+���q M���;1�K\n�:b�3j1��l�:c>�Y���h���ގ�#�;���3ֺ�8�5�:�\\��\0XH���a�����M1�\\�L[YC��vN��\0+\0��t#�\$�����!@*�l��	F�dhd���F���&��Ƙf�)=��0��4�x\0004ED�6K��䢣���\0�nN�];q�4sj-�=-8���\0�sǨ���D�f5p4����J�^���'Ӕ[��H^�NR F�Kw�z�� ��E����gF|!�c���o�db����x�\0�-��6�,E��_���3u�p ��/�wz�(��ex�Ra�H�Y�ce��5�9d\0�0@2@Ґ�Y�fey��Y�cMו�h����[�ez\rv\\0�e���\\�cʃ��[�ue��NY`��ۖ�]9h姗~^Yqe���]�qe_|6!���u�`�f��J�{�7��M{�Yه��j�e��C��S6\0DuasFL}�\$ȇ�(��Mb���Ƥ,0Buί���т2�gxFљ{�a�n:i\rPj�e��r�r��G�BY��M+q��iY�d˙�`0��,>6�fo�0���o�� �Xf����\0�V�L!��f��l��6� �/��1e��\0�>kbf�\r�!�uf�<%�(r˛�a&	����Y��!���mBg=@��\r�; \r�5phI�9bm�\$BYˋ���g�x�#�@QEO��m9���0\"���!�t���ˉ��Ї�O* ���\0��>%�\$�o�rN&s9�f��4���g��~jM�f�wy�g�y�\\`X1y5x����^z�_,& k���|����1x��A�6� \n�o蔻�&x��gg�{r�?緛�-����|t�3�����}gHgK�9����J�<C�C��1��9�7��g����h6!0H���cdy�f��DA;��9�T���0��\0�p�����!� 6^�.�S²?���E(P�Έ .���5��h���EPJv��.���+�\$�5��>P+�?~��g�6\r��h��p�z(�W��`��\"y���:�FadŬ�6:��f��i\0����A;�e�����^��w�f� >y�����`-\r����\0�hr\r�r�8i\"_�	����9�CI��fXˈ2���\"�Ţ����h�L~�\"���%V�:!%��xy�izyg�vx�]���}qg����Zi��|��`�+ _�g�����٣������譞6PA�ʀ\$�=�9�����h��|p��������!��.�!�����i�^���iˢ�8zVC����Z\"����(�����9�U)��!DgU\0�j��?`��4�LTo@�B����N�a�{�r�:\n̟�E��8æ&=�E�*Z:\n?��g���̊��h��.����N�5(�S�h��i2�*c�f�@����7��z\"�|��rP�.ǀ�L8T'��k���:(�q2&��ED�2~���ر�����9���v���8������@��^X=X`��qZ��Q�֮`9j�5^���@竸�n�qv����3����(I6�j�dT���\\� ��3�,��h�k�3�(�3���P�u�V�|\0阮U�k;��JQ���.��	:J\r��1��n�BI\r\0ɬh@��?�N�\nsh���\"��;�r~7O�\$��(�5�R���	�ʽj����FYF��ܔ��~�x޾�f��\"�vۓo��˨��º#��a�����P���<��h�-3麝/G�x����n�i@\"�G�?��,�Zp�xX`v�4X������[�I��7�åXc	��!�b�}�j�_��9�5qti�6f������ٞ5���Fƹ�iѱ�pX'�2��r���0�ƺ��D,#G�U2��؏�I��\rl(�� �챣��=�A�a�쩳-8�dbS����4~���H;���0�6��b��{��޺R���s3z�����N�ބ��`�ˆ+���4<�^a�y���	}r���y������k�&4@��?~���cE����@�LS@���z^�qqN��</H�j^sC�`��sbgGy����^\n�N�\n:G�N}�c\n���� +���=�p�1��N�TB[d������Ћ��ܹ�`�n�oj;�jěwh����c9��p̡[y4���05�͋N��+ο��`Xda��/zn*�P�����#t�赸~�9W�	�V��~=�#��n)����	2��;�j:��J�k�C�!>x��5��==�2���.��|�'���[��'�;��v�������������;:SA	�&�[�me���n������˵���<��6ma�=Y.神��:g����腀����;�I߻x�[��I�J\0�~�zaY������wT\\`��V\n�~P)�zJ�������Q@��[�{rʉ�D�B�v��|i-�E��K�;^n�{���:Nh;���2��ƀp�Ѵ6����罘9�9����X�hQ�~���iA�@D �j���}�ozLV���ѳ~���	8B?�#F}F�Td����e��zc��F���g�7Η���� 6�#.E£����£��S�.J3��5��Kɥ�J���;���n5��:yS��C�voս.�{��	d\\0�?W\0!)�'����Eg�;�+��\0�Y�Nt�bp+��c�����\0�B=\"�c�T�:B������c��������P�I��D��V0��!ROl�O�N~aF�|%�ߺ�����)O��	�W�o����Q�w��:ٟl�0h@:���օ8�Q�&�[�n�F��p,�æ�@��JT�w�9��(���<�{�ƐO\r�	���ڂ\$m�/HnP\$o^�U��\"���{Ė�<.���n�q8\r�\0;�n������硟�+�޳3��n{�D\$7�,Ez7\0��l!{��8��x҂�.s8�PA�Fx�r����Qۮ���1̅�p+@�d��9OP5�lK�/�����\\m����s�q���v�Q�/���	�!���z�7�o��Eǆ�:q�V�5�?G�HO��O�\$�l��+�,�\r;�����~�Ač錳�{�`7|��Ă���r'��Ji\rc+�|�#+<&қ�<W,��>��^�P�&n�Jh�e�%d������C�i�zX�A�'D�>��Έ�Ek���@�B�w(�.��\n99A�hN�c�kN��d`���p`��%2���\0");
} else {
	header("Content-Type: image/gif");
	switch ($_GET["file"]) {
		case "plus.gif": echo "GIF89a\0\0�\0001���\0\0����\0\0\0!�\0\0\0,\0\0\0\0\0\0!�����M��*)�o��) q��e���#��L�\0;"; break;
		case "cross.gif": echo "GIF89a\0\0�\0001���\0\0����\0\0\0!�\0\0\0,\0\0\0\0\0\0#�����#\na�Fo~y�.�_wa��1�J�G�L�6]\0\0;"; break;
		case "up.gif": echo "GIF89a\0\0�\0001���\0\0����\0\0\0!�\0\0\0,\0\0\0\0\0\0 �����MQN\n�}��a8�y�aŶ�\0��\0;"; break;
		case "down.gif": echo "GIF89a\0\0�\0001���\0\0����\0\0\0!�\0\0\0,\0\0\0\0\0\0 �����M��*)�[W�\\��L&ٜƶ�\0��\0;"; break;
		case "arrow.gif": echo "GIF89a\0\n\0�\0\0������!�\0\0\0,\0\0\0\0\0\n\0\0�i������Ӳ޻\0\0;"; break;
	}
}
exit;

}

if ($_GET["script"] == "version") {
	$fp = file_open_lock(get_temp_dir() . "/adminer.version");
	if ($fp) {
		file_write_unlock($fp, serialize(array("signature" => $_POST["signature"], "version" => $_POST["version"])));
	}
	exit;
}

global $adminer, $connection, $driver, $drivers, $edit_functions, $enum_length, $error, $functions, $grouping, $HTTPS, $inout, $jush, $LANG, $langs, $on_actions, $permanent, $structured_types, $has_token, $token, $translations, $types, $unsigned, $VERSION; // allows including Adminer inside a function

if (!$_SERVER["REQUEST_URI"]) { // IIS 5 compatibility
	$_SERVER["REQUEST_URI"] = $_SERVER["ORIG_PATH_INFO"];
}
if (!strpos($_SERVER["REQUEST_URI"], '?') && $_SERVER["QUERY_STRING"] != "") { // IIS 7 compatibility
	$_SERVER["REQUEST_URI"] .= "?$_SERVER[QUERY_STRING]";
}
if ($_SERVER["HTTP_X_FORWARDED_PREFIX"]) {
	$_SERVER["REQUEST_URI"] = $_SERVER["HTTP_X_FORWARDED_PREFIX"] . $_SERVER["REQUEST_URI"];
}
$HTTPS = ($_SERVER["HTTPS"] && strcasecmp($_SERVER["HTTPS"], "off")) || ini_bool("session.cookie_secure"); // session.cookie_secure could be set on HTTP if we are behind a reverse proxy

@ini_set("session.use_trans_sid", false); // protect links in export, @ - may be disabled
if (!defined("SID")) {
	session_cache_limiter(""); // to allow restarting session
	session_name("adminer_sid"); // use specific session name to get own namespace
	$params = array(0, preg_replace('~\?.*~', '', $_SERVER["REQUEST_URI"]), "", $HTTPS);
	if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
		$params[] = true; // HttpOnly
	}
	call_user_func_array('session_set_cookie_params', $params); // ini_set() may be disabled
	session_start();
}

// disable magic quotes to be able to use database escaping function
remove_slashes(array(&$_GET, &$_POST, &$_COOKIE), $filter);
if (function_exists("get_magic_quotes_runtime") && get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(false);
}
@set_time_limit(0); // @ - can be disabled
@ini_set("zend.ze1_compatibility_mode", false); // @ - deprecated
@ini_set("precision", 15); // @ - can be disabled, 15 - internal PHP precision


// not used in a single language version

$langs = array(
	'en' => 'English', // Jakub Vrána - https://www.vrana.cz
	'ar' => 'العربية', // Y.M Amine - Algeria - nbr7@live.fr
	'bg' => 'Български', // Deyan Delchev
	'bn' => 'বাংলা', // Dipak Kumar - dipak.ndc@gmail.com
	'bs' => 'Bosanski', // Emir Kurtovic
	'ca' => 'Català', // Joan Llosas
	'cs' => 'Čeština', // Jakub Vrána - https://www.vrana.cz
	'da' => 'Dansk', // Jarne W. Beutnagel - jarne@beutnagel.dk
	'de' => 'Deutsch', // Klemens Häckel - http://clickdimension.wordpress.com
	'el' => 'Ελληνικά', // Dimitrios T. Tanis - jtanis@tanisfood.gr
	'es' => 'Español', // Klemens Häckel - http://clickdimension.wordpress.com
	'et' => 'Eesti', // Priit Kallas
	'fa' => 'فارسی', // mojtaba barghbani - Iran - mbarghbani@gmail.com, Nima Amini - http://nimlog.com
	'fi' => 'Suomi', // Finnish - Kari Eveli - http://www.lexitec.fi/
	'fr' => 'Français', // Francis Gagné, Aurélien Royer
	'gl' => 'Galego', // Eduardo Penabad Ramos
	'he' => 'עברית', // Binyamin Yawitz - https://stuff-group.com/
	'hu' => 'Magyar', // Borsos Szilárd (Borsosfi) - http://www.borsosfi.hu, info@borsosfi.hu
	'id' => 'Bahasa Indonesia', // Ivan Lanin - http://ivan.lanin.org
	'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
	'ja' => '日本語', // Hitoshi Ozawa - http://sourceforge.jp/projects/oss-ja-jpn/releases/
	'ka' => 'ქართული', // Saba Khmaladze skhmaladze@uglt.org
	'ko' => '한국어', // dalli - skcha67@gmail.com
	'lt' => 'Lietuvių', // Paulius Leščinskas - http://www.lescinskas.lt
	'ms' => 'Bahasa Melayu', // Pisyek
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'no' => 'Norsk', // Iver Odin Kvello, mupublishing.com
	'pl' => 'Polski', // Radosław Kowalewski - http://srsbiz.pl/
	'pt' => 'Português', // André Dias
	'pt-br' => 'Português (Brazil)', // Gian Live - gian@live.com, Davi Alexandre davi@davialexandre.com.br, RobertoPC - http://www.robertopc.com.br
	'ro' => 'Limba Română', // .nick .messing - dot.nick.dot.messing@gmail.com
	'ru' => 'Русский', // Maksim Izmaylov; Andre Polykanine - https://github.com/Oire/
	'sk' => 'Slovenčina', // Ivan Suchy - http://www.ivansuchy.com, Juraj Krivda - http://www.jstudio.cz
	'sl' => 'Slovenski', // Matej Ferlan - www.itdinamik.com, matej.ferlan@itdinamik.com
	'sr' => 'Српски', // Nikola Radovanović - cobisimo@gmail.com
	'sv' => 'Svenska', // rasmusolle - https://github.com/rasmusolle
	'ta' => 'த‌மிழ்', // G. Sampath Kumar, Chennai, India, sampathkumar11@gmail.com
	'th' => 'ภาษาไทย', // Panya Saraphi, elect.tu@gmail.com - http://www.opencart2u.com/
	'tr' => 'Türkçe', // Bilgehan Korkmaz - turktron.com
	'uk' => 'Українська', // Valerii Kryzhov
	'vi' => 'Tiếng Việt', // Giang Manh @ manhgd google mail
	'zh' => '简体中文', // Mr. Lodar, vea - urn2.net - vea.urn2@gmail.com
	'zh-tw' => '繁體中文', // http://tzangms.com
);

/** Get current language
* @return string
*/
function get_lang() {
	global $LANG;
	return $LANG;
}

/** Translate string
* @param string
* @param int
* @return string
*/
function lang($idf, $number = null) {
	if (is_string($idf)) { // compiled version uses numbers, string comes from a plugin
		// English translation is closest to the original identifiers //! pluralized translations are not found
		$pos = array_search($idf, get_translations("en")); //! this should be cached
		if ($pos !== false) {
			$idf = $pos;
		}
	}
	global $LANG, $translations;
	$translation = ($translations[$idf] ? $translations[$idf] : $idf);
	if (is_array($translation)) {
		$pos = ($number == 1 ? 0
			: ($LANG == 'cs' || $LANG == 'sk' ? ($number && $number < 5 ? 1 : 2) // different forms for 1, 2-4, other
			: ($LANG == 'fr' ? (!$number ? 0 : 1) // different forms for 0-1, other
			: ($LANG == 'pl' ? ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2) // different forms for 1, 2-4 except 12-14, other
			: ($LANG == 'sl' ? ($number % 100 == 1 ? 0 : ($number % 100 == 2 ? 1 : ($number % 100 == 3 || $number % 100 == 4 ? 2 : 3))) // different forms for 1, 2, 3-4, other
			: ($LANG == 'lt' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1, 12-19, other
			: ($LANG == 'bs' || $LANG == 'ru' || $LANG == 'sr' || $LANG == 'uk' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1 except 11, 2-4 except 12-14, other
			: 1 // different forms for 1, other
		))))))); // http://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
		$translation = $translation[$pos];
	}
	$args = func_get_args();
	array_shift($args);
	$format = str_replace("%d", "%s", $translation);
	if ($format != $translation) {
		$args[0] = format_number($number);
	}
	return vsprintf($format, $args);
}

function switch_lang() {
	global $LANG, $langs;
	echo "<form action='' method='post'>\n<div id='lang'>";
	echo lang(19) . ": " . html_select("lang", $langs, $LANG, "this.form.submit();");
	echo " <input type='submit' value='" . lang(20) . "' class='hidden'>\n";
	echo "<input type='hidden' name='token' value='" . get_token() . "'>\n"; // $token may be empty in auth.inc.php
	echo "</div>\n</form>\n";
}

if (isset($_POST["lang"]) && verify_token()) { // $error not yet available
	cookie("adminer_lang", $_POST["lang"]);
	$_SESSION["lang"] = $_POST["lang"]; // cookies may be disabled
	$_SESSION["translations"] = array(); // used in compiled version
	redirect(remove_from_uri());
}

$LANG = "en";
if (isset($langs[$_COOKIE["adminer_lang"]])) {
	cookie("adminer_lang", $_COOKIE["adminer_lang"]);
	$LANG = $_COOKIE["adminer_lang"];
} elseif (isset($langs[$_SESSION["lang"]])) {
	$LANG = $_SESSION["lang"];
} else {
	$accept_language = array();
	preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~', str_replace("_", "-", strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"])), $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$accept_language[$match[1]] = (isset($match[3]) ? $match[3] : 1);
	}
	arsort($accept_language);
	foreach ($accept_language as $key => $q) {
		if (isset($langs[$key])) {
			$LANG = $key;
			break;
		}
		$key = preg_replace('~-.*~', '', $key);
		if (!isset($accept_language[$key]) && isset($langs[$key])) {
			$LANG = $key;
			break;
		}
	}
}

$translations = $_SESSION["translations"];
if ($_SESSION["translations_version"] != 1579331192) {
	$translations = array();
	$_SESSION["translations_version"] = 1579331192;
}

function get_translations($lang) {
	switch ($lang) {
		case "en": $compressed = "A9D�y�@s:�G�(�ff�����	��:�S���a2\"1�..L'�I��m�#�s,�K��OP#I�@%9��i4�o2ύ���,9�%�P�b2��a��r\n2�NC�(�r4��1C`(�:Eb�9A�i:�&㙔�y��F��Y��\r�\n� 8Z�S=\$A����`�=�܌���0�\n��dF�	��n:Zΰ)��Q���mw����O��mfpQ�΂��q��a�į�#q��w7S�X3���Q��/�ӗJ�6�����g2qs�_f��o��E�2�<��B�6�k�@���Z���Φ�#Ƥ�nE�c���Ђ���>`@\$cB3��:����x���߻�8��x�����J\0|6����3,��bׇ�x�4�8�1�����Dc�:C������ΎA&2��,�.(�N'N������78c���C�:��E��B�6%�Ш<�\r=\$6�-�\n:��ƀӫˌ3#��94�N)��\0#�t�4��0�3����W�aAc@�#�пU�2)0�X�Ikl*8\0003��0�[���T643�1��@�&\ru8�>#}2�m4�8C���{�C����4�� W���>W��s����2����Y�(�	/�˂\\����b �/��d\"'�~�,�r)s([��3\\��E\n�%��C���˥��z:P��A,�8�7�3B�7��B\rA Cx�3\r�JP��� *\r���7(��5B�h�U@O�wjI~����G�F�5��!�1�ܐ�ˎ[K�0������6� �gU%��Ҥ�:�.hAG��T�/�M�8ŌT_�q�o�c�{�2�!Ȫ:)+��`}�Xl�-˯m��b�J2EH�7��N���	S�8��l���㇤���\\[��Q�mGQ�}ޅ����k_&I؟��^X>em�2���HL��\rd𦩐���R<O8׹�\"���)��� ��VY\"\\e� A s�=\$�0�e��[hsq\r���De�\n�=+���ҫL1�,���~̈��`�q��B@Pzu��@\n\n\0)\$D�g \r(RA�߫wLf�i2d\\ʒ�O�&\$�����魇�����b~'Č9�t.�a�R\n�>#����o���s<@�C�4q�<:�@R�\\7����U���\$�*�g!)�I+%���3�֣��&��4�p�O�z&��.��Z�z����D!.�9�T�M&̨�CLC��'d1��6	�G2�u��r�1BxS\n��0���MST����8̖9�1u���%�K�V�4�5e�s�\$l1N���82d�#H���lMO�43M�x��CJ%������q2���'��@B�D!P\"��j E	��)&�M�a='�AT�\n��A�[kv�5���\$�8�7��9W\rQG;\0*{�'l�LGо�����5L�\0 ��c��dɅ�0�����C�M���b��8\n\n�\n��ˈtt�0�H���0a�3���b�6y�\0���H��+�����������lf�Ǔ�4�\$YɯI~'Z�,S�}�ą9�P�/S�T4Q�h� ���k��@)�P�훿����bmJ�=-��*�x��-Yq��ʞS���k�V�*r�ҩ�����`�cM�@>li�I���Aa M��/d\\��1	�P�v�kx��5C�\0^0��0��%M��z	}���S{�D�����qA~m%?D�A��%0���`Wsg���6�J��w;'c�^�&\r�8C&��~ˮTo�U�L��-#�Ģ�9D�[XAl�=A�gI���A\\2�,򂉼�P�IZѕ^FC�2;%��&C	��f;��C��Y�(C�画��^��01d����r#�I�&(�Ij�G��,�Q��n��Ns��Z�Z켔�v�K�^�Z�S�u�d�b�Y��6*�%�栓�kJ��r�/&VB�Y/=D����6�6/�Ի��1{�d��-}�fv�E<)u3Q��m; ���պ\nKuNa5��l�M]�\rn��7V��Ts�oᚳRli�`#��|S��t����-�J�D~Cɑ5�&G��͍��	劁��m���'���No���F�������#�J 4�R�}��?Q�}V��~=޺5ZC�2�v�m1R���M��*��F>f]LV���m�d\n�]-�bQ�����)*�z�V�����S�=*�w��^EeJjyc�M����΍���\\t����(�W#�W^��#��Lܿg�*��H�W����3��@>���\0S}� ��A_��,΂&�����k=��,�>r[���̉I��r�+��}x�����nJ��)(���������\"��wp�4.���m�l�Onc�\"��� +V�E�����AN��\r������b�0/d����%�N&t���\0���lf��/�\n�yO��#�����8%a�n���%\0�=��>/�7��S���J4��+���\$΂����0�#p�Ц�,��C_	�t;\"2.\"�	p�ä��Pz��q\n�t�-�n����R\$�\r�V���`��D6�b0l�\r �m\"�(l��I,�\0�\n���pe�\\.���JB®r�L�Պ���6/��\"fT3J��V�CN	��V�\r�,�/0v)�j��Ʉ�.��AF��f�J)财@Z��:(�*,�Dbp \"M�f��@�ξM�1�.q�����Ki�|5��Q���}q������6\$'m�Ɔ��ck���oX�ͫ�4\0��e��`� ��`@�4�X0�B��%�Kl@��\"vR%��VH�ld�V��fH�N*��rP���,��/��-t_�\0c��)�����f��Z?�MI�*L�(R�������"; break;
		case "ar": $compressed = "�C�P���l*�\r�,&\n�A���(J.��0Se\\�\r��b�@�0�,\nQ,l)���µ���A��j_1�C�M��e��S�\ng@�Og���X�DM�)��0��cA��n8�e*y#au4�� �Ir*;rS�U�dJ	}���*z�U�@��X;ai1l(n������[�y�d�u'c(��oF����e3�Nb���p2N�S��ӳ:LZ�z�P�\\b�u�.�[�Q`u	!��Jy��&2��(gT��SњM�x�5g5�K�K�¦����0ʀ(�7\rm8�7(�9\r㒞���B�+�\\��c�Y�*�����+\"	���)\"�X��ؐ�eJT*���I�������P��F��t�\"et~�&�M# ��@�\0�7�M0�:#m����1�C��3���8C�Ҏ�Kz�˭L�9��H���4C(��C@�:�t��2t�A#8^2��x�9Σ���J`|6�-+�3A#kt4��px�!򞐖&�m�?2�X�n���j�P���<+!�u��11ڂ�H�����n�\"@P�0�CtƎ�\$^�#�����%|\"��e͖��\$�Y\\���ERR<���:����0������k�n�B,B��ة�Z��ժjlkR<�J�#X��Z��j�Y<ؽ�l�<HƖ�iKk�/#`ch�5���0�к@��d(@)�\"bԇ����Yn���1dF�V�f��6>�\r!��o�1lk�Ȧ��mE���ڼy���d�dy=s�dy���Z�>l;�s �Z�BE,��V��M����pWP���*�A0(�Ωm�Cό��v7LUH�����{�ٕǌ�L���v^��q���_P�1��h��1����C��3��ҍ�0�6J+�!k��&��%`*\r�EL7!\0�����3`\0�7��P�<���C8a=@��@��pu7`�9��\n�R\n9|%��^cK�l��*\$\0l� r}Ɉ���2xn�4�D���\n}O�A�U�TZ��\n@9)%(қ���J� }U9�Uj���R�!�J�'�獁U�3oVD�A�Gj��߂K8�\$T�L�jj7p�K�4蝐(x�>�@\\�S�2P\n	B(e����Q���\"�ԫ�un�\"���C��\r�P:D�|�`8eM���p��X��E^�\"��%'�!���	�F�p4T��H M���#K(Ô!�k�0�h֖_�n~������M鴖�l0�0@㬔\r,ןa�H� �P��R_\n�+1�}�urjC@pe�4���C\\��*ӝ�Γ�L�����!�Y:j�a�6�q%��l��VK��7�zSۣ�!GR38��UI)D:��T�D���fj]0@DoUn)9F�2����a���Π���F���T�S�9\r@�!�0���� �UA��[I�Jp��YA7d~O�lV(��V8��Q0�&k�{h2VץG�ZqBP�)G>G,AFr9J�G:��UŇJL�I&})B�\$FԤ�7��>���fA�H4�L c~�iQ.lkY\r?d�{��T!dE��gLc��h�X���|Gũ&�J�ڴ~CQ�^;��q���Z���`�1�|���rja@\$;c�6��F�Z�O�;I��Z��0T\n	�7. �\$��6n�#H��i�F�TV�rpB��Wŵ����p \n�@\"�p~�&\\.���s����!�v�'d�EQ���h���{�bؠ� �P�v]�������p�#��:��P��1Hl�/�q�A�-n�⪧H��%�d�X����2��j�օ~�2}�kK*6S���B+�][ѧf��Q.L̾��B���n�ț#��A�Bh�σ�0J�9ld��#��g\n\$r��̵Q��V�u��<JNt�L[d�2%�M@��L4��@d�\n`�L2��	Cq\"%\0*4ƹ��5��5��;�\"�-��;�l\nG\rvZ�lWk�-��\\�T@�tr��;��\\���\n\\�;�.�Q�5������8�Cz�Bhߖn���>3 ���E#��C�Ƚξm�̨�1��q�kvp��8d���`T\n�!��Ae�0iJ�^Ǉ4��(� (̈F�(�}�G�����-�x \"ˈ���'�׾D%'�X+Nx~�!O��Ty�b�ڋ0�RS&^W��^���\\zyfJ#��۲�Ҋ�M:�?Hs����\$�\0��ۻOq���wi�� ���l��v�/�n ;��Mag9>ܾ\\�O2\\�2� &<�KS�N�v�[̒�?9��h�x��2?��I\"��h�vG� y�b��0�1ͺȣ9B��\nC��Cc<<䡋u�X���=�}��V����.������f,�D�	'�dM�\n�i����p��Šqo�?F�����p��q��OL�h.O��dD&ŀ]j�:�ƭZ2l*����B��(lL����ňW̜C0��g+zvp1ˤ������E�+\"V�px�\"�\\��\0Pń�&��/l��O�����F�͌��H��bB�� F��yH�\n���P��p㏧\n���M�kP��o��P�q���̤8�������b�aBO�V���k�1�)h�����P8����=\0%��Q&ݯ��V���l)�'12�� ���D���ʴ�C\nb>/�P�Ob/e�0ob�#�f^Ȑ�O�t��Cd�j?����Ԭ���d4�l�p�ά���b/�XDq�V��q��,F�,���<�L1�la�\"��^��m�m��;\r�G!��&0qo����q\"��\r,�*�!n7!��Y-��r�&q4����5!�\r!s0{�8:rHՑI2R9E�\"o�\"/�f��iØ �����f2/�\nEh���7\0r�c(�A2�+2�\$Ҍ����Ps�vf��\"2C\"c(,%r&ς�&���ΟҨC/���xߧo(�tއ&�G\rR'-��*�-��,/�p���WJ�G�Y\n���DC�@��*�K1�1җ��2D>���k&W(R��.��E��c�+*�V�������16Ҭk&q�mC���*�bE�l�`�M�\"rt.O�:.�DN�B�6`g�\r�V�@�`�tL�`�x}@�����M�f��\r��K\n��`�\n���ptHW;���N��go�	Fj#��0�F���F3�ؓ�;��ZN�_%u*��29��X\0EdtL{BƦ����_��&`�\r��T�L���I>�.�R��v�`-��!F/��c�3s���0+��ǃ��#0��{�{H��\0��-h�I�\n�:6cD4���(t\0��d��\$Y��Cq�8DL+2*XblsqIԅDK� &�Z��.e�\0B�N��ې{Ls\"�Q^b��F'���K����r�h �.^8�lf*�:Oq�~�l���|�F��ƹ\"悬��l�6��\r���V7s@qª]K�c�~x\"a\$��	\0�@�	�t\n`�"; break;
		case "bg": $compressed = "�P�\r�E�@4�!Awh�Z(&��~\n��fa��N�`���D��4���\"�]4\r;Ae2��a�������.a���rp��@ד�|.W.X4��FP�����\$�hR�s���}@�Зp�Д�B�4�sE�΢7f�&E�,��i�X\nFC1��l7c��MEo)_G����_<�Gӭ}���,k놊qPX�}F�+9���7i��Z贚i�Q��_a���Z��*�n^���S��9���Y�V��~�]�X\\R�6���}�j�}	�l�4�v��=��3	�\0�@D|�¤���[�����^]#�s.�3d\0*��X�7��p@2�C��9.(��+z>P��K��Ɓ>�B��\"��v��i���>H���%(Ypܚ\$*�Z@�*p�����Bb�6�#tP�x�9�莎������1�c��3��0���0�c(@;�#��7���@8P�����`@O�@�2���D4���9�Ax^;�p�1̱@]��x�7��IR�xD��lWCL�4V6�H�7�x�.1ے��Г��8�S)���K�;+\"%�Ix�ږ�˳�{�pH��Kr���<��Y-��b���+�#��=��(Ȇ�KJ&��B��IF�4�!�Jxܥ��\$�K�V �#䃶�\\I�j�3	��5yB�����Hh(Jr�A%�Vr䖎�76�t�Z�a���%��hE0�FV�f�>QF�\"��4\$ҩ�f����(�?)A�x�/�R>ؿ7��hK����\\��\"��⛝\$�,��+�ye�> \nb����9+��2z\$�il����]��ȳ<���J\\���]:��/r���Fm�/(h��N�v�'s��v��}���N����4;��-zds�wi�z�<�~򵩒?���g��B ��������#�6P�pA��w��Sc�A�<Ef��8s9��˜�n�:	+�E���O��3=���\"�H�~&�\$�W�\$�%K�p�\0���pd���6PK4!E�\r%T��J�E]L���<J��#AD�����CNI�\"�����ێ:�+�I��r~��8����b�H��./5D%�΢)^���%%���⃣6�U�Et���@��|�FIŤd`�x���}1%��TI\"aV3*�Sb�@�:��ܟdL��K8C\"gS\0�M)�<��Tʠ;��Y*�xrV*����2�@�c,YN�VZ-/�G����с�I 솔��i\"�7�eN/#�(v�q�䛓ɭ!W�Ĉ���p\\�6d�)u2���T*�R�uR��R�V\n�Z@H�D�W�5��~��ё6d����8�=�W� ��p&mw�����97����g��\n g�mSTw^Q\\/Fܩт�K�T�P��d,uC`l�CQ(�he`�3@iFêwO!�:� ��:e��((���� jnX��Cd\n8G8��I4m�{�^�\r��b���=GB��Y��H\n�&���� (-����b��a1*(�\rc�`�2%=m\r�<9��T(g�J=:&��Czu�(��@���'�x{���Xۉ@��k��ЊlO��H'�AZ�u�\r��F��\"�Ó�4�0� gTMC]��e(e��匯�|J\r`��'t����#���hqE�II�}P��d�v�n�]ɚ�V(��h�)��G�ن<E�;_SZ��bnk*8��Eo��,`�}�\$Qx�D�l�|��Sjj����Y/����� �*�VRQʦ\r|F^��+�2�(�5� ���\n�%27Ă����JX�����)�,��Q�+*e��z���`���F���cg`\"?;��X�fw��GB옚�w�˩�U˓��Zɡ��!*Y;��#�/)t���2��g44F�nŞ���pY�[Cߺj�b�\nv4���R&�^���%ǌoRA^��of���ɍ�\\Bԏ����z����J��0T�{���l�����j��Kʦ�/�=knҢr���D�+?2@X.�Z��'-S���^B��*���	��I[i�������Ꭳ�=�,�7�\"s�(�rMຆ�H͚�s��3��K��V���9Tʝ@uG4�X�6Fɳ�,��am#��1��!X��d]�r�G�!�^�|�6F&�vZ\r\\ԹϷ�b+9i��3�(�q�pc△v�kx!tn�>�M�l*+��n\$B�0).�w:��J|��[��2�؆���Qq�HC�^���'\\��������g&_�5�3����KV�b���׷D�X��.X<�Ns�M�#��������8.w\\9�l�U��-(4!*@��@ !��֟TݸM��9'K{�r�(���'RnZ&a�^0`��\0�|s��>,��*���Lj��`R�\0�<Ȋp+���Pd����IB[�r+01Cdn�����<�p9��΋\n�� @�ǐNë\"�����o��l��������QД�) �p���6�D��0�`�H&��|\"ЇD���&&p�ߣP�i���65,���|.!</��dnd��f�m<-\"��?�*Z���\r\r.^�.��H� ��������\"H�0����P�J�#�b��?��ҧ�όJ�Br��@Bj&%�B��c��+f�𐜈XB�L�_����4|�������N,��0�e�jc��o/��mB�����!��|�%�h��R��|l�\"N:0���p�1䢍�|'!G�),txE�ffjG�j�(�zJb��~��}�T\n8\"�6%��-q��#'?#��A��v�!\$F\"fB�!#7!g�m���|�%����H�u-��1:#��+�,,�P�0P���\0Rڦ�I���)��M��.2�})�*1�*mp+�Vw�l�Ȓ�}n+�^�d���,�W+r�*Q��U2��\"�#=\"r��q/����,��?/�&PV����4,f�p@I��S93u-�1*b[��SZ�a+Pd+#jY��ˏ�쐎5*�T�-o5�)\0�Se&���XG�q�w-�d{��.�(1Pqi��\$x�+��<�\r�8j~��:�n�@l/�pH�<*d��|�,Βz���9��0q���.��ҏ�x�2���)8\"�8o_���l%����튞/K#�juC�ݓ�b����,��+�xĳ|�ў���u7*�	6��+�DS�'����E��{�S'�.��\",~��Dr�8�|����pp�648�T<�t��􈒲�tY9,�?͆��~7��@�ME�����Hf�ͦ](R�:arJ�;���7i6JB֊d%��<g�A�/6tJ��N�m3�_8��C��O��N�OR�J2ލ�&�d�+�ef�|�U\rT�^\n�O4����&��C���oK�Fu;P4�*�,���z���od*�D�2���/yEUI����t5Eh=T]C�4�9<�p��B�' �%6f�i�p�f�����O��UwG��DĈ�ÍK\$�4���Q�	j^3u�[�^�4�cY�v#�K�\$!GaQU@�b� ���i�ACp��Z/��8��\$�?	��11:��U�,/���(E��\n��	��\rM.�~h�HQ�kkG�92TMcVh_vm7n.ur+&:j@��\0�b�:bc��htj� +h��#_U�'\r,.\"���<�~��4�@\n���Z��4��pY1��	\rVl��L�0�CV�+��Ֆ��|��2��sţUg1�ru�A�/&^�R�%�jP6�N��4�2�`p�W6?u04�D�1�rGPZ�(F�������k��P|Kr��v��w/����b*TATRke���5j�T�S&v���tN ꑿ M-c�k*�r�7�zn�{��z�F�h��@w�;�i��6�I}��T��c㮤���L����|׽>�yC�[�G�l�.��|W�fA)�FA��v3�_;O��ͻB��J�\0���B�n@Z�������3\$�4���BH�\"צ�l��%����dhFD4	%�8v�t!/^Y�[	�\"���4�Ex6\\��[Fe�!�/玊��u�q[��0�>x�\r��E\0^�5Q0�0%N5TGĸ��&u,N8�"; break;
		case "bn": $compressed = "�S)\nt]\0_� 	XD)L��@�4l5���BQp�� 9��\n��\0��,��h�SE�0�b�a%�. �H�\0��.b��2n��D�e*�D��M���,OJÐ��v����х\$:IK��g5U4�L�	Nd!u>�&������a\\�@'Jx��S���4�P�D�����z�.S��E<�OS���kb�O�af�hb�\0�B���r��)����Q��W��E�{K��PP~�9\\��l*�_W	��7��ɼ� 4N�Q�� 8�'cI��g2��O9��d0�<�CA��:#ܺ�%3��5�!n�nJ�mk����,q���@ᭋ�(n+L�9�x���k�I��2�L\0I��#Vܦ�#`�������B��4��:�� �,X���2����,(_)��7*�\n�p���p@2�C��9@��0������F+�z��3Ҟ�22���K�W5b�I�m���*yB�Q��8��|NK�2C��*�S��\n^SS�̐ ��l�6 ����x�>Ä�[�#���r`�5��\0�#��;�/�^=H�;�� X�(�9�0z\r��8a�^��H\\0յ|�7��x�7ㅥjC ^.A��7��`�7����7���^0�ӊP��}��+r�\"�ej}RPF�4�S4�|��0��/�_B��:�N�ss�%P,>��.ʞ� J�4�#]INU�@�B��9\r�B��v/N��N��7٦t���ˣS�F���T���P�@�S��RSEq�P:��y�5�\"ª��\"[��6V��6�.~�OzF0�J�,��j�A��y�O�1�0���j\0�H�4�L�+��Ժ��Q8��<�]�a��	�L�w�)q�}.k�%��DtU1���R1n���Í���\$�7I\"���R.���rF���D�F�)�ԛ�J���T�c�Dg!� �P)�#0g�!�#�݌fOV)HsAzaSA'vL��lR���,��K�b�e�s����t�`��L'�zn���G��J{5i����I���B��|��\\S1�P����<��&����q>Qz.P�~�r�a�8�G���8�F(�%�JS�zz#Y\r4�ʅ3؏��Gw\$�Y{�pJ	�(s��@rGt����0f\r��8��\0K2���(*���Cpy�4�U|�3��7�t�ָtR�0�p�kj�7S�\n�)��T�	D�_�;���X\"�\"RN�0*�|�r�k&�Y1b�q�4�E`�O�[ˁq.E̺R��9/�Ք`�z }@X2MaL26�w0^�3!ƀ�=�BC��#��r�^ּ���؇I�UbIJ\$@�Ւ~��kMj���J���l-���\n�\\��t�uֻCrl��y/H�#U_�\$6��W�t�`�1\n��������5�nd��45���\"���d%E�����Q ����d7A�W�\r���b<��9N��Cf�\n�W�9b�e��MK0�;H\rX��U����c�:q���nR�{!�%��\0�����ъR��Q�6����\n]\n/Q��eH����1�\r�T��@�-b='������U�r���+Uybxw����%s4�R7�j=P);\0�	�X��{+�����\r��c-f��w?��4U��� �Vx�> 겓�[h��\r�p@R��ٚ��K-�,��W�F_`Yxd(�\nl4WR��,(�ޖb0��)3��I�2��X�Y@�шל4��ID5x���B�;f�,��BL����k��!�V��H�y;��v�b@��	�>Kp8����2n\r���+���YVz�c��]BQ(\r!�7��T!��ϕR���	KHO�	��Rl+� i�1|��Qgb��=V�d�\n!�-�پf��U�\\J�RѾ9WU�z�X�K�\0���\0f����,P�-��j�������4wG�8g���x�lb�+LD��6d2��)6G5[!P���J��;)�H��#���dx��R�d`�6zm��ᣉ[���,)+�	D���I0�҈ɪ�ɳ�x�\r�@^I���a�Ê�������7��P�h]t��<�����)�\rͳh�%FF�h�ETx�c]8���#.�dc��K3�Zw�B苇\"<)������,������@N����b)��q~gP��ĵ�巷9ֿ;��2�����yr��>]�9��[�&a2)��e*��k��{�l�uOݜ�S\r!��]J�1�pS�����:�r�>���SO&��U=��;�x\"b	��۱� r/��`�W�<o�����D�HiB@�q�SZ��4d��f�8�7s!���`���N�=��i�e�(T�#��n�u.8�((�C��Ǌ��0@�*)�l�0J-��nN�G��n�Q�()O`�e:3�N(��~RNzO�<w\$���\"H �\n��`��M��V�,���W%v�kj}n���~��t݌\\鎀#f��A\n����~kF�@���8}\"c	F�֯�8Lb�I����O���\n�h��)�ꉶ�O1	h8�P��p�}p�(��0t'���� �\nn��fZd*S\0�|�#����*�)͞|���=ê,�O�B����\n�,���|��n��\0S	���\$�z�Κ��b(�gGo�e�	��Y�@M��\$'*�0��K��RR1�S��ނ�,,��f(��خ�<ol��Q�lj���TG�(�Q�i�b��OJm�.O��c\"��LF�zƅQ-ĭ��-�(E��!�L�\$�2lr-��5N�!�4�-tt�3�����p�P@�j��a��&0���7(�0��F��.�\"J�,�ꮂ�f�Q�x��U\n-�6�(w�#\nLk)Rv�����~d�9�0�R�(�pf�(��#5(��*��+���������,�%)҈nv�H�/��*��.����n�fl�N(��p\\��T	��#����#Q��D�\"����c�2j������#140U6��Smgu�Li/�H��/0ԯ2����1/��g�'5g�&b�ŠPy���L�d�(��Ȗ�����-�?-��8�i0�����PҺhs�-�8���s��g�	�k3�<ѕ����<�&��?�9>��cae#�(=/��!�:stw�i��@�p�CJ��&��AsΤ2ăuH=�sD+���D�a@,j�k^�2�/��J��B� %	,s�@���,�M,����hcZ��	4.TcK%>�����7(�?(l��*f��+X&����	.'�UAT!�^�ƃ��R�CN�j0��f�/!GIGNB��;x��YMBKb���؇�d�\r�Pb��\\��!O�k*���	!��S�ϣ5�B��Hdo�A�\n��P~N@��m�<����mⅎ=8/����E��HN�X�CH��R�>��@U�e3�0��/����)[R}?����U3Q�I�<�AB�L�U]c]T�Y2o[��^B�^�:��G��A�Y�ε�UY{A-��U�a1��a��#��i��8�O%a~�I\$'Z-�+c\"�cq!!j; u�ԵZc�!EpGZR����^�Ƥ{fJ�\r�YR�Y��9/Q�C`0m_U�@U.n��aO�_6)DD*&(�k䤒�_�\$���h�L�G��#�e�\\v'k������#O&�\0��\0R\0J�Z��H4O^Qx�i4�kg��hU�@W#mv�J7ilkqhq�p��e�qr��u�l���`�G_�T�D�s�'(2�IVq`�;u=kvsV�g.�Pe4�`cW`��@G���Uh�~��4��pgS��Q�l�aGD�E��a7oY�mz��5n�0W�C�w{pG{�#{��ƪ��5~*.��F�tgQ�BW�N�zt�R�h��'��z�GG�{o԰@��@�m\r �\rh�ME�qG?iN\r��\r ̕E�.���`���n�i�\n���Z��x>I��74;{7҆NL��B��K�|8Fï|�uG�-��N	J��qS���)N�̎�,dO츮�(Q\0���@7D�du�v�̇Q�3F�Wh�H!1��#j�v��b�2(���j�L7ga��	��j��[��@\0���XHR���f~W�\n2-�o��@�m>r!QY02r�b�8�V;S\r����j��F��eW�q�v	V��ÉRm�rx�ߔuf�[f���)��C�<#��@�^h�\nUϒN2튕&C��\\0�7y���>u�urp-�=2T%2��mn �S�9�T)M�dTε���)�f �����@�W����汷4urS�Y��6(�'{�+5�\$�s��:#`)�N/R\$��%=5	=h_�{O�g�g'[�Gu��p&�z@�܀�=C�tYoR.�v̑DEd��@�	\0t	��@�\n`"; break;
		case "bs": $compressed = "D0�\r����e��L�S���?	E�34S6MƨA��t7��p�tp@u9���x�N0���V\"d7����dp���؈�L�A�H�a)̅.�RL��	�p7���L�X\nFC1��l7AG���n7���(U�l�����b��eēѴ�>4����)�y��FY��\n,�΢A�f �-�����e3�Nw�|��H�\r�]�ŧ��43�X�ݣw��A!�D��6e�o7�Y>9���q�\$���iM�pV�tb�q\$�٤�\n%���LIT�k���)�乷/��6���f9>��(c[Z4��P������ *0��53�*-�R���� ��2�9(�{T�\$((�8+��#��j(�(���0�h@�4�L�w���`@ #C&3��:����x�3��Z���r�3��p^8J2��2��\r����˘ڏ�#x��|��K���CH�FC�p�b����9�X��]0�\r1+D7��8�Q�%L�%u�7��*�;B¸�C�\"2b:!-�a\rK��c�u�E\r��ڟ�cH���r�#�@�kkҿ7� �3#���֋��Qˠ#���ü�n��H�(��c�M3Z3���?��b�N���:�������D\"��쾪nDV5��.5�hv0�A�h�ӱ͍Ӣ∘e��<�����H�Q��5�=3t��hun��=0B#PU.�P�@Q�P@h�����,\"')�h*�c�ʝ')x¶9+�gP����#lb4Ѭ�\"6��N)���'����(�< �t�Cip������r����;C��&@K�솮\"8�7���]pC2��h@�3���	�LC��m7)?\$:��\0�u UB�9��ߋ�*+����]o�@��ªR2�*6���:��t`�HAY?����\r.�a,3�ܺ��\ncL��;��֋�prN	Ȝ�術�>���K���\$\$�@	2\rde+\"��A�+`.�ԐAтtiI*��)��],�优dLɡ5?@�`XnN.@�98����>	,�ӭ����5n�46X���Gd�9<3�!is#��>��j��aG�5��\n`�er���#�~�Xl\r�����c\0a��<�f��,�Y%�\"�(a1��1�3���Iwr�@��Ĉ�r�#��/!��/��i\r\0�(���e9�_ă#r4K��*���8�h�)�5+�CVm�O�A�;â^L��;�u������8��'D~��\r��o\$�\rC��r��t�!'L�])\$�鰥��� �)� ��;C�d�D�\n�����ԗ!��O�&Pa� �0�C��F-����d���;	��rfMV��a\$���4Q��х!�`)�dÈu5H�3����9��'�1�ē%�t54Tɔ�A�O\naQS�*U�A ��B���)T���,��%Qp � 7bC�*��ɩ�IL�s��G�}��ԑ*C\nl�WgiHF\n�ݝV\\\\��L���\$nX\r �lL�̓�hv#f�~�\0��\0U\n �@�A� l��2f�	 �Z�@(L���[���)��W��IB��:V�B��Kh\nc�yj���N�dbD���ޥb�.����t�|����ų�5bg�n��!����B-͂\\�36�=қ���P��[��Uh�c��D��s��7��D��>'�����>��*�ȀE�N�I�����S)\"S�E'd�@ߍJmŷϑL�{Sj̱�Dl��7á0Ys�me���zP\n���P�0�e����*�)��<��&�ՃS�;�qw���1�i���ޣ�A���lPF�P��N	����7��<P�%(��F�RRm��#�()-��x�A�a�r���Kզ�AD8��P�BHG�7kN�r8�Y��ABU	9�I����@�̑�Y{D�m9<��T8Gd��C�8�Py:�m`7%g�'�}��`\0����I'��|P���(��Xz;M�F���sy�m�˄�fQgo��./E���	���\$p�=�of�&��ۇ|nM�Ƕ���\\�p^�8(I���m���x�%�����Ha������ZԊT��O*ѵ��BI��F)���sugP;#�Sd8\\E�C(b��0�QI�?\\T!j}�u ]-���N:*UFy�I�w=���A�>H�̄�|�I��4�%��=�!z�8����9w�'�Y[��W��},��a��=q��|�+��h�~�뮬��+z�����k{����^#�\"�aa�|V���~j�hk耠��'�Δp���XO�C�Z�鐬�x��0(�uL���Ά\n����'�%��������B�Y�^�F�/@�C��/�n�<�&��P��r�+�'N\0�%8����M����\r��ap,(�M\"���F�y�0D�/1�V�L���,Z�&p'���KA�(p�Aic�H�d�.L��b^�z�e��O�J3f\n>��\$�>��~�(вZb������0 \"�� /q\rhԓ ȕ�âF��9�\nkB�P|�\r\n���k�oo���P�p�\r�Bal�X#	%0��i~ͥU	�	\"`�h�-P`f8�#�l�X�p���|*\rS��\0�0%�i��'fa�N�\"	MTa����>J\"�,����c�N���H��H��`2�KQj�#���a���'�^�T1~�D?�~�e8�(���d��CT�I�@�@#�A�����	 ��Ã�����C����+!�j��T�?#�t\$#Q�7����i�%�Z6�_qm#`A�\\=�I�	\r�j����\nH1+�cJ�Fm���,�\r�)����O*��)��T�:(���f8e��E:F�BX�`�~{#�W1�+El��*��c��r���=#���6\r�V�rx�o�+\$v�#�\\�MbPGc\0�\0�\n���pq�����Nj��\r�2�T7��3N2��4����U6�F�^\$nL�T/'�\\�b�10��&b �8���1�6V���3+���n�H@\r��ä8D@�:�B@�	\"֡��d��um�i��`�ʐT�E��\\����2�i#&\r��b�v�*��;K>��*�L�G-@���0��\0T	>è+21*,`1�iP�3p����k���\n����>(����0\r��l#B��I�D� �����B��'��,��8^F\r<n�B��_��0����;D;Ib98�Zt13�@�.�Ԅ�G,�\n��MN��2P:�U�"; break;
		case "ca": $compressed = "E9�j���e3�NC�P�\\33A�D�i��s9�LF�(��d5M�C	�@e6Ɠ���r����d�`g�I�hp��L�9��Q*�K��5L� ��S,�W-��\r��<�e4�&\"�P�b2��a��r\n1e��y��g4��&�Q:�h4�\rC�� �M���Xa����+�����\\>R��LK&��v������3��é�pt��0Y\$l�1\"P� ���d��\$�Ě`o9>U��^y�==��\n)�n�+Oo���M|���*��u���Nr9]f%3M�)��pȺ��h@2��:��H!���0��p��P�:��\n0�ȍ#Қ1h2���e���1KV��#s�:BțFI4�+c�ڢÔ|0�cX7��0���@;��CI!�p��;�� X������D4���9�Ax^;́r?%�r�3���^8J���2��\r��:��˪|�B!�^0���P2ȣL\"���&��&\r�:�M��2�����h� 5(�S�1\"hıl�KG�Np�����>��\0�<�\0M�aX�� 6�j�\n�H�A�Dg�#)\\�c��� o`�猣0��Gò�:��Z9H�`P��DP�> ����B^I��\r�8�7�#`�7�b|��2�7(�p�a���h&B+�G`��Kp<F�\r#�LVD0��3\n7Xp����&.�(C�uaTģ�R��L4�%<9�c+�!���D��cҀi����:7��B�Zס�&��� \"�ը����\\�����r\\<�@S<��T����⠂��:���!ƌ�#���Ɉ�J�P��6�\\��K� &\r�,!/6��2���F\r��Ѵ�*:7��7K8\n��U *\r��}	\"��3N\r��%-�C�<3�+ˌ�X�ż2��Sގ}��'�w~�R	\"b*0o�B��S�FӀ�-��ʌI��L	�2&dК�`wM�ܼ��Cp/@e�DAD��L;�)Ff����4�t8�&�ungE^�\"JQ��/D�&�CpI��OK��S(r\\K�\r1�TΚSZmM�4�@���M��RPY?�@�G����St�͙5!�A���:3r��4֨{I����TU:����.Uh�:� �O5�1���\n�!����Fs	z/i(��0a(Fn�4F�cY��M~sfL!�o\$p��8���\0P	A��AQ(��ezo���}��o���l*�m����ސ�Po�%�I���+��\rҡ�����&��<\$g�%�nZq�<���`h&��2,#s&*\n7�@7�C�R�jnx���+qF���r���R\r?`��%fK(t�s�Н\nx��y�\"�� ����ֻ�&!\$���I/\n=D�RK��(�H	*?(����}�o�f��c��6A@'�0�A�I�'�`�/�cI\0R�+���ʓ-��y[M:��Sei]M�����.*&������yIA���VnR5*KDb�I�n8��3S�L�A0A�.<?Db���/I}����\0U\n �@��m\0D�0\"�d*�#Y�=͵�R��(N����	�1��#P�B�):�d�)�j�݊���ѡS�k	�He�4D=A�1���#jx�ĬXHh�5�����ĺ�y�,����ݽ�Y�8�!��F#\\d�T�\0\$��+�P,�8:��h1)�<䢒.)��Z���jL3g��a,=V�k�2B�Y�⊂�uR��=.�Y:�j\0]��2�vO,5��|4�U�k�(w���h7S 0�kr-W��cC�+�FX#!hq��L���lj�\"0�P�:%�ɏb�@a�{�s���j#�\"��F�E�˝��M̆]Ј�)-���)�\$h|\"6�ܞJ[�*�#j�.���L�|�Ãn����8�����]Ac��@CV4�ck�ˮ���NĚ9 ����}�	n%����	5ʭ\$:���^�+���G��&�*�ϸ\n�s t��>UY�O���{\"�,w�����ۻ*�nկu��7��o�n��욍M���dOh���ܐ\"I�AXDȟ�Pղ[�W)R���6�Q+Nr�y���4�1I+.5���^I��b�sb�ne��\n�Sz�M���O�/?<mLhV��M�ߥ����.�B�;�N`a�H٣@�yo?G�u\\p��EU=؋�E����/�x[��:�y֨��Y��&f�FV0��Qa��V��ڔ�K��R�[�o0�����TK�\"�TH`F6V�qE2\nr�a\$����V�O��!��t����y�N��yh�%������Ej�ŜMCI�:�@R�|���kX7t7~�_�3hƆ#��-��W������/����?in��}*V�j#�����������p\n8�ebڠ�E���a\0#�\0m�\r�2�����k��e�ͤ&�o��0�\n��-P�0L͐R�`�6G~��2@�N.����C�>u,^4��)�&���7\n�=��d�D�f�k���\"�� 3DS�//���O���%㴅˾~0lr'\0��-��xl�o\$=\n,^mf�i�V���ː�&�	��p0�\0@U�����MaK�f�����ɏ����\$ڰ���,��4���S�.����.A��j`��X;M̣\nhMظ-�@*��2��\$�	�\0Q�H�p��ч\n1D̍`yɮ���i*25��+�9�/�1�jq�H�[��	0��h)k��N�,�?Q2�N�.R�k%Q�Q�1�����qM��1�\n��L���'�,AD.��1�!J\$�����5!�	(��zѰ�@�-)��.�I�HGŏ�������\$�Oq�D�/\r%�X��F��둁�P���\$m���(&��&/T ��e4\r�V�\$BIF�]O<�@Zar�l�jAD|���\n���Zw�I�&��M���(�\\8��HR�\0���fЄ&Z �޾BL���f� �C��?��=c�����1m������\$�§�/q�c��P&h6�F�2�;�1e�'�Bi��N��f�O��'�0�Z�cdV�����(h��N�P4��~���7��8����1S�ɣ| �5	�����&̌}�8�\"+�_�(��^�S�ʏ6&F:��@k��of8�f>�`N�c, �gJ!E��)X������S��dj/GK2� ���i3�p2\0003�Z\\��3�*�Vv%6���l�8�>n�:� �.�q\"�D.�j�[�Rdh	\0�@�	�t\n`�"; break;
		case "cs": $compressed = "O8�'c!�~\n��fa�N2�\r�C2i6�Q��h90�'Hi��b7����i��i6ȍ���A;͆Y��@v2�\r&�y�Hs�JGQ�8%9��e:L�:e2���Zt�@\nFC1��l7AP��4T�ت�;j\nb�dWeH��a1M��̬���N���e���^/J��-{�J�p�lP���D��le2b��c��u:F���\r��bʻ�P��77��LDn�[?j1F��7�����I61T7r���{�F�E3i����Ǔ^0�b�b���p@c4{�53��T���9(���5���	(持B#Z�-�((\"�H��#�z9�¤0�����i��.��6�#t��C\"\$��ɻ.V�c�@5��f��!\0�2�A\0�\r�X��@2���D4���9�Ax^;�p��0\\���x�9���9�c�R2��ɨƎF#2R�i�x�!�V+2ۏ! P�7�4>:)c[^���x�6��sz�CmE3Mӭ�f�\rc�ռ(�p5Ѣ��9U�L��0�5�HK\\�U��<�����h��8�*Q P�7���� P��#BH�1�C-�71b��^��k%\"cp޿��S#�p�=�C=�3���P�@P�2�\"�;@H�����FM�Bb`Ȉ����C��d7�,(K��\\�q*��U��(���2���w���vŰN�sQӍ+{FR&yR�����âs�F�F�z4\0����\$-[�#lnň�ƿ��h�]�6�V�P�P��!C�9���X1�P@�*�C����ҳ\r�n�9�Ҡ�����:4���b��F�Mx��\$�>�ەQ�D9V�8�jq��p00��3�0̡FIX�2E0�#e�j��m�4#H�R9�a�C�[M'�^^�؄�V�<��s\r̓l�	���x���y~o�����k�z�����_���`a_\";|�㼓�^`�y̹\r=B�k�\\�?���������@� Mՠb4�l�c�e̢�%lTV�G�oȐu:K\\�\"���êOJ))p�T��R�]K�1�rT�Rl���@��>D�\\9�H�*�P�t�.rr��C�0���C�jO�p4���7\n)5spX���qFII4�Wܼ֒e (�&�䠔��VK	i.%���dFQ9&���<�a�:�v�	��5�b-��șL*	<��o��?����tpg���<X���\$���\"�RJ��{H�ՂG����]ESHv����o��h��Q4��!��q����P������V���R-|�l8��a|^��IF���N#��H\n\0�y�Q[=��(*\0�:��\$���H4�b0C=\\�h���^�єޘ��w�#���I�jt.�خ�,u�(�͆��c����>��9	�9�))&Ū�H.��q�O\0C\naH#HgvN�;oI�9����\$�!��˘*�8�`��8L�]+E���W9�N���M��7�gS���\\��h_��1錁�ThQRiB{ �&R���@5�`�5������q��� ��\n�<!@'�0��P��\\h��(��l\$ny�w��0i���ټjW\n����Ř�8 \nl���r�O�}@oDx�*�C|�*P�~L��\$���\"		XC\r!�u��k\\�Y)5d�<�b��*�4��֑ryo��=\n�dI���	�T��7D�US,\\���ʰBB�d��7����&\$�����^�H\$�dU�\"|�q�������V��l�5�W;JZم=���\$6�s�CJ:-�*�L����V���ui.ˁ�/� + 7TӇ�\"�к�h��*6���S[�o�9�'�*���C���*6�	u�D(ǌ�Bj_�XOt5�4�����Y-AN�_�E_;�e�,��V��MK�z�u�*A�Z��2���/�s�	���g����(C8�J\\�����;�������\n���fk�j.d�7�z��	�E��E	�L�oa��A%�.��g�T!\${I�6�9@�ߓ�NDK/��U?�^W��(`��b�f_\nV'��5���O4p�Bm\r��%ƕJO���5�1�]m��U�|&�����l��eڝ���f�����|��e�1z1�\nuN���[s	s�~�6a��r�����ٹE�=�uhR��z;f2��t	]�y�xW=������:׀��s'�S�ؒ0\$�i���?i�YH��`'yrE���Ud0*�דW1ij�#��������ᔔx�7�!Ɣ�h�(�ߚ��>�?(�\"��&p������J1)u��}�n�E\$����^o���xC���Ε�Y7����P�F0P'_�0���O��<��4�~����(�:�m^�N��cK\0���\n�:���J ��6�HLT�eI�7�\n���]��ظ��j����z*\nE4�H(J��w��xFUfڄ�'`�U@�]��eMb+)\\�i\\殔%e�G�p+/\\YE�`@\r��e`,)������K\0&���2�з��\rE���L�I��T#��P�7��\"Vn�\0=@��\n P<�\0��d������D�p�Op���\0VĜ�0��#J�g��D������� C�=n�����4��k�;���yк F�p@�C�1M�Z��`�p�l�y�x\r�0��8�\"3.Yi����]bb��AI»\$�U��X`�I�<d��I��2H����:�F*2�8ۣ̤L�Bi}#N+CbA1x�1\\ٍ�xEjd�<f�i\0��l��Q�Y�X�Qu�ܱf0�;\"�!�\rц�Ƙ8\rZ\n�����\0�/\0P����QA\"O�\$�Z\$��#���g�H��g\$�'�L�f���:=��FE�	b*���2�k�Ӆ�<��A-����%0��P�+0�&�+?�l�o��\r�^Ú�F���UL�l�{-���.ɇ��UP&��B�Z\$D��g�#-�1\0�\".�4���ͯ2΢�.-���'�M2�:r�?&R�7D5��\"�g%R&@��5\n�w5�;6-/r�5��\r	�5��q<Q��p\"n˰m�Э��������:nw9I]9��ӑ0��Ȁ�#S�4s����%`�#*�=�d��\\C�9�#9G�:�;ξm��:n�5f�1Q?7?N�?�����.���(\r�V;a�Ɔ�I�,@i�?�oNj1B��Q�	�9�h��l\n���Z\n��ऱ��S@I]F �n�tt#TqGEl��3@T�\$j\"�.�D@�F�l��URh�B�? Yb�E�#��/��{�]ņ8\r�\r(�N}KC�o,��F�j˴�k,\n<@�R��VA�PV��(+�O!{O�6���n�nC@-̣	\rQ@����4�O�e1({\"�jV�S#Ss�!Q5H�<����CT�5BF\0eE��E�*��D?\0a5BG��(rN`,��2O�\n�t����\"�1Y`����2\"UoܾE�#�'U���(�r�k�^&�Q�R�r:�4��zȦ?]��6S�?[��\"4�B�D1�p��<!��C�\\Q "; break;
		case "da": $compressed = "E9�Q��k5�NC�P�\\33AAD����eA�\"���o0�#cI�\\\n&�Mpci�� :IM���Js:0�#���s�B�S�\nNF��M�,��8�P�FY8�0��cA��n8����h(�r4��&�	�I7�S	�|l�I�FS%�o7l51�r������(�6�n7���13�/�)��@a:0��\n��]���t��e�����8��g:`�	���h���B\r�g�Л����)�0�3��h\n!��pQT�k7���WX�')�jR�(����Vñ�&o�Y̘�� Bc���b�Ȣ�sB��O��2\r�Z�2\r�(�<-掎\r�>1�p�����1?���4��@�:�#@8?����\0y\r	���CC.8a�^��H\\��(�γ��z�ƃ�l���\r�:0���\"���px�!�N+0�cj2?�P������5����d3H��H�;ϓ��Ҏ��|	��B�\"�P�0�Cr�3�hh�Tp����:��\"Xޏ��(*#�US\r�|J/�`7��ƞ�L0�2���64#��:�SaM7B2+<\r3+�0�*U�:R���;�@쳎k#4��m��`��U	�L\"�\n�jp64c:D	��6R�m�M�-PZ9�l�)�\"`Z5��D)>����P��M��8��hK\"	��\rc���(�a�\nE�-K��6ã�k=��^j�d,�SE�ET\"S���)����:��ZZ�� :��<&�r\r2�oD�.Tb\\��b#A\\8���v�؄����^�dk@bOb�^ϴ�x�3_��O6���*\r��<����DC5�A�X���`�3�+[���rl`2��S���\n\"��c�:\$���%m��:�m�&�Q�Ǯ\r)�� HC��#IT�	I㔣)d�7K!���R�<��DV�� <5�\\.�Zp��7_��C#,BN��[�2�Y�G�:} �#�|�EH�\$;�� �rPJOa������s�̏����>i���2D횪L&|ІrtiI�UɈ�A����C�y�����X����λ4�޸ Ef�N�f�Nz�uL�9���)h���֡Wfm���L9sp���T\\A�\"0���\"pN���@\$\0[�	>(@R���.�ğ�N�)d�4fˊIrSl��D2g�Y�/��*�p�K�@f��ԶM�F*\$e� ��E�|7`�Ѫ7Jl;����D\$-)\n&��İ���Ҡ3��F������k�ɂ)Jn(��J�a.3���b|�	�#���&:o_��[���?p�	8I\"!彗�7'��E�О��?�1�%���L���@���)��B�O\naQ��\"�=T�3n���Hm^��9RR�j�������Zc�\rĐ3�����i� ����<H!9s'\r9�0�Hg�\n2�P(Cj��ܳ��F[�RB2��'(\\s(�6!K�'��@B�D!P\"�\n�(L�����<O�PR����C�)��-�r�\ncjgP��&�46(�[g,�?a53�4��%EE���PHQ��\r\"j��mM蝌����mr�e���5�s�����=�5�T�S��\\4����}��6H\"0Q	�.O�V.�uo�@��S)��J���8p*(�qsCHzURA�:4�d�����W2�~K��Pm�ST`	+/��� E�_�Y%^�4a�R݅ګ\$����mZ��l�ʨ*��o�f#Ĥ�����	Ae�5�����!�Y��1��`�����ֲDhC����*��hY���d(�5ꀘP�p���7)�@ʲ�7�,��6b�1�Oa,���\r�	١	�-�,�c�P!Ȅ�\0�L\0��h=��B�B�E��#\n[V+�\n�A��yT��3H��_��X.���Vg�ɤ��'	p�\0��Xo�)x���B�I-N��l�\\l:��!GU�9OU\$C�������'ͱ%`�8O�ərsBᬊ�mо\nyc-�*��K����\0(\"��.��\r�dvܓ�����xph%F�p`�ո�@�������͸��ܹә������n�﵎Iwyn5��.�q/��\"`&�C,S6E����U(L^��=�>T���Oe��3QǓ����:*�^����� R�,E��|�N�hAm�#\$홡�f@q\$C96��v�yBxFO�����B����ϹJvM]�t�s�\$oM���B�G|eóo��I�Fv���&*��7�ۊ�s�J��}���?�=�윗���2�C+�8��0�\r�ucŞ,qj�K�Ur9xD��h���p������r98�\0��Cx�\n�=B�b���J����Ua�<�TXSd�h�� ٠17��80;����PO�����pNe��H�O<Q��\0L�����\n�\"N	�g��=\$�����<�J\r2ͫ|40M�\0��~`��F�����(���R�ȚX���/�0��|BP)�qo'pD�H\$e�z��ìD\"��R6О|L�gê���OET/d�fάUlj��.��c�{�Z��Z-,a�iТƐ�/�N��O`�x0\"]l�>0��O���Oڕ����3C\r	T���/�2�P�1b`\$�Pl,����K�F�qHDB�uQO��U�,e-���^�� ���<�qv�Qz�pd�\r�V\re�\rm�F��L�(P��E\r�L��)oʇ�K��\n��	���;��R�N��2���OO(B�f�ܷ����#��	�|'ʎI�7��;���|!@Zp1��mO��U1��->�+ڄ��N.����\ni��9��'�0�E���F�T2���.��Ta���2Z>k`�f�;�6��_'N�a���Rl�+'0�E)'��N��\"b2+��)&�H6�Pe2[jڦʁ��	��������%r�^�Ҩ���B:r\0�Cc�d2���\"س��\n�JǢ�F�zOqo&��{�<_B�ƴTҊ/�P@�-7�#�\n:ԞQ>?p�&�"; break;
		case "de": $compressed = "S4����@s4��S��%��pQ �\n6L�Sp��o��'C)�@f2�\r�s)�0a����i��i6�M�dd�b�\$RCI���[0��cI�� ��S:�y7�a��t\$�t��C��f4����(�e���*,t\n%�M�b���e6[�@���r��d��Qfa�&7���n9�ԇCіg/���* )aRA`��m+G;�=DY��:�֎Q���K\n�c\n|j�']�C�������\\�<,�:�\r٨U;Iz�d���g#��7%�_,�a�a#�\\��\n�p�7\r�:�Cx�\$h��0�H ��\r��;.,(��3��(#��;�C����&\r�:�1J���� ��j�6#zZ@�x�:��f�ij7��b���\n;�C@��IÄcC#Z-�3��:�t���#Q���C8^���E.�xD���l�\r�4���J@�}1m���IS�:C�z:���:��b��;���K���ԥ%N�Bp�:ǌ�摏@P�ë�`懠b�!-�a��bt�U#��\r�hڎ�8 ��xZ\$�N���B��ѺC���)�{&˄�b�\$\0P��R���0�3�w��:�eV�J*�.��R��T�}\r�̙T����6T�e�z�7�Z�ރ�p��(��h�h�(߅b)-��1<7E#][W�NBs��u���L(c�۱�D 5��Zr5-X�	#l�s8OX�<j�J��LG�\$�Fl��18�]�X�����AI�ݸ�۞��Z�i��0��C\r-��m{���lTV��B�C�8�h��´�Sڴ��(�3�dv��#�6#l`�����9P�X�7��Y\r�h��`X\\r�:8A8.����� bj� �\"� )��=�=� �gr3�}�~��^#���*�7�����H�z��\"��C�\r�y�:�\0�<w���2��e5���GF��<&(p��\"�L�`LA�2&g�SZmM���@䝈�/��~����#�P���XG\n�QΈ���(��b%����Z�^T\n/ia:�b���D'P���ܡ��F\0000�X���hLi�3؛��pG0�'XdS�#��7'�\0�C�o[�y�D }�1�o1d&�ԬF�k�G�92ը�M�9`\$�\0�B�dX�M='T(��B�J^�d6�8�oK�+d�0�B��cQ\$�膖>_��P�T2�0���`	%`�N������\0�B�֘ g\rب�t�L�;9#C� %8�:��8KٱG��OC\"g����sI�GAAQ �0���h��3d��ˢ�C�o�%�ڮ��0Q,Y)�\0007��ځf,�I�D6(�X��m�U\rW��;�2W�C�� !�,'�d�N�h+��r�u�4֛Q��P���0 \n���\nF/))E~(�Ƞ@�<�0�QH��gG�M!5T�:2rM��\n'�\0�R����A�����\"_ÓM.�*?�AH!f+���\"M�uP�ޓ��V������EOMNd����Hh\$S��3���\0� -]�,���8�JS�50 =5�l]G(�\$��J�!}ô�~DO\";cW1P�Dv�2�\nQT�03�5��ܽ�(���FQl���G�*BE�Q4����K��\r��W\"�5dKo��F[�����x���04���@B�D!P\"�҄B`E*���´Վ�(�u�'�[ja<8K�	�=&L'��^5{sѭ��ZR?Ԓ�)�� wS�Ż#�X>GB50;�S�Y�K�M+�gQ�<˄Æ��r���W���k���D1@�}��t�BL�K]&X��M�lMY� ���B~\n#8�T�l�p�E�A����o���%�TR��q�f1���fgTgH��Q��V[ڡ\r9�X�,��@U�/0�\0���P���y`<\ndpA;�LF3&��.;@��ë���mє�)��a\rf6�P�WU�`����%u�g#2��,���r�e��L*�\0q��ݜ:8��#]�+Ȕ���u�S��3YF���ybj�\\,R��ќj|S�) �KLj����ung*B�t��B`A�g(2<��q�]�L�1A.��O\0@�֒�k���ęO6��/���a';\\ρ��\0[ԏ<��w�s�yʝb��/�,�7�K�3�0�_ֻ�\$�y�`�3CH_��\nYnt��+�ˍ��> �RLTr�&�����RL�j<B�����+ږS�ņ�W(���)�4��Xu�Fx��\\�x�Hi�C�s��}¦h>�+��������(6Y�.�;�7G%?og/�+r���o��[PƳf�|�`whr��1��_�)A]]}��.�Ī'[�\\B��1E���D�IH0��^�t+f�[�lG�X�N�֦*��Y�8( P	�T,B��gO�ɬ~����PF5�>B��̲B0Fl���r�ϓ%\\�n���=`����n��nVz��o�j/�d�d0~?������P����\r	N�����#\r�6�w/���\r��%P�0l�0r�p�\r,�0���\0�V��\r~0\0�KO:���#�>6�p�Bf��Y�h #ZG�ZDC��,��J�zpb��r����� ��y�{�R ��>+'��-�M|v�BlF�hf�-��f�9Q4 Ͱ٣�1�D�IppU�����\nTp�P�q�̮�\"�������є�0�q����-�(j7C*7̮\ne�\\��#`	I�jlt�*�[\0��hۯ1��������g!p���M�\n	�1�e�^�\\�`����AR:@R?��\$RH:�T1�.�6� �2 �\re��W��C����il�-��+{(�4ˏ�(�y\$ҒG�^�æ��P܍�)��C�y(�+nE'�(�&CR�,R#q�\"�B!`�\"�܊�=`�\r&�S(�X�����\r�,=/rFIOq/�&`�wfiֳ�>D�R�`�kL'p��n�e0\r�W1j֓`0���I�+�\$��m�,�1&� �\n���p4�ޑK�6�&p���|��p��0n@��ɓ��P' ���O�%T�+�2m�؃R�pO`;/*?nD���\$\"73J0�b�먲3U'bf����H#'bEk�B��-�:E�sI�:�X���̒��:�b� PS�d�H/���H���@�wAp���)Co�@��\0P��BM]Dɓ\0Q\$u)����~\ng��4=��M@x�2��\"���Hk�jl\$�x0�!N��`�5��Ţp�\nR'D&��22�5\$�.�	�4�S �B\0�Ÿ7e_5�NB4,!FT&�N��=fWC�\r��jg\"M2vO@BY@b84�\nB�  "; break;
		case "el": $compressed = "�J����=�Z� �&r͜�g�Y�{=;	E�30��\ng\$Y�H�9z�X���ň�U�J�fz2'g�akx��c7C�!�(�@��˥j�k9s����Vz�8�UYz�MI��!���U>�P��T-N'��DS�\n�ΤT�H}�k�-(K�TJ���ח4j0�b2��a��s ]`株��t���0���s�Oj��C;3TA]Һ���a�O�r������4�v�O���x�B�-wJ`�����#�k��4L�[_��\"�h�������-2_ɡUk]ô���u*���\"M�n�?O3���)�\\̮(R\nB�\\�\n�hg6ʣp�7kZ~A@�ٝ��L���&��.WB�����\"@I����1H�@&tg:0�Z�'�1����vg�ʃ���C�B��5��x�7(�9\r��Q��j�����A\"���╷��њO9¦sL�J錆M8l(]43�\$%Ί��O�azᗩ�F���,�⸓Yn�R��a,# �4��@2\r�(�K��<:���[#�Yu`�5x�:#�9��\0�4��@�:ׁ\0�e�c��2�\0yb���3��:����x�w��\rUVLAt�3��(��㝳m���\r�-�V�(�c�#x��|�5p�vg)����Q�z��\$P��X�/;���o�D���;:�d�4��e��\\�fSV��)B@N�꼇8RBg%�B��9\r�>��\0�<��(�j��eKN�v/!��N]<M�g��B�+�Z6-DF��2C��\n�,�!�Z�Q�5��b�=��V�A0���\$Qq�7�rB�Ŷo6'l⬔�|�����)I�uBg۾��E�l�m�9%�H����<�����P��%�C�=�x�7lB��f[�V5ԧ���р�(���=�<��@��K �kɗ�PQ�Eȋ�˝}��� ��܊�8H����\"SP�/��\09���}~�P��U�q�&/(�s�CQs� &���\0�jt-��3XJPa J1�2�>��������ϋ���9��سK� L��7Dh���\\I�ᬆ�Ã�wE�'�B��+>\"��	r��_�'ܧ���i� ��\$BJJT3��ܘ�V��BA�:\"@\$�	D�̄�{MQS�M���_|�T�m�8�FW�S��0ܘ;�H�J��\"E�Ԝ��Έ)�n��@�&�\$��[��̸c#�\$z�:FėV����&O�O([/xR��J*h�Y5R�����,��)�M˙v�ԾMr5L�1L�b� ����ː|�n��1���eJ	((x�@�Rڳa!�U���Uj�9-��HdU�|.ƹW:�]k�w�u���K�}�aC�\r_`�R�D�.�^!	�H�\n})�E��VO�DlI�:�ϩ�jc�.�D�{(�i�/J5F|K�!ݏ* ����\\ˡu.�ܼ�^��|���b�3`pvg���ǐ������R�)e>*@��2���qp������L*�*S���vDEqV1VE�B��`͹M���\nJ5%a�:	�)��!Cl�'�?\$��(j\nj��,9g����	������Cfk409�U|�0u��7�uYqV� Z+6\"��@�\r��0�ȲH\"�)%q�ٕb�#�䝾\$�5��ea�y�4dBF��-�������_�~��8P	@��\nIr�>Qp�\"��JK)�7n[�`�Õhc�4B����Hv���3����՜DV��^\\t�sءID���3��/�:�\$��דo�Vk9�u~.����8-E���rj��4������+�y�\r��%(�\$�C�FE|�0��44MІ\$����Q4v�z�N�3�)�9�Q���`��rur�T�@VL뭵�����L.c�r��@^�DJ�v�)�{�ݕSP+B�.��a?n���R�4c�&�LQ%�EOy��&fy�&7�����	���~Y�b�\\Va��:20 \n<)�Hh����=PoU[R`ڢ�}eZβ���P:�C��_Ƨ���u<���P��J3���D�J&�N�^w��M{�͒��L����\0�0�lEVKb���Sx�׿�/���{gX�6S��p\r�v���\"�Q{��u�se H����.\$N9�\$p%��\"�XgS�&\\�8.m���tgA�P	=1���A�)g�)��Y�뉈4�'�?7g��Tm���!]՘h-��I�W���|d����`k<��e�)��hJ!�f�P)�)�.��~.	�q?i���HЀ����`��b�Y���7�S|�N��A \\�c���~<�����|R����J�ɏ��[_��C1��)'`���\"h�����,(]>���`o�t,���)�9ǨoLN��\rǖh>��v,B��H�#��m2!axQ/��\0��8�x&D�I�JqKV;+\\poDf��{���J�G��>�Q���~����k`�E'�ydڄ�4\"�w�s���^�~�cj/n�h�E�;t��&D�7h|���30�<�Bd��t�g��T�(T�_	�z}��p�nO\"��I��v�h�G�c�Y���а|㢔h��O��f�\n@�\n�� �	\0@ �L@�VE�\\LvV˪W%v��INF�)/D4J�yF��@#&�9b�����9�0�B�B��J-���G卐5�\\��4�h|�������Eoz�b�,kO��������	���5q�+Ѵ���.q�9�@����\n5g��b��o*1�'���ì���\"	&~��&�ȔbZk�� �����a�Q�)�qnޒ\$��)�-a���������ﬀ�yE�~������Bn���X0����T�/�\"�Z�b%���r4.24�O\n�~R���h\$��G?B�'⒘�v=Ҷ���%���x�U!�'ȸ�@NCVF\r����0������h�:�OV\$��s�(�kO.�*M�/�00�|�3!-쬏�n��s3�K�(D2��,�\"�I�r���iPI.\r,�B���!\nw5pC5�Q(�Gf�Ak6�:i0DIi�9�v=�,�J\$>��j��~�Ӆ6S|�Ӝs���'���7��:�;e:1�hX?ӕ;�l��<#�&��iNI��ө�)����\n,&p���P�0��{.'�@h2�91�)V�-��p��\$�N�B�CT��<�;ς~��}��/�M��ɩD¢^���3�|���s6�is���iF�>؛��=N�%�n0Q\$�d7=g7�T~o����C�\0w��5�R�2���xe�؅�В*�_���\$��&���K�uK�L-�\$��!�~.#1�t��M���4P�q/M�Bc��t5�r��Pp����>�)��gt�I�w1h�w�-U0U;����DSi9������i��	ÈEؤ�	B�fq��KN�%�oH*�ó2@MCȨɸ�\$&���4q���<���y�x�5eE�z&ЗWU�B�eHR�-�TN3T�\n�������ITpROuL�5Qs�C�H�1��F�Ah8\$M�Q�fe�w\nu�Vg�O���[R�C�VDTCJ�_H��f�)Q��HU!KUc�&y��UhE�]I�P�����bPby��,�)\"M&tp5�R6q����b�AJԛ!��g1g��fYe�5e�yh�=@�ai���\$�6�ehZ�Ugbm\r҅\rB(���?��#3&�/�&-��+C�Q'�*��y+hy×d��&4�g�{GT�NW�Sg�S�[6��pU�i�l=R'�;���n��1(!�=�3@Ѐ�n|�k�t6�<�ct�M��@��WeW?l7N��S<p6�T<*��/1Y��u���L�3�g�\rd�@:p�H3�b�8���S�y�O�)Yw-q�j�~�+P'�V�k�+v��(��+5��6�O�Oĭ2	ywdv71.�չq��c6�9�]�w�<�m<��#%wR�z��\$Ɉ^yn�'䪍�J@���C/�o�N��j����r!j�����+�1�O�\$��I�	J�F�R�5�&�*�N\n�L5�3�4���O�/\$\"�8SqC��x�5��IO\n�ó	i\n\r�V`���֞��B\"\\�\$��mh��<�Ҏ����2�B�f�R�Ax&�H� �\n���p(�I��ܮlf�HI��q�M��w󍒓zE�|�1�ÄR�\"J.\r��|o�����7Î�6�.�D��zB�FCsJ|�.o0�c0�c����\r�i�R�wc��C���G�Yn�-��.	E��Ҥ'\n�iz%�c56�U�RG�v+*o�Be-N��yRF@�N��j�\"!t�0On.Y_���0�&�΍��V�>���t8m'U�H����Sޚ��?u6z#�����E�YCC��Jw�J+�\rw���G_�= �.ES�f��OD>�<=gof:R6v�5�v�]��J�\"x(3��4aPq���\$ژw�	6PYD�Ύ 5h�+R��G�73ob��1��\$ۇ3�\n�w�Hj�ڣ�	s@q%\r*DGoR[�Z�*ę',IN0�㗜lt�J.�S]����:15�@����`k0��3Z�S+��˝��bH/Rb�� J��1�cq��g׈}�\n"; break;
		case "es": $compressed = "�_�NgF�@s2�Χ#x�%��pQ8� 2��y��b6D�lp�t0�����h4����QY(6�Xk��\nx�E̒)t�e�	Nd)�\n�r��b�蹖�2�\0���d3\rF�q��n4��U@Q��i3�L&ȭV�t2�����4&�̆�1��)L�(N\"-��DˌM�Q��v�U#v�Bg����S���x��#W�Ўu��@���R <�f�q�Ӹ�pr�q�߼�n�3t\"O��B�7��(������%�vI��� ���U7�{є�9`\r�Kp��K�D������>�+�ݽ�@��n �9@I��P����&\r���7�S��D,Č�j��?�{R��;X�F��1�(�Ԗ��x�\0�\0�4��C7�k��;�� X�:���D4���9�Ax^;́p�����3���^8IҀ�2��\r�rD���rr�8��^0�ɠ�4�m��=7�:�9�S�7��&:�c��,\nåM*N0L#߶���:��8�����+�+B���\$\0<�\0Ms]�<\"6�h���J8B#k����P�7le�����'���B �3[Cdj;.ue\$��l@�:ڐx܌�,[�XN#1�&g��jD�B�|漱\n�9�ۊ2��+�-R@�]?����11���Wx؛�i\n��vh��☢&S�(ݨ���OT�T3\"���N���8ՏX��R���2�h���p5���P��4Մ<\$��rP݈��p�h�-Q���\$j� �2��/p�*D���n����*�z<�3τ\"�ugnb0ʗ���\nb5!<ֈ�2�C,&a����ݳL�R�\r�0��^i���B�R�_��7��[�xQ1�z3�`��#��	�#�0�.�A_��u�2��R�\nh�>�0�=3��8@a�*��\n���O�P��\0�4^c����U*�����`LI�;�g�}�rkM���^���>���(%�ӵ8I�L>�I��&G%�����l3�=%ZLԑ~/�84��L�p\r&02䨕�BX�u/�ƙS;с)78���bv`��B�ä�<�,�`�P '!�=�P�}���8��q#\$����F�\$>5\n�ӳ^De����W�F4Ĳ7���Vr�!��g}\r��x��#��b��\n�8P����fy�818꽲*xW�V?���8(�F��H\n7�3\\F�AJ% �c��ho���/9\0���&-d�+�pF�D-?d�����r��\rZ\\<��.�Q�j(�2.ZN�q�>�d�F���GI�\$���M���]r`K�pK�\0�Oh6���\nv�0��1����|�`l]	\0��S~���#�l�H��6�hxG��U�(i5��n���;]#Q�9\"㌾!�P?�������I�I\"a�͟�e4��9��h�Ɩ@�	\r�����^����&��hxS\n��!�rG�91l����^�z0䬽ɒtO�-X�p�R�c\$��(� �J&���X��:@�oŗ�B�A*K:�H��'\$h�3U��`B̜&�'%���*�� ��� U��\$-�bl�B	�H)\\b�C�j6�b�{�ta`P%\$/�>q���('��q��2� ��TJa2��ĵ\0���M�*�7l�R)��u���aR�H�)�z1�<q\r��j7�f4�t��:	��U �C'*\n�A� rJ�V>����u�b�R�)1az?��Y���B�1�8G�7��|��iT07۩LB\na�=1�>������ߌn�i�>�yE��Bvø\nPꎅ6�0�k\r��ݠ��A���.�p�b�s��1�Ƣkg+O���!8!DzW��%R�F���������L��FO�u�b�yN���l p�ϰ�f%Sh������	\0����R��6�lS�QI`e\rN=m���IAW@�����e�z�W[]Y/���1��{4�%=��6�-Ӯ����v���;3Ͷ�F��[��R�F��d�+z�l�U`=U�+�����V��cqnGÀweG��:�/�8��{ԍN�&l!M�}���'5�<Uk����%�jIE��E<��,��<��6��u�Qn1%@�&�i����.^Vo�Lf�iؑ��_�q���vu��\n�?b?�\$+�[�#�-�媃��y�VD�����[�=T�\n�w��C��>آe��0Y�}\r�1x5O�o�Z�0�B-t.��Jʥ��������rr�<`p��;�-��o�3V%>�� ?v�����54��x�\neEeJ�fIj9�}�l�uR�(xlV���c0#<�>@�y�\$�)�*���O������7��yVpa��L�\r�g�.(��y���B�e2ݭ�����N,��&��F�m�#�\\��jX��X�0p,cmJ������V�l|&��.>������-��/C�aH(���/C���d�/e�\r�(i\n*�b�k,/O�0�D;����X��u��8\n�ې\nK	��̅P�.��'�|���K�����iƊT�D7m�L�X�Z�L8�����l��;�F�o8u�,����mS.����/��g A\$!�0ZBм�B-%�|%��\$�Bk��ee>�J\r�V��.���NE�0�k�q�&�*hG�^/�op���8l��t�1�іa�8�.�]Rj �-��i�����4��Ҥ�/�%--N�U��Ae�q���7e�i��mhA���PE ��q��/����.L�Z������vW�?��Ce�.6��L��\r#���!#%t2C�%�\\QW%BVi�P�͵&h�-�&�(Y�&\r�V�����+B��i�0��xbh�@\r��F�\ny��\n���p�Æ0b�&��\0���,o��ߣ5\"8#����������/�����̜��K\r��8��G��/-2j-N�D�B���D�2�r~D2O/BHK�L��j�\nDD.qE�(/Q-��QJ��<9����R#G� �).\"��0�J>È��f�/&/�J��=6�2��d�\"�7�C5�J��\$���E̪�����TBPr��hK�����?�vb��b�&��b�0\$P�G�j�KΥ�>�\0��@�X������{��ɂJ!�7e^ңv�I<��J��1��<����XB(t1\0޽��-�1B�5�j>�XO2v:��� 	\0�@�	�t\n`�"; break;
		case "et": $compressed = "K0���a�� 5�M�C)�~\n��fa�F0�M��\ry9�&!��\n2�IIن��cf�p(�a5��3#t����ΧS��%9�����p���N�S\$�X\nFC1��l7AGH��\n7��&xT��\n*LP�|� ���j��\n)�NfS����9��f\\U}:���Rɼ� 4Nғq�Uj;F��| ��:�/�II�����R��7���a�ýa�����t��p���Aߚ�'#<�{�Л��]���a��	��U7�sp��r9Zf�Y��b�΍��~���=����(L3|7\$�8�0�( ������B`޶\"�	Nx� ��A��P9 �ҳ��*ԥc�\\0�c�;A~ծH\nR;�CC-�H�;�# X���9�0z\r��8a�^���\\�:�x\\���x�7ㄍ\$C ^)��(P̴���4��px�!�j+\$m���P��M�\n��j������~�\$�,\\\n�H�+�+�߶(j9G�����B�~��CP�\n�d�\"�*�*@MtW��+<N���#Ã�7�����A�{�fP��(J\$�2��P�(�#��2C`�Y���.:�#�t�A%Z�L P�w,��M%Ic\0���z�ų)��:B��4�K��2�3�T4�cZ��4v�.#\\CcL� ����0������cx1a�6�ncx���r�J�q����4�,����%VB��QU(�~	�H���\r�Q�J�y��\$���J�μ��!��.O	?6��b�P�W6IJ���>A� KHA����X7]�\"k��#M�'�n`��w��Ը6㜄!���լ/Km�#��cŢ�+���������0̍���-%� ��.��;7:Ʊ��q/�9�z/�0���څo�\n��@��nv�yS\n:Z���\n�p�*rN�B���L��RX�>�HdQ&�����XKIq/&N�΢fM��'�����OL�?�h���} g�͆u�UZj\\Ǵ6<�Hٗ;�C&\$2�RnaOzE1)�����0.I�9�%4����[K��/�4ę RfpI�¦����Y��\n���@���\r�ӻ���Qpp=�䞬�.b`��%A��B�xu?����i�j@\r�x�ư��O�!��\"���LK�cД�G��i��'�4�C{�\r�̚�R�S��x#)�2�\0��_�����0i��z*��(��(�L�;�����\0PCO��1��sC��4F���ܝH-GQ�7�xr��k-)AH�|��T_i�U(�ԍ���5Ĥ���:Iܳ0�qKZUX�H�6dmxb&%L0��4!&K�~��\r	òx��4��p~�q=\\�x��u*��1�ZP�ڶR�z\r𚄒(L�)q@�i2\$%RP0�c�_�FE���8J`��MF���\0� -mjh�����ܚ[\n� �GF�\"��P���2\\Lzڬm���L��R��3�ja,n7�3�\0�ɟa�\\��`�-�:uk�0>�~EP\nbXd��ʂ^rp(}�<3�֦U	d�2Ĺ���\0U\n �@���@D�0\"�冱L�\$�%I7NZ��	a,Q�eLʣ�:'Lꒅ���\$��t\r�2\$WP��\0ʞCa~.��)��	�i�����I�.��~Y��G*�PB����v����]�b�[� ���t�-��H]��Et�b�-������J0�H0h��¤U1	\n���,{F�Kyq��|���g0���@b�c�w�3;OL��lA�e�\rB���[�I�'.}���C	ʉ1�!C�f�UTW��3Z6-l�gXL�R<ň8(u��%C�M�p89�=��z�I�\"�%B�E�:v��ɻ��y�xP�'9W���kR�\"�`���ST(B@߳PQ�A�@��օ@��@ �lҺ�hQxp��_6���!(�%����^�-W�^�^�M�FIa����\r��������ݟ�qT�D�_�I����1k�5��C�\n�����_���|HF�Q���o��u�:.>!܋�7HI�\$�׃8�St�'\nA4j\r52}`��fl#�Y4Z��Wߢ�4��~�xP���K��܎.��<k�K�����ß&�sA�Lɸ�j���B�w_9�|[=�`�k��\$�%�h>y8wb�n��R�\\t7�2!�l�u��|�a�����۽��*�h�8|��;�_��Ըv��n��A?g���ΌrH�:x?C�\"�Ahȩa4o<��^ɧ�4�H�,�[=���:5���v[��QߙG,5�/M�=��Cv*Q�N�Z�����ٷf@��D5��qq��|���O�����-�ψ�;EU-�{U��'M}����(t�D4�0�1.���i�.+m�W��j��jbN��b�N0�p\0K0����Ê/���)\0*θJ���	��J���.'D�3cޖhč���h``D�r��k���\r\$���è�f= č�7�ͭ��B���G#BqL4�i���%bu��O/��+.,��Z�h�V�k�\\��;eS)zS�>0�����\r*<S�<T��.�0�%0�\r����K���1��T\0�\$\"�2��߀�����*&�Q%���\rq�Ɔq9�~s�ʿ�H�4 @Pӭ>\n�F\\΅�j�C�	'�B�_�ZÀ8E��\$��iJ�QK�ߌ��Rpd]0&��+\$Q���Dg��]�,�,��1�&��)��-L�\n�1�梨��l��͌�b�0��AH��\\�0��V�V�OJ��1�ʬ�!l� e�%R%�P�\rЍA�9qY�,@��7��.�\$�Wq=�ߒX2Q����̔ P	_`����c�gK��\r�#R�	zWG�+�1��h��2�)�W �`�Yo~ۆ+bl�2�VdqM�{��FFe#�9&  @�h��`�&eNs\")k�\\���\n���Z�\r��\$����M��#�~�`���f\\��|F�鍢\"���`���@\0�r \"������9d}l0�3Z9-������%\$y6�;`���ݢ>;p���f\n�T��4�Ļ�ɂ7f>�oZ�/���VQ5l��,`�rl����.�:��!\0ތFߒ��s�<�\r��B@34%\"�L��\$�2�L �PSX^E�j)��������L��Ϯ�\"R�ˊOi.b9B�.P���q��\nO�ɀ�(f�	��\$Ŋ,��&E��.�O�� �6�a��;L�<Cx5�eAf���Ys�U��@@�-CXA�|1e�>�C&��_c�<`�	\0t	��@�\n`"; break;
		case "fa": $compressed = "�B����6P텛aT�F6��(J.��0Se�SěaQ\n��\$6�Ma+X�!(A������t�^.�2�[\"S��-�\\�J���)Cfh��!(i�2o	D6��\n�sRXĨ\0Sm`ۘ��k6�Ѷ�m��kv�ᶹ6�	�C!Z�Q�dJɊ�X��+<NCiW�Q�Mb\"����*�5o#�d�v\\��%�ZA���#��g+���>m�c���[��P�vr��s��\r�ZU��s��/��H�r���%�)�NƓq�GXU�+)6\r��*��<�7\rcp�;��\0�9Cx��.����*Fɖ(�����%I��&�Т:_+�k	��q�Bk,`X�k2��B\"�6��8@2\r�(�@C�6:��##�!o`�1��:#�9��\0�4��@�:Ɂ\0�-�c�2�\0y*���3��:����x�?��w�pP���p_3�sL��J|6�R��3ACl�4��px�!�B�Sj��	,Z���;d�\$�jB��̻����^ϳM�<�\$�k�ᐌ	D��Έ�\"��9\rҒ8��%~U6��dBOӆ���\0�2k�\"V����_��k\r�κ?���}X+�ImtԵ:L���ZUq��q��{\$D#�Yc\r��::��55S\r�<,���#�(���0(���/�����o�%e�@�^m�a���\n��&0)RBYcvV�Nz�__C=*bWVW��wv�M�j����\"�'���B[�z�Ȇ��*Z�0�%w^Hɲ즊��i������񵅎��K��?�x�!_�TB �9�#d���T7r����s(�:Q2�:9�KIir:hR@�<�^\0�'esª 5\";�gh^\$�8H�>C�(�f�C���6I)D�&���&O�B�Y�V��Fq�\\�,�\nǐ(�SXn*2DF(V�A&��W�MJ��[{Ww>�P��wƥ�'���3�ho���Ŵ�ߋ�~�9r?�����9-�s��_�D�ǜQXI][�{ (*��@�:���<\r�6�7NC\">M��8�4���{O��;��(rP�\$�8�@�+)�ڧ�	[B����mޙ;*�����HWUFM�!7ZX[�66'd�h@E��1,�k#�\n[cz!����B�o\\��6q\"�D��|O�AC��2�QN���M����H�X�!�)le�W��䒪%h�굒�\nױ�n\r��XTc![h8�4HT	�*3��ԛ�^\r!�6\0ėC��A�2�P��\$3a�'%\0�f�l\r��M��\n]r��4�CtX!��8W��̡Q|H��5�ڙ�K:�e��BhU�U�o�(���\"3�>Ʉ�\0((`���k�u6(\\�����a�c��:���I�r��r�d��ܚD\r�2n 9ʨ�cւ�����5\r%j� ARR�sL�>��Y�YC�dLɡ5%��@ia�#�Ν��]��0�P�PS\nA|�֢_	�'Ԓ3�uF�����d����d\$�\r�����S&ee�Pp`[��B���c�ƒ&t�����<	\$h<�4*RFK��j�����uHI3 ��\"z>�(1�\$�>+�CL��v\r.�J#n���X¦KM���h�QEVJ�]���H�KW�0(I\\�(�ڥ�%���.�qfM��z����K�~��=g磌�)m(��CϘ#J?T�ynHx�ڙ/Q�Nn��?»��U��3��8�~ ('��@B�D!P\"��|(L���)Z���\n ��еL�d2�?\n�U��T�I��hǑ��uH���%-�bAg~�I�\$�3��n|D�i��J̤��+q����7�iE����G���T�mGD�v�t��кUV��ʾF��+�щ�1tt�z�<�p���\\J�:�o���R���1I!��k*#+��k\"���F/@�� �a�D.�[�mJQ�Vs���T�},|�.3�Uܬ�,����g�SD6���Kj�v�-��ؙQ`l4�g��\$��`c�����K7��Nɺ�-��cC�H�1Z�-��80/l��\\�|�L��ğ5>G{�~��K���� Aa \\�RT��#���S��8��LjZ���bF�_wNB���hcp�)��7�Y��ԲŇ)�.BÉ/3�%}b��^�Mu5[]S���C�\r�'��k�U2���vI�ʹY���;JM�1Q4<٪�\n�� I�bB�c�q%o�?����<�{�'���zL`�;z�n'��_l��=���W|;f|��G��u䢛������2��2~`��JK�\n��+\$�/������8��s��GZ���Ͽ�ٻ��ݚ�><���B��#a}�D��80gV\$���j P�PYm���\0������\r����i����h�C&�����[�!\0P�\"n,� ��g���F��ހ���,�Em8~�3�~��q���l�-4φ�;8X���\$��o�n���g\nϮ��ڕ�4�涳ϰ.C\n��(�L�0��,Te\"WO��Ř��\\¯�W0�#lJ����Kop�F���-rk�D��P��T2è^A�\nlA�N{�?'�\0�l�Ϣ�G�(�����d�\\?��l&�l�LƸ��0�1,[�΃%���	Ў��]�h��m�~���Em�䅙q��Q�m���;]P��P�M��e)��N�n0^����,D@�\"�C�p�B��c��L��p�P�Ь���-�O)�R���qui~�'ώo�Pl<�)�-\$:��,C��)�zm�`���P�����p��R�%�Q	��&CE#1'bw�\n��vNY#�)��(kqv7�B��k):7�q��o��\$<kM,�JDcBV<�bwn�,�\n���\rDx���1�&+:3���\"Jd�0���W-~5��poCN����2 �����r���-	���\r�V���\rie\rP��3���f����\n���p�������V3�5�\\�/��)*�T�J۱O\n�KE�j��f�%B��S%㺘�\\ �}!��C�M:7�\"^��\\����0�l@ELF���ɞ�\r��1b~����O0#&�Z���o��DB�V���m��d:'�ؑ??�	r#�	q�7�\nw�6�\r4	@ҌѪj��;@��7�h��T�0zS�w�lcVz�=T�\"�`,�dÈ�J�0G���F�ˬn�Pi�}�~h*�fؗ��ݓ�����q�;@�j�\n��3D%\0�-7GeT��A	�J��BF�c@���t��6M�x�o'�H��,�s�)et\\@"; break;
		case "fi": $compressed = "O6N��x��a9L#�P�\\33`����d7�Ά���i��&H��\$:GNa��l4�e�p(�u:��&蔲`t:DH�b4o�A����B��b��v?K������d3\rF�q��t<�\rL5 *Xk:��+d��nd����j0�I�ZA��a\r';e�� �K�jI�Nw}�G��\r,�k2�h����@Ʃ(vå��a��p1I��݈*mM�qza��M�C^�m��v���;��c�㞄凃�����P�F����K�u�ҡ��t2£s�1��e����#Q�4���p��%ɂ���S�ɍɈқ%��0��,���{�4����:�BBX�'���9�-p�0\r�2��@�29���(c��\rLP�(��\n%0�@4��Ry	�Лn0��:h*R94lj���9�0z\r\n\0�9�Ax^;́rO�ap�9�z��c��;�c ^)��ֶ\r�Xx�!�j+%�;�%@���a��7c(��H�ܶ\rc�魴�R׶,@�:�k�/T`�(#[�:!�#^;�HK]%5�)�@�#��8A�X�-.�%p �V{�.����hV!��XѲ�4#=�Z�7�c8�0���vD#\r4�3�ZX�	��Ӿ\n�挨:5�W�p2��\$Jӧ���&Cu�2SVr��/���f\roK��b�����6� �BR�6�EL��0Ĉ��i�N�e�K	);\r�/!�Y�'��1\\=�b�X��}+6�ϡ�.P�%�H:~�O���0KZ[#Mr�׳y�)��e��ÿ��XmiJ@�0K��4�Je�'��g\\\n\r-�߄>�	�Ҁ�ނvԧN��#��z�1(�[â�3�0���l&�������2č#L�6�ʴzR)�j4�4U=Z�-�tW�(.DP�-II(�(�:�c�_��t��M����K�'��4�w���4h���Qca�cϡ�~������?�����h�p/όمP�F^Z-h����]��#a��䤥\r` Fi��Ģ��:\\KɁ1&D̚Rl�`䜓�v\r���u�����2�LZ��uV��ՊG���I5	/��R���~Q�;2�\0��w0HH� Z��*�lΗsRHI�u�%��(tL�5���\r��uN�<\0ܫJbP,L�)ؠ��s�ȍ�B@uI��K&-�*�x���vT\n�)EB6�L�<3��*C�[\n�'dl�G�wR4�7��x;*��(/�)Eךw��Ie����d�vV�,��'���z�eXr�i�~��o�0@@P�q�����8()���w3O����=K\0S(e-,�Dh\r(!�4>\0��ܝ|~Q\nGM=���`�مd8}ɜ�t�E��S�QWa�Y*|�B��fQ���(��Q��m�����(D��[�DQt}Q�(R������p 	����XB��vd�M�δR\nK'�l� Դ]C��?f�<��j�KBDS�9\$Oɜ .�6UJ�OTMTr|<�����6liD9���`��iW���˒pN�8O\naP��)����D��\"�s@uЎ�b:�� n��3��D��	F�n�X�I�I����&2VOnJ�L*\0tO3�9���B%k�D���^JjsZ�4���pAb�\\a<'\0� A\n��^ЈB`E�hAK��yY�z:�Q̂\0�X��A[���A�:�堓gr� ��ĉ�d9�P�lG�\$&�ґ/%f̩3c�E�)�%E�U�Z\"�g���fuS�Hll���Ƙ�Ck%��D�#9�L�n�z%ޙ\"�+��y���d�KfN)�A,���I�^f��r����sX�0��&Ì���.n��ę��b�T`)I�Mc�ɱA\$71����GѲt2�3U�n��i���i�s��8�>rVb�6�T�m��uZw�Տ)�GlD��\\aJ�Nm	Zep�\0K������Z�@��C(s��3H�Z�RD�<풮PN�C>��m��hc��/\n�KY ү�ё\r9�r��+0��i���㻉��	{�{�}�%�{�曀�����پ����Р�͛�T'Wsp^�x��5ϻ��ili�C	p<�v�k?htE((�̾j\"VKn�2f����o�*p�|��s�;r�oQm��stw��#�̡\nH�rV+4�L�+t��Ӯ�#*e+�~(��^�\n�Loc�H�wА���B\rQ0xH9<P���c���0�|��K��2�N�b�t�\r/[7�Vֶ�D�T�\\�rhPu*>r�>�u�}���l�cL��p2N�v�u��K��x�R�q	� �Wk��\$B��k��� ��צ���Wlܣ�B_R�z=d��H�j�(Y�V�	�n9��'�}���]f��i�F���/˳�~�N�o��o�	cD��Rפ|\$�Ȯߧ��N-x�P.|p�-���#��N.��<��\\��B���}o�����>�g��O��ʦ�6� �6�E�4F��� \$vک��lp-�Y�L��v�|U��\$`��:'o�\$|��p йN%(J#�=E�\n�<BP`@���f8D\n�,�_���pxX�\n��Wo����TNF�/��A�UOZ��\" ���-�!OV�����r�)�\0X��+Ɏ�Q0Ft�,�g �`�b�hb&	��� 	D�&�����|#�%��P&�R��&��z��1o�p��:\0�]%֜BG�~\"�.��Ѡ�q��C�r�\$\"E�4���w\0���c�\rZq�^T��\0K)�P�Yp�\$��1J �a1�'lq���m�5�\$�p:<��h�\0/��M�rD@�>���A���v��ʯ��{��##���.Q���%��\$�_�`��g4q��;�&��\rn�p6f�t?ʺ�R�ŀU�`�*�`�@�cnL��8FtH����x�)2R����\n��	&��2����r]�;&M�H[!�/�P�� ��fQB����0�q\"�+0�)�'f�5�,�/cX5�\\����\"R61+���,W��w\"d[,�cmn�����P��B�-Cl�SX����m/cD�n�^f �\"�7�G/�7�p�∙Q4�L7�TepDDv��P/̆	����(ث�a��\"o����\"&Q�V(l���1�<\\F��\$L6��J�7,�ą�C��6�J-�<��<*�=LLf�(��>��8\0�%h%(�w�`@�q����Х�q�*�,NG��0�\\"; break;
		case "fr": $compressed = "�E�1i��u9�fS���i7\n��\0�%���(�m8�g3I��e��I�cI��i��D��i6L��İ�22@�sY�2:JeS�\ntL�M&Ӄ��� �Ps��Le�C��f4����(�i���Ɠ<B�\n �LgSt�g�M�CL�7�j��?�7Y3���:N��xI�Na;OB��'��,f��&Bu��L�K������^�\rf�Έ����9�g!uz�c7�����'���z\\ή�����k��n��M<����3�0����3��P�퍏�*��X�7������P�<��P�BHcR�@P#�0�P���-c\\9��P��%(��̚����2�Ljk\r/Gڵ;-b����R ��j �E�T����B�����<���4X�Ѓ�)�Z���p��z42�0z\r\r��9�Ax^;�p�&��\\3���_5�sh�2��\r�rP��r�����^0��Ђ����`���;Q�Q��6'��:7K�1����\roT����Br�2��&62o��\n��7K��J2xƁ�M�l�m�:!��da����܃m������͕6t�����8�\"��2�2�22o�k	Yc-���+�#;8[�U����\">�W&�{L�J��a� P�9+�VT�c{�9�/���6�H���:��(��0��)�y���M	E`�9N5~eVD)�\"b�:��C�>O�_�)�z1LquRT�{����mf� P�z&\"9�޻��4n>��E<_��@P��.�aP3�h�Bǈ����q�t��8�E�n���\$��\"C�8@�[*�q��hJ]ث��p�biІ4&\\£�0�12��%4oZ�o���K���B;'|�kp3�F�YZ��3�Mz�§B#&�a���,c���0�.'ee�8�UN`MR�8-�P��#1!+}'�jZ\0K'\r���G���[��x��\0j�� ����\"���\r���Q3�*A�� ,�� ��U�\$?��&@��a\$)%u9�S�xOI�?(\0#�D��^���`��<�	�S�1ڇ����(�Րg��9l0��`�U�B�D��F�zϣ2�:�#Ĕ��}O�A���B�g�Ȱ�\"��r�^��E�|؁\0m{)����M�%&FT7(��يA4L5��v�J�)H�.;�i�d�`�1J��\r�ؔ����ƖØf3��7��2�Q�>F�p0��eJJ9(��;i*�K	ck\r5�:S�3���@\$8R�.����E\r1_1�̐�Cܗ;�a�7��P+�[���8D�IAA��2B�␑ڜ؃�\"tr�!*�֐��6��n�&�ܙ	�6�%�<�C\r6�)-�7��pS\nA�T\nRN�V��\0��\r\0%�3SFyxT2�E���%�P���� )�<��5d�S���3��`U#i�ҚsRj�Y^\r�\\ɐ�vȌ�P|EJ��hz�S�G%��#��ȍk�}.|��\0�¡>~ ����\0R;����O�R66ql��]d�'�9�4wYa;K}��\0&0��P4F��,sW>2�n�e��\"�VɃT	�`�=�3����6�ZK�e������t��	{7��g�XH`cV�2���p \n�@\"�o��&\\�W�~'�as>�!Y�#<5�Q\n�zN���C�<8)�Vp�}Y=�i�O[����e9�Dh��Hp�\$�\\�Ɉk���?������(��(���8lr21�d�?��*g�Ve�\$FИt�s�\n�7&���Fcl��3�;�r�� /�P�H�S4�>X�)HH:v�ܞ�Y&��%�v(ҏ��Q�vU_��A@�=�w�l脔���'���g�K-[�G0a��\n�J�2�e��)�{	a�\n�v��|�L�hΨ*N�<RC�\nmJ��\r���nD�bl��qD�\n�I��}=�E/��p��M�'i.^��Tq��83+�9\$�H���b�itГ���!�U��+�m��>�v�T\n�!��A��AJZD�)�GN�r2���{b_��\"�`�VL_8e[�a�w���-�dL���Td�MHc��=4����R��4�`��O;�+����E��Ә�������ۇawZc|p9�,�\$�o�Ժ�?5+�tP]���9�%+��>�J����\"�r9�cٚN�J����϶���e!2�2��|�0�Fr[���M굘�\$6��ck�����p@�(b������Ȼ�3-؂tζQ�:q�u����A!��'�Oُ\n��P���A:�Y�/��\n�u�\\*�gW}��O~�X��Ҹ��&�t����lN����+���?jL�2&O�����A\0E������2pxL%\0dR�O�_�N�+�efY���b�\"��ZC��`�g\r�ha���)TMPTS��_���|���3�N]DZ%iZg�§0*g#p֧�\0L��)�\0����\0\"x���mb�p�\$]Фll�U�қ�!0�	p�\n���\0�\$�Y���s�������n`�������\0\niԋ�p���� b��m��fb�!P�P��̅����(��/���Q�����\r�\ro��S\nB���8�'��kd��.e�RʮެL2�&������(P6�@.�e����n3�t!1d}�h��t�C�T�\rE������E�<�����\"q'1*�MJuB��\$Rof��.�2Ͳ�bQ�\0E{P\r�̷my(�r�r1]\0.Rf����P�Ϲ/�\n!R�c'\"�,��\r�6��:�G\$�!�F�Ͷ32K\"�NY�ݒ_#�<nD�D�P��w��\\�\"�º_�L!E��O�\0Q\nI�Y	�%�9)p�\$2���Ყ%%�\0002Xie2��e+��=�V^2g%��b0e,��n�,2^%�]f�2�C��ek���+�1\n�\$r�}m����s0D[0����3%C˦ݲ����0�ݒ�0�.\n��O`�R=&/�ᭀy ��-��4�1ҩ13ZC�`��_\$`�?f�-�+R��Y�4 ʌ����p�d�����9\"F|�%�3s�)�C�1径�!�X3���^�I7%�\$N�[����*0�\$S�,@�k�_c�5q�-\n���8iA\0�r'b���g�|jJ���3��@��Z���6�@�N���>0�CbUBtq�D\$�TD�����+^���h�dcE`��@��@nE\\iM�\0EG\0D��G8��1�w�!��|�p�vZG��Fq�T!���U;C<4;o)PvQ}9��;&��\"�!4�D�elu\0�L��N�;�D�\rM��:�5t�M�)L¢Th&;d�I�آ�)��:cF:�\\���O�\"�. ���KҶp�T��\"c͞��#�5͂y��#��ɋ�Ħh��\0\rB�t=/�����c��@��qNldǳ��\n�\r�p��YC\\��1�ޗ �e�	\\��BZ���=��>`A`�"; break;
		case "gl": $compressed = "E9�j��g:����P�\\33AAD�y�@�T���l2�\r&����a9\r�1��h2�aB�Q<A'6�XkY�x��̒l�c\n�NF�I��d��1\0��B�M��	���h,�@\nFC1��l7AF#��\n7��4u�&e7B\rƃ�b7�f�S%6P\n\$��ף���]E�FS���'�M\"�c�r5z;d�jQ�0�·[���(��p�% �\n#���	ˇ)�A`�Y��'7T8N6�Bi�R��hGcK��z&�Q\n�rǓ;��T�*��u�Z�\n9M\nf�\$�)�MJ�ʽΠ��h���#����.J�ᎈ���+dǊ\nRs�jP@1���@�#\"��*�L���(�8\$��c�ph�0��º9#��4\r�l�G#��2�\0x���C@�:�t�㼼1S������x��rS��J�|6�.��3/)ʜ���x�?C*@1�p:��0��3�ޔ��X��!-��7��+pԷ@U/L���x�\"cx��C(��B�P��\r�]��\0�<��Mi[W�.7���ھ�B�ҍ��Xܓ�O#\"1�vT+H�z|P�� +.��5o(c4L�@\n���0�7P�f�Q�;63�0��:�c���0�߉�3\0Ǩw�\0��c(&ej����֪�n�U�\r4��p�_P�W\$�����d�X��(��T~�W�����M4��P�C	�0�s��#��U7�6�z6���:r��2��4X��5KE�)\"�fc��j��C-D��z���� P�!H5�C7�#��\n��R�>������\r+%Ci��0�H�1U�TX�D����ao�OE�N�Q�B��k�^߳�G���\$2�c�T:��2;ڈ�B;z���X֪����ɾ�0�I\n�S�(�wڠOz��ꇃ��~\"C�v���\$�hA���k�Q]�5\"_	�c�|F�#�ޚ\n�#����)_�\"�VR�k�#����@�C*SJ�],�����?)�4�^T�{���Bz��qɅI�Txr���[�3NJQ�eeQ����c�\r���(T���d���\$䠔��VK	i.%��,L��3��0`�N	ɰ�@��)Ĩ�R�X��Mg��JÂ<J�������(a�������1�\rƬR`�\r��	6D9RC�Uj�0�cJr�8a��\0��9ؒi���ު��a8���^�C��,x'��a���.@�\0��\r�F\0PU�L� eL��vG��\"���o� ���G��т<���;ç,��'�g/��|.vH-�)�v�\r�<�?�`f�~i��#I#��pH�!6��&	ph���3�elHC��_O����!BS\nA�PC@� ����֮��1+x�d���4g��l��Ʋ����Q='��������K)���e�\ra��ZNF�\np}��i6\$�a���_�aP��d��H��.�CP�R�#�QS'�0���bb�ɖ�Ț��u6�AS�\\V|��4���^�%� �.��U��PS�Ά#���e(䭗���a\r a'\$��S\n���:툓-֨tI	+2h��s�4_E�E��P��՝��\"I+	Ȩ)Q�� �0�BL	!h �)��p \n�@\"�n�A&�'� �yo=��&[�v���eYa,LM( j��F\"��u(d�8Y��Lץ�9Gu���L��Az.�z�C��^� E�h�ǳ�\$�+�E<��1�Vv\n;(�i�()�U,mt3�|�Тm&�S|���Uf*���#�,��|W����.��:p���PSce�`�-�c̒�����~��1f����1�b���^l�UԺ����bX�ȝ�.]C@2��\rallH��\n�x§��mM��[zqb�0�i�%����R�uR8&��<0�z@N��շE���s]���7-��S^��޾�F49�5�eOR�LvL!��ͮ��堀������!�f��J�*0*K�N/F��<�ތL�4ls���\"�_a��3��]��@���!�Ԗ�Njt\"�C���4��aI�Ć�.<L�qR���*�0�!y��L�,4�6Zkvw�+0�+��\\ε�|e���iV�>\"�S�r~�3Q'FC}!��Ӧ\$?l�ڢ�[��eP�ڼ~�!�\\��kz�r���2�����h!)����u^�\r;���'���4\"���(Q��w���C�]��m3�����TJc�r��cb�ZU]�S��#;�&�Dɰ�A�Sô]�g��M�4���������1&��7�oӢ�Kw<�xt�P�{Mȅ�C<hȰ���� U.0�d�V����A3�+�������G�c�o����XfJ| )�\$�\0uh�/�mƀQBh��Uol���	m00�\"n��@D���� �0�r2�B�P�`����nXЎ^ƎB�pE�	)��N8��2��T�>9#\n�\"B�B��E0gB]�F���q\"��.3p���\n@�v%���\\��i����j�\"�&���3��&��I�	�<��,������P�Be\"�\"±�05���%ذG��#��m	�>X�F1,�b�j�?�\r�L1�0��R���P���8\$��Й�k��O~SmI�j���܄+	7�Qʅ�O�U��g�\nf�%1�{� V%�N�c��@�de���R7>��+�4�ި�b{�h�I?Q�F���ʑ����M\\�gj�cq����%��������#f޲�NE��T�>�͘��C!͌=#�(٭�/2\$h��#R,���#� ���UE�%[P\r��2;	qi%�Rf3�`@�B\"`��F�\"��2�T�xWr\0j|\r�j(b�4.)#Z�\"�)ʨq��1�v��*\$�w��+oR{�^����Dxpw@A`�`Ơ`ƙ@ġj��\\��t1�N���+�.\"Ɯ��;	%4b�d�@��Z�\n\$i�1N�BD�z��.#\"6#�9F��ozeE-0�\\���E�/f�=�n�m�ڂ�/l-/-;4ˢ��C �8�����7��<gl�~3>(3P��L<�,(���n]�#	��P�o�i�c�C;��;��=:����	��)�s���y=�;��G@_r��>�.�FbPP��b�o� �B���ޚ��tB.1��>l1��A�;��!B�(����+:bd1�.Ĩ^H��V�=LH)E~\n�2�>@�/C�-j:#~\"�.lJ�E�B�\r�"; break;
		case "he": $compressed = "�J5�\rt��U@ ��a��k���(�ff�P��������<=�R��\rt�]S�F�Rd�~�k�T-t�^q ��`�z�\0�2nI&�A�-yZV\r%��S��`(`1ƃQ��p9��'����K�&cu4���Q��� ��K*�u\r��u�I�Ќ4� MH㖩|���Bjs���=5��.��-���uF�}��D 3�~G=��`1:�F�9�k�)\\���N5�������%�(�n5���sp��r9�B�lwq-��m^�|_����|mzS�;Iʡn,���c��0N�(f�Lק�J�# �4���@2\r�(�;C�2:���#�\rp��0��:#�9��\0�4��@�:ā\0��c��2�\0y���3��:����x�+��%\n;�s�3��(��㜃!���\r�k\n���#x��|�@�kz������HJס�[���H2�	��Ѩl�#n�↠�jjT�B��9\r�R4��[�A��ZkE�t���(\nf����L�94��7\r���i�\"k��?S/�s-p}��'�U����2��A�;�dA㠿�)���.\$�'Hm��\"	���i-��-z�I���@��B��&Y�\\�5Ԫp�?T��шS�']!r�in3q��E��PuYMZ)6k�i����6��y���.Ի����L��l��B �9�#dj���7i:\\7�i�(�:LQL�9ω���4��y�U��p��Ȃ^�Ql��ս6���)���5�u6�C�����[��[�2 O��)O���L��'�����vs:�������}�\"��Y �sH5��2ko���\ra���'�m@��2^��\r��P�*9W]ug޲4�%I�t�)J����ˏ�0�pƠ:N�D&rLN������:p� (�� �u�<����3,�N��:\nI����jOJ)M*�p�R��K��0&\$�����M)���S�Iأ|���c^J���wLŘ�rDl濘`��3Q˄��rN�{�\n���W����i\r��\$j�qA�2������a�\"��b`l\r�\nD�r�5iP4��!��ѯ�4!5�@�B�(�\0P	@��\na{���(!�d*��nEa�7���C�J��*��F��:\r�%����Arh��:Kə+'�!��ކ�hsG�FX�/CppG�� \$ �C�h\r!�4&�|MF��1��㝄����\naC?���rD���t�hRH�w��ؚ�Եr�!��ݙ%o	�\")f�tBT<��~C\0Z�^#���Z�I�;�+��h��\\�&���O�1H����N�(�'�\$�8�P���2DAё����>\\������ُ<�z�R��ӿ�RBb%�I�/��E�<�Y�:Κu6�� \$�@��=\nH�9��-���sI��5Ǹ�*�{L�('\r�v,�es)u�B�&b`M=X&��(�djʖ��\\����H�x�l��H@g\r�-g����`n�Z����*�6�ؽ��5!�6�z�O�v�	V��W��!�Xn�Ii��� �%Gk�Ar���F��%n!�\n�Z�Z� �J����OI{�#d���s9Mҏ\$��ȣV*A�AbH�ޥT�[�b�J1l��ż�,����c�XՎs\rz�X��0U����,�ə�+!�c�iN� 5v�2Ү�YU1����n�C7�F\$��\\T�\"!P*��sg��\\�H<>19\"��ER��ň���c���m��z�s�0���j	�=%dᚐ�yI��8e��l㙏ٝ!���d쉃�P�\\R���:�!��^�i]5�Yϩ��ch�,��)/�7���#q@�-D�ViE�m�Uv19aCi5\rhۻ�UD��	7\0��[{�k=E�J�G�l�p�ݷؘ��@�WG�����Ć��� H4����Ēz`w^,�6ox�����gT���D�Q-rk\rj���B!�(9�3�Z��pD��׌�{*�f�EZp%�@p~\nr��9UƤr���K�PQz�)�gݞ�́��2��s[Q�m��Q\\����%�7�D��t�tI*��dBU<��r�pU9�as���L	8Â\\�Vm�~;+?}��vn�f�k쫗����	k��-���+�N.���3�{'WMbn����O{��8@�k�_�5�3����*to��S=�B�i�f�;�\0��.�B�����!`�X�ZM0��R,[������'^����߶-�N��?3I>�0?8�}{f���?Z0����H��_��Z:t\\�|#{F����MQ��j��v|݆_UR���?���L�����ԵK��.P�Pэ74�X%p�0 �\$��b�P<��6�V9�>�M��/��pD�����n�\0.���s��F�-lR#gԮ���'z%�J80xʐ|���Ȭ\0ߥ�9m :h�/&�k�9��^���k�-n<=�'e�5�Z�0�@�v6�������n���L����-(���H�d���~�n1clV�l4횄��h�.��b���G���q,䨆R��*YK����/\0�E1�r0*��@]\r]�-q&2Eh��,!�^nE�&3�l��:���1n�0�'l^\\�#�ȯ�h0ƾp����ϥ� -�[�ލ�݂<Xq��Ċ�ِWP~���gF�G`����4�ؽ1�몺���[���!�����O�o:��x���ǌY��2�E�-QIg#b ���b����aD��!(<#k�	C���2\0�BY�>@Ef?g\r��;���#b+�\$^�*>�n @"; break;
		case "hu": $compressed = "B4�����e7���P�\\33\r�5	��d8NF0Q8�m�C|��e6kiL � 0��CT�\\\n Č'�LMBl4�fj�MRr2�X)\no9��D����:OF�\\�@\nFC1��l7AL5� �\n�L��Lt�n1�eJ��7)��F�)�\n!aOL5���x��L�sT��V�\r�*DAq2Q�Ǚ�d�u'c-L� 8�'cI�'���Χ!��!4Pd&�nM�J�6�A����p�<W>do6N����\n���\"a�}�c1�=]��\n*J�Un\\t�(;�1�(6B��5��x�73��7�J{z:H����(�X��CT���f	IC\r'|\"P�lBP���\"���=A\0�\r�(ڻ�AH�@�P�ݎb�0�c\n9�Ʉ|�8��Z;�,�O#���;�� X��Ф��D4���9�Ax^;�p��ǐl3��@^8KR��2��\r�cZ���`��\r#x��|����퍉()����5�Lk�'*����i ��/n���/��QUU��a�CRB��0\0�K\r�r���2h:6%��YTN5�P��S#�^V���ɲ�8�ž���c��m*i[X�-� �3#�R��:��P�ٿ���B0��cL<5�8Τ��+}.5[��CC�M��b�\r˝���)X��\r��5���Ch�7S&Ԡ�3�b�7Z���Cc����0��آ&K#����L���ʺK���<&�Cգ3[Sj��U(%j��➴����ˋ1{�BN%E�B�d�>�8�:и@�6ȴ������h�+��l�FѬNz��v�Y=����h\"(.#l���c>7sMj�s��<+#t�Gl[5�~ZP�\"\"(�\$�2d�b���(�-�8ʒ�-�����3QK5�x�3(Ro}��k�*\r�}ф�\$�c5��\r��U/ӣ�`3�+�A�YQ��q��@��\n�\"Y�E�EJ�P���I�:����wh�0��ș�BjM��8t�H�.N��7�H�2�P ����Ԃ�8�5�\$B�Q�\rO:%�*m�r3F����ю9�+!�,�4��L��\r% 2�����gM)�6���ߴ!�i�Ӻ�W	Ts��8hn!�>s�H��e�@�ф~e(��'�rJy��؄�ʁ\$8 I-F��R��9r*�d��\0J�@X'��`�JJ���DH��c�r&�%����\"s�/��%C4p\ri�2@�1�C�W��1��\0%R4eL1Ϙdɾ�VQ��t3���>�|�H \n (LX�2�ˑE2G�2>M��!R�&ǃblͩ�X���\$�L%\".D�a������W� � ��Wd�q~d��4����3J7-���b�w8!�4 ��A��x�i�?�\0��FY0��10J9�5qg�Yl\r��b�V�L�4>a�\"�I���>(D��G5JT�R��*G`J+J3��@t�	�I\"!�ӣ�vk��������J�,�@��(�jfs��\$��1����xS\n��@�X�dg\$9�P\\j�*� �C��V����':2ʳW\"�<�H�\rڙNi81Q�@�2�+�`\0�&�@X���ǲe]�� (\$�9\"�Z�i4��ɕ\\�\n�j�-��T-��ѻ�Ij��T�)�n*Z+�zB�PHf!�i\n���.��i��Ҝ��\r���\0����z/%\$P;��[����'j�X�؊\n\nq�bfS������N\0�)2Z��mMĶp��UpF;�Mv��1b̈́1)*\$�!���=W�b_�aC\$��MfM`/�L�����IE���F�\n��-��)�-(�\n\$����50\n�X��2�r:�Y�\\/�1��y��xk�YV��d��\0C�g���W-sM�кD�`�\r�\n#G�L�����U� ٛ�Q��=��GC��p���.��cD[[CK���<�����*@��@ ����=\n�+����g�4���m�]�c�D������\"��d�]ǹY���m�Ɠ!n�����p´�_�Nz͗r��Ap	��d��ż��*���o�þ�6�(����n�8.����y ��ⷸt�%/����s��7	��bq��\rQ*m��-��\r��ݬ|��S�9'�U b���:�����T:I2%0�9��D��\"/�l�O�4�������TY�L`=E�&��ڵ&�0�j�GFO��61m\\� �ޅU���C�xO���8�.�a1GN'\n��wx	Xl5���k��g��~Q^��Fcq�/j����A��ϫp�̂/+Y�U��Ű�[p^቉�'�B�3K�ڪa��*b�f�Q�a�r�C��\\�G7��`��R�������-�XG�����K���-+�^v�@IϺ�-��;妦k����R��'0D�����/ޱ���c�v����9+2jC��bV��5�8�oj&��2`�3��%�A�!Z�F�nL�nP�\0���UnJ<�l��-P]z��H�0j6Pn�� ��\0�p��S	�� ؘ�hHpM\n�%\"�z���+\ni&t��H��jd���3�Uaul[�\n\rd�&0�&t ��Z��/�%�\r#�!P�\r�8%�l^��A�A+�I6��H�+�\n�&M	�V&�&��~�O���̀��mBr�EM���l)0���hrW����|T�S�ZqqhF����{���'l)�#�O|���G�l;��q���`;��+@�N|�#��\"EBN2�>���3��+o&S�[�`F�e�)��KX`F`�gTU��\r�v\$o� �R\ro�,^k'\"�/1�E\\\n˲C�(�%�*��-:G��\n�S�>�ѩ�e'\${!E)&h�#&��|��=+�w�\"ֱ�4o&��7D:�rkҢ�d8�� ��+ �*mz*�g(�?�7��E�^�Q\0>�?�R5o�a�Q#�ƨr�P�\"���ƅ�q����2��r�0��3/R	r�@��%o�dX��1��YO:gNOS���3Ђ�-�3��	�AE:\r�V�oȰ�I��^�Z�\n����J�n�v\r��)H�~@�\n���ps�#7H^&�h~f�H���-��s�ps�/󢸓�'�R#�@\$BH\$J_��h^&/dv��\"�v�\0�EaB��� 0��+kH��:��\rM#�V�j	%�R�҈\"�(mn	�޶e\n)�x Nc�C�7�\0\\c�Ud>�S*�����N\\n�U�p��\r�J-�1ζ�N�v�6����Th+v�|\"�5For���%*8�(5cZ ����Gl�G\rF���b�<s�HtxcF�	���@��fn�Mfmf{%F5��A�h\nĐ�𾴞^�?�#��\"h�R�	CVU�uE���2-a�tp<t~+�-��cUQ��т*��\r�.�d+\"���\r��9�\$��wc:ۢ�l�� t\r��"; break;
		case "id": $compressed = "A7\"Ʉ�i7�BQp�� 9�����A8N�i��g:���@��e9�'1p(�e9�NRiD��0���I�*70#d�@%9����L�@t�A�P)l�`1ƃQ��p9��3||+6bU�t0�͒Ҝ��f)�Nf������S+Դ�o:�\r��@n7�#I��l2������:c����>㘺M��p*���4Sq�����7hA�]��l�7���c'������'�D�\$��H�4�U7�z��o9jNzn�Q�9��<���)�L���d�BjV:�p�	@ڜ��P�2\r�BP���� ��l���#c�1��t���V��KF�C��V9��@��4C(��C@�:�t�㼌(p�ܔ�@���z29��^)���1�@��Aj���|���ҒĠP�5�H��9@������J�5l��<�˂��t�4�ɐ�\n��ޢ!(ȓENh�7��{�%#��K�+��\$�1�B��x�M#T��#�؍���:����4B2B3�pp��v��O�8��n�Z*΃�����\n\\�%o�r5'#:�2�h�&���l�rQ�6�>��P�.	(�(��P�9��ag�TK�6�	(�5��Z\\:�8>�a^�͢�(�3�r\$�oE	p�o���#wP�}U	��\"@P�]�B\"�ӱ�@@��h����l2�\rܽ��c]C��\0Ε\"V�Gv J�Nو�3TIrd�-42��Uf���Jd�\r�0̴�ih�uᏈ�7�0[t\$0�?�Օ�7��@�Ncʄ��I#�Cn2��R��Z�va�d���[�雪ބ|��F���Bq��q� �r,�\$�OL�(\r�xȲի,�w��3̓2�QXĽF���e�(������	�r��I�|r2Ѭo�G��!H�0�\$B̚9I򎁡h��9����f����6\0�Ԛ�Yl!������\" E~lX�{1J(��@���h1�y15#.�	9�%���6�hI�zD	Q���2�a1�)���:�A�[4`��_��-�������H\n������()\0��Ĳ�KBeIa���\$�̹��M��BniP�B\r�d;ƲZW	�*\0���BJ�\"e8��h��n:�9����d��t|M!َjō9���\nC\naH#E��I(K\r&P6��h��b��e�:�\"Jfzl����T�S�Z�5R�*-��OI�9|�H93CHk(𖄒LA;RqōviQ�qW�c�+]3��%1��57ɹ3PQl\0�T[�Ȣ�rhLOH 	i�n��JP����䞀�rʁ,A�3�A3`�H0�fM�<�JBR�y{���A� a*E3���r#h�r�r {�b�\$!�D*��X�*�	�8P�T�*��\0�B`E�@('C�P�T�Ⱥf��`���ݩ��H|�Xѡ��2�M\$�Mi�:�Y������K���9c.N�]sM\n퉲���Ӊ�`v\"B�e�0AX�����ղd���(��\\:\rE�R3զ�\r��x{�M5�^��[,���zW1�ո��f#\$k:'N� (aM�RfX2�v��M�MPj��.��HE�\"%�6���@�z����xP��>hD1���wJ2	��-�,\n���*���d�F�PR6�!B�[cP#N(\$���Aa N%�f�9�\r�a\r!���:�N%���Z�����(Dh�&�ʿ�h�/�x2؇X�_ʑn�УdN��(��bs)��H�G�e\0�����oB\$l�r\"x�F9�y�~H��3&��0��cr{G��R���������|�q�	i���~\0:G0�]	��C�\r-L���^�A~.��1���\"�����X���Ԏ\\�еIj�P��U���q�K�K�(jS�`N�����V�Ꞷrؚ�4��4Kj�d�(��*���Pajgx\"p��PP&EB\r-E����z-[Ef�=յ�)j���e�\0ӱ�FVS��y�kh��\0(5�p��������{l��l1pޤ��ɞ݈-z�|K���ֿ0NW_o}�X�~5����{�Zq���O�Xa�V?�w��ޞ'��;n�|��qݍ�����{�l�H۔�\0C7u	�{x\n	�N�܎��A�\0j�u�b��*z��&=b\r��צ���;ӫPt�m��Z:��s��t+6��d��6\"sX-�(�`���^m9�&�>;�y����Ɯ����I�|y���t\nG�������t�;u�0e�� ��\$U�з���6���Gc*0��W_�-\\����/�pA龨��ϥ�6/N������>�X-�cQ]�y����2vG�o�<O�	0e��^��{}-�^W�����\\�︅K�����Q� QoX\\��>�F�o������������;�\"��hD�T��Σ(i��ůփ/,Ќ��%�/�Ub�d�Ӑd|\rR�kBN�~d�\r�V���\"�ʤEjV�Dn���EBZ�P��ȅ'\n���Zr�К#��&�8�%�Bi�ް��	�����%�U\0C-8,ö>�2k� p�x�b��,7\"@`%�)��\r�BKdr��(c�:B�n��4����o�6r�v��ق�,��ʯ׎�e���1P�n�&&��1Un��SP\$ـ��A`��Qr8O@�@X잠�H���y��[e����J�'M�\", b^�E���&C��j��l/�\0V��\"/��\"b^@�0n�S��,�����\$�����N\$�%��\\5��m�\r���(4,�(f�d�� I j���j���O�"; break;
		case "it": $compressed = "S4�Χ#x�%���(�a9@L&�)��o����l2�\r��p�\"u9��1qp(�a��b�㙦I!6�NsY�f7��Xj�\0��B��c���H 2�NgC,�Z0��cA��n8���S|\\o���&��N�&(܂ZM7�\r1��I�b2�M��s:�\$Ɠ9�ZY7�D�	�C#\"'j	�� ���!���4Nz��S����fʠ 1�����c0���x-T�E%�� �����\n\"�&V��3��Nw⩸�#;�pPC��S2�u�,�˳T�AE	���h2��k��� ���v��I���	�zԒ�s� P�2\r�[����F:!��C��1��p@�4�����V4212�����`4C(��C@�:�t�㼔0�,�8^���h���\r�C�7�Brݤ��^0��h��7���=E\r35�h�7�\n��\0����/K�`�*s��Mb�6\r����6���0��\r�r��\0�<��M9OT\n�7��\"�\n�L?S����\0004+X��C{�#��6C`��\nt�\n�/�3c�0��3Ǭm��l���cp�a�B|l�K�R��P��\n��s3�,��*5��YTe��#�X_C\"0)�\"`0�L+����\r��@Q�1ݯP��8����I��6ΰ�H��K��9�V.2�R�c���!NAf/�#T֤*0@�*`Ħ�Z&�2��j�o3��]�x�\"/��ۭ�Ut�N��#��z)������2H�B7��3���+	V\r��<��DFÌ��AT�c�p�o���eG\n���aJZ�%K�����{�7).�'czZ*2��W0�Mj7�'�0Q�B��]lt�Ǳ��!Ȳ<�%ɽ��9JR���L�t�ys�a3M4J��rl0���I�b�R6�*�l��ƴ�8\r1��G1�{ HR\$�\$IC����;�y/9�4��R�&�Y=@|QM[gF\ntГ��pP�\rH=J�ИHN;@���)0a�!.d|��b��j'o5��8]ɂu�QN���P�'n�8G���h5���nW)�-�E��bI		G��P�a�J(.��x�V��A\0P	A�[�\0((����Ȯ�	-j�2�4LhX)�\$yN��Vj�r!Q��;�����N�8���F�:�G�4է���i%�)|8 f��-5,P!U؇!ģ7\n��p��(C\naH#I@A%���\nA����`�L[\r+��*�	�!}d��.��^D�]J�� RHXy2\$�N���\rD�G�mx��]�\0B򌒆5Rp�I�J�y����FS��\r\$�(�&� rn\"�HF겛� �#ԆM�z7��t����\nm�̒�5R�Q:�1���Sf0 �R6/����C��w�p�{cѩ9�@�\$�ʌ+�'��@B�D!P\"�ڜ(L�P��h��R����9DӔ��b�]��n��aB\n�\r+������C������@PR&J@�T�\"̿N��Xy�FΛ̚����d�c���`�\0�nL�t��b!@���5�ȳc�\nF���h��j��	9(�_M	�\"��,��Z\\{�K�*8�kӺ�lҕt�RA⅝�)��������sTZ�/ḽ������)�*|�|xs��H����v��L@�I`�y��0��\\��-84���`����6+�L��2b���c�e��0���[��T Aa N�\"g���1�H��!�fx�a�,��x  �t��E�cUn��:�5R�!�o�<%��G�e�0��\0��@��C��o.�\\���b'p����vbhڱ_�`��@�G��/Ty�?h@S��t�?,�m\nI]_tA�!7͔�]�F�Df��#1jZ}BE�A����Z��~��ç�p�����ŕ��!��Xot�d�{��y�\0�3�-�y/��\n�(+�aX��O�\r])�\n��r}���D���ܖwo�v�p�!,U�;,Y\r�\\�>��	v��M�zI�O����(�f���>V�T�C�k%vg�}Jw��`��w[��J�ey����oa���'N���k��IT��\n��i���a�ld�GSyt@HH�#����tsp�:/ME��Ao �P�PRĨ�u����y0-y���֐\n�\r1���b�a�9̪�B�Lf��{tI���7zo�P�o�藻� �==�xh?F��\\��\"��r4TΦi��H�}\"�aTt���s�F��D�D�<s\"�ȩ5���sv[)�����\$��v��w����}����)���C�lx/�>�y\n�iZ�0�f�G5�^g��>3b��q>���\"�+|<.���c�o-��C/�!�k��+��L@������#��v��&�Ď��������p��%�\"�O꙯�bG��p��Z� �D��O��f:Yp>��5h�ɆO��4\0�A�Y�/��\n�&B��px��.���fc���Ƨ�[�Nۯ�\n��K��P��\nЦ���	t]��ꎚ�6�c\n�e\"Z�G�F�#��͸ִ̰DJ�P��\"��Tg\"��-�\0})�~�#�W�\\�kn=	B��&W,�)C1<�&�k��̋��(Hc�\r�V����8��B�I[� U.��@�%	a �\n���ph�r/G`%��1�ħR̍-(b&H��(]�p��fL��\"N0�T�,�e�~;#�q����Z߬1nZ�i�*b0X\$���ޥ��f%��Þ>�/�&B�##��\$1�_H#\n4��,b�΂] ���(����� �\$3.�g�!�3\"\"\"r;�^�@5c(�\"�\"��vB��~o�Q�����Z��bD&�\r&���E��E�N6�\n�(���\$H�G\r\"�k+�JD@�j庢�dN\$-��+��\"��L8�cg!�T�\0ޯG����F-OЏr>[�\"] ��e2PD\$^\nq\$T���	\0�@�	�t\n`�"; break;
		case "ja": $compressed = "�W'�\nc���/�ɘ2-޼O���ᙘ@�S��N4UƂP�ԑ�\\}%QGq�B\r[^G0e<	�&��0S�8�r�&����#A�PKY}t ��Q�\$��I�+ܪ�Õ8��B0��<���h5\r��S�R�9P�:�aKI �T\n\n>��Ygn4\n�T:Shi�1zR��xL&���g`�ɼ� 4N�Q�� 8�'cI��g2��My��d0�5�CA�tt0����S�~���9�����s��=��O�\\�������t\\��m��t�T��BЪOsW��:QP\n�p���p@2�C��99����E�8�i�\\��A\\t�/�>�B�� �Ёlr�j�H���8W���A�#	�ʨ��E��Y���p����\$�r?(�� ��h�7A\0�7�-h�:�|8Ar��1�m��)��\0�+8.H�9�����4�a7�c�2�\0y5���3��:����x�G���)�t3��(����9�xD���l�Jc46�#H�7�x�A�k��NE�\$Ўh�KJ	se���*�WX�E�t�)�M��1\\r���DDb��9\r�@��D���K�\$���E�8��w�vץ�J�I�.Q ��@>gI\\�S�t���J�\0S\$CEi�R�9hQ9��vs�}�^�7�2Fڌ��D�����:�K��6J��1*��d��NB0�6\r�ے�K��7B��&#��='&X�,E��3��Pt!	p�-V)Ic�7�З�\$=h�j?�&;�����y_'�AR��q�8N�7��AF�ģ���f�D��o��F�Ј��Xf�6��*!��:��u}H�<8Ct�W�na͚���{�f�Js,r8Un�ڨH*�A����?�w�㜷�6��H�MTT3�e)ZǒbRAD�P�7��`�<��l:̓0ͣ�`�ҘsO�u��R� � �t� �N(`��\$�PG�j�\n���T��g\rɥ(@��C�\r!�*��P��\"�Q\n)F(� ��2R�eM�wZr�@�*�F�U�\rB��z ��Y��O�F^��1�q��b�š�(�@��(�fa57�(@�C�yOh<\0Ҡ� .O�(U�TZ�Q��H�4����SJe�)�o� I\r����4\"@>u��K'E�o\$�a\rj�.���J����(�I1>'�JAλ]��@�86k@U��94�@�d�P~K.���r]	�8�����NG݆�X���<Ip�Cc�'�,P�Qx-G0� B\r�:a�If'�@\$�9�d��tOB\"\n�&s؏ʲV�༬%��,˙{>MCU�P1�Fn�y�6f�ۆUҘ�t7G-&&�ý&9��F�aZ(�3ԋ�5|��~��bkju2��q@nA;�>�C��a�N�Ρ�9Ûӌ0�T�L��5���!�0����F��|��9D3,�q�Ё��K��+���¤r��_Ag��'�\0��< ��qI+�a�-��45�O��RI�(�!	#�x��ԼH�4�,_\0d�t�.��>qM҂!�ܥ�̂�h ��RT���p���N���xS\n��!)�PI��&��bHd[sWU��.�\0��������c�T\n�h,mگ%��˺����i��?�\$��=V\rH&\0�I�Ma*��Hi�i�[�mC��A��R	a� �[x.�S��Z9E�&	�8P�T�+�\0�B`E�K��\"��#���xϏ���h�c�z�Q����B�%��b��+r>�2-F�m���@{��b,����Ep�9&t,5��j�^��3�ٟ<7f���T%��&!s�}�0�ĖQ��q-�0�^eEu�g�qZr��	�(���y��^��^�*%Q��+\"l�XX�����rd�zěy˨�g�g�9������\0���;�T����ʢ�k\r\n�&�������0Ay_V�kZL��Fȅ��MyM�ee�O�V#{��R�<��7-ix������E�ū�2o[h��k0����&�Ƚ�̘�7���HB��v�4\"�1&���@��@ �h#o��k\rr\\K�(�i`�H�g���*I��\0�u#7�:wF������鍁���6����� D�����H�_T.{��0Y���=iu��as �D�w�0�s(;�{QJ���4?E�g�ʵ<��dA�\$ ��qBN�-nșب��t��,��A\\2�+��}m�(� U��1��f�\"1�Ρ����d��Q��k �\"��� E��R��,^�YC�[��#����Z�� �ز�Z�qb�BG]��\"+Qk-�bY'vG���\$o��ib2�0����\0�/�>���r��m��\0n�r%�g���(ɏ�����L�\n`���^�VF\"���#����*p�M@��%?)^I�M\r���mP5B\"ݭ�[G�^fr��g���B�E\"0b�\\�����\nLּ��s��E-�&��mb�O�Ϋ�����-[�1\r�Ч0���\"��ق�*��o.O���P���찠ZP���Pn��p\$��\0�8����	�Kη1Sb>e�'��>�/>�E�]��\n�h�\"E�*0��@,����F�p����m\n��0b3��p���+����1�0���7�X��X�H؍o��m�g+,ʮ\"m�܎�G��XQ?�_��X�]�X���1@�2��\r11\n�F��f�a�Wb���t2����6?\"\0�0�|0�Vk��c��e��.K�7&�+M'�8b\$���ߍ��v��8�m�F�(�[rs/+D,��#��ѡ�{N�N qC�2��15\"r�-�\n�!P+../�#E�/���x� '���j��l��3B�M�#��1�e*�	ޛ��&҆���pG[A�a0=ap''�)!�ȳE��j&IH��Ŋ�ne�c\$RH/��q��n\$�c+��3i�7&`�y\$��m%�\0000�g�\r�V�\0�`�uDM`�x�	8\r�d��Nc�� \r��K����j\n���pu�R�HF9��gn3a#�g\$�� ����+��F9�\\��G*c�1����&֎6q�%B�\"2nڽ���4&�,B^���W#�D�EB��=�9Dm<'�9�/O�� �<h�<�b�m'�~Ɯ8R�@�H�6�v��Ip?Iƪ#�)/R�	J�+\"0'\"`�t�t5#V�%4u@����n�HtYΕ ��	:8ݴ>^o�'0U\r%qp��u.�лC4 ��@�L`���G\0aK��e�\rH2��(iZ�Z.��I�]ņ��F��n�#�h\"�J�g��t�ܠ��@�6C�m�>'�!��c*f�\0x��ҍU�!"; break;
		case "ka": $compressed = "�A� 	n\0��%`	�j���ᙘ@s@��1��#�		�(�0��\0���T0��V�����4��]A�����C%�P�jX�P����\n9��=A�`�h�Js!O���­A�G�	�,�I#�� 	itA�g�\0P�b2��a��s@U\\)�]�'V@�h]�'�I��.%��ڳ��:Bă�� �UM@T��z�ƕ�duS�*w����y��yO��d�(��OƐNo�<�h�t�2>\\r��֥����;�7HP<�6�%�I��m�s�wi\\�:���\r�P���3ZH>���{�A��:���P\"9 jt�>���M�s��<�.ΚJ��l��*-:��%/�(��i�Z��d��b���M���R�#���3\n�jsZ�=1�hA�M܇�������\$�ˬ:N���[�pD�6D̓��j���*SS�.��# �4��(��\rI0)���(��'r�<�J�3Z�\$��Ԣ,�\0x0�@�2���D4���9�Ax^;ցp�AP�0\\7�C8^2��x�0�c��^�xD���\n�?)��^0��\n�=tj�����ǮT����/\r1R�?�-9D��d;*Űe��]��sy5ף��O��7Q�+v#�v�8����\"J˨�z�>_Ҕ�'1L@A�32�0�2���	;[,�*U�;��������J�]s,��C��F����q#dW<B�Ĉ�y�)m]A\0������/�OK���N�(\rċ+�(�y;o���BJ�y�=��j�6nH�.�+3�Ш�r��}�������D�)��\"ފrwh,��{�+����*�|)�\"e�'z��(��k��w�3z�����o*cN�s��qe��B�z�wSk-'v�'��ގ����NEü���)3%)m�-�\$dI�-�=�>|Ŝf�zb��?���;w��}T堿亼9�i,\"UK2�+����NP�1f.�@ӱ��t�ADaG�3�v��n~��7�P����+-@��w��i9K+�����I-fM��%2�I��eo����G�3�N0�:fzsX�H*�������Iɩ*Dr��Zq��T@&0ޥ�J�2K!`��>BN��y^bD\0�r̢�;�]O���Pr�we��N�ω c�)�t�J�PjC�H�cʪ�@2\n8E4Q�d�(1�?���ܡGTj�S��V�Uz�Vj�[ȵt����7���L�YK6'�h�Q�gM�Ij##��r��m5���N�O�l�̏F��&a9J�	4����b�geKn�|�J|Ap�G�'�RA��1u�����S*�T�r�VJ�;�eq#ڽW�2�����X+,9���Ȉ�u��b�YYvs`��S��V{9w�5�?c���6(�9F|u��^(�h� ��	4qf�g9W1ʫ��D����&TWM�q�9���ʪ[o����Hw2׼�k%ڛ:�\$��	����F>ɅR������ElM>���@��2�i��%� �A_&�2/y��j|ZnI�xV���bi&�Ш��Nu�'���kt%/�g�u֩�t�h��t�������k�Sd^��G6XM܄�E�y�@�NP{�i��ƴW�kK�%6e��L��RΎ߲�&##J�4��=Rĭr8�0����'9�9����\n������\"�bK��(;�-�3BIi,˩h�7͇�9��KE|�܋�1v�H(��0�i0��W��_t��r�����s�6#��\\zc�Z��\"c:4��(�*kCRud�V85��Hg8��Tsv��A�QT�2�5l���gU,����ZfC8�X�/���m�0��n�96@_٧�\r���w�Ii��#�fǾ*b0T\n��3�u/JMg��]�R	�^��*><0e�hsx��,�JOi  ��_(\r��g<��߳�n9��6�P�2N�)�^��h���nl��!�7�]�g/X�^m�Bl���oE�#l���k��;G��m��XK�#�u9d�3�OE��q���R^f��>��Y%��u��̳=r0�������tKm��RA�F0N���O�\0����7au�7����'�]A�'2#��W+�l1���6-!����9z�3�l�F�g(i.�a��[e���]�*�z�(w�v9����A��{r��G���{��H�y�2{�v�d�\$�5�'3Cq5�tDE�����#���l���{}�gKj-t�zз�_bJ\nTQܟ�<��];[=�`RP��?����{������\n.��+����z�؄Y�t�\n��u>��=J։�B�T!\$:8�{&^���A�Y���yG=8�s�����X�.#�\"y|��ͳ�jM�l�\nE�J�A������(#�\r�n{��J)��\\f�Y�å���%��d^�\r}��C���N0���^��F�w��o&�>'��ȎsM�ϪL@�**�ܮ�;N|���,�Ӱ&�nn�G�Ч\\���φ����\"���`/@GN�+7�2����@����L��C(����\\Ԭ�vВ�/1	k���H��x(K1�>�L\0���|�̦nJ2�I�u��4��i���Z���5��O-,m\r�s�Z�f���\r�ւ'�sz�p�xJ�\\9�\0�PZ����>��h�f昏���)/8u�t�n\n]�K�§��\",ާ��^��=�h;�	0�멖(E�mj������R{���N��Φk�O�oꀧΔc��\"�\n�ק10|�`x�30��_����\$����ͧ�d�O����/����oq�/�ѳ\0��,�����ޢ���r� ��w�^h���ε��fM�����n���J�<H��)RFEQ��Op��,�\$M�b����Qj4J�AJV+t�\0007��k��hm�'��N&)���Ҏ'���\"��hi���py)qBAR��d���r�����xp���l�'\r>}��7���Z��R��V\$�Z�Һi��*�|r��q*څ�	�OT�΂�zd��l���+&��0�N�}rJ)�� .|�sE%��}cN�O\"�H�hg���~�o56�p��4���O7k�7����\\�Q ��	7��'='M�9��:�Cvߤ:�� ���V{�f�h���u'�mD�P	�r��.��0�����W	m]��!��>2%8�%�!�ș�92@�\$��\"\$�:S72d(R�;��6��d@��A�<p�2#�B�~y'�<�?C�!	.�CQ�B����io/+Q���wn�*��@Q]9q�İ�AR/5��5Ө4�WH��;TF^n�3GJ�EBq�Fb��2dS�)ӱkVHC��52�M3����+aBQ�&2wL��M�EM1�I���QJ�!f{@��A�\\N��0�@DY�y�Q�78#���X�:4������І)�(b5=ϻ2ї9E�#&���|�G�C�F��욑|\\�Bh^\r�V��N?�A�<mSS0gRQ-��aX�J�g u��\n���`p�U\${/K(����q!1r��O,�OFȨo,\$r��{K�>t�ێ�P^�P��H�[աc��S��4oS�YK2y;�ff,�V�|L%BY�J-��kS ^a�k�R��O�0pp�ϐLv0�\$�@)�~���H���+&5=�|E�΄�J�~�@�P�Mref�,�쏪`�&	�,���H�L��U�f�o55)M��\\D�?X����L�g��)R�i�)L�MSU6��F�+j��d�r�f�k,�Sg�cK�]�_(�3�)G���'����NP�i�;��|�Z'm�(��n�:��(�{�gl5b���'=^�7Msq6p�D�Wu}2Q=h-�(�>�4ݵ{�he���fDL��u\0����\r* �qr�\0dTZ�k܂�DQQ��U�.\$�6j�f���4������"; break;
		case "ko": $compressed = "�E��dH�ڕL@����؊Z��h�R�?	E�30�شD���c�:��!#�t+�B�u�Ӑd��<�LJ����N\$�H��iBvr�Z��2X�\\,S�\n�%�ɖ��\n�؞VA�*zc�*��D���0��cA��n8��k�#�-^O\"\$��S�6�u��\$-ah�\\%+S�L�Av���:G\n�^�в(&Mؗ��-V�*v���ֲ\$�O-F�+N�R�6u-��t�Q����}K�槔�'Rπ�����l�q#Ԩ�9�N���Ӥ#�d��`��'cI�ϟV�	�*[6���a�M P�7\rcp�;��\0�9Cx䠝m�vBZ�!�\"L�:��dB@0R��\r�M/d!���DA�L1p�t���4�5�����E�6N�ga0@�E�P'a8^%ɜ�\"��X�2\r���x�9�P�c��8BS8�1�s����\0�0#��1#���H�4\r����;�C X���9�0z\r��8a�^��\\0˒�'	�x�7�H�EѡxD��l%?��4\$6�#H�7�x�B��y��<BiN�H�E��I�B����j/E�h�*LI\0��cټŝ�Y](9Zu�EK�S���Ir[��P###�X6��y\$��E0�PBDqa�G�(�LN�π�J�#��3���:��%�g�D�Pv'+:���c�A�+�TT&8�J�eX���?N)+t�ec6OE�JL��>� H #c�`A=Cd�9�c���\"c�U%�s�jnc��X�4}\r�\$T=s].�v�E!�S�m�tû���=ok߸G{�3�+۰Al��ݰ�8������~dqt7��]l��|�w��B�|�Ȕ�P���e���H�=�ʜ�	A�w�?���䌣��U�u��\niQcO��\\X�ڏtaz�،�S�\"���V>90���JFJ̐�5nT�#,��6A�B����\r��PܚS�r�x��ϨsQ��9@@��	�h�\$��0e��D����d�e��-H�5FYk�@�؃ZdWA��3��A�<1� ���\nMJ�u2���T!�Q�P܃�r�UJ�0�`试`\"�u^�e����3b���V:��\$K�\"zrJ� \$(�Bt��DZ�Z�\\&�ןr�L	�\0ҥ .R\nIJ)e0���T\n�RD%N�J�UO9�Fd�Hm\rx6�����!�A�� �\rj�3�\0�ti����C�M�H	r�%R��&����PП�|_W�\0004�ƴ���P�V� ��:h�%W�����\rP*\r��t�eh rBW�D���D'H���k�H\n��PUI���v�0:�~+e|�B<Y�A�j�+�8��A��5��XlA�2;���t�At�4��.*��J����l���dN��D�6�T�\r��C(��\$Z�\r�1��C:��z���S�=��H0�F �f�t'�l4�G�j.��cZD)%-vv�ܚ4GLJ�2�R�aFDț�u�G���Ɛ�w�\n	H)v\\u��.)����X���:T*`���3���-��dR�Œ4���m�Y/��r~���D��xS\n�����`�O0�-b�׈{5k��C4��F��`�/��u2 ���w4PP\"�im=��V�W`@�)(oȦ`�/=s� ӊ�VKIy1�DK��r���E��Ĝ�@f��'x���&E���E���xϚ�q�ߞa*a��Ntu�v�G�p�E��S��`Ls,(�!���/��<�tF8�2aD�2%���|�NY��/�\n�u1�svhu�W��Q�n�\$v#�~��3�4�,�߬��d�CZ��3z���ջ����~Z8�\r�y�(Q��FA��J/���EBhX�I�����T%�􅿄��h9Z�v2׬������Pl��\")����,\0�d��l<��䅐��6��L���p�m����ע&���ؒy���bD �x�\\.��U�4�=�]��\"���-���Hvp7��bN?IE)�U流q���P�n�D^�b�v����E�7�%�\"��#{�_�*@��@ ��<4�4�-��Mi��Q~�-E����L0@�=�́�Q���6t��x��]qx\n�d갱�/����^&�W�%;i4;���X�h���	����j�ת���Y�����i�Ү�~�ш��\$����aD�sD����U\n�D����t�KZ��@|0R	r�t9o�Un����x��h�ٜ	�fLٝe+�z����|�^K�̓�,H��c7<���fNu45�蜭�c̏���k�sg�r���`��hJ���4�s���TE�P4�\$|����=�\$v�\"�#F8a7H�*�KN��*���ɡa-֨��po��ʊ}~�5�R5�:F��ghΏ����X���ю�ɋ��A|3h�`�PwkH��Z�p�0�C��{�n3n6/NF䢼�0��'��-�� �J�������,N�,V ��΃��pJ��\r�\r\np�\n���0�\r�\np����c���4<ס%p�)�&\"�8��v'�DD8/�̚/x���M,�� =\"<f��p�q�&+��,!*��<�p��\0]M��� P�p\r��F��1-�PI������K�	gY��5����e���ѭpJ�0��؍.P�Lyl�q���<3��BZ�,�n�d\$0�/�\"clmåNH��Wk�0� ���~��l��\n�T�M�^�8��\"��\"�GG\\�K	#r@t`\rT'.>DBD7�����M���%�&Ih��l��2�2%\n�z�r9%�S&���!G��5\\Ef�&0�'hҙdTbRs!�pΩ�*CO��2	���� P�rㆱ���l-�,�nem��L� ��J��\nK.=�B?b=H�-��Ů1!j�b>���g50+0�������%O��0Jh(\r�V�\n�\rg�A��d&FO�RV��\r ̀�( ����\r��L�ȃ@�\n���px��5���������6��<#%1O�u/�YmƲ,4�\r�pge���;D;��2p�s����g��#_<A\"���)�i+�\0�kXn�o�\$�%2�F����t���#<3(�+��Ȋ;@Nf��g&8d��f�p��k�<�\0@�v#Ɍb:6��IA\\!�QE�:s+��E/��0k2���L�n���Vy@�����#F�#Q�IS�tQ.�G&Ff��/\0p`ϧ&���fC�����`�0�d?2��&�Qt�Q�DmT��raj[0r�gJETF�/ft�ly\n!\nMf6F))�Fi�n\\�A�k�)a00F�e�<��!a`F\n&"; break;
		case "lt": $compressed = "T4��FH�%���(�e8NǓY�@�W�̦á�@f�\r��Q4�k9�M�a���Ō��!�^-	Nd)!Ba����S9�lt:��F �0��cA��n8��Ui0���#I��n�P!�D�@l2����Kg\$)L�=&:\nb+�u����l�F0j���o:�\r#(��8Yƛ���/:E����@t4M���HI��'S9���P춛h��b&Nq���|�J��PV�u��o���^<k4�9`��\$�g,�#H(�,1XI�3&�U7��sp��r9X�:9�V�>��B��94-\n���c`�8�	�_\r�\")#j�H��B�Ȕ�C����\nB;%�2�\r1+��-B�6��@���l�4c��:�1��K�\"�c\"�l�����0��\0�;�c X������D4���9�Ax^;�p��#�\\���z��#��.��t\r�*���V���px�!�H �������\nP���R��.b�c����k�x� �2T�=T�.�6�͜�kP���8Ά��\$:�B#�b��*	e��K��;�@�8.�j>��|4�@��ЄH�1�����*@:��bX:��U)K/�4L5�q�ކ�#;�3�ъ�\$���*��c��9B�4��*W	��RT��h�5�\"bT�B�ʔ\\��̆��*9�hm�6\r�[Zʎc�7;������%��4�c��.��f�<�B���t��C����8h�r4?�اQa�&����Ž\r�v<>K��;�\r,�;!�H�!AP��o�K��A�j����3���n�K݂'_(��5��r��o�ћ�a:�>���7%4h�d�B�)�J��r�t��A�^�׼�5����.�J��R9�1�ʍ�0�6G4��D�:�*\r�V7:P:��H�x-�z��K����,��fGu�2��R�	U\"^����-���bRp���u�L���o�r����t��SHGI��&D̚RlM��;�\$�P��xOA���У!`\"й����Oٟo\0��=B��	z�尔��@� 	�4�4�+�FIQR:O��9,`���/Q&5��JkM��8�8+\nC�yOq]Ӻ�b���p.���B��B4�x��t����.!��D8D]�k,�Ӱ�<�!�#��eR40	M�!�+  zX��33~ß*~|�ԑ>�6�\r;`!��\r�^�`\r����Ա�i\r!�^��G��q\"�2DB*P�P	A8�.GV\n������Jc��=��4g\n!zE����.�٠o�l�<����/�%�B�We�Tl�?�DRC�>��C�����~K�VM���8�OK*Ήpe\$�<���P\0C\naH#G�4ZX L�Ħp]E�]#�)��j�H d �@�o�\"ɣ\$����ZK˃HBe�h6ӆ���C�a��h��I�5>'dʓ�^B�ɐGm!\$D��1�5G8�C�G��m�p�P���Q�&����H�{6�'�0�O�dFll\\_zmdmB\r,��\$P�zR�a�Ւ�(��YU!_����c���Y�sf:�3��h�sI����\0�ψ��U��`�5)��oi\r�\$@�^Hb>漮7�A)'x@*D���t-Ս��sJ�ȼˬ)�j�0�ʭ�W��8�~o�sxl���VC�r�,�\"Ȭ&��;'n�1�7\$�}�Eqj`�4G�?��\"��K9��ԙ�Y��[y\$mK��^DKC.P)�}e��VŪ᱆��G�^,qء\\\" ޒ�n�	\n����lh'�\\K�@A�t�dG�sV`��:N�20������B��w���˽~�PL�jݸ�����oo�\0	�^��z��(Jiu��T��W�����9����&����g�t�\0��(	d�@�\0� �ԕ��n� ��S�gH�;g���<��	�,�yS\n�jp�y�2�\$����(P]fJ)�܌�%�_�\0W3��qYq�8U�\r�J;a*@��@ ��\r��ڐ����-ܗ��К s)K�f�VM(A^p���8Ng��3dt��3B5~>�zQ��\nu`\$*�V���)��Ӯ@�Aw\$��\\�q���I���cq]�*X�+y4|���� ��t�O�yO7�+3��{�_�\\3�����9UF�9Ց�C�ّ1:a��=K�O��#�K��P�d�ɺ���,�0�Wp�Ά&%��� V�dx��ŀMg#�}��N��0*��F���-�Z�N�,�/g������Ta������zOz۞�,�_/=o\"t�;r�-0�bY�YD�{���Z�_k\$zS>^{�ؼ�_���z�����b\"Y��/�f5�I�l�;|p���%�p�eŃ�w��F�g�� /���6�bP�\"��:5�8%8\r%����[ @T&�RƯ�����+2q��#�\\T�L�m�\\@/��P8�a.����W��\0���&�����\$������	,0t�%K�^��7}\0�[0�?-��Q��욐��p�ł%�r�B\"�e����<j�JQ�=\r\rlxp�\r��p�d��Rxi�7�:հ�T\"�i,�:�\"<\\�\"#�z#��}�^�j�g�#`�x�����=����DӮ�7��C@���(��b���R�K���'σ����,.r�-d�킪�V�@���ϑ\r�Ұ��M�Q�U�P��^��z���c�0-�����/pۢ)P�������RUn�#\r�����DNj�X �<��R�dZi��=Џ\n���і��	1���\n�w Ѡo�Hkb����c�\"\0�R��!P�TÙm�|��\"�;�2��#��8�o���׍�P��R`@p���rm����,8��ؑ�#ҁ'M��i��;X@�)N�����ABdj� �Ғ�*�\"H��ҹ��-R\n g��0�,>TB�Y�3\$�d(%� r���!M<���#�X���8~�*6q]!�D���XȘa��	��0�Q�_P��1s\"0��,>\r312�61n�e*\r�V�\0�`�tC��E�2��P�j��;\"z�@��*:~ �\n���ps�0�@�\$�,��^�l)�!3��#Ӕ��.~�s��`�#�[İr�ppϥ\0@�B��&\"\"�v�8�Rb�,b�/dp8�@Uc�\0B�3Cڲ��96��\n٪��`޷E\n��Jd48�B%��\"O�|�FXs���^�3�n:��k�h!B�B����)\0��R��?.�*�\$J�4UF/� LBƀ�s\$z2d���tD dm\0�`�/�:E[l\naUqF�1f�e�@��hd	'��\\��T���� ��Db^�4R�nҀ���\r�	�G6=��-�N�'�b�Gl&Ɩ\n�ME\rd>�]:\"�KeJlv\$CQ<��\r�i=͒>��,��%\"��@!� ٣\n2)��O8�"; break;
		case "ms": $compressed = "A7\"���t4��BQp�� 9���S	�@n0�Mb4d� 3�d&�p(�=G#�i��s4�N����n3����0r5����h	Nd))W�F��SQ��%���h5\r��Q��s7�Pca�T4� f�\$RH\n*���(1��A7[�0!��i9�`J��Xe6��鱤@k2�!�)��Bɝ/���Bk4���C%�A�4�Js.g��@��	�œ��oF�6�sB�������e9NyCJ|y�`J#h(�G�uH�>�T�k7������r�f�\0����6����3���3��P���j0؊;I�Π���::�`ޜ�+�	B��6�A�P�2\r�K��\r�(拍賔8z,0�cL�'\nu/C���H�4\r�^� Ø����`@ c@�2���D4���9�Ax^;ˁr��΀\\���z|����\$��\r�|vԌ�zZ�-!�^0��(�4��D�h*�� ���KÇ\"Pɽ��\rb�	.zh��P�0�MRp���#\n<��MKS�舖7��蔟1��0\"Z|����7�Bu\0��22�PK�#8	���\$RzC0\"@�'ibn0��j0�:L�\\��\$(��ڮ����^�	-��ݿ�p�R��Ov)�\"`ߨ R`�0+�(��w�Rj@��%��)c���ȉ�����4����zţ���~[��MJ���V!��� \"YՒ��@�1\r�ڔ=�w�iQ>����Þ7&K��:�'V�޷��]�<)5w��٪~��-n�0�Pٮ��\n7I��3�l���Q����*n�#o��1�YU�	�NR6�')\nG%&\n0A�/V*D�(��2��s}�s�P0���\$�S���5��`(�H����ARW\$ͯY@74HܘI҄�*J�ĵ.���0�\$�7�\"Ҋ�+L�8'�#���S�e2�z��@���X|�2�2�-���L���B?`��)G�rKI�=(�4����[K�}�&0�S;Uj�e�&���0�:5@�9�4j�3�T`��\r�w�tL#�q����`�hGh�<�z�=�!�:�#8��f9�ȈTb��0u#�	��x��B?Ga�ޣ�Ɠ�s�.!����o\r��RD�<��@@P&�(��\nIш�e���ҤU\n�~:�����0At�F����J��'��\$/('�l\r\$�'4N��*25��5K7��RGJ�;����B!�Q��ȕ��ew0�\0�F�:UAf�A7Bq�<r��a�(��؝����>�P�4�Ĳ���T�rQ6��&�!8:IBQ d��J\r&��v�I0!�l��}0\"7C>�uQ�-�g9B\"��<)�H���'���t��Pe\r8���g��BԤ��@�I�,l���T�'��D敻I�JL�F\n���#&jz2����xd�CF��5`Q�h\n	�8P�T���@�-rX0,�+���V�49S���b\nRI�@� �%��GQl�@�X\n�h@�ك%�ў8�	VC��j7��8�J�肙媟S�A��h�D������&z�q����6�~����>�T+���AX�D���q�Z6G�Bn��)H]�!R6���!\r7b�0�Hb�/E�:��H\n	L��p��i`%���~���P����Epk��:�9�lkY�I 0؈��j_r�\nd̞��OK�l�ؤ��e��j���\"#c�dA\r����n��9>��ټ��AI!��a�8i3���\\t��Q�@<�S����'e�rJ�� �8��HZ���8����\\��܁���K�ـIJ���N�Pe.����6BA2#y��F�UW��m�X�>H����v�	�Um��Щ�)\"dV������Y#��Ԃw6PnfʔJ���^�?66�kx��\r9���#�����@��֋^��`j�{W%^�����YLT�#��L�'~�'5��K�	�\r��u��K�^�7�R߼0G��;���~��ԥ	݆����6IOӼ_���m���-���O��B��.GϤ��B4��\"�<F�r�H�x�d���^~��'�X�W+g!Lŉ�����K�ji�\r�v�LZv#�)\rz.Ќ����'5�\\>�ڎ��y�N���&O��N؇c�z�k�x��t]_�x±�{m�e(K������i\"y�z�}���~E`'>&�(�\":�1B��F<ș3*�yͲ�q�0��A�7dB9�����y�Q�� sН��#ʩW@]��M�k��\$�t���A��}}&{_wn�T�����tߑ�|7\\�����O'�W�\r�K(����Oa:^��w�á%��O�Ng��i(ڙ��[�B>FT(��v�i��O\$2��D�2RNʌϲ�1\0����8l�ɰ��\rȌ%F-�ֹ\"��ZQ��\0�YϦ�P<\\�p5DN\nC�\\��ƐZ����kul�� Y�w.���Q��ǂ\"��Y0b/���̢�l����h��@��s	��	�k��h���M�n��B�Up8����(�0%VG@ƅ�GD:*��|!�G���N��B\$��k��`�-��r(�5B�2�\$�@\"�%c8�\$n�l���ު��\n���p\$���6�\\�DB��v�i�#m8��	mL8H�0+��+���\"Q�\"3��0�X�켂�X�ʝ��b�\$(�����RB\\���\n2���p��ROn_��[�\"b�1�\0�oN��l.5����m��УQ����6&n*�6��RE��Jfu\"h�f`\r�,��'*Bb�O!`@�l8ʈĞ���F\0��`�?��#�#�z_�V����;�4-�?�\"\$����z���;Q&8�Np@\r��:\0�0�l��㒶`�P�m@"; break;
		case "nl": $compressed = "W2�N�������)�~\n��fa�O7M�s)��j5�FS���n2�X!��o0���p(�a<M�Sl��e�2�t�I&���#y��+Nb)̅5!Q��q�;�9��`1ƃQ��p9 &pQ��i3�M�`(��ɤf˔�Y;�M`����@�߰���\n,�ঃ	�Xn7�s�����4'S���,:*R�	��5'�t)<_u�������FĜ������'5����>2��v�t+CN��6D�Ͼ��G#��U7�~	ʘr��({S	�H<���\nhk��=oj9n�����4����O����P�7%�;�ã�R(���ڎ��P�2\r���'�@��m`� p�ƒn�@����<m�5�O��8��x���3�(:7A�^��\\��+���zf�t�2��#R��7˰ڡ�+x�!�j��	���.CW+9�jĊ��e:���++ü��ͣ��F���67S��'+í44�p����(�J��C�V�il�BXޗ�b��8C��6�crL�EëT\r��V��0̮0��b;#`��j�,#�uq1�u�ȋI������	!���3%�\"PÌ�#��!i(@�\\]sח#�6�`�1����&{Z9BP��}28����O�T���	��ܐ��K�M�eS�ߐ�P�+� P�2�j\$<6�c�Ȉ�ƃ��G�22.E��5�\"�ڮJ���	���*������ �C�j*`#�\$�0��4�k��	���K��4���\n7\"����.��K3(+��\r�5�&��Y�tpA=���	��O����C��H��Ͽ��Ҩ�̶p�aO+ˣn�@�7�8ɵ����<�㬃�|ʆ�#�4����J�Ĵ�˲��;�q�e3�SL�2,S��8��C�:;����O�<�**��|���G�ːt2���J�c�2��\0�uJ���Fp��d��R�[{��1&G��Bj\rɩ�(d�Ӑs�%�%��>kNl���JV{L*\$\r9�f	�~�͓���*~x���Z�0T �\rs������!�!����Yt.�9�P����EI!��E0��`cJ��e��ڌ��l���G��PC#.@\$#\"!#a��5�S�AUw@)c����b�4a�Қx(h��S6h�!�@�b^E(�A\"�d�x����2*�Q�6\$����T���	����\n4m�+`��V\$g)� �O�X.�E(q�9��d�v-Q5&�䝓�P�,8D�+�6�l6#mh���(L��z�@�Rg\r�SJ��o� �S	��|s(��2^�͉�M�p��\0� -i�Ԩ��=��k.Nșc_=�nFx�)�4�kazI�)T�I��@���^Le�b/\0��1@�i�a�:�*Hb�TZ<�`���z�!5��2���C\0\nzF����\0U\n �@�E�D�0\"�ez��:@a@�G`��3`�tܿx��ۥ����F�m7\"��s�y�n��D�4�k3P�����0�q�K4f�!�4�R��b(i�0(�F�I*�:����bM7���!�2��f������6,I:*E��9��g���1'1cV�cq;���IE�/�2Q\$���\\yNa7Qq؏�RiC(wR�f�4<X���C���VH���B)�0\"��v&9��z�z�]i'�f0��+���8��0��Ca�ʁ%�������{(`*ކ�~����:fB�a�Fx�w�P�BH0��\$���\"9\r\nV\$��r\$@���A��W�ʙ���U����g�CTHc� \0��C\\����&�s�J)#b�7b_�c�b�f#�(p�W9��#�DIg�Z��ה�\0�Z|9���MNX:cs\"�Q�)�܋�KU�10���_�:�K��6z��!<Z�	�V\$�}�\0��3�E ���}�5~�;��-�2?WX\n��Q'�l`K�/�,�GB���RT���2FrWxIU��������AS�*��e4#�mw��Z\rK�U	_���v�Ʊ�k㷦�X�d�6��8�#�l0�S�Z!��*�Oht�<:���ni�ňB'Sa�ʨ�W�� �,5�`	�(`���s���G��Z�ڦlAΩ?\r�v�t�Rs���̎��J�������\nd_�&�͛�v���q�v٘߼�}���^ø}�u�_��V��xV����W�񝛐��\$X�kuF�t5��H�[�p����m#�8����tQ�!L�`���:D��zRY�4z>����:k�m�?~�e�o%Y�� C8X���/[�5�`��dT���+��l6���'�����2\"H��W��|�����n4�\r��#�����j��TfJ9�G�D����nPb�<���_��q�:�0[B�\r#�ǎ����� ��N���Ys/�\\�̱��PL��[l2S�#�ip)o�&]�v�ê饈�L8�T��ĬT�n�\n���\n�R�e9P�\n0�]�'D;,�ADg\niR=L�Ap���\r�W��P`�3�� �Z\nD���!bf/c�C�\"!e�W#G��5\r�ϱ��b���\r\$~!��vCTYb�\nm�_Q>���\rNU�R*p_ �R �jT=�\$k����rz&B���1C.ʠsg� h�v@�\n���pn���\$R&����\r�&� -J��\0�#��ˏ #4(\">\$/��⠶��`������gb��%��l��<> @Q�\r`Db�z�C�6X��Z;bj	�����i���PJ���g#xȦ��#(\$��Yf���:0����.B��2P�RV�B%ß#��6�%H��J��c%Q�(���f*c838Q0'K����C�S\",]�v\"���8�DR��B`k`Ό�E&\r, �2�t#��BE(@k'E�*Q�Eì\r�.�*R%.���bV/��2���l(dl�D�,r*�d;1:\"�}(�\r�Z.��e�.C|��%F�	\0�@�	�t\n`�"; break;
		case "no": $compressed = "E9�Q��k5�NC�P�\\33AAD����eA�\"a��t����l��\\�u6��x��A%���k����l9�!B)̅)#I̦��Zi�¨q�,�@\nFC1��l7AGCy�o9L�q��\n\$�������?6B�%#)��\n̳h�Z�r��&K�(�6�nW��mj4`�q���e>�䶁\rKM7'�*\\^�w6^MҒa��>mv�>��t��4�	����j���	�L��w;i��y�`N-1�B9{�Sq��o;�!G+D��y�ٰG#���[N��QB<ΎC#0���<2�.[z�?��Ȣ�s�69k` ��jء��x��<�p�:�kC�0�c>�.A\0@�2��H�4\r�N���`@E�B|3�Л�t�㼤1p�.9�@��a|z9�q��J(|6���3-�f7���^0��H����\$b\nʂ\n:<#X�:��+RՎ�H�;�T3T�@���'.#\n��7-�8憌�\0�<�\0HKP�i>%��-\n���U�hڥ���/\r�`֟V�2���69ò�:���3�B2*��S�\0)�5��b������n��;-��̨�0����~�! P��#���BC\$2\r��c��z��c\$��\"`Z5���:4Â.#��C��#�tz\n5�C+\"	�-d�0�������D�+[\0\$����B����eL�H�\0V=A �>*r��/*#D�)z�0��\r&�2�	\0܃N��@)n8'&��\"@�~�ަp��\$���e3p#��+��O�Ǵ����	���1�\\��6���PA^�~�R��G�c5��B��@R��¶0�%C+GC(P9�)H�:B�:�����0iH�4^�؀��c{J�T�1�K�ȁ�bI2X�&��*�rĵ.\r�|:���HD}p%;9N�I�LP\\�x��pZ0n`*y	���nT�Д����j_@\n������ bCH��Ĝ��wJ�e����`lM��&fx��o/��@��!�7�Z�0��٫1�9Y�\$ ���� :�lFi���ճ3�p4�̢���Q�4TDb\"��n�Cfo\$:�D��+�v��(���g\\��ٌ9s)��D����E���'��H!�P	@�G�O�	�����i'����4�E���&M�톖pK�YDƉ?\"\\lOA}wA�<����Pc�w�y5��<�M��3&�?\"��QS�F�7t��@F��4�0�\r���.��=,b�.��7�)� ��Cz�������)�0dfP̒VS�y12HؒR~�C	�k��9Eb�>����d|ҋ��ՉHI\"!���C-�ʡF����E1�&/A��B�Т3�f��2�i��ik}���˞( /���v�L`nHE࡮O	�)�hӔV�	0gP����|��s�1�5�\"8G��\"\$�Ҝ���Ai&`�%�`��g����JS3�U)e!`FpܸL�\n@\r���P�*VQc� E	�ʑ5[)���?�j<���eHY��hZ�|L�8 :\$�h���I���]fJ��PU����#bUY�L5� ��&a��\$1>'���!�h�\\12lٕ�o������*�S�r�9���tJJIi��*	w���k�~B;%<䎔����i�g�*j�y�HzA���T�kPD=����݄K�\0QJ1>��탍��1���(阼1a�	d�~�L[�r����ҖMa�		-�P�߳�,1�P�� �d��)kԾ\"5����]AI>U���:f�1(e�Aa Rb���#�4) R-�\r&�ŢxPKz�\0���#2���\0%��\n��4A9�<%�-b�@�Q!Ȅ���h.��@�Jimb�T�zv�������C���\"yr4e�!�H��&�v��G��G�	HK��܆�4s�`wS�%\n2:G�ԭ[\\���U-��@�P#�`L\0W������lo�ʈ\$�ފ�\0001f�%�>�+�j��'_��(��Nɋ3�,������@R�����X��I���81�u8�_⻌�lPl����S�\"�P�Z�土�����-/�&�lZ\n5*h2P��`0�\"j�0��2\n�@]4���u��Ol��;�u��+�%��-�{tVJ�Ϫ�@\nь-�����q�e�TW=]*���S�SD��Hứ)�vL�o�@�P�s-���V\\ۼ��k����Y4ڭ‟4���������Ft�bܸ���z-���*�v�Ol�9G0c����\\�O���{'W�r�7��+�N�*�V�_�+4���\0����Ŧ~��bP��/����3���D�\n��d��-�|�����J��\$0&he�r7,��-��.Ġ�o��ƠQp\$����!�8��7Kd������s\"S�p�̬PTBO�h��d#V���&V)�n�4]@�\r�t]@����T(�\r�<�0.�:�NB.S2˰�n�\no6��ph#L<��?�rG��( �q�e+�0��Z���;k�/���#P��V�8�N��b��Z�\0004P�l����0�m@�z���0�;�����쯆%#�!.m\n�_��3,�\n�����^gC�Ȃ�������P�)�0���m�V(jPf	e\\T	V��� �E���D�O`Ս��b�Q��@�P\0�`�`\"�����ycJ4�Z5��ڂ�#N�f����\n����\r��%1��m���>�1��\r��c�&���x\"�`�b)p�ͮ��8�J/\"�����H8qΩ.�K:j(m˯\$��\ni.�K�f��Ŏ�#��\\�o��lD��b\n�rj#�'ض�f�R&˚��'ʘ5�Nr撡)2r�f2&���!�l7�F�E��ˮ#2� �\r��m�c#lT_�ܥ\$�2Z����e2���B�ڶ��\nfJ!�G�uRx]@�(���#%\n��1��Ҙ0\0ޡ��-+i ș�:A�}'%!Q�`"; break;
		case "pl": $compressed = "C=D�)��eb��)��e7�BQp�� 9���s�����\r&����yb������ob�\$Gs(�M0��g�i��n0�!�Sa�`�b!�29)�V%9���	�Y 4���I��0��cA��n8��X1�b2���i�<\n!Gj�C\r��6\"�'C��D7�8k��@r2юFF��6�Վ���Z�B��.�j4� �U��i�'\n���v7v;=��SF7&�A�<�؉����r���Z��p��k'��z\n*�κ\0Q+�5Ə&(y���7�����r7���,I��()���h9<	��3�\$#�R7�\n��7#�ݍ�x��cK��+���5��\n5DbȺ��+D7��`�:#�����1���3��¾P�ʡ\r#��7����c��2�\0x�����C@�:�t���1x��O���x�*J̘^*�^�7�p������7���^0�ʘ�5�)�D�-�9�[����`-.�CB�C�M;�@����Ϣ�2C\"40�H�����\$\0005�M{_�V}\$���	cx�:��\0*#��7�����B���#p��[.\rn9)�J���A6��+UH��P�:�-:� ��(� ��L�`P�2Hz�6(oH�0�Rz6�a\n1`Һ��:R:��=փ�L �8o��C�I����c��orH>n�>��\r{����X�(��Tn;�����=�]E\0N]'�zuZ9�It���Af�#��R>��CL6*���.��^Ao�>5�@P�6�w@\"�[�:*�J垍���j{S!-Y��Đ������@�ˎ�\\�9�s�(����\r9�£\"�>�p�h@�H���:�Ԍo�3� �Ѕ7���2�-.�Ce.�:Ό�2E�¸���3d�niZZ:�0�jʌA��;��S_C��)j��q����O�	W���6��,�K�gE�����k�v\$1����_���/��P��gp5���� �K����G���:%h�v��~CɰDa',�E��ے\n�i-#\n��xoGuI��sڒ[	t2���*gM)�6�d���p\r��є��>4e��	��	�a���p�А����\"D&H\\(m�ez2��R�B%%3�����\\v�,%����bL��4&�/Sz��;F7L�PnOi��1��K�х����H��� �d�F�T�} E����A	aA �DAVZW�@ oEΆ(��&R+5!�\0�hhIh�3��a&Ij�A�%���f �������{��&\$�U8��%Ik�ҭ��ax/Q\$=�\n�����6��C(s6U�x(7\"^LI�vb��#�S.]�pIh��'.\\k�@�)�A���'bgQJ(��4rt,��g	b��@b	N	i��k9DL��C�i�L�54��l�@�,��R0������7�p�ÚUJ�4��]C:b�U�0�[*�X3������j�y)� �%B��Ʋ;G�º�hm�*�A��6\rg��vNI���;��ԃ��/��-La2N��P&�(��_M��V�d���VF�� E�\"�:�O�m>A���:|�ySf�X��2N� 1%pvJ��s�,+�C�<��LK��๪�j������H�ź#W·��jZL�d��^��G,�J�H�#\$wR� �R��|�r<�D<6�`���L�H����K�@oX�3R��!\$����|l�Sn:-��?�7��	ݿ+9h���\nw#��L1��,	��}sΨt:�f��īf�gi(�2�S�hf�d��fq��*(Q��g� *3@5B���)�g���y���ej\rJ�Ƽ�)|�ү\0�3Pސ�yi��J��FB��o�39��!���AC\n@��oZ���&�1��G������y\n���`2�Q�^�t5٦��i]�����B�ZC�\"�:�S�#�h7!����e���C#�7MXЦ��N���0�¡@�Q6��� �ߜP\\�xb���(�}D2���7��4\r3�=!H�ϝL��#[�:|҇P�@�L�_,��l��˸�����T��|g`� �@�BH#���uL{T����T�?p���u\"j��冭��1�E+������6}@�Gc��\$5]��_ewz�\\2pg���{=����^�C����*�?��;2���v�v�<�������x�L�Gv���{�O����Р�;���%�z>�݃��7��7������1�9�իy#du�F\"�F���\$�ʞ�#�K�8-�\n�׉T\$z�d�l*͏�CKb��Τl��'�S�t�8��\rH)#�QI_�G,� �)��y��w��)&�0C���\"����j��%&�ш�8C��L>�	�~�4�|�\n�%js��aFf�u��(0N�(���D�bZp[Pa���PX��p���J\$��\"�&̞�G6\0�\ri4h�\\������\"��KV]b�\"@�W�4�h+A��`0��0�4�`\n�(�o@���w���{�Z��Y��Z\"�\n�V,��%�D,���,�ѫ���l������q�;p�k���117��x� ��`ڴH�9��D�.��� ���J�M��9�M1g�m�E#&�FH{�1�[��p�e����K��e�0Y���*Iq���.�Q���ܹBl�M���vFd�C��al�(+4�	t\$�F_�Vjf��-d�Gb%����Z]m�u�4q�v#YE�\$�@)v-���R!��C�K��\\@��	����Pm�((��l��lm��#�#	p9���%P�QaO�'�����k'�Jܬ_)��Np�.���U#��8�Q%q�.\\�('�V6�o,2�+�a+����T,�,R�%�,p�3-r���z�&HZ�Zc�8�c,Ie <K).�^n\$v�ǔ@�`�n�q]�Q0-��䑛)����M3s����T\rh��&��ј��G*��5�_6ƾ��HET����ჺ��{��D��{N)8�a�_9�m���r&�V�s��tl�'�* \"=R�;�A@�A��96�q�<��=(\$*ӟ4.�=��=O�9㤀��)d�gB�2њ<\n�!���EZ3c;AO�f�/\0�/�#j!Bq�B�= �C�#�7/Ϛ�#6*�\"Xi�<EC�2!�8�-�F���h3q�OF�#(&��R��`�*�>���ei�\$���)ú),L1,F�tQ#�B\$��]i�m��Ii�\n���Zl�j�>���%�n�G��t�o�E@��MN�2Һ~��JM\r�\$Bb0��B;\$u��6�㓌`4�b3�%'�M�t9�H��>�FjC�M�V{0E�9d�5�*\n�OFd�%���#���3�p;��(�U�\r�\$&0��zsQ` L0%�SX͙W�P P�XEO_W��)-��Y-�bpEZՎ��a)��;�{�R(In� 5v|�G�6\np��P��6�p��_��_�\"'O�Y�,T �k�cK\\(1�Ɛ -��u�&0���m�b+\"3��Q\n�-{XayI4\\�G��d�F#X��\0ͣ>e�6�\"\r���� �W^>l!`�q�گ�|;�W �"; break;
		case "pt": $compressed = "T2�D��r:OF�(J.��0Q9��7�j���s9�էc)�@e7�&��2f4��SI��.&�	��6��'�I�2d��fsX�l@%9��jT�l 7E�&Z!�8���h5\r��Q��z4��F��i7M�ZԞ�	�&))��8&�̆���X\n\$��py��1~4נ\"���^��&��a�V#'��ٞ2��H���d0�vf�����β�����K\$�Sy��x��`�\\[\rOZ��x���N�-�&�����gM�[�<��7�ES�<�n5���st��L@��%� �L4\r�\nh:T�8�s��㫞��p�Ȕ4�T���X��.p�ǉ�\n�4�n�' P�2\r����T:\"m�<� c�<�ܰpP@;�#�\rIC�9����43�0z\r��8a�^��h]�#r.�8^��A,�C ^*�΄�̺'�{��|�B-xƸ�0,N��LJ�����\r�]2�1����+ѫű�U<9T,;#\"�<̶�P��\r�:(��\0�<� M�aX��!��`꼧#J=e��r���L��΍�Ch0��9��\"�0�:���g��%J1�5��e���7��\n	��P�㋆6`���7��؟>�|\rm�(3�x��b��i�6j�r ��{\r�315�7Z��ܤ��&L���YRU�lGIqEWR���焾B�Wic������tS��#k�0\$���������Z[���PŭD�@�!L�>�\"�#2���]N�9g�.�{e�%�(��q:i��պ�o�c���X�R�KŮ���,.�%`U��4m*Y��0���I�\09N.S&�;�ޠ'��Ag�c��؎c5�ak�%.<^�3�+�\n���42��SHy��=�Ø�x�8曧-;��P�h��\r�=�GC��s2CLKR�L)�2�tҚ�hwM��9 ��p/)�EAD���<�1G>xe��p&��0�ĆR�p����hb3�#�]>,BMN�.K�~�D̚RlM����@䝓Ì�5?��\rz�9�p8�@�R��6o\0005�t�GH;2[�� �'vHW�%\$�	O�4[��T&i\n����V��ՂC42}/��g����R7h���\ngP1�%a��c.\n~!&H��@I�Kqa�MƄ|@PH-�� PTI'+�dŇ2<�I l'07' �s߬[#��;��ٍ��?I\0�0��L��9굒�����7ؙ�CzA%�a �^E�p���XKI<�(�]�,y��9��Wl���aL)g�6�&��\"���K�B�]��������\n\n'I �PJE �0�6R?!�A��������.%u�NI&���~�ٙ�7�u���PM�7N(�)�\n��\"R7�X3�`xS\n�,�\"Rx㔸 譙�`@�� u!t �3&?M)j�@�n����0g��j��EM{Ê����]��7�����'���e\n�5@FaȒ �i�#�Sd���2(��L9�('��@B�D!P\"�KL(L����t�sr�B�9X0�X<d� ���1jL朣X0řD(ʎ��4VAh��GiZ�0���(�U7F�G�����43d�Ի�=n�V��sZ�k�Q�����/�\ns�U���V����ҪJ@\n\n���r����Es`T��)i}���`\"�VW1F�� �F�b�3U�@�a�\"0^+1����e��W�7�@�nL�z�K�P�/F8ո�1�Ɋ�h��\nܰ��!u���.��^�[T�~\\�*�@��dj3MDS6�FߞH=O�\$�o<���&3�ᓨ�r\\�1tALRHs��D�+-��\$7���\n�!��@�K�>u����4)�#%��%�`�r��c��p�2��Ƃ�׊�p�@CP�.k�nhf���/6=U+�+jBR���{|�;N���<O��f��l������fpM��|\$9��rR��6�0�O{�	\r��ߕ������3G)\0�r)ˣg�dȫ-�<B1��2�0Ɣ�0�O�n���\"���ˣ'*�1�r���fxbqG�<q7ɴ��S��>��A��<N�j����B���sbf�c�)����!��|�+�V��4M�z�dpGt�v�u���`/3o*cx�ٌwt��G���n�1��\0(� L\$^��>5�Si���I�pqD6��c���1�>�Om����E�0�:E2�_G�VvsB�H�X\$^�Z�AEec���U�yQ%�d��w;��%�3��򿑀/�}����Q�X�>vqc�lp.\r�M����q�Bo�����/�����\r��ܔ�˚��f߮v\$����)d�o�n������5,�\0+�[n���ʷn��`�J�C�?H�3c�d�b�6e�\r�@�/�jxb�k��dT�nY�\"�<��#-�[�� �PRj����-\0��\0:TN��Ʒc���UN�1��� Z\0�W��ZU/����j���ӫ\n�������}�,[��\0�.���#0���i*	m8���� +!Z��d̀������\$���>(�܃��*J(����' :�\$����'p�}��9E�+/FTp�%�\r-)c�yQL;�FZ\n�Bk�q\$oON}br�:���Q7'����O�О%����F˖��b\0���\r����:q0��\"p\n��R�[]q�B��}��0���d=��\r�J�(��X�V�B|������@���\rg fR�r5H�0B��X�3o������J���Yc�\r�Vc�a+\n!D|}`�P�3qTݢN�@��*k\r��H���\n���p\$��T���/���\n��0�/���):�#�<I	rFk���	�U���n�	��t�h\rq��k� ��|bX'\0����PdL��D�Ω�y�jat]���#b�Fh�1W2�6E?�rV!'�F�e1*Eb�k֎'\0006O�0��\\�D�nn~�'q(|�v�:4��*�\r��9�rI�2�U`b`��R�F/3*0c\n\"��ˈ�o�E�'@�T�,Jì#&2�:�2����I6�`͂�C��b�h.�����i+�<4��n�01+�Y��+�� ��.�6*�fr�M5\$K �Xg �5 "; break;
		case "pt-br": $compressed = "V7��j���m̧(1��?	E�30��\n'0�f�\rR 8�g6��e6�㱤�rG%����o��i��h�Xj���2L�SI�p�6�N��Lv>%9��\$\\�n 7F��Z)�\r9���h5\r��Q��z4��F��i7M�����&)A��9\"�*R�Q\$�s��NXH��f��F[���\"��M�Q��'�S���f��s���!�\r4g฽�䧂�f���L�o7T��Y|�%�7RA\\�i�A��_f�������DIA��\$���QT�*��f�y�ܕM8��3�@��ij�;ê���B�V�B���¤��+�92�`޿��x䞍�Z#\"��\nKn؎��v���\0�1�I�\r�1B�\0�(�j0�p�;�� X�`�ь��D4���9�Ax^;́t7�ar�3��X^8IҀ�2��(��7��z���^0��3�1�,c\r�@P��<��n��C�A\r�4@�%�\"7LST�MJ��pޯ�M\$\n�\n�x����(�C��U�lہB�6�\nt4�5��A��*7m�#����j�ƽ=�0�:�!�`�CkD:��`�e9�Zבh�t�u�ӌt�(�0I�\r�	�V�C6kn�:7��*W\nw(���� ���6%2j�i��*���&L[�>�c( �([�3��F\"��B�6���!��}�����f��5W�l4����F�#lp��B(񫲚\\�Hlh\"f��o��YC�,��ϸ���ŏ�k�A���3/D����X0�՞Ӣ�#}&;�/���t*�Y+Ў2��L�mSX���x�3\r�\0��,�t7�)��X#��6��n_��)��x�3�/���q|�2��S�\$��h��HS\$ާ\"�\\�7�`:�q�[4R����)����Թ/L�;��wM�sh��,\r��A��o�J�P���R��)M�Mɂ���2��j��a\$�&��4�IrVp�P���V})m.���*g~i�9&���I[�:	�;�P�m`�t�@�����\n�b������T�P'Ȩ���o�a�����!�`�Ω�M@ɔ���;i��C��Y��0�h>�Q�wI�x�p��Y�\$ѡ��K�c�#&t43ߡ�n#L:��nn!��^&�(�����7\0���TY�q�d���bPNU�nE!��srH\r�P6�ͫ���[?����TC0id,d�\0�P�+�'Q�=(��R{�	�8\$�@�ҊF8!�4D���|�d/qӺ���S\nA=��C4I� CĴ������(�v��!��f(E�\$���Z�\"�D���􊡨A�@����rp!\$���V�J�4\"Į%ph��f�Ɯ�=�� �c_��\$\$��hP	�L*3sĉ��Q��3������ԇ���'� #FT˳6|s&	�	�MM'd'�ݯ�6�#�d�2K��K�\0F\n�|�+2����5�ҕ�\"N���\$�`��H��c�\0�0\0��P�*[, E	�Α����z�XJ�A�`�K���癇���������.E.�(�G*u���mŰ��\"��^�IG��U\"�jD�t�*�����ͮ��\r�S���m&~�3�z�o]�ij5�Tv���Q�X���BI��Yr'h+�<G\n�:���H��S\\c�&N����*�\\��.C��t�8ͫ��fa:1f4��0��\n�j�qYi	�e�3puMH�_	��e�(+�Ŋ�f'CF����Y��#��'#�\0_j\0m=jL�1|������护Cu�R�S,T\0��s\n�l���ؤ�N��0�������9 �IΊ�qM1�T�Q`T\n�!��@���3t���1�#��u�b����\\�3JyCz�Vz�U��b����*%=Z��12ǹF���>�^�(���g�5������%F��8��yb�����ɮ�bLZ�bR��N��1�X8�`� Q��u|�PC!��Z!a��Ӈr/���&ex�p��Q�l�,���nb�&黩����G\n\n/�����?ay0�1DV��6� +�0��ݺ�6Ɏ)x��ecI�sX�%\08�-�/�_W�(db��0a>��O�6�0�0����}���b��וO`\r�ϱ'��Fn�;�)3���`f�A����Ǘ��{�x��HB]4�b8}�v,f���4f�8@�&�Y��Z�;o��8��v�+�j<�S�>D�+��n2fUFoh�#�\0j!fė�,kT�A��\0��,�Yw��g�]�0�o{/�⼆��,�������^����x|)�T3K2�(�!��ۧg.����8?�B�m�^'��[Ka���O�6Ω����~���̩������/\0���\0,��\n���(-�W��f�/��%c�C\"�˦\$\$�<.NY��(�\nX��Jh����*�od%D>�0>��,bPR?��#�h�/��o�Q\n��=+��\n���̸?�hk�2I�d#6]�@&%/�ȬO��A\rC\n���\r��3��o�ѥ8��>�Z��λ��o���s�悌о�	�:���+�k������@�(�z�DX#�8��&�6�e��-IP�/��P�fQa�����,�&n,.\"��v��Z*:�z�0�J0�\"�\n�hfžoF̫����͂�����l���@����gFd&���x�L���\n�X�&bμ��BB��Q����� 'd�1����1��QbXIbzL�ш3er��\$i��%4E��Q��K�D*�ڥz_��#b7#0o<2�ra����#�f��\$�u&�Pc�\r�V�@�_B,\r��OB9qLۣ�2`Z_�s�>\r��F��x`�\n���qŮ2L�'-~�O\n����A	-�'ok-%	#�<\$D\$�\\%#0�,�H��E\0�!���/��\n���gB�C�)��I�@���\"g�@[�/�rDJ�O\$4�=,C�\n�D�atS\"��n�,6>qIP<�N�\$ojR���\"�7��oh!��g�Cj71�LZ��k7�p!�#�Dz��9^;Ӕ?���*,`����1\r쌷`�{���B��s����J��ܶ�~�px���h��]\"8a`�4s�l�G`꒦;��ct���3/��82��X0ĹE�B�bIS&���Q�W��0k�@�/�V*�;\$�G���\$��@�~/��"; break;
		case "ro": $compressed = "S:���VBl� 9�L�S������BQp����	�@p:�\$\"��c���f���L�L�#��>e�L��1p(�/���i��i�L��I�@-	Nd���e9�%�	��@n��h��|�X\nFC1��l7AFsy�o9B�&�\rن�7F԰�82`u���Z:LFSa�zE2`xHx(�n9�̹�g��I�f;���=,��f��o��NƜ��� :n�N,�h��2YY�N�;���΁� �A�f����2�r'-K��� �!�{��:<�ٸ�\nd& g-�(��0`P�ތ�P�7\rcp�;�)��9�j6�I�f�\r�Bp���K\n��@P�0��`�L#�1P+>:L�7��\"p8&j(�2�L肥�i�@2\r���1À�+Cƫ�hK�HlS\$0�!\0��\r\r�������`@%�C�3��:����x�;��R��Ar�3���^8L3�2��\r��p���:\r.�x�!���6��C��)�<�D�h�̥�C�� �<o-UV\r5s�ɍ����\rb��AN�J+ăr�3�h��\r���:!-�h�h(�k��0���4����Q �:ۏ\"`��h�Cs�m(�2��\nj�����t����m[b��F�%���1�,;�&bL;V�5h|@�)�����E⁏	{���2�blȌL���ΐ�&��9 V41����5��V��!ň�i�SV4�-��:�Ư��������pʃ��(7M�˒�bH�%�C�:\"��魴��(i�^���в�@\")�Zp�Z\n�y�**r�ʄ�R�z��)��	]Eia��ڔ�B\$������_?��q��B8�s�mh6F\0Sb��ր�3\r�\nz\$���*\r��t<�'��̡����3F���a�k(��aJ�m}^�5Ҵ��枊��S��^�H3�~�ɚf;npÕ��R�M��8�4�úyH	�?��zD/�P�(�}�R�R�e��@M�		 !�����	�L�>�3���S�E���I�MK�0%�ỡ< �3���ӂrN��<'���S�P,��J���>nA�ʮ��\n��C'U��CZ�+H�9#�h���f��2� �L�A�%�ܼ,���S�u-�ldɸr�(eg���P{ρ��q��h�eҞCwK��4�e�9���)��,چ�\$o��d�9��\"KnǨ'乃r;�iL����n�\"�oE8�r8̐\\SC\nq�!ǚ�ܻG#j�ij�ߴ˒���X�!W}F���H#څL�h�4G��y�'�ү�BOBS\nA;G��y	F�*'��L\\�\$����QJ9m\$�`��Ffg��\r4m��\"VH�s��%��_�Ry�\\<�\$�V|�4!�g�s�y�X+A����J<t��,�✡\r�I+�50�T�h�����y)�d��H�;pd!}BRK��#����\\B��l��I2vA�ƨ����ſ']�\nl쭳cvK0T\nr�>��US���9��������������FȜ&��2��������L���o����%��dѕ�3�ɐ-]v����u�b�je]R�\0�0���S�~n*E'��t[>�����I��N)8��ܭ.�gC�0H�����}\rjN�ț��V�xAYuK�*t����mx� t�a��\$�\0�hʒ~�����nh>|1�iV� ���%8t��[�ͩ0@�!�?D��ģ�/�9��2�pG��eVk�\"��=S[mQ\\�r�a��j:D2V��.l�Wt:�����E�\\+�N�`�LR�a��?��0 1�m�J�p�����W��m\$������R\"A�\\2f�`KX3��SRv��s����_İ�J!!P*��?&S��t�p����L�0�x/eQh8�A�)cXkUg��k�PQg�`�)�\"3��Ը�[���W�.;����񻌎�*;��nwG��j-����:W���h'��z�6���+}q��uw����w�e��FT� ��V�WD��\"�`q*�eo��G�䥮4&|G:���M�e�'�s�9�q�P�x�+�\"�3�Oa���iEuݨe�Y/�n�A[����ٽ���ͦ�g��6�*�0�R���N)b\n3W�6��n܇�+G���22�&�J�`w��J���:y�y�Io|���Һ�xnS'�UJ.\\8�y�{�`΄D�J��S�&�յ�uɊS��2唶���k����MH�(��{����!C������u�B���d0����i�����<\r�pX*}�\\��濏��L�*p5�Y\0L��y\0´�o����G*: ��l�n�V,.եD�.�����U\r�0.�ND�-:�c�U@50D5��L����(l��h:����F��&�c��pH�opt�Џ�1,�����H��\n��=C�P|*�*�>v/¥���<���,c���Mc\\\r��c�;'��������D�[�i	���px�o.�)�;e�lF�kL�G�Ϩ�[��3��VEh�oD���L����ej%�~��,�j��\rx�eaL�@�lG�L OP�L�.��reP��<���oT)F F�[#����ƶ�q���BOBB:@�aDC�ob~S�\$:c�:�Z�YF�a���Qo:��-Yq:�-^TQ���\r`\$�U1�WP9%D���\"�M{��F#Jw�<�E�/%��\"�є�z�R@��o��.��D�p�>Q�!#�FBB�bo�m%B\\f�Qn�h@�(��\$n�?id\n���A)��[)�:�� 2d'��K��1��:\nr3D\\ N�W\$/T?d����!b��@��2�,hFq��g�Gc�X�/����G�m!%��pd�CE.R�	S1m��\\5�F\0�m*W ��p�-F�h&��z�'�~f�ڀb���\n���Z2\$�>�.Y�䥅������x��|�#,�b:#�B\$g�c�G�f��j���̄��<#4bOo4\">[�B ��琬�����,V�p !�bzFb��m�8�x��DG�w`_b�D�+Ed_cW�0W��1��2, �4�l�oLS�(�Q�ƥA40�T�8�2��6�l2j��d� �B�mgm@/%�_NƼӤ��qF�8���e�R��t��`�b:E�d.XF�8�/`@�ZVp,/��:%� DJ��B��:n�n�4��\$-�0\"��f�P�jt����@\r�\\��mhx�Q�8\\��(`�	\0t	��@�\n`"; break;
		case "ru": $compressed = "�I4Qb�\r��h-Z(KA{���ᙘ@s4��\$h�X4m�E�FyAg�����\nQBKW2)R�A@�apz\0]NKWRi�Ay-]�!�&��	���p�CE#���yl��\n@N'R)��\0�	Nd*;AEJ�K����F���\$�V�&�'AA�0�@\nFC1��l7c+�&\"I�Iз��>Ĺ���K,q��ϴ�.��u�9�꠆��L���,&��NsD�M�����e!_��Z��G*�r�;i��9X��p�d����'ˌ6ky�}�V��\n�P����ػN�3\0\$�,�:)�f�(nB>�\$e�\n��mz������!0<=�����S<��lP�*�E�i�䦖�;�(P1�W�j�t�E���k�!S<�9DzT��\nkX]\$�(���!�y&�h�0�2����X���E4�\$����n����)�56d+R�C��<�%�N��E���3���# �4��(��<�\$5BϤ>�Bnrb_�E�V֖�S�� M�V���<*\$xX�@4C(��C@�:�t�㽜4�1M�x�3��(���9�����K�|h5�ihʵj���)*��D2�\\�x�.��#�Ӵ�ֹN���	���a\$̙,�dO!��iDE�dn�G&�γ!�6�]�C �L�(�Ic�H9��?��3Ά���7:�%V���N{���օ�d���k�⌮�~��Kʟ���ʆ�5� �ijt��\$;�7vo�L67����l�~Խ�*|۳@��\"]bR&)�{>��3�����z�D|꼴.3GNdvJ��R�Dc�Ba�OT}6#�\n�����M��{���m���!�\\W�!������%t�(�9˞ݻYA\nb����\\\"�#)+��\$\\��F�,/=c�w�i2	W%���Ac؆C���%��P��)0��,��^��4�X�x繱V�M��)�ё�Zp�da\"	�#`��#�?�9���ؠ���	˕D�V@D\r!�0� �K�sX-�k\r��;Se���趃�i\r�9��^RÊ�H�\0004�O���!�x�\nk��E���M1�,hq5��&C\\�=ki�6@��,I��3�M_�Q�TDochs�y��|y�q�0��=g��UQ��)�����F ��xH#uC*ա�#�C��`���RċlP0Y���B��t,*�1�9%℀S��N�\"���D�h�%V��NO�)E!���;\"�,I������K��#%�F���7�%f9�K�)	%�=3Ћ��h�G�s6Q�.3uw�C�b+�\"J���a�NQ~Y�#�x�����V>L�Z U�UK����9'(-�'�]���BǹS�z��\n�X�d����֍%Z�Yl-�^\"Xa�)m�%��h>���9��rx�,颙�Z�TzN�(��b�.���m�S?��L%\$\\=4E�&�*4\nI�Gd-�Qy�G�Ue+�N�u���\"�Y)f,���&Z�]l����EL\\��h�H]�Z�sR���c\r\$9�y:�W�!��G�UID|*����z�\\��l�dի�B�Q�\0���z���Œ'XYJ;/�e�('Ď����gM���l�YHm(�I\"��weQ��N�ۢ�싩�<��D����N�\$�!Sݓ����,��*��\$��\\�#�衪�zd�(.@����?rF��n��T�B���zC������k���cP��HLb��\$5����G�j�>�}5Q��[����1�1+v�J��(�Y�˪��\rub}A���wJ�K����Y.��\\ٕ�Gc��w�^@�7 F��R��֥��3T9�ǧB~\r�P�QBF\"G�����d���Ft�J�R����%��t�dҫ9�K���6���T(�%�Q*S\r�]����r�_�a@\"	��٠I�Z���N��]->e\$��\$��g��췑X�ͧ��pBFW�\0�£�U��f�)\"&��*k��3�4DU��[�@�rLRn�]{�&��?��Iy^4��`�8T�*�kLOq5�x��u��\\�&A˧o%]��^��-b��\0�����O%�4�ӥlh8�[K�v�d+�_ȝA�nVjHg9��v�!�~pA:���#z�o�L̆E��R#i����*�ROd}�G������J�8]8�&��~�)L�����!�9>����.�Q�[��`p��1�=	��J���).�� �o3;�aPd'��B�v�\raYڐ�xX�r	d}��__Z��r��P �X�L��y�ƧB�L���J�!A�A�@\$�	>\rC:�.5�z)�����'�,�E�f�q̆��7��:�A�~�C����ψ��Mp|oJF��n�O֯ƛC��M�.6F���,c��-@�b�W�n�d��\$'ʸwBa�@|��pN0g�c�n�(�LLΆb|��\0H�=���B��k�)�&�Dj�/Iz�PbKn���@��/BЁPf�0\$e���������\n]�>nH���&�2��b�mG���^a��A�p�0��ATC���+�n��/N��el�N��\n�� �	������&�L,QK�&�N��P��J��s z��\$g�^1�Tʴ\$�ک�1_��#&�;H�̦&�+�z�\$�|˰J\nOt0a�qn��O	��F��������rz���q~�1�F����r������e�,h}���MX\$q����Ѹ8�K���(#��cܖ���(*޺Ɲf�2\0�� ��/�C�ur�� .�\$��rdFbv�@�`�Ql%�&\"X�NT/�xv�!��%\"C%b%�n/��-��=J��\"�¶0�晄(2h�H��`刈v�bHLi�c��3��F'2�P�,�Kdmd���J�Ơ��>�2��ЊA1n����G��8N,�c��C2l�J\$%/C�o)���`E��0K3�N��H�0,�&�.�B�FNO�,��R��3�[3��=sB/��s4��y�@*�X��@��bd�yfL�B�)�P�33��F��Zo,�o�\$u��.B\"��Z��M��O�a8�tI����L�����B���>�%���i�-&��pLw\"���>��PCN�\$\0R�3w<&��6��5i��/�2�`�l�L�4JC2JF�YA���ߧU4Sl��5BEC�=q�5�6p��D�3i3�E�����f�Wr0Q�BsY>TJ	�F/!4g\$�FÂa�AGR&e�*�T��R~t1HtcH�7L\$�oI�aIь���G�}J��\"%DA�2l�}4m3ΉM��MΒ��HGN��\"��CN>�BU�:��Ը\$*,.j�S���k�,I�8�-B%�%�bb���a�m͸���!j�!�̶P]?�0�T��\$�4?Lh�U0ibmˡåN���V�>�gH0��0е7�F��B6��<�t�d���Ќ�!f:딗�4��L�#,:f�3A��\\r5A���u�1��Jo����^;6��]�]Q\0��^�c]�]�z�+���O�� h;2��]��P��.����0�S����9bV<�)��|j�\"!A\reBBg0�\n˶����E����@M�Z��KԖ��gH��0�VfP�g�L�Q\\��A�{3�+hUK5�6��c&%+��p�uֱ\$DQIV�&I6CkBp�>o��l��h��a�d����Nw�	�	R�P��Nt�.В���!t�M�paEp��qb�cc�~�[oЪ���E�qq���\0���VHGO*A/��O4�0�!�]WRt�p�i5��vN�!v���worWtK\"���Sh��K��t��x�+2�l�}H�b��ы��t�2�k{��41{v�d����\$w�f�xʷ�n�=��j��bNuEÛ*��s�y}h�r�{q��ɗ��Lt��7�w/tw2G{#�0dB8h�\r�VS����{º]a�e9��V��,%N{\\�Wk,�#2�'�X�Up>UV�����mZ\n���`qr�l^UB��ol	����ωla�d�����X�lUjt��=ы����}�{c	.��V-�wb?�ck�x�'�|��p6Cx�W�sr�\\�Wۅ���n9�����XF8P`�\$h��v��Av.��'e`+�5f3&��&P�L(�o�>��)fl�!��\$��q�D�ƴ����fOt�H;UH4�aG��b�R8q�?��I������Q�+�-9|y�����>Y�5�Ƶ0��Z�'Y�3;8�\r����u�	>r�3S��6C�~�Mx�fNP��\"��t:�:\0�M�>����@\n`�Ko\0�\$u\nD�Pm��\"�\0003��>�Z�?�OG�G�.2\0Aj�G��f�f\$���}�dPs.a�]YH�t~j������:Bt�z�-S����bSZ%n�0�46��\n��fՑ*TN%������"; break;
		case "sk": $compressed = "N0��FP�%���(��]��(a�@n2�\r�C	��l7��&�����������P�\r�h���l2������5��rxdB\$r:�\rFQ\0��B���18���-9���H�0��cA��n8��)���D�&sL�b\nb�M&}0�a1g�̤�k0��2pQZ@�_bԷ���0 �_0��ɾ�h��\r�Y�83�Nb���p�/ƃN��b�a��aWw�M\r�+o;I���Cv��\0��!����F\"<�lb�Xj�v&�g��0��<���zn5������9\"j��eHڇ?���\n� �-�~	\rR@�n��0b<4\r����p�991	R4�D��#( ��j�� ��\"�x�5����#��Dcp���0�\0000�j`�4��C=\"E���;�c X��H2���D4���9�Ax^;�r�#�\\���zr�Ը9�xD��j&�.�2&���H�7�x�%\"��8<q*�2&��7��c����@�:\"\nC�6�\n\"44'��WV�m���P��'h�v5â�:7<�hJ2:6=�e�6m�e\rMh��!t8�*R�P�7ՃuPP�b�քH�1�C-�:C �:���R:�T�0V�L��:�c�����o�`_/�P5��*������#�(��C҄����J�Ŵ�X\rb�kFc^\r�c`��0	�1�#r(��b�V���:&��|:��&�V�l6P�U�=\\�#����-�.J ���ȧ�=z�\0�� P����1bC�H�!^�(�>{�Z���\\�;^���;R��\$\"6�ʘ�\n�s�)�Z��e��GG�iH�2��d��e26q��6Ɏ4�O�I�۶�K�`9.8�r�.|�l꒎��x�3\r��R'��qC���ސ�cp�`���#I5�\$��9��/�0��\n�}�t�u��@����C��\nb�CeD�;\"��f��)\n����E�IIh�:��\0ib�Q0�ƙS:iMi�7��ӲxOA���f�Tz�й\ru\"�ʊHQ�����w�\n_E�쬔�JЊ�v��RJ�)O��-�����J���0&%�BjM��8t�aBwI�=�����n���/8n�� U�T��ǰCZ�G�p9=�r!�T�m	y�t���#\"��<��M���A��\$X`�6Y/��@�X�f6DQ�>�����}�M*��8EU` ^zC����L�{/�`�ƼV\"2	�ĥ�tVM|�\\����\"B���^�)��0������\0PCQ�`1�ԕ��#�Ԭ��jͪ=H��7�v<JBqu��iD4�X1L\"p�-�8�_��\"��>�w���\r��u�����@w6�yt�(�Lg�D2�\n���~�'�!�0����:��*�L�� ��\$��\"o# o8�d^ǢXK��2{dj��9.N&��Wǎ.��/U#�@�%9�RHXy3��\rO�NVBU�+�8�²2\0vRR���Q���>��R�<\$�\n�O\naQ�؜�n<4�ʊ�pI��U~���6h�� (!�30�qDhe\rU�x�t ��q��l���@_`b.`�)�\n�<��{*��A.o�ו�[�A�%!:�@�3Y?\",�3�]�L�m�8P�T��@�-�WE��Ų����j��>�X�IYP�e�t+T�}%�9@����S\"+/0���\n9h�6+�t�Le���P�\",�C%(e����D�k��g794|�F#I�Ū5��ߎ0-�卻P��XoH���a��#���1���b���G��@\$\\�8��g�\$�Ld\0o�9�#Fb�b\r@\rɸ��	�m�L4��e:���A�v��ݎ�)0X�0�ʹ}�X��������0m��v�fNub��S�D\0�J1Bz�<��8Q�S�����#V��96���#��g�����h�4a�٠\0PFQjue&<�[hU#�F��,�ZI�#A���#cP��\n�!��A]O�1�TB�\$\0��'��\r�죔|{���yg�t֑��F��₵�A�h�2\0�foR�T%�S\nj��mQ���Z!��\n���&᧓�2��w+��ߗs6���5��X��B�9�Y\r���lr	Iy�F]�slH���Q��������3�+9��˻?<q��������p&���Y����n�Ϻ�N���\0�4�N�QJ����\n��\n=���.j�����AM��9�VL��\"D��%�G�u+(�Ҡ����x9f�]p8��\r�(��e{���BA�U�̟3���\$���Pt꿒d�쇓�@˵��x/�:Â�é�гl�~/��1��7rdP��P>��#x�IO��O���,�|�����Z�.�o���FD��l�@\"~o�\nͪ:B6��J��G���\02 �� F*�Ov#eb��>��m����\n#p^Ĥ(��@E��ln�nz��R�E.��Vl��[�Ɯ��eo�0ʬhp�\0Lt��-��Ȑ�G�\\��\0жC��q��\$0�p�P	�n��\$�\r�S�t\$�� p�.�H����nT�̞�\0H����1/�O������匞0�_m���\0qu��Ђ����1\0P��mt׋�C�����0���H��L��P�U�{�v�U�R��i*�% ����\rExEb,�\"��[@��\"�C�(��&`�ڦ�W���.K�fY�ѴFњ���\"��(Sd�.�ة�T*��C���VS�<\"Ѣ\"®�H�\$�q-~�+W�Bg\0Xm:փ&��n9b�źӭ>Zp���]\"�c#n�qX��o#�!#�6k�Ғ�.:��(B�R@ǰ�C�&�\r�4��]&�'/�\$�\r�<��|1�Tl#�)1�n��48e�Q�b[��\$�|S��Ӯ�j�'Qt p4B-��S�W&��R���'p\r-�+҃�'��f@�Ɇ�U�)����|F/��)��-�\$n\"JZѱ��D��ؑU&o�#s,�b(�l�M�3|��Pڱ�0%k4m�)f�5�XAgRvrm)�A�|��4��2��5\r:ݓr���w\$d8D!7s�4��8n4���.�:A�<do�1��{���0�1�T�Lr�����,�N�5���S���e�\$�bE�H��(\$\n��d�WƚF ���B9�&��лeJ\r�V\rg�?+8�\n���(\$�T�&��H�e#\$% ��	\"G����\n���p?��#cΨ���B&Z��ꎄ��'�n�s�y3��t�o���o�')(�#4#�<\$�_�%Th��\n���Fm�^�f@�	�\\��6E�\$0<�\n�79��L��%\0	�޸�O4�c�[{!B4bp-�0a\n�Q=*���p�R�X-�\n��-G���'�&(~�0,\r���n�p!2�3Q[T\$5c@'�F''*F��uVpGր�/K��\"�'?:#�\\\r�\n�(2~���Y�'�*��ɤF�T5�\nN'K��^m�p%���\$\"g5�1���eJ�c�1��LҕR��-��^,@�lF\$\$n�l�j��_�4�R�T��2c�?��#�dA��g����{a�bvB	\0�@�	�t\n`�"; break;
		case "sl": $compressed = "S:D��ib#L&�H�%���(�6�����l7�WƓ��@d0�\r�Y�]0���XI�� ��\r&�y��'��̲��%9���J�nn��S鉆^ #!��j6� �!��n7��F�9�<l�I����/*�L��QZ�v���c���c��M�Q��3���g#N\0�e3�Nb	P��p�@s��Nn�b���f��.������Pl5MB�z67Q�����fn�_�T9�n3��'�Q�������(�p�]/�Sq��w�NG(֫K�� �(a���֘��y���2�B;4�B�0�B�(�0�\0*5�R<ɍ0d ��j��\$�{4ȧ�>�'��1�C��&�\n�0�h�\r\\J�����`@&�`�3��:����x�'�ʹ4�Ar43��(���v9�xD���\n���ڔ�#x��|��k�(���\n[��X\$���֌��)��+��<;.28�M�.���'\r�&2�#(�\n��\r�:*��\0Ę��MQUU�\r]T�cRKY��2�%C`�2�`P�4�\0P�7�k���## �	2Of�����B\$�0��bk\r��:�K��:��+\0C �:���:�J�5�Òx�8��K���b7ڀP�4�K��7��&�*�͟k�8�63��.�h[�?7���&-C\"mc]H�r�MU�T=%�\"�E�����;�9���Mu��\0AN|P��{��ZN�����(����\"@P��\\��K�(��4K[\0@��2��MÚ^�if)Ay\r#f�����h��#��CP*�Q!��dSÒ���[�TJ��3'J�^'��bM�C{%5���=���<R3[�@�����9t��0���_)��P9�)|\n���9��<���s���#�=T�1)�Q�Ǹ�6w� 1�,�#IT�'J�7*��̶2M�d�1߂<�Գ���bj*x	cz������-ش �(�	/	����\$���<l��8��\0�B|i\$����úQC/�+\$����mKM�-�0��Hm]d��|������T���Q	!=N�-G��O�7D��`@���b\$��d���key��2J��3b������Ha�T�:�Qcu���3F@��a?��1���H�r@�޿����u9	�<2`�ICB��j)�@�\0()\0���d&�M�o%�\r7̊��2�aN'�.f�* E�\\7�u8�֩vA�@ɴb\\���g�1Q.����8pw��%�R� c�(h3�x�4�ǘ�\\��Ĵg��aL)h	�7��F����� Ih5�؜L	�4�X뷂@�eAF!�d���J1\$IjU@C؊��	S4	V6�D�ɋ\$�v]#�M!� ��q�@�az�?���H���)t̼\"\nNI��O\$��I�nxS\n�-:@�u���4�I�vI��V����o	�H14����>H\r�,UE�2 �K��0�#I@B�KD�̟�����3J��`�n��GR1Bȝ�\0s�f����̤�8!��H2|�����QH'��cA4����a�Y(7uuG�0�R�'��E��nb9�~���b`f	�1�M���ȡ��f������yfg�Q]�b��<�2a�;���Ct���X�z��6�\0� )��#�\$R��~  �c�q�'����Ù3P\0(-E�b���2���	Y��\0\ne�` �xripN\nȂt��I���>(�8_0�TA&���^�͑nY���6���y\rL�E2�bLL�ܐ�Yk[��/�����{\0l>�&%����q�:qHź\\�PFMD�>Y���^O9�>'��w��<'蝡p�C	\0��.T>�i*#_���%��N�[&j�*�^Vy�0�u\\i�J�����P����⫁�Y㼑Cu@doH�+�[l�⶙�F�\0��uU9Տ 2��%�u��)ϗ6ϊu����7��C��غ�c��9�u���R\0�k�6K����{gblcg���a֛-�I�s�4�s\$�Ր)������/�nn����z��Gx*���ut\r*�8��I�y�����N\$d���%T��8���[c��bA�����x��s,�\"�8���J�b�#�يs���i*f/�B�K���ض�>���P�|�2�ӥ�Gg�ϩ��؂�cC���sA�H����<�\"����\"��0y>/k`K�%b��ND.��Ϋ_\r��©�dS�R�>1N�F����k	x�tÕ�*�st��W��%��nƢ��Jt�f�L���hf��)�^IV��\0�OZ�q:O	����HL��,�9��\0�^��|Ƙ�]�z6c�X�� �h��v�������P_F\$\n��yaQk<������/��ON��F�t�@�'J��(�c\"%E~\rm����-���{�x9�\$���lَ��-���80=�Bo0G�J��NKM��m<0���e`��L�M�PV0��L��!�/@���7p�!/Lޏ�����\0&v�Nh��r�̄6K>W�ҋ�S�L=갨`訣d\$���`�*b�&�J������6�	�ғ0δ1��*��\$Ȱ\rC\$|C�KG\r��\nl��L�7�~�L�Rl���zW傞\\U(�\0��ť\nC\nQ\\������p\r�Ran�(q`��o���W��Lv�'�\0QZ��l�ѐ(c�������%��MŀO2�f��Kw\0�1��eG�H�?/�1��ؐ�ͱ�L��p\$�X]\$*fK�\r��qr���\re��jPgq�����0�X���#��\$��)+���'p�8 �=\"�k\0q��&��J;��!��%���p,����rL* �#Mn/�ϑ�Ѝ#�'RA\$�Z�x/cb-1�!��ʉP/�8Ɔp����~��Ǝ�+g\0Bn&�#؎�:�����3P[,R��\$���_�ܱ��\nq�K�vU�+���So*�rT�3,����1P�s��1������,S�2�gf\r�V��@��'�~���7�(%���(�i�6��\n���pl��{b�T��i/E0�vc�8�ٰT'\r���8�p��7�����+ӆ��#m�:��c&�@X/`� \nOY! �ΓV��r?)�7�&���&k� I:�\"ܲ���N0�^	�ޭ\$� D\\`�7��>F<�\0�8\$b�̢�����f6�B(��b��B�rRKv��Cϼ'�:64F�Ps\n1�E#PC�2���\$�4��EI�Q��E�)*���2Oho@�Ť�/��*\\<	7IBt'�\\�C��ਫƳ���L\n�L�����FƷ/�\"�L�\0�+���5�\08�4��B&/�#\$�ދ�*7D\"�X�ON�6@�2�;�F;�4%�pE&i�"; break;
		case "sr": $compressed = "�J4��4P-Ak	@��6�\r��h/`��P�\\33`���h���E����C��\\f�LJⰦ��e_���D�eh��RƂ���hQ�	��jQ����*�1a1�CV�9��%9��P	u6cc�U�P��/�A�B�P�b2��a��s\$_��T���I0�.\"u�Z�H��-�0ՃAcYXZ�5�V\$Q�4�Y�iq���c9m:��M�Q��v2�\r����i;M�S9�� :q�!���:\r<��˵ɫ�x�b���x�>D�q�M��|];ٴRT�R�Ҕ=�q0�!/kV֠�N�)\nS�)��H�3��<��Ӛ�ƨ2E�H�2	��׊�p���p@2�C��9<12��?�b0��Q�ȧ�sֲσT�\$�R�&ˋ`Ϊ\n�|�%��8�	!?/,�n�LS�� �L���� ��l% ��8Cx�:c�g;�#��p��3���#��;�.�w>8�H�;�c X�(�9�0z\r��8a�^��H\\0ͳ|i��x�7�%JC ^-���0������7���^0��γ���ʋjh��#,��!���]\\(�\0��T��l��]-����򽢂����)w���¸�9\r�F��#>�N�(��a�a,�\"����>S\$_�R:�^�H�HH'ixZ�ˈ¾Dd�@�N�#��;���:�����ZMy�R<���C&�3��܏k�+��u\\9s',�̒��w��l��C���;*�	��sm��(��̒�܏��H&f�����yHYrR���sJ�]�B�hX)�\"b	����5*�銥I������^����̪n�+1rq�Q�Z��5W�I����\r�I�y|���J	�۴J��%�޾ԪJ~|�z�����ϭ�{'��g���a�MB ��2R����ۼ�@A��w������C��\r�d2�(��y!�\\��[�����SV�Q�b6\rؔ�4c���&N(�\$��Y�r7&�B)���0lM�O/4��B�o8�7�@`uO�\00033`@xgBA�K�@��g(HEF��pu:�9�����>5�l���i\r͹B\n�������B`�6E�`��\r!�8)�z��TJ�S*�T�#��J�Y�������V��H����R�-m���2xk��_���/�Ił�e���!�Ă����D��uザjMJ���J���L)�\0��\n�T��T�uV�Cr0��Y+H\0�\$�W�\$6��U�t� �������I�5���<�%�N6`]!!N\"�X�J���i�(�y(��j\r>��`l\r��1��|�a!�3K����E�q\"%\"�vN�\$A�p\0�0�igP�!�����(�!ב��,6#y�q\n�H\n��ӗ���#^&���4P�Ț!�H�9�q�A�9������y�N����YYD,f�n��jmLc�]�),��C��O�U����ZR2�\\0��v�h�a�3�(v)Pcf�*��*跍d�)� ���4�[04�.�l[��*D\$B;�*Z���e�T��J�(;�\$湅Z�T��n*���>Q��-T�zFJ	�J��%yh(A\$���X#�k\$J�t�����<i�3# �&�p�ȸ1ąJkR�9��ڣ�N�PHP	�L*T��n����[�?��f-�1#e���Ib��Y;R[���)̒ڙ)�����uܛ�%�CL�<����4���HU��Y@@��f�@��P�*Xa\r�\$4���oe�a��#C��L]�o�d����nä�����p \n�@\"�A\0(�dK\$CUO�������\0R�@ �&\\ߜs�Z\"Nb	�t�DZ����Ť�e��>+[��ZWڲ�̸�73��X�І�%������k�\"6���8'�r2\$2��J�5�\$h:��ׅ�j#5�l���Ø�#�|�R���Z�ëX	�� ��P)sHj��Z�K��iwt�1iEuE?p<���1>���2�Ƒ(U�#���\$�2��\\�(��򰴑]S���R`&�eI�w#�zJ�CS������\\Jo�:�<�&�ڶc�������VPxiL���ش�B�e9ur�n�Ɛٯ���C.v�NHe�i�5�hǹ�c �`���A�:�H�x�I����\$��L�m�p�����,�5ɗ��\$�Ԛ&�V��m�wڛކ�aw�F�%*O7�h{�+A@�L��ꤢN���v�)hZs9�LE�zXMaP*��F�:03�pӸsO)�6Ʃ@��@��W.��a8y�'P@�n�����y�%\"2Ԍ�(	UR�?}�����U�%��B���یf7��U�����k�*���ڏ�?k�4/���n��\\@� ����/�x&F�O�A���ʢ]�4����~&�R�g��6M-\"���i���.����ï��O�I����O�ǃP >O�/�3�Yp^��~�+�a�BЀ� 	��Q�@F@�\$bJ�.�,)Nkj.d��F�O��ni����斠�v)\rH��o�~�D ��0�����\nv����_!.%�r�ߥ�έk�~,��C���,�C��\$?/�[0��J�O*�\0�ƄB�Vm��K��0��L�q��*�d?���'�.0��*���n��\"�o�oP����#Aqx?�M�pbdw�l��:*#7�k���c:��M*�K<��O��N@�F@���8l�l.�@֧%	�Dۑ�[\$�Q�&1S��/���z4�p��>�f�� d-�)l��N2��g�.\r��m�|�t&�G��Ͱҧ�%*�ǔv��C��M&D��2k2pm­&'�'�p��l.��5�}(��*���\\{l��\"�`>P\$��e�\0B2�q��1�+0��R~����pޯ6&�(����,�\\R�\$R����)o�,04<,������C%�E,n(���R��!0�\$�2�^Q����l\"�@�D��F��:.�,�B��>������\"q�2J�R�J*��s:*�Ve�!61 F��2�ZpF��r�P�LDl���ۇ`Z�!-[7B0��0�/3c��N�9g�f�x�Ʒ��m�-�p��xu�Hq���Q1CI�F�6֓���k4�?i3��(�������u��O8�S)1q�2�`k���\"s�B&��.�Q�ٴ��B�5DGL҃:{�L�V�HcXy�G5�����\$�:J���}Т���>�����-T1��*�-3�cC�yIRJg(f�Ji/����o�[5�P���u���Jw�X���h�M�M�����l\\����v�K&@�B���I�.�iP�C��2��\"N�AMK���C��\$@Hwt�8��u���I!%�\$�3Bf�H�\\&u��-u[VU^U)��\$�U�R��2�K�>1�-JB�D��n�ڒ�A��O`����������Zb��\"<�_[�a���.0�i3Ԫ���t\\u���=�oB��ҿ./�\0p��u�`u3.CL�/�`5r�`К��\r�V�5~�.��T�*�n@��`��ֲ��@��Z�+�Bb����Z���a-վ=�g_ϣ/�+fN]]m)5N���β����v]\0�\r����Y�>.�9)��cM�:�ޏ���=��_��b�!�/�s.>&:�j]6�{��1���\$����ϳ�,d�v�0_N�6�\r�F�2��\"֕p��{����4����1�AvnH�f�\r�q�q�	�d��+�q�~e��w��;pwO)�֍nj�����uSΕ2\$��5��0�B\0n�Emҕ]�CI�)8�D?�83��iyt���*@�O����Y�um�'ho����3�Z�6�t�q���j¡=�sr)A:��/B&���v.&E\r�4�64��ʠ�8���P�m~^cP�����ǧSj'�"; break;
		case "sv": $compressed = "�B�C����R̧!�(J.����!�� 3�԰#I��eL�A�Dd0�����i6M��Q!��3�Β����:�3�y�bkB BS�\nhF�L���q�A������d3\rF�q��t7�ATSI�:a6�&�<��b2�&')�H�d���7#q��u�]D).hD��1ˤ��r4��6�\\�o0�\"򳄢?��ԍ���z�M\ng�g��f�u�Rh�<#���m���w\r�7B'[m�0�\n*JL[�N^4kM�hA��\n'���s5�dy�mE8Y����e*��	���(�8�Ю��\0000�R:\nX�0�ɒ.���h܎���6���z�(감4(�(9���v֧���A*�]\n\$�9�p@%#C�3����t�㼜\$Q*�(�8^���9��(^)��0�,&���px�!�h+!`ԁ�\0P�4�j�9�X�:������A�C\\��\"p�/\0���l�����4�AM#�X�7ů�<�\0UF6���&���KC<)\r �\rt�:)�o3&2<�\$x2�ӈ�è�?\r3K� ��;�������	���/C���0VB��<� �2�O(�];(�:'�\"��d(��O=��5��9�p��)�\"`Z5�0��7��X�;��BPiLh1A@R���ݾM��c�FB�Aķ�Bp�0�&,�\$�������l94�ÒQk���\" �X3��:<�:B;\ndd� N�8#Z��F3��Gx�-��[�K��'�c���kr��8�<-����V��2�(�0\r��(���x�3\r�\nh&A�L]d3�\0ڍ2���#2\$�52ac<[H9l�0#ss�SҔ\\\"7#ɔ��A-���7΍�ͣ]t=Kl*��T��)'\\ӱ�%�\r�,]�.��0�^�����ys���#�E`��J]e|)M���1��!Ȳ8�\$�rl�(��t���p/��Y����S)�6� ʖ�k�2D��`�h�0 }%�WU�S�&� :�`� �\r��&�`���� B�葒BJI�8;�Ȕ��rJ�aw�R�af�����<`�`>kH��*��I3�~\$e�1hZ�B���紀��� N����p�)4^�ܥ9�T�Q�8H�,�ֶ�<T\$��B����R\n����~���-�\0��Z��B�1\$�0�8�H\n\0�L\nF�ꃛ�'D�2`PSIIy�}|H8I��E��D5�@e�ɛ\r&\nO��v@�b'F�R��fzs�-�5޹��\$Id�y�R���UQ��U���f�a�*����E����\nȮ�O�\$��0��4�\r�(��\0��K�%&9��N*xЌ���^�c��'�4��H�e���m��9�p^Pp�t�S	�Om�ve0\n��}A�[�R����)�)A� dU�X��`s\r\$���\0� -F���S��Q�M#�/v�@�X���έ�����2%bF�d�(�	�0b^ �i%!*�sY�7����ʃ�B�Fl�1r�tI����T����B  \n�@(@�(R	!8#��B�xR\n�P �p�j�j�T�|0�`�o]Tp���+t�O9s����{��h��:D�d;�E^HF��\\���>w'�0���� ��PU�H&M~��w��oXב��FůB\n��@�X��3ܽ��BP[R��DE7��%���@�G�!eHm��T&rRZUR�\$�p�G�k�*�(�.vȮp	p<�x��M\r�.	�bA��1����&�aEUIēcr4\\TAȹ�:_TIe\$�nwE���d�!*\0�9Q�ܿB�\n����#��x�tL�#&cBƑs�A��Qu(;?��}_fOmH����W�aVD�<��w��i�\$��i�H��\$1���:M1]d#�n�Oj9~F�ۻn��jj{O�\n�Z�Q�5���rn���)^�I3��Oh�o�5�v\n{g�ϣ����a,�����\n�)�w��_�A04��{�	����M^{2�T\$��ɱ��5���\n�qW��5��\\S�yS�ビV�[~�,h�1�P��E�\nF^�w{��5��iH�^~�a�6JE�B��m9V'Q�t�q��{�4�\\ϓ�S������ߘ��O�m\r��hU�I�׾\\�Рd��\$�嫫��oO�]�0Eb���\\�/4�LW�����1\r�\0�x�0R���m���q���:�缰��E��E����D�� ����a��U�wl�|c<�\"@�\r��o'O�M�B���\nY���OE�=)����Ԭ�&²�[���s�|S���\$i�i^{���v�'�v�\r��ù,�)񰊤j6�)��tU��!u�5}Hv���6��GNa������i��,m��a�\\������	��h.e��#�k���L���(M������B����\$荆�&HP��Ϣ/�\0�0FB�\"��',��m�0FPH���,��l\ndZ[�\rE�\r\"�5aJ_�4\$�v��:#����2�0���\$�/V�ϝ��b�}e�Z��c.��:�@�e�Y���I �/�\0P��>��WBt�\0�����A�0�,���8�V��,�p;-�l��X��i�\0002e�\n��ż<G�?q @�q#4\"�D���C����\"�\$z�L|P��U\"��	2C�\n�ɘ[�����ؿ���0{mB���\r��3�_n�2b�'j@S�	#S�����nnxQZ�p�R1�1Aju��&��O`�`�q*�)�\"�Xg�~0\$5)�*��ڞcP�\0�\n�\r�ɞթ\n��ұ&x0NUmƭ�\"i�\"Ͷ}�x#�4X����N(�n�:�c�����R\\Ģr�	������ܥZ Dh@=��0j�'Bj/-ā�'p������E@�ax1�t�2��Sr���*-�	���\$�4�.�С*�7(/\"r=,����\n>�t����>���b����i���������cM|ib%�.2~0c�.�\\+tɢ�:�_*�|0k�+�xF\"���Th�4+��2�\\*e⒤r��1�\$��'��EU\0"; break;
		case "ta": $compressed = "�W* �i��F�\\Hd_�����+�BQp�� 9���t\\U�����@�W��(<�\\��@1	|�@(:�\r��	�S.WA��ht�]�R&����\\�����I`�D�J�\$��:��TϠX��`�*���rj1k�,�Յz@%9���5|�Ud�ߠj䦸��C��f4����~�L��g�����p:E5�e&���@.�����qu����W[��\"�+@�m��\0��,-��һ[�׋&��a;D�x��r4��&�)��s<�!���:\r?����8\nRl�������[zR.�<���\n��8N\"��0���AN�*�Åq`��	�&�B��%0dB���Bʳ�(B�ֶnK��*���9Q�āB��4��:�����Nr\$��Ţ��)2��0�\n*��[�;��\0�9Cx�\0��O��2~)�#��6�nz�Z*�ʜ��Ӝ���S�U-��I\\����B�F�@�9��2/�\n�)IJ�6l\"�D,mE�ȌM%��YVA�C&E���\"�l�U�B/�N ��l�3� ���cx�(#��g�#���r@�6K��4�@;�/˹j<��;�C X���9�0z\r��8a�^��\\���t�MC8^2��x�u]����L\0|6�O�3MCk�4��px�!�\"4�\"�T��)�Ju6�)M��4��[��5�K�cq���`GU\\�'\r�wŐ�Q�jS��Q�wM6�ʚ�A���8����b�,�62��h�����7[IJ2FZ�\\ّ�N�����eK�QV)m�1�\".��3Ћr��)��gґ�mڢ\0T�8�z�#��g���:�����R	Nf����#�p�:drB��*�g�1)��3���Ϗ�47g�/���O��F*�|k��u?#�﵌����(D�EoE�h�+G��찀R��'Q�,P�Jy�wn�6�{	\0c!�\"��RK�7�d��u^�;O���s����-Ao�ƀ�߁�i��%7\n���m�5_�N�SW���֓k.GPѯ�Vؤ�X�-�(@��#U6���MCo�F3�]����p��h[��>na�x|�`��:���=�K	T(	&�i�g?]I����\"S�!hp�.Dg������U�G	DU4�h�U�@�왃�9�`������<F\0&���Q>�VP�}��h[I�|\n�t=	%C=q\\��:��OM�^U���XE!&;G����-��E�W\r]\"���A� ��U��QF �#�P�C,�5@)Շ v��v<A�3`ر��qM��6@��+\r��;��V��Ψ0Β��Kq礀AG���0S@e4�4-^��\"��EQ�n��HR�`�c1%x*��|�r��e\$���S:�l�l�E��N��^��}/���X魄������d,PV>�Y%M�\0[��ԅ�I4�3���w��\0��%��\"\"sAsS ��d=Hd�H� \n�_���B\n�M\\'ޡ0���jf�4�@�`j��^��|�����w`k����c��1l�6*��{(F��J��� ���+�C[Y��3Ы{Z�mD(��\n�@��H*V5����Y�\r/����+OA�Z��2�@�C⹁ʠ��C5�ZF��8E���?'���\0�<\0c���4��vg�|m�hLKip�@�td����2c�\0\n\n (��Kp����s>锤L�f����w�h��S�zOY���i �|��Z��7�|�/'�3��J4�n��ؾ�szo�U=L�h�-���ֽL����H�M�b-�;���.i��_x�PuR�x!�0��A�0�0�]:��f�Z�62�]!3`��ú�k^�j��ǈӁ�#�P-�2�\r-���l����4Wmё�V��yM�EO����D_x5�~AB��Xc�����e���f�*��Zi�[]u��S'Ri� �I'qd���yz�\r���]�v�4�ڍk5B͉�1�5�~rK=�vT������L�\0�¢��^n��4�U\$���fF<��v�-��	��8�3<�� ����6z���&P�gC.;<8�m��:76����&R�P�*�Ϲ�8�� =Kt#LK��i������;��`�@��u�qYg:��.���o	�v�� ��P�*^��� E	�¸�@�y~��p�^�f|͎�r5�����(��*U�y���,NŦp�/^2y[�v�=������Zi�����B�+��/y����\nE��	?�]�O��-!�B�d;�H�έ��=c4�9IF(����c'����6D__t���'�=]�y�T�H���x��jDv���\r ۨ��o���>/.X��m���/���\\Ƈ\nׄh�찐�ʐ��sD�Nx�~\n�����=� JXD%\\%0\\,�@���5n\\֊�snh t�#�M ��`@�lJ��hx)d8���Rx�}#\0l��9�DA��\0�^��`�R����P�����f��.\n`�Gb�N�gb\n`�=�~�g�*8�\$�����o������Bp��nkh��G.���.�pLR ��il(�g\":f���*���.��b`B����1�����u�j�n��Q*��B�o��Y\r��\n����8Q��	IM���N��jV�rEhx�`�q��f�\nJ��Dr+�,hR�B\$����:>�R,��\$���/��`.����/֝P�D)�-	\"��mo=h�Ì��� �	\0@��\r%��L�Z\0�ȣ�� {������\"&�qCz��#f���X�/�apF��1A1.���ʎ^�����DCM^GF����p��D�%�prlrm��.����	)`\\,�F�B��S i3�q(�����͗)�j��)����PlHW(�y)&�Q\" ��\$r����␘ѯ0\rgR�����R�l>;2_+g�2���,\r�,���#,��*�&��\$\$�/�����p,F�3P�5M`�r��4�](�28���E��熒Mr惪������'�(��Ӂ5�]��8��8�NB�(S�(�)MY*��K\r%R��6O�)�h��m�%l&4`�ONv�3.\rq9�[7��.�f�m4*qb�e6�Q�,�z)��3T�s�eF�A�4�o<��&����Q~�	-��e���D06bZ\0�=��P�Pp��-Jeㆉ�\0��0��`�ORI����9���m�~�%5�,��b�s�C��HҬ唇F�I�Ώ �N�eQ}I��B�<�q5�KCM4�����/3����ԱѶ�TFiӽ/�\rMt�\rSGM��H�V�OJ����M��*�Αh�����Ǭ =p7�؋��!��N�Run��s��2r� �M ��B5@�A��J#R�Z�rsU�V,� ��%sgQ�����ދ�[�k-�)4YN�j��A��HU	X�c*\r4���u�\0�+��R�	��F\\v��D�\rWUm�Z�2�u��o�O��I�����+�������ڭ�iNM�*�1OJ2�^X�\\��lmȕO�;PN��_�u_�Y��Y�]a\r�P�z+��3�T����]Bs�`��P�1R�\",�-Ra-�J�VY�:��MGv7dr�m��ӱ�^�L��]^�0�f)��\"q=��\nuCЂHk=��%2��.�&�S2nU�b��`��:��m6=VAK�0���UV�!�btzմ�b�����LTI�q�cX�q/��Z���If����d�ni�Z,�adI	����g|�`@�r�H#�~h��A��6��rP/����W�Gq��D�gs�sQq1c�L����q�t�w#W�V5g4�V��SO5� ��d�W�Wj*'Љ�Faw��n1nu��)U��`�����?��H/.6��ׄ�ױx��1z�t�@qiv�k�{V�-��lu���yy�	`�dx1c�~�#!V)K�+LXE���0�ԧO��t��b�E< b~��\r���T�e�7t8-n��xsgxv�8|��ON(um�C�����7�\r���7}Xi��!n��a#���#�e:w\n渟��Y����8�8݋�e�쭅4hHQ�O��;tr'�RP��/�mh��tJ-��T�|�\"���Q������iV�e;��}vaN��L=h�i2�gӈ�AfX���e�*ߏ�È�-tXDw�|���l�2�&ykW�Y|�fC��!!i'�r�)ٌ����%���,�';��%~�m;o��xŔ��p��TȐ��&Y��Y�d6ߕ��ы��XQ��a;�˅���+\r42e�0C~/�Z��!x��W���q��a���R\"'�F��j�>�mxU�ɵ�P8;�q�p�O>y�.xW�Z\\��� zIek���z��Y���\\+��Mv����E��F��}0IX�'�O1��7���ٷu�A�O������@�l�1,��B�!S�.o�5ZE��6ȱ/��E&B�&-E��pg�(:�rZ�5o�8ҝ�������Ύ�_k�M+߲�o���<Zog�U������i�\r�V��`֕��[�Zu�� ���̡��+��� ��E���<\n���Z	^�n~I\"���ǝ,f���W�1nǋڽ�9�P{C���egѻ1:�kt��NwC{ {��3ch-~�P<��S*)�ҰF��5V�U����ӷ���k���M,J\"�&�#}�W��G#8y���A\\3�q��KȊ�7�w�\"Į?m�;��	���F2[���3�[ZC�T>�6�����K���=��r�7뭮^�h C���w��(�Gz�R:���PƻL�����ۗ�fkZZ�U�����+��m�m�ˤؑk�ٿVՃ�u��݄@��C�;��Ϡ�a�Z5uu	��ˮQ�1y/�����Q63O,A���������� 	�Q�?A�zI%�zQ��ZS����.������\0�^�cj�)X�O\0���\r����c�d�'�����mƆ�8x @rG��8��*0���v[�LU�5yqOj'�\\�>��w��I@I�e�k/���P����>}�����/�JP\\P`\r���>��-cз�n�b��=w�u�}LD��	\0t	��@�\n`"; break;
		case "th": $compressed = "�\\! �M��@�0tD\0�� \nX:&\0��*�\n8�\0�	E�30�/\0ZB�(^\0�A�K�2\0���&��b�8�KG�n����	I�?J\\�)��b�.��)�\\�S��\"��s\0C�WJ��_6\\+eV�6r�Jé5k���]�8��@%9��9��4��fv2� #!��j6�5��:�i\\�(�zʳy�W e�j�\0MLrS��{q\0�ק�|\\Iq	�n�[�R�|��馛��7;Z��4	=j����.����Y7�D�	�� 7����i6L�S�������0��x�4\r/��0�O�ڶ�p��\0@�-�p�BP�,�JQpXD1���jCb�2�α;�󤅗\$3��\$\r�6��мJ���+��.�6��Q󄟨1���`P���#pά����P.�JV�!��\0�0@P�7\ro��7(�9\r��Đ�����Z�Ի�b8��+�q1�a8�0�¿�/\nzL�)�5''��Q�� � Si'qyJ�S�{J���7(��\\1圔���m<���W;CN�*��� ��l�7 ��>x�p�8���1����3�\r�A�����L�گ��9����4C(��C@�:�t��6-�9N#8^2��x�uݣ���L@|6�/|�3N#l4��px�!���,,X���y\"mӷJ��!r��i��J���R\n4`\\;.���8����/�iL��Ǝޣ2<R[O�e=#\$Vr=��p+�#��m�iȓ9P�]@ 	�Y,É�hFP+�R�+4�v��3�qI�%Ɓ\".	ܳY-�sm���<Y6\nں��	@\"^��6Y��6�.�����.B�1Gq��\\i����*�ث��\\�.�3��:D>���%Ǝ���|9Vũ�a%QZ\0Q+5���󺞧z:{��qc���R�|�浗����ZB7F6?�c�a\rψ\\9*QH \naD&5PR��+������Z��jȈ𩳄WYj�Q�%�0��ѫhb\0��Ǌ�<TŢ���.cĘ�2��z�{H�QC��\0����	?%l��3�;�#3byQ�Z8F�0�\"���ވj���@����s�%���m\rѴß���(x@��m2��\ni����(�zHH�AH\$�x;�e�3��߹�� 5*��Z�8D̃4��� rG��6���0f\r���V���Whh܅@�|��n �:�����ft`�6�ΕC����a��R� � ���`�P((`�Z��p��<�W�JK<֎�,p�m r�bh?�p9LE���ěɹx8�C\"�^G�z�u����`,\r��6�S�*9�F@�A>��}+26JV�٣��/9X�\\6\"�K�b.%),p̵S��T@��/�x�\"ǚv�ɷQ�`&�\$?Xxs]��6���W�d��y�����`;�F�p.a,-�ǘ�i	!�8����&��Wu����}!��,����\rx����f��ʪ�Lj8�@�T�g�.[���2@�Cc�G�Ç)�]�xa�=hLu�T�S���=6.T��s5�:�^K���P�#�����i����)L�Lc�-�����;.s'o�\0�\0(1\0�!HOu���%��%�QU*�(!��g��>G����[z�A������mo��^����I���>Z,�ncG�o6�&�l@�h7	����m��C��C:����W'n��B��K\\  aL)f�uK�IX[C˪`c��B.�Ut�D���=�\0�\n�׽��)R��n�X,-�,�Wf�\n�l.��eS�sN������S����;��^:���IC��Y4#�`A�^��ԇ5���m�5�db4��j����c2�C���'�h��{�sE�ȶB�i�6y�����U*�\0��!ĳ�Qh.d�`��c��u|א��c�Z�[��֞}\0Sb��n�<a�` ��u�����M�4�E�6�������'(	;�;�&j�������8.�\\!E2�p \n�@\"�@W\"������G���y=��=Z^��sN\n~����]��Y�S��C�\\�F���B�Ӹ��kA+.�B���kXCJC�u���a�K�0A��w�\"09G[aB<+�2�\0��Kj����*YrsѮ㦆ʒWk����2��Zظ�]~w�o�\$̖�;^KT����IƖ\r��ԓb\n#\$'�>2���.gV�\"���� �׀�EV����&y��zҎ�3mC��gē�wL!^K�3�CӪ���p:�R~l�\0�ij����,�Ko>�;��M�Z��-��\"�L'cW��Q�s��P��%���p	�'~���ò�������qm�e�f�� *|਄�²B�~o���O����&��>��?iTDF�<'~vo0;���DM�1�Z}hԝ\$^(Ì��nE�V+��q\nn��\n�l��F����� f��Kރ���� �	\0@҄�\r%���Z ��D\n�Hڇj��\"1����yI�\0^3�D����Wbvi��	\$�&pā�8��wx;m��̃�~)�~������\r�L�-P��Z��j�e��	&+��O���(U�o��0�	��E\0	��\\�@N\0�m��C�h+e&�#�2���}�n����1�p΅K �\$�	��0D5�|p��p�lF�\n��E�߅��g��ȑ&��1�D�&*3���x� ���9�v�B�p�l��~G#��-\\6b\0�3��9����N�^�!hhf���D�,9B�B��k\nZ|)��\r����b�H\$Ǧ�B~-��<��V�d;�\$2b<hy��c�h�ez��&rQ\"�#\$P�/�fҁ(�����82����쒌��R�o�'��1r/�H���e1�|F�s���� Ȭvd(o�C�h+fj{�R����b\0PQ��G�/�r�0�\$|E1\0�.�i(8zSBF�)�32��H|���.o�JI�N*+��\\���S+j��3,Ї�K\$�e*���(.s\r+�/6�w�M`���ʦ���7���S\0hT�9��8�99	C2s\\9��3G�y��'��i0/�h?\"_f�;p�7��� Id.��PD�U�HIN�7s;@��:T��;05ƫBҝ\$�@Non&ÍBf�\$���#Q;�N8���&����H�s��,sLDG8{'fɓB�3<��;��j�p{�w�<P�H\"�3IFPT���E�s�c�o^oo���ly�=�׎	@����L4j�Ƕ�t��K)\"���4i�G&�o4x���i�*h\$D�hSP�WA��;�?N�7�Eu\0;�LlT�R.��14��rp�xx�� ј��E\$ӧ3�<�\"�T4�A\$M:�:�3Q�SwUQ�[R��R�T�Ӭh����S�V�E�NkuRk5��U�<\$�e�|��:#h+�̂�X�jh��g\r9C�ȗ[5rR���`�]�'uI7Um]��]��5PӱV�W�^�<��ң9tSC�7�)XU���@@ܿC2U�Vv>��[\0��ꊰ�<���\$�vE�N5c�U%�9���pۂ�F	4�뀩�A`ssAumg,agu�L3}a�-2�gE�hp�*URV�ah3a�8���cҥj֕1�DG�Y�W5�T�9	R3<��P��jB�+\0��	%Sm3	��V�J�6�Q6��gm��	�\ro=3���\nn�bz���P��\\E�k>Ĕ�oW2HB� ���_�� �6�fm�yG8�r�[��s�+1�a<rG^3�9��?�[oI\n���`�m@\r �\rm�1S8��#R�ۧ�݀���Ɖ�\n���Z���[q=��?5\\;b��WD�mY5����&�N})��5f�@	��{��(�^R� 9��.gy�E�n�hK4��UN�S��9�Ҩ�XrV�n�fN�2b�+\0�\r��c�[�xI{�p'�gη-,���nw���C�\"t�.���u�a\nh	�dD�����i��l}s�HLg!*��;x?6ԋwإ��K^6}_�����=���`�a��\n<Xϲ�h�(��F�P3!]��D�+��v�v�?Ks�����7�5�QI({13���5��|�q��DI��h����FKn|f�\nŬ��\r�\rON���R��B����^'�=Rg�#�N�����̇ǩ,�l��OC��E_1�r3�p��5�8����N�F/=�έ1\"J��\r����@�y��&+�kU�轫��vgB�r\0�	\0t	��@�\n`"; break;
		case "tr": $compressed = "E6�M�	�i=�BQp�� 9������ 3����!��i6`'�y�\\\nb,P!�= 2�̑H���o<�N�X�bn���)̅'��b��)��:GX���@\nFC1��l7ASv*|%4��F`(�a1\r�	!���^�2Q�|%�O3���v��K��s��fSd��kXjya��t5��XlF�:�ډi��x���\\�F�a6�3���]7��F	�Ӻ��AE=�� 4�\\�K�K:�L&�QT�k7��8��K')�NgI,�n:��]�gn|c��7�+%��1>ň#�(��Ħ.8�0��� ��܏*#x�9�\n9��������Ɏh0�3���.���H�4\r�.8FC��`@\"�@�2���D4���9�Ax^;ʁp�\n�H�\\���zbǑL~9�xD���jJ� C2J6�K��|��� 2�`P�0�	�X��֏@���ȯj��*cJ�:A+s�'���IҢ�\rl��b��a(ț0C�UU��R%�듸*/�����h'��|�J3��.���uN���)υ8#8#Z�6O�UF���c P����#�떠��(�=^.��4�-H�ϥ�0�����R����lc�8oȦ(�����P�>�-;w<��<\n�P��\$OҎ��O\$Vu���VO����I��T�d˔�2R��TBR��\"ׅ\"I���(z6��Y�ٙ䙨�OY��\"@Tg>��S�(����\r��\$�[t<=�r��jB3�NeTR���ք��{=ObB�-�4��-�ϣ�P�::f���hɥ��{�I?,�\r|��0�\0�0�O�b��U�Z��\r�.� ˥\0�\r��bU�X��\r5��\"�`�����.<�I=���P&�S=p�ྨ�|��05���|���2ͥ�fv5�u�t����H�D�&I҄���VK\\�%ļ�y3m*P�`|�@�,��8'k��eQ��7'D�����j�\r�rf�C�B\r��:bbS�#%��G��K:*��#�b��Gw0�2P\\l>�\rZ��	c�)%%�Ԟ�R�UJ��\$���+p&-�&p��)L&q�A�|�+ \rЁ��:�ԉ�4�&��|�3`d�!&rhCk�3�6r`G�ɮz�}��E�|!7,\\S��|�փ�y`�4C���!2m�p��r�4�I\r��B��P	@�Ku(���\0�����Rz)�8�2 ��ʦq�X���ݖG�P*��E��݂�\\�ưH	�q�9cG\"\$Q6\$:`p��lC�\\9��&fH7M��8�\0 �1���a65�M0��8GZ�zwL0�F���Ϝ��r\r�,2���a��'g��v��c�^�Ag3�ZYd��2B��T���r�\r�I<p��<���<�#\$m�F����1�D�f��W�\n<Y�ӈwR��y/`(�\ri �\ns�Z���wED��SK�#��p�C9�jG��Ce���MP�R^F�%ɵq�sɳ��\"L�U>�B������ >=�5�k�#\n.Bv����-m)0�7�B��Re8+��P�*Y�rHyG�@@*��d������\nnu��0\"�[�vC5ۉ8�q�5�V�����e\nc���W����]B�\$阢9(N	×�(,�[�P�e�A��`p���b�����Q� O��M�A����mg�6����@��3G���\"-C�ih�K(k/j \"GR�\"���6���eԼ����ra���39\\,�U;��αp�ː�nS\$����+U���ɩ�48����A5��Bp �*\r��-��nQ�Y�09KC����umLW*\r�ym8\"��͑�-�r������\n���i���J'�BN��+k��\n(+�v�\r�|	��K�g\"v� ׍I�;6�Aa �E�%M��9!�t����/����^UՃ0����A}^�U�]A:�75.]��\$�+�������0�}�dw>��rQ�o�O�3�w��pcY�x\\{��ʶ񅐩���3���R�IՃ��RNJ�\r���(�[Ӄ����8���s���y�:g3��5���aI&�oK�>�E������ⲹ�{���;y�;�P�Dy��d�#&��`�C���a�+5Y�E��;?�y���C�4�� .�Y(�7\r����@���I��\n2pə^��	�l���f��1��κ~�ʞI���Uv0��5�~SR�YK1g=գR�� md+`i�F�^S/��(�5��ɓw��?�����C�THJt�x@J��jpfuX:�0�z��f���^I�Z�?�G����J��@���d;(��lP�b���[\0D�%tHp\r�*nt�`��PnB�J-�E�s�?!b�:��&J��&;m\\aOP�/@�k�խ^����q+��b�/h�-Fp	���\$V�PE�D(iΆȃ�:jD��D�	\$�	g�?��9�&ò=�\n	\$�>Ǩ�Т�b�0���B�Րd���t.F\\e���RR���\rbQ- Ѱx��@��\"Q�k�Ї\rp��p�͖0Q�21�~ّ/H�&U�[�F����Ã��pN�-���#p\"�Xv(�@�G�a�O��&Y�AQW\0�J[�\"��>O�4�\"D�ш��[C��\"WQ�=�s�O@�b��`�\nmlX�6�Mo���o��E(�1��-g��Qn;�:>m��o��/0�2	� ��h�jl}��?�{��D��@O22�k��e�ݣ2�cr�R,��w�x5�F2Ǐ\$�4�O�,?łϰ*UM�[�0U%V���n>�pZ�o@؇��\$#.}c8 c`%�.\0�r��e�/圭���\0��Z\$��l\nɨ�a'�&����)+\$�(�-��8��j.ln&\n�\"R�h&ql������mQ)������e0cfU���G<}\"�|�+�\\\"�Vu�/\n+V91���on�0GVk�^W,�@�O��	�Ȓ�%2�	�2�17j^�h^�e6�_�_8�` k�\"A�B���!m(.P�7��Q��P���	�2\$��OL\n6O��*�:�S�jo�1 ނf8�.o��|�lW��R�5��5',#@��Ô�.����@3�ig&10�xТ�(s7��-�4V��?��k\0�#��R����qs���^-Ч�"; break;
		case "uk": $compressed = "�I4�ɠ�h-`��&�K�BQp�� 9��	�r�h-��-}[��Z����H`R������db��rb�h�d��Z��G��H�����\r�Ms6@Se+ȃE6�J�Td�Jsh\$g�\$�G��f�j>���C��f4����j��SdR�B�\rh��SE�6\rV�G!TI��V�����{Z�L����ʔi%Q�B���vUXh���Zk���7*�M)4�/�55�CB�h�ഹ�	 �� �HT6\\��h�t�vc��l�V����Y�j��׶��ԮpNUf@�;I�f��\r:b�ib�ﾦ����j� �i�%l��h%.�\n���{��;�y�\$�CC�I�,�#D�Ė\r�5���X?�j�в���P�p�`Ͷ�Jb��D�b��d*5\"=�[ލL�����Z\r���>ɿΩ�2\\�J��hq��\\��V^��0�.��.��P�2\r�H�2�K��9Ţ^媊y�J:�D�����%rc���d-6���k2��xX�@4C(��C@�:�t��|4%\rD�x�3��(���9��0��K8}��1h�[��'B��/��|�\$��i\r͈�Ħ�0�'6\n�V�T����M�#e���i�jLX��tWr�4k\0��Bb�K��@�J�R��D�`J2Tk^��L�e�F%�_��e,)�#�hH(�D����@�;���K#D�>�hw�f�.8��l����70j0j�65^ӻ,���|L�E\n�ܬƯ�4R5hj�s�L#l���D_h����`Zݡ2��G�2�hæ��͈~-4\$I�&��\0�J���!J.�8���!z��z��n����&�B��&k�:fA#Nls�9m�S!Г��8'~��p�O��vo���.B��B� ������)��F�N^�+(�T���(������m�i�%^#�qo\0h=�`�^*�6'��1W���z�!<ڻ�pD\r!�0� �K0s,�;�#Q%���貃�i\r�9�Sr����ЋPӑ�2&L@��8���N~d�ʝ��4�i�A~�v��rm'\\����CaV6�IP9BhIދy�����TzSs�)ɔX�W��x. ������;C�4��6B�3(|�]�`\\^de7q��F�(p�q1�\n����AP�܏=@X��Zt�+�A�9�+-�d����\$JLfi�U�F�A�z��RyGiE\$�\${��~T����)����E=�-E�aB� ������%�.��.Y�7E\0��Q|�ƈ���5�u&r\n��\n\rB�p����GrE̵X����V��\\+�x����XS�b�u���\$3!�,�D��1q.��].�ԐϺ:ͅ�0H�T�\"5B�2�G�\nA)�H�Ɯ�t�����GL��T��K,�����V/JY]<�\r:\$c�W��g4��A�z�Vj�[��v�U����c,���at0��Qk\rLB�)��5#�`|A\$g7p�xO)>JL�|q|�7���R�'I@�R��T��s�YM�&��K�9�<KN��x����2&�cX|ʍ�9EС*�lT�][�z�Lb���H�����N2�9� �:�H��[#\n�Բ�T��s9b^��6h���|��\n (۱^��1��pSaf���qT��!���UJ(� �k�5�[�4��@\n1-�#�cRmTT��K\r�gh�_]RP̴(���!ICo��s!�R\\�ع��JL��H��\$�^w\"K:T4}6fI���>.�P��0��1植^R���]'	]�E�-T�m<��4C���_��Pŀ'����rA\r�f�����.ﰕ�:��R�WM��:��-����<!-b��Xi�p��(T�M!7�B��)%qAN9�����(6U-�F#�#L_O:v��.��!R��;�,S�N��(�<���5}PtL~�%P�;hѡ��p\"���qb�^:�l�/�Je�N;t�����&�=d�:�%�l]\n�R7Z�x�X�{�w���P���.o��W�p�Ս�ʕ>��d�5r%�c`R��ה������������G2�4܌�r'<4P��ה�ԗ�J/9F��ee��*�b�7�r�@IZ�1)�����K}������A�r�|�I���5!\$�ݴ��&ۺ�\n���M]%BZ�J�z��ٸ�-nb=�^�N�}.�����6^r��d4�Qf�]�q?�ԔE姿�b��\n��w��m��a��,�m/d��_��cAH7���V�u\r~���\$����җvD.�ôg���-*s�����\r�y7ĥ\0�2��ʩ7}���v�;�s�܆��KQ|��_;�@\r�a��Dl�\nm\n(��`����8O�u�t��l��*2&|R�J��s�O��ܪ�c��K��Ihvg:8\$�ŬL^&�-��\\�>�d�*���4���X����Z��\$�z	�Kk�5�\$���\n�� �	�G|��:�E����,G�>H\$*w�8^FZ���E�aE�f#q�s��\r,A�6w��)\"N�\"�⃲�g����0�q\nH���	�_\"�p��fj��cJ�k�OO���ВӑgeA�q�#���9�և,�w�~��4`��'�Z��1�l�8�P�#�p�ӎ�\$�_koQ>ð��Vf�2�0��sl:챆6�0�6���7�ڗ\r�%��|m��w\r�\"b0]Q�,qX���l>��J�m��-�v�'FJ�f(Q���2*D�,1�>�Vc��H���L�G��GX^�\0��&f��2'�4j�\\KNF*^����&D@��D�Ȏ.�gN�,8���K�^�p����&�(X�#B�,b#�\n��/�b�o(DRҲfgҐ4D���cǰD��*���Q*�n����T����\"����%�*\r�Nf�2lF�w�<����>m�:���D,�\$�e��z#CL��&['���C+��%3,��k�Po��q�B���2�*ǝ)��*e2{�tǼ�Bd(�@Qt)r.m,�`IE:��(``�e��Istlģ3�|�|sOms�7/8��7��,�4N�zS��ӣ+͟'2���\0��5r��h��1\r.�hUq|cd�8ϋ43���A3ʵ���i�,�*�=�e�x8S�4���<��?S�?�a=k�=��I���;�E8�P�p2�.23���'t;�����IC4'QyE*CC�Y�3�|SEd�lL0�k8Gp,)��(F�c+�S/T)�<L��8��~b��HIH�~Cp`����q�J���\0thn7�2͊r��L�KlH+����>n#EQF�E����\$^�(�F�{�}P����#�5�FL����>S�4O�R�F})�9R�S�Gk�Fh\n|\n�BKS�����=D�AE�1r�S��A1�V�mVUJ���;��HU~���X�PP5kD�*�ZhBd��>~+ha'{��2��c��?��=��D����E\\�!T\0�^�7V��=U��TuXS�T&{&��x��b��yՎ�5�Br�YUV_	`f�V6|V\\�\$_�\0g�H�E䪤T�s�E\n�]O�V�Qd�-�WU3T6YU�OFM,U{`�b�eϏU/	b�-dĮv�F=W�K6���vP��i��q��*tCR�T6��.�?�_�yT!J>����~61_1�Q�\$���+�\0G�>�[�\0[�Gt\"�)ooS{	��oџp�-'pv�o�8p��m�.φpgK]N�[r�1�1ѽo�p\$�<v�kS�r�>�q�SF1!B�K�v�^��u�UB�~@�� �p��H�l;lQJ���ua6�7B-b���4�j���4Z�Q�Ā�\n���qIs`Tu9,(_��4P���9�<7�Ps�~�MCK}��5Qq�&Ij|.�mp\nv����\r;�J:AdO#t%(rD ED\"ǖ��!�␊9,��@v���e`-.��,�2y��L�İOxi3.��@ՓnR�\"��P�����j�<%���H��1��e*8�8�Y,D�X��ԱSZ�W�|5{��ы�8Ui]�I(��ҹc�>p�\0�h������?o�Tb�yaP_x�,L�R�PǬ��%-�r�\r��t�~hA��o�L\$� �\\���&*-/1&�&n\n��`����X澯�%.�iP��+s��LixP{���D4�=\$j�\$�qT�.����:.��s�M���RH#X�V8<1�u����+N,&/�N�I���S��#:-#\n��^ReL�CH"; break;
		case "vi": $compressed = "Bp��&������ *�(J.��0Q,��Z���)v��@Tf�\n�pj�p�*�V���C`�]��rY<�#\$b\$L2��@%9���I�����Γ���4˅����d3\rF�q��t9N1�Q�E3ڡ�h�j[�J;���o��\n�(�Ub��da���I¾Ri��D�\0\0�A)�X�8@q:�g!�C�_#y�̸�6:����ڋ�.���K;�.���}F��ͼS0��6�������\\��v����N5��n5���x!��r7��Ċl�Զ	���;����l��# \\�	Z:\nzT�\"�P�i�>����2���A��QtV�\0P��<���0�P6��(��� �4�#p ��k��=cx�9�c|(9������1�c����c �:#�9��\0�4��x�*���9�����4C(��C@�:�t��2,��?#8^2��|�9��^)���̮�������7���^0��p�2�oc,6F;r\$V( ƀ����a�Hk�(jx��ed�_����3��C+�#��-��(ȼ�#��aH!�#�t7�%�o���h�&L4h�'�dH�+`�=#��\n��:��UV�n�v�'Jv7]�2pJ���G��+�5�%����n]�7��Q7,tW�ë��Z���^�i\$T����2H;F�R�!	\n(ܙ�7��(��S���d΄�[�46)�8@)�\"`<U�P��Y���d�H!�b&���W��i�X©�މ�U\r�\\WC�x����5E�X�MJ<1TY\n�P�:��P���1�p�w��L܈�ǲ��H�0��*�?!!�v�gsی�Ŵ7KU �cG8]��=~H�/A��:�\nf9��@\0�a\0�0�\"�j�2�3PAḥx�3Vpʥ��Cq4@xũ\$�:RHS�1|4�54vt�ـnN�\\Q�4xr�J�o䣭p�P�n2���\"Q��SI�B��Rw�CZLS��T�]�\"F~��:7�C\"HN��<'�����P�;���r�Q�D2*ED��>���Q�L�	pP\r�A@t��M'e�C� �'mbEw��,J�X%�5	!D,.��\"|(�_�FnKS�wO)�>����*�Q0��E䣔��xJA�0��=DP�(�b�>}��f>\$_@�h�\"��TH�\"ieQ�՚�R�t���4���^S(p>��6�U�C2چ�A+��̾�HoG�3��h�]�� �� �鄿�9�4�܄A�j�8 --T�A��vB�\n\0�	�3DxK�p�I�)!R?`�0��to��9���C<�M�Q&;\$��f1��.9�k`�>�u&0曒� ;s~�����joN!�k�x�)�0gO�\"�5�#�mc�A�2�PۀR�4�+��+�34�Ы�ጡH��ݺ�LĎGS�bAp ���d.�z��7��+���%��\"Dȸ�8��OuW*e_ 5����D�����˰ܵ��O&�%�@�~�9Qs}[�ޙ(\nLM�d�DB��!/d���^xS\n�,���s\"-We�틮�fGk�:'����nNäu�J�\",V�\n#]�K����\\�U�x�䀫˃�Om�A��#J\0s��+%=��D8r]E�V5���ZMqH��\\ŵe�(Nb�%jr�>�\0A�%�M����N��z���a�Ya�F�#t���E�\$���	|\\�]ԏ�@���Y�Es��2�BeTyd���؋�����[�[�s\\��\$=��D��1�Y�,g���.-&��|�&tW�^Z�?��xrw:������Ȅqg�(\n)Ӟ���W�(�);tK0%��itHk�t�Z����K�� +\0�F��e�-a�2.��kCY�\$���N9�X]�i�O�*��H\\�tr�y���43���~\\�7N懴���&�7��`�%��	{t�-�N7\n ̀()&� ����d�![dcn[oǖ��ᆄ`A\n�P �0��\"K}��Υ��)Y!\rq�@KiZ���qk\0^rL2IfWC�.ŉ;�;�)���[ə5&4��A8�##YT��3^��|���EjIw ̓�5��B�\"�H��b���*V�i#!C�ȣ}��6<c�h�]n\$P�L7��R�a����I�\\e|b��S;�9�}ﾑ�\r��`�\n��S��x�7N�ލx���	��S�[�0��(e]��2��#n0�bЊ��Xt�������M�Oj�K�^|��um���k,�Pk���n�g%���8D�斃*��\"��G[ϸֽ2������~�#Vь�b[?H� �T�.�+���ꈘ)v����a�|������o�t�6͜א8�\\:�F�j�z\r%�������l�;�f|/�����B�l��0N�(�n�nB\\�<\\�La�M�H����m�:��H%�,C��:�>�c��O�/�9_G��\r�	��\0��f���&<�\"'k�c���b&m���*���N�Ұ��c�=�rZÐ�-,�R�d&�@!*���\$���b�	p���p���p&���Zm���#���\"�Z�˽	�HHՅ�X�u	{�]0�\n�@)l�s>h����GL�Ekq-��^0�k�W��ь��]���m�t{���!^B�l�r�c�Ge�!���,\rm�:%zN��rEv�u	0pMd���|?�~ю�q���_�|�cn����\$�rs�� �o�(�g��ϴ�l����9�W�y\r,|\\�N�#M�	NL*�\$T C5���N<%h%��G��H�ζ��xANfA�L�;%Ry&����\$��(g��Ҁ{��\n��h��А΍���ep%Pq�X0�0�r�HN:�C�̋�i�|#��Ҍ;��\$Z)r�!�<EJ���b\\	�T�bИ��c���\$~��\$�r=�,�p�/O�4��B�\n���ZN�\n�&�C3\n�ֽl.U�@�����*�R<{�6exI�Tm��/q�Gϲ�ϸ6�U5Ok5e_5�JH#f\\�Z��F�2j&�ı�������U*i��w�;b7�Fvr�i���l�qR��W<�E\"|\$�p��r���\\bъ8F_5�r-�\$��4C�l�7��=\r+*���k��[BiMB�h`����!.s�@���-t�tY����(Arb���\"�������C\n�Mx�\n��'��g�q�P\$���,9U#F��wH�('�3ֱ@\r��>�������8{�\0003�O'�6 �ɍ�Ӕx�j�I�\0�"; break;
		case "zh": $compressed = "�A*�s�\\�r����|%��:�\$\nr.���2�r/d�Ȼ[8� S�8�r�!T�\\�s���I4�b�r��ЀJs!J���:�2�r�ST⢔\n���h5\r��S�R�9Q��*�-Y(eȗB��+��΅�FZ�I9P�Yj^F�X9���P������2�s&֒E��~�����yc�~���#}K�r�s���k��|�i�-r�̀�)c(��C�ݦ#*�J!A�R�\n�k�P��/W�t��Z�U9��WJQ3�W���5��.�\"�.T�{��D-�(�J�s�\nZ�1H)tI���vr����s�	�A�p�2\r�H�2�GIvL&�\"�s�| �����K�̂�N'+�\0BI��1g,����\r��3��:����x�'��1\rC�p�9�x�7��9�c��2��:e1�A��AN���I��|GI\0D��YS�,ZZL�9H]6\$��O�\\ZJ3qr�e�R+�ZK)v]P+�V��)\"E!� @���A��A.��0Y<��řQ9UAU�QPr�D��G�0�Br���=ϥJ�C���M�d�����ZH�v]��\"�^��9zW%�s]Y��x:DaJ����5	CL�!X��M�r���B�\r�D�m��)�\"eL�n�I����54�!P�0>D\\��C�^Y�7OTV;dd�5SGAM2l�.����r��F]�4p�iP,uOS����o��9�1K!%~���:ޯ��%I�X���Xs��22YiUc\nR����K��]�Xմ��]��^D`�!A���}\\#`�9%�	�S=�����T)��0\\��'Ai�����5:e��UV\$1�I-��9#e~ҼkXO�p^�Ɛ���XXC�����69u�ynt�L�*&��ed} HR\$�\$IRd�(J^t�+�2�^2\r�p�:\r?��1L�X_!�A�\"ŉ<\"�Z��:(�`���;��x&������7W�-���x(T������pTN	���\nCH�\$����úQJo=+%���(x�l9�T����hzL=�6���\$e�J�f�+���r�\n9D`�mB�B�4\$��\$0@W��H������s	1��AReUެ>��b����r! �@�mϻf)˨�1�EDF΃�q�D��?�\0��d��\$G�P�J<	��h�\"�pg���X��\"YA��W�Q(�i�F�\"@�S@K�0q��r˙�4%�P\nx����qj��j+����W�D&&!kR��\\�qS\nAqI+�Q+u�eNKa\"���v�5ݒ��-�h�_d��JRlN	Ҭ¸Z��R�j��P5v��X�1��H�N#�jTS�|:��,D0B��(�\0�,*�@'�0��Q��\0R\nz �� �(\"E���� \"�Ţ��t�UҺؼ��N�s�  ��P(�B(	��V�P&�|k���X'���&�`�P�Ǖva@|�x�A<'\0� A\n���ЈB`E�e�V�z-O�i�	@Y�%E�:�<D�:�p�,�7����p\"�����.ԖQN.΁���է��&�Nx����ڗ���m�&h�~ࠇ9�@�o�&[	��YDQ�	�J��`��\"�FNӴ}�P�͡���*}�з�%�s8Kk�2�W2���M����&�r�m�:2��E�\$⎐�RjT�)��(��4�<\\��\"KEА,�m���h�Ab*�W*�U(�'��\\1t��N+>��M��&�H�Ҩ�L�Z+�Ae�0H\\�����%\r5Ab��~m0����@��@�<gLQܶ+��E<�eT�\0^3,�%�|QHyA�`�wH����#ĺ#�7H�^�D���Q1\"�%�լ���/�uw��D�����F!}ڼH��(��B\n�&�����_\n��|�`\n\n�1j��c�)M��xHZ-s���cn��r�4+W	'��B�|�zH�Rb=�aqm�y/e��+��m��:z=��Lќܘr�+FpwX�(���G�p�����p��?��������Q\0��1go[t����Rb1D+Z�[݌Xh�#��nZVii�{�Haay?K*��#��+s�B�����d]\nhZx�Yp�v��갂nዟ���F�T���:9xx���L���~��g�:gm�s�|�#�%77B\\�\\�j��:Wn�7���\$���ΥYX���F�>	����,V�-Bް�|#��.��G�����rszFD�+;���w�,kJ�q��r����YZ�텦�bF��;fR��{`��G��{������>�%7�aO�J>��䤼vϦszW�c���Qv�����Wg�ݱ'��s�\\��w�*9�����?�W^G\n���������Z�r�L��`h��ӌ\\u3�Ba<�N.�|�	�ŏ�R4�P8ߎz����c#K\0p=�6�p�@�m�oB0L�p���L��:£��/�b�	�\r\0������K�2��A>�-4+��-J6+�����p�t!\\'^�в\\0��m���:��ϧx�ag\r�z�g��� 4.pI�����v��\n���Zl�h*���n���b6#�~OæՆ�J��a�!(4��!^�Cp;'� Cn/�r�iV�!^���mLHzZ�4uFj`�I�\0�\\\"\\&l%��Q*2獬���y�X�1��0��B���unF���Ѿ�I��t��4i�[�.\nnf�f�NR�O�jɁ\\��Vn\r6�dn���� ���\r�n\$1��\$Ql4�G��Q�b?���������Z0M���\$2F�R:`����r(�q��� u\"a._.��n"; break;
		case "zh-tw": $compressed = "�^��%ӕ\\�r�����|%��:�\$\ns�.e�UȸE9PK72�(�P�h)ʅ@�:i	%��c�Je �R)ܫ{��	Nd T�P���\\��Õ8�C��f4����aS@/%����N����Nd�%гC��ɗB�Q+����B�_MK,�\$���u��ow�f��T9�WK��ʏW����2mizX:P	�*��_/�g*eSLK�ۈ��ι^9�H�\r���7��Zz>�����0)ȿN�\n�r!U=R�\n����^���J��T�O�](��I��^ܫ�[�f]��b����*��\\gA2��y��O�X�#�v���i`\\��\ns�P� ��h�7���P	�Z��ģBG���Tr��{4Ǒ0�&Q8)�,��ha!\0�9�0z\r��8a�^���\\0�1\\Z\r����p^8#��;̣ ^)A��T��\nt�[T�ex�!�\\\$	psd<-D%y�RP	 s-�~WF��JQO����:��(\\���1�|FM��ZS�����\0�<��(P9�*iXB m O����gANQ�D<vE��M��Q�d֭TMF��9zr��}M��) D)��8��!v]��!b���bsē�s'��UE�s݂���8*���\$�n���u���q\n�/\r�G~g1s\nb��V�����I&t�˃j�5-;#��OT�Ե1�t�V��,��Z���x����'�s���]%�)ϣ�\nƞ�i0�WDQTa���T)#�@s�xO�����Tah���1PP�\$#hV���d���¦K���J��12Af���K�G#��V*\\\\��*\r���˕I6Q0D�<C2�\$7��%��B(��J��7��\$r�MIGF���')C\$��_�IF��3-�'�V��~�O�a:�=OdQE����)\nW(�1H����'J�����ZK�y0>�ƙS:i�7���L\rN`�s�.+��E��,!�8�H�@����*�\na�7A���#\"9�>����C�C�X���\$�,1x�P�?���R�UJ�e-�����L��4&�\"�Cps���:��߇(���7�,�����L�2T>�D+Y�Z�����E�lQ\n����ح�0F�!ʱ�tO���9�ێ�a\$T`�v��r�A�'Ź�v�j0W��)�3��]�dj�š5��(���ě��	�Q	�T&���d,MP�_����\"T��Q�1,����D��0a\\p���!!B>+eX��E�r�9�\0�II�ϟQ/G0��G�����2Q���&�,#�J�rlS\nA@��I���XFÑ2/\rQ1/ÔK\n��-��3%�ę�G9�p�w���Y*''YA[�i����\"�q6��Aa<'1�9fl���<��rD��@�9����äK��h\"G4����N�#,xS\n��#�DvPLem6V訄q1�����(��@� � ���Q=W��i�`�	�\"e\0�,�R	���Ñ�qʂg�;�E\0KA|�h�)��P�5��p \n�@\"�@U�\"���m���HH��L�p�d�5u\\k��X��c+yW*��sΉ�:�슋��.'h���t�P��,�y�l�\" �s�x��mM�r���z����H!1P\"���s���\n�H�H���B�\n�3�At�0MW�T#�,����]Jy{,�S�wo�\n7I�H3����`��	.�E�\n��+���[Ӝ��2xfx��J��	b�*�\0�TX�qh(�#�g��'ey��8�B@�+&TҦ7Z��KKi�M�<B�\\U;>����͙���@��J�<B��;��/\"��\0].g<tضl�K0_V�tKo����lɅ��\n�!���|S�f�Z|R��z���>����Ur�����J)�5 	�-%j�f#����d	�~��bA,�]�I�'�\r6�P.����y���k�S��n�2� �<�m��AN'D��drrT7a#���I���9D���_�:'AX�0�P8��o�B#n*����NXAo��+E.OT	I)MT}���&'vSE�z��`��Y���H�lB��*�]{w�~�����SNbUt��֎��Z(9�B��z<�|)\r�ъ���>�ȩ1��|E�v�a�-�@yEF岌l\n�ǧ����Do&+!ev���{�q�����]r�U�.�'�����B6\r]1	�wa�\r>���������w6����}�bH�n�M���*͠��=��<y���\\�Oc�N������KMj���~�4Z�G��f����Я�����BH�e�Pk>��1엋s9\$\$�B�d���\"z_�VG�\"]N���Y\\ޑ�)�dzF��D�6����T�/��&�hF���|́<�GD�JT�>T%F��v�l��f���l��G�3��.���υ!s\r����T��YF���lf�\0S&���^\\� �{�:�v*�v��~��l�*a!	�\r\nP���X���,�S�^'�\n�n됸\\e���#q�YF���ΞY�ڣ��\nv�p�A0���΂���0��\r�\0ή(s#�M�P�<	=�&��T�*0���q.2��M����#�_��\"4f�K���ؖ��\r�ƅ��X*؏���0<AHQ�F�0��b�i�#k3C\\�%`�)��m���F� gT\r����9�>'�\\[�@2���bL�H���6\n���Z�B��j���iD�B2#b:�EN�d�,�Q\n�F僌0�68�\\�&����%�TC�|x�#)�ǈH)�q�\$&b�b�hJ��fDb��.�z+�&0l���-����n�'\nG��v�E(�&o�̖AB�i��]��%̮�6/�B0�+�2B���A,\"���+��@� ���\r� .�\0 ')Nި̸S�H�):.Q%ƹ&Rh�Q��l���1�Q�����o_�%®���,sL�A"; break;
	}
	$translations = array();
	foreach (explode("\n", lzw_decompress($compressed)) as $val) {
		$translations[] = (strpos($val, "\t") ? explode("\t", $val) : $val);
	}
	return $translations;
}

if (!$translations) {
	$translations = get_translations($LANG);
	$_SESSION["translations"] = $translations;
}


// PDO can be used in several database drivers
if (extension_loaded('pdo')) {
	/*abstract*/ class Min_PDO {
		var $_result, $server_info, $affected_rows, $errno, $error, $pdo;
		
		function __construct() {
			global $adminer;
			$pos = array_search("SQL", $adminer->operators);
			if ($pos !== false) {
				unset($adminer->operators[$pos]);
			}
		}
		
		function dsn($dsn, $username, $password, $options = array()) {
			$options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_SILENT;
			$options[PDO::ATTR_STATEMENT_CLASS] = array('Min_PDOStatement');
			try {
				$this->pdo = new PDO($dsn, $username, $password, $options);
			} catch (Exception $ex) {
				auth_error(h($ex->getMessage()));
			}
			$this->server_info = @$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
		}
		
		/*abstract function select_db($database);*/
		
		function quote($string) {
			return $this->pdo->quote($string);
		}
		
		function query($query, $unbuffered = false) {
			$result = $this->pdo->query($query);
			$this->error = "";
			if (!$result) {
				list(, $this->errno, $this->error) = $this->pdo->errorInfo();
				if (!$this->error) {
					$this->error = lang(21);
				}
				return false;
			}
			$this->store_result($result);
			return $result;
		}
		
		function multi_query($query) {
			return $this->_result = $this->query($query);
		}
		
		function store_result($result = null) {
			if (!$result) {
				$result = $this->_result;
				if (!$result) {
					return false;
				}
			}
			if ($result->columnCount()) {
				$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
				return $result;
			}
			$this->affected_rows = $result->rowCount();
			return true;
		}
		
		function next_result() {
			if (!$this->_result) {
				return false;
			}
			$this->_result->_offset = 0;
			return @$this->_result->nextRowset(); // @ - PDO_PgSQL doesn't support it
		}
		
		function result($query, $field = 0) {
			$result = $this->query($query);
			if (!$result) {
				return false;
			}
			$row = $result->fetch();
			return $row[$field];
		}
	}
	
	class Min_PDOStatement extends PDOStatement {
		var $_offset = 0, $num_rows;
		
		function fetch_assoc() {
			return $this->fetch(PDO::FETCH_ASSOC);
		}
		
		function fetch_row() {
			return $this->fetch(PDO::FETCH_NUM);
		}
		
		function fetch_field() {
			$row = (object) $this->getColumnMeta($this->_offset++);
			$row->orgtable = $row->table;
			$row->orgname = $row->name;
			$row->charsetnr = (in_array("blob", (array) $row->flags) ? 63 : 0);
			return $row;
		}
	}
}


$drivers = array();

/** Add a driver
* @param string
* @param string
* @return null
*/
function add_driver($id, $name) {
	global $drivers;
	$drivers[$id] = $name;
}

/*abstract*/ class Min_SQL {
	var $_conn;
	
	/** Create object for performing database operations
	* @param Min_DB
	*/
	function __construct($connection) {
		$this->_conn = $connection;
	}
	
	/** Select data from table
	* @param string
	* @param array result of $adminer->selectColumnsProcess()[0]
	* @param array result of $adminer->selectSearchProcess()
	* @param array result of $adminer->selectColumnsProcess()[1]
	* @param array result of $adminer->selectOrderProcess()
	* @param int result of $adminer->selectLimitProcess()
	* @param int index of page starting at zero
	* @param bool whether to print the query
	* @return Min_Result
	*/
	function select($table, $select, $where, $group, $order = array(), $limit = 1, $page = 0, $print = false) {
		global $adminer, $jush;
		$is_group = (count($group) < count($select));
		$query = $adminer->selectQueryBuild($select, $where, $group, $order, $limit, $page);
		if (!$query) {
			$query = "SELECT" . limit(
				($_GET["page"] != "last" && $limit != "" && $group && $is_group && $jush == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) . "\nFROM " . table($table),
				($where ? "\nWHERE " . implode(" AND ", $where) : "") . ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : ""),
				($limit != "" ? +$limit : null),
				($page ? $limit * $page : 0),
				"\n"
			);
		}
		$start = microtime(true);
		$return = $this->_conn->query($query);
		if ($print) {
			echo $adminer->selectQuery($query, $start, !$return);
		}
		return $return;
	}
	
	/** Delete data from table
	* @param string
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @return bool
	*/
	function delete($table, $queryWhere, $limit = 0) {
		$query = "FROM " . table($table);
		return queries("DELETE" . ($limit ? limit1($table, $query, $queryWhere) : " $query$queryWhere"));
	}
	
	/** Update data in table
	* @param string
	* @param array escaped columns in keys, quoted data in values
	* @param string " WHERE ..."
	* @param int 0 or 1
	* @param string
	* @return bool
	*/
	function update($table, $set, $queryWhere, $limit = 0, $separator = "\n") {
		$values = array();
		foreach ($set as $key => $val) {
			$values[] = "$key = $val";
		}
		$query = table($table) . " SET$separator" . implode(",$separator", $values);
		return queries("UPDATE" . ($limit ? limit1($table, $query, $queryWhere, $separator) : " $query$queryWhere"));
	}
	
	/** Insert data into table
	* @param string
	* @param array escaped columns in keys, quoted data in values
	* @return bool
	*/
	function insert($table, $set) {
		return queries("INSERT INTO " . table($table) . ($set
			? " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")"
			: " DEFAULT VALUES"
		));
	}
	
	/** Insert or update data in table
	* @param string
	* @param array
	* @param array of arrays with escaped columns in keys and quoted data in values
	* @return bool
	*/
	/*abstract*/ function insertUpdate($table, $rows, $primary) {
		return false;
	}
	
	/** Begin transaction
	* @return bool
	*/
	function begin() {
		return queries("BEGIN");
	}
	
	/** Commit transaction
	* @return bool
	*/
	function commit() {
		return queries("COMMIT");
	}
	
	/** Rollback transaction
	* @return bool
	*/
	function rollback() {
		return queries("ROLLBACK");
	}
	
	/** Return query with a timeout
	* @param string
	* @param int seconds
	* @return string or null if the driver doesn't support query timeouts
	*/
	function slowQuery($query, $timeout) {
	}
	
	/** Convert column to be searchable
	* @param string escaped column name
	* @param array array("op" => , "val" => )
	* @param array
	* @return string
	*/
	function convertSearch($idf, $val, $field) {
		return $idf;
	}

	/** Convert value returned by database to actual value
	* @param string
	* @param array
	* @return string
	*/
	function value($val, $field) {
		return (method_exists($this->_conn, 'value')
			? $this->_conn->value($val, $field)
			: (is_resource($val) ? stream_get_contents($val) : $val)
		);
	}

	/** Quote binary string
	* @param string
	* @return string
	*/
	function quoteBinary($s) {
		return q($s);
	}
	
	/** Get warnings about the last command
	* @return string HTML
	*/
	function warnings() {
		return '';
	}
	
	/** Get help link for table
	* @param string
	* @return string relative URL or null
	*/
	function tableHelp($name) {
	}
	
}


// any method change in this file should be transferred to editor/include/adminer.inc.php and plugins/plugin.php

class Adminer {
	/** @var array operators used in select, null for all operators */
	var $operators;

	/** Name in title and navigation
	* @return string HTML code
	*/
	function name() {
		return "<a href='https://www.adminer.org/'" . target_blank() . " id='h1'>Adminer</a>";
	}

	/** Connection parameters
	* @return array ($server, $username, $password)
	*/
	function credentials() {
		return array(SERVER, $_GET["username"], get_password());
	}

	/** Get SSL connection options
	* @return array array("key" => filename, "cert" => filename, "ca" => filename) or null
	*/
	function connectSsl() {
	}

	/** Get key used for permanent login
	* @param bool
	* @return string cryptic string which gets combined with password or false in case of an error
	*/
	function permanentLogin($create = false) {
		return password_file($create);
	}

	/** Return key used to group brute force attacks; behind a reverse proxy, you want to return the last part of X-Forwarded-For
	* @return string
	*/
	function bruteForceKey() {
		return $_SERVER["REMOTE_ADDR"];
	}
	
	/** Get server name displayed in breadcrumbs
	* @param string
	* @return string HTML code or null
	*/
	function serverName($server) {
		return h($server);
	}

	/** Identifier of selected database
	* @return string
	*/
	function database() {
		// should be used everywhere instead of DB
		return DB;
	}

	/** Get cached list of databases
	* @param bool
	* @return array
	*/
	function databases($flush = true) {
		return get_databases($flush);
	}

	/** Get list of schemas
	* @return array
	*/
	function schemas() {
		return schemas();
	}

	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout() {
		return 2;
	}

	/** Headers to send before HTML output
	* @return null
	*/
	function headers() {
	}

	/** Get Content Security Policy headers
	* @return array of arrays with directive name in key, allowed sources in value
	*/
	function csp() {
		return csp();
	}

	/** Print HTML code inside <head>
	* @return bool true to link favicon.ico and adminer.css if exists
	*/
	function head() {
		
		return true;
	}

	/** Get URLs of the CSS files
	* @return array of strings
	*/
	function css() {
		$return = array();
		$filename = "adminer.css";
		if (file_exists($filename)) {
			$return[] = "$filename?v=" . crc32(file_get_contents($filename));
		}
		return $return;
	}

	/** Print login form
	* @return null
	*/
	function loginForm() {
		global $drivers;
		echo "<table cellspacing='0' class='layout'>\n";
		echo $this->loginFormField('driver', '<tr><th>' . lang(22) . '<td>', html_select("auth[driver]", $drivers, DRIVER, "loginDriver(this);") . "\n");
		echo $this->loginFormField('server', '<tr><th>' . lang(23) . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '" title="hostname[:port]" placeholder="localhost" autocapitalize="off">' . "\n");
		echo $this->loginFormField('username', '<tr><th>' . lang(24) . '<td>', '<input name="auth[username]" id="username" value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">' . script("focus(qs('#username')); qs('#username').form['auth[driver]'].onchange();"));
		echo $this->loginFormField('password', '<tr><th>' . lang(25) . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">' . "\n");
		echo $this->loginFormField('db', '<tr><th>' . lang(26) . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">' . "\n");
		echo "</table>\n";
		echo "<p><input type='submit' value='" . lang(27) . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang(28)) . "\n";
	}
	
	/** Get login form field
	* @param string
	* @param string HTML
	* @param string HTML
	* @return string
	*/
	function loginFormField($name, $heading, $value) {
		return $heading . $value;
	}

	/** Authorize the user
	* @param string
	* @param string
	* @return mixed true for success, string for error message, false for unknown error
	*/
	function login($login, $password) {
		if ($password == "") {
			return lang(29, target_blank());
		}
		return true;
	}

	/** Table caption used in navigation and headings
	* @param array result of SHOW TABLE STATUS
	* @return string HTML code, "" to ignore table
	*/
	function tableName($tableStatus) {
		return h($tableStatus["Name"]);
	}

	/** Field caption used in select and edit
	* @param array single field returned from fields()
	* @param int order of column in select
	* @return string HTML code, "" to ignore field
	*/
	function fieldName($field, $order = 0) {
		return '<span title="' . h($field["full_type"]) . '">' . h($field["field"]) . '</span>';
	}

	/** Print links after select heading
	* @param array result of SHOW TABLE STATUS
	* @param string new item options, NULL for no new item
	* @return null
	*/
	function selectLinks($tableStatus, $set = "") {
		global $jush, $driver;
		echo '<p class="links">';
		$links = array("select" => lang(30));
		if (support("table") || support("indexes")) {
			$links["table"] = lang(31);
		}
		if (support("table")) {
			if (is_view($tableStatus)) {
				$links["view"] = lang(32);
			} else {
				$links["create"] = lang(33);
			}
		}
		if ($set !== null) {
			$links["edit"] = lang(34);
		}
		$name = $tableStatus["Name"];
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($name) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo doc_link(array($jush => $driver->tableHelp($name)), "?");
		echo "\n";
	}

	/** Get foreign keys for table
	* @param string
	* @return array same format as foreign_keys()
	*/
	function foreignKeys($table) {
		return foreign_keys($table);
	}

	/** Find backward keys for table
	* @param string
	* @param string
	* @return array $return[$target_table]["keys"][$key_name][$target_column] = $source_column; $return[$target_table]["name"] = $this->tableName($target_table);
	*/
	function backwardKeys($table, $tableName) {
		return array();
	}

	/** Print backward keys for row
	* @param array result of $this->backwardKeys()
	* @param array
	* @return null
	*/
	function backwardKeysPrint($backwardKeys, $row) {
	}

	/** Query printed in select before execution
	* @param string query to be executed
	* @param float start time of the query
	* @param bool
	* @return string
	*/
	function selectQuery($query, $start, $failed = false) {
		global $jush, $driver;
		$return = "</p>\n"; // required for IE9 inline edit
		if (!$failed && ($warnings = $driver->warnings())) {
			$id = "warnings";
			$return = ", <a href='#$id'>" . lang(35) . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "")
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-$jush'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang(10) . "</a>" : "")
			. $return
		;
	}

	/** Query printed in SQL command before execution
	* @param string query to be executed
	* @return string escaped query to be printed
	*/
	function sqlCommandQuery($query)
	{
		return shorten_utf8(trim($query), 1000);
	}

	/** Description of a row in a table
	* @param string
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription($table) {
		return "";
	}

	/** Get descriptions of selected data
	* @param array all data to print
	* @param array
	* @return array
	*/
	function rowDescriptions($rows, $foreignKeys) {
		return $rows;
	}

	/** Get a link to use in select table
	* @param string raw value of the field
	* @param array single field returned from fields()
	* @return string or null to create the default link
	*/
	function selectLink($val, $field) {
	}

	/** Value printed in select table
	* @param string HTML-escaped value to print
	* @param string link to foreign key
	* @param array single field returned from fields()
	* @param array original value before applying editVal() and escaping
	* @return string
	*/
	function selectVal($val, $link, $field, $original) {
		$return = ($val === null ? "<i>NULL</i>" : (preg_match("~char|binary|boolean~", $field["type"]) && !preg_match("~var~", $field["type"]) ? "<code>$val</code>" : $val));
		if (preg_match('~blob|bytea|raw|file~', $field["type"]) && !is_utf8($val)) {
			$return = "<i>" . lang(36, strlen($original)) . "</i>";
		}
		if (preg_match('~json~', $field["type"])) {
			$return = "<code class='jush-js'>$return</code>";
		}
		return ($link ? "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : "") . ">$return</a>" : $return);
	}

	/** Value conversion used in select and edit
	* @param string
	* @param array single field returned from fields()
	* @return string
	*/
	function editVal($val, $field) {
		return $val;
	}

	/** Print table structure in tabular format
	* @param array data about individual fields
	* @return null
	*/
	function tableStructurePrint($fields) {
		echo "<div class='scrollable'>\n";
		echo "<table cellspacing='0' class='nowrap'>\n";
		echo "<thead><tr><th>" . lang(37) . "<td>" . lang(38) . (support("comment") ? "<td>" . lang(39) : "") . "</thead>\n";
		foreach ($fields as $field) {
			echo "<tr" . odd() . "><th>" . h($field["field"]);
			echo "<td><span title='" . h($field["collation"]) . "'>" . h($field["full_type"]) . "</span>";
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . lang(40) . "</i>" : "");
			echo (isset($field["default"]) ? " <span title='" . lang(41) . "'>[<b>" . h($field["default"]) . "</b>]</span>" : "");
			echo (support("comment") ? "<td>" . h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
	}

	/** Print list of indexes on table in tabular format
	* @param array data about all indexes on a table
	* @return null
	*/
	function tableIndexesPrint($indexes) {
		echo "<table cellspacing='0'>\n";
		foreach ($indexes as $name => $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}
			echo "<tr title='" . h($name) . "'><th>$index[type]<td>" . implode(", ", $print) . "\n";
		}
		echo "</table>\n";
	}

	/** Print columns box in select
	* @param array result of selectColumnsProcess()[0]
	* @param array selectable columns
	* @return null
	*/
	function selectColumnsPrint($select, $columns) {
		global $functions, $grouping;
		print_fieldset("select", lang(42), $select);
		$i = 0;
		$select[""] = array();
		foreach ($select as $key => $val) {
			$val = $_GET["columns"][$key];
			$column = select_input(
				" name='columns[$i][col]'",
				$columns,
				$val["col"],
				($key !== "" ? "selectFieldChange" : "selectAddRow")
			);
			echo "<div>" . ($functions || $grouping ? "<select name='columns[$i][fun]'>"
				. optionlist(array(-1 => "") + array_filter(array(lang(43) => $functions, lang(44) => $grouping)), $val["fun"]) . "</select>"
				. on_help("getTarget(event).value && getTarget(event).value.replace(/ |\$/, '(') + ')'", 1)
				. script("qsl('select').onchange = function () { helpClose();" . ($key !== "" ? "" : " qsl('select, input', this.parentNode).onchange();") . " };", "")
				. "($column)" : $column) . "</div>\n";
			$i++;
		}
		echo "</div></fieldset>\n";
	}

	/** Print search box in select
	* @param array result of selectSearchProcess()
	* @param array selectable columns
	* @param array
	* @return null
	*/
	function selectSearchPrint($where, $columns, $indexes) {
		print_fieldset("search", lang(45), $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "<div>(<i>" . implode("</i>, <i>", array_map('h', $index["columns"])) . "</i>) AGAINST";
				echo " <input type='search' name='fulltext[$i]' value='" . h($_GET["fulltext"][$i]) . "'>";
				echo script("qsl('input').oninput = selectFieldChange;", "");
				echo checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL");
				echo "</div>\n";
			}
		}
		$change_next = "this.parentNode.firstChild.onchange();";
		foreach (array_merge((array) $_GET["where"], array(array())) as $i => $val) {
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators))) {
				echo "<div>" . select_input(
					" name='where[$i][col]'",
					$columns,
					$val["col"],
					($val ? "selectFieldChange" : "selectAddRow"),
					"(" . lang(46) . ")"
				);
				echo html_select("where[$i][op]", $this->operators, $val["op"], $change_next);
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "'>";
				echo script("mixin(qsl('input'), {oninput: function () { $change_next }, onkeydown: selectSearchKeydown, onsearch: selectSearchSearch});", "");
				echo "</div>\n";
			}
		}
		echo "</div></fieldset>\n";
	}

	/** Print order box in select
	* @param array result of selectOrderProcess()
	* @param array selectable columns
	* @param array
	* @return null
	*/
	function selectOrderPrint($order, $columns, $indexes) {
		print_fieldset("sort", lang(47), $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]'", $columns, $val, "selectFieldChange");
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang(48)) . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]'", $columns, "", "selectAddRow");
		echo checkbox("desc[$i]", 1, false, lang(48)) . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select
	* @param string result of selectLimitProcess()
	* @return null
	*/
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang(49) . "</legend><div>"; // <div> for easy styling
		echo "<input type='number' name='limit' class='size' value='" . h($limit) . "'>";
		echo script("qsl('input').oninput = selectFieldChange;", "");
		echo "</div></fieldset>\n";
	}

	/** Print text length box in select
	* @param string result of selectLengthProcess()
	* @return null
	*/
	function selectLengthPrint($text_length) {
		if ($text_length !== null) {
			echo "<fieldset><legend>" . lang(50) . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}

	/** Print action box in select
	* @param array
	* @return null
	*/
	function selectActionPrint($indexes) {
		echo "<fieldset><legend>" . lang(51) . "</legend><div>";
		echo "<input type='submit' value='" . lang(42) . "'>";
		echo " <span id='noindex' title='" . lang(52) . "'></span>";
		echo "<script" . nonce() . ">\n";
		echo "var indexColumns = ";
		$columns = array();
		foreach ($indexes as $index) {
			$current_key = reset($index["columns"]);
			if ($index["type"] != "FULLTEXT" && $current_key) {
				$columns[$current_key] = 1;
			}
		}
		$columns[""] = 1;
		foreach ($columns as $key => $val) {
			json_row($key);
		}
		echo ";\n";
		echo "selectFieldChange.call(qs('#form')['select']);\n";
		echo "</script>\n";
		echo "</div></fieldset>\n";
	}
	
	/** Print command box in select
	* @return bool whether to print default commands
	*/
	function selectCommandPrint() {
		return !information_schema(DB);
	}

	/** Print import box in select
	* @return bool whether to print default import
	*/
	function selectImportPrint() {
		return !information_schema(DB);
	}

	/** Print extra text in the end of a select form
	* @param array fields holding e-mails
	* @param array selectable columns
	* @return null
	*/
	function selectEmailPrint($emailFields, $columns) {
	}

	/** Process columns box in select
	* @param array selectable columns
	* @param array
	* @return array (array(select_expressions), array(group_expressions))
	*/
	function selectColumnsProcess($columns, $indexes) {
		global $functions, $grouping;
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || ($val["col"] != "" && (!$val["fun"] || in_array($val["fun"], $functions) || in_array($val["fun"], $grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], ($val["col"] != "" ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], $grouping)) {
					$group[] = $select[$key];
				}
			}
		}
		return array($select, $group);
	}

	/** Process search box in select
	* @param array
	* @param array
	* @return array expressions to join by AND
	*/
	function selectSearchProcess($fields, $indexes) {
		global $connection, $driver;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && $_GET["fulltext"][$i] != "") {
				$return[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $key => $val) {
			if ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators)) {
				$prefix = "";
				$cond = " $val[op]";
				if (preg_match('~IN$~', $val["op"])) {
					$in = process_length($val["val"]);
					$cond .= " " . ($in != "" ? $in : "(NULL)");
				} elseif ($val["op"] == "SQL") {
					$cond = " $val[val]"; // SQL injection
				} elseif ($val["op"] == "LIKE %%") {
					$cond = " LIKE " . $this->processInput($fields[$val["col"]], "%$val[val]%");
				} elseif ($val["op"] == "ILIKE %%") {
					$cond = " ILIKE " . $this->processInput($fields[$val["col"]], "%$val[val]%");
				} elseif ($val["op"] == "FIND_IN_SET") {
					$prefix = "$val[op](" . q($val["val"]) . ", ";
					$cond = ")";
				} elseif (!preg_match('~NULL$~', $val["op"])) {
					$cond .= " " . $this->processInput($fields[$val["col"]], $val["val"]);
				}
				if ($val["col"] != "") {
					$return[] = $prefix . $driver->convertSearch(idf_escape($val["col"]), $val, $fields[$val["col"]]) . $cond;
				} else {
					// find anywhere
					$cols = array();
					foreach ($fields as $name => $field) {
						if ((preg_match('~^[-\d.' . (preg_match('~IN$~', $val["op"]) ? ',' : '') . ']+$~', $val["val"]) || !preg_match('~' . number_type() . '|bit~', $field["type"]))
							&& (!preg_match("~[\x80-\xFF]~", $val["val"]) || preg_match('~char|text|enum|set~', $field["type"]))
							&& (!preg_match('~date|timestamp~', $field["type"]) || preg_match('~^\d+-\d+-\d+~', $val["val"]))
						) {
							$cols[] = $prefix . $driver->convertSearch(idf_escape($name), $val, $field) . $cond;
						}
					}
					$return[] = ($cols ? "(" . implode(" OR ", $cols) . ")" : "1 = 0");
				}
			}
		}
		return $return;
	}

	/** Process order box in select
	* @param array
	* @param array
	* @return array expressions to join by comma
	*/
	function selectOrderProcess($fields, $indexes) {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$return[] = (preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val) ? $val : idf_escape($val)) //! MS SQL uses []
					. (isset($_GET["desc"][$key]) ? " DESC" : "")
				;
			}
		}
		return $return;
	}

	/** Process limit box in select
	* @return string expression to use in LIMIT, will be escaped
	*/
	function selectLimitProcess() {
		return (isset($_GET["limit"]) ? $_GET["limit"] : "50");
	}

	/** Process length box in select
	* @return string number of characters to shorten texts, will be escaped
	*/
	function selectLengthProcess() {
		return (isset($_GET["text_length"]) ? $_GET["text_length"] : "100");
	}

	/** Process extras in select form
	* @param array AND conditions
	* @param array
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess($where, $foreignKeys) {
		return false;
	}

	/** Build SQL query used in select
	* @param array result of selectColumnsProcess()[0]
	* @param array result of selectSearchProcess()
	* @param array result of selectColumnsProcess()[1]
	* @param array result of selectOrderProcess()
	* @param int result of selectLimitProcess()
	* @param int index of page starting at zero
	* @return string empty string to use default query
	*/
	function selectQueryBuild($select, $where, $group, $order, $limit, $page) {
		return "";
	}

	/** Query printed after execution in the message
	* @param string executed query
	* @param string elapsed time
	* @param bool
	* @return string
	*/
	function messageQuery($query, $time, $failed = false) {
		global $jush, $driver;
		restart_session();
		$history = &get_session("queries");
		if (!$history[$_GET["db"]]) {
			$history[$_GET["db"]] = array();
		}
		if (strlen($query) > 1e6) {
			$query = preg_replace('~[\x80-\xFF]+$~', '', substr($query, 0, 1e6)) . "\n…"; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
		}
		$history[$_GET["db"]][] = array($query, time(), $time); // not DB - $_GET["db"] is changed in database.inc.php //! respect $_GET["ns"]
		$sql_id = "sql-" . count($history[$_GET["db"]]);
		$return = "<a href='#$sql_id' class='toggle'>" . lang(53) . "</a>\n";
		if (!$failed && ($warnings = $driver->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . lang(35) . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-$jush'>" . shorten_utf8($query, 1000) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql") ? '<p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang(10) . '</a>' : '')
			. '</div>'
		;
	}

	/** Print before edit form
	* @param string
	* @param array
	* @param mixed
	* @param bool
	* @return null
	*/
	function editRowPrint($table, $fields, $row, $update) {
	}

	/** Functions displayed in edit form
	* @param array single field from fields()
	* @return array
	*/
	function editFunctions($field) {
		global $edit_functions;
		$return = ($field["null"] ? "NULL/" : "");
		$update = isset($_GET["select"]) || where($_GET);
		foreach ($edit_functions as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && $update)) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || preg_match("~$pattern~", $field["type"])) {
						$return .= "/$val";
					}
				}
			}
			if ($key && !preg_match('~set|blob|bytea|raw|file|bool~', $field["type"])) {
				$return .= "/SQL";
			}
		}
		if ($field["auto_increment"] && !$update) {
			$return = lang(40);
		}
		return explode("/", $return);
	}

	/** Get options to display edit field
	* @param string table name
	* @param array single field from fields()
	* @param string attributes to use inside the tag
	* @param string
	* @return string custom input field or empty string for default
	*/
	function editInput($table, $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='-1' checked><i>" . lang(8) . "</i></label> " : "")
				. ($field["null"] ? "<label><input type='radio'$attrs value=''" . ($value !== null || isset($_GET["select"]) ? "" : " checked") . "><i>NULL</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, 0) // 0 - empty
			;
		}
		return "";
	}

	/** Get hint for edit field
	* @param string table name
	* @param array single field from fields()
	* @param string
	* @return string
	*/
	function editHint($table, $field, $value) {
		return "";
	}

	/** Process sent input
	* @param array single field from fields()
	* @param string
	* @param string
	* @return string expression to use in a query
	*/
	function processInput($field, $value, $function = "") {
		if ($function == "SQL") {
			return $value; // SQL injection
		}
		$name = $field["field"];
		$return = q($value);
		if (preg_match('~^(now|getdate|uuid)$~', $function)) {
			$return = "$function()";
		} elseif (preg_match('~^current_(date|timestamp)$~', $function)) {
			$return = $function;
		} elseif (preg_match('~^([+-]|\|\|)$~', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (preg_match('~^[+-] interval$~', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i", $value) ? $value : $return);
		} elseif (preg_match('~^(addtime|subtime|concat)$~', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (preg_match('~^(md5|sha1|password|encrypt)$~', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}

	/** Returns export output options
	* @return array
	*/
	function dumpOutput() {
		$return = array('text' => lang(54), 'file' => lang(55));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}

	/** Returns export format options
	* @return array empty to disable export
	*/
	function dumpFormat() {
		return array('sql' => 'SQL', 'csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	/** Export database structure
	* @param string
	* @return null prints data
	*/
	function dumpDatabase($db) {
	}

	/** Export table structure
	* @param string
	* @param string
	* @param int 0 table, 1 view, 2 temporary view table
	* @return null prints data
	*/
	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST["format"] != "sql") {
			echo "\xef\xbb\xbf"; // UTF-8 byte order mark
			if ($style) {
				dump_csv(array_keys(fields($table)));
			}
		} else {
			if ($is_view == 2) {
				$fields = array();
				foreach (fields($table) as $name => $field) {
					$fields[] = idf_escape($name) . " $field[full_type]";
				}
				$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
			} else {
				$create = create_sql($table, $_POST["auto_increment"], $style);
			}
			set_utf8mb4($create);
			if ($style && $create) {
				if ($style == "DROP+CREATE" || $is_view == 1) {
					echo "DROP " . ($is_view == 2 ? "VIEW" : "TABLE") . " IF EXISTS " . table($table) . ";\n";
				}
				if ($is_view == 1) {
					$create = remove_definer($create);
				}
				echo "$create;\n\n";
			}
		}
	}

	/** Export table data
	* @param string
	* @param string
	* @param string
	* @return null prints data
	*/
	function dumpData($table, $style, $query) {
		global $connection, $jush;
		$max_packet = ($jush == "sqlite" ? 0 : 1048576); // default, minimum is 1024
		if ($style) {
			if ($_POST["format"] == "sql") {
				if ($style == "TRUNCATE+INSERT") {
					echo truncate_sql($table) . ";\n";
				}
				$fields = fields($table);
			}
			$result = $connection->query($query, 1); // 1 - MYSQLI_USE_RESULT //! enum and set as numbers
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				while ($row = $result->$fetch_function()) {
					if (!$keys) {
						$values = array();
						foreach ($row as $val) {
							$field = $result->fetch_field();
							$keys[] = $field->name;
							$key = idf_escape($field->name);
							$values[] = "$key = VALUES($key)";
						}
						$suffix = ($style == "INSERT+UPDATE" ? "\nON DUPLICATE KEY UPDATE " . implode(", ", $values) : "") . ";\n";
					}
					if ($_POST["format"] != "sql") {
						if ($style == "table") {
							dump_csv($keys);
							$style = "INSERT";
						}
						dump_csv($row);
					} else {
						if (!$insert) {
							$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('idf_escape', $keys)) . ") VALUES";
						}
						foreach ($row as $key => $val) {
							$field = $fields[$key];
							$row[$key] = ($val !== null
								? unconvert_field($field, preg_match(number_type(), $field["type"]) && !preg_match('~\[~', $field["full_type"]) && is_numeric($val) ? $val : q(($val === false ? 0 : $val)))
								: "NULL"
							);
						}
						$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet) { // 4 - length specification
							$buffer .= ",$s";
						} else {
							echo $buffer . $suffix;
							$buffer = $insert . $s;
						}
					}
				}
				if ($buffer) {
					echo $buffer . $suffix;
				}
			} elseif ($_POST["format"] == "sql") {
				echo "-- " . str_replace("\n", " ", $connection->error) . "\n";
			}
		}
	}

	/** Set export filename
	* @param string
	* @return string filename without extension
	*/
	function dumpFilename($identifier) {
		return friendly_url($identifier != "" ? $identifier : (SERVER != "" ? SERVER : "localhost"));
	}

	/** Send headers for export
	* @param string
	* @param bool
	* @return string extension
	*/
	function dumpHeaders($identifier, $multi_table = false) {
		$output = $_POST["output"];
		$ext = (preg_match('~sql~', $_POST["format"]) ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
		header("Content-Type: " .
			($output == "gz" ? "application/x-gzip" :
			($ext == "tar" ? "application/x-tar" :
			($ext == "sql" || $output != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
		)));
		if ($output == "gz") {
			ob_start('ob_gzencode', 1e6);
		}
		return $ext;
	}

	/** Set the path of the file for webserver load
	* @return string path of the sql dump file
	*/
	function importServerPath() {
		return "adminer.sql";
	}

	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage() {
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . lang(56) . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? lang(57) : lang(58)) . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . lang(59) . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . lang(60) . "</a>\n" : "");
		return true;
	}

	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	* @return null
	*/
	function navigation($missing) {
		global $VERSION, $jush, $drivers, $connection;
		?>
<h1>
<?php echo $this->name(); ?> <span class="version"><?php echo $VERSION; ?></span>
<a href="https://www.adminer.org/#download"<?php echo target_blank(); ?> id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing == "auth") {
			$output = "";
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers as $server => $usernames) {
					foreach ($usernames as $username => $password) {
						if ($password !== null) {
							$dbs = $_SESSION["db"][$vendor][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								$output .= "<li><a href='" . h(auth_url($vendor, $server, $username, $db)) . "'>($drivers[$vendor]) " . h($username . ($server != "" ? "@" . $this->serverName($server) : "") . ($db != "" ? " - $db" : "")) . "</a>\n";
							}
						}
					}
				}
			}
			if ($output) {
				echo "<ul id='logins'>\n$output</ul>\n" . script("mixin(qs('#logins'), {onmouseover: menuOver, onmouseout: menuOut});");
			}
		} else {
			$tables = array();
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				$connection->select_db(DB);
				$tables = table_status('', true);
			}
			echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=jush.js&version=4.8.1");
			if (support("sql")) {
				?>
<script<?php echo nonce(); ?>>
<?php
				if ($tables) {
					$links = array();
					foreach ($tables as $table => $type) {
						$links[] = preg_quote($table, '/');
					}
					echo "var jushLinks = { $jush: [ '" . js_escape(ME) . (support("table") ? "table=" : "select=") . "\$&', /\\b(" . implode("|", $links) . ")\\b/g ] };\n";
					foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
						echo "jushLinks.$val = jushLinks.$jush;\n";
					}
				}
				$server_info = $connection->server_info;
				?>
bodyLoad('<?php echo (is_object($connection) ? preg_replace('~^(\d\.?\d).*~s', '\1', $server_info) : ""); ?>'<?php echo (preg_match('~MariaDB~', $server_info) ? ", true" : ""); ?>);
</script>
<?php
			}
			$this->databasesPrint($missing);
			if (DB == "" || !$missing) {
				echo "<p class='links'>" . (support("sql") ? "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . lang(53) . "</a>\n<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . lang(61) . "</a>\n" : "") . "";
				if (support("dump")) {
					echo "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . lang(62) . "</a>\n";
				}
			}
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				echo '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . lang(63) . "</a>\n";
				if (!$tables) {
					echo "<p class='message'>" . lang(9) . "\n";
				} else {
					$this->tablesPrint($tables);
				}
			}
		}
	}

	/** Prints databases list in menu
	* @param string
	* @return null
	*/
	function databasesPrint($missing) {
		global $adminer, $connection;
		$databases = $this->databases();
		if (DB && $databases && !in_array(DB, $databases)) {
			array_unshift($databases, DB);
		}
		?>
<form action="">
<p id="dbs">
<?php
		hidden_fields_get();
		$db_events = script("mixin(qsl('select'), {onmousedown: dbMouseDown, onchange: dbChange});");
		echo "<span title='" . lang(64) . "'>" . lang(65) . "</span>: " . ($databases
			? "<select name='db'>" . optionlist(array("" => "") + $databases, DB) . "</select>$db_events"
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off'>\n"
		);
		echo "<input type='submit' value='" . lang(20) . "'" . ($databases ? " class='hidden'" : "") . ">\n";

		foreach (array("import", "sql", "schema", "dump", "privileges") as $val) {
			if (isset($_GET[$val])) {
				echo "<input type='hidden' name='$val' value=''>";
				break;
			}
		}
		echo "</p></form>\n";
	}

	/** Prints table list in menu
	* @param array result of table_status('', true)
	* @return null
	*/
	function tablesPrint($tables) {
		echo "<ul id='tables'>" . script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$name = $this->tableName($status);
			if ($name != "") {
				echo '<li><a href="' . h(ME) . 'select=' . urlencode($table) . '"'
					. bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
					. " title='" . lang(30) . "'>" . lang(66) . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
						. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"])), (is_view($status) ? "view" : "structure"))
						. " title='" . lang(31) . "'>$name</a>"
					: "<span>$name</span>"
				) . "\n";
			}
		}
		echo "</ul>\n";
	}

}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);

$drivers = array("server" => "MySQL") + $drivers;

if (!defined("DRIVER")) {
	define("DRIVER", "server"); // server - backwards compatibility
	// MySQLi supports everything, MySQL doesn't support multiple result sets, PDO_MySQL doesn't support orgtable
	if (extension_loaded("mysqli")) {
		class Min_DB extends MySQLi {
			var $extension = "MySQLi";

			function __construct() {
				parent::init();
			}

			function connect($server = "", $username = "", $password = "", $database = null, $port = null, $socket = null) {
				global $adminer;
				mysqli_report(MYSQLI_REPORT_OFF); // stays between requests, not required since PHP 5.3.4
				list($host, $port) = explode(":", $server, 2); // part after : is used for port or socket
				$ssl = $adminer->connectSsl();
				if ($ssl) {
					$this->ssl_set($ssl['key'], $ssl['cert'], $ssl['ca'], '', '');
				}
				$return = @$this->real_connect(
					($server != "" ? $host : ini_get("mysqli.default_host")),
					($server . $username != "" ? $username : ini_get("mysqli.default_user")),
					($server . $username . $password != "" ? $password : ini_get("mysqli.default_pw")),
					$database,
					(is_numeric($port) ? $port : ini_get("mysqli.default_port")),
					(!is_numeric($port) ? $port : $socket),
					($ssl ? 64 : 0) // 64 - MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT (not available before PHP 5.6.16)
				);
				$this->options(MYSQLI_OPT_LOCAL_INFILE, false);
				return $return;
			}

			function set_charset($charset) {
				if (parent::set_charset($charset)) {
					return true;
				}
				// the client library may not support utf8mb4
				parent::set_charset('utf8');
				return $this->query("SET NAMES $charset");
			}

			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result) {
					return false;
				}
				$row = $result->fetch_array();
				return $row[$field];
			}
			
			function quote($string) {
				return "'" . $this->escape_string($string) . "'";
			}
		}

	} elseif (extension_loaded("mysql") && !((ini_bool("sql.safe_mode") || ini_bool("mysql.allow_local_infile")) && extension_loaded("pdo_mysql"))) {
		class Min_DB {
			var
				$extension = "MySQL", ///< @var string extension name
				$server_info, ///< @var string server version
				$affected_rows, ///< @var int number of affected rows
				$errno, ///< @var int last error code
				$error, ///< @var string last error message
				$_link, $_result ///< @access private
			;

			/** Connect to server
			* @param string
			* @param string
			* @param string
			* @return bool
			*/
			function connect($server, $username, $password) {
				if (ini_bool("mysql.allow_local_infile")) {
					$this->error = lang(67, "'mysql.allow_local_infile'", "MySQLi", "PDO_MySQL");
					return false;
				}
				$this->_link = @mysql_connect(
					($server != "" ? $server : ini_get("mysql.default_host")),
					("$server$username" != "" ? $username : ini_get("mysql.default_user")),
					("$server$username$password" != "" ? $password : ini_get("mysql.default_password")),
					true,
					131072 // CLIENT_MULTI_RESULTS for CALL
				);
				if ($this->_link) {
					$this->server_info = mysql_get_server_info($this->_link);
				} else {
					$this->error = mysql_error();
				}
				return (bool) $this->_link;
			}

			/** Sets the client character set
			* @param string
			* @return bool
			*/
			function set_charset($charset) {
				if (function_exists('mysql_set_charset')) {
					if (mysql_set_charset($charset, $this->_link)) {
						return true;
					}
					// the client library may not support utf8mb4
					mysql_set_charset('utf8', $this->_link);
				}
				return $this->query("SET NAMES $charset");
			}

			/** Quote string to use in SQL
			* @param string
			* @return string escaped string enclosed in '
			*/
			function quote($string) {
				return "'" . mysql_real_escape_string($string, $this->_link) . "'";
			}

			/** Select database
			* @param string
			* @return bool
			*/
			function select_db($database) {
				return mysql_select_db($database, $this->_link);
			}

			/** Send query
			* @param string
			* @param bool
			* @return mixed bool or Min_Result
			*/
			function query($query, $unbuffered = false) {
				$result = @($unbuffered ? mysql_unbuffered_query($query, $this->_link) : mysql_query($query, $this->_link)); // @ - mute mysql.trace_mode
				$this->error = "";
				if (!$result) {
					$this->errno = mysql_errno($this->_link);
					$this->error = mysql_error($this->_link);
					return false;
				}
				if ($result === true) {
					$this->affected_rows = mysql_affected_rows($this->_link);
					$this->info = mysql_info($this->_link);
					return true;
				}
				return new Min_Result($result);
			}

			/** Send query with more resultsets
			* @param string
			* @return bool
			*/
			function multi_query($query) {
				return $this->_result = $this->query($query);
			}

			/** Get current resultset
			* @return Min_Result
			*/
			function store_result() {
				return $this->_result;
			}

			/** Fetch next resultset
			* @return bool
			*/
			function next_result() {
				// MySQL extension doesn't support multiple results
				return false;
			}

			/** Get single field from result
			* @param string
			* @param int
			* @return string
			*/
			function result($query, $field = 0) {
				$result = $this->query($query);
				if (!$result || !$result->num_rows) {
					return false;
				}
				return mysql_result($result->_result, 0, $field);
			}
		}

		class Min_Result {
			var
				$num_rows, ///< @var int number of rows in the result
				$_result, $_offset = 0 ///< @access private
			;

			/** Constructor
			* @param resource
			*/
			function __construct($result) {
				$this->_result = $result;
				$this->num_rows = mysql_num_rows($result);
			}

			/** Fetch next row as associative array
			* @return array
			*/
			function fetch_assoc() {
				return mysql_fetch_assoc($this->_result);
			}

			/** Fetch next row as numbered array
			* @return array
			*/
			function fetch_row() {
				return mysql_fetch_row($this->_result);
			}

			/** Fetch next field
			* @return object properties: name, type, orgtable, orgname, charsetnr
			*/
			function fetch_field() {
				$return = mysql_fetch_field($this->_result, $this->_offset++); // offset required under certain conditions
				$return->orgtable = $return->table;
				$return->orgname = $return->name;
				$return->charsetnr = ($return->blob ? 63 : 0);
				return $return;
			}

			/** Free result set
			*/
			function __destruct() {
				mysql_free_result($this->_result);
			}
		}

	} elseif (extension_loaded("pdo_mysql")) {
		class Min_DB extends Min_PDO {
			var $extension = "PDO_MySQL";

			function connect($server, $username, $password) {
				global $adminer;
				$options = array(PDO::MYSQL_ATTR_LOCAL_INFILE => false);
				$ssl = $adminer->connectSsl();
				if ($ssl) {
					if (!empty($ssl['key'])) {
						$options[PDO::MYSQL_ATTR_SSL_KEY] = $ssl['key'];
					}
					if (!empty($ssl['cert'])) {
						$options[PDO::MYSQL_ATTR_SSL_CERT] = $ssl['cert'];
					}
					if (!empty($ssl['ca'])) {
						$options[PDO::MYSQL_ATTR_SSL_CA] = $ssl['ca'];
					}
				}
				$this->dsn(
					"mysql:charset=utf8;host=" . str_replace(":", ";unix_socket=", preg_replace('~:(\d)~', ';port=\1', $server)),
					$username,
					$password,
					$options
				);
				return true;
			}

			function set_charset($charset) {
				$this->query("SET NAMES $charset"); // charset in DSN is ignored before PHP 5.3.6
			}

			function select_db($database) {
				// database selection is separated from the connection so dbname in DSN can't be used
				return $this->query("USE " . idf_escape($database));
			}

			function query($query, $unbuffered = false) {
				$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$unbuffered);
				return parent::query($query, $unbuffered);
			}
		}

	}



	class Min_Driver extends Min_SQL {

		function insert($table, $set) {
			return ($set ? parent::insert($table, $set) : queries("INSERT INTO " . table($table) . " ()\nVALUES ()"));
		}

		function insertUpdate($table, $rows, $primary) {
			$columns = array_keys(reset($rows));
			$prefix = "INSERT INTO " . table($table) . " (" . implode(", ", $columns) . ") VALUES\n";
			$values = array();
			foreach ($columns as $key) {
				$values[$key] = "$key = VALUES($key)";
			}
			$suffix = "\nON DUPLICATE KEY UPDATE " . implode(", ", $values);
			$values = array();
			$length = 0;
			foreach ($rows as $set) {
				$value = "(" . implode(", ", $set) . ")";
				if ($values && (strlen($prefix) + $length + strlen($value) + strlen($suffix) > 1e6)) { // 1e6 - default max_allowed_packet
					if (!queries($prefix . implode(",\n", $values) . $suffix)) {
						return false;
					}
					$values = array();
					$length = 0;
				}
				$values[] = $value;
				$length += strlen($value) + 2; // 2 - strlen(",\n")
			}
			return queries($prefix . implode(",\n", $values) . $suffix);
		}
		
		function slowQuery($query, $timeout) {
			if (min_version('5.7.8', '10.1.2')) {
				if (preg_match('~MariaDB~', $this->_conn->server_info)) {
					return "SET STATEMENT max_statement_time=$timeout FOR $query";
				} elseif (preg_match('~^(SELECT\b)(.+)~is', $query, $match)) {
					return "$match[1] /*+ MAX_EXECUTION_TIME(" . ($timeout * 1000) . ") */ $match[2]";
				}
			}
		}

		function convertSearch($idf, $val, $field) {
			return (preg_match('~char|text|enum|set~', $field["type"]) && !preg_match("~^utf8~", $field["collation"]) && preg_match('~[\x80-\xFF]~', $val['val'])
				? "CONVERT($idf USING " . charset($this->_conn) . ")"
				: $idf
			);
		}
		
		function warnings() {
			$result = $this->_conn->query("SHOW WARNINGS");
			if ($result && $result->num_rows) {
				ob_start();
				select($result); // select() usually needs to print a big table progressively
				return ob_get_clean();
			}
		}

		function tableHelp($name) {
			$maria = preg_match('~MariaDB~', $this->_conn->server_info);
			if (information_schema(DB)) {
				return strtolower(($maria ? "information-schema-$name-table/" : str_replace("_", "-", $name) . "-table.html"));
			}
			if (DB == "mysql") {
				return ($maria ? "mysql$name-table/" : "system-database.html"); //! more precise link
			}
		}

	}



	/** Escape database identifier
	* @param string
	* @return string
	*/
	function idf_escape($idf) {
		return "`" . str_replace("`", "``", $idf) . "`";
	}

	/** Get escaped table name
	* @param string
	* @return string
	*/
	function table($idf) {
		return idf_escape($idf);
	}

	/** Connect to the database
	* @return mixed Min_DB or string for error
	*/
	function connect() {
		global $adminer, $types, $structured_types;
		$connection = new Min_DB;
		$credentials = $adminer->credentials();
		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
			$connection->set_charset(charset($connection)); // available in MySQLi since PHP 5.0.5
			$connection->query("SET sql_quote_show_create = 1, autocommit = 1");
			if (min_version('5.7.8', 10.2, $connection)) {
				$structured_types[lang(68)][] = "json";
				$types["json"] = 4294967295;
			}
			return $connection;
		}
		$return = $connection->error;
		if (function_exists('iconv') && !is_utf8($return) && strlen($s = iconv("windows-1250", "utf-8", $return)) > strlen($return)) { // windows-1250 - most common Windows encoding
			$return = $s;
		}
		return $return;
	}

	/** Get cached list of databases
	* @param bool
	* @return array
	*/
	function get_databases($flush) {
		// SHOW DATABASES can take a very long time so it is cached
		$return = get_session("dbs");
		if ($return === null) {
			$query = (min_version(5)
				? "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME"
				: "SHOW DATABASES"
			); // SHOW DATABASES can be disabled by skip_show_database
			$return = ($flush ? slow_query($query) : get_vals($query));
			restart_session();
			set_session("dbs", $return);
			stop_session();
		}
		return $return;
	}

	/** Formulate SQL query with limit
	* @param string everything after SELECT
	* @param string including WHERE
	* @param int
	* @param int
	* @param string
	* @return string
	*/
	function limit($query, $where, $limit, $offset = 0, $separator = " ") {
		return " $query$where" . ($limit !== null ? $separator . "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
	}

	/** Formulate SQL modification query with limit 1
	* @param string
	* @param string everything after UPDATE or DELETE
	* @param string
	* @param string
	* @return string
	*/
	function limit1($table, $query, $where, $separator = "\n") {
		return limit($query, $where, 1, 0, $separator);
	}

	/** Get database collation
	* @param string
	* @param array result of collations()
	* @return string
	*/
	function db_collation($db, $collations) {
		global $connection;
		$return = null;
		$create = $connection->result("SHOW CREATE DATABASE " . idf_escape($db), 1);
		if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
			$return = $match[1];
		} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
			// default collation
			$return = $collations[$match[1]][-1];
		}
		return $return;
	}

	/** Get supported engines
	* @return array
	*/
	function engines() {
		$return = array();
		foreach (get_rows("SHOW ENGINES") as $row) {
			if (preg_match("~YES|DEFAULT~", $row["Support"])) {
				$return[] = $row["Engine"];
			}
		}
		return $return;
	}

	/** Get logged user
	* @return string
	*/
	function logged_user() {
		global $connection;
		return $connection->result("SELECT USER()");
	}

	/** Get tables list
	* @return array array($name => $type)
	*/
	function tables_list() {
		return get_key_vals(min_version(5)
			? "SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
			: "SHOW TABLES"
		);
	}

	/** Count tables in all databases
	* @param array
	* @return array array($db => $tables)
	*/
	function count_tables($databases) {
		$return = array();
		foreach ($databases as $db) {
			$return[$db] = count(get_vals("SHOW TABLES IN " . idf_escape($db)));
		}
		return $return;
	}

	/** Get table status
	* @param string
	* @param bool return only "Name", "Engine" and "Comment" fields
	* @return array array($name => array("Name" => , "Engine" => , "Comment" => , "Oid" => , "Rows" => , "Collation" => , "Auto_increment" => , "Data_length" => , "Index_length" => , "Data_free" => )) or only inner array with $name
	*/
	function table_status($name = "", $fast = false) {
		$return = array();
		foreach (get_rows($fast && min_version(5)
			? "SELECT TABLE_NAME AS Name, ENGINE AS Engine, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() " . ($name != "" ? "AND TABLE_NAME = " . q($name) : "ORDER BY Name")
			: "SHOW TABLE STATUS" . ($name != "" ? " LIKE " . q(addcslashes($name, "%_\\")) : "")
		) as $row) {
			if ($row["Engine"] == "InnoDB") {
				// ignore internal comment, unnecessary since MySQL 5.1.21
				$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\1', $row["Comment"]);
			}
			if (!isset($row["Engine"])) {
				$row["Comment"] = "";
			}
			if ($name != "") {
				return $row;
			}
			$return[$row["Name"]] = $row;
		}
		return $return;
	}

	/** Find out whether the identifier is view
	* @param array
	* @return bool
	*/
	function is_view($table_status) {
		return $table_status["Engine"] === null;
	}

	/** Check if table supports foreign keys
	* @param array result of table_status
	* @return bool
	*/
	function fk_support($table_status) {
		return preg_match('~InnoDB|IBMDB2I~i', $table_status["Engine"])
			|| (preg_match('~NDB~i', $table_status["Engine"]) && min_version(5.6));
	}

	/** Get information about fields
	* @param string
	* @return array array($name => array("field" => , "full_type" => , "type" => , "length" => , "unsigned" => , "default" => , "null" => , "auto_increment" => , "on_update" => , "collation" => , "privileges" => , "comment" => , "primary" => ))
	*/
	function fields($table) {
		$return = array();
		foreach (get_rows("SHOW FULL COLUMNS FROM " . table($table)) as $row) {
			preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
			$return[$row["Field"]] = array(
				"field" => $row["Field"],
				"full_type" => $row["Type"],
				"type" => $match[1],
				"length" => $match[2],
				"unsigned" => ltrim($match[3] . $match[4]),
				"default" => ($row["Default"] != "" || preg_match("~char|set~", $match[1]) ? (preg_match('~text~', $match[1]) ? stripslashes(preg_replace("~^'(.*)'\$~", '\1', $row["Default"])) : $row["Default"]) : null),
				"null" => ($row["Null"] == "YES"),
				"auto_increment" => ($row["Extra"] == "auto_increment"),
				"on_update" => (preg_match('~^on update (.+)~i', $row["Extra"], $match) ? $match[1] : ""), //! available since MySQL 5.1.23
				"collation" => $row["Collation"],
				"privileges" => array_flip(preg_split('~, *~', $row["Privileges"])),
				"comment" => $row["Comment"],
				"primary" => ($row["Key"] == "PRI"),
				// https://mariadb.com/kb/en/library/show-columns/, https://github.com/vrana/adminer/pull/359#pullrequestreview-276677186
				"generated" => preg_match('~^(VIRTUAL|PERSISTENT|STORED)~', $row["Extra"]),
			);
		}
		return $return;
	}

	/** Get table indexes
	* @param string
	* @param string Min_DB to use
	* @return array array($key_name => array("type" => , "columns" => array(), "lengths" => array(), "descs" => array()))
	*/
	function indexes($table, $connection2 = null) {
		$return = array();
		foreach (get_rows("SHOW INDEX FROM " . table($table), $connection2) as $row) {
			$name = $row["Key_name"];
			$return[$name]["type"] = ($name == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? ($row["Index_type"] == "SPATIAL" ? "SPATIAL" : "INDEX") : "UNIQUE")));
			$return[$name]["columns"][] = $row["Column_name"];
			$return[$name]["lengths"][] = ($row["Index_type"] == "SPATIAL" ? null : $row["Sub_part"]);
			$return[$name]["descs"][] = null;
		}
		return $return;
	}

	/** Get foreign keys in table
	* @param string
	* @return array array($name => array("db" => , "ns" => , "table" => , "source" => array(), "target" => array(), "on_delete" => , "on_update" => ))
	*/
	function foreign_keys($table) {
		global $connection, $on_actions;
		static $pattern = '(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';
		$return = array();
		$create_table = $connection->result("SHOW CREATE TABLE " . table($table), 1);
		if ($create_table) {
			preg_match_all("~CONSTRAINT ($pattern) FOREIGN KEY ?\\(((?:$pattern,? ?)+)\\) REFERENCES ($pattern)(?:\\.($pattern))? \\(((?:$pattern,? ?)+)\\)(?: ON DELETE ($on_actions))?(?: ON UPDATE ($on_actions))?~", $create_table, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				preg_match_all("~$pattern~", $match[2], $source);
				preg_match_all("~$pattern~", $match[5], $target);
				$return[idf_unescape($match[1])] = array(
					"db" => idf_unescape($match[4] != "" ? $match[3] : $match[4]),
					"table" => idf_unescape($match[4] != "" ? $match[4] : $match[3]),
					"source" => array_map('idf_unescape', $source[0]),
					"target" => array_map('idf_unescape', $target[0]),
					"on_delete" => ($match[6] ? $match[6] : "RESTRICT"),
					"on_update" => ($match[7] ? $match[7] : "RESTRICT"),
				);
			}
		}
		return $return;
	}

	/** Get view SELECT
	* @param string
	* @return array array("select" => )
	*/
	function view($name) {
		global $connection;
		return array("select" => preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU', '', $connection->result("SHOW CREATE VIEW " . table($name), 1)));
	}

	/** Get sorted grouped list of collations
	* @return array
	*/
	function collations() {
		$return = array();
		foreach (get_rows("SHOW COLLATION") as $row) {
			if ($row["Default"]) {
				$return[$row["Charset"]][-1] = $row["Collation"];
			} else {
				$return[$row["Charset"]][] = $row["Collation"];
			}
		}
		ksort($return);
		foreach ($return as $key => $val) {
			asort($return[$key]);
		}
		return $return;
	}

	/** Find out if database is information_schema
	* @param string
	* @return bool
	*/
	function information_schema($db) {
		return (min_version(5) && $db == "information_schema")
			|| (min_version(5.5) && $db == "performance_schema");
	}

	/** Get escaped error message
	* @return string
	*/
	function error() {
		global $connection;
		return h(preg_replace('~^You have an error.*syntax to use~U', "Syntax error", $connection->error));
	}

	/** Create database
	* @param string
	* @param string
	* @return string
	*/
	function create_database($db, $collation) {
		return queries("CREATE DATABASE " . idf_escape($db) . ($collation ? " COLLATE " . q($collation) : ""));
	}

	/** Drop databases
	* @param array
	* @return bool
	*/
	function drop_databases($databases) {
		$return = apply_queries("DROP DATABASE", $databases, 'idf_escape');
		restart_session();
		set_session("dbs", null);
		return $return;
	}

	/** Rename database from DB
	* @param string new name
	* @param string
	* @return bool
	*/
	function rename_database($name, $collation) {
		$return = false;
		if (create_database($name, $collation)) {
			$tables = array();
			$views = array();
			foreach (tables_list() as $table => $type) {
				if ($type == 'VIEW') {
					$views[] = $table;
				} else {
					$tables[] = $table;
				}
			}
			$return = (!$tables && !$views) || move_tables($tables, $views, $name);
			drop_databases($return ? array(DB) : array());
		}
		return $return;
	}

	/** Generate modifier for auto increment column
	* @return string
	*/
	function auto_increment() {
		$auto_increment_index = " PRIMARY KEY";
		// don't overwrite primary key by auto_increment
		if ($_GET["create"] != "" && $_POST["auto_increment_col"]) {
			foreach (indexes($_GET["create"]) as $index) {
				if (in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"], $index["columns"], true)) {
					$auto_increment_index = "";
					break;
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		return " AUTO_INCREMENT$auto_increment_index";
	}

	/** Run commands to create or alter table
	* @param string "" to create
	* @param string new name
	* @param array of array($orig, $process_field, $after)
	* @param array of strings
	* @param string
	* @param string
	* @param string
	* @param string number
	* @param string
	* @return bool
	*/
	function alter_table($table, $name, $fields, $foreign, $comment, $engine, $collation, $auto_increment, $partitioning) {
		$alter = array();
		foreach ($fields as $field) {
			$alter[] = ($field[1]
				? ($table != "" ? ($field[0] != "" ? "CHANGE " . idf_escape($field[0]) : "ADD") : " ") . " " . implode($field[1]) . ($table != "" ? $field[2] : "")
				: "DROP " . idf_escape($field[0])
			);
		}
		$alter = array_merge($alter, $foreign);
		$status = ($comment !== null ? " COMMENT=" . q($comment) : "")
			. ($engine ? " ENGINE=" . q($engine) : "")
			. ($collation ? " COLLATE " . q($collation) : "")
			. ($auto_increment != "" ? " AUTO_INCREMENT=$auto_increment" : "")
		;
		if ($table == "") {
			return queries("CREATE TABLE " . table($name) . " (\n" . implode(",\n", $alter) . "\n)$status$partitioning");
		}
		if ($table != $name) {
			$alter[] = "RENAME TO " . table($name);
		}
		if ($status) {
			$alter[] = ltrim($status);
		}
		return ($alter || $partitioning ? queries("ALTER TABLE " . table($table) . "\n" . implode(",\n", $alter) . $partitioning) : true);
	}

	/** Run commands to alter indexes
	* @param string escaped table name
	* @param array of array("index type", "name", array("column definition", ...)) or array("index type", "name", "DROP")
	* @return bool
	*/
	function alter_indexes($table, $alter) {
		foreach ($alter as $key => $val) {
			$alter[$key] = ($val[2] == "DROP"
				? "\nDROP INDEX " . idf_escape($val[1])
				: "\nADD $val[0] " . ($val[0] == "PRIMARY" ? "KEY " : "") . ($val[1] != "" ? idf_escape($val[1]) . " " : "") . "(" . implode(", ", $val[2]) . ")"
			);
		}
		return queries("ALTER TABLE " . table($table) . implode(",", $alter));
	}

	/** Run commands to truncate tables
	* @param array
	* @return bool
	*/
	function truncate_tables($tables) {
		return apply_queries("TRUNCATE TABLE", $tables);
	}

	/** Drop views
	* @param array
	* @return bool
	*/
	function drop_views($views) {
		return queries("DROP VIEW " . implode(", ", array_map('table', $views)));
	}

	/** Drop tables
	* @param array
	* @return bool
	*/
	function drop_tables($tables) {
		return queries("DROP TABLE " . implode(", ", array_map('table', $tables)));
	}

	/** Move tables to other schema
	* @param array
	* @param array
	* @param string
	* @return bool
	*/
	function move_tables($tables, $views, $target) {
		global $connection;
		$rename = array();
		foreach ($tables as $table) {
			$rename[] = table($table) . " TO " . idf_escape($target) . "." . table($table);
		}
		if (!$rename || queries("RENAME TABLE " . implode(", ", $rename))) {
			$definitions = array();
			foreach ($views as $table) {
				$definitions[table($table)] = view($table);
			}
			$connection->select_db($target);
			$db = idf_escape(DB);
			foreach ($definitions as $name => $view) {
				if (!queries("CREATE VIEW $name AS " . str_replace(" $db.", " ", $view["select"])) || !queries("DROP VIEW $db.$name")) {
					return false;
				}
			}
			return true;
		}
		//! move triggers
		return false;
	}

	/** Copy tables to other schema
	* @param array
	* @param array
	* @param string
	* @return bool
	*/
	function copy_tables($tables, $views, $target) {
		queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
		foreach ($tables as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			if (($_POST["overwrite"] && !queries("\nDROP TABLE IF EXISTS $name"))
				|| !queries("CREATE TABLE $name LIKE " . table($table))
				|| !queries("INSERT INTO $name SELECT * FROM " . table($table))
			) {
				return false;
			}
			foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
				$trigger = $row["Trigger"];
				if (!queries("CREATE TRIGGER " . ($target == DB ? idf_escape("copy_$trigger") : idf_escape($target) . "." . idf_escape($trigger)) . " $row[Timing] $row[Event] ON $name FOR EACH ROW\n$row[Statement];")) {
					return false;
				}
			}
		}
		foreach ($views as $table) {
			$name = ($target == DB ? table("copy_$table") : idf_escape($target) . "." . table($table));
			$view = view($table);
			if (($_POST["overwrite"] && !queries("DROP VIEW IF EXISTS $name"))
				|| !queries("CREATE VIEW $name AS $view[select]")) { //! USE to avoid db.table
				return false;
			}
		}
		return true;
	}

	/** Get information about trigger
	* @param string trigger name
	* @return array array("Trigger" => , "Timing" => , "Event" => , "Of" => , "Type" => , "Statement" => )
	*/
	function trigger($name) {
		if ($name == "") {
			return array();
		}
		$rows = get_rows("SHOW TRIGGERS WHERE `Trigger` = " . q($name));
		return reset($rows);
	}

	/** Get defined triggers
	* @param string
	* @return array array($name => array($timing, $event))
	*/
	function triggers($table) {
		$return = array();
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\"))) as $row) {
			$return[$row["Trigger"]] = array($row["Timing"], $row["Event"]);
		}
		return $return;
	}

	/** Get trigger options
	* @return array ("Timing" => array(), "Event" => array(), "Type" => array())
	*/
	function trigger_options() {
		return array(
			"Timing" => array("BEFORE", "AFTER"),
			"Event" => array("INSERT", "UPDATE", "DELETE"),
			"Type" => array("FOR EACH ROW"),
		);
	}

	/** Get information about stored routine
	* @param string
	* @param string "FUNCTION" or "PROCEDURE"
	* @return array ("fields" => array("field" => , "type" => , "length" => , "unsigned" => , "inout" => , "collation" => ), "returns" => , "definition" => , "language" => )
	*/
	function routine($name, $type) {
		global $connection, $enum_length, $inout, $types;
		$aliases = array("bool", "boolean", "integer", "double precision", "real", "dec", "numeric", "fixed", "national char", "national varchar");
		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|-- )[^\n]*\n?|--\r?\n)";
		$type_pattern = "((" . implode("|", array_merge(array_keys($types), $aliases)) . ")\\b(?:\\s*\\(((?:[^'\")]|$enum_length)++)\\))?\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?)(?:\\s*(?:CHARSET|CHARACTER\\s+SET)\\s*['\"]?([^'\"\\s,]+)['\"]?)?";
		$pattern = "$space*(" . ($type == "FUNCTION" ? "" : $inout) . ")?\\s*(?:`((?:[^`]|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
		$create = $connection->result("SHOW CREATE $type " . idf_escape($name), 2);
		preg_match("~\\(((?:$pattern\\s*,?)*)\\)\\s*" . ($type == "FUNCTION" ? "RETURNS\\s+$type_pattern\\s+" : "") . "(.*)~is", $create, $match);
		$fields = array();
		preg_match_all("~$pattern\\s*,?~is", $match[1], $matches, PREG_SET_ORDER);
		foreach ($matches as $param) {
			$fields[] = array(
				"field" => str_replace("``", "`", $param[2]) . $param[3],
				"type" => strtolower($param[5]),
				"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $param[6]),
				"unsigned" => strtolower(preg_replace('~\s+~', ' ', trim("$param[8] $param[7]"))),
				"null" => 1,
				"full_type" => $param[4],
				"inout" => strtoupper($param[1]),
				"collation" => strtolower($param[9]),
			);
		}
		if ($type != "FUNCTION") {
			return array("fields" => $fields, "definition" => $match[11]);
		}
		return array(
			"fields" => $fields,
			"returns" => array("type" => $match[12], "length" => $match[13], "unsigned" => $match[15], "collation" => $match[16]),
			"definition" => $match[17],
			"language" => "SQL", // available in information_schema.ROUTINES.PARAMETER_STYLE
		);
	}

	/** Get list of routines
	* @return array ("SPECIFIC_NAME" => , "ROUTINE_NAME" => , "ROUTINE_TYPE" => , "DTD_IDENTIFIER" => )
	*/
	function routines() {
		return get_rows("SELECT ROUTINE_NAME AS SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . q(DB));
	}

	/** Get list of available routine languages
	* @return array
	*/
	function routine_languages() {
		return array(); // "SQL" not required
	}

	/** Get routine signature
	* @param string
	* @param array result of routine()
	* @return string
	*/
	function routine_id($name, $row) {
		return idf_escape($name);
	}

	/** Get last auto increment ID
	* @return string
	*/
	function last_id() {
		global $connection;
		return $connection->result("SELECT LAST_INSERT_ID()"); // mysql_insert_id() truncates bigint
	}

	/** Explain select
	* @param Min_DB
	* @param string
	* @return Min_Result
	*/
	function explain($connection, $query) {
		return $connection->query("EXPLAIN " . (min_version(5.1) && !min_version(5.7) ? "PARTITIONS " : "") . $query);
	}

	/** Get approximate number of rows
	* @param array
	* @param array
	* @return int or null if approximate number can't be retrieved
	*/
	function found_rows($table_status, $where) {
		return ($where || $table_status["Engine"] != "InnoDB" ? null : $table_status["Rows"]);
	}

	/** Get user defined types
	* @return array
	*/
	function types() {
		return array();
	}

	/** Get existing schemas
	* @return array
	*/
	function schemas() {
		return array();
	}

	/** Get current schema
	* @return string
	*/
	function get_schema() {
		return "";
	}

	/** Set current schema
	* @param string
	* @param Min_DB
	* @return bool
	*/
	function set_schema($schema, $connection2 = null) {
		return true;
	}

	/** Get SQL command to create table
	* @param string
	* @param bool
	* @param string
	* @return string
	*/
	function create_sql($table, $auto_increment, $style) {
		global $connection;
		$return = $connection->result("SHOW CREATE TABLE " . table($table), 1);
		if (!$auto_increment) {
			$return = preg_replace('~ AUTO_INCREMENT=\d+~', '', $return); //! skip comments
		}
		return $return;
	}

	/** Get SQL command to truncate table
	* @param string
	* @return string
	*/
	function truncate_sql($table) {
		return "TRUNCATE " . table($table);
	}

	/** Get SQL command to change database
	* @param string
	* @return string
	*/
	function use_sql($database) {
		return "USE " . idf_escape($database);
	}

	/** Get SQL commands to create triggers
	* @param string
	* @return string
	*/
	function trigger_sql($table) {
		$return = "";
		foreach (get_rows("SHOW TRIGGERS LIKE " . q(addcslashes($table, "%_\\")), null, "-- ") as $row) {
			$return .= "\nCREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . table($row["Table"]) . " FOR EACH ROW\n$row[Statement];;\n";
		}
		return $return;
	}

	/** Get server variables
	* @return array ($name => $value)
	*/
	function show_variables() {
		return get_key_vals("SHOW VARIABLES");
	}

	/** Get process list
	* @return array ($row)
	*/
	function process_list() {
		return get_rows("SHOW FULL PROCESSLIST");
	}

	/** Get status variables
	* @return array ($name => $value)
	*/
	function show_status() {
		return get_key_vals("SHOW STATUS");
	}

	/** Convert field in select and edit
	* @param array one element from fields()
	* @return string
	*/
	function convert_field($field) {
		if (preg_match("~binary~", $field["type"])) {
			return "HEX(" . idf_escape($field["field"]) . ")";
		}
		if ($field["type"] == "bit") {
			return "BIN(" . idf_escape($field["field"]) . " + 0)"; // + 0 is required outside MySQLnd
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			return (min_version(8) ? "ST_" : "") . "AsWKT(" . idf_escape($field["field"]) . ")";
		}
	}

	/** Convert value in edit after applying functions back
	* @param array one element from fields()
	* @param string
	* @return string
	*/
	function unconvert_field($field, $return) {
		if (preg_match("~binary~", $field["type"])) {
			$return = "UNHEX($return)";
		}
		if ($field["type"] == "bit") {
			$return = "CONV($return, 2, 10) + 0";
		}
		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
			$return = (min_version(8) ? "ST_" : "") . "GeomFromText($return, SRID($field[field]))";
		}
		return $return;
	}

	/** Check whether a feature is supported
	* @param string "comment", "copy", "database", "descidx", "drop_col", "dump", "event", "indexes", "kill", "materializedview", "partitioning", "privileges", "procedure", "processlist", "routine", "scheme", "sequence", "status", "table", "trigger", "type", "variables", "view", "view_trigger"
	* @return bool
	*/
	function support($feature) {
		return !preg_match("~scheme|sequence|type|view_trigger|materializedview" . (min_version(8) ? "" : "|descidx" . (min_version(5.1) ? "" : "|event|partitioning" . (min_version(5) ? "" : "|routine|trigger|view"))) . "~", $feature);
	}

	/** Kill a process
	* @param int
	* @return bool
	*/
	function kill_process($val) {
		return queries("KILL " . number($val));
	}

	/** Return query to get connection ID
	* @return string
	*/
	function connection_id(){
		return "SELECT CONNECTION_ID()";
	}

	/** Get maximum number of connections
	* @return int
	*/
	function max_connections() {
		global $connection;
		return $connection->result("SELECT @@max_connections");
	}

	/** Get driver config
	* @return array array('possible_drivers' => , 'jush' => , 'types' => , 'structured_types' => , 'unsigned' => , 'operators' => , 'functions' => , 'grouping' => , 'edit_functions' => )
	*/
	function driver_config() {
		$types = array(); ///< @var array ($type => $maximum_unsigned_length, ...)
		$structured_types = array(); ///< @var array ($description => array($type, ...), ...)
		foreach (array(
			lang(69) => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "decimal" => 66, "float" => 12, "double" => 21),
			lang(70) => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
			lang(68) => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
			lang(71) => array("enum" => 65535, "set" => 64),
			lang(72) => array("bit" => 20, "binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
			lang(73) => array("geometry" => 0, "point" => 0, "linestring" => 0, "polygon" => 0, "multipoint" => 0, "multilinestring" => 0, "multipolygon" => 0, "geometrycollection" => 0),
		) as $key => $val) {
			$types += $val;
			$structured_types[$key] = array_keys($val);
		}
		return array(
			'possible_drivers' => array("MySQLi", "MySQL", "PDO_MySQL"),
			'jush' => "sql", ///< @var string JUSH identifier
			'types' => $types,
			'structured_types' => $structured_types,
			'unsigned' => array("unsigned", "zerofill", "unsigned zerofill"), ///< @var array number variants
			'operators' => array("=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "REGEXP", "IN", "FIND_IN_SET", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL", "SQL"), ///< @var array operators used in select
			'functions' => array("char_length", "date", "from_unixtime", "lower", "round", "floor", "ceil", "sec_to_time", "time_to_sec", "upper"), ///< @var array functions used in select
			'grouping' => array("avg", "count", "count distinct", "group_concat", "max", "min", "sum"), ///< @var array grouping functions used in select
			'edit_functions' => array( ///< @var array of array("$type|$type2" => "$function/$function2") functions used in editing, [0] - edit and insert, [1] - edit only
				array(
					"char" => "md5/sha1/password/encrypt/uuid",
					"binary" => "md5/sha1",
					"date|time" => "now",
				), array(
					number_type() => "+/-",
					"date" => "+ interval/- interval",
					"time" => "addtime/subtime",
					"char|text" => "concat",
				)
			),
		);
	}
}
 // must be included as last driver

$config = driver_config();
$possible_drivers = $config['possible_drivers'];
$jush = $config['jush'];
$types = $config['types'];
$structured_types = $config['structured_types'];
$unsigned = $config['unsigned'];
$operators = $config['operators'];
$functions = $config['functions'];
$grouping = $config['grouping'];
$edit_functions = $config['edit_functions'];
if ($adminer->operators === null) {
	$adminer->operators = $operators;
}

define("SERVER", $_GET[DRIVER]); // read from pgsql=localhost
define("DB", $_GET["db"]); // for the sake of speed and size
define("ME", preg_replace('~\?.*~', '', relative_uri()) . '?'
	. (sid() ? SID . '&' : '')
	. (SERVER !== null ? DRIVER . "=" . urlencode(SERVER) . '&' : '')
	. (isset($_GET["username"]) ? "username=" . urlencode($_GET["username"]) . '&' : '')
	. (DB != "" ? 'db=' . urlencode(DB) . '&' . (isset($_GET["ns"]) ? "ns=" . urlencode($_GET["ns"]) . "&" : "") : '')
);


$VERSION = "4.8.1";


/** Print HTML header
* @param string used in title, breadcrumb and heading, should be HTML escaped
* @param string
* @param mixed array("key" => "link", "key2" => array("link", "desc")), null for nothing, false for driver only, true for driver and server
* @param string used after colon in title and heading, should be HTML escaped
* @return null
*/
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $LANG, $VERSION, $adminer, $drivers, $jush;
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . $adminer->name());
	?>
<!DOCTYPE html>
<html lang="<?php echo $LANG; ?>" dir="<?php echo lang(74); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" type="text/css" href="<?php echo h(preg_replace("~\\?.*~", "", ME) . "?file=default.css&version=4.8.1"); ?>">
<?php echo script_src(preg_replace("~\\?.*~", "", ME) . "?file=functions.js&version=4.8.1");  if ($adminer->head()) { ?>
<link rel="shortcut icon" type="image/x-icon" href="<?php echo h(preg_replace("~\\?.*~", "", ME) . "?file=favicon.ico&version=4.8.1"); ?>">
<link rel="apple-touch-icon" href="<?php echo h(preg_replace("~\\?.*~", "", ME) . "?file=favicon.ico&version=4.8.1"); ?>">
<?php foreach ($adminer->css() as $css) { ?>
<link rel="stylesheet" type="text/css" href="<?php echo h($css); ?>">
<?php }  } ?>

<body class="<?php echo lang(74); ?> nojs">
<?php
	$filename = get_temp_dir() . "/adminer.version";
	if (!$_COOKIE["adminer_version"] && function_exists('openssl_verify') && file_exists($filename) && filemtime($filename) + 86400 > time()) { // 86400 - 1 day in seconds
		$version = unserialize(file_get_contents($filename));
		$public = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwqWOVuF5uw7/+Z70djoK
RlHIZFZPO0uYRezq90+7Amk+FDNd7KkL5eDve+vHRJBLAszF/7XKXe11xwliIsFs
DFWQlsABVZB3oisKCBEuI71J4kPH8dKGEWR9jDHFw3cWmoH3PmqImX6FISWbG3B8
h7FIx3jEaw5ckVPVTeo5JRm/1DZzJxjyDenXvBQ/6o9DgZKeNDgxwKzH+sw9/YCO
jHnq1cFpOIISzARlrHMa/43YfeNRAm/tsBXjSxembBPo7aQZLAWHmaj5+K19H10B
nCpz9Y++cipkVEiKRGih4ZEvjoFysEOdRLj6WiD/uUNky4xGeA6LaJqh5XpkFkcQ
fQIDAQAB
-----END PUBLIC KEY-----
";
		if (openssl_verify($version["version"], base64_decode($version["signature"]), $public) == 1) {
			$_COOKIE["adminer_version"] = $version["version"]; // doesn't need to send to the browser
		}
	}
	?>
<script<?php echo nonce(); ?>>
mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick<?php
	echo (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '$VERSION', '" . js_escape(ME) . "', '" . get_token() . "')"); // $token may be empty in auth.inc.php
	?>});
document.body.className = document.body.className.replace(/ nojs/, ' js');
var offlineMessage = '<?php echo js_escape(lang(75)); ?>';
var thousandsSeparator = '<?php echo js_escape(lang(5)); ?>';
</script>

<div id="help" class="jush-<?php echo $jush; ?> jsonly hidden"></div>
<?php echo script("mixin(qs('#help'), {onmouseover: function () { helpOpen = 1; }, onmouseout: helpMouseout});"); ?>

<div id="content">
<?php
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ? $link : ".") . '">' . $drivers[DRIVER] . '</a> &raquo; ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = $adminer->serverName(SERVER);
		$server = ($server != "" ? $server : lang(23));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link) . "' accesskey='1' title='Alt+Shift+1'>$server</a> &raquo; ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> &raquo; ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> &raquo; ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> &raquo; ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' class='jsonly hidden'></div>\n";
	restart_session();
	page_messages($error);
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define("PAGE_HEADER", 1);
}

/** Send HTTP headers
* @return null
*/
function page_headers() {
	global $adminer;
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach ($adminer->csp() as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	$adminer->headers();
}

/** Get Content Security Policy headers
* @return array of arrays with directive name in key, allowed sources in value
*/
function csp() {
	return array(
		array(
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'", // 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"connect-src" => "'self'",
			"frame-src" => "https://www.adminer.org",
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get a CSP nonce
* @return string Base64 value
*/
function get_nonce() {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages
* @param string
* @return null
*/
function page_messages($error) {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = $_SESSION["messages"][$uri];
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
}

/** Print HTML footer
* @param string "auth", "db", "ns"
* @return null
*/
function page_footer($missing = "") {
	global $adminer, $token;
	?>
</div>

<?php switch_lang();  if ($missing != "auth") { ?>
<form action="" method="post">
<p class="logout">
<input type="submit" name="logout" value="<?php echo lang(76); ?>" id="logout">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</p>
</form>
<?php } ?>
<div id="menu">
<?php $adminer->navigation($missing); ?>
</div>
<?php
	echo script("setupSubmitHighlight(document);");
}


/** PHP implementation of XXTEA encryption algorithm
* @author Ma Bingyao <andot@ujn.edu.cn>
* @link http://www.coolcode.cn/?action=show&id=128
*/

function int32($n) {
	while ($n >= 2147483648) {
		$n -= 4294967296;
	}
	while ($n <= -2147483649) {
		$n += 4294967296;
	}
	return (int) $n;
}

function long2str($v, $w) {
	$s = '';
	foreach ($v as $val) {
		$s .= pack('V', $val);
	}
	if ($w) {
		return substr($s, 0, end($v));
	}
	return $s;
}

function str2long($s, $w) {
	$v = array_values(unpack('V*', str_pad($s, 4 * ceil(strlen($s) / 4), "\0")));
	if ($w) {
		$v[] = strlen($s);
	}
	return $v;
}

function xxtea_mx($z, $y, $sum, $k) {
	return int32((($z >> 5 & 0x7FFFFFF) ^ $y << 2) + (($y >> 3 & 0x1FFFFFFF) ^ $z << 4)) ^ int32(($sum ^ $y) + ($k ^ $z));
}

/** Cipher
* @param string plain-text password
* @param string
* @return string binary cipher
*/
function encrypt_string($str, $key) {
	if ($str == "") {
		return "";
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, true);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = 0;
	while ($q-- > 0) {
		$sum = int32($sum + 0x9E3779B9);
		$e = $sum >> 2 & 3;
		for ($p=0; $p < $n; $p++) {
			$y = $v[$p + 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$z = int32($v[$p] + $mx);
			$v[$p] = $z;
		}
		$y = $v[0];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$z = int32($v[$n] + $mx);
		$v[$n] = $z;
	}
	return long2str($v, false);
}

/** Decipher
* @param string binary cipher
* @param string
* @return string plain-text password
*/
function decrypt_string($str, $key) {
	if ($str == "") {
		return "";
	}
	if (!$key) {
		return false;
	}
	$key = array_values(unpack("V*", pack("H*", md5($key))));
	$v = str2long($str, false);
	$n = count($v) - 1;
	$z = $v[$n];
	$y = $v[0];
	$q = floor(6 + 52 / ($n + 1));
	$sum = int32($q * 0x9E3779B9);
	while ($sum) {
		$e = $sum >> 2 & 3;
		for ($p=$n; $p > 0; $p--) {
			$z = $v[$p - 1];
			$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
			$y = int32($v[$p] - $mx);
			$v[$p] = $y;
		}
		$z = $v[$n];
		$mx = xxtea_mx($z, $y, $sum, $key[$p & 3 ^ $e]);
		$y = int32($v[0] - $mx);
		$v[0] = $y;
		$sum = int32($sum - 0x9E3779B9);
	}
	return long2str($v, true);
}


$connection = '';

$has_token = $_SESSION["token"];
if (!$has_token) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}
$token = get_token(); ///< @var string CSRF protection

$permanent = array();
if ($_COOKIE["adminer_permanent"]) {
	foreach (explode(" ", $_COOKIE["adminer_permanent"]) as $val) {
		list($key) = explode(":", $val);
		$permanent[$key] = $val;
	}
}

function add_invalid_login() {
	global $adminer;
	$fp = file_open_lock(get_temp_dir() . "/adminer.invalid");
	if (!$fp) {
		return;
	}
	$invalids = unserialize(stream_get_contents($fp));
	$time = time();
	if ($invalids) {
		foreach ($invalids as $ip => $val) {
			if ($val[0] < $time) {
				unset($invalids[$ip]);
			}
		}
	}
	$invalid = &$invalids[$adminer->bruteForceKey()];
	if (!$invalid) {
		$invalid = array($time + 30*60, 0); // active for 30 minutes
	}
	$invalid[1]++;
	file_write_unlock($fp, serialize($invalids));
}

function check_invalid_login() {
	global $adminer;
	$invalids = unserialize(@file_get_contents(get_temp_dir() . "/adminer.invalid")); // @ - may not exist
	$invalid = ($invalids ? $invalids[$adminer->bruteForceKey()] : array());
	$next_attempt = ($invalid[1] > 29 ? $invalid[0] - time() : 0); // allow 30 invalid attempts
	if ($next_attempt > 0) { //! do the same with permanent login
		auth_error(lang(77, ceil($next_attempt / 60)));
	}
}

$auth = $_POST["auth"];
if ($auth) {
	session_regenerate_id(); // defense against session fixation
	$vendor = $auth["driver"];
	$server = $auth["server"];
	$username = $auth["username"];
	$password = (string) $auth["password"];
	$db = $auth["db"];
	set_password($vendor, $server, $username, $password);
	$_SESSION["db"][$vendor][$server][$username][$db] = true;
	if ($auth["permanent"]) {
		$key = base64_encode($vendor) . "-" . base64_encode($server) . "-" . base64_encode($username) . "-" . base64_encode($db);
		$private = $adminer->permanentLogin(true);
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($password, $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (count($_POST) == 1 // 1 - auth
		|| DRIVER != $vendor
		|| SERVER != $server
		|| $_GET["username"] !== $username // "0" == "00"
		|| DB != $db
	) {
		redirect(auth_url($vendor, $server, $username, $db));
	}
	
} elseif ($_POST["logout"] && (!$has_token || verify_token())) {
	foreach (array("pwds", "db", "dbs", "queries") as $key) {
		set_session($key, null);
	}
	unset_permanent();
	redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang(78) . ' ' . lang(79));
	
} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = $adminer->permanentLogin();
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		set_password($vendor, $server, $username, decrypt_string(base64_decode($cipher), $private));
		$_SESSION["db"][$vendor][$server][$username][$db] = true;
	}
}

function unset_permanent() {
	global $permanent;
	foreach ($permanent as $key => $val) {
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		if ($vendor == DRIVER && $server == SERVER && $username == $_GET["username"] && $db == DB) {
			unset($permanent[$key]);
		}
	}
	cookie("adminer_permanent", implode(" ", $permanent));
}

/** Renders an error message and a login form
* @param string plain text
* @return null exits
*/
function auth_error($error) {
	global $adminer, $has_token;
	$session_name = session_name();
	if (isset($_GET["username"])) {
		header("HTTP/1.1 403 Forbidden"); // 401 requires sending WWW-Authenticate header
		if (($_COOKIE[$session_name] || $_GET[$session_name]) && !$has_token) {
			$error = lang(80);
		} else {
			restart_session();
			add_invalid_login();
			$password = get_password();
			if ($password !== null) {
				if ($password === false) {
					$error .= ($error ? '<br>' : '') . lang(81, target_blank(), '<code>permanentLogin()</code>');
				}
				set_password(DRIVER, SERVER, $_GET["username"], null);
			}
			unset_permanent();
		}
	}
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = lang(82);
	}
	$params = session_get_cookie_params();
	cookie("adminer_key", ($_COOKIE["adminer_key"] ? $_COOKIE["adminer_key"] : rand_string()), $params["lifetime"]);
	page_header(lang(27), $error, null);
	echo "<form action='' method='post'>\n";
	echo "<div>";
	if (hidden_fields($_POST, array("auth"))) { // expired session
		echo "<p class='message'>" . lang(83) . "\n";
	}
	echo "</div>\n";
	$adminer->loginForm();
	echo "</form>\n";
	page_footer("auth");
	exit;
}

if (isset($_GET["username"]) && !class_exists("Min_DB")) {
	unset($_SESSION["pwds"][DRIVER]);
	unset_permanent();
	page_header(lang(84), lang(85, implode(", ", $possible_drivers)), false);
	page_footer("auth");
	exit;
}

stop_session(true);

if (isset($_GET["username"]) && is_string(get_password())) {
	list($host, $port) = explode(":", SERVER, 2);
	if (preg_match('~^\s*([-+]?\d+)~', $port, $match) && ($match[1] < 1024 || $match[1] > 65535)) { // is_numeric('80#') would still connect to port 80
		auth_error(lang(86));
	}
	check_invalid_login();
	$connection = connect();
	$driver = new Min_Driver($connection);
}

$login = null;
if (!is_object($connection) || ($login = $adminer->login($_GET["username"], get_password())) !== true) {
	$error = (is_string($connection) ? h($connection) : (is_string($login) ? $login : lang(87)));
	auth_error($error . (preg_match('~^ | $~', get_password()) ? '<br>' . lang(88) : ''));
}

if ($_POST["logout"] && $has_token && !verify_token()) {
	page_header(lang(76), lang(89));
	page_footer("db");
	exit;
}

if ($auth && $_POST["token"]) {
	$_POST["token"] = $token; // reset token after explicit login
}

$error = ''; ///< @var string
if ($_POST) {
	if (!verify_token()) {
		$ini = "max_input_vars";
		$max_vars = ini_get($ini);
		if (extension_loaded("suhosin")) {
			foreach (array("suhosin.request.max_vars", "suhosin.post.max_vars") as $key) {
				$val = ini_get($key);
				if ($val && (!$max_vars || $val < $max_vars)) {
					$ini = $key;
					$max_vars = $val;
				}
			}
		}
		$error = (!$_POST["token"] && $max_vars
			? lang(90, "'$ini'")
			: lang(89) . ' ' . lang(91)
		);
	}
	
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
	// posted form with no data means that post_max_size exceeded because Adminer always sends token at least
	$error = lang(92, "'post_max_size'");
	if (isset($_GET["sql"])) {
		$error .= ' ' . lang(93);
	}
}


/** Print select result
* @param Min_Result
* @param Min_DB connection to examine indexes
* @param array
* @param int
* @return array $orgtables
*/
function select($result, $connection2 = null, $orgtables = array(), $limit = 0) {
	global $jush;
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	odd(''); // reset odd for each result
	for ($i=0; (!$limit || $i < $limit) && ($row = $result->fetch_row()); $i++) {
		if (!$i) {
			echo "<div class='scrollable'>\n";
			echo "<table cellspacing='0' class='nowrap'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = $field->orgtable;
				$orgname = $field->orgname;
				$return[$field->table] = $orgtable;
				if ($orgtables && $jush == "sql") { // MySQL EXPLAIN
					$links[$j] = ($name == "table" ? "table=" : ($name == "possible_keys" ? "indexes=" : null));
				} elseif ($orgtable != "") {
					if (!isset($indexes[$orgtable])) {
						// find primary key in each table
						$indexes[$orgtable] = array();
						foreach (indexes($orgtable, $connection2) as $index) {
							if ($index["type"] == "PRIMARY") {
								$indexes[$orgtable] = array_flip($index["columns"]);
								break;
							}
						}
						$columns[$orgtable] = $indexes[$orgtable];
					}
					if (isset($columns[$orgtable][$orgname])) {
						unset($columns[$orgtable][$orgname]);
						$indexes[$orgtable][$orgname] = $j;
						$links[$j] = $orgtable;
					}
				}
				if ($field->charsetnr == 63) { // 63 - binary
					$blobs[$j] = true;
				}
				$types[$j] = $field->type;
				echo "<th" . ($orgtable != "" || $field->name != $orgname ? " title='" . h(($orgtable != "" ? "$orgtable." : "") . $orgname) . "'" : "") . ">" . h($name)
					. ($orgtables ? doc_link(array(
						'sql' => "explain-output.html#explain_" . strtolower($name),
						'mariadb' => "explain/#the-columns-in-explain-select",
					)) : "")
				;
			}
			echo "</thead>\n";
		}
		echo "<tr" . odd() . ">";
		foreach ($row as $key => $val) {
			$link = "";
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($orgtables && $jush == "sql") { // MySQL EXPLAIN
					$table = $row[array_search("table=", $links)];
					$link = ME . $links[$key] . urlencode($orgtables[$table] != "" ? $orgtables[$table] : $table);
				} else {
					$link = ME . "edit=" . urlencode($links[$key]);
					foreach ($indexes[$links[$key]] as $col => $j) {
						$link .= "&where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
					}
				}
			} elseif (is_url($val)) {
				$link = $val;
			}
			if ($val === null) {
				$val = "<i>NULL</i>";
			} elseif ($blobs[$key] && !is_utf8($val)) {
				$val = "<i>" . lang(36, strlen($val)) . "</i>"; //! link to download
			} else {
				$val = h($val);
				if ($types[$key] == 254) { // 254 - char
					$val = "<code>$val</code>";
				}
			}
			if ($link) {
				$val = "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : '') . ">$val</a>";
			}
			echo "<td>$val";
		}
	}
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . lang(12)) . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @param string
* @return array ($table_name => $field)
*/
function referencable_primary($self) {
	$return = array(); // table_name => field
	foreach (table_status('', true) as $table_name => $table) {
		if ($table_name != $self && fk_support($table)) {
			foreach (fields($table_name) as $field) {
				if ($field["primary"]) {
					if ($return[$table_name]) { // multi column primary key
						unset($return[$table_name]);
						break;
					}
					$return[$table_name] = $field;
				}
			}
		}
	}
	return $return;
}

/** Get settings stored in a cookie
* @return array
*/
function adminer_settings() {
	parse_str($_COOKIE["adminer_settings"], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param string
* @return array
*/
function adminer_setting($key) {
	$settings = adminer_settings();
	return $settings[$key];
}

/** Store settings to a cookie
* @param array
* @return bool
*/
function set_adminer_settings($settings) {
	return cookie("adminer_settings", http_build_query($settings + adminer_settings()));
}

/** Print SQL <textarea> tag
* @param string
* @param string or array in which case [0] of every element is used
* @param int
* @param int
* @return null
*/
function textarea($name, $value, $rows = 10, $cols = 80) {
	global $jush;
	echo "<textarea name='$name' rows='$rows' cols='$cols' class='sqlarea jush-$jush' spellcheck='false' wrap='off'>";
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time, $elapsed)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Print table columns for type edit
* @param string
* @param array
* @param array
* @param array returned by referencable_primary()
* @param array extra types to prepend
* @return null
*/
function edit_type($key, $field, $collations, $foreign_keys = array(), $extra_types = array()) {
	global $structured_types, $types, $unsigned, $on_actions;
	$type = $field["type"];
	?>
<td><select name="<?php echo h($key); ?>[type]" class="type" aria-labelledby="label-type"><?php
if ($type && !isset($types[$type]) && !isset($foreign_keys[$type]) && !in_array($type, $extra_types)) {
	$extra_types[] = $type;
}
if ($foreign_keys) {
	$structured_types[lang(94)] = $foreign_keys;
}
echo optionlist(array_merge($extra_types, $structured_types), $type);
?></select><td><input name="<?php echo h($key); ?>[length]" value="<?php echo h($field["length"]); ?>" size="3"<?php echo (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : ""); //! type="number" with enabled JavaScript ?> aria-labelledby="label-length"><td class="options"><?php
	echo "<select name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . '><option value="">(' . lang(95) . ')' . optionlist($collations, $field["collation"]) . '</select>';
	echo ($unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>' . optionlist(array("" => "(" . lang(96) . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"])) . '</select>' : '');
	echo ($foreign_keys ? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . lang(97) . ")" . optionlist(explode("|", $on_actions), $field["on_delete"]) . "</select> " : " "); // space for IE
}

/** Filter length value including enums
* @param string
* @return string
*/
function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*\\(?\\s*$enum_length(?:\\s*,\\s*$enum_length)*+\\s*\\)?\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches)
		? "(" . implode(",", $matches[0]) . ")"
		: preg_replace('~^[0-9].*~', '(\0)', preg_replace('~[^-0-9,+()[\]]~', '', $length))
	);
}

/** Create SQL string from field type
* @param array
* @param string
* @return string
*/
function process_type($field, $collate = "COLLATE") {
	global $unsigned;
	return " $field[type]"
		. process_length($field["length"])
		. (preg_match(number_type(), $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate " . q($field["collation"]) : "")
	;
}

/** Create SQL string from field
* @param array basic field information
* @param array information about field type
* @return array array("field", "type", "NULL", "DEFAULT", "ON UPDATE", "COMMENT", "AUTO_INCREMENT")
*/
function process_field($field, $type_field) {
	return array(
		idf_escape(trim($field["field"])),
		process_type($type_field),
		($field["null"] ? " NULL" : " NOT NULL"), // NULL for timestamp
		default_value($field),
		(preg_match('~timestamp|datetime~', $field["type"]) && $field["on_update"] ? " ON UPDATE $field[on_update]" : ""),
		(support("comment") && $field["comment"] != "" ? " COMMENT " . q($field["comment"]) : ""),
		($field["auto_increment"] ? auto_increment() : null),
	);
}

/** Get default value clause
* @param array
* @return string
*/
function default_value($field) {
	$default = $field["default"];
	return ($default === null ? "" : " DEFAULT " . (preg_match('~char|binary|text|enum|set~', $field["type"]) || preg_match('~^(?![a-z])~i', $default) ? q($default) : $default));
}

/** Get type class to use in CSS
* @param string
* @return string class=''
*/
function type_class($type) {
	foreach (array(
		'char' => 'text',
		'date' => 'time|year',
		'binary' => 'blob',
		'enum' => 'set',
	) as $key => $val) {
		if (preg_match("~$key|$val~", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param array
* @param array
* @param string TABLE or PROCEDURE
* @param array returned by referencable_primary()
* @return null
*/
function edit_fields($fields, $collations, $type = "TABLE", $foreign_keys = array()) {
	global $inout;
	$fields = array_values($fields);
	$default_class = (($_POST ? $_POST["defaults"] : adminer_setting("defaults")) ? "" : " class='hidden'");
	$comment_class = (($_POST ? $_POST["comments"] : adminer_setting("comments")) ? "" : " class='hidden'");
	?>
<thead><tr>
<?php if ($type == "PROCEDURE") { ?><td><?php } ?>
<th id="label-name"><?php echo ($type == "TABLE" ? lang(98) : lang(99)); ?>
<td id="label-type"><?php echo lang(38); ?><textarea id="enum-edit" rows="4" cols="12" wrap="off" style="display: none;"></textarea><?php echo script("qs('#enum-edit').onblur = editingLengthBlur;"); ?>
<td id="label-length"><?php echo lang(100); ?>
<td><?php echo lang(101); /* no label required, options have their own label */  if ($type == "TABLE") { ?>
<td id="label-null">NULL
<td><input type="radio" name="auto_increment_col" value=""><acronym id="label-ai" title="<?php echo lang(40); ?>">AI</acronym><?php echo doc_link(array(
	'sql' => "example-auto-increment.html",
	'mariadb' => "auto_increment/",
	
	
	
)); ?>
<td id="label-default"<?php echo $default_class; ?>><?php echo lang(41);  echo (support("comment") ? "<td id='label-comment'$comment_class>" . lang(39) : "");  } ?>
<td><?php echo "<input type='image' class='icon' name='add[" . (support("move_col") ? 0 : count($fields)) . "]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . lang(102) . "'>" . script("row_count = " . count($fields) . ";"); ?>
</thead>
<tbody>
<?php
	echo script("mixin(qsl('tbody'), {onclick: editingClick, onkeydown: editingKeydown, oninput: editingInput});");
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i])) && (support("drop_col") || $orig == "");
		?>
<tr<?php echo ($display ? "" : " style='display: none;'"); ?>>
<?php echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", $inout), $field["inout"]) : ""); ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo h($field["field"]); ?>" data-maxlength="64" autocapitalize="off" aria-labelledby="label-name"><?php } ?>
<input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo h($orig); ?>"><?php edit_type("fields[$i]", $field, $collations, $foreign_keys);  if ($type == "TABLE") { ?>
<td><?php echo checkbox("fields[$i][null]", 1, $field["null"], "", "", "block", "label-null"); ?>
<td><label class="block"><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked<?php } ?> aria-labelledby="label-ai"></label><td<?php echo $default_class; ?>><?php
			echo checkbox("fields[$i][has_default]", 1, $field["has_default"], "", "", "", "label-default"); ?><input name="fields[<?php echo $i; ?>][default]" value="<?php echo h($field["default"]); ?>" aria-labelledby="label-default"><?php
			echo (support("comment") ? "<td$comment_class><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' data-maxlength='" . (min_version(5.5) ? 1024 : 255) . "' aria-labelledby='label-comment'>" : "");
		}
		echo "<td>";
		echo (support("move_col") ?
			"<input type='image' class='icon' name='add[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . lang(102) . "'> "
			. "<input type='image' class='icon' name='up[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=up.gif&version=4.8.1") . "' alt='↑' title='" . lang(103) . "'> "
			. "<input type='image' class='icon' name='down[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=down.gif&version=4.8.1") . "' alt='↓' title='" . lang(104) . "'> "
		: "");
		echo ($orig == "" || support("drop_col") ? "<input type='image' class='icon' name='drop_col[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=cross.gif&version=4.8.1") . "' alt='x' title='" . lang(105) . "'>" : "");
	}
}

/** Move fields up and down or add field
* @param array
* @return bool
*/
function process_fields(&$fields) {
	$offset = 0;
	if ($_POST["up"]) {
		$last = 0;
		foreach ($fields as $key => $field) {
			if (key($_POST["up"]) == $key) {
				unset($fields[$key]);
				array_splice($fields, $last, 0, array($field));
				break;
			}
			if (isset($field["field"])) {
				$last = $offset;
			}
			$offset++;
		}
	} elseif ($_POST["down"]) {
		$found = false;
		foreach ($fields as $key => $field) {
			if (isset($field["field"]) && $found) {
				unset($fields[key($_POST["down"])]);
				array_splice($fields, $offset, 0, array($found));
				break;
			}
			if (key($_POST["down"]) == $key) {
				$found = $field;
			}
			$offset++;
		}
	} elseif ($_POST["add"]) {
		$fields = array_values($fields);
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	} elseif (!$_POST["drop_col"]) {
		return false;
	}
	return true;
}

/** Callback used in routine()
* @param array
* @return string
*/
function normalize_enum($match) {
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($match[0][0] . $match[0][0], $match[0][0], substr($match[0], 1, -1))), '\\')) . "'";
}

/** Issue grant or revoke commands
* @param string GRANT or REVOKE
* @param array
* @param string
* @param string
* @return bool
*/
function grant($grant, $privileges, $columns, $on) {
	if (!$privileges) {
		return true;
	}
	if ($privileges == array("ALL PRIVILEGES", "GRANT OPTION")) {
		// can't be granted or revoked together
		return ($grant == "GRANT"
			? queries("$grant ALL PRIVILEGES$on WITH GRANT OPTION")
			: queries("$grant ALL PRIVILEGES$on") && queries("$grant GRANT OPTION$on")
		);
	}
	return queries("$grant " . preg_replace('~(GRANT OPTION)\([^)]*\)~', '\1', implode("$columns, ", $privileges) . $columns) . $on);
}

/** Drop old object and create a new one
* @param string drop old object query
* @param string create new object query
* @param string drop new object query
* @param string create test object query
* @param string drop test object query
* @param string
* @param string
* @param string
* @param string
* @param string
* @param string
* @return null redirect in success
*/
function drop_create($drop, $create, $drop_created, $test, $drop_test, $location, $message_drop, $message_alter, $message_create, $old_name, $new_name) {
	if ($_POST["drop"]) {
		query_redirect($drop, $location, $message_drop);
	} elseif ($old_name == "") {
		query_redirect($create, $location, $message_create);
	} elseif ($old_name != $new_name) {
		$created = queries($create);
		queries_redirect($location, $message_alter, $created && queries($drop));
		if ($created) {
			queries($drop_created);
		}
	} else {
		queries_redirect(
			$location,
			$message_alter,
			queries($test) && queries($drop_test) && queries($drop) && queries($create)
		);
	}
}

/** Generate SQL query for creating trigger
* @param string
* @param array result of trigger()
* @return string
*/
function create_trigger($on, $row) {
	global $jush;
	$timing_event = " $row[Timing] $row[Event]" . (preg_match('~ OF~', $row["Event"]) ? " $row[Of]" : ""); // SQL injection
	return "CREATE TRIGGER "
		. idf_escape($row["Trigger"])
		. ($jush == "mssql" ? $on . $timing_event : $timing_event . $on)
		. rtrim(" $row[Type]\n$row[Statement]", ";")
		. ";"
	;
}

/** Generate SQL query for creating routine
* @param string "PROCEDURE" or "FUNCTION"
* @param array result of routine()
* @return string
*/
function create_routine($routine, $row) {
	global $inout, $jush;
	$set = array();
	$fields = (array) $row["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (preg_match("~^($inout)\$~", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$definition = rtrim("\n$row[definition]", ";");
	return "CREATE $routine "
		. idf_escape(trim($row["name"]))
		. " (" . implode(", ", $set) . ")"
		. (isset($_GET["function"]) ? " RETURNS" . process_type($row["returns"], "CHARACTER SET") : "")
		. ($row["language"] ? " LANGUAGE $row[language]" : "")
		. ($jush == "pgsql" ? " AS " . q($definition) : "$definition;")
	;
}

/** Remove current user definer from SQL command
* @param string
* @return string
*/
function remove_definer($query) {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', logged_user()) . '`~', '\1', $query); //! proper escaping of user
}

/** Format foreign key to use in SQL query
* @param array ("db" => string, "ns" => string, "table" => string, "source" => array, "target" => array, "on_delete" => one of $on_actions, "on_update" => one of $on_actions)
* @return string
*/
function format_foreign_key($foreign_key) {
	global $on_actions;
	$db = $foreign_key["db"];
	$ns = $foreign_key["ns"];
	return " FOREIGN KEY (" . implode(", ", array_map('idf_escape', $foreign_key["source"])) . ") REFERENCES "
		. ($db != "" && $db != $_GET["db"] ? idf_escape($db) . "." : "")
		. ($ns != "" && $ns != $_GET["ns"] ? idf_escape($ns) . "." : "")
		. table($foreign_key["table"])
		. " (" . implode(", ", array_map('idf_escape', $foreign_key["target"])) . ")" //! reuse $name - check in older MySQL versions
		. (preg_match("~^($on_actions)\$~", $foreign_key["on_delete"]) ? " ON DELETE $foreign_key[on_delete]" : "")
		. (preg_match("~^($on_actions)\$~", $foreign_key["on_update"]) ? " ON UPDATE $foreign_key[on_update]" : "")
	;
}

/** Add a file to TAR
* @param string
* @param TmpFile
* @return null prints the output
*/
function tar_file($filename, $tmp_file) {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct($tmp_file->size), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return[$i]);
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	echo $return;
	echo str_repeat("\0", 512 - strlen($return));
	$tmp_file->send();
	echo str_repeat("\0", 511 - ($tmp_file->size + 511) % 512);
}

/** Get INI bytes value
* @param string
* @return int
*/
function ini_bytes($ini) {
	$val = ini_get($ini);
	switch (strtolower(substr($val, -1))) {
		case 'g': $val *= 1024; // no break
		case 'm': $val *= 1024; // no break
		case 'k': $val *= 1024;
	}
	return $val;
}

/** Create link to database documentation
* @param array $jush => $path
* @param string HTML code
* @return string HTML code
*/
function doc_link($paths, $text = "<sup>?</sup>") {
	global $jush, $connection;
	$server_info = $connection->server_info;
	$version = preg_replace('~^(\d\.?\d).*~s', '\1', $server_info); // two most significant digits
	$urls = array(
		'sql' => "https://dev.mysql.com/doc/refman/$version/en/",
		'sqlite' => "https://www.sqlite.org/",
		'pgsql' => "https://www.postgresql.org/docs/$version/",
		'mssql' => "https://msdn.microsoft.com/library/",
		'oracle' => "https://www.oracle.com/pls/topic/lookup?ctx=db" . preg_replace('~^.* (\d+)\.(\d+)\.\d+\.\d+\.\d+.*~s', '\1\2', $server_info) . "&id=",
	);
	if (preg_match('~MariaDB~', $server_info)) {
		$urls['sql'] = "https://mariadb.com/kb/en/library/";
		$paths['sql'] = (isset($paths['mariadb']) ? $paths['mariadb'] : str_replace(".html", "/", $paths['sql']));
	}
	return ($paths[$jush] ? "<a href='" . h($urls[$jush] . $paths[$jush]) . "'" . target_blank() . ">$text</a>" : "");
}

/** Wrap gzencode() for usage in ob_start()
* @param string
* @return string
*/
function ob_gzencode($string) {
	// ob_start() callback recieves an optional parameter $phase but gzencode() accepts optional parameter $level
	return gzencode($string);
}

/** Compute size of database
* @param string
* @return string formatted
*/
function db_size($db) {
	global $connection;
	if (!$connection->select_db($db)) {
		return "?";
	}
	$return = 0;
	foreach (table_status() as $table_status) {
		$return += $table_status["Data_length"] + $table_status["Index_length"];
	}
	return format_number($return);
}

/** Print SET NAMES if utf8mb4 might be needed
* @param string
* @return null
*/
function set_utf8mb4($create) {
	global $connection;
	static $set = false;
	if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
		$set = true;
		echo "SET NAMES " . charset($connection) . ";\n\n";
	}
}


function connect_error() {
	global $adminer, $connection, $token, $error, $drivers;
	if (DB != "") {
		header("HTTP/1.1 404 Not Found");
		page_header(lang(26) . ": " . h(DB), lang(106), true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), lang(107), drop_databases($_POST["db"]));
		}
		
		page_header(lang(108), $error, false);
		echo "<p class='links'>\n";
		foreach (array(
			'database' => lang(109),
			'privileges' => lang(60),
			'processlist' => lang(110),
			'variables' => lang(111),
			'status' => lang(112),
		) as $key => $val) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . lang(113, $drivers[DRIVER], "<b>" . h($connection->server_info) . "</b>", "<b>$connection->extension</b>") . "\n";
		echo "<p>" . lang(114, "<b>" . h(logged_user()) . "</b>") . "\n";
		$databases = $adminer->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table cellspacing='0' class='checkable'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo "<thead><tr>"
				. (support("database") ? "<td>" : "")
				. "<th>" . lang(26) . " - <a href='" . h(ME) . "refresh=1'>" . lang(115) . "</a>"
				. "<td>" . lang(116)
				. "<td>" . lang(117)
				. "<td>" . lang(118) . " - <a href='" . h(ME) . "dbsize=1'>" . lang(119) . "</a>" . script("qsl('a').onclick = partial(ajaxSetHtml, '" . js_escape(ME) . "script=connect');", "")
				. "</thead>\n"
			;
			
			$databases = ($_GET["dbsize"] ? count_tables($databases) : array_flip($databases));
			
			foreach ($databases as $db => $tables) {
				$root = h(ME) . "db=" . urlencode($db);
				$id = h("Db-" . $db);
				echo "<tr" . odd() . ">" . (support("database") ? "<td>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]), "", "", "", $id) : "");
				echo "<th><a href='$root' id='$id'>" . h($db) . "</a>";
				$collation = h(db_collation($db, $collations));
				echo "<td>" . (support("database") ? "<a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . lang(56) . "'>$collation</a>" : $collation);
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . lang(59) . "'>" . ($_GET["dbsize"] ? $tables : "?") . "</a>";
				echo "<td align='right' id='size-" . h($db) . "'>" . ($_GET["dbsize"] ? db_size($db) : "?");
				echo "\n";
			}
			
			echo "</table>\n";
			echo (support("database")
				? "<div class='footer'><div>\n"
					. "<fieldset><legend>" . lang(120) . " <span id='selected'></span></legend><div>\n"
					. "<input type='hidden' name='all' value=''>" . script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };") // used by trCheck()
					. "<input type='submit' name='drop' value='" . lang(121) . "'>" . confirm() . "\n"
					. "</div></fieldset>\n"
					. "</div></div>\n"
				: ""
			);
			echo "<input type='hidden' name='token' value='$token'>\n";
			echo "</form>\n";
			echo script("tableCheck();");
		}
	}
	
	page_footer("db");
}

if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (isset($_GET["import"])) {
	$_GET["sql"] = $_GET["import"];
}

if (!(DB != "" ? $connection->select_db(DB) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"]) || $_GET["script"] == "connect" || $_GET["script"] == "kill")) {
	if (DB != "" || $_GET["refresh"]) {
		restart_session();
		set_session("dbs", null);
	}
	connect_error(); // separate function to catch SQLite error
	exit;
}




$on_actions = "RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT"; ///< @var string used in foreign_keys()



class TmpFile {
	var $handler;
	var $size;
	
	function __construct() {
		$this->handler = tmpfile();
	}
	
	function write($contents) {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}
	
	function send() {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
	
}


$enum_length = "'(?:''|[^'\\\\]|\\\\.)*'";
$inout = "IN|OUT|INOUT";

if (isset($_GET["select"]) && ($_POST["edit"] || $_POST["clone"]) && !$_POST["save"]) {
	$_GET["edit"] = $_GET["select"];
}
if (isset($_GET["callf"])) {
	$_GET["call"] = $_GET["callf"];
}
if (isset($_GET["function"])) {
	$_GET["procedure"] = $_GET["function"];
}

if (isset($_GET["download"])) {
	
$TABLE = $_GET["download"];
$fields = fields($TABLE);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . friendly_url("$TABLE-" . implode("_", $_GET["where"])) . "." . friendly_url($_GET["field"]));
$select = array(idf_escape($_GET["field"]));
$result = $driver->select($TABLE, $select, array(where($_GET, $fields)), $select);
$row = ($result ? $result->fetch_row() : array());
echo $driver->value($row[0], $fields[$_GET["field"]]);
exit; // don't output footer

} elseif (isset($_GET["table"])) {
	
$TABLE = $_GET["table"];
$fields = fields($TABLE);
if (!$fields) {
	$error = error();
}
$table_status = table_status1($TABLE, true);
$name = $adminer->tableName($table_status);

page_header(($fields && is_view($table_status) ? $table_status['Engine'] == 'materialized view' ? lang(122) : lang(123) : lang(124)) . ": " . ($name != "" ? $name : h($TABLE)), $error);

$adminer->selectLinks($table_status);
$comment = $table_status["Comment"];
if ($comment != "") {
	echo "<p class='nowrap'>" . lang(39) . ": " . h($comment) . "\n";
}

if ($fields) {
	$adminer->tableStructurePrint($fields);
}

if (!is_view($table_status)) {
	if (support("indexes")) {
		echo "<h3 id='indexes'>" . lang(125) . "</h3>\n";
		$indexes = indexes($TABLE);
		if ($indexes) {
			$adminer->tableIndexesPrint($indexes);
		}
		echo '<p class="links"><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . lang(126) . "</a>\n";
	}
	
	if (fk_support($table_status)) {
		echo "<h3 id='foreign-keys'>" . lang(94) . "</h3>\n";
		$foreign_keys = foreign_keys($TABLE);
		if ($foreign_keys) {
			echo "<table cellspacing='0'>\n";
			echo "<thead><tr><th>" . lang(127) . "<td>" . lang(128) . "<td>" . lang(97) . "<td>" . lang(96) . "<td></thead>\n";
			foreach ($foreign_keys as $name => $foreign_key) {
				echo "<tr title='" . h($name) . "'>";
				echo "<th><i>" . implode("</i>, <i>", array_map('h', $foreign_key["source"])) . "</i>";
				echo "<td><a href='" . h($foreign_key["db"] != "" ? preg_replace('~db=[^&]*~', "db=" . urlencode($foreign_key["db"]), ME) : ($foreign_key["ns"] != "" ? preg_replace('~ns=[^&]*~', "ns=" . urlencode($foreign_key["ns"]), ME) : ME)) . "table=" . urlencode($foreign_key["table"]) . "'>"
					. ($foreign_key["db"] != "" ? "<b>" . h($foreign_key["db"]) . "</b>." : "") . ($foreign_key["ns"] != "" ? "<b>" . h($foreign_key["ns"]) . "</b>." : "") . h($foreign_key["table"])
					. "</a>"
				;
				echo "(<i>" . implode("</i>, <i>", array_map('h', $foreign_key["target"])) . "</i>)";
				echo "<td>" . h($foreign_key["on_delete"]) . "\n";
				echo "<td>" . h($foreign_key["on_update"]) . "\n";
				echo '<td><a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . lang(129) . '</a>';
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . lang(130) . "</a>\n";
	}
}

if (support(is_view($table_status) ? "view_trigger" : "trigger")) {
	echo "<h3 id='triggers'>" . lang(131) . "</h3>\n";
	$triggers = triggers($TABLE);
	if ($triggers) {
		echo "<table cellspacing='0'>\n";
		foreach ($triggers as $key => $val) {
			echo "<tr valign='top'><td>" . h($val[0]) . "<td>" . h($val[1]) . "<th>" . h($key) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . lang(129) . "</a>\n";
		}
		echo "</table>\n";
	}
	echo '<p class="links"><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . lang(132) . "</a>\n";
}

} elseif (isset($_GET["schema"])) {
	
page_header(lang(59), "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

$table_pos = array();
$table_pos_js = array();
$SCHEMA = ($_GET["schema"] ? $_GET["schema"] : $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]); // $_COOKIE["adminer_schema"] was used before 3.2.0 //! ':' in table name
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

$top = 0;
$base_left = -1;
$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
$lefts = array(); // float => bool
foreach (table_status('', true) as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}
	$pos = 0;
	$schema[$table]["fields"] = array();
	foreach (fields($table) as $name => $field) {
		$pos += 1.25;
		$field["pos"] = $pos;
		$schema[$table]["fields"][$name] = $field;
	}
	$schema[$table]["pos"] = ($table_pos[$table] ? $table_pos[$table] : array($top, 0));
	foreach ($adminer->foreignKeys($table) as $val) {
		if (!$val["db"]) {
			$left = $base_left;
			if ($table_pos[$table][1] || $table_pos[$val["table"]][1]) {
				$left = min(floatval($table_pos[$table][1]), floatval($table_pos[$val["table"]][1])) - 1;
			} else {
				$base_left -= .1;
			}
			while ($lefts[(string) $left]) {
				// find free $left
				$left -= .0001;
			}
			$schema[$table]["references"][$val["table"]][(string) $left] = array($val["source"], $val["target"]);
			$referenced[$val["table"]][$table][(string) $left] = $val["target"];
			$lefts[(string) $left] = true;
		}
	}
	$top = max($top, $schema[$table]["pos"][0] + 2.5 + $pos);
}

?>
<div id="schema" style="height: <?php echo $top; ?>em;">
<script<?php echo nonce(); ?>>
qs('#schema').onselectstart = function () { return false; };
var tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
var em = qs('#schema').offsetHeight / <?php echo $top; ?>;
document.onmousemove = schemaMousemove;
document.onmouseup = partialArg(schemaMouseup, '<?php echo js_escape(DB); ?>');
</script>
<?php
foreach ($schema as $name => $table) {
	echo "<div class='table' style='top: " . $table["pos"][0] . "em; left: " . $table["pos"][1] . "em;'>";
	echo '<a href="' . h(ME) . 'table=' . urlencode($name) . '"><b>' . h($name) . "</b></a>";
	echo script("qsl('div').onmousedown = schemaMousedown;");
	
	foreach ($table["fields"] as $field) {
		$val = '<span' . type_class($field["type"]) . ' title="' . h($field["full_type"] . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo "<br>" . ($field["primary"] ? "<i>$val</i>" : $val);
	}
	
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$left1 = $left - $table_pos[$name][1];
			$i = 0;
			foreach ($ref[0] as $source) {
				echo "\n<div class='references' title='" . h($target_name) . "' id='refs$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$source]["pos"] . "em; padding-top: .5em;'><div style='border-top: 1px solid Gray; width: " . (-$left1) . "em;'></div></div>";
			}
		}
	}
	
	foreach ((array) $referenced[$name] as $target_name => $refs) {
		foreach ($refs as $left => $columns) {
			$left1 = $left - $table_pos[$name][1];
			$i = 0;
			foreach ($columns as $target) {
				echo "\n<div class='references' title='" . h($target_name) . "' id='refd$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $table["fields"][$target]["pos"] . "em; height: 1.25em; background: url(" . h(preg_replace("~\\?.*~", "", ME) . "?file=arrow.gif) no-repeat right center;&version=4.8.1") . "'><div style='height: .5em; border-bottom: 1px solid Gray; width: " . (-$left1) . "em;'></div></div>";
			}
		}
	}
	
	echo "\n</div>\n";
}

foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$min_pos = $top;
			$max_pos = -10;
			foreach ($ref[0] as $key => $source) {
				$pos1 = $table["pos"][0] + $table["fields"][$source]["pos"];
				$pos2 = $schema[$target_name]["pos"][0] + $schema[$target_name]["fields"][$ref[1][$key]]["pos"];
				$min_pos = min($min_pos, $pos1, $pos2);
				$max_pos = max($max_pos, $pos1, $pos2);
			}
			echo "<div class='references' id='refl$left' style='left: $left" . "em; top: $min_pos" . "em; padding: .5em 0;'><div style='border-right: 1px solid Gray; margin-top: 1px; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
		}
	}
}
?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link"><?php echo lang(133); ?></a>
<?php
} elseif (isset($_GET["dump"])) {
	
$TABLE = $_GET["dump"];

if ($_POST && !$error) {
	$cookie = "";
	foreach (array("output", "format", "db_style", "routines", "events", "table_style", "auto_increment", "triggers", "data_style") as $key) {
		$cookie .= "&$key=" . urlencode($_POST[$key]);
	}
	cookie("adminer_export", substr($cookie, 1));
	$tables = array_flip((array) $_POST["tables"]) + array_flip((array) $_POST["data"]);
	$ext = dump_headers(
		(count($tables) == 1 ? key($tables) : DB),
		(DB == "" || count($tables) > 1));
	$is_sql = preg_match('~sql~', $_POST["format"]);

	if ($is_sql) {
		echo "-- Adminer $VERSION " . $drivers[DRIVER] . " " . str_replace("\n", " ", $connection->server_info) . " dump\n\n";
		if ($jush == "sql") {
			echo "SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
" . ($_POST["data_style"] ? "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
" : "") . "
";
			$connection->query("SET time_zone = '+00:00'");
			$connection->query("SET sql_mode = ''");
		}
	}

	$style = $_POST["db_style"];
	$databases = array(DB);
	if (DB == "") {
		$databases = $_POST["databases"];
		if (is_string($databases)) {
			$databases = explode("\n", rtrim(str_replace("\r", "", $databases), "\n"));
		}
	}

	foreach ((array) $databases as $db) {
		$adminer->dumpDatabase($db);
		if ($connection->select_db($db)) {
			if ($is_sql && preg_match('~CREATE~', $style) && ($create = $connection->result("SHOW CREATE DATABASE " . idf_escape($db), 1))) {
				set_utf8mb4($create);
				if ($style == "DROP+CREATE") {
					echo "DROP DATABASE IF EXISTS " . idf_escape($db) . ";\n";
				}
				echo "$create;\n";
			}
			if ($is_sql) {
				if ($style) {
					echo use_sql($db) . ";\n\n";
				}
				$out = "";

				if ($_POST["routines"]) {
					foreach (array("FUNCTION", "PROCEDURE") as $routine) {
						foreach (get_rows("SHOW $routine STATUS WHERE Db = " . q($db), null, "-- ") as $row) {
							$create = remove_definer($connection->result("SHOW CREATE $routine " . idf_escape($row["Name"]), 2));
							set_utf8mb4($create);
							$out .= ($style != 'DROP+CREATE' ? "DROP $routine IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "") . "$create;;\n\n";
						}
					}
				}

				if ($_POST["events"]) {
					foreach (get_rows("SHOW EVENTS", null, "-- ") as $row) {
						$create = remove_definer($connection->result("SHOW CREATE EVENT " . idf_escape($row["Name"]), 3));
						set_utf8mb4($create);
						$out .= ($style != 'DROP+CREATE' ? "DROP EVENT IF EXISTS " . idf_escape($row["Name"]) . ";;\n" : "") . "$create;;\n\n";
					}
				}

				if ($out) {
					echo "DELIMITER ;;\n\n$out" . "DELIMITER ;\n\n";
				}
			}

			if ($_POST["table_style"] || $_POST["data_style"]) {
				$views = array();
				foreach (table_status('', true) as $name => $table_status) {
					$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
					$data = (DB == "" || in_array($name, (array) $_POST["data"]));
					if ($table || $data) {
						if ($ext == "tar") {
							$tmp_file = new TmpFile;
							ob_start(array($tmp_file, 'write'), 1e5);
						}

						$adminer->dumpTable($name, ($table ? $_POST["table_style"] : ""), (is_view($table_status) ? 2 : 0));
						if (is_view($table_status)) {
							$views[] = $name;
						} elseif ($data) {
							$fields = fields($name);
							$adminer->dumpData($name, $_POST["data_style"], "SELECT *" . convert_fields($fields, $fields) . " FROM " . table($name));
						}
						if ($is_sql && $_POST["triggers"] && $table && ($triggers = trigger_sql($name))) {
							echo "\nDELIMITER ;;\n$triggers\nDELIMITER ;\n";
						}

						if ($ext == "tar") {
							ob_end_flush();
							tar_file((DB != "" ? "" : "$db/") . "$name.csv", $tmp_file);
						} elseif ($is_sql) {
							echo "\n";
						}
					}
				}

				// add FKs after creating tables (except in MySQL which uses SET FOREIGN_KEY_CHECKS=0)
				if (function_exists('foreign_keys_sql')) {
					foreach (table_status('', true) as $name => $table_status) {
						$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
						if ($table && !is_view($table_status)) {
							echo foreign_keys_sql($name);
						}
					}
				}

				foreach ($views as $view) {
					$adminer->dumpTable($view, $_POST["table_style"], 1);
				}

				if ($ext == "tar") {
					echo pack("x512");
				}
			}
		}
	}

	if ($is_sql) {
		echo "-- " . $connection->result("SELECT NOW()") . "\n";
	}
	exit;
}

page_header(lang(62), $error, ($_GET["export"] != "" ? array("table" => $_GET["export"]) : array()), h(DB));
?>

<form action="" method="post">
<table cellspacing="0" class="layout">
<?php
$db_style = array('', 'USE', 'DROP+CREATE', 'CREATE');
$table_style = array('', 'DROP+CREATE', 'CREATE');
$data_style = array('', 'TRUNCATE+INSERT', 'INSERT');
if ($jush == "sql") { //! use insertUpdate() in all drivers
	$data_style[] = 'INSERT+UPDATE';
}
parse_str($_COOKIE["adminer_export"], $row);
if (!$row) {
	$row = array("output" => "text", "format" => "sql", "db_style" => (DB != "" ? "" : "CREATE"), "table_style" => "DROP+CREATE", "data_style" => "INSERT");
}
if (!isset($row["events"])) { // backwards compatibility
	$row["routines"] = $row["events"] = ($_GET["dump"] == "");
	$row["triggers"] = $row["table_style"];
}

echo "<tr><th>" . lang(134) . "<td>" . html_select("output", $adminer->dumpOutput(), $row["output"], 0) . "\n"; // 0 - radio

echo "<tr><th>" . lang(135) . "<td>" . html_select("format", $adminer->dumpFormat(), $row["format"], 0) . "\n"; // 0 - radio

echo ($jush == "sqlite" ? "" : "<tr><th>" . lang(26) . "<td>" . html_select('db_style', $db_style, $row["db_style"])
	. (support("routine") ? checkbox("routines", 1, $row["routines"], lang(136)) : "")
	. (support("event") ? checkbox("events", 1, $row["events"], lang(137)) : "")
);

echo "<tr><th>" . lang(117) . "<td>" . html_select('table_style', $table_style, $row["table_style"])
	. checkbox("auto_increment", 1, $row["auto_increment"], lang(40))
	. (support("trigger") ? checkbox("triggers", 1, $row["triggers"], lang(131)) : "")
;

echo "<tr><th>" . lang(138) . "<td>" . html_select('data_style', $data_style, $row["data_style"]);
?>
</table>
<p><input type="submit" value="<?php echo lang(62); ?>">
<input type="hidden" name="token" value="<?php echo $token; ?>">

<table cellspacing="0">
<?php
echo script("qsl('table').onclick = dumpClick;");
$prefixes = array();
if (DB != "") {
	$checked = ($TABLE != "" ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$checked>" . lang(117) . "</label>" . script("qs('#check-tables').onclick = partial(formCheck, /^tables\\[/);", "");
	echo "<th style='text-align: right;'><label class='block'>" . lang(138) . "<input type='checkbox' id='check-data'$checked></label>" . script("qs('#check-data').onclick = partial(formCheck, /^data\\[/);", "");
	echo "</thead>\n";

	$views = "";
	$tables_list = tables_list();
	foreach ($tables_list as $name => $type) {
		$prefix = preg_replace('~_.*~', '', $name);
		$checked = ($TABLE == "" || $TABLE == (substr($TABLE, -1) == "%" ? "$prefix%" : $name)); //! % may be part of table name
		$print = "<tr><td>" . checkbox("tables[]", $name, $checked, $name, "", "block");
		if ($type !== null && !preg_match('~table~i', $type)) {
			$views .= "$print\n";
		} else {
			echo "$print<td align='right'><label class='block'><span id='Rows-" . h($name) . "'></span>" . checkbox("data[]", $name, $checked) . "</label>\n";
		}
		$prefixes[$prefix]++;
	}
	echo $views;

	if ($tables_list) {
		echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
	}

} else {
	echo "<thead><tr><th style='text-align: left;'>";
	echo "<label class='block'><input type='checkbox' id='check-databases'" . ($TABLE == "" ? " checked" : "") . ">" . lang(26) . "</label>";
	echo script("qs('#check-databases').onclick = partial(formCheck, /^databases\\[/);", "");
	echo "</thead>\n";
	$databases = $adminer->databases();
	if ($databases) {
		foreach ($databases as $db) {
			if (!information_schema($db)) {
				$prefix = preg_replace('~_.*~', '', $db);
				echo "<tr><td>" . checkbox("databases[]", $db, $TABLE == "" || $TABLE == "$prefix%", $db, "", "block") . "\n";
				$prefixes[$prefix]++;
			}
		}
	} else {
		echo "<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";
	}
}
?>
</table>
</form>
<?php
$first = true;
foreach ($prefixes as $key => $val) {
	if ($key != "" && $val > 1) {
		echo ($first ? "<p>" : " ") . "<a href='" . h(ME) . "dump=" . urlencode("$key%") . "'>" . h($key) . "</a>";
		$first = false;
	}
}

} elseif (isset($_GET["privileges"])) {
	
page_header(lang(60));

echo '<p class="links"><a href="' . h(ME) . 'user=">' . lang(139) . "</a>";

$result = $connection->query("SELECT User, Host FROM mysql." . (DB == "" ? "user" : "db WHERE " . q(DB) . " LIKE Db") . " ORDER BY Host, User");
$grant = $result;
if (!$result) {
	// list logged user, information_schema.USER_PRIVILEGES lists just the current user too
	$result = $connection->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");
}

echo "<form action=''><p>\n";
hidden_fields_get();
echo "<input type='hidden' name='db' value='" . h(DB) . "'>\n";
echo ($grant ? "" : "<input type='hidden' name='grant' value=''>\n");
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th>" . lang(24) . "<th>" . lang(23) . "<th></thead>\n";

while ($row = $result->fetch_assoc()) {
	echo '<tr' . odd() . '><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . '<td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . lang(10) . "</a>\n";
}

if (!$grant || DB != "") {
	echo "<tr" . odd() . "><td><input name='user' autocapitalize='off'><td><input name='host' value='localhost' autocapitalize='off'><td><input type='submit' value='" . lang(10) . "'>\n";
}

echo "</table>\n";
echo "</form>\n";

} elseif (isset($_GET["sql"])) {
	
if (!$error && $_POST["export"]) {
	dump_headers("sql");
	$adminer->dumpTable("", "");
	$adminer->dumpData("", "table", $_POST["query"]);
	exit;
}

restart_session();
$history_all = &get_session("queries");
$history = &$history_all[DB];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}

page_header((isset($_GET["import"]) ? lang(61) : lang(53)), $error);

if (!$error && $_POST) {
	$fp = false;
	if (!isset($_GET["import"])) {
		$query = $_POST["query"];
	} elseif ($_POST["webfile"]) {
		$sql_file_path = $adminer->importServerPath();
		$fp = @fopen((file_exists($sql_file_path)
			? $sql_file_path
			: "compress.zlib://$sql_file_path.gz"
		), "rb");
		$query = ($fp ? fread($fp, 1e6) : false);
	} else {
		$query = get_file("sql_file", true);
	}

	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (function_exists('memory_get_usage')) {
			@ini_set("memory_limit", max(ini_bytes("memory_limit"), 2 * strlen($query) + memory_get_usage() + 8e6)); // @ - may be disabled, 2 - substr and trim, 8e6 - other variables
		}

		if ($query != "" && strlen($query) < 1e6) { // don't add big queries
			$q = $query . (preg_match("~;[ \t\r\n]*\$~", $query) ? "" : ";"); //! doesn't work with DELIMITER |
			if (!$history || reset(end($history)) != $q) { // no repeated queries
				restart_session();
				$history[] = array($q, time()); //! add elapsed time
				set_session("queries", $history_all); // required because reference is unlinked by stop_session()
				stop_session();
			}
		}

		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|-- )[^\n]*\n?|--\r?\n)";
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$connection2 = connect(); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if (is_object($connection2) && DB != "") {
			$connection2->select_db(DB);
			if ($_GET["ns"] != "") {
				set_schema($_GET["ns"], $connection2);
			}
		}
		$commands = 0;
		$errors = array();
		$parse = '[\'"' . ($jush == "sql" ? '`#' : ($jush == "sqlite" ? '`[' : ($jush == "mssql" ? '[' : ''))) . ']|/\*|-- |$' . ($jush == "pgsql" ? '|\$[^$]*\$' : '');
		$total_start = microtime(true);
		parse_str($_COOKIE["adminer_export"], $adminer_export);
		$dump_format = $adminer->dumpFormat();
		unset($dump_format["sql"]);

		while ($query != "") {
			if (!$offset && preg_match("~^$space*+DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = $match[1];
				$query = substr($query, strlen($match[0]));
			} else {
				preg_match('(' . preg_quote($delimiter) . "\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // should always match
				list($found, $pos) = $match[0];
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e5);
				} else {
					if (!$found && rtrim($query) == "") {
						break;
					}
					$offset = $pos + strlen($found);

					if ($found && rtrim($found) != $delimiter) { // find matching quote or comment end
						while (preg_match('(' . ($found == '/*' ? '\*/' : ($found == '[' ? ']' : (preg_match('~^-- |^#~', $found) ? "\n" : preg_quote($found) . "|\\\\."))) . '|$)s', $query, $match, PREG_OFFSET_CAPTURE, $offset)) { //! respect sql_mode NO_BACKSLASH_ESCAPES
							$s = $match[0][0];
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e5);
							} else {
								$offset = $match[0][1] + strlen($s);
								if ($s[0] != "\\") {
									break;
								}
							}
						}

					} else { // end of a query
						$empty = false;
						$q = substr($query, 0, $pos);
						$commands++;
						$print = "<pre id='sql-$commands'><code class='jush-$jush'>" . $adminer->sqlCommandQuery($q) . "</code></pre>\n";
						if ($jush == "sqlite" && preg_match("~^$space*+ATTACH\\b~i", $q, $match)) {
							// PHP doesn't support setting SQLITE_LIMIT_ATTACHED
							echo $print;
							echo "<p class='error'>" . lang(140) . "\n";
							$errors[] = " <a href='#sql-$commands'>$commands</a>";
							if ($_POST["error_stops"]) {
								break;
							}
						} else {
							if (!$_POST["only_errors"]) {
								echo $print;
								ob_flush();
								flush(); // can take a long time - show the running query
							}
							$start = microtime(true);
							//! don't allow changing of character_set_results, convert encoding of displayed query
							if ($connection->multi_query($q) && is_object($connection2) && preg_match("~^$space*+USE\\b~i", $q)) {
								$connection2->query($q);
							}

							do {
								$result = $connection->store_result();

								if ($connection->error) {
									echo ($_POST["only_errors"] ? $print : "");
									echo "<p class='error'>" . lang(141) . ($connection->errno ? " ($connection->errno)" : "") . ": " . error() . "\n";
									$errors[] = " <a href='#sql-$commands'>$commands</a>";
									if ($_POST["error_stops"]) {
										break 2;
									}

								} else {
									$time = " <span class='time'>(" . format_time($start) . ")</span>"
										. (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . lang(10) . "</a>" : "") // 1000 - maximum length of encoded URL in IE is 2083 characters
									;
									$affected = $connection->affected_rows; // getting warnigns overwrites this
									$warnings = ($_POST["only_errors"] ? "" : $driver->warnings());
									$warnings_id = "warnings-$commands";
									if ($warnings) {
										$time .= ", <a href='#$warnings_id'>" . lang(35) . "</a>" . script("qsl('a').onclick = partial(toggle, '$warnings_id');", "");
									}
									$explain = null;
									$explain_id = "explain-$commands";
									if (is_object($result)) {
										$limit = $_POST["limit"];
										$orgtables = select($result, $connection2, array(), $limit);
										if (!$_POST["only_errors"]) {
											echo "<form action='' method='post'>\n";
											$num_rows = $result->num_rows;
											echo "<p>" . ($num_rows ? ($limit && $num_rows > $limit ? lang(142, $limit) : "") . lang(143, $num_rows) : "");
											echo $time;
											if ($connection2 && preg_match("~^($space|\\()*+SELECT\\b~i", $q) && ($explain = explain($connection2, $q))) {
												echo ", <a href='#$explain_id'>Explain</a>" . script("qsl('a').onclick = partial(toggle, '$explain_id');", "");
											}
											$id = "export-$commands";
											echo ", <a href='#$id'>" . lang(62) . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "") . "<span id='$id' class='hidden'>: "
												. html_select("output", $adminer->dumpOutput(), $adminer_export["output"]) . " "
												. html_select("format", $dump_format, $adminer_export["format"])
												. "<input type='hidden' name='query' value='" . h($q) . "'>"
												. " <input type='submit' name='export' value='" . lang(62) . "'><input type='hidden' name='token' value='$token'></span>\n"
												. "</form>\n"
											;
										}

									} else {
										if (preg_match("~^$space*+(CREATE|DROP|ALTER)$space++(DATABASE|SCHEMA)\\b~i", $q)) {
											restart_session();
											set_session("dbs", null); // clear cache
											stop_session();
										}
										if (!$_POST["only_errors"]) {
											echo "<p class='message' title='" . h($connection->info) . "'>" . lang(144, $affected) . "$time\n";
										}
									}
									echo ($warnings ? "<div id='$warnings_id' class='hidden'>\n$warnings</div>\n" : "");
									if ($explain) {
										echo "<div id='$explain_id' class='hidden'>\n";
										select($explain, $connection2, $orgtables);
										echo "</div>\n";
									}
								}

								$start = microtime(true);
							} while ($connection->next_result());
						}

						$query = substr($query, $offset);
						$offset = 0;
					}

				}
			}
		}

		if ($empty) {
			echo "<p class='message'>" . lang(145) . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang(146, $commands - count($errors));
			echo " <span class='time'>(" . format_time($total_start) . ")</span>\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . lang(141) . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF

	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
$execute = "<input type='submit' value='" . lang(147) . "' title='Ctrl+Enter'>";
if (!isset($_GET["import"])) {
	$q = $_GET["sql"]; // overwrite $q from if ($_POST) to save memory
	if ($_POST) {
		$q = $_POST["query"];
	} elseif ($_GET["history"] == "all") {
		$q = $history;
	} elseif ($_GET["history"] != "") {
		$q = $history[$_GET["history"]][0];
	}
	echo "<p>";
	textarea("query", $q, 20);
	echo script(($_POST ? "" : "qs('textarea').focus();\n") . "qs('#form').onsubmit = partial(sqlSubmit, qs('#form'), '" . js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history")) . "');");
	echo "<p>$execute\n";
	echo lang(148) . ": <input type='number' name='limit' class='size' value='" . h($_POST ? $_POST["limit"] : $_GET["limit"]) . "'>\n";
	
} else {
	echo "<fieldset><legend>" . lang(149) . "</legend><div>";
	$gz = (extension_loaded("zlib") ? "[.gz]" : "");
	echo (ini_bool("file_uploads")
		? "SQL$gz (&lt; " . ini_get("upload_max_filesize") . "B): <input type='file' name='sql_file[]' multiple>\n$execute" // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
		: lang(150)
	);
	echo "</div></fieldset>\n";
	$importServerPath = $adminer->importServerPath();
	if ($importServerPath) {
		echo "<fieldset><legend>" . lang(151) . "</legend><div>";
		echo lang(152, "<code>" . h($importServerPath) . "$gz</code>");
		echo ' <input type="submit" name="webfile" value="' . lang(153) . '">';
		echo "</div></fieldset>\n";
	}
	echo "<p>";
}

echo checkbox("error_stops", 1, ($_POST ? $_POST["error_stops"] : isset($_GET["import"]) || $_GET["error_stops"]), lang(154)) . "\n";
echo checkbox("only_errors", 1, ($_POST ? $_POST["only_errors"] : isset($_GET["import"]) || $_GET["only_errors"]), lang(155)) . "\n";
echo "<input type='hidden' name='token' value='$token'>\n";

if (!isset($_GET["import"]) && $history) {
	print_fieldset("history", lang(156), $_GET["history"] != "");
	for ($val = end($history); $val; $val = prev($history)) { // not array_reverse() to save memory
		$key = key($history);
		list($q, $time, $elapsed) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang(10) . "</a>"
			. " <span class='time' title='" . @date('Y-m-d', $time) . "'>" . @date("H:i:s", $time) . "</span>" // @ - time zone may be not set
			. " <code class='jush-$jush'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace('~^(#|-- ).*~m', '', $q)))), 80, "</code>")
			. ($elapsed ? " <span class='time'>($elapsed)</span>" : "")
			. "<br>\n"
		;
	}
	echo "<input type='submit' name='clear' value='" . lang(157) . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . lang(158) . "</a>\n";
	echo "</div></fieldset>\n";
}
?>
</form>
<?php
} elseif (isset($_GET["edit"])) {
	
$TABLE = $_GET["edit"];
$fields = fields($TABLE);
$where = (isset($_GET["select"]) ? ($_POST["check"] && count($_POST["check"]) == 1 ? where_check($_POST["check"][0], $fields) : "") : where($_GET, $fields));
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || $adminer->fieldName($field) == "" || $field["generated"]) {
		unset($fields[$name]);
	}
}

if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_POST["referer"];
	if ($_POST["insert"]) { // continue edit or insert
		$location = ($update ? null : $_SERVER["REQUEST_URI"]);
	} elseif (!preg_match('~^.+&select=.+$~', $location)) {
		$location = ME . "select=" . urlencode($TABLE);
	}

	$indexes = indexes($TABLE);
	$unique_array = unique_array($_GET["where"], $indexes);
	$query_where = "\nWHERE $where";

	if (isset($_POST["delete"])) {
		queries_redirect(
			$location,
			lang(159),
			$driver->delete($TABLE, $query_where, !$unique_array)
		);

	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = $val;
			}
		}

		if ($update) {
			if (!$set) {
				redirect($location);
			}
			queries_redirect(
				$location,
				lang(160),
				$driver->update($TABLE, $set, $query_where, !$unique_array)
			);
			if (is_ajax()) {
				page_headers();
				page_messages($error);
				exit;
			}
		} else {
			$result = $driver->insert($TABLE, $set);
			$last_id = ($result ? last_id() : 0);
			queries_redirect($location, lang(161, ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$row = null;
if ($_POST["save"]) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$as = convert_field($field);
			if ($_POST["clone"] && $field["auto_increment"]) {
				$as = "''";
			}
			if ($jush == "sql" && preg_match("~enum|set~", $field["type"])) {
				$as = "1*" . idf_escape($name);
			}
			$select[] = ($as ? "$as AS " : "") . idf_escape($name);
		}
	}
	$row = array();
	if (!support("table")) {
		$select = array("*");
	}
	if ($select) {
		$result = $driver->select($TABLE, $select, array($where), $select, array(), (isset($_GET["select"]) ? 2 : 1));
		if (!$result) {
			$error = error();
		} else {
			$row = $result->fetch_assoc();
			if (!$row) { // MySQLi returns null
				$row = false;
			}
		}
		if (isset($_GET["select"]) && (!$row || $result->fetch_assoc())) { // $result->num_rows != 1 isn't available in all drivers
			$row = null;
		}
	}
}

if (!support("table") && !$fields) {
	if (!$where) { // insert
		$result = $driver->select($TABLE, array("*"), $where, array("*"));
		$row = ($result ? $result->fetch_assoc() : false);
		if (!$row) {
			$row = array($driver->primary => "");
		}
	}
	if ($row) {
		foreach ($row as $key => $val) {
			if (!$where) {
				$row[$key] = null;
			}
			$fields[$key] = array("field" => $key, "null" => ($key != $driver->primary), "auto_increment" => ($key == $driver->primary));
		}
	}
}

edit_form($TABLE, $fields, $row, $update);

} elseif (isset($_GET["create"])) {
	
$TABLE = $_GET["create"];
$partition_by = array();
foreach (array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST') as $key) {
	$partition_by[$key] = $key;
}

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$table_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$table_status = table_status($TABLE);
	if (!$table_status) {
		$error = lang(9);
	}
}

$row = $_POST;
$row["fields"] = (array) $row["fields"];
if ($row["auto_increment_col"]) {
	$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
}

if ($_POST) {
	set_adminer_settings(array("comments" => $_POST["comments"], "defaults" => $_POST["defaults"]));
}

if ($_POST && !process_fields($row["fields"]) && !$error) {
	if ($_POST["drop"]) {
		queries_redirect(substr(ME, 0, -1), lang(162), drop_tables(array($TABLE)));
	} else {
		$fields = array();
		$all_fields = array();
		$use_all_fields = false;
		$foreign = array();
		$orig_field = reset($orig_fields);
		$after = " FIRST";

		foreach ($row["fields"] as $key => $field) {
			$foreign_key = $foreign_keys[$field["type"]];
			$type_field = ($foreign_key !== null ? $referencable_primary[$foreign_key] : $field); //! can collide with user defined type
			if ($field["field"] != "") {
				if (!$field["has_default"]) {
					$field["default"] = null;
				}
				if ($key == $row["auto_increment_col"]) {
					$field["auto_increment"] = true;
				}
				$process_field = process_field($field, $type_field);
				$all_fields[] = array($field["orig"], $process_field, $after);
				if (!$orig_field || $process_field != process_field($orig_field, $orig_field)) {
					$fields[] = array($field["orig"], $process_field, $after);
					if ($field["orig"] != "" || $after) {
						$use_all_fields = true;
					}
				}
				if ($foreign_key !== null) {
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && $jush != "sqlite" ? "ADD" : " ") . format_foreign_key(array(
						'table' => $foreign_keys[$field["type"]],
						'source' => array($field["field"]),
						'target' => array($type_field["field"]),
						'on_delete' => $field["on_delete"],
					));
				}
				$after = " AFTER " . idf_escape($field["field"]);
			} elseif ($field["orig"] != "") {
				$use_all_fields = true;
				$fields[] = array($field["orig"]);
			}
			if ($field["orig"] != "") {
				$orig_field = next($orig_fields);
				if (!$orig_field) {
					$after = "";
				}
			}
		}

		$partitioning = "";
		if ($partition_by[$row["partition_by"]]) {
			$partitions = array();
			if ($row["partition_by"] == 'RANGE' || $row["partition_by"] == 'LIST') {
				foreach (array_filter($row["partition_names"]) as $key => $val) {
					$value = $row["partition_values"][$key];
					$partitions[] = "\n  PARTITION " . idf_escape($val) . " VALUES " . ($row["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . ($value != "" ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			$partitioning .= "\nPARTITION BY $row[partition_by]($row[partition])" . ($partitions // $row["partition"] can be expression, not only column
				? " (" . implode(",", $partitions) . "\n)"
				: ($row["partitions"] ? " PARTITIONS " . (+$row["partitions"]) : "")
			);
		} elseif (support("partitioning") && preg_match("~partitioned~", $table_status["Create_options"])) {
			$partitioning .= "\nREMOVE PARTITIONING";
		}

		$message = lang(163);
		if ($TABLE == "") {
			cookie("adminer_engine", $row["Engine"]);
			$message = lang(164);
		}
		$name = trim($row["name"]);

		queries_redirect(ME . (support("table") ? "table=" : "select=") . urlencode($name), $message, alter_table(
			$TABLE,
			$name,
			($jush == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
			$foreign,
			($row["Comment"] != $table_status["Comment"] ? $row["Comment"] : null),
			($row["Engine"] && $row["Engine"] != $table_status["Engine"] ? $row["Engine"] : ""),
			($row["Collation"] && $row["Collation"] != $table_status["Collation"] ? $row["Collation"] : ""),
			($row["Auto_increment"] != "" ? number($row["Auto_increment"]) : ""),
			$partitioning
		));
	}
}

page_header(($TABLE != "" ? lang(33) : lang(63)), $error, array("table" => $TABLE), h($TABLE));

if (!$_POST) {
	$row = array(
		"Engine" => $_COOKIE["adminer_engine"],
		"fields" => array(array("field" => "", "type" => (isset($types["int"]) ? "int" : (isset($types["integer"]) ? "integer" : "")), "on_update" => "")),
		"partition_names" => array(""),
	);

	if ($TABLE != "") {
		$row = $table_status;
		$row["name"] = $TABLE;
		$row["fields"] = array();
		if (!$_GET["auto_increment"]) { // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids
			$row["Auto_increment"] = "";
		}
		foreach ($orig_fields as $field) {
			$field["has_default"] = isset($field["default"]);
			$row["fields"][] = $field;
		}

		if (support("partitioning")) {
			$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . q(DB) . " AND TABLE_NAME = " . q($TABLE);
			$result = $connection->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
			list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
			$partitions = get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
			$partitions[""] = "";
			$row["partition_names"] = array_keys($partitions);
			$row["partition_values"] = array_values($partitions);
		}
	}
}

$collations = collations();
$engines = engines();
// case of engine may differ
foreach ($engines as $engine) {
	if (!strcasecmp($engine, $row["Engine"])) {
		$row["Engine"] = $engine;
		break;
	}
}
?>

<form action="" method="post" id="form">
<p>
<?php if (support("columns") || $TABLE == "") {  echo lang(165); ?>: <input name="name" data-maxlength="64" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<?php if ($TABLE == "" && !$_POST) { echo script("focus(qs('#form')['name']);"); }  echo ($engines ? "<select name='Engine'>" . optionlist(array("" => "(" . lang(166) . ")") + $engines, $row["Engine"]) . "</select>" . on_help("getTarget(event).value", 1) . script("qsl('select').onchange = helpClose;") : ""); ?>
 <?php echo ($collations && !preg_match("~sqlite|mssql~", $jush) ? html_select("Collation", array("" => "(" . lang(95) . ")") + $collations, $row["Collation"]) : ""); ?>
 <input type="submit" value="<?php echo lang(14); ?>">
<?php } ?>

<?php if (support("columns")) { ?>
<div class="scrollable">
<table cellspacing="0" id="edit-fields" class="nowrap">
<?php
edit_fields($row["fields"], $collations, "TABLE", $foreign_keys);
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p>
<?php echo lang(40); ?>: <input type="number" name="Auto_increment" size="6" value="<?php echo h($row["Auto_increment"]); ?>">
<?php echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : adminer_setting("defaults")), lang(167), "columnShow(this.checked, 5)", "jsonly");  echo (support("comment")
	? checkbox("comments", 1, ($_POST ? $_POST["comments"] : adminer_setting("comments")), lang(39), "editingCommentsClick(this, true);", "jsonly")
		. ' <input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '">'
	: '')
; ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php } ?>

<?php if ($TABLE != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $TABLE));  } 
if (support("partitioning")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", lang(169), $row["partition_by"]);
	?>
<p>
<?php echo "<select name='partition_by'>" . optionlist(array("" => "") + $partition_by, $row["partition_by"]) . "</select>" . on_help("getTarget(event).value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;"); ?>
(<input name="partition" value="<?php echo h($row["partition"]); ?>">)
<?php echo lang(170); ?>: <input type="number" name="partitions" class="size<?php echo ($partition_table || !$row["partition_by"] ? " hidden" : ""); ?>" value="<?php echo h($row["partitions"]); ?>">
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th><?php echo lang(171); ?><th><?php echo lang(172); ?></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr>';
	echo '<td><input name="partition_names[]" value="' . h($val) . '" autocapitalize="off">';
	echo ($key == count($row["partition_names"]) - 1 ? script("qsl('input').oninput = partitionNameChange;") : '');
	echo '<td><input name="partition_values[]" value="' . h($row["partition_values"][$key]) . '">';
}
?>
</table>
</div></fieldset>
<?php
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["indexes"])) {
	
$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status($TABLE, true);
if (preg_match('~MyISAM|M?aria' . (min_version(5.6, '10.0.5') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
if (preg_match('~MyISAM|M?aria' . (min_version(5.7, '10.2.2') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "SPATIAL";
}
$indexes = indexes($TABLE);
$primary = array();
if ($jush == "mongo") { // doesn't support primary key
	$primary = $indexes["_id_"];
	unset($index_types[0]);
	unset($indexes["_id_"]);
}
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"]) {
	$alter = array();
	foreach ($row["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$descs = array();
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = $index["lengths"][$key];
					$desc = $index["descs"][$key];
					$set[] = idf_escape($column) . ($length ? "(" . (+$length) . ")" : "") . ($desc ? " DESC" : "");
					$columns[] = $column;
					$lengths[] = ($length ? $length : null);
					$descs[] = $desc;
				}
			}

			if ($columns) {
				$existing = $indexes[$name];
				if ($existing) {
					ksort($existing["columns"]);
					ksort($existing["lengths"]);
					ksort($existing["descs"]);
					if ($index["type"] == $existing["type"]
						&& array_values($existing["columns"]) === $columns
						&& (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)
						&& array_values($existing["descs"]) === $descs
					) {
						// skip existing index
						unset($indexes[$name]);
						continue;
					}
				}
				$alter[] = array($index["type"], $name, $set);
			}
		}
	}

	// drop removed indexes
	foreach ($indexes as $name => $existing) {
		$alter[] = array($existing["type"], $name, "DROP");
	}
	if (!$alter) {
		redirect(ME . "table=" . urlencode($TABLE));
	}
	queries_redirect(ME . "table=" . urlencode($TABLE), lang(173), alter_indexes($TABLE, $alter));
}

page_header(lang(125), $error, array("table" => $TABLE), h($TABLE));

$fields = array_keys(fields($TABLE));
if ($_POST["add"]) {
	foreach ($row["indexes"] as $key => $index) {
		if ($index["columns"][count($index["columns"])] != "") {
			$row["indexes"][$key]["columns"][] = "";
		}
	}
	$index = end($row["indexes"]);
	if ($index["type"] || array_filter($index["columns"], 'strlen')) {
		$row["indexes"][] = array("columns" => array(1 => ""));
	}
}
if (!$row) {
	foreach ($indexes as $key => $index) {
		$indexes[$key]["name"] = $key;
		$indexes[$key]["columns"][] = "";
	}
	$indexes[] = array("columns" => array(1 => ""));
	$row["indexes"] = $indexes;
}
?>

<form action="" method="post">
<div class="scrollable">
<table cellspacing="0" class="nowrap">
<thead><tr>
<th id="label-type"><?php echo lang(174); ?>
<th><input type="submit" class="wayoff"><?php echo lang(175); ?>
<th id="label-name"><?php echo lang(176); ?>
<th><noscript><?php echo "<input type='image' class='icon' name='add[0]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . lang(102) . "'>"; ?></noscript>
</thead>
<?php
if ($primary) {
	echo "<tr><td>PRIMARY<td>";
	foreach ($primary["columns"] as $key => $column) {
		echo select_input(" disabled", $fields, $column);
		echo "<label><input disabled type='checkbox'>" . lang(48) . "</label> ";
	}
	echo "<td><td>\n";
}
$j = 1;
foreach ($row["indexes"] as $index) {
	if (!$_POST["drop_col"] || $j != key($_POST["drop_col"])) {
		echo "<tr><td>" . html_select("indexes[$j][type]", array(-1 => "") + $index_types, $index["type"], ($j == count($row["indexes"]) ? "indexesAddRow.call(this);" : 1), "label-type");

		echo "<td>";
		ksort($index["columns"]);
		$i = 1;
		foreach ($index["columns"] as $key => $column) {
			echo "<span>" . select_input(
				" name='indexes[$j][columns][$i]' title='" . lang(37) . "'",
				($fields ? array_combine($fields, $fields) : $fields),
				$column,
				"partial(" . ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . ", '" . js_escape($jush == "sql" ? "" : $_GET["indexes"] . "_") . "')"
			);
			echo ($jush == "sql" || $jush == "mssql" ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h($index["lengths"][$key]) . "' title='" . lang(100) . "'>" : "");
			echo (support("descidx") ? checkbox("indexes[$j][descs][$i]", 1, $index["descs"][$key], lang(48)) : "");
			echo " </span>";
			$i++;
		}

		echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off' aria-labelledby='label-name'>\n";
		echo "<td><input type='image' class='icon' name='drop_col[$j]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=cross.gif&version=4.8.1") . "' alt='x' title='" . lang(105) . "'>" . script("qsl('input').onclick = partial(editingRemoveRow, 'indexes\$1[type]');");
	}
	$j++;
}
?>
</table>
</div>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["database"])) {
	
$row = $_POST;

if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		$_GET["db"] = ""; // to save in global history
		queries_redirect(remove_from_uri("db|database"), lang(177), drop_databases(array(DB)));
	} elseif (DB !== $name) {
		// create or rename database
		if (DB != "") {
			$_GET["db"] = $name;
			queries_redirect(preg_replace('~\bdb=[^&]*&~', '', ME) . "db=" . urlencode($name), lang(178), rename_database($name, $row["collation"]));
		} else {
			$databases = explode("\n", str_replace("\r", "", $name));
			$success = true;
			$last = "";
			foreach ($databases as $db) {
				if (count($databases) == 1 || $db != "") { // ignore empty lines but always try to create single database
					if (!create_database($db, $row["collation"])) {
						$success = false;
					}
					$last = $db;
				}
			}
			restart_session();
			set_session("dbs", null);
			queries_redirect(ME . "db=" . urlencode($last), lang(179), $success);
		}
	} else {
		// alter database
		if (!$row["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($name) . (preg_match('~^[a-z0-9_]+$~i', $row["collation"]) ? " COLLATE $row[collation]" : ""), substr(ME, 0, -1), lang(180));
	}
}

page_header(DB != "" ? lang(56) : lang(109), $error, array(), h(DB));

$collations = collations();
$name = DB;
if ($_POST) {
	$name = $row["name"];
} elseif (DB != "") {
	$row["collation"] = db_collation(DB, $collations);
} elseif ($jush == "sql") {
	// propose database name with limited privileges
	foreach (get_vals("SHOW GRANTS") as $grant) {
		if (preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~', $grant, $match) && $match[1]) {
			$name = stripcslashes(idf_unescape("`$match[2]`"));
			break;
		}
	}
}
?>

<form action="" method="post">
<p>
<?php
echo ($_POST["add_x"] || strpos($name, "\n")
	? '<textarea id="name" name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" id="name" value="' . h($name) . '" data-maxlength="64" autocapitalize="off">'
) . "\n" . ($collations ? html_select("collation", array("" => "(" . lang(95) . ")") + $collations, $row["collation"]) . doc_link(array(
	'sql' => "charset-charsets.html",
	'mariadb' => "supported-character-sets-and-collations/",
	
)) : "");
echo script("focus(qs('#name'));");
?>
<input type="submit" value="<?php echo lang(14); ?>">
<?php
if (DB != "") {
	echo "<input type='submit' name='drop' value='" . lang(121) . "'>" . confirm(lang(168, DB)) . "\n";
} elseif (!$_POST["add_x"] && $_GET["db"] == "") {
	echo "<input type='image' class='icon' name='add' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . lang(102) . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["call"])) {
	
$PROCEDURE = ($_GET["name"] ? $_GET["name"] : $_GET["call"]);
page_header(lang(181) . ": " . h($PROCEDURE), $error);

$routine = routine($_GET["call"], (isset($_GET["callf"]) ? "FUNCTION" : "PROCEDURE"));
$in = array();
$out = array();
foreach ($routine["fields"] as $i => $field) {
	if (substr($field["inout"], -3) == "OUT") {
		$out[$i] = "@" . idf_escape($field["field"]) . " AS " . idf_escape($field["field"]);
	}
	if (!$field["inout"] || substr($field["inout"], 0, 2) == "IN") {
		$in[] = $i;
	}
}

if (!$error && $_POST) {
	$call = array();
	foreach ($routine["fields"] as $key => $field) {
		if (in_array($key, $in)) {
			$val = process_input($field);
			if ($val === false) {
				$val = "''";
			}
			if (isset($out[$key])) {
				$connection->query("SET @" . idf_escape($field["field"]) . " = $val");
			}
		}
		$call[] = (isset($out[$key]) ? "@" . idf_escape($field["field"]) : $val);
	}
	
	$query = (isset($_GET["callf"]) ? "SELECT" : "CALL") . " " . table($PROCEDURE) . "(" . implode(", ", $call) . ")";
	$start = microtime(true);
	$result = $connection->multi_query($query);
	$affected = $connection->affected_rows; // getting warnigns overwrites this
	echo $adminer->selectQuery($query, $start, !$result);
	
	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		$connection2 = connect();
		if (is_object($connection2)) {
			$connection2->select_db(DB);
		}
		
		do {
			$result = $connection->store_result();
			if (is_object($result)) {
				select($result, $connection2);
			} else {
				echo "<p class='message'>" . lang(182, $affected)
					. " <span class='time'>" . @date("H:i:s") . "</span>\n" // @ - time zone may be not set
				;
			}
		} while ($connection->next_result());
		
		if ($out) {
			select($connection->query("SELECT " . implode(", ", $out)));
		}
	}
}
?>

<form action="" method="post">
<?php
if ($in) {
	echo "<table cellspacing='0' class='layout'>\n";
	foreach ($in as $key) {
		$field = $routine["fields"][$key];
		$name = $field["field"];
		echo "<tr><th>" . $adminer->fieldName($field);
		$value = $_POST["fields"][$name];
		if ($value != "") {
			if ($field["type"] == "enum") {
				$value = +$value;
			}
			if ($field["type"] == "set") {
				$value = array_sum($value);
			}
		}
		input($field, $value, (string) $_POST["function"][$name]); // param name can be empty
		echo "\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="submit" value="<?php echo lang(181); ?>">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["foreign"])) {
	
$TABLE = $_GET["foreign"];
$name = $_GET["name"];
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	$message = ($_POST["drop"] ? lang(183) : ($name != "" ? lang(184) : lang(185)));
	$location = ME . "table=" . urlencode($TABLE);
	
	if (!$_POST["drop"]) {
		$row["source"] = array_filter($row["source"], 'strlen');
		ksort($row["source"]); // enforce input order
		$target = array();
		foreach ($row["source"] as $key => $val) {
			$target[$key] = $row["target"][$key];
		}
		$row["target"] = $target;
	}
	
	if ($jush == "sqlite") {
		queries_redirect($location, $message, recreate_table($TABLE, $TABLE, array(), array(), array(" $name" => ($_POST["drop"] ? "" : " " . format_foreign_key($row)))));
	} else {
		$alter = "ALTER TABLE " . table($TABLE);
		$drop = "\nDROP " . ($jush == "sql" ? "FOREIGN KEY " : "CONSTRAINT ") . idf_escape($name);
		if ($_POST["drop"]) {
			query_redirect($alter . $drop, $location, $message);
		} else {
			query_redirect($alter . ($name != "" ? "$drop," : "") . "\nADD" . format_foreign_key($row), $location, $message);
			$error = lang(186) . "<br>$error"; //! no partitioning
		}
	}
}

page_header(lang(187), $error, array("table" => $TABLE), h($TABLE));

if ($_POST) {
	ksort($row["source"]);
	if ($_POST["add"]) {
		$row["source"][] = "";
	} elseif ($_POST["change"] || $_POST["change-js"]) {
		$row["target"] = array();
	}
} elseif ($name != "") {
	$foreign_keys = foreign_keys($TABLE);
	$row = $foreign_keys[$name];
	$row["source"][] = "";
} else {
	$row["table"] = $TABLE;
	$row["source"] = array("");
}
?>

<form action="" method="post">
<?php
$source = array_keys(fields($TABLE)); //! no text and blob
if ($row["db"] != "") {
	$connection->select_db($row["db"]);
}
if ($row["ns"] != "") {
	set_schema($row["ns"]);
}
$referencable = array_keys(array_filter(table_status('', true), 'fk_support'));
$target = array_keys(fields(in_array($row["table"], $referencable) ? $row["table"] : reset($referencable)));
$onchange = "this.form['change-js'].value = '1'; this.form.submit();";
echo "<p>" . lang(188) . ": " . html_select("table", $referencable, $row["table"], $onchange) . "\n";
if ($jush == "pgsql") {
	echo lang(189) . ": " . html_select("ns", $adminer->schemas(), $row["ns"] != "" ? $row["ns"] : $_GET["ns"], $onchange);
} elseif ($jush != "sqlite") {
	$dbs = array();
	foreach ($adminer->databases() as $db) {
		if (!information_schema($db)) {
			$dbs[] = $db;
		}
	}
	echo lang(65) . ": " . html_select("db", $dbs, $row["db"] != "" ? $row["db"] : $_GET["db"], $onchange);
}
?>
<input type="hidden" name="change-js" value="">
<noscript><p><input type="submit" name="change" value="<?php echo lang(190); ?>"></noscript>
<table cellspacing="0">
<thead><tr><th id="label-source"><?php echo lang(127); ?><th id="label-target"><?php echo lang(128); ?></thead>
<?php
$j = 0;
foreach ($row["source"] as $key => $val) {
	echo "<tr>";
	echo "<td>" . html_select("source[" . (+$key) . "]", array(-1 => "") + $source, $val, ($j == count($row["source"]) - 1 ? "foreignAddRow.call(this);" : 1), "label-source");
	echo "<td>" . html_select("target[" . (+$key) . "]", $target, $row["target"][$key], 1, "label-target");
	$j++;
}
?>
</table>
<p>
<?php echo lang(97); ?>: <?php echo html_select("on_delete", array(-1 => "") + explode("|", $on_actions), $row["on_delete"]); ?>
 <?php echo lang(96); ?>: <?php echo html_select("on_update", array(-1 => "") + explode("|", $on_actions), $row["on_update"]);  echo doc_link(array(
	'sql' => "innodb-foreign-key-constraints.html",
	'mariadb' => "foreign-keys/",
	
	
	
)); ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang(191); ?>"></noscript>
<?php if ($name != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $name));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["view"])) {
	
$TABLE = $_GET["view"];
$row = $_POST;
$orig_type = "VIEW";
if ($jush == "pgsql" && $TABLE != "") {
	$status = table_status($TABLE);
	$orig_type = strtoupper($status["Engine"]);
}

if ($_POST && !$error) {
	$name = trim($row["name"]);
	$as = " AS\n$row[select]";
	$location = ME . "table=" . urlencode($name);
	$message = lang(192);

	$type = ($_POST["materialized"] ? "MATERIALIZED VIEW" : "VIEW");

	if (!$_POST["drop"] && $TABLE == $name && $jush != "sqlite" && $type == "VIEW" && $orig_type == "VIEW") {
		query_redirect(($jush == "mssql" ? "ALTER" : "CREATE OR REPLACE") . " VIEW " . table($name) . $as, $location, $message);
	} else {
		$temp_name = $name . "_adminer_" . uniqid();
		drop_create(
			"DROP $orig_type " . table($TABLE),
			"CREATE $type " . table($name) . $as,
			"DROP $type " . table($name),
			"CREATE $type " . table($temp_name) . $as,
			"DROP $type " . table($temp_name),
			($_POST["drop"] ? substr(ME, 0, -1) : $location),
			lang(193),
			$message,
			lang(194),
			$TABLE,
			$name
		);
	}
}

if (!$_POST && $TABLE != "") {
	$row = view($TABLE);
	$row["name"] = $TABLE;
	$row["materialized"] = ($orig_type != "VIEW");
	if (!$error) {
		$error = error();
	}
}

page_header(($TABLE != "" ? lang(32) : lang(195)), $error, array("table" => $TABLE), h($TABLE));
?>

<form action="" method="post">
<p><?php echo lang(176); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo (support("materializedview") ? " " . checkbox("materialized", 1, $row["materialized"], lang(122)) : ""); ?>
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php if ($TABLE != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $TABLE));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["event"])) {
	
$EVENT = $_GET["event"];
$intervals = array("YEAR", "QUARTER", "MONTH", "DAY", "HOUR", "MINUTE", "WEEK", "SECOND", "YEAR_MONTH", "DAY_HOUR", "DAY_MINUTE", "DAY_SECOND", "HOUR_MINUTE", "HOUR_SECOND", "MINUTE_SECOND");
$statuses = array("ENABLED" => "ENABLE", "DISABLED" => "DISABLE", "SLAVESIDE_DISABLED" => "DISABLE ON SLAVE");
$row = $_POST;

if ($_POST && !$error) {
	if ($_POST["drop"]) {
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), lang(196));
	} elseif (in_array($row["INTERVAL_FIELD"], $intervals) && isset($statuses[$row["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($row["INTERVAL_VALUE"]
			? "EVERY " . q($row["INTERVAL_VALUE"]) . " $row[INTERVAL_FIELD]"
			. ($row["STARTS"] ? " STARTS " . q($row["STARTS"]) : "")
			. ($row["ENDS"] ? " ENDS " . q($row["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . q($row["STARTS"])
			) . " ON COMPLETION" . ($row["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;
		
		queries_redirect(substr(ME, 0, -1), ($EVENT != "" ? lang(197) : lang(198)), queries(($EVENT != ""
			? "ALTER EVENT " . idf_escape($EVENT) . $schedule
			. ($EVENT != $row["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($row["EVENT_NAME"]) : "")
			: "CREATE EVENT " . idf_escape($row["EVENT_NAME"]) . $schedule
			) . "\n" . $statuses[$row["STATUS"]] . " COMMENT " . q($row["EVENT_COMMENT"])
			. rtrim(" DO\n$row[EVENT_DEFINITION]", ";") . ";"
		));
	}
}

page_header(($EVENT != "" ? lang(199) . ": " . h($EVENT) : lang(200)), $error);

if (!$row && $EVENT != "") {
	$rows = get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . q(DB) . " AND EVENT_NAME = " . q($EVENT));
	$row = reset($rows);
}
?>

<form action="" method="post">
<table cellspacing="0" class="layout">
<tr><th><?php echo lang(176); ?><td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime"><?php echo lang(201); ?><td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th title="datetime"><?php echo lang(202); ?><td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th><?php echo lang(203); ?><td><input type="number" name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" class="size"> <?php echo html_select("INTERVAL_FIELD", $intervals, $row["INTERVAL_FIELD"]); ?>
<tr><th><?php echo lang(112); ?><td><?php echo html_select("STATUS", $statuses, $row["STATUS"]); ?>
<tr><th><?php echo lang(39); ?><td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" data-maxlength="64">
<tr><th><td><?php echo checkbox("ON_COMPLETION", "PRESERVE", $row["ON_COMPLETION"] == "PRESERVE", lang(204)); ?>
</table>
<p><?php textarea("EVENT_DEFINITION", $row["EVENT_DEFINITION"]); ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php if ($EVENT != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $EVENT));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["procedure"])) {
	
$PROCEDURE = ($_GET["name"] ? $_GET["name"] : $_GET["procedure"]);
$routine = (isset($_GET["function"]) ? "FUNCTION" : "PROCEDURE");
$row = $_POST;
$row["fields"] = (array) $row["fields"];

if ($_POST && !process_fields($row["fields"]) && !$error) {
	$orig = routine($_GET["procedure"], $routine);
	$temp_name = "$row[name]_adminer_" . uniqid();
	drop_create(
		"DROP $routine " . routine_id($PROCEDURE, $orig),
		create_routine($routine, $row),
		"DROP $routine " . routine_id($row["name"], $row),
		create_routine($routine, array("name" => $temp_name) + $row),
		"DROP $routine " . routine_id($temp_name, $row),
		substr(ME, 0, -1),
		lang(205),
		lang(206),
		lang(207),
		$PROCEDURE,
		$row["name"]
	);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? lang(208) : lang(209)) . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? lang(210) : lang(211))), $error);

if (!$_POST && $PROCEDURE != "") {
	$row = routine($_GET["procedure"], $routine);
	$row["name"] = $PROCEDURE;
}

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$routine_languages = routine_languages();
?>

<form action="" method="post" id="form">
<p><?php echo lang(176); ?>: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? lang(19) . ": " . html_select("language", $routine_languages, $row["language"]) . "\n" : ""); ?>
<input type="submit" value="<?php echo lang(14); ?>">
<div class="scrollable">
<table cellspacing="0" class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . lang(212);
	edit_type("returns", $row["returns"], $collations, array(), ($jush == "pgsql" ? array("void", "trigger") : array()));
}
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p><?php textarea("definition", $row["definition"]); ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php if ($PROCEDURE != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $PROCEDURE));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["trigger"])) {
	
$TABLE = $_GET["trigger"];
$name = $_GET["name"];
$trigger_options = trigger_options();
$row = (array) trigger($name, $TABLE) + array("Trigger" => $TABLE . "_bi");

if ($_POST) {
	if (!$error && in_array($_POST["Timing"], $trigger_options["Timing"]) && in_array($_POST["Event"], $trigger_options["Event"]) && in_array($_POST["Type"], $trigger_options["Type"])) {
		// don't use drop_create() because there may not be more triggers for the same action
		$on = " ON " . table($TABLE);
		$drop = "DROP TRIGGER " . idf_escape($name) . ($jush == "pgsql" ? $on : "");
		$location = ME . "table=" . urlencode($TABLE);
		if ($_POST["drop"]) {
			query_redirect($drop, $location, lang(213));
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? lang(214) : lang(215)),
				queries(create_trigger($on, $_POST))
			);
			if ($name != "") {
				queries(create_trigger($on, $row + array("Type" => reset($trigger_options["Type"]))));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? lang(216) . ": " . h($name) : lang(217)), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<table cellspacing="0" class="layout">
<tr><th><?php echo lang(218); ?><td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "triggerChange(/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/, '" . js_escape($TABLE) . "', this.form);"); ?>
<tr><th><?php echo lang(219); ?><td><?php echo html_select("Event", $trigger_options["Event"], $row["Event"], "this.form['Timing'].onchange();");  echo (in_array("UPDATE OF", $trigger_options["Event"]) ? " <input name='Of' value='" . h($row["Of"]) . "' class='hidden'>": ""); ?>
<tr><th><?php echo lang(38); ?><td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p><?php echo lang(176); ?>: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo script("qs('#form')['Timing'].onchange();"); ?>
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php if ($name != "") { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, $name));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["user"])) {
	
$USER = $_GET["user"];
$privileges = array("" => array("All privileges" => ""));
foreach (get_rows("SHOW PRIVILEGES") as $row) {
	foreach (explode(",", ($row["Privilege"] == "Grant option" ? "" : $row["Context"])) as $context) {
		$privileges[$context][$row["Privilege"]] = $row["Comment"];
	}
}
$privileges["Server Admin"] += $privileges["File access on server"];
$privileges["Databases"]["Create routine"] = $privileges["Procedures"]["Create routine"]; // MySQL bug #30305
unset($privileges["Procedures"]["Create routine"]);
$privileges["Columns"] = array();
foreach (array("Select", "Insert", "Update", "References") as $val) {
	$privileges["Columns"][$val] = $privileges["Tables"][$val];
}
unset($privileges["Server Admin"]["Usage"]);
foreach ($privileges["Tables"] as $key => $val) {
	unset($privileges["Databases"][$key]);
}

$new_grants = array();
if ($_POST) {
	foreach ($_POST["objects"] as $key => $val) {
		$new_grants[$val] = (array) $new_grants[$val] + (array) $_POST["grants"][$key];
	}
}
$grants = array();
$old_pass = "";

if (isset($_GET["host"]) && ($result = $connection->query("SHOW GRANTS FOR " . q($USER) . "@" . q($_GET["host"])))) { //! use information_schema for MySQL 5 - column names in column privileges are not escaped
	while ($row = $result->fetch_row()) {
		if (preg_match('~GRANT (.*) ON (.*) TO ~', $row[0], $match) && preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~', $match[1], $matches, PREG_SET_ORDER)) { //! escape the part between ON and TO
			foreach ($matches as $val) {
				if ($val[1] != "USAGE") {
					$grants["$match[2]$val[2]"][$val[1]] = true;
				}
				if (preg_match('~ WITH GRANT OPTION~', $row[0])) { //! don't check inside strings and identifiers
					$grants["$match[2]$val[2]"]["GRANT OPTION"] = true;
				}
			}
		}
		if (preg_match("~ IDENTIFIED BY PASSWORD '([^']+)~", $row[0], $match)) {
			$old_pass = $match[1];
		}
	}
}

if ($_POST && !$error) {
	$old_user = (isset($_GET["host"]) ? q($USER) . "@" . q($_GET["host"]) : "''");
	if ($_POST["drop"]) {
		query_redirect("DROP USER $old_user", ME . "privileges=", lang(220));
	} else {
		$new_user = q($_POST["user"]) . "@" . q($_POST["host"]); // if $_GET["host"] is not set then $new_user is always different
		$pass = $_POST["pass"];
		if ($pass != '' && !$_POST["hashed"] && !min_version(8)) {
			// compute hash in a separate query so that plain text password is not saved to history
			$pass = $connection->result("SELECT PASSWORD(" . q($pass) . ")");
			$error = !$pass;
		}

		$created = false;
		if (!$error) {
			if ($old_user != $new_user) {
				$created = queries((min_version(5) ? "CREATE USER" : "GRANT USAGE ON *.* TO") . " $new_user IDENTIFIED BY " . (min_version(8) ? "" : "PASSWORD ") . q($pass));
				$error = !$created;
			} elseif ($pass != $old_pass) {
				queries("SET PASSWORD FOR $new_user = " . q($pass));
			}
		}

		if (!$error) {
			$revoke = array();
			foreach ($new_grants as $object => $grant) {
				if (isset($_GET["grant"])) {
					$grant = array_filter($grant);
				}
				$grant = array_keys($grant);
				if (isset($_GET["grant"])) {
					// no rights to mysql.user table
					$revoke = array_diff(array_keys(array_filter($new_grants[$object], 'strlen')), $grant);
				} elseif ($old_user == $new_user) {
					$old_grant = array_keys((array) $grants[$object]);
					$revoke = array_diff($old_grant, $grant);
					$grant = array_diff($grant, $old_grant);
					unset($grants[$object]);
				}
				if (preg_match('~^(.+)\s*(\(.*\))?$~U', $object, $match) && (
					!grant("REVOKE", $revoke, $match[2], " ON $match[1] FROM $new_user") //! SQL injection
					|| !grant("GRANT", $grant, $match[2], " ON $match[1] TO $new_user")
				)) {
					$error = true;
					break;
				}
			}
		}

		if (!$error && isset($_GET["host"])) {
			if ($old_user != $new_user) {
				queries("DROP USER $old_user");
			} elseif (!isset($_GET["grant"])) {
				foreach ($grants as $object => $revoke) {
					if (preg_match('~^(.+)(\(.*\))?$~U', $object, $match)) {
						grant("REVOKE", array_keys($revoke), $match[2], " ON $match[1] FROM $new_user");
					}
				}
			}
		}

		queries_redirect(ME . "privileges=", (isset($_GET["host"]) ? lang(221) : lang(222)), !$error);

		if ($created) {
			// delete new user in case of an error
			$connection->query("DROP USER $new_user");
		}
	}
}

page_header((isset($_GET["host"]) ? lang(24) . ": " . h("$USER@$_GET[host]") : lang(139)), $error, array("privileges" => array('', lang(60))));

if ($_POST) {
	$row = $_POST;
	$grants = $new_grants;
} else {
	$row = $_GET + array("host" => $connection->result("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)")); // create user on the same domain by default
	$row["pass"] = $old_pass;
	if ($old_pass != "") {
		$row["hashed"] = true;
	}
	$grants[(DB == "" || $grants ? "" : idf_escape(addcslashes(DB, "%_\\"))) . ".*"] = array();
}

?>
<form action="" method="post">
<table cellspacing="0" class="layout">
<tr><th><?php echo lang(23); ?><td><input name="host" data-maxlength="60" value="<?php echo h($row["host"]); ?>" autocapitalize="off">
<tr><th><?php echo lang(24); ?><td><input name="user" data-maxlength="80" value="<?php echo h($row["user"]); ?>" autocapitalize="off">
<tr><th><?php echo lang(25); ?><td><input name="pass" id="pass" value="<?php echo h($row["pass"]); ?>" autocomplete="new-password">
<?php if (!$row["hashed"]) { echo script("typePassword(qs('#pass'));"); }  echo (min_version(8) ? "" : checkbox("hashed", 1, $row["hashed"], lang(223), "typePassword(this.form['pass'], this.checked);")); ?>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th colspan='2'>" . lang(60) . doc_link(array('sql' => "grant.html#priv_level"));
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*" ? "<input name='objects[$i]' value='" . h($object) . "' size='10' autocapitalize='off'>" : "<input type='hidden' name='objects[$i]' value='*.*' size='10'>*.*"); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	$i++;
}
echo "</thead>\n";

foreach (array(
	"" => "",
	"Server Admin" => lang(23),
	"Databases" => lang(26),
	"Tables" => lang(124),
	"Columns" => lang(37),
	"Procedures" => lang(224),
) as $context => $desc) {
	foreach ((array) $privileges[$context] as $privilege => $comment) {
		echo "<tr" . odd() . "><td" . ($desc ? ">$desc<td" : " colspan='2'") . ' lang="en" title="' . h($comment) . '">' . h($privilege);
		$i = 0;
		foreach ($grants as $object => $grant) {
			$name = "'grants[$i][" . h(strtoupper($privilege)) . "]'";
			$value = $grant[strtoupper($privilege)];
			if ($context == "Server Admin" && $object != (isset($grants["*.*"]) ? "*.*" : ".*")) {
				echo "<td>";
			} elseif (isset($_GET["grant"])) {
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected" : "") . ">" . lang(225) . "<option value='0'" . ($value == "0" ? " selected" : "") . ">" . lang(226) . "</select>";
			} else {
				echo "<td align='center'><label class='block'>";
				echo "<input type='checkbox' name=$name value='1'" . ($value ? " checked" : "") . ($privilege == "All privileges"
					? " id='grants-$i-all'>" //! uncheck all except grant if all is checked
					: ">" . ($privilege == "Grant option" ? "" : script("qsl('input').onclick = function () { if (this.checked) formUncheck('grants-$i-all'); };")));
				echo "</label>";
			}
			$i++;
		}
	}
}

echo "</table>\n";
?>
<p>
<input type="submit" value="<?php echo lang(14); ?>">
<?php if (isset($_GET["host"])) { ?><input type="submit" name="drop" value="<?php echo lang(121); ?>"><?php echo confirm(lang(168, "$USER@$_GET[host]"));  } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["processlist"])) {
	
if (support("kill")) {
	if ($_POST && !$error) {
		$killed = 0;
		foreach ((array) $_POST["kill"] as $val) {
			if (kill_process($val)) {
				$killed++;
			}
		}
		queries_redirect(ME . "processlist=", lang(227, $killed), $killed || !$_POST["kill"]);
	}
}

page_header(lang(110), $error);
?>

<form action="" method="post">
<div class="scrollable">
<table cellspacing="0" class="nowrap checkable">
<?php
echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
// HTML valid because there is always at least one process
$i = -1;
foreach (process_list() as $i => $row) {

	if (!$i) {
		echo "<thead><tr lang='en'>" . (support("kill") ? "<th>" : "");
		foreach ($row as $key => $val) {
			echo "<th>$key" . doc_link(array(
				'sql' => "show-processlist.html#processlist_" . strtolower($key),
				
				
			));
		}
		echo "</thead>\n";
	}
	echo "<tr" . odd() . ">" . (support("kill") ? "<td>" . checkbox("kill[]", $row[$jush == "sql" ? "Id" : "pid"], 0) : "");
	foreach ($row as $key => $val) {
		echo "<td>" . (
			($jush == "sql" && $key == "Info" && preg_match("~Query|Killed~", $row["Command"]) && $val != "") ||
			($jush == "pgsql" && $key == "current_query" && $val != "<IDLE>") ||
			($jush == "oracle" && $key == "sql_text" && $val != "")
			? "<code class='jush-$jush'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . lang(228) . '</a>'
			: h($val)
		);
	}
	echo "\n";
}
?>
</table>
</div>
<p>
<?php
if (support("kill")) {
	echo ($i + 1) . "/" . lang(229, max_connections());
	echo "<p><input type='submit' value='" . lang(230) . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php echo script("tableCheck();"); 
} elseif (isset($_GET["select"])) {
	
$TABLE = $_GET["select"];
$table_status = table_status1($TABLE);
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$foreign_keys = column_foreign_keys($TABLE);
$oid = $table_status["Oid"];
parse_str($_COOKIE["adminer_import"], $adminer_import);

$rights = array(); // privilege => 0
$columns = array(); // selectable columns
$text_length = null;
foreach ($fields as $key => $field) {
	$name = $adminer->fieldName($field);
	if (isset($field["privileges"]["select"]) && $name != "") {
		$columns[$key] = html_entity_decode(strip_tags($name), ENT_QUOTES);
		if (is_shortable($field)) {
			$text_length = $adminer->selectLengthProcess();
		}
	}
	$rights += $field["privileges"];
}

list($select, $group) = $adminer->selectColumnsProcess($columns, $indexes);
$is_group = count($group) < count($select);
$where = $adminer->selectSearchProcess($fields, $indexes);
$order = $adminer->selectOrderProcess($fields, $indexes);
$limit = $adminer->selectLimitProcess();

if ($_GET["val"] && is_ajax()) {
	header("Content-Type: text/plain; charset=utf-8");
	foreach ($_GET["val"] as $unique_idf => $row) {
		$as = convert_field($fields[key($row)]);
		$select = array($as ? $as : idf_escape(key($row)));
		$where[] = where_check($unique_idf, $fields);
		$return = $driver->select($TABLE, $select, $where, $select);
		if ($return) {
			echo reset($return->fetch_row());
		}
	}
	exit;
}

$primary = $unselected = null;
foreach ($indexes as $index) {
	if ($index["type"] == "PRIMARY") {
		$primary = array_flip($index["columns"]);
		$unselected = ($select ? $primary : array());
		foreach ($unselected as $key => $val) {
			if (in_array(idf_escape($key), $select)) {
				unset($unselected[$key]);
			}
		}
		break;
	}
}
if ($oid && !$primary) {
	$primary = $unselected = array($oid => 0);
	$indexes[] = array("type" => "PRIMARY", "columns" => array($oid));
}

if ($_POST && !$error) {
	$where_check = $where;
	if (!$_POST["all"] && is_array($_POST["check"])) {
		$checks = array();
		foreach ($_POST["check"] as $check) {
			$checks[] = where_check($check, $fields);
		}
		$where_check[] = "((" . implode(") OR (", $checks) . "))";
	}
	$where_check = ($where_check ? "\nWHERE " . implode(" AND ", $where_check) : "");
	if ($_POST["export"]) {
		cookie("adminer_import", "output=" . urlencode($_POST["output"]) . "&format=" . urlencode($_POST["format"]));
		dump_headers($TABLE);
		$adminer->dumpTable($TABLE, "");
		$from = ($select ? implode(", ", $select) : "*")
			. convert_fields($columns, $fields, $select)
			. "\nFROM " . table($TABLE);
		$group_by = ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : "");
		if (!is_array($_POST["check"]) || $primary) {
			$query = "SELECT $from$where_check$group_by";
		} else {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT" . limit($from, "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields) . $group_by, 1) . ")";
			}
			$query = implode(" UNION ALL ", $union);
		}
		$adminer->dumpData($TABLE, "table", $query);
		exit;
	}

	if (!$adminer->selectEmailProcess($where, $foreign_keys)) {
		if ($_POST["save"] || $_POST["delete"]) { // edit
			$result = true;
			$affected = 0;
			$set = array();
			if (!$_POST["delete"]) {
				foreach ($columns as $name => $val) { //! should check also for edit or insert privileges
					$val = process_input($fields[$name]);
					if ($val !== null && ($_POST["clone"] || $val !== false)) {
						$set[idf_escape($name)] = ($val !== false ? $val : idf_escape($name));
					}
				}
			}
			if ($_POST["delete"] || $set) {
				if ($_POST["clone"]) {
					$query = "INTO " . table($TABLE) . " (" . implode(", ", array_keys($set)) . ")\nSELECT " . implode(", ", $set) . "\nFROM " . table($TABLE);
				}
				if ($_POST["all"] || ($primary && is_array($_POST["check"])) || $is_group) {
					$result = ($_POST["delete"]
						? $driver->delete($TABLE, $where_check)
						: ($_POST["clone"]
							? queries("INSERT $query$where_check")
							: $driver->update($TABLE, $set, $where_check)
						)
					);
					$affected = $connection->affected_rows;
				} else {
					foreach ((array) $_POST["check"] as $val) {
						// where is not unique so OR can't be used
						$where2 = "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields);
						$result = ($_POST["delete"]
							? $driver->delete($TABLE, $where2, 1)
							: ($_POST["clone"]
								? queries("INSERT" . limit1($TABLE, $query, $where2))
								: $driver->update($TABLE, $set, $where2, 1)
							)
						);
						if (!$result) {
							break;
						}
						$affected += $connection->affected_rows;
					}
				}
			}
			$message = lang(231, $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id();
				if ($last_id) {
					$message = lang(161, " $last_id");
				}
			}
			queries_redirect(remove_from_uri($_POST["all"] && $_POST["delete"] ? "page" : ""), $message, $result);
			if (!$_POST["delete"]) {
				edit_form($TABLE, $fields, (array) $_POST["fields"], !$_POST["clone"]);
				page_footer();
				exit;
			}

		} elseif (!$_POST["import"]) { // modify
			if (!$_POST["val"]) {
				$error = lang(232);
			} else {
				$result = true;
				$affected = 0;
				foreach ($_POST["val"] as $unique_idf => $row) {
					$set = array();
					foreach ($row as $key => $val) {
						$key = bracket_escape($key, 1); // 1 - back
						$set[idf_escape($key)] = (preg_match('~char|text~', $fields[$key]["type"]) || $val != "" ? $adminer->processInput($fields[$key], $val) : "NULL");
					}
					$result = $driver->update(
						$TABLE,
						$set,
						" WHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($unique_idf, $fields),
						!$is_group && !$primary,
						" "
					);
					if (!$result) {
						break;
					}
					$affected += $connection->affected_rows;
				}
				queries_redirect(remove_from_uri(), lang(231, $affected), $result);
			}

		} elseif (!is_string($file = get_file("csv_file", true))) {
			$error = upload_error($file);
		} elseif (!preg_match('~~u', $file)) {
			$error = lang(233);
		} else {
			cookie("adminer_import", "output=" . urlencode($adminer_import["output"]) . "&format=" . urlencode($_POST["separator"]));
			$result = true;
			$cols = array_keys($fields);
			preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~', $file, $matches);
			$affected = count($matches[0]);
			$driver->begin();
			$separator = ($_POST["separator"] == "csv" ? "," : ($_POST["separator"] == "tsv" ? "\t" : ";"));
			$rows = array();
			foreach ($matches[0] as $key => $val) {
				preg_match_all("~((?>\"[^\"]*\")+|[^$separator]*)$separator~", $val . $separator, $matches2);
				if (!$key && !array_diff($matches2[1], $cols)) { //! doesn't work with column names containing ",\n
					// first row corresponds to column names - use it for table structure
					$cols = $matches2[1];
					$affected--;
				} else {
					$set = array();
					foreach ($matches2[1] as $i => $col) {
						$set[idf_escape($cols[$i])] = ($col == "" && $fields[$cols[$i]]["null"] ? "NULL" : q(str_replace('""', '"', preg_replace('~^"|"$~', '', $col))));
					}
					$rows[] = $set;
				}
			}
			$result = (!$rows || $driver->insertUpdate($TABLE, $rows, $primary));
			if ($result) {
				$result = $driver->commit();
			}
			queries_redirect(remove_from_uri("page"), lang(234, $affected), $result);
			$driver->rollback(); // after queries_redirect() to not overwrite error

		}
	}
}

$table_name = $adminer->tableName($table_status);
if (is_ajax()) {
	page_headers();
	ob_start();
} else {
	page_header(lang(42) . ": $table_name", $error);
}

$set = null;
if (isset($rights["insert"]) || !support("table")) {
	$set = "";
	foreach ((array) $_GET["where"] as $val) {
		if ($foreign_keys[$val["col"]] && count($foreign_keys[$val["col"]]) == 1 && ($val["op"] == "="
			|| (!$val["op"] && !preg_match('~[_%]~', $val["val"])) // LIKE in Editor
		)) {
			$set .= "&set" . urlencode("[" . bracket_escape($val["col"]) . "]") . "=" . urlencode($val["val"]);
		}
	}
}
$adminer->selectLinks($table_status, $set);

if (!$columns && support("table")) {
	echo "<p class='error'>" . lang(235) . ($fields ? "." : ": " . error()) . "\n";
} else {
	echo "<form action='' id='form'>\n";
	echo "<div style='display: none;'>";
	hidden_fields_get();
	echo (DB != "" ? '<input type="hidden" name="db" value="' . h(DB) . '">' . (isset($_GET["ns"]) ? '<input type="hidden" name="ns" value="' . h($_GET["ns"]) . '">' : "") : ""); // not used in Editor
	echo '<input type="hidden" name="select" value="' . h($TABLE) . '">';
	echo "</div>\n";
	$adminer->selectColumnsPrint($select, $columns);
	$adminer->selectSearchPrint($where, $columns, $indexes);
	$adminer->selectOrderPrint($order, $columns, $indexes);
	$adminer->selectLimitPrint($limit);
	$adminer->selectLengthPrint($text_length);
	$adminer->selectActionPrint($indexes);
	echo "</form>\n";

	$page = $_GET["page"];
	if ($page == "last") {
		$found_rows = $connection->result(count_rows($TABLE, $where, $is_group, $group));
		$page = floor(max(0, $found_rows - 1) / $limit);
	}

	$select2 = $select;
	$group2 = $group;
	if (!$select2) {
		$select2[] = "*";
		$convert_fields = convert_fields($columns, $fields, $select);
		if ($convert_fields) {
			$select2[] = substr($convert_fields, 2);
		}
	}
	foreach ($select as $key => $val) {
		$field = $fields[idf_unescape($val)];
		if ($field && ($as = convert_field($field))) {
			$select2[$key] = "$as AS $val";
		}
	}
	if (!$is_group && $unselected) {
		foreach ($unselected as $key => $val) {
			$select2[] = idf_escape($key);
			if ($group2) {
				$group2[] = idf_escape($key);
			}
		}
	}
	$result = $driver->select($TABLE, $select2, $where, $group2, $order, $limit, $page, true);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		if ($jush == "mssql" && $page) {
			$result->seek($limit * $page);
		}
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			if ($page && $jush == "oracle") {
				unset($row["RNUM"]);
			}
			$rows[] = $row;
		}

		// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
		if ($_GET["page"] != "last" && $limit != "" && $group && $is_group && $jush == "sql") {
			$found_rows = $connection->result(" SELECT FOUND_ROWS()"); // space to allow mysql.trace_mode
		}

		if (!$rows) {
			echo "<p class='message'>" . lang(12) . "\n";
		} else {
			$backward_keys = $adminer->backwardKeys($TABLE, $table_name);

			echo "<div class='scrollable'>";
			echo "<table id='table' cellspacing='0' class='nowrap checkable'>";
			echo script("mixin(qs('#table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true), onkeydown: editingKeydown});");
			echo "<thead><tr>" . (!$group && $select
				? ""
				: "<td><input type='checkbox' id='all-page' class='jsonly'>" . script("qs('#all-page').onclick = partial(formCheck, /check/);", "")
					. " <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . lang(236) . "</a>");
			$names = array();
			$functions = array();
			reset($select);
			$rank = 1;
			foreach ($rows[0] as $key => $val) {
				if (!isset($unselected[$key])) {
					$val = $_GET["columns"][key($select)];
					$field = $fields[$select ? ($val ? $val["col"] : current($select)) : $key];
					$name = ($field ? $adminer->fieldName($field, $rank) : ($val["fun"] ? "*" : $key));
					if ($name != "") {
						$rank++;
						$names[$key] = $name;
						$column = idf_escape($key);
						$href = remove_from_uri('(order|desc)[^=]*|page') . '&order%5B0%5D=' . urlencode($key);
						$desc = "&desc%5B0%5D=1";
						echo "<th id='th[" . h(bracket_escape($key)) . "]'>" . script("mixin(qsl('th'), {onmouseover: partial(columnMouse), onmouseout: partial(columnMouse, ' hidden')});", "");
						echo '<a href="' . h($href . ($order[0] == $column || $order[0] == $key || (!$order && $is_group && $group[0] == $column) ? $desc : '')) . '">'; // $order[0] == $key - COUNT(*)
						echo apply_sql_function($val["fun"], $name) . "</a>"; //! columns looking like functions
						echo "<span class='column hidden'>";
						echo "<a href='" . h($href . $desc) . "' title='" . lang(48) . "' class='text'> ↓</a>";
						if (!$val["fun"]) {
							echo '<a href="#fieldset-search" title="' . lang(45) . '" class="text jsonly"> =</a>';
							echo script("qsl('a').onclick = partial(selectSearch, '" . js_escape($key) . "');");
						}
						echo "</span>";
					}
					$functions[$key] = $val["fun"];
					next($select);
				}
			}

			$lengths = array();
			if ($_GET["modify"]) {
				foreach ($rows as $row) {
					foreach ($row as $key => $val) {
						$lengths[$key] = max($lengths[$key], min(40, strlen(utf8_decode($val))));
					}
				}
			}

			echo ($backward_keys ? "<th>" . lang(237) : "") . "</thead>\n";

			if (is_ajax()) {
				if ($limit % 2 == 1 && $page % 2 == 1) {
					odd();
				}
				ob_end_clean();
			}

			foreach ($adminer->rowDescriptions($rows, $foreign_keys) as $n => $row) {
				$unique_array = unique_array($rows[$n], $indexes);
				if (!$unique_array) {
					$unique_array = array();
					foreach ($rows[$n] as $key => $val) {
						if (!preg_match('~^(COUNT\((\*|(DISTINCT )?`(?:[^`]|``)+`)\)|(AVG|GROUP_CONCAT|MAX|MIN|SUM)\(`(?:[^`]|``)+`\))$~', $key)) { //! columns looking like functions
							$unique_array[$key] = $val;
						}
					}
				}
				$unique_idf = "";
				foreach ($unique_array as $key => $val) {
					if (($jush == "sql" || $jush == "pgsql") && preg_match('~char|text|enum|set~', $fields[$key]["type"]) && strlen($val) > 64) {
						$key = (strpos($key, '(') ? $key : idf_escape($key)); //! columns looking like functions
						$key = "MD5(" . ($jush != 'sql' || preg_match("~^utf8~", $fields[$key]["collation"]) ? $key : "CONVERT($key USING " . charset($connection) . ")") . ")";
						$val = md5($val);
					}
					$unique_idf .= "&" . ($val !== null ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
				}
				echo "<tr" . odd() . ">" . (!$group && $select ? "" : "<td>"
					. checkbox("check[]", substr($unique_idf, 1), in_array(substr($unique_idf, 1), (array) $_POST["check"]))
					. ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "' class='edit'>" . lang(238) . "</a>")
				);

				foreach ($row as $key => $val) {
					if (isset($names[$key])) {
						$field = $fields[$key];
						$val = $driver->value($val, $field);
						if ($val != "" && (!isset($email_fields[$key]) || $email_fields[$key] != "")) {
							$email_fields[$key] = (is_mail($val) ? $names[$key] : ""); //! filled e-mails can be contained on other pages
						}

						$link = "";
						if (preg_match('~blob|bytea|raw|file~', $field["type"]) && $val != "") {
							$link = ME . 'download=' . urlencode($TABLE) . '&field=' . urlencode($key) . $unique_idf;
						}
						if (!$link && $val !== null) { // link related items
							foreach ((array) $foreign_keys[$key] as $foreign_key) {
								if (count($foreign_keys[$key]) == 1 || end($foreign_key["source"]) == $key) {
									$link = "";
									foreach ($foreign_key["source"] as $i => $source) {
										$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
									}
									$link = ($foreign_key["db"] != "" ? preg_replace('~([?&]db=)[^&]+~', '\1' . urlencode($foreign_key["db"]), ME) : ME) . 'select=' . urlencode($foreign_key["table"]) . $link; // InnoDB supports non-UNIQUE keys
									if ($foreign_key["ns"]) {
										$link = preg_replace('~([?&]ns=)[^&]+~', '\1' . urlencode($foreign_key["ns"]), $link);
									}
									if (count($foreign_key["source"]) == 1) {
										break;
									}
								}
							}
						}
						if ($key == "COUNT(*)") { //! columns looking like functions
							$link = ME . "select=" . urlencode($TABLE);
							$i = 0;
							foreach ((array) $_GET["where"] as $v) {
								if (!array_key_exists($v["col"], $unique_array)) {
									$link .= where_link($i++, $v["col"], $v["val"], $v["op"]);
								}
							}
							foreach ($unique_array as $k => $v) {
								$link .= where_link($i++, $k, $v);
							}
						}
						
						$val = select_value($val, $link, $field, $text_length);
						$id = h("val[$unique_idf][" . bracket_escape($key) . "]");
						$value = $_POST["val"][$unique_idf][bracket_escape($key)];
						$editable = !is_array($row[$key]) && is_utf8($val) && $rows[$n][$key] == $row[$key] && !$functions[$key];
						$text = preg_match('~text|lob~', $field["type"]);
						echo "<td id='$id'";
						if (($_GET["modify"] && $editable) || $value !== null) {
							$h_value = h($value !== null ? $value : $row[$key]);
							echo ">" . ($text ? "<textarea name='$id' cols='30' rows='" . (substr_count($row[$key], "\n") + 1) . "'>$h_value</textarea>" : "<input name='$id' value='$h_value' size='$lengths[$key]'>");
						} else {
							$long = strpos($val, "<i>…</i>");
							echo " data-text='" . ($long ? 2 : ($text ? 1 : 0)) . "'"
								. ($editable ? "" : " data-warning='" . h(lang(239)) . "'")
								. ">$val</td>"
							;
						}
					}
				}

				if ($backward_keys) {
					echo "<td>";
				}
				$adminer->backwardKeysPrint($backward_keys, $rows[$n]);
				echo "</tr>\n"; // close to allow white-space: pre
			}

			if (is_ajax()) {
				exit;
			}
			echo "</table>\n";
			echo "</div>\n";
		}

		if (!is_ajax()) {
			if ($rows || $page) {
				$exact_count = true;
				if ($_GET["page"] != "last") {
					if ($limit == "" || (count($rows) < $limit && ($rows || !$page))) {
						$found_rows = ($page ? $page * $limit : 0) + count($rows);
					} elseif ($jush != "sql" || !$is_group) {
						$found_rows = ($is_group ? false : found_rows($table_status, $where));
						if ($found_rows < max(1e4, 2 * ($page + 1) * $limit)) {
							// slow with big tables
							$found_rows = reset(slow_query(count_rows($TABLE, $where, $is_group, $group)));
						} else {
							$exact_count = false;
						}
					}
				}

				$pagination = ($limit != "" && ($found_rows === false || $found_rows > $limit || $page));
				if ($pagination) {
					echo (($found_rows === false ? count($rows) + 1 : $found_rows - $page * $limit) > $limit
						? '<p><a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" class="loadmore">' . lang(240) . '</a>'
							. script("qsl('a').onclick = partial(selectLoadMore, " . (+$limit) . ", '" . lang(241) . "…');", "")
						: ''
					);
					echo "\n";
				}
			}
			
			echo "<div class='footer'><div>\n";
			if ($rows || $page) {
				if ($pagination) {
					// display first, previous 4, next 4 and last page
					$max_page = ($found_rows === false
						? $page + (count($rows) >= $limit ? 2 : 1)
						: floor(($found_rows - 1) / $limit)
					);
					echo "<fieldset>";
					if ($jush != "simpledb") {
						echo "<legend><a href='" . h(remove_from_uri("page")) . "'>" . lang(242) . "</a></legend>";
						echo script("qsl('a').onclick = function () { pageClick(this.href, +prompt('" . lang(242) . "', '" . ($page + 1) . "')); return false; };");
						echo pagination(0, $page) . ($page > 5 ? " …" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " …" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . lang(243) . "</a>"
							);
						}
					} else {
						echo "<legend>" . lang(242) . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " …" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " …" : "") : "");
					}
					echo "</fieldset>\n";
				}
				
				echo "<fieldset>";
				echo "<legend>" . lang(244) . "</legend>";
				$display_rows = ($exact_count ? "" : "~ ") . $found_rows;
				echo checkbox("all", 1, 0, ($found_rows !== false ? ($exact_count ? "" : "~ ") . lang(143, $found_rows) : ""), "var checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$display_rows' : checked); selectCount('selected2', this.checked || !checked ? '$display_rows' : checked);") . "\n";
				echo "</fieldset>\n";

				if ($adminer->selectCommandPrint()) {
					?>
<fieldset<?php echo ($_GET["modify"] ? '' : ' class="jsonly"'); ?>><legend><?php echo lang(236); ?></legend><div>
<input type="submit" value="<?php echo lang(14); ?>"<?php echo ($_GET["modify"] ? '' : ' title="' . lang(232) . '"'); ?>>
</div></fieldset>
<fieldset><legend><?php echo lang(120); ?> <span id="selected"></span></legend><div>
<input type="submit" name="edit" value="<?php echo lang(10); ?>">
<input type="submit" name="clone" value="<?php echo lang(228); ?>">
<input type="submit" name="delete" value="<?php echo lang(18); ?>"><?php echo confirm(); ?>
</div></fieldset>
<?php
				}

				$format = $adminer->dumpFormat();
				foreach ((array) $_GET["columns"] as $column) {
					if ($column["fun"]) {
						unset($format['sql']);
						break;
					}
				}
				if ($format) {
					print_fieldset("export", lang(62) . " <span id='selected2'></span>");
					$output = $adminer->dumpOutput();
					echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
					echo html_select("format", $format, $adminer_import["format"]);
					echo " <input type='submit' name='export' value='" . lang(62) . "'>\n";
					echo "</div></fieldset>\n";
				}

				$adminer->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
			}

			echo "</div></div>\n";

			if ($adminer->selectImportPrint()) {
				echo "<div>";
				echo "<a href='#import'>" . lang(61) . "</a>";
				echo script("qsl('a').onclick = partial(toggle, 'import');", "");
				echo "<span id='import' class='hidden'>: ";
				echo "<input type='file' name='csv_file'> ";
				echo html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"], 1); // 1 - select
				echo " <input type='submit' name='import' value='" . lang(61) . "'>";
				echo "</span>";
				echo "</div>";
			}

			echo "<input type='hidden' name='token' value='$token'>\n";
			echo "</form>\n";
			echo (!$group && $select ? "" : script("tableCheck();"));
		}
	}
}

if (is_ajax()) {
	ob_end_clean();
	exit;
}

} elseif (isset($_GET["variables"])) {
	
$status = isset($_GET["status"]);
page_header($status ? lang(112) : lang(111));

$variables = ($status ? show_status() : show_variables());
if (!$variables) {
	echo "<p class='message'>" . lang(12) . "\n";
} else {
	echo "<table cellspacing='0'>\n";
	foreach ($variables as $key => $val) {
		echo "<tr>";
		echo "<th><code class='jush-" . $jush . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		echo "<td>" . h($val);
	}
	echo "</table>\n";
}

} elseif (isset($_GET["script"])) {
	
header("Content-Type: text/javascript; charset=utf-8");

if ($_GET["script"] == "db") {
	$sums = array("Data_length" => 0, "Index_length" => 0, "Data_free" => 0);
	foreach (table_status() as $name => $table_status) {
		json_row("Comment-$name", h($table_status["Comment"]));
		if (!is_view($table_status)) {
			foreach (array("Engine", "Collation") as $key) {
				json_row("$key-$name", h($table_status[$key]));
			}
			foreach ($sums + array("Auto_increment" => 0, "Rows" => 0) as $key => $val) {
				if ($table_status[$key] != "") {
					$val = format_number($table_status[$key]);
					json_row("$key-$name", ($key == "Rows" && $val && $table_status["Engine"] == ($sql == "pgsql" ? "table" : "InnoDB")
						? "~ $val"
						: $val
					));
					if (isset($sums[$key])) {
						// ignore innodb_file_per_table because it is not active for tables created before it was enabled
						$sums[$key] += ($table_status["Engine"] != "InnoDB" || $key != "Data_free" ? $table_status[$key] : 0);
					}
				} elseif (array_key_exists($key, $table_status)) {
					json_row("$key-$name");
				}
			}
		}
	}
	foreach ($sums as $key => $val) {
		json_row("sum-$key", format_number($val));
	}
	json_row("");

} elseif ($_GET["script"] == "kill") {
	$connection->query("KILL " . number($_POST["kill"]));

} else { // connect
	foreach (count_tables($adminer->databases()) as $db => $val) {
		json_row("tables-$db", $val);
		json_row("size-$db", db_size($db));
	}
	json_row("");
}

exit; // don't print footer

} else {
	
$tables_views = array_merge((array) $_POST["tables"], (array) $_POST["views"]);

if ($tables_views && !$error && !$_POST["search"]) {
	$result = true;
	$message = "";
	if ($jush == "sql" && $_POST["tables"] && count($_POST["tables"]) > 1 && ($_POST["drop"] || $_POST["truncate"] || $_POST["copy"])) {
		queries("SET foreign_key_checks = 0"); // allows to truncate or drop several tables at once
	}

	if ($_POST["truncate"]) {
		if ($_POST["tables"]) {
			$result = truncate_tables($_POST["tables"]);
		}
		$message = lang(245);
	} elseif ($_POST["move"]) {
		$result = move_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang(246);
	} elseif ($_POST["copy"]) {
		$result = copy_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = lang(247);
	} elseif ($_POST["drop"]) {
		if ($_POST["views"]) {
			$result = drop_views($_POST["views"]);
		}
		if ($result && $_POST["tables"]) {
			$result = drop_tables($_POST["tables"]);
		}
		$message = lang(248);
	} elseif ($jush != "sql") {
		$result = ($jush == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? "" : " ANALYZE"), $_POST["tables"])
		);
		$message = lang(249);
	} elseif (!$_POST["tables"]) {
		$message = lang(9);
	} elseif ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"])))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? lang(26) . ": " . h(DB) : lang(189) . ": " . h($_GET["ns"])), $error, true);

if ($adminer->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3 id='tables-views'>" . lang(250) . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . lang(9) . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . lang(251) . " <span id='selected2'></span></legend><div>";
				echo "<input type='search' name='query' value='" . h($_POST["query"]) . "'>";
				echo script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');", "");
				echo " <input type='submit' name='search' value='" . lang(45) . "'>\n";
				echo "</div></fieldset>\n";
				if ($_POST["search"] && $_POST["query"] != "") {
					$_GET["where"][0]["op"] = "LIKE %%";
					search_tables();
				}
			}
			echo "<div class='scrollable'>\n";
			echo "<table cellspacing='0' class='nowrap checkable'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo '<thead><tr class="wrap">';
			echo '<td><input id="check-all" type="checkbox" class="jsonly">' . script("qs('#check-all').onclick = partial(formCheck, /^(tables|views)\[/);", "");
			echo '<th>' . lang(124);
			echo '<td>' . lang(252) . doc_link(array('sql' => 'storage-engines.html'));
			echo '<td>' . lang(116) . doc_link(array('sql' => 'charset-charsets.html', 'mariadb' => 'supported-character-sets-and-collations/'));
			echo '<td>' . lang(253) . doc_link(array('sql' => 'show-table-status.html',  ));
			echo '<td>' . lang(254) . doc_link(array('sql' => 'show-table-status.html', ));
			echo '<td>' . lang(255) . doc_link(array('sql' => 'show-table-status.html'));
			echo '<td>' . lang(40) . doc_link(array('sql' => 'example-auto-increment.html', 'mariadb' => 'auto_increment/'));
			echo '<td>' . lang(256) . doc_link(array('sql' => 'show-table-status.html',  ));
			echo (support("comment") ? '<td>' . lang(39) . doc_link(array('sql' => 'show-table-status.html', )) : '');
			echo "</thead>\n";

			$tables = 0;
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !preg_match('~table|sequence~i', $type));
				$id = h("Table-" . $name);
				echo '<tr' . odd() . '><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array($name, $tables_views, true), "", "", "", $id);
				echo '<th>' . (support("table") || support("indexes") ? "<a href='" . h(ME) . "table=" . urlencode($name) . "' title='" . lang(31) . "' id='$id'>" . h($name) . '</a>' : h($name));
				if ($view) {
					echo '<td colspan="6"><a href="' . h(ME) . "view=" . urlencode($name) . '" title="' . lang(32) . '">' . (preg_match('~materialized~i', $type) ? lang(122) : lang(123)) . '</a>';
					echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '" title="' . lang(30) . '">?</a>';
				} else {
					foreach (array(
						"Engine" => array(),
						"Collation" => array(),
						"Data_length" => array("create", lang(33)),
						"Index_length" => array("indexes", lang(126)),
						"Data_free" => array("edit", lang(34)),
						"Auto_increment" => array("auto_increment=1&create", lang(33)),
						"Rows" => array("select", lang(30)),
					) as $key => $link) {
						$id = " id='$key-" . h($name) . "'";
						echo ($link ? "<td align='right'>" . (support("table") || $key == "Rows" || (support("indexes") && $key != "Data_length")
							? "<a href='" . h(ME . "$link[0]=") . urlencode($name) . "'$id title='$link[1]'>?</a>"
							: "<span$id>?</span>"
						) : "<td id='$key-" . h($name) . "'>");
					}
					$tables++;
				}
				echo (support("comment") ? "<td id='Comment-" . h($name) . "'>" : "");
			}

			echo "<tr><td><th>" . lang(229, count($tables_list));
			echo "<td>" . h($jush == "sql" ? $connection->result("SELECT @@default_storage_engine") : "");
			echo "<td>" . h(db_collation(DB, collations()));
			foreach (array("Data_length", "Index_length", "Data_free") as $key) {
				echo "<td align='right' id='sum-$key'>";
			}

			echo "</table>\n";
			echo "</div>\n";
			if (!information_schema(DB)) {
				echo "<div class='footer'><div>\n";
				$vacuum = "<input type='submit' value='" . lang(257) . "'> " . on_help("'VACUUM'");
				$optimize = "<input type='submit' name='optimize' value='" . lang(258) . "'> " . on_help($jush == "sql" ? "'OPTIMIZE TABLE'" : "'VACUUM OPTIMIZE'");
				echo "<fieldset><legend>" . lang(120) . " <span id='selected'></span></legend><div>"
				. ($jush == "sqlite" ? $vacuum
				: ($jush == "pgsql" ? $vacuum . $optimize
				: ($jush == "sql" ? "<input type='submit' value='" . lang(259) . "'> " . on_help("'ANALYZE TABLE'") . $optimize
					. "<input type='submit' name='check' value='" . lang(260) . "'> " . on_help("'CHECK TABLE'")
					. "<input type='submit' name='repair' value='" . lang(261) . "'> " . on_help("'REPAIR TABLE'")
				: "")))
				. "<input type='submit' name='truncate' value='" . lang(262) . "'> " . on_help($jush == "sqlite" ? "'DELETE'" : "'TRUNCATE" . ($jush == "pgsql" ? "'" : " TABLE'")) . confirm()
				. "<input type='submit' name='drop' value='" . lang(121) . "'>" . on_help("'DROP TABLE'") . confirm() . "\n";
				$databases = (support("scheme") ? $adminer->schemas() : $adminer->databases());
				if (count($databases) != 1 && $jush != "sqlite") {
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo "<p>" . lang(263) . ": ";
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo " <input type='submit' name='move' value='" . lang(264) . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . lang(265) . "'> " . checkbox("overwrite", 1, $_POST["overwrite"], lang(266)) : "");
					echo "\n";
				}
				echo "<input type='hidden' name='all' value=''>"; // used by trCheck()
				echo script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^(tables|views)\[/));" . (support("table") ? " selectCount('selected2', formChecked(this, /^tables\[/) || $tables);" : "") . " }");
				echo "<input type='hidden' name='token' value='$token'>\n";
				echo "</div></fieldset>\n";
				echo "</div></div>\n";
			}
			echo "</form>\n";
			echo script("tableCheck();");
		}

		echo '<p class="links"><a href="' . h(ME) . 'create=">' . lang(63) . "</a>\n";
		echo (support("view") ? '<a href="' . h(ME) . 'view=">' . lang(195) . "</a>\n" : "");

		if (support("routine")) {
			echo "<h3 id='routines'>" . lang(136) . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table cellspacing='0'>\n";
				echo '<thead><tr><th>' . lang(176) . '<td>' . lang(38) . '<td>' . lang(212) . "<td></thead>\n";
				odd('');
				foreach ($routines as $row) {
					$name = ($row["SPECIFIC_NAME"] == $row["ROUTINE_NAME"] ? "" : "&name=" . urlencode($row["ROUTINE_NAME"])); // not computed on the pages to be able to print the header first
					echo '<tr' . odd() . '>';
					echo '<th><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . lang(129) . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p class="links">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . lang(211) . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . lang(210) . "</a>\n"
			;
		}





		if (support("event")) {
			echo "<h3 id='events'>" . lang(137) . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . lang(176) . "<td>" . lang(267) . "<td>" . lang(201) . "<td>" . lang(202) . "<td></thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"] ? lang(268) . "<td>" . $row["Execute at"] : lang(203) . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
					echo '<td><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . lang(129) . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = $connection->result("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links"><a href="' . h(ME) . 'event=">' . lang(200) . "</a>\n";
		}

		if ($tables_list) {
			echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
		}
	}
}

}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
