<?php
/**
 * @package		Feedback
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2013, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
global $Config, $Index, $Page, $L, $User, $Mail;
$Index->form	= true;
$Index->buttons	= false;
$Page->css('components/modules/Feedback/includes/css/general.css');
$Index->content(
	h::{'section.cs-feedback-form article'}(
		h::{'header h2.cs-center'}($L->Feedback).
		h::{'table.cs-fullwidth-table.cs-center tr| td'}([
			h::{'input[name=name][required]'}([
				'placeholder'	=> $L->feedback_name,
				'value'			=> $User->user() ? $User->username() : (isset($_POST['name']) ? $_POST['name'] : '')
			]),
			h::{'input[type=email][name=email][required]'}([
				'placeholder'	=> $L->feedback_email,
				'value'			=> $User->user() ? $User->email : (isset($_POST['email']) ? $_POST['email'] : '')
			]),
			h::{'textarea[name=text][required]'}([
				'placeholder'	=> $L->feedback_text,
				'value'			=> isset($_POST['text']) ? $_POST['text'] : ''
			]),
			h::{'button[type=submit]'}($L->feedback_send)
		])
	)
);
if (isset($_POST['name'], $_POST['email'], $_POST['text'])) {
	if (!$_POST['name'] || !$_POST['email'] || !$_POST['text']) {
		$Page->warning($L->feedback_fill_all_fields);
		return;
	}
	if ($Mail->send_to(
		$Config->core['admin_email'],
		$L->feedback_email_from(xap($_POST['name']), $Config->core['name']),
		xap($_POST['text']),
		null,
		null,
		$_POST['email']
	)) {
		$Page->notice($L->feedback_sent_successfully);
	} else {
		$Page->warning($L->feedback_sending_error);
	}
}