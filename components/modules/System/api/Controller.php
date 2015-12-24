<?php
/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
namespace cs\modules\System\api;
use
	cs\modules\System\api\Controller\admin,
	cs\modules\System\api\Controller\general,
	cs\modules\System\api\Controller\profile,
	cs\modules\System\api\Controller\profiles,
	cs\modules\System\api\Controller\user_;
class Controller {
	use
		admin\about_server,
		admin\blocks,
		admin\databases,
		admin\groups,
		admin\languages,
		admin\mail,
		admin\modules,
		admin\optimization,
		admin\permissions,
		admin\plugins,
		admin\site_info,
		admin\storages,
		admin\system,
		admin\themes,
		admin\upload,
		admin\users,
		admin\users\general,
		admin\users\permissions,
		general,
		profile,
		profiles,
		user_;
}
