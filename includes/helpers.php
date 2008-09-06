<?php
	/**
	 * File: Helpers
	 * Various functions used throughout Chyrp's code.
	 */

	# Integer: $time_start
	# Times Chyrp.
	$time_start = 0;

	/**
	 * Function: session
	 * Begins Chyrp's custom session storage whatnots.
	 */
	function session() {
		session_set_save_handler(array("Session", "open"),
		                         array("Session", "close"),
		                         array("Session", "read"),
		                         array("Session", "write"),
		                         array("Session", "destroy"),
		                         array("Session", "gc"));
		$domain = (substr_count($_SERVER['HTTP_HOST'], ".")) ? preg_replace("/^www\./", ".", $_SERVER['HTTP_HOST']) : null ;
		session_set_cookie_params(60 * 60 * 24 * 30, "/", $domain);
		session_name("ChyrpSession");
		session_start();
	}

	/**
	 * Function: error
	 * Shows an error message.
	 *
	 * Parameters:
	 *     $title - The title for the error dialog.
	 *     $body - The message for the error dialog.
	 */
	function error($title, $body) {
		# Clear all output sent before this error.
		if (($foo = ob_get_contents()) !== false) {
			ob_end_clean();

			# Since the header might already be set to gzip, start output buffering again.
			if (extension_loaded("zlib") and !ini_get("zlib.output_compression") and
				isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
			    substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip")) {
				ob_start("ob_gzhandler");
				header("Content-Encoding: gzip");
			} else
				ob_start();
		} else {
			# If output buffering is not started, assume this
			# is sent from the Session class or somewhere deep.
			error_log($title.": ".$body);
			exit;
		}

		if (TESTER)
			exit("ERROR: ".$body);

		# Display the error.
		if (class_exists("Theme") and Theme::current()->file_exists("pages/error"))
			Theme::current()->load("pages/error", array("title" => $title, "body" => $body));
		else
			require INCLUDES_DIR."/error.php";

		if ($foo !== false)
			ob_end_flush();

		exit;
	}

	/**
	 * Function: show_403
	 * Shows an error message with a 403 status.
	 *
	 * Parameters:
	 *     $title - The title for the error dialog.
	 *     $body - The message for the error dialog.
	 */
	function show_403($title, $body) {
		header("Status: 403");
		error($title, $body);
	}

	/**
	 * Function: logged_in
	 * Returns whether or not they are logged in by returning the <Visitor.$id> (which defaults to 0).
	 */
	function logged_in() {
		return Visitor::current()->id != 0;
	}

	/**
	 * Function: load_translator
	 * Loads a .mo file for gettext translation.
	 *
	 * Parameters:
	 *     $domain - The name for this translation domain.
	 *     $mofile - The .mo file to read from.
	 */
	function load_translator($domain, $mofile) {
		global $l10n;

		if (isset($l10n[$domain]))
			return;

		if (is_readable($mofile))
			$input = new CachedFileReader($mofile);
		else
			return;

		$l10n[$domain] = new gettext_reader($input);
	}

	/**
	 * Function: __
	 * Returns a translated string.
	 *
	 * Parameters:
	 *     $text - The string to translate.
	 *     $domain - The translation domain to read from.
	 */
	function __($text, $domain = "chyrp") {
		global $l10n;
		return (isset($l10n[$domain])) ? $l10n[$domain]->translate($text) : $text ;
	}

	/**
	 * Function: _p
	 * Returns a plural (or not) form of a translated string.
	 *
	 * Parameters:
	 *     $single - Singular string.
	 *     $plural - Pluralized string.
	 *     $number - The number to judge by.
	 *     $domain - The translation domain to read from.
	 */
	function _p($single, $plural, $number, $domain = "chyrp") {
		global $l10n;
		return (isset($l10n[$domain])) ?
		       $l10n[$domain]->ngettext($single, $plural, $number) :
		       (($number != 1) ? $plural : $single) ;
	}

	/**
	 * Function: _f
	 * Returns a formatted translated string.
	 */
	function _f($string, $args = array(), $domain = "chyrp") {
		array_unshift($args, __($string, $domain));
		return call_user_func_array("sprintf", $args);
	}

	/**
	 * Function: redirect
	 * Redirects to the given URL and exits immediately.
	 */
	function redirect($url, $use_chyrp_url = false) {
		# Handle URIs without domain
		if ($url[0] == "/")
			$url = (ADMIN or $use_chyrp_url) ?
			       Config::current()->chyrp_url.$url :
			       Config::current()->url.$url ;
		elseif (class_exists("Route") and !substr_count($url, "://"))
			$url = url($url);

		header("Location: ".html_entity_decode($url));
		exit;
	}

	/**
	 * Function: url
	 * Mask for Route->url().
	 */
	function url($url) {
		return Route::current()->url($url);
	}

	/**
	 * Function: pluralize
	 * Returns a pluralized string. This is a port of Rails's pluralizer.
	 *
	 * Parameters:
	 *     $string - The string to pluralize.
	 */
	function pluralize($string) {
		$uncountable = array("moose", "sheep", "fish", "series", "species",
		                     "rice", "money", "information", "equipment", "piss");

		if (in_array($string, $uncountable))
			return $string;

		$replacements = array("/person/i" => "people",
		                      "/man/i" => "men",
		                      "/child/i" => "children",
		                      "/cow/i" => "kine",
		                      "/goose/i" => "geese",
		                      "/(penis)$/i" => "\\1es", # Take that, Rails!
		                      "/(ax|test)is$/i" => "\\1es",
		                      "/(octop|vir)us$/i" => "\\1ii",
		                      "/(cact)us$/i" => "\\1i",
		                      "/(alias|status)$/i" => "\\1es",
		                      "/(bu)s$/i" => "\\1ses",
		                      "/(buffal|tomat)o$/i" => "\\1oes",
		                      "/([ti])um$/i" => "\\1a",
		                      "/sis$/i" => "ses",
		                      "/(hive)$/i" => "\\1s",
		                      "/([^aeiouy]|qu)y$/i" => "\\1ies",
		                      "/^(ox)$/i" => "\\1en",
		                      "/(matr|vert|ind)(?:ix|ex)$/i" => "\\1ices",
		                      "/(x|ch|ss|sh)$/i" => "\\1es",
		                      "/([m|l])ouse$/i" => "\\1ice",
		                      "/(quiz)$/i" => "\\1zes");

		$replaced = preg_replace(array_keys($replacements), array_values($replacements), $string, 1);

		if ($replaced == $string)
			return $string."s";
		else
			return $replaced;
	}

	/**
	 * Function: depluralize
	 * Returns a depluralized string. This is the inverse of <pluralize>.
	 *
	 * Parameters:
	 *     $string - The string to depluralize.
	 */
	function depluralize($string) {
		$replacements = array("/people/i" => "person",
		                      "/^men/i" => "man",
		                      "/children/i" => "child",
		                      "/kine/i" => "cow",
		                      "/geese/i" => "goose",
		                      "/(penis)es$/i" => "\\1",
		                      "/(ax|test)es$/i" => "\\1is",
		                      "/(octopi|viri|cact)i$/i" => "\\1us",
		                      "/(alias|status)es$/i" => "\\1",
		                      "/(bu)ses$/i" => "\\1s",
		                      "/(buffal|tomat)oes$/i" => "\\1o",
		                      "/([ti])a$/i" => "\\1um",
		                      "/ses$/i" => "sis",
		                      "/(hive)s$/i" => "\\1",
		                      "/([^aeiouy]|qu)ies$/i" => "\\1y",
		                      "/^(ox)en$/i" => "\\1",
		                      "/(vert|ind)ices$/i" => "\\1ex",
		                      "/(matr)ices$/i" => "\\1ix",
		                      "/(x|ch|ss|sh)es$/i" => "\\1",
		                      "/([ml])ice$/i" => "\\1ouse",
		                      "/(quiz)zes$/i" => "\\1");

		$replaced = preg_replace(array_keys($replacements), array_values($replacements), $string, 1);

		if ($replaced == $string and substr($string, -1) == "s")
			return substr($string, 0, -1);
		else
			return $replaced;
	}

	/**
	 * Function: truncate
	 * Truncates a string to the passed length, appending an ellipsis to the end.
	 *
	 * Parameters:
	 *     $text - String to shorten.
	 *     $numb - Length of the shortened string.
	 *     $keep_words - Whether or not to keep words in-tact.
	 *     $minimum - If the truncated string is less than this and $keep_words is true, it will act as if $keep_words is false.
	 */
	function truncate($text, $numb = 50, $keep_words = true, $minimum = 10) {
		# Entities only represent one character when rendered, so treat them as one character.
		preg_match_all("/&([^\s;]+);/", $text, $entities);
		foreach ($entities[0] as $entity)
			$numb += strlen($entity) - 1;

		$original = $text;
		$numb -= 3;
		if (strlen($text) > $numb) {
			if (function_exists('mb_strcut')) {
				if ($keep_words) {
					$text = mb_strcut($text, 0, $numb, "utf-8");
					$text = mb_strcut($text, 0 , strrpos($text, " "), "utf-8");

					if (strlen($text) < $minimum)
						$text = mb_strcut($original, 0, $numb, "utf-8");

					$text.= "...";
				} else {
					$text = mb_strcut($text, 0, $numb, "utf-8")."...";
				}
			} else {
				if ($keep_words) {
					$text = substr($text, 0, $numb);
					$text = substr($text, 0 , strrpos($text, " "));

					if (strlen($text) < $minimum)
						$text = substr($text, 0, $numb);

					$text.= "...";
				} else {
					$text = substr($text, 0, $numb)."...";
				}
			}
		}
		return $text;
	}

	/**
	 * Function: when
	 * Returns date formatting for a string that isn't a regular time() value
	 *
	 * Parameters:
	 *     $formatting - The formatting for date().
	 *     $time - The string to convert to time (typically a datetime).
	 *     $strftime - Use `strftime` instead of `date`?
	 */
	function when($formatting, $when, $strftime = false) {
		$time = (is_numeric($when)) ? $when : strtotime($when) ;

		if ($strftime)
			return strftime($formatting, $time);
		else
			return date($formatting, $time);
	}

	/**
	 * Function: datetime
	 * Returns a standard datetime string based on either the passed timestamp or their time offset, usually for MySQL inserts.
	 *
	 * Parameters:
	 *     $when - An optional timestamp.
	 */
	function datetime($when = null) {
		fallback($when, time());

		$time = (is_numeric($when)) ? $when : strtotime($when) ;

		return date("Y-m-d H:i:s", $time);
	}

	/**
	 * Function: fix
	 * Returns a HTML-sanitized version of a string.
	 */
	function fix($string, $quotes = false, $decode_first = true) {
		$quotes = ($quotes) ? ENT_QUOTES : ENT_NOQUOTES ;

		if ($decode_first)
			$string = html_entity_decode($string, ENT_QUOTES, "utf-8");

		return htmlspecialchars($string, $quotes, "utf-8");
	}

	/**
	 * Function: unfix
	 * Returns the reverse of fix().
	 */
	function unfix($string) {
		return html_entity_decode($string, ENT_QUOTES, "utf-8");
	}

	/**
	 * Function: lang_code
	 * Returns the passed language code (e.g. en_US) to the human-readable text (e.g. English (US))
	 *
	 * Parameters:
	 *     $code - The language code to convert
	 *
	 * Credits:
	 *     This is from TextPattern, modified to match Chyrp's language code formatting.
	 */
	function lang_code($code) {
		$langs = array("ar_DZ" => "جزائري عربي",
		               "ca_ES" => "Català",
		               "cs_CZ" => "Čeština",
		               "da_DK" => "Dansk",
		               "de_DE" => "Deutsch",
		               "el_GR" => "Ελληνικά",
		               "en_GB" => "English (GB)",
		               "en_US" => "English (US)",
		               "es_ES" => "Español",
		               "et_EE" => "Eesti",
		               "fi_FI" => "Suomi",
		               "fr_FR" => "Français",
		               "gl_GZ" => "Galego (Galiza)",
		               "he_IL" => "עברית",
		               "hu_HU" => "Magyar",
		               "id_ID" => "Bahasa Indonesia",
		               "is_IS" => "Íslenska",
		               "it_IT" => "Italiano",
		               "ja_JP" => "日本語",
		               "lv_LV" => "Latviešu",
		               "nl_NL" => "Nederlands",
		               "no_NO" => "Norsk",
		               "pl_PL" => "Polski",
		               "pt_PT" => "Português",
		               "ro_RO" => "Română",
		               "ru_RU" => "Русский",
		               "sk_SK" => "Slovenčina",
		               "sv_SE" => "Svenska",
		               "th_TH" => "ไทย",
		               "uk_UA" => "Українська",
		               "vi_VN" => "Tiếng Việt",
		               "zh_CN" => "中文(简体)",
		               "zh_TW" => "中文(繁體)",
		               "bg_BG" => "Български");
		return (isset($langs[$code])) ? str_replace(array_keys($langs), array_values($langs), $code) : $code ;
	}

	/**
	 * Function: sanitize
	 * Returns a sanitized string, typically for URLs.
	 *
	 * Parameters:
	 *     $string - The string to sanitize.
	 *     $anal - If set to *true*, will remove all non-alphanumeric characters.
	 */
	function sanitize($string, $force_lowercase = true, $anal = false) {
		$strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
		               "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
		               "—", "–", ",", "<", ".", ">", "/", "?");
		$clean = trim(str_replace($strip, "", strip_tags($string)));
		$clean = preg_replace('/\s+/', "-", $clean);
		$clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
		return ($force_lowercase) ?
			(function_exists('mb_strtolower')) ?
				mb_strtolower($clean, 'UTF-8') :
				strtolower($clean) :
			$clean;
	}

	/**
	 * Function: trackback_respond
	 * Responds to a trackback request.
	 *
	 * Parameters:
	 *     $error - Is this an error?
	 *     $message - Message to return.
	 */
	function trackback_respond($error = false, $message = "") {
		header("Content-Type: text/xml; charset=utf-8");
		if ($error) {
			echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
			echo "<response>\n";
			echo "<error>1</error>\n";
			echo "<message>".$message."</message>\n";
			echo "</response>";
			exit;
		} else {
			echo '<?xml version="1.0" encoding="utf-8"?'.">\n";
			echo "<response>\n";
			echo "<error>0</error>\n";
			echo "</response>";
		}
		exit;
	}

	/**
	 * Function: trackback_send
	 * Sends a trackback request.
	 *
	 * Parameters:
	 *     $post - The post we're sending from.
	 *     $target - The URL we're sending to.
	 */
	function trackback_send($post, $target) {
		if (empty($target)) return false;

		$target = parse_url($target);
		$title = $post->title();
		fallback($title, ucfirst($post->feather)." Post #".$post->id);
		$excerpt = strip_tags(truncate($post->excerpt(), 255));

		if (!empty($target["query"])) $target["query"] = "?".$target["query"];
		if (empty($target["port"])) $target["port"] = 80;

		$connect = fsockopen($target["host"], $target["port"]);
		if (!$connect) return false;

		$config = Config::current();
		$query = "url=".rawurlencode($post->url())."&".
		         "title=".rawurlencode($title)."&".
		         "blog_name=".rawurlencode($config->name)."&".
		         "excerpt=".rawurlencode($excerpt);

		fwrite($connect, "POST ".$target["path"].$target["query"]." HTTP/1.1\n");
		fwrite($connect, "Host: ".$target["host"]."\n");
		fwrite($connect, "Content-type: application/x-www-form-urlencoded\n");
		fwrite($connect, "Content-length: ". strlen($query)."\n");
		fwrite($connect, "Connection: close\n\n");
		fwrite($connect, $query);

		fclose($connect);

		return true;
	}

	/**
	 * Function: send_pingbacks
	 * Sends pingback requests to the URLs in a string.
	 *
	 * Parameters:
	 *     $string - The string to crawl for pingback URLs.
	 *     $post - The post we're sending from.
	 */
	function send_pingbacks($string, $post) {
		foreach (grab_urls($string) as $url)
			if ($ping_url = pingback_url($url)) {
				if (!class_exists("IXR_Client"))
					require INCLUDES_DIR."/lib/ixr.php";

				$client = new IXR_Client($ping_url);
				$client->timeout = 3;
				$client->useragent.= " -- Chyrp/".CHYRP_VERSION;
				$client->query("pingback.ping", $post->url(), $url);
			}
	}

	/**
	 * Function: grab_urls
	 * Crawls a string for links.
	 *
	 * Parameters:
	 *     $string - The string to crawl.
	 *
	 * Returns:
	 *     $matches[] - An array of all URLs found in the string.
	 */
	function grab_urls($string) {
		$regexp = "/<a[^>]+href=[\"|']([^\"]+)[\"|']>[^<]+<\/a>/";
		preg_match_all(Trigger::current()->filter($regexp, "link_regexp"), stripslashes($string), $matches);
		$matches = $matches[1];
		return $matches;
	}

	/**
	 * Function: pingback_url
	 * Checks if a URL is pingback-capable.
	 *
	 * Parameters:
	 *     $url - The URL to check.
	 *
	 * Returns:
	 *     $url - The pingback target, if the URL is pingback-capable.
	 */
	function pingback_url($url) {
		extract(parse_url($url), EXTR_SKIP);
		if (!isset($host)) return false;

		$path = (!isset($path)) ? '/' : $path ;
		if (isset($query)) $path.= '?'.$query;
		$port = (isset($port)) ? $port : 80 ;

		# Connect
		$connect = @fsockopen($host, $port, $errno, $errstr, 2);
		if (!$connect) return false;

		# Send the GET headers
		fwrite($connect, "GET $path HTTP/1.1\r\n");
		fwrite($connect, "Host: $host\r\n");
		fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION."\r\n\r\n");

		# Check for X-Pingback header
		$headers = "";
		while (!feof($connect)) {
			$line = fgets($connect, 512);
			if (trim($line) == "") break;
			$headers.= trim($line)."\n";

			if (preg_match("/X-Pingback: (.+)/i", $line, $matches))
				return trim($matches[1]);

			# Nothing's found so far, so grab the content-type
			# for the <link> search afterwards
			if (preg_match("/Content-Type: (.+)/i", $headers, $matches))
				$content_type = trim($matches[1]);
		}

		# No header found, check for <link>
		if (preg_match('/(image|audio|video|model)/i', $content_type)) return false;
		$size = 0;
		while (!feof($connect)) {
			$line = fgets($connect, 1024);
			if (preg_match("/<link rel=[\"|']pingback[\"|'] href=[\"|']([^\"]+)[\"|'] ?\/?>/i", $line, $link))
				return $link[1];
			$size += strlen($line);
			if ($size > 2048) return false;
		}

		fclose($connect);

		return false;
	}

	/**
	 * Function: camelize
	 * Converts a given string to camel-case.
	 *
	 * Parameters:
	 *     $string - The string to camelize.
	 *     $keep_spaces - Whether or not to convert underscores to spaces or remove them.
	 *
	 * Returns:
	 *     A CamelCased string.
	 */
	function camelize($string, $keep_spaces = false) {
		$lower = strtolower($string);
		$deunderscore = str_replace("_", " ", $lower);
		$dehyphen = str_replace("-", " ", $deunderscore);
		$final = ucwords($dehyphen);

		if (!$keep_spaces)
			$final = str_replace(" ", "", $final);

		return $final;
	}

	/**
	 * Function: decamelize
	 * Decamelizes a string.
	 *
	 * Parameters:
	 *     $string - The string to decamelize.
	 *
	 * Returns:
	 *     A de_camel_cased string.
	 *
	 * See Also:
	 * <camelize>
	 */
	function decamelize($string) {
		return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $string));
	}

	/**
	 * Function: selected
	 * If $val1 == $val2, outputs ' selected="selected"'
	 */
	function selected($val1, $val2, $return = false) {
		if ($val1 == $val2)
			if ($return)
				return ' selected="selected"';
			else
				echo ' selected="selected"';
	}

	/**
	 * Function: checked
	 * If $val == 1 (true), outputs ' checked="checked"'
	 */
	function checked($val) {
		if ($val == 1) echo ' checked="checked"';
	}

	/**
	 * Function: module_enabled
	 * Returns whether the given module is enabled or not.
	 *
	 * Parameters:
	 *     $name - The folder name of the module.
	 */
	function module_enabled($name) {
		$config = Config::current();
		return in_array($name, $config->enabled_modules);
	}

	/**
	 * Function: feather_enabled
	 * Returns whether the given feather is enabled or not.
	 *
	 * Parameters:
	 *     $name - The folder name of the feather.
	 */
	function feather_enabled($name) {
		$config = Config::current();
		return in_array($name, $config->enabled_feathers);
	}

	/**
	 * Function: fallback
	 * Gracefully falls back a given variable if it's empty or not set.
	 *
	 * Parameters:
	 *     &$variable - The variable to check for.
	 *     $fallback - What to set if the variable is empty or not set.
	 *     $return - Whether to set it or to return.
	 *
	 * Returns:
	 *     $variable = $fallback - If $return is false and $variable is empty or not set.
	 *     $fallback - If $return is true and $variable is empty or not set.
	 */
	function fallback(&$variable, $fallback = null, $return = false) {
		if (is_bool($variable))
			return $variable;

		$set = (!isset($variable) or empty($variable) or (is_string($variable) and trim($variable) == ""));

		if (!$return and $set)
			$variable = $fallback;

		return $set ? $fallback : $variable ;
	}

	/**
	 * Function: random
	 * Returns a random string.
	 *
	 * Parameters:
	 *     $length - How long the string should be.
	 */
	function random($length, $specialchars = false) {
		$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";

		if ($specialchars)
			$pattern.= "!@#$%^&*()?~";

		$len = ($specialchars) ? 47 : 35 ;

		$key = $pattern{rand(0, $len)};
		for($i = 1; $i < $length; $i++) {
			$key.= $pattern{rand(0, $len)};
		}
		return $key;
	}

	/**
	 * Function: unique_filename
	 * Makes a given filename unique for the uploads directory.
	 *
	 * Parameters:
	 *     $name - The name to check.
	 *
	 * Returns:
	 *     $name - A unique version of the given $name.
	 */
	function unique_filename($name, $path = "", $num = 2) {
		if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$name))
			return $name;

		$name = explode(".", $name);

		# Handle "double extensions"
		foreach (array("tar.gz", "tar.bz", "tar.bz2") as $extension) {
			list($first, $second) = explode(".", $extension);
			$file_first =& $name[count($name) - 2];
			if ($file_first == $first and end($name) == $second) {
				$file_first = $first.".".$second;
				array_pop($name);
			}
		}

		$ext = ".".array_pop($name);

		$try = implode(".", $name)."-".$num.$ext;
		if (!file_exists(MAIN_DIR.Config::current()->uploads_path.$path.$try))
			return $try;

		return unique_filename(implode(".", $name).$ext, $path, $num + 1);
	}

	/**
	 * Function: upload
	 * Moves an uploaded file to the uploads directory.
	 *
	 * Parameters:
	 *     $file - The $_FILES value.
	 *     $extension - An array of valid extensions (case-insensitive).
	 *     $path - A sub-folder in the uploads directory (optional).
	 *     $put - Use copy() instead of move_uploaded_file()?
	 *
	 * Returns:
	 *     $filename - The resulting filename from the upload.
	 */
	function upload($file, $extension = null, $path = "", $put = false) {
		$file_split = explode(".", $file['name']);

		$original_ext = end($file_split);

		# Handle "double extensions"
		foreach (array("tar.gz", "tar.bz", "tar.bz2") as $ext) {
			list($first, $second) = explode(".", $ext);
			$file_first =& $file_split[count($file_split) - 2];
			if ($file_first == $first and end($file_split) == $second) {
				$file_first = $first.".".$second;
				array_pop($file_split);
			}
		}

		$file_ext = end($file_split);

		if (is_array($extension)) {
			if (!in_array(strtolower($file_ext), $extension) and !in_array(strtolower($original_ext), $extension)) {
				$list = "";
				for ($i = 0; $i < count($extension); $i++) {
					$comma = "";
					if (($i + 1) != count($extension)) $comma = ", ";
					if (($i + 2) == count($extension)) $comma = ", and ";
					$list.= "<code>*.".$extension[$i]."</code>".$comma;
				}
				error(__("Invalid Extension"), _f("Only %s files are supported.", array($list)));
			}
		} elseif (isset($extension) and
		          strtolower($file_ext) != strtolower($extension) and
		          strtolower($original_ext) != strtolower($extension))
			error(__("Invalid Extension"), _f("Only %s files are supported.", array("*.".$extension)));

		array_pop($file_split);
		$file_clean = implode(".", $file_split);
		$file_clean = sanitize($file_clean, false).".".$file_ext;
		$filename = unique_filename($file_clean, $path);

		$message = __("Couldn't upload file. CHMOD <code>".MAIN_DIR.Config::current()->uploads_path."</code> to 777 and try again. If this problem persists, it's probably timing out; in which case, you must contact your system administrator to increase the maximum POST and upload sizes.");

		if ($put) {
			if (!@copy($file['tmp_name'], MAIN_DIR.Config::current()->uploads_path.$path.$filename))
				error(__("Error"), $message);
		} elseif (!@move_uploaded_file($file['tmp_name'], MAIN_DIR.Config::current()->uploads_path.$path.$filename))
			error(__("Error"), $message);

		return $filename;
	}

	/**
	 * Function: upload_from_url
	 * Copy a file from a specified URL to their upload directory.
	 *
	 * Parameters:
	 *     $url - The URL to copy.
	 *     $extension - An array of valid extensions (case-insensitive).
	 *     $path - A sub-folder in the uploads directory (optional).
	 *
	 * See Also:
	 *     <upload>
	 */
	function upload_from_url($url, $extension = null, $path = "") {
		$file = tempnam(sys_get_temp_dir(), "chyrp");
		file_put_contents($file, get_remote($url));

		$fake_file = array("name" => basename(parse_url($url, PHP_URL_PATH)),
		                   "tmp_name" => $file);

		return upload($fake_file, $extension, $path, true);
	}

	/**
	 * Function: timer_start
	 * Starts the timer.
	 */
	function timer_start() {
		global $time_start;
		$mtime = explode(" ", microtime());
		$mtime = $mtime[1] + $mtime[0];
		$time_start = $mtime;
	}

	/**
	 * Function: timer_stop
	 * Stops the timer and returns the total time.
	 *
	 * Parameters:
	 *     $precision - Number of decimals places to round to.
	 *
	 * Returns:
	 *     A formatted number with the given $precision.
	 */
	function timer_stop($precision = 3) {
		global $time_start;
		$mtime = microtime();
		$mtime = explode(" ", $mtime);
		$mtime = $mtime[1] + $mtime[0];
		$time_end = $mtime;
		$time_total = $time_end - $time_start;
		return number_format($time_total, $precision);
	}

	/**
	 * Function: normalize
	 * Attempts to normalize all newlines and whitespace into single spaces.
	 */
	function normalize($string) {
		$trimmed = trim($string);
		$newlines = str_replace("\n\n", " ", $trimmed);
		$newlines = str_replace("\n", "", $newlines);
		$normalized = preg_replace("/\s+/", " ", $newlines);
		return $normalized;
	}

	/**
	 * Function: get_remote
	 * Grabs the contents of a website/location.
	 */
	function get_remote($url) {
		extract(parse_url($url), EXTR_SKIP);

		if (ini_get("allow_url_fopen")) {
			$content = @file_get_contents($url);
		} elseif (function_exists("curl_init")) {
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $url);
			curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 1);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($handle, CURLOPT_TIMEOUT, 60);
			$content = curl_exec($handle);
			curl_close($handle);
		} else {
			$path = (!isset($path)) ? '/' : $path ;
			if (isset($query)) $path.= '?'.$query;
			$port = (isset($port)) ? $port : 80 ;

			$connect = @fsockopen($host, $port, $errno, $errstr, 2);
			if (!$connect) return false;

			# Send the GET headers
			fwrite($connect, "GET ".$path." HTTP/1.1\r\n");
			fwrite($connect, "Host: ".$host."\r\n");
			fwrite($connect, "User-Agent: Chyrp/".CHYRP_VERSION."\r\n\r\n");

			$content = "";
			while (!feof($connect)) {
				$line = fgets($connect, 128);
				if (preg_match("/\r\n/", $line)) continue;

				$content.= $line;
			}

			fclose($connect);
		}

		return $content;
	}

	/**
	 * Function: self_url
	 * Returns the current URL.
	 */
	function self_url() {
		$split = explode("/", $_SERVER['SERVER_PROTOCOL']);
		$protocol = strtolower($split[0]);
		$default_port = ($protocol == "http") ? 80 : 443 ;
		$port = ($_SERVER['SERVER_PORT'] == $default_port) ? "" : ":".$_SERVER['SERVER_PORT'] ;
		return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
	}

	/**
	 * Function: show_404
	 * Shows a 404 error message, extracting the passed array into the scope.
	 *
	 * Parameters:
	 *     $scope - An array of values to extract into the scope.
	 */
	 function show_404() {
		header("HTTP/1.1 404 Not Found");

		if (!defined('CHYRP_VERSION'))
			exit("404 Not Found");

		$theme = Theme::current();

		$theme->title = "404";

		if ($theme->file_exists("pages/404"))
			$theme->load("pages/404");
		else {
?>
		<h1><?php echo __("Not Found", "theme"); ?></h1>
		<div class="post body"><?php echo __("Sorry, but you are looking for something that isn't here."); ?></div>
<?php
		}
		exit;
	}

	/**
	 * Function: set_locale
	 * Set locale in a platform-independent way
	 *
	 * Parameters:
	 *     $locale - the locale name ('en_US', 'uk_UA', 'fr_FR' etc.)
	 *
	 * Returns:
	 *     The encoding name used by locale-aware functions.
	 */
    function set_locale($locale) { # originally via http://www.onphp5.com/article/22; heavily modified
		if ($locale == "en_US") return; # en_US is the default in Chyrp; their system may have
		                                # its own locale setting and no Chyrp translation available
		                                # for their locale, so let's just leave it alone.

		list($lang, $cty) = explode("_", $locale);
		$locales = array($locale.".UTF-8", $lang, "en_US.UTF-8", "en");
		$result = setlocale(LC_ALL, $locales);

		return (!strpos($result, 'UTF-8')) ? "CP".preg_replace('~\.(\d+)$~', "\\1", $result) : "UTF-8" ;
    }

	/**
	 * Function: sanitize_input
	 * Makes sure no inherently broken ideas such as magic_quotes break our application
	 *
	 * Parameters:
	 *     $data - The array to be sanitized, usually one of ($_GET, $_POST, $_COOKIE, $_REQUEST)
	 */
	function sanitize_input(&$data) {
		foreach ($data as &$value)
			if (is_array($value))
				sanitize_input($value);
			else
				$value = get_magic_quotes_gpc() ? stripslashes($value) : $value ;
	}

	/**
	 * Function: match
	 * Try and match a string against an array of regular expressions.
	 *
	 * Parameters:
	 *     $try - An array of regular expressions, or a single regular expression.
	 *     $haystack - The string to test.
	 */
	function match($try, $haystack) {
		if (is_string($try))
			return preg_match($try, $haystack);

		foreach ($try as $needle)
			if (preg_match($needle, $haystack))
				return true;

		return false;
	}

	/**
	 * Function: cancel_module
	 * Temporarily removes a module from $config->enabled_modules.
	 */
	 function cancel_module($target) {
		$this_disabled = array();

		$config = Config::current();
		foreach ($config->enabled_modules as $module)
			if ($module != $target)
				$this_disabled[] = $module;

		return $config->enabled_modules = $this_disabled;
	}

	/**
	 * Function: timezones
	 * Returns an array of timezones that have unique offsets. Doesn't count deprecated timezones.
	 */
	function timezones() {
		require INCLUDES_DIR."/lib/timezones.php"; # $timezones

		$zones = array();
		$offsets = array();
		$undo = $timezones[get_timezone()];
		foreach ($timezones as $timezone => $offset) {
			if (!in_array($offset, $offsets))
				$zones[] = array("offset" => ($offsets[] = $offset) / 3600,
				                 "name" => $timezone,
				                 "now" => time() - $undo + $offset);
		}

		function by_time($a, $b) {
			return ($a["now"] < $b["now"]) ? -1 : 1;
		}

		usort($zones, "by_time");

		return $zones;
	}

	/**
	 * Function: set_timezone
	 * Sets the timezone.
	 *
	 * Parameters:
	 *     $timezone - The timezone to set.
	 */
	function set_timezone($timezone) {
		if (function_exists("date_default_timezone_set"))
			date_default_timezone_set($timezone);
		else
			ini_set("date.timezone", $timezone);
	}

	/**
	 * Function: get_timezone()
	 * Returns the current timezone.
	 */
	function get_timezone() {
		if (function_exists("date_default_timezone_set"))
			return date_default_timezone_get();
		else
			return ini_get("date.timezone");
	}

	/**
	 * Function: error_panicker
	 * Exits and states where the error occurred.
	 */
	function error_panicker($number, $message, $file, $line) {
		exit("ERROR: ".$message." (".$file." on line ".$line.")");
	}

	/**
	 * Function: keywords
	 * Handle keyword-searching.
	 *
	 * Parameters:
	 *     $query - The query to parse.
	 *     $plain - WHERE syntax to search for non-keyword queries.
	 *
	 * Returns:
	 *     An array containing the "WHERE" queries and the corresponding parameters.
	 */
	function keywords($query, $plain) {
		if (!trim($query))
			return array(array(), array());

		$search = array();
		$matches = array();
		$where = array();
		$params = array();

		$queries = explode(" ", $query);
		foreach ($queries as $query)
			if (!preg_match("/([a-z0-9_]+):(.+)/", $query))
				$search[] = $query;
			else
				$matches[] = $query;

		$times = array("year", "month", "day", "hour", "minute", "second");

		foreach ($matches as $match) {
			list($test, $equals,) = explode(":", $match);

			if (in_array($test, $times))
				$where[strtoupper($test)."(created_at)"] = $equals;
			elseif ($test == "author") {
				$user = new User(null, array("where" => array("login" => $equals)));
				$where["user_id"] = $user->id;
			} elseif ($test == "group") {
				$group = new Group(null, array("where" => array("name" => $equals)));
				$test = "group_id";
				$equals = ($group->no_results) ? 0 : $group->id ;
			} else
				$where[$test] = $equals;
		}

		if (!empty($search)) {
			$where[] = $plain;
			$params[":query"] = "%".join(" ", $search)."%";
		}

		return array($where, $params);
	}
