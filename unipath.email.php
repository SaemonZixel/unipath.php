<?php
/**
 * E-mail and MIME extension for UniPath 2.2+
 * 
 * @version 1.0
 * @author Saemon Zixel (saemonzixel@gmail.com)
 * @license Public Domain
 *
 */

if(function_exists('_uni_email') == false) {

function _uni_asMIME() { 
	trigger_error("Not emplemented yet!", E_USER_ERROR); 
}

function _uni_email($tree, $lv = 0) {

	$result = array('data' => array(), 'data_type' => 'array/email', 
		'metadata' => array('array/email', 'cursor()' => '_cursor_email'));
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	foreach($args_types as $name => $type)
		if($type == 'unipath') {
			$uni_result = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				isset($tree[$lv-1]['metadata']) 
					? $tree[$lv-1]['metadata'][0] 
					: $tree[$lv-1]['data_type'], 
				isset($tree[$lv-1]['data_tracking']) 
					? $tree[$lv-1]['data_tracking'] 
					: isset($tree[$lv-1]['metadata']) 
					? $tree[$lv-1]['metadata']
					: null, 
				$args[$name]);
			$result['data'][$name] = $uni_result['data'];
		} 
		else 
			$result['data'][$name] = $args[$name];

	return $result;
}

function _cursor_email($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
// if($cursor_cmd != 'next')
// var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:''));

	// EVAL headers()
	if($cursor_cmd == 'eval' and isset($cursor_arg1['name'][7]) and substr_compare($cursor_arg1['name'], "headers(", 0, 8, true) == 0) {
		
		$boundary_separator = md5(time());
		$eol = "\r\n"; // PHP_EOL;

		$from = $tree[$lv]['data']['from'][0];
		$from = is_array($from) && count($from) > 1 
			? '=?UTF-8?B?'.base64_encode($from[1]).'?= <'.$from[0].'>'
			: $from[0];
		$headers = "From: ".$from . $eol;
			
		if(!empty($tree[$lv]['data']['bcc'])) {
			$bcc = $tree[$lv]['data']['bcc'][0];
			$bcc = is_array($bcc) && count($bcc) > 1 
				? '=?UTF-8?B?'.base64_encode($bcc[1]).'?= <'.$bcc[0].'>'
				: $bcc[0];
			$headers .= "Bcc: " . $bcc . $eol;
		}
		
		// заголовок в формате multipart
		$headers .= "MIME-Version: 1.0" . $eol;
		$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary_separator\"" . $eol;
		$headers .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
		$headers .= "This is a MIME encoded message." . $eol . $eol;

		// тело сообщения
		$headers .= "--" . $boundary_separator . $eol;
		$headers .= "Content-Type: text/html; charset=\"utf-8\"" . $eol;
		$headers .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
		$headers .= $tree[$lv]['data']['body'] . $eol;
		
		// прикррепляем файлы
		if(!empty($tree[$lv]['data']['files'])) 
		foreach($tree[$lv]['data']['files'] as $file_rec) {
// 			$file_size = filesize($file);
			$human_filename = isset($file_rec[1]) ? $file_rec[1] : $file_rec[0];
			$content = chunk_split(base64_encode(file_get_contents($file_rec[0])));
			if(!empty($content)) {
				$headers .= "--" . $boundary_separator . $eol;
				$headers .= "Content-Type: application/octet-stream; name=\"$human_filename\"".$eol;
				$headers .= "Content-Transfer-Encoding: base64" . $eol;
				$headers .= "Content-Disposition: attachment; filename=\"$human_filename\"" . $eol . $eol;
				$headers .= $content . $eol;
			}
			else
				trigger_error("File {$file_rec[0]} is empty!", E_USER_ERROR);
		}
		$headers .= "--" . $boundary_separator . "--";
		
		return array(
			'data' => $headers,
			'data_type' => 'string',
			'metadata' => array('string'));
	}

	// EVAL xxx()
	if($cursor_cmd == 'eval' and $pos = strpos($cursor_arg1['name'], "(")) {
	
		// разберём аргументы
		list($args, $args_types) = __uni_parseFuncArgs($cursor_arg1['name']);
// 		assert('empty($args["arg1"]) == false;');
		foreach($args_types as $name => $type)
			if($type == 'unipath') {
				$uni_result = __uni_with_start_data(
					$tree[$lv-1]['data'], 
					isset($tree[$lv-1]['metadata']) 
						? $tree[$lv-1]['metadata'][0] 
						: $tree[$lv-1]['data_type'], 
					isset($tree[$lv-1]['data_tracking']) 
						? $tree[$lv-1]['data_tracking'] 
						: isset($tree[$lv-1]['metadata']) 
						? $tree[$lv-1]['metadata']
						: null, 
					$args[$name]);
				$args[$name] = $uni_result['data'];
			} 

		$result = array(
			'data' => $tree[$lv]['data'], 
			'data_type' => $tree[$lv]['metadata'][0],
			'metadata' => $tree[$lv]['metadata']);
			
		$func_name = strtolower(substr($cursor_arg1['name'], 0, $pos));
		switch($func_name) {
			default: // subject, body
				$result['data'][$func_name] = $args[0];
				break;
			case 'from':
/*				$result['data']['from'] = $args;
				break; */
			case 'to':
			case 'cc':
			case 'bcc':
				if(!isset($result['data'][$func_name]))
					$result['data'][$func_name] = array();
					
				// TODO сделать проверку на дубли
				if(is_array($args[0])) 
					foreach($args[0] as $email_rec) $result['data'][$func_name][] = $email_rec;
				else 
					$result['data'][$func_name][] = $args;
				break;
			case 'last_send_result':
			case 'last_error':
				if(array_key_exists($func_name, $tree[$lv]['metadata']))
					return array(
						'data' => $tree[$lv]['metadata'][$func_name],
						'data_type' => gettype($tree[$lv]['metadata'][$func_name]),
						'metadata' => array(gettype($tree[$lv]['metadata'][$func_name]), 'key()' => $func_name));
				else
					return array('data' => null, 'data_type' => 'null', 'metadata' => array('null', 'key()' => $func_name));
				break;
			case 'attacheFile':
			case 'addFile':
			case 'file':
				if(!isset($result['data']['files']))
					$result['data']['files'] = array();
				if(is_array($args[0])) 
					foreach($args[0] as $file_rec) $result['data']['files'][] = $file_rec;
				else 
					$result['data']['files'][] = $args;
				break;
			case 'send':
				assert('!empty($tree[$lv]["data"]["to"]);');
// var_dump($tree[$lv-1]);
				$to = $tree[$lv]['data']['to'][0];
				$to = is_array($to) && count($to) > 1 
					? '=?UTF-8?B?'.base64_encode($to[1]).'?= <'.$to[0].'>'
					: $to[0];
				$subject = empty($tree[$lv]['data']['subject'])?'':'=?UTF-8?B?'.base64_encode($tree[$lv]['data']['subject']).'?=';
				$headers = _cursor_email($tree, $lv, 'eval', array('name' => 'headers()'));
// var_dump($headers);
				$result['metadata']['last_send_result'] = call_user_func(
					isset($GLOBALS['unipath_test_mail_function']) ? 
						$GLOBALS['unipath_test_mail_function'] 
						: 'mail', 
					$to, $subject, "", 
					$headers['data']);
				$result['metadata']['last_error'] = error_get_last();
				break;
		}
// print_r($result);
		return $result;
	}
	
	// EVAL xxx
	elseif($cursor_cmd == 'eval') {
		if(array_key_exists($cursor_arg1['name'], $tree[$lv-1]['data']))
			return array(
			'data' => $tree[$lv-1]['data'][$cursor_arg1['name']],
			'data_type' => gettype($tree[$lv-1]['data'][$cursor_arg1['name']]),
			'metadata' => array(gettype($tree[$lv-1]['data'][$cursor_arg1['name']]), 'key()' => $cursor_arg1['name']));
		else
			return array('data' => null, 'data_type' => 'null', 'metadata' => array('null', 'key()' => $cursor_arg1['name']));
	}
}

