<?php
/**
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2013-2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace	cs\Page;

/**
 * Class includes few methods used for processing CSS and HTML files before putting into cache.
 *
 * This is because CSS and HTML files may contain other includes of other CSS, JS files, images, fonts and so on with absolute and relative paths.
 * Methods of this class handles all this includes and put them into single resulting file compressed with gzip.
 * This allows to decrease number of HTTP requests on page and avoid breaking of relative paths for fonts, images and other includes
 * after putting them into cache directory.
 */
class Includes_processing {
	/**
	 * Analyses file for images, fonts and css links and include they content into single resulting css file.
	 *
	 * Supports next file extensions for possible includes:
	 * jpeg, jpe, jpg, gif, png, ttf, ttc, svg, svgz, woff, eot, css
	 *
	 * @param string	$data	Content of processed file
	 * @param string	$file	Path to file, that includes specified in previous parameter content
	 *
	 * @return string	$data
	 */
	static function css ($data, $file) {
		$cwd = getcwd();
		chdir(dirname($file));
		/**
		 * Remove comments, tabs and new lines
		 */
		$data	= preg_replace('#(/\*.*?\*/)|\t|\n|\r#s', ' ', $data);
		/**
		 * Remove unnecessary spaces
		 */
		$data	= preg_replace('#\s*([,;+>{}\(])\s*#s', '$1', $data);
		$data	= preg_replace('#\s+#s', ' ', $data);
		/**
		 * Return spaces required in media queries
		 */
		$data	= preg_replace('/\s(and|or)\(/s', ' $1 (', $data);
		/**
		 * Remove unnecessary trailing semicolons
		 */
		$data	= str_replace(';}', '}', $data);
		/**
		 * Minify repeated colors declarations
		 */
		$data	= preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/is', '#$1$2$3', $data);
		/**
		 * Minify rgb colors declarations
		 */
		$data	= preg_replace_callback(
			'/rgb\(([0-9,\.]+)\)/is',
			function ($rgb) {
				$rgb	= explode(',', $rgb[1]);
				return
					'#'.
					str_pad(dechex($rgb[0]), 2, 0, STR_PAD_LEFT).
					str_pad(dechex($rgb[1]), 2, 0, STR_PAD_LEFT).
					str_pad(dechex($rgb[2]), 2, 0, STR_PAD_LEFT);
			},
			$data
		);
		/**
		 * Remove unnecessary zeros
		 */
		$data	= preg_replace('/([^0-9])0\.([0-9]+)/is', '$1.$2', $data);
		/**
		 * Includes processing
		 */
		$data	= preg_replace_callback(
			'/(url\((.*?)\))|(@import[\s\t\n\r]*[\'"](.*?)[\'"])/',
			function ($match) use (&$data) {
				$link		= trim($match[count($match) - 1], '\'" ');
				if (
					mb_strpos($link, 'http://') === 0 ||
					mb_strpos($link, 'https://') === 0 ||
					mb_strpos($link, 'ftp://') === 0 ||
					mb_strpos($link, '/') === 0 ||
					!file_exists($link)
				) {
					return $match[0];
				}
				$extension	= file_extension($link);
				$mime_type	= 'text/html';
				switch ($extension) {
					case 'jpeg':
					case 'jpe':
					case 'jpg':
						$mime_type = 'image/jpg';
					break;
					case 'gif':
						$mime_type = 'image/gif';
					break;
					case 'png':
						$mime_type = 'image/png';
					break;
					case 'ttf':
					case 'ttc':
						$mime_type = 'application/x-font-ttf';
					break;
					case 'svg':
					case 'svgz':
						$mime_type = 'image/svg+xml';
					break;
					case 'woff':
						$mime_type = 'application/x-font-woff';
					break;
					case 'eot':
						$mime_type = 'application/vnd.ms-fontobject';
					break;
					case 'css':
						$mime_type = 'text/css';
					break;
				}
				$content	= file_get_contents($link);
				/**
				 * For recursive includes processing, if CSS file includes others CSS files
				 */
				if ($extension == 'css') {
					$content	= static::css($content, $link);
				}
				$content	= base64_encode($content);
				return str_replace($match[count($match) - 1], "data:$mime_type;charset=utf-8;base64,$content", $match[0]);
			},
			$data
		);
		chdir($cwd);
		return $data;
	}
	/**
	 * Analyses file for scripts and styles, combines them into resulting files in order to optimize loading process
	 * (files with combined scripts and styles will be created)
	 *
	 * @param string	$data				Content of processed file
	 * @param string	$file				Path to file, that includes specified in previous parameter content
	 * @param string	$base_filename		Base filename for resulting combined files
	 * @param string	$destination		Directory where to put combined files or <i>false</i> to make includes built-in (vulcanization)
	 *
	 * @return string	$data
	 */
	static function html ($data, $file, $base_filename, $destination) {
		$cwd = getcwd();
		chdir(dirname($file));
		preg_match_all('/<script(.*)<\/script>/Uims', $data, $scripts);
		if ($scripts) {
			$scripts_content	= '';
			foreach ($scripts[1] as $index => $script) {
				$script	= explode('>', $script);
				if (preg_match('/src\s*=\s*[\'"](.*)[\'"]/Uims', $script[0], $url)) {
					$url	= $url[1];
					if (static::is_relative_path($url) || !file_exists($url)) {
						unset($scripts[0][$index]);
						continue;
					}
					$scripts_content	.= file_get_contents($url).";\n";
				} else {
					$scripts_content	.= $script[1].";\n";
				}
				unset($url);
			}
			unset($index, $script);
			if ($destination) {
				$content_md5	= substr(md5($scripts_content), 0, 5);
				file_put_contents(
					"$destination/$base_filename.js",
					gzencode($scripts_content, 9),
					LOCK_EX | FILE_BINARY
				);
				// Replace first script with combined file
				$data	= str_replace(
					$scripts[0][0],
					"<script src=\"$base_filename.js?$content_md5\"></script>",
					$data
				);
				unset($content_md5);
			} else {
				// Replace first script with combined content
				$data	= str_replace(
					$scripts[0][0],
					$scripts_content,
					$data
				);
			}
			unset($scripts_content);
			// Remove the rest of scripts
			$data	= str_replace($scripts[0], '', $data);
		}
		unset($scripts);
		preg_match_all('/<link(.*)>|<style(.*)<\/style>/Uims', $data, $links_and_styles);
		$links_and_styles	= isset($links_and_styles[1]) ? $links_and_styles : [];
		$shim				= false;
		if ($links_and_styles) {
			$styles_content		= '';
			$imports_content	= '';
			$styles_to_replace	= [];
			$imports_to_replace	= [];
			foreach ($links_and_styles[1] as $index => $link) {
				if (
					$link &&
					preg_match('/stylesheet/Uims', $link) &&
					preg_match('/href\s*=\s*[\'"](.*)[\'"]/Uims', $link, $url)
				) {
					$url	= $url[1];
					if (static::is_relative_path($url) || !file_exists($url)) {
						continue;
					}
					$styles_to_replace[]	= $links_and_styles[0][$index];
					if (preg_match('/shim-shadowdom/Uims', $links_and_styles[0][$index])) {
						$shim = true;
					}
					$styles_content	.= static::css(
						file_get_contents($url),
						$url
					);
				} elseif (
					$link &&
					preg_match('/import/Uims', $link) &&
					preg_match('/href\s*=\s*[\'"](.*)[\'"]/Uims', $link, $url)
				) {
					$url	= $url[1];
					if (static::is_relative_path($url) || !file_exists($url)) {
						continue;
					}
					$imports_to_replace[]	= $links_and_styles[0][$index];
					if (preg_match('/shim-shadowdom/Uims', $links_and_styles[0][$index])) {
						$shim = true;
					}
					$imports_content	.= static::html(
						file_get_contents($url),
						$url,
						$base_filename.'-'.basename($url, '.html'),
						$destination
					);
				} elseif (mb_strpos($links_and_styles[0][$index], '</style>') !== -1) {
					$styles_to_replace[]	= $links_and_styles[0][$index];
					if (preg_match('/shim-shadowdom/Uims', $links_and_styles[0][$index])) {
						$shim = true;
					}
					$style	= explode('>', $links_and_styles[2][$index], 2)[1];
					$styles_content	.= static::css($style, $file);
				} else {
					unset($links_and_styles[0][$index]);
				}
				unset($url, $style);
			}
			unset($index, $link);
			if ($destination) {
				$content_md5	= substr(md5($styles_content), 0, 5);
				file_put_contents(
					"$destination/$base_filename.css",
					gzencode($styles_content, 9),
					LOCK_EX | FILE_BINARY
				);
				// Replace first link or style with combined file
				$shim	= $shim ? ' shim-shadowdom' : '';
				$data	= str_replace(
					$links_and_styles[0][0],
					"<link rel=\"stylesheet\" href=\"$base_filename.css?$content_md5\"$shim>",
					$data
				);
				unset($content_md5);
			} else {
				// Replace first link or style with combined content
				$data	= str_replace(
					$links_and_styles[0][0],
					$styles_content,
					$data
				);
			}
			unset($styles_content);
			// Remove the rest of links and styles
			$data	= str_replace($links_and_styles[0], '', $data);
			// Add imports to the end of file
			$data	.= $imports_content;
			unset($imports_content);
		}
		unset($links_and_styles);
		chdir($cwd);
		return $data;
	}
	protected static function is_relative_path ($path) {
		return !(
			mb_strpos($path, 'http://') === 0 ||
			mb_strpos($path, 'https://') === 0 ||
			mb_strpos($path, 'ftp://') === 0 ||
			mb_strpos($path, '/') === 0
		);
	}
}
