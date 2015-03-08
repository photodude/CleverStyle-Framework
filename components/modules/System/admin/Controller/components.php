<?php
/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
namespace cs\modules\System\admin\Controller;
use
	cs\Cache,
	cs\Config,
	cs\Core,
	cs\Event,
	cs\Group,
	cs\Index,
	cs\Language,
	cs\Page,
	cs\Permission,
	cs\Text,
	cs\User,
	h;
function get_block_title ($id) {
	$Config = Config::instance();
	return Text::instance()->process($Config->module('System')->db('texts'), $Config->components['blocks'][$id]['title']);
}

function get_block_content ($id) {
	$Config = Config::instance();
	return Text::instance()->process($Config->module('System')->db('texts'), $Config->components['blocks'][$id]['content']);
}

/**
 * Check dependencies for new component (during installation/updating/enabling)
 *
 * @param string      $name Name of new component
 * @param string      $type Type of new component module|plugin
 * @param null|string $dir  Path to new component (if null - component should be found among installed)
 * @param string      $mode Mode of checking for modules install|update|enable
 *
 * @return bool
 */
function check_dependencies ($name, $type, $dir = null, $mode = 'enable') {
	if (!$dir) {
		switch ($type) {
			case 'module':
				$dir = MODULES."/$name";
				break;
			case 'plugin':
				$dir = PLUGINS."/$name";
				break;
			default:
				return false;
		}
	}
	if (!file_exists("$dir/meta.json")) {
		return true;
	}
	$meta   = file_get_json("$dir/meta.json");
	$Config = Config::instance();
	$Core   = Core::instance();
	$L      = Language::instance();
	$Page   = Page::instance();
	if (isset($meta['db_support']) && !empty($meta['db_support'])) {
		$return = false;
		if (in_array($Core->db_type, $meta['db_support'])) {
			$return = true;
		} else {
			foreach ($Config->db as $database) {
				if (isset($database['type']) && in_array($database['type'], $meta['db_support'])) {
					$return = true;
					break;
				}
			}
			unset($database);
		}
		if (!$return) {
			$Page->warning(
				$L->compatible_databases_not_found(
					implode('", "', $meta['db_support'])
				)
			);
		} elseif (!$Config->core['simple_admin_mode']) {
			$Page->success(
				$L->compatible_databases(
					implode('", "', $meta['db_support'])
				)
			);
		}
	} else {
		$return = true;
	}
	if (isset($meta['storage_support']) && !empty($meta['storage_support'])) {
		$return_s = false;
		if (in_array($Core->storage_type, $meta['storage_support'])) {
			$return_s = true;
		} else {
			foreach ($Config->storage as $storage) {
				if (in_array($storage['connection'], $meta['storage_support'])) {
					$return_s = true;
					break;
				}
			}
			unset($storage);
		}
		if (!$return_s) {
			$Page->warning(
				$L->compatible_storages_not_found(
					implode('", "', $meta['storage_support'])
				)
			);
		} elseif (!$Config->core['simple_admin_mode']) {
			$Page->success(
				$L->compatible_storages(
					implode('", "', $meta['storage_support'])
				)
			);
		}
		$return = $return && $return_s;
		unset($return_s);
	}
	$provide  = [];
	$require  = [];
	$conflict = [];
	if (isset($meta['provide'])) {
		$provide = (array)$meta['provide'];
	}
	if (isset($meta['require']) && !empty($meta['require'])) {
		$require = dep_normal((array)$meta['require']);
	}
	if (isset($meta['conflict']) && !empty($meta['conflict'])) {
		$conflict = dep_normal((array)$meta['conflict']);
	}
	unset($meta);
	/**
	 * Checking for compatibility with modules
	 */
	$return_m = true;
	foreach ($Config->components['modules'] as $module => $module_data) {
		/**
		 * If module uninstalled, disabled (in enable check mode), module name is the same as checked or meta.json file absent
		 * Then skip this module
		 */
		if (!file_exists(MODULES."/$module/meta.json")) {
			continue;
		}
		$module_meta = file_get_json(MODULES."/$module/meta.json");
		/** @noinspection NotOptimalIfConditionsInspection */
		if (
			$module_data['active'] == -1 ||
			(
				$mode == 'enable' && $module_data['active'] == 0
			) ||
			(
				$module == $name && $type == 'module'
			)
		) {
			/**
			 * If module updates, check update possibility from current version
			 */
			if (
				$module == $name && $type == 'module' && $mode == 'update' &&
				isset($meta['update_from']) && version_compare($meta['update_from_version'], $module_meta['version'], '>')
			) {
				if ($return_m) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$Page->warning(
					$L->module_cant_be_updated_from_version_to_supported_only($module, $module_meta['version'], $meta['version'], $meta['update_from_version'])
				);
				return false;
			}
			continue;
		}
		/**
		 * If some module already provides the same functionality
		 */
		if (
			!empty($provide) &&
			isset($module_meta['provide']) &&
			!empty($module_meta['provide']) &&
			$intersect = array_intersect($provide, (array)$module_meta['provide'])
		) {
			if ($return_m) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_m = false;
			$Page->warning(
				$L->module_already_provides_functionality(
					$module,
					implode('", "', $intersect)
				)
			);
		}
		unset($intersect);
		/**
		 * Checking for required packages
		 */
		if (!empty($require) && isset($require[$module_meta['package']])) {
			if (
			version_compare(
				$module_meta['version'],
				$require[$module_meta['package']][1],
				$require[$module_meta['package']][0]
			)
			) {
				unset($require[$module_meta['package']]);
			} else {
				if ($return_m) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$return_m = false;
				$Page->warning(
					$L->unsatisfactory_version_of_the_module(
						$module,
						$require[$module_meta['package']][0].' '.$require[$module_meta['package']][1],
						$module_meta['version']
					)
				);
			}
		}
		/**
		 * Checking for required functionality
		 */
		if (
			!empty($require) &&
			isset($module_meta['provide']) &&
			!empty($module_meta['provide'])
		) {
			foreach ((array)$module_meta['provide'] as $p) {
				unset($require[$p]);
			}
			unset($p);
		}
		/**
		 * Checking for conflict packages
		 */
		if (
			!empty($conflict) &&
			isset($module_meta['conflict']) &&
			version_compare(
				$module_meta['version'],
				$conflict[$module_meta['package']][1],
				$conflict[$module_meta['package']][0]
			)
		) {
			if ($return_m) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_m = false;
			$Page->warning(
				$L->conflict_module(
					$module_meta['package'],
					$module
				).
				(
				$conflict[$module_meta['package']][1] != 0 ? $L->compatible_package_versions(
					$require[$module_meta['package']][0].' '.$require[$module_meta['package']][1]
				) : $L->package_is_incompatible(
					$module_meta['package']
				)
				)
			);
		}
	}
	$return = $return && $return_m;
	unset($return_m, $module, $module_data, $module_meta);
	/**
	 * Checking for compatibility with plugins
	 */
	$return_p = true;
	foreach ($Config->components['plugins'] as $plugin) {
		if (
			(
				$plugin == $name && $type == 'plugin'
			) ||
			!file_exists(PLUGINS."/$plugin/meta.json")
		) {
			continue;
		}
		$plugin_meta = file_get_json(PLUGINS."/$plugin/meta.json");
		/**
		 * If some plugin already provides the same functionality
		 */
		if (
			!empty($provide) &&
			isset($plugin_meta['provide']) &&
			is_array($plugin_meta['provide']) &&
			$intersect = array_intersect($provide, $plugin_meta['provide'])
		) {
			if ($return_p) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_p = false;
			$Page->warning(
				$L->plugin_already_provides_functionality(
					$plugin,
					implode('", "', $intersect)
				)
			);
		}
		unset($intersect);
		/**
		 * Checking for required packages
		 */
		if (isset($require[$plugin_meta['package']])) {
			if (
			version_compare(
				$plugin_meta['version'],
				$require[$plugin_meta['package']][1],
				$require[$plugin_meta['package']][0]
			)
			) {
				unset($require[$plugin_meta['package']]);
			} else {
				if ($return_p) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$return_p = false;
				$Page->warning(
					$L->unsatisfactory_version_of_the_plugin(
						$plugin,
						$require[$plugin_meta['package']][0].' '.$require[$plugin_meta['package']][1],
						$plugin_meta['version']
					)
				);
			}
		}
		/**
		 * Checking for required functionality
		 */
		if (
			!empty($require) &&
			isset($plugin_meta['provide']) &&
			!empty($plugin_meta['provide'])
		) {
			foreach ((array)$plugin_meta['provide'] as $p) {
				unset($require[$p]);
			}
			unset($p);
		}
		/**
		 * Checking for conflict packages
		 */
		if (
			isset($plugin_meta['conflict']) &&
			is_array($plugin_meta['conflict']) &&
			version_compare(
				$plugin_meta['version'],
				$conflict[$plugin_meta['package']][1],
				$conflict[$plugin_meta['package']][0]
			)
		) {
			if ($return_p) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_p = false;
			$Page->warning(
				$L->conflict_plugin($plugin).
				(
				$conflict[$plugin_meta['package']][1] != 0 ? $L->compatible_package_versions(
					$require[$plugin_meta['package']][0].' '.$require[$plugin_meta['package']][1]
				) : $L->package_is_incompatible(
					$plugin_meta['package']
				)
				)
			);
		}
	}
	$return = $return && $return_p;
	unset($return_p, $plugin, $plugin_meta, $provide, $conflict);
	/**
	 * If some required packages missing
	 */
	$return_r = true;
	if (!empty($require)) {
		foreach ($require as $package => $details) {
			if ($return_r) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_r = false;
			$Page->warning(
				$L->package_or_functionality_not_found($details[1] ? "$package $details[0] $details[1]" : $package)
			);
		}
	}
	return $return && $return_r;
}

/**
 * Check backward dependencies (during uninstalling/disabling)
 *
 * @param string $name Component name
 * @param string $type Component type module|plugin
 * @param string $mode Mode of checking for modules uninstall|disable
 *
 * @return bool
 */
