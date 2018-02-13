<?php
/**
 * HTML CAPTCHA extension for UniPath 2.4+
 * 
 * @version 0.1
 * @author Saemon Zixel (saemonzixel@gmail.com)
 * @license Public Domain
 *
 */

if(function_exists('_uni_captcha_html') == false) {

	function _uni_captcha_html($tree, $lv = 0) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		
		// строка которую надо зашифровать
		if(!isset($args[0]))
			$captcha_text = substr(time().'', rand(0,6), 4);
		elseif($args_types[0] == 'unipath') {
			$captcha_text =__uni_with_start_data(
					$tree[$lv-1]['data'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'][0] : $tree[$lv-1]['data_type'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'] : (isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null),
					$args[0]);
			$captcha_text = $captcha_text['data'];
		}
		else
			$captcha_text = $args[0];
		assert('is_string($captcha_text); /* '.print_r($captcha_text, true).' */');

		// ширина каптчи в px
		/*if(!isset($args[1]))
			$captcha_width = 80;
		elseif($args_types[1] == 'unipath')
			$captcha_width =__uni_with_start_data(
					$tree[$lv-1]['data'],
					$tree[$lv-1]['metadata'][0],
					$tree[$lv-1]['metadata'],
					$args[1]);
		else
			$captcha_width = $args[1];
		
		// высота каптчи в px
		if(!isset($args[2]))
			$captcha_height = 20;
		elseif($args_types[2] == 'unipath')
			$captcha_height =__uni_with_start_data(
					$tree[$lv-1]['data'],
					$tree[$lv-1]['metadata'][0],
					$tree[$lv-1]['metadata'],
					$args[2]);
		else
			$captcha_height = $args[2];*/
			
		// цвет каптчи CSS
		if(!isset($args['color']))
			$captcha_color = 'black';
		elseif($args_types['color'] == 'unipath') {
			$captcha_color = __uni_with_start_data(
					$tree[$lv-1]['data'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'][0] : $tree[$lv-1]['data_type'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'] : (isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null),
					$args['color']);
			$captcha_color = $captcha_color['data'];
		}
		else
			$captcha_color = $args['color'];
		$captcha_color = is_array($captcha_color) ? $captcha_color : explode(",", $captcha_color);
			
		// фоновый цвет каптчи CSS
		if(!isset($args['bgcolor']))
			$captcha_bgcolor = 'white';
		elseif($args_types['bgcolor'] == 'unipath') {
			$captcha_bgcolor =__uni_with_start_data(
					$tree[$lv-1]['data'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'][0] : $tree[$lv-1]['data_type'],
					isset($tree[$lv-1]['metadata']) ? $tree[$lv-1]['metadata'] : (isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null),
					$args['bgcolor']);
			$captcha_bgcolor = $captcha_bgcolor['data'];
		}
		else
			$captcha_bgcolor = $args['bgcolor'];
			
		// свёрстанный алфавит
		$captcha_html_chars = '
	<div id="char0" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:8px"></i>
		<i style="top:1px;left:5px;height:8px"></i>
		<i style="top:3px;left:4px"></i>
		<i style="top:4px;left:3px"></i>
		<i style="top:5px;left:2px"></i>
		<i style="top:6px;width:2px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="char1" class="captcha-char">
		<i style="left:2px;height:10px"></i>
		<i style="top:1px;left:1px"></i>
		<i style="top:2px"></i>
		<i style="top:9px;width:5px"></i>
	</div>
	<div id="char2" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:2px;"></i>
		<i style="top:1px;left:5px;height:3px"></i>
		<i style="top:3px;left:5px"></i>
		<i style="top:4px;left:4px"></i>
		<i style="top:5px;left:3px"></i>
		<i style="top:6px;left:2px"></i>
		<i style="top:7px;left:1px"></i>
		<i style="top:8px"></i>
		<i style="top:9px;width:6px"></i>
	</div>
	<div id="char3" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:2px;"></i>
		<i style="top:1px;left:5px;height:3px"></i>
		<i style="top:4px;left:2px;width:3px"></i>
		<i style="top:5px;left:5px;height:4px"></i>
		<i style="top:7px;height:2px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="char4" class="captcha-char">
		<i style="left:5px;height:10px"></i>
		<i style="top:1px;left:4px"></i>
		<i style="top:2px;left:3px"></i>
		<i style="top:3px;left:2px"></i>
		<i style="top:4px;left:1px"></i>
		<i style="top:5px"></i>
		<i style="top:6px;width:5px"></i>
	</div>
	<div id="char5" class="captcha-char">
		<i style="width:6px"></i>
		<i style="top:1px;height:4px"></i>
		<i style="top:4px;width:5px"></i>
		<i style="top:5px;left:5px;height:4px"></i>
		<i style="top:7px"></i>
		<i style="top:8px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="char6" class="captcha-char">
		<i style="left:2px;width:3px"></i>
		<i style="top:1px;left:1px"></i>
		<i style="top:2px"></i>
		<i style="top:3px"></i>
		<i style="top:4px;width:5px"></i>
		<i style="top:5px"></i>
		<i style="top:5px;left:5px"></i>
		<i style="top:6px"></i>
		<i style="top:6px;left:5px"></i>
		<i style="top:7px"></i>
		<i style="top:7px;left:5px"></i>
		<i style="top:8px"></i>
		<i style="top:8px;left:5px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="char7" class="captcha-char">
		<i style="width:6px"></i>
		<i style="top:1px;left:5px;height:2px"></i>
		<i style="top:3px;left:4px;height:2px"></i>
		<i style="top:5px;left:3px;height:2px"></i>
		<i style="top:7px;left:2px;height:3px"></i>
	</div>
	<div id="char8" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:3px;"></i>
		<i style="top:1px;left:5px;height:3px"></i>
		<i style="top:4px;left:1px;width:4px"></i>
		<i style="top:5px;left:5px;height:4px"></i>
		<i style="top:5px;height:4px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="char9" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:4px;"></i>
		<i style="top:1px;left:5px;height:7px"></i>
		<i style="top:5px;left:1px;width:4px"></i>
		<i style="top:8px;left:4px"></i>
		<i style="top:9px;width:4px"></i>
	</div>
	<div id="charA" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:9px;"></i>
		<i style="top:1px;left:5px;height:9px"></i>
		<i style="top:5px;left:1px;width:4px"></i>
	</div>
	<div id="charB" class="captcha-char">
		<i style="width:5px"></i>
		<i style="top:1px;height:9px;"></i>
		<i style="top:1px;left:5px;height:3px"></i>
		<i style="top:4px;left:1px;width:4px"></i>
		<i style="top:5px;left:5px;height:4px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="charC" class="captcha-char">
		<i style="left:1px;width:4px"></i>
		<i style="top:1px;height:8px;"></i>
		<i style="top:1px;left:5px;height:2px"></i>
		<i style="top:7px;left:5px;height:2px"></i>
		<i style="top:9px;left:1px;width:4px"></i>
	</div>
	<div id="charD" class="captcha-char">
		<i style="width:4px"></i>
		<i style="top:1px;height:8px;"></i>
		<i style="top:1px;left:4px"></i>
		<i style="top:2px;left:5px;height:6px;"></i>
		<i style="top:8px;left:4px"></i>
		<i style="top:9px;width:4px"></i>
	</div>
	<div id="charE" class="captcha-char">
		<i style="width:6px"></i>
		<i style="top:1px;height:9px;"></i>
		<i style="top:4px;left:1px;width:3px"></i>
		<i style="top:9px;left:1px;width:5px"></i>
	</div>
	<div id="charF" class="captcha-char">
		<i style="width:6px"></i>
		<i style="top:1px;height:9px;"></i>
		<i style="top:4px;left:1px;width:3px"></i>
	</div>';
		
		// распарсим алфавит в структуру
		$captcha_chars = array();
		foreach(explode("<div id=\"char", $captcha_html_chars) as $captcha_html_char) {
			if(strlen($captcha_html_char) < 10) continue;
			$captcha_chars[$captcha_html_char[0]] = array_map('trim', explode("\n", trim( substr($captcha_html_char, 24, -8), "\n\r\t ")));
		}
// echo '<xmp>'.print_r($captcha_chars, true).'</xmp>';

		// формируем каптчу
		$captcha = array();
		for($i = 0; $i < strlen($captcha_text); $i++) {
			$captcha_char = isset($captcha_chars[$captcha_text[$i]]) 
				? $captcha_chars[$captcha_text[$i]]
				: array();
			shuffle($captcha_char);
			$captcha[] = '<div style="display:inline-block;width:7px;height:10px;position:relative;transform:skewX('.(rand(0,2)>1?'-':'').rand(0,20).'deg) skewY('.(rand(0,2)>1?'-':'').rand(0,20).'deg);">'.str_replace('<i style="', '<i style="display:block;width:1px;height:1px;position:absolute;top:0;left:0;background:'.$captcha_color[rand(0,count($captcha_color)-1)].';transform:none;', implode("\n\t", $captcha_char)).'</div>';
		}
		
		// добавим белую линию поверх
		$captcha[] = '<div style="position:absolute;width:'.(count($captcha)*7).'px;height:2px;background:'.$captcha_bgcolor.';top:'.(rand(30,60)).'%;transform:rotate('.(rand(0,2)>1?'-':'').rand(0,2).'deg);"></div>';
		
		return array('data' => implode("", $captcha), 'data_type' => 'string', 'metadata' => array('string'));
	}
	
	function _tests_captcha_html() {
		
		echo "<h3>--- _uni_captcha_html('1234567890?ABCDEF', color=`red,green,blue,black`, bgcolor=`yellow`) ---</h3>";
		$uni_result = _uni_captcha_html(array(array(
			'name' => 'captcha_html("1234567890?ABCDEF", color=`red,green,blue,black`, bgcolor=`yellow`)',
			'data' => null)), 0);
// echo '<xmp>'.print_r($uni_result,true).'</xmp>';
		assert('$uni_result["metadata"][0] == "string"; /* '.print_r($uni_result["metadata"], true).' */');
		assert('is_string($uni_result["data"]); /* '.var_export($uni_result["data"], true).' */')
		and print('<div style="white-space:normal;position:relative;display:inline-block;background:yellow;padding:3px 7px;overflow:hidden;transform:scale(2);transform-origin:0 0;">'.$uni_result["data"].'</div>');
	}
}