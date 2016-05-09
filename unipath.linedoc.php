<?php
/**
 * LineDocument extension for UniPath 2.x
 * 
 * @version 1.0
 * @author Saemon Zixel (saemonzixel@gmail.com)
 * @license Public Domain
 *
 */

if(function_exists('_uni_asLineDocument') == false) {

function _uni_asLineDocument($tree, $lv = 0) {

	if($tree[$lv-1]['data_type'] == "string/local-pathname")
		$res = fopen($tree[$lv-1]['data'], 'rb');
	elseif(is_resource($tree[$lv-1]['data']))
		$res = $tree[$lv-1]['data'];
	else {
		trigger_error('UniPath: asLineDocument(): Unknown type of source - '.gettype($tree[$lv-1]['data']).'('.substr(strval($tree[$lv-1]['data']), 0, 20).'...)');
		return array('data' => null, 'data_type' => 'null');
	}

	$cursor_vars = array();
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	// if_not_seekable = <resource>
	if(!isset($args['if_not_seekable']))
		/* skip */;
	elseif($args_types['if_not_seekable'] == 'unipath') {
		$arg1 = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['data_type'], isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null, $args['if_not_seekable']);
		$cursor_vars['if_not_seekable'] = $arg1['data'];
		
		// сразу обрежем файл
		if(is_resource($cursor_vars['if_not_seekable']))
			ftruncate($cursor_vars['if_not_seekable'], 0);
	} else {
		$cursor_vars['if_not_seekable'] = $args['if_not_seekable'];
	}
	
	// records_limit = NNN
// 	if(isset($args['records_limit']))
// 		$cursor_vars['records_limit'] = $args['records_limit'];

	return array(
		'data' => $res, 
		'data_type' => 'resource/linedocument',
		'data_tracking' => array(
			'key()' => isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking']['key()'] : null,
			'cursor()' => '_cursor_asLineDocument',
			'cursor_vars' => $cursor_vars)
		);
}