function check_backward_dependencies ($name, $type = 'module', $mode = 'disable') {
	switch ($type) {
		case 'module':
			$dir = MODULES."/$name";
			break;
		case 'plugin':
			$dir = PLUGINS."/$name";
			break;
		default:
			return false;
	}
	if (!file_exists("$dir/meta.json")) {
		return true;
	}
	$meta   = file_get_json("$dir/meta.json");
	$return = true;
	$Config = Config::instance();
	$L      = Language::instance();
	$Page   = Page::instance();
	/**
	 * Checking for backward dependencies of modules
	 */
	$return_m = true;
	foreach ($Config->components['modules'] as $module => $module_data) {
		/**
		 * If module uninstalled, disabled (in disable check mode), module name is the same as checking or meta.json file does not exists
		 * Then skip this module
		 */
		/** @noinspection NotOptimalIfConditionsInspection */
		if (
			$module_data['active'] == -1 ||
			(
				$mode == 'disable' && $module_data['active'] == 0
			) ||
			(
				$module == $name && $type == 'module'
			) ||
			!file_exists(MODULES."/$module/meta.json")
		) {
			continue;
		}
		$module_require = file_get_json(MODULES."/$module/meta.json");
		if (!isset($module_require['require'])) {
			continue;
		}
		$module_require = dep_normal($module_require['require']);
		if (
			isset($module_require[$meta['package']]) ||
			(
				isset($meta['provide']) && array_intersect(array_keys($module_require), (array)$meta['provide'])
			)
		) {
			if ($return_m) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_m = false;
			$Page->warning($L->this_package_is_used_by_module($module));
		}
	}
	$return = $return && $return_m;
	unset($return_m, $module, $module_data, $module_require);
	/**
	 * Checking for backward dependencies of plugins
	 */
	$return_p = true;
	foreach ($Config->components['plugins'] as $plugin) {
		if (
			(
				$plugin == $name && $type == 'plugin'
			) ||
			!file_exists(PLUGINS."/$plugin/meta.json")
		) {
			continue;
		}
		$plugin_require = file_get_json(PLUGINS."/$plugin/meta.json");
		if (!isset($plugin_require['require'])) {
			continue;
		}
		$plugin_require = dep_normal($plugin_require['require']);
		if (
			isset($plugin_require[$meta['package']]) ||
			(
				isset($meta['provide']) && array_intersect(array_keys($plugin_require), (array)$meta['provide'])
			)
		) {
			if ($return_p) {
				$Page->warning($L->dependencies_not_satisfied);
			}
			$return_p = false;
			$Page->warning($L->this_package_is_used_by_plugin($plugin));
		}
	}
	return $return && $return_p;
}

/**
 * Function for normalization of dependence structure
 *
 * @param array|string $dependence_structure
 *
 * @return array
 */
function dep_normal ($dependence_structure) {
	$return = [];
	foreach ((array)$dependence_structure as $d) {
		preg_match('/^([^<=>!]+)([<=>!]*)(.*)$/', $d, $d);
		$return[$d[1]] = [
			isset($d[2]) && $d[2] ? str_replace('=>', '>=', $d[2]) : (isset($d[3]) && $d[3] ? '=' : '>='),
			isset($d[3]) && $d[3] ? $d[3] : 0
		];
	}
	return $return;
}

