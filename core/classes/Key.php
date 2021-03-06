<?php
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2011-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs;

/**
 * @method static $this instance($check = false)
 */
class Key {
	use
		Singleton;
	/**
	 * Generates guaranteed unique key
	 *
	 * @param int|\cs\DB\_Abstract $database Keys database
	 *
	 * @return string
	 *
	 * @throws ExitException
	 */
	public function generate ($database) {
		if (!is_object($database)) {
			$database = DB::instance()->db_prime($database);
		}
		while (true) {
			$key  = hash('sha224', random_bytes(1000));
			$time = time();
			if (!$database->qfs(
				"SELECT `id`
				FROM `[prefix]keys`
				WHERE
					`key`		= '$key' AND
					`expire`	>= $time
				LIMIT 1"
			)
			) {
				return $key;
			}
		}
	}
	/**
	 * Adding key into specified database
	 *
	 * @param int|\cs\DB\_Abstract $database Keys database
	 * @param bool|string          $key      If <b>false</b> - key will be generated automatically, otherwise must contain 56 character [0-9a-z] key
	 * @param null|mixed           $data     Data to be stored with key
	 * @param int                  $expire   Timestamp of key expiration, if not specified - default system value will be used
	 *
	 * @return false|string
	 *
	 * @throws ExitException
	 */
	public function add ($database, $key, $data = null, $expire = 0) {
		if (!is_object($database)) {
			$database = DB::instance()->db_prime($database);
		}
		if ($key === false) {
			$key = $this->generate($database);
		} elseif (!preg_match('/^[a-z0-9]{56}$/', $key)) {
			return false;
		}
		$expire = (int)$expire;
		$Config = Config::instance();
		$time   = time();
		if ($expire == 0 || $expire < $time) {
			$expire = $time + $Config->core['key_expire'];
		}
		$this->del($database, $key);
		$database->q(
			"INSERT INTO `[prefix]keys`
				(
					`key`,
					`expire`,
					`data`
				) VALUES (
					'%s',
					'%s',
					'%s'
				)",
			$key,
			$expire,
			_json_encode($data)
		);
		$id = $database->id();
		/**
		 * Cleaning old keys
		 */
		if ($id && ($id % $Config->core['inserts_limit']) == 0) {
			$database->q(
				"DELETE FROM `[prefix]keys`
				WHERE `expire` < $time"
			);
		}
		return $key;
	}
	/**
	 * Check key existence and/or getting of data stored with key. After this key will be deleted automatically.
	 *
	 * @param int|\cs\DB\_Abstract $database Keys database
	 * @param string               $key      56 character [0-9a-z] key
	 * @param bool                 $get_data If <b>true</d> - stored data will be returned on success, otherwise boolean result of key existence will be
	 *                                       returned
	 *
	 * @return bool|mixed
	 *
	 * @throws ExitException
	 */
	public function get ($database, $key, $get_data = false) {
		if (!preg_match('/^[a-z0-9]{56}$/', $key)) {
			return false;
		}
		if (!is_object($database)) {
			$database = DB::instance()->db_prime($database);
		}
		$time   = time();
		$result = $database->qf(
			"SELECT
				`id`,
				`data`
			FROM `[prefix]keys`
			WHERE
				(
					`key`	= '$key'
				) AND
				`expire` >= $time
			ORDER BY `id` DESC
			LIMIT 1"
		);
		$this->del($database, $key);
		if (!$result || !is_array($result)) {
			return false;
		} elseif ($get_data) {
			return _json_decode($result['data']);
		} else {
			return true;
		}
	}
	/**
	 * Key deletion from database
	 *
	 * @param int|\cs\DB\_Abstract $database Keys database
	 * @param string               $key      56 character [0-9a-z] key
	 *
	 * @return bool
	 *
	 * @throws ExitException
	 */
	public function del ($database, $key) {
		if (!preg_match('/^[a-z0-9]{56}$/', $key)) {
			return false;
		}
		if (!is_object($database)) {
			$database = DB::instance()->db_prime($database);
		}
		return (bool)$database->q(
			"DELETE FROM `[prefix]keys`
			WHERE `key`	= '$key'"
		);
	}
}
