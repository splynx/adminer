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
	return script("$selector.onclick = function () { return confirm('" . ($message ? js_escape($message) : 'Are you sure?') . "'); };", "");
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
	return sprintf('%.3f s', max(0, microtime(true) - $start));
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
	return ($error ? 'Unable to upload a file.' . ($max_size ? " " . sprintf('Maximum allowed file size is %sB.', $max_size) : "") : 'File does not exist.');
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
	return strtr(number_format($val, 0, ".", ','), preg_split('~~u', '0123456789', -1, PREG_SPLIT_NO_EMPTY));
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
	$return = ($empty !== null ? "<label><input type='$type'$attrs value='$empty'" . ((is_array($value) ? in_array($empty, $value) : $value === 0) ? " checked" : "") . "><i>" . 'empty' . "</i></label>" : "");
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
	$functions = (isset($_GET["select"]) || $reset ? array("orig" => 'original') : array()) + $adminer->editFunctions($field);
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
	echo ($sep ? "<p class='message'>" . 'No tables.' : "</ul>") . "\n";
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
		($update ? 'Edit' : 'Insert'),
		$error,
		array("select" => array($table, $table_name)),
		$table_name
	);
	$adminer->editRowPrint($table, $fields, $row, $update);
	if ($row === false) {
		echo "<p class='error'>" . 'No rows.' . "\n";
	}
	?>
<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
	if (!$fields) {
		echo "<p class='error'>" . 'You have no privileges to update this table.' . "\n";
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
		echo "<input type='submit' value='" . 'Save' . "'>\n";
		if (!isset($_GET["select"])) {
			echo "<input type='submit' name='insert' value='" . ($update
				? 'Save and continue edit'
				: 'Save and insert next'
			) . "' title='Ctrl+Shift+Enter'>\n";
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . 'Saving' . "…', this); };") : "");
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . 'Delete' . "'>" . confirm() . "\n"
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

function get_lang() {
	return 'en';
}

