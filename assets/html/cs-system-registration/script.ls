/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
Polymer(
	is			: 'cs-system-registration'
	behaviors	: [
		cs.Polymer.behaviors.Language('system_profile_')
	]
	attached : !->
		@$.email.focus()
	_registration : (e) !->
		e.preventDefault()
		cs.registration(@$.email.value)
)