function _cursor_asLineDocument($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
// if($cursor_cmd != 'next')
// var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:''));

	$cursor_vars = isset($tree[$lv]['data_tracking']['cursor_vars']) 
		? $tree[$lv]['data_tracking']['cursor_vars'] 
		: array();
		
	assert('is_resource($tree[$lv]["data"])') or var_dump("NOT resource = ",$tree[$lv]['name']);
		
	// seekable - определимся с seekable для начала
	if(array_key_exists('source_seekable', $cursor_vars) == false) {
		$meta = stream_get_meta_data($tree[$lv]['data']) or var_dump($tree[$lv]);
		$cursor_vars['source_seekable'] = $meta['seekable'];
	}
		
	// REWIND - попросили перемотать в начало
	if($cursor_cmd == 'rewind') {
		
		// источник поток перематываемый, так что перемотаем его и всё
		if(!empty($cursor_vars['source_seekable'])) {
		
			// перематываем в начало поток, который не закончен
			if(feof($tree[$lv]['data']) == false and ftell($tree[$lv]['data']) > 0) 
				trigger_error('UniPath: asLineDocument_cursor(rewind): rewind feof(<original source>) == false!', E_USER_NOTICE);
		
			$ret = fseek($tree[$lv]['data'], 0);
			if($ret == -1) {
				// вернём обратно кодировку mbstring если включена
				isset($mbstring_internal_encoding) and ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
				
				trigger_error('UniPath: asLineDocument_cursor(rewind): fseek(<original source>, 0) faild!');
				return array(false, 'cursor_vars' => $cursor_vars);
			}
			
			unset($cursor_vars['last_line']);
		} 
		
		// источник поток не перематывается, перематываем вспомогательный буфер
		elseif(empty($cursor_vars['source_seekable']) and isset($cursor_vars['if_not_seekable'])) {
		
			if(feof($tree[$lv]['data']) == false and ftell($tree[$lv]['data']) > 0)
				trigger_error('UniPath: asLineDocument_cursor(rewind): original source stream not ended!', E_USER_NOTICE);
				
			$ret = fseek($cursor_vars['if_not_seekable'], 0);
			if($ret == -1) {
				trigger_error('UniPath: asLineDocument_cursor(rewind): fseek(<if_not_seekable>, 0) faild!');
				return array(false, 'cursor_vars' => $cursor_vars);
			} else
			
			$cursor_vars['last_line'] = fgets($cursor_vars['if_not_seekable']);
if(!empty($GLOBALS['unipath_debug'])) var_dump('UniPath: asLineDocument_cursor(rewind): buffer rewinded. (last_line = '.$cursor_vars['last_line'].')');
		} 
		
		// оригенальный поток не перематывается и нет вспомогательного буфера!
		else {
			trigger_error('UniPath: asLineDocument_cursor(rewind): original source not seekable!');
			return array(false, 'cursor_vars' => $cursor_vars);
		}
		
		return array(true, 'cursor_vars' => $cursor_vars);
	}
	
	// NEXT - следующий элемент из потока
	if($cursor_cmd == 'next' and $tree[$lv]['data_type'] == 'resource/linedocument') {
		if(is_resource($tree[$lv]["data"]) == false) {
			trigger_error('UniPath: asLineDocument_cursor('.$cursor_cmd.'): $tree[$lv][data] is not resource type!');
			return;
		}
		
		// временно переключим mbstring.func_overload в 1-байтовую кодировку
		if(ini_get('mbstring.func_overload') > 1) {
			$mbstring_internal_encoding = ini_get('mbstring.internal_encoding');
			ini_set('mbstring.internal_encoding', 'ISO-8859-1');
		}

		$record = array(); // результат
		$prefix = false; $prefix_len = 0; $key = false; 
		$buffer = array(); // считанные строки для записи в if_not_seekable
		global $__uni_prt_cnt; // защита от зацикливания
		for($prt_cnt = $__uni_prt_cnt*100; $prt_cnt; $prt_cnt-- ) {

			// возмём считанную и не обработанную с прошлого раза
			if(array_key_exists('last_line', $cursor_vars)) {
				$line = $cursor_vars['last_line'];
				unset($cursor_vars['last_line']);
// var_dump('$line = $cursor_vars[\'last_line\']');
			} 
			
			// либо считаем новую
			elseif(feof($tree[$lv]['data']) == false) {
				$line = fgets($tree[$lv]['data']);
// var_dump('$line = $tree[$lv][\'data\'], foef = '.feof($tree[$lv]['data']));
				// если исходный поток/файл нельзя будет перемотать в начало, то сохраняем всё что считываем в промежуточный файл
				if($cursor_vars['source_seekable'] == false and isset($cursor_vars['if_not_seekable']) and is_resource($cursor_vars['if_not_seekable']))
					$buffer[] = $line;
			} 
			
			// либо считаем новую из вспомогательного файла
			elseif(isset($cursor_vars['if_not_seekable']) 
			and is_resource($cursor_vars['if_not_seekable']) 
			and feof($cursor_vars['if_not_seekable']) == false 
			and ($line = fgets($cursor_vars['if_not_seekable'])) !== false) {
				/* $line */
// var_dump('$line = $cursor_vars[\'if_not_seekable\'], foef = '.feof($cursor_vars['if_not_seekable']));
			}
			
			// либо исходный и вспомогательный поток/файл закончился
			else {
				if(!empty($buffer) and isset($cursor_vars['if_not_seekable']))
					fwrite($cursor_vars['if_not_seekable'], implode('', $buffer));
// var_dump(__FUNCTION__.': original or buffer source is feof!', 'feof(*** original ***) = '.feof($tree[$lv]['data']), 'feof(*** if_not_seekable ***) = '.(isset($cursor_vars['if_not_seekable']) ? feof($cursor_vars['if_not_seekable']) : '(none)'), 'empty($record) = '.empty($record));
// var_dump($cursor_vars);

				// вернём обратно кодировку mbstring если включена
				isset($mbstring_internal_encoding) and ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);

				if(empty($record))
					return array('cursor_vars' => $cursor_vars);
				else
					return array(
						'data' => $record, 
						'data_type' => 'string', 
						'data_tracking' => array('key()' => $prefix), 
						'cursor_vars' => $cursor_vars);
			}
				
			// уберём BOM если есть
			if(strncmp($line, pack('CCC', 0xef, 0xbb, 0xbf), 3) == 0) // UTF-8 BOM
				$line = trim(mb_substr($line, 3, 999, 'ISO-8859-1'));
			else
				$line = trim($line);
// var_dump($line);
			// название поля
			if(strncmp($line, '---', 3) == 0) {
				if(!$prefix) { 
					$prefix_len = strpos($line, '--', 3) - 3;
					$prefix = substr($line, 3, $prefix_len);
				}

				// если наш префикс, то наше поле
				if(strpos($line, $prefix) == 3 and in_array($line[$prefix_len+3], array('-', "\n", "\r"))) {
					$key = substr($line, $prefix_len+5);
					$record[$key] = null;
				}
				
				// ой, пошло чужое поле
				else {
					/*$prefix_len = strpos($line, '--', 3) - 3;
					$prefix = substr($line, 3, $prefix_len);
					continue;*/
					
					if(!empty($buffer) and isset($cursor_vars['if_not_seekable']))
						fwrite($cursor_vars['if_not_seekable'], implode('', $buffer));

					// вернём обратно кодировку mbstring если включена
					isset($mbstring_internal_encoding) and ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
					
					return array(
						'data' => $record, 
						'data_type' => 'string', 
						'data_tracking' => array('key()' => $prefix), 
						'cursor_vars' => /*empty($line_is_last_line) 
							?*/ array_merge($cursor_vars, array('last_line' => $line))
							/*: $cursor_vars*/
						);
				}
			} 
			
			// значение поля
			else {
				if(isset($record[$key]))
					$record[$key] .= "\n$line";
				else
					$record[$key] = $line;
			}
		}
	}
	
	if($cursor_cmd == 'eval' and
	 ( strpos($cursor_arg1['name'], '(') !== false
// 	|| strncmp($cursor_arg1['name'], 'cache(', 6) == 0 
// 	|| strncmp($cursor_arg1['name'], 'ifEmpty(', 6) == 0
	|| strpos($cursor_arg1['name'], '%') !== false
	|| $cursor_arg1['name'] == '.'))
		return false;
	
	trigger_error('UniPath: asLineDocument_cursor('.$cursor_cmd.'): Why we are here???');
	return array('data' => null, 'data_type' => 'null');
}