function lang($translation, $number = null) {
	if (is_array($translation)) {
		$pos = ($number == 1 ? 0
			: 1
		);
		$translation = $translation[$pos];
	}
	$translation = str_replace("%d", "%s", $translation);
	$number = format_number($number);
	return sprintf($translation, $number);
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
					$this->error = 'Unknown error.';
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
		echo $this->loginFormField('driver', '<tr><th>' . 'System' . '<td>', html_select("auth[driver]", $drivers, DRIVER, "loginDriver(this);") . "\n");
		echo $this->loginFormField('server', '<tr><th>' . 'Server' . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '" title="hostname[:port]" placeholder="localhost" autocapitalize="off">' . "\n");
		echo $this->loginFormField('username', '<tr><th>' . 'Username' . '<td>', '<input name="auth[username]" id="username" value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">' . script("focus(qs('#username')); qs('#username').form['auth[driver]'].onchange();"));
		echo $this->loginFormField('password', '<tr><th>' . 'Password' . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">' . "\n");
		echo $this->loginFormField('db', '<tr><th>' . 'Database' . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">' . "\n");
		echo "</table>\n";
		echo "<p><input type='submit' value='" . 'Login' . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], 'Permanent login') . "\n";
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
			return sprintf('Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.', target_blank());
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
		$links = array("select" => 'Select data');
		if (support("table") || support("indexes")) {
			$links["table"] = 'Show structure';
		}
		if (support("table")) {
			if (is_view($tableStatus)) {
				$links["view"] = 'Alter view';
			} else {
				$links["create"] = 'Alter table';
			}
		}
		if ($set !== null) {
			$links["edit"] = 'New item';
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
			$return = ", <a href='#$id'>" . 'Warnings' . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "")
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-$jush'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . 'Edit' . "</a>" : "")
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
			$return = "<i>" . lang(array('%d byte', '%d bytes'), strlen($original)) . "</i>";
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
		echo "<thead><tr><th>" . 'Column' . "<td>" . 'Type' . (support("comment") ? "<td>" . 'Comment' : "") . "</thead>\n";
		foreach ($fields as $field) {
			echo "<tr" . odd() . "><th>" . h($field["field"]);
			echo "<td><span title='" . h($field["collation"]) . "'>" . h($field["full_type"]) . "</span>";
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . 'Auto Increment' . "</i>" : "");
			echo (isset($field["default"]) ? " <span title='" . 'Default value' . "'>[<b>" . h($field["default"]) . "</b>]</span>" : "");
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
		print_fieldset("select", 'Select', $select);
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
				. optionlist(array(-1 => "") + array_filter(array('Functions' => $functions, 'Aggregation' => $grouping)), $val["fun"]) . "</select>"
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
		print_fieldset("search", 'Search', $where);
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
					"(" . 'anywhere' . ")"
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
		print_fieldset("sort", 'Sort', $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]'", $columns, $val, "selectFieldChange");
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), 'descending') . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]'", $columns, "", "selectAddRow");
		echo checkbox("desc[$i]", 1, false, 'descending') . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select
	* @param string result of selectLimitProcess()
	* @return null
	*/
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . 'Limit' . "</legend><div>"; // <div> for easy styling
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
			echo "<fieldset><legend>" . 'Text length' . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}

	/** Print action box in select
	* @param array
	* @return null
	*/
	function selectActionPrint($indexes) {
		echo "<fieldset><legend>" . 'Action' . "</legend><div>";
		echo "<input type='submit' value='" . 'Select' . "'>";
		echo " <span id='noindex' title='" . 'Full table scan' . "'></span>";
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
		$return = "<a href='#$sql_id' class='toggle'>" . 'SQL command' . "</a>\n";
		if (!$failed && ($warnings = $driver->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . 'Warnings' . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-$jush'>" . shorten_utf8($query, 1000) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql") ? '<p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . 'Edit' . '</a>' : '')
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
			$return = 'Auto Increment';
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
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='-1' checked><i>" . 'original' . "</i></label> " : "")
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
		$return = array('text' => 'open', 'file' => 'save');
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
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . 'Alter database' . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? 'Alter schema' : 'Create schema') . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . 'Database schema' . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . 'Privileges' . "</a>\n" : "");
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
				echo "<p class='links'>" . (support("sql") ? "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . 'SQL command' . "</a>\n<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . 'Import' . "</a>\n" : "") . "";
				if (support("dump")) {
					echo "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . 'Export' . "</a>\n";
				}
			}
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				echo '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . 'Create table' . "</a>\n";
				if (!$tables) {
					echo "<p class='message'>" . 'No tables.' . "\n";
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
		echo "<span title='" . 'database' . "'>" . 'DB' . "</span>: " . ($databases
			? "<select name='db'>" . optionlist(array("" => "") + $databases, DB) . "</select>$db_events"
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off'>\n"
		);
		echo "<input type='submit' value='" . 'Use' . "'" . ($databases ? " class='hidden'" : "") . ">\n";

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
					. " title='" . 'Select data' . "'>" . 'select' . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
						. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"])), (is_view($status) ? "view" : "structure"))
						. " title='" . 'Show structure' . "'>$name</a>"
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
					$this->error = sprintf('Disable %s or enable %s or %s extensions.', "'mysql.allow_local_infile'", "MySQLi", "PDO_MySQL");
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
				$structured_types['Strings'][] = "json";
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
			'Numbers' => array("tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20, "decimal" => 66, "float" => 12, "double" => 21),
			'Date and time' => array("date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4),
			'Strings' => array("char" => 255, "varchar" => 65535, "tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295),
			'Lists' => array("enum" => 65535, "set" => 64),
			'Binary' => array("bit" => 20, "binary" => 255, "varbinary" => 65535, "tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295),
			'Geometry' => array("geometry" => 0, "point" => 0, "linestring" => 0, "polygon" => 0, "multipoint" => 0, "multilinestring" => 0, "multipolygon" => 0, "geometrycollection" => 0),
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
<html lang="en" dir="ltr">
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

<body class="ltr nojs">
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
var offlineMessage = '<?php echo js_escape('You are offline.'); ?>';
var thousandsSeparator = '<?php echo js_escape(','); ?>';
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
		$server = ($server != "" ? $server : 'Server');
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

<?php if ($missing != "auth") { ?>
<form action="" method="post">
<p class="logout">
<input type="submit" name="logout" value="Logout" id="logout">
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
		auth_error(lang(array('Too many unsuccessful logins, try again in %d minute.', 'Too many unsuccessful logins, try again in %d minutes.'), ceil($next_attempt / 60)));
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
	redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1), 'Logout successful.' . ' ' . 'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.');
	
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
			$error = 'Session expired, please login again.';
		} else {
			restart_session();
			add_invalid_login();
			$password = get_password();
			if ($password !== null) {
				if ($password === false) {
					$error .= ($error ? '<br>' : '') . sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.', target_blank(), '<code>permanentLogin()</code>');
				}
				set_password(DRIVER, SERVER, $_GET["username"], null);
			}
			unset_permanent();
		}
	}
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = 'Session support must be enabled.';
	}
	$params = session_get_cookie_params();
	cookie("adminer_key", ($_COOKIE["adminer_key"] ? $_COOKIE["adminer_key"] : rand_string()), $params["lifetime"]);
	page_header('Login', $error, null);
	echo "<form action='' method='post'>\n";
	echo "<div>";
	if (hidden_fields($_POST, array("auth"))) { // expired session
		echo "<p class='message'>" . 'The action will be performed after successful login with the same credentials.' . "\n";
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
	page_header('No extension', sprintf('None of the supported PHP extensions (%s) are available.', implode(", ", $possible_drivers)), false);
	page_footer("auth");
	exit;
}

stop_session(true);

if (isset($_GET["username"]) && is_string(get_password())) {
	list($host, $port) = explode(":", SERVER, 2);
	if (preg_match('~^\s*([-+]?\d+)~', $port, $match) && ($match[1] < 1024 || $match[1] > 65535)) { // is_numeric('80#') would still connect to port 80
		auth_error('Connecting to privileged ports is not allowed.');
	}
	check_invalid_login();
	$connection = connect();
	$driver = new Min_Driver($connection);
}

$login = null;
if (!is_object($connection) || ($login = $adminer->login($_GET["username"], get_password())) !== true) {
	$error = (is_string($connection) ? h($connection) : (is_string($login) ? $login : 'Invalid credentials.'));
	auth_error($error . (preg_match('~^ | $~', get_password()) ? '<br>' . 'There is a space in the input password which might be the cause.' : ''));
}

if ($_POST["logout"] && $has_token && !verify_token()) {
	page_header('Logout', 'Invalid CSRF token. Send the form again.');
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
			? sprintf('Maximum number of allowed fields exceeded. Please increase %s.', "'$ini'")
			: 'Invalid CSRF token. Send the form again.' . ' ' . 'If you did not send this request from Adminer then close this page.'
		);
	}
	
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
	// posted form with no data means that post_max_size exceeded because Adminer always sends token at least
	$error = sprintf('Too big POST data. Reduce the data or increase the %s configuration directive.', "'post_max_size'");
	if (isset($_GET["sql"])) {
		$error .= ' ' . 'You can upload a big SQL file via FTP and import it from server.';
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
				$val = "<i>" . lang(array('%d byte', '%d bytes'), strlen($val)) . "</i>"; //! link to download
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
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . 'No rows.') . "\n";
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
	$structured_types['Foreign keys'] = $foreign_keys;
}
echo optionlist(array_merge($extra_types, $structured_types), $type);
?></select><td><input name="<?php echo h($key); ?>[length]" value="<?php echo h($field["length"]); ?>" size="3"<?php echo (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : ""); //! type="number" with enabled JavaScript ?> aria-labelledby="label-length"><td class="options"><?php
	echo "<select name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . '><option value="">(' . 'collation' . ')' . optionlist($collations, $field["collation"]) . '</select>';
	echo ($unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>' . optionlist(array("" => "(" . 'ON UPDATE' . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"])) . '</select>' : '');
	echo ($foreign_keys ? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . 'ON DELETE' . ")" . optionlist(explode("|", $on_actions), $field["on_delete"]) . "</select> " : " "); // space for IE
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
<th id="label-name"><?php echo ($type == "TABLE" ? 'Column name' : 'Parameter name'); ?>
<td id="label-type">Type<textarea id="enum-edit" rows="4" cols="12" wrap="off" style="display: none;"></textarea><?php echo script("qs('#enum-edit').onblur = editingLengthBlur;"); ?>
<td id="label-length">Length
<td><?php echo 'Options'; /* no label required, options have their own label */  if ($type == "TABLE") { ?>
<td id="label-null">NULL
<td><input type="radio" name="auto_increment_col" value=""><acronym id="label-ai" title="Auto Increment">AI</acronym><?php echo doc_link(array(
	'sql' => "example-auto-increment.html",
	'mariadb' => "auto_increment/",
	
	
	
)); ?>
<td id="label-default"<?php echo $default_class; ?>>Default value
<?php echo (support("comment") ? "<td id='label-comment'$comment_class>" . 'Comment' : "");  } ?>
<td><?php echo "<input type='image' class='icon' name='add[" . (support("move_col") ? 0 : count($fields)) . "]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . 'Add next' . "'>" . script("row_count = " . count($fields) . ";"); ?>
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
			"<input type='image' class='icon' name='add[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . 'Add next' . "'> "
			. "<input type='image' class='icon' name='up[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=up.gif&version=4.8.1") . "' alt='↑' title='" . 'Move up' . "'> "
			. "<input type='image' class='icon' name='down[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=down.gif&version=4.8.1") . "' alt='↓' title='" . 'Move down' . "'> "
		: "");
		echo ($orig == "" || support("drop_col") ? "<input type='image' class='icon' name='drop_col[$i]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=cross.gif&version=4.8.1") . "' alt='x' title='" . 'Remove' . "'>" : "");
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
		page_header('Database' . ": " . h(DB), 'Invalid database.', true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), 'Databases have been dropped.', drop_databases($_POST["db"]));
		}
		
		page_header('Select database', $error, false);
		echo "<p class='links'>\n";
		foreach (array(
			'database' => 'Create database',
			'privileges' => 'Privileges',
			'processlist' => 'Process list',
			'variables' => 'Variables',
			'status' => 'Status',
		) as $key => $val) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . sprintf('%s version: %s through PHP extension %s', $drivers[DRIVER], "<b>" . h($connection->server_info) . "</b>", "<b>$connection->extension</b>") . "\n";
		echo "<p>" . sprintf('Logged as: %s', "<b>" . h(logged_user()) . "</b>") . "\n";
		$databases = $adminer->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table cellspacing='0' class='checkable'>\n";
			echo script("mixin(qsl('table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true)});");
			echo "<thead><tr>"
				. (support("database") ? "<td>" : "")
				. "<th>" . 'Database' . " - <a href='" . h(ME) . "refresh=1'>" . 'Refresh' . "</a>"
				. "<td>" . 'Collation'
				. "<td>" . 'Tables'
				. "<td>" . 'Size' . " - <a href='" . h(ME) . "dbsize=1'>" . 'Compute' . "</a>" . script("qsl('a').onclick = partial(ajaxSetHtml, '" . js_escape(ME) . "script=connect');", "")
				. "</thead>\n"
			;
			
			$databases = ($_GET["dbsize"] ? count_tables($databases) : array_flip($databases));
			
			foreach ($databases as $db => $tables) {
				$root = h(ME) . "db=" . urlencode($db);
				$id = h("Db-" . $db);
				echo "<tr" . odd() . ">" . (support("database") ? "<td>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]), "", "", "", $id) : "");
				echo "<th><a href='$root' id='$id'>" . h($db) . "</a>";
				$collation = h(db_collation($db, $collations));
				echo "<td>" . (support("database") ? "<a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . 'Alter database' . "'>$collation</a>" : $collation);
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . 'Database schema' . "'>" . ($_GET["dbsize"] ? $tables : "?") . "</a>";
				echo "<td align='right' id='size-" . h($db) . "'>" . ($_GET["dbsize"] ? db_size($db) : "?");
				echo "\n";
			}
			
			echo "</table>\n";
			echo (support("database")
				? "<div class='footer'><div>\n"
					. "<fieldset><legend>" . 'Selected' . " <span id='selected'></span></legend><div>\n"
					. "<input type='hidden' name='all' value=''>" . script("qsl('input').onclick = function () { selectCount('selected', formChecked(this, /^db/)); };") // used by trCheck()
					. "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm() . "\n"
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

page_header(($fields && is_view($table_status) ? $table_status['Engine'] == 'materialized view' ? 'Materialized view' : 'View' : 'Table') . ": " . ($name != "" ? $name : h($TABLE)), $error);

$adminer->selectLinks($table_status);
$comment = $table_status["Comment"];
if ($comment != "") {
	echo "<p class='nowrap'>" . 'Comment' . ": " . h($comment) . "\n";
}

if ($fields) {
	$adminer->tableStructurePrint($fields);
}

if (!is_view($table_status)) {
	if (support("indexes")) {
		echo "<h3 id='indexes'>" . 'Indexes' . "</h3>\n";
		$indexes = indexes($TABLE);
		if ($indexes) {
			$adminer->tableIndexesPrint($indexes);
		}
		echo '<p class="links"><a href="' . h(ME) . 'indexes=' . urlencode($TABLE) . '">' . 'Alter indexes' . "</a>\n";
	}
	
	if (fk_support($table_status)) {
		echo "<h3 id='foreign-keys'>" . 'Foreign keys' . "</h3>\n";
		$foreign_keys = foreign_keys($TABLE);
		if ($foreign_keys) {
			echo "<table cellspacing='0'>\n";
			echo "<thead><tr><th>" . 'Source' . "<td>" . 'Target' . "<td>" . 'ON DELETE' . "<td>" . 'ON UPDATE' . "<td></thead>\n";
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
				echo '<td><a href="' . h(ME . 'foreign=' . urlencode($TABLE) . '&name=' . urlencode($name)) . '">' . 'Alter' . '</a>';
			}
			echo "</table>\n";
		}
		echo '<p class="links"><a href="' . h(ME) . 'foreign=' . urlencode($TABLE) . '">' . 'Add foreign key' . "</a>\n";
	}
}

if (support(is_view($table_status) ? "view_trigger" : "trigger")) {
	echo "<h3 id='triggers'>" . 'Triggers' . "</h3>\n";
	$triggers = triggers($TABLE);
	if ($triggers) {
		echo "<table cellspacing='0'>\n";
		foreach ($triggers as $key => $val) {
			echo "<tr valign='top'><td>" . h($val[0]) . "<td>" . h($val[1]) . "<th>" . h($key) . "<td><a href='" . h(ME . 'trigger=' . urlencode($TABLE) . '&name=' . urlencode($key)) . "'>" . 'Alter' . "</a>\n";
		}
		echo "</table>\n";
	}
	echo '<p class="links"><a href="' . h(ME) . 'trigger=' . urlencode($TABLE) . '">' . 'Add trigger' . "</a>\n";
}

} elseif (isset($_GET["schema"])) {
	
page_header('Database schema', "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

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
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link">Permanent link</a>
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

page_header('Export', $error, ($_GET["export"] != "" ? array("table" => $_GET["export"]) : array()), h(DB));
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

echo "<tr><th>" . 'Output' . "<td>" . html_select("output", $adminer->dumpOutput(), $row["output"], 0) . "\n"; // 0 - radio

echo "<tr><th>" . 'Format' . "<td>" . html_select("format", $adminer->dumpFormat(), $row["format"], 0) . "\n"; // 0 - radio

echo ($jush == "sqlite" ? "" : "<tr><th>" . 'Database' . "<td>" . html_select('db_style', $db_style, $row["db_style"])
	. (support("routine") ? checkbox("routines", 1, $row["routines"], 'Routines') : "")
	. (support("event") ? checkbox("events", 1, $row["events"], 'Events') : "")
);

echo "<tr><th>" . 'Tables' . "<td>" . html_select('table_style', $table_style, $row["table_style"])
	. checkbox("auto_increment", 1, $row["auto_increment"], 'Auto Increment')
	. (support("trigger") ? checkbox("triggers", 1, $row["triggers"], 'Triggers') : "")
;

echo "<tr><th>" . 'Data' . "<td>" . html_select('data_style', $data_style, $row["data_style"]);
?>
</table>
<p><input type="submit" value="Export">
<input type="hidden" name="token" value="<?php echo $token; ?>">

<table cellspacing="0">
<?php
echo script("qsl('table').onclick = dumpClick;");
$prefixes = array();
if (DB != "") {
	$checked = ($TABLE != "" ? "" : " checked");
	echo "<thead><tr>";
	echo "<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$checked>" . 'Tables' . "</label>" . script("qs('#check-tables').onclick = partial(formCheck, /^tables\\[/);", "");
	echo "<th style='text-align: right;'><label class='block'>" . 'Data' . "<input type='checkbox' id='check-data'$checked></label>" . script("qs('#check-data').onclick = partial(formCheck, /^data\\[/);", "");
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
	echo "<label class='block'><input type='checkbox' id='check-databases'" . ($TABLE == "" ? " checked" : "") . ">" . 'Database' . "</label>";
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
	
page_header('Privileges');

echo '<p class="links"><a href="' . h(ME) . 'user=">' . 'Create user' . "</a>";

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
echo "<thead><tr><th>" . 'Username' . "<th>" . 'Server' . "<th></thead>\n";

while ($row = $result->fetch_assoc()) {
	echo '<tr' . odd() . '><td>' . h($row["User"]) . "<td>" . h($row["Host"]) . '<td><a href="' . h(ME . 'user=' . urlencode($row["User"]) . '&host=' . urlencode($row["Host"])) . '">' . 'Edit' . "</a>\n";
}

if (!$grant || DB != "") {
	echo "<tr" . odd() . "><td><input name='user' autocapitalize='off'><td><input name='host' value='localhost' autocapitalize='off'><td><input type='submit' value='" . 'Edit' . "'>\n";
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

page_header((isset($_GET["import"]) ? 'Import' : 'SQL command'), $error);

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
							echo "<p class='error'>" . 'ATTACH queries are not supported.' . "\n";
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
									echo "<p class='error'>" . 'Error in query' . ($connection->errno ? " ($connection->errno)" : "") . ": " . error() . "\n";
									$errors[] = " <a href='#sql-$commands'>$commands</a>";
									if ($_POST["error_stops"]) {
										break 2;
									}

								} else {
									$time = " <span class='time'>(" . format_time($start) . ")</span>"
										. (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . 'Edit' . "</a>" : "") // 1000 - maximum length of encoded URL in IE is 2083 characters
									;
									$affected = $connection->affected_rows; // getting warnigns overwrites this
									$warnings = ($_POST["only_errors"] ? "" : $driver->warnings());
									$warnings_id = "warnings-$commands";
									if ($warnings) {
										$time .= ", <a href='#$warnings_id'>" . 'Warnings' . "</a>" . script("qsl('a').onclick = partial(toggle, '$warnings_id');", "");
									}
									$explain = null;
									$explain_id = "explain-$commands";
									if (is_object($result)) {
										$limit = $_POST["limit"];
										$orgtables = select($result, $connection2, array(), $limit);
										if (!$_POST["only_errors"]) {
											echo "<form action='' method='post'>\n";
											$num_rows = $result->num_rows;
											echo "<p>" . ($num_rows ? ($limit && $num_rows > $limit ? sprintf('%d / ', $limit) : "") . lang(array('%d row', '%d rows'), $num_rows) : "");
											echo $time;
											if ($connection2 && preg_match("~^($space|\\()*+SELECT\\b~i", $q) && ($explain = explain($connection2, $q))) {
												echo ", <a href='#$explain_id'>Explain</a>" . script("qsl('a').onclick = partial(toggle, '$explain_id');", "");
											}
											$id = "export-$commands";
											echo ", <a href='#$id'>" . 'Export' . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "") . "<span id='$id' class='hidden'>: "
												. html_select("output", $adminer->dumpOutput(), $adminer_export["output"]) . " "
												. html_select("format", $dump_format, $adminer_export["format"])
												. "<input type='hidden' name='query' value='" . h($q) . "'>"
												. " <input type='submit' name='export' value='" . 'Export' . "'><input type='hidden' name='token' value='$token'></span>\n"
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
											echo "<p class='message' title='" . h($connection->info) . "'>" . lang(array('Query executed OK, %d row affected.', 'Query executed OK, %d rows affected.'), $affected) . "$time\n";
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
			echo "<p class='message'>" . 'No commands to execute.' . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang(array('%d query executed OK.', '%d queries executed OK.'), $commands - count($errors));
			echo " <span class='time'>(" . format_time($total_start) . ")</span>\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . 'Error in query' . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF

	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
$execute = "<input type='submit' value='" . 'Execute' . "' title='Ctrl+Enter'>";
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
	echo 'Limit rows' . ": <input type='number' name='limit' class='size' value='" . h($_POST ? $_POST["limit"] : $_GET["limit"]) . "'>\n";
	
} else {
	echo "<fieldset><legend>" . 'File upload' . "</legend><div>";
	$gz = (extension_loaded("zlib") ? "[.gz]" : "");
	echo (ini_bool("file_uploads")
		? "SQL$gz (&lt; " . ini_get("upload_max_filesize") . "B): <input type='file' name='sql_file[]' multiple>\n$execute" // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
		: 'File uploads are disabled.'
	);
	echo "</div></fieldset>\n";
	$importServerPath = $adminer->importServerPath();
	if ($importServerPath) {
		echo "<fieldset><legend>" . 'From server' . "</legend><div>";
		echo sprintf('Webserver file %s', "<code>" . h($importServerPath) . "$gz</code>");
		echo ' <input type="submit" name="webfile" value="' . 'Run file' . '">';
		echo "</div></fieldset>\n";
	}
	echo "<p>";
}

echo checkbox("error_stops", 1, ($_POST ? $_POST["error_stops"] : isset($_GET["import"]) || $_GET["error_stops"]), 'Stop on error') . "\n";
echo checkbox("only_errors", 1, ($_POST ? $_POST["only_errors"] : isset($_GET["import"]) || $_GET["only_errors"]), 'Show only errors') . "\n";
echo "<input type='hidden' name='token' value='$token'>\n";

if (!isset($_GET["import"]) && $history) {
	print_fieldset("history", 'History', $_GET["history"] != "");
	for ($val = end($history); $val; $val = prev($history)) { // not array_reverse() to save memory
		$key = key($history);
		list($q, $time, $elapsed) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . 'Edit' . "</a>"
			. " <span class='time' title='" . @date('Y-m-d', $time) . "'>" . @date("H:i:s", $time) . "</span>" // @ - time zone may be not set
			. " <code class='jush-$jush'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace('~^(#|-- ).*~m', '', $q)))), 80, "</code>")
			. ($elapsed ? " <span class='time'>($elapsed)</span>" : "")
			. "<br>\n"
		;
	}
	echo "<input type='submit' name='clear' value='" . 'Clear' . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . 'Edit all' . "</a>\n";
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
			'Item has been deleted.',
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
				'Item has been updated.',
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
			queries_redirect($location, sprintf('Item%s has been inserted.', ($last_id ? " $last_id" : "")), $result); //! link
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
		$error = 'No tables.';
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
		queries_redirect(substr(ME, 0, -1), 'Table has been dropped.', drop_tables(array($TABLE)));
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

		$message = 'Table has been altered.';
		if ($TABLE == "") {
			cookie("adminer_engine", $row["Engine"]);
			$message = 'Table has been created.';
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

page_header(($TABLE != "" ? 'Alter table' : 'Create table'), $error, array("table" => $TABLE), h($TABLE));

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
<?php if (support("columns") || $TABLE == "") { ?>
Table name: <input name="name" data-maxlength="64" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<?php if ($TABLE == "" && !$_POST) { echo script("focus(qs('#form')['name']);"); }  echo ($engines ? "<select name='Engine'>" . optionlist(array("" => "(" . 'engine' . ")") + $engines, $row["Engine"]) . "</select>" . on_help("getTarget(event).value", 1) . script("qsl('select').onchange = helpClose;") : ""); ?>
 <?php echo ($collations && !preg_match("~sqlite|mssql~", $jush) ? html_select("Collation", array("" => "(" . 'collation' . ")") + $collations, $row["Collation"]) : ""); ?>
 <input type="submit" value="Save">
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
Auto Increment: <input type="number" name="Auto_increment" size="6" value="<?php echo h($row["Auto_increment"]); ?>">
<?php echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : adminer_setting("defaults")), 'Default values', "columnShow(this.checked, 5)", "jsonly");  echo (support("comment")
	? checkbox("comments", 1, ($_POST ? $_POST["comments"] : adminer_setting("comments")), 'Comment', "editingCommentsClick(this, true);", "jsonly")
		. ' <input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '">'
	: '')
; ?>
<p>
<input type="submit" value="Save">
<?php } ?>

<?php if ($TABLE != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $TABLE));  } 
if (support("partitioning")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", 'Partition by', $row["partition_by"]);
	?>
<p>
<?php echo "<select name='partition_by'>" . optionlist(array("" => "") + $partition_by, $row["partition_by"]) . "</select>" . on_help("getTarget(event).value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;"); ?>
(<input name="partition" value="<?php echo h($row["partition"]); ?>">)
Partitions: <input type="number" name="partitions" class="size<?php echo ($partition_table || !$row["partition_by"] ? " hidden" : ""); ?>" value="<?php echo h($row["partitions"]); ?>">
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th>Partition name<th>Values</thead>
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
	queries_redirect(ME . "table=" . urlencode($TABLE), 'Indexes have been altered.', alter_indexes($TABLE, $alter));
}

page_header('Indexes', $error, array("table" => $TABLE), h($TABLE));

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
<th id="label-type">Index Type
<th><input type="submit" class="wayoff">Column (length)
<th id="label-name">Name
<th><noscript><?php echo "<input type='image' class='icon' name='add[0]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . 'Add next' . "'>"; ?></noscript>
</thead>
<?php
if ($primary) {
	echo "<tr><td>PRIMARY<td>";
	foreach ($primary["columns"] as $key => $column) {
		echo select_input(" disabled", $fields, $column);
		echo "<label><input disabled type='checkbox'>" . 'descending' . "</label> ";
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
				" name='indexes[$j][columns][$i]' title='" . 'Column' . "'",
				($fields ? array_combine($fields, $fields) : $fields),
				$column,
				"partial(" . ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . ", '" . js_escape($jush == "sql" ? "" : $_GET["indexes"] . "_") . "')"
			);
			echo ($jush == "sql" || $jush == "mssql" ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h($index["lengths"][$key]) . "' title='" . 'Length' . "'>" : "");
			echo (support("descidx") ? checkbox("indexes[$j][descs][$i]", 1, $index["descs"][$key], 'descending') : "");
			echo " </span>";
			$i++;
		}

		echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off' aria-labelledby='label-name'>\n";
		echo "<td><input type='image' class='icon' name='drop_col[$j]' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=cross.gif&version=4.8.1") . "' alt='x' title='" . 'Remove' . "'>" . script("qsl('input').onclick = partial(editingRemoveRow, 'indexes\$1[type]');");
	}
	$j++;
}
?>
</table>
</div>
<p>
<input type="submit" value="Save">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["database"])) {
	
$row = $_POST;

if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		$_GET["db"] = ""; // to save in global history
		queries_redirect(remove_from_uri("db|database"), 'Database has been dropped.', drop_databases(array(DB)));
	} elseif (DB !== $name) {
		// create or rename database
		if (DB != "") {
			$_GET["db"] = $name;
			queries_redirect(preg_replace('~\bdb=[^&]*&~', '', ME) . "db=" . urlencode($name), 'Database has been renamed.', rename_database($name, $row["collation"]));
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
			queries_redirect(ME . "db=" . urlencode($last), 'Database has been created.', $success);
		}
	} else {
		// alter database
		if (!$row["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($name) . (preg_match('~^[a-z0-9_]+$~i', $row["collation"]) ? " COLLATE $row[collation]" : ""), substr(ME, 0, -1), 'Database has been altered.');
	}
}

page_header(DB != "" ? 'Alter database' : 'Create database', $error, array(), h(DB));

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
) . "\n" . ($collations ? html_select("collation", array("" => "(" . 'collation' . ")") + $collations, $row["collation"]) . doc_link(array(
	'sql' => "charset-charsets.html",
	'mariadb' => "supported-character-sets-and-collations/",
	
)) : "");
echo script("focus(qs('#name'));");
?>
<input type="submit" value="Save">
<?php
if (DB != "") {
	echo "<input type='submit' name='drop' value='" . 'Drop' . "'>" . confirm(sprintf('Drop %s?', DB)) . "\n";
} elseif (!$_POST["add_x"] && $_GET["db"] == "") {
	echo "<input type='image' class='icon' name='add' src='" . h(preg_replace("~\\?.*~", "", ME) . "?file=plus.gif&version=4.8.1") . "' alt='+' title='" . 'Add next' . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["call"])) {
	
$PROCEDURE = ($_GET["name"] ? $_GET["name"] : $_GET["call"]);
page_header('Call' . ": " . h($PROCEDURE), $error);

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
				echo "<p class='message'>" . lang(array('Routine has been called, %d row affected.', 'Routine has been called, %d rows affected.'), $affected)
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
<input type="submit" value="Call">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
<?php
} elseif (isset($_GET["foreign"])) {
	
$TABLE = $_GET["foreign"];
$name = $_GET["name"];
$row = $_POST;

if ($_POST && !$error && !$_POST["add"] && !$_POST["change"] && !$_POST["change-js"]) {
	$message = ($_POST["drop"] ? 'Foreign key has been dropped.' : ($name != "" ? 'Foreign key has been altered.' : 'Foreign key has been created.'));
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
			$error = 'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.' . "<br>$error"; //! no partitioning
		}
	}
}

