<?php
/**
 * @package   WebSockets
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs\modules\WebSockets;
use
	cs\Trigger;
Trigger::instance()
	->register(
		'System/User/del_session/after',
		function ($data) {
			Server::instance()->close_by_session($data['id']);
		}
	)
	->register(
		'System/User/del_all_sessions',
		function ($data) {
			Server::instance()->close_by_user($data['id']);
		}
	);