function _tests_asLineDocument() {

$test_ldoc_header = "---FileHeader--DateTime
2016-03-08 22:54:12
---FileHeader--Generator
ОбменИнтернетМагазин_2802_2016.epf";

$test_ldoc_groups = <<<LDOC2
---Группа1--Code
УТ0000001  
---Группа1--Description
Бытовая техника
---Группа1--Родитель
Товары
---Группа1--Родитель--Код
УТ000000Х  
---Группа1--DeletionMark
ложь
---Группа135--Code
УТ0000004  
---Группа135--Description
Компьютерная техника
---Группа135--Родитель
Товары
---Группа135--Родитель--Код
УТ000000Х  
---Группа135--DeletionMark
ложь
LDOC2;

$test_ldoc_products = <<<LDOC4
---Номенклатура21--Code
ОЦ000010000
---Номенклатура21--ВыгружатьНаСайт
ложь
---Номенклатура21--Цены--Краснодар
3744
---Номенклатура21--Цены--ИнтернетЦена
4640
---Номенклатура21--Цены--Закупочный
0
---Номенклатура21--Цены--Безнал
4826
---Номенклатура21--Цены--Карта-интернет
4733
---Номенклатура21--Цены--Оптовая
4593,6
---Номенклатура21--ОстаткиНаСкладе--Сочи
0
---Номенклатура2138--Code
ОЦ000020000
---Номенклатура2138--ВыгружатьНаСайт
ложь
---Номенклатура2138--Цены--ИнтернетЦена
71 898,01
---Номенклатура2138--Цены--Краснодар
111 771,02
---Номенклатура2138--Description
Принтер HP Color LaserJet Enterprise CP4525xh 
---Номенклатура2138--Родитель
Компьютеры
---Номенклатура2138--Родитель--Код
УТ0000005
---Номенклатура2138--DeletionMark
ложь
---Номенклатура2138--Ссылка
{"#",44a01d39-c2b6-4751-85e6-06fdc2f23043,62:18bb001122334455bbeb0265cf156f1f}
---Номенклатура2138--Артикул
CC495A
---Номенклатура2138--БазоваяЕдиницаИзмерения
шт
---Номенклатура2138--Комментарий

---Номенклатура2138--НаименованиеПолное
Принтер HP Color LaserJet Enterprise CP4525xh 
---Номенклатура2138--ДополнительноеОписаниеНоменклатуры

Общие характеристики
Устройство	принтер 
Тип печати	цветная 
Технология печати	лазерная 
Размещение	напольный 
Область применения	большой офис 
Количество страниц в месяц	120000 

Принтер	
Максимальный формат:	A4 
Автоматическая двусторонняя печать: есть 
Количество цветов: 4 
Максимальное разрешение для ч/б печати: 1200x1200 dpi 
Максимальное разрешение для цветной печати: 1200x1200 dpi 
Скорость печати: 40 стр/мин (ч/б А4), 40 стр/мин (цветн. А4) 
Время выхода первого отпечатка: 9.50 c (ч/б), 9.50 c (цветн.) 

---Номенклатура2138--КраткоеОписание
Принтер HP Color LaserJet Enterprise CP4525xh, А4, цветной
---Номенклатура2138--Цены--Закупочный
0
---Номенклатура2138--Цены--Безнал
74774,03
---Номенклатура2138--Цены--Карта-интернет
173 336,04
---Номенклатура2138--Цены--Оптовая
171 179,02
---Номенклатура2138--ОстаткиНаСкладе--Сочи
1 999
---Номенклатура2138--ДополнительноеОписаниеНоменклатурыВФорматеHTML
<p>Подробные характеристики принтера на официальном сайте производителя - 
<a href="http://store.hp.com/us/en/pdp/hp-color-laserjet-enterprise-cp4525xh-printer-p-cc495a">HP Color LaserJet Enterprise CP4525xh (CC495A)</a></p>
---Номенклатура2139--Code
ОЦ000030000
---Номенклатура2139--ВыгружатьНаСайт
ложь
---Номенклатура2139--Цены--Краснодар
210568
---Номенклатура2139--Цены--ИнтернетЦена
210990
---Номенклатура2139--Description
Принтер Xerox Phaser 7760DN 
---Номенклатура2139--Родитель
Компьютеры
---Номенклатура2139--Родитель--Код
УТ0000005
---Номенклатура2139--DeletionMark
ложь
---Номенклатура2139--Ссылка
{"#",44a01d39-c2b6-4751-85e6-06fdc2f23043,62:1bbe001122334455be7d1d30cf1579a5}
---Номенклатура2139--Артикул
#7760V_Z
---Номенклатура2139--НаименованиеПолное
Принтер Xerox Phaser 7760DN 
---Номенклатура2139--Изображение1--СодержимоеВBase64
iVBORw0KGgoAAAANSUhEUgAAAoAAAAGQCAIAAACxkUZyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA
B3RJTUUH3AsYFgkMi4oQNQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUH
AAAE+0lEQVR42u3VMREAAAjEMMC/58cFA5dI6NJOUgDArZEAAAwYAAwYADBgADBgAMCAAcCAAQAD
BgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMG
AAMGAAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYA
AwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgAD
BgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMG
AAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYA
DBgADBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYADBgADBgADBgAMCAAcCAAQADBgADBgAM
GAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwY
AAwYADBgADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYADBgA
DBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYADBgADBgADBgAMCAAcCAAQADBgADBgAMGAAM
GAAwYAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwY
ADBgADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAwYAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgA
MGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAw
YAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwYADBg
ADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAwYAAwYADAgAHAgAHAgCUAAAMGAAMGAAwYAAwYADBg
ADBgAMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAA
MGAAwIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAw
YADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBg
AMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAAMGAA
wIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADA
gAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYAAwYADBgADBgAMCA
AcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIAB
wIABAAMGAAMGAAwYAAwYAAwYADBgAPhrAUkcBh1kyqz7AAAAAElFTkSuQmCC
LDOC4;

	@unlink('unipath.linedoc.test.txt');
	file_put_contents('unipath.linedoc.test.txt', $test_ldoc_header."\n".$test_ldoc_groups."\n".$test_ldoc_products);
	$tree = array(
		array(
			'name' => 'unipath.linedoc.test.txt',
			'data' => 'unipath.linedoc.test.txt',
			'data_type' => 'string/local-pathname'
		),
		array(
			'name' => 'asLineDocument()',
			'data' => null
		)
	);
	
	echo '<pre style="white-space:pre-wrap">';
	
	// --- asLineDocument()
	echo "<h3>--- _uni_asLineDocument() ---</h3>";
	$uni_result = _uni_asLineDocument($tree, 1);
// print_r($uni_result);
	assert('$uni_result["data_type"] == "resource/linedocument"; /* '.print_r($uni_result["data_type"], true).' */');
	assert('$uni_result["data_tracking"]["cursor()"] == "_cursor_asLineDocument"; /* '.print_r($uni_result["data_tracking"]["cursor()"], true).' */');
	assert('$uni_result["data_tracking"]["cursor_vars"] == array(); /* '.print_r($uni_result["data_tracking"]["cursor_vars"], true).' */');
	
	$uni_result += $tree[1];
	
	// --- REWIND
	echo "<h3>--- _cursor_asLineDocument(rewind) ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'rewind');
// var_dump($call_result);
	assert('$call_result[0] == true; /* '.json_encode($call_result).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true); /* '.json_encode($call_result).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	} 

	// --- NEXT #1
	echo "<h3>--- _cursor_asLineDocument(next) #1 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	assert("\$call_result['data'] == array('DateTime' => '2016-03-08 22:54:12', 'Generator' => 'ОбменИнтернетМагазин_2802_2016.epf'); /* ".print_r($call_result['data'], true).' */');
	assert("\$call_result['data_type'] == 'string'; /* ".print_r($call_result['data_type'], true).' */');
	assert("\$call_result['data_tracking'] == array('key()' => 'FileHeader'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true, "last_line" => "---Группа1--Code"); /* '.json_encode($call_result).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #2
	echo "<h3>--- _cursor_asLineDocument(next) #2 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	assert('$call_result["data"] == array(
		"Code" => "УТ0000001",
		"Description" => "Бытовая техника",
		"Родитель" => "Товары",
		"Родитель--Код" => "УТ000000Х",
		"DeletionMark" => "ложь"); /* '.print_r($call_result['data'], true).' */');
	assert("\$call_result['data_tracking'] == array('key()' => 'Группа1'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true, "last_line" => "---Группа135--Code"); /* '.print_r($call_result['cursor_vars'], true).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #3
	echo "<h3>--- _cursor_asLineDocument(next) #3 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	assert('$call_result["data"] == array(
		"Code" => "УТ0000004",
		"Description" => "Компьютерная техника",
		"Родитель" => "Товары",
		"Родитель--Код" => "УТ000000Х",
		"DeletionMark" => "ложь"); /* '.print_r($call_result['data'], true).' */');
	assert("\$call_result['data_tracking'] == array('key()' => 'Группа135'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true, "last_line" => "---Номенклатура21--Code"); /* '.print_r($call_result['cursor_vars'], true).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #4
	echo "<h3>--- _cursor_asLineDocument(next) #4 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	assert('$call_result["data"] == array(
		"Code" => "ОЦ000010000",
		"ВыгружатьНаСайт" => "ложь",
		"Цены--Краснодар" => "3744",
		"Цены--ИнтернетЦена" => "4640",
		"Цены--Закупочный" => "0",
		"Цены--Безнал" => "4826",
		"Цены--Карта-интернет" => "4733",
		"Цены--Оптовая" => "4593,6",
		"ОстаткиНаСкладе--Сочи" => "0"); /* '.print_r($call_result['data'], true).' */');
	assert("\$call_result['data_tracking'] == array('key()' => 'Номенклатура21'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true, "last_line" => "---Номенклатура2138--Code"); /* '.print_r($call_result['cursor_vars'], true).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #5
	echo "<h3>--- _cursor_asLineDocument(next) #5 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	foreach(array(
		"Code" => "ОЦ000020000",
		"ВыгружатьНаСайт" => "ложь",
		"Цены--ИнтернетЦена" => "71 898,01",
		"Цены--Краснодар" => "111 771,02",
		"Description" => "Принтер HP Color LaserJet Enterprise CP4525xh",
		"Родитель" => "Компьютеры",
		"Родитель--Код" => "УТ0000005",
		"DeletionMark" => "ложь",
		"Ссылка" => "{\"#\",44a01d39-c2b6-4751-85e6-06fdc2f23043,62:18bb001122334455bbeb0265cf156f1f}",
		"Артикул" => "CC495A",
		"БазоваяЕдиницаИзмерения" => "шт",
		"Комментарий" => "",
		"НаименованиеПолное" => "Принтер HP Color LaserJet Enterprise CP4525xh",
		"ДополнительноеОписаниеНоменклатуры" => "
Общие характеристики
Устройство	принтер
Тип печати	цветная
Технология печати	лазерная
Размещение	напольный
Область применения	большой офис
Количество страниц в месяц	120000

Принтер
Максимальный формат:	A4
Автоматическая двусторонняя печать: есть
Количество цветов: 4
Максимальное разрешение для ч/б печати: 1200x1200 dpi
Максимальное разрешение для цветной печати: 1200x1200 dpi
Скорость печати: 40 стр/мин (ч/б А4), 40 стр/мин (цветн. А4)
Время выхода первого отпечатка: 9.50 c (ч/б), 9.50 c (цветн.)
",
    "КраткоеОписание" => "Принтер HP Color LaserJet Enterprise CP4525xh, А4, цветной",
    "Цены--Закупочный" => "0",
    "Цены--Безнал" => "74774,03",
    "Цены--Карта-интернет" => "173 336,04",
    "Цены--Оптовая" => "171 179,02",
    "ОстаткиНаСкладе--Сочи" => "1 999",
    "ДополнительноеОписаниеНоменклатурыВФорматеHTML" => "<p>Подробные характеристики принтера на официальном сайте производителя -\n<a href=\"http://store.hp.com/us/en/pdp/hp-color-laserjet-enterprise-cp4525xh-printer-p-cc495a\">HP".chr(194).chr(160)."Color LaserJet Enterprise CP4525xh (CC495A)</a></p>") as $key => $val)
		if( ! assert('$call_result["data"]["'.$key.'"] == "'.addcslashes($val, '"').'";')) {
			for($i = 0; $i < strlen($call_result["data"][$key]); $i++) {
				$char1 = $call_result["data"][$key][$i]; $char2 = $val[$i];
				if($char1 != $char2) 
					var_dump("$i: ".$char1." [".ord($char1)."] != ".$char2." [".ord($char2)."]");
			}
		}
		
	assert("\$call_result['data_tracking'] == array('key()' => 'Номенклатура2138'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true, "last_line" => "---Номенклатура2139--Code"); /* '.print_r($call_result['cursor_vars'], true).' */');
	
	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #6
	echo "<h3>--- _cursor_asLineDocument(next) #6 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	foreach(array(
		"Code" => "ОЦ000030000",
		"ВыгружатьНаСайт" => "ложь",
		"Цены--Краснодар" => "210568",
		"Цены--ИнтернетЦена" => "210990",
		"Description" => "Принтер Xerox Phaser 7760DN",
		"Родитель" => "Компьютеры",
		"Родитель--Код" => "УТ0000005",
		"DeletionMark" => "ложь",
		"Ссылка" => '{"#",44a01d39-c2b6-4751-85e6-06fdc2f23043,62:1bbe001122334455be7d1d30cf1579a5}',
		"Артикул" => "#7760V_Z",
		"НаименованиеПолное" => "Принтер Xerox Phaser 7760DN",
		"Изображение1--СодержимоеВBase64" => "iVBORw0KGgoAAAANSUhEUgAAAoAAAAGQCAIAAACxkUZyAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA
B3RJTUUH3AsYFgkMi4oQNQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUH
AAAE+0lEQVR42u3VMREAAAjEMMC/58cFA5dI6NJOUgDArZEAAAwYAAwYADBgADBgAMCAAcCAAQAD
BgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMG
AAMGAAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYA
AwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgAD
BgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMG
AAwYAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYA
DBgADBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYADBgADBgADBgAMCAAcCAAQADBgADBgAM
GAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwY
AAwYADBgADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAwYAAwYAAwYADAgAHAgAEAAwYAAwYADBgA
DBgAMGAAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYADBgADBgADBgAMCAAcCAAQADBgADBgAMGAAM
GAAwYAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwY
ADBgADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAwYAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgA
MGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAw
YAAwYADAgAHAgAHAgAEAAwYAAwYADBgADBgAMGAAMGAAwIABwIABwIABAAMGAAMGAAwYAAwYADBg
ADBgAMCAAcCAAcCAAQADBgADBgAMGAAMGAAwYAAwYADAgAHAgAHAgCUAAAMGAAMGAAwYAAwYADBg
ADBgAMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAA
MGAAwIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAw
YADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBg
AMCAAcCAAQADBgADBgADBgAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYAAwYADBgADBgAMGAAMGAA
wIABwIABAAMGAAMGAAMGAAwYAAwYADBgADBgAMCAAcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADA
gAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIABwIABAAMGAAMGAAwYAAwYAAwYADBgADBgAMCA
AcCAAQADBgADBgAMGAAMGAAMGAAwYAAwYADAgAHAgAEAAwYAAwYADBgADBgADBgAMGAAMGAAwIAB
wIABAAMGAAMGAAwYAAwYAAwYADBgAPhrAUkcBh1kyqz7AAAAAElFTkSuQmCC") as $key => $val)
		if( ! assert('$call_result["data"]["'.$key.'"] == "'.addcslashes($val, '"').'";')) {
			var_dump($call_result["data"][$key]);
			for($i = 0; $i < strlen($call_result["data"][$key]); $i++) {
				$char1 = $call_result["data"][$key][$i]; $char2 = $val[$i];
				if($char1 != $char2) 
					var_dump("$i: ".$char1." [".ord($char1)."] != ".$char2." [".ord($char2)."]");
			}
		}
	assert("\$call_result['data_tracking'] == array('key()' => 'Номенклатура2139'); /* ".print_r($call_result['data_tracking'], true).' */');
	assert('$call_result["cursor_vars"] == array("source_seekable" => true); /* '.print_r($call_result['cursor_vars'], true).' */');

	// промежуточные переменные курсора перенесём в предыдущий data_tracking
	if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
		$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
		unset($call_result['cursor_vars']);
	}
	
	// --- NEXT #7
	echo "<h3>--- _cursor_asLineDocument(next) #7 ---</h3>";
	$call_result = _cursor_asLineDocument(array($uni_result), 0, 'next');