page_header('Foreign key', $error, array("table" => $TABLE), h($TABLE));

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
echo "<p>" . 'Target table' . ": " . html_select("table", $referencable, $row["table"], $onchange) . "\n";
if ($jush == "pgsql") {
	echo 'Schema' . ": " . html_select("ns", $adminer->schemas(), $row["ns"] != "" ? $row["ns"] : $_GET["ns"], $onchange);
} elseif ($jush != "sqlite") {
	$dbs = array();
	foreach ($adminer->databases() as $db) {
		if (!information_schema($db)) {
			$dbs[] = $db;
		}
	}
	echo 'DB' . ": " . html_select("db", $dbs, $row["db"] != "" ? $row["db"] : $_GET["db"], $onchange);
}
?>
<input type="hidden" name="change-js" value="">
<noscript><p><input type="submit" name="change" value="Change"></noscript>
<table cellspacing="0">
<thead><tr><th id="label-source">Source<th id="label-target">Target</thead>
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
ON DELETE: <?php echo html_select("on_delete", array(-1 => "") + explode("|", $on_actions), $row["on_delete"]); ?>
 ON UPDATE: <?php echo html_select("on_update", array(-1 => "") + explode("|", $on_actions), $row["on_update"]);  echo doc_link(array(
	'sql' => "innodb-foreign-key-constraints.html",
	'mariadb' => "foreign-keys/",
	
	
	
)); ?>
<p>
<input type="submit" value="Save">
<noscript><p><input type="submit" name="add" value="Add column"></noscript>
<?php if ($name != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $name));  } ?>
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
	$message = 'View has been altered.';

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
			'View has been dropped.',
			$message,
			'View has been created.',
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

