<?php
/**
 * @package   Service worker cache
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs;
Event::instance()->on(
	'System/Page/render/before',
	function () {
		if (Config::instance()->module('Service_worker_cache')->enabled()) {
			$version = file_get_json(__DIR__.'/meta.json')['version'];
			Page::instance()
				->config($version, 'cs.service_worker_cache.version')
				->js("/modules/Service_worker_cache/assets/js/register.js?$version");
		}
	}
);
