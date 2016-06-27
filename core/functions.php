<?php
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2011-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
/**
 * Base system functions, do not edit this file, or make it very carefully
 * otherwise system workability may be broken
 */
use
	cs\Cache,
	cs\Config,
	cs\Language,
	cs\Page,
	cs\Text;

/**
 * Auto Loading of classes
 */
spl_autoload_register(
	function ($class) {
		static $cache, $aliases;
		$get_from_cache = function ($file) {
			return defined('CACHE') && file_exists(CACHE."/classes/$file") ? file_get_json(CACHE."/classes/$file") : [];
		};
		$put_into_cache = function ($file, $content) {
			if (defined('CACHE') && is_dir(CACHE)) {
				/** @noinspection MkdirRaceConditionInspection */
				@mkdir(CACHE.'/classes', 0770);
				file_put_json(CACHE."/classes/$file", $content);
			}
		};
		if (!isset($cache)) {
			$cache   = $get_from_cache('autoload');
			$aliases = $get_from_cache('aliases');
		}
		if (isset($aliases[$class])) {
			spl_autoload_call($aliases[$class]);
			return class_exists($aliases[$class]) || class_alias($aliases[$class], $class);
		}
		if (isset($cache[$class])) {
			return $cache[$class] ? require_once $cache[$class] : false;
		}
		$prepared_class_name = ltrim($class, '\\');
		if (strpos($prepared_class_name, 'cs\\') === 0) {
			$prepared_class_name = substr($prepared_class_name, 3);
		}
		$prepared_class_name = explode('\\', $prepared_class_name);
		$namespace           = count($prepared_class_name) > 1 ? implode('/', array_slice($prepared_class_name, 0, -1)) : '';
		$class_name          = array_pop($prepared_class_name);
		$cache[$class]       = false;
		/**
		 * Try to load classes from different places. If not found in one place - try in another.
		 */
		if (
			file_exists($file = __DIR__."/classes/$namespace/$class_name.php") ||    //Core classes
			file_exists($file = __DIR__."/thirdparty/$namespace/$class_name.php") || //Third party classes
			file_exists($file = __DIR__."/traits/$namespace/$class_name.php") ||     //Core traits
			file_exists($file = __DIR__."/engines/$namespace/$class_name.php") ||                 //Core engines
			file_exists($file = MODULES."/../$namespace/$class_name.php") ||              //Classes in modules
			file_exists($file = PLUGINS."/../$namespace/$class_name.php")                 //Classes in plugins
		) {
			$cache[$class] = realpath($file);
			$put_into_cache('autoload', $cache);
			require_once $file;
			return true;
		}
		$put_into_cache('autoload', $cache);
		// Processing components aliases
		if (strpos($namespace, 'modules') === 0 || strpos($namespace, 'plugins') === 0) {
			$Config      = Config::instance();
			$directories = [];
			foreach ($Config->components['modules'] ?: [] as $module_name => $module_data) {
				if ($module_data['active'] == Config\Module_Properties::UNINSTALLED) {
					continue;
				}
				$directories[] = MODULES."/$module_name";
			}
			foreach ($Config->components['plugins'] ?: [] as $plugin_name) {
				$directories[] = PLUGINS."/$plugin_name";
			}
			$class_exploded = explode('\\', $class);
			foreach ($directories as $directory) {
				if (file_exists("$directory/meta.json")) {
					$meta = file_get_json("$directory/meta.json") + ['provide' => []];
					if ($class_exploded[2] != $meta['package'] && in_array($class_exploded[2], (array)$meta['provide'])) {
						$class_exploded[2] = $meta['package'];
						$alias             = implode('\\', $class_exploded);
						$aliases[$class]   = $alias;
						$put_into_cache('aliases', $aliases);
						spl_autoload_call($alias);
						return class_exists($alias) || class_alias($alias, $class);
					}
				}
			}
		}
		return false;
	}
);

/**
 * Clean cache of classes autoload and customization
 */
function clean_classes_cache () {
	@unlink(CACHE.'/classes/autoload');
	@unlink(CACHE.'/classes/aliases');
	@unlink(CACHE.'/classes/modified');
}

/**
 * Get or set modified classes (used in Singleton trait)
 *
 * @param array|null $updated_modified_classes
 *
 * @return array
 */