page_header(($TABLE != "" ? 'Alter view' : 'Create view'), $error, array("table" => $TABLE), h($TABLE));
?>

<form action="" method="post">
<p>Name: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo (support("materializedview") ? " " . checkbox("materialized", 1, $row["materialized"], 'Materialized view') : ""); ?>
<p><?php textarea("select", $row["select"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($TABLE != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $TABLE));  } ?>
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
		query_redirect("DROP EVENT " . idf_escape($EVENT), substr(ME, 0, -1), 'Event has been dropped.');
	} elseif (in_array($row["INTERVAL_FIELD"], $intervals) && isset($statuses[$row["STATUS"]])) {
		$schedule = "\nON SCHEDULE " . ($row["INTERVAL_VALUE"]
			? "EVERY " . q($row["INTERVAL_VALUE"]) . " $row[INTERVAL_FIELD]"
			. ($row["STARTS"] ? " STARTS " . q($row["STARTS"]) : "")
			. ($row["ENDS"] ? " ENDS " . q($row["ENDS"]) : "") //! ALTER EVENT doesn't drop ENDS - MySQL bug #39173
			: "AT " . q($row["STARTS"])
			) . " ON COMPLETION" . ($row["ON_COMPLETION"] ? "" : " NOT") . " PRESERVE"
		;
		
		queries_redirect(substr(ME, 0, -1), ($EVENT != "" ? 'Event has been altered.' : 'Event has been created.'), queries(($EVENT != ""
			? "ALTER EVENT " . idf_escape($EVENT) . $schedule
			. ($EVENT != $row["EVENT_NAME"] ? "\nRENAME TO " . idf_escape($row["EVENT_NAME"]) : "")
			: "CREATE EVENT " . idf_escape($row["EVENT_NAME"]) . $schedule
			) . "\n" . $statuses[$row["STATUS"]] . " COMMENT " . q($row["EVENT_COMMENT"])
			. rtrim(" DO\n$row[EVENT_DEFINITION]", ";") . ";"
		));
	}
}