// var_dump($call_result);
	assert('$call_result == array("cursor_vars" => array("source_seekable" => true));');
	
	global $GLOBALS_data_types, $GLOBALS_data_tracking, $GLOBALS_data_timestamp;
	$GLOBALS['test_ldoc'] = & $uni_result['data'];
	$GLOBALS_data_types['test_ldoc'] = & $uni_result['data_type'];
	$GLOBALS_data_tracking['test_ldoc'] = & $uni_result['data_tracking'];
	$GLOBALS_data_timestamp['test_ldoc'] = time();
	
	// --- /test_ldoc
	echo "<h3>--- /test_ldoc ---</h3>";
	$result = uni('/test_ldoc');
// var_dump($result);
	assert("\$result == \$uni_result['data']; /* ".print_r($result, true)." */");
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/test_ldoc/Номенклатура%i/cache(`test_ldoc1`)";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("is_object(\$result); /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/test_ldoc1/next()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == array(
		"Code" => "ОЦ000010000",
		"ВыгружатьНаСайт" => "ложь",
		"Цены--Краснодар" => "3744",
		"Цены--ИнтернетЦена" => "4640",
		"Цены--Закупочный" => "0",
		"Цены--Безнал" => "4826",
		"Цены--Карта-интернет" => "4733",
		"Цены--Оптовая" => "4593,6",
		"ОстаткиНаСкладе--Сочи" => "0"); /* '.print_r($result, true).' */');
	assert("\$GLOBALS_data_tracking['test_ldoc1']['cursor_vars'] == array('was_rewinded' => true); /* ".print_r($GLOBALS_data_tracking['test_ldoc1'], true).' */');
		
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/test_ldoc1/next()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result["Code"] == "ОЦ000020000"; /* '.print_r($result, true).' */');
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/test_ldoc1/next_with_key()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result[0]["Code"] == "ОЦ000030000"; /* '.print_r($result[0], true).' */');
	assert('$result[1] == "Номенклатура2139"; /* '.print_r($result[1], true).' */');
