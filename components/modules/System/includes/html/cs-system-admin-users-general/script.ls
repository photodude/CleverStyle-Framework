/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
L = cs.Language
Polymer(
	'is'		: 'cs-system-admin-users-general'
	behaviors	: [
		cs.Polymer.behaviors.Language
		cs.Polymer.behaviors.admin.System.settings
	]
	properties	:
		registration_with_confirmation	:
			computed	: '_registration_with_confirmation(settings.allow_user_registration, settings.require_registration_confirmation)'
			type		: Boolean
		settings_api_url	: 'api/System/admin/users/general'
	_registration_with_confirmation : (allow_user_registration, require_registration_confirmation) ->
		allow_user_registration ~= 1 && require_registration_confirmation ~= 1
)