page_header(($EVENT != "" ? 'Alter event' . ": " . h($EVENT) : 'Create event'), $error);

if (!$row && $EVENT != "") {
	$rows = get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . q(DB) . " AND EVENT_NAME = " . q($EVENT));
	$row = reset($rows);
}
?>

<form action="" method="post">
<table cellspacing="0" class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="<?php echo h($row["EVENT_NAME"]); ?>" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="<?php echo h("$row[EXECUTE_AT]$row[STARTS]"); ?>">
<tr><th title="datetime">End<td><input name="ENDS" value="<?php echo h($row["ENDS"]); ?>">
<tr><th>Every<td><input type="number" name="INTERVAL_VALUE" value="<?php echo h($row["INTERVAL_VALUE"]); ?>" class="size"> <?php echo html_select("INTERVAL_FIELD", $intervals, $row["INTERVAL_FIELD"]); ?>
<tr><th>Status<td><?php echo html_select("STATUS", $statuses, $row["STATUS"]); ?>
<tr><th>Comment<td><input name="EVENT_COMMENT" value="<?php echo h($row["EVENT_COMMENT"]); ?>" data-maxlength="64">
<tr><th><td><?php echo checkbox("ON_COMPLETION", "PRESERVE", $row["ON_COMPLETION"] == "PRESERVE", 'On completion preserve'); ?>
</table>
<p><?php textarea("EVENT_DEFINITION", $row["EVENT_DEFINITION"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($EVENT != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $EVENT));  } ?>
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
		'Routine has been dropped.',
		'Routine has been altered.',
		'Routine has been created.',
		$PROCEDURE,
		$row["name"]
	);
}