function __test_mail($to, $subject, $message, $additional_headers = null, $additional_parameters = null) {
// var_dump(func_get_args());
	$GLOBALS['unipath_test_mail_arguments'] = func_get_args();
	return true;
}

function _tests_email() {
	$GLOBALS['unipath_test_mail_function'] = '__test_mail';
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/email()/to('root@localhost')/from('nobody@localhost')/body('123')/send()/last_send_result()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
// print_r($result);
	assert('$result == true; /* '.var_export($result, true).' */');
	
// print_r($GLOBALS['unipath_test_mail_arguments']);
	assert('$GLOBALS[\'unipath_test_mail_arguments\'][0] == "root@localhost"; /* '.var_export($GLOBALS['unipath_test_mail_arguments'][0], true).' */');
	assert('$GLOBALS[\'unipath_test_mail_arguments\'][1] == ""; /* '.var_export($GLOBALS['unipath_test_mail_arguments'][1], true).' */');
	assert('$GLOBALS[\'unipath_test_mail_arguments\'][2] == ""; /* '.var_export($GLOBALS['unipath_test_mail_arguments'][2], true).' */');
	
	preg_match('~boundary="([0-9a-f]+)"~', $GLOBALS['unipath_test_mail_arguments'][3], $boundary);
	$expect = "From: nobody@localhost\r
MIME-Version: 1.0\r
Content-Type: multipart/mixed; boundary=\"{$boundary[1]}\"\r
Content-Transfer-Encoding: 7bit\r
\r
This is a MIME encoded message.\r
\r
--{$boundary[1]}\r
Content-Type: text/html; charset=\"utf-8\"\r
Content-Transfer-Encoding: 8bit\r
\r
123\r
--{$boundary[1]}--";

/*	for($i = 0; $i < strlen($expect); $i++) {
		var_dump($i, substr($expect, 0, $i) == substr($GLOBALS['unipath_test_mail_arguments'][3], 0, $i), substr($expect, 0, $i));
	} */
	
	assert('$GLOBALS[\'unipath_test_mail_arguments\'][3] == $expect; /* '.var_export($GLOBALS['unipath_test_mail_arguments'][3], true).' */');
	
}

}