function modified_classes ($updated_modified_classes = null) {
	static $modified_classes;
	if (!defined('CACHE')) {
		return [];
	}
	/** @noinspection MkdirRaceConditionInspection */
	@mkdir(CACHE.'/classes', 0770, true);
	if (!isset($modified_classes)) {
		$modified_classes = file_exists(CACHE.'/classes/modified') ? file_get_json(CACHE.'/classes/modified') : [];
	}
	if ($updated_modified_classes) {
		$modified_classes = $updated_modified_classes;
		file_put_json(CACHE.'/classes/modified', $modified_classes);
	}
	return $modified_classes;
}

/**
 * Easy getting of translations
 *
 * @param string  $item
 * @param mixed[] $arguments There can be any necessary number of arguments here
 *
 * @return string
 */
function __ ($item, ...$arguments) {
	$L = Language::instance();
	if (func_num_args() > 1) {
		return $L->format($item, ...$arguments);
	} else {
		return $L->$item;
	}
}

/**
 * Get file url by it's destination in file system
 *
 * @param string $source
 *
 * @return false|string
 */
function url_by_source ($source) {
	$Config = Config::instance(true);
	if (!$Config) {
		return false;
	}
	$source = realpath($source);
	if (mb_strpos($source, DIR) === 0) {
		return $Config->core_url().mb_substr($source, mb_strlen(DIR));
	}
	return false;
}

/**
 * Get file destination in file system by it's url
 *
 * @param string $url
 *
 * @return false|string
 */
function source_by_url ($url) {
	$Config = Config::instance(true);
	if (!$Config) {
		return false;
	}
	if (mb_strpos($url, $Config->core_url()) === 0) {
		return DIR.mb_substr($url, mb_strlen($Config->core_url()));
	}
	return false;
}

/**
 * Public cache cleaning
 *
 * @return bool
 */
function clean_pcache () {
	$ok   = true;
	$list = get_files_list(PUBLIC_CACHE, false, 'fd', true, true, 'name|desc');
	foreach ($list as $item) {
		if (is_writable($item)) {
			is_dir($item) ? @rmdir($item) : @unlink($item);
		} else {
			$ok = false;
		}
	}
	return $ok;
}

/**
 * Formatting of time in seconds to human-readable form
 *
 * @param int $time Time in seconds
 *
 * @return string
 */
function format_time ($time) {
	if (!is_numeric($time)) {
		return $time;
	}
	$L   = Language::instance();
	$res = [];
	if ($time >= 31536000) {
		$time_x = round($time / 31536000);
		$time -= $time_x * 31536000;
		$res[] = $L->time($time_x, 'y');
	}
	if ($time >= 2592000) {
		$time_x = round($time / 2592000);
		$time -= $time_x * 2592000;
		$res[] = $L->time($time_x, 'M');
	}
	if ($time >= 86400) {
		$time_x = round($time / 86400);
		$time -= $time_x * 86400;
		$res[] = $L->time($time_x, 'd');
	}
	if ($time >= 3600) {
		$time_x = round($time / 3600);
		$time -= $time_x * 3600;
		$res[] = $L->time($time_x, 'h');
	}
	if ($time >= 60) {
		$time_x = round($time / 60);
		$time -= $time_x * 60;
		$res[] = $L->time($time_x, 'm');
	}
	if ($time > 0 || empty($res)) {
		$res[] = $L->time($time, 's');
	}
	return implode(' ', $res);
}

/**
 * Formatting of data size in bytes to human-readable form
 *
 * @param int      $size
 * @param bool|int $round
 *
 * @return float|string
 */
function format_filesize ($size, $round = false) {
	if (!is_numeric($size)) {
		return $size;
	}
	$L    = Language::prefix('system_filesize_');
	$unit = '';
	if ($size >= 1099511627776) {
		$size /= 1099511627776;
		$unit = " $L->TiB";
	} elseif ($size >= 1073741824) {
		$size /= 1073741824;
		$unit = " $L->GiB";
	} elseif ($size >= 1048576) {
		$size /= 1048576;
		$unit = " $L->MiB";
	} elseif ($size >= 1024) {
		$size /= 1024;
		$unit = " $L->KiB";
	} else {
		$size = "$size $L->Bytes";
	}
	return $round ? round($size, $round).$unit : $size.$unit;
}

/**
 * Get list of timezones
 *
 * @return array
 */