page_header(($PROCEDURE != "" ? (isset($_GET["function"]) ? 'Alter function' : 'Alter procedure') . ": " . h($PROCEDURE) : (isset($_GET["function"]) ? 'Create function' : 'Create procedure')), $error);

if (!$_POST && $PROCEDURE != "") {
	$row = routine($_GET["procedure"], $routine);
	$row["name"] = $PROCEDURE;
}

$collations = get_vals("SHOW CHARACTER SET");
sort($collations);
$routine_languages = routine_languages();
?>

<form action="" method="post" id="form">
<p>Name: <input name="name" value="<?php echo h($row["name"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo ($routine_languages ? 'Language' . ": " . html_select("language", $routine_languages, $row["language"]) . "\n" : ""); ?>
<input type="submit" value="Save">
<div class="scrollable">
<table cellspacing="0" class="nowrap">
<?php
edit_fields($row["fields"], $collations, $routine);
if (isset($_GET["function"])) {
	echo "<tr><td>" . 'Return type';
	edit_type("returns", $row["returns"], $collations, array(), ($jush == "pgsql" ? array("void", "trigger") : array()));
}
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p><?php textarea("definition", $row["definition"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($PROCEDURE != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $PROCEDURE));  } ?>
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
			query_redirect($drop, $location, 'Trigger has been dropped.');
		} else {
			if ($name != "") {
				queries($drop);
			}
			queries_redirect(
				$location,
				($name != "" ? 'Trigger has been altered.' : 'Trigger has been created.'),
				queries(create_trigger($on, $_POST))
			);
			if ($name != "") {
				queries(create_trigger($on, $row + array("Type" => reset($trigger_options["Type"]))));
			}
		}
	}
	$row = $_POST;
}

page_header(($name != "" ? 'Alter trigger' . ": " . h($name) : 'Create trigger'), $error, array("table" => $TABLE));
?>

<form action="" method="post" id="form">
<table cellspacing="0" class="layout">
<tr><th>Time<td><?php echo html_select("Timing", $trigger_options["Timing"], $row["Timing"], "triggerChange(/^" . preg_quote($TABLE, "/") . "_[ba][iud]$/, '" . js_escape($TABLE) . "', this.form);"); ?>
<tr><th>Event<td><?php echo html_select("Event", $trigger_options["Event"], $row["Event"], "this.form['Timing'].onchange();");  echo (in_array("UPDATE OF", $trigger_options["Event"]) ? " <input name='Of' value='" . h($row["Of"]) . "' class='hidden'>": ""); ?>
<tr><th>Type<td><?php echo html_select("Type", $trigger_options["Type"], $row["Type"]); ?>
</table>
<p>Name: <input name="Trigger" value="<?php echo h($row["Trigger"]); ?>" data-maxlength="64" autocapitalize="off">
<?php echo script("qs('#form')['Timing'].onchange();"); ?>
<p><?php textarea("Statement", $row["Statement"]); ?>
<p>
<input type="submit" value="Save">
<?php if ($name != "") { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', $name));  } ?>
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
		query_redirect("DROP USER $old_user", ME . "privileges=", 'User has been dropped.');
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

		queries_redirect(ME . "privileges=", (isset($_GET["host"]) ? 'User has been altered.' : 'User has been created.'), !$error);

		if ($created) {
			// delete new user in case of an error
			$connection->query("DROP USER $new_user");
		}
	}
}

page_header((isset($_GET["host"]) ? 'Username' . ": " . h("$USER@$_GET[host]") : 'Create user'), $error, array("privileges" => array('', 'Privileges')));

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
<tr><th>Server<td><input name="host" data-maxlength="60" value="<?php echo h($row["host"]); ?>" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="<?php echo h($row["user"]); ?>" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="<?php echo h($row["pass"]); ?>" autocomplete="new-password">
<?php if (!$row["hashed"]) { echo script("typePassword(qs('#pass'));"); }  echo (min_version(8) ? "" : checkbox("hashed", 1, $row["hashed"], 'Hashed', "typePassword(this.form['pass'], this.checked);")); ?>
</table>

<?php
//! MAX_* limits, REQUIRE
echo "<table cellspacing='0'>\n";
echo "<thead><tr><th colspan='2'>" . 'Privileges' . doc_link(array('sql' => "grant.html#priv_level"));
$i = 0;
foreach ($grants as $object => $grant) {
	echo '<th>' . ($object != "*.*" ? "<input name='objects[$i]' value='" . h($object) . "' size='10' autocapitalize='off'>" : "<input type='hidden' name='objects[$i]' value='*.*' size='10'>*.*"); //! separate db, table, columns, PROCEDURE|FUNCTION, routine
	$i++;
}
echo "</thead>\n";

foreach (array(
	"" => "",
	"Server Admin" => 'Server',
	"Databases" => 'Database',
	"Tables" => 'Table',
	"Columns" => 'Column',
	"Procedures" => 'Routine',
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
				echo "<td><select name=$name><option><option value='1'" . ($value ? " selected" : "") . ">" . 'Grant' . "<option value='0'" . ($value == "0" ? " selected" : "") . ">" . 'Revoke' . "</select>";
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
<input type="submit" value="Save">
<?php if (isset($_GET["host"])) { ?><input type="submit" name="drop" value="Drop"><?php echo confirm(sprintf('Drop %s?', "$USER@$_GET[host]"));  } ?>
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
		queries_redirect(ME . "processlist=", lang(array('%d process has been killed.', '%d processes have been killed.'), $killed), $killed || !$_POST["kill"]);
	}
}

page_header('Process list', $error);
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
			? "<code class='jush-$jush'>" . shorten_utf8($val, 100, "</code>") . ' <a href="' . h(ME . ($row["db"] != "" ? "db=" . urlencode($row["db"]) . "&" : "") . "sql=" . urlencode($val)) . '">' . 'Clone' . '</a>'
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
	echo ($i + 1) . "/" . sprintf('%d in total', max_connections());
	echo "<p><input type='submit' value='" . 'Kill' . "'>\n";
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
			$message = lang(array('%d item has been affected.', '%d items have been affected.'), $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id();
				if ($last_id) {
					$message = sprintf('Item%s has been inserted.', " $last_id");
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
				$error = 'Ctrl+click on a value to modify it.';
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
				queries_redirect(remove_from_uri(), lang(array('%d item has been affected.', '%d items have been affected.'), $affected), $result);
			}

		} elseif (!is_string($file = get_file("csv_file", true))) {
			$error = upload_error($file);
		} elseif (!preg_match('~~u', $file)) {
			$error = 'File must be in UTF-8 encoding.';
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
			queries_redirect(remove_from_uri("page"), lang(array('%d row has been imported.', '%d rows have been imported.'), $affected), $result);
			$driver->rollback(); // after queries_redirect() to not overwrite error

		}
	}
}

