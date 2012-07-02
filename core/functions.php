<?php
//Base system functions, do not edit this file, or make it very carefully
//otherwise system workability may be broken
	//Special functions for files including, strongly recommended for usage instead of system constructions
		/**
		 * @param string		$file
		 * @param bool			$once
		 * @param bool|Closure	$show_errors	If bool error will be processed, if Closure - only Closure will be called
		 *
		 * @return bool
		 */
		function _require ($file, $once = false, $show_errors = true) {
			$file = str_to_path($file);
			if (file_exists($file)) {
				if ($once) {
					return require_once $file;
				} else {
					return require $file;
				}
			} else {
				if (is_bool($show_errors) && $show_errors) {
					$data = debug_backtrace();
					trigger_error('File '.$file.' does not exists in '.$data[0]['file'].' on line '.$data[0]['line'], E_USER_ERROR);
				} elseif ($show_errors instanceof Closure) {
					return (bool)$show_errors();
				}
				return false;
			}
		}
		/**
		 * @param string		$file
		 * @param bool			$once
		 * @param bool|Closure	$show_errors	If bool error will be processed, if Closure - only Closure will be called
		 *
		 * @return bool
		 */
		function _include ($file, $once = false, $show_errors = true) {
			$file = str_to_path($file);
			if (file_exists($file)) {
				if ($once) {
					return include_once $file;
				} else {
					return include $file;
				}
			} else {
				if (is_bool($show_errors) && $show_errors) {
					$data = debug_backtrace();
					trigger_error('File '.$file.' does not exists in '.$data[0]['file'].' on line '.$data[0]['line'], E_USER_WARNING);
				} elseif ($show_errors instanceof Closure) {
					return (bool)$show_errors();
				}
				return false;
			}
		}
		/**
		 * @param string		$file
		 * @param bool|Closure	$show_errors	If bool error will be processed, if Closure - only Closure will be called
		 *
		 * @return bool
		 */
		function _require_once ($file, $show_errors) {
			return _require($file, true, $show_errors);
		}
		/**
		 * @param string		$file
		 * @param bool|Closure	$show_errors	If bool error will be processed, if Closure - only Closure will be called
		 *
		 * @return bool
		 */
		function _include_once ($file, $show_errors) {
			return _include($file, true, $show_errors);
		}
	//Class autoloading
	spl_autoload_register(function ($class) {
		if (substr($class, 0, 3) == 'cs\\') {
			$class	= substr($class, 3);
		}
		$class	= explode('\\', $class);
		$class	= [
			'namespace'	=> count($class) > 1 ? implode(DS, array_slice($class, 0, -1)).DS : '',
			'name'		=> array_pop($class)
		];
		_require(CLASSES.DS.$class['namespace'].'class.'.$class['name'].'.php', true, false) ||
		_require(ENGINES.DS.$class['namespace'].$class['name'].'.php', true, false);
	});
	//Функция для корректной остановки выполнения из любого места движка
	function __finish () {
		global $Objects;
		if (is_object($Objects)) {
			$Objects->__finish();
		}
		exit;
	}
	//Приостановить ограничение на время выполнение скрипта
	//Применяется для выполнения длительных операций без ошибок
	function time_limit_pause ($pause = true) {
		static $time_limit;
		if (!isset($time_limit)) {
			$time_limit = ['max_execution_time' => ini_get('max_execution_time'), 'max_input_time' => ini_get('max_input_time')];
		}
		if ($pause) {
			set_time_limit(900);
			@ini_set('max_input_time', 900);
		} else {
			set_time_limit($time_limit['max_execution_time']);
			@ini_set('max_input_time', $time_limit['max_input_time']);
		}
	}
	//Включение или отключение обработки ошибок
	function errors_on () {
		global $Error;
		is_object($Error) && $Error->error = true;
	}
	function errors_off () {
		global $Error;
		is_object($Error) && $Error->error = false;
	}
	//Включение или отключение отображения полного интерфейса
	function interface_on () {
		global $Page;
		if (is_object($Page)) {
			$Page->interface = true;
		} else {
			global $interface;
			$interface = true;
		}
	}
	function interface_off () {
		global $Page;
		if (is_object($Page)) {
			$Page->interface = false;
		} else {
			global $interface;
			$interface = false;
		}
	}
	/**
	 * Function for getting content of a directory
	 *
	 * @param	string		$dir			Directory for searching
	 * @param	bool|string	$mask			Regexp for items
	 * @param	string		$mode			Mode of searching<br>
	 * 										<b>f</b> - files only<br> (default)
	 * 										<b>d</b> - directories only<br>
	 * 										<b>fd</b> - both files and directories
	 * @param	bool|string	$prefix_path	Path to be added to the beginning of every found item. If <b>true</b> - prefix will
	 * 										be absolute path to item on server.
	 * @param	bool		$subfolders		Search in subdirectories or not
	 * @param	bool		$sort			Sort mode in format <b>mode|order</b>:<br>
	 * 										Possible values for mode: <b>name</b> (default), <b>date</b>, <b>size</b>
	 * 										Possible values for mode: <b>asc</b> (default), <b>desc</b>
	 * @param	bool|string	$exclusion		If specified file exists in scanned directory - it will be excluded from scanning
	 *
	 * @return	array|bool
	 */
	function get_list ($dir, $mask = false, $mode = 'f', $prefix_path = false, $subfolders = false, $sort = false, $exclusion = false) {
		if ($mode == 'df') {
			$mode = 'fd';
		}
		$dir = rtrim($dir, DS).DS;
		if (!_is_dir($dir) || ($exclusion !== false && _file_exists($dir.$exclusion))) {
			return false;
		}
		if ($sort === false) {
			$sort = 'name';
			$sort_x = ['name', 'acs'];
		} else {
			$sort = mb_strtolower($sort);
			$sort_x = explode('|', $sort);
			if (!isset($sort_x[1]) || $sort_x[1] != 'desc') {
				$sort_x[1] = 'asc';
			}
		}
		if (isset($sort_x) && $sort_x[0] == 'date') {
			$prepare = function (&$list, $tmp, $link) {
				$list[_fileatime($link) ?: _filemtime($link)] = $tmp;
			};
		} elseif (isset($sort_x) && $sort_x[0] == 'size') {
			$prepare = function (&$list, $tmp, $link) {
				$list[_filesize($link)] = $tmp;
			};
		} else {
			$prepare = function (&$list, $tmp, $link) {
				$list[] = $tmp;
			};
		}
		$list = [];
		if ($prefix_path !== true && $prefix_path) {
			$prefix_path = rtrim($prefix_path, DS).DS;
		}
		$dirc = _opendir($dir);
		if (!is_resource($dirc)) {
			return false;
		}
		//If file name if '0', it considered as boolean false, that's why $file === '0' was added
		while (($file = _readdir($dirc)) !== false) {
			if (
				($mask && !preg_match($mask, $file) && (!$subfolders || !_is_dir($dir.$file))) ||
				$file == '.' || $file == '..' || $file == '.htaccess' || $file == '.htpasswd' || $file == '.gitignore'
			) {
				continue;
			}
			if (
				(_is_file($dir.$file) && ($mode == 'f' || $mode == 'fd')) ||
				(_is_dir($dir.$file) && ($mode == 'd' || $mode == 'fd'))
			) {
				$prepare(
					$list,
					$prefix_path === true ? $dir.$file : ($prefix_path ? $prefix_path.$file : $file),
					$dir.$file
				);
			}
			if ($subfolders && _is_dir($dir.$file)) {
				$list = array_merge(
					$list,
					get_list(
						$dir.$file,
						$mask,
						$mode,
						$prefix_path === true || $prefix_path === false ? $prefix_path : $prefix_path.$file,
						$subfolders,
						$sort,
						$exclusion
					) ?: []
				);
			}
		}
		closedir($dirc);
		unset($prepare);
		if (!empty($list) && isset($sort_x)) {
			switch ($sort_x[0]) {
				case 'date':
				case 'size':
					if ($sort_x[1] == 'desc') {
						krsort($list);
					} else {
						ksort($list);
					}
				break;
				case 'name':
					natcasesort($list);
					if ($sort_x[1] == 'desc') {
						$list = array_reverse($list);
					}
				break;
			}
		}
		return array_values($list);
	}
	//Функции str_to_path() и path_to_str() являются обратными, и используются при работе с файловой системой.
	//Так, как в разных операционных системах названия одних и тех же файлов с Unicode символами php может отображать по разному,
	//эти две функции обеспечивают кроссплатформенность работы с адресами файлов и папок в Unicode кодировке, и их настоятельно
	//рекомендуется использовать везде, где нет уверенности в том, что Unicode символы не встретятся в пути к файлу или папке.
		//Функция подготавливает строку, которая должна использоваться как путь для файловой системы
		function str_to_path ($str) {
			if (is_array($str)) {
				foreach ($str as &$s) {
					$s = str_to_path($s);
				}
				return $str;
			}
			//Null byte injection protection
			$str = null_byte_filter($str);
			return	FS_CHARSET == 'utf-8' ||
					strpos($str, 'http://') === 0 ||
					strpos($str, 'https://') === 0 ||
					strpos($str, 'ftp://') === 0 ||
					strpos($str, '//') === 0 ? $str : !is_unicode($str) ? $str : iconv('utf-8', FS_CHARSET, $str);
		}
		//Функция подготавливает строку, которая была получена как путь в файловой системе, для использования в движке
		function path_to_str ($path) {
			if (is_array($path)) {
				foreach ($path as &$p) {
					$p = str_to_path($p);
				}
				return $path;
			}
			return FS_CHARSET == 'utf-8' ? $path : (is_unicode($path) ? $path : iconv(FS_CHARSET, 'utf-8', $path));
		}
		//Detection of unicode strings
		if (!function_exists('is_unicode')) {
			function is_unicode ($s) {
				return mb_check_encoding ($s, 'utf-8');
			}
		}
	//Аналоги системных функций с теми же параметрами в том же порядке. Настоятельно рекомендуется использовать вместо стандартных
	//При использовании этих функций будет небольшая потеря в скорости, зато нивелируются различия в операционных системах
	//при использовании кириллических и других Unicode символов (не латинских) в пути к файлу или папке
		function _file ($filename, $flags = 0, $context = null) {
			return is_null($context) ?
					file(str_to_path($filename), $flags) :
					file(str_to_path($filename), $flags, $context);
		}
		function _file_get_contents ($filename, $flags = 0, $context = null, $offset = -1, $maxlen = -1) {
			return is_null($context) ?
					file_get_contents(str_to_path($filename), $flags, $context, $offset) :
					file_get_contents(str_to_path($filename), $flags, $context, $offset, $maxlen);
		}
		function _file_put_contents ($filename, $data, $flags = 0, $context = null) {
			return is_null($context) ?
					file_put_contents(str_to_path($filename), $data, $flags) :
					file_put_contents(str_to_path($filename), $data, $flags, $context);
		}
		function _copy ($source, $dest, $context = null) {
			return is_null($context) ?
					copy(str_to_path($source), str_to_path($dest)) :
					copy(str_to_path($source), str_to_path($dest), $context);
		}
		function _unlink ($filename, $context = null) {
			return is_null($context) ?
					unlink(str_to_path($filename)) :
					unlink(str_to_path($filename), $context);
		}
		function _file_exists ($filename) {
			return file_exists(str_to_path($filename));
		}
		function _rename ($oldname, $newname, $context = null) {
			return is_null($context) ?
					rename(str_to_path($oldname), str_to_path($newname)) :
					rename(str_to_path($oldname), str_to_path($newname), $context);
		}
		function _mkdir ($pathname, $mode = 0777, $recursive = false, $context = null) {
			return is_null($context) ?
					mkdir(str_to_path($pathname), $mode, $recursive) :
					mkdir(str_to_path($pathname), $mode, $recursive, $context);
		}
		function _rmdir ($dirname, $context = null) {
			return is_null($context) ?
					rmdir(str_to_path($dirname)) :
					rmdir(str_to_path($dirname), $context);
		}
		function _basename ($path, $suffix = '') {
			return basename(str_to_path($path), $suffix);
		}
		function _dirname ($path) {
			return dirname(str_to_path($path));
		}
		function _chdir ($directory) {
			return chdir(str_to_path($directory));
		}
		function _filesize ($filename) {
			return filesize(str_to_path($filename));
		}
		function _fopen ($filename, $mode, $use_include_path = false, $context = null) {
			return is_null($context) ?
					fopen(str_to_path($filename), $mode, $use_include_path) :
					fopen(str_to_path($filename), $mode, $use_include_path, $context);
		}
		function _opendir ($path, $context = null) {
			return is_null($context) ?
					opendir(str_to_path($path)) :
					opendir(str_to_path($path), $context);
		}
		function _readdir ($dir_handle = null) {
			return path_to_str(readdir($dir_handle));
		}
		function _scandir ($directory, $sorting_order = null, $context = null) {
			return is_null($context) ?
				path_to_str(scandir(str_to_path($directory), $sorting_order)) :
				path_to_str(scandir(str_to_path($directory), $sorting_order, $context));
		}
		function _is_dir ($filename) {
			return is_dir(str_to_path($filename));
		}
		function _is_file ($filename) {
			return is_file(str_to_path($filename));
		}
		function _is_readable ($filename) {
			return is_readable(str_to_path($filename));
		}
		function _is_writable ($filename) {
			return is_writable(str_to_path($filename));
		}
		function _is_uploaded_file ($filename) {
			return is_uploaded_file(str_to_path($filename));
		}
		function _move_uploaded_file ($filename, $destination) {
			return move_uploaded_file(str_to_path($filename), str_to_path($destination));
		}
		function _realpath ($path) {
			return realpath(str_to_path($path));
		}
		function _filectime ($filename) {
			return filectime(str_to_path($filename));
		}
		function _fileatime ($filename) {
			return fileatime(str_to_path($filename));
		}
		function _filemtime ($filename) {
			return filemtime(str_to_path($filename));
		}
	//Получить URL файла по его расположению в файловой системе
	function url_by_source ($source) {
		$source = _realpath($source);
		if (strpos($source, DIR.DS) === 0) {
			global $Config;
			if (is_object($Config)) {
				return str_replace(DS, '/', str_replace(DIR, $Config->server['base_url'], $source));
			}
		}
		return false;
	}
	//Получить расположение файла в файловой системе по его URL
	function source_by_url ($url) {
		global $Config;
		if (strpos($url, $Config->server['base_url']) === 0) {
			if (is_object($Config)) {
				return str_replace('/', DS, str_replace($Config->server['base_url'], DIR, $url));
			}
		}
		return false;
	}
	/**
	 * Public cache cleaning
	 * @return bool
	 */
	function clean_pcache () {
		$ok = true;
		$list = get_list(PCACHE, false, 'fd', true, true, 'name|desc');
		foreach ($list as $item) {
			if (_is_writable($item)) {
				_is_dir($item) ? @_rmdir($item) : @_unlink($item);
			} else {
				$ok = false;
			}
		}
		unset($list, $item);
		return $ok;
	}
	/**
	 * Closure processing
	 * @param Closure[] $functions
	 */
	function closure_process (&$functions) {
		$functions = (array)$functions;
		foreach ($functions as &$function) {
			if ($function instanceof Closure) {
				$function();
			}
		}
	}
	//Функция форматирования времени из секунд в удобночитаемый вид
	function format_time ($time) {
		global $L;
		$res = [];
		if ($time >= 31536000) {
			$time_x = round($time/31536000);
			$time -= $time_x*31536000;
			$res[] = $L->time($time_x, 'y');
		}
		if ($time >= 2592000) {
			$time_x = round($time/2592000);
			$time -= $time_x*2592000;
			$res[] = $L->time($time_x, 'M');
		}
		if($time >= 86400) {
			$time_x = round($time/86400);
			$time -= $time_x*86400;
			$res[] = $L->time($time_x, 'd');
		}
		if($time >= 3600) {
			$time_x = round($time/3600);
			$time -= $time_x*3600;
			$res[] = $L->time($time_x, 'h');
		}
		if ($time >= 60) {
			$time_x = round($time/60);
			$time -= $time_x*60;
			$res[] = $L->time($time_x, 'm');
		}
		if ($time > 0 || empty($res)) {
			$res[] = $L->time($time, 's');
		}
		return implode(' ', $res);
	}
	/**
	 * Function for formatting of file size in bytes to human-readable form
	 * @param $size
	 * @param bool|int $round
	 * @return float|string
	 */
	function format_filesize ($size, $round = false) {
		global $L;
		$unit = '';
		if($size >= 1099511627776) {
			$size = $size/1099511627776;
			$unit = ' '.$L->TB;
		} elseif($size >= 1073741824) {
			$size = $size/1073741824;
			$unit = ' '.$L->GB;
		} elseif ($size >= 1048576) {
			$size = $size/1048576;
			$unit = ' '.$L->MB;
		} elseif ($size >= 1024) {
			$size = $size/1024;
			$unit = ' '.$L->KB;
		} else {
			$size = $size." ".$L->Bytes;
		}
		return $round ? round($size, $round).$unit : $size;
	}
	/**
	 * Protecting against null Byte injection
	 * @param string|array $in
	 * @return string|array
	 */
	function null_byte_filter ($in) {
		if (is_array($in)) {
			foreach ($in as &$val) {
				$val = null_byte_filter($val);
			}
		} else {
			$in = str_replace(chr(0), '', $in);
		}
		return $in;
	}
	/**
	 * Filtering and functions for recursive processing of arrays
	 * @param string|string[] $text
	 * @param string $mode
	 * @param bool|string $data
	 * @param null|string $data2
	 * @param null|string $data3
	 * @return string|string[]
	 */
	function filter ($text, $mode = '', $data = false, $data2 = null, $data3 = null) {
		if (is_array($text)) {
			foreach ($text as $item => &$val) {
				$text[$item] = filter($val, $mode, $data, $data2, $data3);
			}
			return $text;
		}
		switch ($mode) {
			case 'stripslashes':
			case 'addslashes':
				return $mode($text);
			case 'trim':
			case 'ltrim':
			case 'rtrim':
				return $data === false ? $mode($text) : $mode($text, $data);
			case 'substr':
				return $data2 === null ? $mode($text, $data) : (
					$data3 === null ? $mode($text, $data, $data2) : $mode($text, $data, $data2, $data3)
				);
			case 'mb_substr':
				return $data2 === null ? $mode($text, $data) : $mode($text, $data, $data2);
			case 'mb_strtolower':
			case 'mb_strtoupper':
				return $mode($text, $data);
			case 'strtolower':
			case 'strtoupper':
				return $mode($text);
			default:
				return str_replace(['&', '"', '\'', '<', '>'], ['&amp;', '&quot;', '&apos;', '&lt;', '&gt;'], trim($text));
		}
	}
	//Функции работы со строками аналоги системных, но вместо входящей строки могут принимать массив для его рекурсивной обработки
		function _stripslashes ($str) {
			return filter($str, 'stripslashes');
		}
		function _addslashes ($str) {
			return filter($str, 'addslashes');
		}
		function _trim ($str, $charlist = false) {
			return filter($str, 'trim', $charlist);
		}
		function _ltrim ($str, $charlist = false) {
			return filter($str, 'ltrim', $charlist);
		}
		function _rtrim ($str, $charlist = false) {
			return filter($str, 'rtrim', $charlist);
		}
		function _substr ($string, $start, $length = null) {
			return filter($string, 'substr', $start, $length);
		}
		function _mb_substr ($string, $start, $length = null, $encoding = null) {
			return filter($string, 'substr', $start, $length, $encoding);
		}
		function _strtolower ($string) {
			return filter($string, 'strtolower');
		}
		function _strtoupper ($string) {
			return filter($string, 'strtoupper');
		}
		function _mb_strtolower ($string, $encoding = false) {
			return filter($string, 'mb_strtolower', $encoding ?: mb_internal_encoding());
		}
		function _mb_strtoupper ($string, $encoding = false) {
			return filter($string, 'mb_strtoupper', $encoding ?: mb_internal_encoding());
		}
	//Аналог системной функции json_encode, корректно работает с кирилицей и делает результирующую строку короче,
	//настоятельно рекомендуется к использованию вместо стандартной!
	function _json_encode ($in) {
		return json_encode($in, JSON_UNESCAPED_UNICODE);
	}
	//Аналог системной функции json_decode, сразу возвращает ассоциативный массив, просто так удобнее вызывать
	function _json_decode ($in, $depth = 512) {
		return @json_decode($in, true, $depth);
	}
	/**
	 * Function for setting cookies on all mirrors. Parameters like in system function, but $path, $domain and $secure
	 * are skipped, they are detected automatically, and $api parameter added in the end.
	 *
	 * @param string     $name
	 * @param string     $value
	 * @param int        $expire
	 * @param bool       $httponly
	 * @param bool       $api		//Is this cookie setting during api request (in most cases it is not necessary to change this parameter)
	 *
	 * @return bool
	 */
	function _setcookie ($name, $value, $expire = 0, $httponly = false, $api = false) {
		static $path, $domain, $prefix, $secure;
		global $Config, $Core;
		if (!isset($prefix) && is_object($Config)) {
			$prefix		= $Config->core['cookie_prefix'];
			$secure		= $Config->server['protocol'] == 'https';
			if ($Config->server['mirror_index'] == -1) {
				$domain	= $Config->core['cookie_domain'];
				$path	= $Config->core['cookie_path'];
			} else {
				$domain	= $Config->core['mirrors_cookie_domain'][$Config->server['mirror_index']];
				$path	= $Config->core['mirrors_cookie_path'][$Config->server['mirror_index']];
			}
		}
		$_COOKIE[$prefix.$name] = $value;
		if (!$api && is_object($Core)) {
			$data = [
				'name'		=> $name,
				'value'		=> $value,
				'expire'	=> $expire,
				'httponly'	=> $httponly
			];
			$Core->register_trigger(
				'System/Page/pre_display',
				function () use ($data) {
					global $Config, $Key, $Page, $User, $db;
					if ($Config->server['mirrors']['count'] > 1) {
						$mirrors_url			= $Config->core['mirrors_url'];
						$mirrors_cookie_domain	= $Config->core['mirrors_cookie_domain'];
						$database				= $db->{$Config->components['modules']['System']['db']['keys']}();
						$data['check']			= md5($User->ip.$User->forwarded_for.$User->client_ip.$User->user_agent._json_encode($data));
						$js						= '';
						foreach ($mirrors_cookie_domain as $i => $domain) {
							$mirrors_url[$i] = explode(';', $mirrors_url[$i], 2)[0];
							if ($domain && ($mirrors_url[$i] != $Config->server['base_url'])) {
								if ($Key->add($database, $key = $Key->generate($database), $data)) {
									$js .= '$.get(\'http://'.$mirrors_url[$i].'/api/System/user/setcookie/'.$key.'\');';
								}
							}
						}
						if ($js) {
							$Page->post_Body .= h::script('$(function {'.$js.'});');
						}
					}
				}
			);
		}
		if (isset($prefix)) {
			return setcookie(
				$prefix.$name,
				$value,
				$expire,
				$path,
				$domain,
				$secure,
				$httponly
			);
		} else {
			return setcookie(
				$prefix.$name,
				$value,
				$expire,
				'/',
				$_SERVER['HTTP_HOST'],
				false,
				$httponly
			);
		}
	}
	function _getcookie ($name) {
		static $prefix;
		if (!isset($prefix)) {
			global $Config;
			$prefix = is_object($Config) && $Config->core['cookie_prefix'] ? $Config->core['cookie_prefix'].'_' : '';
		}
		return isset($_COOKIE[$prefix.$name]) ? $_COOKIE[$prefix.$name] : false;
	}
	/**
	 * XSS Attack Protection
	 *
	 * @param string|string[] $in HTML code
	 * @param bool|string $html <b>text</b> - text at output (default), <b>true</b> - processed HTML at output, <b>false</b> - HTML tags will be deleted
	 * @return string|string[]
	 */
	function xap ($in, $html = 'text') {
		if (is_array($in)) {
			foreach ($in as &$item) {
				$item = xap($item, $html);
			}
			return $in;
		} elseif ($html === true) {
			//Make safe HTML
			$in = preg_replace(
				'/(<(link|script|iframe|object|applet|embed).*?>[^<]*(<\/(link|script|iframe).*?>)?)/i',
				'',
				$in
			);
			$in = preg_replace(
				'/(script:)|(data:)|(expression\()/i',
				'\\1&nbsp;',
				$in
			);
			$in = preg_replace(
				'/(onblur|onchange|onclick|ondblclick|onfocus|onkeydown|onkeypress|onkeyup|onload|onmousedown|'.
					'onmousemove|onmouseout|onmouseover|onmouseup|onreset|onselect|onsubmit|onunload)=?/i',
				'',
				$in
			);
			$in = preg_replace(//TODO page redirection processing
				'/((href).*?=.*?)(http:\/\/)/i',
				'\\1redirect/\\2',
				$in
			);
			return $in;
		} elseif ($html === false) {
			return strip_tags($in);
		} else {
			return htmlspecialchars($in, ENT_QUOTES | ENT_HTML5 | ENT_DISALLOWED | ENT_SUBSTITUTE);
		}
	}
	if (!function_exists('hex2bin')) {
		/**
		 * Function, reverse to bin2hex()
		 * @param $str
		 * @return string
		 */
		function hex2bin ($str){
			$len	= strlen($str);
			$res	= '';
			for ($i = 0; $i < $len; $i += 2) {
				$res .= pack("H", $str[$i]) | pack("h", $str[$i + 1]);
			}
			return $res;
		}
	}
	/**
	 * Function for convertion of Ipv4 and Ipv6 into hex values to store in db
	 * @param string $ip
	 * @return bool|string
	 */
	function ip2hex ($ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
			$isIPv4 = true;
		} elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			$isIPv4 = false;
		} else {
			return false;
		}
		//IPv4 format
		if($isIPv4) {
			$parts = explode('.', $ip);
			foreach ($parts as &$part) {
				$part = str_pad(dechex($part), 2, '0', STR_PAD_LEFT);
			}
			unset($part);
			$ip			= '::'.$parts[0].$parts[1].':'.$parts[2].$parts[3];
			$hex		= implode('', $parts);
		//IPv6 format
		} else {
			$parts		= explode(':', $ip);
			$last_part	= count($parts) - 1;
			//If mixed IPv6/IPv4, convert ending to IPv6
			if(filter_var($parts[$last_part], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
				$parts[$last_part] = explode('.', $parts[$last_part]);
				foreach ($parts[$last_part] as &$part) {
					$part = str_pad(dechex($part), 2, '0', STR_PAD_LEFT);
				}
				unset($part);
				$parts[]			= $parts[$last_part][2].$parts[$last_part][3];
				$parts[$last_part]	= $parts[$last_part][0].$parts[$last_part][1];
			}
			$numMissing		= 8 - count($parts);
			$expandedParts	= [];
			$expansionDone	= false;
			foreach($parts as $part) {
				if(!$expansionDone && $part == '') {
					for($i = 0; $i <= $numMissing; ++$i) {
						$expandedParts[] = '0000';
					}
					$expansionDone = true;
				} else {
					$expandedParts[] = $part;
				}
			}
			foreach($expandedParts as &$part) {
				$part = str_pad($part, 4, '0', STR_PAD_LEFT);
			}
			$ip = implode(':', $expandedParts);
			$hex = implode('', $expandedParts);
		}
		//Check final IP
		if(filter_var($ip, FILTER_VALIDATE_IP) === false) {
			return false;
		}
		return strtolower(str_pad($hex, 32, '0', STR_PAD_LEFT));
	}
	/**
	 * Returns IP for given hex representation
	 * @param string $hex
	 * @param int $mode	6	- result IP will be in form of Ipv6<br>
	 * 					4	- if possible, result will be in form of Ipv4, otherwise in form of IPv6<br>
	 * 					10	- result will be array(IPv6, IPv4)
	 * @return array|bool|string
	 */
	function hex2ip ($hex, $mode = 6) {
		if (!$hex || strlen($hex) != 32) {
			return false;
		}
		$IPv4_range = false;
		if (preg_match('/^0{24}[0-9a-f]{8}$/', $hex)) {
			$IPv4_range = true;
		}
		if ($IPv4_range) {
			$hex = substr($hex, 24, 8);
			switch ($mode) {
				case 4:
					return	hexdec(substr($hex, 0, 2)).'.'.
							hexdec(substr($hex, 2, 2)).'.'.
							hexdec(substr($hex, 4, 2)).'.'.
							hexdec(substr($hex, 6, 2));
				case 10:
					$result = [];
					//IPv6
					$result[] = '0000:0000:0000:0000:0000:0000:'.substr($hex, 0, 4).':'.substr($hex, 4, 4);
					//IPv4
					$result[] =	hexdec(substr($hex, 0, 2)).'.'.
								hexdec(substr($hex, 2, 2)).'.'.
								hexdec(substr($hex, 4, 2)).'.'.
								hexdec(substr($hex, 6, 2));
					return $result;
				default:
					return '0000:0000:0000:0000:0000:0000:'.substr($hex, 0, 4).':'.substr($hex, 4, 4);
			}
		} else {
			$result =	substr($hex, 0, 4).':'.
						substr($hex, 4, 4).':'.
						substr($hex, 8, 4).':'.
						substr($hex, 12, 4).':'.
						substr($hex, 16, 4).':'.
						substr($hex, 20, 4).':'.
						substr($hex, 24, 4).':'.
						substr($hex, 28, 4);
			if ($mode == 10) {
				return [$result, false];
			} else {
				return $result;
			}
		}
	}
	/**
	 * Get list of timezones
	 * @return array
	 */
	function get_timezones_list () {
		global $Cache;
		if (!is_object($Cache) || ($timezones = $Cache->timezones) === false) {
			$tzs = timezone_identifiers_list();
			$timezones_ = $timezones = [];
			foreach ($tzs as $tz) {
				$offset		= (new DateTimeZone($tz))->getOffset(new DateTime);
				$offset_	=	($offset < 0 ? '-' : '+').
								str_pad(floor(abs($offset / 3600)), 2, 0, STR_PAD_LEFT).':'.
								str_pad(abs(($offset % 3600) / 60), 2, 0, STR_PAD_LEFT);
				$timezones_[(39600 + $offset).$tz] = [
					'key'	=> strtr($tz, '_', ' ').' ('.$offset_.')',
					'value'	=> $tz
				];
			}
			unset($tzs, $tz, $offset);
			ksort($timezones_, SORT_NATURAL);
			/**
			 * @var array $offset
			 */
			foreach ($timezones_ as $tz) {
				$timezones[$tz['key']] = $tz['value'];
			}
			unset($timezones_, $tz);
			if (is_object($Cache)) {
				$Cache->timezones = $timezones;
			}
		}
		return $timezones;
	}
	/**
	 * Check password strength
	 *
	 * @param	string	$password
	 * @return	int		In range [0..7]<br><br>
	 * 					<b>1</b> - numbers<br>
	 *  				<b>2</b> - numbers + letters<br>
	 * 					<b>3</b> - numbers + letters in different registers<br>
	 * 		 			<b>4</b> - numbers + letters in different registers + special symbol on usual keyboard +=/^ and others<br>
	 * 					<b>5</b> - numbers + letters in different registers + special symbols (more than one)<br>
	 * 					<b>6</b> - as 5, but + special symbol, which can't be found on usual keyboard or non-latin letter<br>
	 * 					<b>7</b> - as 5, but + special symbols, which can't be found on usual keyboard or non-latin letter (more than one symbol)<br>
	 */
	function password_check ($password) {
		global $Config;
		$min		= is_object($Config) && $Config->core['password_min_length'] ? $Config->core['password_min_length'] : 4;
		$password	= preg_replace('/\s+/', ' ', $password);
		$s			= 0;
		if(strlen($password) >= $min) {
			if(preg_match('/[~!@#\$%\^&\*\(\)\-_=+\|\\/;:,\.\?\[\]\{\}]+/', $password, $match)) {
				$s = 4;
				if (strlen(implode('', $match)) > 1) {
					++$s;
				}
			} else {
				if(preg_match('/[A-Z]+/', $password)) {
					++$s;
				}
				if(preg_match('/[a-z]+/', $password)) {
					++$s;
				}
				if(preg_match('/[0-9]+/', $password)) {
					++$s;
				}
			}
			if (preg_match('/[^[0-9a-z~!@#\$%\^&\*\(\)\-_=+\|\\/;:,\.\?\[\]\{\}]]+/i', $password, $match)) {
				++$s;
				if (strlen(implode('', $match)) > 1) {
					++$s;
				}
			}
		}
		return $s;
	}
	/**
	 * Generates passwords till 5th level of strength, 6-7 - only for humans:)
	 *
	 * @param	int		$length
	 * @param	int		$strength In range [1..5], but it must be smaller, than $length<br><br>
	 * 					<b>1</b> - numbers<br>
	 * 					<b>2</b> - numbers + letters<br>
	 * 					<b>3</b> - numbers + letters in different registers<br>
	 * 					<b>4</b> - numbers + letters in different registers + special symbol<br>
	 * 					<b>5</b> - numbers + letters in different registers + special symbols (more than one)
	 * @return	string
	 */
	function password_generate ($length = 10, $strength = 5) {
		static $special = [
			'~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_',
			'=', '+', '|', '\\', '/', ';', ':', ',', '.', '?', '[', ']', '{', '}'
		];
		static $small, $capital;
		if ($length < 4) {
			$length = 4;
		}
		if ($strength < 1) {
			$strength = 1;
		} elseif ($strength > $length) {
			$strength = $length;
		}
		if (!isset($small)) {
			$small = range('a', 'z');
		}
		if (!isset($capital)) {
			$capital = range('A', 'Z');
		}
		$password = [];
		$symbols = range(0, 9);
		if ($strength > 5) {
			$strength = 5;
		}
		if ($strength > $length) {
			$strength = $length;
		}
		if ($strength > 3) {
			$symbols = array_merge($symbols, $special);
		}
		if ($strength > 2) {
			$symbols = array_merge($symbols, $capital);
		}
		if ($strength > 1) {
			$symbols = array_merge($symbols, $small);
		}
		$size = count($symbols)-1;
		while (true) {
			for ($i = 0; $i < $length; ++$i) {
				$password[] = $symbols[rand(0, $size)];
			}
			shuffle($password);
			if (password_check(implode('', $password)) == $strength) {
				return implode('', $password);
			}
			$password = [];
		}
		return '';
	}
	//Некоторые функции для определение состояния сервера
	//Проверка версии БД
	function check_db () {
		global $DB_TYPE, $db, $L;
		global $$DB_TYPE;
		if (!$$DB_TYPE) {
			return $L->unsupported_db_type;
		}
		preg_match('/[\.0-9]+/', $db->server(), $db_version);
		return (bool)version_compare($db_version[0], $$DB_TYPE, '>=');
	}
	//Проверка версии PHP
	function check_php () {
		global $PHP;
		return (bool)version_compare(PHP_VERSION, $PHP, '>=');
	}
	//Проверка наличия и версии mcrypt
	function check_mcrypt ($n = 0) { //0 - версия библиотеки (и наличие), 1 - подходит ли версия библиотеки
		static $mcrypt_data;
		if (!isset($mcrypt_data)) {
			ob_start();
			@phpinfo(INFO_MODULES);
			$mcrypt_version = ob_get_clean();
			preg_match(
				'/mcrypt support.*?(enabled|disabled)(.|\n)*?Version.?<\/td><td class=\"v\">(.*?)[\n]?<\/td><\/tr>/',
				$mcrypt_version,
				$mcrypt_version
			);
			$mcrypt_data[0] = $mcrypt_version[1] == 'enabled' ? trim($mcrypt_version[3]) : false;
			global $mcrypt;
			$mcrypt_data[1] = $mcrypt_data[0] ? (bool)version_compare($mcrypt_data[0], $mcrypt, '>=') : false;
		}
		return $mcrypt_data[$n];
	}
	//Проверка наличия zlib
	function zlib () {
		return extension_loaded('zlib');
	}
	//Проверка автоматического сжатия страниц с помощью zlib
	function zlib_compression () {
		return zlib() && strtolower(ini_get('zlib.output_compression')) != 'off';
	}
	function zlib_compression_level () {
		return ini_get('zlib.output_compression_level');
	}
	//Проверка наличия curl
	function curl () {
		return extension_loaded('curl');
	}
	//Проверка отображения ошибок
	function display_errors () {
		return (bool)ini_get('display_errors');
	}
	//Проверка типа сервера
	function server_api () {
		global $L;
		ob_start();
		phpinfo(INFO_GENERAL);
		$tmp = ob_get_clean();
		preg_match('/Server API <\/td><td class=\"v\">(.*?) <\/td><\/tr>/', $tmp, $tmp);
		if ($tmp[1]) {
			return $tmp[1];
		} else {
			return $L->indefinite;
		}
	}
	/**
	 * @param string $host
	 * @param string $path
	 * @param array  $data
	 *
	 * @return bool|string
	 */
	function post_request ($host, $path, $data) {
		if (!is_array($data) || empty($data)) {
			return false;
		}
		$host	= explode(':', $host);
		$socket = fsockopen($host[0], isset($host[1]) ? $host[1] : 80);
		if(!is_resource($socket)) {
			return false;
		}
		$data = http_build_query($data, null, null, PHP_QUERY_RFC3986);
		fwrite(
			$socket,
			"POST $path HTTP/1.1\r\n".
			'Host: '.implode(':', $host)."\r\n".
			"Content-type: text/plain\r\n".
			"Content-length:".strlen($data)."\r\n".
			"Accept:*/*\r\n".
			"User-agent: CleverStyle CMS\r\n\r\n".
			$data."\r\n\r\n"
		);
		unset($data);
		$return = explode("\r\n\r\n", stream_get_contents($socket), 2)[1];
		fclose($socket);
		return $return;
	}
	/**
	 * Sends header with string representation of error code, for example "404 Not Found" for corresponding server protocol
	 *
	 * @param int $code Error code number
	 *
	 * @return null|string String representation of error code
	 */
	function error_header ($code) {
		$string_code = null;
		switch ($code) {
			case 400:
				$string_code = '400 Bad Request';
				break;
			case 403:
				$string_code = '403 Forbidden';
				break;
			case 404:
				$string_code = '404 Not Found';
				break;
			case 500:
				$string_code = '500 Internal Server Error';
				break;
		}
		if ($string_code) {
			header($_SERVER['SERVER_PROTOCOL'].' '.$string_code);
		}
		return $string_code;
	}
	/**
	 * Bitwise XOR operation for 2 strings
	 *
	 * @param string $string1
	 * @param string $string2
	 *
	 * @return string
	 */
	function xor_string ($string1, $string2) {
		$len1	= mb_strlen($string1);
		$len2	= mb_strlen($string2);
		if ($len2 > $len1) {
			list($string1, $string2, $len1, $len2) = [$string2, $string1, $len2, $len1];
		}
		for ($i = 0; $i < $len1; ++$i) {
			$pos = $i % $len2;
			$string1[$i] = chr(ord($string1[$i]) ^ ord($string2[$pos]));
		}
		return $string1;
	}
	/**
	 * Checks associativity of array
	 *
	 * @param array	$array	Array to be checked
	 *
	 * @return bool
	 */
	function is_array_assoc ($array) {
		$keys = array_keys($array);
		return array_keys($keys) !== $keys;
	}

$temp = base64_decode('Y29weXJpZ2h0');
$$temp = [
	0 => base64_decode('Q2xldmVyU3R5bGUgQ01TIGJ5IE1va3J5bnNreWkgTmF6YXI='),																		//Generator
	1 => base64_decode('Q29weXJpZ2h0IChjKSAyMDExLTIwMTIgYnkgTW9rcnluc2t5aSBOYXphcg=='),															//Copyright
	2 => base64_decode('PGEgdGFyZ2V0PSJfYmxhbmsiIGhyZWY9Imh0dHA6Ly9jc2Ntcy5vcmciIHRpdGxlPSJDbGV2ZXJTdHlsZSBDTVMiPkNsZXZlclN0eWxlIENNUzwvYT4=')	//Link
];
unset($temp);