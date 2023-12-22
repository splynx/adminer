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
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>â€¦</i>");
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
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . lang(17) . "â€¦', this); };") : "");
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
	echo lzw_decompress("\0\0\0` \0„\0\n @\0´C„è\"\0`EãQ¸àÿ‡?ÀtvM'”JdÁd\\Œb0\0Ä\"™ÀfÓˆ¤îs5›ÏçÑAXPaJ“0„¥‘8„#RŠT©‘z`ˆ#.©ÇcíXÃşÈ€?À-\0¡Im? .«M¶€\0È¯(Ì‰ıÀ/(%Œ\0");
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress("\n1Ì‡“ÙŒŞl7œ‡B1„4vb0˜Ífs‘¼ên2BÌÑ±Ù˜Şn:‡#(¼b.\rDc)ÈÈa7E„‘¤Âl¦Ã±”èi1Ìs˜´ç-4™‡fÓ	ÈÎi7†³¹¤Èt4…¦ÓyèZf4°i–AT«VVéf:Ï¦,:1¦Qİ¼ñb2`Ç#ş>:7Gï—1ÑØÒs°™L—XD*bv<ÜŒ#£e@Ö:4ç§!fo·Æt:<¥Üå’¾™oâÜ\niÃÅğ',é»a_¤:¹iï…´ÁBvø|Nû4.5Nfi¢vpĞh¸°l¨ê¡ÖšÜO¦‰î= £OFQĞÄk\$¥Óiõ™ÀÂd2Tã¡pàÊ6„‹ş‡¡-ØZ€ƒ Ş6½£€ğh:¬aÌ,£ëî2#8Ğ±#’˜6nâî†ñJˆ¢h«t…Œ±Šä4O42ô½okŞ¾*r ©€@p@†!Ä¾ÏÃôş?Ğ6À‰r[ğLÁğ‹:2Bˆj§!HbóÃPä=!1V‰\"ˆ²0…¿\nSÆÆÏD7ÃìDÚ›ÃC!†!›à¦GÊŒ§ È+’=tCæ©.C¤À:+ÈÊ=ªªº²¡±å%ªcí1MR/”EÈ’4„© 2°ä± ã`Â8(áÓ¹[WäÑ=‰ySb°=Ö-Ü¹BS+É¯ÈÜı¥ø@pL4Ydã„qŠøã¦ğê¢6£3Ä¬¯¸AcÜŒèÎ¨Œk‚[&>ö•¨ZÁpkm]—u-c:Ø¸ˆNtæÎ´pÒŒŠ8è=¿#˜á[.ğÜŞ¯~ mËy‡PPá|IÖ›ùÀìQª9v[–Q•„\n–Ùrô'g‡+áTÑ2…­VÁõzä4£8÷(	¾Ey*#j¬2]­•RÒÁ‘¥)ƒÀ[N­R\$Š<>:ó­>\$;–> Ì\r»„ÎHÍÃTÈ\nw¡N åwØ£¦ì<ïËGwàöö¹\\Yó_ Rt^Œ>\r}ŒÙS\rzé4=µ\nL”%Jã‹\",Z 8¸™i÷0u©?¨ûÑô¡s3#¨Ù‰ :ó¦ûã½–ÈŞE]xİÒs^8£K^É÷*0ÑŞwŞàÈŞ~ãö:íÑiØşv2w½ÿ±û^7ãò7£cİÑu+U%{PÜ*4Ì¼éLX./!¼‰1CÅßqx!H¹ãFdù­L¨¤¨Ä Ï`6ëè5®™f€¸Ä†¨=Høl ŒV1“›\0a2×;Ô6†àöş_Ù‡Ä\0&ôZÜS d)KE'’€nµ[X©³\0ZÉŠÔF[P‘Ş˜@àß!‰ñYÂ,`É\"Ú·Â0Ee9yF>ËÔ9bº–ŒæF5:üˆ”\0}Ä´Š‡(\$Ó‡ë€37Hö£è M¾A°²6R•ú{Mqİ7G ÚC™Cêm2¢(ŒCt>[ì-tÀ/&C›]êetGôÌ¬4@r>ÇÂå<šSq•/åú”QëhmšÀĞÆôãôLÀÜ#èôKË|®™„6fKPİ\r%tÔÓV=\" SH\$} ¸)w¡,W\0F³ªu@Øb¦9‚\rr°2Ã#¬DŒ”Xƒ³ÚyOIù>»…n†Ç¢%ãù'‹İ_Á€t\rÏ„zÄ\\1˜hl¼]Q5Mp6k†ĞÄqhÃ\$£H~Í|Òİ!*4ŒñòÛ`Sëı²S tíPP\\g±è7‡\n-Š:è¢ªp´•”ˆl‹B¦î”7Ó¨cƒ(wO0\\:•Ğw”Áp4ˆ“ò{TÚújO¤6HÃŠ¶rÕ¥q\n¦É%%¶y']\$‚”a‘ZÓ.fcÕq*-êFWºúk„zƒ°µj‘°lgáŒ:‡\$\"ŞN¼\r#ÉdâÃ‚ÂÿĞscá¬Ì „ƒ\"jª\rÀ¶–¦ˆÕ’¼Ph‹1/‚œDA) ²İ[ÀknÁp76ÁY´‰R{áM¤Pû°ò@\n-¸a·6şß[»zJH,–dl B£ho³ìò¬+‡#Dr^µ^µÙeš¼E½½– ÄœaP‰ôõJG£zàñtñ 2ÇXÙ¢´Á¿V¶×ßàŞÈ³‰ÑB_%K=E©¸bå¼¾ßÂ§kU(.!Ü®8¸œüÉI.@KÍxnş¬ü:ÃPó32«”míH		C*ì:vâTÅ\nR¹ƒ•µ‹0uÂíƒæîÒ§]Î¯˜Š”P/µJQd¥{L–Ş³:YÁ2b¼œT ñÊ3Ó4†—äcê¥V=¿†L4ÎĞrÄ!ßBğY³6Í­MeLŠªÜçœöùiÀoĞ9< G”¤Æ•Ğ™Mhm^¯UÛNÀŒ·òTr5HiM”/¬nƒí³T [-<__î3/Xr(<‡¯Š†®Éô“ÌuÒ–GNX20å\r\$^‡:'9è¶O…í;×k¼†µf –N'a¶”Ç­bÅ,ËV¤ô…«1µïHI!%6@úÏ\$ÒEGÚœ¬1(mUªå…rÕ½ïßå`¡ĞiN+Ãœñ)šœä0lØÒf0Ã½[UâøVÊè-:I^ ˜\$Øs«b\re‡‘ugÉhª~9Ûßˆb˜µôÂÈfä+0¬Ô hXrİ¬©!\$—e,±w+„÷ŒëŒ3†Ì_âA…kšù\nkÃrõÊ›cuWdYÿ\\×={.óÄ˜¢g»‰p8œt\rRZ¿vJ:²>ş£Y|+Å@À‡ƒÛCt\r€jt½6²ğ%Â?àôÇñ’>ù/¥ÍÇğÎ9F`×•äòv~K¤áöÑRĞW‹ğz‘êlmªwLÇ9Y•*q¬xÄzñèSe®İ›³è÷£~šDàÍá–÷x˜¾ëÉŸi7•2ÄøÑOİ»’û_{ñú53âút˜›_ŸõzÔ3ùd)‹C¯Â\$?KÓªP%ÏÏT&ş˜&\0P×NA^­~¢ƒ pÆ öÏœ“Ôõ\r\$ŞïĞÖìb*+D6ê¶¦ÏˆŞíJ\$(ÈolŞÍh&”ìKBS>¸‹ö;z¶¦xÅoz>íœÚoÄZğ\nÊ‹[Ïvõ‚ËÈœµ°2õOxÙVø0fû€ú¯Ş2BlÉbkĞ6ZkµhXcdê0*ÂKTâ¯H=­•Ï€‘p0ŠlVéõèâ\r¼Œ¥nm¦ï)((ô:#¦âòE‰Ü:C¨CàÚâ\r¨G\rÃ©0÷…iæÚ°ş:`Z1Q\n:€à\r\0àçÈq±°ü:`¿-ÈM#}1;èş¹‹q‘#|ñS€¾¢hl™DÄ\0fiDpëL ``™°çÑ0y€ß1…€ê\rñ=‘MQ\\¤³%oq–­\0Øñ£1¨21¬1°­ ¿±§Ñœbi:“í\r±/Ñ¢› `)šÄ0ù‘@¾Â›±ÃI1«NàCØàŠµñO±¢Zñã1±ïq1 òÑüà,å\rdIÇ¦väjí‚1 tÚBø“°â’0:…0ğğ“1 A2V„ñâ0 éñ%²fi3!&Q·Rc%Òq&w%Ñì\ràVÈ#Êø™Qw`‹% ¾„Òm*r…Òy&iß+r{*²»(rg(±#(2­(ğå)R@i›-  ˆ•1\"\0Û²Rêÿ.e.rëÄ,¡ry(2ªCàè²bì!BŞ3%Òµ,R¿1²Æ&èşt€äbèa\rL“³-3á Ö ó\0æóBp—1ñ94³O'R°3*²³=\$à[£^iI;/3i©5Ò&’}17²# Ñ¹8 ¿\"ß7Ñå8ñ9*Ò23™!ó!1\\\0Ï8“­rk9±;S…23¶àÚ“*Ó:q]5S<³Á#383İ#eÑ=¹>~9Sè³‘rÕ)€ŒT*aŸ@Ñ–ÙbesÙÔ£:-ó€éÇ*;, Ø™3!i´›‘LÒ²ğ#1 +nÀ «*²ã@³3i7´1©´_•F‘S;3ÏF±\rA¯é3õ>´x:ƒ \r³0ÎÔ@’-Ô/¬ÓwÓÛ7ñ„ÓS‘J3› ç.Fé\$O¤B’±—%4©+tÃ'góLq\rJt‡JôËM2\rôÍ7ñÆT@“£¾)â“£dÉ2€P>Î°€Fià²´ş\nr\0¸bçk(´D¶¿ãKQƒ¤´ã1ã\"2t”ôôºPè\rÃÀ,\$KCtò5ôö#ôú)¢áP#Pi.ÎU2µCæ~Ş\"ä");
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("f:›ŒgCI¼Ü\n8œÅ3)°Ë7œ…†81ĞÊx:\nOg#)Ğêr7\n\"†è´`ø|2ÌgSi–H)N¦S‘ä§\r‡\"0¹Ä@ä)Ÿ`(\$s6O!ÓèœV/=Œ' T4æ=„˜iS˜6IO G#ÒX·VCÆs¡ Z1.Ğhp8,³[¦Häµ~Cz§Éå2¹l¾c3šÍés£‘ÙI†bâ4\néF8Tà†I˜İ©U*fz¹är0EÆÀØy¸ñfY.:æƒIŒÊ(Øc·áÎ‹!_l™í^·^(¶šN{S–“)rËqÁY“–lÙ¦3Š3Ú\n˜+G¥Óêyºí†Ëi¶ÂîxV3w³uhã^rØÀº´aÛ”ú¹cØè\r“¨ë(.ÂˆºChÒ<\r)èÑ£¡`æ7£íò43'm5Œ£È\nPÜ:2£P»ª‹q òÿÅC“}Ä«ˆúÊÁê38‹BØ0hR‰Èr(œ0¥¡b\\0ŒHr44ŒÁB!¡pÇ\$rZZË2Ü‰.Éƒ(\\5Ã|\nC(Î\"€P…ğø.ĞNÌRTÊÎ“Àæ>HN…8HPá\\¬7Jp~„Üû2%¡ĞOC¨1ã.ƒ§C8Î‡HÈò*ˆj°…á÷S(¹/¡ì¬6KUœÊ‡¡<2‰pOI„ôÕ`Ôäâ³ˆdOH Ş5-üÆ4ŒãpX25-Ò¢òÛˆ°z7£¸\"(°P \\32:]UÚèíâß…!]¸<·AÛÛ¤’ĞßiÚ°‹l\rÔ\0v²Î#J8«ÏwmíÉ¤¨<ŠÉ æü%m;p#ã`XDŒø÷iZøN0Œ•È9ø¨å Áè`…wJD¿¾2Ò9tŒ¢*øÎyìËNiIh\\9ÆÕèĞ:ƒ€æáxï­µyl*šÈˆÎæY Ü‡øê8’W³â?µŞ›3ÙğÊ!\"6å›n[¬Ê\r­*\$¶Æ§¾nzxÆ9\rì|*3×£pŞï»¶:(p\\;ÔËmz¢ü§9óĞÑÂŒü8N…Áj2½«Î\rÉHîH&Œ²(Ãz„Á7iÛk£ ‹Š¤‚c¤‹eòı§tœÌÌ2:SHóÈ Ã/)–xŞ@éåt‰ri9¥½õëœ8ÏÀËïyÒ·½°VÄ+^WÚ¦­¬kZæY—l·Ê£Œ4ÖÈÆ‹ª¶À¬‚ğ\\EÈ{î7\0¹p†€•D€„i”-TæşÚû0l°%=Á ĞËƒ9(„5ğ\n\n€n,4‡\0èa}Üƒ.°öRsï‚ª\02B\\Ûb1ŸS±\0003,ÔXPHJspåd“Kƒ CA!°2*WŸÔñÚ2\$ä+Âf^\n„1Œ´òzEƒ Iv¤\\äœ2É .*A°™”E(d±á°ÃbêÂÜ„Æ9‡‚â€ÁDh&­ª?ÄH°sQ˜2’x~nÃJ‹T2ù&ãàeRœ½™GÒQTwêİ‘»õPˆâã\\ )6¦ôâœÂòsh\\3¨\0R	À'\r+*;RğHà.“!Ñ[Í'~­%t< çpÜK#Â‘æ!ñlßÌğLeŒ³œÙ,ÄÀ®&á\$	Á½`”–CXš‰Ó†0Ö­å¼û³Ä:Méh	çÚœGäÑ!&3 D<!è23„Ã?h¤J©e Úğhá\r¡m•˜ğNi¸£´’†ÊNØHl7¡®v‚êWIå.´Á-Ó5Ö§ey\rEJ\ni*¼\$@ÚRU0,\$U¿E†¦ÔÔÂªu)@(tÎSJkáp!€~­‚àd`Ì>¯•\nÃ;#\rp9†jÉ¹Ü]&Nc(r€ˆ•TQUª½S·Ú\08n`«—y•b¤ÅLÜO5‚î,¤ò‘>‚†xââ±fä´’âØ+–\"ÑI€{kMÈ[\r%Æ[	¤eôaÔ1! èÿí³Ô®©F@«b)RŸ£72ˆî0¡\nW¨™±L²ÜœÒ®tdÕ+íÜ0wglø0n@òêÉ¢ÕiíM«ƒ\nA§M5nì\$E³×±NÛál©İŸ×ì%ª1 AÜûºú÷İkñrîiFB÷Ïùol,muNx-Í_ Ö¤C( fél\r1p[9x(i´BÒ–²ÛzQlüº8CÔ	´©XU Tb£İIİ`•p+V\0î‹Ñ;‹CbÎÀXñ+Ï’sïü]H÷Ò[ák‹x¬G*ô†]·awnú!Å6‚òâÛĞmSí¾“IŞÍKË~/Ó¥7ŞùeeNÉòªS«/;dåA†>}l~Ïê ¨%^´fçØ¢pÚœDEîÃa·‚t\nx=ÃkĞ„*dºêğT—ºüûj2ŸÉjœ\n‘ É ,˜e=‘†M84ôûÔa•j@îTÃsÔänf©İ\nî6ª\rdœ¼0ŞíôYŠ'%Ô“íŞ~	Ò¨†<ÖË–Aî‹–H¿G‚8ñ¿Îƒ\$z«ğ{¶»²u2*†àa–À>»(wŒK.bP‚{…ƒoı”Â´«zµ#ë2ö8=É8>ª¤³A,°e°À…+ìCè§xõ*ÃáÒ-b=m‡™Ÿ,‹a’Ãlzkï\$Wõ,mJiæÊ§á÷+‹èı0°[¯ÿ.RÊsKùÇäXçİZLËç2`Ì(ïCàvZ¡ÜİÀ¶è\$×¹,åD?H±ÖNxXôó)’îM¨‰\$ó,Í*\nÑ£\$<qÿÅŸh!¿¹S“âƒÀŸxsA!˜:´K¥Á}Á²“ù¬£œRşšA2k·Xp\n<÷ş¦ıëlì§Ù3¯ø¦È•VV¬}£g&Yİ!†+ó;<¸YÇóŸYE3r³Ùñ›Cío5¦Åù¢Õ³Ïkkş…ø°ÖÛ£«Ït÷’Uø…­)û[ıßÁî}ïØu´«lç¢:DŸø+Ï _oãäh140ÖáÊ0ø¯bäK˜ã¬’ öşé»lGª„#ªš©ê†¦©ì|Udæ¶IK«êÂ7à^ìà¸@º®O\0HÅğHiŠ6\r‡Û©Ü\\cg\0öãë2BÄ*eà\n€š	…zr!nWz& {H–ğ'\$X  w@Ò8ëDGr*ëÄİHå'p#Ä®€¦Ô\ndü€÷,ô¥—,ü;g~¯\0Ğ#€Ì²EÂ\rÖI`œî'ƒğ%EÒ. ]`ÊĞ›…î%&Ğîm°ı\râŞ%4S„vğ#\n fH\$%ë-Â#­ÆÑqBâíæ ÀÂQ-ôc2Š§‚&ÂÀÌ]à™ èqh\rñl]à®s ĞÑhä7±n#±‚‚Ú-àjE¯Frç¤l&dÀØÙåzìF6¸ˆÁ\" “|¿§¢s@ß±®åz)0rpÚ\0‚X\0¤Ùè|DL<!°ôo„*‡D¶{.B<Eª‹‹0nB(ï |\r\nì^©à h³!‚Öêr\$§’(^ª~èŞÂ/pq²ÌB¨ÅOšˆğú,\\µ¨#RRÎ%ëäÍdĞHjÄ`Â ô®Ì­ Vå bS’d§iE‚øïoh´r<i/k\$-Ÿ\$o”¼+ÆÅ‹ÎúlÒŞO³&evÆ’¼iÒjMPA'u'Î’( M(h/+«òWD¾So·.n·.ğn¸ìê(œ(\"­À§hö&p†¨/Ë/1DÌŠçjå¨¸EèŞ&â¦€,'l\$/.,Äd¨…‚W€bbO3óB³sH :J`!“.€ª‚‡Àû¥ ,FÀÑ7(‡ÈÔ¿³û1Šlås ÖÒ‘²—Å¢q¢X\rÀš®ƒ~Ré°±`®Òó®Y*ä:R¨ùrJ´·%LÏ+n¸\"ˆø\r¦ÎÍ‡H!qb¾2âLi±%ÓŞÎ¨Wj#9ÓÔObE.I:…6Á7\0Ë6+¤%°.È…Ş³a7E8VSå?(DG¨Ó³Bë%;ò¬ùÔ/<’´ú¥À\r ì´>ûMÀ°@¶¾€H DsĞ°Z[tH£Enx(ğŒ©R xñû@¯şGkjW”>ÌÂÚ#T/8®c8éQ0Ëè_ÔIIGII’!¥ğŠYEdËE´^tdéthÂ`DV!Cæ8¥\r­´Ÿb“3©!3â@Ù33N}âZBó3	Ï3ä30ÚÜM(ê>‚Ê}ä\\Ñtê‚f fŒËâI\r®€ó337 XÔ\"tdÎ,\nbtNO`Pâ;­Ü•Ò­ÀÔ¯\$\n‚ßäZÑ­5U5WUµ^hoıàætÙPM/5K4Ej³KQ&53GX“Xx)Ò<5D…\rûVô\nßr¢5bÜ€\\J\">§è1S\r[-¦ÊDuÀ\rÒâ§Ã)00óYõÈË¢·k{\nµÄ#µŞ\r³^·‹|èuÜ»Uå_nïU4ÉUŠ~YtÓ\rIšÃ@ä³™R ó3:ÒuePMSè0TµwW¯XÈòòD¨ò¤KOUÜà•‡;Uõ\n OYéYÍQ,M[\0÷_ªDšÍÈW ¾J*ì\rg(]à¨\r\"ZC‰©6uê+µYóˆY6Ã´0ªqõ(Ùó8}ó3AX3T h9j¶jàfõMtåPJbqMP5>ğÈø¶©Y‡k%&\\‚1d¢ØE4À µYnÊí\$<¥U]Ó‰1‰mbÖ¶^Òõš ê\"NVéßp¶ëpõ±eMÚŞ×WéÜ¢î\\ä)\n Ë\nf7\n×2´õr8‹—=Ek7tVš‡µ7P¦¶LÉía6òòv@'‚6iàïj&>±â;­ã`Òÿa	\0pÚ¨(µJÑë)«\\¿ªnûòÄ¬m\0¼¨2€ôeqJö­PôtŒë±fjüÂ\"[\0¨·†¢X,<\\Œî¶×â÷æ·+md†å~âàš…Ñs%o°´mn×),×„æÔ‡²\r4¶Â8\r±Î¸×mE‚H]‚¦˜üÖHW­M0Dïß€—å~Ë˜K˜îE}ø¸´à|fØ^“Ü×\r>Ô-z]2s‚xD˜d[s‡tS¢¶\0Qf-K`­¢‚tàØ„wT¯9€æZ€à	ø\nB£9 Nb–ã<ÚBşI5o×oJñpÀÏJNdåË\rhŞÃ2\"àxæHCàİ–:øı9Yn16Æôzr+z±ùş\\’÷•œôm Ş±T öò ÷@Y2lQ<2O+¥%“Í.Óƒhù0AŞñ¸ŠÃZ‹2R¦À1£Š/¯hH\r¨X…ÈaNB&§ ÄM@Ö[xŒ‡Ê®¥ê–â8&LÚVÍœvà±*šj¤ÛšGHåÈ\\Ù®	™²¶&sÛ\0Qš \\\"èb °	àÄ\rBs›Éw‚	ÙáBN`š7§Co(ÙÃà¨\nÃ¨“¨1š9Ì*E˜ ñS…ÓU0Uº tš'|”m™°Ş?h[¢\$.#É5	 å	p„àyBà@Rô]£…ê@|„§{™ÀÊP\0xô/¦ w¢%¤EsBd¿§šCUš~O×·àPà@Xâ]Ô…¨Z3¨¥1¦¥{©eLY‰¡ŒÚ¢\\’(*R` 	à¦\n…ŠàºÌQCFÈ*¹¹àéœ¬Úp†X|`N¨‚¾\$€[†‰’@ÍU¢àğ¦¶àZ¥`Zd\"\\\"…‚¢£)«‡Iˆ:ètšìoDæ\0[²¨à±‚-©“ gí³‰™®*`hu%£,€”¬ãIµ7Ä«²Hóµm¤6Ş}®ºNÖÍ³\$»MµUYf&1ùÀ›e]pz¥§ÚI¤Åm¶G/£ ºw Ü!•\\#5¥4I¥d¹EÂhq€å¦÷Ñ¬kçx|Úk¥qDšb…z?§º‰>úƒ¾:†“[èLÒÆ¬Z°Xš®:¹„·ÚÇjßw5	¶Y¾0 ©Â“­¯\$\0C¢†dSg¸ë‚ {@”\n`	ÀÃüC ¢·»Mºµâ»²# t}xÎN„÷º‡{ºÛ°)êûCƒÊFKZŞj™Â\0PFY”BäpFk–›0<Ú>ÊD<JE™šg\rõ.“2–ü8éU@*Î5fkªÌJDìÈÉ4•TDU76É/´è¯@·‚K+„ÃöJ®ºÃÂí@Ó=ŒÜWIOD³85MšNº\$Rô\0ø5¨\ràù_ğªœìEœñÏI«Ï³Nçl£Òåy\\ô‘ˆÇqU€ĞQû ª\n@’¨€ÛºÃpš¬¨PÛ±«7Ô½N\rıR{*qmİ\$\0R”×Ô“ŠÅåqĞÃˆ+U@ŞB¤çOf*†CË¬ºMCä`_ èüò½ËµNêæTâ5Ù¦C×»© ¸à\\WÃe&_XŒ_Øhå—ÂÆBœ3ÀŒÛ%ÜFW£û|™GŞ›'Å[¯Å‚À°ÙÕV Ğ#^\rç¦GR€¾˜€P±İFg¢ûî¯ÀYi û¥Çz\nâ¨Ş+ß^/“¨€‚¼¥½\\•6èßb¼dmh×â@qíÕAhÖ),J­×W–Çcm÷em]ÓeÏkZb0ßåşYñ]ymŠè‡fØe¹B;¹ÓêOÉÀwŸapDWûŒÉÜÓ{›\0˜À-2/bN¬sÖ½Ş¾Ra“Ï®h&qt\n\"ÕiöRmühzÏeø†àÜFS7µĞPPòä–¤âÜ:B§ˆâÕsm¶­Y düŞò7}3?*‚túòéÏlTÚ}˜~€„€ä=cı¬ÖŞÇ	Ú3…;T²LŞ5*	ñ~#µA•¾ƒ‘sx-7÷f5`Ø#\"NÓb÷¯G˜Ÿ‹õ@Üeü[ïø¤Ìs‘˜€¸-§˜M6§£qqš h€e5…\0Ò¢À±ú*àbøISÜÉÜFÎ®9}ıpÓ-øı`{ı±É–kP˜0T<„©Z9ä0<Õš\r­€;!Ãˆgº\r\nKÔ\n•‡\0Á°*½\nb7(À_¸@,îe2\rÀ]–K…+\0Éÿp C\\Ñ¢,0¬^îMĞ§šº©“@Š;X\r•ğ?\$\r‡j’+ö/´¬BöæP ½‰ù¨J{\"aÍ6˜ä‰œ¹|å£\n\0»à\\5“Ğ	156ÿ† .İ[ÂUØ¯\0dè²8Yç:!Ñ²‘=ºÀX.²uCªŠŒö!Sº¸‡o…pÓBİüÛ7¸­Å¯¡Rh­\\h‹E=úy:< :u³ó2µ80“si¦ŸTsBÛ@\$ Íé@Çu	ÈQº¦.ô‚T0M\\/ê€d+Æƒ\n‘¡=Ô°dŒÅëA¢¸¢)\r@@Âh3€–Ù8.eZa|.â7YkĞcÀ˜ñ–'D#‡¨Yò@Xq–=M¡ï44šB AM¤¯dU\"‹Hw4î(>‚¬8¨²ÃC¸?e_`ĞÅX:ÄA9Ã¸™ôp«GĞä‡Gy6½ÃF“Xr‰¡l÷1¡½Ø»B¢Ã…9Rz©õhB„{€™\0ëå^‚Ã-â0©%Dœ5F\"\"àÚÜÊÂ™úiÄ`ËÙnAf¨ \"tDZ\"_àV\$Ÿª!/…D€áš†ğ¿µ‹´ˆÙ¦¡Ì€F,25Éj›Tëá—y\0…N¼x\rçYl¦#‘ÆEq\nÍÈB2œ\nìà6·…Ä4Ó×”!/Â\nóƒ‰Q¸½*®;)bR¸Z0\0ÄCDoŒË48À•´µ‡Ğe‘\nã¦S%\\úPIk‡(0ÁŒu/™‹G²Æ¹ŠŒ¼\\Ë} 4Fp‘Gû_÷G?)gÈotº[vÖ\0°¸?bÀ;ªË`(•ÛŒà¶NS)\nãx=èĞ+@êÜ7ƒjú0—,ğ1Ã…z™“­>0ˆ‰GcğãL…VXôƒ±ÛğÊ%À…Á„Q+øéoÆFõÈéÜ¶Ğ>Q-ãc‘ÚÇl‰¡³¤wàÌz5G‘ê‚@(h‘cÓHõÇr?ˆšNbş@É¨öÇø°îlx3‹U`„rwª©ÔUÃÔôtØ8Ô=Àl#òõlÿä¨‰8¥E\"Œƒ˜™O6\n˜Â1e£`\\hKf—V/Ğ·PaYKçOÌı éàx‘	‰Oj„ór7¥F;´êB»‘ê£íÌ’‡¼>æĞ¦²V\rÄ–Ä|©'Jµz«¼š”#’PBä’Y5\0NC¤^\n~LrR’Ô[ÌŸRÃ¬ñgÀeZ\0x›^»i<Qã/)Ó%@Ê’™fB²HfÊ{%Pà\"\"½ø@ªş)ò’‘“DE(iM2‚S’*ƒyòSÁ\"âñÊeÌ’1Œ«×˜\n4`Ê©>¦Q*¦Üy°n”’¥TäuÔâä”Ñ~%+W²XK‹Œ£Q¡[Ê”àlPYy#DÙ¬D<«FLú³Õ@Á6']Æ‹‡û\rFÄ`±!•%\n0cĞôÀË©%c8WrpGƒ.TœDo¾UL2Ø*é|\$¬:çXt5ÆXYâIˆp#ñ ²^\nê„:‚#Dú@Ö1\r*ÈK7à@D\0¸C’C£xBhÉEnKè,1\"õ*y[á#!ó×™âÙ™©Ê°l_¢/€öxË\0àÉÚ5ĞZÇÿ4\0005JÆh\"2ˆŒ‡%Y…¦a®a1SûO4ˆÊ%niøšPŒàß´qî_Ê½6¤š•~ŠÈI\\¾š‘d‰údÑøŒ®—DÜÈ”€µ3g^ãü@^6Õ„îå_ÀHD·.ksL´Ô@ÂùÉˆæn­I¦ÄÑ~Ä\r“b @¸Ó€•Nt\0séÂ]:uğÎX€b@^°1\0½©¥2?èTÀó6dLNeÉ›+ê\0Ç:©Ğ²l¡ƒz6q=Ìºx“§çN6 ÜO,%@s›0\næ\\)ÒL<òCÊ|·¦P¶b¢˜¼ÎA>I‹…á\"	ŒÜ^K4ü‹gIXi@P…jE©&/1@æfÜ	ÔNáºx0coaß§Áª‰ó,C'Üy#6F@¡Ğ ‰H0Ç{z3t–|cXMJ.*BĞ)ZDQğå\0°ñ“T-v¥Xa*”İ,*Ã<bÁ•Ë#xÑ˜İd€PÆòKG8—Æ y“K	\\#=è)ígÈ‘hŒ&È8])½CÅ\nÃ´ñÀ9¼zˆW\\’gşM 7Šˆ!Ê•¡óÆŠ–¬,Åò9ñ²Š©©\$T\"£,Š¨%.F!Ëš A»-àé”ø¹-àg¨âŠ\0002R>KEˆ'ØUÙ_IĞ÷ì³9³Ë¼¡j(Q°@Ë@ò4/¬7ô˜“'J.â‡RT…\0]KS¹D‡–Ap5¼\rÂH0!ä›Â´e	d@RÒÒà¸´Ê9¢S©;7H‘BÀbxóJèÖ_viÑU`@ˆµÃSAM…¯XËÏGØXiÙÓU*¬Úö€ÊõûÍ'øİ:VòWJv£D¾ÿN'\$ìzh\$d_y§œ“Z]•™­óYÊ°³8Ø”ş¡æ]¨Pìœ*hÔÖ§e;€ºpeû¢\$kæw§ì*7N²DTx_ÔÔ§½Giô&PÿÔ†tÍ†¨bè\\EÆH\$iE\"cr½å0l‰?>ÁñŒ‘C(ŠW@3ÈÁ•22a´“IÁà¹Õ¡{¥B`ÜÚ³iÅ¸Go^6E\r¡ºG˜M¤p1iÙI¼¤Xª\00032ÇKü§Óôİzl&Ö†‰'ILÖ\\Î\"’7¤>¬j(>ãjôFG_âä& 10IÆA31=h q\0ÆFŠ«–„Ä·Šİ_ÂJªŒ„Ô³VÎ–º‡Ü†qÙÕš¢Ù	Âà(/¾dOC_sm§<g˜x\0’°\"ğ\n@EkH\0¡Jˆ­®8€(¬¨¯km[‰‘ì¿ÁS4ğ\nY40›«+L\nŠ¦À“‘ì#BÓ«bçÀ%RÖ–°µ×­‘ÀR:Æ<\$!Û¥r;œ…Ç	%|Ê¨á(€|«H‡\0àğ‘ÁĞŒ°…]ÂcÒ¡=0¯íZá¨\"\"=ÖX•˜)½fëNŸ6V}FÕÚ=[Éà§¢huô-ø±\0t¥åbW~ºõQ•ÕiJŠö—Lñ5×­q#kb İWn««ÍQøTƒ!ëÂeõncSÑ[+Ö´E¯<-‡–a]ÅƒˆìYbÓ\n\nJ~ä|JÉƒ8® ìLpŸ™Áæoñ €Nä©Ü¨…J.ùÅƒSÈ¡2c9Ãj©yŸ-`a\0Äö*ìÖˆ@\0+´ØmgÉÚ6°1¤ÔMe\0ªËQ ‰_„}!Iö’GL€f)ÃXño,“ShxÂ\0000\"hğ+L¥MÔÉ ªÑ˜±ÊZ	j—\0¶ µ/˜\$’¨>u*—Z9”îZå®eõ«+Jœ‰™¸tzÈËûÈşR¨KÔ¯ĞÑâDyŞÙqá0C—-f¢Åm‚¶¹ªBIí|’¹HB‰œsQlÀX°ƒ.İÅöÔ|¸cˆªÀ[–óZhZåÃl˜¨ÛxÂ@'µ ml²KrQ¶26½•]¯Ò·n§d[İöñ©‡dş€‘\"GJ9uòûBƒo“©Zß–Õa¥²n@Áªn°lW|*gX´\nn2åF¬|x`Dk›„uPP!Q\rr‹™`W/¹ŒŸ	1æ[-o,71bUs˜¢©çN¸7²ËÉÛGq¸.\\Q\"CCT\"æ‘à–ÄÒ*?u¨ts¶‰”°Ç]áÙ©Pz[¥[YFÏ¹¢›FD3¤\"–ºÇ]uÛ)wz­:#¶ÍİIiwŠêpÉ›»ñ{¯oÖ0nğ¶Û;Õâ\\éx¸°Ø\0q·måãíª&Ø~Âîî—”7²øÀ¹9[¤HéqdL•Oº2´v|B¯tæŠ\\Æ¤‰Hd¦ëâH‘\" òìN\n\0·©GÅgÎF ¸Fˆ}\"ì­&QEK¾‘{}\ryÇ¾˜r×›t›À„ï†7ÔNuÃ³[Aøgh;S¥.Ò ‚š±Â¥|yùÏ[Õ†_bòÈ¨¬!+RñèZXù@0NééşÁP€Şì%¡jD£Â¯z	şà—[øU\"¶{e’8ôŸ>”EL4JĞ½…0›¡¦è7 €´d·¬ ÀQ^`0`œ•¯]cğ<g@²hy8˜íp.ef\nóÎeh‡ƒaXÚÃømSßßjBÚ˜Q\"‡\rë×ÇK3†=>ÇªAX”[,,\"'<µ›–%¶a€«Ó´Ãµ.\$ñ\0ç%\0ásV¤îËp M\$¼@já×ğ>¤­}VeÄ\$@—Í„#§ªĞ(3:ø`‚UğšYÌ¶uæ¨ûˆÏâÎ@ÄV#E‰G/¸üXD\$ˆhµƒav–¼xS\"]k18a¯Ñ9dJROÓŠs‘`EJ°½§øUo³m{l¹B8¥ˆÁ(\n}ei±büø, ; N”ªÍ‡øQØ\\èÇ¸I5yR¼\$!>\\Ê‰ŒgÂuj*?n°MÓŞ²hİø\r%Á³àU(d€¦Nµd#}špA:¬¨ı•-\\èA»*Ä4€2I€®è\rÖ£»… 0h@\\ÔµÉÀ8ğ3‚rq]òùd8\"ğQ ŒÿîÆ™:cÆàyÇ4	Ïá‘šdaÂ€‡Î 6>UÛAÚÑ:½@˜2‹Ûÿ\$òeh2´ûF»§É™Ná+’ŒŸ\rşÔ€(îAr‚°d*ü\0[®#cjŠû´>!(SğÈéLˆeıTÉÆM	9\0W:™BDıø‚3JŒ¬Õ_@sÇárue‡ø¦ğ»ı¬ +º'B«É}\"B\"üz2î‹rël»xF[èLÙË²Ea9 Êcdb½¾^,ÔUC=/2»×ò¼øì/\$CÆ#Ú÷8¡}DÀÛ×6Ï`^;6B0U7ó·_=	,ª1âj1V[¨.	H9(1ï±Æ±ÒLz¢C¸	Ç\$.AÊfhã–«¾ÍàïDrY	ıHØe~o—r19æ—Ù…\\šß„P’)\"ÃQ¹´,ÑeòöL¾”w0Ï\0§—š–Ï;wìX³Ç¨‰çqo¹ï¾~Ÿ«öçø>9ô>}²òºdc¿\0åÊg¾¶fÎùq–&9—¹-ıJ#¤Š¸ª3^4m/Ì™¯\0\0006À¦n8£·>äˆ´.Ó—é’cph±ËÙù•››º_A@[‰•7«|9\$pMh >‰ŒÁ5°K¥úÃE=hşšAÒtŠ^âV×	©\"	c£B;¤öŞi…ÕQÒ t¬›òé@,\nØ)­óˆsÓ`Ÿ™°°;Ñ4´—‚„Ií£©‘íùèy€ -¤0yeÊ¨—U‚”Bî©v³¥3H™PÇGË5êï’s|·º\rğĞ\$0ãèò•ò1½©l3€é(*oF~PK´ª.ı,'·J/Ó²tğ‹d:š—n§\n©ğj†Y«zê(Æó’ü“w°İ Zì#ZÊ	Io•@1ÆÎ»\$ïò±¦=VWz•	nBøaú›A»µqª@™´I€p	@Ñ5Ó–lH{UºÜoXõ¿fğÓ¿\\zµ×.§š²,-\\Ú—^y n^Å×ÊBq·ş…¤zXã‰¡ƒ\$¨*J72ÕD4.†Õ…!¤M0¶óDëìFŠàóã G¡ÏLˆmØc*mïcI£å5ÉŒ»^—t¿ª’jlŒ7æ›¿S¶Q ¢.i’éÖÔh¨õLĞÚ±B6Ô„h˜&ïJ …l\\‰ğWeªcÎf%kj™Á ¦pÃR=Œäi’@.õ¥(ä2klHUW\"™o¥j½§’p!S5Æè­pL'`\0¤O *¦Q3XÂ“‰ŞlJ\08\n…\r·²¸*€añüë–¼ûr™`<¤&ÚXBhÖ8!xš®&äBht¥\$ÿ‡ş]Énß†éóÉcL€€[Æµ©d¸á<`œ®\0œ€¢Ï‚ŞawæO%;‘õBC»…Q’\rÌ­ÓìŒì€pŠ¤«ØPQ¶Z’¸úZÁAu=N&Ğia\nÑmK6I}Ñ×n	šÅt\nd)í®ĞÈ÷bpÎ€\"ğg'¦0œ7ÃuÈ&@â7å8X NÀxÄáö­ú\$BùßZB/¶M¯gB»i¦ÖÑ§¶\\âmƒmIÌÄ€Êç;5=#&4˜ÌçşPÕ‰½éğqí’A™ä›\\…,q¤cŞŸ\ncâB–‚¾×úw\0BgjD‹@;=0m“k®Ä\rÄ²‹`À¤'5¤•¶k-Œ{¢‰\0¯_›Muîøƒ2“Ò×†§»£Àqø‰¬ğ>)9ÈW\näd+…ÔÔ§ÀG\rıÃn4„‹äOØ:5ö†Ş8»1µ:Îš?¥‡(yGgWK\rİ7­²“—m5.œ‚eŒHÙhJ«Ak#»ÓL¶..›\\Î=ÕñUÙĞ„˜ƒÓ:Ğ>7ºW+^yD‚“œb­üG¡‘OZÍ4ïŠr(|xµÆıPr¸£,y©Ğ8qaÜ©O2µkªn˜Š#p2¾ûÇˆºØ”.¼£c’–U—c”öäëÅ‚jó\$ôí8Ä¬~š7ZR:ğ×†8­9Î¨w(a”L¤%­-,ÔÈì¿Œ#ôfƒ%8şÉ|Şc‡‘¬œÚ×%X‘WÂ\n}6’‘HìÿñæË¤¡#¹&J,'z“MüM…¢‰Œààº‘Ü†² ‘˜®/y6YQ¯‘ì¶ÚºdÓ™dÁŞóÏ:õãô£EƒŒp2gŸgÁ/î,ÒËäÚÕˆ'8ì^;´UWN…ÑÅŞÕ{ÉOCò…Ñ¤ô¢zÉiKX¢’Ú”NŒdG£RCJYõ’‘i²’×y#>zS²MUc£õƒ¨ûÿêRORÔ¾¡0)Ø0Êú]:=Ï™tƒ‘Áëé'\$™sÒrFöÙ67	=\$BÄÓ!qs	1\"ü¬vÆ÷%‘ŒI•l<Êb!Û®6(Cd-Ê^<H`~2¹KìÍzKİÙœ€Ô±­ÙÕy,qAá*º\0}‚İC¨pb€\\ÓSå5İßùÚ'(›áÓí|»Mëğ„ÀWÚÀ5;\$5µT|ºò;kõñÈtîñ@ò‘â;9³)½ò;i.Û;›·í_¥ê×ÌF¶=ñœDä¥M`HŞ“ƒ\0ˆ	 N @°%w‡ªdèPbğ\$H|kÆ[¾ÜdCI!:lÅü,§¨ı<÷”uòt”ô¼NeÏW^¡wè'6•ŒD¿áfıu ¬ihI÷Z:ŸÑ~ı÷Ï£r¾…ÈzÄ3õ+¯uoC·s2ÕbÆua”XğwWK£	HÔ¶27>âWÏÍİyÃ£¬İMëJ£rpT¼”Lğ‰|`f™…:ÊõšA²täŠd|i½³[wüèj„ŠW˜ 7‘¤£au‹© úëe ò•šA5­Q' Ê\0È 3‹Ò¾\$ÂçıŒ\rk)a; óæH=ù™Ö~óIGŠIæ°<ù´•\"ù¬ÉI1'è ™¢Gcm\0P\nïwèü#Í>Œ½ÛxB\"ñÒEm|…ù2Š\$}<3PYXgo£dß¶€<Ôş£¿qE\"`×úÈ4ág«8r£]\nˆ¡—õ:ø›qVbTì£Òm°•…9K&Ò“Ä¤ÃmÔ7)@¨ÀQz›ÃÓ=¢½ßµÅ±íŸH\nÔëö}Oçi}»\rÙ£.¢¹v‹®p¾JW&ßu×550	Ô5ÀîPËIŒÁ\n½Ûí¸³Ææ­l\0O5*=Şú	…P-¢éÊH\0óf×%Ìtãº*¥S:±tÏ› €€?øÈ‚Hâñ÷ºq4ˆĞKÍ”§@€Ô¬»Ü‚.O(±ëü Z¡\$ÏÊÓ]¼‚Åo¿€n‹z«A±!€t85<WñR2[„8ò‚¶ùn5\$Iİµæµ•Z¤Àéó]'}ET\nŸú†Šä.˜í¤&ä7¦ÏVË@¤_ÀD”oÈı&J6°ß4iÃj\$ÈÒEL¢äşu“Üt¢‰Ëä+I¡Ğ¢¢šûØ£~üS±SZTXÒ ¾PYz½Å\"\$VÇ_]ÿM(§ã7òƒºü·ÚÌáÃÀ‡t_´S‰óˆÃê/­ßt…½“Ä‚ü¿âmHä:\0»5à- _Z'#ö¥Á1‡P¿é´,}(Ÿ°~¸\0ì‹ş!Ò–`-şP\neùy (¿Êˆ `9OËú!Á;5‰\n½\$ê{úŸ¯şğìUAü¨7ùá!¿çò€[ı ¸Yı¿ÅFæ¿´ÿƒı¯ğ>è8&€›Şÿ!CLà¦ÿH€¯õ(”\0'Ç2ûìd\r%‚;àkæŠ4ûÀ_OÏ>ş5³öà@DıÒ¼ÏŞ\0VÃA€6' AY¬¢¶ıS°¿‚££rÔ¾´4š+h@bÿãõ­¾´ş‚Oá”M\0Àå˜ÀrÌ›ú@ÿ\rJùÓm0\08ùOò€ìÿ;kÓ ÊëşA(6£|	`8 ß\0ˆ°&¿²EĞVÏå\0VşãñÏï€wk…NÀ°KùÁ—¡xdpÀÒÿsìAL§â«A¾Xëkÿ‘u\0Œïş„Ít ÀÔ¢ò.‰>(N’ÅK'flï¢ªdúAŠ‚â?++ğN“Œ~‚ ÿ²˜úkæ€¾²€ªPR\0èúx¡ØãûèÊ‘ô”‹BK]¦bUÃÑ\\Ì›¸€„d\0S@¿ä«QÀïÍ‰šb™\0\0b„„Ö\0_\\¡@\nN—î äOÎA„PfÁ„€ Œ¶ôÔAj ¨ÂM4<¤9“°Ú+çÀ¿¨Ÿ`S‰‹ ìü”Èw3Tğ¬„7âX»Â†T!\0eïPAIÈb 1!\0€4³åà'¹ @ ! 8\0’Ë/ïˆ º!:K•,ØCASğX‘f®e©ÎMùı.:˜¼:òÆtŸ»¡àÃÌ._ºd„ÿ‹°81v`B\"ä‚Å!.^Ú*åáN.^‡š\n„&\r(Ÿš.Á©§îO0Š«@÷ÙPŠ¹njÒàÚ—#¡¼îäÓå&¹‚rHØ<¨†  ¢!à’3¶Ü(i @ÜAaÁÅ{õ Â¬#ÉS©½†6ğ¨˜¶F@©Ô¦ãY[Oœƒ( .‡¬/„BüËñÇó)L02BØˆÌ-ÁÆ€Øùqp¹‹J<¤.Ğ‘\0\nçï\0ĞÔ/@8C¤4PÀÇ\r	PÂ•°)üğFâå\$q.]¬\"B#‹Å	œ#\\£Â84\$Ãs:.(*Oi>™|#T'`—Bu«a/ˆ€ãCÀÂTØKaêX8Î`p ¸ÚÕÁ\0`Ê\0");
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress("v0œF£©ÌĞ==˜ÎFS	ĞÊ_6MÆ³˜èèr:™E‡CI´Êo:C„”Xc‚\ræØ„J(:=ŸE†¦a28¡xğ¸?Ä'ƒi°SANN‘ùğxs…NBáÌVl0›ŒçS	œËUl(D|Ò„çÊP¦À>šE†ã©¶yHchäÂ-3Eb“å ¸b½ßpEÁpÿ9.Š˜Ì~\n?Kb±iw|È`Ç÷d.¼x8EN¦ã!”Í2™‡3©ˆá\r‡ÑYÌèy6GFmY8o7\n\r³0¤÷\0DbcÓ!¾Q7Ğ¨d8‹Áì~‘¬N)ùEĞ³`ôNsßğ`ÆS)ĞOé—·ç/º<xÆ9o»ÔåµÁì3n«®2»!r¼:;ã+Â9ˆCÈ¨®‰Ã\n<ñ`Èó¯bè\\š?`†4\r#`È<¯BeãB#¤N Üã\r.D`¬«jê4ÿpéar°øã¢º÷>ò8Ó\$Éc ¾1Écœ ¡c êİê{n7ÀÃ¡ƒAğNÊRLi\r1À¾ø!£(æjÂ´®+Âê62ÀXÊ8+Êâàä.\rÍÎôƒÎ!x¼åƒhù'ãâˆ6Sğ\0RïÔôñOÒ\n¼…1(W0…ãœÇ7qœë:NÃE:68n+äÕ´5_(®s \rã”ê‰/m6PÔ@ÃEQàÄ9\n¨V-‹Áó\"¦.:åJÏ8weÎq½|Ø‡³XĞ]µİY XÁeåzWâü 7âûZ1íhQfÙãu£jÑ4Z{p\\AUËJ<õ†káÁ@¼ÉÃà@„}&„ˆL7U°wuYhÔ2¸È@ûu  Pà7ËA†hèÌò°Ş3Ã›êçXEÍ…Zˆ]­lá@MplvÂ)æ ÁÁHW‘‘Ôy>Y-øYŸè/«›ªÁî hC [*‹ûFã­#~†!Ğ`ô\r#0PïCË—f ·¶¡îÃ\\î›¶‡É^Ã%B<\\½fˆŞ±ÅáĞİã&/¦O‚ğL\\jF¨jZ£1«\\:Æ´>N¹¯XaFÃAÀ³²ğÃØÍf…h{\"s\n×64‡ÜøÒ…¼?Ä8Ü^p\"ë°ñÈ¸\\Úe(¸PƒNµìq[g¸Árÿ&Â}PhÊà¡ÀWÙí*Şír_sËP‡hà¼àĞ\nÛËÃomõ¿¥Ãê—Ó#§¡.Á\0@épdW ²\$Òº°QÛ½Tl0† ¾ÃHdHë)š‡ÛÙÀ)PÓÜØHgàıUş„ªBèe\r†t:‡Õ\0)\"Åtô,´œ’ÛÇ[(DøO\nR8!†Æ¬ÖšğÜlAüV…¨4 hà£Sq<à@}ÃëÊgK±]®àè]â=90°'€åâøwA<‚ƒĞÑaÁ~€òWšæƒD|A´††2ÓXÙU2àéyÅŠŠ=¡p)«\0P	˜s€µn…3îr„f\0¢F…·ºvÒÌG®ÁI@é%¤”Ÿ+Àö_I`¶ÌôÅ\r.ƒ N²ºËKI…[”Ê–SJò©¾aUf›Szûƒ«M§ô„%¬·\"Q|9€¨Bc§aÁq\0©8Ÿ#Ò<a„³:z1Ufª·>îZ¹l‰‰¹ÓÀe5#U@iUGÂ‚™©n¨%Ò°s¦„Ë;gxL´pPš?BçŒÊQ\\—b„ÿé¾’Q„=7:¸¯İ¡Qº\r:ƒtì¥:y(Å ×\nÛd)¹ĞÒ\nÁX; ‹ìêCaA¬\ráİñŸP¨GHù!¡ ¢@È9\n\nAl~H úªV\nsªÉÕ«Æ¯ÕbBr£ªö„’­²ßû3ƒ\rP¿%¢Ñ„\r}b/‰Î‘\$“5§PëCä\"wÌB_çÉUÕgAtë¤ô…å¤…é^QÄåUÉÄÖj™Áí Bvhì¡„4‡)¹ã+ª)<–j^<Lóà4U* õBg ëĞæè*nÊ–è-ÿÜõÓ	9O\$´‰Ø·zyM™3„\\9Üè˜.oŠ¶šÌë¸E(iåàœÄÓ7	tßšé-&¢\nj!\rÀyœyàD1gğÒö]«ÜyRÔ7\"ğæ§·ƒˆ~ÀíàÜ)TZ0E9MåYZtXe!İf†@ç{È¬yl	8‡;¦ƒR{„ë8‡Ä®ÁeØ+ULñ'‚F²1ıøæ8PE5-	Ğ_!Ô7…ó [2‰JËÁ;‡HR²éÇ¹€8pç—²İ‡@™£0,Õ®psK0\r¿4”¢\$sJ¾Ã4ÉDZ©ÕI¢™'\$cL”R–MpY&ü½Íiçz3GÍzÒšJ%ÁÌPÜ-„[É/xç³T¾{p¶§z‹CÖvµ¥Ó:ƒV'\\–’KJa¨ÃMƒ&º°£Ó¾\"à²eo^Q+h^âĞiTğ1ªORäl«,5[İ˜\$¹·)¬ôjLÆU`£SË`Z^ğ|€‡r½=Ğ÷nç™»–˜TU	1Hyk›Çt+\0váD¿\r	<œàÆ™ìñjG”­tÆ*3%k›YÜ²T*İ|\"CŠülhE§(È\rÃ8r‡×{Üñ0å²×şÙDÜ_Œ‡.6Ğ¸è;ãü‡„rBjƒO'Ûœ¥¥Ï>\$¤Ô`^6™Ì9‘#¸¨§æ4Xş¥mh8:êûc‹ş0ø×;Ø/Ô‰·¿¹Ø;ä\\'( î„tú'+™òı¯Ì·°^]­±NÑv¹ç#Ç,ëvğ×ÃOÏiÏ–©>·Ş<SïA\\€\\îµü!Ø3*tl`÷u\0p'è7…Pà9·bsœ{Àv®{·ü7ˆ\"{ÛÆrîaÖ(¿^æ¼İE÷úÿë¹gÒÜ/¡øUÄ9g¶î÷/ÈÔ`Ä\nL\n)À†‚(Aúağ\" çØ	Á&„PøÂ@O\nå¸«0†(M&©FJ'Ú! …0Š<ïHëîÂçÆù¥*Ì|ìÆ*çOZím*n/bî/ö®Ôˆ¹.ìâ©o\0ÎÊdnÎ)ùi:RÎëP2êmµ\0/vìOX÷ğøFÊ³ÏˆîŒè®\"ñ®êöî¸÷0õ0ö‚¬©í0bËĞgjğğ\$ñné0}°	î@ø=MÆ‚0nîPŸ/pæotì€÷°¨ğ.ÌÌ½g\0Ğ)o—\n0È÷‰\rF¶é€ b¾i¶Ão}\n°Ì¯…	NQ°'ğxòFaĞJîÎôLõéğĞàÆ\rÀÍ\r€Öö‘0Åñ'ğ¬Éd	oepİ°4DĞÜÊ¦q(~ÀÌ ê\r‚E°ÛprùQVFHœl£‚Kj¦¿äN&­j!ÍH`‚_bh\r1 ºn!ÍÉ­z™°¡ğ¥Í\\«¬\rŠíŠÃ`V_kÚÃ\"\\×‚'Vˆ«\0Ê¾`ACúÀ±Ï…¦VÆ`\r%¢’ÂÅì¦\rñâƒ‚k@NÀ°üBñíš™¯ ·!È\n’\0Z™6°\$d Œ,%à%laíH×\n‹#¢S\$!\$@¶İ2±„I\$r€{!±°J‡2HàZM\\ÉÇhb,‡'||cj~gĞr…`¼Ä¼º\$ºÄÂ+êA1ğœE€ÇÀÙ <ÊL¨Ñ\$âY%-FDªŠd€Lç„³ ª\n@’bVfè¾;2_(ëôLÄĞ¿Â²<%@Úœ,\"êdÄÀN‚erô\0æƒ`Ä¤Z€¾4Å'ld9-ò#`äóÅ–…à¶Öãj6ëÆ£ãv ¶àNÕÍf Ö@Ü†“&’B\$å¶(ğZ&„ßó278I à¿àP\rk\\§—2`¶\rdLb@Eöƒ2`P( B'ã€¶€º0²& ô{Â•“§:®ªdBå1ò^Ø‰*\r\0c<K|İ5sZ¾`ºÀÀO3ê5=@å5ÀC>@ÂW*	=\0N<g¿6s67Sm7u?	{<&LÂ.3~DÄê\rÅš¯x¹í),rîinÅ/ åO\0o{0kÎ]3>m‹”1\0”I@Ô9T34+Ô™@e”GFMCÉ\rE3ËEtm!Û#1ÁD @‚H(‘Ón ÃÆ<g,V`R]@úÂÇÉ3Cr7s~ÅGIói@\0vÂÓ5\rVß'¬ ¤ Î£PÀÔ\râ\$<bĞ%(‡Ddƒ‹PWÄîĞÌbØfO æx\0è} Üâ”lb &‰vj4µLS¼¨Ö´Ô¶5&dsF Mó4ÌÓ\".HËM0ó1uL³\"ÂÂ/J`ò{Çş§€ÊxÇYu*\"U.I53Q­3Qô»J„”g ’5…sàú&jÑŒ’Õu‚Ù­ĞªGQMTmGBƒtl-cù*±ş\rŠ«Z7Ôõó*hs/RUV·ğôªBŸNËˆ¸ÃóãêÔŠài¨Lk÷.©´Ätì é¾©…rYi”Õé-Sµƒ3Í\\šTëOM^­G>‘ZQjÔ‡™\"¤¬i”ÖMsSãS\$Ib	f²âÑuæ¦´™å:êSB|i¢ YÂ¦ƒà8	vÊ#é”Dª4`‡†.€Ë^óHÅM‰_Õ¼ŠuÀ™UÊz`ZJ	eçºİ@Ceíëa‰\"mób„6Ô¯JRÂÖ‘T?Ô£XMZÜÍĞ†ÍòpèÒ¶ªQv¯jÿjV¶{¶¼ÅCœ\rµÕ7‰TÊª úí5{Pö¿]’\rÓ?QàAAÀè‹’Í2ñ¾ “V)Ji£Ü-N99f–l JmÍò;u¨@‚<FşÑ ¾e†j€ÒÄ¦I‰<+CW@ğçÀ¿Z‘lÑ1É<2ÅiFı7`KG˜~L&+NàYtWHé£‘w	Ö•ƒòl€Òs'gÉãq+Lézbiz«ÆÊÅ¢Ğ.ĞŠÇzW²Ç ùzd•W¦Û÷¹(y)vİE4,\0Ô\"d¢¤\$Bã{²!)1U†5bp#Å}m=×È@ˆwÄ	P\0ä\rì¢·‘€`O|ëÆö	œÉüÅõûYôæJÕ‚öE×ÙOu_§\n`F`È}MÂ.#1á‚¬fì*´Õ¡µ§  ¿zàucû€—³ xfÓ8kZR¯s2Ê‚-†’§Z2­+Ê·¯(åsUõcDòÑ·Êì˜İX!àÍuø&-vPĞØ±\0'LïŒX øLÃ¹Œˆo	İô>¸ÕÓ\r@ÙPõ\rxF×üE€ÌÈ­ï%Àãì®ü=5NÖœƒ¸?„7ùNËÃ…©wŠ`ØhX«98 Ìø¯q¬£zãÏd%6Ì‚tÍ/…•˜ä¬ëLúÍl¾Ê,ÜKa•N~ÏÀÛìú,ÿ'íÇ€M\rf9£w˜!x÷x[ˆÏ‘ØG’8;„xA˜ù-IÌ&5\$–D\$ö¼³%…ØxÑ¬Á”ÈÂ´ÀÂŒ]›¤õ‡&o‰-39ÖLù½zü§y6¹;u¹zZ èÑ8ÿ_•Éx\0D?šX7†™«’y±OY.#3Ÿ8 ™Ç€˜e”Q¨=Ø€*˜™GŒwm ³Ú„Y‘ù ÀÚ]YOY¨F¨íšÙ)„z#\$eŠš)†/Œz?£z;™—Ù¬^ÛúFÒZg¤ù• Ì÷¥™§ƒš`^Úe¡­¦º#§“Øñ”©ú?œ¸e£€M£Ú3uÌåƒ0¹>Ê\"?Ÿö@×—Xv•\"ç”Œ¹¬¦*Ô¢\r6v~‡ÃOV~&×¨^gü šÄ‘Ù‡'Î€f6:-Z~¹šO6;zx²;&!Û+{9M³Ù³d¬ \r,9Öí°ä·WÂÆİ­:ê\rúÙœùã@ç‚+¢·]œÌ-[g™Û‡[s¶[iÙiÈq››y›éxé+“|7Í{7Ë|w³}„¢›£E–ûW°€Wk¸|JØ¶å‰xmˆ¸q xwyjŸ»˜#³˜e¼ø(²©‰¸ÀßÃ¾™†ò³ {èßÚ y“ »M»¸´@«æÉ‚“°Y(gÍš-ÿ©º©äí¡š¡ØJ(¥ü@ó…;…yÂ#S¼‡µY„Èp@Ï%èsúoŸ9;°ê¿ôõ¤¹+¯Ú	¥;«ÁúˆZNÙ¯Âº§„š k¼V§·u‰[ñ¼x…|q’¤ON?€ÉÕ	…`uœ¡6|­|X¹¤­—Ø³|Oìx!ë:¨œÏ—Y]–¬¹™c•¬À\r¹hÍ9nÎÁ¬¬ë€Ï8'—ù‚êà Æ\rS.1¿¢USÈ¸…¼X‰É+ËÉz]ÉµÊ¤?œ©ÊÀCË\r×Ë\\º­¹ø\$Ï`ùÌ)UÌ|Ë¤|Ñ¨x'ÕœØÌäÊ<àÌ™eÎ|êÍ³ç—â’Ìé—LïÏİMÎy€(Û§ĞlĞº¤O]{Ñ¾×FD®ÕÙ}¡yu‹ÑÄ’ß,XL\\ÆxÆÈ;U×ÉWt€vŸÄ\\OxWJ9È’×R5·WiMi[‡Kˆ€f(\0æ¾dÄšÒè¿©´\rìMÄáÈÙ7¿;ÈÃÆóÒñçÓ6‰KÊ¦Iª\rÄÜÃxv\r²V3ÕÛßÉ±.ÌàRùÂşÉá|Ÿá¾^2‰^0ß¾\$ QÍä[ã¿D÷áÜ£å>1'^X~t1\"6Lş›+ş¾Aàeá“æŞåI‘ç~Ÿåâ³â³@ßÕ­õpM>Óm<´ÒSKÊç-HÉÀ¼T76ÙSMfg¨=»ÅGPÊ°›PÖ\r¸é>Íö¾¡¥2Sb\$•C[Ø×ï(Ä)Ş%Q#G`uğ°ÇGwp\rkŞKe—zhjÓ“zi(ôèrO«óÄŞÓşØT=·7³òî~ÿ4\"ef›~íd™ôíVÿZ‰š÷U•-ëb'VµJ¹Z7ÛöÂ)T‘£8.<¿RMÿ\$‰ôÛØ'ßbyï\n5øƒİõ_àwñÎ°íUğ’`eiŞ¿J”b©gğuSÍë?Íå`öáì+¾Ïï Mïgè7`ùïí\0¢_Ô-ûŸõ_÷–?õF°\0“õ¸X‚å´’[²¯Jœ8&~D#Áö{P•Øô4Ü—½ù\"›\0ÌÀ€‹ı§ı@Ò“–¥\0F ?* ^ñï¹å¯wëĞ:ğ¾uàÏ3xKÍ^ów“¼¨ß¯‰y[Ô(æ–µ#¦/zr_”g·æ?¾\0?€1wMR&M¿†ù?¬St€T]İ´Gõ:I·à¢÷ˆ)‡©Bïˆ‹ vô§’½1ç<ôtÈâ6½:W{ÀŠôx:=Èî‘ƒŒŞšóø:Â!!\0x›Õ˜£÷q&áè0}z\"]ÄŞo•z¥™ÒjÃw×ßÊÚÁ6¸ÒJ¢PÛ[\\ }ûª`S™\0à¤qHMë/7B’€P°ÂÄ]FTã•8S5±/IÑ\rŒ\n îO¯0aQ\n >Ã2­j…;=Ú¬ÛdA=­p£VL)Xõ\nÂ¦`e\$˜TÆ¦QJÎk´7ª*Oë .‰ˆ…òÄ¡\röµš\$#pİWT>!ªªv|¿¢}ë× .%˜Á,;¨ê›å…­Úf*?«ç„˜ïô„\0¸ÄpD›¸! ¶õ#:MRcúèB/06©­®	7@\0V¹vg€ ØÄhZ\nR\"@®ÈF	‘Êä¼+Êš°EŸIŞ\n8&2ÒbXşPÄ¬€Í¤=h[§¥æ+ÕÊ‰\r:ÄÍFû\0:*åŞ\r}#úˆ!\"¤c;hÅ¦/0ƒ·Ş’òEj®íÁ‚Î]ñZ’ˆ‘—\0Ú@iW_–”®h›;ŒVRb°ÚP%!­ìb]SBšƒ’õUl	åâ³érˆÜ\rÀ-\0 À\"Q=ÀIhÒÍ€´	 F‘ùşLèÎFxR‚Ñ@œ\0*Æj5Œük\0Ï0'	@El€O˜ÚÆH CxÜ@\"G41Ä`Ï¼P(G91«\0„ğ\"f:QÊ¸@¨`'>7ÑÈädÀ¨ˆíÇR41ç>ÌrIHõGt\n€RH	ÀÄbÒ€¶71»ìfãh)Dª„8 B`À†°(V<Q§8c? 2€´€E4j\0œ9¼\r‚Íÿ@‹\0'FúDš¢,Å!ÓÿH=Ò* ˆEí(×ÆÆ?Ñª&xd_H÷Ç¢E²6Ä~£uÈßG\0RXıÀZ~P'U=Çß @èÏÈl+A­\n„h£IiÆ”ü±ŸPG€Z`\$ÈP‡ş‘À¤Ù.Ş;ÀEÀ\0‚}€ §¸Q±¤“äÓ%èÑÉjA’W’Ø¥\$»!ıÉ3r1‘ {Ó‰%i=IfK”!Œe\$àé8Ê0!üh#\\¹HF|Œi8tl\$ƒğÊlÀìläi*(ïG¸ñçL	 ß\$€—xØ.èq\"Wzs{8d`&ğWô©\0&E´¯Íì15jWäb¬öÄ‡ÊŞV©R„³™¿-#{\0ŠXi¤²Äg*÷š7ÒVF3‹`å¦©p@õÅ#7°	å†0€æ[Ò®–¬¸[øÃ©hË–\\áo{ÈáŞT­ÊÒ]²ï—Œ¼Å¦á‘€8l`f@—reh·¥\nÊŞW2Å*@\0€`K(©L•Ì·\0vTƒË\0åc'L¯ŠÀ:„” 0˜¼@L1×T0b¢àhşWÌ|\\É-èïÏDN‡ó€\ns3ÀÚ\"°€¥°`Ç¢ùè‚’2ªå€&¾ˆ\rœU+™^ÌèR‰eS‹n›i0ÙuËšb	J˜’€¹2s¹Ípƒs^n<¸¥òâ™±Fl°aØ\0¸š´\0’mA2›`|ØŸ6	‡¦nrÁ›¨\0DÙ¼Íì7Ë&mÜß§-)¸ÊÚ\\©ÆäİŒ\n=â¤–à;* ‚Şb„è“ˆÄT“‚y7cú|o /–Ôßß:‹ît¡P<ÙÀY: K¸&C´ì'G/Å@ÎàQ *›8çv’/‡À&¼üòWí6p.\0ªu3«ŒñBq:(eOPáp	”é§²üÙã\rœ‹á0(ac>ºNö|£º	“t¹Ó\n6vÀ_„îeİ;yÕÎè6fügQ;yúÎ²[Sø	äëgöÇ°èO’ud¡dH€Hğ= Z\ræ'ÚÊùqC*€) œîgÂÇEêO’€ \" ğ¨!kĞ('€`Ÿ\nkhTùÄ*ösˆÄ5R¤Eöa\n#Ö!1¡œ¿‰×\0¡;ÆÇSÂiÈ¼@(àl¦Á¸I× Ìv\rœnj~ØçŠ63¿ÎˆôI:h°ÔÂƒ\n.‰«2plÄ9Btâ0\$bº†p+”Ç€*‹tJ¢ğÌ¾s†JQ8;4P(ı†Ò§Ñ¶!’€.Ppk@©)6¶5ı”!µ(ø“\n+¦Ø{`=£¸H,É\\Ñ´€4ƒ\"[²Cø»º1“´Œ-èÌluoµä¸4•[™±â…EÊ%‡\"‹ôw] Ù(ã ÊTe¢)êK´A“E={ \n·`;?İôœ-ÀGŠ5I¡í­Ò.%Á¥²şéq%EŸ—ıs¢é©gFˆ¹s	‰¦¸ŠKºGÑøn4i/,­i0·uèx)73ŒSzgŒâÁV[¢¯hãDp'ÑL<TM¤äjP*oœâ‰´‘\nHÎÚÅ\n 4¨M-W÷NÊA/î†@¤8mH¢‚Rp€tp„V”=h*0ºÁ	¥1;\0uG‘ÊT6’@s™\0)ô6À–Æ£T\\…(\"èÅU,ò•C:‹¥5iÉKšl«ì‚Û§¡E*Œ\"êrà¦ÔÎ.@jRâJ–QîŒÕ/¨½L@ÓSZ”‘¥Põ)(jjJ¨««ªİL*ª¯Ä\0§ªÛ\r¢-ˆñQ*„QÚœgª9é~P@…ÕÔH³‘¬\n-e»\0êQw%^ ETø< 2Hş@Ş´îe¥\0ğ e#;öÖI‚T’l“¤İ+A+C*’YŒ¢ªh/øD\\ğ£!é¬š8“Â»3AĞ™ÄĞEğÍE¦/}0tµJ|™Àİ1Qm«Øn%(¬p´ë!\nÈÑÂ±UË)\rsEXú‚’5u%B- ´Àw]¡*•»E¢)<+¾¦qyV¸@°mFH òÔšBN#ı]ÃYQ1¸Ö:¯ìV#ù\$“æ şô<&ˆX„€¡úÿ…x« tš@]GğíÔ¶¥j)-@—qĞˆL\nc÷I°Y?qC´\ràv(@ØËX\0Ov£<¬Rå3X©µ¬Q¾Jä–Éü9Ö9ÈlxCuÄ«d±± vT²Zkl\rÓJíÀ\\o›&?”o6EĞq °³ªÉĞ\r–÷«'3úËÉª˜J´6ë'Y@È6ÉFZ50‡VÍT²yŠ¬˜C`\0äİVS!ıš‹&Û6”6ÉÑ³rD§f`ê›¨Jvqz„¬àF¿ ÂÂò´@è¸İµ…šÒ…Z.\$kXkJÚ\\ª\"Ë\"àÖi°ê«:ÓEÿµÎ\roXÁ\0>P–¥Pğmi]\0ªöö“µaV¨¸=¿ªÈI6¨´°ÎÓjK3ÚòÔZµQ¦m‰EÄèğbÓ0:Ÿ32ºV4N6³´à‘!÷lë^Ú¦Ù@hµhUĞ>:ú	˜ĞE›>jäèĞú0g´\\|¡Shâ7yÂŞ„\$•†,5aÄ—7&¡ë°:[WX4ÊØqÖ ‹ìJ¹Æä×‚Şc8!°H¸àØVD§Ä­+íDŠ:‘¡¥°9,DUa!±X\$‘ÕĞ¯ÀÚ‹GÁÜŒŠBŠt9-+oÛt”L÷£}Ä­õqK‹‘x6&¯¯%x”ÏtR¿–éğ\"ÕÏ€èR‚IWA`c÷°È}l6€Â~Ä*¸0vkıp«Ü6Àë›8z+¡qúXöäw*·EƒªIN›¶ªå¶ê*qPKFO\0İ,(Ğ€|œ•‘”°k *YF5”åå;“<6´@ØQU—\"×ğ\rbØOAXÃvè÷v¯)H®ôo`STÈpbj1+Å‹¢e²Á™ Ê€Qx8@¡‡ĞÈç5\\Q¦,Œ‡¸Ä‰NëİŞ˜b#Y½H¥¯p1›ÖÊøkB¨8NüoûX3,#UÚ©å'Ä\"†é”€ÂeeH#z›­q^rG[¸—:¿\r¸m‹ngòÜÌ·5½¥V]«ñ-(İWğ¿0âëÑ~kh\\˜„ZŠå`ïél°êÄÜk ‚oÊjõWĞ!€.¯hFŠÔå[tÖA‡wê¿e¥Mà««¡3!¬µÍæ°nK_SF˜j©¿ş-S‚[rœÌ€wä´ø0^Áh„fü-´­ı°?‚›ıXø5—/±©Š€ëëIY ÅV7²a€d ‡8°bq·µbƒn\n1YRÇvT±õ•,ƒ+!Øıü¶NÀT£î2IÃß·ÄÄ÷„ÇòØ‡õ©K`K\"ğ½ô£÷O)\nY­Ú4!}K¢^²êÂàD@á…÷naˆ\$@¦ ƒÆ\$AŠ”jÉËÇø\\‹D[=Ë	bHpùSOAG—ho!F@l„UËİ`Xn\$\\˜Íˆ_†¢Ë˜`¶âHBÅÕ]ª2ü«¢\"z0i1‹\\”ŞÇÂÔwù.…fyŞ»K)£îíÂ‡¸ pÀ0ä¸XÂS>1	*,]’à\r\"ÿ¹<cQ±ñ\$t‹„qœ.‹ü	<ğ¬ñ™+t,©]Lò!È{€güãX¤¶\$¤6v…˜ùÇ ¡š£%GÜHõ–ÄØœÈE ÒXÃÈ*Á‚0ÛŠ)q¡nCØ)I›ûà\"µåÚÅŞíˆ³¬`„KFçÁ’@ïd»5Œê»AÈÉp€{“\\äÓÀpÉ¾Nòrì'£S(+5®ĞŠ+ \"´Ä€£U0ÆiËÜ›úæ!nMˆùbrKÀğä6Ãº¡r–ì¥â¬|aüÊÀˆ@Æx|®²kaÍ9WR4\"?5Ê¬pıÛ“•ñk„rÄ˜«¸¨ıß’ğæ¼7Â—Hp†‹5YpW®¼ØG#ÏrÊ¶AWD+`¬ä=Ê\"ø}Ï@HÑ\\p°“Ğ€©ß‹Ì)C3Í!sO:)Ùè_F/\r4éÀç<A¦…\nn /Tæ3f7P1«6ÓÄÙıOYĞ»Ï²‡¢óqì×;ìØÀæaıXtS<ã¼9Ânws²x@1ÎxsÑ?¬ï3Å@¹…×54„®oÜÈƒ0»ŞĞïpR\0Øà¦„†Îù·óâyqßÕL&S^:ÙÒQğ>\\4OInƒZ“nçòvà3¸3ô+P¨…L(÷Ä”ğ…Àà.x \$àÂ«Cå‡éCnªAkçc:LÙ6¨ÍÂr³w›ÓÌh°½ÙÈnr³Zêã=è»=jÑ’˜³‡6}MŸGıu~3ùšÄbg4Åùôs6sóQé±#:¡3g~v3¼ó€¿<¡+Ï<ô³Òa}Ï§=Îe8£'n)ÓcCÇzÑ‰4L=hıŒ{i´±Jç^~çƒÓwg‹Dà»jLÓéÏ^šœÒÁ=6Î§NÓ”êÅÁ¢\\éÛDóÆÑN”†êEı?hÃ:SÂ*>„ô+¡uúhhÒ…´W›E1j†x²Ÿôí´ŠtÖ'Îtà[ îwS²¸ê·9š¯Tö®[«,ÕjÒv“òÕît£¬A#T™¸Ôæ‚9ìèj‹K-õÒŞ ³¿¨Yèi‹Qe?®£4ÓÓÁë_WzßÎéó‹@JkWYêhÎÖpu®­çj|z4×˜õ	èi˜ğm¢	àO5à\0>ç|ß9É×–«µè½ öëgVyÒÔu´»¨=}gs_ºãÔV¹sÕ®{çk¤@r×^—õÚ(İwÏ…øH'°İaì=i»ÖNÅ4µ¨‹ë_{Ï6ÇtÏ¨ÜöÏ—e [Ğh-¢“Ul?Jîƒ0O\0^ÛHlõ\0.±„Z‚’œ¼âÚxu€æğ\"<	 /7ÁŠ¨Ú û‹ïi:Ò\nÇ ¡´à;íÇ!À3ÚÈÀ_0`\0H`€Â2\0€ŒHò#h€[¶P<í¦†‘×¢g¶Ü§m@~ï(şÕ\0ßµkâY»vÚæâ#>¥ù„\nz\n˜@ÌQñ\n(àGİ\nöüà'kóš¦èº5“n”5Û¨Ø@_`Ğ‡_l€1Üşèwp¿Pî›w›ªŞ\0…cµĞoEl{Åİ¾é7“»¼¶o0ĞÛÂôIbÏên‹zÛÊŞÎï·›¼ ‹ç{Ç8øw=ëîŸ| /yê3aíß¼#xqŸÛØò¿»@ï÷kaà!ÿ\08dîmˆäR[wvÇ‹RGp8øŸ vñ\$Zü½¸mÈûtÜŞİÀ¥·½íôºÜû·Ç½Ôîûu€oİp÷`2ğãm|;#x»mñnç~;ËáVëE£ÂíØğÄü3OŸ\r¸,~o¿w[òáNêø}ºş ›clyá¾ñ¸OÄÍŞñ;…œ?á~ì€^j\"ñWz¼:ß'xWÂŞ.ñ	Áu’(¸ÅÃäq—‹<gâçv¿hWq¿‰\\;ßŸ8¡Ã)M\\³š5vÚ·x=h¦iºb-ÀŞ|bÎğàpyDĞ•Hh\rceà˜y7·p®îxşÜG€@D=ğ Öù§1Œÿ!4Ra\r¥9”!\0'ÊYŒŸ¥@>iS>æ€Ö¦Ÿo°óoòÎfsO 9 .íşéâ\"ĞF‚…ló20åğE!Qšá¦çËD9dÑBW4ƒ›\0û‚y`RoF>FÄa„‰0‘ùÊƒó0	À2ç<‚IÏP'\\ñçÈIÌ\0\$Ÿœ\n R aUĞ.‚sĞ„«æ\"ùš1Ğ†…eºYç ¢„Zêqœñ1 |Ç÷#¯G!±P’P\0|‰HÇFnp>Wü:¢`YP%”ÄâŸ\nÈa8‰ÃP>‘ÁÁè–™`]‘‹4œ`<Ğr\0ùÃ›ç¨û¡–z–4Ù‡¥Ë8€ùÎĞ4ó`mãh:¢Îª¬HDªãÀjÏ+p>*ä‹ÃÄê8äŸÕ 08—A¸È:€À»Ñ´]wêÃºùz>9\n+¯ççÍÀñØ:—°ii“PoG0°Öö1ş¬)ìŠZ°Ú–èn¤È’ì×eRÖ–Üí‡g£M¢à”ÀŒgs‰LC½rç8Ğ€!°†À‚Œ3R)Îú0³0Œôs¨IéJˆVPpK\n|9e[á•ÖÇË‘²’D0¡Õ àz4Ï‘ªo¥Ôéáèà´,N8nåØsµ#{è“·z3ğ>¸BSı\";Àe5VD0±¬š[\$7z0¬ºøÃËã=8ş	T 3÷»¨Q÷'R’±—’ØnÈ¼LĞyÅ‹ìö'£\0oäÛ,»‰\0:[}(’¢ƒ|×ú‡X†>xvqWá“?tBÒE1wG;ó!®İ‹5Î€|Ç0¯»JI@¯¨#¢ˆŞuÅ†Iáø\\p8Û!'‚]ß®šl-€låSßBØğ,Ó—·»ò]èñ¬1‡Ô•HöÿNÂ8%%¤	Å/;FGSôòôhé\\Ù„ÓcÔt²¡á2|ùWÚ\$tøÎ<ËhİOŠ¬+#¦BêaN1ùç{ØĞyÊwòš°2\\Z&)½d°b',XxmÃ~‚Hƒç@:d	>=-Ÿ¦lK¯ŒÜşJí€\0ŸÌÌó@€rÏ¥²@\"Œ(AÁñïªıZ¼7Åh>¥÷­½\\Íæú¨#>¬õø\0­ƒXrã—YøïYxÅæq=:šÔ¹ó\rlŠoæm‡gbööÀ¿À˜ï„D_àTx·C³ß0.Šôy€†R]Ú_İëÇZñÇ»WöIàëGÔï	MÉª(®É|@\0SO¬ÈsŞ {î£”ˆø@k}äFXSÛb8àå=¾È_ŠÔ”¹l²\0å=ÈgÁÊ{ HÿÉyGüÕáÛ sœ_şJ\$hkúF¼q„àŸ÷¢Éd4Ï‰ø»æÖ'ø½>vÏ¬ !_7ùVq­Ó@1zë¤uSe…õjKdyuëÛÂS©.‚2Œ\"¯{úÌKşØË?˜s·ä¬Ë¦h’ßRíd‚é`:y—ÙåûGÚ¾\nQéı·Ùßow’„'öïhS—î>ñ©¶‰LÖX}ğˆe·§¸G¾â­@9ıãíŸˆüWİ|íøÏ¹û@•_ˆ÷uZ=©‡,¸åÌ!}¥ŞÂ\0äI@ˆä#·¶\"±'ãY`¿Ò\\?Ìßpó·ê,Gú¯µı×œ_®±'åGúÿ²Ğ	ŸT†‚#ûoŸÍH\rş‡\"Êëúoã}§ò?¬şOé¼”7ç|'ÎÁ´=8³M±ñQ”yôaÈH€?±…ß®‡ ³ÿ\0ÿ±öbUdè67şÁ¾I Oöäïû\"-¤2_ÿ0\rõ?øÿ«–ÿ hO×¿¶t\0\0002°~şÂ° 4²¢ÌK,“Öoh¼Î	Pc£ƒ·z`@ÚÀ\"îœâŒàÇH; ,=Ì 'S‚.bËÇS„¾øàCc—ƒêìšŒ¡R,~ƒñXŠ@ '…œ8Z0„&í(np<pÈ£ğ32(ü«.@R3ºĞ@^\r¸+Ğ@ , öò\$	ÏŸ¸„E’ƒèt«B,²¯¤âª€Ê°h\r£><6]#ø¥ƒ;‚íC÷.Ò€¢ËĞ8»Pğ3ş°;@æªL,+>½‰p(#Ğ-†f1Äz°Áª,8»ß ÆÆPà:9ÀŒï·RğÛ³¯ƒ¹†)e\0Ú¢R²°!µ\nr{Æîe™ÒøÎGA@*ÛÊnDöŠ6Á»ğòóíN¸\rR™Ôø8QK²0»àé¢½®À>PN°Ü©IQ=r<á;&À°fÁNGJ;ğUAõÜ¦×A–P€&şõØã`©ÁüÀ€);‰ø!Ğs\0î£Áp†p\r‹¶à‹¾n(ø•@…%&	S²dY«ŞìïuCÚ,¥º8O˜#ÏÁ„óòoªšêRè¬v,€¯#è¯|7Ù\"Cp‰ƒ¡Bô`ìj¦X3«~ïŠ„RĞ@¤ÂvÂø¨£À9B#˜¹ @\nğ0—>Tíõá‘À-€5„ˆ/¡=è€ ¾‚İE¯—Ç\nç“Âˆd\"!‚;ŞÄp*n¬¼Z²\08/ŒjX°\r¨>F	PÏe>À•OŸ¢LÄ¯¡¬O0³\0Ù)kÀÂºã¦ƒ[	ÀÈÏ³Âêœ'L€Ù	Ãåñƒ‚é›1 1\0ø¡Cë 1Tº`©„¾ìRÊz¼Äš£îÒp®¢°ÁÜ¶ìÀ< .£>î¨5İ\0ä»¹>Ÿ BnËŠ<\"he•>ĞººÃ®£çsõ!ºHı{Ü‘!\rĞ\rÀ\"¬ä| ‰>Rš1dàö÷\"U@ÈD6ĞåÁ¢3£çğŸ>o\r³çá¿vL:K„2å+Æ0ì¾€>°È\0äí ®‚·Bé{!r*Hî¹§’y;®`8\0ÈËØ¯ô½dş³ûé\rÃ0ÿÍÀ2AşÀ£î¼?°õ+û\0ÛÃ…\0A¯ƒwSû‡lÁ²¿°\r[Ô¡ª6ôcoƒ=¶ü¼ˆ0§z/J+ê†ŒøW[·¬~C0‹ùeü30HQP÷DPY“}‡4#YDö…ºp)	º|û@¥&ã-À†/F˜	á‰T˜	­«„¦aH5‘#ƒëH.ƒA>Ğğ0;.¬­şY“Ä¡	Ã*ûD2 =3·	pBnuDw\n€!ÄzûCQ \0ØÌHQ4DË*ñ7\0‡JÄñ%Ä±puD (ôO=!°>®u,7»ù1†ãTM+—3ù1:\"P¸Ä÷”RQ?¿“üP°Š¼+ù11= ŒM\$ZÄ×lT7Å,Nq%E!ÌS±2Å&öŒU*>GDS&¼ªéó›ozh8881\\:ÑØZ0hŠÁÈT •C+#Ê±A%¤¤D!\0ØïòñÁXDAÀ3\0•!\\í#h¼ªí9bÏ‚T€!dª—ˆÏÄY‘j2ôSëÈÅÊ\nA+Í½¤šHÈwD`íŠ(AB*÷ª+%ÕEï¬X.Ë Bé#ºƒÈ¿Œ¸&ÙÄXe„EoŸ\"×è|©r¼ª8ÄW€2‘@8Daï|ƒ‚ø÷‘Š”Núhô¥ÊJ8[¬Û³öÂö®WzØ{Z\"L\0¶\0€È†8ØxŒÛ¶X@”À E£Íïë‘h;¿af˜¼1Âş;nÃÎhZ3¨E™Â«†0|¼ ì˜‘­öAà’£tB,~ôŠW£8^»Ç ×ƒ‚õ<2/	º8¢+´¨Û”‚O+ %P#Î®\n?»ß‰?½şeË”ÁO\\]Ò7(#û©DÛ¾(!c) NöˆºÑMF”E£#DXîgï)¾0Aª\0€:ÜrBÆ×``  ÚèQ’³H>!\rB‡¨\0€‰V%ce¡HFH×ñ¤m2€B¨2IêµÄÙë`#ú˜ØD>¬ø³n\n:LŒıÉ9CñÊ˜0ãë\0“x(Ş©(\nş€¦ºLÀ\"GŠ\n@éø`[Ãó€Š˜\ni'\0œğ)ˆù€‚¼y)&¤Ÿ(p\0€Nˆ	À\"€®N:8±é.\r!'4|×œ~¬ç§ÜÙÊ€ê´·\"…cúÇDlt‘Ó¨Ÿ0c«Å5kQQ×¨+‹ZGkê!F€„cÍ4ˆÓRx@ƒ&>z=¹\$(?óŸïÂ(\nì€¨>à	ëÒµ‚ÔéCqÛŒ¼Œt-}ÇG,tòGW ’xqÛHf«b\0\0zÕìƒÁT9zwĞ…¢Dmn'îccb H\0z…‰ñ3¹!¼€ÑÔÅ HóÚHz×€Iy\",ƒ- \0Û\"<†2ˆî Ğ'’#H`†d-µ#cljÄ`³­i(º_¤ÈdgÈíÇ‚*Ój\rª\0ò>Â 6¶ºà6É2ókjã·<ÚCq‘Ğ9àÄ†ÉI\r\$C’AI\$x\r’H¶È7Ê8 Ü€Z²pZrR£òà‚_²U\0äl\r‚®IRXi\0<²äÄÌr…~xÃS¬é%™Ò^“%j@^ÆôT3…3É€GH±z€ñ&\$˜(…Éq\0Œšf&8+Å\rÉ—%ì–2hCüx™¥ÕI½šlbÉ€’(hòSƒY&àBªÀŒ•’`”f•òxÉv n.L+ş›/\"=I 0«d¼\$4¨7rŒæ¼A£„õ(4 2gJ(D˜á=F„¡â´Èå(«‚û-'Ä òXGô29Z=˜’Ê,ÊÀr`);x\"Éä8;²–>û&…¡„ó',—@¢¤2Ãpl²—ä:0ÃlI¡¨\rrœJDˆÀúÊ»°±’hAÈz22pÎ`O2hˆ±8H‚´Ä„wt˜BF²Œg`7ÉÂä¥2{‘,Kl£ğ›Œß°%C%úomû€¾àÀ’´ƒ‘+X£íûÊ41ò¹¸\nÈ2pŠÒ	ZB!ò=VÆÜ¨èÈ€Ø+H6²ÃÊ*èª\0ækÕà—%<² øK',3ØrÄI ;¥ 8\0Z°+EÜ­Ò`Ğˆ²½Êã+l¯ÈÏËW+¨YÒµ-t­fËb¡Qò·Ë_-Ó€Ş…§+„· 95ŠLjJ.GÊ©,\\·òÔ….\$¯2ØJè\\„- À1ÿ-c¨²‚Ë‡.l·fŒxBqK°,d·èË€â8äA¹Ko-ô¸²îÃæ²°3KÆ¯r¾¸/|¬ÊËå/\\¸r¾Ëñ,¡HÏ¤¸!ğYÀ1¹0¤@­.Â„&|˜ÿËâ+ÀéJ\0ç0P3JÍ-ZQ³	»\r&„‘Ãá\nÒLÑ*ÀËŞj‘Ä‰|—ÒåËæ#Ô¾ª\"Ëº“AÊï/ä¹òû8)1#ï7\$\"È6\n>\nô¢Ã7L1à‹òh9Î\0B€Z»d˜#©b:\0+A¹¾©22ÁÓ'Ì•\nt ’ÄÌœÉOÄç2lÊ³.L¢”HC\0™é2 ó+L¢\\¼™r´Kk+¼¹³Ë³.êŒ’êº;(DÆ€¢Êù1s€ÕÌòdÏs9Ìú•¼ P4ÊìŒœÏó@‹.ìÄáAäÅnhJß1²3óKõ0„Ñ3J\$\0ìÒ2íLk3ãˆáQÍ;3”Ñn\0\0Ä,ÔsIÍ@Œûu/VAÅ1œµ³UMâ<ÆLe4DÖ2şÍV¢% ¨Ap\nÈ¬2ÉÍ35ØòĞA-´“TÍu5š3òÛ¹1+fL~ä\nô°ƒ	„õ->£° ÖÒ¡M—4XLóS†õdÙ²ÖÍŸ*\\Ú@Í¨€˜YÓk¤Š¤ÛSDM»5 Xf° ¬ªD³s¤äÀUs%	«Ì±p+Ké6ÄŞ/ÍÔüİ’ñ8XäŞ‚=K»6pHà†’ñ%è3ƒÍ«7lØI£K0ú¤ÉLíÎD»³uƒêõ`±½P\rüÙSOÍ™&(;³L@Œ£ÏˆN>Sü¸2€Ë8(ü³Ò`J®E°€r­F	2üåSE‰”M’†MÈá\$qÎE¶Ÿ\$ÔÃ£/I\$\\“ãáIDå\" †\nä±º½w.tÏS	€æ„Ñ’Pğò#\nWÆõ-\0CÒµÎ:jœRíÍ^Süí„Å8;dì`”£ò5ÔªaÊ–ÇôE¹+(XröMë;Œì3±;´•ó¼B,Œ˜*1&î“ÃÎË2XåS¼ˆõ)<Í ­L9;òRSN¼Ş£ÁgIs+ÜëÓ°Kƒ<¬ñsµLY-Z’:A<áÓÂOO*œõ2vÏW7¹¹+|ô €Ë»<TÖóÕ9 h’“²Ïy\$<ôÎ#Ï;ÔöÓá›v±\$öOé\0­ ¬,Hkòü-äõàÏš\rÜú²ŸÏ£;„”¹O•>ìù“·Ë7>´§3@O{.4öpO½?TübÃÏË.ë.~O…4ôÏSïÏì>1SS€Ï*4¶PÈ£ó>ü·ÁÏï3í\0ÒWÏ>´ô2å><ëóßP?4€Û@Œôt\nNÀÇùAŒxpÜû%=P@ÅÒCÏ@…RÇËŸ?x°ó\n˜´Œ0NòwĞO?ÕTJC@õÎ#„	.dş“·MêÌt¯&=¹\\ä4èÄAÈå:L“¥€í\$ÜéÒNƒ­:Œ’\rÎÉI'Å²–AÕráŒ;\r /€ñCôÈåBåÓ®Œi>LèŠ7:9¡¡€ö|©C\$ÊË)Ñù¡­¹z@´tlÇ:>€úCê\n²Bi0GÚ,\0±FD%p)o\0Š°©ƒ\n>ˆú`)QZIéKGÚ%M\0#\0DĞ ¦Q.Hà'\$ÍE\n «\$Ü%4IÑD°3o¢:LÀ\$£Îm ±ƒ0¨	ÔB£\\(«¨8üÃé€š…hÌ«D½ÔCÑsDX4TK€¦Œ{ö£xì`\n€,…¼\nE£ê:Òp\nÀ'€–> ê¡o\0¬“ıtIÆ` -\0‹D½À/€®KPú`/¤êøH×\$\n=‰€†>´U÷FP0£ëÈUG}4B\$?EıÛÑ%”T€WD} *©H0ûT„\0tõ´†‚ÂØ\"!o\0Eâ7±ïR.“€útfRFu!ÔDğ\nï\0‡F-4V€QHÅ%4„Ñ0uN\0ŸDõQRuEà	)ÍI\n &Q“m€)Çš’m ‰#\\˜“ÒD½À(\$Ì“x4€€WFM&ÔœR5Hå%qåÒ[F…+ÈùÑIF \nT«R3DºLÁo°Œ¼y4TQ/E´[Ñ<­t^ÒËFü )Qˆå+4°Q—IÕ#´½‰IF'TiÑªXÿÀ!Ñ±FĞ*ÔnRÊ>ª5ÔpÑÇKm+ÔsÇÜ û£ïÒáIåôŸREı+Ô©¤ÙM\0ûÀ(R°?+HÒ€¥Jí\"TÃDˆª\$˜Œà	4wQà}Tz\0‹Gµ8|ÒxçÍ©R¢õ6ÀRæ	4XR6\nµ4yÑmNôãQ÷NMà&RÓH&É2Q/ª7#èÒ›Ü{©'ÒÒ,|”’ÇÎ\n°	.·\0˜>Ô{Áo#1D…;ÀÂĞ?Uô‘Ò•Jò9€*€š¸j”ı€¯F’N¨ÒÑ‰Jõ #Ñ~%-?CôÇßL¨3Õ@EP´{`>QÆÈ”µÔ%Oí)4ïR%IŠ@Ôô%,\"ÕÓùIÕ<‘ëÓÏå\$Ô‰TP>Ğ\nµ\0QP5DÿÓkOFÕTYµ<ÁoıQ…=T‰\0¬“x	5©D¥,Â0?ÍiÎ?xş  ºmE}>Î|¤ÀŒÀ[Èç\0€•&RL€ú”H«S9•G›I›§1ä€–…M4V­HşoT-S)QãGÇF [ÃùTQRjN±ã#x]N(ÌU8\nuU\n?5,TmÔ?Ğÿ’Ü?€ş@ÂU\nµu-€‹Rê9ãğU/S \nU3­IEStQYJu.µQÒõF´o\$&ŒÀûi	ÜKPCó6Â>å5µG\0uR€ÿu)U'R¨0”Ğ€¡DuIU…J@	Ô÷:åV8*ÕRf%&µ\\¿RÈõMU9RøüfUAU[T°UQSe[¤µ\0KeZUa‚­UhúµmS<»®À,Rès¨`&Tj@ˆçGÇ!\\xô^£0>¨ş\0&ÀpÿÎ‚Q¿Q)T˜UåPs®@%\0ŸW€	`\$Ôò(1éQ?Õ\$CïQp\nµOÔJ¹ñX#ƒıV7Xu;Ö!YBî°ÓSåcşÑ+V£ÎÃñ#MUÕW•HÍUıR²Ç…U-+ôğVmY}\\õ€ÈOK¥Mƒì\$ÉSíeToV„ŒÍHTùÑ!!<{´RÓÍZA5œRÁ!=3U™¤(’{@*Ratz\0)QƒP5HØÒ“ÎÕ°­N5+•–ÏP[Ôí9óV%\"µ²ÖØ\n°ıñäG•SL•µÔò9”ùÇÌë•lÀ£ˆ‘\rVˆØ¤Í[•ouºUIY…R_T©Y­p5OÖ§\\q`«U×[ÕBu'Uw\\mRUÇÔ­\\Es5ÓK\\úƒïVÉ\\ÅS•{×AZ%Oõ¼\$Ü¥FµÔ¬>ı5E×WVm`õ€Wd]& \$ÑÎŒÅ•ÛÓ!R¥Z}Ô…]}v5À€§ZUgôÔQ^y` Ñ!^=F•áRÁ^¥vëUÅKex@+¤Şr5À#×@?=”uÎ“s •¤×¥YšNµsS!^c5ğ\$.“u`µÜ\0«XE~1ï9Ò…JóUZ¢@²#1_[­4JÒ2à\nà\$VI²4n»\0˜?ò4aªRç!U~)&ÓòB>t’RßIÕ0ÀÔ_EkTUSØœ|µıUk_Â8€&€›E°ü(â€˜?â@õ××JÒ5Ò½JU†BQT}HVÖ‘j€¤Qx\neÖVsU=ƒÔıV‘N¢4Õ²Ø—\\xèÒÖïR34İG¿D\":	KQş>˜[Õ\rÕY_å#!ª#][j<6Ø®X	¨ìÍc‰•Ø#KL}>`'\0¨5”XÑcU[\0õ(ÔÙÑWt|tô€R]pÀ/£]H2I€QO‹­1âS©Qj•Z€¨¸´Hº´m¨ÌÙ)dµ^SXCY\rtu@Jëpüµ%ÓÿM¸ø€¨óµ“Ö?ÙUQ°\nö=Råar:Ô¿Eí‘À¥-G€\0\$ÑÇd½“ö]Òmeh*ÃìQ‰Wt„öc€¡`•˜AªY=S\r®¯«	m-´‚¤=MwÖH£]Jå\"ä´Ä õş­fõ\"´{#9Teœ‰ÙÍMÔc¹ñNêI£òÙßD¥œõÙÜçUœ6ÙñgÑ2Ù×İ¶eƒa­L´€Q&&uTåX51Y >£óûSıÖŠQ#êIµ¥Õj\0ûœ£ÅW PÑş?ub5FUóLn¶)V5R¢@ãë\$!%o¶ÔPúÉ'€‰EµUÁÔP-†¶š¤Bp\nµF\$ŸS4…t±UF|{–qÖÈ“0û•ÎUmjsÎÃü€²øı\$´Ú›j…cëÚå¦Ö«€¿aZI5X€ƒj26®¤&>vÑ\n\r)2Õ_kîG¶®TJÚÁeQ-cîZñVM­Ö½£z>õ]•a¹c£Ëcìß`t„”HÚÑjİ6¹£+kŠM–\0Œ>Œ„€##3l=à'´¥^6Í\0¨Ã¨v¦Z9Se£€\"×ÊêbÎ¡ÔB>)•/TÁ=ö9\0ù`Pà\$\0¿]í/0Úª•«äµ½k-š6İÛ{küÖá[F\r|´SÑ¿J¥õMQ¿D=õ/ÈWX¢öœV—a¬'¶¹éa¨to€©lå†¶ĞXj}C@\"ÀKPÛÎÖÚom’3\0#HV”µ…v÷Ñ~“{µÖ?gx	n|[Ø?U¶äµ[rê½h¶ŞG¸`õ3#Gk%L£ê\0¿I`CùDŞê¸	 \"\0ˆŒÅ§¶°#cN«6ßÚ¹fÂÔzÛêº;Ñ¤ÃeeF–7Ù/N\r:ôâQñGÕ9	\$ÔóIøÕ¼ºß]£®TİØWGs«ÔdWõMÚIãèÑÙf’BcêÛ¤êõÂ÷!#cnu&(ŞSã_Õw£ùSfë&TšZ:…0CóSÙLN`Ü³Yj=·¶>Å²ÃñZ!=€rV]gû	Ó£rµ ËXlŒÉ-.¹UÄ'uJuJ\0ƒs­J¶'W%·¶­\\>?òBöëV­j4µÏJ}I/-ÒrRLºSè3\0,RgqÓ­ôÇTf>İ1Õï\0¥_•”Ç\\V8õ¡ZÛt…Ácè€†ú<^\\ùll´j\0¾˜şT¥]CİÔw×Î“zI¶ÙZwN…¶¶pVW…jv»Y¶>2Ó	o\$|U‡WÃL%{toX3_õ¶òR‰J5~6\"×ãZl}´`Ôkc­ÑîÛeR=^UÔ•¥1òÑ½w7eØdµİvÙb=á\0ùf €,³må)ÕéGpûÕ-Ó¼½)9Lı“š>|Ôë \"Ì@èû¤5§`†:›ô\0é,€ñt@ºÄxº“òlÃJÈ»b¨6 à…½‰İaŞA\0Ø»ARì[A»Ã0\$qo—AàÊSÒü@Ìø¬<@ÓyÄĞ\"as.âÎä÷V^„•è®¥^õ›…—œ\0ÜÈHÁ·[H@’bK—©Ş)zÀ\r·¨¤¤=éÁ^¿zˆB\0º¿’¤äNéo<Ì‡t<xî£\0Ú¬0*R ºI{¥í®´^æEµî·¸:{KÕ§1Eˆ0²ÓYº•›à/ÕÑcêÀ\"\0„ê¸4øÉF7'€†˜\nÕ0İÉ`U£Tù¤?MPÔÀÓlµÈ4ŒÓr(	´ÁZ¿|„€&†©t\"Iµ¿ÖÛL w+Òm}…§÷€Wi\r>ÖU__uÅ÷63ßy[¢8µT-÷ÙVÏ}¤xãô_~è%ø7Ùß{jMáo_šEù÷ØÓë~]ôP\$ßJõCaXGŠ9„\0007Åƒ5óA#á\0.‹Àä\rË´_Ö¢áÀßÚ%şáÀÀ\n€\r#<MÅxØJËù±|¸Ø2ğ\0¨–;oŒ^a+F€í¸Îç¬€LkúÁ;À_Ûİê#€¾M\\“¬€¤pr@ä“ÃµÆÔøÂşOR€¿ñ–~zÇûAÁNE°YÁO	(1N×‰ˆRø¨8Ø€C¼¦ë¨Én?O)ƒ¶1AçDo\0ä\r»Ç¢?àkJâî‘“„\"â,OFÈÌa…›ùª-bà6]PSø)Æ™ 5xCâ=@j°€ÇL”ÁèÈLî˜:\"èƒ»ÎŠ¤l#¢ÀéBèk£“ˆ›€ÖË@ •Nº:ê>ï|Bé9î	«Èî”:Nıñ\$èéS¥ CB:j6î—Şé•àÎ‰Jk”†uKğ_W›Í¢Ã˜I =@TvãÒ\n0^o…\\¿Ó ?/Á‡&uê.ŞØ_˜æ\r®î¥Cæì+Úøc†~±J¸b†6ÓüØe\0ÍyóÑ¡\0wxêhÁ8j%S›À–VH@N'\\Û¯‡ÆN¥`n\r‹ÒuŞn‰KèqUÃBé+í˜f>G‡°\r¸»ˆ=@G¤Åädç‚†\nã)¬ĞFOÅ hÊ·›†ÃˆfC‡É…X|˜‡I…]æğ3auyàUi^â9yÖ\no^rt\r8ÀÍ‡#óîØâN	VÈâY†;Êc*â%Và<›‰#Øh9r \rxcâv(\raŸá¨æ(xja¡`g¸0çVÌ¼°Œ¿Q†©x(ÇëƒÀglÕ°{—Ægh`sW<Kj°'¿;)°Gnq\$¨pæ+ÎÉŒ_ŠÉdø¶^& ¯Š˜DÂxà!bèvŞ!EjPV¤' ââÁ(”=ÏbÂ\rˆ\"–b¦İL¼\0€¿Ìbtá‚\n>J¬Ôã1;üù¼ÖîÛˆ¿4^s¨QÁp`Öfr`7‚ˆ«xª»E<lÑÏã	8sş¯'PT°øÖºæËƒ¸°z_ÊT[>Ğ€:Ïó`³1.î¾°;7ó@[ÑŞ>º6!¡*\$`²•\0À„æ`,€“øÇàİÁ@°àáå?Ìm˜>ƒ>\0êLCÇ¸ñˆR¸În™°/+½`;CŠ£Õø\0ê½*€<F“„ö+ëƒâ„q MŒÁş;1ºK\nÀ:b3j1™Ôl–:c>áYøhôìŞ¾#Ô;ã´Ü3Öº”8à5Ç:ï\\Şï¨\0XH·Â…¶«aş®¸™M1ä\\æL[YC…£vN’·\0+\0Ôät#ø\$¬ÆØØà!@*©l¦„	F»dhdİıùF›‘à&˜˜Æ˜fó¹)=˜¦0¡ 4…x\0004ED6KÍòä¢£±…”\0ònN¨];qº4sj-Ê=-8½ê†\0æsÇ¨ûˆ¹D§f5p4Œàé©Jè^Öí’'Ó”[úùH^·NR F˜Kw¼z¢Ò ÜĞE”º“ágF|!Èc©ôäo•dbÁêùxß\0ì-åà6ß,Eí„_†íê3uåp ÇÂ/åwz¨( ØexRaºH¼YùceŠš5ê9d\0ó–0@2@ÒÖYùfey–YÙcM×•ºhÙÃ•Ö[¹ez\rv\\0Áeƒ•ö\\¹cÊƒ†î[Ùue“—NY`•åÛ–Î]9hå§—~^Yqe±–¦]™qe_|6!Şóuï`fÕî™Jæ{è7¸ºM{¶YÙ‡©øj‚eÆÌC»¢S6\0DuasFL}º\$È‡à(å”Mb…ÈàÆ¤,0BuÎ¯…ì¥Ñ‚2ögxFÑ™{a¸n:i\rPjıeÏñ˜rÈrØÏGıBY ˆM+qïçiY”dË™é`0À,>6®foš0ù©†o™ó æXf¢äù\0ÀVİL!“«f…†láœ6 Å/ëæ£1eƒ•\0‰>kbfé\r˜!ïufò<%ä(rË›ùa&	ı™¨àY€Ş!¡Òñ–mBg=@ƒĞ\rç; \rŞ5phI 9bm›\$BYË‹ÿšÄgxç#‰@QEOÇæm9–®Ë0\"€ºç!t¨˜ê†Ë‰¸®Ğ‡çO* Ååÿ\0Âİ>%Ö\$éoîrN&s9¿f£4çù™gŠä~jMùf›wyèg›yí\\`X1y5xÿŒù^zï_,& kÑæ¢é|¡€À¦1xçÏA‘6ğ \nîoè”»Œ&xÙïgg™{r…?ç·›ü-°½…®|tä3±šˆÈÍ}gHgK¢9¿¿¨õJÀ<C C° 1„î9ş7‡g÷š‚ïh6!0Hâí›cdy´fÿ¡DA;ƒ‚9…Tæ¢ÿ®0¬Ä\0ÆpØàù†!‡ 6^ã.øSÂ²?ÆØ¦E(P­Îˆ .æÂ 5€ÄhŠéˆEPJv‰ .‹•¢+—\$ç5Œ>P+µ?~‰¡gŒ6\r³öh¢¼p«z(è†WÙÄ`Â•¨±\"y¯ñÏ:ĞFadÅ¬6:ù¡f˜Şi\0ì˜İØàA;áe¢°àì¬ç^ÊÖwf„ >yÍŠËõ`-\rŠÚ…á\0­hr\rÎr£8i\"_Ú	££¼9¡CI¹fXËˆ2¦‰š\"ÍÅ¢‰… øh¢L~Š\"ö…š%V•:!%Šxyèizyg„vxÚ]‚Æ}qgÄÃZiŒä|Œ`Ç+ _úgèòú†™Ù£¾úªÂÀÂè­6PA€Ê€\$¶=9¢ŒùàÍh‹¢|p’ ÿ¢ˆé˜íè!¢.ø!”ş¶üiç§^œøÚiË¢8zVCÌùöŒZ\"€æäØ(Ä¥›¹°9èU)û¥!DgU\0Ãjÿã¿?`Çğ4ãLTo@•B¤§úN†aš{Ãrç:\nÌŸ“E„»8Ã¦&=êE¨*Z:\n?˜¨g¢èÌŠ£‹h¢õ.•˜’ Nş5(ˆSƒhÑôi2Ö*c„fı@•“ÑŞ7¦œz\"áƒ|ÖúrP†.Ç€ÊL8T'¿¸k¢ˆß:(¹q2&œÆED±2~ÿ¿Ø±şœŒ¬Ã9ûÒÂv£©¼8ÿƒ©– @úé^X=X`ªqZºĞQ«Ö®`9jø5^ˆ¹å@ç«¸În¼qv±á¨3±ÚÇèŠ(I6ğªjšdT±ÚÂ\\Š ‚Ÿ3¢,™Ïhék¢3ú(ë3¬‘‘PÒu•VÏ|\0ï§†Uâk;¢ÌJQ¶ã é. Ú	:J\rŠ1ŸênìBI\r\0É¬h@˜¼?ÒN±\nsh—®å\"ë’ò;¦r~7O§\$ ú(ã5¤RÅèÆ	èÊ½jÂîšØFYF šÜ”£«~‰xŞ¾©f º\"ã†vÛ“ošëË¨ººÂº#ŒÜaÒèŠõ¶®P“„Ë<ãáh£-3éº/Gx®õ²nÇi@\"’G…?ó¤,ïZpÖxX`v¦4XÆõóàû„[ƒI¶œ7Ã¥Xc	îÅ!¡bç¢}ÚjŒ_¾¥9á5qti¦6f»’°¸İÙ5ÿûç FÆ¹ãiÑ±©pX'ø2¡rƒ„®0ÆÆºé§D,#GëU2€ÌØâIè\rl(£— €ì±£¦¨=ĞA¸a€ì©³-8›dbSşˆûõ4~‚ô—H;°Â­0à6Çbé{ª„ŞºRæèÃs3zë¯ÃÀüNğŞ„`ÆË†+ò¦­ 4<ø^aƒy°¬”	}r°Âây´õãáû¸kŒ&4@ˆÁ?~ÔäÅcE´ÂÈ­@ˆLS@€Œéz^qqN¦°</H‚j^sCâ`èæsbgGy¹¤Ö^\nÈNó\n:G¶N}¼c\nîÚÕí¤ +£†ï=†pÙ1º’NµTB[dÀÿ¶–š¶Ğ‹¢¾Ü¹ñ`³nÚoj;jÄ›whØõ€c9ƒ‚pÌ¡[y4«¨¶05œÍ‹NßÁ+Î¿·Ğ`Xdaáæ/zn*öPÀ‡êÁ¸#tíèµ¸~à9Wî	šVâò~=¸#Ùùn)¨î´î	2ÜÉ;…j:õ°Ják„C¸!>xîù5š£==¦2»—‚. ã|¿'¨îä[€Ì'—;üÚv½ù«–“¸„®÷ÎëÎ;:SA	º&Ğ[£me†êãn±ëúûªî™«Ëµ¦Ä•<Ÿº6ma‘=Y.ç¥ÀÅ:g¶ÔşÉè…€ù°Ğ;«Iß»xÅ[”éI¡J\0÷~ÂzaY®íºîüwT\\`–íV\nÆ~P)ézJ¾©æ½üñğQ@İà[¶{rÊ‰µDîB„v—ï|i-¹EæøKŒ;^n»{êó½å:Nh;–—Ú2Á¨Æ€pçÑ´6“úƒ»ç½˜9§9¡¥öÖXÂhQœ~—ÛÛiAŸ@D šj‡¥î}ÑozLV÷ïçÑ³~ù•	8B?â#F}F¾Td­ë»áĞe±ÃzcîçŸFÅÀŠg‚7Î—Ûêà€ 6ı#.EÂ£¼áÀÖÂ£¥ğS£.J3¥ö5»¯KÉ¥óJ™§¸;¤—„n5¾¾:ySï‘ÀCÛvoÕ½.˜{ñğ	d\\0ë?W\0!)ğ'šû¼èEgá;à+»\0üY Ntbp+À†cŒø“ş£\0©B=\"ùc†Tñ:Bœ±Á¤úcğïˆşîÆï¸P‘IÜÈD¸ÂV0ÊÇ!ROl‰O˜N~aFş|%Éßº³¸¬…ò)Où¿	Wìo´û‡Qğw¨È:ÙŸlé0h@:ƒ«ÀÖ…8îQ£&™[Ànç¹FïÛp,Ã¦å@‡ºJTöw°9½„(ş†œ<é{ÃÆO\rñ	¥àùÚ‚\$m…/HnP\$o^®U¡Ì\"»¿ã{Ä–…<.îç¡‹n¥q8\rÕ\0;³n£ÄŞÔÛğç¡Ÿˆ+ÎŞ³3¢¼n{ÃD\$7¬,Ez7\0…“l!{˜é8÷á¶xÒ‚°.s8‡PA¹FxÛrğÄÓôQÛ®€¹†1Ì…¸p+@ØdÔŞ9OP5¼lKÂ/¾‘·¾˜\\mæú¸Äs‡q» îvºQí/§ÿÜ	„!»¶åz¼7¾oœ¿EÇ†Ò:qàV 5˜?G¡HO®âO†\$ül¾š+â,òœ\r;ãç°¾¤’~ÎAÄéŒ³é{È`7|‡ÿÄ‚Äàër'‰°Ji\rc+¢|—#+<&Ò›¹<W,Ã>¢»^òPğ&nÂJhĞe‡%d¶æìèÏÜCƒi¶zXÃAÿ'DÍ>ÉÎˆ¡Ek£Ê¬@©Bòw(€.–¾\n99Aê¯hNæcîkN¾d`£ĞÂp`Âò°%2ö¦½\0");
} else {
	header("Content-Type: image/gif");
	switch ($_GET["file"]) {
		case "plus.gif": echo "GIF89a\0\0\0001îîî\0\0€™™™\0\0\0!ù\0\0\0,\0\0\0\0\0\0!„©ËíMñÌ*)¾oú¯) q•¡eˆµî#ÄòLË\0;"; break;
		case "cross.gif": echo "GIF89a\0\0\0001îîî\0\0€™™™\0\0\0!ù\0\0\0,\0\0\0\0\0\0#„©Ëí#\naÖFo~yÃ._wa”á1ç±JîGÂL×6]\0\0;"; break;
		case "up.gif": echo "GIF89a\0\0\0001îîî\0\0€™™™\0\0\0!ù\0\0\0,\0\0\0\0\0\0 „©ËíMQN\nï}ôa8ŠyšaÅ¶®\0Çò\0;"; break;
		case "down.gif": echo "GIF89a\0\0\0001îîî\0\0€™™™\0\0\0!ù\0\0\0,\0\0\0\0\0\0 „©ËíMñÌ*)¾[Wş\\¢ÇL&ÙœÆ¶•\0Çò\0;"; break;
		case "arrow.gif": echo "GIF89a\0\n\0€\0\0€€€ÿÿÿ!ù\0\0\0,\0\0\0\0\0\n\0\0‚i–±‹”ªÓ²Ş»\0\0;"; break;
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
	'en' => 'English', // Jakub VrÃ¡na - https://www.vrana.cz
	'ar' => 'Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©', // Y.M Amine - Algeria - nbr7@live.fr
	'bg' => 'Ğ‘ÑŠĞ»Ğ³Ğ°Ñ€ÑĞºĞ¸', // Deyan Delchev
	'bn' => 'à¦¬à¦¾à¦‚à¦²à¦¾', // Dipak Kumar - dipak.ndc@gmail.com
	'bs' => 'Bosanski', // Emir Kurtovic
	'ca' => 'CatalÃ ', // Joan Llosas
	'cs' => 'ÄŒeÅ¡tina', // Jakub VrÃ¡na - https://www.vrana.cz
	'da' => 'Dansk', // Jarne W. Beutnagel - jarne@beutnagel.dk
	'de' => 'Deutsch', // Klemens HÃ¤ckel - http://clickdimension.wordpress.com
	'el' => 'Î•Î»Î»Î·Î½Î¹ÎºÎ¬', // Dimitrios T. Tanis - jtanis@tanisfood.gr
	'es' => 'EspaÃ±ol', // Klemens HÃ¤ckel - http://clickdimension.wordpress.com
	'et' => 'Eesti', // Priit Kallas
	'fa' => 'ÙØ§Ø±Ø³ÛŒ', // mojtaba barghbani - Iran - mbarghbani@gmail.com, Nima Amini - http://nimlog.com
	'fi' => 'Suomi', // Finnish - Kari Eveli - http://www.lexitec.fi/
	'fr' => 'FranÃ§ais', // Francis GagnÃ©, AurÃ©lien Royer
	'gl' => 'Galego', // Eduardo Penabad Ramos
	'he' => '×¢×‘×¨×™×ª', // Binyamin Yawitz - https://stuff-group.com/
	'hu' => 'Magyar', // Borsos SzilÃ¡rd (Borsosfi) - http://www.borsosfi.hu, info@borsosfi.hu
	'id' => 'Bahasa Indonesia', // Ivan Lanin - http://ivan.lanin.org
	'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
	'ja' => 'æ—¥æœ¬èª', // Hitoshi Ozawa - http://sourceforge.jp/projects/oss-ja-jpn/releases/
	'ka' => 'áƒ¥áƒáƒ áƒ—áƒ£áƒšáƒ˜', // Saba Khmaladze skhmaladze@uglt.org
	'ko' => 'í•œêµ­ì–´', // dalli - skcha67@gmail.com
	'lt' => 'LietuviÅ³', // Paulius LeÅ¡Äinskas - http://www.lescinskas.lt
	'ms' => 'Bahasa Melayu', // Pisyek
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'no' => 'Norsk', // Iver Odin Kvello, mupublishing.com
	'pl' => 'Polski', // RadosÅ‚aw Kowalewski - http://srsbiz.pl/
	'pt' => 'PortuguÃªs', // AndrÃ© Dias
	'pt-br' => 'PortuguÃªs (Brazil)', // Gian Live - gian@live.com, Davi Alexandre davi@davialexandre.com.br, RobertoPC - http://www.robertopc.com.br
	'ro' => 'Limba RomÃ¢nÄƒ', // .nick .messing - dot.nick.dot.messing@gmail.com
	'ru' => 'Ğ ÑƒÑÑĞºĞ¸Ğ¹', // Maksim Izmaylov; Andre Polykanine - https://github.com/Oire/
	'sk' => 'SlovenÄina', // Ivan Suchy - http://www.ivansuchy.com, Juraj Krivda - http://www.jstudio.cz
	'sl' => 'Slovenski', // Matej Ferlan - www.itdinamik.com, matej.ferlan@itdinamik.com
	'sr' => 'Ğ¡Ñ€Ğ¿ÑĞºĞ¸', // Nikola RadovanoviÄ‡ - cobisimo@gmail.com
	'sv' => 'Svenska', // rasmusolle - https://github.com/rasmusolle
	'ta' => 'à®¤â€Œà®®à®¿à®´à¯', // G. Sampath Kumar, Chennai, India, sampathkumar11@gmail.com
	'th' => 'à¸ à¸²à¸©à¸²à¹„à¸—à¸¢', // Panya Saraphi, elect.tu@gmail.com - http://www.opencart2u.com/
	'tr' => 'TÃ¼rkÃ§e', // Bilgehan Korkmaz - turktron.com
	'uk' => 'Ğ£ĞºÑ€Ğ°Ñ—Ğ½ÑÑŒĞºĞ°', // Valerii Kryzhov
	'vi' => 'Tiáº¿ng Viá»‡t', // Giang Manh @ manhgd google mail
	'zh' => 'ç®€ä½“ä¸­æ–‡', // Mr. Lodar, vea - urn2.net - vea.urn2@gmail.com
	'zh-tw' => 'ç¹é«”ä¸­æ–‡', // http://tzangms.com
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
		case "en": $compressed = "A9D“yÔ@s:ÀGà¡(¸ffƒ‚Š¦ã	ˆÙ:ÄS°Şa2\"1¦..L'ƒI´êm‘#Çs,†KƒšOP#IÌ@%9¥i4Èo2ÏÆó €Ë,9%ÀPÀb2£a¸àr\n2›NCÈ(Şr4™Í1C`(:Ebç9AÈi:‰&ã™”åy·ˆFó½ĞY‚ˆ\r´\n– 8ZÔS=\$Aœ†¤`Ñ=ËÜŒ²‚0Ê\nÒãdFé	ŒŞn:ZÎ°)­ãQ¦ÕÈmwÛø€İO¼êmfpQËÎ‚‰†qœêaÊÄ¯±#q®–w7SX3–óQ°ê/ØÓ—Jı6éÊ™Ìïg2qs‘_fœ˜oµEñ·˜2¶<üBÈ6­kğ@£²ÊZš„‚Î¦Œ#Æ¤nE¾cêëÀĞ‚ÂŒƒØ÷>`@\$cB3¡Ğ:ƒ€æáxï…Éß»«8ÎÀxá½ãÈ„J\0|6¬éàÜ3,ïób×‡xÂ48Ê1©¬ÚâÔDcº:C¬À„´âØÎA&2ğ,ß.(³N'NğğŠãä78cšŒCË:„´E¤BŞ6%ïĞ¨<\r=\$6½-„\n:³ØÆ€Ó«ËŒ3#¬ß94£N)ËÓ\0#£tÀ4µô0‚3ŒèÎÎWÕaAc@æ#¸Ğ¿Uğ˜2)0»Xá¹Ikl*8\0003–0ƒ[µõT643Ò1¹@¢&\ru8æ>#}2ım4í8C“Ëë{ÁCŠ°Œ4ŠÎ Wõ–ÿ>WÒûsàø É2”€’±YÂ(ñ	/øË‚\\¨½Ÿßb „/¨½d\"'­~Ÿ,êr)s([˜¸3\\´“E\n·%ãıC˜åàË¥¯²z:PÂ×A,¢8Ê7½3BË7¼¬B\rA CxÌ3\rJPûŒñ *\rèÓü7(Úù5BïhÍU@OÃwjI~µÏÎÍGõFì›5‘µ!´1·ÜæË[K 0ïÆ÷¾³¬6Á ûgU%£ê§Ò¤â:Ã.hAGëôT×/ƒM¡8ÅŒT_Æq¬oÇc¼{Á2å!Èª:)+Ò`}áXl³-Ë¯m‚§bãJ2EHî7…ñNÏµ¥	SÁ8Èïl’©ã‡¤š…Ñ\\[ÙÆQ¤mGQä}Ş…Ò‰âæ½k_&IØŸ¬©^X>emğ2­ĞŞHLÁş\rdğ¦©äÙĞR<O8×¹”\"„Ûã¤)¬ù— ÈçVY\"\\e“ A s¢=\$„0†eğá[hsq\rÄü¢Deá\nõ=+µéÁÒ«L1,Üıó~Ìˆ‚İ`¤qŒÒB@PzuıÀ@\n\n\0)\$D‘g \r(RA¸ß«wLfˆi2d\\Ê’šO&\$Íô İšé­‡¦Â†Ãç–b~'ÄŒ9¤t.ŞaÂR\n>#àˆúÅo…¥Âs<@—C¤4qÚ<:à@Â˜RÆ\\7À°ÜúU¬‘\$‘*·g!)ÉI+%¤¼ß3ÔÖ£™„&¤4œpÈOŠz&¯ .‡ËZ£zä €’D!.ä†9ÙT·M&Ì¨’CLC½‘'d1‘æ6	òG2®u²ÂrÓ1BxS\n‹ä0¾¤MST—ŒÉ8Ì–9á1u„”ç%K¹Væ4”5eÚsÉ\$l1Nğ¦ºåá82dŒ#H¤ĞÉlMOÅ43MòxËñCJ%¤ìÀ³¾q2 çè'„à@B€D!P\"€ªj E	)&¥Mğa='íAT¨\n‚Aö[kvŸ5‚®ƒ\$Ô8¤7š¢9W\rQG;\0*{¥'lLGĞ¾’†‚ÂŒ±‹5Lù\0 ³„cª°dÉ…0†ÀÌìôCÇM‡†øb€š8\n\nÊ\n§¥Ëˆtt„0ŸHˆ İ0aÔ3†‰‹b›6yå\0åˆÚH‘»+¬–’”•Œ¤©“ÄlfŠÇ“¥4Ô\$YÉ¯I~'Zï,S„}¶Ä…9§P­/S¨T4QÙh… ›—Ák¹ª@)Pä…í›¿€«ğò bmJÃ=-¥”*…xÏĞ-Yq…¤ÊSùÏúk§VŸ*r¬Ò©œ¬ˆ‘¨`ŠcMƒ@>liI«º Aa M²ı/d\\š±1	ÇPŠvÙkxÁÎ5C‚\0^0éƒê0â%M›z	}¿ÑS{ÄD‰­ÛÇĞqA~m%?DàA‹ñ%0’¦ê`Wsg—ò“†6üJŞÇw;'cì^±&\rÅ8C&âÜ~Ë®ToËUÜL¬Ê-#çÄ¢‚9D²[XAl¥=AõgIáäA\\2†,ò‚‰¼ÌPÁIZÑ•^FCƒ2;%øî&C	¢ƒf;ÃÉC†YË(C˜ç”»ˆæ^Œé01dúº• r#¨IÔ&(öIjêG©Ô,ÇQêã²nËôNs¬«ZêZì¼”Îv¹K›^†Zê«S„uØdãb×Y”Á6*ª%€æ “‹kJ«rß/&VBÏY/=DÔÍÄñ6á6/¤Ô»«”1{ºdú¹-}²fvŞE<)u3Q” m;  ‹œÕº\nKuNa5°lİM]ö\rn¯¼7VëâTsµoáš³RliÉ`#¯°|Sìtîôã­-¡JşD~CÉ‘5È&GÂĞÍ ø	åŠñÍmÌ»Ô'ùÂãNo‘³°F¼‰´òğÉÄ#¯J 4°Rî}½ù?Qé}Vñõ~=Şº5ZC´2ãvŠm1R¤ø—Mİë*áÑF>f]LVè²âçmƒd\nˆ]-ùbQ¡í¶£ğ)*‘zÌVÒîµÁ™ºSÚ=*êw‡®^EeJjycÁM°€†çÎŞğœı\\tûË÷Ç(±W#¯W^Ÿ°#¯¦LÜ¿gö*ÿØHÅWşúŞì3·¼@>£¬ü\0S}§ Î×A_,Î‚&£³—Šk=Ùä,¡>r[ƒóÌ‰I¥ïrî+×ı}xÎÜìØşnJö¾)(ıœ÷äû—ÉÉ\"µ„wpÈ4.„Øúmål¶Oncƒ\"°Éğ +V²Eîş¼«ØANœê\r„¼ËÚ÷ÂbÙ0/dâş­ %˜N&t¼ãË\0ë«ÚlfŠû/¦\n£yOÒâ#ÍïÎäĞ8%anãïì%\0=…–>/æ7àêSÉîÉJ4æ÷+åàÖ\$Î‚ËÿÄ0®#p×Ğ¦æ,ìÓC_	Ãt;\"2.\"Ú	p¨Ã¤şÔPzèÎq\nÃtÔ-én¾æĞŞR\$Ğ\r€Vœ Ò`ÖÑD6Ûb0lÈ\r Ìm\"ğ(lÒïI,‘\0ª\n€Œ peÃ\\.ãšÊ‰JBÂ®rÆL†ÕŠæëñ6/èê\"fT3J·‰VóCN	±ëVŒ\rĞ,î/0v)Ñj±ªÉ„ .ñ‡AF¾ÍføJ)è´¢@ZƒÖ:(À*,öDbp \"Mëf±Ñ@ŞÎ¾Mõ1ª.q®çêÌÙKií|5ÇÍQ¬íí}qÑÑÔëåä6\$'m£Æ†¸«ck®³ÏoX¿Í«Î4\0˜ªeŒß`ñ ’á`@Â4îX0«B ê%ê«Kl@¢Ø\"vR%—ËVHç¤ldÒVÆ¬fH”N*ÖärP¨Àà,ªº/Àî-t_ğ\0cÀ‚)¢ÔÀˆ©fö Z?ÂMI¬*L³(RˆÑÈëòÀ"; break;
		case "ar": $compressed = "ÙC¶P‚Â²†l*„\r”,&\nÙA¶í„ø(J.™„0Se\\¶\r…ŒbÙ@¶0´,\nQ,l)ÅÀ¦Âµ°¬†Aòéj_1CĞM…«e€¢S™\ng@ŸOgë¨ô’XÙDMë)˜°0Œ†cA¨Øn8Çe*y#au4¡ ´Ir*;rSÁUµdJ	}‰ÎÑ*zªU@¦ŠX;ai1l(nóÕòıÃ[Óy™dŞu'c(€ÜoF“±¤Øe3™Nb¦ êp2NšS¡ Ó³:LZúz¶PØ\\bæ¼uÄ.•[¶Q`u	!Š­Jyµˆ&2¶(gTÍÔSÑšMÆxì5g5¸K®K¦Â¦àØ÷á—0Ê€(ª7\rm8î7(ä9\rã’¸±¥Â€B¾+‘\\ÈècîY§*ƒøü›œ+\"	ãêñª)\"¶X£ªØ¢eJT*©Ú¶I£÷§»¦¸¢Pê°ìFêÔt‚\"et~é°&ÁM# Úõ@Á\0È7¶M0Ş:#m¸áËÆ1¶C›Ô3„¤İ8CæÒãKzÏË­LÔ9ğHÈäÌ4C(Ì„C@è:˜t…ã½2t¡A#8^2Áxá9Î£È„J`|6Á-+Ô3A#kt4ãpxŒ!ò–&Ám‚?2«XÖn£“j©PúÆÌ<+!×u•‹11Ú‚ÀHé£ö„ª²në\"@P®0CtÆ£\$^ #õÓö–«%|\"ƒ«eÍ–º›\$ŠY\\‹ò—ERR<“ğÂ:’Àì0ƒ¨ÊûÂkİn’B,B²¸Ø©ÂZİÕªjlkR<ñ‘Jº#XµZÆÌjØY<Ø½Ôlò<HÆ–¸iKkÖ/#`chá5èØÆ0Ğº@ §d(@)Š\"bÔ‡©£ŒíYn²…ß1dFêVÊf„ê6>§\r!°™o–1lkÀÈ¦ÈşmEºµØÚ¼y¬×¦dşdy=s¡dy­²‡Z¶>l;Ìs ñZğ«BE,†­V±M®‚Š›pWPˆßç*˜A0(İÎ©mŸCÏŒ£Ãv7LUHæ§ÃÃğì{ŠÙ•ÇŒÌL•Îv^Á¨q­âè_P†1ÉÉhûì€1¨›ŞûC Ø3Í¸Òã0Ì6J+ñ!kñä&“«%`*\ríEL7!\0ëÖ²üÂ3`\0Ø7ŒïPç<ƒ—ÚC8a=@‚®@“Ãpu7` 9‚“\n†R\n9|%ñ—Ä^cKél¤Ì*\$\0lÕ r}Éˆõ‚›2xn¸4†D¢\n}OêA¨U¢TZ…\n@9)%(Ò›£Š¡J‚ }U9ëUj´§¸Rö!ÈJ´'ÉçUÄ3oVD¸AGj‹Óß‚K8¹\$TLÂjj7p‰K‡4è(x¥>†@\\SÜ2P\n	B(e¢ƒºŒQÁ¹å\"¤Ô«©un¶\"©à’C¯\rªP:D°|é`8eMëˆÚÀpÂÕXõÉE^¼\"ê÷%'í! ÔŠ	ïFp4T½ÕH M¡±#K(Ã”!’kˆ0†hÖ–_”n~Œéû¿”›Mé´–¤l0É0@ã¬”\r,×Ÿa’H¯ …P’R_\nÛ+1“}»urjC@peä4‰Ÿ³C\\„ä*ÓòÎ“êLÂ©!ÂY:ja®6•q%·¢lÍúVK²ğ7‡zSÛ£“!GR38¹ÆUI)D:„ØTËD¨šfj]0@DoUn)9Få2¸ƒ¹¾a¢†Î ¾šF•Ÿ‡TÖSÉ9\r@€!…0¤¨üå ‹UA¹æ[IÁJp…¶YA7d~OálV(¸şV8¼‡Q0‰&ká{h2V×¥GZqBPÊ)G>G,AFr9JÑG:„UÅ‡JLÂI&})BÚ\$FÔ¤œ7ÆÍ>‡ÒÀfA¶H4£L c~é®iQ.lkY\r?dú{Îğ Â˜T!dE¬ÖgLc»ÃhÌX½×Ò|GÅ©&ÌJ«Ú´~CQ¤^;–éq²†‹Z¡‰Ÿ`”1´|ÂÛõrja@\$;cú6ïİF¦ZƒOš;Iš…Z™‚0T\n	”7. Ó\$ä³6nÈ#H‚iÇF…TVÖrpBÉöWÅµ‘Š€Âp \n¡@\"¨p~&\\.…ĞÍsŸü¶!‹vƒ'dúEQ–¸²h‘®Ã{«bØ — îPÑv]–ø…“âåÉp‰#¢á:öíPÀ1Hlø/q¹A£-nâª§Hçû%·dØX÷†ÃÖ2´ÊjåÖ…~Õ2}ĞkK*6S¾áóB+Ê][Ñ§fæQ.LÌ¾·áBŞÛÁn¤È›#ÌƒAïBh¹Ïƒ©0Jˆ9ldœëˆ#» g\n\$rù”ÌµQÂÍV”u¯¬<JNtóL[d‡2%„M@ÅÁL4‡¦@d„\n`áL2šú	Cq\"%\0*4Æ¹¶÷5²â5¡”;­\"Ú-µƒ; l\nG\rvZî¢lWkã-¨à\\ŠT@í”tr±¡;ÆÅ\\”»¬\n\\ğ;‘.ÇQ”5´’®™é8îCz¡Bhß–n¯å>3 ù¡E#çÓC”È½Î¾m®Ì¨È1“ùq­kvp›8dÃ‡’`T\n!„€Ae0iJ¯^Ç‡4´—(¹ (ÌˆFÔ(Û}ÖG˜•¡—„-x \"Ëˆ¹ı'§×¾D%'¼X+Nx~š!O¸×Ty’bÖÚ‹0¹RS&^W»å^Ä‡Ö\\zyfJ#âÂÛ²÷ÒŠ¯M:?HsæÕƒ\$Ô\0•ïÛ»OqéÏ¨wißŞ Áƒl—¸v°/ºn ;‘ÄMag9>Ü¾\\•O2\\Ü2­ &<ªKS¤N¢vè[Ì’÷?9âÍhõxœ—2?ƒ»I\"óŞhšvG† yËbúá0æ1ÍºÈ£9Bà€\nCÕëCc<<ä¡‹u‹XŠ¨®=¯}¦V½Œïÿ.ü¨´é±í±…f,ŒDµ	'ë«dM¿\nÅiú¯êépŸ£Å qoä?F¤Œ‡Œîp¦ÎqüÚOLÄh.OşÚdD&Å€]jú:ğÆ­Z2l*£ì„B˜ì(lL‚ìÀèÅˆWÌœC0ÛÇg+zvp1Ë¤éûê®ÚÃÿEØ+\"VÛpxÜ\"‚\\éÒ\0PÅ„&ÔçŒ/l¶ØO€ÉÌäÍFæÍŒÈËH¢«bBÇ FéÎyHµ\n¦äÊP°–pã§\n†âÏMäkP²üo¼ÔPØqìÅâÌ¤8§éé¦°b¶aBOªV°ÒkŠ1º)hìÓì˜İìP8íİ´Á=\0%ŒÛQ&İ¯ÍâVÏåàl)î'12÷­ ÊD­ÀœÊ´ÜC\nb>/¡P¹Ob/e‚0obï#f^È€O«t¯äCÂdÎj?†œÎÔ¬åÎÎd4Ül½pÌÎ¬¨ªåb/©XDqV£Çq–Ò,F«, ôñ<ƒL1¥la°\"üÜ^ñ°óm¨m­Ì;\róG!ÅÒ&0qoİ­ıq\"şò\r,ß*ç!n7!¦ĞY- ürË&q4ıª¼â’5!‡\r!Â‚s0{Ò8:rHÕ‘I2R9E†\"o•\"/šfÏÂiÃ˜ ĞêÂ‚‰Òf2/÷\nEháñÒ7\0rƒc(’A2+2‡\$ÒŒ£ƒ¨ÁPs’vfû\"2C\"c(,%r&Ï‚Û&‘ˆó¯ÎŸÒ¨C/øüÄxß§o(°tŞ‡&ñG\rR'-²ç*ã-Æÿ,/‡pö¬œWJªG­Y\nÈÿ¤DC@²á*ÄK1ƒ1Ò—­©2D>âäk&W(R’À.ÊÉEäãcç+*èV’ÄÏÉß…16Ò¬k&q­mC®†ø*®bEÚl³`İMœ\"rt.OĞ:.´DN¹B“6`gú\r€V´@Ò`ÖtLÆ`£x}@Ş€ÒÇÚMÂfËÚ\rªŒK\n„€`ª\n€Œ ptHW;ƒÖşNĞgoÂ	Fj#¥Ê0âFùìÌF3‹Ø“·; òZN¢_%u*­Î29ĞàX\0EdtL{BÆ¦ù¬ºæ_ìò&`˜\rëÊTúLã‚ñôI>Ø.‚R¤†v¬`-§–!F/ªÈcï3sÉÆÇ0+°ˆÇƒ¨Ö#0”ˆ{ç{HéÛ\0Ğå-h¥Iô†\n‡:6cD4Š‚¥(t\0àˆdÙ\$Yô©Cqµ8DL+2*XblsqIÔ…DK‚ &ŒZ«Î.e”\0BŸNñèÛ{Ls\"ñQ^bêÇF'¾èà¬KÀê Úrğh ç.^8âlf*¶:Oq´~ÈlŠÒâ|¯F«ÌÆ¹\"æ‚¬„Çl—6¯´\rëşãV7s@qÂª]Kªc¤~x\"a\$…Ô	\0@š	 t\n`¦"; break;
		case "bg": $compressed = "ĞP´\r›EÑ@4°!Awh Z(&‚Ô~\n‹†faÌĞNÅ`Ñ‚şDˆ…4ĞÕü\"Ğ]4\r;Ae2”­a°µ€¢„œ.aÂèúrpº’@×“ˆ|.W.X4òå«FPµ”Ìâ“Ø\$ªhRàsÉÜÊ}@¨Ğ—pÙĞ”æB¢4”sE²Î¢7fŠ&EŠ, Ói•X\nFC1 Ôl7còØMEo)_G×ÒèÎ_<‡GÓ­}†Íœ,kë†ŠqPX”}F³+9¤¬7i†£Zè´šiíQ¡³_a·–—ZŠË*¨n^¹ÉÕS¦Ü9¾ÿ£YŸVÚ¨~³]ĞX\\Ró‰6±õÔ}±jâ}	¬lê4v±ø=ˆè†3	´\0ù@D|ÜÂ¤‰³[€’ª’^]#ğs.Õ3d\0*ÃXÜ7ãp@2CŞ9.(ÜÔ+z>P¯ˆK»ÃÆ>•BÃÇ\"ŠÁvÇišä¡å‚>H§²ı%(YpÜš\$*¼Z@é*p¥ª¤¸œBbÈ6#tPƒxÊ9„è£€áÍãÆ1ÍcœÊ3„Ğ0ƒÄ0c(@;# Ğ7³¨@8Pƒ˜ïŒ`@OÃ@ä2ŒÁèD4ƒ à9‡Ax^;ÕpÃ1Ì±@]ŒáxÊ7ô€çIRxD·ÃlWCL£4V6ĞHŞ7xÂ.1Û’˜²Ğ“¾²8óS) ë¤KÈ;+\"%ÎIxáÚ–¢Ë³È{ópHíÛKr´ïÉ<í¼Y-Šüb°¨˜+Œ#İ=£Á(È†µKJ&àØB‚ÔIF4¯!îJxÜ¥òÆ\$¯KˆV „#äƒ¶Š\\I“jš3	ñå5yB¢îÁ‘¦Hh(JröA%‰Vrä–œ76ğœt¯Z¶a¡Œş%öÄhE0ŠFV“fÌ>QF©\"±¡4\$Ò©ƒfà á“è(ª?)Aò¶xÒ/©R>Ø¿7¾ÔhK‘«¢À\\æì\"²íâ›\$š,¤é¸+‹ye¯> \nbˆ˜†¦9+¼ı2z\$›il´´÷]˜åÈ³<ı¢®J\\¥ÒÜ]:¸²/rµöôFmã/(hö›N‰vÎ'sà¹üvÕâ}­×ÇN›»´É4;®÷-zds¨wiÇz<„~òµ©’?„Âİg–B …ÎÙüÿ¿ö#6PËpAÄãwîşScüA”<Ef–8s9„¼Ëœ’nÙ:	+ìE¿ƒêO‰©3=í”ù‘\"îHû~&ç\$ÖW˜\$Ë%K¦pÒ\0 Ø“pdøÀó6PK4!EÅ\r%T®éJÃE]L¤Ó£<JÉÉ#AD˜ª’„¤CNIó\"‡ÙôÓÛ:–+ÑI‡¤r~á¢ú8à†šbÆHÊØ./5D%øÎ¢)^ˆåæ%%¨šµâƒ£6§UÔEt¯¢Š@‹§|“FIÅ¤d`ñ™x§±å}1%’ÆTI\"aV3*€ÂˆSbÂ@€:§ ÜŸdLÁÉK8C\"gS\0M)Å<¨¤TÊ ;ª¥Y*ÕxrV*Ì¦—ü2´@úc,YN²VZ-/èG¼“¾¹Ñ½I ì†”´i\"Ñ7ˆeN/#Â(v¡q…ä›“É­!WŠÄˆš¾Àp\\’6dğ)u2¦ÔêŸT*RªuRªåR®V\nÉZ@H DÊWˆ5¢ª~„ĞÑ‘6d»ããê8¢=ÇW ŸÒp&mw••š•Î97‚ØŞÁgÂ‚Šª\n gÙmSTw^Q\\/FÜ©Ñ‚¯K€TPÉÚd,uC`l‰CQ(ƒhe`á„3@iFÃªwO!˜:Õ ØÃ:e©ê((¥ıªÈ jnX†éCd\n8G8ÓÔI4m£{ƒ^Ä\rÌÃbğö«=GBšãY‚€H\nİ&×óŒâé (-À¤¼Éb²Ëa1*(\rc¦`Ç2%=m\rê<9íT(g«J=:&Çê›Czuª(’­@²°Ì'Ôx{ô°«XÛ‰@µkÉÀĞŠlOÁÍH'€AZ«u×\rÁÁF¨õ\"¤Ã“á 4†0Ğ gTMC]Æe(e·ÓåŒ¯Â|J\r`„'t¾¹‚ê#‰¤ıhqEÃII®}P±”dÉvÌnÙ]Éš¦V(‰ÑhŠ)´§G¯Ù†<EË;_SZ”œbnk*8½ÀEoˆõ,`à} \$Qx·Dælë|“SjjÏŸŠ‡Y/­¤…¸ •*¶VRQÊ¦\r|F^½+Ğ2ø(è5² ‚Šæ\nˆ%27Ä‚—£…ÌJX•Ÿˆç)Ø,ÔæªQ‚+*e’zà×ô`òá„ÂFù¸Ócg`\"?;¥ÇXÌfwÏÖGBì˜šÜwÁË©ÎUË“òèZÉ¡ÆÉ!*Y;–ê#™/)t©³Å2¡ƒg44F§nÅ˜pY½[Cßºjéb¦\nv4µˆ¸R&Â^Øîè%ÇŒoRA^Åof»ìÂÉº\\BÔÂ¢¾Ëæz“×ØßJóè0T¶{»øÄlïà‰Öûj½úKÊ¦ó/ù=knÒ¢r¨ƒßDÍ+?2@X.õZ¡'-S½šÒ^BÖÚ*à•³	ÆÛI[išü´ĞîÏá£Š=´,ï7ï\"sô‰(îrMàº†šHÍšês¹ö3òK½ºVéìú9TÊ@uG4‘XÉ6FÉ³ë,›am#Íá½1Îî!X¸Ód]ôrÜG÷!Ù^é|ï6F&ÏvZ\r\\Ô¹Ï·£b+9i”í3³(¢qœpcâ–³vkx!tnş>ºM—l*+Ìón\$BÛ0).Áw:•ÌJ|‹×[õ’2†Ø†€¦¹QqìHCº^ó§ßİ'\\ÖŞ‰¢û¿g&_ê5Ä3¤‰¬îKV¦bõ¦£×·D„XûÚ.X<ç½NsÍM‘#Øü›­êãÿº8.w\\9ĞlúU²®-(4!*@‚Â@ !Õ”ÖŸTİ¸Mõ9'K{·r‰(ÈÄÛ'RnZ&a ^0`ãŒ†\0Ä|s >,Š×*„ğŠLjËÁ`RÆ\0ë<ÈŠp+­¦PdÏü¦¬IB[‚r+01CdnÂôÌ£<ã¡p9ƒ†Î‹\nÍï @ÇNÃ«\"¢ˆŒ¬‰oöÇlÀ©ğŸÿ¦¢ËçQĞ”°) Ëp¦ËË6ÕD€÷0Ä`ØH&­ì|\"Ğ‡D†&&pÈß£P‰iâÕĞ65,®ËÁ|.!</ö±dnd³Ífğm<-\"£?ğ*Zí¬ÁÑ\r\r.^í.ÊÒH¨ ªŒ×ì¦¦Œå\"Hğ0àÕò×PÂJ#´b¢º?ÎèÒ§àÏŒJ¹BrÙæ¼@Bj&%°B¨†c¥ú+fğœˆXBãLæ_‰¬¢É4|äÂæ·®ã¦èN,š®0eÆjc‚âo/íÊmB®õšã!®Ú|‡%Äh£ĞRŸ|l±\"N:0úäâpò1ä¢œ|'!GÌ),txE²ffjGˆjØ(àzJb¨„~°Ê}¤T\n8\"á 6%¢ò-q®ç#'?#ÎAÑşv’!\$F\"fB½!#7!g¡mè©Ë¨|ã%Û¨†HÆu-Âó1:#îÎ+æ,,ÀP¦0P¦Â\0RÚ¦ÈI‡‘)ñ—†MÃĞ.2œ})­*1Û*mp+•Vw­l²È’ }n+ğ^ûd¡Œ³,ÎW+rÕ*QÆ×U2âç\"‡#=\"råÍq/¢¼¦,æ‡Ê?/¤&PV²ƒó¨4,fÁp@I“ÁS93u-…1*b[Ó‘SZ¨a+Pd+#jY£ŒË‰ì5*³T-o5Â)\0ÙSe&èßÓXGÓq“w-d{¹.Ë(1PqiÊÙ\$x´+‚ó<Ô\r”8j~äÜ:ën£@l/øpH²<*d§Ã|¨,Î’zê¡Ùë9¬ï0qóè.áÓÒ£xÆ2àŠ)8\"Á8o_Ñèñ²l%åöóç‡íŠ/K#ïjuC¶İ“Üb¤ñÄ,ñê+“xÄ³|ËÑğíŞu7*	6°ª+©DS'ºßåøE²Ü{ÎS'”.ğÑ\",~£è‘Dr¾8ˆ|ã°òú‰ppß648çT<“t„¸ôˆ’²»tY9,Ë?Í†òÇ~7«@ÎME¯º¦ô±HfîÍ¦](R€:arJ;²±7i6JBÖŠd%ùó<g³AÓ/6tJã²ËNÎm3í_8´÷C²õOåÃNóOR§J2Şõ&çd÷+ôef–|±U\rTª^\núO4¶ûË&”òCú…´oK•Fu;P4ğ*æ,¦ù¶z†°Ùod*D”2†®ö/yEUIãµ€öt5Eh=T]Cõ4òµ9<•p÷•Bå' %6fÈiôpŠfî÷ÏÀëOÄÂUwG±ËDÄˆœÃK\$„4•ÎØQá	j^3uÇ[ë‘^Ÿ4ÔcY¨v#áKò\$!GaQU@Üb’ ¤ì„iÏACpŒµZ/õâ8ÆÂ\$?	²ñ11:Ãñ­U–,/„’(E¯ğ\nŠÒ	æå\rM.ä~hÊHQkkG92TMcVh_vm7n.ur+&:j@††\0Øbú:bc¥ğ¥htj¤ +h´á­#_Uå'\r,.\"çåã<¤~¨í4À@\n ¨ÀZüí4¥¡pY1õ÷	\rVlÆĞLÕ0¿CV‚+’ÂÕ–ò±Ë|©2‰ÒsÅ£Ug1êruŸA¦/&^‚RÃ%£jP6•Núä4±2æ`p¼W6?u04€D¤1ßrGPZ(FÔÛÀ¦‹˜õkæP|KrƒvÍúw/À±ÇÊb*TATRkeÁÉ¹5jùTñ—S&vşğ–ÄtN ê‘¿ M-cÒk*Šr›7·znİ{ãÇzí FÓh÷¹@wª;òiõÑ6–I}÷©T÷­cã®¤ÕÀîL¨Ê÷İ|×½>¬yCª[°Gµlë.¯ø|Wò¥fA)²FA îv3÷_;O²ÒÍ»BÁJ×\0óØÜB„n@Z¢œ›¦à†£3\$ë4®ÌÚBHÚ\"×¦ÒlÈ%·ş‘dhFD4	%€8vò—t!/^YÏ[	æ\"‡¦Î4·Ex6\\£Ñ[Feñ!ã/çŠ¯¸uÎq[˜¯0‚>x\ràìE\0îµ¨^È5Q0Û0%N5TGÄ¸ô•&u,N8à"; break;
		case "bn": $compressed = "àS)\nt]\0_ˆ 	XD)L¨„@Ğ4l5€ÁBQpÌÌ 9‚ \n¸ú\0‡€,¡ÈhªSEÀ0èb™a%‡. ÑH¶\0¬‡.bÓÅ2n‡‡DÒe*’D¦M¨ŠÉ,OJÃ°„v§˜©”Ñ…\$:IK“Êg5U4¡Lœ	Nd!u>Ï&¶ËÔöå„Òa\\­@'Jx¬ÉS¤Ñí4ĞP²D§±©êêzê¦.SÉõE<ùOS«éékbÊOÌafêhb\0§Bïğør¦ª)—öªå²QŒÁWğ²ëE‹{K§ÔPP~Í9\\§ël*‹_W	ãŞ7ôâÉ¼ê 4NÆQ¸Ş 8'cI°Êg2œÄO9Ôàd0<‡CA§ä:#Üº¸%3–©5Š!n€nJµmk”Åü©,qŸÁî«@á­‹œ(n+Lİ9ˆx£¡ÎkŠIÁĞ2ÁL\0I¡Î#VÜ¦ì#`¬æ¬‡B›Ä4Ã:Ğ ª,X‘¶í2À§§Î,(_)ìã7*¬\n£pÖóãp@2CŞ9@Š‚0Á°­²öƒF+ÄzÂË3Ò·22Ù¯ŒKŠW5b¢I m³¬¢*yB¶QËÃ8·Š|NK­2CƒÅ*ªSÎÒ\n^SS‹Ì Œƒl™6 Şø¼£xè>Ã„ß[Œ#Æør`Î5‹ó\0Œ#›È;/ã½^=Hæ;Íã X–(Ğ9£0z\r è8aĞ^÷H\\0Õµ|á7ŒáxÊ7ã…¥jC ^.AğÛ7¼’`Í7¯ÈÒ7Áà^0‡ÓŠP¨…}İÃ+r¼\"í£ej}RPFÎ4îS4‘|°Œ0òˆâ/”_Bñî:€NËssŠ%P,>–Ä.Ê¥ Jö4Ü#]INU‚@B¸Â9\rÖBŒˆv/N“àN©«7Ù¦tÀåË£S¯F¼â­ÅTŞéPå@ÑSø´RSEq”P:û™y®5È\"ÂªíĞ\"[£ê6Vã°Â6£.~ŒOzF0èJ,‰èj‹A°íyğOÇ1š0ƒ¸°j\0ÒH¥4£LÅ+ÚıÔº›ŠQ8ªé<Ğ]ªa±ï	ÙL¡w®)qä}.kÙ%ôòDtU1ˆ÷R1n²àÃœ÷\$Â7I\"ˆ˜¶R.·ĞrFÙÄşDå»F•)ÓÔ›‹JíÔÆT¨cÎDg!Ù ”P)#0gˆ!Š#ÂˆÃİŒfOÂ“V)HsAzaSA'vL”¡lRéµª,ñÔKÁb‰eœsÈŞt†`°¹L'Êznæ¨ÃG¢J{5iâ¼÷£I¬„îBçœ|†Í\\S1‘PàÏóß<…È&ôÖ¢Ñq>Qz.Pğ~ƒrÁaÍ8§G’õ 8‚F(Ô%òJSØzz#Y\r4Ê…3Ø”¬Gw\$§Y{¢pJ	ö(sàœ@rGtï´óÈÃ0f\rŠ½8©²\0K2†à(*óÎÁCpyÖ4‡U|°3‡°7†t˜Ö¸tR¨0†pÂ“Àkj¸7Sô\n˜)“¤TÊ	Dó_›;Ç®˜X\"ğ\"RNæ0*Ì|˜r•k&‚Y1b×q¬4†E`¶OÜ[Ëq.EÌºRì¼9/æÕ”`Œz }@X2MaL26›w0^²3!Æ€·=„BCˆ›#å½rŒ^Ö¼ŸäØ‡IåUbIJ\$@ÆÕ’~§ökMj¦°ğJÜ€¹l-©ä·×\nã\\«t‡uÖ»CrlËÁy/HÍ#U_¡\$6‡ÜW˜t¡`ú1\n°³š©ô«Á„5°nd¨«45‘¹ˆ\"æ¡û³d%EåÀÆôÌQ ¦”æŒÇd7AäW´\r„˜èb<• 9N°ÕCf¦\nâWÓ9b÷e¤¶MK0ş;H\rXUÒâãc¼:qö¼ÃnR¨{!‘%¶Õ\0 „€†ÎÑŠR«©Q›6ÀÀ‚ä\n]\n/QµåeH¡Ğà1\r„TÇ@ç-b='¬öğÊÕUĞr‡Èÿ+Uybxw»éÄÏ%s4ÔR7¹j=P);\0›	òX¡Í{+ù‚šã\rÁÂc-f¾¨w?¡Œ4UàÒ× ±Vxò> ê²“‰[h‚\rŠp@Â˜RÆÔÙšÒİK-ğ¦,ğØWºF_`Yxd(à\nl4WR‰ ,(ŠŞ–b0•Œ)3·ôIÅ2ØàX¡Y@±Ñˆ×œ4ÃòID5xµ¨B§;f´,±˜BL…ÕÖÜkÙ€!¹V•ÓHøy;ÊÆvŞb@¼ë	ı>Kp8¸Ğæ­Ã2n\r³š¤+šƒ´YVzò¯càœ]BQ(\r!ı7° Â˜T!ÏéÏ•R›¦á	KHO¥	‘ƒRl+Â iÚ1|ÍŠQgbŠ¦=VÛdä\n!‘-ÂÙ¾f†şU³\\JòRÑ¾9WUƒzìX˜K‚\0¦ù\0f» €ö,PŒ-Ëãj¡¦ª«¹ƒ 4wG8g£‰İx€lb™+LDœ¦6d2€â)6G5[!P¶ïˆüJú…;)áHØõ#·ëídxÂ¶Rd`Ü6zm¼§á£‰[£›­,)+	D’›I0®ÒˆÉªÉ³x¥\r×@^IÀòé…aâÃŠÀ²£ÁßÅí7çP©h]t<‰‡–ö¾)Û\rÍ³h¤%FFühäETxÅc]8Ùùó#.Šdc‹µK3èZw¢Bè‹‡\"<)·ˆŠ‰¤,Šªœš×@NÕû²ˆb)“íq~gP¹¨Äµâå··9Ö¿;‡¼2ï·ìyrËÙ>]á9ÄŞ[»&a2)íçe*º÷ké×{Æl³uOİœàS\r!éÇ]J«1œpS§ºëİ÷:”rÖ>éîî—SO&ı£U=”;²x\"b	·õÛ±â r/Êæ‹`ïW–<o³İİûÿD—HiB@Äq‚SZ€4d¾Œf”8Ü7s!Ğú`éÊãNæ”=ÄïiÖeú(Tô#¬Ôn¤u.8ì((®CŠ¢ÇŠ¤¢0@á*)éœlã0J-üúnNêG„ôn–QÆ()O`ûe:3ÌN(…§~RNzOî<w\$ôìî\"H ¨\n€‚`úM€ÒV‰,ÎÀæW%v½kj}nøˆä~óîtİŒ\\é€#fªàA\nƒªàá~kFª@°´ç8}\"c	FæÖ¯ò8Lb€I®óÜËO§çî\nhˆÅ)°ê‰¶€O1	h8üPÒşpÖ}pÚ(ğß0t'ƒ¯§Ø µ\nnüfZd*S\0ç|Å#¤Öè¤×*º)Í|ğı=Ãª,äOÎBîÏÇ±\n›,µœ|ÇĞnğÇ\0S	¾é\$‚zóÎšûñb(‘gGo±eĞ	¸Y @MÀî\$'*º0 ¤KàôRR1®S«ŞŞ‚ô,,„€f(ıÏØ®Ğ<olØÔQµljæï®TG”(âQ‘iäb±‡OJmÔ.OëŞc\"ğàLFšzÆ…Q-Ä­±ì-Ê(E¢Ù!L£\$¢2lr-†‚5NÏ!Ğ4Ø-ttò3€äÄïÇpÅP@¢j½ña£³&0íëæ7(Ü0ÂéF„ø.\"Jó,²ê®‚úfŒQÒxêU\n-ê6é¡(wñ#\nLk)Rv€¦–èñ€~d†9Ñ0‘R«(ÑpfÌ(€ä#5(‘İ*Ñ÷+‚õ²×‘·î¯ã,Ñ%)ÒˆnvƒH/’Ë*ñá.¯¤Çãná‹flÊN(„p\\€ñ„T	 # „ÜÄ#Q†ĞDò\"¹¬ˆòcŠ2jü¢…¾„ê#140U6ØòSmguğLi/¬H¼ï/0Ô¯2¨£’ç1/¸îg‹'5gñ&bØÅ PyÎÑÁLâd¾(¢áÈ–ï§ú„‚¿-¯?-æÉ8Ñi0ºë¾èÎPÒºhs¤-³8óĞìsÔÃgı	°k3é<Ñ•“ğ†ñ‰<&Ò÷?ï9>ÒĞcae#û(=/³ã!Ñ:stwë‘iĞï@púCJ€ô&½ó‹AsÎ¤2ÄƒuH=sD+á¨›D´a@,jÅk^İ2Ù/óÆJ²‹Bô %	,sı@ó…“‰,”M,ã¶ş´hcZƒ³	4.TcK%>†¢ˆíÏ7(ÿ?(lËİ*fÈà+X&±Šêô	.'øUAT!Î^€Æƒ¤ÄRêCNíj0´Üf„/!GIGNB¸Î;xû«YMBKbîÜæØ‡‚dÔ\rîPbãî\\‘õ!O¢k*ªöŞ	!”‡S¹Ï£5ÏBŞìHdo§Aè\n„óP~N@€èm¥<ßó£ˆãmâ…=8/äü²ãEôÅHNƒXÉCHî˜øRá>´Ç@U e3ù0´Ã/ÒèùÆ)[R}?¨ÿÒU3QÃI‹<ABÔL…U]c]T¥Y2o[´™^Bİ^:š¾GµÊA­YÕÎµğUY{A-„ìUûa1•¹a¢Ÿ#‡¢i†¾8­O%a~íI\$'Z-Ğ+c\"ñcq!!j; uêÔµZc”!EpGZR„ô¯‡^®Æ¤{fJç\r§YR«Y•½9/QæC`0m_Uã@U.nÊİaOÉ_6)DD*&(ßkä¤’ñ_¥\$¯öhèL§G®Ö#±eµ\\v'kÎÄô”ò†È#O&Ó\0ˆá\0R\0JÙZ“ËH4O^Qxôi4òkgö¥hU÷@W#mv‘J7ilkqhq®p¶¸eòqr–ñu·l–Üş`æG_òT”DsÏ'(2İIVq`Å;u=kvsV•g.‚Pe4÷`cW`¶ÿ@G£ÔUhÕ~Ëï4Õê”pgS¦·QñlèaGD¹EÛa7oYÑmz–¨5nØ0W±Côw{pG{¶#{÷‚Æªêõ5~*.¼ÇFÎtgQä»BWØN°ztïR´hğí'¦—z´GGì{oÔ°@†—@Øm\r Æ\rhºMEŠqG?iN\ràÈ\r Ì•Eš. ŒÚ`ÚÂånÂi€\n ¨ÀZ¾x>I¢ë€74;{7Ò†NL×ÛB·K|8FÃ¯|ôuG’-‡¯N	JõÙqS€ëŞ)NºÌ¿,dOì¸®¤(Q\0›ƒØ@7D€duïv¯Ì‡Q‹3F‡WhğH!1¢ã†#jõvÕŞb2(Œ”ÃjüL7ga°¦	’j¥ş[…Œ@\0ÅØXHRÍĞÌf~Wé‹\n2-ÁoŒ¤@üm>r!QY02rçbŒ8´V;S\rŠ™”•j„®FäÙeWÎq×v	V’î€Ã‰Rm…rx¦ß”uf—[føƒŠ)¸‹Cä<#ÆÂ@Ê^hº\nUÏ’N2íŠ•&C±ÿ\\0Á7y²…¯>uÂurp-Á=2T%2³½mn ğS°9’T)MídTÎµó­)f òµÖñ®ƒ£®@¬W ê Úæ±·4urS§Y”²6(’'{¤+5¶\$¶s¢ù:#`)ùN/R\$ú¦%=5	=h_²{O«g®g'[–Gu–’p&¹z@ŞÜ€î=CõtYoR.æ‘vÌ‘DEd€@	\0t	 š@¦\n`"; break;
		case "bs": $compressed = "D0ˆ\r†‘Ìèe‚šLçS‘¸Ò?	EÃ34S6MÆ¨AÂt7ÁÍpˆtp@u9œ¦Ãx¸N0šÆV\"d7Æódpİ™ÀØˆÓLüAH¡a)Ì….€RL¦¸	ºp7Áæ£L¸X\nFC1 Ôl7AG‘„ôn7‚ç(UÂlŒ§¡ĞÂb•˜eÄ“Ñ´Ó>4‚Š¦Ó)Òy½ˆFYÁÛ\n,›Î¢A†f ¸-†“±¤Øe3™NwÓ|œáH„\r]øÅ§—Ì43®XÕİ£w³ÏA!“D‰–6eào7ÜY>9‚àqÃ\$ÑĞİiMÆpVÅtb¨q\$«Ù¤Ö\n%Üö‡LITÜk¸ÍÂ)Èä¹·/˜Ê6¢ïêf9>æ‡(c[Z4±€P˜œ¨ª¢ò· *Â0ÂÂ‚è53Ã*-£RŒÑÄ° ŒŒ2¬9(ã{TĞ\$((ê8+è#ÆÕj(Ï(èúş0h@î4¶LÔwÉ˜î¹Œ`@ #C&3¡Ğ:ƒ€æáxï3…ÃZ¢¿¡ræ3…îp^8J2˜ä2áš\r«š¢ŒË˜Ú#xÜã|ÁKûú‚CHúFCÏpÂb—ÎÈè9£Xè†±]0†\r1+D7Œí8ÉQÕ%LÛ%uœ7²È*ü;BÂ¸Â†ÈCš\"2b:!-a\rKõ‹cØu¤E\rƒ¨ÚŸÄcHá±ƒrı#ª@´kkÒ¿7â Ê3#¨Ù²Ö‹ ÂQË #¯¸ÆÃ¼än£Hå(ÉÓcÏM3Z3Œ—ğ¡?„Šb¹NˆğÔ:¦®€¶¡ÃD\"´Âì¾ªnDV5Şñ.5²hv0“AÃhàÓ±ÍÓ¢âˆ˜e¨Ò<¹‰İä”ÉHƒQ®•5„=3tŠıhunŠâ=0B#PU. Pê@QŠP@h«ğÜ,\"')Şh*§cÓÊ')xÂ¶9+îgPèğÎÃ#lb4Ñ¬ä\"6‹ûN)¡æş'íºÂğü(Ê< ãtƒCip©‹Ëÿr£ö“¨;Cğ &@KÊì†®\"8Ê7±”Û]pC2Í×h@Ş3È¬—	øLCím7)?\$:ÇÒ\0Íu UBŠ9ÊÔß‹*+ŠıØÁ]o¡@æ¥ÂªR2½*6Ã²â£:ûµt`”HAY?²°ÇÊ\r.ìa,3’Üº—Ó\ncL©œ;¦”Ö‹ÓprN	Èœóè¡“È>ªñ¨…K‚²—\$\$¤@	2\rde+\"ƒ’AÈ+`.ì—ÔAÑ‚tiI*Àğ)”É],¿ä¼˜dLÉ¡5?@å`XnN.@ç98”ƒ˜>	,¸Ó­éó„5n´46XöâGd¨9<3œ!is#îÙ>²j“ƒaG¤5™µ\n`ğerÁ „#Ø~ÒXl\r‹«Ÿ×Üc\0aĞÁ<˜fòä,ïY%›\"û(a1€€1™3º¶ƒIwr¦@¾†Äˆ‚rø#Çî/!¨æ/ˆĞi\r\0€(€ Œe9 _Äƒ#r4K‚†*ÁŒú8¾hÍ)§5+‡CVm¢OA¼;Ã¢^L²˜;Äu²“Èôˆãá“8¦®'D~õäº\rÁÁo\$ë\rC¹³rÌÃt¿!'Lœ])\$—é°¥ÅñŞ )… ŒÎ;CÅdí™D™\n•¡­áÔ—!æºÔOÙ&Pa­ ¨0ØC–›F-°œéd©×ä;	¤ñrfMV‹”a\$Š‡“4QßÌÑ…!¹`) dÃˆu5Hì3ğÚü 9ı'ì1’Ä“%æ‚t54TÉ”ÜA€O\naQS¶*U¡A ¯®B†”‡)Tô¼›,’è%Qp Ô 7bCê…*ÔàÉ©ãIL‰s³ŸGí}†ôÔ‘*C\nlØWgiHF\n’İV\\\\ÑÚL±µ‡\$nX\r „lL¡ÍƒËhv#f‘~\0\0U\n …@ŠA£ lµÃ2f¦	 ¤Zğ@(L¶ÚÜ[£Üë)¸W˜£IBŠÓ:Vë§BÚëKh\ncŒyjğàÉNƒdbD†ØŞ¥bø.†€€†tı|²¾ˆÅ³Â5bgñ´n©¯!ŠºŞ¸B-Í‚\\‡36ß=Ò›ëÁ¿Põ‚[š›UhØc´´DáÀs­°7£üD¿ï‰>'µü‹—å>¯œ*ºÈ€E¥NŸI©›–²ŞS)\"Sò•E'd„@ßJmÅ·Ï‘L‘{SjÌ±ÏDlÌğ7Ã¡0YsÜmeıŒÌzP\n…ĞÂP0ôe¥¡Şø*“)”Ì<´ñ´&ˆÕƒS×;´qw¶ÄÍ1¿iª¹‡Ş£ˆA˜Á´lPFÜPÖşN	‰ĞÙä7èò<PÑ%(ÄÍFRRmš‚#à()-äÙx•A»aÅrøåîKÕ¦ŠAD8÷ÛP¨BHG£7kN°r8³Y‹¾ABU	9®IŠ…ì‚ğ@¯Ì‘”Y{Dm9<³ŒT8Gd˜”C‚8ùPy:m`7%g³'¡}Îì`\0«¶·I'Óû|Pšøè(ÆæXz;MÖFŒîŞsyìmí¸·Ë„ßfQgo¡./EñœÓÂ	÷¾ã¸\$p—=”of¸&ŞàÛ‡|nMÛÇ¶ÖıŞ\\‹p^¹8(I˜ü„mÕñËxÍ%­¯’˜—Ha‘ÙÿêøŒZÔŠT°O*ÑµŠóBI×F)„©™sugP;#ıSd8\\EšC(bëõ0¶QIº?\\T!j}«u ]-İĞæN:*UFyÄIôw=Œ¤ÂAó>HÌ„ò†|ÌI„¿4ƒ%âÓ=²!zÂ8ˆŸÚÍ9w‹'İY[õÓWÔ¥},ÏÌaéå=qô¦|ÿ+œñhŠ~–ë®¬¦ˆ+z´¶´‹ó”k{¡•Èú^#ş\"Ñaa‘|V¢±«~jï“hkè€ ­ğ¾'‰Î”pìàÿXOŒC–ZÙé¬òx´Š0(ŒuL“û‰Î†\nõŸÈ'ÿ%éøÀßÓÌÀÊBœYÏ^şFì—/@ƒCœÁ/önì<Ş&øóP¢¢rÖ+à'N\0Ş%8˜ ÂßM¨áÍş\rÍÚap,(ÂM\"íøFÄyâº0DÊ/1ÂV¢Láæâ,ZÑ&p'­ÏîºKA(pAic«H¯dô.Lç‚æ¯b^âz¯e ıO¤J3f\n>‰Æ\$†>„è~Í(Ğ²Zb†¼Èû†0 \"Şğ¦ /q\rhÔ“ È•åÃ¢FåÉ9é¾\nkBÑP|Ó\r\nğìô†´k…oo¾¤ÍP½pÒ\rÅBalÜX#	%0€òi~Í¥U	ğ	\"`Àhã-P`f8#’l‘XÕpõ¢|*\rS¬ó\0ê0%Íiìò'fañNµ\"	MTaæ´¦–>J\"ã,ÑàíöcÅNä£­HÔĞHşÑ`2íKQjü#±ÉaÍşÏ'–^ÃT1~ÏD?ä~ıe8Ô(Ë‘Şd‘ãCTÌIå@¾@#êAÒïñ	 ÒÃƒ£òÒğC†+!j‘öTÒ?#¬t\$#Q’7ãâôíiò%ÒZ6’_qm#`AÒ\\=ÑI 	\rŒjÂáìÕ\nH1+ÍcJÚFm¸Ññ,\r§)®¾ÁÂO*¼æ)™ÃTó:(¥¼çf8eìğE:FãBXÃ`£~{#W1…+Elñâ*ÒäcôÚrŞÑğ=#¬º˜6\r€V„rxÓo°+\$vã#Â\\ÃMbPGc\0\0ª\n€Œ pqîùƒÆã®Nj·ê\r¬2T7¬™3N2²½4Ÿ°¶U6¬Fø^\$nLâT/'Ë\\øb²10õ£&b 8Ğå1£6V‚®3+£ğån“H@\rãÒÃ¤8D@«:„B@‘	\"Ö¡ŠâdÀ†um¢iÖÔ`¨ÊTÀEÄÏ\\›óÚÎ2îi#&\r­èb§vö*ÏÑ;K>“İ*ÏLÂG-@³šè0Àõ\0T	>Ã¨+21*,`1¬iPş3pïóÂ÷kÆı°\nÁÄı†>(¬öÄâ0\ràÄl#BÿI¤Dƒ Æ¥¤¸´Bø­'ìÍ,ªÂ8^F\r<nßBÓò_Ù0ö¢Âæ‘;D;Ib98ãZt13œ@î.‚Ô„G,Ş\n±°MN¶Í2P:æUå¬"; break;
		case "ca": $compressed = "E9j˜€æe3NCğP”\\33AD“iÀŞs9šLFÃ(€Âd5MÇC	È@e6Æ“¡àÊr‰†´Òdš`gƒI¶hp—›L§9¡’Q*–K¤Ì5LŒ œÈS,¦W-—ˆ\rÆù<òe4&\"ÀPÀb2£a¸àr\n1e€£yÈÒg4›Œ&ÀQ:¸h4ˆ\rC„à ’M†¡’Xa‰› ç+âûÀàÄ\\>RñÊLK&ó®ÂvÖÄ±ØÓ3ĞñÃ©Âpt0Y\$lË1\"Pò ƒ„ådøé\$ŒÄš`o9>UÃ^yÅ==äÎ\n)ínÔ+OoŸŠ§M|°õ*›u³¹ºNr9]f%3MÒ)¥ípÈº²ãh@2ã¨æ:¤£H!éä0 ãpòÈP¢:§§\n0æÈ#Òš1h2†Œ˜e›Á1KV Œ#s´:BÈ›FI4Ù+c¢Ú¢Ã”|0ŒcX7èô0·°Ğ@;¥ƒCI!µp æ;®£ X‰ ĞÊÁèD4ƒ à9‡Ax^;Ír?%árê3…îĞ^8JÒÀä2á¢\r«ª:¼ŒËª|öB!à^0‡ÏÚP2È£L\"¢£ê&¢ã¤&\r:‡M£è…2Ã•°´âh× 5(ßSÄ1\"hÄ±l»KG¨Np®’Îô>Œ\0Ä<·\0MaX”´ 6£jô\nƒHáAïDg²#)\\Èc¨èô o`ÆçŒ£0ÂÅGÃ²ö:Œ±Z9HÒ`PŒ‘DPŒ> Œìô‰ÅB^ÂI ã\rˆ8ì7¥#`Ø7Üb|öí2ôŒ7(Îp˜a•©´h&B+ÅG`¨ÉKp<F‚\r#ŞLVD0·È3\n7XpÈÊ˜¢&.ƒ(CÕuaTÄ£R°ıL4Õ%<9àc+Ò!²‰¢D“é—cÒ€i©ôè:7¢BƒZ×¡ê&Š®© \"Õ¨º’ôÄ\\õ®„ƒër\\<çˆ@S<şÓT°ˆşåˆâ ‚®©:õ–ª!ÆŒ¹#´ŒŞÉˆ‡JÆPúü6ç\\˜è°K« &\rœ,!/6À2ÚÔÔF\r”ĞÑ´©*:7ŒÃ7K8\nÇÆU *\rêâ}	\"Äã3N\rëÃ%-ÓCÊ<3Œ+ËŒûXñÅ¼2…˜SŞ}øİ'‘w~üR	\"b*0o’B¤ĞS¼FÓ€å-üÈÊŒI’é‚L	‰2&dĞš“`wMÈÜ¼§æCp/@eéDAD šL;Ê)Ff‰ˆÂü4Åt8”&¡ungE^¢\"JQ“É/DÄ&¡CpI“ÁOKÈÂS(r\\KĞ\r1¦TÎšSZmMğ4˜@ôìäMÁ’RPY?‘@àGäÎš†St«Í™5!­A£â€:3rÅì4Ö¨{IŒäèTU:‚›É„.Uh‚:‘ ªO5ä¤1ØÎı\nÁ!š£÷˜Fs	z/i(£ˆ0a(Fn£4FÁcY‡ŠM~sfL!†o\$p‚Ê8¤½\0P	A¶æŠAQ(ş‘ezoœ³À}¯İoš¶Æl*ÁmÄş• ŞØPoó%ËIĞİ˜+ÛÄ\rÒ¡çß™¤Í&Í<\$g·%ÔnZq™<¥”`h&¥İ2,#s&*\n7ë@7ÆCìÂ˜RÓjnx‚ñ”+qF¸’’r¬¡ŒR\r?`œÍ%fK(tÎsŒĞ\nxŠy…\"¡Î „™®¶Ö»‡&!\$ˆ‡“I/\n=DÌRKÌÓ(ÓH	*?(üÄÉĞ}ƒoêf§ƒc’6A@'…0¨A‘Iö'Ä`š/”cI\0R™+°°©Ê“-Êáy[M:”¢Sei]Måó²„Ñ.*&Š—²÷“ÃyIAˆ»³VnR5*KDb°Iën8Èø3S´LA0Aæ.<?Db¥ù/I}²€\0U\n …@ŠÌm\0D¡0\"Úd*Ì#Y«=Íµ²R‘(N™°äö	Ã1ÔÉ#PÌB©):çdç)‡j’İŠ—¥äÑ¡SÂk	é•He¼4D=Aİ1‡‚Š#jxèÄ¬XHh§5ù¼»”…Äºy½,¦£¯İ½ÊYÒ8‹!ÊF#\\dáT±\0\$ˆĞ+ôP,Ü8:‹’h1)Å<ä¢’.)òÊZªùßjL3g a,=V¬k¾2BÍYÊâŠ‚£uRÇğ=.ÜY:×j\0]æà2ÅvO,5†ò|4ãU‚kƒ(wÀ·ùh7S 0Äkr-W´…cCˆ+ÊFX#!hq­ºLüÈljé\"0–P•:%Ébê@aÉ{æs”Ôäj#è\"†æF§Eñ¢Ë¶ÒMÌ†]ĞˆĞ)-è‡Â)¨\$h|\"6àÜJ[”*†#jì.ÆÉÛLä|‚Ãƒnš¬ü8“´ª±’]Ac‚ğ@CV4•ck–Ë®ĞúÖNÄš9 ·ˆı±}	n%µ®œ	5Ê­\$:à°^Ä+»’ŒG²É&Í*øÏ¸\n s t¤ˆ>UY›OÊÎÆ{\"É,wû¬öŞóÛ»*´nÕ¯uÙ7´—o©nú÷ìšM¯‚ì‚dOhÁùëÜ\"IŠAXDÈŸ‡PÕ²[ÉW)R´€6¶Q+Nr¼yöŒ™41I+.5Øş^I¦˜bá›sb•neÆÂ\n£Sz½Mö„ıO±/?<mLhVÉÑMËß¥¡ğà².ÆBë;´N`a©HÙ£@§yo?GÊu\\p¾ÏEU=Ø‹¶EİÚ™/Áx[¶’:çyÖ¨½­Yäê&f·FV0¾ºQaŒV—Ú”ôKäÀR[üo0¼é ÚİÇTK´\"“TH`F6VáqE2\nr³a\$¾·ØùV¸Oıš!”¾tº¡şßy¼NÔêyh£% ´£ÂøEj¬ÅœMCI°:†@R|ãÇìkX7t7~÷_¿3hÆ†#âÔ-…ë¿W×ğöŠÇİ/Ïßı?inŸ»}*Vëj#ìÉÍñ€äÑÂôÙÍÈp\n8ÚebÚ üE´ÿía\0#Ù\0mÆ\r 2Ïı¯ûkÒÍeÎÍ¤&†oúø0Ö\nƒë-Pò0LÍR¼`˜6G~ùÇ2@àN.¢îÕãC…>u,^4Ãì)ã&¶ÈÄ7\nº=…¦d¶D°f¶kşäğ\"³¬ 3DSê//Šä½Oó…Ê%ã´…Ë¾~0lr'\0„Ç-ÌÜxlœo\$=\n,^mfªiÌVü„ÌËÍ&ç	¯Ÿp0ş\0@U‡Üÿ¯íMaKÇfâñ‘š¦â¿ÉÅ¯¸í\$Ú°üşÑ,îã4ıîÖSÑ.†şï‘.Aëîj`Ë¬X;MÌ£\nhMØ¸-°@*Õë2Šè¼\$±	\0QíHñpÏéÑ‡\n1DÌ`yÉ®”¦i*25†™+²9Í/™1®jq¤HÃ[	0¼óh)k± NÏ,ä?Q2ñNé.RÏk%QßQå1ÜÂÑìÎqMìñ1ø\n†œLæ×±'Œ,AD.Ïé1ß!J\$ÿ±Œí5!€	(ÌzÑ°¢@-)úÇ.ĞI…HGÅ¢˜¤¼£í\$ÈOqºDâ/\r%âXîˆ™âöF®„ë‘òP‡ì\$m¬÷ê¥(&ï­Ô&/T ¦¦e4\r€V¨\$BIF¦]O<ô@Zar˜l‚jAD|©Î\n ¨ÀZwãIè±&ÒÄM×ç(²\\8ÆĞHRå\0­€¥fĞ„&Z åŞ¾BLòìœÌf «C¸„?†Ô=cÚƒŞïŒü1m£èÎş±Ü\$ÀÂ§Ò/qØcŠôP&h6çF¯2Ú;à 1e'ØBiøİN§ÍfİO²é'Ê0ÑZícdV‚†ñğ‚(h®ĞNî“‚ÆP4¼Ç~„³”7“™8Ó³–1S†É£| £5	ìÕàŞØ&ÌŒ}ó˜8Ñ\"+í‚_å(»í^½S„Ê6&F:ú†@kŠ¾of8úf>•`Nùc, ñgJ!EÚæ)XÀ¤ˆ¥š¸SÆîdj/GK2ƒ Á¢ìi3«p2\0003‘Z\\‰É3“*ÈVv%6ûĞÜl‰8ô>n³:è² î.Ãq\"çD.Äjã[„Rdh	\0@š	 t\n`¦"; break;
		case "cs": $compressed = "O8Œ'c!Ô~\n‹†faÌN2œ\ræC2i6á¦Q¸Âh90Ô'Hi¼êb7œ…À¢i„ği6È†æ´A;Í†Y¢„@v2›\r&³yÎHs“JGQª8%9¥e:L¦:e2ËèÇZt¬@\nFC1 Ôl7APèÉ4TÚØªùÍ¾j\nb¯dWeH€èa1M†³Ì¬«šN€¢´eŠ¾Å^/Jà‚-{ÂJâpßlPÌDÜÒle2bçcèu:F¯ø×\rÈbÊ»ŒP€Ã77šàLDn¯[?j1F¤»7ã÷»ó¶òI61T7r©¬Ù{‘FÁE3i„õ­¼Ç“^0òbbâ©îp@c4{Ì53Í†T¯Ê9(“é5ƒ¢‚	(æŒB#ZÀ-£((\"ÃHĞï”#›z9ÂÂ¤0»ëèáÃi´.âÈ6#t«¢C\"\$¥©É».V«c€@5„£f¶!\0Ä2A\0Ñ\rƒXú@2ŒÁèD4ƒ à9‡Ax^;ÌpÃÆ0\\”ŒáxÆ9…ã€Â9cºR2á É¨ÆF#2RòŒi¨xŒ!òV+2Û! P´7¾4>:)c[^¥Âãxô6´ƒsz‰CmE3MÓ­ëfÁ\rcªÕ¼(“p5Ñ¢°Â9U…L„’0Â5€HK\\ŒUØè<×Ö„¹¼hÈê8£*Q P–7©àé P‚Ø#BHÜ1C-ª71b±†^¿Òk%\"cpŞ¿½ÔS#£p×=ÛC=Â3¹µPÈ@PÖ2ª\"„;@H´ÆÈÄëFMBb`ÈˆûßÁCÀéd7¼,(KÓ\\Ïq*üÀUËÆ(‰‘Ñ2šÖĞwŒ£´vÅ°N‹sQÓ+{FR&yR¨ïÃşò½Ã¢sè£FFŠz4\0ßè¨€\$-[‰#lnÅˆ£Æ¿¤éh»]”6 VªPP°ˆ!Cµ9½ĞÃX1ğP@¨*éC€œ›Ò³\rÛnö9ï¨Ò •˜¤ÀÊ:4ƒÕğb‰¬FÂMxÚß\$ß>ƒÛ•QŠD9Vâ8ËjqãÓp00”£3Ã0Ì¡FIX2E0ë#eåj„ímÜ4#H•R9®a“C‘[M'^^ôØ„£V¦<ûİs\rÍƒlø	¯†ŸxÃày~o‹÷¾’kêzÈç±íŞã_ø±‹`a_\";|ïã¼“‚^`¯yÌ¹\r=B‹kô\\?‡ô÷ŸëáéŞ@è MÕ b4ˆlc€eÌ¢©%lTV˜G²oÈu:K\\›\"çÀ€ÃªOJ))p¥T®–RÚ]Ké…1ÃrTšRl¼@Ó¨>Dä\\9‡H¯*ƒP¤tê.rrŠšCÑ0Ÿ¨–CÒjOŠp4Š™Ò7\n)5spX•…šqFII4ÑWÖ’Ü¼e (­&‚ä ”¡êVK	i.%äÀÓdFQ9&”ÖÜ<Œa¹:§vº	Úã5ñb-çŠãÈ™L*	<À’oÈÁ?Â„ÆáÃtpg¡¸¸<XºÚ½\$„à\"ÁRJØê{HÁÕ‚G€ÁñÀ]ESHv‹ƒ›oÆšhŞíQ4¨Â!¸Úq”­œ“PŠ Òã™•VèíR-|‰l8ÿ™a|^åêIF¨ÜãN#ğ€H\n\0‚yÏQ[=æğ(*\0¦:¡’\$•¬ŞH4‡b0C=\\óh”±±^æÑ”Ş˜„íw¢#åìÛI§jt.¹Ø®Ã,u„(•Í†ÒæcÈäÁ‰> Ò9	9›))&ÅªÛH.”¶qã‚O\0C\naH#HgvNÑ;oIø9Ó¶Êî\$¬!ØË˜*´8•`ò’ó8L×]+E‚ôàW9†N‰¬†MÈí7¢gS‡°\\†§h_–´1éŒ°ThQRiB{ ‚&R“Ëú@5ï`£5•ìş™°™qôåÀ Òñ\nƒ<!@'…0¨P‚\\h­½(Çöl\$ny½w”Âˆƒ0iáÔáˆÙ¼jW\n–£²¹Å˜Â‚×8 \nl°’’r­O…}@oDx*şC|é*P³~LùÆ\$­Ã\"		XC\r!éuâk\\ËY)5dõ<«bîğ*¤4¦’Ö‘ryoæ¤=\nÂdIƒ‚ø	ØT›«7DëUS,\\Æ ÄÊ°BB·díá7€ƒ—©&\$—”ª¬^ñ‰°H\$édUõ\"|q ¤ùµ¶ØV„Úl¨5ºW;JZÙ…=ØşÜ\$6Øs”CJ:-›*¡L®èôÚV‰î¡ui.Ëã/ì + 7TÓ‡®\"„Ğº‹h‚ë*6¢©ºS[¸oÎ9ì'†*ÔïğCÀªÜ*6ƒ	uŠD(ÇŒ‰Bj_ÌXOt5Ï4»¼§ŒY-AN¹_ºE_;Še€,ı£Vó¡MK³zŒu“*A‘Z ß2ÖÆ/œs›	µş§gºàÎĞ(C8©J\\­ ‘¬;õğ‰³¹Ñè\nÉşófkÒj.d®7á™z±ã	‘E‰ÈE	¶L¶oa‘A%·.ó×g T!\${I6Ñ9@ÿß“ò°NDK/áéU?À^Wßä(`­b™f_\nV'ãá5¬‡ËO4p„Bm\rœ‚%Æ•JOÄõ¿5¸1„]mĞà³Uë|&åü˜¤l˜öeÚºŠçœfÉùõ‡å|·¡e³1z1é\nuN¸®˜[s	s~Å6a¿Òr¸äˆÓ·ùÙ¹E„=—uhRøûz;f2»©t	]Õy‡xW=Á˜¼ŞûË:×€è†Ûs'‘S¤Ø’0\$œi‚ò?i—YH»Ê`'yrE¢üÉUd0*”×“W1ij#—Ùª¢©æó”Ñá””xç7ë!Æ”‚hÑ(äßšÔú>ü?(õ\"”ù&pÇà÷² J1)uç¸â}œnŒE\$ŒÚ©•^où™¿xC¶¿¼Î•‡Y7íÜ×êPõF0P'_ 0·ÏÂOÍÁ<üÚ4Ú~ŸîÉÅ(Ê:ÿm^ıN¶ÁcK\0úü\nÜ:€òËJ üÌ6ÉHLTãeI¨7‹\nÁ…Ì]ÙØ¸…†jàÈÓãz*\nE4ƒH(Jş‚wàxFUfÚ„Â'`ØU@]¥ŞeMb+)\\æi\\æ®”%e GÍp+/\\YE˜`@\ràÄe`,)èÊÇÿğK\0&¶üÇ2ËĞ·ªà\rEÆşL–I¨ËT#ĞÓPÖ7ğ\"VnÇ\0=@Üâ\n P<ç\0ü®d­ĞæÇøé§DçpöOpûÙ\0VÄœë0Šé#Jég²ìDéìÆİüşñ C‚=nµ¥Üæñ4¼ò¿kú;í–®²yĞº F·p@ÙC‰1MÑZ¿ñ`æpÚl¢yíx\rê0ñÇ8\"3.Yi”¬Àæ]bb¢AIÂ»\$„UŠøX`àIâ<d‡ÍIÉ¬2H± ˜Î:ä¨F*2–8Û£Ì¤LˆBi}#N+CbA1xÙ1\\Ù€xEjdğ<f¤i\0´ØløÅQÈY­XÕQuÀÜ±f0…;\"’!å\rÑ†ŞÆ˜8\rZ\n­à–å\0Ò/\0PìİíâQA\"OÍ\$’Z\$ï#±…†gÂH™’g\$¹'’LÒfìÒÃ:=¥¦FEœ	b*ÍáÅ2ÇkÓ…Ö<åÒA-Í‹™Ã%0¾§Pù+0ÿ&½+?’lËoõí†\rØ^ÃšØFˆÈUL²l£{-¥îË.É‡°úUP&ÃãBüZ\$DÚÄg¹#-›1\0å\".¸4°ÙÍ¯2Î¢Õ.-ªÙä'ÅM2“:r‡?&R‚7D5¢ƒ\"Ñg%R&@íõ5\nßw5Ê;6-/rÑ5¦ì\r	Î5ŒÓq<Q¯äp\"nË°mƒĞ­ªŞ²¢ª°íñ:nw9I]9ˆ˜Ó‘0°ìÈ€”#S¬4só Üè%`–#*Ô=ød Ö\\CŸ9£#9Gœ:ª;Î¾mŒÃ:n5fÊ1Q?7?Nµ?“êë³şç.Ä×å(\r€V;Â†aëÆ†âˆIå,@iú?öoNj1B¦ôQï	„9°h–ôl\n ¨ÀZ\nÙ‹à¤±ÛñS@I]F ånÙtt#TqGElëÑ3@TŸ\$j\"¢.ëD@FèlïúURhƒBş? YbûE¤#ê/Ãş{†]Å†8\rò\r(±N}KCƒo,¼¢FÅjË´Şk,\n<@¦RØVAãPVà†(+²O!{OÎ6ÕÏûnnC@-Ì£	\rQ@Øï‰îÇ4ÊOøe1({\"ˆjVS#Ssõ!Q5Hœ<¢„ıõCTõ5BF\0eEşÕEĞ*ÑÂD?\0a5BG‚‚(rN`,ÚÒ2Oå\nÂt—±¦ú\"ğ1Y`òú¢Œ2\"UoÜ¾E´#Õ'UÆê¸å(şr¬kÚ^&³QÇR¬r:â4ÉzÈ¦?]…Ü6Sµ?[ˆ¦\"4BñD1‹pª§<!ÏåC£\\Q "; break;
		case "da": $compressed = "E9‡QÌÒk5™NCğP”\\33AAD³©¸ÜeAá\"©ÀØo0™#cI°\\\n&˜MpciÔÚ :IM’¤Js:0×#‘”ØsŒB„S™\nNF’™MÂ,¬Ó8…P£FY8€0Œ†cA¨Øn8‚†óh(Şr4™Í&ã	°I7éS	Š|l…IÊFS%¦o7l51Ór¥œ°‹È(‰6˜n7ˆôé13š/”)‰°@a:0˜ì\n•º]—ƒtœe²ëåæó8€Íg:`ğ¢	íöåh¸‚¶B\r¤gºĞ›°•ÀÛ)Ş0Å3Ëh\n!¦pQTÜk7Îô¸WXå')jRœ(íìöáVÃ±º&o‘YÌ˜íÔÂ BcŠµ¿b‚È¢ãsB­O°‚2\r«Z„2\rã(æ<-æ\rÃ>1Œp²¸³èú1?èÀî4ƒ@Ş:´#@8?ã˜îı\0y\r	èÌ„CC.8aĞ^òH\\Â(»Î³Œáz”Æƒœlıáˆ\r«:0µ¶ã“\"˜ãpxŒ!òN+0ƒcj2? P¬§ ££´5ƒ¨äd3HŒÃHÊ;Ï“ğÈÒ‹ª|	ÃËBØ\"àP®0CrÖ3hhÈTpÊ„´İ:šÓ\"XŞ¢Ã(*#…US\r®|J/â`7„€ÆÖL0ˆ2ŒÃê64#²Ú:ÕSaM7B2+<\r3+ 0Ö*U‚:RÎ¦;ƒ@ì³k#4ŸºmÂÿ`‰U	ƒL\"Ã\nŠjp64c:D	È6Röm‡MÑ-PZ9Œl)Š\"`Z5¬D)>«³ìÙP³üM€Å8­ŒhK\"	î¨Ë\rcÃùŒ(ëa“\nE¼-Kø’6Ã£’k=ˆ©^jÃd,³SEÎET\"S’Ç)ëªğ¡Âë:˜¶ZZˆâ :˜Ê<&£r\r2oDì¥.Tb\\çÏb#A\\8©‹÷vªØ„µ°“Û^²dk@bObÌ^Ï´£xÌ3_ˆºO6£«ö*\rõÄ<£šĞëDC5†A‹XçÎÜ`Â3Œ+[Œ¡Órl`2…˜SÁ¸£\n\"÷µcË:\$â ĞÉ%m¶œ:Äm¬&¡QÈÇ®\r)„í HC¤‰#IT™	Iã”£)Â‹dÈ7K!÷ªĞRó<ÒôDVŒ¦ <5è\\.ÍZpŞ7_”ÃC#,BN”í¸[ğ2¿YÈGÙ:} ¹#Ä|òEHé\$;¤´ ôrPJOa«µ–¶öĞsÌÌ”ÍŞğ>ià€ç–2DíšªL&|Ğ†rtiI¡UÉˆÉA÷À•ŸCˆy–×ÜÜXâô¯øÎ»4‡Ş¸ Ef­Nˆf˜Nz›uLŞ9¬äŒó•)h­“Ö¡Wfm’Ú×L9spÊ‘õT\\AŸ\"0œŠ¼\"pNŒ¹›@\$\0[Ş	>(@RÿÌì.ÅÄŸâNÓ)dÅ4fËŠIrSlí§D2gÍYâ/„œ*†pòºKó”@fâãÔ¶MüF*\$e£ æŸE©|7`ŒÑª7Jl;†€ÒÃD\$-)\n&ÅèÄ°Š¤”Ò 3š†ÂF¥Œ„”·ÖkÖÉ‚)Jn(§îJ‰a.3êÌÏb|¶	õ#¼÷‡&:o_óë[¸¥?pÓ	8I\"!å½—Õ7'Ê¬E©Ğ‡‘?Ã1Ö%îíèLâ–Èù@‹ÉÑ)ÎíB€O\naQ‹£\"…=T÷3nÀ²ˆHm^«ª9RR˜jÊûäìÆâ–Zcú\rÄ3§§¸´i ¬ÉÂ<H!9s'\r9„0¢Hg”\n2ÁP(Cj»™Ü³¦”F[â£RB2¼Š'(\\s(—6!Kô'„à@B€D!P\"²\nì(Lµğûª„<O°PR‘…›ŸCì)šÉ-§rÈ\ncjgPÁË&«46(é[g,ñ?a53»4Íõ%EE“ŸêPHQ§½\r\"jì’mMèŒ³×üĞmrªe¡½‡5øs²”ŒÖÉ=…5TËS°\\4Çİ£Ü}á’6H\"0Q	´.OÎV.Ûuoà@‹ĞS)•âJÍÊæ8p*(ªqsCHzURAš:4ådÕñÖ ĞW2Û~Kå×PmßST`	+/éõ‹ Eä_ÂY%^á4aŠRİ…Ú«\$¡˜£mZ‹ål­Ê¨*€Îoñf#Ä¤Ãğ¡¢	Ae¬5€¢ö†Ö! Y¥1ãä`ôÁ£²Ö²DhCÚÆÕì*†®hY¾Šëd(á”5ê€˜P°p…åÊ7) @Ê²¡7†,„ª6b…1ŒOa,¶©·\r‰	Ù¡	á-ç,ğcP!È„»\0ŸL\0ĞØh=ó¡BÍB—EèÚ#\n[V+é\nA¤¡yTÍÕ3Hçø_ ‚X.ÎÀƒVgàÉ¤õ'	p½\0®äXo™)x‘òB³I-NØÊl¡\\l:ÿ«!GU”9OU\$Cƒ ¶«´Â'Í±%`åŒ8OÉÉ™rsBá¬Š›mĞ¾\nyc-ô*¢ËK‹ö¦§\0(\"†Ò.£È\rÆdvÜ“ïİşÿÃxph%FÜp`ÊÕ¸§@ÆûÇÿÀ£Í¸ÁÅÜ¹Ó™Æø×ã¤„™nªïµIwyn5ô¥.ãq/¥ü\"`&˜C,S6Eä÷ U(L^¹ç=ñ>T²¦‚OeÃÈ3QÇ“ı¸é‹È:*Õ^©ä‰°¸’ R£,E­µ|N‹hAm³#\$í™¡·f@q\$C96†Äv»yBxFOäı²àB’Œ¶ºÏ¹JvM] t¾sÏ\$oMéŞÔB–G|eÃ³o¡ÈIñ¡§Fv¥›Š&*âÛ7´ÛŠÒs±JÜô}ÿø?ê=ªìœ—ùì2ºC+ë8´Ë0Ë\r³ucÅ,qjúKÕUr9xDà‡hğ³µÓp“†ÇõïÃr98·\0‚–Cx°\n‰=Böb™åå¼JÒö‰UaÌ<ÀTXSd¬hâ Ù 17¾ë80;¿î äÖPOúàĞö«pNeÿÎHïO<Q¥‹\0L’ãë¼ïĞ\nã\"N	•gşğ=\$§ÎòäÏ<ÄJ\r2Í«|40Mä\0¹Î~`àØFğÚÀ¬Á(°ÖíRÈšXÄöò/0äğ|BP)‹qo'pD·H\$ezïè‰Ã¬D\"ş°R6Ğ|LœgÃª¸‚øOET/dòfÎ¬Ulj¬î.ô§câ{¯ZóÌZ-,aiĞ¢Æä/ğ®NĞòO`¨x0\"]lÚ>0ç®OÑÂOÚ•°—ãş3C\r	TàÒÉ/é2éPÌ1b`\$¤Pl,å«ŠKŒFÖqHDB„uQOÀ–UÜ,e-À‚Ñ^ÍÅ ‰Íó<ÖqvóQzó«pdì\r€V\reø\rm¨FùÁLé(P…ƒE\rãL­’)oÊ‡ñ¢Kêî\n€Œ	¶ Î;ãğRâNĞÍ2êÀ¾OO(B¤f‘Ü·ø§íÜ#à§í	¬|'ÊIÀ7­ò;¤ûã|!@Zp1˜…mO‘®U1¸é->€+Ú„ŠÄN.˜½Œ˜\ni®‚9€'Â0üE†¹æFåT2¶.ÌÇTaŠää2Z>k`ì«fÚ;Î6¾ò_'NÎa€àéRlã…+'0ƒE)'ş¶NÎá\"b2+á)&îH6¥Pe2[jÚ¦ÊªŞ	 Şå®øë…ìåÖ%rĞ^êÒ¨€ÇÂB:r\0ìCc¸d2¦Àì˜\"Ø³¬˜\n‹JÇ¢öF‚zOqo&ƒÒ{ã<_Bæ¸Æ´TÒŠ/êP@î-7ã#Æ\n:ÔQ>?pš&¬"; break;
		case "de": $compressed = "S4›Œ‚”@s4˜ÍSü%ÌĞpQ ß\n6L†Sp€ìo‘'C)¤@f2š\r†s)Î0a–…À¢i„ği6˜M‚ddêb’\$RCIœäÃ[0ÓğcIÌè œÈS:–y7§a”ót\$Ğt™ˆCˆÈf4†ãÈ(Øe†‰ç*,t\n%ÉMĞb¡„Äe6[æ@¢”Âr¿šd†àQfa¯&7‹Ôªn9°Ô‡CÑ–g/ÑÁ¯* )aRA`€êm+G;æ=DYĞë:¦ÖQÌùÂK\n†c\n|j÷']ä²C‚ÿ‡ÄâÁ\\¾<,å:ô\rÙ¨U;IzÈd£¾g#‡7%ÿ_,äaäa#‡\\ç„Î\n£pÖ7\rãº:†Cxäª\$hàÄ0ÀH òó\r®Ú;.,(Üş3£(#˜æ;ÁC ËÁğ&\rã:Ä1Jƒ½®ó Œƒj†6#zZ@Šxæ:„füij7íÛb‘¯Ñ\n;±C@ŞşIÃ„cC#Z-†3¡¾:˜t…ã¼Ü#QÒ÷ÁC8^òÔE.xD¨‡Ãl¦\rÃ4œƒJ@ã}1mØë³ISê:C«z:º°ƒ:¢½b²´;„ÒäKêşÛÔ¥%NïBpÊ:ÇŒ¸æ‘@P®Ã«²`æ‡ bò’!-ƒa¥¯bt’U#Èà¼\rãhÚŒ8 ¿ƒxZ\$ÀN¦øB´êÑºC’”)Ë{&Ë„úb\$\0PŒğ·´R÷Œê0Ê3¤w³ê:¹eV­J*å.ŞàRüóTõ}\rÍÌ™TŒ£Àè6TÉe¯zŞ7›Z£ŞƒŒpƒ¶(‰h—h§(ß…b)-×1<7E#][W¹NBs£u€§ŸL(cÌÛ±‘D 5¥ÈZr5-XÊ	#ls8OXŠ<jôJõÕLG£\$šFlÅç18„]ÏXˆ¦¯Ù¢AIàİ¸ Ûë¹â¬Zi˜´î£0èíC\r-¤¤m{ğlTV¦»BÍCÂ8ËhŒ´Â´·SÚ´ƒ (Ş3Ãdv™ˆ#¦6#l`´İÊÖÆ9PÉXØ7¶Y\rÁh­£`X\\rÜ:8A8.¾´ú¨ bjş Ğ\" )ÈØ=š=Û Ègr3÷}ï~€ø^#‘ãù*˜7ùÎ¡ÑúHíŒzĞİ\"öƒCÜ\rÏyÖ:ã\0ñ<w¼•œ2¼ƒe5¡‘GF’<&(p§š\"şL`LA•2&gšSZmMéÅ§@äˆØ/„~’üâ§#©PÀ†¶XG\nŒQÎˆş«è(­èb%„ø”ãZÁ^T\n/ia:÷bƒ›üD'P¢öŒÜ¡»‰F\0000‡XšñáhLi•3Â„Ø›ƒºpG0´'XdS»#ü7'å\0ÕCo[íyûD }ˆ1²o1d&¹Ô¬FÍkGç92Õ¨»MÑ9`\$Í\0BœdX’M='T(€ BšJ^Ñd6©8²oKµ+d½0¨BÍØcQ\$€è†–>_Á”PÒT20Â¡`	%`êNİÛÀ—¡Ò\0ÍB­Ö˜ g\rØ¨’tàL¢;9#Cñ¤ %8:¢·8KÙ±G‡†OC\"gŒ¥”ó’sIÒGAAQ ‚0‘ğäh¡Ñ3d€½Ë¢“C o˜%ÑÚ®©‹0Q,Y)©\0007”ùÚf,œI†D6(’X€á•m¨U\rW¼¶;Å2W£Cğ¡¸ !Á,'¤d—Nˆh+Àr¾uˆ4Ö›Q”ÚP ¦‚0 \n¼Ó\nF/))E~( È @”<Ï0ğQHœ•gGƒM!5Tª:2rMŞô\n'Å\0ƒRôâ‰ÛA«ô¬êÆ\"_Ã“M.*?ƒAH!f+‘Çï\"MuP§Ş“•ÓV¼ì… €–EOMNd„¨­£Hh\$S½•3œ™…\0Â -]‰,››8‚JSÇ50 =5›l]G(å\$¥·Jô†!}Ã´Ç~DO\";cW1P£Dv½2µ\nQTå03îŒ5”€Ü½à(Á¾ÓFQl™¤ÁG*BEÉQ4‡„ÁK¾¼\r„©W\"‚5dKo‰˜F[Á‘¦±xˆŒĞ04À¹à@B€D!P\"àÒ„B`EÂ‹*’ØàÂ´Õ‘(ıu§'‘[ja<8K¶	¹=&L'ˆı^5{sÑ­æÂZR?Ô’“)ÒÍ wSÅ»#’X>GB50;©S”YÃKM+…gQª<Ë„Ã†–ğr–©¾WïÀ¯k¤®•D1@‚}ÙøtçBLºK]&X¬çMØlMYì ©³€B~\n#8¹T›l¯pŠE¦A¬¹Ÿo–º“%™TR¥˜qõf1„ÔêfgTgH²ë…QèÕV[Ú¡\r9ƒX›,ÀĞ@U°/0ÿ\0 ´í€PÃŒñy`<\ndpA;¨LF3&¢ù.;@¢’Ã«²ñ–ÎmÑ”†)a\rf6†PÄWUı`’¤¨%uÂg#2İÓ,§ü‡rÛeÊøL*†\0q‰Ìİœ:8’#]¤+È”–úûuàS¼î´3YF¦şÁybjÙ\\,R­ŒÑœj|S‹) æKLj†·³ung*B˜tñœòB`Aªg(2<«–qş]ÈLï1A. âO\0@ÊÖ’†kÂƒô®‹Ä™O6¤ïš/ª‡Îa';\\Ïıõ\0[Ô<§w“s¡yÊb“å/Û,“7ĞKû3®0¢_Ö»¦\$ÿyö`¡3CH_¬é\nYnt™®+ò…Ë­> ê•RLTr‰&©¬õ˜RL¸j<B‹˜Àœ§+Ú–S¬Å†‘W(‚ƒÎ)Í4õ„XuÌFxî‘í\\—xŒHi¹Cs”—}Â¦h>ó—+†çïÈÜìÁŸ(6Yò.¡;ÿ7G%?og/Â+rÛêûo¾[PÆ³f½|í`whrÎÉ1®Æ_×)A]]}¶ä.‘Äª'[ã\\B¯ö1EüóÌDIH0ïø^Åt+f¦[lGâXûN¶Ö¦* ¨YÌ8( P	ŒT,BÄ‚gO É¬~ø†ĞËPF5è>BâÈÌ²B0FlÃÆrûÏ“%\\ønÒâ¨Ì=`¤İÉÅn°ínVzúoÏj/îdè‚d0~?‚úğ†ëPŠå¦üè\r	N†ŒíŠÏÌ#\r6‚w/¥°º\r°¾%PÂ0lø0rûpÏ\r,Ù0ÚüÊ\0¢Vƒ§\r~0\0æKO:¨Ö#¢>6Ğp‚BfàèYÉh #ZGéZDC™,™íJÊzpb¢®rü¾‹€ ÄÆyë{‘R Û±>+'À-M|vÆBlF¦hfœ-‚Øf‚9Q4 Í°Ù£Ô1…D¥IppUì¡ñ’úå©¯\nTpëPïq‘Ì®Ş\"Šİ«¬¡€ÏÑ”û0ßqÅ‘ °-õ(j7C*7Ì®\neª\\Ãà#`	IŠjlt€*ô[\0æíhÛ¯1İï¬ûñ¢Éâg!p¢ûñ—MÛ\n	ã1Ùeä^Œ\\×`ËÀŞÕAR:@R?Ã×\$RH:²T1.ÿ6‹ ¤2 Æ\reøşWí¨ÚC«ŒœúilÚ-­²+{(²4Ë›(„y\$Ò’G’^×Ã¦§’PÜä)­ìC„y(¥+nE'ï¯(ğ&CR¹,R#q®\"¤B!`É\"òÜŠæ=`®\r&‚S(šXèËä”ûœ\räŠ,=/rFIOq/ó&`–wfiÖ³¬>DÊRóƒ´`‚kL'p´ænŠe0\r€W1jÖ“`0£°öIä‹+ \$‰Àmã,½1&‹ ª\n€Œ p4 Ş‘K¢6‚&p¬ö|úâpîï·0n@íòÉ“†ïP' €î¯ªOº%Tğ+â2m„ØƒRÑpO`;/*?nD·ì˜Ü\$\"73J0Ïbœë¨²3U'bf‘¨ÕãH#'bEkäBÉÎ-Ç:E˜sI°:‚XÜò´üÌ’öâ:‰bàº PSÂd¢H/¸ÕãH¹šÇ@ÈwAp„ú”)Co€@ñÀ\0PïBM]DÉ“\0Q\$u)ªªÂÅ~\ngŠ§4=‡ıM@x¦2âŒÒ\"—€ñHkˆjl\$¤x0¨!NÛÈ`ê5âäÅ¢pÓ\nR'D&Ó¼22ò5\$´.	”4”S İB\0ËÅ¸7e_5ğNB4,!FT&NÍÌ=fWCÍ\rëëjg\"M2vO@BY@b84•\nBö  "; break;
		case "el": $compressed = "ÎJ³•ìô=ÎZˆ &rÍœ¿g¡Yè{=;	EÃ30€æ\ng\$YËH‹9zÎX³—Åˆ‚UƒJèfz2'g¢akx´¹c7CÂ!‘(º@¤‡Ë¥jØk9s˜¯åËVz8ŠUYzÖMI—Ó!ÕåÄU> P•ñT-N'”®DS™\nŒÎ¤T¦H}½k½-(KØTJ¬¬´×—4j0Àb2£a¸às ]`æ ª¬Şt„šÎ0ĞùsOj¶ùC;3TA]Òººòúa‡OĞrÉŸ»”¬ç4Õv—OÙáx­B¶-wJ`åÜëôÆ#§kµ°4L¾[_–Ö\"µh‡“²®åõ­-2_É¡Uk]Ã´±»¤u*»´ª\"MÙn?O3úÿ¢)Ú\\Ì®(R\nB«î¢„\\¥\n›hg6Ê£p™7kZ~A@ÙµğLµ«”&…¸.WBÊÙ®«ê\"@IµŠ¢¤1H˜@&tg:0¤ZŠ'ä1œâ™ÑÁvg‘Êƒ€ÎíCÑB¨Ü5Ãxî7(ä9\rã’îQ™äj ƒ–îòA\"µ¬Ëâ•·é‹ÑÑšO9Â¦sLŠJéŒ†M8l(]43Á\$%ÎŠ¼ÁOŠazá—©ĞF«ì©,Äâ¸“Â‰Yn—RôÊa,# Ú4Ó@2\rã(æK£¢<:„Ë[#ÇYu`Î5xÂ:#Â9Œ¡\0î4ƒ@Ş:×\0áec¼Ê2\0ybÊ3¡Ğ:ƒ€æáxïw…Ã\rUVLAtÊ3…ã(ÜÚã³m…áè\r³-›VÓ(Ûc#xÜã|Ù5p¡vg)…óÔìQèzÂğ\$Pø–Xö/;äî£äoÜDµ§‘;:Šd™4–‹e™¦\\ófSVúÎ)B@Nãê¼‡8RBg%B¸Â9\rÖ>Œ\0Ä<ƒ(ªjÚÅeKNÆv/!”“N]<M¬g‹Â…üBö+‰Z6-DF—æ2C‘¦\n¶,¡!ÊZ¿Q¦5˜€b»=¤ÜVøA0ÚÖØ\$Qq¾7órBğÅ¶o6'lâ¬”Ò|£µ­´)IÆuBgÛ¾€¢E‰lŠm©9%»H‚Îÿ»<öˆ·°‹P±»%CÅ=¢xå7lBøf[ò¾V5Ô§‹ÒæÑ€¦(‰‰±=»<„™@İäK ì™kÉ—ÈPQEÈ‹¾Ë}ùçã™ ªüÜŠ‹8H‰—¶×\"SPò/®ò\09Ñßú}~‚Pè”UÄqŞ&/(€s€CQs‡ &Ù×\0Ójt-–3XJPa J12–>¤Îê•£²ÅÏ‹Œ¤9¬Ø³K  L©„7Dh¬â\\I¡á¬†å‚Ãƒ™wE©'´Bî¹+>\"¼	rºß_'Ü§¤¢’i© ¤Ê\$BJJT3ÙˆÜ˜ÒVBA°:\"@\$¤	D±Ì„å{MQSM³ˆ_|’TÊm½8‡FWÌSŠ…0Ü˜;÷HßJ¹ \"EøÔœ¢¶Îˆ)‡nò„Ø@©&Ç\$°‚[°ØÌ¸c#¼\$zè:FÄ—V‰ÀŒÀ&OÒO([/xR”ŸJ*h Y5R¼ŠËœö,´’)¦MË™v¤Ô¾Mr5Lé¿1LÆbó ƒŠÃòË|›nçù1¸şŒeJ	((x£@ĞRÚ³a!ÈU‚–©Uj´9-ĞÇHdUË|.Æ¹W:é]kµw‡uâ¼è¢öKá}õaC£\r_`ˆRöDƒ.â^!	Hä\n})“E­ÓVOÒDlIâ:Ï©Âjc¡.˜D{(§i×/J5F|K”!İ* ›¼¸ä\\Ë¡u.ÅÜ¼•^«İ|¯¸§b½3`pvgÂÃÊÇ¢”äô„Rš)e>*@¹«2š‘‰qp°ÇÄÂ§ÆL*‰*Sï‡—vDEqV1VE£BøÚ`Í¹M¯çÅ\nJ5%aì:	“)ØÔ!Clá'Í?\$á(j\nj½¦,9gØ²Í	Š…†ĞÊÕCfk409‡U|°0u¹°7†uYqVˆ Z+6\"Üà@×\rÔÄ0†È²H\"Ù)%q¬Ù•b™#Ğä¾\$¾5“ôeaŠyÊ4dBFÈÀ-›¨–² ¯_‰~ŠË8P	@‚è\nIrš>Qpû\"¨òJK)Î7n[”`†Ã•hc¦4Bñõ¬ƒHv¸Á”3Üõ¬®ÕœDV½^\\t½sØ¡ID¥‹Í3¾œ/ô:È\$ƒ¼×“oÓVk9­u~.ıãË¸8-E¬¶Ğrj¡Ü4Æ«ëäŞ+yÃ\r¥İ%(‚\$ÍC´FE|¹0¦‚44MĞ†\$ú¢ ˆQ4vçz©Nêª3…)«9ÄQ’›«`àˆrur¶Tó@VLë­µº’Ë“L.c§rßá@^ÚDJ®vØ)‹{Üİ•SP+B¶.Üça?n¸¨ÍR›4cè&¶LQ%éEOy¡³&fyÇ&7°“µ†·	‹–³~Yñbì\\Va’…:20 \n<)…HhîæÓÔ=PoU[R`Ú¢ê}eZÎ² ‘P:¤C¤²_Æ§ƒÖºu<ûÊÍP–J3£¶–Dá J&íNË^wãùM{ºÍ’†øL±ˆœ‚\0Œ0¤lEVKb¢·¢Sxé×¿Û/šá÷{gX®6S¶¤p\rÒv¶ª\"°Q{Ãuğse H’â‘„é.\$N9Ç\$p%ú‘\"êXgSÈ&\\Ú8.mØöòtgA”P	=1æåªA­)g‰)×ÔYë‰ˆ4ç'‹?7gÙÚTm÷•ç†!]Õ˜h-¼¸I¹W÷¤¨|dçÈñØ`k<©óe§)¢ƒhJ!Èf§P)Ç)Ë.–š~.	—q?iı®æHĞ€Š„”›`¸½bY‹±–7íS|…NÑÉA \\ıc‰‘‡~<¡¢›¥Š|R½ûÏæJØÉ…ê[_Á·C1¯»)'`êÆò² \"h÷üÈá,(]>†¨ÿ`oòt,ïğ×)Ü9Ç¨oLNéë†\rÇ–h>§v,B„±HÒ#Ìğm2!axQ/ø‡\0”ˆ8Éx&D”IJqKV;+\\poDf¼{¦÷†JÜGÕğ>èQÂ¿ê~ãÇ¨Æk`ÃE'´ydÚ„í4\"‚wÆsÇàò°^ñ~ñcj/nŒh¯E’;têø&DÔ7h|ïÀ30¨<ÍBdÇt³g«áœT©(Tâ_	„z}â¨pìnO\"ã–IÖv‚hêGcÇYíÎîĞ°|ã¢”hÎèO¤şfì\n@‚\n€¨ †	\0@ êL@ÒVEˆ\\LvVËªW%vÈÀINFî)/D4J®yF¸à@#&ª9b®÷¦·®9æ0€BòBÂøJ-îâ…Gå5â\\öá4—h|ÀÂ„ü±¢˜±„Eoz™bğ,kO”´±œø©Öø	Ù‘¬5q°+Ñ´€‚.qº9‹@’èñÃ\n5g¤bˆÃo*1'£À‘Ã¬˜éÖ\"	&~ÑÄ&ñÈ”bZk‘› ñĞÀ£ÿaœQÀ)¿qnŞ’\$Á²)²-a†¿ø“„†Õï¬€ËyE~·¯¶ŞÌÑBnõäè©X0ˆÁäÄTÉ/µ\"ëZ–b%Ïòr4.24äO\nÒ~R‰î¿ğ¸h\$øÓG?Bù'â’˜Îv=Ò¶óòºè%œ Äx²U!«'È¸ï°@NCVF\r¤™Ñö0ã¢¡Áœh’:³OV\$âÖsË(ƒkO.ê*Mé/é¿00Ú|á3!-ì¬Ænîòs3€Kê(D2…,\"¸IèrŒ¢ÜiPI.\r,ğB÷Ñä!\nw5pC5ÆQ(éGfïAk6°:i0DIiŒ9ƒv=‚,¬J\$>…ĞjŸ§~çÓ…6S|öÓœs“ Ú'ñÃÏ7£ÿ:Æ;e:1ËhX?Ó•;çlîï<#ì†&ğiNIÊÓ©)©¦–§\n,&p„ÈçP0Çæ{.'¯@h2¨91í’)V‚-pÏÅ\$ëNÃB×CTÓğœ<Ë;Ï‚~ÆÌ}óÈ/ç²M†ÀÉ©DÂ¢^Ÿ‚Ì3Ö|“ìÏs6¦isªŒ¨iFò >Ø›ñÓ=NÊ%Ôn0Q\$…d7=g7óT~o‚“µCë\0w“Ò5òRü2à“ªxe£Ø…òĞ’*à_¢­ô…\$±¹&‚Ÿé™K¦uKğL-¹\$”È!Ì~.#1Ôt‘éMƒ›4P­q/M¨Bc°ït5´r÷ô‰Ppªèµµõ>î•)®øgt«I”w1hè’wµ-U0U;’ùƒ‰DSi9’ü‘ƒüiÆÙ	ÃˆEØ¤×	B’fqŠ´KNâ„%õoH*ÛÃ³2@MCÈ¨É¸´\$&´ƒÂ4qú•Œ<‹‹ğyíx¶5eEµz&Ğ—WU†B«eHRÂ-’TN3T’\n†­ÑíÀƒ¨ITpROuL”5QsÄCåH™1ü€FÓAh8\$MÇQĞfe£w\nu»VgâOºòğ[RñCËVDTCJõ_Hµ€f¶)Q¤HU!KUc¶&yÍUhEõ]IÈP€´•£ÃbPby‘Œ,‘)\"M&tp5©R6q³¬´§bôAJÔ›!‚õg1g”‘fYe–5eĞyhÆ=@ëai–†€\$ñ6¡ehZçUgbm\rÒ…\rB(¶èâ?ÖÕ#3&œ/Æ&-Êà+CãQ'›*‚y+hyÃ—d´â&4çg¯{GT‰NW²SgóS¶[6´½pUßiï—l=R'İ;–°ñn’€1(!Ä=×3@Ğ€ñn|¬kót6»<¶ctúMõ§@ş—WeW?l7N‚×S<p6ÉT<*ß/1Y¦ÎuôãäLÙ3¥g×\rd•@:pÔH3ÛbÕ8µ–‘Sõy¤O·)Yw-qğ›jĞ~ô+P'ğÂƒVÍk×+vè×(÷Í+5¨ù6¶OšOÄ­2	ywdv71.ÖÕ¹q“¹c6“9‚]€wù<—m<÷Á#%wR•zãş\$Éˆ^ynò'äª÷J@‡®éC/­o¥Néâj²¶úÃr!j™‚‘„ó+…1·OÒ\$ú³I†	J‹FÄR—5„&¸*õN\né˜L5Ç3Ğ4™´ÑO’/\$\"›8SqC‰ô‡x¥5†”IO\n³Ã³	i\n\r€V`ØÌÀÖ²÷B\"\\Ó\$úæ•mh·ª<ãÒ–”ä²Å2¢Bîf¨RˆAx&à·Hô ª\n€Œ p(ÀI±ˆÜ®lfó’HI—ˆq‡M±Šwó’“zE‹|ù1ôÃ„RÃ\"J.\r²¶|oè‚àÓÜÖ7Ã÷6ÿ.òD®ÂzBóFCsJ|ñ.o0ãc0Şcà–ÉË\r‘i—R‘wc¿C¿—G˜Ynç-´à.	EâîÒ¤'\nñiz%Ñc56ÃUâRG´v+*o´Be-Nµ”yRF@NŞäjÉ\"!t·0On.Y_©¶µ0†&ÔÎ…Vü>“ÄÁt8m'UÇHöÒı¡SŞš‰?u6z#¡¯¢¬ÖE£YCC´ÄJwæJ+Í\rwØ‚ÔG_š= ˆ.ESëf±ĞOD>Ç<=gof:R6v‡5ºv‰]¤ÉJò\"x(3æò4aPqœº‚\$Ú˜w²	6PYD†Î 5há+RƒGÙ73ob¶°1‰¡\$Û‡3õ\näw’Hj®Ú£…	s@q%\r*DGoR[ Z¨*Ä™',IN0ã—œltJ.òS]°¸‘§:15±@ŞÄÄì`k0¢ˆ3ZšS+‰ŠË­ÿbH/Rb”“ J¢ù1ùcq·Æg×ˆ}á\n"; break;
		case "es": $compressed = "Â_‘NgF„@s2™Î§#xü%ÌĞpQ8Ş 2œÄyÌÒb6D“lpät0œ£Á¤Æh4âàQY(6˜Xk¹¶\nx’EÌ’)tÂe	Nd)¤\nˆr—Ìbæè¹–2Í\0¡€Äd3\rFÃqÀän4›¡U@Q¼äi3ÚL&È­V®t2›„‰„ç4&›Ì†“1¤Ç)Lç(N\"-»ŞDËŒMçQ Âv‘U#vó±¦BgŒŞâçSÃx½Ì#WÉĞu”ë@­¾æR <ˆfóqÒÓ¸•prƒqß¼än£3t\"O¿B7›À(§Ÿ´™æ¦É%ËvIÁ›ç ¢©ÏU7ê‡{Ñ”å9`\rçKp‚†KòDÿµÏàæï>Í+œİ½®@Òçˆn è9@IØÂPıµèè&\rëˆÜè7ÉS†âÂˆD,ÄŒƒjÒû?Ã{Rªˆ;X“F£Æ1£(òÔ–¿ÍxÃ\0¡\0î4ƒC7£kğæ;­Ã Xƒ:ÆÁèD4ƒ à9‡Ax^;ÌpÃÅïÊÜ3…ëÈ^8IÒ€ä2á”\r«rD´ŒËrrã8à^0‡É ¬4mÃÈ=7ñ:ğ´9ÀSË7èÌ&:Œc¢°,\nÃ¥M*N0L#ß¶¸œ:Œª8¤„Ñ›+¥+BÕ„£\$\0<¯\0Ms]¯<\"6£hİŠƒJ8B#k¸”ÁàP’7leœâ¢ãêş'ÅŒáB Ê3[Cdj;.ue\$Âl@Œ:ÚxÜŒ±,[çXN#1&g¹ÎjD„Bâ|æ¼±\nç9®ÛŠ2Ãï+»-R@ë]?ëÀğş11İûWxØ›Èi\nÃÄvhÂæ¢â˜¢&SÍ(İ¨ª•×OTéT3\"ÀìNÔÕ8ÕX†ÆR£ˆ2½h–œÃp5ÀÀP Ò4Õ„<\$¤«rPİˆ£ÄpÄhã-Q’Âò\$j¢ „2•‹/pÑ*D¥înèÓû¼*Ôz<â3Ï„\"¦ugnb0Ê—ÊÎ•\nb5!<Öˆ2½C,&a•°½Ìİ³LâR‘\rã0Ìò^i †ûB‰R_Ñ¨7§¹[ƒxQ1Üz3©`Ù­#œ§	¸#Î0­. A_ÅÃu¶2…˜Rš\nhÎ>½0¶=3ÊÑ8@a•*øš\nŒóâO©Pëá\0ƒ4^c”§£ÊU*Ê÷´—ò`LI;¦gè}rkM¡¸°^¼“¨>Çµ(%öÓµ8IL>¦Iä&G%…–ôl3í¬=%ZLÔ‘~/©84”L€p\r&02ä¨•ƒBXéu/¦Æ™S;Ñ)78òààbv`ù®BµÃ¤Ï<,ä’`P '!­=£Pà}‰Êó8¤Ñq#\$¥ùF™\$>5\nˆÓ³^Deô‚ˆ¦W’F4Ä²7³ìûVrº!š£g}\rÂx…é#¤“bÀÃ\nÎ8Pğî’æfy818ê½²*xW‚V?ñàÈ8(öF‚€H\n7¶3\\FAJ% ‚cÈáho¸â/9\0Ïá£&-dÔ+¤pF”D-?dµ‘†ğîr‰¢\rZ\\<‚ò.áQ j(é2.ZN÷qä¢>ŸdöF’´ÀGIì½\$™èÚM†ÉÉ]r`KãpKˆ\0‘Oh6ûÊÈ\nvñ½0¦‚1ÔÄòè|•`l]	\0¼šS~ğÈÒ#¨l–H‡ƒ6ÊhxG†×UÎ(i5Êén” Ì;]#Qœ9\"ãŒ¾!ğP?°ˆş­¨°ßI I\"aäÍŸêe4‘©9¦Æh˜Æ–@á	\r¯Ê’©ì^§ ¦É&š‚hxS\n‰”!²rG“91l¬¡Ãå^¨z0ä¬½É’tOÉ-X§p‘Róc\$§Â¤(¿ ÑJ&–˜XğÓ:@oÅ—ÈBÖA*K:èHÚé'\$hÔ3U¢‚`BÌœ&¦'%Î*²€ Ùà‚Â U·¡\$-¤blğB	áH)\\bêCŒj6åb¬{Ÿta`P%\$/Ä>qŠš€('‡øq¡Š2† ÀšTJa2¤½Äµ\0†£M*Ç7lîR) µuˆˆaRÙHÃ)äz1Ú<q\rıüj7ùf4†tÔğ:	ŠÆU àC'*\nËA» rJˆV>ÎÑÅÀuí¢b§Rä­)1az?òÆY”«¾B›1Î8G¨7âü|«íiT07Û©LB\na¤=1›>ëÙÁÅâñßŒnÂi¯>ÒyEœ«BvÃ¸\nPê…6€0Äk\rœ¬İ ›÷Aœ—£.åp©bÒsÚŸ1¨Æ¢kg+OµÖŠ!8!DzW³Ë%RÿF×À­Ö®ÂâçL¸FOÈu«b”yNâ¦l pˆÏ°«f%Sh·œğ‚¥¿	\0¥–óRéê‚6âlSïQI`e\rN=m¨×ğIAW@¼­«öâeƒz¾W[]Y/“«´1¾Ë{4×%=¬¬6Á-Ó®¸‚à„vúæÜ;3Í¶÷Fİİ[² RFÈédÛ+zõlÜU`=UÒ+ú¤£×âV³¤cqnGÃ€weGİÜ:Â/ü8ŸÜ{ÔNœ&l!Mâ­}ˆÓã'5<UkÆıš“%¥jIE»ÇE<¨“,ã‘ä<›Ö6“şuQn1%@£&‚i‘ıšáŠ.^VoÂLf×iØ‘ŠÀ_ºqØùvuÙÍ\nª?b?Ğ\$+ü[×#â-€åªƒ‡•y•VD®üîĞŞ[‘=Tº\n‹wıòCÃÅ>Ø¢eûß0Y®}\r¢1x5Oä³oZ¹0“B-t.”«JÊ¥˜Ÿ¾•ˆŞŒ§rrù<`p‹„;®-õäoÉ3V%>Úô ?vöÎ×54ôó†x\neEeJâfIj9İ}ål›uR°(xlVñ›®c0#<Ã>@¹yŒ\$Õ)Œ*ğOÏøüßíü7õŸyVpaüÀLí\r“gï.(à­ÊyíğïBße2İ­ŞÿªÚäN,Şã&ğêF‘mø#§\\ĞëjXïÈXï0p,cmJÄòşïåˆVÑl|&ÿ.>º…Œããş-Ìö/C‚aH(éÈÜ/C¬¤Šdò/e”\rä¦(i\n*Åb‡k,/O¨0ÂD;‹¤•X¶ğuŒş8\nøÛ\nK	ÜãÌ…P–.ğŒ'æ|€İ°Kåò„ÏÂØiÆŠTD7mÒL†XZÍL8şÌêó×lü¯;ÅF“o8uÌ,ÌìØÕmS.ı±†™/âğ­g A\$!±0ZBĞ¼æB-%˜|%ƒ†\$¢BkèÄee>ÍJ\rêVÕÁ.úğ¦¯ÍNEÑ0ò†kñq±&†*hG€^/‰opÔÃæ8l£®tÖ1“Ñ–aÆ8ú.Â]Rj ì£-¢ÑiäÑÆõ°4†ÀÒ¤Ï/Í%--NıUñÜAeê½qç±Ä7e’i…—mhAñğ²ğPE …›qàğ /ÀĞïå¶.L˜Zƒ•ˆªÔãvWì?ÉÌCeê.6™ÍLœÌ\r#íûŒ!#%t2Cø%è\\QW%BVi‘P¢Íµ&h±-×&ğ(Y„&\r€Vœ–€Á+BøÈiÂ0î äxbhË@\r§ŞF©\ny ª\n€Œ pëÃ†0bÔ&Ï\0‰Îç,ošÌß£5\"8#Æøµì¼ëôÆÊú/¬èÀòğÌœ÷¯K\rãØ8ÃÈGŠø/-2j-NçDšBÓåDÉ2r~D2O/BHK¾LãÂjÂ\nDD.qEÌ(/Q-äÈQJŠÇ<9à˜ãåR#GÔ î£).\"–è0ò£J>ÃˆÆÃfÊ/&/®J–©=62•‘dò\"ı7¢C5ĞJÃ\$–£ŒEÌª‡³†ãã‚TBPrçŞhKüëÒîÖ?ƒvb‹Âbì&ÖÄbÆ0\$P’G‚jKÎ¥Â>ü\0Ûî@êX«ÈùĞà{ÓøÉ‚J!„7e^Ò£vîI<ì‹æJÃ÷1†Õ<¦ª½±XB(t1\0Ş½Àî-ãº1Bò5Âj>¢XO2v:…ö® 	\0@š	 t\n`¦"; break;
		case "et": $compressed = "K0œÄóa”È 5šMÆC)°~\n‹†faÌF0šM†‘\ry9›&!¤Û\n2ˆIIÙ†µ“cf±p(ša5œæ3#t¤ÍœÎ§S‘Ö%9¦±ˆÔpË‚šN‡S\$ÔX\nFC1 Ôl7AGHñ Ò\n7œ&xTŒØ\n*LPÚ| ¨Ôê³jÂ\n)šNfS™Òÿ9àÍf\\U}:¤“RÉ¼ê 4NÒ“q¾Uj;FŒ¦| €é:œ/ÇIIÒÍÃ ³RœË7…Ãí°˜a¨Ã½a©˜±¶†t“áp­Æ÷Aßš¸'#<{ËĞ›Œà¢]§†îa½È	×ÀU7ó§sp€Êr9Zf¤Y´Êb‘Î¦ó~œäÀ=ìÛş€(L3|7\$Ë8 0¾( ú³ğ€B`Ş¶\"¬	Nxë ï²†AğŠP9 ŞÒ³£¢*Ô¥c”\\0Œc;A~Õ®H\nR;ªCC-¥Hæ;­# XøĞ9£0z\r è8aĞ^òè\\’:Ïx\\´ŒáxÊ7ã„\$C ^)ó÷(PÌ´£è4ãpxŒ!òj+\$mã® PœàªMû\n¢jšˆ³‰«ë~É\$ƒ,\\\nÂHŠ+…+â¨ß¶(j9G´Š†µB¬~„CPÊ\nğdí\"Á*ü*@MtW“Ø+<N¢à#Ãƒ¸7¯°ÜÃA{ÍfP¬Ù(J\$Î2ÀP‰(Œ#­®2C`ëY»¬’.:½#¢tşA%Z©L PŸw,ìÑM%Ic\0´µzÎÅ³)íÊ:B€Ò4Kø¨2§3ÚT4cZŒ¸4væ.#\\CcL¹ ¨¨ÆÆ0ˆ˜ŠšíÔcx1aª6‹ncxŸ©£rÿJÔqÓêû¿4¥,Š”…%VBÌõQU(~	§H¨Öñ\r Q†J­y•ä\$±ŠJ©Î¼³é!†Ø.O	?6ğ„b×P‰W6IJÅ>A» KHA»Œ£ÃX7]ü\"k‡â#Mä'£n`¦°w“ºÔ¸6ãœ„!©ÂÑÕ¬/Km¢#Œ¶cÅ¢+ı®ÿ²¬¼”ã0Ì§©¬-%Å Øß.ãŠÆ;7:Æ±¸Íq/º9Éz/‚0ŒèËÚ…oñ\nó¡@æĞnv™yS\n:ZÉë\n£pø*rNñB•„LRXÇ>§HdQ&³„¤•²XKIq/&N˜Î¢fM©'´ú›ÁôOL­?¨hÓâ} gµÍ†uØUZj\\Ç´6<£HÙ—;C&\$2“RnaOzE1)°»€àÃ0.I‰9ÿ%4ª•ÒÊ[K¡İ/¿4Ä™ RfpIÂ¦ôâ×YÁÒ\nƒæş@±®\räÓ»ö¦Qpp=éä¬ø.b`Êñ%A”ÍBxu?éô’”ij@\rŒx¹Æ°äûO±!˜Ö\"‚„ñLKÇcĞ”¶G”‚i—Ù'Ø4 C{©\rÌš„R SËÑx#)œ2ä\0‚_¬„²šÄ0iÔÒz*€€(€¡(ƒL¤;Æì‚÷\0PCO¤ô1§´sC 4Fƒ£ÜH-GQì7‡xrØÀk-)AH¹|¢ØT_iÒU(äÔ“©í5Ä¤…Í’:IÜ³0ĞqKZUX¶H²6dmxb&%L0¦‚4!&K±~¹ã\r	Ã²x…ì4Ââp~‹q=\\xßÄu*é–1ÒZP³Ú¶Rz\rğš„’(L²)q@‚i2\$%RP0¥câ_ŒFE¬ö†8J`éÁMFš…\0Â -mjh”§™õˆÜš[\n® ¸GFÅ\"û¥P©Ó2\\LzÚ¬mğ•€àL™†R˜²3Úja,n7ô3‚\0¦ÉŸa¢\\¤`©-Ô:uk¬0>Ê~EP\nbXd¼—Ê‚^rp(}¥<3Ö¦U	d¬2Ä¹–ğ\0U\n …@Š½­@D¡0\"Úå†±Lº\$ì%I7NZ©¢	a,Q‹eLÊ£:'Lê’…çá\$­Æt\rù2\$WP‚¶\0ÊCa~.±Ë)¾Û	óiíƒÁ¢ªI’.¸§~Y»G*›PB®ù³Şv€…ñ]Ğb[¦ Ãæ´tÀ-¦®H]éôEt–bĞ-õ–’ØâŸÉJ0©H0h”ÈÂ¤U1	\n«•,{F©Kyq´®|À‡¥g0šëØ@bc¸w3;OL™œlAÍeë\rB°æÒ[ÂI'.}éÒC	Ê‰1íŠ!C•fÒUTWŠë3Z6-l­gXL¤R<Åˆ8(uœÒ%C™Møp89¹=†¹zIá\"…%BÊEá:v®É» ÕyºxP¬'9WñõkRô\"…`Š‚ ST(B@ß³PQˆAå@ÅşÖ…@‚Â@ §lÒºúhQxp™Ó_6£µª!(Â%¬¿«ğ^-W‹^¿^ùMÙFIa´—èó\rµåË¸„½„¼İŸ§qTDÕ_ç¡I¯«íï1kŒ5¨ÃC¶\nª¾™¬«_¢íÕ|HFîQ±Ğîo£Áu‰:.>!Ü‹Â7HIš\$«×ƒ8¢StŒ'\nA4j\r52}`¼¨fl#ƒY4Z’ÌWß¢4îó~ÎxP…éKÆÃÜ.ù‚<k¥K‘œ‘ÊùÃŸ&ùsAæLÉ¸Ìj¼—±BÎw_9†|[=ó`òkŠå\$“%h>y8wb²n÷ºRî´\\t7æ2!°l÷uİñ|òaÉ÷áŸŞÓÛ½×Æ*Šhª8|ŸÙ;‡_ÃİÔ¸v»ƒnïËA?gô´äÎŒrHã¹:x?Câ\"¹AhÈ©a4o<¢ø^É§¿4ŸHåº,á[=îÖò‘:5—û·v[à¸ÊQß™G,5È/M·=õôCv*Q•N¸Z’ÿ·ŞßÙ·f@Ş©D5ñòqqĞÇ|¶óOÏÏõıê-÷ÏˆÔ;EU-{UÒß'M}’¨İ(t±D4Ê0†1.ó´ö¯iÛ.+m½WºÍj²ÁjbN¸ûbùN0²p\0K0õ´ëÃŠ/®è¾Ë)\0*Î¸Jû¢ö	ƒ‚J Ê§.'Dª3cŞ–hÄ¢ìÊh``Dòrå©ækê¦öå’\r\$—€éÃ¨îf= Äë¾7í¨Í­ÎÃBö«‚G#BqL4ªišƒ%bu¢ÔO/şÍ+.,ì¾€ZÆhÅVèk”\\…Š;eS)zS¥>0ßäş®ù\r*<S…<TŞù.“0è%0í\r°òû‰K«øÕ1°ØT\0Ó\$\"Õ2ìğß€§ƒŒ©¡*&¥Q%‘øğ\rq…Æ†q9Æ~sæÊ¿±H4 @PÓ­>\nF\\Î…ÁjŒCÀ	'¢B¢_¤ZÃ€8E©¦\$ŞÀiJşQKèßŒşŞRpd]0&©­+\$Q‡“‘Dg§]¯,Á,¬™1¡&‘¢)º-L…\n•1Ğï§¢êĞl…”ÍŒøb‘0í­AHÍç\\í±0üÃVïŒV©OJÍñ1øÊ¬ó!lû eÔ%R%€PĞ\rĞAç9qY¢,@Œ‚7Ñû.ù\$ÍWq=¬ß’X2QÀï€ÍÂÌ” P	_`ÈĞñåcègK¼¼\r™#RŞ	zWG¸+î”1ÌÀhĞÎ2¡)W —`ÜYo~Û†+blÿ2œVdqM¸{°ŒFFe#Ì9&  @†h Ø`Ö&eNs\")k¤\\§¨\n ¨ÀZÜ\rÇî\$£´¥«M¢¤#…~Ù`ÂîÌf\\ŒŠ|F€é¢\"Àš‹`ÒÀò@\0Şr \"şô’Úñ’à9d}l0È3Z9-¢«öÄæ%\$y6ó;`˜¥öİ¢>;pšŒ…f\n…TÌà4ÅÄ»æ´É‚7f>ÆoZï/æ€ĞVQ5lŠ”,`¦rlºïôœ.×:¯ !\0ŞŒFß’¦–s¿<Ğ\rĞÆB@34%\"æLâö\$ì2ÅL òPSX^Eîj)´ƒËüü”Š¤Lí®òÏ®‹\"R÷ËŠOi.b9BÆ.PƒğŠq¢Î\nOøÉ€Æ(fª	óø\$ÅŠ,ãè&EÑ.O¦Š ‚6óaÂË;LÉ<Cx5eAf‚¹ì‚YsÔU€Ş@@î-CXAã|1eÔ>‰C&€Â_c†<`	\0t	 š@¦\n`"; break;
		case "fa": $compressed = "ÙB¶ğÂ™²†6Pí…›aTÛF6í„ø(J.™„0SeØSÄ›aQ\n’ª\$6ÔMa+XÄ!(A²„„¡¢Ètí^.§2•[\"S¶•-…\\J§ƒÒ)Cfh§›!(iª2o	D6›\n¾sRXÄ¨\0Sm`Û˜¬›k6ÚÑ¶µm­›kvÚá¶¹6Ò	¼C!ZáQ˜dJÉŠ°X¬‘+<NCiWÇQ»Mb\"´ÀÄí*Ì5o#™dìv\\¬Â%ZAôüö#—°g+­…¥>m±c‘ùƒ[—ŸPõvræsö\r¦ZUÍÄs³½/ÒêH´r–Âæ%†)˜NÆ“qŸGXU°+)6\r‡*«’<ª7\rcpŞ;Á\0Ê9Cxäå.ˆŠı­*FÉ–(éÂ¡”×%I‹¥&î»¦ò¤Ğ¢:_+©k	²ÌqÍBk,`X²k2¤ƒB\"È6½8@2\rã(æ@C¢6:„##Ç!o`Î1øÂ:#Â9Œ¡\0î4ƒ@Ş:É\0á-c¼2\0y*Ê3¡Ğ:ƒ€æáxï?…ÃwÁpPÎŒ£p_3sLÖ„J|6ÁRëØ3ACl®4ãpxŒ!ó”¨BåSj•	,ZşÅè;dî\$¨jBÁÔÌ»ŞåªÉÍ^Ï³MÓ<Ş\$¬kúáŒ	DÂÎˆ³\"¸Â9\rÒ’8Œ%~U6¥­dBOÓ†ûÕË\0‡2kÒ\"V°êãé_·¬k\rÖÎº?¤†É}X+‹ImtÔµ:LÀÛÉZUqÔËq±°{\$D#¨Yc\r±¥::€­55S\r³<,©±ä#‚(ö–‡0(ˆ„ /Óîòºøóo’%eœ@„^m•a¦íó\n˜¢&0)RBYcvVè»NzÉ__C=*bWVW®ÓwvÖM­j‘°éŞ\"Ê'•š˜B[·zéÈ† ”*Z¤0Û%w^HÉ²ì¦ŠşåiÁ³«ºˆëñµ…ÄñK­©?xˆ!_›TB Ò9Ë#dº¡„T7r¼¼‡Ís(ğ:Q2:9¹KIir:hR@ü<Õ^\0˜'esÂª 5\";—gh^\$á8H†>Cª(™fçC ØØ6I)D«&êê”&OªB»YVøÕFq¯\\Ï,ê\nÇ(òSXn*2DF(V«A&ÄØWíMJı‰[{Ww>²P£ŞwÆ¥¾'Èù‰3èho¨‰¾Å´ûß‹ó~¥9r?—®®ŸèŒ9-ãs†ù_D•ÇœQXI][ô{ (*€Â€˜@€:¥ÚÒ<\rÈ6†7NC\">Mà8§4êÓÊ{O©ü;¨Ô(rPê\$¤8âŠ@ú+)³Ú§Õ	[Bî­–ÒmŞ™;*Ğı·ØôHWUFM¡!7ZX[ª66'dµh@E‘´1,¹k#‰\n[cz!À¸’£BÂo\\ÁÅ6q\"§DìÒ|OÊAC¤”2ˆQN‰ÒçM”’öH¹X·!ò)le«Wœôä’ª%háêµ’å\n×±¼n\rƒ½XTc![h8Û4HT	Â*3ö¥Ô›ê^\r!°6\0Ä—C‚†A´2­PÂ\$3aÕ'%\0Ìfèl\ráœöM¤Â\n]r“4åCtX!±Ô8W·Ì¡Q|H‘‹5ÂÚ™¡K:Èeô˜BhUùU€o°(€¡¦\"3>É„«\0((`¥”¤k¬u6(\\Ÿ€ †§aØc‹Ü:ôÌƒIërÁr¦d–ÜšD\ré2n 9Ê¨ècÖ‚ŠÈÛÏ5\r%j§ ARR¨sLé>¯ YóYC‚dLÉ¡5%ªÃ@ia #ÀÎæò]¬áŒ0ÃPÊPS\nA|ğÖ¢_	«'Ô’3ËuFˆŒ†‹’dÌàØØd\$\r”Û©‹S&eeÖPp`[úªBæ¢şc‰Æ’&t§®¦¯Ä<	\$h<¦4*RFK¹j¦’œƒˆuHI3 Û\"z>Ÿ(1Ï\$¹>+ŠCLõåv\r.œJ#nªÈÈXÂ¦KM¢‘–hÉQEVJ©]ª°ŸHÕKW™0(I\\(ÌÚ¥š%¦“Û.ËqfM‹¹z”™€ªKê~ÊË=gïª«ê)m(©§CÏ˜#J?T˜ynHxğÚ™/Q‰Nn‡³?Â»Ñ¼U–™3ØÍ8¨~ ('„à@B€D!P\"ãÌ|(L¹¾)Z·˜Õ\n ºĞµL…d2Ä?\nÒU·ÑTºI¤hÇ‘ØãuH¹üĞ%-²bAg~³IÄ\$î¶3›õn|D…iîğJÌ¤ÁÛ+qµºÚ7ŸiEìÏµ¼GÓìã˜T‚mGDóvàtŒĞºUV»ÈÊ¾FÂÇ+ÔÑ‰¶1ttzå<«p¨Ñ\\JÀ:¿oÜÉÙRª½é1I!ªÈk*#+„ûk\"‡«ÇF/@’¼ ŒaÂD.ù[°mJQäVsÍëùTÒ},|–.3ÅUÜ¬´,®ÍæÑgéSD6ñö£KjØv‘-Ş÷Ø™Q`l4Çgå\$æ`c…•éãëK7ÊğNÉºú-ÀócCÓHü1Z¤-óê80/l°á\\î|±LĞßÄŸ5>G{Ñ~—´KÀâ¡ĞÂ Aa \\”RT«É#¤”—SøÙ8×ùLjZÚå²šbF¶_wNB½‚ôƒhcpÄ)‘7¡Yñ±©ÎÔ²Å‡)ã.BÃ‰/3à%}bñÚ^ñMu5[]S£ö‚CÛ\r'ªãk¼U2‰æävIó‘Ê¹YÙ°—;JMØ1Q4<ÙªÇ\nª¡ IæbB®câq%oš?àÔøŸ<À{ò'’ÆâzL`Í;zŒn'Ñì_lî×=®ÄÆW|;f|„¼GÇÊuä¢›ƒÕ÷¥Œ²2Œ2~`´ŞJK£\n‘ù+\$ñ/±º˜ëêÑ8àî³s¼ßGZüŸ•Ï¿ŸÙ»„ƒİš‡><ÒÈÛBøÌ#a}„Döè80gV\$ìÁj P˜PYmğşë\0òşîşË\rÆİÂ–ŞÚiˆÛÊhŒC&µêíŞ[£!\0PË\"n,š ôÒgæäÄFùŞ€¬¦˜,öEm8~î¾3~°q•ÚlÕ-4Ï†Ú;8XÈ¢\$£èoün‚¾Ôg\nÏ®¤çÚ•¬4´æ¶³Ï°.C\nï÷(ğ˜ªL…0ºğ,Te\"WOœàÅ˜„ƒ\\Â¯ÄW0æ#lJÆÈŞøKopúFĞşá-rkËDüP´âT2Ã¨^A±\nlAğN{é–?'«\0¢lĞÏ¢¦GŠ(éĞîÆçdç\\?¬òl&´lLÆ¸áê0â1,[íÎƒ%€ßï‡	ĞÍĞ]°hùÏm¥~ÓÉ«EmÒä…™q‚ıQ˜m¯Îà;]PŠPMéçe)ßñ¾N¾n0^ÀøÍ,D@¬\"³Cöp™B¸òcˆ‘L¿¢pÑPõĞ¬óñ¸ı-ÌO)İR¦¶ßqui~å¤'ÏoëPl<²)Ò-\$:Ïí,C©¤)Šzmˆ`šPòù± áp’ÅR%’Q	¥Û&CE#1'bwí\n¨øvNY#ˆ)èû(kqv7²Bïêk):7Çq ÎoŠú\$<kM,ÌJDcBV<Ïbwn¾,\n…´Ô\rDx°Àõ1û&+:3‚‚Ø\"JdÂœÆ0ºãïW-~5èûpoCN´êœú2 Â‚’«’•r£‹ğ-	ú€ä\r€Vºªä\rie\rP€Ì3™ÍÇf„¾ ª\n€Œ p§°®¹ìV3Ç5²\\î/ßˆ)*ÍTÈJÛ±O\nêKE˜j±©fú%BĞÍS%ãº˜æ\\ ï}!­èC«M:7ó\"^‹Ò\\‚®•‹0…l@ELF˜É‚Éğ\r–¤1b~ËØòÄO0#&ZÒÑÈo‡†DBÎV¦¼ım¦èd:'ÆØ‘??	r#Ã	qÍ7´\nwÓ6×\r4	@ÒŒÑªjü¦;@Ìæ7ôhÔÚTí¨0zSwælcVz´=Tß\"Â`,°dÃˆåJü0G°–¼F´Ë¬nêPiÏ}‰~h*ÔfØ—Îšİ“¤ú´–ØqÜ;@ê…j¾\n”Î3D%\0Ó-7GeTáŒßA	¡J°ùBFîc@ŞÄê€tƒæ6Môx¬o'ÅH´ë,Ôs…)et\\@"; break;
		case "fi": $compressed = "O6N†³x€ìa9L#ğP”\\33`¢¡¤Êd7œÎ†ó€ÊiƒÍ&Hé°Ã\$:GNaØÊl4›eğp(¦u:œ&è”²`t:DH´b4o‚Aùà”æBšÅbñ˜Üv?Kš…€¡€Äd3\rFÃqÀät<š\rL5 *Xk:œ§+dìÊnd“©°êj0ÍI§ZA¬Âa\r';e²ó K­jI©Nw}“G¤ø\r,Òk2h«©ØÓ@Æ©(vÃ¥²†a¾p1IõÜİˆ*mMÛqzaÇM¸C^ÂmÅÊv†Èî;¾˜cšã„å‡ƒòù¦èğP‘F±¸´ÀK¶u¶Ò¡ÔÜt2Â£sÍ1ÈĞeš’­‡#Q–4—¬p –%É‚ğôSÖÉÉˆÒ›%ƒæ0¶è¢,°Ï{Æ4¿€È:BBXÙ'ƒ€ò9-p×0\rÜ2®ì@‚29àäÍ(c¨Ê\rLP×(ˆ‘\n%0 @4ˆRy	¤Ğ›n0…:h*R94ljˆèĞ9£0z\r\n\0à9‡Ax^;ÍrO®apŞ9ázî¥c˜æ;Îc ^)ÁóÖ¶\rXxŒ!òj+%;ª%@œô½a®Ğ7c(Á´HèÜ¶\rcÌé­´âR×¶,@ª:kÜ/T`ä‘(#[ª:!£#^; HK]%5ã)¡@Š#Èà8AâXß-.ê%p ´V{ .Éã¢ô¢hV!°ßXÑ²¥4#=ÃZˆ7Úc8Ï0ö¸ÓvD#\r4Ã3ñZXå	ƒÔÓ¾\nÜæŒ¨:5ŠWóp2ÖÊ\$JÓ§˜¨‘&Cu˜2SVr”¾/ÖÒÙf\roKßŠbˆ˜ ˜ª6· ìBRæ6ÌELÈ0ÄˆÃÓiÅNãe¬K	);\rÄ/!ÃYã'³í1\\=ªb‰X¢Ù}+6òÏ¡Ã.P¨%H:~êO’ä0KZ[#Mrœ×³yİ)³˜eµ´Ã¿­İXmiJ@§0K›Š4Jeè'©•g\\\n\r-¸ß„>Ä	‘Ò€—Ş‚vÔ§Nâğ#Œ¶z1(ä[Ã¢–3Ğ0Ê¨’l&š¼èë„ê2Ä#LŒ6éÊ´zR)j4÷4U=Zé-ƒtWÙ(.DP-II(©(û:œc_„®tÊMŠ€ÙÀKİ'ä4wş“â4h“ÔùQcaæ±cÏ¡½~¢õ‘ÛØ?É¦«§¼hŸp/ÏŒÙ…PØF^Z-h¥›·Ş]ƒ¹#aÍêä¤¥\r` FiÀÄ¢¸:\\KÉ1&DÌšRlé¹`äœ“¢v\rÀ¼Œu×Á‚¡ 2âLZ• uVÅÜÕŠG¤¡ÔI5	/÷R¹ï~Qû;2…\0™w0HHø Z“’*ÆlÎ—sRHIŠuğ¨%ôÂ˜Ó(tLé¥5¦Ôß\r¡ÂuNá”<\0Ü«JbP,Lø)Ø ›Às¤È†B@uIáğK&-†*Ôx‰ÃÈvT\n¶)EB6­LÙ<3‹Ò*C˜[\n‹'dl‰G wR4˜7ªĞx;*¦Â(/)E×šw‰ë¼Ie² ¢ØdÊvVÓ,À'´‰©z™eXr‚i§~ˆåo¶0@@P³qº†è”ñÕ8()À¥Äw3O¨†™=K\0S(e-,Dh\r(!¤4>\0àœÜ|~Q\nGM=‰éÌ`üÙ…d8}ÉœœtĞEû¸S’QWa•Y*|ÕB‚°fQø¶Ÿ(œéQ˜ÒmÓÑÄƒ(DŠ[‘DQt}Q¨(Â˜RÀµƒ—ßÁp 	…°û©XBµvdÍMƒÎ´R\nK'Ôl Ô´]Cš£?f¸<“újˆKBDS®9\$OÉœ .˜6UJòOTMTr|<öã¡ª6liD9—Óû` iW„ÕóË’pN§8O\naP©Ô)¡”œíDÛå\"Ís@uĞ×b:ÚÈ nÁ¤3“’DÈÒ	Fƒn½X«I¶IÎ±¤æ&2VOnJ¡L*\0tO3Å9ìĞÂB%k“D¨—“^JjsZî4ƒİåpAbò\\a<'\0ª A\nÒ^ĞˆB`E¾hAKËÒyYz:òQÌ‚\0ØX‚¬A[ÎòîAª:¤å “gr­ ­­Ä‰™d9†P‰lG‰\$&³Ò‘/%fÌ©3cEÑ)áŸ%E®UÍZ\"ügøã¶fuSÒHllåâÑÆ˜’Ck%ÁÊD©#9çLën“z%Ş™\"é+ÜÅyÂ¶dòKfN)ÍA,“³¯IÀ^fÑñr¸×èsXê0Ÿš&ÃŒ™‘À.nèèÄ™Üé©b¡T`)IŠMcÉ±A\$71‰¦®šGÑ²t2™3UÆnˆŠi™áŞi¯s¼8¼>rVbï6T“mÜêuZwåÕ)‘GlDÌì\\aJ³Nm	Zep§\0K‚ÔòìÎšZù@‚ÂC(sÆî½3H†ZšRDä¼<í’®PNíC>Óßm†Êhcà/\nåKY Ò¯•Ñ‘\r9Ÿr‡˜+0ƒ¨iÑ·ã»‰Ûå	{×{Á}÷%{ßæ›€˜ô¥´ÈÙ¾±©ÌÓĞ ÃÍ›ÜT'Wsp^ùxÖõ5Ï»“iliÌC	p<£všk?htE((íÌ¾j\"VKné2f–İöëoÒ*p»|à˜s£;r oQmø‚stw²´#†Ì¡\nHŠrV+4æL¬+tÀßÓ®ë#*e+“~(©Ï^ƒ\nŸLocëH©wĞ‚–êB\rQ0xH9<PÜ™ÚcµÉÅ0í|»¬K¸æ2¡Næ¯bÔt\r/[7ÇVÖ¶è±DòT¡\\ªrhPu*>r>–uè}±‚³lÂcLØÇp2Nñv²u–úK‰Äx¨RŠq	ò WkÒé\$B¸økÎÿû ƒî×¦ŒÑÇWlÜ£‡B_RÒz=d¬¶H±jÈ(Y…V	ì±n9ô¾'Ğ}Ÿú½]f­õiŒFÖ¿«/Ë³Ñ~üNıo®Æoú	cD±®R×¤|\$¨È®ß§¸ŞN-xóP.|pá-üå¯¼#®ÔN.îÏ<÷Ï\\£ÍB®«Ê}o¬ôïİĞ>ÔgÀ¹OöõÊ¦6§ 6÷EŒ4F‰‚‚ \$vÚ©’ğlp-”YˆLáğvü|U§Á\$`¥â:'o¦\$|´ä¢p Ğ¹N%(J#Ö=E“\n<BP`@àŞÓf8D\nå,ˆ_‡æÕpxXí\nĞíWo°õĞîTNFÆ/Û…AÃUOZÿ§\" ­€-„!OVóãşıÃrØ)\0XõÑ+ÉÿQ0Ftµ,Øg Ç`ÃbÁhb&	¾÷ 	D†&ÂêÜÇú|#è%‡œP&ñ‰Rı‘&õÑz×Ñ1oûpÏù:\0Ì]%ÖœBGŒ~\"ë.ğÑÑ Íq²ªCârÀ\$\"EÂ4û¥˜w\0ÒÕĞcƒ\rZqÍ^Tğõ\0K)íP˜Ypâ\$±í1J ¢a1à'lqñãÆmé5Ñ\$Æp:<òÙhß\0/á¤ÙM³rD@æ>àÉAàš¨vÌãÊ¯¢Ú{‘¼##ü“È.QèŞ©%Œ‚\$±_‡`â˜g4qÆş;Ã&ãÇ\rn¡p6f¤t?ÊºàR‰Å€Uä`î*¼`è@ØcnLçîµ¯ 8FtH¨–”x¹)2RË€ª\nŠ–	&º¢2·İÄr];&MäH[!í/ÌP…‚ ÅÊfQBäÊÁã†0¦q\"å+0ï)Ö'fş5Ğ,€/cX5Ë\\²‹ƒŒ\"R61+€€Œ,W†ºw\"d[,Ücmnõâş»æP«¹BĞ-CléSXÆäÃóm/cDõnš^f õ\"ü7ĞG/û7“pëâˆ™Q4ó‡L7ÑTepDDv´åP/Ì†	 Şö®(Ø«‚aïÈ\"o¤“ªÁ\"&QŒV(l‘Ü1Ì<\\F–\$L6íÌJ¤7,ÊÄ…œC®û6’J-¬<Ã€<*»=LLfä(ÂÌ>Ìî¿8\0‚%h%(˜wå`@‚qğÚğâĞ¥‡qä*ı,NGœ0à\\"; break;
		case "fr": $compressed = "ÃE§1iØŞu9ˆfS‘ĞÂi7\n¢‘\0ü%ÌÂ˜(’m8Îg3IˆØeæ™¾IÄcIŒĞi†DÃ‚i6L¦Ä°Ã22@æsY¼2:JeS™\ntL”M&Óƒ‚  ˆPs±†LeCˆÈf4†ãÈ(ìi¤‚¥Æ“<B\n LgSt¢gMæCLÒ7Øj“–?ƒ7Y3™ÔÙ:NŠĞxI¸Na;OB†'„™,f“¤&Bu®›L§K¡†  õØ^ó\rf“Îˆ¦ì­ôç½9¹g!uz¢c7›‘¬Ã'Œíöz\\Î®îÁ‘Éåk§ÚnñóM<ü®ëµÒ3Œ0¾ŒğÜ3» Pªí›*ÃXÜ7ìÊ±º€P¦<¹æPİBHcRÜ@P#æ0 Pã¨©-c\\9Œ P„×%(ÈìÌšÀ£ Ğ2»Ljk\r/GÚµ;-b¡°®ÔR Œƒj ˜EêT¨³£‚B†„Ú‘¢<²”Ä4Xí Ğƒª)ëZ‘épÈâz42£0z\r\ràà9‡Ax^;ĞpÃ&Éî¢\\3…ñÀ_5shä2á¨\r©rPˆÉr†ğÁà^0‡ÉĞ‚ü©‰ƒ`”Æ’;Q«Qºº6'¯£:7KÆ1Êë”Ğ\roTÉë“BrÈ2&62o°è\nãä7K¨èJ2xÆ€M¯lÛmÚ:!ãdaŠƒÈáÜƒmœ‡²¬€ÆÍ•6t³ôØÒ8À\"²2·2º22oÔk	Yc-·Ì+¢#;8[³UŒ÷ Ï\">–W&Ì{L–Jè²a— P¨9+€VTŞc{æ9Œ/¸˜–6‘H˜ìÀ:§Ã(ğ0“à)ŒyŠ¤ŸM	E`Ÿ9N5~eVD)Š\"bô:ÙC£>O£_×)Šz1LquRTÆ{¸˜¥šmfŒ P‡z&\"9·Ş»ˆ4n>—²E<_†Õ@P¡”.öaP3ÆhşBÇˆ£Çèîq•tÂé‹8ÀE±nÏˆ‚\$öØ\"Cë8@ª[*ìq¦õhJ]Ø«Ùüp’biĞ†4&\\Â£¶0Ú12¨è‰%4oZ§ošˆKğÓâB;'|²kp3î‹F„YZ’•3MzøÂ§B#&ìa¹ö“,c†ö0ª.'ee“8ëUN`MRÊ8-¬PÂÏ#1!+}'äjZ\0K'\rïÉ¿GÜıß[ú…x‘†\0jÍø €†¦¦\"êÖÄ\ráÍQ3Ã*A˜ ,™Ş ’¦UÑ\$?à&@Òœa\$)%u9èS²xOIñ?(\0î ”#ÊD†à^àØÂ‘Ñ`íŸõ<¨	ĞSÌ1Ú‡¦Œ“‚(œÕgÁÒ9l0†À`êUÉB•D„¡F‚zÏ£2ô:±#Ä”òÚ}OêAÃå”BŠgÄÈ°Å\"¤ÜrÌ^©ÕEğ|Ø\0m{)ºÀÂMÊ%&FT7(ø‘ÙŠA4L5ÀövÍJ¯)H¡.;˜išd`€1Jğå\rÒØ”ä®™ĞÆ–Ã˜f3ÄÄ7—Ó2™Q±>Fäp0›¢eJJ9(ğ°é;i*‘K	ck\r5‡:Sç3¸„á@\$8R±.Š¨‚¨E\r1_1Ì­CÜ—;‡aĞ7šÇP+Œ[„„Ÿ8DÓIAA”2BØâ‘ÚœØƒ£\"tr—!*ÄÖ³Ï6”¸nÕ&ÄÜ™	¹6”%ñ<ÌC\r6Ú)-˜7†´pS\nAó–T\nRN¨V–…\0ÊÂ\r\0%Ñ3SFyxT2±EŞ•”%–PÚüİá )ç<”™5dêS‹Ü3¬â`U#i¸ÒšsRjÓY^\rÕ\\É£vÈŒ«P|EJ™Ñhz¡SG%Ğİ#ÀŠÈkŒ}.|„…\0Â¡>~ Š³“\0R;áÕ¶–Oê¡R66ql³È]då®'µ94wYa;K}„Ñ\0&0Ñç P4FÖ,sW>2ƒn¦eŠ’\"–VÉƒT	Œ`©=3·µŒˆ6²ZKØe±¦½¶¡¦tî¤Ñ	{7ÿ¡g¨XH`cVŒ2¬àÂp \n¡@\"¨oş&\\±WË~'§as>Ü!YÃ#<5·Q\nƒzNÙÁİC<8)ÅVp }Y=‹iŞO[•ü¯e9¶Dhá‹ÅHpÆ\$Â\\ŞÉˆkÁú?ˆ©²¹çÚ(­ƒ(åâ¯§8lr21Édí?‰ö*gºVe‹\$FĞ˜t s\nÎ7&èåÉFclÙ³3Ÿ;èrÚÄ /æPÓHèS4©>XÉ)HH:v…ÜŞY&íã%Év(Ò£«QÈvU_„£A@¥=ÆwÅlè„””¯Ò'¿©“gK-[‘G0a±„\n—J§2eÌ¡)ğ{	a\nÇv±å|îL“hÎ¨*NÍ<RCÀ\nmJ˜¥\r„nD•bl³¢qDÌ\nì…I¬­}=öE/©–pÜÇMÆ'i.^¶îTqºé83+¬9\$HçÓÛbÕitĞ“ÈèÃ!ÙUœñ³+•m±”>ˆv¸T\n!„€A¤ÙAJZD¡)å—GNr2¬àò{b_Ûâ\"ë`‚VL_8e[Ëa†w¥Ã-ŠdLùõ£Td–MHcó=4à·ù«˜Rªà4Ò`ÓùO;÷+ÇîƒĞÃEèüÓ˜—£²ìíÛ‡awZc|p9ã,Å\$éoò†£Ôº§?5+¬tP]ÌÌ×9ê%+´˜>«Jú¼Îë\"¢r9¨cÙšNÛJŸ¤¸ÛÏ¶›²­e!2ó2‰|‘0 Fr[†ÚMêµ˜ù\$6ÃÊck·ìóÒúp@Ã(bœ¾¬ù¯È»š3-Ø‚tÎ¶QÙ:q€uì©ışéA!ç½'OÙ\n‰¢P˜µ€A:ËY©/½›\n•uÆ\\*ÿgW}¼ªO~öXëäÒ¸¿É&–t¦ÊÖˆlNÖ°ı§+•ãò?jLÿ2&Oöÿ¯Öé¯A\0E†ÏæËæ2pxL%\0dRO€_ÂNİ+¸efYàˆ´b¨\"À ZC¶Ö`Ãg\rƒha‡‚Â)TMPTSŒê_ï”Øæ|ú®˜3ŠN]DZ%iZgÊÂ§0*g#pÖ§…\0LÔÊ)ù\0¯¾Öì·\0\"xÇçÍmb¥pï\$]Ğ¤llŠUÙÒ›ğ!0§	pÁ\nïºÿ‹\0ğ\$¬YäíşsœËúçÆĞën`æÈòúÏØô\0\niÔ‹°pòê°øñ®½ b®Âm¼¾fbÜ!P¬PîÿÌ…­º°±(¾±/ğØıQ‹õÏøÜ\rÅ\roûáS\nB”Â…ÎÊ8Ú'’kd‹¯.eÃRÊ®Ş¬L2”&¢›Àé„à(P6€@.€eÍØÙĞn3©t!1d}±h´ñt¤CÌTç\rEèÃÒİéøEï<û‡ÜÍä\"q'1*ÜMJuBîØ\$Rofàø.˜2Í²ºbQ£\0E{P\r°Ì·my(‘r¯r1]\0.RfÏÈàPàÏ¹/Á\n!Rüc'\"’,üğ³\rÒ6ıÒ:à­G\$Ç!²FÚÍ¶32K\"²NYòİ’_#ğ<nDúDP¦äw\\ƒ\"½Âº_‹L!EòÇOÓ\0Q\nIñY	ì´%‘9)pí\$2«Ğèá²§%%é\0002Xie2Üğe+¤Ô=«V^2g%¢²b0e,í¸Ån²,2^%Ò]f2ªCŠ¾ekôİç–+²1\n¡\$rò}mÌŞ›s0D[0’ø·±3%CË¦İ²öÜòú0òİ’õ0³.\n‹úO`àR=&/Üá­€y Ïÿ-ïÇ4Î1Ò©13ZCÆ`â‘_\$`?fø-ı+RëèYæ4 ÊŒåòÜÎpÅd²÷¤®ñ9\"F|…%¤3s)ÉC²1å¾„ª!ÍX3œ™^ûI7%™\$Nî[óĞàì¨*0Ç\$S¶,@Økô_cº5q¥-\n÷ƒÖ8iA\0âr'b”öËg±|jJÄİã3‹À@¨ÀZØëı6æ@ŸNœé»>0³CbUBtqÂD\$‡TD­‚ÕæÌ+^†Ôh°dcE`ÄÀ@ƒ¢@nE\\iMä\0EG\0D¹¦G8Óÿ1Òw‰!àØ|¬p§vZG¿ĞFq†T!…©ËU;C<4;o)PvQ}9‚Ä;& ì\"°!4ËDÆelu\0ÙLŒñN˜;ÍD”\rMÔï:±5tùM°)LÂ¢Th&;dIÈØ¢Î)â£â:cF:Ô\\„ËÙOÕ\"ø. ÄşÄKÒ¶pÃTŒ½\"cÍªú#Ï5Í‚y°ü# ¬É‹şÄ¦hô£\0\rÂBt=/®ÒêÜãc¢‚@ÜãqNldÇ³ü\nÅ\rºp­ÉYC\\ˆÌ1àŞ— îe€	\\«³BZú¥ì=‡Ö>`A`Ü"; break;
		case "gl": $compressed = "E9jÌÊg:œãğP”\\33AADãy¸@ÃTˆó™¤Äl2ˆ\r&ØÙÈèa9\râ1¤Æh2šaBàQ<A'6˜XkY¶x‘ÊÌ’l¾c\nNFÓIĞÒd•Æ1\0”æBšM¨³	”¬İh,Ğ@\nFC1 Ôl7AF#‚º\n7œ4uÖ&e7B\rÆƒŞb7˜f„S%6P\n\$› ×£•ÿÃ]EFS™ÔÙ'¨M\"‘c¦r5z;däjQ…0˜Î‡[©¤õ(°Àp°% Â\n#Ê˜ş	Ë‡)ƒA`çY•‡'7T8N6âBiÉR¹°hGcKÀáz&ğQ\nòrÇ“;ùTç*›uó¼Z•\n9M\nf›\$ä)©MJ Ê½Î Òòh¨èô#«èØòŒ.J¨áˆàÃ+dÇŠ\nRsŒjP@1¢°Ó@ò#\"™¥*ƒL¯ˆ(ê8\$±‹cphÎ0° Âº9#ºš4\rã¬l×G#ºò2\0xÂØÌ„C@è:˜t…ã¼¼1S°¥ËÈÎ°xárSŒ„J°|6¯.¸Ü3/)ÊœŠ‡xÂ?C*@1Œp:ûŒ0¨æ3£Ş”³XêÙ!-øš7±ª+pÔ·@U/L¥ÍÃxß\"cxì•ĞC(ÚÀB P®”\rÏ]Œ\0Ä<´ Mi[Wä.7ƒ¨Ú¾B Ò×õXÜ“¯O#\"1³vT+HÃz|P©Ñ +.«Ê5o(c4Lò@\nŠ±â0ê7P¦fÉQƒ;63Ì0°„:cŠªˆ0ğß‰î3\0Ç¨wË\0¡Ùc(&ej˜ï‰ˆª€ÙÖª‚n±U¢\r4İÃp€_PÊW\$‰¦á°Íëd¼X°¦(‰€T~ÊW£ªÊÓãM4”ˆPŠC	Ñ0¶s·#ˆëU7â6ëz6‘éº:r©¤2Ÿè4X Ô5KE‰)\"ófcãÄjÇéC-DÃåz¶¨©ë Pˆ!H5ÍC7â#ù¤\n¨æ•Rê>ªÜü°\r+%Ci ‡0íH›1U¡TX‰D°©ˆôaoâOEˆNÂQBÀËk°^ß³ì«G‹ïñ\$2 c T:—·2;ÚˆšB;zïîõXÖª–½É¾–0¨I\n¿S¬(µwÚ Ozœ÷ê‡ƒ„Ù~\"Cãv—“î\$şhAçÕ•kêQ]´5\"_	İc—|F°#˜Şš\nŒ#äßÓÁ)_¬\"”VR’k²#¥”¤ğ@”C*SJ©],¥´º—Ó?)™4à^Tê{ƒÀˆBzÓêqÉ…I¤TxrÛâ[ä¤3NJQ¸eeQô—î²Ğc¢\r†œì(T××Ğd…¡Ô\$ä ”’¢VK	i.%àî˜,L¡É3¦–0`éN	É°‡@´ƒ)Ä¨áR…X³…Mg –JÃ‚<J°²¨Æşıë(aˆÀ•™Ñùª1Ç\rÆ¬R`\r’†	6D9RC”UjÔ0†cJrš8a˜Ï\0ØÃ9Ø’i›ÈóŞªÑ›a8ºÈ×^âœCŠ†,x'µÖaƒ‹î.@‚\0 \ríF\0PUL† eLş²vG‰\"¨€§oá ğê‡G‚ßÑ‚<’¡¼;Ã§,Œ…'òg/„ª|.vH-Ä)¢v±\r…<?Â`fÉ~i­’#I#ÎÀpHÉ!6¤¹&	ph¡¤3¥elHC¤_OÅşğ×!BS\nA‡PC@Í ¡º€ŠÖ®ùÕ1+xd©’¹4gûl¨„Æ²‚¼äÊQ='å·¯¦ÄßãÉK)´ÆÌe½\raºÔZNF \np}© i6\$Âa¢’¶_©aP•ÀdÆàHö.âCPáRÖ#—QS'…0¨ÕÅbbËÉ–™Èš¡Ğu6äªASÈ\\V|…æ4ÍÉì^ù%¤ ô.×ìU‰ PS˜Î†#šºÉe(ä­—„Æßa\r a'\$ì•ÓS\n‚¤É:íˆ“-Ö¨tI	+2h¼¡s4_EõE‡‰Pù—Õ“æ˜\"I+	È¨)Q„Ø „0¨BL	!h …)œÂp \n¡@\"¨nĞA&Á'… ¥yo=é&[ßvˆ¤°eYa,LMÂ( j­F\"˜˜u(dÔ8YåáL×¥”9Gu„©åLˆ‰Az.˜zäC‚è^Ï E h¬Ç³Ô\$Ö+ÜE<è˜Å1üVv\n;(ñ¿µiŒ()ÿU,mt3Æ|ÛĞ¢m&ãS|’¢ÏUf*À…#„,·Ó|WÄ­²•.Ûğ:pæìôPSceÌ`­-¾cÌ’¬¯æâõ~éÓ1fûŒÚù1ábçÔÓ^lŠUÔºÁ²ìë´bX¶ÈÄ.]C@2«•\rallHİ•\nÎxÂ§£´mM¹Ã[zqbè0ÄiÑ%Êù¡ªRÃuR8&¥è<0§z@NÁäÕ·E˜Âs]ÅÈÍ7-¼ÍS^€òŞ¾ÃF49Ç5êeORôLvL!øøÍ®¢Éå € ¤‘‹·!î¹fã’ÉJ¨*0*KºN/F˜”<àŞŒLê4ls¹‡\"_aù3æı]‚ğ@®•¯!ªÔ–èNjt\"‹C“¸„4‘“aIªï†›Ä†Î.<LÏqRÅÅÍ*¼0Õ!yÇùL¶,4ç6ZkvwÒ+0š+¸É\\Îµç|eûïìiVù>\"¼S—r~‡3Q'FC}!²òƒÓ¦\$?ló¬Ú¢ï[±ÍeP­Ú¼~Ì!‘\\¤škzØrƒ•Ä2¡¯…ú€h!)¡‰¿u^‡\r;©äÁ'¡À¢4\"ùº(QÎåwü…‚C]Åém3ÒíáùÏTJcãr¾ücb©ZU]¤S’ª#;Ã&†DÉ°ßAêSÃ´]úgäÊMñ4ô¾€İè™Şéıç¸1&‰Ô7„oÓ¢ÓKw<šxtêPó¢{MÈ…üC<hÈ°ÍÛŠÑ U.0¿d—V•·óğA3å+ô»‚ßõGñc¾oãªÕúÁXfJ| )¬\$¬\0uh×/‚mÆ€QBh±¾Uol…ğÈ	m00°\"nÃÅ@DÈğÈ †0‚r2°B¶Pš`äÜúãnXĞ^ÆBäpEâ	)ºåN8åªæ2‘¬TÓ>9#\nØ\"B¹Bú÷E0gB]¤F¸ğŠq\"•ä.3p™‡\n@‚v%€À®\\­i¦¨×¾j‰\"Ã&§¨Ú3„è&ïêI©	ç<´â,şê¹‡îÿPìBe\"×\"Â±05¤¤Ğ%Ø°G½§#ò¸Èm	é>X…F1,‘b¾jœ?Ç\r«L1ì0×âRÀ÷P•„8\$ãóĞ™¬kƒO~SmIïjõâÂÜ„+	7åQÊ…O¯UˆÜg‡\nfÔ%1ˆ{£ V%ÎNècÇÔ@‚de¬ŒÇR7>“ğ+‚4àŞ¨b{¢hıI?Q½F÷ñÌÊ‘¾ùñÓM\\Õgj×cq‘Ñ­Ğ%…ÖÒÑ÷İ¥Œ#fŞ²éNE ÊTã>ó°Í˜ÙÄC!ÍŒ=#Ò(Ù­/2\$h†õ#R,¸±÷#ò ¸ ¨UE‘%[P\réì¨2;	qi%å›Rf3²`@B\"`½F’\"±À2«TíxWr\0j|\rîj(bœ4.)#Z€\"Á)Ê¨qÏü1åv¤*\$äw±µ+oR{°^äöáïDxpw@A`Ø`Æ `Æ™@Ä¡jùƒ\\ˆt1ÉN‡ÎÊ+¦.\"Æœ®±;	%4bÇd½@¨ÀZâ\n\$i˜1NëBDëzø‚.#\"6#§9FîûozeE-0Í\\ÛÅìEá/fÈ=Ïn m¤Ú‚©/l-/-;4Ë¢ÂC Ò8Ñè¹ŒŸ7Åè<gl¯~3>(3Pé“¦L<,(ïÚÇn]ä#	ÌÇP„oó±i–cC;‡å;ÍÏ=:³ºøé	àà)Â˜ÚsÆıÌy=€;ğûG@_rÉç£>Å.ùFbPPşÊbŠo† ÖB„š ŞšçĞtB.1óİ>l1à‚AÔ;‘ª!Bé(€ÊÙÃ+:bd1å.Ä¨^H©øVĞ=LH)E~\nÄ2¬>@î/C¾-j:#~\"†.lJşEêB¾\rÀ"; break;
		case "he": $compressed = "×J5Ò\rtè‚×U@ Éºa®•k¥Çà¡(¸ffÁPº‰®œƒª Ğ<=¯RÁ”\rtÛ]S€FÒRdœ~kÉT-tË^q ¦`Òz\0§2nI&”A¨-yZV\r%ÏS ¡`(`1ÆƒQ°Üp9ª'“˜ÜâKµ&cu4ü£ÄQ¸õª š§K*u\rÎ×u—I¯ĞŒ4÷ MHã–©|õ’œBjsŒ¼Â=5–â.ó¤-ËóuF¦}ŠƒD 3‰~G=¬“`1:µFÆ9´kí¨˜)\\÷‰ˆN5ºô½³¤˜Ç%ğ (ªn5›çsp€Êr9ÎBëlwq-½âm^™|_Ó÷Æç|mzSË;IÊ¡n,„¹¨cô 0NÖ(f¹L×§¨Jô# Ú4Îø@2\rã(æ;C¢2:ƒÅ#Ç\rp Î0¸Â:#Â9Œ¡\0î4ƒ@Ş:Ä\0ác»Ä2\0yÊ3¡Ğ:ƒ€æáxï+…Ã%\n;ásÄ3…ã(ÜÇãœƒ!…á–\r¯k\nÏÛ#xÜã|ò@îkzÁÁÌ´À­HJ×¡‰[ìÄÑH2¢	—Ñ¨l¢#n‚â† ÎjjTB¸Â9\rÑR4Œˆ[÷A¼ÍZkE§t‹¾º(\nfš¨Š’L‡94´¾7\r´µ½iº\"k×Ú?S/âs-p} †'«Uæº–“–2ŞÿAŒ;ådAã ¿°)šğÃ.\$‡'Hmš\"	¤Ói-Ñ×-zI éê@„¤B˜¢&Y°\\Ş5Ôªpü?Tú­ÑˆSÁ']!r§in3qãÈEˆPuYMZ)6k¦ií¤¡„‚6Áy• ¸.Ô»£‡ÙLÿŠlúÖB Ò9Æ#dj¥„¼7i:\\7§iƒ(ğ:LQLì9Ï‰Äü“4ö–y”UŒÓp¶È‚^Ql¢ÖÕ½6£®)’ÖË5èu6ûC ØÀ§©[„[É2 O¿ù)O™¤ÈLşœ'©ª„Á¾vs:–¿©İ•¥©}í\" éY ısH5éÕ2koÀˆä\ra³¨'®m@Õè2^€¾\r¢ËPœ*9W]ugŞ²4‘%I’t¡)J’´±àË¿0ÌpÆ :N³D&rLNÏÔø„½Í:pÄ ( £ ãuÖ<ã¢Æú3,ÂNÛÑ:\nIå’’ÒjOJ)M*¥pî–RÛÃKÁÉ0&\$ÂÕÚËİM)­ú’S IØ£| ùÓc^JÉ¤ëwLÅ˜árDlæ¿˜`¹3QË„–¨rNÉ{ˆ\n¡¢W¸‘°i\r°\$júqA´2ªÂšÃÄaÕ\"€Ìb`l\rá\nD”rÊ5iP4”Ü!±®Ñ¯É4!5Ä@÷B®(Á\0P	@‚–\na{à(!§d*ŞànEaĞ7£àäC´J¡*£äF†Ú:\rè’%¸ªŸArhûƒ:KÉ™+'ú!ôŞ†ÑhsGèœFXÓ/CppGˆù \$ äªC¸h\r!Œ4&Ò|MF²ü1†êã„ÌÅã›\naC?®ÏrD€¡thRHåw‹‘ØšòÔµrª!„•İ™%o	ˆ\")f÷tBT<å›êª~C\0Z„^#“ÒZòI;–+”æ¯hïÔ\\Š&¢ˆƒOú1H‰áú¡Nü(ğ¦'É\$¤8ÄPÿœ‰2DAÑ‘ÉşÀé½>\\ˆ‘‚Ú Ù<Ôz’R‚ÊÓ¿ÁRBb%IÀ/êè½E<ÏYå:Îšu6Åù \$µ@èÂ=\nHõ9¢ò-ÖõŒsIêú5Ç¸*ã¢{L“('\rév,¶es)u¤B™&b`M=X&áø(²djÊ–²Ì\\å³ÃòÉHƒxôl¹öH@g\r¡-g¸€¶¢`n–Zå³ÌúÕ*Æ6ãØ½Ÿµ5!Š6âzÿOÙv†	VÄ×W†Š!¶XnÑIi•Çé ¤%GkÅAr‰»¤FÁ%n!ä\nÖZÔZü ëJ¬•OI{ù#d®ØÖs9MÒ\$ÄÎÈ£V*AAbHŞ¥TÉ[«bê±J1l¹Å¼§,™ö­cÌXÕs\rzÍX¡É0U–µ‹È,¦É™Ë+!c¡iN 5vØ2Ò®ËYU1ŒÄ×ÁnîC7¶F\$êã\\Tá‰\"!P*†Õsgìœƒ\\ËH<>19\"à‘ERğ©ÅˆûãÈc–ˆ‚m„“z‘sñ±0§÷Şj	…=%dášøyI©8e©Ÿlã™Ù!™¤ódì‰ƒ¶Pƒ\\RÎÒñ:ó!²¥^îi]5YÏ©ö­chµ,ÀÏ)/¬7”¨#q@¯-D§ViEmÕUv19aCi5\rhÛ»UD€†	7\0Åî[{¾k=E«J¶GálŠpÁİ·Ø˜„¤@ĞWGÜÙÔåÓÄ†œÄÍ H4‘ÀÔìÄ’z`w^,œ6oxã¤µ«ÚïgT„Á‘D°Q-rk\rjê¶²B!á(9‘3˜ZØİpDğò×Œ„{*÷f‡EZp%À@p~\nr§»9UÆ¤r‰ƒäK¿PQzÖ)¯gİ‘Ì©å®2æÓs[Q¢mäëQ\\ÒÏóö%Î7³Dç½ĞtätI*…ÒdBU<§ÔrÎpU9as“Ÿ£L	8Ã‚\\ÉVmÙ~;+?}vnŸfûkì«—¤œ²	kôõ-ÆÀ“+ÍN.àÜÁ3’{'WMbn‚è÷O{öÇ8@Âk“_·5‘3æùİÃ*toÏÙS=ŞBôiÉfÅ;Š\0…ç.õBÿ±˜»!`¯XÕZM0ÅúR,[’å×êÆà'^œ»¿ßß¶-İN¸?3I>¯0?8“}{fş¦ş?Z0åàÕöHéù_ƒ¿Z:t\\¬|#{FºêÓÆMQŠêjû«v|İ†_UR¾ö„?ôøâLŞêşş®ÔµKÎò.PæPÑ74ïˆX%pù0 ±\$­ğbîP<ÂÔ6ÃV9¬>İMÀÅ/ìçpDÅÊúíáné\0.”ûÆs–ùFô-lR#gÔ®Èí'z%ĞJ80xÊ|íğ‚È¬\0ß¥ 9m :hÿ/&³kÄ9ÉÊ^¬¼‡k„-n<=Ï'eì5ÂZÆ0ª@âv6Âıƒ›ÂÚnªµ‚LÎÎÀÒ-( èH˜dâÄ®~ãn1clVƒl4íš„Â†Íhğ.ºŸb×£òG€Ì q,ä¨†R›*YKŞÕíô/\0›E1ƒr0*¾Ñ@]\r]Ê-q&2Eh¢Æ,!‚^nEº&3ªlÓğ:À£Ì1nª0'l^\\–#ËÈ¯¢h0Æ¾pï×€Ï¥¤ -â[ğŞöİ‚<Xq©…ÄŠŞÙWP~ø‘ gFñG`«Ôê©4êØ½1Èëªºù…¤[†¥ !‹Õ£ÊÇOÊo:¶‹x°±şÇŒYã®2«E-QIg#b §‹Êbş–aD­®!(<#kÏ	CÍ‰à·2\0´BY>@Ef?g\ràì;àî’æ°#b+È\$^‘*>é²n @"; break;
		case "hu": $compressed = "B4†ó˜€Äe7Œ£ğP”\\33\r¬5	ÌŞd8NF0Q8Êm¦C|€Ìe6kiL Ò 0ˆÑCT¤\\\n ÄŒ'ƒLMBl4Áfj¬MRr2X)\no9¡ÍD©±†©:OF“\\Ü@\nFC1 Ôl7AL5å æ\nL”“LtÒn1ÁeJ°Ã7)£F³)Î\n!aOL5ÑÊíx‚›L¦sT¢ÃV\r–*DAq2QÇ™¹dŞu'c-LŞ 8'cI³'…ëÎ§!†³!4Pd&é–nM„J•6şA»•«ÁpØ<W>do6N›è¡ÌÂ\næõº\"a«}Åc1Å=]ÜÎ\n*JÎUn\\tó(;‰1º(6B¨Ü5Ãxî73ãä7J{z:H¢¶·°(ÓXÇÉCTş¿æf	IC\r'|\"PÂlBP«ˆ\"¯£=A\0äŠ\r±(Ú»£AHÜ@ªPæİb”0Œc\n9½É„|ß8ãZ;,ÓO#¶àæ;Áƒ X‰ˆĞ¤ÁèD4ƒ à9‡Ax^;ÎpÂĞÇl3…è@^8KRàä2á˜\r°cZ»ŒĞ`Úß\r#xÜã|›Šƒäí‰()ƒê5¥Lk¾'*ì”‰–i æÌ/nóàŠ/©QUUë¾a“CRB««0\0¯K\rÏrŞŒˆ2h:6%Œ¢YTN5€PÃS#…^V«˜É²£8òÅ¾¢êÑc¢¹m*i[Xú-â Ê3#ªRÃØ:Œ P–Ù¿ïâîB0ëŒcL<5§8Î¤ğê+}.5[ëCC±MÁƒb¤\rËÀ·¯)XÖÂ\rÌè5ÁŠ±Ch°7S&Ô Œ3Àb–7Z“€ŞCc†â„˜Æ0ÔØ¢&K#–€¼LÎÑÊºK“·Ñ<&‰CÕ£3[Sj½ªU(%jŠ»â´˜¾”èË‹1{¡BN%EBƒdÚ>ƒ8Ò:Ğ¸@’6È´¾·ˆ£Æûh¾+âülªFÑ¬NzÛvˆY=øŸßÕh\"(.#l¥ °c>7sMj˜sİÊ<+#t™Gl[5~ZPÇ\"\"(‘\$Ò2dÂb‚í±º(Ù-â8Ê’©-“¾”±Í3QK5£xÌ3(Ro}¾‘kê*\rí}Ñ„‡\$Éc5åÄ\rû·U/Ó£Ê`3Œ+¸AùYQØİqŒ¡@æ°\næ\"YµE‡EJ¤PœòÚI¡: ä—Ã¬whõ0š”È™“BjM‰¹8tåHĞ.Néä7òHè2P ú¨ÀÜÔ‚’8ì5†\$BŠQ¶\rO:%ó*mr3F¤ò ÔÑ9ü+!É,‡4¶—Lû‚\r% 2äÀ˜ ºgM)­6¦ôâœß´!„iéÓº—W	TsÍì8hn!ˆ>s„H¤±eŠ@ÉÑ„~e(Œ‡'¾rJy†Ø„Ê\$8 I-F†àRçÃ9r*çd­È\0JÔ@X'ø²`áJJ±DHú¢cñr&š%³Ğêù\"sæ“/¥ù%C4p\riŞ2@€1ÅC’W³­1åæ\0%R4eL1Ï˜dÉ¾äVQÌØt3ªíÚ>“|ÓH \n (LX—2ùË‘E2G‡2>MÂ!R&ÇƒblÍ©·X®‚œ\$‚L%\".DÜaˆäŠ¹£W¤ ˜ ğŞWdéq~d˜‡4ø’Ÿš3J7-˜øŸb€w8!Œ4 ÒÓAè—ñxÎiù?¨\0© FY0¦‚10J9¯5qg‚Yl\r‘bVL4>a…\"‡I¢Éá>(D­ÔG5JTÏRóì*G`J+J3æÂ@tç	¸I\"!äÓ£çvk’ç‚”€â¾™JÈ,@ÈÃ(šjfs„Ÿ\$Š”1Œè‹´œxS\n”ß@ÕX¶dg\$9¬PÂ\\j§*å åCÕèVòéªôí':2Ê³W\"µ<êH‚\rÚ™Ni81Qğ@Ú2+Ò`\0Œ&ë@X¤õÁÇ²e]ê„ (\$—9\"‚Zòi4ƒšÉ•\\Ê\n«jç-”âT-™Ñ»«IjğàTÃ)¬n*Z+ÄzB¯PHf!éi\nÁ×Ó.ˆÎi¶†ÒœÚÂ\r„œú\0 ˜»æz/%\$P;†¾[çìÿ 'j–XÆØŠ\n\nqØbfS°Ş—®ÂíN\0Ğ)2ZÄÃmMÄ¶pŞÚUpF;æMvù1bÍ„1)*\$Ø!¤•´=WÊb_›aC\$óöMfM`/íL Š·®©IEÎ¾‘FÈ\nù-ñü)³-(–\n\$ˆğâ50\n‚X¦Ğ2‡r:©Y¾\\/×1ºyŸ˜xkYVüd‚˜\0C’gªÓW-sM½ĞºDÿ`Ô\r“\n#GÃLÉ™ˆ¦–UÁ Ù›æQŒÑ=«GC“çp‚’ã.ôÎcD[[CKøÿô<Ì¦¡˜¤*@‚Â@ ­ù¼Ü=\nÑ+ƒƒƒŸgè4±¤œmƒ]›cÇDƒ´”ÊúÊ\"ÁÉd¬]Ç¹YêÀÒm£Æ“!n ”¥…¶pÂ´ª_ÜNzÍ—r²šAp	Àûd£í¹Å¼ó*ûÔÕo€Ã¾·6ü(ûø¤Ôn»8.îàïûy èâ·¸tß%/ˆÄÍús¸©7	ÅŞbqıì´\rQ*m£Ì-ÅÇ\rÆØİ¬|›ãSÃ9'©U bŞ‰£:À—œµòT:I2%0ë9µëD¯\"/êlÁOò4Œê¾¡ˆéõè˜TYÑL`=Eó&“¸Úµ&Ö0¹j•GFOĞ61m\\½ Ş…U÷€åCxO¨ÃÑ8Á.ºa1GN'\nì¬…wx	Xl5’Îùk±•g›È~Q^„Fcq“/jÄÏáìAÖÉÏ«pêÌ‚/+YìUƒ‡Å°ì[p^á‰‰ş'åB3KóÚªa°É*b•fÎQ„aŞr˜C‚–\\ÌG7ú¶`ÙRá¦ûãË×øÿ-è½XGèçğ‚K¾‘-+œ^vè@IÏº•-…´;å¦¦kÎŒ³€RÎï†'0DÇõ¬Öõ/Ş±€ÈÄc„vëà’©9+2jC„÷bV÷Ğ5Œ8Ğoj&ÃÀ2`Ğ3òÖ%ÌA•!ZÎFßnLânPà\0‡åğUnJ<lßî-P]záîHİ0j6Pnµ ¹è\0ÒpÃïS	êº Ø˜hHpM\nÍ%\"Ïzöã®Ñ+\ni&t‹ÊH¤ïjd¦‚ô3²Uaul[É\n\rd¾&0Ô&t €ôZª¢/ä%«\r#î!Pù\rœ8%ê¿l^›AçA+ÜI6’éH¬+\ní&M	¨V&²&àÇ~ÇO¼‘¼Í€¿í†mBrøEM¹‘l)0¦õéhrW‚÷|TSìZqqhF®æÖñ{˜ÖÌ'l)ñ¢#ñ§O|±¤¥Gİl;ñ¹¼q„ÅÌ`;à–+@ÈN|’#Àş\"EBN2¦>»®ì3 ä+o&SĞ[ï`FÍeÍ)ÇåKX`F`ÎgTUÀŠ\rˆv\$oò ğR\roÓ,^k'\"²/1ÏE\\\nË²C¸(ã¢%ï *æö-:Gª±\n’Sª>ÓÑ©Òe'\${!E)&h™#&°Ó|‰’=+˜w\"Ö±È4o&À„7D:ærkÒ¢×d8×Ò ±«+ Ù*mz*Îg(ô?ƒ7°ˆEè^’Q\0>æ?ÒR5oºaå”Q#²Æ¨r«Pô\"şİğÆ…Êqøïüà2îÆrõ0’ú3/R	rÈ@«è%oîdX×1¥¢YO:gNOSëİ3Ğ‚å-Ñ3°	“AE:\r€V¯oÈ°ÅIÅê^ãZ\n€ÒÇÜJ¢nËv\r¥´)Hˆ~@ª\n€Œ psè#7H^&ğh~føHÃşÄ-ĞÜs¤ps«/ó¢¸“¨'ïR#Â@\$BH\$J_©’h^&/dvãÀ\"óv×\0¤EaBü ì¯ 0ã÷+kH¡¯:£Ç\rM#ëV«j	%áR°Òˆ\"¢(mn	€Ş¶e\n)x NcÜC°7¤\0\\c°Ud>ñS*™ˆ¨ÖåN\\nŞUÏpø¤\rƒJ-‡1Î¶ÏNşvã6’€ôôTh+vâ|\"Ï5Forïò®õ%*8­(5cZ ê¼ÅÌGlä°G\rF©ÅÿbÜ<sÙHtxcF‚	©°İ@š³fnÿMfmf{%F5 ¦Ah\nÄ¦ğ¾´^Â?ì#òã\"hRâ	CVUuEÔØÀ2-a¢tp<t~+Œ-€æcUQ’àÑ‚*ÁÆ\rä.ãd+\"Ö‚²\r²â9à\$‰²wc:Û¢ÖlÚ t\r Ú"; break;
		case "id": $compressed = "A7\"É„Öi7ÁBQpÌÌ 9‚Š†˜¬A8N‚i”Üg:ÇÌæ@€Äe9Ì'1p(„e9˜NRiD¨ç0Çâæ“Iê*70#d@%9¥²ùL¬@tŠA¨P)l´`1ÆƒQ°Üp9Íç3||+6bUµt0ÉÍ’Òœ†¡f)šNf“…×©ÀÌS+Ô´²o:ˆ\r±”@n7ˆ#IØÒl2™æü‰Ôá:c†‹Õ>ã˜ºM±“p*ó«œÅö4Sq¨ë›7hAŸ]ªÖl¨7»İ÷c'Êöû£»½'¬D…\$•óHò4äU7òz äo9jNznºQ9Šã<€İÍ)ÎL–®¿d¸BjV:p‹	@Úœ£ÀP‚2\r¨BP‹ìÛ lğàô#cÆ1¦Út´ŠVÇãKFÄC¬’V9ï@Èâ4C(Ì„C@è:˜t…ã¼Œ(pˆÜ”Ï@Î£Áz29Æ^)ğÚô1È@ÌôAj‚Êã|–Š¸Ò’Ä P™5£H€è9@ƒ êøŠ¬J¸5l»½<¨Ë‚ä£tæ4¤Éê\néÜŞ¢!(È“ENh–7ƒ{Ú%#ËĞK·+ƒâ\$¼1ÍB•ÑxéM#Tğ‰#¨ØÃØ:Œ¯”Ô4B2B3¯ppÏ¤v†ÂOÚ8œ n£Z*Îƒ¢üÜÎé\n\\%o’r5'#:2hŠ&€»­lÓrQÊ6Â>•’P„.	(¦(‰€PÅ9«ÛagÏTKı6Ğ	(æ5Œ°Z\\:Î8>üa^ÒÍ¢ƒ(Ë3r\$µoE	pÃoˆ…†#wPÙ}U	¼Ú\"@Pá]B\"ôÓ±Ê@@ôöh£¦ùÆl2\rÜ½¬©c]C¬È\0Î•\"VêGv JîƒNÙˆÂ3TIrdã-42ĞàUfø°ìJdÇ\rã0Ì´Éih—uáˆ¨7²0[t\$0Ì?ŒÕ•Ú7Œè@çNcÊ„‘¡I#ˆCn2…˜R¥¥Z÷vaºd–ŠŒ[Â›Ëé›ªŞ„|””FƒŠŠBq³Çqì Èr,\$ÂOL(\rÁxÈ²Õ«,®wïºî3Ì“2è˜QXÄ½Fœ½ËeÃ(²‹À¥°×	Êr¨åIè|r2Ñ¬oØG‘ô!H’0ï\$BÌš9Iò¡hì°9‡ØààfÉÌğ6\0ĞÔš«Yl!Å´›±„´\" E~lXÑ{1J(›µ@Òœàh1Èy15#.»	9’%„©6®hI›zD	Q¾®Å2àa1£)ğˆˆ:ã‚A¥[4`Š¸_Âé-†½¶†¢€H\nà°É‰Ğ()\0¤§Ä²ÜKBeIaã™Ã\$ÅÌ¹™ªM‡BniP¹B\rŠd;Æ²ZW	›*\0˜†ˆBJì\"e8¢‚hÒèn:9£¾¹¤d•†t|M!ÙjÅ9ÀŞÂ\nC\naH#EØøI(K\r&P6·ƒh·¡b²•eĞ:\"Jfzl¡´¢’TèSZá5Rî*-ãöOIù9|§H93CHk(ğ–„’LA;RqÅviQÈqW¬cÏ+]3¹“%1©–57É¹3PQl\0 Â˜T[áÈ¢«rhLOH 	iİn¨„JP‹‰ä€´rÊ,A¸3’A3`ÑH0ÓfM”<¦JBR‹y{„ÉËA– a*E3ôâ˜èr#hªrÎr {ÒbŸ\$!D*‚ÌXŠ*Á	á8P T *¡‚\0ˆB`E©@('CâPÕTªÈºf‘Ø`¶ªİ©‰µH|ÀXÑ¡—§2­M\$µMiÒ:‡Y”ŸòßãéK®´ä9c.NÌ]sM\ní‰²¥°ÂÓ‰ƒ`v\"BòeË0AXÂÖÑÈÑÕ²d¢Œ¤(¡±\\:\rE¦R3Õ¦æ\rÄ¦x{‰M5ª^ Ö[,ÉËÈzW1„Õ¸óÜf#\$k:'NÍ (aMºRfX2‡všŸM£MPj’Ğ.¾ÀHEÕ\"%Ô6œƒ@úz¹ìåêxP‘¾>hD1¨ºÍwJ2	½Š-‹,Â‹\n ‚®*¡“„dÀF€PR6ä!BÓ[cP#N(\$‘Ä Aa N%æfÛ9\rèa\r!Èì‚:¶N%”¼ÈZ“àƒ¨“(Dhó&Ê¿Ähã/Ÿx2Ø‡Xü_Ê‘n¿Ğ£dNÖá´(µÆbs)Œñ®HÄG†e\0 •”ËòoB\$ló‡r\"x©F9Ùyõ~HÜö3&Äí0Í«cr{GÀ—Rœ„¡Š×å|”qó±	i†¬Ü~\0:G0Ó]	§¢C¦\r-L‚é˜^ÃA~.ªÒ1 ÚÃ\"†ÉõöşX‰µˆÔ\\ĞµIj…PìÛU×óöqæKºK¯(jS÷`N«œ¶ú÷V°ê¶rØšØ4¯²4Kj«dÀ(¢*¨ŸµPajgx\"pàÏPP&EB\r-E¢¶©‘z-[Efî=ÕµÊ)jº–eµ\0Ó±åFVS¤ßyİkh¦\0(5‹p‚š´Âëº×{lÂl1pŞ¤ì‘éÉİˆ-z±|K†½Ö¿0NW_o}‘Xª~5¸»­ù{«Zq—ÆêOšXaV? wšŞ'¯õ;n§|õÒqİÉùİç§Ë{£lÖHÛ”Ç\0C7u	µ{x\n	‹N„Ü¡ÒAÁ\0jßuÚbÃÔ*zŒ­&=b\rõ¸×¦Šˆ»;Ó«Ptèm¦ŸZ:¦¡sËĞt+6ÊídÈï6\"sX-ã(Õ`öŞö^m9ÿ&ß>;•y°®†€Æœ—”¶I£|yŞ¸Ët\nG¼øÇşÔtß;uÊ0eÀÖ û\$U”Ğ·äÃİ6Á‰ó¦Gc*0ÂÃW_¼-\\±Äôß/€pAé¾¨–üÏ¥É6/NÙşÀ’·Ò>°X-cQ]‘yÿ¼°çŸ2vGòo¥<O£	0e¤ï^°Ó{}-‚^WÏÔòìÿ\\éï¸…KæöşåQª QoX\\Ãè>ÎFóo²ùãæÁ£îÿ­ó£ê;\"õã¤hD€TúîÎ£(i£’Å¯Öƒ/,ĞŒªÄ%È/ÇUbàdíÓd|\rRÇkBNŒ~dæ\r€V¯î\"ÂÊ¤EjVãDn¨ƒçEBZÊPÓéÈ…'\n ¨ÀZr¼Ğš#ì˜æƒ&îš8Ğ%¬BiîŞ°¦	°šÀòÀ%ÖU\0C-8,Ã¶>é¦2kì pìxãbÈÂ,7\"@`%Ş)ÖÜ\rêBKdr®Ñ(c :BŠnÀ†4ƒâ÷ÅoÈ6rív…°Ù‚Ä,…ŞÊ¯×šeåÈÓ1PØnš&&ØÙ1UnôÑSP\$Ù€Ş A`Ø×Qr8O@å@Xì ìH•°Úy…Ì[eº©ËÜJÂ'Mú\", b^±E®Åë&CÀê—jÇål/Â\0VÍÔ\"/„š\"b^@ä0nâSâÆ,¦–ş«²\$‰¦‹áN\$Ş%Íñ\\5£òm€\rãàã(4,¬(f¦díÄ I jƒ†„j¯ÂìOŞ"; break;
		case "it": $compressed = "S4˜Î§#xü%ÌÂ˜(†a9@L&Ó)¸èo¦Á˜Òl2ˆ\rÆóp‚\"u9˜Í1qp(˜aŒšb†ã™¦I!6˜NsYÌf7ÈXj\0”æB–’c‘éŠH 2ÍNgC,¶Z0Œ†cA¨Øn8‚ÇS|\\oˆ™Í&ã€NŒ&(Ü‚ZM7™\r1ã„Išb2“M¾¢s:Û\$Æ“9†ZY7Dƒ	ÚC#\"'j	¢ ‹ˆ§!†© 4NzØS¶¯ÛfÊ  1É–³®Ïc0ÚÎx-T«E%¶ šü­¬Î\n\"›&V»ñ3½Nwâ©¸×#;ÉpPC”¶S2Îuø,±Ë³T‹AE	ÑÌïh2ˆškœëä ¯ƒv¾I°Üù	ƒzÔ’s¾ P‚2\r«[ŒìúF:!à´CƒÆ1°îp@˜4«ÄÿºV4212ú¾ãÈâ`4C(Ì„C@è:˜t…ã¼”0¤,ò­8^Š…ã„hüáŒ\r«C‚7ËBrİ¤à^0‡Éh¬Õ7®ô=E\r35±hÓ7¦\n˜å\0ˆü¼/Kâ`Î*súò½¢Mbè6\r‹ğœ²ÈÂ6ô¢«0®ˆ\rÎrŒ\0Ä<ª€M9OT\nŠ7‰\"Ø\nƒL?S©šÍ\0004+XÇÖC{õ#¨Ù6C`êù\ntœ\n’/Â3cÓ0Î3Ç¬m˜ùlú³¬cpãaB|lêKÒRŠ£P‹­\nª‰s3,ğĞ*5¦YTe¦¥#İX_C\"0)Š\"`0³L+¶ÚĞÔ\r¿®@Qê1İ¯P‡Ï8£ÒÙãIîáÂƒ6Î°¢HÛK“ 9åV.2¦Rê¿cïóô!NAf/Â#TÖ¤*0@´*`Ä¦èZ&„2‚j’o3”ç]«xêŠ\"/ÓøÛ­«UtˆN¦²#Œ£z)©¯ÕûÜÈ2H‚B7ŒÃ3¥¥¢+	V\rèÄ<¸ìDFÃŒÕğATÁcœpåoƒµ„eG\nĞÊaJZí%K’¡¸Œ²{Í7).ı'czZ*2óW0Mj7á'È0QÇBš£]ltÉÇ±üƒ!È²<“%É½¤ 9JR¥ü¶Lƒt²ys—a3M4J›ârl0Çì‰Iªb‘R6*l¬Æ´î˜8\r1èÈG1ß{ HR\$\$IC¼˜“;Çy/9¦4ä–RÛ&Y=@|QM[gF\ntĞ“ÖpPâ\rH=J’Ğ˜HN;@Á¤Ÿ)0aÌ!.d|•šbª«j'o5‘ò8]É‚u„QN†ÌúPë'n8G‹ƒ h5€‰’nW)»-­E•ÀbI		G§‹PœaÌJ(.¤…x‘V¦A\0P	A‡[\0((À¤˜²È®Š	-j¨2Ã4LhX)œ\$yN²ÓVjŠr!Q¼;ÅôüˆªNÅ8’¬ÒF:íG®4Õ§”¬‰i%‰)|8 fûÂ-5,P!UØ‡!Ä£7\nôáºpßÉ(C\naH#I@A%—óô\nA”µ™Â`£L[\r+ÄÇ*Â	¡!}dá¥ú.Œª^Dí]J¶è RHXy2\$ÅN¡²¹\rDˆG«mx‘Ò]”\0BòŒ’†5RpçI«JÆyÓÌ÷àFS”¿\r\$À(ğ¦&— rn\"˜HFê²›Ó ½#Ô†M±z7tü´æø\nm’Ì’š5R“Q:1€€Â€Sf0 ÁR6/×ÉÑCšwÎpä{cÑ©9¥@ô\$ÊŒ+Ì'„à@B€D!P\"­Úœ(LµP·¶hœªR«ÈÌ9DÓ”®‚bë]©án‡˜aB\nÏ\r+®“–Ğú…C¦¾¼¹¢Ô@PR&J@ÂŸ´T‹\"Ì¿NÓ­XyğFÎ›Ìš‡­ dãc–£ `è\0ÜnLÉt„Ôb!@ãÁÒ5åÈ³cÚ\nF‚à‚h‰j…	9(º_M	¹\"€ê,âÔZ\\{©KÌ*8ÆkÓºÔlÒ•t¬RAâ…²)şì¢…¡ŞÒÅsTZ/á¸½¦¡Ô¡½¦)¥*|‚|xs¦íHŞöµëv‘ÖL@ÎI`¸y™Æ0Ïì\\ğ -84—°×`Úú¾ö6+óL®‚2bÃö¡c e¢í0¬¼˜[åÓTÂ Aa NÂ\"gæôæ1¹Hã !—fxÕa‡,¨Áx  êt´†E“cUnÑô:Ò5R²!‚o<%£…G“eé0çğ\0œ¦@²¶CÆÙo.”\\ »şb'p¸–ÅòvbhÚ±_¤`ÒÔ@ËGŸŒ/Ty¯?h@Sİ‡tÙ?,äm\nI]_tAÖ!7Í”ô]šFÒDf’•#1jZ}BEµA°¬éZÇÌ~ÈáÃ§æpÊµ‘í¥öÅ•çı!®íXot¨d½{ÓÒy¬\0€3-Šy/¡Ô\nè(+ãŠaX…ñO©\r])ª\nÒr}ºÀÍD‰ë•ÈÜ–wo‘v„p!,U—;,Y\rÆ\\>ô™	vÿæMçzI„OßÅÀŠ(¦f’îÒ>V™T‘Cñk%vgÁ}Jw²Ø`ù‘w[êïJÜeyğÂĞÉoa„›ñˆ§'N«ˆÁk½·IT†ã\n°­iÀòåa˜ldÜGSyt@HH‰#Şú¼ştspÆ:/MEœ±Ao ‡PPRÄ¨Üuƒù—³y0-yÈíï€Ö\në´\r1½ÌÜbó†a…9ÌªÜBLf­ó{tI›İğ7zoP‰o…è—»È Á==˜xh?Fù¯\\ÒÏ\"û›r4TÎ¦i„ÊHÂ’}\"ÈaTt¦ƒ˜sŒFŒµDÖD‡<s\"ôÈ©5…ñùsv[)°¡´ŞÂİ\$ßvñÅw½ÿÃ}ú¢÷ô)±ÒüCÃlx/Ø>ºy\nÃiZù0ğ—f§G5ß^gİû>3bÊ¡q>çü¬\"âˆ+|<.—ıÄco-²¢C/ó!Öküê+ÖãL@ÄÀÀêà#‡Òv×&ÜÄÒëÖğÎĞöâpş¸%°\"íOê™¯îbG÷ëp¿ÎZ´ ŞD€ÈOÏöf:Yp>üÃ5h¡É†OªŒ4\0òA¬Yä/çÌ\nŒ&Bï¶İpxÁ„.ş§Ífc£ÜüÆ§Ü[ËNÛ¯ô\n¤èK¶ŞP¤ò°\nĞ¦üä	t]£óêšÈ6¿c\n˜e\"ZåG«Fæ#âØÍ¸Ì°Ö´DJîPàø\"æ¢üTg\"âä-Ã\0})Ş~ş#ƒWÍ\\£kn=	Bíå&W,Ê)C1<¸& kä¿ÍÌ‹®ì(Hc”\r€VÂÂã8ëá¢BƒI[‚ U.ìÕ@Ú%	a ª\n€Œ ph£r/G`%¬¾1Ä§RÌ-(b&HŞî«(]ËpóéfL°±\"N0†TÛ,˜eä~;#¶q¨ŠÂÑZß¬1nZ“iØ*b0X\$¼ƒÀŞ¥º“f%ÑßÃ>„/Ñ&Bı##ªĞ\$1¤_H#\n4àæ,bÊÎ‚] ô„ô(àÒ…ï ²\$3.£gá!Ò3\"\"\"r;è^½@5c(õ\"è\"¦†vB«À~oQˆÈüåÒZ äbD&Î\r&ËŞáEÔçEÜN6­\nÕ(®Äå\$HÅG\r\"èk+ÎJD@êjåº¢òdN\$-„İ+ˆö\"Â©L8äcg!†T¯\0Ş¯G®ÌøêF-OĞr>[â\"]   e2PD\$^\nq\$T…‚Ì	\0@š	 t\n`¦"; break;
		case "ja": $compressed = "åW'İ\nc—ƒ/ É˜2-Ş¼O‚„¢á™˜@çS¤N4UÆ‚PÇÔ‘Å\\}%QGqÈB\r[^G0e<	ƒ&ãé0S™8€r©&±Øü…#AÉPKY}t œÈQº\$‚›Iƒ+ÜªÔÃ•8¨ƒB0¤é<†Ìh5\rÇSRº9P¨:¢aKI ĞT\n\n>ŠœYgn4\nê·T:Shiê1zR‚ xL&ˆ±Îg`¢É¼ê 4NÆQ¸Ş 8'cI°Êg2œÄMyÔàd05‡CA§tt0˜¶ÂàS‘~­¦9¼ş†¦s­“=”×O¡\\‡£İõë• ït\\‹…måŠt¦T™¥BĞªOsW«÷:QP\n£pÖ×ãp@2CŞ99ˆá¿Eú8†i‰\\œåA\\t”/Ê>¦B¨á ªĞlr’j¨H£åÊ8W¯äªAñ#	ÂÊ¨—E‚®Y§¥pîäÑƒ\$©r?(èä€ ŒƒhÒ7A\0È7·-hŞ:›|8AràÂ1ŒmÈç)Œá\0Ã+8.HÂ9µƒ¸Òâ4Óa7c¼2\0y5Ê3¡Ğ:ƒ€æáxïG…Ã£)Át3…ã(ÜÄô9xD¨‡ÃlÖJc46¸#HŞ7xÂA¤kééNE\$ĞháKJ	se¢û°*ÁWXÖE”t”)ÎM•È1\\r¤áÌDDb¸Â9\rÓ@æ‰D«‘ÉK¯\$ñEš8w±v×¥ÎJ•Ié.Q ÑÊ@>gI\\ÄSòt’ÅÌJ–\0S\$CEiÌR‡9hQ9¥Ùvs„}è^Æ7á2FÚŒ¡ÊDØñ’Ä:¶Kåë6J–è1*¼‘d­¸NB0ê6\rÛ’ÛK£Â7B˜¢&#÷Ñ='&X±,E Ù3œïºPt!	p¤-V)IcÙ7¦Ğ—Ä\$=hí±j?¸&;“Æò¼õÕy_'¥ARøÄqú8Nå7¢ÀAF¿Ä£‹öãf©D–‡oğµ¶ÇFÑĞˆ©áXf6–Ö*!İ: İu}HÊ<8CtÏWnaÍšæùÊ{ŠføJs,r8Un×Ú¨H*ìA€£åÂ?äwÁãœ·6ƒ“HÓMTT3Ãe)ZÇ’bRAD¾P¨7µõ`Ü<„¯l:Ì“0Í£„`ŞÒ˜sO¯uúÎR˜  •t¥ ÜN(`¥ô\$²PGßjí\n†¥¥TŸªg\rÉ¥(@äŸC¸\r!‘*§óP ”\"†Q\n)F(å ¤¡2RêeM¥wZr›@ú*ØF¬Uš\rBì•öz ÊöYäøO¸F^ƒÄ1¤qô¸bµÅ¡Ö(”@ˆò(ñfa57œ(@§CšyOh<\0Ò ƒ .OÊ(U¢TZQáİH©4©ƒ’˜SJeÚ)§o• I\r¡ÀÛÕ4\"@>uà‚K'EÒo\$àa\rj¡.ü¤¼J‰‚•ˆ(ŸI1>'™JAÎ»]Œñ¤@³86k@Uà94€@d¦P~K.Â£r]	–8¿¶’ÿ NGİ†ƒXÃ–<IpÒCc¹'¢,PQx-G0© B\r”:aÌIf'@\$´9§dîÈtOB\"\n‰&sØÊ²V¾à¼¬%‘¦,Ë™{>MCUéP1ÄFnÍy±6fÔÛ†UÒ˜t7G-&&Ã½&9‡¬FaZ(È3Ô‹©5|€©~ø©bkju2À¤q@nA;Ç>ºC¹Åa¢NÎ¡æ9Ã›ÓŒ0‡TáLÅÅ5¦ôä!…0¤ˆã×FÈÁ|˜Á9D3,çqåĞKÒò+„ĞçÂ¤rˆñ_Ag¨ª'Å\0¡Â< ©ÃqI+ŠaĞ-×45ÜOÖçRI‡(¢!	#×xÉ«Ô¼Hæ4’,_\0d…tµ.©©>qMÒ‚!ÔÜ¥ÀÌ‚ƒh „ªRT„ßòp›´±N›ƒ˜xS\n‰!)ìPI¨&â†bHd[sWU”Ñ.­\0°µöÔÁ×‹“c T\nÖh,mÚ¯%ŠÓËºùù§iÈß?å\$š¦=V\rH&\0ÍI´Ma*Ô—Hi’i†[ëmC‘«A…äR	aÒ Ü[x.¢S±–Z9EÓ&	á8P T +‚\0ˆB`EÇK¹œ\"’ò#Ä¡–xÏ¥ö°hòcĞzÄQÍÌøŠB‚%²Õb§ê+r>Ù2-Fm²£›@{òìb,…ˆˆİEpç9&t,5«Újó^ëë3ÎÙŸ<7fÒå×T%¨ò&!sÖ}…0§Ä–QŠq-‡0…^eEu£g¥qZr¹‚	ø(íóÆy‹×^Ò^¢*%Qêá+\"l®XX¯‰‹ñŠËrd‘zÄ›yË¨®g¼g¶9…Âí¹Àù\0¨Ù§;æT›†ÊÊ¢Ák\r\nì&òå«Ìı0Ay_VƒkZL¯FÈ……”MyMÑeeüOŠV#{ÊÅRµ<®7-ixä„ŸÀ›éEÅ«ˆ2o[hƒÏk0¼ìÃÌ&ËÈ½İÌ˜Á7“—¡HB‹évè4\"‰1&Ñ…@‚Â@ ·h#o¥Æk\rr\\KÁÂ”(ò½i`ãH…g‘ş”*I‚è\0¼u#7Ò:wFË€™¼å¬é€ƒ¯6ù×ìå D…‘ö¸½Hç_T.{™ú0Yú‘é=iuÎÖas ÄD€wğ0Òs(;®{QJ»•¢4?E£gãÊµ<–ëdAš\$ ü«qîƒœBNÑ-nÈ™Ø¨ÅætÎß,úéA\\2†+ÊÜ}m(Å Uêÿ1îÌfö\"1ÅÎ¡Ó€ Œd¬Q·…k ë\"ãğå² EùœRÊ,^­YC˜[Ê#…Ù»„Zƒ ÅØ²ÕZşqbíBG]ü\"+Qk-‰bY'vGÏğì\$oøşib2Ã0íèÜÃ\0ø/ê>åÁr‹ğmˆÃ\0nÒr%™gõÇ(ÉŠ­¡Óä€Læ\n`ãæ„^äVF\"ü²œ#ˆ£æã*p×M@§®%?)^IĞM\rÒİËmP5B\"İ­Ö[G²^fr ìœgªø¡BòE\"0b®\\ÍÜìÏí\nLÖ¼ŒÚs°¬E-¦&îÌmb³OòÎ«ÆÎğÖÏ-[§1\rÆĞ§0£¡\"ÊÙ‚Â*„’o.O‹âŠPê³ÅÒì° ZPĞßÌPnåˆÚp\$şï\0‘8à¶ìñ	±KÎ·1Sb>eØ'Şâ>ß/>³E ]ïšÇ\nâh§\"EŠ*0àÃ@,‰ô­ÍFÑp¡ïÍ‰m\nŞ0b3Ã®pşºâ¸+ÂÀ®ç1Œ0‘’Ÿ7«XËñXHØoâÒmæg+,Ê®\"mÄÜûG±ôXQ?ñ_ÑöX‘]ğX°øÑ1@Î2…“\r11\nŞFÚæf´aÊWbê¡t2Ìá¡6?\"\0‡0¡|0Vkã£„cåíe¼Ñ.KÎ7&ê+M'ò8b\$²¥ß‹vÓÂ8ÌmãFµ(ğ[rs/+D,Óò¡#…¾Ñ¡¬{NãN qCÎ2àÎ15\"rË-\n÷!P+../ò“#E•/Îù¼x¦ '‚®åjÒælÌß3BÓMæ#‡Û1Îe*à	Ş›ÀÉ&Ò†§¡ÌpG[AÈa0=ap''™)!ÏÈ³Eàìj&IH–ĞÅŠneæc\$RH/Òàq’˜n\$€c+è¡3iİ7&`óy\$®òm%¥\0000Ğgº\r€V¹\0Ò`ÖuDM`ìx¤	8\rëdÇèNc˜Ì \rª¼K‰¨Ìj\n€Œ puˆR¶HF9Çgn3a#îg\$Üğ ÙÌú»+·ƒF9Á\\¦ÁG*cœ1ÁÎıã&Ö6q¡%B£\"2nÚ½âêÁ4&Ë,B^ûĞÎW#ïDï³EBãÖ=¢9Dm<'¢9ƒ/O¶¡ Á<h<Øb°m'º~Æœ8RÕ@°H´6áv¦ƒIp?IÆª#)/Rí	J§+\"0'\"`¨tãt5#V«%4u@àˆ¤ÛnHtYÎ• îí	:8İ´>^o§'0U\r%qpšİu.ËĞ»C4 Œ”@¬L`ê ÛG\0aKŒÀeá\rH2äá(iZã¦Z.¢îIö]Å†é´ìFğònÆ#¢h\"ëJ¤gô®t²Ü ŞÄ@î6C„mÂ>'£!¦òc*fË\0xŠŞÒUœ!"; break;
		case "ka": $compressed = "áA§ 	n\0“€%`	ˆj‚„¢á™˜@s@ô1ˆ#Š		€(¡0¸‚\0—ÉT0¤¶Vƒš åÈ4´Ğ]AÆäÒÈıC%ƒPĞjXÎPƒ¤Éä\n9´†=A§`³h€Js!Oã”éÌÂ­AG¤	‰,I#¦Í 	itA¨gâ\0PÀb2£a¸às@U\\)ó›]'V@ôh]ñ'¬IÕ¹.%®ªÚ³˜©:BÄƒÍÎ èUM@TØëzøÆ•¥duS­*w¥ÓÉÓyØƒyOµÓd©(æâOÆNoê<©h×t¦2>\\r˜ƒÖ¥ôú™Ï;‹7HP<6Ñ%„I¸m£s£wi\\Î:®äì¿\r£Pÿ½®3ZH>Úòó¾Š{ªA¶É:œ¨½P\"9 jtÍ>°Ë±M²s¨»<Ü.ÎšJõlóâ»*-:œê%/ü(¸·iÛZœ§dÂ€¤Æb¢ª»MÛ€ÌRí#®èã3\n·jsZ=1ÄhA¥MÜ‡¬ïŠÜÚÂĞ¯\$·Ë¬:N¤­Ó[¶pDÌ6DÌ““‹jªÒÁ*SSÀ.ºÖ# Ú4Ã(ä \rI0)ü²¶(Â„»'rÛ<Jë3Zê\$©ÌÔ¢,¡\0x0„@ä2ŒÁèD4ƒ à9‡Ax^;ÖpÃAP”0\\7C8^2Áxà0c˜ï^xD¯‡Ğ\n“?)¡à^0‡Î\n«=tjãºîü²ëÇ®TÁÃíÛ/\r1R€?—-9D¿íd;*Å°eÂ×]òâsy5×£O÷è7Qã+v#Šv³8ÍÍ„£\"JË¨”z>_Ò”ã'1L@A“32¡0Ë2ş¨	;[,È*U¿; ïÔàãËØÖJö]s,°ëC´†F€¨“Şq#dW<BÿÄˆÖy )m]A\0ÇÈãø§/âOKãïöNç(\rÄ‹+©(ôy;o‹ù‡BJ½yá=éÀj•6nHñ. +3ĞĞ¨Êrï©Ô}Å©úš­‘şDï©)Ëã\"ŞŠrwh,ìó{¹+ÎÈöó*Ä|)Š\"e¹'zÖ·(´§k—ƒwÛ3z®üõ×Úo*cNús°ğqeùÜBøz§wSk-'vœ'‹ŸŞº„ûîNEÃ¼‚€)3%)mê-ß\$dIŞ-§=Î>|Åœf‡zb‰æ?öÉú;w¤}Tå ¿äº¼9ói,\"UK2¡+ïö¶ÒNP™1f.Î@Ó±‡ tÌADaGş3’všÊn~ìè7¦P•˜ûÛ+-@—°wêÙi9K+Üô†Àè™I-fM´%2ˆIßÑeo„¢—ÈG3©N0Ù:fzsX›H*¢¼ÿ–õôÛIÉ©*Dr³öZq’ÛT@&0Ş¥âJ™2K!`¸™>BNãÉy^bD\0İrÌ¢;ö]O•ŒíPrÛweµN–Ï‰ cÅ)¶t£JPjC”HªcÊªí@2\n8E4Q”dƒ(1Ç?˜’ÏÜ¡GTj•Sª•V«Uz±VjÕ[Èµt¯òÀá7†àÂLµYK6'ÅhØQšgMëIj##şõr„m5ŸÉÖN¾OŒl‘ÌFÔï&a9Jˆ	4ÉÆØùb™geKnè|½J|ApóGÁ'ÒRAèÃ1uÔÜŒ’S*…T«r°VJĞ;«eq#Ú½Wë2‡€è°œ¹X+,9ËÈäÈˆñ“uïæbé”YYvs`ßÂSäòV{9wˆ5Ÿ?cÙâ6(³9F|uÍÄ^(”h» òÉ	4qfÎg9W1Ê«”D’‘é¹&TWM³q¹9˜ª†Êª[oÉõ¶Hw2×¼çk%Ú›:–\$£‹	¦ŒŠ£F>É…R€ô¤Òò«ElM>Æõû@Šç2ëi¾ª%Ä œA_&2/yòÜj|ZnIúxV¶œÏbi&íĞ¨§’NuÚ'‹“¥kt%/ì±guÖ©œt‹hÙòt²±…ØÉâk”Sd^òG6XMÜ„ØE„y­@ôNP{êi‹æÆ´W«kKÒ%6eÚåLäĞÂ˜RÎß²Â&##Jµ4­·=RÄ­r8ì0ÿ‰©ì'9­9Õ§Áæ\nÒÜÌ¶…\"ÓbK ¥(;Õ-•3BIi,Ë©h¡7Í‡í9ÉİKE|ÓÜ‹Î1v¾H(³ö0i0£·W¼¢_túär€ êÑŞs›6#éÆ\\zc’ZüÓ\"c:4Äü(ğ¦*kCRudV85—ÄHg8ºÒTsvô§A²QT¬2Ø5l¤ÓîgU,£ãÜZfC8êX¢/œª›m›0™«nÉ96@_Ù§‘\r”ŞãwÎIiÄõ#‰fÇ¾*b0T\nŒ³3êu/JMgÇ]¸R	—^Ãàµ*><0eÈhsxœ­,èJOi  àó_(\rÀ»g<ÚÇß³°n9¹Â6›P‡2N­)¬^öÂhÁšœnl„Ó!Ã7â¦]Õg/X€^m†Blõ­«oE¼#lÿÎkÃÛ;G¿í mØıXK#ƒu9dû3OEÙÒq¶ˆôR^fø>÷‘Y%íÊu¢æÌ³=r0ğ™ÑßÎíò÷tKmÊÕRA¯F0N÷ÛûOì\0‘ òÎ7au±7¿´¡¾'½]A'2#ù…W+ñl1»Ÿ³6-!¡ò9z÷3ölËFóg(i.¿a‰Ç[eŒê÷]·*Õz(w–v9’ÚëÁA»ñ{r÷ºG©Éÿ{üÁHìy™2{¾vûdÉ\$¦5”'3Cq5–tDEèıš¦#š…¨l—™“{}ÓgKj-tázĞ·»_bJ\nTQÜŸ¥<÷š];[=Æ`RPó·å?˜”®×{³”ö¼Ô\n.Æá+µ¦ÅÜz©Ø„Y’tì\n¥ãu>±ë=JÖ‰ÀB T!\$:8ç{&^™öèA«Y–ÈåyG=8ñ¯s•°¿¸ÃX¡.#æ\"y|ùıÍ³újM†lÚ\nEJüA›„Èòüœ(#é¡\r¢n{ÏÅJ)ÇÊ\\fˆYËÃ¥üö“%åöd^ê\r}º’C¤¼ıN0ı­ò^…î’Fêw£şo&–>'èÎÈsM®ÏªL@í**®Ü®Æ;N|ç§ã,´Ó°&ånnƒGìĞ§\\¦Â˜Ï†ÒçÌÓ\"€°`/@GN„+7‡2·„ô¸@ğ¯ÍëëLèæC(€û\\Ô¬œvĞ’…/1	kº…H´îx(K1æ>£L\0¢|Ì¦nJ2ò³I´uÏÆ4çşiÑêZüåş5ĞÇO-,m\rìsîZîfÜâÓ\rÖ‚'Êsz¸pÈxJƒ\\9ü\0ŞPZëé¼ğ>èé–hÇfæ˜¬üç‘)/8uÏt´n\n]ÖK‰Â§§ö\",Ş§‹Ä^ˆú=±h;í	0òë©–(Eómjƒíè­ÌÖò¯R{±ì±ƒN êÎ¦kãOğ’oê€§Î”c°‡\"Ø\n€×§10|«`xâ”30Õ±_­Øİä\$ÈúÒÍ§çdËOû‰™Ñ/±ĞìÂoqÙ/×Ñ³\0²ÿ,‘‚ñÌŞ¢ìŞærè¡ ûãwî–^hÔÚĞÎµöÓfMˆÓîØn±Ò”J’<HÔ)RFEQı²Opˆí,³\$MÿbŸÑèÖQj4JíAJV+t\0007°Âkìğhm£'¦½N&)èú©Ò'ñ±µ\"ò›†hi±Ëÿpy)qBARœ™dŠ–êr‚ÜÌóxp„Šl¹'\r>}«È7±¨òZÈÒRµÒV\$òZ„Òºi’¿*¦|rÑ×q*Ú…é	ËOTíÎ‚å’zdÈælÄÃò+&Û“0åNè}rJ)íÕ .|ÌsE%ñò}cNÛO\"ÁH hgĞõ†~Şo56ÄpóĞ4­Ó£O7k—7¦©“\\üQ ‰ç	7Æİ'='MÇ9°—:¿Cvß¤:Èó ƒ’¯V{ÃfŞhúÓãu'ä¬mDãP	‘rÎ.ù„0ÜÓÎÿíW	m]£!ãş>2%8×%!ÇÈ™Ô92@\$‚ô\"\$ï:S72d(RŞ;êÇ6ó¶ëd@ÉÑAç<p¥2#±Bè~y'Ó<¦?CÒ!	.‹CQ­B„¯âio/+Q°äÃwnï*å@Q]9qÄ°ÓAR/5†ï5Ó¨4èWHå;TF^nå3GJÔEBqõFbª¯2dSœ)Ó±kVHCµî52òM3ò÷è²ß+aBQ™&2wL§ËMëEM1—I¢€şQJ©!f{@´»Aô\\NóÙ0¼@DYyÍQ¨78#äü´X©:4æà’“ŠµĞ†)Ë(b5=Ï»2Ñ—9Eû#&ù‹î|ĞG¯C‹FŒéìš‘|\\¥Bh^\r€V¤ÂN?‹Aè<mSS0gRQ-ò›ÑaX­Jåg uÂØ\n ¨»`pÎU\${/K(¼ü°Œq!1r¤ÉO,¼OFÈ¨o,\$rÂë{K>tæÛ‹P^P†²Hú[Õ¡c„™Sœ´4oSYK2y;•ff,³VÒ|L%BYÕJ-„kS ^a„kè…RôÈOô…0ppŞÏLv0Š\$ö@)ò¢~•€èHíÈÄ+&5=æ|EŠÎ„ÓJÑ~Õ@¼PºMref±,çìª`›&	Ñ,îÍíHÒL¤ŸUÃfÒo55)M–\\D‹?X­ĞâìL‡gîÂ)Rıiö)LõMSU6”¯Fè+j¶ dërf¶k,ÔSg¯cKÇ]_(æ3Ç)G•Ë´'Âöü˜NPƒiÇ;çÆ|™Z'mî(¿n®:æÂ(º{ƒgl5bô°é'=^æ7Msq6pµDï·Wu}2Q=h-¯(ñ>¨4İµ{·heĞèÔfDL–šu\0ŞÅàä\r* ãqr®\0dTZğkÜ‚ÆDQQÌÜU¥.\$ğ6jìf‚ï4ÒÖóú¶ "; break;
		case "ko": $compressed = "ìE©©dHÚ•L@¥’ØŠZºÑh‡Rå?	EÃ30Ø´D¨Äc±:¼“!#Ét+­Bœu¤Ódª‚<ˆLJĞĞøŒN\$¤H¤’iBvrìZÌˆ2Xê\\,S™\n…%“É–‘å\nÑØVAá*zc±*ŠD‘ú°0Œ†cA¨Øn8‚k”#±-^O\"\$ÈÀS±6u¬×\$-ahë\\%+S«LúAv£—Å:G\n‚^×Ğ²(&MØ—Ä-VÌ*v¶íÆÖ²\$ì«O-F¬+NÔRâ6u-‘tæ›Q•µåğª}KËæ§”¶'RÏ€³¾¡‘°lÖq#Ô¨ô9İN°‚ƒÓ¤#Ëd£©`€Ì'cI¸ÏŸV»	Ì*[6¿³åaØM Pª7\rcpŞ;Á\0Ê9Cxä mËvBZ­!å\"L¨:Â‰dB@0R¯’\r‘M/d!Ö÷ÃDAÚL1p«t×°Ä4‡5Ğêô—E»6N±ga0@E¬P'a8^%Éœ«\"ÈìX‚2\r¯¬ƒxÊ9„Pèc¨à8BS8Â1ŒsæúŒá\0Ã0# Â1#˜ÊãHè4\rã¬Üèæ;ÂC X“¸Ğ9£0z\r è8aĞ^õ\\0Ë’ô'	áxÊ7ôHçEÑ¡xDªÃl%?¾£4\$6Ï#HŞ7xÂB´y¥â<BiN¬HòE¸¤Â€I°B©¤‹‹j/E™h¨*LI\0†¬cÙ¼ÅÎY](9Zu•EKÌS‘‰‰Ir[ƒªP###ÌX6…£y\$’å¢E0¥PBDqaÖG“(ñLN½Ï€‰JŒ#¨Ù3ÃØ:Œ±%›g¤D©Pv'+:ƒÀ¨cšA‘+ÑTT&8ôJeXÂ’ïÉş?N)+tec6OE¸JLœª>€ H #cÃ`A=Cdş9ŒcÜøŠ\"cÌU%¥s jncœ½XØ4}\rÈ\$T=s].ìvE!ÖS‘mßtÃ»æıÀ=okß¸G{”3º+Û°Al–İ°á8–ÃŞıõ­~dqt7ÂØ]lï|’wÛÃBü|È”€Pˆ«–eˆƒHç=ëÊœÉ	AÃw?ª‰äŒ£ÀéUÎuøç\niQcO©‘\\XÂÚtaz¥ØŒ‚SÅ\"Å’ V>90¶±ÚJFJÌÑ5nT¯#,Ëé6AB ŞÁ¼\rÁäPÜšS‚rÌxÀŞÏ¨sQáĞ9@@ÂÃ	õh°\$º”0eÌ¡Dˆş«úd¥e‹ª-H‚5FYk…@ĞØƒZdWAÊ§3ìÜAê<1½ Òú‘\nMJ©u2¦ÔêŸT!İQªPÜƒÁr¨UJ±0¼`è¯•`\"Ñu^Ÿe‚°Ê¹3b„šçV:Åù\$KÃ\"zrJæ \$(óBt²´DZÓZ«\\&§×Ÿr°L	æ‡\0Ò¥ .R\nIJ)e0¦”âT\n‰RD%N•J«UO9èFd­Hm\rx6ª°éÁóÂ€!•A†ö ¼\rjá3‡\0¥ti‘¬£–C‹M©H	r¼%R¢ø&‚¥úÃPĞŸÓ|_Wê\0004†Æ´“ü¾PîV° Â¤:h°%WÀ¶µ ‚\rP*\r­ÍtĞeh rBW–DôÉ™D'Hã˜îk€H\nàPUI¦‚v‘0:ã™~+e|ÁB<YÎA¬jş+†8¿åÂˆA¤ú5àÎXlAÑ2;äÊÓtŞAtÂ4‰ê.*…İJÑø“¸lƒÕÂdNáÍD§6ƒT¸\rÁÁC(…£\$Z€\r¤1†ˆC:™‚z§öÂSõ=§õH0†ÂF Âfˆt'‰l4’Gj.Óè‡cZD)%-vvÓÜš4GLJ“2RŠaFDÈ›Ñu’G‰ÕÆ™w„\n	H)v\\uˆª.)°‹X „’:T*`ˆôİ3§àİ-êÚdRÅ’4ÎˆmˆY/Õàr~´ÙDÒò‚xS\n‡™¸£Ñ`ÆO0„-b°×ˆ{5kİÕC4§•F²‡`¤/¦¸u2 ‰ËÉw4PP\"“im=©VÌW`@Û)(oÈ¦`¨/=s† ÓŠÂVKIy1‹DKàçr‰‹è—E¨–ÄœÑ@fÄÆ'xÀÙÛ&EÀëÖE¾œ¶xÏš¸q¬ßa*aáÍNtuãvØGpëEåèS¯±`Ls,(ù!µ´‰/£é<âtF8–2aDâ2%æº|•NY½Æ/Ô\n…u1›svhuúW§ìQúnØ\$v#ò~ìä34»,äß¬»¹dëCZ¥©3zÇÏÍÕ»¯…ô¿~Z8³\r¶y¨(QæÉFAˆœJ/¢€•EBhXí¡§Iá¨÷•’T%ñô…¿„‘h9Z´v2×¬×ı¹³÷PlŒ‰\")Ëğê—Ñ,\0°dÎîŒl<ëÏä…óø6õLô¡İpºm¥±òÉ×¢&¢éÍØ’y‚’·bD ×xŸ\\.ÂâŒU‹4è=™]ƒä\"Ÿ˜ø-¨½¥Hvp7’’bN?IE)¥Uï§Šq…ÄÀPônˆD^™b vˆ¶§E‘7º%Ç\"”‹#{­_*@‚Â@ ·ˆ<4¦4ì¥-¤êMi¶Q~•-Eˆ°¶ŒL0@Á=ÄÌ³QÙÑø6t§Äx€õ]qx\n˜dê°±¼/¿›š§^&¶W±%;i4;ÔÈ÷Xµh£õÒ	ÙóÛÜj¬×ª‘ãöY—½‡ƒiƒÒ®»~µÑˆé¯\$Ã§õÏaDßsDº‹…”U\n¹D…¥«t±KZ‚ğ@|0R	rût9oùUn­ö‰xéøh¬Ùœ	ÔfLÙe+Ízú¼ìÑ|¬^KÑÌ“Ü,HÍŞc7<Ÿ§ófNu45ãèœ­ĞcÌÎÛ•kşsgÙrèı†`Øî“hJ½Úú4ÿsÀşçTEÄP4ã\$|…°¼Ã=Á\$vÄ\"„#F8a7HØ*îKN›ğ*ˆØÈÉ¡a-Ö¨¯àpoæİÊŠ}~œ5ÀR5°:FÄÑghÎÏ¶î®XºÌâÑ”É‹ÖÿA|3hë`ÑPwkHÑÇZÿp0’C¬ì¡{¤n3n6/NFä¢¼™0®á¬'ìô-Â ÃJ¾ïâìĞÄ×,NÅ,V ÆúÎƒöápJı\rç\r\npÓ\n°Öó0í\rÎ\np˜¢‹c”˜ã4<Â–×¡%pº)†&\"ˆ8ŒĞv'ĞDD8/âÌš/x©¨¯M,÷± =\"<fŠ‹pqÏ&+¢¾,!*™ñ<špıî\0]M†ô Pâp\röŞFÊÇ1-ÍPI¬İ…É×KÍ	gYø5ÖåËeøÚï¡Ñ­pJÿ0›°Ø.PåLyl‰q¶Ğá<3ÁØBZ,ˆnád\$0ì/¡\"clmÃ¥NHäÏWkİ0· Ññª~Ò£láÁ\nÆTÉMâ^¸8£™\"åã\"¬GG\\ÚK	#r@t`\rT'.>DBD7¯½ĞäÌM¡ÏÍ%Î&Ihı²làä2Ğ2%\nÒzár9%¬S&ñ³ƒÂ!G½Ä5\\Ef™&0é'hÒ™dTbRs!pÎ©™*COõ2	úšàÉ P¸rã†±£ÌÅl-‘,nemèêL ÇàJ¨Õ\nK.=ÄB?b=Há-¢›Å®1!jøb>®Âêg50+0‘èîòòûïª%O®û0Jh(\r€V¹\n¸\rg”A¤îd&FOêRVÀÈ\r Ì€…( Œ›¤ş\rªĞLêÈƒ@ª\n€Œ px¨‹5ãìîÀÈïÆúÇ6Ãâ<#%1Oèu/îYmÆ²,4ê\rÂpge°…Î;D;¯”2põs¶¾¢Äg¦ü#_<A\"âáÚ)Ği+é\0¿kXnàoá\$Ñ%2ŞFÜÂôÅt´®à½#<3(Ú+ÂÈÈŠ;@Nf¥èg&8dºáf‘pşËk¬<Ë\0@v#ÉŒb:6ı¦IA\\!‚QEı:s+ÏÙE/Şü0k2†§LˆnÄşœVy@à‹äºÛé#FÃ#Q±ISòtQ.ÑG&FfêÁ/\0p`Ï§&¥ÍáfCËÆÁ¤¤`ª0¡d?2î±î&ÕQtÅQôDmT¾Äraj[0r¼gJETFÇ/ft´ly\n!\nMf6F))ÕFi–n\\£AØk¯)a00FeÌ<®¸!a`F\n&"; break;
		case "lt": $compressed = "T4šÎFHü%ÌÂ˜(œe8NÇ“Y¼@ÄWšÌ¦Ã¡¤@f‚\râàQ4Âk9šM¦aÔçÅŒ‡“!¦^-	Nd)!Ba—›Œ¦S9êlt:›ÍF €0Œ†cA¨Øn8‚©Ui0‚ç#IœÒn–P!ÌD¼@l2›‘³Kg\$)L†=&:\nb+ uÃÍül·F0j´²o:ˆ\r#(€İ8YÆ›œË/:E§İÌ@t4M´æÂHI®Ì'S9¾ÿ°Pì¶›hñ¤å§b&NqÑÊõ|‰J˜ˆPVãuµâo¢êü^<k49`¢Ÿ\$Üg,—#H(—,1XIÛ3&ğU7òçsp€Êr9Xä…:9–Vî>ã³î›B°Â94-\n–†Šc`Â8ƒ	Š_\réª\")#jâ»Hô¶B‚È”C«¾¿Š\nB;%Á2›\r1+¾•-BÈ6¬ï¸@ö³ì³l†4c‚Æ:Æ1³éK¿\"Çc\"ôl¨îì„ˆ0ÅÊ\0æ;¬c X’ÀĞãÁèD4ƒ à9‡Ax^;ÎpÃÇ#\\±Œázâ¢#œ·.…át\r«*³ŒËVãpxŒ!òH ÖâÊã\nP«¬†R£­.b•c“¶¼¯këxÈ ô2Tî=Tâ.’6à¡Íœé±kP¯Ë8Î†„£\$:‚B#˜bØë*	eØÏK²»;Š@Ø8.ˆj>¼Ã|4¹@ñ¨êĞ„HÜ1¸Öøèƒ*@:£•bX:Œ U)K/â4L5ŒqˆŞ†ˆ#;Œ3ÈÑŠÂ\$Œªâ*¿³c˜ê9B’4¯Ï*W	­ƒRT‹•hä5¬\"bTì­B Ê”\\ŒâÌ†ŠÃ*9¥hmô6\r[ZÊcÂ7;‚ˆ˜°˜×%Ëœ4¯c“¬.­Ëfè<µBùŠÇtĞİCÕÀ——8hör4?ÎØ§Qaî&¿°¹Å½\r–v<>K¥û;ÿ\r,Û;!¢HÛ!APŠ”o–K¹²Aõj¹•Å3µêûn²Kİ‚'_(ˆ›5‹‚r—¬o²Ñ›óa:>ÃÂæ7%4hæ’dÙBÔ)äJ¼°rÒt°éAÈ^å×¼´5°ŠÏã.×JàÀR9ˆ1ìŒÊã0Ì6G4˜éDÃ:ş*\rè²V7:P:ÈòHÍx-ãzÍÔKô¯Âä,áãfGuÆ2…˜R’	U\"^“Áß-†™ûbRpÏòÿu†Lø’õoÉrÂÙúŸt¾İSHGI„É&DÌšRlMÉÀ;§\$èP“²xOA¸ÒĞ£!`\"Ğ¹£õ¤OÙŸo\0‚=B”	zÒå°”‚à@İ 	Ø4Í4Ë+¢FIQR:O©ü9,`Ê¾/Q&5İÓJkM©½8§8+\nC’yOq]Óº˜b ÛĞp.ªƒçB†B4ïx“¨tˆ¶“ß.!Ò¢D8D]Èk,ÄÓ°ò<•!Ä#ÉíeR40	Mš!Ó+  zXÁ„33~ÃŸ*~|ìÔ‘>¹6•\r;`!…\rĞ^¤`\r¬µ–Ô± i\r!¾^–â„GŒÙq\"Ñ2DB*PàP	A8‘.GV\néı€ †£Jc›=¢ã4g\n!zEÉ—š£.•Ù oñl‡<¸°ˆò/€%™BµWeàTlù?òDRCò>¦¡C†àà¸ÒÊ~K‘VMšÆ8é¢OK*Î‰pe\$„<¹£¾P\0C\naH#G²4ZX Lì™Ä¦p]E›]#ê)Ïå¾jšH d å’@¬oŒ\"É£\$œ”’²ZKËƒHBe‚h6Ó†¼ÃÔC‡a““hŒŠI©5>'dÊ“£^BÃÉGm!\$D¢–1¨5G8¯C†GÏÈm‚pPÃêÉQ–&¨ˆ™âHÑ{6‘'…0©O›dFll\\_zmdmB\r,œŒ\$PäzRÍa²Õ’¡(ĞÌYU!_¡ª¿c†“¤YËsf:µ3£êhÉsIˆ²‚\0¦ÏˆüèU…€`©5)£òoi\rù\$@Í^Hb>æ¼®7†A)'x@*D—×°t-Õ‚äsJãÈ¼Ë¬)­j³0ÒÊ­¬W8ğ~oŠsxlÄæ†ÆVC‰r’,‚\"È¬&¹×;'nÄ1ò7\$…}ÃEqj`Ò4Gò?Áå\"’¸K9†Ô™‚Y†°[y\$mK¶ò^DKC.P)”}e”ÃVÅªá±†¶ÊGœ^,qØ¡\\\" Ş’šnñ	\nÔñ´³lh'ü\\K¤@AÉtèdGšsV`†ë:N°20’‡—»á—óB”¼w”¿„Ë½~ËPLjİ¸ÀÒ—¬ßooİ\0	Ä^±®z‡’(JiuŸçTÉèW²•´Ö9µÁ“›&¢¬š¡gšt—\0¡Š(	dØ@„\0” âÔ•™ån˜ èİSÇgH—;gˆ² <¬æ	§,†yS\nüjpÃy›2Å\$ü—»Ğ(P]fJ)ÛÜŒ’%©_æ\0W3±qYq 8Uä‚\r±J;a*@‚Â@ ®¨\r¼úÚƒ-Ü—¶€Ğš s)KÕfòœ³VM(A^p ÓÃ8Ng¿€3dtöñ3B5~>”zQÂ§\nu`\$*ñV‹Æ§)‘Ó®@ïAw\$äÜ\\Œq“ÊÑIãÄåcq]æ*Xã+y4|Ğø± ë£tOÍyO7æ+3¦ğ{Ô_\\3ªô–ÛÖ9UFê9Õ‘¤CòÙ‘1:a¬¶=KøOŒâ#°KöêPÓd¢Éº‚†è‡,·0ÀWpÕÎ†&%ü€æ VìdxÆÅ€Mg#Æ}¤NÒ0*òFœ€Ô-úZ‚NÈ,…/gªĞØÒõƒTaº”À«ÙÛzOzÛ¹,ó«_/=o\"tŒ;rû-0âbYÑYD’{üùíZç_k\$zS>^{šØ¼‰_¥ƒñzš½—²¸b\"Y‡»/Íf5øIùlƒ;|pÔı…%‡pìeÅƒwö¾Fùg¸ /¾ˆ6ÔbP»\"öÂ:5í8%8\r%¸½ª°[ @T&ÀRÆ¯úúâÔÜ+2qä²Æ#°\\TÇL†mä\\@/¬ùP8Ça.ùÆÊúW¯ƒ\0ï’úë&–Úâôµ\$°ì¤çÃï©	,0tÛ%K¡^æÎ7}\0Î[0Œ?-±Qšåìšğ¢úpºÅ‚%ír©B\"øeÀ—¾<jæ»JQ=\r\rlxpÅ\r†âpdÔíRxi–7â:Õ°äT\"ûi,„:’\"<\\‚\"#¤z#¤„}¦^Ñj¨gÜ#`òx‘ñ‰ ê=¢–‘DÓ®î7ì‚ÜC@‘Ãì(¢b®÷ÖRæKâÎø'Ïƒ¸¢ãü,.rù-dÀí‚ªå®VíŒ@ÌşÒÏ‘\rêŒÒ°ÚáMöQ„UêP÷^—ŒzÇååc¶0-¼°‘”Ê/pÛ¢)P‰±³¯—RUn°#\rÉ‘ÒØÄDNj§X †<ÂÕRªdZi£=Ğ\nĞÊÉÑ–úÿ	1Šµë\nw Ñ o¢Hkbñ…öµc·\"\0æRéó!PTÃ™mÌ|ãã\"’;Ò2Èò#€ò8ªo¯¥¸×‰PÌöR`@p‡ªØrmÆú—’,8‡Ø‘Õ#Ò'MŠùiñ¼;X@ä)Nîö¹íĞABdjÊ ğÒ’«*É\"HÔàÒ¹âü-R\n g¤²0¼,>TBşYƒ3\$Ãd(%Æ rİ²á!M<ü’í#¦XÒâÉ8~Å*6q]!âD€²ŞXÈ˜a©ˆ	Òë0ÄQ¢_P„æ1s\"0œä,>\r312“61n”e*\r€V°\0Ò`ÖtCêèEæ2¥ÈP¢jÇÂ;\"z¸@ÚÀ*:~ ª\n€Œ psÈ0¬@Î\$“,ÉÂ^îl)!3Œœ#Ó”Ãÿ.~ís–¤`Ê#¾[Ä°rŒppÏ¥\0@šB²Æ&\"\"¸v€8ÅRb¢,bØ/dp8­@Uc\0Bü3CÚ²óØ96Ÿ¨\nÙªŠù`Ş·E\n ƒJd48„B%Ä‹\"O¬|ÕFXsÄ ô^Ï3Ân: ì™kÒh!B›B—˜ü)\0ÂôRø´?.‚*‹\$Jİ4UF/… LBÆ€¨s\$z2d“ÂâtD dm\0”`Ó/Â:E[l\naUqF³1fúeê@ÂÈhd	'ä¿Ä\\ĞàTº¿à˜ €çDb^™4RönÒ€Æ ê\r 	ôŠG6=À‚-©Nó'¯bÖGl&Æ–\n”ME\rd>ô]:\"‰KeJlv\$CQ< –\rôi=Í’>àî,‚ç%\"´æ@!³ Ù£\n2)£ÍO8–"; break;
		case "ms": $compressed = "A7\"„æt4ÁBQpÌÌ 9‚‰§S	Ğ@n0šMb4dØ 3˜d&Áp(§=G#Âi„Ös4›N¦ÑäÂn3ˆ†“–0r5ÍÄ°Âh	Nd))WFÎçSQÔÉ%†Ìh5\rÇQ¬Şs7ÎPca¤T4Ñ fª\$RH\n*˜¨ñ(1Ô×A7[î0!èäi9É`J„ºXe6œ¦é±¤@k2â!Ó)ÜÃBÉ/ØùÆBk4›²×C%ØA©4ÉJs.g‘¡@Ñ	´Å“œoF‰6ÓsB–œïØ”èe9NyCJ|yã`J#h(…GƒuHù>©TÜk7Îû¾ÈŞrÙf²\0¢–6À“·°3„øÎ3¼€P–Š j0ØŠ;I¸Î ¨ÍÚ::¢`Şœ¹+ğ	B‹ê6ÌA€P‚2\r­K \rã(æ‹è³”8z,0ŒcL˜'\nu/C˜ÊãHè4\rã¬^‚ Ã˜î÷Œ`@ c@ä2ŒÁèD4ƒ à9‡Ax^;Ër‰Î€\\÷Œáz| £œ\$á”\r¯|vÔŒÏzZ„-!à^0‡É( 4­ªDê»h*€¡ îˆÖÑKÃ‡\"PÉ½£„\rbì	.zhÃ P®0MRpˆ„£#\n<¹àMKS¹èˆ–7Àîè”Ÿ1ŞÎ0\"Z|Œ¹‚„7Bu\0Œ„22úPKˆ#8	ƒèÿ\$RzC0\"@·'ibn0…©j0“:L˜\\®‹\$(ÊÚ®‰Â„ç¥ğò^·	-óÂİ¿£pê¤RÃÙOv)Š\"`ß¨ R`Ü0+Í(ÚÁwóRj@æÌ%®ò)c¨§È‰Ä‰ºµÓ4˜³¬ûzÅ£è©~[—ˆMJæºßÀV!‘ÁĞ \"YÕ’âŒ@Â1\r‘Ú”=áw¤iQ>›¥Œ£Ã7&K“Œ:Ô'VãŞ·Úã]ä<)5w€­Ùª~·ã-nç0àPÙ®ÃÈ\n7IÈŞ3ËlÂ’Ã»ìŠQ¢£¡¬*n›#oèä1¸YU‹	ÛNR6ü')\nG%&\n0AÊ/V*DË(­ï2Õs}ÂsÄP0®‚è›\$¼S–²è5•Ã`(ÃHÿ¼•ìARW\$Í¯Y@74HÜ˜IÒ„¥*JÒÄµ.ò÷0¾\$Ì7ã\"ÒŠ+LÛ8'Ã# –ÏSãe2ÎzŒ¤@ìéĞX|¼2æœ2ö-¤•¡LìÍÚB?`û)GòrKI©=(¥4ª•ÒÊ[K©}ä&0ä™S;Ujíeò&àæ0Ä:5@ë9¡4jà3†T`¡²\rå™w´tL#‹q¤ì—€Ì`ÈhGhÁ<´zù=ˆ!É:‡#8©ƒf9áÈˆTbŒÃ0u#á±	š”xŠB?GaÌŞ£°Æ“ßsĞ.!±­—äo\rñÀRD<ÃĞ@@P&¨(‚”\nIÑˆ‡e¤œ˜Ò¤U\n²~:æôßÇã0AtƒF¬œÎèJŒ‰'¤ş\$/('ål\r\$è'4NŞÈ*25æî5K7ô‘RGJ˜;†€ÒÊB!éQÚÀÈ•ew0ìŸ\0†ÂF•:UAfèƒA7Bq±<ra–(ÁœØå‡¢ºï>ÊPÎ4Ä²Œ´ÙTÎrQ6¸&¹!8:Â”IBQ dÜÆJ\r&ËĞvI0!„l¹‡}0\"7C>’uQ‰-ég9B\"á<)…Hâ×ó'±°øtÔÌPe\r8ÙöÄgìêBÔ¤µ³@ÛI˜,l‹«ÂT 'Ã“Dæ•»IÛJL°F\n’İó#&jz2Çôû“xdéCFœ5`Q¢h\n	á8P Tµª¶@Š-rX0,³+öïé¾V”49Sˆ®—b\nRIı@© ©%¤ôGQlé@ÜX\n‰h@ˆÙƒ%ËÑ8	VC¶ój7€Ò8ŞJ³è‚™åªŸS­A’èhƒD¥±ı±&zÅqü¶úÙ6¢~±î‡ë>ÁT+×©ÒAXèD”©éq†Z6GÒBnä‚)H]Ì!R6‹Ñ!\r7bñ…0ÊHbã/E´:ÎúH\n	Lá’pÒi`%¼è‡~®‰ÄP°¼İÏEpk±˜:”9¨lkYÈI 0Øˆ°âj_rñ\ndÌ¨ÒOK–l‹Ø¤ºÀeñjäù\"#cñdA\rØÖé¸nĞã9>¦•Ù¼“±AI!•aÈ8i3ÌıŸ\\tñøQ§@<»S‹ˆµ¬'e¢rJ˜‘ ¦8å°äHZ¦àƒ8¸ì ²Ö\\ËÎÜ“²ŒKÖÙ€IJ¬ÃŠNƒPe.‘ÔÁĞ6BA2#yø—FÈUW³®mÏX—>Hœÿ¥‰v˜	šUm€¥Ğ©´)\"dV¢‘ª¿õY#¸Ô‚w6PnfÊ”J®ñ‘É^½?66äkxïÚ\r9šÕ„#¡£’• @€Ö‹^¡`jÚ{W%^âé£ÎõÕYLTæ“#ª¦L…'~ò'5›ÙKº	¤\r¶¬u‹›K¼^ò‘7šRß¼0G÷Ñ;¶ëï~ßÍÔ¥	İ† €“Æ6IOÓ¼_«üŸm£ò°ç-×îÜOàæBÛ.GÏ¤İÃB4­\"­<Fár®H°xÂ˜Ãd¿ƒÛ^~•¢'ÃX…W+g!LÅ‰‡ ¦¿¨K‡jiŸ\ràvçLZv#Ò)\rz.ĞŒÔıÑÙ'5”\\>×Ú³Ãy¿Nœ‹ÿ&O¾´NØ‡cĞz¡k¢x·¢t]_ÇxÂ±ö{m‚e(KÅõˆÔÈÛi\"y¿zî}ñğÓ~E`'>&‡(ß\":«1BøˆF<È™3*¥yÍ²©qà0¤¬A‡7dB9›´Ëø£yäQ½¿ sĞ«#Ê©W@]ïÖMük¼†\$Ìt·—åAŠÔ}}&{_wnÂTŒ´ïü¼tß‘ñ|7\\ÊÄääöO'ºWÍ\rÒK(±òÃOa:^Ùî²wáÃ¡%ÃÑOòNg„ä¿i(Ú™£«[ÜB>FT(ÄÒv¢i¶ O\$2ƒìDÂ2RNÊŒÏ²ã­1\0ï”üÏÂ8lÎÉ°áñ\rÈŒ%F-ÏÖ¹\"Î£ZQ¬­\0ÎYÏ¦¹P<\\®p5DN\nCœ\\£´ÆZíÏÀékuløĞ Yìw.ìıÔQåòÇ‚\"´Y0b/ˆØúÌ¢Êl†ËËhé£ü@„ìs	°©	ëkÏèh”Œ—MÂn£¾BşUp8”îÚï(È0%VG@Æ…çGD:*ÔÂ|!êGÍÒßNŞÑB\$ †k Ø`Æ- Ær(î5BŒ2Í\$òŒ@\"š%c8Ê\$nÿl®†ÀŞª ª\n€Œ p\$­í®ì6„\\’DB±£vâi¦#m8×Å	mL8Hô0+…+„Çí\"Qñ\"3„À0‘Xì¼‚ˆX ÊæÎb„\$(úşöRB\\—Å±\n2ÒĞêpğğROn_Îì[Æ\"bí1®\0üoN˜ûl.5Î‡ámşîĞ£QÈîÀŞ6&n*ª6¦úRE¬¢Jfu\"hâf`\rÎ,ë¥Ú'*Bb‹O!`@çl8ÊˆÄ£¼í«¦F\0êø`š?Ñï#»#èz_ÃV£êËğ’;â4-Ò?‚\"\$ÿ˜’ëzê‘;Q&8‚Np@\ràì:\0î0ãŸl„ã’¶`ä½Pım@"; break;
		case "nl": $compressed = "W2™N‚¨€ÑŒ¦³)È~\n‹†faÌO7Mæs)°Òj5ˆFS™ĞÂn2†X!ÀØo0™¦áp(ša<M§Sl¨Şe2³tŠI&”Ìç#y¼é+Nb)Ì…5!Qäò“q¦;å9¬Ô`1ÆƒQ°Üp9 &pQ¼äi3šMĞ`(¢É¤fË”ĞY;ÃM`¢¤şÃ@™ß°¹ªÈ\n,›à¦ƒ	ÚXn7ˆs±¦å©4'S’‡,:*R£	Šå5'œt)<_u¼¢ÌÄã”ÈåFÄœ¡†íöìÃ'5Æ‘¸Ã>2ããœÂvõt+CNñş6D©Ï¾ßÌG#©§U7ô~	Ê˜rš‘({S	ÎH<¤¨Ú\nhkˆÉ=oj9n°ÃŠãÆ4ƒºšâOÓşŒ P’7%ã;¶Ã£ÃR(çÈàÚŒ€P‚2\r«Òé'ê›@m`à» pÆ’nø@ëµĞÛü<m‹5´Oèç8®ˆxëÊ3¡(:7AĞ^ó\\˜Æ+Ûğ»ázft 2áª#R²¢7Ë°Ú¡+xŒ!òj¿¬	š¦ÿ.CW+9ŒjÄŠe:£++Ã¼†“Í£›ıF¶¨í67S´ø'+Ã­44¥pƒ¨®Å(°J”ŒCÊVÖil’BXŞ—Àb ò8C¢Ş6ÅcrLêEÃ«T\rÉäV»Å0Ì®0£b;#`ë‰jò,#£uq1ŠuºÈ‹Iû–º¸–ì	!¬ˆ‚3%ö\"PÃŒ¶#”Æ!i(@ÂŒé\\]s×—#Ú6É`æ1·¢˜¢&{Z9BP¢ë}28³œŞÃOäT¬ˆ§	Œ³ÜKÆM”eSİßŒPâ+˜ P¤2Âj\$<6Çc•Èˆ£Æƒ¡ÔG™22.Eˆ‚5İ\"¤Ú®J¢¦»	°–*¸«°Œ£Â‰ ¬Cšj*`#£\$“0ŒŒ4k¬È	ˆüæKĞÂ4ÎÈå\n7\"Ëı.ğíµK3(+¨–\rã5ñ&¢­YÄtpA=ÃÊ	Â£OÖÎ¯C›£HóÃÏ¿¨«Ò¨”Ì¶pÊaO+Ë£nØ@Ô7ƒ8Éµ³Š§<¸ã¬ƒ„|Ê†º#Ò4¸Á¦ƒJÒÄ´§Ë²üÂ;Ìq„e3SLÖ2,SâÅ8‡ßC¬:;óıÊÓOõ<¯**›‰|¬şGËt2ª¤îJ‰c˜2äİ\0œuJ›ÚëFp•á½d¨öRÊ[{©1&GÆ“Bj\rÉ©³(dûÓsÁ% %’>kNl€ŞJV{L*\$\r9Âf	ª~ä‘Í“òˆïÃ*~x†’–ZŒ0T é\rs¨óÆÂÉ¢!¯!’Â”Yt.Œ9ºPØéËÉEI! •E0æƒÉ`cJÄÉe•ºÚŒ‘Âl­œ¯G’ŠPC#.@\$#\"!#aæÈ5³S¨AUw@)câö¹ŞbÏ4a¤Òšx(h‘áS6hİ!˜@Şb^E(²A\"¾dó„x‹Õü¤2*›Qü6\$°½œ“TôæÉ	¹²ğ…\n4m+`ÿ‰V\$g)… ŒOX.…E(qÈ9‹‹d¯v-Q5&ää“ÔPÜ,8D+è6Èl6#mhš„’(LÑÒz‹@§Rg\r‘SJÁÅo° ÌS	ëÎ|s(¢†2^‘Í‰³M¤pš…\0Â -iÒÔ¨²›=æàk.NÈ™c_=¤nFx‡)õ4©kazIà¼)TøI«@¢šÂ^Le°b/\0‰1@Ìi‰a¥:á*Hb¶TZ<‡`‚‡¬z!5ÅØ2—‚ŠC\0\nzFõÀ\0U\n …@ŠEë€D¡0\"×ez¯Ö:@a@êG`›Á3`¨tÜ¿x•ùÛ¥¦¨ì“ôFÃm7\"áµà©sÄy­n™óD–4æk3PÉû´ñâ0Ôq¥K4fÊ!4‹R‘‰b(i‡0(ÇF‰I*—:‘ØÁ²bM7•“ú!©2ƒ¨fŠÈ„Ñî6,I:*E’”9Üâ«gª©1'1cVËcq;¤…IE/˜2Q\$­ÍË\\yNa7QqØ¢RiC(wRêfô4<XÃÁ‘CäË”VH“ŒˆB)‡0\"òûv&9•z†zÎ]i'·f0»¢+—‰Á8öè0®ğ CaòÊ%è÷ªÃ¥°â{(`*Ş†ô~ª¼²Ä:fB‚aîFx±w×P¨BH0†®\$†¥\"9\r\nV\$—ò r\$@”‚ğA™¡W¤Ê™¢·óU…€Ÿ¥gšCTHc¶ \0œŞC\\ñí™Ò&s›J)#b¼7b_—cóbìf#è(pó©—W9…Ï#—DIgĞZâ‚ì×”ó‚\0ŒZ|9êå ì¬MNX:cs\"ŞQ‰)Ü‹’KUê10‹ó_–:ŠK¶Â6zŒ’!<Z‹	¨V\$õ}\0Ä«3‘E ”İı}·5~¶;†«-Ï2?WX\n§ÅQ'—l`K‘/ò,¯GB¤‚¹RTÁ¸•2FrWxIU´ÖûÄÙûÀÑAS±*ÖÃe4#Âmw§‚Z\rKU	_§÷†v•Æ±Ğkã·¦ÄX¤d6áÏ8©#•l0ˆSƒZ!ö¡*ÈOht–<:ÂÇniÏÅˆB'Saà¶Ê¨øWŸ¸ •,5Á`	è(`„¬ªs€í’Gµ¼ZØÚ¦lAÎ©?\r½vÜt†RsùùëÌìñ²Jîõ®™·–ú\nd_©&†Í›ŸvŒûqÿvÙ˜ß¼ç}»ö^Ã¸}Ïu»_Á€V¬¸xVÍÂüW“ñ›ğÌ\$XkuF„t5õ“HÂ[…p Ø©m#8„‘ğÜtQ™!L½`Â¯ö›:D°zRYı4z>¾ â¢:kâm¥?~ıe˜o%Y¼® C8XÁÔÎ/[´5ı`ƒ´dTÆòı+ş¯l6Š™ó'ö©¬˜Ñ2\"H‰¼WÇñ|†‡ïôån4ó\rÔÿ#’‹ÎÚó¢jÿå”TfJ9G¬DÙíÆçnPbÄ<‚…ê_€îqâŠ:Í0[Bš\r#¾ÇèÈìıï ğêNĞıYs/è\\…Ì±ĞÃPLÇĞ[l2SÃ#Æip)oĞ&]æv›Ãªé¥ˆÅL8ğTâà¨Ä¬Tınß\n®äé\n¬RÉe9P½\n0ˆ]à¨'D;,ADg\niR=L’Ap°áá\rŒWŒøP`Ê3€æ äZ\nD–äã!bf/c˜Cå\"!eŞW#G‚¦5\rÈÏ±î€âbÎŠŒ\r\$~!¬ĞvCTYbú\nmÈ_Q>áÚó\rNU†R*p_ †R ØjT=Í\$k£…®ˆrz&BÒíş1C.Ê sg” h²v@ª\n€Œ pn§¤\$R&­Üğ‰\r«&„ -JÔï\0ã„#‘²Ë #4(\">\$/ºŒâ ¶…ü`š†àÒÌÆgb„ç %ÿ±l¥¢<> @Qô\r`Db±z±CŠ6XµÆZ;bj	„ˆ¤ìiÆğØPJ°ˆÒg#xÈ¦²âš#(\$…²YfÜãÂ:0°ˆ.B€•2P½RVõB%ÃŸ#Ãò6Ó%HŸÏJ¼òc%QÀ(°Üâf*c83ÂŒ8Q0'KØÒ†ùC†S\",]±v\"Š´Ä8İDRäæB`k`ÎŒÄE&\r, ¬2¦t#ïĞBE(@k'Eº*Q¸EÃ¬\r„.é*R%.à¹bV/ ˜2˜›’l(dlåD†,r*íd;1:\"Å}(Æ\rëZ.ëâe€.C|ìÇ%Fî	\0@š	 t\n`¦"; break;
		case "no": $compressed = "E9‡QÌÒk5™NCğP”\\33AAD³©¸ÜeAá\"a„ætŒÎ˜Òl‰¦\\Úu6ˆ’xéÒA%“ÇØkƒ‘ÈÊl9Æ!B)Ì…)#IÌ¦á–ZiÂ¨q£,¤@\nFC1 Ôl7AGCy´o9Læ“q„Ø\n\$›Œô¹‘„Å?6B¥%#)’Õ\nÌ³hÌZárºŒ&KĞ(‰6˜nW˜úmj4`éqƒ–e>¹ä¶\rKM7'Ğ*\\^ëw6^MÒ’a„Ï>mvò>Œät á4Â	õúç¸İjÍûŞ	ÓL‹Ôw;iñËy›`N-1¬B9{ÅSq¬Üo;Ó!G+D¤¦y¨Ù°G#¶Áâ…[NÆàQB<ÎC#0‹²<2·.[z¶?‚‚È¢ãsœ69k` ŒƒjØ¡ŒƒxÊÑ<îpæ:¤kCœ0Œc>ƒ.A\0Â@2‚ãHè4\rã¬Nøî´`@EãB|3¡Ğ›˜t…ã¼¤1pÒ.9Ë@Î©a|z9qü„J(|6­ÂØ3-Šf7Áà^0‡ÉH¬Ÿ·£\$b\nÊ‚\n:<#Xè:ºÂ+RÕÃHÊ;T3TŒ@­‚Ş'.#\nãä7-ƒ8æ†Œ\0Ä<¤\0HKPÔi>%ğ¢-\nƒÈáUƒhÚ¥À®¬/\rë`ÖŸVğ“2ŒÃê69Ã²Ü:Á¸Ø3B2*–Sƒ\0)Œ5²—b”âäÀ§nğĞ;-èÚÌ¨£0²ÔÁ~É! P¨§#íÎÆBC\$2\r”í£czğc\$ÀŠ\"`Z5¬°„:4Ã‚.#­İC£Õ#ãtz\n5C+\"	é-d™0Ããêç–äâ‚ÓD¯+[\0\$£…ÙB¥¹ó”eLH¯\0V=A …>*r »/*#DÒ)z¢0¢„\r&¸2	\0ÜƒN›İ@)n8'&»¬\"@ƒ~ºŞ¦p˜¨\$°„¶e3p#Œ«+´O®Ç´©˜Ø	ØòÜ1Ì\\åÃ6‹¶PA^Ã~åR¨¬GÅc5B»…@R­°Â¶0ª%C+GC(P9…)Hª:Bƒ:×¾­ˆò„0iH¨4^Ø€±c{JÈT„1ì£K€ÈbI2Xé&ÉòŒ§*ÃrÄµ.\rÁ|:¶ÍÿHD}p%;9N‰I†LP\\ßxpZ0n`*y	¹¤nTĞ”’²¢j_@\n„¡£ Øÿ bCH©î¤Äœ”wJˆeò¥ä–Òë`lM‘÷&fxÊÁo/Ğ€@­Ë!Œ7ÍZœ0Î‰Ù«1‡9Y‡\$ ®³øŒ :²lFiÃÍôÕ³3Âp4ÏÌ¢àÆûQ¡4TDb\"¼µn¨Cfo\$:ºDÀé˜+½vÕ“(ºÖÉg\\’ÜÙŒ9s)ˆéD˜Â€‚Œ‚EÁØÖ'øœH!ĞP	@ÈG¬OÊ	®”Òâ¡ûi'åü”„4àEâú&MÈí†–pKœYDÆ‰?\"\\lOA}wAœ<†Âü‹Pc½wêy5¦Ò<µMšö3&­?\"ğæQS±Fé¨7tò@F 4†0Ñ\rÌÁÎ.ñÚ=,bƒ.åì¿7¡)… ŒâCzïüÁ“ÈÙ)‘0dfPÌ’VSÉy12HØ’R~·C	ûk§Ô9Ebû>ßû†€d|Ò‹€ÖÕ‰HI\"!åÉåC-¤Ê¡FéøŸ™E1ì&/AòÎB˜Ğ¢3¤f‰—2iù¤ik}¦©ğË( /„ vÚL`nHEà¡®O	ô‡)‘hÓ”Vœ	0gP“²—â| ¥sÅ1Ä5™\"8G‰¹\"\$…Òœ¦ÃAi&`ª%„`¨™g¬ı—JS3ˆU)e!`FpÜ¸L \n@\råğœ¨P*VQcÂ E	Ê‘5[)ÚÉû?ªj<·“ôeHY¥¹hZ—|L‰8 :\$h†È×Iµ¶ä]fJ¦ÀPU—’øµ#bUYƒL5Ï €†&a™Ø\$1>'‹ŒÓ!Ûh×\\12lÙ•Ño¬´µ£¬Ğ*ƒSír†9¡åtJJIiŒÊ*	wÓî®ká~B;%<ä”•¼½ÉiÀgì*j™yÃHzA™»TùkPD=–ä•ØÂİ„Kì\0QJ1>¨µíƒ‰‡1„´À(é˜¼1aÖ	d~ÓL[ñrµÍÒØÒ–Ma—		-áP´ß³‘,1ãP©µ èŒd•Á)kÔ¾\"5ŒÆ]AI>Uá›ÍÉ:fô1(e Aa Rb¢š#4) R-±\r&ùÅ¢xPKz§\0¼«è#2¡Ğæ\0%–æ\nƒ4A9Á<%¤-bà@¥Q!È„ÀÀ£h.Ò@ƒJimbÊTÓzvŒ˜‚˜Ä²ÿCÄáê\"yr4eÔ!ÓH“Í&Áv‰ëGëÍG¥	HKˆğÜ†”4s`wSå%\n2:GØÔ­[\\™˜‚U-±@—P#¢`L\0W¡Š¢ĞÊãloÎÊˆ\$à·ŞŠÕ\0001fÏ%½>š+ºj÷Ñ'_­à´(¥ìNÉ‹3Ÿ,¾¡—ò£@RÕ¨€İX¸•I“Öà81âu8Ë_â»Œç¼lPl¸ß‰÷S©\"èPÊZåœŸ—ÔÊë-/œ&‚lZ\n5*h2PÂü`0Ñ\"jÄ0ÿò¡2\nõ@]4™Şå¬uº§Ol¼ñ; u‹+“%€ó-«{tVJÒÏªğ@\nÑŒ-®„ŸÆáqÊeÌTW=]*ÅİîSÚSD”ÓHá»©)ğvLñ¾oÅî§‹@üPÔs-“£€V\\Û¼ÕıkÓ©”´Y4Ú­â€Ÿ4†ü¦¶óîËĞêŸFtåbÜ¸ü¾Øz-¼ç*ºvÚOlñ9G0c›’Âû\\‚Oüçå{'Wşrá¾7ªê¹+ıNò*ÙV÷_µ+4…ş´\0Š…¦ÎÅ¦~³‹bP¥¤/ ÒÌÙ3É©ãDâ¿\nø¼dûŞ-¯|øâŞÆÁJÊå\$0&heìr7,Ü-†í¦.Ä îŸo„òÆ Qp\$â®üº!î8ç¨7Kd¶åÄÀŠÌs\"S¥póÌ¬PTBO’h¯–d#V¬À»&V)°n½4]@¦\r„t]@¡‹ªT(à\r<“0.å”:ÏNB.S2Ë°¡nÏ\no6ò”÷ph#L<®¬?ŒrGƒØ( Èqäe+´0¾Z„á¶;kÀ/°Ğó#PºVŠ8ğNòb¤òZå‘\0004P¹l–ÈĞà0‘m@¨zÅúæ0”;ìÍıÃğì¯†%#î!.m\nÄ_Àæ3,·\n„ìö•©^gC¬È‚œ¢ç»¥êĞPä¼)º0‚ô±m¤V(jPf	e\\T	V …Ô íEÔÒïDÓO`Õ„ÑbÕQ¢¬@†P\0Ø`Ö`\"ıèèÌöycJ4ğZ5‰’Ú‚èŠ#NŠfü§Àª\n€Œ–Ê\réÔ%1œãmœÎÎ>Ø1œæ\r¾¯câ&¬ÍÀx\"æ`Ãb)p»Í®ê¬8®J/\"ƒ®ãÃH8qÎ©.İK:j(mË¯\$ŒÂ\ni.±KÇfªşÅ½#Äî\\¹o¹ÄlDàìb\nærj#õ'Ø¶ĞfçR&Ëšï¿'Ê˜5Â†÷Nræ’¡)2r·f2&¨²!®l7°FÚEÀ¢Ë®#2± š\râÈmäc#lT_ÜÜ¥\$î2Zèš–¥ e2²ÃêŠBâÚ¶Òş\nfJ!ìG¥uRx]@(†¶#%\n–ñ„1¨³Ò˜0\0Ş¡€î-+i È™â:A¨}'%!Qí`"; break;
		case "pl": $compressed = "C=D£)Ìèeb¦Ä)ÜÒe7ÁBQpÌÌ 9‚Šæs‘„İ…›\r&³¨€Äyb âù”Úob¯\$Gs(¸M0šÎg“i„Øn0ˆ!ÆSa®`›b!ä29)ÒV%9¦Å	®Y 4Á¥°I°€0Œ†cA¨Øn8‚X1”b2„£i¦<\n!GjÇC\rÀÙ6\"™'C©¨D7™8kÌä@r2ÑFFÌï6ÆÕ§éŞZÅB’³.Æj4ˆ æ­UöˆiŒ'\nÍÊév7v;=¨ƒSF7&ã®A¥<éØ‰ŞĞçrÔèñZÊ–pÜók'“¼z\n*œÎº\0Q+—5Æ&(yÈõà7ÍÆü÷är7œ¦Å,Ië“()Œ£’h9<	‹£3É\$#šR7¯\n‚Å7#ĞİƒxÎãcK–æŒ+«–¾5ƒš\n5DbÈºĞ+D7 ©`Ş:#ØàüÇÆ1 ±Ü3„¸Â¾PˆÊ¡\r# Ğ7±êc»ò2\0x €Ì„C@è:˜t…ã¼Ô1xÓ¿OÈÎ¢xá*JÌ˜^*ò^È7ÃpÌü§£ Ò7Áà^0‡Ê˜Ş5Œ)‹D-Â˜è9£[µ«ó`-.¨CB†CšM;‘@‡´ê‰‰Ï¢È2C\"40Hü†Œ„£\$\0005€M{_¸V}\$Ø¨î	cxØ:ª\0*#…˜7¨‚ø÷B¢•‰#pÆÈ[.\rn9)ÈJ“©A6ŸŒ+UH—€PŒ:¾-:Ü £ƒ(Ï õL±`PÎ2HzŒ6(oH§0 Rz6±a\n1Â‚`ÒºŒ:R:Œê=Öƒ§L €8oÔC½Iíş…¡c¡orH>nê> É\r{šÁ»ÒX¦(‰€Tn;²¶ãüç=õ]E\0N]'ÃzuZ9ÃItğšAf÷#ÍìR> CL6*³ƒˆ.ÁÆ^Ao¢>5Ù@P’6Çw@\"§[î:*ëºJå‘’ÿj{S!-Y°ÏÄ®›¬¯¸ @¨Ë \\×9Ïsƒ(ğƒÑ\r9¦Â£\"ƒ>‹ph@®Hõ°ò:‚ÔŒoò3 ÍĞ…7×À2Û-.»Ce.ò ¨:ÎŒÃ2E¦Â¸óÃì3dÑniZZ:í0ÖjÊŒA¸ú;Ã³S_Cç)jæ Øq„¢å…ÀO¾	W¯6§Î,ßKëgEÙ÷™Çäk£v\$1ü¦ô¢_ëÿ€/ò†ˆPóìgp5øÀ‡æ¢ ’K‚ŠùıG²öá:%hív’~CÉ°Da',’EÚıÛ’\ni-#\nûŒxoGuIÀŞsÚ’[	t2¥ôÂ˜Ó*gM)­6Ädâ“˜p\r€¼Ñ”¢Ø>4eğå	•£	²a¨ ‡pò¿Ğ‚‰ùî\"D&H\\(mïez2¢RƒB%%3’•”ÌÉ\\v,%¤¸—“bL‰™4&£/SzÉÉ;F7LêPnOiôÙ1¸ÄK–Ñ…àø¡H°æ† …d§F¤TÉ} E°¾åÖA	aA ÊDAVZWù@ oEÎ†(òÈ&R+5!Ü\0hhIhú3†ä˜a&Ij‘A‡%²¯ƒf áÉ£ô‚ƒ¬é{‰½&\$ãU8ˆÂ%IkŒÒ­¢ìax/Q\$=ô\n›‚ñ¢í6£çC(s6U³x(7\"^LI™vbÁ#²S.]ëpIh…©'.\\kæ@À)¦A‘™Ö'bgQJ(ƒö4rt,¦äg	bª@b	N	i£‰k9DLàœCÛiˆLš54—Ÿlá@©,¬¤R0ÒŸõ7´pÃšUJå4—Ì]C:bU¬0Â[*µX3¡¦­’¨ìj”y)… %Bœ„Æ²;GÂºçhmŸ*à¹A‚ƒ6\rgáŞÂ‰vNIÛ—è‘;ĞÄÔƒ•/à€-La2N‹‘P&Á(„Ã_M£²V‘d¤„£VFºˆ EÊ\"Ê:–Oğm>A¤Öç:|ƒySfÖXù—2N 1%pvJª¶sæª,+¦CÓ<³ßLKöà¹ªİjÁÀÁ¥‘ÚH¯Åº#WÎ‡°ÖjZL—dÂß^€îG,¹JšH©#\$wRˆ ÁRá…|¥r<„D<6“`–³éLÕHÊ™ã¸ĞK™@oX3RÃ!\$¾¦àİ|lµSn:-‹â?†7˜	İ¿+9hœ‘Ê\nw#²ñL1áÈ,	áÁ}sÎ¨t:çfĞÑÄ«fàgi(2SÆhfá”d¤ğfqª¼*(Q„ÚgÚ *3@5BçËá)˜gˆ¢yœöej\rJµÆ¼Ø)|€Ò¯\0¾3PŞ›yi×İJ—ÃFBºoá39°ç!¦Ÿ¦AC\n@ÉÈoZ¸ú¶&Ê1´æGâ÷ìíÉËy\n†¹Š`2ˆQ™^½t5Ù¦ i]ÁáÍæÅB ZCÔ\"Ê:©Sœ#»h7!‘ÜËÇe—åâ¸C#¯7MXĞ¦ıâNĞÅ0–Â¡@†Q6â¬Îæ éßœP\\ƒxb¤‡«(Ä}D2÷Ü7‡­4\r3×=!H¸ÏL½”#[´:|Ò‡Pä@Lø_,í¦lÎİË¸à‡ı¹·T¤ù|g`€ …@¨BH#À–‡uL{T¡˜¤îT¡?pàĞøu\"jø•å†­ºÆ1æE+‡µÖ¢6}@Gc€¥\$5]ä¶è_ewz¿\\2pg±ó®Ê{=†‹º†^ÚCÈı¦î*Ö?îëŒ;2ˆíîvÌvÜ<¾ìÈËçŠìŒÊxñLíGv®ûæ{ÉOÃş–¿Ğ ¶;ãöñ%¸z>Ÿİƒ¦õ7»ú7ëš …ıœÃ1¦9ÖÕ«y#duûF\"˜FˆæÆ\$ÍÊ#·KÛ8-„\n×‰T\$zºdıl*Í˜CKb‹üÎ¤l”¹'¶SãŒt©8™€\rH)#„QI_ğG,Á ò²)Àäy‡´w¥Ü)&ş0C²ƒ\"ÀâÜã…jĞé–%&äÑˆş8CöîL>Ñ	˜~å4¢|Ü\n›%jsÃaFfÒu­˜(0NÑ(õ¬ˆDïbZp[PaÎÜñPXİĞp™‚‚J\$òÜ\"€&ÌÉG6\0æ\ri4h†\\ëØÈÅØõ\"ÜÛKV]b \"@ W„4éh+Aƒ’`0Ê0Ä4ã`\nğ¸(ìŒo@ÜŞâwíñï{¦Z¥¤Y¥Z\"‚\nËV,ğÉ%âD,½Ğå,†Ñ«×°Êlñğß±qˆ;p‚k£ó‘117‰Ğxü êü`Ú´Hä¥9‘üD.¯ïï õ¯€JïM¨ğ9®M1gñm¯E#&ğ®FH{Ñ1„[‰Ñpòe”ÄÎÅK—ğeĞ0Y‘²á*Iq¨ƒÁ.áQ·åÃÜ¹BlßMø«‡vFd€C¬”alš(+4Š	t\$æF_ÂVjfƒ„-d²Gb%©ÌÿâZ]mìuïº4qîv#YE¢\$ò@)v-†¢—R!åÖC–KÊÆ\\@òÀ	œÄñÑPmÒ((‡Øl‰Älmèâ#ÿ#	p9€ İ%PÉQaOâ§'Í×‘»ğk'¥JÜ¬_)…ñNp.³²ŒU#˜å«8¥Q%qí.\\¨('âV6ño,2Ç+‘a+Ñİ¨T,Ñ,R¶%­,pÏ3-rìôçz—&HZâZcÔ8Œc,Ie <K).^n\$vÆÇ”@¤`Än¥q]ĞQ0-‹´ä‘›)Ñ²—M3s°‡íèT\rhÚà&‰Ñ˜äÄG*’ñ5³_6Æ¾³³HETëüÖòáƒºáÇ{’¾DñÌ{N)8ña¨_9£m’£ãr&ÓVâsŠâ³tl´'Î* \"=Rô;àªA@èA†ï96½qÍ<æé=(\$*ÓŸ4.ß=óÑ=O·9ã¤€³°)dgBİ2Ñš<\n³!‡˜ÛEZ3c;AO²fÎ/\0ï/Ã#j!BqB¡= ÓC®#Ô7/Ïš‡#6*¦\"Xi¸<EC”2!Ó8”-ÍFíñòh3q«OF¯#(&À†RàØ`Ö*¢>²Öei‚\$â¦)Ãº),L1,FtQ#¤B\$Àò]iˆmÎŞIi¤\n ¨ÀZlğjç> ÂÄ%Ñn¿G‘¹tÚoŒE@‡ûMNÂ2Òº~ôİJM\rŒ\$Bb0éB;\$u¢ğ“6Èã“Œ`4¢b3á%'ÔMìt9ĞH´>ÆFjC»MæV{0E˜9d€5*\nõOFd¹%¥€è#ãÔÒ3½p;íÚ(ˆUâ\rë\$&0ê™ÕzsQ` L0%©SXÍ™WğP P—XEO_WÔç)-µ±Y-„bpEZÕ¬†a)­Ô;Œ{†R(InŒ 5v|ÇGÄ6\np˜Pø6åp½À_¢ë_ò\"'O„YÊ,T ÔkÜcK\\(1¬Æ -¢ËuÃ&0À‚ÏmÀb+\"3ª¶Q\n«-{XayI4\\¥G¦¨dŞF#XÏö\0Í£>e®6†\"\ràìúğ äW^>l!`q Ú¯§|;óW à"; break;
		case "pt": $compressed = "T2›DŒÊr:OFø(J.™„0Q9†£7ˆj‘ÀŞs9°Õ§c)°@e7&‚2f4˜ÍSIÈŞ.&Ó	¸Ñ6°Ô'ƒI¶2d—ÌfsXÌl@%9§jTÒl 7Eã&Z!Î8†Ìh5\rÇQØÂz4›ÁFó‘¤Îi7M‘ZÔ»	&))„ç8&›Ì†™X\n\$›py­ò1~4× \"‘–ï^Î&ó¨€Ğa’V#'¬¨Ù2œÄHÉÔàd0ÂvfŒÎÏ¯œÎ²ÍÁÈÂâK\$ğSy¸éxáË`†\\[\rOZãôx¼»ÆNë-Ò&À¢¢ğgM”[Æ<“‹7ÏES<ªn5›çstœä›L@ŞÚ%£ ÊL4\rÂŠ\nh:T¤8ÂsãÀã«õ¡p£È”4àTÉÁ°XÃ½.päÇ‰¨\nè4¡n’' P‚2\r«Âí‹T:\"m²<„ c<èÜ°pP@;#¢\rICƒ9ë Èâ43£0z\r è8aĞ^óh]Ç#r.ƒ8^”ò²A,C ^*òŒÎ„ÀÌº'{šã|œB-xÆ¸¯0,NLJ½¥­‹Å\r±]2Å1‰‹”•+Ñ«Å±®U<9T,;#\"“<Ì¶€P®–\rÏ:(Œ\0Ä<¡ MaXˆ!ã`ê¼§#J=eÂîr„º®LˆÆÎ©Ch0Óâ¡9ãä\"£0Â:’İg°ı%J1â5ëe­•7ÅôŠ\n	°ÇP±ã‹†6`ÑØØ7±ËØŸ>¥|\rm¶(3x‰bœ§iê6jår ßÀ{\r‰315è›7Z‰àÜ¤˜¢&L¾îÔYRU©lGIqEWR±Ôèç„¾BëWicªˆº§ÁtSÒ×#k½0\$²ã£ÆÌÇéÉZ[•° PÅ­DÑ@ˆ!Lë>Ú\"‚#2ßÀê‚]N¯9gÙ.Š{e‘%(ÃÙq:i¢ÅÕº›o²c–ŸÔXÍRŠKÅ®¸¸ã,.ñ%`UÚã4m*Yã0ÌõÎIÂ\09N.S&; Ş '£ÈAgc¬ŒØc5Øak»%.<^Â3Œ+Å\nşØóŒ42…˜SHyêÜ=­Ã˜x²8æ›§-;ğÙPéhëÇ\rÃ=ÁGC”¹s2CLKRñ¦L)2¦tÒšÓhwMïá9 êÃp/)åEAD ™¹<ê1G>xeÊàp&¨œ0¥Ä†R´p‡‰¢¥hb3ã#©]>,BMN .K©~¦DÌšRlMÉÁü§@ä“ÃŒ§5?¨È\rzİ9Ğp8 @·R‘Š6o\0005 t¢GH;2[Äà ›'vHWú%\$„	O¨4[œ€T&i\n¤ğÒÊVø­Õ‚C42}/©ågšõ’‚R7hú­Ò\ngP1Î%a±Èc.\n~!&Hìä@IâKqaâMÆ„|@PH-¬Ü PTI'+ÒdÅ‡2<¯I l'07' Æsß¬[#¦¶;›‚Ù‘½?I\0½0°ïL„‘9êµ’‘’ğŠë7Ø™¨CzA%°a Ï^E½pà•ÒXKI<Ş(®]“,yœ¦9ù†WlâéıaL)g¯6Ú&à€\"’–ÜK¡Bˆ]„°¸š¢İŠø\n\n'I äPJE ˆ0Ç6R?!ÏAåøƒ´‚ƒ‰….%uÑNI&‘¿ä~ŒÙ™»7¦u„•èPMˆ7N(í)‚\nÂÑ\"R7©X3Ï`xS\n‰,Á\"Rxã”¸ è­™Ï`@­ƒ u!t Á3&?M)jŞ@ônòÜÖá0g¨®j¸£EM{ÃŠ½¤ Ä]™«7…¢‚¤ª'«¢¶e\n5@FaÈ’ –iÉ#‹Sdö¦Â2(¹™L9 ('„à@B€D!P\"ÚKL(L¶°Š¡t–srĞBÒ9X0ÅX<dê³ òû1jLæœ£X0Å™D(ÊÊÃ4VAhÕÔGiZë·0Ò’¶(ÖU7F‹Gæ¼ÙÁ„œ43dÖÔ»‹=næV¾çsZæk„Q¶œ¦†Ö/Ø\nsÕUÆòÀV¡…’“ÒªJ@\n\nÄ™r ¢±¹Es`TŞó§)i}•¨¨`\"íVW1FÃ” ‡FŒbÍ3U@Øa\"0^+1²½µäeùÔWˆ7Ş@šnL¡zÇK•Pï„ŒÎ/F8Õ¸Œ1ÌÉŠÊhí\nÜ°‘í!u©¡´.‚¼^–[TÇ~\\Å*˜@ê¾Îdj3MDS6¯FßH=OŒ\$õo<ÂğÏ&3¦á“¨ƒr\\˜1tALRHs”ÀD¾+-§¿\$7Œõ«\n!„€@ÂKª>uôİâ‡Í4)ÿ#%äæ%Ø`ˆrÁå…c·¥pÖ2Á×Æ‚ş×Š»pş@CP‘.kÕnhf¦¤à/6=U+›+jBRŸ°¶{|Ú;Nø“‰<O¥ç±f¬–l–ÙÉÂÇ›fpM¶÷|\$9‡ÿrRí­¼¶6·0ÄO{í¹	\r’æß•–µ†ÓÁÎ3G)\0›r)Ë£g°dÈ«-š<B1™×2è0Æ”·0‰O­n„„à\"“¼„Ë£'*¥1r÷üâfxbqG¤<q7É´†íS¤Ç>¶°AÏš<Néj›®¦B¸ºÕsbfÊcÂ)‹™Œ§!î·|ß+ìVÍì 4MµzïdpGtÊv‚uŸ`/3o*cx¯ÙŒwtíæG’¾énÑ1¾î\0(£ L\$^‹¢>5‡SiÆÂßI°pqD6ß‚cŸˆï1ò§>ä‘OmüÚıäEË0³:E2¶_GÅVvsB´HóX\$^¨ZAEecÁ¼ÛU»yQ%ğ¤d”öw;Ùğ%û3„¡ò¿‘€/}ù×ÿáQÿXÒ>vqc§lp.\r¾MÆÍÜ“qíBoµøÈûû/ğíÿ—Ù\r”€Ü”ËšŒçfß®v\$ıËìç)déoò„nîõ®òøï´5,é\0+®[nüøèÊ·n½ç`ÌJŒCÎ?HÔ3c¼dºbŒ6e¤\rä¸@Ğ/âjxbök–ÎdT­nY°\"À<Í–#-†[äÂ âPRj³ŒçË-\0ïï\0:TNöîÆ·côÁæ˜UNğ1ìú¿ Z\0¬WúåZîªU/öø¯j«²Ó«\nÃÒÒå¾ø‹è},[ÎË\0….áĞĞ#0ÄíÔi*	m8 ğÚÓ +!Z¹dÌ€†„Šôä\$Ğè>( ÜƒŒÒ*J(°İ°Ö' :‘\$ÓûÑí'pê}î9Eì+/FTpà%¬\r-)c”yQL;ûFZ\nBkŞq\$oON}brĞ:ÿİQ7'ØÏìÏO¥Ğ%±”ìôFË–ó£b\0£ĞÏ\rÃæÓä:q0Ôì\"p\n±¼Râ[]qÊB‘š}€0ÀĞÈd=¥¨\rÊJĞ(ŸŒXñVÁB|æüñù¢@£çÇ\rg fR­r5H¶0Böò£Xä¢3oäüÍş±‰Jü­ÊYcÄ\r€VcÖa+\n!D|}`ÄP‚3qTİ¢Në@Â¦*k\r§æHœ€ª\n€Œ p\$ñìT‡êŞ/Æ×å\nãò0Ø/Ş×é“):’Âš#‚<I	rFkîÍâ	ğœU€òë¬nó¯	ñºìt¡h\rqÍk¬ ‚ô|bX'\0˜¯…‚PdL×âDãÎ©â„yÄjat]¢Š¼#bàFhÅ1W2´6E?ÎrV!'¾FÃe1*Eb¼kÖ'\0006Oü0ŠŒ\\ÈDšnn~è'q(|£vÓ:4Ïì*Œ\ràà9ärI¡2ÄU`b`ÆşRÊF/3*0c\n\"ƒğËˆ÷o E–'Â@ğTË,JÃ¬#&2Ã:í2˜ğæ”I6Ì`Í‚òCƒÂbêh.º»ƒÏi+´<4ºï¨n¥01+ÈY€á+¬Š àî.¦6*šfrúM5\$K óXg Ï5 "; break;
		case "pt-br": $compressed = "V7˜Øj¡ĞÊmÌ§(1èÂ?	EÃ30€æ\n'0Ôfñ\rR 8Îg6´ìe6¦ã±¤ÂrG%ç©¤ìoŠ†i„ÜhXjÁ¤Û2LSI´pá6šN†šLv>%9§\$\\Ön 7F£†Z)Î\r9†Ìh5\rÇQØÂz4›ÁFó‘¤Îi7M‘‹ªË„&)A„ç9\"™*RğQ\$Üs…šNXHŞÓfƒˆF[ı˜å\"œ–MçQ Ã'°S¯²ÓfÊs‚Ç§!†\r4gà¸½¬ä§‚»føæÎLªo7TÍÇY|«%Š7RA\\¾i”A€Ì_f³¦Ÿ·¯ÀÁDIA—›\$äóĞQTç”*›fãyÜÜ•M8äã3ì@Âí¡ij’Í¾Ãª†¾¯BšV×BïÀÂ¤¢â+¢92‚`Ş¿¿êxäÉZ#\"£¦\nKnØˆ³v¡\0Â1¡IÓ\rë1Bá\0î©(¬j0¤pæ;¯ƒ X’`ĞÑŒÁèD4ƒ à9‡Ax^;Ìt7¡arø3…éX^8IÒ€ä2á°(í¾7Ëâz©ºà^0‡Î3Ú1¯,c\r¤@Pš‘<®«nòÉC¬A\rˆ4@˜%©\"7LST“MJÒâpŞ¯ŠM\$\nó\néxÜô¢á(ÈCÊÖU¥lÛBŞ6¬\nt4¤5ëçAëË*7mÛ#‚”ù¨j˜Æ½=ƒ0Â:Ó!í`°CkD: `˜e9†Z×‘hÓtİuà ÓŒt³(š0I¢\rˆ	óšVÊC6kn…:7˜£*W\nw(¼ƒ¢ô ŞıÎ6%2j‹iõ*¢˜¢&L[Ä>îc( Ó([«3”ƒF\"ù…B˜6÷Óè!¯µ}ˆ£‡›fÄ5Wšl4ˆòÿF‰#lp÷B(ñ«²š\\˜Hlh\"f°Îo¦‚YCÑ, ˆÏ¸ò°˜çŒÅîkàA¹¬ˆ3/D±îÛËX0…ÕÓ¢â#}&;Ê/…¬Ìt*ÀY+Ğ2ØüL³mSX—»ÃxÌ3\rŒ\0Êã,Òt7¨)ğòX#˜ë6ã˜Ín_‘Á)¼½xÂ3õ/û½Öq|”2…˜SÔ\$­íhĞöHS\$Ş§\"£\\ı7ê`:qü[4R¯¿Ã)„ªÖËÔ¹/LÈ;ÌÑwMÍshÜ©,\rû„A÷òoJ‚PŠ‘˜RÈÒ)M´MÉ‚ıáÔ2‚Öj²Ïa\$ä&˜å4“IrVpÍPÈ’¢V})m.¥ôÂ˜Ó*g~i¨9&ÄÜßI[İ:	Õ;µPàm`Ğt€@ù½›…\n³bÀ€†³¼T‰P'È¨ã²øoÒa˜€«°Ü!“`¤Î©®M@É”—ªò;i¨“C“ÚYêÌ0†h>ôQë¶wIá¤xpñ÷Yä\$Ñ¡“¢Kƒc#&t43ß¡Ğn#L:“Önn!Á—^&à(€ šá½7\0 ¬’TY¤q’d„°’bPNUğnE!ŒésrH\r›P6ÊÍ«›ƒ„[?°˜œŸTC0id,d\0ìPİ+Ô'Q¡=(²œR{	ì8\$¨@“ÒŠF8!Œ4DÂü—|Ûd/qÓº¸œÂS\nA=ÌñC4IÀ CÄ´ê‘Èä·©±ƒ(ªv…©!Íëf(E£\$ÆÌÈZ±\"D©¡ÃôŠ¡¨A½@öô¶ârp!\$Š‡“VJ¢4\"Ä®%ph×ÑfÆœ„=÷ç øc_¬Ö\$\$àçhP	áL*3sÄ‰ëİQ€€3¢²éÁ©Ô‡†Äå'¤ #FTË³6|s&	¢	™MM'd'üİ¯Ô6#¶dÁ2K¥¼K\0F\n’|Ÿ+2ŒÕÎú5ÔÒ•‡\"N€Õé®\$ò`ÓêHº×c\0è0\0œ¨P*[, E	Î‘…ÍÎzÀXJšA¶` KÆ¢ˆç™‡“â€À‚ğ‹ˆ°É.E.³(¥G*uöèmÅ°èé\"ğ^•IG°èU\"¶jD¦tĞ*„­‚’Í®»Ù\rá¥Sø¡—m&~ë3…zĞo]Õij5ÈTvÌ£µQ¡X—®ñBIÌûYr'h+µ<G\nÀ:ˆ¨éHÌñS\\c‡&NÉò°‘À*«\\£à¬.C¡Ôt¤8Í«àØfa:1f4‹…0Ò—\n¾jÏqYi	‘eæ3puMHŒ_	«¸eà(+òÅŠŒf'CF™”»YŠ›#¢·'#Ö\0_j\0m=jLÀ1|¢ËííêÅæŠ¤CuRáS,T\0äÎs\nål´…´Ø¤ŠNŠü0¬ó¼Ô“ñ¾º¼é9 ŠIÎŠqM1¯TåQ`T\n!„€@¾‹ê3t©Ù¯1Ê#†ç™u¸bˆŠ³å¥\\š3JyCz¸Vz¹U¨Ùbì”ÎÆÉ*%=Zª12Ç¹Fà­õ>º^¦(Şëíg°5¸»%F¯¥8—ì­ybËã×£’É®öbLZèbR¸N†ã1ÄX8î`ñº Q¸İu|PC!½öZ!aˆÔÓ‡r/¢¹Õ&ex½p‚ŠQlÜ,¦ÅÓnbã&é»©À†¤‚G\n\n/ˆ°®”Ş?ay0á”1DV³À6ø +…0›´İºÏ6É)x…ecI€sXì%\08€-Á/«_Wó(db‚0a>¨íO¯6»0œ0««úº}¼†ûbı‹×•O`\ríÏ±'³ÃFné;É)3¶’†`fAÈÆõÇ—ûÏ{ÅxŸ¿HB]4­b8}Ìv,fïâı4fá8@å&ÏY§ˆZş;oª8æÜvİ+Ïj<ñSÅ>D¾+Àn2fUFoh#¯\0j!fÄ—Ù,kT“AŠ«\0¦Ó,ôYw¯“g¢]´0Şo{/â¼†Œş,ûÏñş‡Êú^ë÷¯³x|)‚T3K2Ã(´!€ÛÛ§g.­°¶¡8?£B˜mñ³^'îí[KaõÇïOÊ6Î©ÌÔî²ù§~û¬ÆÌ©ô±¬Ò¨ü/\0»\0,ÍĞ\nğ¥šğğ(-ôWå‚ŞfÌ/‚ü%cœC\"ÌË¦\$\$â<.NYàæ(â\nX€ŞJh¼çƒãÉ*Êod%D>0>÷î,bPR?ƒœ#hØ/ÜØo¨Q\n—ã=+ĞÍ\nÉúºÌ¸?ŒhkŠ2I’d#6]Ï@&%/È¬OÇ A\rC\nù\rĞà3®Ûo®Ñ¥8ÈÅ>êZÂùÎ»Í‰o˜öâs‘æ‚ŒĞ¾™	”:°şÑ+Şkéõ®•¥È@Œ(ÅzDX#Œ8‡íˆ&ğ6äeê¢ı-IPğ/èıPØfQa¼ğï,¾&n,.\"ËÆv—„ÂŒZ*:±zÅ0õJ0ˆ\"ô\nhfÅ¾oFÌ«âù¨Í‚üÍÑñïlé½@Ï‘ğîºgFd&ÌÍËx™LÅìê\n‚X±&bÎ¼ÑáBBºÃQ¿‘ıïÇ 'd 1Òìà1ÀĞQbXIbzLê‡Ñˆ3er¿†\$iüë%4EíŠÖQ˜¿KÄD*ÔÚ¥z_¥Õ#b7#0o<2…ra¢ùû#òf’±\$„u&òPcÊ\r€V§@Ò_B,\ràÄOB9qLÛ£¬2`Z_¢s¦>\r§¸F¨Şx`ª\n€Œ qÅ®2LÒ'-~íO\n¯îŒÒA	-£'ok-%	#‚<\$D\$‰\\%#0½,äHøÉE\0ó!Ãô¢/†°\n¢¦ÄgBÏCõ)ãŞI¤@‰€ß\"gƒ@[Ã/‚rDJŞO\$4Õ=,CÔ\n†D„atS\"ŒºnÙ,6>qIP<ğNç\$ojR…˜ß\"¶7şŞoh!¤»gîCj71LZèÔk7óp!í#ÉDz¦ã9^;Ó”?àŞ¦*,`„ğ°Äé1\rìŒ·`„{ÄûBÀŞs ¸¯ÅJ°¼Ü¶¢~çpx·¥Âh¤à]\"8a`ì4sÿlˆG`ê’¦;îÍct£îò3/Î®82ŒØX0Ä¹E÷B¢bIS&ûëÜQë¦WÀá0kŒ@î/¤V*€;\$ïG¡Ãİ\$Îğ@ç~/€Â"; break;
		case "ro": $compressed = "S:›†VBlÒ 9šLçS¡ˆƒÁBQpÌÍ¢	´@p:\$\"¸Üc‡œŒf˜ÒÈLšL§#©²>e„LÎÓ1p(/˜Ìæ¢i„ğiL†ÓIÌ@-	NdùéÆe9%´	‘È@n™hõ˜|ôX\nFC1 Ôl7AFsy°o9B&ã\rÙ†7FÔ°É82`uøÙÎZ:LFSa–zE2`xHx(’n9ÌÌ¹Äg’If;ÌÌÓ=,›ãfƒî¾oŞNÆœ©° :n§N,èh¦ğ2YYéNû;Ò¹ÆÎê ˜AÌføìë×2ær'-KŸ£ë û!†{Ğù:<íÙ¸Î\nd& g-ğ(˜¤0`P‚ŞŒ Pª7\rcpŞ;°)˜ä¼9ªj6ºIÒfÓ\r¬Bp·ƒK\nà@P 0Áã`ÂL#Ä1P+>:Lè˜7Œñ\"p8&j(Ü2 Lè‚¥¯i˜@2\ríü­1Ã€à¼+CÆ«hKìHlS\$0´!\0îÍ\r\r”šË˜î¼`@%ƒCÈ3¡Ğ:ƒ€æáxï;…ÊR™Arğ3…ñà^8L3ä2á¤\r«Âp½ŒËÂŠ:\r.àxŒ!óšÊ£6ôÀC“ë«)¥<†DâhŞÌ¥ÉCÔò õ<o-UV\r5s”É‰¨¿´\rbºœANûJ+Äƒrö3h˜È\rôğÊ:!-Ÿh h(Òk¨Ş0Ìè¨4àß Q Ò:Û\"`ı´hâCsÔm(ˆ2ŒÃ\nj­ËèëŠtå¤èˆÈÇm[bÎòF¯%¸‡¤1²î¼ ,;¥&bL;Vò5h|@ÿ)Óü€ŒêEâ	{„àè2à£blÈŒLª•Î¢&ş9 V41¹°æóº5¸ÓVºì!Åˆ¥iSV4ª-Úô:íÆ¯¬³¢‰¬¥špÊƒ°ò®¤(7MàË’ÛbHÛ%C£:\" é­´•Û(iÑ^Õˆ‚Ğ²¸@\")®Zp©Z\nôyÇ**rñÊ„ĞR­zü)¢ó¨	]EiaÈĞÚ”B\$¬õ ô¦ç_?êÌq÷B8ËsÚmh6F\0SbÆÖ€Ì3\r’\nz\$ ‰*\ríÄt<„'«˜Ì¡‘£á3FÀÃ¯aÓk(òaJ«m}^‹5Ò´şàæŠ¤S”‰^©H3‚~Éšf;npÃ•äĞRøM©½8§4êÃºyH	ñ?ÅzD/ŠPî(}ŒRËRêeş‚@M		 !¤ƒ’ÂÄ	ŠLÈ>‡3®¼S¦Eú“±IèMKí0%³á»¡< ¹3¦”×Ó‚rN‰Ù<'¤‚ŸSüP,¥˜J¢ƒ˜>nAÀÊ®€é\nÁó˜C'Uœ¬CZ+Hà9#¥h¦á’ÊfÈÀ2† êLˆA×%ÈÜ¼,¢ÿÁSÎu-ÂldÉ¸r€(eg†ÍÚP{Ïñ¶qÎ‘hèeÒCwKëû4ÎeÛ9˜Èç)–†,Ú†“\$oºÊdÜ9¨Ë\"KnÇ¨'ä¹ƒr;’iLà›ÒnĞ\"ÏoE8ä¤r8Ì\\SC\nqÒ!Çš‡Ü»G#jÃijÂß´Ë’‰õâXÔ!W}Fã¨ğÜH#Ú…L‰hä4GÚÓy‚'õÒ¯£BOBS\nA;G“ƒy	Fò*'‚ÈL\\›\$õÕ’†QJ9m\$•`š×FfgÁÜ\r4m›Å\"VHâ”sÀ‚%¢ğ_¡Ryä\\<›\$†V|Ş4!¹gœs’yñX+A™‘èåJ<t¯…,Ôâœ¡\rôI+„50 Â˜T—hÜùÓÉ¡y)‹dƒÒHÒ;pd!}BRKë¬ì#¯¡À²\\BÕÚlõòI2vAêÆ¨ÌáìÆÅ¿']Ô\nlì­³cvK0T\nr¦>¦æ“USª¤ä9´ú·Á˜ÁÉ€£¶‰–ÑÜFÈœ&Å®2·§¬ı›æ½ËL­¶İo£µ¶ê§%‚dÑ•²3ìÉ-]vÕ¯ Îuéb®je]RÂ\0ï0¼¤˜Såš~n*E'¥Òt[>×ëÍ…•IÈÚN)8¯´Ü­.ògCĞ0Hªú¢Öù}\rjNÔÈ›•¢V¡xAYuKó*t•®À÷mx• tæa–ä\$–\0‘hÊ’~…íª‰ñ‰nh>|1æ¸iVí¸ Öéé™%8tò[®Í©0@Ã!ø?DÌşÄ£÷/§9áì2‡pG•ˆeVk§\"«Ã=S[mQ\\röaÃÆj:D2VÄ×.l¼Wt:İóÔØäE³\\+ÙN¢`ÜLRßaÙó?òî0 1èmˆJŒpëÇÈüWµ÷m\$Èì°àŒ¤ŒR\"Aå\\2f§`KX3¯‡SRv²£s„ÁÁù_IÌ‡´J!!P*†ë?&SØòtpÛÔâë¸Lš0Ùx/eQh8ğA·)cXkUgî„k€PQgï`±)Ö\"3ìÜÔ¸œ[ö–®W¨.;¨™îÀñ»Œğ*;”ÕnwG½•j-¤†œ:W€ƒÓh'·ğzû6Öşà+}qÖuwİäãŞwôe÷ÿFT€ ğî³V¦WD¸ì”\"ü`q*·eošGÂä¥®4&|G:“ÕîMíe®'Ås›9„qÒP»xà+¦\"´3˜Oaµ‘ò¢™iEuİ¨e‰Y/ùnÂA[¹âÇñÙ½¬„•Í¦Ôg‰“6º*¿0İR¼÷N)b\n3Wó»6şñnÜ‡‡+GÀ™ï22½&ŞJâ`wùæJœáà:yõyèIo|•¦ÒºÔxnS'ôUJ.\\8øyÙ{°`Î„DîJ‘ÇS³&©ÕµıuÉŠSßã2å”¶Ñ¢ºk°ÏçÕMHŞ(Š¯{’¹å×!CÍù¦êäšuÁB½ºèd0é•ÆíıiøûùÌ‰<\rópX*}©\\õêæ¿ôÂLõ*p5Y\0Lş‰y\0Â´ôo¼¿€‚G*: ƒÉlãnÆV,.Õ¥DŞ.¥¥†ÿ¯U\rò0.ûNDà-:ÕcÂU@50D5ÅåLûà†¶(lŒ‹h:ïìïÃFğù&ÏcöĞpH§opt–ĞĞ1,üÅÀœ„HÑÉ\né=CÜP|*ğ*¡>v/Â¥æ¢§È<‰™À,cÿ†ÒMc\\\rÁŠc®;'²¢ìĞ¹ªDÀ[¬i	†ÎâpxĞo.ó)î;eÊlF°kLôGÃÏ¨‚[ÅÀ3¬ÀVEhçoDûL¾¬ÂÌej%~ïï,ÅjÚó¥\rxîeaLÈ@­lG†L OPûLğ.ÖñreP“ñ<ô‘İoT)F F¯[#Ô±€Æ¶îq¥¦ğBOBB:@ÌaDC²ob~S¦\$:cª:íZ¨YFaÆÙ¥Qo:ÿù-Yq:¸-^TQéëù\r`\$å´U1ŠWP9%DÁéÖ\"ÉM{±äF#Jw†<‚E¡/%¶®\"¸Ñ”§z–R@Ò›oºã.ÑòDóp¢>QŸ!#®FBBñbo²m%B\\fæQn×h@Å(œíƒ\$nú?id\n²ŠØA)·[)„:© 2d' K°Ê1ıÃ:\nr3D\\ N†W\$/T?döÆÇÙ!b°ß@‚û2×,hFqéµÌgÂGc¢Xè/®òòìGĞm!%êàpdÜCE.Ré	S1mäàª\\5å¸F\0Øm*W Öép¿-Fåh&„¨zÓ'¢~fÀÚ€b´“Ò\n ¨ÀZ2\$ï¸>Î.Yàä¥…£êãÈå³xÕÓ|à#,–b:#âB\$gˆcÔGÒf†şj†‚£Ì„†€<#4bOo4\">[êB ğ–Ïç¬²’åæ,V„p !á„bzFb”ûmš8«x²å–DGÎw`_bDç¨+Ed_cWÏ0W­1â´Å2, ®4ûläoLS”(ûQ—Æ¥A40ÀTó¯8ô2¢œ6ƒl2j´ÏdÉ ÉBæmgm@/%¸_NÆ¼Ó¤ÃçqFã»8Ïö×eÀR”€tÆ`âb:Eøî¶¦d.XFÌ8Å/`@ZVp,/Œ²:%º DJÇñBƒ:nÜnà4 š\$-°0\"úìfPÎjtØÈğ—Å@\rë\\âòmhxúQ´8\\°¢(`	\0t	 š@¦\n`"; break;
		case "ru": $compressed = "ĞI4QbŠ\r ²h-Z(KA{‚„¢á™˜@s4°˜\$hĞX4móEÑFyAg‚ÊÚ†Š\nQBKW2)RöA@Âapz\0]NKWRi›Ay-]Ê!Ğ&‚æ	­èp¤CE#©¢êµyl²Ÿ\n@N'R)û‰\0”	Nd*;AEJ’K¤–©îF°Ç\$ĞVŠ&…'AAæ0¤@\nFC1 Ôl7c+ü&\"IšIĞ·˜ü>Ä¹Œ¤¥K,q¡Ï´Í.ÄÈu’9¢ê †ì¼LÒ¾¢,&²NsDšM‘‘˜ŞŞe!_Ìé‹Z­ÕG*„r;i¬«9Xƒàpdû‘‘÷'ËŒ6ky«}÷VÍì\nêP¤¢†Ø»N’3\0\$¤,°:)ºfó(nB>ä\$e´\n›«mz”û¸ËËÃ!0<=›–”ÁìS<¡lP…*ôEÁióä¦–°;î´(P1 W¥j¡tæ¬EŒºˆkè–!S<Ÿ9DzT’‘\nkX]\$©(è³‘!šy&¡hÉ0§2‘ ìÂŠ’X÷¤ÚE4£\$üâãÎn™ ¯«)ü56d+RüCˆÉ<ç%¯NÁÍE’€í3ÎâÎ# Ú4Ã(äÛ<Í\$5BÏ¤>ëBnrb_¥EÓVÖ–©S„Ù M¥Vîôë•<*\$xXƒ@4C(Ì„C@è:˜t…ã½œ4µ1MÃxä3…ã(Üæ9ö°È„K|h5“ihÊµjš‡§)*§¹D2š\\xÂ.¨‹#ÇÓ´¾Ö¹N’€å	‹ˆÂa\$Ì™,ğdO!áiDE‹dnúG&„Î³!±6ı]á¢C ²Lá(¥Ic±H9†?âè3Î†ÂÈş7:£%V¾¦ N{Ÿª¯Ö…Ÿdº÷³kËâŒ®ï¡~Œ“KÊŸˆ¡íÊ†ú5 Åijtââš\$;á¯7vo¹L67šºÀålÛ~Ô½¦*|Û³@‰¡\"]bR&)ò—{>…Â3¯¥øªõz»D|ê¼´.3GNdvJä‰RÏDc°Ba²OT}6#¼\nÅÄÛù M©ª{¢ŒËmææÜ!ª\\WÚ!¥Íîü ş%tì(°9Ëİ»YA\nbˆ˜ºó\\\"“#)+¥á\$\\¦F‘,/=cûwƒi2	W%µ‡½AcØ†Cñàí%”£Pôã)0¶Ä,Óş^­4şX“xç¹±VÌM“ñ)é±Ñ‘‘ZpÊda\"	“#`ÄÙ#Ì?¬9ô²çØ Š‚	Ë•DŒV@D\r!Ì0† ØKˆsX-¨k\ráÌ;SeÊ‡€è¶ƒ˜i\rá¸9Ÿö^RÃŠHÈ\0004ÛOÂàğ!‘x\nkŠ±EŞƒM1¦,hq5‘‘&C\\â=kiĞ6@ä‘áŠ,I°¶3ùM_¥QßTDochsyä¡|yòq0¥ã=g¼ØUQŠ)ü¦õ“ÂF ÒÁxH#uC*Õ¡¶#èCÄË`¸Æ•RÄ‹lP0Y—²ÀB¤‹t,*Ê1ƒ9%â„€SÂÂN³\"òÍÎD¢h¦%V”ÉNOÛ)E!²±¤;\"§,I°‹–’ØíK†­#%ä¾FÄÆÉ7ß%f9÷Kó)	%™=3Ğ‹”híGÍs6Qâ´.3uw˜Cìb+\"JÅ¿Êa¡NQ~Y¢#²x’´¾„ÑV>L™Z UªUK©ä®Ò9'(-ê']Ÿ‘ÈBÇ¹S•z¯Ö\nÃX«d¬µš³Ö%Z‹Yl- ^\"Xa‘)m‚%Ï±h>ôÁÖ9ò¾ârx“,é¢™äZšTzN¢(¦‰b¾.•İ¦mâS?£×L%\$\\=4EĞ&Š*4\nIâGd-êQyóG‰Ue+ÇNÓu€°–\"ÆY)f,àî´•&Z«]l­°Ë¢EL\\‹˜h¸H]±ZŸsR«ƒéc\r\$9¥y:¥Wû!²öGÌUID|*‚ğÿ˜zÇ\\™l¦dÕ«ĞBÁQÔ\0°¥§zïÑÅÅ’'XYJ;/èe¶('ÄÁ’„•gMµº²lØYHm(áI\"š£weQ‰NÊÛ¢Äì‹©Õ<´¦D¡ÖŞ×Nª\$¦!Sİ“æ”ĞÕá,•é*ßæ\$š’\\•#ìè¡ª“zd (.@¤ºÉò?rFËn´‰T±Bšğ‰zC´…÷çŒèÉk—ï²cP”°HLbŒ¿\$5›±“éG¤jî>‘}5QÔİ[“’¹°1ş1+vœJéà(ŒYùËªü®\rub}A’ÁwJ³K‘”ÉÜY.ƒ›\\Ù•èGc¼¨wõ^@¤7 F¢¬Â˜RÀ¶Ö¥ƒå3T9‰Ç§B~\râPïQBF\"G’»€ãd½–ÊFtìJR‹¸–%™—t¹dÒ«9µK´Ã6³ÒT(ò£%ğ¹Q*S\rä]€ÈÛ’rÄ_è†a@\"	–’Ù I²Zµ¤‹N“ì]->e\$ÕÌ\$ƒ“gÅíì·‘X¹Í§™òpBFW¤\0Â£ÔUúªf°)\"&¥§*k¯–3Ø4DUƒÆ[­@ä½rLRn²]{Ç& ·?µ•Iy^4—Ë`â8T*ÈkLOq5¬x©ušæ\\Ú&AË§o%]Àô^†-bíã\0Œ…ÙÔO%Á4ÎÓ¥lh8î[KÖvÊd+ö_ÈAÂnVjHg9×Áv™!ğ~pA:µ½Ã#z“oŠLÌ†EÅÛR#iöª±É*®ROd}·G‚€İã¶Jó8]8ï&Š‰~š)Lêùºı²!Ú9>şçÌò.ÊQø[‘`p·ƒ1À=	¹ÖJŸ·ä).©ñ Ào3;çaPd'ùÀBşvŠ\raYÚ÷xXÂr	d}¥õ__ZüÙr³ÎP íXŠLïÑyùÆ§B€Lõ¸¤J©!AÔAÙ@\$ù	>\rC:ƒ.5·z)Æçèıá '™,×EÄfÏqÌ†£æ7Èê:µAî~äCÎı†µÏˆˆÍMp|oJF¢ÂnôOÖ¯Æ›Cè´šMì.6FÒÄÆ,cˆÅ-@ê§bäWî¦nîd¯ò\$'Ê¸wBaæ@|äÍpN0gcËn›(üLLÎ†b|¦±\0H°=ôñBˆkÀ)®&ÄDj¶/IzªPbKnòÇÈ@ÚÎ/BĞPf·0\$eƒ×ÂØÆïş¼\n]Ï>nHæçş&¢2°Èb¨mG®÷ã^a‹ˆAíp¨0â¤ATCˆÀ‹+ìnôö/Nììel’N€‚\n€¨ †	¨ãí×ÈòŒ&›L,QK–&ÂNâêPíêJäüs zá¢\$gÀ^1æ†TÊ´\$Ú©òŠ1_ˆ #&ä;HÙÌ¦&º+áz­\$–|Ë°J\nOt0aœqn÷¬O	¢äFãäã‘ª”ØÑrz±°q~Ù1„FÊ•±°r¬àÍÃÊÛeè,h}Œ”MX\$qÈş¢Ñ¸8í‘K¯«º(#²¡cÜ–‘ñô(*ŞºÆfğ–2\0»ç‘ ‘¯/CÍur‘ê .Œ\$‰rdFbv†@±`«Ql%§&\"XñNT/¢xvî€!…%\"C%b%Œn/‚ª-ƒŞ=JÂè\"‹Â¶0§æ™„(2h›H¯²`åˆˆvíbHÂ‚Li´c±–3’†F'2PĞ,ıKdmd¦¿¦JÏÆ Éê>—2ËæĞŠA1nºŒïGÏÚ8N,Åc’ğC2lŒJ\$%/CÄo)âØÆ`Eòšğ0K3âN’¢HÍ0,ë&æ.°B„FNOÓ,ŠÇRò3‚[3Åì=sB/ñÅs4Ïğy³@*“Xéñ@“ìbdì£yfLÿBì)¥P„33ÓÌFş®Zo,o†\$uĞÏ.B\"³£Z¶öMöO«a8ÓtIÖìÄL½‰œÉÂBíç»¥>ƒ%ñÆôiÅ-&¤ÉpLw\"˜Šé>»€PCNä\$\0Rí3w<&àóŠ6óé‰5i°˜/é2ö`lÕLÍ4JC2JFìYAÔóÖß§U4Slõ”5BECÔ=qƒ5Ó6pÀäDô3i3ÅE²¤Øğàf¤Wr0Q‘BsY>TJ	ÇF/!4g\$ıFÃ‚a‚AGR&eç*®T¶´R~t1HtcHÔ7L\$´oIaIÑŒŠ¬áGÉ}J±©\"%DA§2l´}4m3Î‰MˆıMÎ’ôãHGN”×\"îéCN>å”BUì:ÉäÔ¸\$*,.jSâ†æÛkª,Iª8©-B%­%•bb£ĞøaµmÍ¸ºğÅ!j¢!ÌÌ¶P]?­0ÇTñã\$¨4?Lh¸U0ibmË¡Ã¥Nµ•ó¬V•>ÖgH0‹–0Ğµ7ĞFŠáB6‹Ó<Ğtêd‘°ĞŒ·!f:ë”—³4èõLñ§#,:f“3A®Í\\r5A“ÕÏuÓ1õ×JoàƒÎ×^;6òÇ]Á]Q\0Şé‘^´c]¨]ëzš+Œí•íO”Ó h;2ÈØ]ÕíPõò.µ«“ø0÷Söş„9bV<í)³Á|j\"!A\reBBg0Û\nË¶ãÎôªEÚÁïà@M¯Zõ½KÔ–áÓgH´å0ÔVfPßgäLÔQ\\±ëAÖ{3ğ+hUK5ï6ö‹c&%+°ìpã´uÖ±\$DQIV¢&I6CkBpÓ>oÕ÷lìÑhöÆa¥dª†˜ÃNw¥	»	R‡P¶†Ntü.Ğ’—°–!t÷M§paEp°©qbÃcc˜~Š[oĞªõõñEÃqq×ÿÂ\0‹ÈÁVHGO*A/©¨O4ã0·!Õ]WRtĞpõi5´ûvNÈ!vŒ¼äworWtK\"øª¶ShÑõKö¥tóÀxğ+2Šl„}H’bãñ¿Ñ‹–¡t±2ëk{Š‘41{vÅd²–·¬\$wÃf±xÊ·ÍnĞ=âêj·ØbNuEÃ›*© sğy}h·r³{qÏ¾É—±­Lt£7Ùw/tw2G{#ò0dB8hì\r€VSÌ÷ˆ¾{Âº]aå¢e9¬ËVµ³,%N{\\Wk, #2'®XÀUp>UV¸î·mZ\n ¨Ï`qrl^UB­Çol	©˜–¤Ï‰laˆd‘ø ‰æX¨lUjt˜œ=Ñ‹„¾Í×}”{c	.ÇÑV-’wb?°ckğxĞ'‘|Œép6CxÅWĞsr²\\ØWÛ…†ìÌn9Œ‘öŠXF8P`ğ¸\$h¢„v†ÁAv.±—'e`+Õ5f3&öÈ&P¼L(o³>Šè)fl¨!áŒ\$·ÓqËDÛÆ´ƒ“ƒfOt®H;UH4ÓaG›˜b®R8qÇ?˜¯I—¹‘…ÔQ+›-9|yÔÌëÕİ>Y5æÆµ0›ÒZŸ'Yš3;8¨\r™•üşuèŒ	>r©3Sµ6C”~¢MxåšfNPÇß\"¾ßt:…:\0ÜMõ>ÓÚÒï„@\n`äƒKo\0ù\$u\nD¾Pm–\"š\0003™Ë>±Zç…?OGôG.2\0Aj¾GÒêf¨f\$ìğë}¦dPs.a¢]YHõt~jøŒµ†ªş:Btò®z¸-S¬£šùbSZ%n¬0‚46ÔÚ\nìfÕ‘*TN%£¬ÊòÔÀ"; break;
		case "sk": $compressed = "N0›ÏFPü%ÌÂ˜(¦Ã]ç(a„@n2œ\ræC	ÈÒl7ÅÌ&ƒ‘…Š¥‰¦Á¤ÚÃP›\rÑhÑØŞl2›¦±•ˆ¾5›ÎrxdB\$r:ˆ\rFQ\0”æB”Ãâ18¹”Ë-9´¹H€0Œ†cA¨Øn8‚)èÉDÍ&sLêb\nb¯M&}0èa1gæ³Ì¤«k02pQZ@Å_bÔ·‹Õò0 _0’’É¾’hÄÓ\rÒY§83™Nb¤„êp/ÆƒN®şbœa±ùaWw’M\ræ¹+o;I”³ÁCv˜Í\0­ñ¿!À‹·ôF\"<Âlb¨XjØv&êg¦0•ì<šñ§“—zn5èÎæá”ä9\"jˆò¬˜eHÚ‡?Éèä\nó ñ¹-’~	\rR@ƒn¶Œ0b<4\r‰€æàpè¨991	R4±Dœ#( Œƒjêÿ ŞÕ\"ãxè†5èĞåŒ#ÆÕDcpÎàÇ0Ó\0000j`î4ƒC=\"E’€æ;¢c Xˆ²H2ŒÁèD4ƒ à9‡Ax^;Îrù#\\‰ŒázrÔ¸9xD§Ãj&¦.£2&õƒHŞ7xÂ%\"°ò8<q*ˆ2&ûÒ7¾¯c€ğŠ@Ö:\"\nCÒ6Æ\n\"44'ëåWV¹m»  Pœä'hÓv5Ã¢º:7<èhJ2:6=“e6m‘e\rMhì‡†!t8£*RàP–7ÕƒuPPÁbêÖ„HÜ1C-Ê:C Â:‘üR:ÆTµ0VÓL˜Œ:¿cŒ÷ˆÎè¯o`_/P5Œƒ*‚Ÿ§¯µ#Ã(ÈÉCÒ„­€˜—JÅ´ÈX\rb¯kFc^\ràc`ØÖ0	Â1Œ#r(‰‘bØVØÒâ:&Èó|:Íã&õV’l6PUÌ=\\±#¥µÍ-¤.J ç­ÀÕÈ§²=z“\0¶ Pµ¦²1bC¢HÛ!^‚(ñ¼>{•Z°¸×\\¶;^©ˆ‚;RôÎ\$\"6¹Ê˜§\n¢sÉ)­Z©ÊeéÌGGiH¤2¤ïd„e26qèê6É4¡OI˜Û¶ˆKª`9.8Ërµ.|Álê’©ƒxÌ3\r‘ºR'Œ‘qC±‚ Ş½cpò`ƒ˜ë#I5ğ\$ Ü9Ëõ/¶0Œã\nê}ötéuŒ¡@æùşŒCéÆ\nbÖCeD„;\"Âäfƒ©)\n†€üµEªIIhÑ:ä¾İ\0ib¤Q0‚Æ™S:iMiµ7§æÓ²xOA¸£¢f£Tz‚Ğ¹\ru\"¤ÊŠHQÇñÆ³ÂwÃ\n_E¯ì¬”ÄJĞŠãvÔäRJâ)O¡Í-¥ÓúŞÃJñ€¹0&%ãBjM‰¹8täaBwIå=¹×ÏÔ„nÁÀœ/8nœÈ U‰TšÇ°CZˆGëp9=’r!ÌT‡m	y’tŠÚê#\"Áˆ<ªãMÉÊA ¦\$X`›6Y/øÿ@õX²f6DQï>¨ø™»å}éM*šÉ8EU` ^zC—°ØèL¡{/¦`Æ¼V\"2	ÈÄ¥ÄtVM|\\ËÀõƒ\"B‰¤è^)ˆ¦0­™Œ‚œş\0PCQä`1¨Ô•š¦#á”Ô¬†ğjÍª=HŒØ7‡v<JBquÒiD4¡X1L\"p®-ù8¼_«\"ÁÍ>¤wàªˆ\rÁÁu¥˜¨ŸÖ@w6“ytÑ(å±LgD2Ğ\n†¦ä~'ğ!…0¤¢ãĞ:ÄÅ*ÉL¾Ø ‰‡\$”„\"o# o8Òd^Ç¢XK‰2{dj¥È9.N&äŞWÇ.£ /U#ş@Î%9’RHXy3Èæ\rO¢NVBUŸ+Ä8¯Â²2\0vRR“øÉQ–Óå>š’RÍ<\$ğŸ\n€O\naQ©Øœ€n<4öÊŠşpIÁ¨U~°Áâ6hÊĞ (!¸30êqDhe\rU±xªt öÊqœ­lğşò@_`b.`€)´\nñ<Éá{*‚–A.oÁ×•É[ÑAş%!:†@õ3Y?\",µ3]LÉmÁ8P T·¬@Š-òWE´äÅ²¶Ùåûj—õ>’XöIYPºeˆt+T¡}%ô9@€îùÂ‡S\"+/0÷‰Ö\n9hè6+št¨Le¢…°P·\",áC%(e©‡§¶Dk•…g794|ñF#IØÅª5àòß0-ªå»PÂìXoHáÌäa…Ë#ÖÁ‰1§úï‘bœ£ÊGšá@\$\\ä8â®g½\$£Ld\0oÌ9·#FbŒb\r@\rÉ¸³Ö	ƒm¡L4‡¤e:›»ôAâv±æİÕ)0X†0•Ê¹}¡XõÉ¡İö¬´0mÖèvÛfNub¡ãS†D\0§J1Bz£<‘â8QİS’ğ‹ëÆ#Vùæ¤96Û„Š#ÂØgùé¹©‰h¦4a‹Ù \0PFQjue&<ª[hU#–F¡‹,ßZIÀ#AÕŠÔ#cPíñ\n!„€A]Oò1¢TBÔ\$\0àŞ'üé\rĞì£”|{’Îåyg”tÖ‘˜˜Fˆğâ‚µìAãh²2\0¥foR©T%ËS\njÈ×mQÛàÆZ!ìã\n£òÉ&á§“™2ŠÖw+ÉÎß—s6ÿøï5€ÜX¤ã‚BÖ9õY\r¦¡×lr	Iy˜F]¸slH‰áñQùú–€ªÇû3¦+9ğõË»?<q–­ØşÙÁñ·p&ğ´÷Y»Ôû¿nëÏºÆN‘ú\0ä4€NÈQJšªñ¸°è\n“ä\n=ıòå.j±ØİØAM˜£9³VLÁş\"DÙØ%²Gˆu+(“Ò °´”şx9f–]p8§Õ\rß(¥e{ÓèÛBAµUÌÌŸ3ò™‡\$¾è‚Ptê¿’dÜì‡“Ö@Ëµ‡ïx/:Ã‚©Ã©ßĞ³l˜~/´å1ÁÙ7rdPÀ­P>ßæ#xóIOìÔOğü¢,ê|ş¥¾şæZş.ÂoÏüÀFD€¥lĞ@\"~o°\nÍª:B6»â˜JÂŞGÆÚ„\02 À¤ F*±Ov#ebÑæ> Øm œ¿ˆ\n#p^Ä¤(´ğ@EÜÕlnçnzÇÁRE.¢íVlËş[€Æœ Èeoğ0Ê¬hpÂ\0Ltÿ-°¦È«Gş\\¯Ù\0Ğ¶C¬qü\$0ÈpÉP	¯nóÆ\$±\r¼St\$°¬ pà.ÎH¨ğÚínTêÌè¤\0HğéĞØ1/¹OÊçúîĞşåŒ0®_m¿¯ó\0qu‘éĞ‚©‰¼è1\0PÀì»mt×‹ÄCŒü°®Ò0²›‘H»ÑL×ÑP±U¡{ív«UñRä°Ìi*˜% ´ÕÀŞ\rExEb,¡\"ëü[@à¢É\"¹CÖ(Œö&`ÖÚ¦¤WŠš….KâfY”Ñ´FÑšÀç‰\"œ‰(SdÈ.¯Ø©­T*ä˜ÚCØı„VS‹<\"Ñ¢\"Â®ùHš\$ñq-~Ö+WBg\0Xm:Öƒ&Øín9b³ÅºÓ­>Zpøäñ]\"Åc#nÿqXÇÑo#†!#Ğ6kíÒ’Ò.:Æ(B‰R@Ç°ÜC¿&—\r’4ı²]&â‡'/¹\$å\r¤<Âˆ»²|1’Tl#—)1nùä48eÄQäb[À–\$Á|SşÓ®µj–'Qt p4B-»…SñW&ÑRÑĞï'p\r-Ñ+Òƒò‡'’¯f@ÒÉ†²UÑ)¬ŒÉˆ|F/íÑ)ˆ‘-ï\$n\"JZÑ±°DâæØ‘U&oû#s,Ùb(ä±lM…3|ÈòPÚ±…0%k4m™)fş5“XAgRvrm)òAÍ|İÄ4±³2ÒÙ5\r:İ“rİâ²w\$d8D!7s‹4°Ï8n4ÃÒÏ.¤:A¯<doç1Î{’À¶0ı1£TïLr³Âçî¨Ê,§NÎ5ÓÒêS×Œ©eÄ\$îbEœHàØ(\$\n³ÂdœWÆšF Öü¢B9è&åÀĞ»eJ\r€V\rgš?+8õ\n¶£Œ(\$¾Tâ&±ã¤Hãe#\$% Œ¹	\"Gè¢ëÚ\n€Œ p?¥Ş#cÎ¨ªºB&ZÎüê„å î'ànôsÔy3Üït‚oßñèoÔ')(#4#‚<\$Â_æ%ThÆÚ\nÉÀ¢Fm£^úf@±	¬\\¢’6Eª\$0<µ\n§79‰¯L²Œ%\0	€Ş¸¢O4öcä”[{!B4bp-íª0a\n¿Q=*” ÔpÊRìX-Ğ\nÿç-GşÄ×'•&(~Ç0,\råşÏnëp!2Õ3Q[T\$5c@'©F''*FÍ‹uVpGÖ€Ò/Kàó\"ì'?:#¦\\\r°\nË(2~³¥éYÃ'Œ*æ¼ÁÉ¤FÃT5 \nN'KäŠ§^mÕp%ÏÕæ\$\"g5Ô1€¦µeJïc’1†ßLÒ•RÂÚ-ôÎ^,@ÃlF\$\$n¶l”j_Ì4ÄR«Tñş2c?Àî#ÃdA‡ögÀ¬¿Æ{aêbvB	\0@š	 t\n`¦"; break;
		case "sl": $compressed = "S:D‘–ib#L&ãHü%ÌÂ˜(6›à¦Ñ¸Âl7±WÆ“¡¤@d0\rğY”]0šÆXI¨Â ™›\r&³yÌé'”ÊÌ²Ñª%9¥äJ²nnÌSé‰†^ #!˜Ğj6 ¨!„ôn7‚£F“9¦<l‹I†”Ù/*ÁL†QZ¨v¾¤Çc”øÒc—–MçQ Ã3›àg#N\0Øe3™Nb	P€êp”@s†ƒNnæbËËÊfƒ”.ù«ÖÃèé†Pl5MBÖz67Q ­†»fnœ_îT9÷n3‚‰'£QŠ¡¾Œ§©Ø(ªp]/…Sq®ĞwäNG(Ö«KáÀ ²(a¯½àÖ˜¡yæÌÚ2B;4BÌ0Bƒ(›0¤\0*5£R<É0d ŒƒjÜõ\$ã{4È§ã›>ü'ãÆ1³C› &í\nè0hî’\r\\JÆ˜î`@&í`Ê3¡Ğ:ƒ€æáxï'…Í´4¨Ar43…ã(ÜÌv9xD¤ÈÊ\nãŒÈĞÚ”#xÜã|—ºk«(í\n[ãXÂ‘\$ÃĞÎÖŒƒÒ) ƒ+şÙ<;.28”M¹.‹²Ò'\rã³&2Ã#(è\nãä†\rÃ:*Œ\0Ä˜€MQUUƒ\r]TÕcRKYÖµ2â%C`à2Œ`P¨4\0P–7Ñk˜äç## Ë	2Of„Œ£²£B\$†0£bk\rƒ¬:½KàŒ:¼£+\0C ä:Ğìé:èJø5¨Ã’x8ˆÒK¬²b7Ú€P˜4ÃK²7”&–*–ÍŸkÀ8Ø63‹£.Šh[½?7¢˜¢&-C\"mc]H£rÒMUT=%—\"ƒE´Š©õ­;å9»‰›Mu€õ\0AN|PïÃ{¸éZN—œ™İ(ºÀ™„\"@PÒê\\¶ËK(ìÚ4K[\0@¤Ğ2İMÃš^Áif)Ay\r#f®”¨ãh„Æ#¶CP*İQ!ËdSÃ’ÄÛÖ[ÆTJˆÌ3'J‚^'ŒbMØC{%5ÃË=·±<R3[¡@¶íñğè9tÃÎ0¶¥_)¨P9…)|\n±é¸ê9×îº<—ŠŒsÊÍÍ#“=T¡1)½QğÇ¸6w¥ 1¨,‰#IT™'J”7*ÊòÌ¶2MÓdİ1ß‚<’Ô³„äÕbj*x	czÀÖÑñà-Ø´ ä(çˆ	/	¨À€½\$ºÑÒ<là8’õ\0ßB|i\$¤´š“ÃºQC/­+\$°–’ËmKMÁ-¦0æ‚Hm]d¿ |ÙÌÙÿˆÕT™ÔÖQ	!=N‘-G„OÁ7Dİ”`@™‰¸b\$„™dğÎÁkeyŒÙ2J›3bê©ÿ§¢ÕHaĞTŠ:”QcuæÑÄ3F@ƒ˜a?à€1—¨šHûr@¤Ş¿ô®ÔÁu9	å<2`äICB‘êj)À@\0()\0¤’d&ÒMÀo%á\r7ÌŠÌé2ÆaN'‰.fÍ* EŒ\\7‡u8ÜÖ©vA…@É´b\\°™²g—1Q.¢ƒÎ8pw¨â%õRÍ c“(h3¤xÙ4ëÇ˜ä\\œ Ä´gìçaL)h	¡7Fõº¨× Ih5šØœL	‘4±Xë·‚@‡eAF!’dğ‚”J1\$IjU@CØŠˆÌ	S4	V6èDÃÉ‹\$ïv]#İM!› ¡Åqà@azï®?œ€ÆH‰£—)tÌ¼\"\nNIÚÑO\$¤IÂnxS\n-:@Ôuèìù4¡I¹vI–•VÄ¥äo	¹H14‰œƒ>H\r²,UE°2 ™KÛÉ0Ü#I@BÕKD†ÌŸÓƒƒ‘3J«İ`ÖnªöGR1BÈÏ\0s¨f à›ËÌ¤8!ÔéH2|Ãƒ³¯ıQH'ÕØcA4‹âĞa˜Y(7uuGœ0‚RŠ'˜“Eõ—nb9²~ÁÎãb`f	¸1èM«ôĞÈ¡’Šf‰¹¦˜™yfgéQ]–bà‹<‚2a¾;«µËCtóÉì€Xà¬zá6ˆ\0ê )³û#ª\$R¨ò~  ”cèqÈ'Òú€Ã™3P\0(-E£b¶¢•2ç­ 	Yê\0\neÙ` ŒxripN\nÈ‚tĞÕI€¡İ>(å8_0‹TA&“œÃ^ÌÍ‘nYÁ­Ğ6’²Ây\rL±E2ëbLLáºÜ–Yk[Éõ/¡›·{\0l>¨&%¸šë‹qö:qHÅº\\ØPFMD¥>Yğàòœ^O9Á>'£Çw¹Ÿ<'è¡p¨C	\0‚–.T>åi*#_Òü˜%ÔüNÃ[&j¾*ğ^Vy„0Ìu\\i¢Jº´é…Óá—Pé´ù¤”â«ÑYã¼‘Cu@doHû+ä[l„â¶™ÔF«\0™á¬uU9Õ 2¼£%¬u™Ç)Ï—6ÏŠuÚêÚø7ì©CÈ­Øº¿c´Õ9²uª‹¡R\0Ék­6K¶¦ÖÕ{gblcg·µaÖ›-—Is´4Ûs\$ÁÕ)šÁ»ôš†/·nn¼–›œzÙúGx*½¦Îut\r*8îI•yàØ§‡ğN\$d©Ïá%Tñ®º8†Ûâ[cƒñbAøÎÕá©ÓxÆÂs,½\"¡8å€J‰b—#îÙŠsò„ßói*f/­BĞKÍíÆØ¶»>­’šPÊ|Ì2áÓ¥‘GgÔÏ©ŒÀØ‚ê’cCÑîÙsA¥HÚèâù<ò€\"’Éí»\"ûğ0y>/k`KÚ%b÷ùND.’ÖÎ«_\rÔûÂ©î“dSÉRÀ>1N‡FÑƒ±òk	x­tÃ•Õ*ŞstÂ–¨W£Æ%ÛÏnÆ¢¨½JtfŸLä¯ñ•hfˆÂ)Ë^IVÉì\0‡OZ–q:O	óãü’HLí¶,ß9¨ \0¬^½ù|Æ˜í]•z6cÌX‡î ‚h¡¸v¨“¸¼î­¬P_F\$\nüyaQk<¿î“­†õàäö/şşONÖïFît±@–'JÊé(´c\"%E~\rmæüÊé-ÌÓí{­x9­\$ğÍÂ‡ílÙ¼¬-ğÓî80=ğBo0GJûğNKMÈám<0íÑâe`ß°LÜMëPV0ç˜ÉL˜±!é/@º°–7pš!/LŞ°¨±­\0&v¡NhÇìr¦Ì„6K>WÏÒ‹üSÂL=ê°¨`è¨£d\$ à€`*bæ&ÂJŠãÂüƒ˜6©	 Ò“0Î´1™â¤*ô\$È°\rC\$|CÔKG\rìí\nl—L‚7ó¦~·LˆRlÊÅzWå‚\\U(­\0ğÀÅ¥\nC\nQ\\–°¼ö´àp\rñRanò(q`ş‘o„óÑW–ÎLvé'\0QZ¼ílìÑ(c¼Ï¡£ìÄ¨%…ŒMÅ€O2·fˆºKw\0Ö1€ŞeGHé?/ë1Õ‘ØŒÍ±åLñ¬p\$ÇX]\$*fKÑ\rñ×qr¥åĞ\reÕÒjPgq¤úàÂá0æXŒË#êé\$ò¸Æ)+ÎÊ´'p¯8 ’=\"ñk\0qƒÅ&ûòJ;¦¡!Œ¯%£ìp,©ÃîÏrL* É#Mn/¢Ï‘çĞ#¿'RA\$ñZÄx/cb-1ã!ÃìÊ‰P/‡8Æ†p¥åĞ~ŞëÆí§+g\0Bn&ã#Øğ:áÍÊä²Î3P[,R×ò½\$‹ÊÂ_¤Ü±¬†\nqÊK£vU·+’ûSo*ÚrTõ3,²Üñ¢ç1P°sòÛ1…³Öòèä“,SÅ2•gf\r€V°ò@”ï°'¢~“ ê7æ(%àŒ®(¯i¾6€ª\n€Œ pl€Ü{böT¢ği/E0ğvc®8ŞÙ°T'\r£ «8ĞpûĞ7“Šµ°+Ó†˜ø#mŞ:¤Øc&¨@X/`Ì \nOY! æÎ“VÅòr?)è7Æ&¶Äğ&k¦ I:Ü\"Ü²¢÷“N0Â^	€Ş­\$Ì D\\`Ô7£¢>F<Ä\0È8\$bìÌ¢ÌÌìØõf6²B(Ÿ¢bª­BĞrRKvÖéCÏ¼'ˆ:64FÂPs\n1ßE#PCã2êœğ†\$¤4Å™EIQ¥ûEêŒ)*¨‡ş2Oho@ĞÅ¤Ü/¢ì¨*\\<	7IBt'Š\\ÇC˜·à¨«Æ³â°úíL\nÄL¦úÀàáFÆ·/€\"ßL¦\0·+íğ†5£\08”4©áB&/¸#\$åŞ‹æ…*7D\"øXôONÊ6@î2‚;ÅF;¥4%ºpE&i€"; break;
		case "sr": $compressed = "ĞJ4‚í ¸4P-Ak	@ÁÚ6Š\r¢€h/`ãğP”\\33`¦‚†h¦¡ĞE¤¢¾†Cš©\\fÑLJâ°¦‚şe_¤‰ÙDåeh¦àRÆ‚ù ·hQæ	™”jQŸÍĞñ*µ1a1˜CV³9Ôæ%9¨P	u6ccšUãPùíº/œAèBÀPÀb2£a¸às\$_ÅàTù²úI0Œ.\"uÌZîH‘™-á0ÕƒAcYXZç5åV\$Q´4«YŒiq—ÌÂc9m:¡MçQ Âv2ˆ\rÆñÀäi;M†S9”æ :q§!„éÁ:\r<ó¡„ÅËµÉ«èx­b¾˜’xš>Dšq„M«÷|];Ù´RT‰R×Ò”=q0ø!/kVÖ è‚NÚ)\nSü)·ãHÜ3¤<Å‰ÓšÚÆ¨2EÒH•2	»è×Š£pÖáãp@2CŞ9<12ŞÕ?íb0£ÇQÜÈ§sÖ²ÏƒT‡\$ŠR&Ë‹`Îª\nº|§%ªû8²	!?/,ën’LSÎù ŒL€œËÈ Œƒl% Şç8Cxè‘:c„g;Œ#ÆçpÎ3‹¬ï#›‚;.Ëw>8´Hæ;Æc X”(Ğ9£0z\r è8aĞ^õH\\0Í³|iŒáxÊ7ã…%JC ^-ğÛ¸0Í®°Ò7Áà^0‡ÍÎ³ñÔºÊ‹jhÿÛ#,´…!ˆ»Ê]\\(±\0ŠµTøÊlšÚ]-™ –¾ò½¢‚İ‚ˆÛ)w®£ÉÂ¸Â9\rÔFŒˆ#>ó¡€N…(©‰a‡a,ö\"—¼Ñòœ >S\$_ãR:Æ^âHºHH'ixZËˆÂ¾Dd¯@‰NŒ#¨Ù;ÃØ:Œ°­Ÿ‹ZMy R<¨ÕÈC&ë3şÎÜkª+ïìµu\\9s',âÌ’€‚wœ‘lÇêCöó±ë«;*§	Ü’sméÁ(òÛÌ’»Ü¦°H&f‘ÍıÉyHYrRš¦ŠsJŸ]ğB‰hX)Š\"b	ÄÈìê5*±éŠ¥IãÆò¶éÅâ™^˜„ ŠÌªn½+1rq QİZóï5WÉI°´°Ï\rÃI‘y|•—‹J	­Û´Jı’%«Ş¾ÔªJ~|¨z¥º†ÅÌÏ­¿{'÷Îgõ¶¯a¦MB „’2RĞÛ¼å„@AÅãwæàşßÇìCÁ×\rÊd2Ö(‰“y!†\\ÅĞ[±åˆ¨»SV’Q±b6\rØ”‚4c†€“&N(¤\$¸àY¸r7&íB)Çîƒ0lMå¬O/4ºÆB o8‹7@`uOÊ\00033`@xgBAÍK‡@åƒg(HEF›ƒpu:à 9‚’Öí‰>5ˆl®ºái\rÍ¹B\n†ùõ†¡úB`6E„`¥Ã\r!‘8)“z§ò TJ‘S*…T«#²¯JÅY‚ôäşƒ¢ÇV€ˆHõŒ„ÖRÌ-mà2xk‹€_¥˜ø/èIÅ‚—e‰ï!’Ä‚‹¢íƒïD¡Õuã‚¶jMJ¢ğğJœ€¹L)©\0§Õ\n£TªT‡uV«Cr0ÊÁY+H\0 \$“W¡\$6‡–U˜t“ ùúœùÀ£˜IÑ5¬î„<œ%¬N6`]!!N\"„X¥Jå¦ò×i¤(Ëy(…¬j\r>É`l\r€€1à£|àa!„3K„ñåÜE¢q\"%\"åvN…\$A†p\0Ç0§igPØ!ò˜ùœƒ²(”!×‘’•,6#yÍq\n€H\nİ×Ó—¥#^&Üò“ç4P‚Èš!HÇ9ÔqAÊ9•„§ äyÛN©ò‰ğïYYD,fİn ÒjmLcÕ]ô),¡Cš¶OñU•‚ƒ„ZR2í\\0îvƒha¤3ª(v)Pcfª*¸“*è·dı)… Œåã4Ä[04.÷l[ê³*D\$B;Â*ZÖÓÆeåT„ÕJÓ(;™\$æ¹…ZÄT«Én*Œ£>Q¢Ô-TõzFJ	ºJìÃ%yh(A\$‡˜X#İk\$JÎt£§‹<iÜ3# Û&‚p°È¸1Ä…JkR¶9¶áÚ£ÂNˆPHP	áL*T˜ìnŒö¶½[÷?¥Œf-­1#eØë‘áIbäÔY;R[×õ¨)Ì’Ú™)ö˜›ñÄuÜ›¯%ÈCL’<œ¥İË4‹œHUŠŠY@@å°f¬@€ä¨PŒ*Xa\rÌ\$4ÍÔ÷oeî¼aÈà#CÒÕL]Äo¢d•ºæ‡nÃ¤«’†¤Âp \n¡@\"¨A\0(•dK\$CUOÉåş¨‡¸•\0R³@ &\\ßœs›Z\"Nb	¡t€DZ‰ÏëèÅ¤Áeˆ‰>+[äãZWÚ²„Ì¸’73ÚİXèĞ†¦%¿—•°óêkÀ\"6¸–Ú8'İr2\$2µÔJ¶5Ä\$h:ñã×…Şj#5Ïl±¡˜Ã˜“#È|‹R¦¼ÒZÌÃ«X	¥‚ ë‘P)sHjõÌZÑK’¿iwtï1iEuE?p<½ºì‰1>ÙÎÅ2ÚÆ‘(U«#ŒÜò\$2¹œ\\¤(œò°´‘]S– šR`&İeI©w#—zJËCS•‰¤û‘è\\Joä:«<“&ë•Ú¶càù™¯À… VPxiLö¬ÍØ´ÏB˜e9ur²nÆÙ¯–Òâ™C.vÂNHeéi×5İhÇ¹¹c Í`œ¹¸Aİ:ëHİx¸IıĞçÄ\$ÄÜLöm©p‘çÛú,­5É—°‡\$Ôš&ĞVÚ°m§wÚ›Ş†‚awûFÿ%*O7×h{Á+A@ÊL¦¯ê¤¢N–…°v®)hZs9òLE¹zXMaP*†éF¥:03‚pÓ¸sO)î¸6Æ©@—èƒ@¥ÖW.†Áa8yŠ'P@¾nÃÔùÇÜyŒ%\"2ÔŒö(	URì?}Šƒ¯ÑUÀ%ÙüBù‰ßÛŒf7ïÁUÎîïãùkÉ*íÿ„ÚĞ?k˜4/Øú¢núí\\@¯ ûèÜ/ìx&FÿOœA…ø“Ê¢]°4Â‚¦¢Ó~&ÇRÚgÛä6M-\"ÜïÈiĞş´.¯æƒïêÃ¯îıO£Iü“ÂøOãÇƒP >O×/Ü3Yp^ûĞ~ü+™a BĞ€é 	ŒšQ @F@î\$bJ•.Œ,)Nkj.dŸFÕOàÖniÂÛàÚæ– Äv)\rH‚Ào–~ÇD ‹ü0äáäÄê\nv¬µæğ_!.%´rìß¥¶Î­k~,€C¸İÂ,ÖCâÑ\$?/€[0”ÄJ”O*å\0”Æ„BÃVmäáK½0èäLî£qåÿ*£d?°„á'¼.0ôª*ğÀñnæÇ\"ëoÍoP¨±İ#Aqx?ƒMî²•–pbdwÑl³­:*#7´kä®ëc:‘¨M*‚K<î¢éOÄN@ˆF@àœ¢8l«l.„@Ö§%	æDÛ‘Æ[\$úQè&1S¾”/üş±z4Áp€æ>ìf¼ì­ d-”)läŒíN2ŒügÈ.\r²Úmì|ñt&°G÷Í°Ò§%*Ç”v«©CÍòM&D¤î2k2pmÂ­&'“'êpÚÒl.‘¦5‘}(±Ö*øóç\\{lşÎ\"Ì`>P\$ü±eİ\0B2ıqø¹1+0ƒ‰R~¯öùğpŞ¯6&Å(¯Øô­,ğ\\Rµ\$Rºÿ§)oÏ,04<,°¨£âîì»C%ÇE,n(ì¹óR›³!0ó\$ï2„^Q¸ªîÎl\"æ@DàÓFÎÒ:.,ÅBÿã>ŸÃâÙÂÀ\"qÂ2J¬RóJ*ˆÊs:*óVeÆ!61 FòŞ2ÎZpFíÈrïPÊLDlã¨’Û‡`Zï¾!-[7B0é0Ç/3cÖíNÙ9gÁf°x¢Æ·íÑm–-Šp¸óxuÆHqÖêÇQ1CI†F·6Ö“ôÑÑk4Ë?i3±·(‘¼ô¢±åÜuàŸO8ŞS)1q›2í`k¢¾ó´\"sûB&®ı.åQ´Ù´íĞB¯5DGLÒƒ:{¯L¹Vó±HcXy¤G5ƒş®ú\$‘:J†Ÿğ”}Ğ¢„¦û>Á òò÷-T1Ë*“-3—cCÒyIRJg(f­Ji/Ãî†Šo­[5´PŸíüuğİäJw³X¶”Æhæ“MƒMÇÂÜßèl\\Åòôäv‰K&@ÕBÈïÄI´.¨iP¯C³ù2îø\"NÿAMK¨¾úCâğµ\$@HwtÛ8ÃŞuÏïâI!%å\$´3BfÈHõ\\&uÑì²-u[VU^U)ÏÔ\$‡UÍR“2áK“>1¯-JB­D¬ºnöÚ’¥A¯•O`ßâ†²ÕÒÆù¬çZbÒ\"<³_[èaõ¶Å.0Ài3Ôªª¿ôt\\uÛÙÂ=ÈoB°ãÒ¿./Ó\0p†ùuğ`u3.CL¡/£`5rÁ`Ğš«œ\r€VÙ5~ñ’.ÏßTª*«n@ŒÈ`Ú«Ö²ëÍ@¨ÀZò+ÀBb„üùZÌõ–a-Õ¾=¶g_Ï£/Ğ+fN]]m)5NªÜûÎ²‚ÑÃáv]\0š\rëÀÀóY¬>.Ä9)¦ŞcMÉ:®Şş °=ÍÀ_‘ãbê§!•/ñs.>&:Òj]6Ğ{ÑÓ1ôºà\$¦µ´ÔÏ³È,dŠv±0_N±6Ü\r÷F»2Âç\"Ö•p¯ş{³ÖûŒ4¹Îñ1ÄAvnHéf´\rÍq—qö	•d¨·+›qÊ~e’¶w£·;pwO)“Önj“¾®´ã¢uSÎ•2\$‰ê5‘Í0ÆB\0n’EmÒ•]’CIƒ)8‡D?æ83å«Òiyt’ùğ*@¬O ê ÛY£umÂ'ho‚¬¿ã3ôZ·6ÄtËqìñíjÂ¡=ësr)A:¶‰/B&ÜÇ”v.&E\r4·64àŞÊ î8ã®òÂPûm~^cP ƒğø‚Ç§Sj' "; break;
		case "sv": $compressed = "ÃB„C¨€æÃRÌ§!ø(J.™ À¢!”è 3°Ô°#I¸èeL†A²Dd0ˆ§€€Ìi6MÂàQ!†¶3œÎ’“¤ÀÙ:¥3£yÊbkB BS™\nhF˜L¥ÑÓqÌAÍ€¡€Äd3\rFÃqÀät7›ATSI:a6‰&ã<ğÂb2›&')¡HÊd¶ÂÌ7#q˜ßuÂ]D).hD‚š1Ë¤¤àr4ª6è\\ºo0\"ò³„¢?ŒÔ¡îñz™M\nggµÌf‰uéRh¤<#•ÿŒmõ­äw\rŠ7B'[m¦0ä\n*JL[îN^4kMÇhA¸È\n'šü±s5çdymE8YÚáñùe*´Ü	‰¸æ™(¯8ˆĞ®ãç\0000ìR:\nXÚ0É’.hÜŒïÈ6£¬êz½(ê°4(¼(9ƒªvÖ§Á¤ÓA*´]\n\$°9p@%#CŠ3¡ĞÑ˜t…ã¼œ\$Q*¾(£8^™í æ9ğ(^)ğ›0ª,&ãpxŒ!òh+!`ÔÁ\0P¬4jè9£Xè:ºèÌÔÃÈAC\\¼®\"pòƒ/\0¬ÉÃl«®¤ƒò4ÀAM#ğX–7Å¯ó°<\0UF6°³&ğÅËKC<)\r ä„\rt¬:)§o3&2<…\$x2ÎÓˆÔÃ¨İ?\r3Kî Œã;ŠºÍø¦›¼	»ìÆ/C£’ß0VBà“<Ö „2ÚO(ç];(ğ:'å\"èó˜d(èŒØO=’½5«½9p¥Ø)Š\"`Z5­0›Ì7Ğä²X¡;­­BPiLh1A@RüÀìİ¾M¶ÚcãFBŠAÄ·Bpƒ0Í&,ã®\$£…Àä£Æl94ùÃ’QkĞ‹ã\" „X3“’:<£:B;\nddØ N„8#ZŠ­F3º”GxÚ-—[ŞK«®'µcïÏãkrİÆ8™<-¸„©Vğ2­(³0\r“Ú(‚µƒxÌ3\r‘\nh&A©L]d3á\0Ú2¼”ñ#2\$Ä52ac<[H9l¤0#ss·SÒ”\\\"7#É”¼¬A-œÏ7ÎĞÍ£]t=Kl*øïT´ê)'\\Ó±ü%„\rÈ,]ß.±ë°0ó^†‹ ‰óysˆƒÄ#—E`÷½J]e|)M´ÎÅ1üƒ!È²8é\$ÉrlŸ(ü¡tª•Ãp/‰¥Y¦”¾ S)­6› Ê–Ökª2DŸ‹`àhß0 }%ãWUİS©& :‡`ê ª\rÈ‹&`±£ˆù Bçè‘’BJI‰8;¥È”ßğrJÉawò«RúafÀ˜ªÒ<` `>kHÉÕ*ÒĞI3‘~\$eÒ1hZ’BÆÈÒç´€à‚Î NĞøÁ£pÖ)4^«Ü¥9ÇTÂQ 8Hº,ÅÖ¶<T\$®è‘B¨®œ‘R\n°”¹ç~‡™¤-Ò\0Ç˜Z×ÈBÑ1\$¤0µ8€H\n\0¶L\nFÂêƒ›È'DÜ2`PSIIyí}|H8I¡âEˆºD5ğ@eÌÉ›\r&\nO³²v@b'FR‘øfzs-²5Ş¹òé\$Id“y„R’ğÜUQ‘ÂU¥é’fœa„*…Ñó“EØªÆá\nÈ®›Oš\$õ§0¦‚4¤\rä(™‚\0¦ÃKš%&9Ğê¼N*xĞŒ›“”^ôcÌË'ó4¡áHàe”ÈËm€‡9Áp^PpÕtæS	 OmÏve0\nîä}A™[ŠRêê©ã”)Ò)Añ dU¶X½`s\r\$ ™¡\0Â -F¨ÖS¢İQğM#¬/v¸@X¶¥áÎ­“Š³´Ï2%bFã‰d‚(„	‚0b^ Ği%!*ŞsY«7¬¤ºÊƒêBê¨Fl’1r½tI»œ‹T…¡ÄìB  \n¡@(@‚(R	!8#ÙûB‚xR\nP „pjíj£T³|0ª`o]Tp—æÖ+tÙO9s©¥éà{´hÓĞ:Díd;“E^HFºÔ\\û•>w'Ñ0Ÿ‰Ğ× £ÊPUH&M~–ãwÙâoX×‘œ–FÅ¯B\nÈİ@ôXİ“3Ü½´³BP[R¤DE7Œò%£”·@¦G©!eHmÁ¸T&rRZUR¤\$ÖpÌG®kğ*¼(¥.vÈ®p	p<÷xí³ÀM\r.	âbA¥”1ÙÅÎ†&ÅaEUIÄ“cr4\\TAÈ¹í:_TIe\$ønwEäğ‡d÷!*\0“9QÉÜ¿B³\n¡±ˆ#•äx¨tLë°#&cBÆ‘såAûæQu(;?“}_fOmH´ê Óâ‹WåaVDå<¼•w…£iù\$€¼i£H”é\$1…È:M1]d#œn‰Oj9~F–Û»nÁÙjj{Oä\nÕZ³QÒ5‰Örn¤°”)^İI3òãOhÍoª5Ôv\n{g Ï£İÎÑØa,ÏâÕĞÙ\n©)Ôwªó_ÖA04•Ú{º	óäíM^{2ÉT\$–ÙÉ±ôì˜5¬–é\n™qWŒË5“Ú\\SºySæãƒ“Vè[~Ü,hã1ÓP¸ÌE•\nF^Âw{Š¶5™üiHˆ^~Œa¿6JE©BúÈm9V'QœtqğÓ{ù4È\\Ï“¾S°ùÚüå×ß˜ôØOøm\rí×hUI“×¾\\¦Ğ dÉî\$Ãå««³ÎoO¿]ë0Eb¯¢\\ò/4–LW˜öˆŠª1\r»\0¡x¯0R­ú¦mˆÔÕqàÃÈ:ç¼°«µE™šEê‘Âé­Dšß •ìåæ‡a÷¶UßwlÛ|c<ß\"@˜\r›Õo'O’M€Bõ“°\nYçÏ×OE¯=)ôáËÔ¬‹&Â²Î[°¿›sƒ|Sı¾Ç\$iÍi^{æ¾Ëİv¯'Ìvå\r€¶Ã¹,Ÿ)ñ°Š¤j6™)„ï tUˆ‹!uÊ5}Hv²Í6”§GNa‹§´âêäiõ…,m›Åaï\\®÷˜ø¢â	¬h.eƒÊ#Êk¢ı¥LÈÔå(Må÷şæîB÷äí¯\$è†Ğ&HP¬öÏ¢/Ï\0ç0FBĞ\"çĞ',Úèmæ0FPH¾Í„,ÀÌl\ndZ[Ì\rEÌ\r\"ğ5aJ_ƒ4\$‰v™Ï:#®Ô÷°2¾0–õ°\$æ/VóÏÈäb}e–Z¬c.¿¢:ğ@¡e”Y„ÓÆI Æ/å\0Påì>£ôWBtî\0‚ËÀäÌAĞ0ğ,ºÌæ8°Vùÿ,Ñp;-‡lÍÑXÊiñ\0002eˆ\nŠ§Å¼<GĞ?q @Äq#4\"îDãæÅCõÕé‚\"ğ\$zÑL|P…ñU\"¾ç¬	2CÏ\n¤É˜[Àœ¦ÓĞØ¿ÎÔŞ0{mB—ëù\r¤¶3¨_nú2bĞ'j@S»	#SÄòäËÙnnxQZÚpR1¡1Aju Ê&€†O`Ø`Öq*ı)Â\"å‚Xg´~0\$5)*öÿÚcP\0ª\nŠ\rÎÉÕ©\n…àÒ±&x0NUmÆ­Ù\"i\"Í¶}‡x#è4XÂìÅ£N(ÄnÀ:ãŠc…ÃãÌşR\\Ä¢rîª	£…ñö¤çÜ¥Z Dh@=Š¦0j¤'Bj/-Äê'p”èÑÓÑÖE@ìax1®tû2…çSrœèµ*-Š	†çò³\$Ò4æ°.÷Ğ¡*Ò7(/\"r=,êÎú¥\n>å®tëèÒĞ>àšébŠÏâài«’üîêæäâ«ècM|ib%Ñ.2~0cÄ.à\\+tÉ¢ü:²_*£|0kµ+ÂxF\"¤à‡Th„4+®Ä2È\\*eâ’¤rñÀ1î\$‚¤'¥éEU\0"; break;
		case "ta": $compressed = "àW* øiÀ¯FÁ\\Hd_†«•Ğô+ÁBQpÌÌ 9‚¢Ğt\\U„«¤êô@‚W¡à(<É\\±”@1	| @(:œ\r†ó	S.WA•èhtå]†R&Êùœñ\\µÌéÓI`ºD®JÉ\$Ôé:º®TÏ X’³`«*ªÉúrj1k€,êÕ…z@%9«Ò5|–Udƒß jä¦¸ˆ¯CˆÈf4†ãÍ~ùL›âg²Éù”Úp:E5ûe&­Ö@.•î¬£ƒËqu­¢»ƒW[•è¬\"¿+@ñm´î\0µ«,-ô­Ò»[Ü×‹&ó¨€Ğa;Dãx€àr4&Ã)œÊs<´!„éâ:\r?¡„Äö8\nRl‰¬Êü¬Î[zR.ì<›ªË\nú¤8N\"ÀÑ0íêä†AN¬*ÚÃ…q`½Ã	&°BÎá%0dB•‘ªBÊ³­(BÖ¶nK‚æ*Îªä9QÜÄB›À4Ã:¾ä”ÂNr\$ƒÂÅ¢¯‘)2¬ª0©\n*Ã[È;Á\0Ê9Cxå\0­åÂœOªÑ2~)#›î6µnz¬Z*ÄÊœ°¬ÓœÎğSÊU-ªËI\\Š•ËÔBéFÁ@ª9Ìô2/Î\nù)IJ•6l\"ÛD,mEÑÈŒM%Ã²YVAñC&E®ŒâŠ\"Ğl™UÄB/­N Œƒl‘3„ Ş÷¼cxè(#„ÕgŒ#Æ÷r@Î6Kìÿ4 @;/Ë¹j<×æ;ÍC X–èĞ9£0z\r è8aĞ^ø\\¢ØÃtÎMC8^2Áxáu]ƒÈ„L\0|6ÍO3MCkì4ãpxŒ!ó\"4º\"èT­Ì)ÄJu6¤)M¹×4Äß[¥‹5—KÔcqŸÁğ”¡`GU\\Ã'\rêwÅê‘QšjS¦ÊQÆwM6íÊšÒAª¬8ğÂİªb‘,æ62”ÃhŠéŠãä7[IJ2FZñ\\Ù‘ N÷¾ç»üßeKÊQV)m”1–\".”ê3Ğ‹r¯Ê)ÒßgÒ‘ÍmÚ¢\0Tç‘8‰zŒ#¨ÙgÃØ:Œªû„›R	Nf‚ ù·#£pÆ:drBåÖ*±g™1)ø‚3Œ÷ Ïä47gÇ/ªù€OÍíF*|k¤ñu?#ÒïµŒ®İÂ³(D¼EoEôh‡+Gô¼ì°€R¬™'Q½,PãJy¯wn¨6ü{	\0c!¹\"…˜RK†7ªdˆ¼u^ñ;Oı¾s‚¨Úã-AoœÆ€”ß¡iÉ¼%7\nçËàmÍ5_øNè¡SW…‚¸Ö“k.GPÑ¯VØ¤ÑX…-â»(@Êà#U6·è€òMCoĞF3ƒ]‘Ò†±pŸöh[àÓ>naÉx|Î`«á:ÄéË=¤K	T(	&i¨g?]IüØÁ\"SÒ!hpÕ.DgğãÕÂÏUñG	DU4Šh…U„@„ì™ƒµ9ñ`¤»–¾éÜ<F\0&¤Ì¥Q>ºVPğ}ŞÁh[IÂ|\n˜t=	%C=q\\ĞÚ:¨OM¨^Uš´XE!&;Gü–™Ô-í¤ŞEâW\r]\"ƒÎAÇ ùÈUæÜQF  #†PŞC,Â5@)Õ‡ vÎëv<A¼3`Ø±ÎŸqM¡˜6@¨Ï+\rÁä;ĞæV²ØÎ¨0Î’šïKqç¤€AGœ§Ü0S@e4€4-^ª©\"ÍÊEQ³n‚ÓHR÷`Ëc1%x*ôÂ|àr¡«e\$‚ˆÁS:ïl…l†E¼Nòô^Ëá}/ÅüÀXé­„°¶²¥ƒÀd,PV>’Y%M\0[¨’Ô…áI4…3³–wº£\0¿¦%½À\"\"sAsS ïòd=HdŒH» \nõ_©§ÚB\nğM\\'Ş¡0ğæº×jfÀ4¯@É`j‚ó^«İ|¯µú¿Øw`k­°€äÂ˜c–ì1lÖ6*Áô{(F™†JÒ¥ ™¾+„C[YáÁ3Ğ«{ZÍmD(ÖÄ\nã@ÊÃH*V5”§òâYš\r/„ÚÂÁ+OAâZµ‰2®@ÙCâ¹Ê ÏøC5”ZFËÑ8E¨òä?'Æô’\0Ã<\0c³Öô4ºévg‹|m”hLKipß@’td £¾‰2cÁ\0\n\n (‹¯Kp™Ššs>é”¤LføìĞÙwëhøSÎzOYí­ñi è|ÚÍZ—¼7‡|‡/'…3…µJ4ÜnËê»Ø¾êszoÀU=Lìhø-ĞæÃÖ½L§åàáH×M—b-ğ;Ÿ Æ.iëä_x PuR…x!…0¤ŠA³0Ñ0¸]:ŞÍféZ…62ß]!3`…ÖÃºŞk^üj›®ÇˆÓ¨#»P-ú2Ä\r-â¤’Êl©ä„Œ4WmÑ‘ïVùyMÃEOğŞÁÇD_x5­~ABÀÉXc›¸€ßeÜˆfŒ*°ûZi£[]u’S'Rië ‚I'qd†–øyz”\r×ıè]‚v™4†Úk5BÍ‰”1”5Ä~rK=ÇvTõ–¥ÙLŒ\0Â¢‰ò^nìÙ4ÊU\$˜„îfF<·¹vÜ-Ãğ•	ÊÎ8á§3<‡æ ÒŠ–ƒ6z¡Éè&PÉgC.;<8Êm˜Ó:76¦û²&R‚PÊ*ÜÏ¹è8¾ò =Kt#LKÛài™¬ıù;Éƒ`ø@˜ºu´qYg:šÎ.¹„¨o	±vÑÄ ğœ¨P*^ûßÂ E	Â¸Ã@y~âîpÛ^£f|ÍŒr5ª¥ÎÈü(¶*UÂy‰Åß,NÅ¦pº/^2y[vä=¹ĞÙäµÕZi¶×ØöšBÂ+äÃ/y©Æıò\nE°í	?Å]ñ½Oóß-!ÉBêµd;»H«Î­ç=c4ó9IF(¦ÏÊÈc'ùœÆñ©6D__t­ïí'ë=]²y†TıH†şÄxıï”jDv‡î\r Û¨¼ûoúûÎ>/.Xú‰m¨’’/²úÏ\\Æ‡\n×„h­ì°ÉÊ†ÂsD®NxÉ~\nÆìÁ£ş=å JXD%\\%0\\,ì@˜ú5n\\ÖŠşsnh t¸#òM êÏ`@ÄlJÂËhx)d8åÑÈRxï}#\0l¦Ü9­DAÊô\0¤^¥è`ĞRÎïò­ÏP‡‡¨ïf˜ç.\n`ÒGbÇN¤gb\n`Ê=‡~Èg*8Ë\$²ŒŸğàoƒÔ î”º¥Bpõnkh ‘G.…°Ê.ä‹pLR ’Şil(ég\":fü‘¢*œˆ’.Œ‡b`Bª—ĞÎ1ˆê÷ä¤èªuÊj…nà‰Q*÷‰BØo€ÚY\r‰µ\nğŒÎ8Q†ö	IM¤‘ˆN÷§jVÎrEhx`»qˆæfÈ\nJìõDr+á,hR”B\$ÕéÖÆ:>ÅR,¯¨\$‹¨Ä/£°`.±õèã/ÖPœD)¸-	\"üòmo=h—ÃŒğ€¨ †	\0@ßÎ\r%˜ŸL”Z\0àÈ£î³ì {Ğ×‰ºıÑ\"&°qCzüš#føŒÒX¦/ÇapFøò1A1.õ¢ŞÊ^ı­¤ôÎDCM^GF£¦ŞÔpÒÙD„%Şprlrmˆ….Œ»ò„ÿ	)`\\,¬F’BÑêS i3¯q(¯¶şæåÍ—)²jû±)ïäòÈPlHW(ğy)&şQ\" º¹\$r¼Ğãâ˜Ñ¯0\rgR®‚ÈÇîRÆl>;2_+gâ„2‚º°,\r‘,ğ®ÿâ#,Òí*ë&Š÷\$\$Ò/¸‹¨ÎÚp,FÍ3P³5M`ºròò4“](28À˜ìEÊÒç†’Mræƒªøğö´'î(‚Ó5¬]««8É8ÑNB€(S”(³)MY*†áK\r%Rüõ6O§)ğh­ğmÓ%l&4`üONvÏ3.\rq9Î[7óï.„fÃm4*qbÖe6øQÌ,åz)şÛ3Tò««sŒeFÊAë4¨o<üü&‚õãØQ~å	-‡Æe²¤ûD06bZ\0†=†ìPÍPp²É-Jeã†‰¢\0ˆä0Šî`¯ORI¯êû“9Òî÷m˜~ğ%5­,™ÈbÙsCÉHÒ¬å”‡F²IÒÎ ’NìeQ}I³ùBÓ<§q5òKCM4şÆşÓ/3´ ÔôÔ±Ñ¶°TFiÓ½/³\rMtÃ\rSGMô¥H¬V¯OJ”Û¢ûM”*éÎ‘hÔô²ö÷Ç¬ =p7æØ‹ª§!‰¦NÇRunˆsğŒ2rÄ ˆM à–B5@©AòÉJ#RÌZ×rsUõV,ñ ’à%sgQ¯¬¯òŞ‹Ã[Õk-Ô)4YNäjôæAÔëHU	X†c*\r4œèÅu’\0¨+™ªR÷	ÎòF\\vÀDÅ\rWUmZ‰2ûuÒşo±Oô»Iõ¤‰Ïù+¨¤‚§ÈòõÚ­ñiNM*³1OJ2^X±\\º´lmÈ•OÔ;PNÕß_u_ÖY“©Yó]a\r—P…z+ñá3¯Tº•‚â]Bs»`¨×P¤1R–\",©-Ra-©JÓVYÖ:…´MGv7dr´m±âÓ±é^ÓL…¶]^Ô0Öf)ÓÈ\"q=£\nuCĞ‚Hk=¶£%2ğı.Ø&ÑS2nUëbµï`ò”°Ë:‘›m6=VAKÖ0éÍ¬UVã©!ùbtzÕ´ÓbõòÃÔ’LTIqòcXÊq/–»Z•Èæ±If¦šøšdƒni×Z,«adI	× ÌÌg|È`@ğrÆH#•~hŠğA³×6ÕûrP/’ãÑW·Gq“ÃD‹gs‡sQq1c¯L÷š•ÃqÖtòw#W«V5g4«VÇÉSO5¸  ¬dÃW©Wj*'Ğ‰¼Faw‹Ïn1nu¬ï)U„ñ`ÿ’¨ß?‹‹H/.6äú×„¼×±x·¥1z’t¹@qivåk–{VŸ-±˜luşúôyy—	`Õdx1cö~Ö#!V)K˜+LXE‚â÷0„Ô§O·‡tûbÚE< b~£Â\rÀèÆTÍeó7t8-nîÖxsgxv8|·±ON(um¸C‚ø“‡ô7¢\r‰Øˆ7}XiƒÂ!nõña#†è#‰e:w\næ¸Ÿˆ£Y©˜•‹8›8İ‹¬e„ì­…4hHQâO·÷;tr'ìRP×È/‡mhøtJ-óÛT¨|µ\"º¢ÊQ°®üû˜iVe;µÏ}vaN˜L=hñ‹i2á‚gÓˆ¸AfX­“öe”*ß˜Ãˆ–-tXDw‡|½–ëlä¥2°&ykW’Y|ÇfC‹é!!i'˜r¾)ÙŒö¨‹š%™™µ,é';€é%~„m;o¶é”xÅ”²ëpØÍTÈ÷å&YƒƒYÙd6ß•…œÑ‹ç›XQ”™a;øË…¹÷+\r42e›0C~/­Z€Ó!xÚ†W›™¦q‰­aøé‹µR\"'ºF§ùj£>´mxUˆÉµâP8;¤qÄpúO>yë.xWZ\\Üòû zIekòá—z”ØY‚à\\+Ò–Mv¡–ĞÏE©ÕFÑÄ}0IX™'éO1öô7ƒªÿÙ·uèAòO†ŸƒŒÓ@Âl½1,¹œB¹!SÓ.o­5ZEöñ6È±/‘‚E&B¥&-EïÂpgç(:írZó5oª8Ò¢ØÁ‚´‘•ÎÍ_kèM+ß²ÔoˆÑù<ZogÅU³¶„ÙÓÊi„\r€Và Ò`Ö•¤Ê[§Zuãğ¡ ŞçàÌ¡…Ê+ÀŒë ÚĞEÏÊ<\n ¨ÀZ	^©n~I\"½²µÇ,fèğûW¨1nÇ‹Ú½§9óP{Cº·çegÑ»1:­kt³NwC{ {Äÿ3ch-~çP<îÅS*)ÆÒ°FÜö5VóUà›· Ó·ôˆèøk ØçªM,J\"¾&Ù#}ÙWÂÏG#8y•µŞA\\3´qºÅKÈŠÔ7Äw¦\"Ä®?m™;¥à	“”¸F2[ÃûÇ3”[ZC T>‚6‚”º²ˆK±ñ âœ=šôrÅ7ë­®^‘h C‰¨Ùw”ì(˜GzR:¤ü§PÆ»L¯‘÷¨ùÛ—™fkZZ‰U®–úêà+—×m†mçË¤Ø‘k¼Ù¿VÕƒ¹uŠ™İ„@¨•Cà;ãÂÏ Êa‰Z5uu	ÏüË®Qû1y/¿ÂùÒÒQ63O,A¿“Ğ›¬Òà™‘Æ 	Qú?A˜zI%zQş„ZSƒµæï—.ÖÇü…¾\0Œ^‡cjÚ)XäO\0Æ ê\r´‰ƒöc“d¢'àŸÒÀmÆ†Œ8x @rG¸ø8·*0ÊëÈv[²LUÓ5yqOj'à¨\\¾>Ûøw‘µI@IÔeök/íÀ¥Púøõ÷>}ù†ÜÎÓ/¨JP\\P`\råãĞ>îì®-cĞ·‰nì†bßÍ=w£uù}LDà	\0t	 š@¦\n`"; break;
		case "th": $compressed = "à\\! ˆMÀ¹@À0tD\0†Â \nX:&\0§€*à\n8Ş\0­	EÃ30‚/\0ZB (^\0µAàK…2\0ª•À&«‰bâ8¸KGàn‚ŒÄà	I”?J\\£)«Šbå.˜®)ˆ\\ò—S§®\"•¼s\0CÙWJ¤¶_6\\+eV¸6r¸JÃ©5kÒá´]ë³8õÄ@%9«9ªæ4·®fv2° #!˜Ğj65˜Æ:ïi\\ (µzÊ³y¾W eÂj‡\0MLrS«‚{q\0¼×§Ú|\\Iq	¾në[­Rã|¸”é¦›©7;ZÁá4	=j„¸´Ş.óùê°Y7Dƒ	ØÊ 7Ä‘¤ìi6LæS˜€èù£€È0xè4\r/èè0ŒOËÚ¶í‘p—²\0@«-±p¢BP¤,ã»JQpXD1’™«jCb¹2ÂÎ±;èó¤…—\$3€¸\$\rü6¹ÃĞ¼J±¶+šçº.º6»”Qó„Ÿ¨1ÚÚå`P¦ö#pÎ¬¢ª²P.åJVİ!ëó\0ğ0@Pª7\roˆî7(ä9\rã”ŸÄ„´ƒ¹¤Z„Ô»±b8¨«+ùq1ña8³0ÌÂ¿¶/\nzL«)ú5''ÅéQêÉ Á Si'qyJæS³{J¬î”é7(‚¾\\1åœ”Ïîm<»Õ…W;CN³*©œ¢ Œƒl«7 Şş>xèpá8ÙãÆ1¿ƒœª3„\r“Aƒæ÷ãLôÚ¯Ä9óˆÈå¼4C(Ì„C@è:˜t…ã¾6-9N#8^2Áxáuİ£È„L@|6Î/|ª3N#l4ãpxŒ!óŠæË,,XíÖë‹y\"mÓ·J©“!r­¦iûÃJÏí£ËR\n4`\\;.”’Ù8Ú²ğ£‚/ iL‹£ÆŞ£2<R[OÌe=#\$Vr=¤²p+Œ#İmiÈ“9PÒ]@ 	œY,Ã‰ÜhFP+šRª+4švÉ3áqI¸%Æ\".	Ü³Y-²sm›Õú<Y6\nÚºó	@\"^Ãê6Yã°Â6£.”»ğÂ¼.B¥1Gq¾\\i°Ò¦”*ÊØ«´›\\·.‘3¶:D>€Ÿ»%Æğñõ|9VÅ©ë‘a%QZ\0Q+5Œ•óº§z:{¦«qcÃËÉRò|¬æµ—®ïêë·ZB7F6?Ğcò´a\rÏˆ\\9*QH \naD&5PRĞÊ+¨ôãÀæ†ÑZ›ÙjÈˆğ©³„WYj QŠ%˜0öàÑ«hb\0€ÊÇŠË<TÅ¢ÂğÂ”ƒ.cÄ˜ï2æ±”z¥{HµQC„È\0˜íÑ	?%l©¢3É;ì®#3byQ»Z8F¡0¸\"¦”áŞˆj…˜„@„êÈë­sè%ÿóŠm\rÑ´ÃŸØãÃ(x@¡¹m2æƒÎ\ni¯±‡Ü(ÍzHHˆAH\$×x;©eáœ3„Ñß¹ö 5*úZ¥8DÌƒ4âğ rG õ6“ŞÃ0f\r‹¢ÆV«ÛWhhÜ…@Ş|˜ğn €:ÇÀêµÖÈft`€6ğÎ•CšğÊa†ÎR¨ › •·¬`ÜP((`¦ZºÙpõ’<‘W’JK<Öî“,p§m r°bh?¬p9LE´•Ä›É¹x8üC\"È^G­z¯uò¾×êÿ`,\r‚Ğ6˜Së*9‡F@ÃA>£Œ}+26JVšÙ£ğò/9Xá\\6\"Kªb.%),pÌµS¨…T@¬¼/±xé\"ÇšvÙÉ·Q¥`&®\$?Xxs]‹¹6‡€àW¨dËÅyĞÕğ¾—âş`;°F“p.a,-†Ç˜÷i	!´8ÚÃ¥&ÑÔWuÎÛÏı}!­,ğà›¦\rxÄÀ¸f„ßÊª‹Lj8…@ªTÈg¼.[¡2@¹CcüG¾Ã‡)ù]ÛxaÕ=hLu±T¦Sü™³=6.T¬Ğs5Ü:³^K§çPŞ#†‰ÁÒi¨ĞæÃ)LïLc-ââä˜û—;.s'o¦\0\0(1\0¦!HOu»ƒ%±¦%§QU*à(!²ÑgÙş>GĞûƒô[zÓAĞş •šµmoøÂ^¨®‰ÒI¨¼í›>Z,ÊncGõo6¶&Òl@ìh7	Àºª“máİCšúC:úÜøW'n³¥BóÃK\\  aL)fàuK†IX[CËª`cˆ…B.ÜUtêDéĞó=Ò\0ä\ní×½Á¬)RØÍnãX,-ı,•Wf‹\nél.ÇØeS’sNÛõ¦³¤¶S™Øã‘;¨È^:•€’ICÉéY4#­`Aı^¡ÅÔ‡5“€m 5db4Øæjã·‡Ÿ¼c2›C¼ÅÃ'³hŸÙ{ÍsEŞÈ¶Bìiœ6y²¸íâÊU*ù\0ÍÁ!Ä³®Qh.dõ`•ócªÌu|×¥÷c°ZÎ[ÊüÖ}\0Sb™¬nã<a ` ×øu¼‚¥à€M¼4×E©6´¾™ÑÁÈ÷'(	;ˆ;³&j¤ˆ²Âàò›–8.î\\!E2Âp \n¡@\"¨@W\"„À‹ÅÛêƒGñ»¡õÀy=‡Ç=Z^˜–sN\n~åñ¦ìœ]ÚôYS±ÖCç\\á´F’ÔÂBòÓ¸“¶kA+.ŸB„¡ºkXCJC§uÒÍa§Kâ0Aş wã\"09G[aB<+ª2é\0“¨Kj”³’¤*YrsÑ®ã¦†Ê’Wk„£°ª2ÔÏZØ¸ï]~w×oÑ\$Ì–Í;^KTºı‰IÆ–\r¾òÔ“b\n#\$'÷>2¶’–.gVæ\"¶ùØ »×€ÄEVš‡¥Ó&y÷ĞzÒÙ3mCÊÅgÄ“şwL!^K£3ğ¦CÓª¾µÒp:ªR~læ\0ëij¦Ôûƒ,şKo>á”;ÈéMÎZÏÅ-Á¨\"ºL'cW¼íQµsÂÍPçæ%ìËÜp	³'~±ĞÍÃ²÷„¦¤…ò¦–qmŒeâfàÈ *|à¨„ÚÂ²B‚~oãÏ¾OæøÏğ&ƒü> Î?iTDFş<'~vo0;©şìDM 1íZ}hÔ\$^(ÃŒ—‡nEíV+†€q\nn÷¢\næl¢ìFïÌÆğ¶ f’²KŞƒàâÀ¨ †	\0@Ò„Ü\r%˜•ÍZ àÀD\n«HÚ‡jÚÌ\"1îàìè†yI¸\0^3ÖD¤šü„Wbvi¤³	\$²&pÄÈ8Éêwx;mîÊÌƒ¶~)Ø~‚æ÷£ŠÉ\röL¾-PøÊZ—ëj€eğí	&+ßˆOìèì(UèœoÑØ0üî¢³	Õ¤E\0	ê\\À@N\0îm£¾Ch+e&÷#”2È}nÿ¦†é1…pÎ…K á\$ÿ	Œ¸0D5¤|pÄôpÉlFø\nàÊEÆß…İgşöÈ‘&°ó1¬DÃ&*3Ìñ x‡ ²†9¢våBäpÉl‚~G#Œö-\\6b\0å‘3„ö9±ÿ©ÒN¯^‹!hhf„ĞèDÂ,9B¶B´k\nZ|)Òñ\r±¦†îb H\$Ç¦…B~-ƒÂ<ÑV¦d;ß\$2b<hy®cî’húezÛ±&rQ\"ã‘#\$P‹/ÄfÒ(îşÿƒÂ82š‹†Èì’ŒÎRäoÃ'Àä1r/íHÂí¯Çe1Ò|Fç€säàêâ³ È¬vd(oåC„h+fj{’Rìˆòòøb\0PQô”GÕ/æµrï0¨\$|E1\0ˆ.Øi(8zSBF‹)òµ32ÂÙH|ıïÊ.oÑJIÎN*+Âæ\\æã„àS+jŠë¯3,Ğ‡±K\$ªe*ö‚ó(.s\r+/6ò²w†M`‡òÊï“ŒÊ¦ËÎÅ7ƒšıS\0hTë9¨Ç8Ó99	C2s\\9¤Ê3Gˆy¢»'’ºi0/¥h?\"_fà;pã7•æˆï Id.œàPD¤U‘HINå7s;@•:TÊÙ;05Æ«BÒ\$ô@Non&ÃBf±\$“”ê#Q;áN8§ë&±§ª’Hÿs–È,sLDG8{'fÉ“BÖ3<ˆí•;¯ò†jàp{íwğ<PÓH\"ş3IFPTíÄ÷E¿sÛc¾o^ooÒÿ”lyÔ=Î×	@Î€’«L4jÿÇ¶‡t†äK)\"¦‘°4iÑG&†o4xûÆşi´*h\$DıhSPˆWA“À;µ?Nâ7óEu\0;´LlT±R.ìè14ˆîrpĞxx±ˆ Ñ˜ìÄE\$Ó§3µ<‹\"ÙT4ÏA\$M:µ:ô3Q±SwUQæ§[R¨­RôTîÓ¬hÕıÉÙSõVÛEôNkuRk5‹U“<\$Šeô|êŒÆ:#h+¬Ì‚‰Xäjhòäg\r9CÃÈ—[5rR¦˜ø`¤]'uI7Um]©Á]õ´5PÓ±V‹Wà^Š<­áÒ£9tSC•7²)XUöıÀ@@Ü¿C2UõVv>€Ş[\0È¥êŠ°Œ<¶˜¶\$€vEæN5c‡U%’9¤¹ôpÛ‚ïF	4úë€©ĞA`ssAumg,aguïL3}a–-2ögE‘hp‹*URV‰ah3a¨8äİcÒ¥jÖ•1´DG‡Y§W5£T±9	R3<±ÓPğÿjBó+\0Œ¡	%Sm3	¨ƒVÖJğ6èQ6ÕÕgm¢°	Ä\ro=3è©ÒÎ\nnÎbz®—æPˆç\\E¡k>Ä”—oW2HB‡ ’_Ñè ²6fmÈyG8²r¯[‘Ïsä+1òa<rG^3ø9²Ã?î[oI\nŒ„š`Øm@\r Æ\rm‰1S8Ôí#RĞÛ§òİ€ÚÇÆ‰²\n ¨ÀZä ÀÈ[q=ƒ?5\\;b»óˆ§WDìmY5®Œ½—&äN})’¼5f¾@	 ß{àÌ(æ¦^R§ 9òô‰.gy…EÁnãhK4µ“UN¸S·’9ÈÒ¨¶XrV£nçfN½2b¬+\0˜\ríÈcê[äxI{ä¬p'²gÎ·-,ÆÌçnw–ÎÿC›\"tÍ.·èèuûa\nh	§dD©Èø—ëi˜l}s×HLg!*˜“;x?6Ô‹wØ¥‡ÔK^6}_À¨£ú=ƒÜÆ`Êaˆà\n<XÏ²±hµ(®èF›P3!]§DÂ+¸³v®v“?Ks™Š³œÿ7ÿ5¯QI({13ˆÿ‹5ƒ„|®qÓDIÏäh’ç‚øéFKn|f\nÅ¬ ê\r°\rON…ÄêR¦şBˆ””Ö^'¡=Rg©#„N§‘ïÕÌ‡Ç©,®l£¶OC¦çE_1¥r3–p§±5Ä8ƒ³¸ÊNúF/=˜Î­1\"JËà\ríúãê@¯yš¥&+¦kU†è½«˜’vgBã¶rÂ‚\0	\0t	 š@¦\n`"; break;
		case "tr": $compressed = "E6šMÂ	Îi=ÁBQpÌÌ 9‚ˆ†ó™äÂ 3°ÖÆã!”äi6`'“yÈ\\\nb,P!Ú= 2ÀÌ‘H°€Äo<N‡XƒbnŸ§Â)Ì…'‰ÅbæÓ)ØÇ:GX‰ùœ@\nFC1 Ôl7ASv*|%4š F`(¨a1\râ	!®Ã^¦2Q×|%˜O3ã¥Ğßv§‡K…Ês¼ŒfSd†˜kXjyaäÊt5ÁÏXlFó:´Ú‰i–£x½²Æ\\õFša6ˆ3ú¬²]7›F	¸Óº¿™AE=é”É 4É\\¹KªK:åL&àQTÜk7Îğ8ñÊK')šNgI,ên:Óõ]“gn|cŸŠ7Ô+%áŞ1>Åˆ#(úÊÄ¦.8Ğ0Šü ŒˆÜ*#xÊ9„\n9£€à’°£ÆÉh0Ü3„¸É.æ×ãHè4\rê‚.8FC˜î’Œ`@\"ã@ä2ŒÁèD4ƒ à9‡Ax^;ÊpÃ\n Hğ\\’ŒázbÇ‘L~9xD¢‡ÃjJ× C2J6ÂK‚ã|“Àú 2³`P²0	óX²³Ö@èĞäÈ¯jğÛ*cJÊ:A+sœ'Š’©IÒ¢Ì\rlî¢Êbúºa(È›0C UUƒËR%ë“¸*/˜²’£h'ƒ|áJ3å–.˜¬şuNíÒı)Ï…8#8#Z’6OãUF²‰•c PĞÍãº#Èë– ­—(‚=^.à˜4 -HšÏ¥‚0¥‚ÊËRÍË¸®lc‡8oÈ¦(‰•„ PÃ>Ô-;w<¸ø<\n P”Õ\$OÒ™ŠO\$Vu£‘ä VO”„¨ëI¶TdË”á°2R•¶TBRÍÀ\"×…\"IòÊş(z6‘®YˆÙ™ä™¨—OYÃò\"@Tg>ˆS×(¹ˆäİ\rÛ‰\$»[t<=ƒràjB3¹NeTRÈøĞÖ„¤á{=ObBé¥-Ø4Àã-„Ï£àPØ::f¦£ãhÉ¥éö{I?,Ÿ\r|àË0á\0Ì0O®bUìZÍÎ\r´.¦ Ë¥\0Ÿ\rœÙbUõXˆâ\r5€á\"ï`Öáìèì.<´I=šöP&—S=pæà¾¨â|“Â´05¾ß|©§İ2Í¥fv5uËt£íˆHÒD•&IÒ„¤•ºVK\\“%Ä¼y3m*Pà¦`|@±,®è8'kÅáeQ‚ğ7'Dìôƒ²jå\rœrfïCªB\r…à:bbSÌ#%‘ÁGÒøK:*™#œbÅâGw0œ2P\\l>å\rZ°Â†º	c÷)%%¤Ô”RšUJïŠ‡\$º—Ã+p&-Î&pæ›)L&qøAà|¨+ \rĞê’:¡Ô‰º4²&–Â|ï‰3`d‰!&rhCk‹3¨6r`GŞÉ®zÂ}¦²EÃ|!7,\\S»Ç|ÑÖƒÛy`4C’§ù!2m’pª½rÈ4„I\r¼¢B…ÈP	@äKu(áÈÎ\0 ¢‚—ğºRz)…8¨2 ØøÊ¦qøX—ò‡ûİ–G†P*‡ÒEæÃİ‚Ä\\„Æ°H	çq­9cG\"\$Q6\$:`p¨òlC„\\9£Ã&fH7M¸8Â’\0 ëœ1·òÒa65ÔM0êŒÉ8GZ¤zwL0†ÂF˜Á¨ÏœŒÀr\r¦,2¼³øaÑ'gó˜—ËvĞêc®^¤Ag3ÔZYdóß2BâÙTÑ˜¬rƒ\rÌI<p¡–<¾²<Â#\$múF£¢ŒÎ1£Dñf‡—WÕ\n<YÍÓˆwR‹Ày/`(ğ¦\ri É\ns½Z½›éwED‹¶SKë‘#ÍápêC9ïjGğâCe÷–MPÁR^F§%Éµq®sÉ³°ö\"LÊU>®Bğä™éºÍú >=5Ğk#\n.Bv½“„ú-m)0ş7ÅBªÏRe8+ğœ¨P*Y†rHyGÓ@@*¢›dÑÅÎĞÊ\nnuß¡0\"İ[¯vC5Û‰8Â—q¯5èVŠÙ»ãõe\nc”·úWö¦Î]Bç\$é˜¢9(N	Ã—§(,‡[PÌeÉA¥Á`pÕöbŠòÇQ‹ O½œMåA¯¹ÏÅmg”6¾µ˜æ@Œå™3GÀ¦É\"-Cih¤K(k/j \"GR‡\"‹´6´„ÙeÔ¼¥²øõra“ì™Ã39\\, U;¾Î±p›ËçnS\$¹‹•¨+U¼¿‰É©»48å¨ûëŒÄA5òØBp ê*\r«í-²nQªY„09KC›‡ÒÄumLW*\ràym8\"ìĞÍ‘ï-ráˆÙòÙÊ\n‹óíi„ÍäJ'áBN¶²+k¤Ø\n(+åv™\rÃ|	çÖK³g\"vÓ ×Iª;6 Aa àEã³%M²‡9!ºtÈ¦—ª/À…ª°^UÕƒ0ş™õA}^èU]A:ã75.]™Í\$û+ª´¯½·±0¦}Ådw>ŞßrQìoáOÀ3—w¼¢pcYÂx\\{–øÊ¶ñ…©¬Ó3­‰˜RêIÕƒÛâRNJÚ\rÿ®¸(Í[Óƒ¡ıõÍ8©æîs“ÔÉyÇ:g3“Ô5ğœaI&µoK>¤Eú£ôéñèâ²¹{­é;y€;†PÄDyñ¢êdš#&·Û`¿C¸œ„aµ+5Y¥E«Â;?ĞyŒÅÊCÈ4í¶ .·Y(Ø7\r‰€¾„@èÀÒIîï\n2pÉ™^åõ	„lºÇËf¶å1 Îº~ñÊIêñëUv0£Ó5®~SRÁYK1g=Õ£R–™ md+`iÀF¡^S/ä(İ5ó·É“wúğ?ô¾CŠTHJt¸x@Jä¼¥jpfuX:•0ëz¡ÿfø×ù^IZÑ?°G¨€úÆJûÏ@ÿèïd;(ŠÆlPÇb†û[\0DàÂ‚%tHp\rş*ntã…`ç­îPnBá¯J-Eãsá?!bà°:»Î&J·è&;m\\aOP˜/@Õk€Õ­^ÿËÈäq+‡b„/hÔ-Fp	ğÔÆ\$VÅPE­D(iÎ†Èƒ‚:jDà×D…	\$‘	g¸?Ô9æ€&Ã²=ğ¯\n	\$Ä>Ç¨÷Ğ¢™b0¨ú×BÕdÕîê¾t.F\\ešú­RR«öı\rbQ- Ñ°xõÏ@Ñí\"Q°kñĞ‡\rp‘ÊpÚÍ–0Qä‘21Å~Ù‘/H&U†[±FÁ°ĞÏÃƒæÎpNÆ-äà‘#p\"îXv(œ@ĞG¬aÂO¨ì¼&Y¯AQW\0ÑJ[¾\"¤ä>OÂ4–\"D„ÑˆÔ˜[C¢ı\"WQ¦=ƒs§O@Ébğ‚`é\nmlX‘6õMoŒÍo±•E(1à-g‘ÄQn;‘:>m¢‰oêõ/02	í ò×h˜jl}®ê?‡{«ıDı @O22kæãe’İ£2cr”R,©îwîx5†F2Ç\$î4ÍOÌ,?Å‚Ï°*UMò[«0U%Vä¹În>òpZäo@Ø‡š¼\$#.}c8 c`%ä.\0†r€Øe/åœ­®º\0¨ÀZ\$ƒël\nÉ¨a'ï&¶äô·)+\$(-éØ8òåj.ln&\n¼\"R¦h&ql’‘à¢òÉmQ)±®õ²Še0cfU‚¦›G<}\"†|’+Í\\\"çVu¢/\n+V91¤µ‰on–0GVkÑ^W,”@êO¬ë	¢È’‹%2û	¬2Î17j^å’h^²e6ª_ğ_8Í` k²\"AâB”ÿ!m(.Pš7ê·©Q”İP¢²€	©2\$¯äOL\n6OÚæ*ü:üSÎjoÜ1 Ş‚f8ô.o…–|‘lWààR5ÀŠ5',#@ôùÃ”¼.‚ù…×@3äig&10ĞxĞ¢ÿ(s7£ò-ì4V“†?ÀŞk\0î#ãØRÀÖÅğqsËÇä^-Ğ§à"; break;
		case "uk": $compressed = "ĞI4‚É ¿h-`­ì&ÑKÁBQpÌÌ 9‚š	Ørñ ¾h-š¸-}[´¹Zõ¢‚•H`Rø¢„˜®dbèÒrbºh d±éZí¢Œ†Gà‹Hü¢ƒ Í\rõMs6@Se+ÈƒE6œJçTd€Jsh\$g\$æG†­fÉj> ”CˆÈf4†ãÌj¾¯SdRêBû\rh¡åSEÕ6\rVG!TI´ÂV±‘ÌĞÔ{Z‚L•¬éòÊ”i%QÏB×ØÜvUXh£ÚÊZk€Àé7*¦M)4â/ñ55”CBµh¥à´¹æ	 †È ÒHT6\\›hîœt¾vc ’lüV¾–ƒ¡Y…j¦ˆ×¶‰øÔ®pNUf@¦;I“fù«\r:bÕib’ï¾¦ƒÜü’jˆ iš%l»ôh%.Ê\n§Á°{à™;¨y×\$­CC Ië,•#DôÄ–\r£5·ĞŠX?ŠjªĞ²¥‚ÖP¦pº`Í¶ëJb”¸D¢b†¶d*5\"=è[ŞL‚²ÙÈÍZ\rèÑ>É¿Î©2\\“JŒ¤hqÁ \\¶“V^íÌ0ı.®„.ºÀP‚2\r£HÜ2K‚ş9Å¢^åªŠy¢J:ŒD»—ôªĞ%rc¨–¦d-6‡ñìk2¨ÄxXƒ@4C(Ì„C@è:˜t…ã½|4%\rDÃxä3…ã(Üæ9ö0È„K8}·Ê1h‘[œ—'B²¢/¢ã|à\$¥Œi\rÍˆ„Ä¦È0ã'6\nóV T‘’¸ßM°#eº­ÑiàjLXÃtWrš4k\0ªÉBb„K—š@„J¨R˜‘D°`J2Tk^äùLçe™F%•_­ñe,)©#°hH(ÑD’—ùÂ@;³İşK#D„>˜hwºfÉ.8Õİl¨öâï70j0jñ65^Ó»,ÈËÒ|LªE\nÜ¬Æ¯š4R5hjsúL#lÍšD_hÕİÑÄ`Zİ¡2œµGÁ2ûhÃ¦Ê•Íˆ~-4\$Ië&„\0¤J¶ó!J.8ë¦ù¿!zŠëz¥ÜnÌï¼Ìş&ñB˜¢&kè:fA#Nls9m¢S!Ğ“é€Ò8'~šæpâŸOáÓvoùø„.B£¸Bš ×³ÈÍŞé)ü²FÁN^Î+(ÌT‡„çº(Ñâ°÷ÕûÙm±i %^#’qo\0h=ò`ø^*ø6' Ñ1Wš›ïzÁ!<Ú»ÛpD\r!Ì0† ØK0s,¨;á#Q%‡€è²ƒ˜i\rá¸9šSrâ«ËéĞ‹PÓ‘â2&L@¸¦8ëßòN~dèÊ¥ä4™i§A~´v² rm'\\›ÃÔæCaV6‡IP9BhIŞ‹y± ¸´TzSs‘)É”X¡WÊÊx. ‰ş”×ËÒ;CÇ4²Ä6B‡3(|¦]—`\\^de7q—F©(pãq1\nˆºÇÒAPûÜ=@XçZt+ÚA½9„+-‘d”ô°é\$JLfi’U§FÂA£z¡RyGiE\$å\${”ò~Tœ‰Øä)’òÆE=É-EìaB® ¢”¦éé%É.ƒŠ.YÇ7E\0»œQ|ªÆˆ‰›Ñ5â©u&r\nˆŒ\n\rB¨päªİô£GrEÌµX«•‚²VŠÙ\\+¥x¯ƒºÀXSùb¬u’²Ã\$3!Ò,°DµÈ1q.¤€].…ÔÏº:Í…â0H†TÉ\"5Bâ2€G÷\nA)ŒHÁÆœât£‹¢ıŸGLºÎT¤K,¬š­Ÿ¥V/JY]<´\r:\$cîWİò§g4­»AÕz±VjÕ[«•v¯UúÁŸ«c,…”²at0£«Qk\rLBŠ)Îó5#Ò`|A\$g7p†xO)>JLŸ|q|7˜ĞşRé¾'I@ªR„ıT‘ës‚YM©&‰Ká9ä<KN’ºx›„ ²2&—cX|Ê¨9EĞ¡*’lT‘][¨zÇLbš£ªH²œ†ÁóN2¥9È ¶:ìH£ë[#\n«Ô²…Tµ£s9b^’İ6hŒÆ£|Îã\n (Û±^îÒ1€€pSafõ³’qT¡©!£ÖÚUJ(â ò¦kê5[ï4œå¨@\n1-§#¹cRmTTıë…K\râgh”_]RPÌ´(ö’é!ICoãÉs!â¹R\\Ø¹¤ÙJLµ°Hç„ë\$¥^w\"K:T4}6fIÑ‡™>.¶P«„0¦‚1æ¤^R‘ÈÌ]'	]¨E-T‚m<‹’4C“¤È_¦ìPÅ€'õ­©rA\rêf¥«æ¡ßå.ï°•™:¬ÚR‰WMáí: ç-¥¹–Š<!-b½âXi¶pš(TÂM!7îB±á)%qAN9ø°çû½(6U-ãF#Ÿ#L_O:vÆÍ.¦ñ!RÖ§;–,SÊN€€(ğ¦<²•5}PtL~×%P‚;hÑ¡¢Ûp\"¡ãØqb¡^:—l‡/ÂJeáN;t© …Şİ&Ö=d“:–%òl]\nÖR7Z•xºX‹{ºw’½ªPŒƒµ.oâ¶Wúp‰Õ›Ê•>˜d5r%Ùc`R×”Â×àö°êë°ĞéçîG2ó4ÜŒ€r'<4P›æ×””Ô—õJ/9F¾eeíÎ*Îb°7Òr÷@IZÚ1)ÍÒŞ—ÜK}ªô¥¸Ùç A¬rú|ıI•š5!\$¢İ´ÍÆ&Ûº—\nŒ¬»M]%BZÓJz¢½Ù¸ñ-nb=Ù^êNÂ}.°şØôû6^ræ³Ìd4¹QfÏ]çq?½Ô”Eå§¿ÉbÎ·\n¥¼w–óµm¸Éa¾»,ñm/d©Â„ç_ õcAH7³¸İVã­u\r~í£ƒ¸ä\$€­•ÑÒ—vD.±Ã´gµü·-*s‡¢çûç\rõy7Ä¥\0ª2ÁÛÊ©7}—ğÃvï;·s¯Ü†”ÇKQ|£Õ_;÷@\r‚aşëDlè\nm\n(ïâ`¨’·æœ8O¸u«t´lâÆ*2&|RòJ§–sé¸O‹èÜª®cÅò²KÂÒIhvg:8\$ÜÅ¬L^&-Œº\\ˆ>Éd*“ÅÊ4åüÇX¨êåâZ‰è\$Èz	èKkş5ƒ\$‡À‚\n€¨ †	ÓG|ğĞ:¾Eê‘ª„¶,GĞ>H\$*w­8^FZâô–EşaEèf#qˆsÅõ\r,A­6wîîµ¦¶)\"Nó\"œâƒ²Ğgš“²‘0Îq\nH–èÉ	¢_\"›pûËfjĞòcJk¢OOÌÀ©Ğ’Ó‘geA«qñ#ğõì9ĞÖ‡,Éw‡~ÂÅ4`ğå'†Z¥‘1ÑlÑ8ãPğ#ép˜Ó®\$±_koQ>Ã°Í‘VfÑ2—0é«sl:ì±†6‡0Û6ãğş7Ú—\rÜ%Íà|mî‹áw\rÌ\"b0]QÆ,qXÃºÃl>¨äJ®m†Ş-ÁvÜ'FJÄf(QèªèŸï2*Dø,1È>£VcäH­’ÈLôGâŒøGX^¯\0µä&f¬ä‡2'ü4j¬\\KNF*^¸ªä&D@îŞDûÈ.êgNî˜,8ÕĞŞK’^ápö£³&‘(Xµ#B,b#·\n˜ı/‚b²o(DRÒ²fgÒ4D’êcÇ°D¤…*€«Q*ƒn¸ˆ»‚TÃÎäç\"ƒÔã%ş*\r¦Nf´2lFÂw¢<‰Áşî>m¤:¢ ùD,â\$øeì¨Îz#CLø‰&['üçËC+äÊ%3,’ŞkPoØqŞBçøÁ2—*Ç)ïĞ*e2{êtÇ¼°Bd(Ÿ@Qt)r.m,¤`IE:ñé(``òeàIstlÄ£3ç|ë£|sOmsƒ7/8ˆã7‚•,»4NâzSûÓ£+ÍŸ'2é\0¤Ğ5r«ÍhÕï1\r.™hUq|cd‹8Ï‹43€’üA3Êµ“Âíi—,’*Ù=Æeèx8S¹4ù“í<„“?SÏ?a=k=°ıIË“¥;±E8âPâp2®.23Œ‘“'t;ğ§ÌàÀÔIC4'QyE*CCôYŒ3ó¨|SEdçlL0æk8Gp,)­ì(FÄc+S/T)à<Lä‰Ë8¸”~b”‹HIHŒ~Cp`şº¤qÏJ´‹Ê\0thn7«2ÍŠr¾ğª¿LõKlH+¢„«Ç>n#EQFôE¤ÂüÚ\$^ˆ(ıFø{ç¸}Pöî¥ñ#â5ƒFLòöü¦>S½4ORËF})Ô9R¦S´GkÕFh\n|\nŞBKSÏ¢ºÕ=DÕAEÓ1r•SÓçA1§VğmVUJªÒÃ;ç™ğHU~òõ‡XìPP5kD±*óZhBd·‚>~+ha'{¦­2æ¤êcòí?Óı=’×D²š›³E\\¯!T\0ñ^Ô7VÕİ=UàëTuXS«T&{&æâx…ÌbÕşyÕ±5ÏBr×YUV_	`fçV6|V\\Ô\$_Æ\0góH¾Eäª¤TŞs¡E\n¯]O§VµQd¬-µWU3T6YU¶OFM,U{`òb¥eÏU/	bĞ-dÄ®v¦F=WøK6„ÎçvPï‰i‰êq…*tCR¯T6©.±?§_”yT!J>åºû°è~61_1íQ³\$˜ÑÃ+±\0G—>òƒ[²\0[±Gt\"¥)ooS{	•½oÑŸpò-'pv³o8pÓ·m«.Ï†pgK]Nä[r†1¦1Ñ½o«p\$ı<vÅkSér”>¹q×SF1!B´K‚v÷^–·u‘UBã~@†‹ Øp¹ËH¿l;lQJ«˜·ua6á7B-bÚßò 4†jôºì4Z Q¾Ä€ª\n€Œ qIs`Tu9,(_æ˜Â4PĞï×9Â<7áPsë~‘MCK}×õ5Qqê&Ij|.împ\nv«˜¶\r;ÒJ:AdO#t%(rD ED\"Ç–ùĞ!—âŠ9,Ëå@v‰â×e`-.™ó,Á2yñÑLÀÄ°Oxi3.òò@Õ“nRÆ\"ãäP²éô–Èj£<%òµòÓHµæ1”Ìe*8Ÿ8ÓY,DıX‰Ô±SZ¾W©|5{‹²Ñ‹ó¤8Ui]‹I(ò·ŠøÒ¹c”>p÷\0øh´¸›ŒãÄ?oÁTb˜yaP_xñ,L¸RÏPÇ¬®Å%-¡rù\r–×t~hAÙàoÛL\$ş £\\ÿâÒ&*-/1&Î&n\nÀÂ`ê ÚéXæ¾¯·%.ĞiPæñ²+s‡–LixP{ƒæ€ÌD4í=\$j­\$¹qTë.³ó¦¥ÖÒ:.Øîs Mù‹‹RH#XÃV8<1é§uÂêàó+N,&/ÎNİI• óS®í¹#:-#\nÁ¬^ReLÆCH"; break;
		case "vi": $compressed = "Bp®”&á†³‚š *ó(J.™„0Q,ĞÃZŒâ¤)vƒ@Tf™\nípj£pº*ÃV˜ÍÃC`á]¦ÌrY<•#\$b\$L2–€@%9¥ÅIÄô×ŒÆÎ“„œ§4Ë…€¡€Äd3\rFÃqÀät9N1 QŠE3Ú¡±hÄj[—J;±ºŠo—ç\nÓ(©Ubµ´da¬®ÆIÂ¾Ri¦Då\0\0A)÷XŞ8@q:g!ÏC½_#yÃÌ¸™6:‚¶ëÑÚ‹Ì.—òŠšíK;×.ğ›­Àƒ}FÊÍ¼S06ÂÁ½†¡Œ÷\\İÅv¯ëàÄN5°ªn5›çx!”är7œ¥ÄŠlÒÔ¶	®øò„§;• ˆÒlœ©# \\À	Z:\nzT·\"ŒP¢iÁ>õ²¬»2„ˆA¯¨QtV\0PÌ<áƒÅ0’P6§Ì(Á‰ Î4Œ#p Œƒk¶û=cxÊ9³c|(9£€àüÂƒÆ1Éc›¶¨c Â:#Â9Œ¡\0î4££xë*„„Æ9ïÈÈâ†4C(Ì„C@è:˜t…ã½2,ı?#8^2Á|Ü9Î^)ğÚüÌ®ØÌü²øÒ7Áà^0‡Ép°2ºoc,6F;r\$V( Æ€”¬ÒĞa—Hkå(jxë˜ed…_°‹ÊŞ3°ÌC+Œ#İ-¢(È¼# ÊaH!Ç#£t7 %Óo¢åÒh˜&L4h©'ŒdHÇ+`‰=#¨Ù\nÃØ:Œ UVÅnúv™'Jv7]ì2pJ®ÈñGŠ–+¦5¸%û½°¥n]•7™†Q7,tW¥Ã«ÇéZ€”ò^œi\$TİÍ2H;F–R÷!	\n(Ü™¨7­˜(¦S»Å·dÎ„Ù[ù46)º8@)Š\"`<U€PØÜY£¤—dªH!Šb&ÄÃíW’i•XÂ©ˆŞ‰ïU\r¿\\WC•xî¦5EÛXMJ<1TY\nÅPÆ:×úPïíŠØ1ÃpéwÕÑLÜˆ‚Ç²ˆƒHç0“*?!!ÚvÒgsÛŒ£Å´7KU æ—cG8]’÷=~H¼/Aå¥Ù:’\nf9àÃ@\0”a\0Û0×\"»j»2Ğ3PAhÌ£xÌ3VpÊ¥ñCq4@xÅ©\$:RHSß1|4í54vtÖÙ€nN„\\Q 4xr‰Jåoä£­pàºP n2¡„—\"Q†øSIßBáRwğCZLSáÈT´]’\"F~ÁÉ:7ŒC\"HNÀ<'¤øŸ”‚PŠ;¨…ÁrQêD2*ED©¸>Š¡¹Q¥Lª	pP\r¥A@tÎÑM'eÄCª œ'mbEw™·,JáX%Á5	!D,.Ã\"|(¨_¡FnKSªwO)í>§õ Ô*‡Q0èûEä£”‚xJAâ©0Áñ‡=DP“(Ìbø>}ê™f>\$_@‹hï\"¦ÔTHÚ\"ieQ’Õš™R°t—©˜4†ÀØ^S(p>ĞÌ6†U®C2Ú†‰A+¥Ì¾‰HoGä3‘Òh™]œÍ ©æ †é„¿9‹4…Ü„Aùj8 --T¶A™óvBİ\n\0	£3DxK¹pøI“)!R?`Ç0‹°toáÀ9“´íC<ÎM©Q&;\$š•f1ó™Å.9ˆk`ø>Êu&0æ›’À ;s~™ààšÓjoN!Ék‡xÇ)’0gOÓ\"š5ò™#ÜmcàA2ĞPÛ€RĞ4Ç+‘´+å34Ğ«¯áŒ¡Hòåİº·LÄGS¢bAp ™ğ‡d.Åz¬Ö7Ôä+¸¢%¨ \"DÈ¸©8‡à»OuW*e_ 5ˆ’à’DƒËî‘Ë°ÜµÈíO&¡%¡@Ì~œ9Qs}[ÀŞ™(\nLMÔd—DB¬É!/d•À^xS\n,ïÁ’s\"-We¥í‹®ÂfGk¹:'„¡ünNÃ¤u­Jæ\",V¼\n#]·K˜£­Ò\\ÿU±x‚ä€«ËƒŠOméAåØ#J\0sêÂ+%=¿ºD8r]E·V5¬ÊZMqHÓæ\\Åµe¾(NbÉ%jrí>á\0Aú%Mª¹àĞN™Éz•¸aYaìF¤#t¡ÂE¯\$ÔÔÂ	|\\‚]Ô@ğòËY—EsÜ2âBeTyd‰²…Ø‹Âïéäì[“[‹s\\­ÿ\$=‡´DŞî1åYÃ,g¼˜Î.-&„©|£&tW”^Zå?¨Áxrw:”…¼‹ùëÈ„qgÒ(\n)ÓÔÏâWÆ(ğ);tK0%ÜâitHk­tŒZÀŸÓßKçÙ +\0õFĞeæ-a›2.±´kCY\$œ—ÄN9éX]ëiá®OË*¨H\\ítrñy‰Àğ43à¥Ï‹~\\«7Næ‡´ˆºÎ&Š7áÉ`Û%“Ş	{tÔ-ˆN7\n Í€()&ª ’õÒò©d™![dcn[oÇ–À á†„`A\nP „0‘õ\"K}éæÎ¥¤•)Y!\rqæ@KiZÕÆúqk\0^rL2IfWCü.Å‰;¼;Ó)‘¤è[É™5&îœ»4“„A8ï##YT€‹3^¬•|Ÿ”ÄEjIw Ì“5èÙB¼\"íH™b¢ş*V»i#!CğÈ£}½Å6<c­hÈ]n\$P²L7Óò†R»a¶ÚïûI¬\\e|b…â´S;Æ9Ã}ï¾‘ä\r­ì`œ\n²ìSğ‰xâ7NëŞx¼šç	›¢S‘[¨0ÊÈ(e]¹2÷±#n0—bĞŠ³…Xtõ‘¶·†Ú¬M–Oj™KÕ^|Üëum»ßk,Pk¸£»n’g%–¯ò8D¤æ–ƒ*‹£\"úÙG[Ï¸Ö½2œ‡à”€~£#VÑŒØb[?H¢ —T.Ö+†àÿêˆ˜)v¢ûŠûaò­|×¾¯æo”tÃ6Íœ×8Â\\:®FØjÆz\r%¼şä„ÉÎà÷l¦;ïf|/†€ˆÊÉB¼l¸Ë0Nø(ænì°nB\\í<\\îLaMÜHäé‡ĞmÈ:ÄÎH%Â,CË°:İ>õcŒƒO/’9_GòÁ\r›	Îá\0êùfü–&<Á\"'kğcë‚b&m¬—à*« êNÒ°«¬cÂ=ãrZÃÊ-,¿Rßd&Œ@!*–ÎÖ\$¢ª¼b³	p¬ÙÏpæ°ùã®p&øÃğZm¥ÎÙ#§…Ä\"ÍZË½	ğ£HHÕ…ˆXÏu	{Í]0³\n‡@)l¶s>hë¼õÂõGLŞEkq-¦ğ^0Úk°W£“ÑŒØê]æãmôt{­èÖ!^BlãrÜc„GeÒ!¨øâ¬,\rmº:%zN€ÈrEv‘u	0pMd„ü|?à~Ñßqìİè_ğ|êcn‘ü€\$­rsæÿ ¢oî(ãg¼ÿÏ´l¤úÂö9ÃW±y\r,|\\°NóÂ‡#M±	NL*ñ¥\$T C5ñÚòN<%h%¥šG§şHÎ¶äÑxANfA¬Là;%Ry&‹æ…ÑÛ\$†ÿ(g¬ßÒ€{èÛ\n£Àh¨ÎĞÎÊğÆep%Pq£X0Í0r¸HN:˜CªÌ‹¼iø|#ØñÒŒ;ú±\$Z)rØ!È<EJØøƒb\\	ÄT¢bĞ˜ëğcˆĞĞ\$~ÿò\$èr=,pˆ/OŞ4§”Bâ\n ¨ÀZNö\nÑ&ÀC3\n‰Ö½l.U„@Şì‡¤ƒ*ÁR<{ì6exIòTm‹Ù/qèGÏ² Ï¸6…U5Ok5e_5¤JH#f\\¤Z©çFÈ2j&¦Ä±É¨ÜÉíÛÂU*iæîwò;b7ñFvrŞiÀàÉlÒqRû‘W<ÑE\"|\$ƒpø¯r¿…ì\\bÑŠ8F_5‹r-ò”\$ùä4Clï¨7“„=\r+*« æk¥ó[BiMB¦h`ğêÖğ!.s“@©æÈ-t–tYìÊã(Arb‹Ğş\"¬ØÀ–«ªœC\nôMxã\nÑÀ'ÄÖg”q¤P\$ªøú,9U#F”¬wHÂ('œ3Ö±@\ràì>Àî¢¥´Üéö¬Ï8{Í\0003®O'Ä6 ğÉÀÓ”xŞj½I‘\0 "; break;
		case "zh": $compressed = "æA*ês•\\šr¤îõâ|%ÌÂ:\$\nr.®„ö2Šr/d²È»[8Ğ S™8€r©!T¡\\¸s¦’I4¢b§r¬ñ•Ğ€Js!J¥“É:Ú2r«STâ¢”\n†Ìh5\rÇSRº9QÉ÷*-Y(eÈ—B†­+²¯Î…òFZI9PªYj^F•X9‘ªê¼Pæ¸ÜÜÉÔ¥2s&Ö’Eƒ¡~™Œª®·yc‘~¨¦#}K•r¶s®Ôûkõ|¿iµ-rÙÍ€Á)c(¸ÊC«İ¦#*ÛJ!A–R\nõk¡P€Œ/Wît¢¢ZœU9ÓêWJQ3ÓWÕÜë5ŞÆ.¨\"”.TÏ{¹D-á(ÛJ½s”\nZÄ1H)tI¬¤Évr—¤«s„	ÏAp‚2\r£HÜ2GIvL&Å\"s…| š•ÅùÒK•Ì‚äN'+ı\0BIÑÍ1g,†àÂ\rÊ3¡Ğ:ƒ€æáxï'…Ã1\rCpŞ9áxÊ7ã€Â9c¼®2á:e1ÌA§ANš³çI…ã|GI\0DœÄYS±,ZZLÇ9H]6\$™ÌO\\ZJ3qr“eõR+²ZK)v]P+¤V”Ç)\"E!ã @¨’çA–¤A.²–0Y<·œÅ™Q9UAU¤QPr”DôäGÏ0üBräó=Ï¥JÒC—´òMÒd–’áÎZHÁv]œÄ\"†^‘§9zW%¤s]Y²¡x:DaJ”Ù—‡5	CL±!X–ËM„r¤âÒB•\rÌD•mı)Š\"eLnIœ¥ã°İ54½!PÇ0>D\\œÅCæ^Y‰7OTV;dd5SGAM2l«.ş—€rëF]˜4p›iP,uOSäü²çoºŒ9„1K!%~¬£ë:Ş¯¬‘%IÊX’ª½Xs•…22YiUc\nR¥ÄùÌK–Ñ]óXÕ´ÑÈ]Œö^D`º!A„´¡}\\#`è9%¨	ÎS=¥Ñ¿ÏT)¢Á0\\˜–'Aiº‹“Ïé‰5:eºUV\$1ÒI-„9#e~Ò¼kXOÂp^ŠÆ„ùĞXXC¶îÂğÌ69u…yntĞL’*&µì—ed} HR\$\$IRd(J^t«+Ë2Ø^2\rãpÂ:\r?ŒÊ1LÁX_!„AĞ\"Å‰<\"„Z‘:(ˆ`‰‰Õ;‘x&™¨âÉÔ7W¢-İÁœx(T«‘”ŒĞå‚pTN	ò”÷Ò\nCH©\$¤´š“ÃºQJo=+%„´—(x‰l9¿T¸™ƒšhzL=³6ˆ›\$eÌJ“fÌ+„æàr‘\n9D`–mBüBÀ4\$ñİ\$0@W‰ÔH‰‰¢s	1ÂáAReUŞ¬>ò¸çbà„ˆÂr! ­@°mÏ»f)Ë¨Œ1¦EDFÎƒ‘q’D¨?‚\0 ƒdˆ®\$GÆPÿJ<	ƒ¤hÁ\"pg„ğæÂ•X‹Î\"YA”â¬WQ(Ìi†Fâ\"@ S@Kã0qıôrË™Å4%™P\nxëü¶äqj‰‰j+Åâ¾ˆ¬W‘D&&!kR‚‚\\˜qS\nAqI+äQ+u°eNKa\"‰©v¢5İ’ÁĞ-“h¿_d½ÕJRlN	Ò¬Â¸Z¨RjÄËP5vÔÎXœ1ÆöH£N#jTSÚ|:ÑÌ,D0BÍÈ(×\0à,*á@'…0¨ÖQÁ‹\0R\nz ôš (\"Ešµ¢ö \"ºÅ¢ŒtˆUÒºØ¼î—ëN’sÈ  …ÁP(òB(	øåVìP&Â|kèùğX'Ìú£&‚`ÛP¤Ç•va@|Åx¹A<'\0ª A\n½×ĞˆB`E°e¤V‰z-OÙiâ	@YÒ%Eú:Ë<DÀ:ôpÎ,‰7æÁ‘p\"ˆ¨»´Â.Ô–QN.Î…œÌÕ§¶¨&¬Nx™”æÚ—†¢´m£&hê~à ‡9Å@‹o&[	…YDQ¾	°Jñ¿`š”\"ğFNÓ´}ä¸P‘Í¡«¹Ô*}ÚĞ·»%æ‹s8Kkº2¯W2”M…çÊÎ&¢rÎm¹:2½³E•\$â§RjTé)¥¾(˜ê4Ê<\\àü\"KEĞ,¢mŠÉÉh·Ab*°W*é‚U('Ğï\\1t›±N+>¥¤M‹¢&‡H“Ò¨ÂLˆZ+³Ae–0H\\‹”½ùÄ%\r5AbëĞ~m0ŒèÁ@‚Â@æ<gLQÜ¶+ğƒE<¢eT‚\0^3,‰%¹|QHyA„`æwHğª¬Ş#Äº#–7H´^ŒD‹ƒ Q1\"ñ%™Õ¬‰Òå›/ìuwù»D˜İš˜´F!}Ú¼H´é(ÓâƒB\n·&„é¢•_\nªÔ|±`\n\ná”1júØcÉ)M¼¦xHZ-s’ñşcnâØrˆ4+W	'«ùB¨|ÆzHRb=¸aqmÑy/eôÁ+±‹m¹Î:z=Âñ¤LÑœÜ˜rÛ+FpwX®(û¶ÜG»pš˜ŞûŸp²õ?¾çôúóÆÑ²Q\0®‹1go[tÕÁùÍRb1D+ZÄ[İŒXhÂ#‰ãnZVii‚{åHaay?K*àÂ#Ÿ‹+sšBÅàˆ²öd]\nhZxˆYpëvÒíê°‚ná‹Ÿ»‹½FşTñï…:9xxâûäLŒ„~¥êgß:gm©sÖ|½#æ%77B\\\\öjéÒ:Wní7·ÁÊ\$£ö®Î¥YX¹ŒîFê>	ü©Ï,VÂ-BŞ°ó|#ıú.ŠÉG…ÁÆ‡•rszFD§+;±şÚwÿ,kJ­qìë³r÷¶’ÊYZÈí…¦¥bFùÏ;fR½“{`§ÜG¾÷{Ÿ¤ü¥¯ï>É%7§aO–J>Ÿùä¤¼vÏ¦szW×c¤À·Qv¢„éïì´WgÀİ±'æœs‰\\Éòwº*9äõš¦?ŸW^G\nßèÈáƒ®ÿ¨ZÏr LŒû`h…ªÓŒ\\u3ÏBa<ñN.ç|Æ	ìÅîR4ÅP8ßzø­şìc#K\0p=Ğ6Æp@Æm†oB0L”p­ÂÀLğ:Â£º½/ùb®	¾\r\0Ê¬ş¥è•K®2‚ÒA>¶-4+¹-J6+®ÙÒ‚ p†t!\\'^ûĞ²\\0¦ómâ–Ìò:¬öÏ§xag\rƒzŸgåÈá 4.pIíƒ×íˆôÁvº\n ¨ÀZlÄh*íÍën›‚”b6#«~OÃ¦Õ†ıJô¥aĞ!(4Âä!^ÛCp;'– Cn/¬rÇiV÷!^•…šmLHzZ£4uFj`ÍI¬\0¦\\\"\\&l%¡æQ*2ç¬©˜àyXµ1¬ù0¸ÓBì÷‹unF‚¦ÀÑ¾êIôïtùª4i[¬.\nnf®f³NRÂOîjÉ\\™„Vn\r6çdnÎ ¬ Æ ê\r®n\$1ÊÎ\$Ql4ÁGÃÆQÂb?‘¦™ƒ¤±²µéZ0MˆŠ‹\$2FµR:`ƒ¤Ãşr(êqğñÄ u\"a._.ÙÅn"; break;
		case "zh-tw": $compressed = "ä^¨ê%Ó•\\šr¥ÑÎõâ|%ÌÂ:\$\ns¡.ešUÈ¸E9PK72©(æP¢h)Ê…@º:i	%“Êcè§Je åR)Ü«{º	Nd TâPˆ£\\ªÔÃ•8¨CˆÈf4†ãÌaS@/%Èäû•N‹¦¬’Ndâ%Ğ³C¹’É—B…Q+–¹Öê‡Bñ_MK,ª\$õÆçu»ŞowÔfš‚T9®WK´ÍÊW¹•ˆ§2mizX:P	—*‘½_/Ùg*eSLK¶Ûˆú™Î¹^9×HÌ\rºÛÕ7ºŒZz>‹ êÔ0)È¿Nï\nÙr!U=R\n¤ôÉÖ^¯ÜéJÅÑTçO©](ÅI–Ø^Ü«¥[f]œå©bë…Òè©*ÁÊ\\gA2‡¥y­OËXş#Év—”ªi`\\…É\nsÃPà ŒƒhÒ7£‘ÒP	‘Z¨œÄ£BG–‰‘Tr’¤{4Ç‘0Œ&Q8)´,ı•ha!\0Ğ9£0z\r è8aĞ^òè\\0Å1\\Z\rãÎŒ£p^8#˜æ;Ì£ ^)AñĞT¤„\ntÄ[T¾exŒ!ğ\\\$	psd<-D%yÎRP	 s-î±~WF¥ÊJQO„²ú¬:ôá(\\ÂÕÅ1‘|FM•ÏZS¤‰‡Œ\0Ä<ƒ(P9…*iXB m O¤òà™gANQ¼D<vE’´MÆQ„dÖ­TMF¥Ä9zr—„}MÇ) D)¤8¡®!v]œÄ!bœåíbsÄ“÷s'ª‘UE¬sİ‚§è8*£Àè\$©nı”Éu¨€‰q\nÂ/\rG~g1s\nbˆ˜V¥íäğœÄI&t’Ëƒjä·5-;#ÃÕOT¯Ôµ1›t©V×ä,‚ZÒôÎxşéš„'Éséº¹]%Ú)Ï£è\nÆñi0¹WDQTaŒÄÖT)#˜@s´xOíûçºïTah—§1PPÀ\$#hV’èùd¦’¥Â¦K©¤¹Jºç12Af„›ºKÕG#†ÕV*\\\\—È*\rƒ äË•I6Q0D×<C2Ñ\$7­Ã%ÛæB(‰ÌJ’í7ÆÑ\$r€MIGF¤×')C\$‚ª_‡IFÎå3-º'äVñë~ÙOa:€=OdQEƒ—“°)\nW(Ğ1Hµ¤Œ™'J”¨•’ÂZK‰y0>ÄÆ™S:iá7†àÂL\rN`øsÕ.+ñ E»á,!Ç8¶H¼@Á Ô*‡\na•7AÜË¼#\"9Ê>ôüõÍC¶C†X¡´\$…,1x®PÀ?´”RšUJée-¥Ğî—ÓíL‰™4&¦\"¯Cps…ÉÍ:¼¡ß‡(±° 7Ñ,§ˆèèÂLƒ2T>ˆD+YÆZÂØËø…EğlQ\n¶îÛŞØ­Š0F±Â†!Ê±™tO¾†Ş9ÄÛ›a\$T`švÎìr½AÊ'Å¹”v¤j0WàÜ)3‹È]ºdjÅ¡5€€(€ ÜÄ›”Î	ÂQ	ŠT&‡‚à©d,MP£_‚•çÎ\"Tñ£“â‚Q¼1,¤°‘´D½³0a\\p…²É!!B>+eX¸´ErÎ9…\0¼IIüÏŸQ/G0˜åGßÑèé2QŞÁÊ&æ¡,#JÍrlS\nA@ŞÓI€‹ÏXFÃ‘2/\rQ1/Ã”K\nãğ-£Ô3%äÄ™ÎG9…pµwâáëY*''YA[”i£œÑÊ\"…q6‚˜Aa<'1˜9flºÉÑ<úÓrD”€@9…ˆ‚†Ã¤K‰Æh\"G4‚„ƒ¤NŠ#,xS\n€#ŠDvPLem6Vè¨„q1”ş ”¢(‘…@­ Ä ´ÍáQ=W‘òiâ„`¨	Ù\"e\0¡,¦R	Á½©Ã‘ qÊ‚gä;¡E\0KA|ôhµ)¢€P”5ŒÂp \n¡@\"¨@U°\"„À‹mÖÄ¥HHÑÎLÉpd®5u\\kÅX¸ºc+yW*³šsÎ‰Ó:¤ìŠ‹³¬.'h§»×tğP¹­,šyøl©\" ›s¼x²ŞmMŒrµæÁzĞÂ£œH!1P\"×ûÎs…øñ\nãHãHÀ‹˜B—\nı3ÄAt„0MWãT#Ş,´–Òâ]Jy{,ÊS¹wo×\n7I…H3ŠÕÚÑ`±›	.§Eñ\n‰®+œ…í[Óœîñ2xfx©J„«	bš*•\0¦TXqh(—#§gÂç'ey”­8æB@Œ+&TÒ¦7Z½ŠKKiÇM©<Bœ\\U;>‚¯±¯Í™¹¡ä@ˆ¦J–<BñÇ;‰à/\"æ¸ø\0].g<tØ¶l†K0_VŒtKoæïÑÔlÉ…¥¶\n!„Ì|S½f¡Z|Rê¦zÄùó>¢¨ğ‚UrÁ¸­”—J)©5 	á-%jíf#ø—¢öd	ñ~öêbA,¸]ŠIë'å\r6‘P.‘µµyŒ°×kS¨‰nĞ2Š Ÿ<ám­ğAN'D‚ÉdrrT7a#½°„Iˆú9DœŒ_ë:'AX„0ŒP8¸oİB#n*Â×ºÕNXAoŠ+E.OT	I)MT}‰‘¤&'vSEÄzÇä`Á˜YöÇÇHƒlB§‚*ñ]{w´~½ü®öšSNbUt¤ÖœÅZ(9µBè‚Ëz<ì|)\rêÑŠöå>¤È©1ÑÍ|Eõv•aî-Ç@yEFå²Œl\nÜÇ§¸ƒóººDo&+!evñËÜ{·q¹½ˆ—’]rUÊ.×'åİ¢¸B6\r]1	‰wa¹\r>öôëáÒèáÌÙw6ôô¾·}ôbHÚn…M³»ğ*Í ‘Ğ=³è<yÏšõ\\™Oc±NÆï÷»§ÚKMj¯£Ê~û4ZŸGçğf÷ù§àĞ¯–‰øˆØBHâeáPk>å1ì—‹s9\$\$•BŞdˆ´•\"z_ÀVGíŠ\"]NŒìğY\\Ş‘ñ)”dzF½´D‰6”ø«Tı/®Æ&ÂhFœÀÎ|Ì<ñGDê¯JTå>T%Føîvl‘ƒfóÌãl•«G°3ìò°.ĞåäÏ…!s\røìãĞTùĞYFºÑğlfŒ\0S&„¢Á^\\É Á{‰:Øv*vòÌ~õÍlÄ*a!	Á\r\nP¨‡X¥ÄĞ,ÂS¤^'â©\nnë¸\\eË¦#qŠYFüëÎYÌÚ£ìß\nvÎpğA0¬ëìÎ‚ÿŒÅ0úÎ\rÍ\0Î®(s#¬MÑPè<	=&°ôTñ*0Šóæq.2ÀM Ğ È#ş_âšÌ\"4fºKÊ×ÉØ–€ä\ràÆ…€æX*Ø¤¨®0<AHQÁF¢0Æ¡bÛiØ#k3C\\è%`Ø)šØmŠØíF gT\rƒ—ƒ¢9í>'ã\\[‡@2¬ÔbLûHôıÑ6\n ¨ÀZŠBjéÄÛiD¡B2#b:¾ENádÆ,¦Q\nºFåƒŒ0ñ68®\\Ï&˜÷ñÚ%¬TC|xå#)ÄÇˆH)ªqã£\$&bÁbêhJ÷fDbÎê.–z+»&0l®¨†-è†Š÷nš'\nGï„v…E(‘&î¦¤o©Ì–AB®iáÌ]Åà%Ì®ì­6/ºB0ğ¬¯+æ2BÉÜâA,\"íĞ–+úƒ@¬ Æ ê\r¯ .\0 ')NŞ¨Ì¸SíHÌ):.Q%Æ¹&Rh¼Q®¡l¦Ç1»Q‰ÄøÁĞo_­%Â®Á‘¡,sL§A"; break;
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
			$query = preg_replace('~[\x80-\xFF]+$~', '', substr($query, 0, 1e6)) . "\nâ€¦"; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
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
			. "<input type='image' class='icon' name='up[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=up.gif&version=4.8.1") . "' alt='â†‘' title='" . lang(103) . "'> "
			. "<input type='image' class='icon' name='down[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=down.gif&version=4.8.1") . "' alt='â†“' title='" . lang(104) . "'> "
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
						echo "<a href='" . h($href . $desc) . "' title='" . lang(48) . "' class='text'> â†“</a>";
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
							$long = strpos($val, "<i>â€¦</i>");
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
							. script("qsl('a').onclick = partial(selectLoadMore, " . (+$limit) . ", '" . lang(241) . "â€¦');", "")
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
						echo pagination(0, $page) . ($page > 5 ? " â€¦" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " â€¦" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . lang(243) . "</a>"
							);
						}
					} else {
						echo "<legend>" . lang(242) . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " â€¦" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " â€¦" : "") : "");
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