$table_name = $adminer->tableName($table_status);
if (is_ajax()) {
	page_headers();
	ob_start();
} else {
	page_header('Select' . ": $table_name", $error);
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
	echo "<p class='error'>" . 'Unable to select the table' . ($fields ? "." : ": " . error()) . "\n";
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
			echo "<p class='message'>" . 'No rows.' . "\n";
		} else {
			$backward_keys = $adminer->backwardKeys($TABLE, $table_name);

			echo "<div class='scrollable'>";
			echo "<table id='table' cellspacing='0' class='nowrap checkable'>";
			echo script("mixin(qs('#table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true), onkeydown: editingKeydown});");
			echo "<thead><tr>" . (!$group && $select
				? ""
				: "<td><input type='checkbox' id='all-page' class='jsonly'>" . script("qs('#all-page').onclick = partial(formCheck, /check/);", "")
					. " <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . 'Modify' . "</a>");
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
						echo "<a href='" . h($href . $desc) . "' title='" . 'descending' . "' class='text'> ↓</a>";
						if (!$val["fun"]) {
							echo '<a href="#fieldset-search" title="' . 'Search' . '" class="text jsonly"> =</a>';
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

			echo ($backward_keys ? "<th>" . 'Relations' : "") . "</thead>\n";

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
					. ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "' class='edit'>" . 'edit' . "</a>")
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
								. ($editable ? "" : " data-warning='" . h('Use edit link to modify this value.') . "'")
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
						? '<p><a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" class="loadmore">' . 'Load more data' . '</a>'
							. script("qsl('a').onclick = partial(selectLoadMore, " . (+$limit) . ", '" . 'Loading' . "…');", "")
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
						echo "<legend><a href='" . h(remove_from_uri("page")) . "'>" . 'Page' . "</a></legend>";
						echo script("qsl('a').onclick = function () { pageClick(this.href, +prompt('" . 'Page' . "', '" . ($page + 1) . "')); return false; };");
						echo pagination(0, $page) . ($page > 5 ? " …" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " …" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . 'last' . "</a>"
							);
						}
					} else {
						echo "<legend>" . 'Page' . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " …" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " …" : "") : "");
					}
					echo "</fieldset>\n";
				}
				
				echo "<fieldset>";
				echo "<legend>" . 'Whole result' . "</legend>";
				$display_rows = ($exact_count ? "" : "~ ") . $found_rows;
				echo checkbox("all", 1, 0, ($found_rows !== false ? ($exact_count ? "" : "~ ") . lang(array('%d row', '%d rows'), $found_rows) : ""), "var checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$display_rows' : checked); selectCount('selected2', this.checked || !checked ? '$display_rows' : checked);") . "\n";
				echo "</fieldset>\n";

				if ($adminer->selectCommandPrint()) {
					?>
<fieldset<?php echo ($_GET["modify"] ? '' : ' class="jsonly"'); ?>><legend>Modify</legend><div>
<input type="submit" value="Save"<?php echo ($_GET["modify"] ? '' : ' title="' . 'Ctrl+click on a value to modify it.' . '"'); ?>>
</div></fieldset>
<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type="submit" name="edit" value="Edit">
<input type="submit" name="clone" value="Clone">
<input type="submit" name="delete" value="Delete"><?php echo confirm(); ?>
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
					print_fieldset("export", 'Export' . " <span id='selected2'></span>");
					$output = $adminer->dumpOutput();
					echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
					echo html_select("format", $format, $adminer_import["format"]);
					echo " <input type='submit' name='export' value='" . 'Export' . "'>\n";
					echo "</div></fieldset>\n";
				}

				$adminer->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
			}

			echo "</div></div>\n";

			if ($adminer->selectImportPrint()) {
				echo "<div>";
				echo "<a href='#import'>" . 'Import' . "</a>";
				echo script("qsl('a').onclick = partial(toggle, 'import');", "");
				echo "<span id='import' class='hidden'>: ";
				echo "<input type='file' name='csv_file'> ";
				echo html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"], 1); // 1 - select
				echo " <input type='submit' name='import' value='" . 'Import' . "'>";
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
page_header($status ? 'Status' : 'Variables');

$variables = ($status ? show_status() : show_variables());
if (!$variables) {
	echo "<p class='message'>" . 'No rows.' . "\n";
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
		$message = 'Tables have been truncated.';
	} elseif ($_POST["move"]) {
		$result = move_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = 'Tables have been moved.';
	} elseif ($_POST["copy"]) {
		$result = copy_tables((array) $_POST["tables"], (array) $_POST["views"], $_POST["target"]);
		$message = 'Tables have been copied.';
	} elseif ($_POST["drop"]) {
		if ($_POST["views"]) {
			$result = drop_views($_POST["views"]);
		}
		if ($result && $_POST["tables"]) {
			$result = drop_tables($_POST["tables"]);
		}
		$message = 'Tables have been dropped.';
	} elseif ($jush != "sql") {
		$result = ($jush == "sqlite"
			? queries("VACUUM")
			: apply_queries("VACUUM" . ($_POST["optimize"] ? "" : " ANALYZE"), $_POST["tables"])
		);
		$message = 'Tables have been optimized.';
	} elseif (!$_POST["tables"]) {
		$message = 'No tables.';
	} elseif ($result = queries(($_POST["optimize"] ? "OPTIMIZE" : ($_POST["check"] ? "CHECK" : ($_POST["repair"] ? "REPAIR" : "ANALYZE"))) . " TABLE " . implode(", ", array_map('idf_escape', $_POST["tables"])))) {
		while ($row = $result->fetch_assoc()) {
			$message .= "<b>" . h($row["Table"]) . "</b>: " . h($row["Msg_text"]) . "<br>";
		}
	}

	queries_redirect(substr(ME, 0, -1), $message, $result);
}

page_header(($_GET["ns"] == "" ? 'Database' . ": " . h(DB) : 'Schema' . ": " . h($_GET["ns"])), $error, true);