// $GLOBALS['unipath_debug'] = false;
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/test_ldoc1/next()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == false; /* '.print_r($result, true).' */');
	
	// --- FULL TEST
	$zip = new ZipArchive;
	$zip_open_result = $zip->open('unipath.linedoc.test.zip', ZipArchive::CREATE);
	assert('$zip_open_result !== false;');
	$zip->addFromString('test.ldoc', $test_ldoc_header."\n".$test_ldoc_groups."\n".$test_ldoc_products);
	$zip->close();
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "fs()/./unipath.linedoc.test.zip/asZIPFile()/0/asLineDocument(if_not_seekable=/fs()/./unipath.linedoc.test.buffer/open())/cache('test_ldoc2')";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('is_resource($result); /* '.print_r($result, true).' */');
	assert("\$GLOBALS_data_tracking['test_ldoc2']['cursor()'] == '_cursor_asLineDocument'; /* ".print_r($GLOBALS_data_tracking['test_ldoc2'], true).' */');
	assert("is_resource(\$GLOBALS_data_tracking['test_ldoc2']['cursor_vars']['if_not_seekable']); /* ".print_r($GLOBALS_data_tracking['test_ldoc2'], true).' */');
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = "/test_ldoc2/Группа%i/toHash('Code')";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("is_array(\$result); /* ".print_r($result, true)." */ ");
	assert("array_keys(\$result) == array('УТ0000001', 'УТ0000004'); /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/test_ldoc2/Номенклатура%i/toHash('Code')";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("is_array(\$result); /* ".print_r($result, true)." */ ");
	assert("array_keys(\$result) == array('ОЦ000010000', 'ОЦ000020000', 'ОЦ000030000'); /* ".print_r($result, true)." */ ");

	
}

}