function get_timezones_list () {
	$timezones = [];
	foreach (timezone_identifiers_list() as $timezone) {
		$offset          = (new DateTimeZone($timezone))->getOffset(new DateTime);
		$key             = (39600 + $offset).$timezone;
		$sign            = ($offset < 0 ? '-' : '+');
		$hours           = str_pad(floor(abs($offset / 3600)), 2, 0, STR_PAD_LEFT);
		$minutes         = str_pad(abs(($offset % 3600) / 60), 2, 0, STR_PAD_LEFT);
		$timezones[$key] = [
			'key'   => str_replace('_', ' ', $timezone)." ($sign$hours:$minutes)",
			'value' => $timezone
		];
	}
	ksort($timezones, SORT_NATURAL);
	return array_column($timezones, 'value', 'key');
}

/**
 * Get multilingual value from $Config->core array
 *
 * @param string $item
 *
 * @return false|string
 */
function get_core_ml_text ($item) {
	$Config = Config::instance(true);
	if (!$Config) {
		return false;
	}
	return Text::instance()->process($Config->module('System')->db('texts'), $Config->core[$item], true);
}

/**
 * Set multilingual value from $Config->core array
 *
 * @param string $item
 * @param string $value
 *
 * @return false|string
 */
function set_core_ml_text ($item, $value) {
	$Config = Config::instance(true);
	if (!$Config || !isset($Config->core[$item])) {
		return false;
	}
	return Text::instance()->set($Config->module('System')->db('texts'), 'System/Config/core', $item, $value);
}

/**
 * String representation of HTTP status code
 *
 * @param int $code
 *
 * @return null|string
 */
function status_code_string ($code) {
	switch ($code) {
		case 201:
			$string_code = '201 Created';
			break;
		case 202:
			$string_code = '202 Accepted';
			break;
		case 301:
			$string_code = '301 Moved Permanently';
			break;
		case 302:
			$string_code = '302 Found';
			break;
		case 303:
			$string_code = '303 See Other';
			break;
		case 307:
			$string_code = '307 Temporary Redirect';
			break;
		case 400:
			$string_code = '400 Bad Request';
			break;
		case 403:
			$string_code = '403 Forbidden';
			break;
		case 404:
			$string_code = '404 Not Found';
			break;
		case 405:
			$string_code = '405 Method Not Allowed';
			break;
		case 409:
			$string_code = '409 Conflict';
			break;
		case 429:
			$string_code = '429 Too Many Requests';
			break;
		case 500:
			$string_code = '500 Internal Server Error';
			break;
		case 501:
			$string_code = '501 Not Implemented';
			break;
		case 503:
			$string_code = '503 Service Unavailable';
			break;
		default:
			return null;
	}
	return $string_code;
}

/**
 * Pages navigation based on links
 *
 * @param int             $page       Current page
 * @param int             $total      Total pages number
 * @param callable|string $url        if string - it will be formatted with sprintf with one parameter - page number<br>
 *                                    if callable - one parameter will be given, callable should return url string
 * @param bool            $head_links If <b>true</b> - links with rel="prev" and rel="next" will be added
 *
 * @return bool|string <b>false</b> if single page, otherwise string, set of navigation links
 */
function pages ($page, $total, $url, $head_links = false) {
	if ($total == 1) {
		return false;
	}
	$Page             = Page::instance();
	$original_url     = $url;
	$base_url         = Config::instance()->base_url();
	$url              = function ($page) use ($original_url, $base_url) {
		$href = is_callable($original_url) ? $original_url($page) : sprintf($original_url, $page);
		if (is_string($href) && strpos($href, 'http') !== 0) {
			$href = ltrim($href, '/');
			$href = "$base_url/$href";
		}
		return $href;
	};
	$output           = [];
	$render_page_item = function ($i) use ($Page, $page, $url, $head_links, &$output) {
		$href = $url($i);
		if ($head_links) {
			switch ($i) {
				case $page - 1:
					$Page->link(['href' => $href, 'rel' => 'prev']);
					break;
				case $page + 1:
					$Page->link(['href' => $href, 'rel' => 'next']);
					break;
				case $page:
					$Page->canonical_url($href);
					break;
			}
		}
		$output[] = [
			$i,
			[
				'href'    => $i == $page ? false : $href,
				'is'      => 'cs-link-button',
				'primary' => $i == $page
			]
		];
	};
	if ($total <= 11) {
		array_map($render_page_item, range(1, $total));
	} else {
		if ($page <= 6) {
			array_map($render_page_item, range(1, 7));
			$output[] = [
				'...',
				[
					'disabled' => true
				]
			];
			array_map($render_page_item, range($total - 2, $total));
		} elseif ($page >= $total - 5) {
			array_map($render_page_item, range(1, 3));
			$output[] = [
				'...',
				[
					'disabled' => true
				]
			];
			array_map($render_page_item, range($total - 6, $total));
		} else {
			array_map($render_page_item, range(1, 2));
			$output[] = [
				'...',
				[
					'disabled' => true
				]
			];
			array_map($render_page_item, range($page - 2, $page + 2));
			$output[] = [
				'...',
				[
					'disabled' => true
				]
			];
			array_map($render_page_item, range($total - 1, $total));
		}
	}
	return h::{'a[is=cs-link-button]'}($output);
}