if ($adminer->homepage()) {
	if ($_GET["ns"] !== "") {
		echo "<h3 id='tables-views'>" . 'Tables and views' . "</h3>\n";
		$tables_list = tables_list();
		if (!$tables_list) {
			echo "<p class='message'>" . 'No tables.' . "\n";
		} else {
			echo "<form action='' method='post'>\n";
			if (support("table")) {
				echo "<fieldset><legend>" . 'Search data in tables' . " <span id='selected2'></span></legend><div>";
				echo "<input type='search' name='query' value='" . h($_POST["query"]) . "'>";
				echo script("qsl('input').onkeydown = partialArg(bodyKeydown, 'search');", "");
				echo " <input type='submit' name='search' value='" . 'Search' . "'>\n";
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
			echo '<th>' . 'Table';
			echo '<td>' . 'Engine' . doc_link(array('sql' => 'storage-engines.html'));
			echo '<td>' . 'Collation' . doc_link(array('sql' => 'charset-charsets.html', 'mariadb' => 'supported-character-sets-and-collations/'));
			echo '<td>' . 'Data Length' . doc_link(array('sql' => 'show-table-status.html',  ));
			echo '<td>' . 'Index Length' . doc_link(array('sql' => 'show-table-status.html', ));
			echo '<td>' . 'Data Free' . doc_link(array('sql' => 'show-table-status.html'));
			echo '<td>' . 'Auto Increment' . doc_link(array('sql' => 'example-auto-increment.html', 'mariadb' => 'auto_increment/'));
			echo '<td>' . 'Rows' . doc_link(array('sql' => 'show-table-status.html',  ));
			echo (support("comment") ? '<td>' . 'Comment' . doc_link(array('sql' => 'show-table-status.html', )) : '');
			echo "</thead>\n";

			$tables = 0;
			foreach ($tables_list as $name => $type) {
				$view = ($type !== null && !preg_match('~table|sequence~i', $type));
				$id = h("Table-" . $name);
				echo '<tr' . odd() . '><td>' . checkbox(($view ? "views[]" : "tables[]"), $name, in_array($name, $tables_views, true), "", "", "", $id);
				echo '<th>' . (support("table") || support("indexes") ? "<a href='" . h(ME) . "table=" . urlencode($name) . "' title='" . 'Show structure' . "' id='$id'>" . h($name) . '</a>' : h($name));
				if ($view) {
					echo '<td colspan="6"><a href="' . h(ME) . "view=" . urlencode($name) . '" title="' . 'Alter view' . '">' . (preg_match('~materialized~i', $type) ? 'Materialized view' : 'View') . '</a>';
					echo '<td align="right"><a href="' . h(ME) . "select=" . urlencode($name) . '" title="' . 'Select data' . '">?</a>';
				} else {
					foreach (array(
						"Engine" => array(),
						"Collation" => array(),
						"Data_length" => array("create", 'Alter table'),
						"Index_length" => array("indexes", 'Alter indexes'),
						"Data_free" => array("edit", 'New item'),
						"Auto_increment" => array("auto_increment=1&create", 'Alter table'),
						"Rows" => array("select", 'Select data'),
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

			echo "<tr><td><th>" . sprintf('%d in total', count($tables_list));
			echo "<td>" . h($jush == "sql" ? $connection->result("SELECT @@default_storage_engine") : "");
			echo "<td>" . h(db_collation(DB, collations()));
			foreach (array("Data_length", "Index_length", "Data_free") as $key) {
				echo "<td align='right' id='sum-$key'>";
			}

			echo "</table>\n";
			echo "</div>\n";
			if (!information_schema(DB)) {
				echo "<div class='footer'><div>\n";
				$vacuum = "<input type='submit' value='" . 'Vacuum' . "'> " . on_help("'VACUUM'");
				$optimize = "<input type='submit' name='optimize' value='" . 'Optimize' . "'> " . on_help($jush == "sql" ? "'OPTIMIZE TABLE'" : "'VACUUM OPTIMIZE'");
				echo "<fieldset><legend>" . 'Selected' . " <span id='selected'></span></legend><div>"
				. ($jush == "sqlite" ? $vacuum
				: ($jush == "pgsql" ? $vacuum . $optimize
				: ($jush == "sql" ? "<input type='submit' value='" . 'Analyze' . "'> " . on_help("'ANALYZE TABLE'") . $optimize
					. "<input type='submit' name='check' value='" . 'Check' . "'> " . on_help("'CHECK TABLE'")
					. "<input type='submit' name='repair' value='" . 'Repair' . "'> " . on_help("'REPAIR TABLE'")
				: "")))
				. "<input type='submit' name='truncate' value='" . 'Truncate' . "'> " . on_help($jush == "sqlite" ? "'DELETE'" : "'TRUNCATE" . ($jush == "pgsql" ? "'" : " TABLE'")) . confirm()
				. "<input type='submit' name='drop' value='" . 'Drop' . "'>" . on_help("'DROP TABLE'") . confirm() . "\n";
				$databases = (support("scheme") ? $adminer->schemas() : $adminer->databases());
				if (count($databases) != 1 && $jush != "sqlite") {
					$db = (isset($_POST["target"]) ? $_POST["target"] : (support("scheme") ? $_GET["ns"] : DB));
					echo "<p>" . 'Move to other database' . ": ";
					echo ($databases ? html_select("target", $databases, $db) : '<input name="target" value="' . h($db) . '" autocapitalize="off">');
					echo " <input type='submit' name='move' value='" . 'Move' . "'>";
					echo (support("copy") ? " <input type='submit' name='copy' value='" . 'Copy' . "'> " . checkbox("overwrite", 1, $_POST["overwrite"], 'overwrite') : "");
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

		echo '<p class="links"><a href="' . h(ME) . 'create=">' . 'Create table' . "</a>\n";
		echo (support("view") ? '<a href="' . h(ME) . 'view=">' . 'Create view' . "</a>\n" : "");

		if (support("routine")) {
			echo "<h3 id='routines'>" . 'Routines' . "</h3>\n";
			$routines = routines();
			if ($routines) {
				echo "<table cellspacing='0'>\n";
				echo '<thead><tr><th>' . 'Name' . '<td>' . 'Type' . '<td>' . 'Return type' . "<td></thead>\n";
				odd('');
				foreach ($routines as $row) {
					$name = ($row["SPECIFIC_NAME"] == $row["ROUTINE_NAME"] ? "" : "&name=" . urlencode($row["ROUTINE_NAME"])); // not computed on the pages to be able to print the header first
					echo '<tr' . odd() . '>';
					echo '<th><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'callf=' : 'call=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . h($row["ROUTINE_NAME"]) . '</a>';
					echo '<td>' . h($row["ROUTINE_TYPE"]);
					echo '<td>' . h($row["DTD_IDENTIFIER"]);
					echo '<td><a href="' . h(ME . ($row["ROUTINE_TYPE"] != "PROCEDURE" ? 'function=' : 'procedure=') . urlencode($row["SPECIFIC_NAME"]) . $name) . '">' . 'Alter' . "</a>";
				}
				echo "</table>\n";
			}
			echo '<p class="links">'
				. (support("procedure") ? '<a href="' . h(ME) . 'procedure=">' . 'Create procedure' . '</a>' : '')
				. '<a href="' . h(ME) . 'function=">' . 'Create function' . "</a>\n"
			;
		}





		if (support("event")) {
			echo "<h3 id='events'>" . 'Events' . "</h3>\n";
			$rows = get_rows("SHOW EVENTS");
			if ($rows) {
				echo "<table cellspacing='0'>\n";
				echo "<thead><tr><th>" . 'Name' . "<td>" . 'Schedule' . "<td>" . 'Start' . "<td>" . 'End' . "<td></thead>\n";
				foreach ($rows as $row) {
					echo "<tr>";
					echo "<th>" . h($row["Name"]);
					echo "<td>" . ($row["Execute at"] ? 'At given time' . "<td>" . $row["Execute at"] : 'Every' . " " . $row["Interval value"] . " " . $row["Interval field"] . "<td>$row[Starts]");
					echo "<td>$row[Ends]";
					echo '<td><a href="' . h(ME) . 'event=' . urlencode($row["Name"]) . '">' . 'Alter' . '</a>';
				}
				echo "</table>\n";
				$event_scheduler = $connection->result("SELECT @@event_scheduler");
				if ($event_scheduler && $event_scheduler != "ON") {
					echo "<p class='error'><code class='jush-sqlset'>event_scheduler</code>: " . h($event_scheduler) . "\n";
				}
			}
			echo '<p class="links"><a href="' . h(ME) . 'event=">' . 'Create event' . "</a>\n";
		}

		if ($tables_list) {
			echo script("ajaxSetHtml('" . js_escape(ME) . "script=db');");
		}
	}
}

}

// each page calls its own page_header(), if the footer should not be called then the page exits
page_footer();
