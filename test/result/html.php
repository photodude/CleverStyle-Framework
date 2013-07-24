<!doctype html>
<?php
/**
 * @package		CleverStyle CMS
 * @subpackage	Tester
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2013, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
$title			= _json_decode(file_get_contents(TEST.'/test.json'))['title'];
$tests_total	= $tests_success + $tests_failed;
header('Content-Type: text/html; charset=utf-8');
echo	h::title("Test results $tests_success/$tests_total ".round($tests_success / $tests_total * 100, 2).'%').
		h::meta([
			'charset'	=> 'utf-8'
		]).
		h::link([
			'href'	=> 'test/includes/style.css',
			'rel'	=> 'stylesheet'
		]).
		h::header(
			h::img([
				'src'	=> 'test/includes/logo.png'
			]).
			h::h1($title)
		).
		h::section(
			h::h2("Test results $tests_success/$tests_total ".round($tests_success / $tests_total * 100, 2).'%').
			h::article(array_map(
				function ($suite) {
					$tests_total	= $suite['success'] + $suite['failed'];
					return	h::h3("$suite[title] $suite[success]/$tests_total ".round($suite['success'] / $tests_total * 100, 2).'%').
							h::{'p.more'}(array_map(
								function ($test) {
									return [
										[
											$test['title'],
											[
												'class'	=> $test['result'] ? 'success' : 'failed'
											]
										],
										$test['result'] ? false : $test['result_text']
									];
								},
								$suite['tests']
							));
				},
				$test_suites
			))
		).
		h::footer(
			'Copyright (c) 2011-2013, Nazar Mokrynskyi'
		);