/**
 * Pages navigation based on buttons (for search forms, etc.)
 *
 * @param int                  $page  Current page
 * @param int                  $total Total pages number
 * @param bool|callable|string $url   Adds <i>formaction</i> parameter to every button<br>
 *                                    if <b>false</b> - only form parameter <i>page</i> will we added<br>
 *                                    if string - it will be formatted with sprintf with one parameter - page number<br>
 *                                    if callable - one parameter will be given, callable should return url string
 *
 * @return false|string                        <b>false</b> if single page, otherwise string, set of navigation buttons
 */
function pages_buttons ($page, $total, $url = false) {
	if ($total == 1) {
		return false;
	}
	if (!is_callable($url)) {
		$original_url = $url;
		$url          = function ($page) use ($original_url) {
			return sprintf($original_url, $page);
		};
	}
	$output           = [];
	$render_page_item = function ($i) use ($page, $url, &$output) {
		$output[] = [
			$i,
			[
				'formaction' => $i == $page || $url === false ? false : $url($i),
				'value'      => $i == $page ? false : $i,
				'type'       => $i == $page ? 'button' : 'submit',
				'primary'    => $i == $page
			]
		];
	};
	if ($total <= 11) {
		array_map($render_page_item, range(1, $total));
	} else {
		if ($page <= 6) {
			array_map($render_page_item, range(1, 7));
			$output[] = [
				'...',
				[
					'type' => 'button',
					'disabled'
				]
			];
			array_map($render_page_item, range($total - 2, $total));
		} elseif ($page >= $total - 5) {
			array_map($render_page_item, range(1, 3));
			$output[] = [
				'...',
				[
					'type' => 'button',
					'disabled'
				]
			];
			array_map($render_page_item, range($total - 6, $total));
		} else {
			array_map($render_page_item, range(1, 2));
			$output[] = [
				'...',
				[
					'type' => 'button',
					'disabled'
				]
			];
			array_map($render_page_item, range($page - 2, $page + 2));
			$output[] = [
				'...',
				[
					'type' => 'button',
					'disabled'
				]
			];
			array_map($render_page_item, range($total - 1, $total));
		}
	}
	return h::{'button[is=cs-button][name=page]'}($output);
}

/**
 * Checks whether specified functionality available or not
 *
 * @param string|string[] $functionality One functionality or array of them
 *
 * @return bool `true` if all functionality available, `false` otherwise
 */
function functionality ($functionality) {
	if (is_array($functionality)) {
		$result = true;
		foreach ($functionality as $f) {
			$result = $result && functionality($f);
		}
		return $result;
	}
	$all = Cache::instance()->get(
		'functionality',
		function () {
			$functionality = [];
			$Config        = Config::instance();
			$components    = $Config->components;
			foreach (array_keys($components['modules']) as $module) {
				if (!$Config->module($module)->enabled() || !file_exists(MODULES."/$module/meta.json")) {
					continue;
				}
				$functionality[] = [$module];
				$meta            = file_get_json(MODULES."/$module/meta.json");
				if (isset($meta['provide'])) {
					$functionality[] = (array)$meta['provide'];
				}
			}
			foreach ($components['plugins'] as $plugin) {
				if (!file_exists(PLUGINS."/$plugin/meta.json")) {
					continue;
				}
				$functionality[] = [$plugin];
				$meta            = file_get_json(PLUGINS."/$plugin/meta.json");
				if (isset($meta['provide'])) {
					$functionality[] = (array)$meta['provide'];
				}
			}
			return array_merge(...$functionality);
		}
	);
	return in_array($functionality, $all);
}