trait components {
	static function components_blocks () {
		$Config = Config::instance();
		$L      = Language::instance();
		$Page   = Page::instance();
		$User   = User::instance();
		$a      = Index::instance();
		$rc     = $Config->route;
		$form   = true;
		if (isset($rc[2])) {
			switch ($rc[2]) {
				case 'enable':
					if (!isset($rc[3], $Config->components['blocks'][$rc[3]])) {
						break;
					}
					$Config->components['blocks'][$rc[3]]['active'] = 1;
					$a->save();
					break;
				case 'disable':
					if (!isset($rc[3], $Config->components['blocks'][$rc[3]])) {
						break;
					}
					$Config->components['blocks'][$rc[3]]['active'] = 0;
					$a->save();
					unset(Cache::instance()->{'blocks/'.$Config->components['blocks'][$rc[3]]['index']});
					break;
				case 'delete':
					if (!isset($rc[3], $Config->components['blocks'][$rc[3]])) {
						break;
					}
					$form                  = false;
					$a->buttons            = false;
					$a->cancel_button_back = true;
					$a->action             = 'admin/System/'.$rc[0].'/'.$rc[1];
					$Page->title($L->deletion_of_block(get_block_title($rc[3])));
					$a->content(
						h::{'h2.cs-center'}(
							$L->sure_to_delete_block(get_block_title($rc[3])).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'mode',
									'value' => 'delete'
								]
							).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'id',
									'value' => $rc[3]
								]
							)
						).
						h::{'button.uk-button[type=submit]'}($L->yes)
					);
					break;
				case 'add':
					$form                  = false;
					$a->apply_button       = false;
					$a->cancel_button_back = true;
					$a->form_attributes[]  = 'formnovalidate';
					$Page->title($L->adding_a_block);
					$a->content(
						h::{'h2.cs-center'}(
							$L->adding_a_block
						).
						h::{'cs-table[center][right-left] cs-table-row| cs-table-cell'}(
							[
								h::info('block_type'),
								h::select(
									array_merge(['html', 'raw_html'], _mb_substr(get_files_list(BLOCKS, '/^block\..*?\.php$/i', 'f'), 6, -4)),
									[
										'name'     => 'block[type]',
										'size'     => 5,
										'onchange' => 'cs.block_switch_textarea(this)'
									]
								)
							],
							[
								h::info('block_title'),
								h::input(
									[
										'name' => 'block[title]'
									]
								)
							],
							[
								h::info('block_active'),
								h::{'div radio'}(
									[
										'name'  => 'block[active]',
										'value' => [1, 0],
										'in'    => [$L->yes, $L->no]
									]
								)
							],
							[
								h::info('block_template'),
								h::select(
									_mb_substr(get_files_list(TEMPLATES.'/blocks', '/^block\..*?\.(php|html)$/i', 'f'), 6),
									[
										'name' => 'block[template]',
										'size' => 5
									]
								)
							],
							[
								h::info('block_start'),
								h::{'input[type=datetime-local]'}(
									[
										'name'  => 'block[start]',
										'value' => date('Y-m-d\TH:i', TIME)
									]
								)
							],
							[
								h::info('block_expire'),
								h::radio(
									[
										'name'  => 'block[expire][state]',
										'value' => [0, 1],
										'in'    => [$L->never, $L->as_specified]
									]
								).
								h::br(2).
								h::{'input[type=datetime-local]'}(
									[
										'name'  => 'block[expire][date]',
										'value' => date('Y-m-d\TH:i', TIME)
									]
								)
							]
						).
						h::{'div#cs-block-content-html textarea.EDITOR'}(
							'',
							[
								'name' => 'block[html]'
							]
						).
						h::{'div#cs-block-content-raw-html[style=display:none;] textarea'}(
							'',
							[
								'name' => 'block[raw_html]'
							]
						).
						h::{'input[type=hidden]'}(
							[
								'name'  => 'mode',
								'value' => $rc[2]
							]
						)
					);
					break;
				case 'edit':
					if (!isset($rc[3], $Config->components['blocks'][$rc[3]])) {
						break;
					}
					$form                  = false;
					$a->apply_button       = false;
					$a->cancel_button_back = true;
					$a->form_attributes[]  = 'formnovalidate';
					$block                 = &$Config->components['blocks'][$rc[3]];
					$Page->title($L->editing_a_block(get_block_title($rc[3])));
					$a->content(
						h::{'h2.cs-center'}(
							$L->editing_a_block(get_block_title($rc[3]))
						).
						h::{'cs-table[center][right-left] cs-table-row| cs-table-cell'}(
							[
								h::info('block_title'),
								h::input(
									[
										'name'  => 'block[title]',
										'value' => get_block_title($rc[3])
									]
								)
							],
							[
								h::info('block_active'),
								h::{'div radio'}(
									[
										'name'    => 'block[active]',
										'checked' => $block['active'],
										'value'   => [1, 0],
										'in'      => [$L->yes, $L->no]
									]
								)
							],
							[
								h::info('block_template'),
								h::select(
									[
										'in' => _mb_substr(get_files_list(TEMPLATES.'/blocks', '/^block\..*?\.(php|html)$/i', 'f'), 6)
									],
									[
										'name'     => 'block[template]',
										'selected' => $block['template'],
										'size'     => 5
									]
								)
							],
							[
								h::info('block_start'),
								h::{'input[type=datetime-local]'}(
									[
										'name'  => 'block[start]',
										'value' => date('Y-m-d\TH:i', $block['start'] ?: TIME)
									]
								)
							],
							[
								h::info('block_expire'),
								h::radio(
									[
										'name'    => 'block[expire][state]',
										'checked' => $block['expire'] != 0,
										'value'   => [0, 1],
										'in'      => [$L->never, $L->as_specified]
									]
								).
								h::br(2).
								h::{'input[type=datetime-local]'}(
									[
										'name'  => 'block[expire][date]',
										'value' => date('Y-m-d\TH:i', $block['expire'] ?: TIME)
									]
								)
							]
						).
						(
						$block['type'] == 'html'
							? h::{'textarea.EDITOR'}(
							get_block_content($rc[3]),
							[
								'name' => 'block[html]'
							]
						)
							: (
						$block['type'] == 'raw_html' ? h::textarea(
							get_block_content($rc[3]),
							[
								'name' => 'block[raw_html]'
							]
						) : ''
						)
						).
						h::{'input[type=hidden]'}(
							[
								[
									[
										'name'  => 'block[id]',
										'value' => $rc[3]
									]
								],
								[
									[
										'name'  => 'mode',
										'value' => $rc[2]
									]
								]
							]
						)
					);
					break;
				case 'permissions':
					if (!isset($rc[3], $Config->components['blocks'][$rc[3]])) {
						break;
					}
					$form                  = false;
					$a->apply_button       = false;
					$a->cancel_button_back = true;
					$permission            = Permission::instance()->get(null, 'Block', $Config->components['blocks'][$rc[3]]['index'])[0]['id'];
					$groups                = Group::instance()->get_all();
					$groups_content        = [];
					foreach ($groups as $group) {
						$group_permission = $User->db()->qfs(
							[
								"SELECT `value`
					FROM `[prefix]groups_permissions`
					WHERE
						`id`			= '%s' AND
						`permission`	= '%s'",
								$group['id'],
								$permission
							]
						);
						$groups_content[] = h::cs_table_cell(
							[
								$group['title'],
								[
									'data-title' => $group['description']
								]
							],
							h::radio(
								[
									'name'    => "groups[$group[id]]",
									'checked' => $group_permission === false ? -1 : $group_permission,
									'value'   => [-1, 0, 1],
									'in'      => [$L->inherited, $L->deny, $L->allow]
								]
							)
						);
					}
					unset($groups, $group, $group_permission);
					if (count($groups_content) % 2) {
						$groups_content[] = h::cs_table_cell().h::cs_table_cell();
					}
					$count    = count($groups_content);
					$content_ = [];
					for ($i = 0; $i < $count; $i += 2) {
						$content_[] = $groups_content[$i].$groups_content[$i + 1];
					}
					$groups_content = $content_;
					unset($count, $content_);
					$users_list    = $User->db()->qfa(
						[
							"SELECT
					`id`,
					`value`
				FROM `[prefix]users_permissions`
				WHERE `permission` = '%s'",
							$permission
						]
					);
					$users_content = [];
					foreach ($users_list as &$user) {
						$value           = $user['value'];
						$user            = $user['id'];
						$users_content[] = h::cs_table_cell(
							$User->username($user),
							h::radio(
								[
									'name'    => 'users['.$user.']',
									'checked' => $value,
									'value'   => [-1, 0, 1],
									'in'      => [$L->inherited, $L->deny, $L->allow]
								]
							)
						);
					}
					unset($user, $value);
					$Page->title($L->permissions_for_block(get_block_title($rc[3])));
					$a->content(
						h::{'h2.cs-center'}(
							$L->permissions_for_block(get_block_title($rc[3]))
						).
						h::{'ul.cs-tabs li'}(
							$L->groups,
							$L->users
						).
						h::{'div div'}(
							h::{'p.cs-left'}(
								h::{'button.uk-button.cs-permissions-invert'}($L->invert).
								h::{'button.uk-button.cs-permissions-allow-all'}($L->allow_all).
								h::{'button.uk-button.cs-permissions-deny-all'}($L->deny_all)
							).
							h::{'cs-table[right-left] cs-table-row'}($groups_content),
							h::{'p.cs-left'}(
								h::{'button.uk-button.cs-permissions-invert'}($L->invert).
								h::{'button.uk-button.cs-permissions-allow-all'}($L->allow_all).
								h::{'button.uk-button.cs-permissions-deny-all'}($L->deny_all)
							).
							h::{'cs-table#cs-block-users-changed-permissions[right-left] cs-table-row'}($users_content).
							h::{'input#block_users_search[type=search]'}(
								[
									'autocomplete' => 'off',
									'permission'   => $permission,
									'placeholder'  => $L->type_username_or_email_press_enter,
									'style'        => 'width: 100%'
								]
							).
							h::{'div#block_users_search_results'}()
						).
						h::{'input#cs-block-users-search-found[type=hidden]'}(
							[
								'value' => implode(',', $users_list)
							]
						).
						h::br().
						h::{'input[type=hidden]'}(
							[
								[
									[
										'name'  => 'block[id]',
										'value' => $rc[3]
									]
								],
								[
									[
										'name'  => 'mode',
										'value' => $rc[2]
									]
								]
							]
						)
					);
			}
		}
		if ($form) {
			$a->custom_buttons .= h::{'button.uk-button.cs-reload-button'}(
				$L->reset
			);
			$blocks_array = [
				'top'      => '',
				'left'     => '',
				'floating' => '',
				'right'    => '',
				'bottom'   => ''
			];
			if (!empty($Config->components['blocks'])) {
				foreach ($Config->components['blocks'] as $id => $block) {
					$blocks_array[$block['position']] .= h::li(
						h::{'div.cs-blocks-items-title'}("#$block[index] ".get_block_title($id)).
						h::a(
							[
								h::{'div icon'}('pencil'),
								[
									'href'       => "$a->action/edit/$id",
									'data-title' => $L->edit
								]
							],
							[
								h::{'div icon'}('key'),
								[
									'href'       => "$a->action/permissions/$id",
									'data-title' => $L->edit_permissions
								]
							],
							[
								h::{'div icon'}($block['active'] ? 'minus' : 'check'),
								[
									'href'       => "$a->action/".($block['active'] ? 'disable' : 'enable')."/$id",
									'data-title' => $L->{$block['active'] ? 'disable' : 'enable'}
								]
							],
							[
								h::{'div icon'}('trash-o'),
								[
									'href'       => "$a->action/delete/$id",
									'data-title' => $L->delete
								]
							]
						),
						[
							'data-id' => $id,
							'class'   => $block['active'] ? 'uk-button-success' : 'uk-button-default'
						]
					);
					unset($block_data);
				}
				unset($id, $block);
			}
			foreach ($blocks_array as $position => &$content) {
				$content = h::{'cs-table-cell.cs-blocks-items-groups ul.cs-blocks-items'}(
					h::{'li.uk-button-primary'}(
						$L->{"{$position}_blocks"},
						[
							'onClick' => "cs.blocks_toggle('$position');"
						]
					).
					$content,
					[
						'data-mode' => 'open',
						'id'        => "cs-{$position}-blocks-items"
					]
				);
			}
			unset($position, $content);
			$a->content(
				h::{'cs-table cs-table-row'}(
					[
						h::cs_table_cell().$blocks_array['top'].h::cs_table_cell(),
						"$blocks_array[left]$blocks_array[floating]$blocks_array[right]",
						h::cs_table_cell().$blocks_array['bottom'].h::cs_table_cell()
					]
				).
				h::{'p.cs-left a.uk-button'}(
					"$L->add $L->block",
					[
						'href' => "admin/System/$rc[0]/$rc[1]/add"
					]
				).
				h::{'input#cs-blocks-position[type=hidden][name=position]'}()
			);
		}
	}
	static function components_databases () {
		$Config      = Config::instance();
		$Core        = Core::instance();
		$L           = Language::instance();
		$Page        = Page::instance();
		$a           = Index::instance();
		$rc          = $Config->route;
		$test_dialog = false;
		if (isset($rc[2])) {
			$a->apply_button       = false;
			$a->cancel_button_back = true;
			switch ($rc[2]) {
				case 'edit':
					if (!isset($rc[3])) {
						break;
					}
				case 'add':
					$test_dialog = true;
					if ($rc[2] == 'edit') {
						if (isset($rc[4])) {
							$database = &$Config->db[$rc[3]]['mirrors'][$rc[4]];
						} else {
							$database = &$Config->db[$rc[3]];
						}
						$mirror = isset($rc[4]);
						$cdb    = $Config->db[$rc[3]];
						if ($mirror) {
							$cdbm = $Config->db[$rc[3]]['mirrors'][$rc[4]];
							$name = "$L->mirror ".($rc[3] ? "$L->db $cdb[name]" : $L->core_db).", $cdbm[name] ($cdbm[host]/$cdbm[type])?";
							unset($cdbm);
						} else {
							$name = "$L->db $cdb[name] ($cdb[host]/$cdb[type])?";
						}
						unset($mirror, $cdb);
					} elseif ($rc[2] == 'add') {
						$dbs     = [-1, 0];
						$dbsname = [$L->separate_db, $L->core_db];
						foreach ($Config->db as $i => $db) {
							if ($i) {
								$dbs[]     = $i;
								$dbsname[] = $db['name'];
							}
						}
						unset($i, $db);
					}
					$a->action = "admin/System/$rc[0]/$rc[1]";
					/**
					 * @var array  $dbsname
					 * @var array  $dbs
					 * @var array  $database
					 * @var string $name
					 */
					$Page->title($rc[2] == 'edit' ? $L->editing_the_database($name) : $L->addition_of_db);
					$a->content(
						h::{'h2.cs-center'}(
							$rc[2] == 'edit' ? $L->editing_the_database($name) : $L->addition_of_db
						).
						h::{'cs-table[center][right-left] cs-table-row| cs-table-cell'}(
							[
								h::info($rc[2] == 'add' ? 'db_mirror' : false),
								$rc[2] == 'add'
									? h::select(
									[
										'in'    => $dbsname,
										'value' => $dbs
									],
									[
										'name'     => 'db[mirror]',
										'selected' => isset($rc[3]) ? $rc[3] : -1,
										'size'     => 5
									]
								)
									: false
							],
							[
								h::info('db_host'),
								h::input(
									[
										'name'  => 'db[host]',
										'value' => $rc[2] == 'edit' ? $database['host'] : $Core->db_host
									]
								)
							],
							[
								h::info('db_type'),
								h::select(
									[
										'in' => _mb_substr(get_files_list(ENGINES.'/DB', '/^[^_].*?\.php$/i', 'f'), 0, -4)
									],
									[
										'name'     => 'db[type]',
										'selected' => $rc[2] == 'edit' ? $database['type'] : $Core->db_type,
										'size'     => 5
									]
								)
							],
							[
								h::info('db_prefix'),
								h::input(
									[
										'name'  => 'db[prefix]',
										'value' => $rc[2] == 'edit' ? $database['prefix'] : $Core->db_prefix
									]
								)
							],
							[
								h::info('db_name'),
								h::input(
									[
										'name'  => 'db[name]',
										'value' => $rc[2] == 'edit' ? $database['name'] : ''
									]
								)
							],
							[
								h::info('db_user'),
								h::input(
									[
										'name'  => 'db[user]',
										'value' => $rc[2] == 'edit' ? $database['user'] : ''
									]
								)
							],
							[
								h::info('db_password'),
								h::input(
									[
										'name'  => 'db[password]',
										'value' => $rc[2] == 'edit' ? $database['password'] : ''
									]
								)
							],
							[
								h::info('db_charset'),
								h::input(
									[
										'name'  => 'db[charset]',
										'value' => $rc[2] == 'edit' ? $database['charset'] : $Core->db_charset
									]
								).
								h::{'input[type=hidden]'}(
									[
										'name'  => 'mode',
										'value' => $rc[2] == 'edit' ? 'edit' : 'add'
									]
								)
							]
						).
						(
						isset($rc[3])
							? h::{'input[type=hidden]'}(
							[
								'name'  => 'database',
								'value' => $rc[3]
							]
						)
							: ''
						).
						(
						isset($rc[4])
							? h::{'input[type=hidden]'}(
							[
								'name'  => 'mirror',
								'value' => $rc[4]
							]
						)
							: ''
						).
						h::{'p button.uk-button'}(
							$L->test_connection,
							[
								'onMouseDown' => "cs.db_test();"
							]
						)
					);
					break;
				case 'delete':
					$a->buttons = false;
					$content    = [];
					if (!isset($rc[4])) {
						foreach ($Config->components['modules'] as $module => &$mdata) {
							if (isset($mdata['db']) && is_array($mdata['db'])) {
								foreach ($mdata['db'] as $db_name) {
									if ($db_name == $rc[3]) {
										$content[] = h::b($module);
										break;
									}
								}
							}
						}
						unset($module, $mdata, $db_name);
					}
					if (!empty($content)) {
						$Page->warning($L->db_used_by_modules.': '.implode(', ', $content));
					} else {
						$a->action = 'admin/System/'.$rc[0].'/'.$rc[1];
						$mirror    = isset($rc[4]);
						$cdb       = $Config->db[$rc[3]];
						if ($mirror) {
							$cdbm = $Config->db[$rc[3]]['mirrors'][$rc[4]];
							$name = "$L->mirror ".($rc[3] ? "$L->db $cdb[name]" : $L->core_db).", $cdbm[name] ($cdbm[host]/$cdbm[type])?";
							unset($cdbm);
						} else {
							$name = "$L->db $cdb[name] ($cdb[host]/$cdb[type])?";
						}
						unset($mirror, $cdb);
						$Page->title($L->deletion_of_database($name));
						$a->content(
							h::{'h2.cs-center'}(
								$L->sure_to_delete.' '.$name.
								h::{'input[type=hidden]'}(
									[
										[
											[
												'name'  => 'mode',
												'value' => $rc[2]
											]
										],
										[
											[
												'name'  => 'database',
												'value' => $rc[3]
											]
										]
									]
								).
								(isset($rc[4]) ? h::{'input[type=hidden]'}(
									[
										'name'  => 'mirror',
										'value' => $rc[4]
									]
								) : '')
							).
							h::{'button.uk-button[type=submit]'}($L->yes)
						);
					}
			}
		} else {
			$test_dialog = true;
			$db_list     = [];
			$databases   = $Config->db;
			if (!empty($databases)) {
				foreach ($databases as $i => &$db_data) {
					$db_list[] = [
						[
							[
								h::{'a.uk-button.cs-button-compact'}(
									[
										h::icon('plus'),
										[
											'href'       => "$a->action/add/$i",
											'data-title' => "$L->add $L->mirror $L->of_db"
										]
									],
									$i ? [
										h::icon('pencil'),
										[
											'href'       => "$a->action/edit/$i",
											'data-title' => "$L->edit $L->db"
										]
									] : false,
									$i ? [
										h::icon('trash-o'),
										[
											'href'       => "$a->action/delete/$i",
											'data-title' => $L->delete.' '.$L->db
										]
									] : false,
									[
										h::icon('signal'),
										[
											'onMouseDown' => "cs.db_test($i);",
											'data-title'  => $L->test_connection
										]
									]
								),
								[
									'class' => 'cs-left-all'
								]
							],
							$i ? $db_data['host'] : $Core->db_host,
							$i ? $db_data['type'] : $Core->db_type,
							$i ? $db_data['prefix'] : $Core->db_prefix,
							$i ? $db_data['name'] : $Core->db_name,
							$i ? $db_data['user'] : '*****',
							$i ? $db_data['charset'] : $Core->db_charset
						],
						[
							'class' => $i ? '' : 'text-primary'
						]
					];
					foreach ($Config->db[$i]['mirrors'] as $m => &$mirror) {
						if (is_array($mirror) && !empty($mirror)) {
							$db_list[] = [
								[
									h::{'a.uk-button.cs-button-compact'}(
										[
											h::icon('pencil'),
											[
												'href'       => "admin/System/$rc[0]/$rc[1]/edit/$i/$m",
												'data-title' => "$L->edit $L->mirror $L->of_db"
											]
										],
										[
											h::icon('trash-o'),
											[
												'href'       => "admin/System/$rc[0]/$rc[1]/delete/$i/$m",
												'data-title' => "$L->delete $L->mirror $L->of_db"
											]
										],
										[
											h::icon('signal'),
											[
												'onMouseDown' => "cs.db_test($i, $m);",
												'data-title'  => $L->test_connection
											]
										]
									),
									[
										'class' => 'cs-right-all'
									]
								],
								$mirror['host'],
								$mirror['type'],
								$mirror['prefix'],
								$mirror['name'],
								$mirror['user'],
								$mirror['charset']
							];
						}
					}
					unset($m, $mirror);
				}
				unset($i, $db_data);
			}
			unset($databases);
			$a->content(
				h::{'cs-table[list][with-header]'}(
					h::{'cs-table-row cs-table-cell'}(
						$L->action,
						$L->db_host,
						$L->db_type,
						$L->db_prefix,
						$L->db_name,
						$L->db_user,
						$L->db_charset
					).
					h::{'cs-table-row| cs-table-cell'}($db_list ? [$db_list] : false)
				).
				h::{'cs-table[right-left] cs-table-row| cs-table-cell'}(
					[
						[
							h::info('db_balance'),
							h::radio(
								[
									'name'    => 'core[db_balance]',
									'checked' => $Config->core['db_balance'],
									'value'   => [0, 1],
									'in'      => [$L->off, $L->on]
								]
							)
						],
						[
							h::info('maindb_for_write'),
							h::radio(
								[
									'name'    => 'core[maindb_for_write]',
									'checked' => $Config->core['maindb_for_write'],
									'value'   => [0, 1],
									'in'      => [$L->off, $L->on]
								]
							)
						]
					]
				).
				h::{'p a.uk-button'}(
					$L->add_database,
					[
						'href' => "admin/System/$rc[0]/$rc[1]/add"
					]
				).
				h::{'input[type=hidden]'}(
					[
						'name'  => 'mode',
						'value' => 'config'
					]
				)
			);
		}
		$test_dialog && $a->content(
			h::{'div#cs-db-test.uk-modal div'}(
				h::h3($L->test_connection).
				h::div()
			)
		);
	}
	/**
	 * Provides next events:
	 *  admin/System/components/modules/install/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/update/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/uninstall/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/update_system/prepare
	 *
	 *  admin/System/components/modules/default_module/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/db/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/storage/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/enable/prepare
	 *  ['name' => module_name]
	 *
	 *  admin/System/components/modules/disable/prepare
	 *  ['name' => module_name]
	 */
	static function components_modules () {
		$Config       = Config::instance();
		$L            = Language::instance();
		$Page         = Page::instance();
		$User         = User::instance();
		$a            = Index::instance();
		$rc           = $Config->route;
		$a->buttons   = false;
		$show_modules = true;
		if (
			isset($rc[2]) &&
			!empty($rc[2]) &&
			(
				in_array($rc[2], ['update_system', 'remove']) ||
				(
					isset($rc[3], $Config->components['modules'][$rc[3]]) ||
					(
						isset($rc[3]) && $rc[2] == 'install' && $rc[3] == 'upload'
					)
				)
			)
		) {
			switch ($rc[2]) {
				case 'install':
					if ($rc[3] == 'upload' && isset($_FILES['upload_module']) && $_FILES['upload_module']['tmp_name']) {
						switch ($_FILES['upload_module']['error']) {
							case UPLOAD_ERR_INI_SIZE:
							case UPLOAD_ERR_FORM_SIZE:
								$Page->warning($L->file_too_large);
								break;
							case UPLOAD_ERR_NO_TMP_DIR:
								$Page->warning($L->temporary_folder_is_missing);
								break;
							case UPLOAD_ERR_CANT_WRITE:
								$Page->warning($L->cant_write_file_to_disk);
								break;
							case UPLOAD_ERR_PARTIAL:
							case UPLOAD_ERR_NO_FILE:
								break;
						}
						if ($_FILES['upload_module']['error'] != UPLOAD_ERR_OK) {
							break;
						}
						$tmp_file = TEMP.'/'.md5($_FILES['upload_module']['tmp_name'].openssl_random_pseudo_bytes(1000)).'.phar';
						move_uploaded_file($_FILES['upload_module']['tmp_name'], $tmp_file);
						$tmp_dir     = "phar://$tmp_file";
						$module_name = file_get_contents("$tmp_dir/dir");
						if (!$module_name) {
							unlink($tmp_file);
							break;
						}
						$rc[3] = $module_name;
						/** @noinspection NotOptimalIfConditionsInspection */
						if (!file_exists("$tmp_dir/meta.json") || file_get_json("$tmp_dir/meta.json")['category'] != 'modules') {
							$Page->warning($L->this_is_not_module_installer_file);
							unlink($tmp_file);
							break;
						}
						if (isset($Config->components['modules'][$module_name])) {
							$current_version = file_get_json(MODULES."/$module_name/meta.json")['version'];
							$new_version     = file_get_json("$tmp_dir/meta.json")['version'];
							if (!version_compare($current_version, $new_version, '<')) {
								$Page->warning($L->update_module_impossible_older_version($module_name));
								unlink($tmp_file);
								break;
							}
							if (!Event::instance()->fire(
								'admin/System/components/modules/update/prepare',
								[
									'name' => $module_name
								]
							)
							) {
								break;
							}
							$check_dependencies = check_dependencies($module_name, 'module', $tmp_dir, 'update');
							if (!$check_dependencies && $Config->core['simple_admin_mode']) {
								break;
							}
							$rc[2]        = 'update';
							$show_modules = false;
							$Page->title($L->updating_of_module($module_name));
							rename($tmp_file, $tmp_file = TEMP.'/'.$User->get_session_id().'_module_update.phar');
							$a->content(
								h::{'h2.cs-center'}(
									$L->update_module(
										$module_name,
										$current_version,
										$new_version
									)
								)
							);
							$a->cancel_button_back = true;
							$a->content(
								h::{'button.uk-button[type=submit]'}($L->{$check_dependencies ? $L->yes : 'force_update_not_recommended'})
							);
							break;
						}
						if (!file_exists(MODULES."/$module_name") && !mkdir(MODULES."/$module_name", 0770)) {
							$Page->warning($L->cant_unpack_module_no_write_permissions);
							unlink($tmp_file);
							break;
						}
						$fs      = file_get_json("$tmp_dir/fs.json");
						$extract = array_product(
							array_map(
								function ($index, $file) use ($tmp_dir, $module_name) {
									if (
										!file_exists(dirname(MODULES."/$module_name/$file")) &&
										!mkdir(dirname(MODULES."/$module_name/$file"), 0770, true)
									) {
										return 0;
									}
									return (int)copy("$tmp_dir/fs/$index", MODULES."/$module_name/$file");
								},
								$fs,
								array_keys($fs)
							)
						);
						file_put_json(MODULES."/$module_name/fs.json", array_keys($fs));
						unlink($tmp_file);
						unset($tmp_file, $tmp_dir);
						if (!$extract) {
							$Page->warning($L->module_files_unpacking_error);
							break;
						}
						$Config->components['modules'][$module_name] = [
							'active'  => -1,
							'db'      => [],
							'storage' => []
						];
						unset($module_name);
						ksort($Config->components['modules'], SORT_STRING | SORT_FLAG_CASE);
						$Config->save();
					} elseif ($rc[3] == 'upload') {
						break;
					}
					$show_modules = false;
					$Page->title($L->installation_of_module($rc[3]));
					$a->content(
						h::{'h2.cs-center'}(
							$L->installation_of_module($rc[3])
						)
					);
					if (!Event::instance()->fire(
						'admin/System/components/modules/install/prepare',
						[
							'name' => $rc[3]
						]
					)
					) {
						break;
					}
					$check_dependencies = check_dependencies($rc[3], 'module', null, 'install');
					if (!$check_dependencies && $Config->core['simple_admin_mode']) {
						break;
					}
					if (file_exists(MODULES."/$rc[3]/meta.json")) {
						$meta = file_get_json(MODULES."/$rc[3]/meta.json");
						if (isset($meta['optional'])) {
							$Page->success(
								$L->for_complete_feature_set(
									implode(', ', (array)$meta['optional'])
								)
							);
						}
						unset($meta);
					}
					$a->cancel_button_back = true;
					if ($Config->core['simple_admin_mode']) {
						if (file_exists(MODULES."/$rc[3]/meta/db.json")) {
							$db_json = file_get_json(MODULES."/$rc[3]/meta/db.json");
							foreach ($db_json as $database) {
								$a->content(
									h::{'input[type=hidden]'}(
										[
											'name'  => "db[$database]",
											'value' => 0
										]
									)
								);
							}
							unset($db_json, $database);
						}
						if (file_exists(MODULES."/$rc[3]/meta/storage.json")) {
							$storage_json = file_get_json(MODULES."/$rc[3]/meta/storage.json");
							foreach ($storage_json as $storage) {
								$a->content(
									h::{'input[type=hidden]'}(
										[
											'name'  => "storage[$storage]",
											'value' => 0
										]
									)
								);
							}
							unset($storage_json, $storage);
						}
					} else {
						goto module_db_settings;
						back_to_module_installation_1:
						goto module_storage_settings;
						back_to_module_installation_2:
					}
					$a->content(
						h::{'button.uk-button[type=submit]'}(
							$L->{$check_dependencies ? 'install' : 'force_install_not_recommended'}
						)
					);
					break;
				case 'uninstall':
					$show_modules = false;
					$Page->title($L->uninstallation_of_module($rc[3]));
					$a->content(
						h::{'h2.cs-center'}(
							$L->uninstallation_of_module($rc[3])
						)
					);
					if (!Event::instance()->fire(
						'admin/System/components/modules/uninstall/prepare',
						[
							'name' => $rc[3]
						]
					)
					) {
						break;
					}
					$check_dependencies = check_backward_dependencies($rc[3], 'module', 'uninstall');
					if (!$check_dependencies && $Config->core['simple_admin_mode']) {
						break;
					}
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}(
							$L->{$check_dependencies ? 'uninstall' : 'force_uninstall_not_recommended'}
						)
					);
					break;
				case 'update_system':
					if (!isset($_FILES['upload_system']) || !$_FILES['upload_system']['tmp_name']) {
						break;
					}
					switch ($_FILES['upload_system']['error']) {
						case UPLOAD_ERR_INI_SIZE:
						case UPLOAD_ERR_FORM_SIZE:
							$Page->warning($L->file_too_large);
							break;
						case UPLOAD_ERR_NO_TMP_DIR:
							$Page->warning($L->temporary_folder_is_missing);
							break;
						case UPLOAD_ERR_CANT_WRITE:
							$Page->warning($L->cant_write_file_to_disk);
							break;
						case UPLOAD_ERR_PARTIAL:
						case UPLOAD_ERR_NO_FILE:
							break;
					}
					if ($_FILES['upload_system']['error'] != UPLOAD_ERR_OK) {
						break;
					}
					move_uploaded_file(
						$_FILES['upload_system']['tmp_name'],
						$tmp_file = TEMP.'/'.md5($_FILES['upload_system']['tmp_name'].openssl_random_pseudo_bytes(1000)).'.phar'
					);
					$tmp_dir = "phar://$tmp_file";
					if (!file_exists("$tmp_dir/version") || !file_exists("$tmp_dir/themes.json")) {
						$Page->warning($L->this_is_not_system_installer_file);
						unlink($tmp_file);
						break;
					}
					$current_version = file_get_json(MODULES.'/System/meta.json')['version'];
					$new_version     = file_get_json("$tmp_dir/version");
					if (!version_compare($current_version, $new_version, '<')) {
						$Page->warning($L->update_system_impossible_older_version);
						unlink($tmp_file);
						break;
					}
					$new_meta = file_get_json("$tmp_dir/fs.json")['components/modules/System/meta.json'];
					$new_meta = file_get_json("$tmp_dir/fs/$new_meta");
					if (isset($new_meta['update_from_version']) && version_compare($new_meta['update_from_version'], $current_version, '>')) {
						$Page->warning(
							$L->update_system_impossible_from_version_to($current_version, $new_version, $new_meta['update_from_version'])
						);
						unlink($tmp_file);
						break;
					}
					unset($new_meta);
					$rc[2]        = 'update_system';
					$show_modules = false;
					if (!Event::instance()->fire('admin/System/components/modules/update_system/prepare')) {
						break;
					}
					$Page->title($L->updating_of_system);
					rename($tmp_file, $tmp_file = TEMP.'/'.$User->get_session_id().'_update_system.phar');
					$a->content(
						h::{'h2.cs-center'}(
							$L->update_system(
								$current_version,
								$new_version
							)
						).
						h::{'button.uk-button[type=submit]'}($L->yes)
					);
					$rc[3]                 = 'System';
					$a->cancel_button_back = true;
					break;
					break;
				case 'default_module':
					$show_modules = false;
					$Page->title($L->setting_default_module($rc[3]));
					$a->content(
						h::{'h2.cs-center'}(
							$L->setting_default_module($rc[3])
						)
					);
					if (!Event::instance()->fire(
						'admin/System/components/modules/default_module/prepare',
						[
							'name' => $rc[3]
						]
					)
					) {
						break;
					}
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}($L->yes)
					);
					break;
				case 'db':
					$show_modules = false;
					if (count($Config->db) > 1) {
						$Page->warning($L->changing_settings_warning);
						$Page->title($L->db_settings_for_module($rc[3]));
						$a->content(
							h::{'h2.cs-center'}(
								$L->db_settings_for_module($rc[3])
							)
						);
						if (!Event::instance()->fire(
							'admin/System/components/modules/db/prepare',
							[
								'name' => $rc[3]
							]
						)
						) {
							break;
						}
						$a->buttons            = true;
						$a->apply_button       = false;
						$a->cancel_button_back = true;
						module_db_settings:
						if (file_exists(MODULES."/$rc[3]/meta/db.json")) {
							$Core = Core::instance();
							$dbs  = [0 => "$L->core_db ($Core->db_type)"];
							foreach ($Config->db as $i => &$db_data) {
								if ($i) {
									$dbs[$i] = "$db_data[name] ($db_data[host] / $db_data[type])";
								}
							}
							unset($i, $db_data);
							$db_list = [];
							$db_json = file_get_json(MODULES."/$rc[3]/meta/db.json");
							foreach ($db_json as $database) {
								$db_list[] = [
									$database,
									h::select(
										[
											'in'    => array_values($dbs),
											'value' => array_keys($dbs)
										],
										[
											'name'     => "db[$database]",
											'selected' => isset($Config->components['modules'][$rc[3]]['db'][$database]) ?
												$Config->components['modules'][$rc[3]]['db'][$database] : 0,
											'size'     => 5
										]
									)
								];
							}
							unset($db_json, $dbs, $database);
							$a->content(
								h::{'cs-table[right-left][with-header] cs-table-row| cs-table-cell'}(
									[
										h::info('appointment_of_db'),
										h::info('system_db')
									],
									$db_list
								)
							);
							unset($db_list);
						}
						if ($rc[2] == 'install') {
							goto back_to_module_installation_1;
						}
					}
					break;
				case 'storage':
					$show_modules = false;
					if (count($Config->storage) > 1) {
						$Page->warning($L->changing_settings_warning);
						$Page->title($L->storage_settings_for_module($rc[3]));
						$a->content(
							h::{'h2.cs-center'}(
								$L->storage_settings_for_module($rc[3])
							)
						);
						if (!Event::instance()->fire(
							'admin/System/components/modules/storage/prepare',
							[
								'name' => $rc[3]
							]
						)
						) {
							break;
						}
						$a->buttons            = true;
						$a->apply_button       = false;
						$a->cancel_button_back = true;
						module_storage_settings:
						if (file_exists(MODULES."/$rc[3]/meta/storage.json")) {
							$storages = [0 => $L->core_storage];
							foreach ($Config->storage as $i => &$storage_data) {
								if ($i) {
									$storages[$i] = "$storage_data[host] ($storage_data[connection])";
								}
							}
							unset($i, $storage_data);
							$storage_list = [];
							$storage_json = file_get_json(MODULES."/$rc[3]/meta/storage.json");
							foreach ($storage_json as $storage) {
								$storage_list[] = [
									$storage,
									h::select(
										[
											'in'    => array_values($storages),
											'value' => array_keys($storages)
										],
										[
											'name'     => "storage[$storage]",
											'selected' => isset($Config->components['modules'][$rc[3]]['storage'][$storage]) ?
												$Config->components['modules'][$rc[3]]['storage'][$storage] : 0,
											'size'     => 5
										]
									)
								];
							}
							unset($storage_json, $storages, $storage);
							$a->content(
								h::{'cs-table[right-left][with-header] cs-table-row| cs-table-cell'}(
									[
										h::info('appointment_of_storage'),
										h::info('system_storage')
									],
									$storage_list
								)
							);
							unset($storage_list);
						}
						if ($rc[2] == 'install') {
							goto back_to_module_installation_2;
						}
					}
					break;
				case 'enable':
					$show_modules       = false;
					$check_dependencies = check_dependencies($rc[3], 'module', null, 'enable');
					if (!$check_dependencies && $Config->core['simple_admin_mode']) {
						break;
					}
					Event::instance()->fire(
						'admin/System/components/modules/enable/prepare',
						[
							'name' => $rc[3]
						]
					);
					$Page->title($L->enabling_of_module($rc[3]));
					$a->content(
						h::{'h2.cs-center'}(
							$L->enable_module($rc[3])
						)
					);
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}($L->{$check_dependencies ? 'yes' : 'force_enable_not_recommended'})
					);
					break;
				case 'disable':
					$show_modules       = false;
					$check_dependencies = check_backward_dependencies($rc[3], 'module', 'disable');
					if (!$check_dependencies && $Config->core['simple_admin_mode']) {
						break;
					}
					Event::instance()->fire(
						'admin/System/components/modules/disable/prepare',
						[
							'name' => $rc[3]
						]
					);
					$Page->title($L->disabling_of_module($rc[3]));
					$a->content(
						h::{'h2.cs-center'}(
							$L->disable_module($rc[3])
						)
					);
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}($L->{$check_dependencies ? 'yes' : 'force_disable_not_recommended'})
					);
					break;
				case 'remove':
					$show_modules = false;
					$Page->title($L->complete_removal_of_module($_POST['remove_module']));
					$a->content(
						h::{'h2.cs-center'}(
							$L->completely_remove_module($_POST['remove_module'])
						)
					);
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}($L->yes)
					);
					$rc[3] = $_POST['remove_module'];
					break;
			}
			switch ($rc[2]) {
				case 'install':
				case 'uninstall':
				case 'update':
				case 'update_system':
				case 'default_module':
				case 'db':
				case 'storage':
				case 'enable':
				case 'disable':
				case 'remove':
					$a->content(
						h::{'input[type=hidden]'}(
							[
								'name'  => 'mode',
								'value' => $rc[2]
							]
						).
						h::{'input[type=hidden]'}(
							[
								'name'  => 'module',
								'value' => $rc[3]
							]
						)
					);
			}
		}
		unset($rc);
		if (!$show_modules) {
			return;
		}
		$a->file_upload = true;
		$modules_list   = [];
		foreach ($Config->components['modules'] as $module_name => &$module_data) {
			/**
			 * If module if enabled or disabled
			 */
			$addition_state = $action = '';
			$admin_link     = false;
			/**
			 * Notice about API existence
			 */
			if (is_dir(MODULES."/$module_name/api")) {
				if (
					file_exists($file = MODULES."/$module_name/api/readme.txt") ||
					file_exists($file = MODULES."/$module_name/api/readme.html")
				) {
					$tag    = substr($file, -3) == 'txt' ? 'pre' : 'div';
					$uniqid = uniqid('module_info_');
					$modal  = "#{$module_name}_api.uk-modal .uk-modal-dialog.uk-modal-dialog-large";
					$Page->post_Body .= h::$modal(
						h::{'.uk-modal-caption'}("$module_name » $L->api").
						h::$tag($uniqid)
					);
					$Page->replace(
						$uniqid,
						$tag == 'pre' ? prepare_attr_value(file_get_contents($file)) : file_get_contents($file)
					);
				}
				$addition_state .= h::icon(
					'link',
					[
						'data-title'    => $L->api_exists.h::br().(file_exists($file) ? $L->click_to_view_details : ''),
						'data-uk-modal' => "{target : '#{$module_name}_api'}",
						'class'         => file_exists($file) ? 'uk-button cs-button-compact' : false
					]
				);
				unset($file, $tag, $uniqid, $modal);
			}
			/**
			 * Information about module
			 */
			if (
				file_exists($file = MODULES."/$module_name/readme.txt") ||
				file_exists($file = MODULES."/$module_name/readme.html")
			) {
				$tag    = substr($file, -3) == 'txt' ? 'pre' : 'div';
				$uniqid = uniqid('module_info_');
				$modal  = "#{$module_name}_readme.uk-modal .uk-modal-dialog.uk-modal-dialog-large";
				$Page->post_Body .= h::$modal(
					h::{'.uk-modal-caption'}("$module_name » $L->information_about_module").
					h::$tag($uniqid)
				);
				$Page->replace(
					$uniqid,
					$tag == 'pre' ? prepare_attr_value(file_get_contents($file)) : file_get_contents($file)
				);
				$addition_state .= h::{'icon.uk-button.cs-button-compact'}(
					'exclamation',
					[
						'data-title'    => $L->information_about_module.h::br().$L->click_to_view_details,
						'data-uk-modal' => "{target : '#{$module_name}_readme'}"
					]
				);
			}
			unset($file, $tag, $uniqid, $modal);
			/**
			 * License
			 */
			if (
				file_exists($file = MODULES."/$module_name/license.txt") ||
				file_exists($file = MODULES."/$module_name/license.html")
			) {
				$tag    = substr($file, -3) == 'txt' ? 'pre' : 'div';
				$uniqid = uniqid('module_info_');
				$modal  = "#{$module_name}_license.uk-modal .uk-modal-dialog.uk-modal-dialog-large";
				$Page->post_Body .= h::$modal(
					h::{'.uk-modal-caption'}("$module_name » $L->license").
					h::$tag($uniqid)
				);
				$Page->replace(
					$uniqid,
					$tag == 'pre' ? prepare_attr_value(file_get_contents($file)) : file_get_contents($file)
				);
				$addition_state .= h::{'icon.uk-button.cs-button-compact'}(
					'legal',
					[
						'data-title'    => $L->license.h::br().$L->click_to_view_details,
						'data-uk-modal' => "{target : '#{$module_name}_license'}"
					]
				);
			}
			unset($file, $tag, $uniqid, $modal);
			if ($module_data['active'] != -1) {
				/**
				 * Setting default module
				 */
				if (
					$module_data['active'] == 1 &&
					$module_name != $Config->core['default_module'] &&
					(
						file_exists(MODULES."/$module_name/index.php") ||
						file_exists(MODULES."/$module_name/index.html") ||
						file_exists(MODULES."/$module_name/index.json")
					)
				) {
					$action .= h::{'a.uk-button.cs-button-compact'}(
						h::icon('home'),
						[
							'href'       => "$a->action/default_module/$module_name",
							'data-title' => $L->make_default_module
						]
					);
				}
				/**
				 * DataBases settings
				 */
				if (!$Config->core['simple_admin_mode'] && file_exists(MODULES."/$module_name/meta/db.json") && count($Config->db) > 1) {
					$action .= h::{'a.uk-button.cs-button-compact'}(
						h::icon('database'),
						[
							'href'       => "$a->action/db/$module_name",
							'data-title' => $L->databases
						]
					);
				}
				/**
				 * Storages settings
				 */
				if (!$Config->core['simple_admin_mode'] && file_exists(MODULES."/$module_name/meta/storage.json") && count($Config->storage) > 1) {
					$action .= h::{'a.uk-button.cs-button-compact'}(
						h::icon('hdd-o'),
						[
							'href'       => "$a->action/storage/$module_name",
							'data-title' => $L->storages
						]
					);
				}
				if ($module_name != 'System') {
					/**
					 * Link to the module admin page
					 */
					if (file_exists(MODULES."/$module_name/admin/index.php") || file_exists(MODULES."/$module_name/admin/index.json")) {
						$action .= h::{'a.uk-button.cs-button-compact'}(
							h::icon('sliders'),
							[
								'href'       => "admin/$module_name",
								'data-title' => $L->module_admin_page
							]
						);
						$admin_link = true;
					}
					if ($module_name != $Config->core['default_module']) {
						$action .= h::{'a.uk-button.cs-button-compact'}(
								$module_data['active'] == 1 ? h::icon('minus') : h::icon('check')." $L->enable",
								[
									'href'       => $a->action.($module_data['active'] == 1 ? '/disable/' : '/enable/').$module_name,
									'data-title' => $module_data['active'] == 1 ? $L->disable : false
								]
							).
								   h::{'a.uk-button.cs-button-compact'}(
									   h::icon('trash-o'),
									   [
										   'href'       => "$a->action/uninstall/$module_name",
										   'data-title' => $L->uninstall
									   ]
								   );
					}
				}
				/**
				 * If module uninstalled or not installed yet
				 */
			} else {
				$action .= h::{'a.uk-button.cs-button-compact'}(
					h::icon('download')." $L->install",
					[
						'href' => "$a->action/install/$module_name"
					]
				);
			}
			$module_info = false;
			if (file_exists(MODULES."/$module_name/meta.json")) {
				$module_meta = file_get_json(MODULES."/$module_name/meta.json");
				$module_info = $L->module_info(
					$module_meta['package'],
					$module_meta['version'],
					$module_meta['description'],
					$module_meta['author'],
					isset($module_meta['website']) ? $module_meta['website'] : $L->none,
					$module_meta['license'],
					isset($module_meta['db_support']) ? implode(', ', $module_meta['db_support']) : $L->none,
					isset($module_meta['storage_support']) ? implode(', ', $module_meta['storage_support']) : $L->none,
					isset($module_meta['provide']) ? implode(', ', (array)$module_meta['provide']) : $L->none,
					isset($module_meta['require']) ? implode(', ', (array)$module_meta['require']) : $L->none,
					isset($module_meta['conflict']) ? implode(', ', (array)$module_meta['conflict']) : $L->none,
					isset($module_meta['optional']) ? implode(', ', (array)$module_meta['optional']) : $L->none,
					isset($module_meta['multilingual']) && in_array('interface', $module_meta['multilingual']) ? $L->yes : $L->no,
					isset($module_meta['multilingual']) && in_array('content', $module_meta['multilingual']) ? $L->yes : $L->no,
					isset($module_meta['languages']) ? implode(', ', $module_meta['languages']) : $L->none
				);
			}
			unset($module_meta);
			$modules_list[] = [
				[
					h::a(
						$L->$module_name,
						[
							'href'       => $admin_link ? "admin/$module_name" : false,
							'data-title' => $module_info
						]
					),
					h::icon(
						$module_data['active'] == 1 ? (
						$module_name == $Config->core['default_module'] ? 'home' : 'check'
						) : (
						$module_data['active'] == 0 ? 'minus' : 'times'
						),
						[
							'data-title' => $module_data['active'] == 1 ? (
							$module_name == $Config->core['default_module'] ? $L->default_module : $L->enabled
							) : (
							$module_data['active'] == 0 ? $L->disabled : "$L->uninstalled ($L->not_installed)"
							)
						]
					).
					$addition_state,
					[
						$action,
						[
							'left' => ''
						]
					]
				],
				[
					'class' => $module_data['active'] == 1 ? 'uk-alert-success' : ($module_data['active'] == -1 ? 'uk-alert-danger' : 'uk-alert-warning')
				]
			];
			unset($module_info);
		}
		$modules_for_removal = array_keys(
			array_filter(
				$Config->components['modules'],
				function ($module_data) {
					return $module_data['active'] == '-1';
				}
			)
		);
		$a->content(
			h::{'cs-table[list][center][with-header]'}(
				h::{'cs-table-row cs-table-cell'}(
					$L->module_name,
					$L->state,
					$L->action
				).
				h::{'cs-table-row| cs-table-cell'}($modules_list)
			).
			h::p(
				h::{'input[type=file][name=upload_module]'}().
				h::{'button.uk-button[type=submit]'}(
					h::icon('upload').$L->upload_and_install_update_module,
					[
						'formaction' => "$a->action/install/upload"
					]
				)
			).
			h::p(
				h::{'input[type=file][name=upload_system]'}().
				h::{'button.uk-button[type=submit]'}(
					h::icon('upload').$L->upload_and_update_system,
					[
						'formaction' => "$a->action/update_system"
					]
				)
			).
			(
			$modules_for_removal
				? h::p(
				h::{'select[name=remove_module]'}($modules_for_removal).
				h::{'button.uk-button[type=submit]'}(
					h::icon('trash-o').$L->complete_module_removal,
					[
						'formaction' => "$a->action/remove"
					]
				)
			)
				: ''
			).
			h::{'button.uk-button[type=submit]'}(
				h::icon('refresh').$L->update_modules_list,
				[
					'data-title' => $L->update_modules_list_info,
					'name'       => 'update_modules_list'
				]
			)
		);
	}
	/**
	 * Provides next events:
	 *  admin/System/components/plugins/update/prepare
	 *  ['name' => plugin_name]
	 *
	 *  admin/System/components/plugins/enable/prepare
	 *  ['name' => plugin_name]
	 *
	 *  admin/System/components/plugins/disable/prepare
	 *  ['name' => plugin_name]
	 *
	 */
	static function components_plugins () {
		$Config     = Config::instance();
		$L          = Language::instance();
		$Page       = Page::instance();
		$a          = Index::instance();
		$rc         = $Config->route;
		$plugins    = get_files_list(PLUGINS, false, 'd');
		$a->buttons = false;
		if (
			isset($rc[2]) &&
			!empty($rc[2]) &&
			(
				(
					isset($rc[3]) &&
					!empty($rc[3])
				) ||
				$rc[2] == 'remove'
			)
		) {
			switch ($rc[2]) {
				case 'enable':
					if ($rc[3] == 'upload' && isset($_FILES['upload_plugin']) && $_FILES['upload_plugin']['tmp_name']) {
						switch ($_FILES['upload_plugin']['error']) {
							case UPLOAD_ERR_INI_SIZE:
							case UPLOAD_ERR_FORM_SIZE:
								$Page->warning($L->file_too_large);
								break;
							case UPLOAD_ERR_NO_TMP_DIR:
								$Page->warning($L->temporary_folder_is_missing);
								break;
							case UPLOAD_ERR_CANT_WRITE:
								$Page->warning($L->cant_write_file_to_disk);
								break;
							case UPLOAD_ERR_PARTIAL:
							case UPLOAD_ERR_NO_FILE:
								break;
						}
						if ($_FILES['upload_plugin']['error'] != UPLOAD_ERR_OK) {
							break;
						}
						$tmp_file = TEMP.'/'.md5($_FILES['upload_plugin']['tmp_name'].openssl_random_pseudo_bytes(1000)).'.phar';
						move_uploaded_file($_FILES['upload_plugin']['tmp_name'], $tmp_file);
						$tmp_dir = "phar://$tmp_file";
						$plugin  = file_get_contents("$tmp_dir/dir");
						if (!$plugin) {
							unlink($tmp_file);
							break;
						}
						$rc[3] = $plugin;
						/** @noinspection NotOptimalIfConditionsInspection */
						if (!file_exists("$tmp_dir/meta.json") || file_get_json("$tmp_dir/meta.json")['category'] != 'plugins') {
							$Page->warning($L->this_is_not_plugin_installer_file);
							unlink($tmp_file);
							break;
						}
						if (in_array($plugin, $plugins)) {
							$current_version = file_get_json(PLUGINS."/$plugin/meta.json")['version'];
							$new_version     = file_get_json("$tmp_dir/meta.json")['version'];
							if (!version_compare($current_version, $new_version, '<')) {
								$Page->warning($L->update_plugin_impossible_older_version($plugin));
								unlink($tmp_file);
								break;
							}
							if (!Event::instance()->fire(
								'admin/System/components/plugins/update/prepare',
								[
									'name' => $plugin
								]
							)
							) {
								break;
							}
							$check_dependencies = check_dependencies($plugin, 'plugin', $tmp_dir);
							if (!$check_dependencies && $Config->core['simple_admin_mode']) {
								break;
							}
							$rc[2] = 'update';
							$Page->title($L->updating_of_plugin($plugin));
							rename($tmp_file, $tmp_file = TEMP.'/'.User::instance()->get_session_id().'_plugin_update.phar.php');
							$a->content(
								h::{'h2.cs-center'}(
									$L->update_plugin(
										$plugin,
										$current_version,
										$new_version
									)
								).
								h::{'input[type=hidden]'}(
									[
										'name'  => 'mode',
										'value' => $rc[2]
									]
								).
								h::{'input[type=hidden]'}(
									[
										'name'  => 'plugin',
										'value' => $rc[3]
									]
								)
							);
							$a->cancel_button_back = true;
							$a->content(
								h::{'button.uk-button[type=submit]'}($L->{$check_dependencies ? $L->yes : 'force_update_not_recommended'})
							);
							return;
						}
						if (!file_exists(PLUGINS."/$plugin") && !mkdir(PLUGINS."/$plugin", 0770)) {
							$Page->warning($L->cant_unpack_plugin_no_write_permissions);
							unlink($tmp_file);
							break;
						}
						$fs      = file_get_json("$tmp_dir/fs.json");
						$extract = array_product(
							array_map(
								function ($index, $file) use ($tmp_dir, $plugin) {
									if (
										!file_exists(dirname(PLUGINS."/$plugin/$file")) &&
										!mkdir(dirname(PLUGINS."/$plugin/$file"), 0770, true)
									) {
										return 0;
									}
									return (int)copy("$tmp_dir/fs/$index", PLUGINS."/$plugin/$file");
								},
								$fs,
								array_keys($fs)
							)
						);
						file_put_json(PLUGINS."/$plugin/fs.json", array_keys($fs));
						unset($tmp_dir);
						if (!$extract) {
							$Page->warning($L->plugin_files_unpacking_error);
							break;
						}
						unlink($tmp_file);
						$plugins[] = $plugin;
						unset($tmp_file, $plugin);
					}
					/** @noinspection NotOptimalIfConditionsInspection */
					if (!in_array($rc[3], $Config->components['plugins']) && in_array($rc[3], $plugins)) {
						$Page->title($L->enabling_of_plugin($rc[3]));
						$a->content(
							h::{'h2.cs-center'}(
								$L->enabling_of_plugin($rc[3])
							)
						);
						if (!Event::instance()->fire(
							'admin/System/components/plugins/enable/prepare',
							[
								'name' => $rc[3]
							]
						)
						) {
							break;
						}
						$check_dependencies = check_dependencies($rc[3], 'plugin');
						if (!$check_dependencies && $Config->core['simple_admin_mode']) {
							break;
						}
						if (file_exists(PLUGINS."/$rc[3]/meta.json")) {
							$meta = file_get_json(PLUGINS."/$rc[3]/meta.json");
							if (isset($meta['optional'])) {
								$Page->success(
									$L->for_complete_feature_set(
										implode(', ', (array)$meta['optional'])
									)
								);
							}
							unset($meta);
						}
						$a->cancel_button_back = true;
						$a->content(
							h::{'button.uk-button[type=submit]'}(
								$L->{$check_dependencies ? 'enable' : 'force_enable_not_recommended'}
							).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'mode',
									'value' => $rc[2]
								]
							).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'plugin',
									'value' => $rc[3]
								]
							)
						);
						return;
					}
					break;
				case 'disable':
					if (in_array($rc[3], $Config->components['plugins'])) {
						$Page->title($L->disabling_of_plugin($rc[3]));
						$a->content(
							h::{'h2.cs-center'}(
								$L->disabling_of_plugin($rc[3])
							)
						);
						if (!Event::instance()->fire(
							'admin/System/components/plugins/disable/prepare',
							[
								'name' => $rc[3]
							]
						)
						) {
							break;
						}
						$check_dependencies = check_backward_dependencies($rc[3], 'plugin');
						if (!$check_dependencies && $Config->core['simple_admin_mode']) {
							break;
						}
						$a->cancel_button_back = true;
						$a->content(
							h::{'button.uk-button[type=submit]'}(
								$L->{$check_dependencies ? 'disable' : 'force_disable_not_recommended'}
							).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'mode',
									'value' => $rc[2]
								]
							).
							h::{'input[type=hidden]'}(
								[
									'name'  => 'plugin',
									'value' => $rc[3]
								]
							)
						);
					}
					return;
					break;
				case 'remove':
					$Page->title($L->complete_removal_of_plugin($_POST['remove_plugin']));
					$a->content(
						h::{'h2.cs-center'}(
							$L->completely_remove_plugin($_POST['remove_plugin'])
						)
					);
					$a->cancel_button_back = true;
					$a->content(
						h::{'button.uk-button[type=submit]'}($L->yes).
						h::{'input[type=hidden]'}(
							[
								'name'  => 'mode',
								'value' => $rc[2]
							]
						).
						h::{'input[type=hidden]'}(
							[
								'name'  => 'plugin',
								'value' => $_POST['remove_plugin']
							]
						)
					);
					return;
					break;
			}
		}
		unset($rc);
		$a->buttons     = false;
		$a->file_upload = true;
		$plugins_list   = [];
		if (!empty($plugins)) {
			foreach ($plugins as $plugin) {
				$addition_state = $action = '';
				/**
				 * Information about plugin
				 */
				if (
					file_exists($file = PLUGINS."/$plugin/readme.txt") ||
					file_exists($file = PLUGINS."/$plugin/readme.html")
				) {
					$tag    = substr($file, -3) == 'txt' ? 'pre' : 'div';
					$uniqid = uniqid('plugin_info_');
					$modal  = "#{$plugin}_readme.uk-modal .uk-modal-dialog.uk-modal-dialog-large";
					$Page->post_Body .= h::$modal(
						h::{'.uk-modal-caption'}("$plugin » $L->information_about_plugin").
						h::$tag($uniqid)
					);
					$Page->replace(
						$uniqid,
						$tag == 'pre' ? prepare_attr_value(file_get_contents($file)) : file_get_contents($file)
					);
					$addition_state .= h::{'icon.uk-button.cs-button-compact'}(
						'exclamation',
						[
							'data-title'    => $L->information_about_plugin.h::br().$L->click_to_view_details,
							'data-uk-modal' => "{target : '#{$plugin}_readme'}"
						]
					);
					unset($uniqid);
				}
				unset($tag, $file);
				/**
				 * License
				 */
				if (
					file_exists($file = PLUGINS."/$plugin/license.txt") ||
					file_exists($file = PLUGINS."/$plugin/license.html")
				) {
					$tag    = substr($file, -3) == 'txt' ? 'pre' : 'div';
					$uniqid = uniqid('plugin_info_');
					$modal  = "#{$plugin}_license.uk-modal .uk-modal-dialog.uk-modal-dialog-large";
					$Page->post_Body .= h::$modal(
						h::{'.uk-modal-caption'}("$plugin » $L->license").
						h::$tag($uniqid)
					);
					$Page->replace(
						$uniqid,
						$tag == 'pre' ? prepare_attr_value(file_get_contents($file)) : file_get_contents($file)
					);
					$addition_state .= h::{'icon.uk-button.cs-button-compact'}(
						'legal',
						[
							'data-title'    => $L->license.h::br().$L->click_to_view_details,
							'data-uk-modal' => "{target : '#{$plugin}_license'}"
						]
					);
				}
				unset($tag, $file);
				$state = in_array($plugin, $Config->components['plugins']);
				$action .= h::{'a.uk-button.cs-button-compact'}(
					h::icon($state ? 'minus' : 'check'),
					[
						'href'       => $a->action.($state ? '/disable/' : '/enable/').$plugin,
						'data-title' => $state ? $L->disable : $L->enable
					]
				);
				$plugin_info = false;
				if (file_exists(PLUGINS."/$plugin/meta.json")) {
					$plugin_meta = file_get_json(PLUGINS."/$plugin/meta.json");
					$plugin_info = $L->plugin_info(
						$plugin_meta['package'],
						$plugin_meta['version'],
						$plugin_meta['description'],
						$plugin_meta['author'],
						isset($plugin_meta['website']) ? $plugin_meta['website'] : $L->none,
						$plugin_meta['license'],
						isset($plugin_meta['provide']) ? implode(', ', (array)$plugin_meta['provide']) : $L->none,
						isset($plugin_meta['require']) ? implode(', ', (array)$plugin_meta['require']) : $L->none,
						isset($plugin_meta['conflict']) ? implode(', ', (array)$plugin_meta['conflict']) : $L->none,
						isset($plugin_meta['optional']) ? implode(', ', (array)$plugin_meta['optional']) : $L->none,
						isset($plugin_meta['multilingual']) && in_array('interface', $plugin_meta['multilingual']) ? $L->yes : $L->no,
						isset($plugin_meta['multilingual']) && in_array('content', $plugin_meta['multilingual']) ? $L->yes : $L->no,
						isset($plugin_meta['languages']) ? implode(', ', $plugin_meta['languages']) : $L->none
					);
				}
				unset($plugin_meta);
				$plugins_list[] = [
					[
						h::span(
							$L->$plugin,
							[
								'data-title' => $plugin_info
							]
						),
						h::icon(
							$state ? 'check' : 'minus',
							[
								'data-title' => $state ? $L->enabled : $L->disabled
							]
						).
						$addition_state,
						$action
					],
					[
						'class' => $state ? 'uk-alert-success' : 'uk-alert-warning'
					]
				];
				unset($plugin_info);
			}
			unset($plugin, $state, $addition_state, $action);
		}
		$plugins_for_removal = array_values(
			array_filter(
				$plugins,
				function ($plugin) use ($Config) {
					return !in_array($plugin, $Config->components['plugins']);
				}
			)
		);
		$a->content(
			h::{'cs-table[center][list][with-header]'}(
				h::{'cs-table-row cs-table-cell'}(
					$L->plugin_name,
					$L->state,
					$L->action
				).
				h::{'cs-table-row| cs-table-cell'}($plugins_list ?: false)
			).
			h::p(
				h::{'input[type=file][name=upload_plugin]'}(
					[
						'style' => 'position: relative;'
					]
				).
				h::{'button.uk-button[type=submit]'}(
					h::icon('upload').$L->upload_and_install_update_plugin,
					[
						'formaction' => "$a->action/enable/upload"
					]
				)
			).
			(
			$plugins_for_removal
				? h::p(
				h::{'select[name=remove_plugin]'}($plugins_for_removal).
				h::{'button.uk-button[type=submit]'}(
					h::icon('trash-o').$L->complete_plugin_removal,
					[
						'formaction' => "$a->action/remove"
					]
				)
			)
				: ''
			)
		);
	}
	static function components_storages () {
		$Config      = Config::instance();
		$L           = Language::instance();
		$Page        = Page::instance();
		$a           = Index::instance();
		$rc          = $Config->route;
		$test_dialog = true;
		if (isset($rc[2])) {
			$a->apply_button       = false;
			$a->cancel_button_back = true;
			switch ($rc[2]) {
				case 'add':
				case 'edit':
					if ($rc[2] == 'edit' && isset($rc[3])) {
						$storage = &$Config->storage[$rc[3]];
					}
					/**
					 * @var array $storage
					 */
					$a->action = "admin/System/$rc[0]/$rc[1]";
					$Page->title(
						$rc[2] == 'edit' ? $L->editing_of_storage($Config->storage[$rc[3]]['host'].'/'.$Config->storage[$rc[3]]['connection']) :
							$L->adding_of_storage
					);
					$a->content(
						h::{'h2.cs-center'}(
							$rc[2] == 'edit' ? $L->editing_of_storage($Config->storage[$rc[3]]['host'].'/'.$Config->storage[$rc[3]]['connection']) :
								$L->adding_of_storage
						).
						h::{'cs-table[center][right-left] cs-table-row| cs-table-cell'}(
							[
								h::info('storage_url'),
								h::input(
									[
										'name'  => 'storage[url]',
										'value' => $rc[2] == 'edit' ? $storage['url'] : ''
									]
								)
							],
							[
								h::info('storage_host'),
								h::input(
									[
										'name'  => 'storage[host]',
										'value' => $rc[2] == 'edit' ? $storage['host'] : ''
									]
								)
							],
							[
								h::info('storage_connection'),
								h::select(
									[
										'in' => _mb_substr(get_files_list(ENGINES.'/Storage', '/^[^_].*?\.php$/i', 'f'), 0, -4)
									],
									[
										'name'     => 'storage[connection]',
										'selected' => $rc[2] == 'edit' ? $storage['connection'] : '',
										'size'     => 5
									]
								)
							],
							[
								h::info('storage_user'),
								h::input(
									[
										'name'  => 'storage[user]',
										'value' => $rc[2] == 'edit' ? $storage['user'] : ''
									]
								)
							],
							[
								h::info('storage_pass'),
								h::input(
									[
										'name'  => 'storage[password]',
										'value' => $rc[2] == 'edit' ? $storage['password'] : ''
									]
								)
							]
						).
						h::{'input[type=hidden]'}(
							[
								'name'  => 'mode',
								'value' => $rc[2] == 'edit' ? 'edit' : 'add'
							]
						).
						(
						isset($rc[3])
							? h::{'input[type=hidden]'}(
							[
								'name'  => 'storage_id',
								'value' => $rc[3]
							]
						)
							: ''
						).
						h::{'button.uk-button'}(
							$L->test_connection,
							[
								'onMouseDown' => "cs.storage_test();"
							]
						)
					);
					break;
				case 'delete':
					$a->buttons = false;
					$modules    = [];
					foreach ($Config->components['modules'] as $module => &$mdata) {
						if (isset($mdata['storage']) && is_array($mdata['storage'])) {
							foreach ($mdata['storage'] as $storage_name) {
								if ($storage_name == $rc[3]) {
									$modules[] = h::b($module);
									break;
								}
							}
						}
					}
					unset($module, $mdata, $storage_name);
					if (!empty($modules)) {
						$Page->warning($L->storage_used_by_modules.': '.implode(', ', $modules));
					} else {
						$a->action = "admin/System/$rc[0]/$rc[1]";
						$Page->title($L->deletion_of_storage($Config->storage[$rc[3]]['host'].'/'.$Config->storage[$rc[3]]['connection']));
						$a->content(
							h::{'h2.cs-center'}(
								$L->sure_to_delete.' '.$L->storage.' '.
								$Config->storage[$rc[3]]['host'].'/'.$Config->storage[$rc[3]]['connection'].'?'.
								h::{'input[type=hidden]'}(
									[
										'name'  => 'mode',
										'value' => 'delete'
									]
								).
								h::{'input[type=hidden]'}(
									[
										'name'  => 'storage',
										'value' => $rc[3]
									]
								)
							).
							h::{'button.uk-button[type=submit]'}($L->yes)
						);
					}
			}
		} else {
			$storages_list = [];
			$Core          = Core::instance();
			$storages      = $Config->storage;
			if (!empty($storages)) {
				foreach ($storages as $i => &$storage_data) {
					$storages_list[] = [
						[
							($i ?
								h::{'a.uk-button.cs-button-compact'}(
									h::icon('pencil'),
									[
										'href'       => "$a->action/edit/$i",
										'data-title' => "$L->edit $L->storage"
									]
								).
								h::{'a.uk-button.cs-button-compact'}(
									h::icon('trash-o'),
									[
										'href'       => "$a->action/delete/$i",
										'data-title' => "$L->delete $L->storage"
									]
								).
								h::{'a.uk-button.cs-button-compact'}(
									h::icon('signal'),
									[
										'onMouseDown' => "cs.storage_test($i);",
										'data-title'  => $L->test_connection
									]
								) : '-'),
							[
								'class' => $i ? '' : 'text-primary'
							]
						],
						[
							[
								$i ? $storage_data['url'] : $Core->storage_url ?: url_by_source(PUBLIC_STORAGE),
								$i ? $storage_data['host'] : $Core->storage_host,
								$i ? $storage_data['connection'] : $Core->storage_type,
								$i ? $storage_data['user'] : $Core->storage_user ?: '-'
							],
							[
								'class' => $i ? '' : 'text-primary'
							]
						]
					];
				}
				unset($i, $storage_data);
			}
			unset($storages);
			$a->content(
				h::{'cs-table[center][list][with-header]'}(
					h::{'cs-table-row cs-table-cell'}(
						$L->action,
						$L->storage_url,
						$L->storage_host,
						$L->storage_connection,
						$L->storage_user
					).
					h::{'cs-table-row| cs-table-cell'}($storages_list ? [$storages_list] : false)
				).
				h::{'p a.uk-button'}(
					$L->add_storage,
					[
						'href' => "admin/System/$rc[0]/$rc[1]/add"
					]
				)
			);
			unset($storages_list);
		}
		$test_dialog && $a->content(
			h::{'div#cs-storage-test.uk-modal div'}(
				h::h3($L->test_connection).
				h::div()
			)
		);
	}
}