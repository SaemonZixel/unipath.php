<?php

/**
 *  UniPath - XPath like access to DataBase, Files, XML, Arrays and any other data from PHP
 *  
 *  @version: 2.0-dev
 *  @author: Saemon Zixel (saemon-zixel.ru) on 2013-2016 year
 *
 *	UniversalPath (UniPath.php) - универсальный доступ к любым ресурсам
 *  Задумывался как простой, компактный и удобный интерфейс ко всему. Идеологически похож на jQuery и XPath.
 *  Позваляет читать и манипулировать, в том числе и менять, как локальные переменные внутри программы,
 *  так и в файлы на диске, таблицы в базе данных, удалённые ресурсы и даже менять на удалённом сервере 
 *  параметры запущенного приложения или считать запись access.log по определённой дате и подстроке UserAgent и т.д.
 *  Но всё это в светлом будущем:) Сейчас реализованна только маленькая часть.
 *
 *  @license: MIT
 *
 *  Copyright (c) 2013-2016 Saemon Zixel
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software *  and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */

global $GLOBALS_data_types, $GLOBALS_data_tracking, $GLOBALS_data_timestamp, 
       $__uni_prt_cnt, $__uni_benchmark, $__uni_optimeze; // for PHP 5.3 and upper

$GLOBALS_data_types = array(); // unipath-типы некоторых переменных в $GLOBALS
$GLOBALS_data_tracking = array(); // источники некоторых переменных в $GLOBALS
$GLOBALS_data_timestamp = array(); // timestamp если были закешированны

$__uni_prt_cnt = 100000; // лимит интереций циклов, защитный счётчик от бесконечного зацикливания
$__uni_optimeze = 1; // 0 - off; 1 - optimeze: /abc, ./abc, ./`abc`
$__uni_benchmark = array(); // для статистики скорости обработки unipath запросов

// режим присвоения значения
// $__uni_assign_mode = false;

function uni($unipath) {

	if(func_num_args() > 1) {
		$uni_result = __uni_with_start_data(null, null, null, $unipath, func_get_arg(1));
		return $uni_result;
	}
		
	$uni_result = __uni_with_start_data(null, null, null, $unipath);
	
// if(!isset($uni_result['data'])) var_dump("!!!", $uni_result);

	// cursor() - оборачиваем в объект Uni потому, что неизвестно нужен ли будет результат дальше
	if(isset($uni_result['data_tracking']) and isset($uni_result['data_tracking']['cursor()'])) {
		/*$call_result = call_user_func($uni_result['data_tracking']['cursor()'], array($uni_result), 0, 'all');
			
		// промежуточные переменные курсора перенесём в наш data_tracking
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
			$uni_result['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
			unset($call_result['cursor_vars']);
		}*/
		
		// если данных нет, а тип данных ненулевой, то оборачиваем курсор в объект
		if(!isset($uni_result['data']) and !in_array($uni_result['data_type'], array('null', 'NULL')))
			return new Uni($uni_result['data'], $uni_result['data_type'], $uni_result['data_tracking']);
			
		// иначе возращаем скалярное 
		else
			return $uni_result['data'];
	}
			
	return $uni_result['data'];
}

// тот-же uni(), но с указанием стартовых данных
// $data_tracking = array('pos()' => 1, 'key()' => 1, ...)
function __uni_with_start_data($data, $data_type, $data_tracking, $unipath) {

	// возможно, просто просят взять определённое поле без присвоения? - тогда оптимизируем
	if(!empty($GLOBALS['__uni_optimeze']) and func_num_args() < 5 and ($unipath[0] == '/' or !is_array($data_tracking) or empty($data_tracking['cursor()']))) {
		$unipath_len = strlen($unipath);
		
		if(strspn($unipath, 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789') == $unipath_len) 
			$field_name = $unipath;
		elseif($unipath_len > 4 and $unipath[0] == '.' and $unipath[1] == '/'
			and strpos('\'"`', $unipath[2]) !== false 
			and strpos($unipath, $unipath[2], 3)+1 == $unipath_len)
			$field_name = substr($unipath, 3, $unipath_len-4);
		elseif($unipath[0] == '/' and strspn($unipath, 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789', 1) == $unipath_len - 1) {
			$field_name = substr($unipath, 1);
			$data = $data_type = $data_tracking = null;
		}

		if(isset($field_name)) {
		
			// теперь вытаскиваем значение из переданной $data
			if(is_array($data) and array_key_exists($field_name, $data))
				return array('data' => $data[$field_name], 'data_type' => gettype($data[$field_name]), 'data_tracking' => null);

			// если не передали $data, то вытаскиваем из $GLOBALS
			if(is_null($data) and is_null($data_type) and array_key_exists($field_name, $GLOBALS)) {
				global $GLOBALS_data_types, $GLOBALS_data_tracking;
				return array(
					'data' => $GLOBALS[$field_name], 
					'data_type' => isset($GLOBALS_data_types[$field_name]) ? $GLOBALS_data_types[$field_name] : gettype($GLOBALS[$field_name]), 
					'data_tracking' => isset($GLOBALS_data_tracking[$field_name]) ? $GLOBALS_data_tracking[$field_name] : null);
			}
		
			// неоткуда вытаскивать, возврщаем NULL
			return array('data' => null, 'data_type' => 'NULL', 'data_tracking' => null);
		}
	}

	// если включено измерение времени выполнения запроса
	if(!empty($GLOBALS['unipath_benchmark'])) {
		$mark1 = microtime(true);
		
		global $__uni_benchmark;
		isset($__uni_benchmark[$unipath]) or $__uni_benchmark[$unipath] = 0;
	}
	
	// мы в режиме присвоения значения?
	global $__uni_assign_mode;
	$old___uni_assign_mode = $__uni_assign_mode;
	$__uni_assign_mode = func_num_args() > 4;

	// разберём путь в дерево, и укажам какие будут стартовые данные в первом узле дерева
	$tree = __uni_parseUniPath($unipath, $data, $data_type, $data_tracking);

	// выполним дерево
	$tree = __uni_evalUniPath($tree);
	
	// вернём обратно флаг режима присвоения
	$__uni_assign_mode = $old___uni_assign_mode;
	
	// последний узел - результат выполнения
	$lv = count($tree)-1;
	$tree_node =& $tree[$lv];
	
	// надо присвоить значение?
	if(func_num_args() > 4) {
		$set_value = func_get_arg(4);

		// если указанна функция cursor() то через неё меняем значение
		if(isset($tree_node['data_tracking']) and isset($tree_node['data_tracking']['cursor()'])) {

			if(function_exists($tree_node['data_tracking']['cursor()']))
				call_user_func($tree_node['data_tracking']['cursor()'], array($tree_node), 0, 'set', $set_value);
			else
				trigger_error('UniPath: '.$tree_node['data_tracking']['cursor()'].' - not exist! *** ', E_USER_ERROR);
			
			// если включен benchmark
			!empty($GLOBALS['unipath_benchmark']) and $__uni_benchmark[$unipath] = microtime(true) - $mark1;
			
			return $set_value;
		} 

		
		// занесём значение в последний узел
// var_dump("=== $lv ==="); print_r($tree[$lv]);
		$tree[$lv]['data'] = $set_value;
			
		// пройдёмся по дереву к самому началу
		$prev_i = $lv;
		for($i = $lv-1; $i >= 0; $i--) {
	
			$data_tracking = isset($tree[$i]['data_tracking']) ? $tree[$i]['data_tracking'] : array();

			// этот шаг является просто копией другого шага
			// по этому переключаемся на него
			if(isset($data_tracking['this_step_is_copy_of_step'])) {
				if($data_tracking['this_step_is_copy_of_step'] > $i)
					trigger_error('UniPath: this_step_is_copy_of_step > current_lv!', E_USER_ERROR);
// var_dump(" === $i -> ".$data_tracking['this_step_is_copy_of_step'].' ===');
				$i = $data_tracking['this_step_is_copy_of_step'];
// 				$data_tracking = isset($tree[$i]['data_tracking']) ? $tree[$i]['data_tracking'] : array();
			}
// var_dump("=== $i ==="); print_r($tree[$i]);			

			// если это cursor()
			if(isset($data_tracking['cursor()'])) {
				if(function_exists($data_tracking['cursor()']))
					call_user_func($data_tracking['cursor()'], array($tree[$i]), 0, 'set', $tree[$prev_i]['data'], $tree[$prev_i]['data_type'], $tree[$prev_i]['data_tracking']);
				else
					trigger_error('UniPath: '.$data_tracking['cursor()'].' - not exist! *** ', E_USER_ERROR);
					
				break; // прерываем цикл, обработчик cursor() дальше сам справился
			}
			
			// array[key()] = ...
			elseif(isset($tree[$prev_i]['data_tracking']) and isset($tree[$prev_i]['data_tracking']['key()'])) {
// var_dump('key = '.$tree[$prev_i]['data_tracking']['key()']);
				$tree[$i]['data'][$tree[$prev_i]['data_tracking']['key()']] = $tree[$prev_i]['data'];
			}
			
			// простой тип данных
			else /* if(!is_array($tree[$i]['data'])) */
				$tree[$i]['data'] = $tree[$prev_i]['data'];
				
			
		/*	if(isset($tree[$i]['data_tracking']) and $tree[$i]['data_tracking'] != '$GLOBALS')
				if($tree[$i]['data_tracking'][0] == '$') {
					$GLOBALS[substr($tree[$i]['data_tracking'], 1)] = $tree[$i]['data'];
					continue;
				} */
// var_dump("new value", $tree[$i]['data']);

			$prev_i = $i; // запомним текущий обработанный узел/шаг как предыдущий
		}
		
		// если включен benchmark
		!empty($GLOBALS['unipath_benchmark']) and $__uni_benchmark[$unipath] = microtime(true) - $mark1;
		
// var_dump('=== RESULT ===', $tree[max($i, 0)]);
		// закончили присвоение
		return $set_value;
	}
	
	// надо получить значение/результат
/*	else {
		if(isset($tree_node['data_tracking']) and isset($tree_node['data_tracking']['cursor()'])) {
			$call_result = call_user_func($tree_node['data_tracking']['cursor()'], $tree, $lv, 'all');
			
			// промежуточные переменные курсора перенесём в наш data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$tree[$lv]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			$tree[$lv]['data'] = $call_result['data'];
			$tree[$lv]['data_type'] = $call_result['data_type'];
			if(isset($call_result['data_tracking']))
				$tree[$lv]['data_tracking'] = $call_result['data_tracking'];
		}
	} */

	// если включен benchmark
	isset($GLOBALS['unipath_benchmark']) and $__uni_benchmark[$unipath] += microtime(true) - $mark1;
	
	if(array_key_exists('data', $tree_node) == false) {
		trigger_error("UniPath: no data to return!\n".print_r($tree_node,true));
		return array('data' => null, 'data_type' => 'null');
	}
	
	return $tree_node;
}

// удобная функция для вызова __uni_* функций без подготовки $tree
function __uni_func($func_name, $data, $data_type = null) {
	if(function_exists("_uni_{$func_name}") == false) {
		trigger_error('UniPath: '.__FUNCTION__.": _uni_{$func_name} does not exist!", E_USER_ERROR);
		return null;
	}
		
	$result = call_user_func("_uni_{$func_name}", array(
		array('name' => '?start_data?', 'data' => $data, 'data_type' => $data_type), 
		array('name' => $func_name.'()', 'data' => null)), 
	1);
	
	return isset($result['data']) ? $result['data'] : null;
}

class Uni extends ArrayIterator {
	public $tree;
	public $last_tree_node;
	public $current_cursor_result; // для cursor

	function Uni($unipath_or_data, $data_type = null, $data_tracking = null) {
	
		// если передали данные, то обернём их в объект
		if(func_num_args() > 1) {
			$this->tree = array(
				array(
					'name' => '', 
					'data' => $unipath_or_data,
					'data_type' => $data_type)
			);
			if(func_num_args() > 2)
				$this->tree[0]['data_tracking'] = $data_tracking;
		} 
		
		// если передали путь, то выполним его
		else {
			// разберём путь в дерево
			$this->tree = __uni_parseUniPath($unipath_or_data);
		
			// выполним дерево
			$this->tree = __uni_evalUniPath($this->tree);
		}
		
		// будем работать с последним узлом т.к. там конечный рзультат
		$this->last_tree_node =& $this->tree[count($this->tree)-1];
// print_r($this->last_tree_node);
	}
	
	function rewind() { 
// var_dump('Uni: rewind');
		// cursor()
		if(isset($this->last_tree_node['data_tracking']) and isset($this->last_tree_node['data_tracking']['cursor()'])) {
			$call_result = call_user_func($this->last_tree_node['data_tracking']['cursor()'], array($this->last_tree_node), 0, 'rewind');
			
			// промежуточные переменные курсора перенесём в наш data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$this->last_tree_node['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			return $call_result;
		}
		
		// normal data
		if(is_array($this->last_tree_node['data']))
			return reset($this->last_tree_node['data']);
		
		return true;
	}
	
	function current() { 

		// cursor()
		if(isset($this->current_cursor_result['data_tracking']) and isset($this->current_cursor_result['data_tracking']['cursor()'])) {
// var_dump('Uni: current: cursor()');
			return new Uni(
				$this->current_cursor_result['data'], 
				$this->current_cursor_result['data_type'], 
				$this->current_cursor_result['data_tracking']);
		}
		
		// normal data
		$tmp = $this->last_tree_node['data'];
		return new Uni($tmp, gettype($tmp));
	}
	
	function key() { 

		// cursor()
		if(isset($this->last_tree_node['data_tracking']) and isset($this->last_tree_node['data_tracking']['cursor()'])) {
			return $this->current_cursor_result['data_tracking']['key()'];
		}
		
		// normal data
		if(is_array($this->last_tree_node['data']))
			return key($this->last_tree_node['data']);
		else
			return 0;
	}
	
	function next() { 
		// cursor()
		if(isset($this->last_tree_node['data_tracking']) and isset($this->last_tree_node['data_tracking']['cursor()'])) {
			/* skip */
		}
	
		if(is_array($this->last_tree_node['data']))
			return next($this->last_tree_node['data']);
		else
			return false;
	}
	
	function valid() { 
	
		// cursor() - сразу возмём следующую строку
		if(isset($this->last_tree_node['data_tracking']) and isset($this->last_tree_node['data_tracking']['cursor()'])) {
			$call_result = call_user_func(
				$this->last_tree_node['data_tracking']['cursor()'], 
				array($this->last_tree_node), 
				0, 'next');
			
			// промежуточные переменные курсора перенесём в data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$this->last_tree_node['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			$this->current_cursor_result = $call_result;
			
			return !empty($call_result);
		}
		
		// normal data
		if(is_array($this->last_tree_node['data']))
			$key = key($this->last_tree_node['data']);
		else
			$key = false;
			
		return ($key !== NULL && $key !== FALSE); 
	}
	
	function count() { 
	
		// cursor()
		if(isset($this->last_tree_node['data_tracking']) and isset($this->last_tree_node['data_tracking']['cursor()'])) {
			trigger_error('UniPath: cursor() not countable!');
			return 0;
		}
	
		// normal data
		if(is_array($this->last_tree_node['data']))
			return count($this->last_tree_node['data']);
		else
			return 1;
	}
	
	function offsetExists($offset_as_unipath) { return true; }
	function offsetUnset($offset_as_unipath) { return true; }
	
	function offsetSet($offset_as_unipath, $set_value) {
		/* Not implemented... */
// 		trigger_error("Not implemented! Uni->offsetSet('$offset_as_unipath') = ...", E_USER_NOTICE);
		
		return __uni_with_start_data(
			$this->last_tree_node['data'], 
			$this->last_tree_node['data_type'],
			array_key_exists('data_tracking', $this->last_tree_node) ? $this->last_tree_node['data_tracking'] : null,
			$offset_as_unipath,
			$set_value
			);
	}
	
	function offsetGet($offset_as_unipath) {
		return __uni_with_start_data(
			$this->last_tree_node['data'], 
			$this->last_tree_node['data_type'],
			array_key_exists('data_tracking', $this->last_tree_node) ? $this->last_tree_node['data_tracking'] : null,
			$offset_as_unipath
			);
	}
}

// главная функция (сердце UniPath)
function __uni_evalUniPath($tree) {
	global $GLOBALS_data_types, $GLOBALS_data_tracking, $GLOBALS_data_timestamp;

if(!empty($GLOBALS['unipath_debug'])) echo "\n**** ".$tree[count($tree)-1]['unipath']." ****\n";
	for($lv = 1; $lv < count($tree); $lv++) {
		$name = isset($tree[$lv]['name']) ? strval($tree[$lv]['name']) : '';
		$filter = empty($tree[$lv]['filter']) ? array() : $tree[$lv]['filter'];
		$prev_data_type = $lv > 0 && isset($tree[$lv-1]['data_type']) ? $tree[$lv-1]['data_type'] : '';

if(!empty($GLOBALS['unipath_debug'])) echo "<br>--- $lv ---<br>".print_r($tree[$lv], true);

		// *** cursor() ***
		if($lv > 0 and isset($tree[$lv-1]['data_tracking'], $tree[$lv-1]['data_tracking']['cursor()'])) {
			$call_result = call_user_func($tree[$lv-1]['data_tracking']['cursor()'], $tree, $lv-1, 'eval', $tree[$lv]);

			// промежуточные переменные курсора перенесём в наш data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			// если ответ пришёл нормальный, то переходем к следующему узлу
			if(is_array($call_result)) {
				$tree[$lv] = array_merge($tree[$lv], $call_result);
				continue; 
			} else {
// var_dump("*** cursor(): ", $call_result);
			}
		}
		
		// data_type должен быть обязательно
		if($lv > 0 and !isset($tree[$lv-1]['data_type']) and empty($current_tree_node_already_evaluted))
			trigger_error("UniPath: no data_type set on step #$lv! \n".print_r($tree[$lv-1], true), E_USER_ERROR);

		// начинаем обрабатывать step
/*		if(empty($name) and $lv == 0) {
// 			var_dump('absolute path start...'); 
		}*/
		
		// <PDO-object/odbc-link/mysql-link>/<table_name>[...]
		if($lv > 0 and is_string($name) 
			and in_array($prev_data_type, array('object/PDO', 'resource/odbc-link', 'resource/mysql-link')) 
			and strpos($name, 'last_error(') === false
			and strpos($name, 'last_affected_rows(') === false
			and strpos($name, 'last_insert_id(') === false) {
			
				$db = $tree[$lv-1]['data'];
				$data_tracking = array('where' => array(), 'tables' => array());
				
				// FROM ... LEFT JOIN ... WHERE ...
				$sql_join = "";
				$sql_where = "";
				$sql_from_binds = array(); $sql_join_binds = array();
				for($i = 0; $i < 10; $i++) {
					$suffix = $i ? "_$i" : "";
					if(isset($tree[$lv]["name$suffix"]) == false) break;
					
					$filter = isset($tree[$lv]["filter$suffix"]) 
						? $tree[$lv]["filter$suffix"]
						: array();
					$expr = isset($filter['start_expr']) ? $filter['start_expr'] : 'expr1';

					// SQL-expression
					while($expr && isset($filter[$expr])) {
						// left
						if(isset($filter[$expr]['left']))
						switch($filter[$expr]['left_type']) {
							case 'string':
								switch($prev_data_type) {
									case 'object/PDO':
										$filter[$expr]['left_sql'] = $db->quote($filter[$expr]['left']);
										break;
									case 'resource/mysql-link':
										$filter[$expr]['left_sql'] = "'".mysql_real_escape_string($filter[$expr]['left'])."'";
										break;
									case 'resource/odbc-link':
									default:
										$filter[$expr]['left_sql'] = "'".str_replace("'","''",$filter[$expr]['left'])."'";
								}
/*								if(empty($sql_join))
									$sql_from_binds[] = $filter[$expr]['left'];
								else
									$sql_join_binds[] = $filter[$expr]['left']; */
								break;
							case 'number':
								$filter[$expr]['left_sql'] = $filter[$expr]['left'];
								break;
							case 'expr':
								$filter[$expr]['left_sql'] = $filter[$filter[$expr]['left']]['sql'];
								break;
							case 'function':
								if(substr_compare($filter[$expr]['left'], 'like(', 0, 5, true) == 0) {
									$filter[$expr]['left_sql'] = preg_replace("~like\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['left']);
								} elseif(substr_compare($filter[$expr]['left'], 'ilike(', 0, 6, true) == 0) {
									$filter[$expr]['left_sql'] = preg_replace("~ilike\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 ILIKE $2'$3'", $filter[$expr]['left']);
								} elseif(substr_compare($filter[$expr]['left'], 'like2(', 0, 5, true) == 0) {
									$filter[$expr]['left_sql'] = iconv('UTF-8', 'WINDOWS-1251', preg_replace("~like2\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['left']));
								} else
									$filter[$expr]['left_sql'] = $filter[$expr]['left'];
								break;
							case 'name':
								$filter[$expr]['left_sql'] = $filter[$expr]['left'];
								break;
						}
						
						// right
						if(isset($filter[$expr]['right_type']))
						switch($filter[$expr]['right_type']) { 
							case 'string':
								switch($prev_data_type) {
									case 'object/PDO':
										$filter[$expr]['right_sql'] = $db->quote($filter[$expr]['right']);
										break;
									case 'resource/mysql-link':
										$filter[$expr]['right_sql'] = "'".mysql_real_escape_string($filter[$expr]['right'])."'";
										break;
									case 'resource/odbc-link':
									default:
										$filter[$expr]['right_sql'] = "'".str_replace("'","''",$filter[$expr]['right'])."'";
								}
/*								if(empty($sql_join))
									$sql_from_binds[] = $filter[$expr]['right'];
								else
									$sql_join_binds[] = $filter[$expr]['right']; */
								break;
							case 'number':
								$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'list_of_numbers':
								$filter[$expr]['right_sql'] = "(".implode(',',$filter[$expr]['right']).")";
								$filter[$expr]['op'] = 'IN';
								break;
							case 'list_of_strings':
								switch($prev_data_type) {
									case 'object/PDO':
										$filter[$expr]['right_sql'] = "(".implode(', ',
											array_map(array($db, 'quote'), 
											$filter[$expr]['right'])).")";
										break;
									case 'resource/mysql-link':
										$filter[$expr]['right_sql'] = "('".implode("','",
											array_map('mysql_real_escape_string', 
											$filter[$expr]['right']))."')";
										break;
									case 'resource/odbc-link':
									default:
										$filter[$expr]['right_sql'] = "('".implode(', ',
											array_map(create_function('$a', 'retrun str_replace("\'","\'\'",$a);'),
											$filter[$expr]['right']))."')";
								}
								$filter[$expr]['op'] = 'IN';
								break;
							case 'expr':
								$filter[$expr]['right_sql'] = $filter[$filter[$expr]['right']]['sql'];
								break;
							case 'function':
								if(substr_compare($filter[$expr]['right'], 'like', 0, 4, true) == 0) {
									$filter[$expr]['right_sql'] = preg_replace("~like2?\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['right']);
								} elseif(substr_compare($filter[$expr]['right'], 'ilike(', 0, 6, true) == 0) {
									$filter[$expr]['right_sql'] = preg_replace("~ilike\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 ILIKE $2'$3'", $filter[$expr]['right']);
								} elseif(substr_compare($filter[$expr]['right'], 'sql_query(', 0, 10, true) == 0) {
									list($args, $args_types) = __uni_parseFunc($filter[$expr]['right']);
									$filter[$expr]['right_sql'] = '('.$args['arg1'].')';
									$filter[$expr]['op'] = 'IN';
								} else/*if(substr_compare($filter[$expr]['right'], 'like2(', 0, 5, true) == 0) {
									$filter[$expr]['right_sql'] = iconv('UTF-8', 'WINDOWS-1251', preg_replace("~like2\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['right']));
								} else*/
									$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'name':
								if(in_array($filter[$expr]['right'], array('NULL', 'Null', 'null'))) {
									$filter[$expr]['right_sql'] = 'NULL';
									$filter[$expr]['op'] = $filter[$expr]['op'] == '=' ? 'IS' : 'IS NOT';
								} else
									$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'unipath':
								$value = uni($filter[$expr]['right']);
								if(is_string($value))
								switch($prev_data_type) {
									case 'object/PDO':
										$filter[$expr]['right_sql'] = $db->quote($value);
										break;
									case 'resource/mysql-link':
										$filter[$expr]['right_sql'] = "'".mysql_real_escape_string($value)."'";
										break;
									case 'resource/odbc-link':
									default:
										$filter[$expr]['right_sql'] = "'".str_replace("'","''",$value)."'";
								}
								elseif(is_array($value)) {
									$filter[$expr]['right_sql'] = array();
									foreach($value as $val)
									switch($prev_data_type) {
										case 'object/PDO':
											$filter[$expr]['right_sql'][] = $db->quote($val);
											break;
										case 'resource/mysql-link':
											$filter[$expr]['right_sql'] = "'".mysql_real_escape_string($value)."'";
											break;
										case 'resource/odbc-link':
										default:
											$filter[$expr]['right_sql'][] = "'".str_replace("'","''",$val)."'";
									}
									$filter[$expr]['right_sql'] = implode(',', $filter[$expr]['right_sql']);
								} 
								else
									$filter[$expr]['right_sql'] = strval($value);
								break;
						}
						
						// op
						if(isset($filter[$expr]['sql']) == false) {
							$open_braket = isset($filter[$expr]['open_braket']) ? '(' : '';
							$close_braket = isset($filter[$expr]['close_braket']) ? ')' : '';
							
							if(!isset($filter[$expr]['left'])) 
								$filter[$expr]['sql'] = '';
							else if(isset($filter[$expr]['op'])) {
								$filter[$expr]['sql'] = "{$filter[$expr]['left_sql']} {$filter[$expr]['op']} $open_braket{$filter[$expr]['right_sql']}$close_braket";
								
								if(!isset($filter[$expr]['right_sql'])) 
									error_log('UniPath: right_sql is not set in filter! --  '.print_r($filter, true), E_USER_ERROR);
							} else
								$filter[$expr]['sql'] = $filter[$expr]['left_sql'];
							
							// вытащим из фильтров значения определённых полей (хоть что-то)
							if(isset($filter[$expr]['op']) 
								and in_array($filter[$expr]['op'], array('=', 'IN'))
								and isset($filter[$expr]['left'], $filter[$expr]['right']) 
								and $filter[$expr]['left_type'] == 'name'
// 								and in_array($filter[$expr]['right_type'], array('string', 'number'))
								) {
									$data_tracking['where'][$filter[$expr]['left']] = $filter[$expr]['op'].' '.$filter[$expr]['right_sql'];
							}
						}
						
						// next
						$last_expr = $expr;
						$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
					}
					
					// alias()
					if(strlen($tree[$lv]["name$suffix"]) >= 6 and substr_compare($tree[$lv]["name$suffix"], 'alias(', 0, 6, true) === 0) {
						list($args, $args_types) = __uni_parseFunc($tree[$lv]["name$suffix"]);
						$tbl_name = $args['arg1'].' AS '.$args['arg2'];
/*						$tbl_name = rtrim(substr($tree[$lv]["name$suffix"], 6), "'\") ");
						$commar_pos = strrpos($tbl_name, ',');
						$alias_name = ltrim(substr($tbl_name, $commar_pos+1), "'\" ");
						$tbl_name = trim(substr($tree[$lv]["name$suffix"], 6, $commar_pos-1), $tbl_name[0]." ") . " AS $alias_name";*/
						
						// иак-же добавим таблицу в список используемых таблиц
						in_array($args['arg1'], $data_tracking['tables']) 
							or $data_tracking['tables'][] = $args['arg1'];
							
						// ... а алиас в карту алиасов-таблиц
						if(isset($data_tracking['table_aliases']))
							$data_tracking['table_aliases'][$args['arg2']] = $args['arg1'];
						else
							$data_tracking['table_aliases'] = array($args['arg2'] => $args['arg1']);
					} 
					
					// table1
					else {
						$tbl_name = $tree[$lv]["name$suffix"];
						
						// так-же добавим таблицу в список используемых таблиц
						in_array($tbl_name, $data_tracking['tables']) 
							or $data_tracking['tables'][] = $tbl_name;
					}
				
					// FROM ... / JOIN ...
					if(empty($suffix)) {
						$sql_join = $tbl_name; // FROM ...
						$sql_where = (isset($last_expr) and !empty($filter[$last_expr]['sql'])) ? "WHERE ".$filter[$last_expr]['sql'] : "";
					} else
					if(empty($filter[$last_expr]['sql']))
						$sql_join .= " NATURAL JOIN $tbl_name";
					else
						$sql_join .= " LEFT JOIN $tbl_name ON ".$filter[$last_expr]['sql'];
				}
				
				// SELECT ... GROUP BY ... ORDER BY ... LIMIT...
				$sql_select = "*";
				$sql_group_by = "";
				$sql_order_by = "";
				$sql_limit = "";
				$correct_lv = $lv;
				for($i = $lv+1; $i < count($tree); $i++) {
					if(strlen($tree[$i]['name']) >= 9 and substr_compare($tree[$i]['name'], 'order_by(', 0, 9, true) == 0) {
						/*$arg1 = trim(substr($tree[$i]['name'], 9, -1), ' ');
						$char = empty($arg1) ? '' : $arg1[0];
						$arg1 = in_array($char, array('"', "'")) 
							? strtr(substr($arg1, 1, -1), array($char.$char => $char))
							: $arg1;*/
						list($args, $args_types) = __uni_parseFunc($tree[$i]['name']);
						foreach($args as $key => $val) {
							if(!empty($sql_order_by))
								$sql_order_by .= ', '.$val;
							else
								$sql_order_by = "ORDER BY $val";
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 9 and substr_compare($tree[$i]['name'], 'group_by(', 0, 9, true) == 0) {
						list($args, $args_types) = __uni_parseFunc($tree[$i]['name']);
						foreach($func_args as $key => $val) {
							if(!empty($sql_group_by))
								$sql_group_by .= ', '.$val;
							else
								$sql_group_by = "GROUP BY $val";
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 8 and substr_compare($tree[$i]['name'], 'columns(', 0, 8, true) == 0) {
						list($args, $args_types) = __uni_parseFunc($tree[$i]['name']);
// var_dump($args, $tree[$i]['name']);
						$sql_select = '';
						$chunked_array = array();
						foreach($args as $key => $arg) {
							if(substr_compare($arg, 'chunked(', 0, 8, true) == 0) {
								list($chunked_args, $chunked_args_types) = __uni_parseFunc($arg);
//print_r($chunked_args);
								$chunked_array[$chunked_args['arg1']] = array();
								$col_name = str_replace('.', '_', $chunked_args['arg1']);
								$chunk_size = isset($chunked_args['arg3']) ? intval($chunked_args['arg3']) : 3000;
								for($ii = 0; $ii < ceil($chunked_args['arg2']/$chunk_size); $ii++) {
									if(isset($chunked_args['arg4']))
										$sql_select .= "CAST(SUBSTRING({$chunked_args['arg1']}, ".($ii*$chunk_size+1).", $chunk_size) as {$chunked_args['arg4']}) AS uni_chunk_{$ii}_$col_name, ";
									else
										$sql_select .= "SUBSTRING({$chunked_args['arg1']}, ".($ii*$chunk_size+1).", $chunk_size) AS uni_chunk_{$ii}_$col_name, ";
									$chunked_array[$chunked_args['arg1']]["uni_chunk_{$ii}_$col_name"] = true;
								}
							} 
							// именованыый аргумент? (название поля = тип поля)
							elseif(sscanf($key, "arg%i", $arg_num) == 0)
								$sql_select .= "$key, ";
							else
								$sql_select .= "$arg, ";
						}
						$sql_select = rtrim($sql_select, ', ');
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 6 and substr_compare($tree[$i]['name'], 'limit(', 0, 6, true) == 0) {
						$sql_limit = "LIMIT ".trim(substr($tree[$i]['name'], 6, -1), ' ');
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 11 and substr_compare($tree[$i]['name'], 'asSQLQuery(', 0, 11, true) == 0) {
						$asSQLQuery = true;
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 10 and substr_compare($tree[$i]['name'], 'sql_iconv(', 0, 10, true) == 0) {
						list($args, $args_types) = __uni_parseFunc($tree[$i]['name']);
						if(isset($args['arg2'])) {
							$iconv_from = $args['arg1'];
							$iconv_to = $args['arg2'];
						} else {
							$iconv_from = $args['arg1'];
							$iconv_to = 'UTF-8';
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 4 and substr_compare($tree[$i]['name'], 'top(', 0, 4, true) == 0) {
						list($args, $args_types) = __uni_parseFunc($tree[$i]['name']);
						$sql_select = "TOP {$args['arg1']} $sql_select";
						$correct_lv++;
					} else
						break;
				}
				
				// конструируем окончательно запрос
				$sql_binds = array_merge($sql_join_binds, $sql_from_binds);
				$sql = rtrim("SELECT $sql_select FROM $sql_join $sql_where $sql_group_by $sql_order_by $sql_limit", ' ');
				
				// ICONV
				if(isset($iconv_from, $iconv_to))
					$sql = iconv($iconv_from, $iconv_to, $sql);

				// выполняем запрос или возвращаем
// var_dump($sql, $sql_binds, isset($asSQLQuery));
				if(isset($asSQLQuery)) {
					$tree[$correct_lv]['data'] = array('query' => $sql, 'params' => $sql_binds);
					$tree[$correct_lv]['data_type'] = "array/sql-query-with-params";
				} elseif($sql == "SELECT * FROM $name" and !isset($tree[$lv]['filter'])) {
					$tree[$correct_lv]['data'] = array($db, $name);
					$tree[$correct_lv]['data_type'] = 'array/db-table';
				} /* elseif($prev_data_type == 'resource/odbc-link') {
					$res = odbc_prepare($db, $sql) or print_r(array(odbc_error($db), odbc_errormsg($db)));
					odbc_setoption($res, 2, 0, 15); // time-out
					odbc_execute($res, $sql_binds) or print_r(array(odbc_error($db), odbc_errormsg($db)));
				} else {
					$res = $db->prepare($sql);
					if($res) $res_execute_result = $res->execute($sql_binds);
					
					// сообщим об ошибке в запросе
					if(!$res or isset($res_execute_result) and !$res_execute_result) {
						$err_info = $db->errorInfo();
						if($err_info[0] == '00000')
							trigger_error("UniPath: PDO: execute() return false! ($sql)", E_USER_NOTICE);
						else
							trigger_error("UniPath: PDO: ".implode(';',$err_info)." ($sql)", E_USER_NOTICE);
					}
				} */
				
				// не выполняем запрос, а сохраняем и возвращаем cursor
				else {
					$tree[$correct_lv]['data'] = null;
					$tree[$correct_lv]['data_type'] = 'cursor/db-rows';
				
					// сгенерируем и положим информацию для присвоения
					$tree[$correct_lv]['data_tracking'] = $data_tracking + array(
						'cursor()' => '__cursor_database',
						'db' => $db,
						'db_type' => $prev_data_type,
						'sql_query' => $sql,
						'columns' => array(),
// 						'tables' => array($tbl_name), // substr($tbl_name, 0, strcspn($tbl_name, ' '))),
						);
				}

				// выбераем каждую строку
// 				if(!empty($res))
// 				while($row = $prev_data_type == 'resource/odbc-link' ? odbc_fetch_array($res) : $res->fetch(PDO::FETCH_ASSOC)) {
// 
// 					// chunked()
// 					if(!empty($chunked_array)) 
// 					foreach($chunked_array as $orig_col_name => $group) {
// 						$row[$orig_col_name] = implode(array_intersect_key($row, $group));
// 						$row = array_diff_key($row, $group);
// 					}
// 				
// 					$tree[$correct_lv]['data'][] = $row;
// 					//$tree[$correct_lv]['data_tracking'][] = "sql_table($name)/row";
// 				};

				// освободим ресурсы/память
/*				if($prev_data_type == 'resource/odbc-link'
					and !empty($res) and is_resource($res)) 
					odbc_free_result($res);
				
				if(empty($tree[$correct_lv]['data'])) {
					$tree[$correct_lv]['data'] = null;
					$tree[$correct_lv]['data_type'] = 'null';
				} elseif(!isset($tree[$correct_lv]['data_type'])) {
					$tree[$correct_lv]['data_type'] = 'array/db-rows';
				};*/
				
				// поправим описание колонок
				if(isset($columns_func_args)) foreach($columns_func_args as $key => $val) {
					if($key == 'func_name' or strpos($key, ':type')) continue;
					if(sscanf($key, 'arg%i', $arg_num) == 1) {
						foreach(explode(",", $val) as $col_name)
							$tree[$correct_lv]['data_tracking']['columns'][trim($col_name)] = '';
					} else
						$tree[$correct_lv]['data_tracking']['columns'][$key] = $val;
				}

				// корректируем уровень (если были /db1/.../order_by()/group_by()/limit()/...)
				$lv = $correct_lv;
		}
		
		// next()
		elseif($name == 'next()') {
			assert('isset($tree[$lv-1]["data_tracking"]);');
			assert('is_array($tree[$lv-1]["data_tracking"]);');
			assert('isset($tree[$lv-1]["data_tracking"]["cursor()"]);') or var_dump($tree[$lv-1]);
			
			$call_result = call_user_func($tree[$lv-1]["data_tracking"]['cursor()'], $tree, $lv-1, 'next');
if(!empty($GLOBALS['unipath_debug'])) var_dump("next().\$call_result => ", $call_result);

			// промежуточные переменные курсора перенесём в предыдущий data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
				
			// если ответ это одна запись, то преобразуем в массив с одной записьмю
			if(is_array($call_result) and !empty($call_result)) {
				$tree[$lv]['data'] = $call_result['data'];
				$tree[$lv]['data_type'] = $call_result['data_type'];
				$tree[$lv]['data_tracking'] = $call_result['data_tracking'];
			}
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			}
		}
		
		// all()
		elseif($name == 'all()' and isset($tree[$lv-1]['data_tracking']['cursor()'])) {
			assert('isset($tree[$lv-1]["data_tracking"]);');
			assert('is_array($tree[$lv-1]["data_tracking"]);');
			assert('isset($tree[$lv-1]["data_tracking"]["cursor()"]);');
			
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = 'array';
			$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			$data_tracking = $tree[$lv-1]["data_tracking"];
			
			// REWIND - перематаем в начало если это курсор
			$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'rewind');

			// промежуточные переменные курсора перенесём в предыдущий data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			global $__uni_prt_cnt;
			for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

				// NEXT
				$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next', $__uni_prt_cnt);

				// промежуточные переменные курсора перенесём в предыдущий data_tracking
				if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
					$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
					unset($call_result['cursor_vars']);
				}

				// если ответ - это одна запись, то преобразуем в массив с одной записью
				if(is_array($call_result) and isset($call_result['data'], $call_result['data_type'])) {
					$tree[$lv]['data'][] = $call_result['data'];
				} 
				
				// если ответ это набор записей next(NNN)
				elseif(is_array($call_result) and !empty($call_result)) {
					$tree[$lv]['data_type'] = $call_result['data_type'];
					$tree[$lv]['data_tracking'] = $call_result['data_tracking'];
					unset($call_result['data_type'], $call_result['data_tracking']);
					$tree[$lv]['data'] = $call_result;
				} 
				
				// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
				else
					break;
			}
		}
		
		// fs() [local-filesystem]
		elseif($name == 'file://' or $name == 'fs()') {
			$tree[$lv]['data'] = 'file://';
			$tree[$lv]['data_type'] = 'string/local-filesystem';
			$tree[$lv]['data_tracking'] = array('key()' => 'file://');
		}
				
		// [local-filesystem] start
		elseif($prev_data_type == 'string/local-filesystem') {
			if($name == '~') {
				$tree[$lv]['data'] = realpath('~');
				$tree[$lv]['data_type'] = 'string/local-directory';
				$tree[$lv]['data_tracking'] = array('url' => 'file://'.$tree[$lv]['data'], 'key()' => $name);
			} else
			if($name == '') {
				$tree[$lv]['data'] = '';
				$tree[$lv]['data_type'] = 'string/local-directory';
				$tree[$lv]['data_tracking'] = array('url' => 'file://', 'key()' => $name);
			} else
			if($name == '.') {
				$tree[$lv]['data'] = realpath('.');
				$tree[$lv]['data_type'] = 'string/local-directory';
				$tree[$lv]['data_tracking'] = array('url' => 'file://'.$tree[$lv]['data'], 'key()' => $name);
			} else {
				$path = realpath('.') . '/' . $name;
				$tree[$lv]['data'] = $path;
				$tree[$lv]['data_tracking'] = array('url' => 'file://'.$path, 'key()' => $name);

				if(file_exists($path)) {
					if(is_dir($path))
						$tree[$lv]['data_type'] = 'string/local-directory';
					else
					if(is_file($path) or is_link($path)) {
						$tree[$lv]['data_type'] = 'string/local-pathname';
						$tree[$lv]['data_tracking']['cursor()'] = '_cursor_asFile';
					} else
						$tree[$lv]['data_type'] = 'string/local-entry';
				}
			};
		}
			
		// [string/local-directory, string/local-entry]
		elseif(strpos($name, '(') === false and in_array($prev_data_type, array('string/local-directory', 'string/local-entry'))) {
			if($name == '.') $path = realpath($tree[$lv-1]['data'].'/'.$name);
			else $path = $tree[$lv-1]['data'].'/'.$name;

			$tree[$lv]['data'] = $path;
			$tree[$lv]['data_tracking'] = array('url' => 'file://'.$path, 'key()' => $name);

			// пока-что незнаем что это
			$tree[$lv]['data_type'] = 'string/local-entry';
			$tree[$lv]['data_tracking']['cursor()'] = '_cursor_asFile';
			
			if(file_exists($path)) {
				if(is_dir($path)) {
					$tree[$lv]['data_type'] = 'string/local-directory';
					unset($tree[$lv]['data_tracking']['cursor()']);
				} else
				if(is_file($path) or is_link($path)) {
// 					if(isset($tree[$lv+1]) and $tree[$lv+1]['name'] == 'asImageFile()') {
						$tree[$lv]['data'] = $path;
						$tree[$lv]['data_type'] = 'string/local-pathname';
						$tree[$lv]['data_tracking']['cursor()'] = '_cursor_asFile';
// 					} else {
// 						$tree[$lv]['data'] = file_get_contents($path);
// 						$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
// 					}
				}
			};
		}
			
		// _uni_<name>()
		elseif(strpos($name, '(') != false and sscanf($name, '%[^(]', $src_name)
			and function_exists("_uni_{$src_name}")) {
			$tree[$lv] = array_merge($tree[$lv], call_user_func("_uni_{$src_name}", $tree, $lv));
		}
		
		// php_*()
		elseif(strpos($name, '(') !== false and strncmp($name, 'php_', 4) == 0) {
			$func_name = substr($name, 4, strpos($name, '(')-4);
			list($args, $args_types) = __uni_parseFunc($name);
			if(empty($args))
				$args = array($tree[$lv-1]['data']);
			else {
				for($i=0; $i < count($args); $i++)
					if($args[$i] == '.') 
						$args[$i] = $tree[$lv-1]['data'];
					elseif($args_types[$i] == 'unipath')
						$args[$i] = __uni_with_start_data(
							$tree[$lv-1]['data'],
							$tree[$lv-1]['data_type'],
							isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null,
							$args[$i]
						);
			}
			$tree[$lv]['data'] = call_user_func_array($func_name, $args);
			$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
		}
		
		// .[]/...%s...[] - повторная фильтрация данных с шаблоном ключя или без
		elseif($name == '.' or strpos($name, '%') !== false /* or is_numeric($name) */) {
			
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			$data_tracking = isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : array();

			// вернём себя как курсор если предыдущий cursor()
			if(isset($data_tracking['cursor()'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'cursor' . (strpos($tree[$lv]['data_type'], '/') !== false 
					? substr($tree[$lv]['data_type'], strpos($tree[$lv]['data_type'], '/'))
					: '');
				$tree[$lv]['data_tracking'] = array(
					'cursor()' => '__cursor_filtration', 
					'cursor_vars' => array('was_rewinded' => false), // ещё не перемотан источник
					'name' => $tree[$lv]['name'],
					'filter' => isset($tree[$lv]['filter']) ? $tree[$lv]['filter'] : null,
					'tree_node' => & $tree[$lv-1]);
				continue;
			}
			
			// REWIND - перематаем в начало если это курсор
			/*if(isset($data_tracking['cursor()'])) {
				$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'rewind');

				// промежуточные переменные курсора перенесём в предыдущий data_tracking
				if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
					$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
					unset($call_result['cursor_vars']);
				}
				
				$tree[$lv]['data_tracking'] = & $tree[$lv-1]['data_tracking'];
			}*/

			if(strpos($name, '%') !== false)
				$sscanf_format = $name;
			
			global $__uni_prt_cnt;
			for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
			
				// если предыдушие данные надо получать через cursor()
				/* if(isset($data_tracking['cursor()'])) {
			
					$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next', 10);
// var_dump($call_result);
					// промежуточные переменные курсора перенесём в предыдущий data_tracking
					if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
						$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
						unset($call_result['cursor_vars']);
					}
				
					// если ответ это одна запись, то преобразуем в массив с одной записью
					if(is_array($call_result) and isset($call_result['data'], $call_result['data_type'])) {
						$to_filter = array($call_result['data_tracking']['key()'] => $call_result['data']);
					} 
				
					// если ответ это набор записей next(10)
					elseif(is_array($call_result) and !empty($call_result)) {
						$to_filter = $call_result;
					} 
					
					// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
					else
						break;
				} 
				
				// стандартный массив данных
				else*/ if(!isset($to_filter) and is_array($tree[$lv-1]['data']))
					$to_filter = $tree[$lv-1]['data'];
		
				// string, number...
				elseif(!isset($to_filter) and !is_array($tree[$lv-1]['data'])) {
					$key_for_not_array = (isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking']['key()'])) 
						? $tree[$lv-1]['data_tracking']['key()'] 
						: 0;
					$to_filter = array($key_for_not_array => $tree[$lv-1]['data']);
				}
		
				// всё обработали - прерываем
				else
					break;

				// теперь фильтруем
				if(is_array($to_filter))
				foreach($to_filter as $key => $data) {

				// если указана sscanf-маска то проверяем ей сначало ключ
				if(isset($sscanf_format)) {
					$found = sscanf($key, $sscanf_format);
					if(is_null($found) or is_null($found[0]))
						continue;
				}
				
				// возможно фильтра нет и фильтровать не надо
				if(empty($tree[$lv]['filter'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('no filter - PASS');
					$tree[$lv]['data'][$key] = $data;
					continue;
				}
				
				$expr = $tree[$lv]['filter']['start_expr'];
				$filter = $tree[$lv]['filter'];
				while($expr && isset($filter[$expr])) {
					// left
					switch($filter[$expr]['left_type']) {
						case 'unipath':
							$left_result = __uni_with_start_data($data, gettype($data), array('key()' => $key), $filter[$expr]['left']);
							$left_result = $left_result['data'];
							break;
						case 'expr':
//if(!isset($filter[$filter[$expr]['left']]['result'])) print_r($filter);
							$left_result = $filter[$filter[$expr]['left']]['result'];
							break;
						case 'name':
							if(in_array($filter[$expr]['left'], array('null', 'NULL')))
								$left_result = null;
							else
								$left_result = isset($data[$filter[$expr]['left']]) ? $data[$filter[$expr]['left']] : null;
							break;
						case 'function':
							if($filter[$expr]['left'] == 'key()') {
								/* if(isset($tree[$lv-1]['data_tracking']) 
								and is_array($tree[$lv-1]['data_tracking']) 
								and isset($tree[$lv-1]['data_tracking']['key()'])) {
									$left_result = $tree[$lv-1]['data_tracking']['key()'];
								} else */
									$left_result = $key;
							} elseif(strncmp($filter[$expr]['left'], 'like(', 5) == 0) {
								list($args, $args_types) = __uni_parseFunc($filter[$expr]['left']);
								if($args_types['arg1'] == 'unipath')
									$left_result = __uni_with_start_data($data, gettype($data), null, $filter[$expr]['left']);
								else
									$left_result = $filter[$expr]['left'];
//var_dump($left_result, trim($func_and_args['arg2'], '%'), strpos($left_result, trim($func_and_args['arg2'], '%')));
								$left_result = strpos($left_result, trim($args['arg2'], '%')) !== false;
							}
							break;
						case 'dot':
							$left_result = $data;
							break;
						case 'string':
						case 'number':
						default:
							$left_result = $filter[$expr]['left'];
							break;
					}
if(!empty($GLOBALS['unipath_debug'])) $filter[$expr]['left_result'] = $left_result;
					// right
					if(isset($filter[$expr]['right_type']))
					switch($filter[$expr]['right_type']) { 
						case 'unipath':
							$right_result = __uni_with_start_data($data, gettype($data), array('key()' => $key), $filter[$expr]['right']);
							$right_result = $right_result['data'];
							break;
						case 'expr':
							$right_result = $filter[$filter[$expr]['right']]['result'];
							break;
						case 'name':
							if(in_array($filter[$expr]['right'], array('null', 'NULL')))
								$right_result = null;
							else
								$right_result = isset($row[$filter[$expr]['right']]) ? $row[$filter[$expr]['right']] : null;
						case 'list_of_strings':
							$right_result = $filter[$expr]['right'];
							$filter[$expr]['op'] = 'in_right';
							break;
						case 'string':
						case 'number':
						default:
							$right_result = $filter[$expr]['right'];
							break;
					}
if(!empty($GLOBALS['unipath_debug'])) $filter[$expr]['right_result'] = $right_result;
					// op
					if(!isset($filter[$expr]['op']))
						$filter[$expr]['result'] = $left_result;
					else
					switch($filter[$expr]['op']) {
						case '=':
// if(!isset($right_result)) var_dump($tree[$lv]['unipath']);
							if(is_numeric($left_result) and is_numeric($right_result))
								$filter[$expr]['result'] = $left_result == $right_result;
							else
								$filter[$expr]['result'] = $left_result === $right_result;
							break;
						case '<>':
						case '!=':
							$filter[$expr]['result'] = $left_result != $right_result;
							break;
						case 'or':
							$filter[$expr]['result'] = $left_result || $right_result;
							break;
						case 'and':
							$filter[$expr]['result'] = $left_result && $right_result;
							break;
						case '>':
							if(is_numeric($left_result) and is_numeric($right_result)) 
								$filter[$expr]['result'] = $left_result > $right_result;
							else
								$filter[$expr]['result'] = false;
							break;
						case '<':
							if(is_numeric($left_result) and is_numeric($right_result)) 
								$filter[$expr]['result'] = $left_result < $right_result;
							else
								$filter[$expr]['result'] = false;
							break;
						case '<=':
							if(is_numeric($left_result) and is_numeric($right_result)) 
								$filter[$expr]['result'] = $left_result <= $right_result;
							else
								$filter[$expr]['result'] = false;
							break;
						case '>=':
							if(is_numeric($left_result) and is_numeric($right_result)) 
								$filter[$expr]['result'] = $left_result >= $right_result;
							else
								$filter[$expr]['result'] = false;
							break;
						case 'in_right':
var_dump($left_result, $right_result, $tree[$lv]['unipath']);
							$filter[$expr]['result'] = in_array($left_result, $right_result);
							break;
						default:
							$filter[$expr]['result'] = $left_result;
					}

					// next
					$last_expr = $expr;
					$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
				}

if(!empty($GLOBALS['unipath_debug'])) { var_dump("key = $key, filter = ".($filter[$last_expr]['result']?'PASS':'FAIL'), $data); print_r($filter); }
				// если прошёл фильтрацию
				if($filter[$last_expr]['result']) {
					$tree[$lv]['data'][$key] = $data;
				} 
				}
			
				// если обрабатывали не массив, а string, number, resource...
				// то он либо прошёл фильтр, либо нет
				if(isset($key_for_not_array)) {
					if(array_key_exists($key_for_not_array, $tree[$lv]['data'])) {
						$tree[$lv]['data'] = $tree[$lv-1]['data'];
						$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
						$tree[$lv]['data_tracking'] = isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : array();
						if(isset($tree[$lv-1]['data_tracking']['this_step_is_copy_of_step']) == false)
							$tree[$lv]['data_tracking']['this_step_is_copy_of_step'] = $lv-1;
					} 
					else {
						$tree[$lv]['data'] = null;
						$tree[$lv]['data_type'] = 'null';
					}
				}
			} // for(prt_cnt)
		}
		
		// * [array]
		elseif($name == '*' and is_array($tree[$lv-1]['data'])) {
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = 'array';
			foreach($tree[$lv-1]['data'] as $key => $val) {
			foreach($val as $key2 => $val2)
				if(!isset($tree[$lv]['data'][$key2]))
					$tree[$lv]['data'][$key2] = array($key => $val2);
				else
					$tree[$lv]['data'][$key2][$key] = $val2;
			};
		}
		
		// array/field, array/NNN
		elseif(in_array($name, array('', '.', '*')) == false and strpos($name, '(') === false and strpos($name, ':') === false and strpos($name, '%') === false) {
// 			and strncmp($prev_data_type, 'array', 5) == 0 and strpos($name, '(') === false):

			// начало unipath
/*			if($lv == 0) {
			
				// поищем в глобальном пространстве
				if(array_key_exists($name, $GLOBALS)) {
					$tree[$lv]['data'] = $GLOBALS[$name];
				} else
					$tree[$lv]['data'] = null;

				$tree[$lv]['data_type'] = isset($GLOBALS_data_types[$name]) 
					? $GLOBALS_data_types[$name] 
					: gettype($tree[$lv]['data']);
// var_dump($GLOBALS_data_tracking[$name]);
				if(isset($GLOBALS_data_tracking[$name]))
					$tree[$lv]['data_tracking'] =& $GLOBALS_data_tracking[$name];
			}  

			// предыдущий это cursor()
			else */ 
			if(isset($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking']['cursor()'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';

				// REWIND - перематаем в начало если это курсор
				$call_result = call_user_func($tree[$lv-1]['data_tracking']['cursor()'], $tree, $lv-1, 'rewind');

				// промежуточные переменные курсора перенесём в предыдущий data_tracking
				if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
					$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
					unset($call_result['cursor_vars']);
				}
				
				global $__uni_prt_cnt;
				for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
					
					$call_result = call_user_func($tree[$lv-1]['data_tracking']['cursor()'], $tree, $lv-1, 'next', 10);
// if(!empty($GLOBALS['unipath_debug'])) var_dump($call_result);
					// cursor_vars - промежуточные переменные курсора перенесём в предыдущий data_tracking
					if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
						$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
						unset($call_result['cursor_vars']);
					}
					
					// если ответ это одна запись, то преобразуем в массив с одной записьмю
					if(is_array($call_result) and isset($call_result['data'], $call_result['data_type'])) {
						if($call_result['data_tracking']['key()'] == $name) {
							$tree[$lv]['data'] = $call_result['data'];
							$tree[$lv]['data_type'] = $call_result['data_type'];
							$tree[$lv]['data_tracking'] = $call_result['data_tracking'];
							break;
						}
					} 
					
					// если ответ это набор записей next(10)
					elseif(is_array($call_result) and !empty($call_result)) {
						if(array_key_exists($name, $call_result)) {
							$tree[$lv]['data'] = $call_result[$name];
							$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
							if(isset($call_result['data_tracking'])
							&& isset($call_result['data_tracking']['each_data_tracking'])
							&& isset($call_result['data_tracking']['each_data_tracking'][$name]))
								$tree[$lv]['data_tracking'] = $call_result['data_tracking']['each_data_tracking'][$name];
							else
								$tree[$lv]['data_tracking'] = array('key()' => $name);
							break;
						}
					} 
					
					// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
					else
						break;
				}
			} 
			
			// просто array()
			elseif(is_array($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = array_key_exists($name, $tree[$lv-1]['data']) ? $tree[$lv-1]['data'][$name] : null;
				if($tree[$lv-1]['name'] == '?start_data?' and $tree[$lv-1]['data'] == $GLOBALS) {
					$tree[$lv]['data_type'] = isset($GLOBALS_data_types[$name]) ? $GLOBALS_data_types[$name] : gettype($tree[$lv]['data']);
					if(isset($GLOBALS_data_tracking[$name]))
						$tree[$lv]['data_tracking'] = & $GLOBALS_data_tracking[$name];
					else
						$tree[$lv]['data_tracking'] = array('key()' => $name);
				} else {
					$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
					$tree[$lv]['data_tracking'] = array('key()' => $name);
				}
			} 
			
			// неизвестно что и поле не можем взять -> null
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
				$tree[$lv]['data_tracking'] = array('key()' => $name);
// 				$tree[$lv]['data_tracking']['this_step_is_copy_of_step'] = $lv-1;
			};
			
			switch($tree[$lv]['data_type']) {
				case 'object':
					$tree[$lv]['data_type'] = "object/".get_class($tree[$lv]['data']);
					break;
				case 'resource':
					$tree[$lv]['data_type'] = "resource/".str_replace(' ', '-', get_resource_type($tree[$lv]['data']));
					break;
			};
			
			// незабываем про data_tracking
			//$tree[$lv]['data_tracking'] = $name;
		
			// filter1
			if(!empty($filter) and $tree[$lv]['data_type'] == 'array') {
				$result = array();
			
				// фильтруем каждый эллемент массива
				for(reset($tree[$lv]['data']); list($key, $val) = each($tree[$lv]['data']);) {

					if(!isset($filter['expr1']))
						$filter_pass = true;
					else
						switch(isset($filter['expr1']['op']) ? $filter['expr1']['op'] : '') {
							case '=':
								if($filter['expr1']['left_type'] == 'dot'
									and $tree[$lv]['data'][$key] == $filter['expr1']['right']) {
										$filter_pass = true;
								} elseif(is_array($tree[$lv]['data'][$key]) 
									and array_key_exists($filter['expr1']['left'], $tree[$lv]['data'][$key])
									and strval($tree[$lv]['data'][$key][$filter['expr1']['left']]) == $filter['expr1']['right']) {
										$filter_pass = true;
								} else
									$filter_pass = false;
								break;
							case '<>':
							case '!=':
								if(is_array($tree[$lv]['data'][$key]) 
									and array_key_exists($filter['expr1']['left'], $tree[$lv]['data'][$key])
									and strval($tree[$lv]['data'][$key][$filter['expr1']['left']]) != $filter['expr1']['right']) {
										$filter_pass = true;
								} else
									$filter_pass = false;
								break;
							default:
								$filter_pass = false;
						}
						
					// если подходит под фильтр
					if($filter_pass)
						$result[] = $tree[$lv]['data'][$key];
				}
				
				$tree[$lv]['data'] = $result;
			};
			
		}
		
		// если не понятно что делать, тогда просто копируем данные
		else {
			$tree[$lv]['data'] = $lv > 0 ? $tree[$lv-1]['data'] : array();
			$tree[$lv]['data_type'] = $lv > 0 ? $tree[$lv-1]['data_type'] : 'array';
			if($lv > 0 && isset($tree[$lv-1]['data_tracking']))
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			else
				$tree[$lv]['data_tracking'] = array();
			$tree[$lv]['data_tracking']['this_step_is_copy_of_step'] = $lv-1;
		};
		
		// сохраним для отладки
if(!empty($GLOBALS['unipath_debug'])) {
		if(!empty($filter)) $tree[$lv]['filter'] = $filter;
		$GLOBALS['unipath_last_tree'] = $tree;
}
		
	} // for($lv = ...)

	// закончился unipath?
	if(isset($tree[$lv+1]) == false) { 
if(!empty($GLOBALS['unipath_debug'])) { 
	if($tree[0]['data'] == $GLOBALS) {
		unset($tree[0]['data']); // & <- !!!
		$tree[0]['data'] = '*** $GLOBALS ***';
		print_r($tree);
		$tree[0]['data'] = & $GLOBALS;
	} else	
		print_r($tree);
}
		return $tree;
	} 
	
	// обработка xpath была прервана?
	trigger_error("UniPath: evaluation interrupted on step #$lv! (".$tree[$lv-1]['unipath'].")", E_USER_ERROR);
	return $tree;
}

function __uni_parseUniPath($xpath = '', $start_data = null, $start_data_type = null, $start_data_tracking = null) {

		// временно переключим mbstring.func_overload в 1-байтовую кодировку
		if(ini_get('mbstring.func_overload') > 1) {
			$mbstring_internal_encoding = ini_get('mbstring.internal_encoding');
			ini_set('mbstring.internal_encoding', 'ISO-8859-1');
		}

		$tree = array();
		$suffix = '';
		$p = 0;

		// временно для удобства
		if($xpath[0] == '$') $xpath[0] = '/';
		
		// абсалютный путь - стартовые данные это $GLOBALS
		// для относительного, если не передали стартовые данные, то тоже $GLOBALS
		if($xpath[0] == '/' or is_null($start_data) and is_null($start_data_type) and is_null($start_data_tracking))
			$tree[] = array('name' => '?start_data?', 'data' => &$GLOBALS, 'data_type' => 'array', 'unipath' => ''); 
			
		// относительный путь - стартовые данные берём, которые передали
		else
			$tree[] = array('name' => '?start_data?', 
				'data' => $start_data, 
				'data_type' => isset($start_data_type) ? $start_data_type : gettype($start_data), 
				'data_tracking' => $start_data_tracking, 
				'unipath' => '');
		
		// если первым указан протокол, то распарсим его заранее
		if(sscanf($xpath, '%[qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789]:%2[/]', $scheme, $trailing_component) == 2) {
			$scheme = strtolower($scheme);
			switch($scheme) {
				case 'file':
					$tree[] = array('name' => 'file://', /*'data' => 'file://', 'data_type' => 'string/local-filesystem', 'data_tracking' => array('key()' => 'file://'),*/ 'unipath' => 'file://'); 
					break;
				/*case 'http':
					$url = strpos($xpath, "??/") > 0 ? substr($xpath, 0, strpos($xpath, "??/")) : $xpath;
					$tree[] = array('name' => $url, 'unipath' => $url, 'data' => $url, 'data_type' => 'string/url', 'data_tracking' => array('key()' => $url));
					break;*/
				default:
					$tree[] = array('name' => "$scheme://", 'unipath' => "$scheme://"); 
			}
// 			$tree[] = array('name' => null);
			$p = strlen($scheme) + 3;
		} 
		
		// если относительный путь, то создадим сразу новый узел
		if($xpath[0] != '/') {
			$tree[] = array('name' => null);
		} 

		while($p < strlen($xpath)) {
		
			// новая ось
			if($xpath[$p] == '/') {
			
				// укажем путь предыдушего уровня пути
				if(!empty($tree))
					$tree[count($tree)-1]['unipath'] = substr($xpath, 0, $p);
					
				$tree[] = array( 'name' => null );
				$suffix = '';
				$p++;
					
				continue;
			} 
			
			// разделитель (+,,)
			if($xpath[$p] == '+' or $xpath[$p] == ',') {
				for($i = 1; $i < 10; $i++)
					if(!isset($tree[count($tree)-1]["separator_$i"])) {
						$suffix = "_$i";
						break;
					}
				$tree[count($tree)-1]['separator'.$suffix] = $xpath[$p];
				$p++;
				
				continue;
			}
			
			// названия поля/оси
			// strpos('qwertyuiopasdfghjklzxcvbnm_*@0123456789.$', strtolower($xpath[$p]))
			if(strpos("\\|/,+[](){}?!~`'\";#^&=- \n\t", $xpath[$p]) === false) {
				$start_p = $p;
				$len = strcspn($xpath, "\\|/,+[](){}?!~`'\";#^&= \t\n", $start_p);
				$tree[count($tree)-1]['name'.$suffix] = substr($xpath, $start_p, $len);
				$p += $len;

				// может это function() ?
				if(isset($xpath[$p]) and $xpath[$p] == '(') {
					$inner_brakets = 1; $inner_string = false;
					while($inner_brakets > 0) {
						$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
						if(!isset($xpath[$p])) throw new Exception("UniPath: __uni_parseUniPath(): Not found close braket in $xpath!");
						if($xpath[$p] == "'") $inner_string = !$inner_string;
						if($xpath[$p] == '(' and !$inner_string) $inner_brakets++;
						if($xpath[$p] == ')' and !$inner_string) $inner_brakets--;
					}
					$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
				}
				
				// Кастыль: возможно это название файла со скобками
				/* while(isset($xpath[$p]) and !in_array($xpath[$p], array('/', '[', "\n", "\t")))
					$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++]; */
				
				continue;
			}
			
			// [] фильтрация
			if($xpath[$p] == '[') {
				$p++; // [
								
				// разбираем фильтр
				$filter = array('start_expr' => 'expr1', 'expr1' => array() );
				$next_expr_num = 2;
				$expr = 'expr1';
				$expr_key = 'left';
				while($p < strlen($xpath) and $xpath[$p] != ']') {
					while(strpos(" \n\t", $xpath[$p]) !== false) $p++;
//print_r(array(substr($xpath, 0, $p), $filter));
					// до конца фильтрации были пробелы?
					if($xpath[$p] == ']') continue;
					
					// (
					if($xpath[$p] == '(') {
						$filter[$expr]['open_braket'] = true;
						$p++;
						continue;
					}
					
					// )
					if($xpath[$p] == ')') {
						$filter[$expr]['close_braket'] = true;
						$p++;
						continue;
					}
					
					// +,-
					if($xpath[$p] == '+' or ($xpath[$p] == '-'
						and isset($xpath[$p+1]) and strpos('0123456789', $xpath[$p+1]) === false)) {
						$op = $xpath[$p++];
						
						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}

						// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
						$old_expr = $expr;
						while(in_array($filter[$old_expr]['op'], array('*','div','mod','+','-')) and empty($filter[$old_expr]['open_braket']))
							if( empty($filter[$old_expr]['next']) ) break;
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','div','mod','+','-')) 
						    and empty($filter[$old_expr]['open_braket'])) {
							$expr = 'expr'.($next_expr_num++);
							$filter[$expr] = array(
								'left' => $old_expr,
								'left_type' => 'expr',
								'op' => $op,
								'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null
							);
							$filter[$old_expr]['next'] = $expr;
						} 
						
						// отбираем правую часть
						else {
							$expr = 'expr'.($next_expr_num++);

							// пред. звено -> [мы]
							if($filter[$old_expr]['left_type'] == 'expr') {
								/*if($filter['start_expr'] == $filter[$filter[$old_expr]['left']]['next'])
									$filter['start_expr'] = $expr;*/
								$filter[$filter[$old_expr]['left']]['next'] = $expr;
								
							}
							
							// [мы]
							$filter[$expr] = array(
								'left' => $filter[$old_expr]['right'],
								'left_type' => $filter[$old_expr]['right_type'],
								'op' => $op,
								'next' => $old_expr // [мы] -> (старое) теущее звено
							);
							
							// right = [мы]
							$filter[$old_expr]['right'] = $expr;
							$filter[$old_expr]['right_type'] = 'expr';
							
							// если мы вклиниваемся, то и начало цепочки корректируем
							if($filter['start_expr'] == $old_expr)
									$filter['start_expr'] = $expr;
						}
						
						continue;
					};
					
					// *,div,mod
					if($xpath[$p] == '*' or stripos($xpath, 'div ', $p) == $p or stripos($xpath, 'mod ', $p) == $p) {
						$op = $xpath[$p] == '*' ? '*' : substr($xpath, $p, 3);
						$p += $xpath[$p] == '*' ? 1 : 3;

						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}
						
						if($expr_key == 'right') {
						
							// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
							$old_expr = $expr;
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod'))
								or isset($filter[$old_expr]['close_braket']))
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];

							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod'))
								or isset($filter[$old_expr]['close_braket'])) {
								$expr = 'expr'.($next_expr_num++);
								$filter[$expr] = array(
									'left' => $old_expr,
									'left_type' => 'expr',
									'op' => $op,
									'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null
								);
								$filter[$old_expr]['next'] = $expr;
							} 
							
							// отбираем правую часть (вклиниваемся между звеньями)
							else {
								$expr = 'expr'.($next_expr_num++);

								// пред. звено -> [мы]
								if($filter[$old_expr]['right_type'] == 'expr')
									$filter[$filter[$old_expr]['right']]['next'] = $expr;
								else
								if($filter[$old_expr]['left_type'] == 'expr')
									$filter[$filter[$old_expr]['left']]['next'] = $expr;
									
								// [мы]
								$filter[$expr] = array(
									'left' => $filter[$old_expr]['right'],
									'left_type' => $filter[$old_expr]['right_type'],
									'op' => $op,
									'next' => $old_expr // [мы] -> (старое) теущее звено
								);
								
								// right = [мы]
								$filter[$old_expr]['right'] = $expr;
								$filter[$old_expr]['right_type'] = 'expr';

								// если мы вклиниваемся, то и начало цепочки корректируем
								if($filter['start_expr'] == $old_expr)
									$filter['start_expr'] = $expr;
							}
						
							continue;
						}
					};
					
					// and, or
					if(stripos($xpath, 'and ', $p) == $p or stripos($xpath, 'or ', $p-1) == $p) {
						$op = $xpath[$p] == 'a' ? 'and' : 'or';
						$p += $xpath[$p] == 'a' ? 3 : 2;

						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}

						// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
						$old_expr = $expr;
						while(in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', '<>', '!=', 'and', 'or')) 
							and empty($filter[$old_expr]['open_braket']))
							if( empty($filter[$old_expr]['next']) ) break;
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', 'and', 'or'))
							and empty($filter[$old_expr]['open_braket'])) {
							$expr = 'expr'.($next_expr_num++);
							$filter[$expr] = array(
								'left' => $old_expr,
								'left_type' => 'expr',
								'op' => $op,
								'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null
							);
							$filter[$old_expr]['next'] = $expr;
						} 
						
						// отбираем правую часть
						else {
							$expr = 'expr'.($next_expr_num++);
//var_dump("$expr: $old_expr");
							// пред. звено -> [мы]
							if($filter[$old_expr]['right_type'] == 'expr')
								$filter[$filter[$old_expr]['right']]['next'] = $expr;
							elseif($filter[$old_expr]['left_type'] == 'expr')
									$filter[$filter[$old_expr]['left']]['next'] = $expr;
							
							// [мы]
							$filter[$expr] = array(
								'left' => $filter[$old_expr]['right'],
								'left_type' => $filter[$old_expr]['right_type'],
								'op' => $op,
								'next' => $old_expr // [мы] -> (старое) теущее звено
							);
							
							// right = [мы]
							$filter[$old_expr]['right'] = $expr;
							$filter[$old_expr]['right_type'] = 'expr';
						}
						
						continue;
					};
					
					// =, >, <, <=, >=, <>, !=
					if($xpath[$p] == '=' or $xpath[$p] == '>' or $xpath[$p] == '<' or $xpath[$p] == '!') {
						$op = $xpath[$p++];
						if($xpath[$p] == '=' or $xpath[$p] == '>') $op .= $xpath[$p++];
							
						// стандартное начало выражения
						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}
						
						// продолжение цепочки выражения
						if($expr_key == 'right') {
							
							// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
							$old_expr = $expr;
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!=')) and empty($filter[$old_expr]['open_braket']))
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];
//var_dump("$expr: $old_expr");
							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!=')) and empty($filter[$old_expr]['open_braket'])) {
								$expr = 'expr'.($next_expr_num++);
								$filter[$expr] = array(
									'left' => $old_expr,
									'left_type' => 'expr',
									'op' => $op,
									'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null
								);
								$filter[$old_expr]['next'] = $expr;
							} 
							
							// отбираем правую часть (вклиниваемся между звеньями)
							else {
								$expr = 'expr'.($next_expr_num++);

								// пред. звено -> [мы]
								/*if($filter[$old_expr]['left_type'] == 'expr')
									$filter[$filter[$old_expr]['left']]['next'] = $expr;*/
								foreach($filter as $key => $val)
									if(isset($val['next']) and $val['next'] == $old_expr) {
										$filter[$key]['next'] = $expr;
										break;
									}
									
								// кастыль ...(...
/*								if(isset($filter[$old_expr]['next']) 
									and isset($filter[$filter[$old_expr]['next']]['open_braket']))
									$filter[$filter[$filter[$old_expr]['next']]['left']]['next'] = $expr;*/
								
								// [мы]
								$filter[$expr] = array(
									'left' => $filter[$old_expr]['right'],
									'left_type' => $filter[$old_expr]['right_type'],
									'op' => $op,
									'next' => $old_expr // [мы] -> (старое) теущее звено
								);
								
								// right = [мы]
								$filter[$old_expr]['right'] = $expr;
								$filter[$old_expr]['right_type'] = 'expr';

//var_dump("$old_expr.next = ".$filter[$old_expr]['next']);
							}
						
							continue;
						}
					};
				
					// название поля
					if(strpos('@qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_', $xpath[$p]) !== false) {
						$start_p = $p;
						while(strpos('@qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789-.', $xpath[$p]) !== false) 
							$p++;
						$filter[$expr][$expr_key] = substr($xpath, $start_p, $p - $start_p); 
						$filter[$expr][$expr_key.'_type'] = 'name';
						
						// возможно это func()
						if(isset($xpath[$p]) and $xpath[$p] == '(') {
							while($xpath[$p] != ')')
								$filter[$expr][$expr_key] .= $xpath[$p++];
							$filter[$expr][$expr_key] .= $xpath[$p++];
							$filter[$expr][$expr_key.'_type'] = 'function';
						}
				
						continue;
					}

					// число
					if(strpos('0123456789', $xpath[$p]) !== false or ($xpath[$p] == '-' 
						and isset($xpath[$p+1]) and strpos('0123456789', $xpath[$p+1]) !== false )) {
						$len = strspn($xpath, '0123456789-.', $p);
						$val = substr($xpath, $p, $len);
						$p += $len;
						
						$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'number';

						// возможно это список чисел
						if($xpath[$p] == ',') {
							$filter[$expr][$expr_key.'_type'] = 'list_of_numbers';
							$filter[$expr][$expr_key] = array($filter[$expr][$expr_key]);
							$p++;
							
							$len = strspn($xpath, '0123456789,', $p);
							$filter[$expr][$expr_key] = array_merge($filter[$expr][$expr_key], explode(',', substr($xpath, $p, $len)));
							$p += $len;
						}
							
						continue;
					}
					
					// строка
					if($xpath[$p] == "'" or $xpath[$p] == '"' or $xpath[$p] == '`') {
						$start_p = $p++;
						$p = strcspn($xpath, $xpath[$start_p], $p);
						$val = substr($xpath, $start_p+1, $p);
						$p = $start_p + $p + 2;

						$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'string';
						
						// возможно это список строк
						$space = strspn($xpath, " \t\n", $p);
						if($xpath[$p+$space] == ',') {
							$filter[$expr][$expr_key.'_type'] = 'list_of_strings';
							$filter[$expr][$expr_key] = array($filter[$expr][$expr_key]);
							$p += $space+1;

							$space = strspn($xpath, " \t\n", $p);
							while(isset($xpath[$p+$space]) 
								and strpos("\"'`", $xpath[$p+$space]) !== false) {
								// начало, конец, строку в список, передвинем указатель
								$start_p = $p + $space;
								$p = strcspn($xpath, $xpath[$start_p], $start_p+1);
								$filter[$expr][$expr_key][] = substr($xpath, $start_p+1, $p);
								$p = $start_p + $p + 2;
								
								$space = strspn($xpath, " \t\n", $p);
								if($xpath[$p+$space] != ',') break;
								$space += strspn($xpath, " \t\n", $p+$space+1) + 1;
							}
						}
						continue;
					}
					
					// текущий элемент
					if($xpath[$p] == '.') {
						$p++;
						$filter[$expr][$expr_key] = '.';
						$filter[$expr][$expr_key.'_type'] = 'dot';
						continue;
					}
					
					// вложенный unipath
					if($xpath[$p] == '/') {
						$start_p = $p;
					
						$flag_string = false;
						while(!in_array($xpath[$p], array(' ', ']', "\n", '=')) and $p < strlen($xpath)) {
// echo "$p = $xpath[$p]\n";
							// грязный пропуск строк
							if(strpos(' "\'`', $xpath[$p]) > 0)
								for($p++; $p < strlen($xpath) and strpos(' "\'`', $xpath[$p]) === false; $p++);
							$p++;
						}
						
						$val = substr($xpath, $start_p, $p - $start_p);

						if(isset($filter[$expr][$expr_key]))
							$filter[$expr][$expr_key] .= $val;
						else
							$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'unipath';

						continue;
					}
					
					$p++; // непонятно что -> пропустим
				}
				$p++; // ]
				
				if(isset($tree[count($tree)-1]['filter'.$suffix]))
					$tree[count($tree)-1]['filter2'.$suffix] = $filter;
				else
					$tree[count($tree)-1]['filter'.$suffix] = $filter;
					
				continue;
			} // фильтрация
			
			// `'" строка
			if($xpath[$p] == '`' or $xpath[$p] == '"' or $xpath[$p] == "'") {
				//$start_string_p = $p++;
				switch($xpath[$p]) {
					case '`':
						if(substr_compare($xpath, '```', $p, 3, false) == 0)
							$string_border = '```';
						else
							$string_border = '`';
						break;
					case "'":
					case '"':
						$string_border = $xpath[$p];
				}
				$p += strlen($string_border);
				
				// поищем окончание строки
				$end = strpos($xpath, $string_border, $p);
				$tree[count($tree)-1]['name'.$suffix] = substr($xpath, $p, $end-$p);
				
				// передвинем указатель
				$p = $end + strlen($string_border);
				continue;
			}
			
			$p++; // попробуем пропустить непонятные символы
			
		}; // while($xpath)
		
		// пропишем путь у последнего уровня пути
		if(!empty($tree))
			$tree[count($tree)-1]['unipath'] = $xpath;

		// вернём обратно кодировку mbstring если включена
		if(ini_get('mbstring.func_overload') > 1) {
			ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
		}
			
		return $tree;
}

// вытаскивает список аргументов внутри функции
function __uni_parseFunc($string) {

	// временный кастыль для ifEmpty()
// 	if(strncmp($string, 'ifEmpty(', 8) == 0 and strpos($string, '/') !== false 
// 	  and in_array($string[8], array("'", "`", "\"")) == false)
// 		return array(array('arg1' => substr($string, 8, strlen($string)-9)), 'arg1:type' => 'unipath');
	
	// временно переключим mbstring.func_overload в 1-байтовую кодировку
	if(ini_get('mbstring.func_overload') > 1) {
		$mbstring_internal_encoding = ini_get('mbstring.internal_encoding');
		ini_set('mbstring.internal_encoding', 'ISO-8859-1');
	}
	
	$result = array(); $result_types = array();
	$in_string = $in_binary_string = false; // внутри строки?
	$brakets_level = 0; // уровень вложенности скобок
	$arg_start = 0; $arg_key = 'arg1';
	$mode = ''; $p = 0; $strlen = strlen($string);
	global $__uni_prt_cnt;
	for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
	
		if($in_binary_string)
			$len = strcspn($string, "`", $p);
		elseif($in_string)
			$len = strcspn($string, $in_string, $p);
		else
			$len = strcspn($string, ",()'\"`=", $p);
			
if(!empty($GLOBALS['unipath_debug'])) echo(" --- $mode\n ".substr($string, $p+$len).' -- p='.$p.' brakets_level='.$brakets_level);

		if($len+$p == $strlen) break; // дошли до конца строки
		
		// выделим имя функции
		if($brakets_level == 0 and $string[$p+$len] == '(' and !$in_string and !$in_binary_string) {
// 			$result['func_name'] = ltrim(substr($string, 0, $len));
			$brakets_level++;
			$arg_start = $p = $len+1;
			$mode = 'func_name';
			continue;
		}
		
		// (
		if($string[$p+$len] == '(' and !$in_string and !$in_binary_string) {
			$brakets_level++;
			$p += $len+1;
			$mode = 'open_braket';
			continue;
		}
		
		// )
		if($string[$p+$len] == ')' and !$in_string and !$in_binary_string) {
			$brakets_level--;
			
			if($brakets_level == 0) {
				if(isset($last_string)) {
					$result[$arg_key] = $last_string;
					$result_types[$arg_key] = 'string';
					$last_string = null;
				} elseif($arg_start < $p+$len) {
					$result[$arg_key] = trim(substr($string, $arg_start, $p+$len-$arg_start));
					if($result[$arg_key] == 'null') {
						$result[$arg_key] = NULL;
						$result_types[$arg_key] = 'null';
					} elseif(is_numeric($result[$arg_key]))
						$result_types[$arg_key] = 'number';
					else
						$result_types[$arg_key] = 'unipath';
				}
				
				$p = $arg_start = $p + $len + 1;
				$mode = 'close_braket_end';
			} else {
				$p += $len+1;
				$mode = 'close_braket';
			}
			
			$last_string = null; // на всяк случай
			continue;
		}
		
		// ' - string
		if($string[$p+$len] == "'" and !$in_string and !$in_binary_string) {
			
			$in_string = "'";
			$last_string = '';
			$p += $len+1;
			$mode = 'in_string_start';
			continue;
		}
		
		// '' and string end
		if($string[$p+$len] == "'" and $in_string == "'" and !$in_binary_string) {
			if(substr_compare($string, "''", $p+$len, 2) == 0) {
				$last_string .= substr($string, $p, $len+1);
				$p += $len+1;
				$mode = 'in_string_escp1';
				continue;
			} elseif($p+$len > 0 and $string[$p+$len-1] == "'" and $last_string != '') {
				$p += $len+1;
				$mode = 'in_string_escp2';
				continue;
			} else {
				$last_string .= substr($string, $p, $len);
				$in_string = false;
				$p += $len+1;
				$mode = 'in_string_end';
				continue;
			}
		}
		
		// " - string
		if($string[$p+$len] == '"' and !$in_string and !$in_binary_string) {
			$in_string = '"';
			$last_string = '';
			$p += $len+1;
			$mode = 'in_string_start';
			continue;
		}
		
		// "" and string end
		if($string[$p+$len] == '"' and $in_string == '"' and !$in_binary_string) {
			if(substr_compare($string, '""', $p+$len, 2) == 0) {
				$last_string .= substr($string, $p, $len+1);
				$p += $len+1;
				$mode = 'in_string_escp1';
				continue;
			} elseif($p+$len > 0 and $string[$p+$len-1] == '"' and $last_string != '') {
				$p += $len+1;
				$mode = 'in_string_escp2';
				continue;
			} else {
				$last_string .= substr($string, $p, $len);
				$in_string = false;
				$p += $len+1;
				$mode = 'in_string_end';
				continue;
			}
		}
		
		// ``` - binary-string
		if($string[$p+$len] == "`" and !$in_string and !$in_binary_string) {
			if($strlen >= $p+$len+7 and substr_compare($string, '```````', $p+$len, 7) == 0)
				$in_binary_string = 7;
			elseif($strlen >= $p+$len+3 and substr_compare($string, '`````', $p+$len, 3) == 0)
				$in_binary_string = 3;
			else
				$in_binary_string = 1;
				
			// если был / до строки, то мы внутри unipath!
			$slash_pos = strpos($string, '/', $arg_start);
			$last_string = ($slash_pos !== false && $slash_pos < $p+$len) ? false : '';
			
			$p += $len+$in_binary_string;
			$mode = 'in_binary_string_start';
			continue;
		}
		
		// ``` - binary-string end
		if($string[$p+$len] == "`" and !$in_string and $in_binary_string) {
			$len2 = strspn($string, '`', $p+$len);
			
			// если апострафов больше или столько же сколько открывающих, то это конец строки
			if($len2 >= $in_binary_string) {
				if($last_string === false)
					$last_string = null; // строка была в unipath
				else
					$last_string .= substr($string, $p, $len + ($len2-$in_binary_string));
				$in_binary_string = false;
				$p += $len+$len2;
				$mode = 'in_binary_string_end';
			} 
			
			// в середине встретились апострофы
			else {
				$last_string .= substr($string, $p, $len+$len2);
				$p += $len+$len2;
			}
			
			continue;
		}

		// , - skip
		if($string[$p+$len] == "," and !$in_string and !$in_binary_string and $brakets_level > 1) {
			$p = $p + $len + 1;
			$mode = 'inner_commar_skip';
			continue;
		}
		
		// , - argument
		if($string[$p+$len] == "," and !$in_string and !$in_binary_string and $brakets_level == 1) {
				
			if(isset($last_string)) {
				$result[$arg_key] = $last_string;
				$result_types[$arg_key] = 'string';
				$last_string = null;
			} else {
				$result[$arg_key] = trim(substr($string, $arg_start, $p+$len-$arg_start));
				if($result[$arg_key] == 'null') {
					$result[$arg_key] = NULL;
					$result_types[$arg_key] = 'null';
				} elseif(is_numeric($result[$arg_key]))
					$result_types[$arg_key] = 'number';
				else
					$result_types[$arg_key] = 'unipath';
			}
				
			$p = $arg_start = $p + $len + 1;
			$mode = $arg_key;
			
			// сгенерируем следующий $arg_key
			for($arg_num = 1; $arg_num < 99; $arg_num++)
				if(!isset($result["arg$arg_num"])) { 
					$arg_key = "arg$arg_num";
					break;
				}
			
			continue;
		}

		// = - named argument
		if($string[$p+$len] == "=" and !$in_string and !$in_binary_string and $brakets_level >= 1) {

			// возможно мы внутри фильтра в unipath? или внутри других скобок
// 			$filter_open_pos = strpos($string, '[', $arg_start);
			if($brakets_level > 1 
			|| ($filter_open_pos = strpos($string, '[', $arg_start)) !== false
			&& $filter_open_pos < $p+$len) {
				$p += $len+1;
				$mode = 'skip_named_arg';
				continue;
			}
		
			if(isset($last_string)) {
				$arg_key = $last_string;
				$last_string = null;
			} else {
				$arg_key = trim(substr($string, $arg_start, $p+$len-$arg_start));
			}
			$arg_start = $p+$len+1;
			$p += $len+1;
			$mode = 'named_arg';
			continue;
		}
	}
	
	// вернём обратно кодировку mbstring если включена
	if(ini_get('mbstring.func_overload') > 1) {
		ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
	}
	
	return array($result, $result_types);
}

function __cursor_filtration($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_data_type = null, $cursor_arg1_data_tracking = null) {
// if($cursor_cmd != 'next' or !empty($GLOBALS['unipath_debug']))
// var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:'')); 

	if(!in_array($cursor_cmd, array('rewind', 'next'))) return false;

	$cursor_vars = isset($tree[$lv]['data_tracking']['cursor_vars']) ? $tree[$lv]['data_tracking']['cursor_vars'] : array();
	
	$tree_node = & $tree[$lv]['data_tracking']['tree_node'];
	assert('function_exists($tree_node["data_tracking"]["cursor()"]);') or var_dump($tree_node);
	
	// REWIND - перематаем в начало
	if($cursor_cmd == 'rewind' or ($cursor_cmd == 'next' and empty($cursor_vars['was_rewinded']))) {
					
		$call_result = call_user_func($tree_node['data_tracking']['cursor()'], array($tree_node), 0, 'rewind');

		// промежуточные переменные курсора перенесём в предыдущий data_tracking
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
			$tree_node['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
// 			unset($call_result['cursor_vars']);
		}
		
		$cursor_vars['was_rewinded'] = true;

		// если попросили только перемотать, то вернём результат и наши $cursor_vars
		if($cursor_cmd == 'rewind')
			return array(
				is_array($call_result) ? $call_result[0]: $call_result, 
				'cursor_vars' => $cursor_vars);
	}
	
	// NEXT
	if(strpos($tree[$lv]['data_tracking']['name'], '%') !== false)
		$sscanf_format = $tree[$lv]['data_tracking']['name'];
	
	global $__uni_prt_cnt;
	for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

		$call_result = call_user_func($tree_node['data_tracking']['cursor()'], array($tree_node), 0, 'next'/*, $cursor_arg1*/);
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.".\$call_result => ", $call_result);

		// cursor_vars - промежуточные переменные курсора перенесём в предыдущий data_tracking
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
			$tree_node['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
			unset($call_result['cursor_vars']);
		}
		
		// пустой или отрицательный ответ, возвращаем как есть.
		if(empty($call_result) or !is_array($call_result)) {
			return array('cursor_vars' => $cursor_vars);
		}
		
		// теперь фильтруем
		$key = $call_result['data_tracking']['key()'];
		$data = $call_result['data'];

		// если указана sscanf-маска то проверяем ею сначало ключ
		if(isset($sscanf_format)) {
			$found = sscanf($key, $sscanf_format);

			// если фильтрацию не прошёл - следующий
			if(is_null($found) or is_null($found[0]))
				continue;
		}
				
		// возможно фильтра нет и фильтровать не надо
		if(empty($tree[$lv]['data_tracking']['filter'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('no filter - PASS');
			return array_merge($call_result, array('cursor_vars' => $cursor_vars));
		}
				
		$expr = $tree[$lv]['data_tracking']['filter']['start_expr'];
		$filter = $tree[$lv]['data_tracking']['filter'];
		while($expr && isset($filter[$expr])) {
			// left
			switch($filter[$expr]['left_type']) {
				case 'unipath':
					$left_result = __uni_with_start_data($call_result['data'], $call_result['data_type'], $call_result['data_tracking'], $filter[$expr]['left']);
					$left_result = $left_result['data'];
					break;
				case 'expr':
//if(!isset($filter[$filter[$expr]['left']]['result'])) print_r($filter);
					$left_result = $filter[$filter[$expr]['left']]['result'];
					break;
				case 'name':
					if(in_array($filter[$expr]['left'], array('null', 'NULL')))
						$left_result = null;
					else
						$left_result = isset($data[$filter[$expr]['left']]) ? $data[$filter[$expr]['left']] : null;
					break;
				case 'function':
					if($filter[$expr]['left'] == 'key()') {
						$left_result = $key;
					} elseif(strncmp($filter[$expr]['left'], 'like(', 5) == 0) {
						list($args, $args_types) = __uni_parseFunc($filter[$expr]['left']);
						if($args_types['arg1'] == 'unipath')
							$left_result = __uni_with_start_data($call_result['data'], $call_result['data_type'], $call_result['data_tracking'], $args_types['arg1']);
						else
							$left_result = $filter[$expr]['left'];
						$left_result = strpos($left_result, trim($args['arg2'], '%')) !== false;
					}
					break;
				case 'dot':
					$left_result = $data;
					break;
				case 'string':
				case 'number':
				default:
					$left_result = $filter[$expr]['left'];
					break;
			}
if(!empty($GLOBALS['unipath_debug'])) $filter[$expr]['left_result'] = $left_result;

			// right
			if(isset($filter[$expr]['right_type']))
			switch($filter[$expr]['right_type']) { 
				case 'unipath':
					$right_result = __uni_with_start_data($call_result['data'], $call_result['data_type'], $call_result['data_tracking'], $filter[$expr]['right']);
					$right_result = $right_result['data'];
					break;
				case 'expr':
					$right_result = $filter[$filter[$expr]['right']]['result'];
					break;
				case 'name':
					if(in_array($filter[$expr]['right'], array('null', 'NULL')))
						$right_result = null;
					else
						$right_result = isset($row[$filter[$expr]['right']]) ? $row[$filter[$expr]['right']] : null;
					break;
				case 'list_of_strings':
					$right_result = $filter[$expr]['right'];
					$filter[$expr]['op'] = 'in_right';
					break;
				case 'string':
				case 'number':
				default:
					$right_result = $filter[$expr]['right'];
					break;
			}
if(!empty($GLOBALS['unipath_debug'])) $filter[$expr]['right_result'] = $right_result;

			// op
			if(!isset($filter[$expr]['op']))
				$filter[$expr]['result'] = $left_result;
			else
			switch($filter[$expr]['op']) {
				case '=':
// if(!isset($right_result)) var_dump($tree[$lv]['unipath']);
					if(is_numeric($left_result) and is_numeric($right_result))
						$filter[$expr]['result'] = $left_result == $right_result;
					else
						$filter[$expr]['result'] = $left_result === $right_result;
					break;
				case '<>':
				case '!=':
					$filter[$expr]['result'] = $left_result != $right_result;
					break;
				case 'or':
					$filter[$expr]['result'] = $left_result || $right_result;
					break;
				case 'and':
					$filter[$expr]['result'] = $left_result && $right_result;
					break;
				case '>':
					if(is_numeric($left_result) and is_numeric($right_result)) 
						$filter[$expr]['result'] = $left_result > $right_result;
					else
						$filter[$expr]['result'] = false;
					break;
				case '<':
					if(is_numeric($left_result) and is_numeric($right_result)) 
						$filter[$expr]['result'] = $left_result < $right_result;
					else
						$filter[$expr]['result'] = false;
					break;
				case '<=':
					if(is_numeric($left_result) and is_numeric($right_result)) 
						$filter[$expr]['result'] = $left_result <= $right_result;
					else
						$filter[$expr]['result'] = false;
					break;
				case '>=':
					if(is_numeric($left_result) and is_numeric($right_result)) 
						$filter[$expr]['result'] = $left_result >= $right_result;
					else
						$filter[$expr]['result'] = false;
					break;
				case 'in_right':
// var_dump($left_result, ' - in_right - ', $right_result, $tree[$lv]['unipath']);
					$filter[$expr]['result'] = in_array($left_result, $right_result);
					break;
				default:
					$filter[$expr]['result'] = $left_result;
			}

			// next
			$last_expr = $expr;
			$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
		}

if(!empty($GLOBALS['unipath_debug'])) { var_dump("key = $key, filter = ".($filter[$last_expr]['result']?'PASS':'FAIL'), $data); print_r($filter); }

		// если прошёл фильтрацию
		if($filter[$last_expr]['result']) {
			return array_merge($call_result, array('cursor_vars' => $cursor_vars));
		} 
	}
	
	return false; // ничего не нашлось подходящего, либо закончились данные
}

/* function __cursor_grouping($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_data_type = null, $cursor_arg1_data_tracking = null) {

	if($cursor_cmd == 'eval') return false;
	assert('$tree');
} */

function __cursor_database($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_type = null, $cursor_arg1_tracking = null) {
	global $__uni_assign_mode;

if(/*$cursor_cmd != 'next' or*/ !empty($GLOBALS['unipath_debug']))
var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:''));

	// SET - перенаправим в специальный обработчик
	if($cursor_cmd == 'set')
		return __cursor_database_set($tree, $lv, $cursor_arg1, $cursor_arg1_type, $cursor_arg1_tracking);

	// дополнительная информация о базе хранятся в кеше - она нам пригодится
	if($cursor_cmd == 'rewind' or $cursor_cmd == 'next') {
		if(!isset($GLOBALS['__cursor_database']))
			$GLOBALS['__cursor_database'] = array(array('db' => $tree[$lv]['data_tracking']['db']));
		foreach($GLOBALS['__cursor_database'] as $num => $item) 
			if($item['db'] == $tree[$lv]['data_tracking']['db']) 
				$cache_item =& $GLOBALS['__cursor_database'][$num];
		
		$data_tracking = $tree[$lv]['data_tracking'];
		$cursor_vars = isset($data_tracking['cursor_vars']) ? $cursor_vars = $data_tracking['cursor_vars'] : array();
	}
	
	// REWIND - попросили перемотать для следующих next()
	if($cursor_cmd == 'rewind') {
// var_dump($tree[$lv]);

		$db = $data_tracking['db'];
		$sql_query = $data_tracking['sql_query'];
		
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql_query;
			else
// 				trigger_error('UniPath: '.__FUNCTION__.': '.$sql_upd, E_USER_NOTICE);
				echo 'UniPath: '.__FUNCTION__.': '.$sql_query; // error_reporting(0);
		}
		
		// ещё не выполнянли запрос - выполним
		if(array_key_exists('stmt', $cursor_vars) == false) {
// var_dump('First rewind', $cursor_vars);
			switch($data_tracking['db_type']) {
			case 'object/PDO':
				$res = $db->prepare($sql_query);
// if(!empty($GLOBALS['unipath_debug_sql'])) {
// 				if($GLOBALS['unipath_debug_sql'] == 'simulate')
// 					$res_execute_result = false;
// } else {
				if($res) $res_execute_result = $res->execute(array());
// }
					
				// сообщим об ошибке в запросе
				if(!$res or isset($res_execute_result) and !$res_execute_result) {
					$err_info = $db->errorInfo();
					if($err_info[0] == '00000')
						trigger_error("UniPath: __cursor_database: PDO: execute() return false! ($sql_query)", E_USER_NOTICE);
					else
						trigger_error("UniPath: __cursor_database: PDO: ".implode(';',$err_info)." ($sql_query)", E_USER_NOTICE);
					$cache_item['last_error()'] = $err_info;
				} 
				
				// успешно выполнен запрос
				else {
					$cursor_vars['stmt'] = $res;
					$cursor_vars['stmt_result_rows'] = array();
					$cursor_vars['current_pos'] = 0;
					$cache_item['last_affected_rows()'] = $res->rowCount();
				}
				break;
			case 'resource/mysql-link':
				$res = mysql_query($sql_query, $db);
				if(empty($res)) {
					trigger_error("UniPath: __cursor_database: MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db))." ($sql_query)";
					$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
				} else {
					$cursor_vars['stmt'] = $res;
					$cursor_vars['stmt_result_rows'] = array();
					$cursor_vars['current_pos'] = 0;
					$cache_item['last_affected_rows()'] = mysql_num_rows($res);
				}
				break;
			case 'resource/odbc-link':
				$res = odbc_prepare($db, $sql_query);
				if(empty($res)) {
					trigger_error("UniPath: __cursor_database: ODBC Prepare: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db))." ($sql_query)";
					$cache_item['last_error()'] = array(odbc_error($db), odbc_errormsg($db));
				} else {
// if(!empty($GLOBALS['unipath_debug_sql'])) {
// 					if($GLOBALS['unipath_debug_sql'] == 'simulate')
// 					$res_execute_result = false;
// } else {
					if($res) { 
						odbc_setoption($res, 2, 0, 15); // time-out
						$res_execute_result = odbc_execute($res, $sql_binds);
					}
// }
					if($res and !$res_execute_result) {
						trigger_error("UniPath: __cursor_database: ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db))." ($sql_query)";
						$cache_item['last_error()'] = array(odbc_error($db), odbc_errormsg($db));
					} else {
						$cursor_vars['stmt'] = $res;
						$cursor_vars['stmt_result_rows'] = array();
						$cursor_vars['current_pos'] = 0;
						$cache_item['last_affected_rows()'] = odbc_num_rows($res);
					}
				} 
				break;
			default:
				trigger_error("UniPath: __cursor_database: Didn`t know how-to work with '".gettype($db)."' of type '".(is_resource($db)?get_resource_type($db):(is_object($db)?get_class($db):'unknown'))."'");
			}
			return array(true, 'cursor_vars' => $cursor_vars);
		} 
		
		// запрос уже выполнен, данные выгружены, ресурс освобждён
		elseif(is_null($cursor_vars['stmt'])) {
// var_dump('Second rewind');
			if(isset($cursor_vars['stmt_result_rows']) and is_array($cursor_vars['stmt_result_rows'])) {
// 				reset($cursor_vars['stmt_result_rows']);
				$cursor_vars['current_pos'] = 0;
				return array(true, 'cursor_vars' => $cursor_vars);
			} else {
				trigger_error("UniPath: __cursor_database: stmt_result_rows is not set or not array!", E_USER_NOTICE);
				return array(false, 'cursor_vars' => $cursor_vars);
			}
		} 
		
		// запрос не смог нормально выполниться?
		elseif($cursor_vars['stmt'] == false) {
// var_dump("stmt = ", $tree[$lv]['data_tracking']['cursor_vars']['stmt']);
			return array(false, 'cursor_vars' => $cursor_vars);
		} 
		
		// не все данные выгружены, а нас просят перемотать - перемотаем выгруженные тогда
		elseif(!empty($cursor_vars['stmt']) and !empty($cursor_vars['stmt_result_rows'])) {
			$cursor_vars['current_pos'] = 0;
			return array(true, 'cursor_vars' => $cursor_vars);
		}
		
		// данные похоже ещё не выгружали (?)
// 		elseif(!empty($cursor_vars['stmt']) and empty($cursor_vars['stmt_result_rows'])) {
// 			return array(true, 'cursor_vars' => $cursor_vars);
// 		}
		
		else {
			var_dump("UniPath: __cursor_database($cursor_cmd): Unknown what to do... ", $cursor_vars);
			trigger_error("UniPath: __cursor_database($cursor_cmd): Unknown what to do ???");
		}
	}

	// NEXT - попросили следующую строку из результата запроса
	if($cursor_cmd == 'next') {
// var_dump($tree[$lv]);

		if(array_key_exists('stmt', $cursor_vars) == false) 
			trigger_error("UniPath: __cursor_database: SQL Query not executed! Do rewind at first ({$data_tracking['sql_query']})", E_USER_NOTICE);
			
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.": start \$cursor_vars = ", $cursor_vars);

		// STMT_RESULT_ROWS - сначало вытаскиваем из кеша уже выгруженных строк
		if(isset($cursor_vars['stmt_result_rows']) && is_array($cursor_vars['stmt_result_rows'])
		&& count($cursor_vars['stmt_result_rows']) > $cursor_vars['current_pos']) {
		
			// текущая позиция в кеше
			$pos = isset($cursor_vars['current_pos']) ? $cursor_vars['current_pos'] : 0;
			
			$result = array(
				'data_type' => 'array/db-rows',
				'data_tracking' => array(
					'each_data_tracking' => $__uni_assign_mode ? array() : null));
			
			// начнём вытаскивать строки из кеша
			for($i = 0; $i <= intval($cursor_arg1); $i++) {
				if(isset($cursor_vars['stmt_result_rows'][$pos])) {
					$result[$pos] = $cursor_vars['stmt_result_rows'][$pos];
					
					// в режиме пресвоения, сохраним дополнительную информацию
					if($__uni_assign_mode)
						$result['data_tracking']['each_data_tracking'][$pos] = array(
							'key()' => $pos, 'pos()' => $pos, 
							'cursor()' => __FUNCTION__);
						
					$cursor_vars['current_pos'] = ++$pos;
				} else
					break;
			}
			
			// переделаем массив из 1 элемента в один элемент
			if(count($result) == 3) {
				$result = array(
					'data' => $result[$pos-1], 
					'data_type' => 'array/db-row', 
					'data_tracking' => isset($result['data_tracking']['each_data_tracking'])
						? $result['data_tracking']['each_data_tracking'][$pos-1]
						: array(
							'key()' => $pos-1, 'pos()' => $pos-1,
							'cursor()' => __FUNCTION__,
							'db' => &$data_tracking['db'],
							'where' => &$data_tracking['where'],
							'columns' => &$data_tracking['columns'],
							'tables' => &$data_tracking['tables'])
				);
			} 
				
			return array_merge($result, array('cursor_vars' => $cursor_vars));
		} 
		
		// STMT - если результат есть, выбераем следующую строку результата
		if(!empty($cursor_vars['stmt'])) {
		
			$result = array(
				'data_type' => 'array/db-rows',
				'data_tracking' => array(
					'each_data_tracking' => $__uni_assign_mode ? array() : null));
					
			for($i = 0; $i <= intval($cursor_arg1); $i++) {
			
				// все строки выбраны и запрос уничтожен - прекращаем выберать строки
				if($cursor_vars['stmt'] === false or is_null($cursor_vars['stmt'])) 
					break;

				switch($data_tracking['db_type']) {
					case 'object/PDO':
						$row = $cursor_vars['stmt']->fetch(PDO::FETCH_ASSOC);
						// закончились строки, закрываем и освобождаем
						if($row == false) {
							$cursor_vars['stmt']->closeCursor();
							$cursor_vars['stmt'] = null;
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.': cursor_vars[stmt] = ', $cursor_vars['stmt']);
						} 
						break;
					case 'resource/mysql-link':
						$row = mysql_fetch_assoc($cursor_vars['stmt']);
						// закончились строки, закрываем и освобождаем
						if($row == false) {
							mysql_free_result($cursor_vars['stmt']);
							$cursor_vars['stmt'] = null;
						}
						break;
					case 'resource/odbc-link':
						$row = odbc_fetch_array($cursor_vars['stmt']);
						// закончились строки, закрываем и освобождаем
						if($row == false) {
							odbc_free_result($cursor_vars['stmt']);
							$cursor_vars['stmt'] = null;
						}
						break;
					default:
						trigger_error("UniPath: __cursor_database: Result resource has unknown type! ".gettype($res));
				}
				
				// если есть строка, добавляем к себе
				if($row != false) {
					$result[$cursor_vars['current_pos']] = $cursor_vars['stmt_result_rows'][] = $row;
					
					// в режиме пресвоения, сохраним дополнительную информацию
					if($__uni_assign_mode)
						$result['data_tracking']['each_data_tracking'][$cursor_vars['current_pos']] = array(
							'key()' => $cursor_vars['current_pos'], 
							'pos()' => $cursor_vars['current_pos'], 
							'cursor()' => __FUNCTION__,
							'db' => &$data_tracking['db'],
							'where' => &$data_tracking['where'],
							'columns' => &$data_tracking['columns'],
							'tables' => &$data_tracking['tables']);
							
					$cursor_vars['current_pos']++;
				}
			}
			
			// список из 1 элемента превратим в просто один элемент
			if(count($result) == 3) {
				$pos = $cursor_vars['current_pos'];
				$result = array(
					'data' => $result[$pos-1], 
					'data_type' => 'array/db-row', 
					'data_tracking' => isset($result['data_tracking']['each_data_tracking'])
						? $result['data_tracking']['each_data_tracking'][$pos-1]
						: array('key()' => $pos-1, 'pos()' => $pos-1)
					);
			} 
			
// var_dump("result *** ", array_merge($result, array('cursor_vars' => $cursor_vars)));
			return array_merge($result, array('cursor_vars' => $cursor_vars));
		}
		
		// все строки уже были выгружены из результата и запрос закрыт
		elseif(is_null($cursor_vars['stmt'])) {
			return array('cursor_vars' => $cursor_vars);
		}
		
		// запрос был неудачный и выгружать из него невозможно
		elseif($cursor_vars['stmt'] === false) {
			trigger_error('UniPath: __cursor_database: Result set == false!');
			return array('cursor_vars' => $cursor_vars);
		}
		
		// что-то не так
		else {
			trigger_error('UniPath: __cursor_database: nothing to return!');
var_dump(__FUNCTION__.'.cursor_vars = ', $cursor_vars);
			return array('cursor_vars' => $cursor_vars);
		}
			
		
		// normal data
		/*if(is_array($this->last_tree_node['data'])) {
			$result = array(
				'data' => current($this->last_tree_node['data']),
				'data_type' => 'array/db-row',
				'data_tracking' => $this->last_tree_node['data_tracking'] + array(
					'pos()' => key($this->last_tree_node['data']))
			);
			
			// сохраним значения ключевых полей в data_tracking
			foreach($this->last_tree_node['data_tracking']['columns'] as $col_name => $descr) {
				if(stripos($descr, 'KEY') === false) continue;
				
				if(isset($result['data'][$col_name]))
					$result['data_tracking']['where'][$col_name] = $result['data'][$col_name];
				elseif(strpos($col_name, '.') !== false) {
					$key = substr($col_name, strpos($col_name, '.')+1);
					if(isset($result['data'][$key]))
						$result['data_tracking']['where'][$col_name] = $result['data'][$key];
				}
			}
			
			return new Uni($result['data'], $result['data_type'], $result['data_tracking']);
		} */
	}
	
	// EVAL
	if($cursor_cmd == 'eval') {
		if(strpos($cursor_arg1['name'], '(') !== false) return false;
// 		if(strpos($cursor_arg1['name'], 'cache(') !== false) return false;
		if($cursor_arg1['name'] == '.') return false;
		if(is_numeric($cursor_arg1['name'])) return false;
		
		
		// несколько полей/колонок
		if(is_array($tree[$lv]['data']) and isset($cursor_arg1['separator_1'])) {
			$result = array(
				'data' => array(), 
				'data_type' => 'array/db-row', 
				'data_tracking' => $tree[$lv]['data_tracking']);
				
			foreach($tree[$lv] as $key => $val) { 
				if(strncmp($key, 'name', 4) != 0) continue;
				$result['data'][$val] = isset($tree[$lv]['data'][$val]) ? $tree[$lv]['data'][$val] : null;
			}
			
			return $result;
		}

		// одинарное поле
		if(is_array($tree[$lv]['data']) and array_key_exists($cursor_arg1['name'], $tree[$lv]['data'])) {
			$result = array(
				'data' => $tree[$lv]['data'][$cursor_arg1['name']], 
				'data_type' => gettype($tree[$lv]['data'][$cursor_arg1['name']]).'/db-row-value', 
				'data_tracking' => array_merge(
					isset($tree[$lv]['data_tracking']) 
					? $tree[$lv]['data_tracking'] 
					: array(), array('key()' => $cursor_arg1['name'])));
			
			// сохраним значение ключевых колонок в трекинге если было .../columns('id KEY'...)/...
			if(isset($result['data_tracking']['columns'])) 
				$columns = array_filter(
					$result['data_tracking']['columns'], 
					create_function('$a','return stripos($descr, "KEY") !== false;')
				);
				
			// неуказаны были ключевые поля, тогда добавим все колонки как ключевые
			if(empty($columns))
				$columns = $tree[$lv]['data'];

			if(!isset($result['data_tracking']['where'])) 
				$result['data_tracking']['where'] = array();
					
			foreach($columns as $col_name => $unused) {
				
				// предположим, что название колонки без указания таблицы
				if(isset($tree[$lv]['data'][$col_name]))
					$key = $col_name;
				
				// уберём указание таблицы, к которой принадлежит поле для удобства (alias())
				elseif(strpos($col_name, '.') !== false) {
					$key = substr($col_name, strpos($col_name, '.')+1);
					if(isset($tree[$lv]['data'][$key]) == false)
						continue;
				} 
				
				// нет значения для этого поля у нас - пропустим
				else
					continue;
				
				// перенесём в where значение экранировав и поставив оператор
				$result['data_tracking']['where'][$col_name] = "= '".str_replace("'", "''", strval($tree[$lv]['data'][$key]))."'";
			}
			
			return $result;
		}
	}
	
	return null;
}

function __cursor_database_set($tree, $lv, $set_value, $cursor_arg1_data_type = null, $cursor_arg1_data_tracking = null) {
// print_r(array($tree[$lv]['data'], $set_value));
	assert('isset($tree[$lv]["data_tracking"])');
	assert('isset($tree[$lv]["data_tracking"]["db"])');
	assert('isset($tree[$lv]["data_tracking"]["columns"])');
	assert('isset($tree[$lv]["data_tracking"]["where"])');
	
	$data_type = $tree[$lv]['data_type'];
	$data_tracking = $tree[$lv]['data_tracking'];
	$db = $data_tracking['db'];
	$db_type = is_resource($db) 
			? 'resource/'.str_replace(' ', '-', get_resource_type($db))
			: (is_object($db) ? 'object/'.get_class($db) : gettype($db));
	
	// промежуточные результаты хранятся в кеше
	isset($GLOBALS['__cursor_database']) or $GLOBALS['__cursor_database'] = array();
	foreach($GLOBALS['__cursor_database'] as $num => $item) 
		if($item['db'] == $db) 
			$cache_item =& $GLOBALS['__cursor_database'][$num];
	if(!isset($cache_item)) {
		$GLOBALS['__cursor_database'][] = array('db' => $db);
		$cache_item =& $GLOBALS['__cursor_database'][count($GLOBALS['__cursor_database']) - 1];
	}

	// одно поле таблицы меняем
	$names_and_values = array();
	if(strpos($data_type, '/db-row-value') > 0)
		$names_and_values[$tree[$lv]['name']] = $set_value;
	
	// несколько полей меняем
	else
	if(strpos($data_type, '/db-row') > 0)
	foreach($set_value as $key => $val)
		if(is_numeric($key)) { // при присвоении не обязательно указывать название колонок
			isset($col_names) or $col_names = array_keys($tree[$lv]['data']);
			$names_and_values[$col_names[$key]] = $val;
		} else
			$names_and_values[$key] = $val;

	// формируем UPDATE SET ...
	$sql_upd = array();
	foreach($names_and_values as $name => $set_value) {

		$columns = $data_tracking['columns'];
		
		// если нет колонок, то используем ключи из присвояемого массива
		foreach($names_and_values as $col_name => $unused)
			// если использовались алиасы
/*			if(stripos($col_name, ' AS ') !== false) {
				$table_and_alias = explode()
				$columns[$data_tracking['tables'][0].".".$col_name] = '';
			} 
			else */ if(strpos($col_name, '.') === false)
				$columns[$data_tracking['tables'][0].".".$col_name] = '';
			else
				$columns[$col_name] = '';

		// поищем среди описаний колонок наше поле и начнём формировать запрос на обновление поля
		foreach($columns as $col_name => $col_descr) {
			$table_and_col = explode('.', $col_name);
			if(count($table_and_col) == 1) continue;

			// ага нашли!
			if($table_and_col[1] == $name or $col_name == $name) {
				isset($sql_upd_set[$table_and_col[0]]) or $sql_upd_set[$table_and_col[0]] = array();

				// подготовим новое значение
				if(is_array($set_value) or is_object($set_value) or is_resource($set_value)) {
					trigger_error('UniPath: database_set: '.$table_and_col[1].' == '.gettype($set_value).'!!! ('.print_r($set_value, true).')');
				} elseif(is_numeric($set_value)) 
					$sql_upd_set[$table_and_col[0]][$table_and_col[1]] = "$set_value";
				else
				switch($db_type) {
					case 'object/PDO':
						$sql_upd_set[$table_and_col[0]][$table_and_col[1]] = $db->quote($set_value);
						break;
					case 'resource/mysql-link':
						$sql_upd_set[$table_and_col[0]][$table_and_col[1]] = "'".mysql_real_escape_string($set_value)."'";
						break;
					case 'resource/odbc-link':
					default:
						$sql_upd_set[$table_and_col[0]][$table_and_col[1]] = "'".str_replace("'","''",$set_value)."'";
				}
				break;
			}
		}
		
		// не удалось найти описание колонки/поля таблицы в data_tracking
		if(!isset($sql_upd_set[$table_and_col[0]][$table_and_col[1]])) {
			trigger_error("UniPath: not found column description '$name' in data_tracking[columns]! Add .../columns('$name' = '<sql column definition>')/... to your unipath.");
			return;
		}
	}
// var_dump($sql_upd_set, $tree[$lv], $cursor_arg1_data_type, $cursor_arg1_data_tracking);
	
	// теперь добавим WHERE
	$sql_upd_where = array();
	foreach($sql_upd_set as $table => $values) {
		$table_prefix = $table.'.';
		foreach($data_tracking['where'] as $col_name => $val) {

			// если поле относится к нашей таблице, то добавляем к нашему WHERE
			if(strpos($col_name, $table_prefix) === 0 or strpos($col_name, '.') === false) {
				isset($sql_upd_where[$table]) or $sql_upd_where[$table] = array();
			
				/* ! правая часть уже экранирована ! */
				
				// числовые не экранируем
// 				if(is_numeric($val))
					$sql_upd_where[$table][$col_name] = strval($val);
				
				// всё остальное преобразуем в строку и экранируем
/*				else
				switch(gettype($data_tracking['db'])) {
					case 'object':
						if(get_class($data_tracking['db']) == 'PDO') {
							$sql_upd_where[$table][$col_name] = $db->quote(strval($val));
							break;
						}
					default:
						$sql_upd_where[$table][$col_name] = "'".str_replace("'","''",strval($val))."'";
				}*/
			}
		}
		
		if(isset($sql_upd_where[$table]))
		$sql_upd_where[$table] = array(implode(' AND ', array_map(create_function('$a, $b', 'return "$b $a";'), $sql_upd_where[$table], array_keys($sql_upd_where[$table]))));
	}
	
	// возможно лучше использовать реальные строки для WHERE
	if(isset($tree[$lv]['data']) and is_array($tree[$lv]['data'])) {
		if($tree[$lv]['data_type'] == 'array/db-row')
			$rows = array($tree[$lv]['data']);
		else
			$rows = $tree[$lv]['data'];
		foreach($rows as $row) {
			assert('is_array($row)') or var_dump('not array -> ', $row);
			
			// соберём where для выбранной строки
			$sql_where = [];
			foreach($row as $col_name => $val) {
				// числовые не экранируем
				if(is_numeric($val))
					$sql_where[] = $col_name . ' = '. strval($val);
				
				// всё остальное преобразуем в строку и экранируем
				else
				switch($db_type) {
					case 'object/PDO':
						$sql_where[] = $col_name . ' = '. $db->quote(strval($val));
						break;
					case 'resource/mysql-link':
						$sql_where[] = "$col_name = '".mysql_real_escape_string($val)."'";
						break;
					case 'resource/odbc-link':
					default:
						$sql_where[] = "$col_name = '".str_replace("'","''",strval($val))."'";
				}
			}
			
			foreach($sql_upd_set as $table => $values) {
				isset($sql_upd_where[$table]) or $sql_upd_where[$table] = array();
				$sql_upd_where[$table][] = implode(' AND ', $sql_where);
			}
		}
	}
		
		
	// выполним запросы
	foreach($sql_upd_set as $table => $new_values) {
	
		// сформируем конечный запрос
		$sql_upd = "UPDATE $table SET "
			.implode(', ', array_map(create_function('$a, $b', 'return "$b = $a";'), $new_values, array_keys($new_values)))
			.(isset($sql_upd_where[$table]) ? " WHERE ".implode(' OR ',$sql_upd_where[$table]) : '');
			
		// отладка SQL-запросов
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql_upd;
			else
// 				trigger_error('UniPath: '.__FUNCTION__.': '.$sql_upd, E_USER_NOTICE);
				echo 'UniPath: '.__FUNCTION__.': '.$sql_upd; // error_reporting(0);
		}

		$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = null;
		$cache_item['last_error()'] = $cache_item["last_error($table)"] = null;
		
if(function_exists('bench')) bench("database_set(UPDATE)");
		// в зависимости от типа соединения с базой выполним
		switch($db_type) {
			case 'object/PDO':
				$res = $db->prepare($sql_upd);
if(!empty($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
				$res_execute_result = false;
} else {
				if($res) $res_execute_result = $res->execute(array());
}
if(!empty($GLOBALS['unipath_debug_sql']) and !is_array($GLOBALS['unipath_debug_sql'])) var_dump("\$res = ".print_r($res, true).", \$res_execute_result = ".(isset($res_execute_result)?$res_execute_result:'undefined').", rowCount = ".(is_object($res) ? $res->rowCount(): 'NULL'));
				// сообщим об ошибке в запросе
				if(!$res or isset($res_execute_result) and !$res_execute_result) {
					$err_info = $db->errorInfo();

					if($err_info[0] == '00000' and isset($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate')
						/* skip ??? */
						trigger_error("UniPath: PDO: unipath_debug_sql = simulate! ($sql_upd)", E_USER_NOTICE);
					elseif($err_info[0] == '00000')
						trigger_error("UniPath: PDO: execute() return false! ($sql_upd)", E_USER_NOTICE);
					else
						trigger_error("UniPath: PDO: ".implode(';',$err_info)." ($sql_upd)", E_USER_NOTICE);
						
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = $err_info;
				} else {
					$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $res->rowCount();
				}
				break;
			case 'resource/odbc-link':
				$res = odbc_prepare($db, $sql_upd);
				if(empty($res)) {
					trigger_error("UniPath: ODBC Prepare: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = array(odbc_error($db), odbc_errormsg($db));
					break;
				} 
				
if(!empty($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
				$res_execute_result = false;
} else {
				if($res) { 
					odbc_setoption($res, 2, 0, 15); // time-out
					$res_execute_result = odbc_execute($res, $sql_binds);
				}
}
				if(isset($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate')
					/* skip ??? */
					trigger_error("UniPath: ODBC: unipath_debug_sql = simulate! ($sql_upd)", E_USER_NOTICE);
				elseif($res and !$res_execute_result) {
					trigger_error("UniPath: ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = array(odbc_error($db), odbc_errormsg($db));
				} else {
					$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = odbc_num_rows($res);
				}
				break;
			case 'resource/mysql-link':
				$stmt = mysql_query($sql_upd, $db);
				if(empty($stmt)) {
					trigger_error("UniPath: ".__FUNCTION__.": MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql_upd)");
					$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
				} else {
					$cache_item['last_affected_rows()'] = mysql_affected_rows($db);
				}
				break;
			default:
				trigger_error("UniPath: ".__FUNCTION__.": Didn`t know how-to work with $db_type");
		}
	}
if(function_exists('mark')) mark("database_set(UPDATE)");
}

function _uni_last_affected_rows($tree, $lv = 0) { return _uni_last_error($tree, $lv); }
function _uni_last_insert_id($tree, $lv = 0) { return _uni_last_error($tree, $lv); }
function _uni_last_error($tree, $lv = 0) {
	if(isset($GLOBALS['__cursor_database']))
	foreach($GLOBALS['__cursor_database'] as $num => $item) 
		if($item['db'] == $tree[$lv-1]['data'])
			$cache_item =& $GLOBALS['__cursor_database'][$num];

	if(isset($cache_item)) {
		$result = array(
			'data' => @$cache_item[
				strpos($tree[$lv]['name'], 'last_error(') === 0 
				? 'last_error()'
				: (strpos($tree[$lv]['name'], 'last_insert_id(') === 0
				? 'last_insert_id()'
				: 'last_affected_rows()')]);
		$result['data_type'] = gettype($result['data']);
	} else 
		$result = array('data' => null, 'data_type' => 'null');

	return $result;
}

function _uni_new_row($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
// var_dump($tree[$lv]['name']." -- $cursor_cmd");

	// .../new_row()
	if(is_null($cursor_cmd) and is_null($cursor_arg1))
		return array(
			'data' => null, 
			'data_type' => 'array/db-row', 
			'data_tracking' => array(
				'cursor()' => '_uni_new_row',
				'db' => $tree[$lv-1]['data'][0], 
				'from_tables' => array($tree[$lv-1]['data'][1])
				)
		);
	
	if($cursor_cmd == 'set') {
		$data_tracking = $tree[$lv]['data_tracking'];
		$db = $data_tracking['db'];
		$db_type = is_resource($db) 
			? 'resource/'.str_replace(' ', '-', get_resource_type($db))
			: (is_object($db) ? 'object/'.get_class($db) : gettype($db));
		$table = $data_tracking['from_tables'][0];
		
		// проверим подключение к базе данных
		if(!is_resource($db) and !is_object($db)) {
			trigger_error('UniPath: '.__FUNCTION__.': $db is not resource or object! ('.$tree[$lv]['unipath'].')');
			return false;
		}
		
		// промежуточные результаты хранятся в кеше
		isset($GLOBALS['__cursor_database']) or $GLOBALS['__cursor_database'] = array();
		foreach($GLOBALS['__cursor_database'] as $num => $item) 
			if($item['db'] == $db) 
				$cache_item =& $GLOBALS['__cursor_database'][$num];
		if(!isset($cache_item)) {
			$GLOBALS['__cursor_database'][] = array('db' => $db);
			$cache_item =& $GLOBALS['__cursor_database'][count($GLOBALS['__cursor_database']) - 1];
		}

		$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = null;
		$cache_item['last_error()'] = $cache_item["last_error($table)"] = null;
		
		$sql_fileds = array_keys($cursor_arg1); 
		$sql_vals = array();
		foreach($cursor_arg1 as $key => $val)
			if(is_null($val))
				$sql_vals[] = 'NULL';
			elseif(is_string($val)) {
				switch($db_type) {
					case 'object/PDO':
						$sql_vals[] = $db->quote(strval($val));
						break;
					case 'resource/mysql-link':
						$sql_vals[] = "'".mysql_real_escape_string(strval($val))."'";
						break;
					case 'resource/odbc-link':
					default:
						$sql_vals[] = "'".str_replace("'", "''", strval($val))."'";
				}
			}
			elseif(is_array($val))
var_dump('!!! bad $val = ', print_r($val, true));
			elseif(is_bool($val))
				$sql_vals[] = $val ? 1 : "''";
			else
				$sql_vals[] = strval($val);

		$sql = "INSERT INTO {$table} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")";
		
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql;
			else
// 				trigger_error('UniPath: '.__FUNCTION__.': '.$sql_upd, E_USER_NOTICE);
				echo 'UniPath: '.__FUNCTION__.': '.$sql; // error_reporting(0);
		}

		// MySQL
		if($db_type == 'resource/mysql-link') {
			$stmt = mysql_query($sql, $db);
			if(empty($stmt)) {
				trigger_error("UniPath: __cursor_database: MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql)");
				$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
			} else {
				$cache_item['last_affected_rows()'] = mysql_affected_rows($db);
				$cache_item['last_insert_id()'] = $cache_item["last_insert_id($table)"] = mysql_insert_id();
			}
		}
		
		// PDO
		else {
			$stmt = $db->prepare($sql);
	
if(!empty($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
		$db_execute_result = false;
} else {
			if($stmt) $db_execute_result = $stmt->execute();
}

if(!empty($GLOBALS['unipath_debug_sql'])) var_dump("\$stmt = ".print_r($stmt, true).", \$db_execute_result = ".(isset($db_execute_result)?$db_execute_result:'undefined').", rowCount = ".(is_object($stmt)?$stmt->rowCount():$stmt));
	
			if($stmt and !empty($db_execute_result)) {
				$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $stmt->rowCount();
				$cache_item['last_insert_id()'] = $cache_item["last_insert_id($table)"] = $db->lastInsertId();
			} else {
				$err_info = $db->errorInfo();

				if($err_info[0] == '00000' and isset($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
					/* skip ??? */
					trigger_error("UniPath(new_row): PDO: unipath_debug_sql = simulate! ($sql)", E_USER_NOTICE);
					var_dump($GLOBALS['unipath_debug_sql']);
				}
				elseif($err_info[0] == '00000' and empty($db_execute_result))
					trigger_error("UniPath(new_row): PDO: execute() return false! ($sql)", E_USER_NOTICE);
				else
					trigger_error("UniPath(new_row): PDO: ".implode(';',$err_info)." ($sql)", E_USER_NOTICE);
			
				$cache_item['last_error()'] = $cache_item["last_error($table)"] = $err_info;
			}
		}
		
		return ($stmt or !empty($db_exec_result));
	}
}

function _uni_insert_into($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

		$dst = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['data_type'], isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null, $args['arg1']);
		
		if(empty($dst['data'])) {
			trigger_error('UniPath: insert_into(): arg1 is not database table description! (arg1 = '.json_encode($args['arg1']).')');
			return array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null);
		}
		
		$db = $dst['data'][0];

		$sql_fileds = array_keys($tree[$lv-1]['data']); 
		$sql_vals = array();
		foreach($tree[$lv-1]['data'] as $key => $val)
			if(is_null($val))
				$sql_vals[] = 'NULL';
			elseif(is_string($val))
				$sql_vals[] = $db->quote($val);
			else
				$sql_vals[] = $val;
//echo "INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")";
		$db->exec("INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")");
		
		return array('data' => $db->lastInsertId(), 'data_type' => 'integer');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_assertEqu($tree, $lv = 0) {
		$result = array(
			'data' => $tree[$lv-1]['data'], 
			'data_type' => $tree[$lv-1]['data_type'],
			'data_tracking' => array('cursor()' => '_cursor_assertEqu'));
		
		return $result;
		
/*		if(isset($tree[$lv]['data_tracking']) and is_array($tree[$lv]['data_tracking'])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
				foreach($tree[$lv]['data'] as $key => $item)
					if(isset($tree[$lv-1]['data_tracking']))
						$tree[$lv]['data_tracking'][$key] = "assertEqu(".$tree[$lv-1]['data_tracking'][$key].")";
					else
						$tree[$lv]['data_tracking'][$key] = "assertEqu()";
			} else {
				if(isset($tree[$lv-1]['data_tracking']))
					$tree[$lv]['data_tracking'] = "assertEqu(".$tree[$lv-1]['data_tracking'].")";
				else
					$tree[$lv]['data_tracking'] = "assertEqu()";
			};*/
}

function _cursor_assertEqu($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
	assert('$cursor_cmd == "set";');

	$data = $tree[$lv]['data'];
	$test = $cursor_arg1;

	if(sscanf($tree[$lv]['name'], 'assertEqu(%[^)]', $test_name) == 1)
		$test_name = trim($test_name, "'").' - ';
	else
		$test_name = '';

	if(!is_array($test))
		return assert('$data == $test; /* '.$test_name.print_r($data, true).' != '.print_r($test, true).' */');

	if(isset($test))
		assert('isset($data);');
	
	$skip = array();
	foreach($test as $test_key => $test_val) {
		$found = null;
		foreach($data as $key => $val) if(!in_array($key, $skip)) {
//var_dump("-------", array_intersect_assoc($val, $test_val), $test_val, $val, array_intersect($val, $test_val) == $test_val);
			if(!is_array($val)) { 
				if($val == $test_val)
					$found = $key;
				continue;
			}

			if(is_array($val)) {
				if(!is_array($test_val)) continue;
				if(@array_intersect_assoc($val, $test_val) == $test_val) 
					$found = $key;
			}
		}
		
		if(is_null($found)) 
			assert("in_array(\$test['$test_key'], \$data); /* \n--- $test_name NOT FOUND ---\n ".print_r($test_val, true)."\n --- BY KEY '$test_key' IN --- \n".print_r($data, true).' */');
		else
			$skip[] = $found;
	}
	
}

function _uni_cache($tree, $lv = 0) {
	global $GLOBALS_data_types, $GLOBALS_data_tracking, $GLOBALS_data_timestamp;

	$name = $tree[$lv]['name'];
	$result = array('data' => null, 'data_type' => 'null');
	
	list($args, $args_types) = __uni_parseFunc($name);
	$arg1 = $args['arg1'];
	$arg1_type = $args_types['arg1'];
	$lifetime = isset($args['lifetime']) ? $args['lifetime'] : 60*10;

	// временно, для совместимости со старым кодом
	if($arg1_type == 'string') $arg1 = '/'.$arg1;
// var_dump("cache key = ".$arg1);
	// cache(/var1)
	if(sscanf($arg1, '%[/$]%[qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789]', $first_char, $var_name) == 2 and strlen($arg1) == strlen($var_name)+1) {

		// save
		if($lv > 0 and $tree[$lv-1]['name'] != '?start_data?') {
// var_dump('save to cache - '.$var_name);
			$GLOBALS[$var_name] = $result['data'] = $tree[$lv-1]['data'];
			$GLOBALS_data_types[$var_name] = $result['data_type'] = $tree[$lv-1]['data_type'];
			$GLOBALS_data_timestamp[$var_name] = time();
			if(array_key_exists('data_tracking', $tree[$lv-1]))
				$GLOBALS_data_tracking[$var_name] = $result['data_tracking'] = & $tree[$lv-1]['data_tracking'];
		} 
		// restore
		else {
// var_dump('restore from cache - '.$var_name);
// 			if(isset($uni_cache_data[$arg1]) or $arg1[0] == '$') {
				$result['data'] = isset($GLOBALS[$var_name]) ? $GLOBALS[$var_name] : null;
				$result['data_type'] = $GLOBALS_data_types[$var_name];
				if(array_key_exists($var_name, $GLOBALS_data_tracking))
					$result['data_tracking'] = $GLOBALS_data_tracking[$var_name];
			} 
// 			else {
// 				trigger_error("UniPath: \$uni_cache_data[".ltrim($arg1, '$')."] not set!");
// 				$result['data'] = null;
// 				$result['data_type'] = 'null';
// 			}
		}
// 	} 
	
	// cache(/.../...) - json_encode/json_decode
	else {
		// save
		if($lv > 0 and $tree[$lv-1]['name'] != '?start_data?') {
// var_dump('save to cache - uni('.$arg1.', ...)');
			__uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['data_type'], $tree[$lv-1]['data_tracking'], $arg1, json_encode($tree[$lv-1]+array('cache_timestamp' => time())));
			$result['data'] = $tree[$lv-1]['data'];
			$result['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1]))
				$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
		} 
		// restore
		else {
			$json_string = uni($arg1);
// var_dump('restore from cache - uni('.$arg1.')', $json_string);
			if(is_string($json_string)) {
				$json_string = json_decode($json_string, true);
				
				// проверим валидность и lifetime
				if(is_array($json_string)) {
					if(isset($json_string['cache_timestamp'])
					and $json_string['cache_timestamp'] < time() - $lifetime/* and strpos($_SERVER['HTTP_HOST'], '.loc') === false*/) {
						$result['data'] = null;
						$result['data_type'] = 'null';
					} else
						$result = array_merge($result, $json_string);
				}
			}

			// либо раскодировать не удалось, либо простые данные...
			if(!is_array($json_string)) {
				$result['data'] = $json_string;
				$result['data_type'] = gettype($json_string);
				if($lv > 0 and array_key_exists('data_tracking', $tree[$lv-1]))
					$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
			}
		}
	};
	
	return $result;
}

function _uni_ifEmpty($tree, $lv = 0) {
	$result = array();
	if(empty($tree[$lv-1]['data']) and $tree[$lv-1]['data'] !== '0') {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		if(!isset($args['arg1'])) {
			$result['data'] = null;
			$result['data_type'] = 'null';
		} 
		elseif($args_types['arg1'] == 'unipath') {
			$arg1 = __uni_with_start_data(null, null, null, $args['arg1']);
			$result = array_merge($result, $arg1);
		} 
		else {
			$result['data'] = $args['arg1'];
			$result['data_type'] = gettype($args['arg1']);
		}
	
	} else {
		$result['data'] = $tree[$lv-1]['data'];
		$result['data_type'] = $tree[$lv-1]['data_type'];
	};
	
	return $result;
}

function _uni_ifNull($tree, $lv = 0) {
	$result = array();
	if(isset($tree[$lv-1]['data']) == false) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		if(!isset($args['arg1'])) {
			$result['data'] = null;
			$result['data_type'] = 'null';
		} 
		elseif($args_types['arg1'] == 'unipath') {
			$arg1 = __uni_with_start_data(null, null, null, $args['arg1']);
			$result = array_merge($result, $arg1);
		} 
		else {
			$result['data'] = $args['arg1'];
			$result['data_type'] = gettype($args['arg1']);
		}
	
	} else {
		$result['data'] = $tree[$lv-1]['data'];
		$result['data_type'] = $tree[$lv-1]['data_type'];
	};
	
	return $result;
}

function _uni_unset($tree, $lv = 0) { return _uni_unlet($tree, $lv); }
function _uni_unlet($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(!isset($args['arg1']))
		$arg1 = '';
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
		
	$result = array('data' => $tree[$lv-1]['data']);
	unset($result['data'][$arg1]);
	$result['data_type'] = $tree[$lv-1]['data_type'];
	
	return $result;
}
		
function _uni_set($tree, $lv = 0) { return _uni_let($tree, $lv); }
function _uni_let($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"])');
	
	$result = array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type']);
	
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
//var_dump($args, $result['data']);
	if(strncmp($args['arg1'], './', 2) == 0)
		$args['arg1'] = substr($args['arg1'], 2);
	
	if(strncmp($args['arg2'], './', 2) == 0)
		$args['arg2'] = substr($args['arg2'], 2);
	
	if(strncmp($args['arg1'], '*/', 2) == 0) {
		$arg1 = substr($args['arg1'], 2);
		$arg2 = substr($args['arg2'], 2);
		
		foreach($result['data'] as $key => $row) {
			$uni_result = __uni_with_start_data($row, null, null, $arg2);
			$result['data'][$key][$arg1] = $uni_result['data'];
		}
	} 
	else {
		$uni_result = __uni_with_start_data($result['data'], null, null, $args['arg2']);
		$result['data'][$args['arg1']] = $uni_result['data'];
	};
	
	return $result;
}

function _uni_toArray($tree, $lv = 0) {
	if(empty($tree[$lv-1]['data']))
		return array('data' => array(), 'data_type' => 'array');
		
	$result = array(
		'data' => (array) $tree[$lv-1]['data'],
		'data_type' => is_array($tree[$lv-1]['data']) 
			|| strncmp($tree[$lv-1]['data_type'], 'array', 5) == 0 
			? $tree[$lv-1]['data_type'] : 'array');
			
	if(array_key_exists('data_tracking', $tree[$lv-1]))
		$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
		
	return $result;
}

function _uni_replace($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	assert('isset($args["arg1"])');
	assert('isset($args["arg2"])');
// var_dump($args, $args_types);

	$arg1_sscanf = strpos($args['arg1'], '%') !== false;

	$data_tracking = isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : array();
	$result = array('data' => array(), 'data_type' => 'array');
	$pos = 0;

	for($prt_cnt = 0; $prt_cnt < 1000; $prt_cnt++) {
		// если предыдушие данные надо получать через cursor()
		if(isset($data_tracking['cursor()'])) {
			
			$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next', 10);
			
			// промежуточные переменные курсора перенесём в предыдущий data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$tree[$lv-1]['data_tracking']['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			// если ответ это одна запись, то преобразуем в массив с одной записьмю
			if(is_array($call_result) and isset($call_result['data'], $call_result['data_type'])) {
				$data = array($pos => $call_result['data']);
			} 
			
			// если ответ это набор записей next(10)
			elseif(is_array($call_result) and !empty($call_result)) {
				$data = $call_result['data'];
			} 
			
			// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
			else
				break;
		
		// стандартный массив данных
		} elseif(!isset($data))
			$data = $tree[$lv-1]['data'];
		
		// всё обработали прерываем массив
		else
			break;
		
		// делаем преобразование элементов массива
		foreach($data as $key => $val) { $pos++;
		
			// ключ является маской
			if($arg1_sscanf) {
				$sscanf_result = sscanf($key, $args['arg1']);
				if(is_null($sscanf_result[0])) continue;
			} 
			
			// неподходит по ключу
			elseif($key != $args['arg1'])
				continue;

			$uni_result = __uni_with_start_data($val, gettype($val), array('key()' => $key, 'pos()' => $pos), $args['arg2']);
			$result['data'][$key] = $uni_result['data'];
// var_dump($uni_result, 'key='.$key.', pos='.$pos);
		}
	
	}
	
	return $result;
}

function _uni_array($tree, $lv = 0) {
	
	$result = array('data' => array(), 'data_type' => 'array');
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	foreach($args as $key => $val) {
	
		// возможно элементы массива без имён, т.е. по порядку
		if(strncmp($key, 'arg', 3) == 0 and is_numeric($key[3])) 
			$i = intval(substr($key, 3))-1;
		else
			$i = $key;
				
		if($args_types[$key] == 'unipath') {
			$uni_result = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['data_type'], isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null, $val);
			$result['data'][$i] = $uni_result['data'];
		}
		else
			$result['data'][$i] = $val;
	}
	
	return $result;
}

function _uni_toHash($tree, $lv = 0) {
	
	// вытащим из аргументов название ключевого поля
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(empty($args['arg1'])) {
		trigger_error('toHash(?) need field name as argument wich will be key field!');
		return array('data' => null, 'data_type' => 'null');
	} elseif($args_types['arg1'] == 'unipath')
		$pkey = strval(uni($args['arg1']));
	else
		$pkey = $args['arg1'];
// var_dump('toHash(): key = '.$pkey);
	
	// --- Вариант с cursor()
	if(isset($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking']['cursor()'])) {
		assert('function_exists($tree[$lv-1]["data_tracking"]["cursor()"]);') or var_dump($tree[$lv-1]);
	
		$data_tracking = & $tree[$lv-1]["data_tracking"];
		$next_limit = 10;
	
		// подготовим результат
		$result = array(
			'data' => array(), 
			'data_type' => /*strncmp($tree[$lv-1]['data_type'], 'array', 5) == 0 ? $tree[$lv-1]['data_type'] :*/ 'array');
		/*if(array_key_exists('data_tracking', $tree[$lv-1])) 
			$result['data_tracking'] = $tree[$lv-1]['data_tracking']; */
	
		// REWIND - перематаем в начало
		$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'rewind');

		// промежуточные переменные курсора перенесём в предыдущий data_tracking
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
			$data_tracking['cursor_vars'] = $call_result['cursor_vars'];
			unset($call_result['cursor_vars']);
		}
		
		global $__uni_prt_cnt;
		for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

			// постепенно наращиваем лимит запрашиваемых порций
			$next_limit = $next_limit < 20 ? $next_limit+1 : ($next_limit < 150 ? $next_limit+10 : 1000);
			
			$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next', $next_limit);
// var_dump($call_result); exit;
			// промежуточные переменные курсора перенесём в предыдущий data_tracking
			if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
				$data_tracking['cursor_vars'] = $call_result['cursor_vars'];
				unset($call_result['cursor_vars']);
			}
			
			// если ответ это одна запись, то преобразуем в массив с одной записьмю
			if(is_array($call_result) and isset($call_result['data'])) {
				$data = array($call_result['data']);
			} 
			
			// если ответ это набор записей next(100)
			elseif(is_array($call_result) and !empty($call_result)) {
				$data = $call_result;
			} 
		
			// пустой или отрицательный ответ, возвращаем как есть.
			else
				return $result;
		
			// построим hash
			foreach($data as $item) {
				if(is_array($item) == false) continue; // data_type, data_tracking...
				if(array_key_exists($pkey, $item)) {
					$new_key = $item[$pkey];
					
					if(isset($args['arg2']) and $args_types['arg2'] == 'unipath') {
						$uni_result = __uni_with_start_data($item, $call_result['data_type'], isset($call_result['data_tracking']) ? $call_result['data_tracking'] : null, $args['arg2']);
						$result['data'][$new_key] = $uni_result['data'];
					} else
						$result['data'][$new_key] = $item;

				} 
			}
// print_r($result); exit;
		} 

		trigger_error('UniPath: toHash: protection counter $__uni_prt_cnt exhausted!');
		return $result;
	}

	// --- Класический вариант
	if(is_array($tree[$lv-1]['data'])) {
	
		// подготовим результат
		$result = array(
			'data' => array(), 
			'data_type' => strncmp($tree[$lv-1]['data_type'], 'array', 5) == 0 ? $tree[$lv-1]['data_type'] : 'array');
		if(array_key_exists('data_tracking', $tree[$lv-1])) 
			$result['data_tracking'] = $tree[$lv-1]['data_tracking'];

		// построим hash
		foreach($tree[$lv-1]['data'] as $key => $val) {
			if(is_array($val) and array_key_exists($pkey, $val)) {
				$new_key = $val[$pkey];
				
				if(isset($args['arg2']) and $args_types['arg2'] == 'unipath') {
					$uni_result = __uni_with_start_data($val, gettype($val), array('key()' => $key), $args['arg2']);
					$result['data'][$new_key] = $uni_result['data'];
				} else
					$result['data'][$new_key] = $val;

			}
		} 

		return $result;
	}
	
	// если не массив, но есть ключ, то просто оборачиваем в массив
	if(isset($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking']['key()']))
		return array(
			'data' => array($tree[$lv-1]['data_tracking']['key()'] => $tree[$lv-1]['data']), 
			'data_type' => 'array');
	
	// иначе просто оборачиваем в массив
	return array('data' => (array) $tree[$lv-1]['data'], 'data_type' => 'array');
}

function _uni_count($tree, $lv = 0) {
	return array('data_type' => 'integer', 'data' => count($tree[$lv-1]['data']));
}
		
function _uni_sum($tree, $lv = 0) {
	return array('data' => array_sum((array) $tree[$lv-1]['data']), 'data_type' => gettype($tree[$lv-1]['data']));
}

function _uni_asFile($tree, $lv = 0) {
	$path = realpath($tree[$lv-1]['data']);
// 	if(file_exists($path)) {
		$result = array(
			'data' => $path, 
			'data_type' => 'string/local-pathname', 
			'data_tracking' => array('url' => 'file://'.$path, 'key()' => $tree[$lv-1]['data'], 'cursor()' => '_cursor_asFile'));
// 	}
	return $result;
}

function _cursor_asFile($tree, $lv = 0, $cursor_cmd = '', $cursor_arg1 = null) {
	if($cursor_cmd == 'all') {
		return $tree[$lv];
	}
// var_dump($cursor_cmd, $cursor_arg1);

	if($cursor_cmd == 'set') {
		$track = $tree[$lv]['data_tracking'];
		$url = $track['url'];
		
		if(strpos($url, 'file://') === 0)
			$url = substr($url, 7);
			
		if(!file_put_contents($url, $cursor_arg1))
			debug_print_backtrace();

// var_dump('*** saved '.$url);
		return array();
	}
	
	if($cursor_cmd == 'eval') {
		return false;
	}

	if($cursor_cmd == 'next') {
		if($tree[$lv]['data_type'] == "string/local-pathname")
			return false;
	}
}

function _uni_asDirectory($tree, $lv = 0) {
	$path = realpath($tree[$lv-1]['data']);
	$result = array(
		'data' => $path,
		'data_type' => 'string/local-directory',
		'data_tracking' => array('url' => 'file://'.$path, 'key()' => $tree[$lv-1]['data'])
	);
	return $result;
}

function _uni_asZIPFile($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {

	// zip/NNN запись в Zip-архиве
	if($cursor_cmd == 'eval' and $tree[$lv]['data_type'] == 'object/zip') {
		if(is_numeric($cursor_arg1['name'])) {
			$entry = $tree[$lv]['data']->statIndex($cursor_arg1['name']);
			$result = array(
				'data' => $tree[$lv]['data']->getStream($entry['name']),
				'data_tracking' => array(
					'key()' => $cursor_arg1['name'], 
					'zip' => &$tree[$lv]['data'],
					'zip_entry' => $entry
					)
				);
			if($result['data'])
				$result['data_type'] = gettype($result['data']).'/zip-file';

			return $result;
		} 
		else {
			trigger_error('UniPath: asZipFile(): can`t get file by '.$tree[$lv]['name']);
		}
		
		return array('data' => null, 'data_type' => 'null/zip-file');
	}
	
	if($cursor_cmd == 'eval' and $tree[$lv-1]['data_type'] == 'object/zip-file') {
	
		// zip/NNN/contents()
		if(strpos($cursor_arg1['name'], 'contents(') === 0) {
			$data = stream_get_contents($tree[$lv]['data']);
			return array('data' => $data, 'data_type' => gettype($res).'/zip-file-contents');
		} 
		
		// zip/NNN/saveAs()
		elseif(strpos($cursor_arg1['name'], 'saveAs(') === 0) {
			list($args, $args_types) = __uni_parseFunc($cursor_arg1['name']);
			if(empty($args['arg1']))
				trigger_error('UniPath: asZipFile(): arg1 must be filename!');
			elseif($args_types['arg1'] == 'unipath')
				$arg1 = strval(uni($args['arg1']));
			else
				$arg1 = $args['arg1'];
				
			$dst = fopen($arg1, 'wb');
			stream_copy_to_stream($tree[$lv]['data'], $dst);
			fclose($dst);
			
			return array(
				'data' => &$tree[$lv]['data'], 
				'data_type' => &$tree[$lv]['data_type'], 
				'data_tracking' => &$tree[$lv]['data_tracking']);
		}
		
		return false;
	}
	
	// пусть обрабатывается стандартным обработчиком UniPath
	if($cursor_cmd == 'eval' and strpos($cursor_arg1['name'], '(') !== false) {
var_dump('asZipFile() reject this -> '.$cursor_arg1['name'], $tree[$lv]);
		return false;
	}

	$zip = new ZipArchive();
	$open_result = $zip->open($tree[$lv-1]['data']);
	/*
	ZIPARCHIVE::ER_EXISTS - 10
	ZIPARCHIVE::ER_INCONS - 21
	ZIPARCHIVE::ER_INVAL - 18
	ZIPARCHIVE::ER_MEMORY - 14
	ZIPARCHIVE::ER_NOENT - 9
	ZIPARCHIVE::ER_NOZIP - 19
	ZIPARCHIVE::ER_OPEN - 11
	ZIPARCHIVE::ER_READ - 5
	ZIPARCHIVE::ER_SEEK - 4
	*/
	if($open_result === true)
		return array(
			'data' => $zip, 
			'data_type' => 'object/zip', 
			'data_tracking' => array(
				'key()' => $tree[$lv-1]['data'],
				'cursor()' => '_uni_asZIPFile'));
	else
		return array(
			'data' => null, 
			'data_type' => 'null/zip', 
			'data_tracking' => array(
				'key()' => $tree[$lv-1]['data']/*,
				'cursor()' => '_uni_asZIPFile'*/));
}

function _uni_url($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

	if($args_types['arg1'] == 'unipath') {
// $GLOBALS['unipath_debug'] = true;
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], $tree[$lv-1]['data_type'], 
			isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null, 
			$args['arg1']);
// $GLOBALS['unipath_debug'] = false;
// print_r($arg1);
		return $arg1;
	} 
	
	if(strpos($args['arg1'], 'file://') === 0) {
		if(strpos($args['arg1'], '/./') !== false)
			$args['arg1'] = str_replace('/./', '/'.realpath('.').'/', $args['arg1']);
// var_dump($args['arg1']);
		$result = array(
			'data' => $args['arg1'], 
			'data_type' => 'string/local-pathname', 
			'data_tracking' => array(
				'key()' => $args['arg1'],
				'url' => $args['arg1'],
				'cursor()' => '_cursor_asFile'));
	}
// var_dump($result);
	return isset($result) ? $result : array('data' => $args['arg1'], 'data_type' => 'string/url');
}

function _uni_open($tree, $lv = 0) {
	if(in_array($tree[$lv-1]['data_type'], array('string/local-pathname', 'string/local-entry'))) {
		$result = array(
			'data' => fopen($tree[$lv-1]['data'], file_exists($tree[$lv-1]['data']) ? 'rb+' : 'cb+')
		);
		
	} else
		$result = array('data' => null);
		
	return $result + array('data_type' => gettype($result['data']));
}

function _uni_content($tree, $lv = 0) { return _uni_contents($tree, $lv); }
function _uni_contents($tree, $lv = 0) {
// var_dump($tree[$lv-1]);
	// содержимое интернет ресурса
	if($tree[$lv-1]['data_type'] == 'string/url') {

		$url_host = parse_url($tree[$lv-1]['data'], PHP_URL_HOST);
		$url_host_port = parse_url($tree[$lv-1]['data'], PHP_URL_PORT) or $url_host_port = 80;
		$url_path = parse_url($tree[$lv-1]['data'], PHP_URL_PATH);
		$url_query = parse_url($tree[$lv-1]['data'], PHP_URL_QUERY);
		empty($url_query) or $url_query = '?'.$url_query;
		
if(!empty($GLOBALS['unipath_debug'])) var_dump($url_host, $url_path.$url_query, $url_host_port);
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($socket === false) {
			trigger_error("UniPath: socket_create() failed: reason: " . socket_strerror(socket_last_error()), E_USER_ERROR);
		} 
		else {
		
			$conn = socket_connect($socket, gethostbyname($url_host), $url_host_port);
			
			if($conn === false) {
				trigger_error("UniPath: socket_connect() failed.\nReason: ($conn) " . socket_strerror(socket_last_error($socket)));
			} else {
				$req = "GET {$url_path}{$url_query} HTTP/1.1\r\nHost: {$url_host}\r\nConnection: Close\r\n\r\n";
				socket_write($socket, $req, strlen($req));

				$resp = array();
				while($out = socket_read($socket, 2048))
					$resp[] = $out;
				
				socket_close($socket);
if(!empty($GLOBALS['unipath_debug'])) var_dump($resp);
			}
		}

		// если есть ответ и в нём статус указан
		if(isset($resp) and sscanf($resp[0], 'HTTP/1.%i %i', $http_ver, $http_status) == 2) {
			switch($http_status) {
				case 200:
					$result = array('data' => implode('', $resp), 'data_type' => 'string/binnary');
			
					// отделим заголовок
					$result['data'] = substr($result['data'], strpos($result['data'], "\r\n\r\n")+4);
//var_dump($tree[$lv]['data']);
					break;
				case 404:
				default:
					break;
			}
		
		
		}
		
	}
	
	// соержимое локального файла
	elseif($tree[$lv-1]['data_type'] == 'string/local-pathname') {
		$result = array(
			'data' => file_get_contents($tree[$lv-1]['data']), 
			'data_type' => 'string/binnary',
			'data_tracking' => array('url' => $tree[$lv-1]['data'], 'key()' => $tree[$lv-1]['data'])
			);
// var_dump('*** readed '.$tree[$lv-1]['data']);
	}
	
	// неудача
	if(!isset($result))
		return array(
			'data' => null, 
			'data_type' => 'null/binnary', 
			'data_tracking' => array('url' => $tree[$lv-1]['data'], 'key()' => $tree[$lv-1]['data']));
	
	return $result;
}

function _uni_key($tree, $lv = 0) {

	$result = array('data' => null);
	if(array_key_exists('data_tracking', $tree[$lv-1]) and is_array($tree[$lv-1]['data_tracking'])) {
		if(isset($tree[$lv-1]['data_tracking']['key()']))
			$result['data'] = $tree[$lv-1]['data_tracking']['key()'];
		else
			$result['data'] = $tree[$lv-1]['name'];
	}
	
	// key('name%i') -- если указана маска, то название ключа должно совпадать с ней!
	if(strlen($tree[$lv]['name']) > 5) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		$found = sscanf($result['data'], $args['arg1']);
		if(is_null($found) or is_null($found[0]))
			$result['data'] = null;
	}
	
	$result['data_type'] = gettype($result['data']);
		
	return $result;
}
		
function _uni_pos($tree, $lv = 0) {

	if(array_key_exists('data_tracking', $tree[$lv-1]) 
	&& is_array($tree[$lv-1]['data_tracking']) 
	&& isset($tree[$lv-1]['data_tracking']['pos()']))
		$result = array(
			'data' => intval($tree[$lv-1]['data_tracking']['pos()']), 
			'data_type' => 'number');
	else
		$result = array('data' => null, 'data_type' => 'null');
	
	
	return $result;
}

function _uni_first($tree, $lv = 0) {
// 	assert('is_array($tree[$lv-1]["data"])') or var_dump($tree[$lv-1]);

	// если это cursor() то запросим один элемент и вернём что получиться (либо элемент либо false)
	if(isset($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking']['cursor()'])) {
		$data_tracking = & $tree[$lv-1]['data_tracking'];
	
		// 1) перед запросом первого элемента, перемотаем в начало - REWIND
		$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'rewind');

		// промежуточные переменные курсора перенесём в data_tracking[cursor_vars]
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result))
			$data_tracking['cursor_vars'] = $call_result['cursor_vars'];
	
		// 2) запросим первый элемент - NEXT
		$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next');
// var_dump($call_result);
		// промежуточные переменные курсора перенесём в data_tracking[cursor_vars]
		if(is_array($call_result) and array_key_exists('cursor_vars', $call_result)) {
			$data_tracking['cursor_vars'] = $call_result['cursor_vars'];
// 			unset($call_result['cursor_vars']);
		}
		
		// 3) вернём первый элемент или false/empty_array()
		return array(
			'data' => isset($call_result['data']) ? $call_result['data'] : null,
			'data_type' => isset($call_result['data_type']) ? $call_result['data_type'] : 'null',
			'data_tracking' => isset($call_result['data_tracking']) ? $call_result['data_tracking'] : array(),
			);
	}
	
	$result = array();
	if(is_array($tree[$lv-1]['data']) and !empty($tree[$lv-1]['data'])) {
		$key = current(array_keys($tree[$lv-1]['data']));
		$result['data'] = $tree[$lv-1]['data'][$key];
		$result['data_type'] = gettype($result['data']);
		$result['data_tracking'] = array('key()' => $key, 'pos()' => $key);
	} 
	elseif(!is_array($tree[$lv-1]['data'])) {
		$result['data'] = null;
		$result['data_type'] = 'null';
	} 
	else {
		$result['data'] =& $tree[$lv-1]['data'];
		$result['data_type'] =& $tree[$lv-1]['data_type'];
		if(isset($tree[$lv-1]['data_tracking'])) 
		$result['data_tracking'] =& $tree[$lv-1]['data_tracking'];
	};
	
	return $result;
}

function _uni_regexp_match($tree, $lv = 0) {
// print_r($tree[$lv]);
	
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(empty($args['arg1']))
		return array('data' => false);
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = strval(uni($args['arg1']));
	else
		$arg1 = $args['arg1'];
		
// var_dump("~$arg1~ui", $tree[$lv-1]['data'], preg_match("~$arg1~ui", $tree[$lv-1]['data']));
	return array('data' => preg_match("~$arg1~ui", $tree[$lv-1]['data']));
}

function _uni_regexp_replace($tree, $lv = 0) {
	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'data_type' => 'null');
		
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
// var_dump($args);
	if(empty($args['arg1']))
		return array(
			'data' => $tree[$lv-1]['data'], 
			'data_type' => $tree[$lv-1]['data_type']);
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = strval(uni($args['arg1']));
	else
		$arg1 = $args['arg1'];

	// есть ньюанс с регулярками и надо его обойти для удобства
	// (описан - http://stackoverflow.com/questions/13705170/preg-replace-double-replacement)
	if($arg1 == '.*') $arg1 = '(.+|^$)';
		
	return array(
		'data' => preg_replace("/{$arg1}/u", $args['arg2'], $tree[$lv-1]['data']), 
		'data_type' => 'string');
}

function _uni_replace_string($tree, $lv = 0) {
	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'data_type' => 'null');
		
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

	// старый простой вариант
	if(isset($args['arg1'], $args['arg2']))
		return array('data' => str_replace($args['arg1'], $args['arg2'], $tree[$lv-1]['data']), 'data_type' => 'string');
		
	$result_string = $tree[$lv-1]['data'];
	foreach($args as $old => $new) {
	
		if($args_types[$old] == 'unipath') {
			$uni_result = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['data_type'], isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null, $new);
			$new = $uni_result['data'];
		}
		
		$result_string = str_replace($old, $new, $result_string);
	}
	
	return array('data' => $result_string, 'data_type' => 'string');
}

function _uni_remove_start($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"])');
	
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];

	if(strncmp($tree[$lv-1]['data'], $arg1, strlen($arg1)) == 0) {
		$result = array('data' => substr((string) $tree[$lv-1]['data'], strlen($arg1)));
		$result['data'] === false and $result['data'] = null;
	} else
		$result = array('data' => $tree[$lv-1]['data']);

	$result['data_type'] = $tree[$lv-1]['data_type'];
	
	return $result;
}

function _uni_remove_end($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"])');
	
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(!isset($args['arg1']))
		$arg1 = ';';
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
				
	if(mb_strpos($tree[$lv-1]['data'], $arg1, mb_strlen($tree[$lv-1]['data'])-mb_strlen($arg1)) !== false)
		$result = array('data' => mb_substr($tree[$lv-1]['data'], 0, -mb_strlen($arg1)));
	else
		$result = array('data' => $tree[$lv-1]['data']);
		
	$result['data_type'] = $tree[$lv-1]['data_type'];
	
// 	if(array_key_exists('data_tracking', $tree[$lv-1])) {
// 		$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
// 	};

	return $result;
}
			
function _uni_remove_empty($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"])');
	
// 	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
	$result = array('data' => array(), 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => array());
	
	foreach($tree[$lv-1]['data'] as $key => $item) 
		if(empty($item) == false)
			if(is_string($key)) {
				$result['data'][$key] = $item;
// 				$result['data_tracking'][$key] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
			} else {
				$result['data'][] = $item;
// 				$result['data_tracking'][] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
			}

	return $result;
}
			
function _uni_trim($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"])');
	
	$result = array('data' => array(), 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => array());
	
	foreach($tree[$lv-1]['data'] as $key => $item) 
		if(is_string($key)) {
			$result['data'][$key] = trim($item);
// 			$result['data_tracking'][$key] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
		} else {
			$result['data'][] = trim($item);
// 			$result['data_tracking'][] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
		}
		
	return $result;
}

function _uni_append($tree, $lv = 0){ return _uni_add($tree, $lv); }
function _uni_add($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"]) or is_numeric($tree[$lv-1]["data"]);');

	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];

	$result = array('data' => $tree[$lv-1]['data'] . $arg1, 'data_type' => $tree[$lv-1]['data_type']);
	if(array_key_exists('data_tracking', $tree[$lv-1]))
		$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
	
	return $result;
}

function _uni_prepand($tree, $lv = 0) { return _uni_prepend($tree, $lv); }
function _uni_prepend($tree, $lv = 0) { 
	assert('is_string($tree[$lv-1]["data"]) or is_numeric($tree[$lv-1]["data"]);');

	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
				
	$result = array('data' => $arg1.$tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type']);
	if(array_key_exists('data_tracking', $tree[$lv-1]))
		$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
	
	return $result;
}

function _uni_split($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(!isset($args['arg1']))
		$arg1 = ';';
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
			
	$result = array('data_type' => 'array');
	if(!is_array($tree[$lv-1]['data']))
		$result['data'] = explode($arg1, strval($tree[$lv-1]['data']));
	else
		$result['data'] = array_map(create_function('$a','return explode(\''.$arg1.'\', $a);'), $tree[$lv-1]['data']);

	return $result;
}

function _uni_join($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(!isset($args['arg1']))
		$arg1 = '';
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
				
	$result = array('data' => implode($arg1, (array) $tree[$lv-1]['data']), 'data_type' => 'string');
	
	return $result;
}

function _uni_wrap($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	if(!isset($args['arg1']))
		$arg1 = ';';
	elseif($args_types['arg1'] == 'unipath')
		$arg1 = uni($args['arg1']);
	else
		$arg1 = $args['arg1'];
	
	$result = array('data_type' => $tree[$lv-1]['data_type']);
	if(is_array($tree[$lv-1]['data'])) {
		$result['data'] = array();
		foreach($tree[$lv-1]['data'] as $key => $val)
			if(!empty($tree[$lv-1]['data'][$key]))
				$result['data'][$key] = $arg1.strval($tree[$lv-1]['data']).$arg1;
			else
				$result['data'][$key] = $arg1;
	} 
	
	// для всех остальных типов относимся как к строкам
	else {
		if(!empty($tree[$lv-1]['data']))
			$result['data'] = $arg1.strval($tree[$lv-1]['data']).$arg1;
		else
			$result['data'] = $arg1;
	} 
	
	if(array_key_exists('data_tracking', $tree[$lv-1]))
		$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
		
	return $result;
}

function _uni_asJSON($tree, $lv = 0) {
	$data = json_decode($tree[$lv-1]['data'], true);
	return array('data' => $data, 'data_type' => gettype($data));
}

function _uni_translit($tree, $lv = 0) {
	$str = $tree[$lv-1]['data'];

	$ru_str = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщыэюя -,'; 
    $en_str = array('A','B','V','G','D','E','JO','ZH','Z','I','J','K','L','M','N','O','P','R','S','T',
    'U','F','H','C','CH','SH','SHH',chr(35),'I',chr(39),'JE','JU',
    'JA','a','b','v','g','d','e','jo','zh','z','i','j','k','l','m','n','o','p','r','s','t','u','f',
    'h','c','ch','sh','shh','i','je','ju','ja','_','_','_');
	$newname = '';
	$l = mb_strlen($str, 'UTF-8');
	for($i = 0; $i < $l; $i++) { 
		$char = mb_substr($str, $i, 1, 'UTF-8');
		$n = mb_strpos($ru_str, $char, 0, 'UTF-8'); 
		if($n !== false) 
		$newname .= $en_str[$n];  
		else {
		if(preg_match('~^[_0-9a-zA-Z.]$~', $char)) 
			$newname .= $char;
		}
	} 
    
    return array('data' => $newname, 'data_type' => 'string');
}

function _uni_toURLTranslit($tree, $lv = 0) {
	$str = $tree[$lv-1]['data'];

	// карта транслитерации
	$ru_str = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщыэюя ,'; 
    $en_str = array('a','b','v','g','d','e','jo','zh','z','i','j','k','l','m','n','o','p','r','s','t',
    'u','f','h','c','ch','sh','shh','','i','','je','ju',
    'ja','a','b','v','g','d','e','jo','zh','z','i','j','k','l','m','n','o','p','r','s','t','u','f',
    'h','c','ch','sh','shh','i','je','ju','ja','-','-');
    
	$newname = '';
	$len = mb_strlen($str, 'UTF-8');
	for($i = 0; $i < $len; $i++) { 
		$char = mb_substr($str, $i, 1, 'UTF-8');
		$n = mb_strpos($ru_str, $char, 0, 'UTF-8'); 
		if($n !== false) 
			$newname .= $en_str[$n];  
			
		// если нет в карте транслитерации, но он разрешён - добавим
		else if(mb_strpos('._-0123456789qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM', $char, 0, 'UTF-8') !== false) 
			$newname .= $char;
	} 
    
    return array('data' => $newname, 'data_type' => 'string');
}

function _uni_decode_url($tree, $lv = 0) {
	if(is_null($tree[$lv-1]['data'])) {
		 return array('data' => null, 'data_type' => 'null');
	} elseif(is_array($tree[$lv-1]['data'])) {
		$result = array();
		foreach($tree[$lv-1]['data'] as $key => $str) 
			$result[$key] = urldecode($str);
		return array('data' => $result, 'data_type' => $tree[$lv-1]['data_type']);
	} else
		return array('data' => urldecode($tree[$lv-1]['data']), 'data_type' => $tree[$lv-1]['data_type']);
}

function _uni_formatPrice($tree, $lv = 0) {
	assert('!is_array($tree[$lv-1]["data"])');

	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

		if(!isset($args['arg1']))
			$arg1 = '';
		elseif($args_types['arg1'] == 'unipath')
			$arg1 = strval(uni($args['arg1']));
		else
			$arg1 = $args['arg1'];
		
		$price = floatval($tree[$lv-1]['data']);

		// если указали округлять до 50руб
		if(isset($args['arg2']) and $args['arg2'] == '999>50') {
			if($price > 999.999)
				$price = ceil($price / 50) * 50;
		}

		// отформатируем число
		$price_formated = number_format($price, 0, ',', ' ') . $arg1;

		return array('data' => $price_formated, 'data_type' => 'null');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_normalize_float($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"]) == false');
	return array(
		'data' => floatval(strtr($tree[$lv-1]['data'], array(' '=>'',','=>'.', 'ложь'=>'0', 'истина'=>'1'))), 
		'data_type' => 'string',
		'data_tracking' => isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null);
}

function _uni_normalize_int($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"]) == false');
	
	if(strpos($tree[$lv]['name'], 'multiply') > 0)
		$multiply = intval(ltrim(substr($tree[$lv]['name'], strpos($tree[$lv]['name'], 'multiply')+8), ' ='));
	else
		$multiply = 1;
// var_dump('$multiply = '.$multiply);
	return array(
		'data' => intval(floatval(strtr($tree[$lv-1]['data'], array(' '=>'',','=>'.', 'ложь'=>'0', 'истина'=>'1'))) * $multiply), 
		'data_type' => 'string',
		'data_tracking' => isset($tree[$lv-1]['data_tracking']) ? $tree[$lv-1]['data_tracking'] : null);
}

function _uni_substr($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		return array('data' => substr($tree[$lv-1]['data'], $args['arg1']), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_array_flat($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		return array('data' => call_user_func_array('array_merge', $tree[$lv-1]['data']), 'data_type' => 'array');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_replace_in($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		return array('data' => strtr($args['arg1'], $tree[$lv-1]['data']), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_iconv($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
		
		$result = array('data' => array(), 'data_type' => $tree[$lv-1]['data_type']);
		foreach($tree[$lv-1]['data'] as $key => $val) 
			if(is_array($val)) {
				$result['data'][$key] = array();
				foreach($val as $key2 => $val2)
					$result['data'][$key][$key2] = mb_convert_encoding($val2, $args['arg2'], $args['arg1']);
			} else {
				$result['data'][$key] = mb_convert_encoding($val, $args['arg2'], $args['arg1']);
			}
		
		/*if(array_key_exists('data_tracking', $tree[$lv-1]))
				$result['data_tracking'] = $tree[$lv-1]['data_tracking'];*/

		return $result;
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_formatQuantity($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

		$founded = $tree[$lv-1]['data'];
		$suffix = isset($func_and_args['arg3']) ? $func_and_args['arg3'] : 'штук';
		if($founded % 10 == 1 and $founded != 11)
			$suffix = isset($args['arg1']) ? $args['arg1'] : 'штука';
		elseif(in_array($founded % 10, array(2,3,4)) && floor($founded / 10) != 1 or $founded == 0)
			$suffix = isset($args['arg2']) ? $args['arg2'] : 'штуки';
		
		return array('data' => $founded." ".$suffix, 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_formatDate($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);

		if(strpos($tree[$lv-1]['data'], '-') != false) {
			$str = date('j-m-Y, H:i', strtotime($tree[$lv-1]['data']));
		} else
			$str = date('j-m-Y, H:i');
			
        $str = strtr($str, array('-01-' => ' января ', '-02-' => ' февраля ', '-03-' => ' марта ', '-04-' => ' апреля ',
        '-05-' => ' мая ', '-06-' => ' июня ', '-07-' => ' июля ', '-08-' => ' августа ', '-09-' => ' сентября ',
        '-10-' => ' октября ', '-11-' => ' ноября ', '-12-' => ' декабря '));
		
		return array('data' => $str, 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_html_safe($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array('data' => htmlspecialchars($tree[$lv-1]['data']), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_html_attr_safe($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array('data' => htmlspecialchars($tree[$lv-1]['data'], ENT_QUOTES), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_asImageFile($tree, $lv = 0) {

		if(!in_array($tree[$lv-1]['data_type'], array('string/pathname')))
			return array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type']);
		
		// определим тип и загрузим фото
		$img_info = getimagesize($tree[$lv-1]['data']);
		switch($img_info[2]) {
			case IMAGETYPE_JPEG: $im1 = imagecreatefromjpeg($tree[$lv-1]['data']); break;
			case IMAGETYPE_GIF: 
				$im1 = imagecreatefromgif($tree[$lv-1]['data']); 
				break;
			case IMAGETYPE_PNG: 
				if(!isset($im1)) $im1 = imagecreatefrompng($tree[$lv-1]['data']); 
				$im0 = imagecreatetruecolor($img_info[0], $img_info[1]);
				imagealphablending($im0, false);
				imagesavealpha($im0,true);
				$transparent = imagecolorallocatealpha($im0, 255, 255, 255, 127);
				imagefilledrectangle($im0, 0, 0, $img_info[0], $img_info[1], $transparent);
				imagecopy($im0, $im1, 0, 0, 0, 0, $img_info[0], $img_info[1]);
				imagedestroy($im1);
				$im1 = $im0;
				break;
			default: $im1 = imagecreatefromstring(file_get_contents($tree[$lv-1]['data']));
		}
		return array('data' => $im1, 'data_type' => 'resource/gd', 'data_tracking' => $img_info);
}

function _uni_resize($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($args['arg1']))
		$new_width = '';
	elseif($args_types['arg1'] == 'unipath')
		$new_width = uni($args['arg1']);
	else
		$new_width = $args['arg1'];
		
	if(!isset($args['arg2']))
		$new_height = '';
	elseif($func_and_args['arg2:type'] == 'unipath')
		$new_height = uni($args['arg2']);
	else
		$new_height = $args['arg2'];
		
	if(!isset($func_and_args['arg3']))
		$resize_mode = '';
	elseif($func_and_args['arg3_type'] == 'unipath')
		$resize_mode = uni($func_and_args['arg3']);
	else
		$resize_mode = $func_and_args['arg3'];
	
	assert('is_resource($tree[$lv-1]["data"])');
	assert('isset($tree[$lv-1]["data_tracking"])') or print_r($tree);
	
	// расчитываем новые размеры в соответствии с указанным режимом
	$height = 0; 
	$width = 0;
	$img_info = $tree[$lv-1]['data_tracking'];
	switch($resize_mode) {
		default:
		case "": // only larger
			if($img_info[0] < $new_width and $img_info[1] < $new_height) {
				$width = intval($img_info[0]);
				$height = intval($img_info[1]);
			} else {
				if($new_width) {
					$width = $new_width;
					$height = $img_info[1] / ($img_info[0] / $new_width);
				}
				if($new_height and $height > $new_height) {
					$height = $new_height;
					$width = $img_info[0] / ($img_info[1] / $new_height);
				}
			}
			break;
		case "inbox":
			if($new_width) {
				$width = $new_width;
				$height = $img_info[1] / ($img_info[0] / $new_width);
			}
			if($new_height and $height > $new_height) {
				$height = $new_height;
				$width = $img_info[0] / ($img_info[1] / $new_height);
			}
			break;
		case "fill":
			if($new_width) {
				$width = $new_width;
				$height = $img_info[1] / ($img_info[0] / $new_width);
			}
			if($new_height and $height < $new_height) {
				$height = $new_height;
				$width = $img_info[0] / ($img_info[1] / $new_height);
			}
			break;
	}
if(!empty($GLOBALS['unipath_debug'])) var_dump($resize_mode, $width, $height);

	// уменьшаем/увеличиваем
	$im2 = imagecreatetruecolor($width, $height);
	imagecopyresampled($im2, $tree[$lv-1]['data'], 0, 0, 0, 0, $width, $height, $img_info[0], $img_info[1]);
	imagedestroy($tree[$lv-1]['data']);
	
	return array(
		'data' => $im2, 
		'data_type' => $tree[$lv-1]['data_type'], 
		'data_tracking' => array($width, $height, 3=> "height=\"{$height}\" width=\"{$width}\"") + $tree[$lv-1]['data_tracking']);
}

function _uni_crop($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($args['arg1']))
		$new_width = '';
	elseif($args_types['arg1'] == 'unipath')
		$new_width = uni($args['arg1']);
	else
		$new_width = $args['arg1'];
		
	if(!isset($args['arg2']))
		$new_height = '';
	elseif($func_and_args['arg2:type'] == 'unipath')
		$new_height = uni($args['arg2']);
	else
		$new_height = $args['arg2'];
		
	if(!isset($func_and_args['arg3']))
		$gravity = 'auto';
	elseif($func_and_args['arg3_type'] == 'unipath')
		$gravity = uni($func_and_args['arg3']);
	else
		$gravity = $func_and_args['arg3'];
		
	// теперь вырежем центральную область
	$src_x = $src_y = 0;
//	if(in_array($gravity, array("", 'auto', 'center'))) {
		$src_x = round(($tree[$lv-1]['data_tracking'][0] - $new_width) / 2);
		$src_y = round(($tree[$lv-1]['data_tracking'][1] - $new_height) / 2);

		$im2 = imagecreatetruecolor($new_width, $new_height);
		imagecopy($im2, $tree[$lv-1]['data'], 0, 0, $src_x, $src_y, $tree[$lv-1]['data_tracking'][0], $tree[$lv-1]['data_tracking'][1]);
		imagedestroy($tree[$lv-1]['data']);
//	}
	
	return array(
		'data' => $im2, 
		'data_type' => $tree[$lv-1]['data_type'], 
		'data_tracking' => array($new_width, $new_height, 3=> "height=\"{$new_height}\" width=\"{$new_width}\"") + $tree[$lv-1]['data_tracking']);
}

function _uni_watermark($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($args['arg1']))
		return array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => $tree[$lv-1]['data_tracking']);
	elseif($args_types['arg1'] == 'unipath')
		$wm_file = uni($args['arg1']);
	else
		$wm_file = $args['arg1'];
	
	// определим тип и загрузим фото
	if(is_resource($wm_file)) {
		$wm_info = array(imagesx($wm_file), imagesy($wm_file));
		$wm = $wm_file;
	} else {
		$wm_info = getimagesize($wm_file);
		switch($wm_info[2]) {
			case IMAGETYPE_JPEG: $wm = imagecreatefromjpeg($wm_file); break;
			case IMAGETYPE_GIF: $wm = imagecreatefromgif($wm_file); break;
			case IMAGETYPE_PNG: $wm = imagecreatefrompng($wm_file); break;
			default: $wm = imagecreatefromstring(file_get_contents($wm_file));
		}
	}

	// уменьшим пропорционально водяной знак если он больше фото
	$dest_width = $wm_info[0];
	$dest_height = $wm_info[1];
	if($tree[$lv-1]['data_tracking'][0] < $wm_info[0]) {
		$dest_width = $tree[$lv-1]['data_tracking'][0];
		$dest_height = $wm_info[1] / ($wm_info[0] / $dest_width);
	}
	if($tree[$lv-1]['data_tracking'][1] < $dest_height) {
		$dest_height = $tree[$lv-1]['data_tracking'][1];
		$dest_width = $wm_info[0] / ($wm_info[1] / $dest_height);
	}
	
	// вычеслим центр
	$dest_x = ($tree[$lv-1]['data_tracking'][0] - $dest_width) / 2;
	$dest_y = ($tree[$lv-1]['data_tracking'][1] - $dest_height) / 2;
	
	imagealphablending($tree[$lv-1]['data'], true);
	imagealphablending($wm, true);
		
	imagecopyresampled(
		$tree[$lv-1]['data'], $wm, 
		$dest_x, $dest_y, 0, 0, 
		$dest_width, $dest_height, $wm_info[0], $wm_info[1]);

//header('Content-Type: image/jpeg'); imagejpeg($tree[$lv-1]['data']);

	return array('data' => $tree[$lv-1]['data'], 'data_type' => 'resource/gd', 'data_tracking' => $tree[$lv-1]['data_tracking']);
}

function _uni_saveAs($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($args['arg1']))
		$filepath = '';
	elseif($args_types['arg1'] == 'unipath')
		$filepath = uni($args['arg1']);
	else
		$filepath = $args['arg1'];
	
	$quality = 83;
	
	switch($tree[$lv-1]['data_type']) {
		case 'resource/gd':
			$file_ext = substr($filepath, -4);
			if(stripos($file_ext, 'gif') !== false)
				imagegif($tree[$lv-1]['data'], $filepath);
			elseif(stripos($file_ext, 'png') !== false)
				imagepng($tree[$lv-1]['data'], $filepath, round($quality/10));
			else 
				imagejpeg($tree[$lv-1]['data'], $filepath, $quality);
			break;
		default:
			file_put_contents($filepath, $tree[$lv-1]['data']);
			break;
	}
	
	return array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => $tree[$lv-1]['data_tracking']);
}

function _uni_basename($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array(
			'data' => substr($tree[$lv-1]['data'], max(strrpos($tree[$lv-1]['data'], '/'), -1) + 1), 
			'data_type' => 'string');
	}
	
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		$result = array();
		foreach($tree[$lv-1]['data'] as $filename)
		if(is_string($filename))
			$result = substr($filename, max(strrpos($filename, '/'), -1) + 1);
			
		return array(
			'data' => $result, 
			'data_type' => 'array');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_plus_procents($tree, $lv = 0) {
	if(is_numeric($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFunc($tree[$lv]['name']);
	
		if(!isset($args['arg1']))
			$arg1 = 0;
		elseif($args_types['arg1'] == 'unipath')
			$arg1 = floatval(uni($args['arg1']));
		else
			$arg1 = floatval($args['arg1']);
			
		return array('data' => floatval($tree[$lv-1]['data']) + floatval($tree[$lv-1]['data']) * $arg1 / 100, 'data_type' => 'number');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_XMLtoArray($tree, $lv = 0) {
	$result = array(
		'data' => @simplexml_load_string((string) $tree[$lv-1]['data']),
		'data_type' => 'array'
	);
	
	if(is_object($result['data'])) {
		$result['data'] = array($result['data']->getName() => 
			array_map(create_function('$a', 'return (array) $a;'), (array) $result['data']));
	} else 
		$result['data'] = array();
		
	return $result;
}

function _uni_ArrayToXML($tree, $lv = 0) {

	$result = array('data' => '', 'data_type' => 'string/xml-fragment');
	
	$array = (array) $tree[$lv-1]['data'];
	foreach($array as $nodeName => $nodeValue) {
		if(!is_numeric($nodeName)) {
			$result['data'] .= "<$nodeName";
			if(is_array($nodeValue) and isset($nodeValue['attrs()']))
			foreach($nodeValue['attrs()'] as $attr => $val)
				$result['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
				$result['data'] .= ">";
		}
					
		if(is_array($nodeValue)) foreach($nodeValue as $nodeName2 => $nodeValue2) {
			if(!is_numeric($nodeName2)) {
				$result['data'] .= "<$nodeName2";
				if(is_array($nodeValue2) and isset($nodeValue2['attrs()']))
				foreach($nodeValue2['attrs()'] as $attr => $val)
					$result['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
				$result['data'] .= ">";
			}
				
			if(is_array($nodeValue2)) foreach($nodeValue2 as $nodeName3 => $nodeValue3) {
				if(!is_numeric($nodeName3)) {
					$result['data'] .= "<$nodeName3";
					if(is_array($nodeValue3) and isset($nodeValue3['attrs()']))
					foreach($nodeValue3['attrs()'] as $attr => $val)
						$result['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
					$result['data'] .= ">";
				}
					
				if(is_array($nodeValue3)) foreach($nodeValue3 as $attr => $val) {
					if($attr !== 'attrs()') $result['data'] .= $val;
				} else
					$result['data'] .= $nodeValue3;
							
				$result['data'] .= "</$nodeName3>";
			} else 
				$result['data'] .= $nodeValue2;
					
			if(!is_numeric($nodeName2))
				$result['data'] .= "</$nodeName2>";
				
		} else 
			$result['data'] .= $nodeValue2;
				
		if(!is_numeric($nodeName))
			$result['data'] .= "</$nodeName>";
	} // foreach 1
	
	return $result;
}

function _uni_asXml($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"]);');
	
	return array(
		'data' => &$tree[$lv-1]['data'], 
		'data_type' => 'string/xml', 
		'data_tracking' => array('cursor()' => '_cursor_asXml'));
}

function _cursor_asXml($tree, $lv = 0, $cursor_cmd = '', $cursor_arg1 = null) {
// if($cursor_cmd != 'next')
// var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:''));
	
	// REWIND
	if($cursor_cmd == 'rewind' and $tree[$lv]['data_type'] == "array/xml-fragments") {
		$cursor_vars = array('current_pos' => 0);
		return array(true, 'cursor_vars' => $cursor_vars);
	}
	
	// NEXT
	if($cursor_cmd == 'next' and $tree[$lv]['data_type'] == "array/xml-fragments") {
		$cursor_vars = isset($tree[$lv]['data_tracking']['cursor_vars']) ? $tree[$lv]['data_tracking']['cursor_vars'] : array();
		$pos = isset($cursor_vars['current_pos']) ? $cursor_vars['current_pos'] : 0;
		
		if(isset($tree[$lv]['data'][$pos])) {
			$result = array(
				'data' => $tree[$lv]['data'][$pos],
				'cursor_vars' => array('current_pos' => $pos+1) + $cursor_vars
			);
			
			if(isset($tree[$lv]['data_tracking']['each_data_tracking'], $tree[$lv]['data_tracking']['each_data_tracking'][$pos])) {
				$result['data_tracking'] = $tree[$lv]['data_tracking']['each_data_tracking'][$pos];
				$result['data_type'] = $result['data_tracking']['type()'];
			}

			return $result;
		}
		
		return array('cursor_vars' => $cursor_vars);
	}
	
	// EVAL
	if($cursor_cmd == 'eval' and strpos($cursor_arg1['name'], '(') === false) {
		$name = $cursor_arg1['name'];
// var_dump($name, $tree[$lv]['data_type']);

		if($name == '.') return false;

		// asXML()/NNN [array]
		if(is_numeric($name) and strncmp($tree[$lv]['data_type'], 'array/xml', 9) == 0
		and is_array($tree[$lv]['data'])) {
			if(array_key_exists($name, $tree[$lv]['data'])) {
				$result = array(
				'data' => $tree[$lv]['data'][$name],
				'data_type' => 'string/xml-fragment',
				'data_tracking' => array('key()' => $name));
				if(isset($tree[$lv]['data_tracking']["each_data_tracking"][$name]))
					$result['data_tracking'] = $tree[$lv]['data_tracking']["each_data_tracking"][$name];
				$result['data_tracking']['cursor()'] = '_cursor_asXml';
// print_r($result);
				return $result;
			} 
		}

		// asXML()/*
		if($name == '*' and strncmp($tree[$lv]['data_type'], 'string/xml', 10) == 0 and is_string($tree[$lv]['data'])) {
			$uni_result = _cursor_asXml_parseXML($tree[$lv]['data'], $name);
			$uni_result['data_tracking']['cursor()'] = '_cursor_asXml';
// var_dump($uni_result);
			return $uni_result;

			// теперь соберём 2 уровень и сгрупируем по key()
/*			$result = array(
				'data' => array(), 
				'data_type' => 'array', 
				'data_tracking' => array('key()' => '*', "each_data_tracking" => array()));
			foreach($uni_result['data'] as $item) {
				$uni_result2 = _cursor_asXml_parseXML($item, '*');
var_dump($uni_result2);
				if(empty($uni_result2['data'])) continue;
				
				foreach($uni_result2['data_tracking']["each_data_tracking"] as $num => $track)
					if(isset($result['data'][$track['key()']])) {
						$result['data'][$track['key()']][] = $uni_result2['data'][$num];
					} else {
						$result['data'][$track['key()']] = array($uni_result2['data'][$num]);
						$result['data_tracking']['each_data_tracking'][$track['key()']] = array('key()' => $track['key()']);
					}
						
			}		
print_r($result);
			return $result; */
		}
		
		// asXML()/tag_name [array]
		if($name != '' and $name != '.' and strncmp($tree[$lv]['data_type'], 'array/xml', 9) == 0
		and is_array($tree[$lv]['data'])) {
			$result = array(
				'data' => array(), 
				'data_type' => 'array/xml-fragments', 
				'data_tracking' => array('key()' => $name, 'cursor()' => '_cursor_asXml'));
			foreach($tree[$lv]['data'] as $num => $data) {
				$uni_result = _cursor_asXml_parseXML($data, $name);
				$result['data'][] = $uni_result['data'];
			}
// var_dump($result);
			return $result;
		}
	
	
		// asXML()/tag_name [string]
		if($name != '' and strncmp($tree[$lv]['data_type'], 'string/xml', 10) == 0 
		and is_string($tree[$lv]['data'])) {
			$uni_result = _cursor_asXml_parseXML($tree[$lv]['data'], $name);
			$uni_result['data_tracking']['cursor()'] = '_cursor_asXml';
// var_dump($uni_result);
			return $uni_result;
		}
	}
	
	// EVAL - toHash()
	if($cursor_cmd == 'eval' and $cursor_arg1['name'] == 'toHash()' and $tree[$lv]['data_type'] == "array/xml-fragments") {
		$result = array();
		$result_tracking = array(
			'key()' => $tree[$lv]['data_tracking']['key()'],
			'each_data_tracking' => array());
		foreach($tree[$lv]['data_tracking']['each_data_tracking'] as $num => $track) {
			if(isset($result[$track['key()']])) {
				$result[$track['key()']][] = $tree[$lv]['data'][$num];
				$result_tracking['each_data_tracking'][$track['key()']][] = $track;
			} 
			else {
				$result[$track['key()']] = array($tree[$lv]['data'][$num]);
				$result_tracking['each_data_tracking'] = array($track);
			}
		}
		return array('data' => $result, 'data_type' => 'array', 'data_tracking' => $result_tracking);
	}
			
	// EVAL - attrs() [string]
	if($cursor_cmd == 'eval' and $cursor_arg1['name'] == 'attrs()' and strncmp($tree[$lv]['data_type'], 'string/xml-fragment', 19) == 0) {
		$result = array(
			'data' => null, 
			'data_type' => 'null',
			'data_tracking' => array('key()' => $tree[$lv]['data_tracking']['key()']));
// var_dump($tree[$lv]['data_tracking']); // exit;

		// извлекаем атрибуты из data_tracking-а
		if(isset($tree[$lv]['data_tracking'], $tree[$lv]['data_tracking']['tag'])) {
			$result['data'] = array();
			$result['data_type'] = 'array/xml-attributes';
			if(preg_match_all('~([^: ]+:)?([^= ]+)=("[^"]+"|[^ >])+~u', $tree[$lv]['data_tracking']['tag'], $matches, PREG_SET_ORDER))
				foreach($matches as $match) {
					$result['data'][$match[2]] = trim($match[3], '"');
				}
		}
				
		return $result;
	}
}

function _cursor_asXml_parseXML($string, $tag_name) {
	// стартовый контекст
	$call = array('find_node', 
		'xml_as_string' => $string, 
		'tag_name' => $tag_name, 
		'result_data' => array(), 
		'result_data_type' => 'array/xml-fragments', 
		'result_data_tracking' => array('key()' => $tag_name, 
			'each_data_tracking' => array()));
	
	global $__uni_prt_cnt;
	for($prt_cnt = $__uni_prt_cnt; $prt_cnt; $prt_cnt--) {
	
		// распакуем упакованный $call
		if(isset($call[2])) {
			$call['step'] = array_pop($call);
			$call['called'] = array_pop($call);
			$call['called']['caller'] =& $call;
			$call =& $call['called'];
		}
		if(isset($call[1])) 
			$call['step'] = array_pop($call);
		
		// проверим и поправим step
		isset($call['step']) or $call['step'] = '';

if(!empty($GLOBALS['unipath_debug'])) 
echo "--- $prt_cnt --- {$call[0]}.{$call['step']}\n";
		
		// find_child_node, find_node
		if(in_array($call[0], array('find_child_node', 'find_node'))) switch($call['step']) {
			default:
				if(is_array($call['xml_as_string']))
					$block = $call['xml_as_string'][0];
				else
					$block = $call['xml_as_string'];
if(!empty($GLOBALS['unipath_debug'])) var_dump($block);
				// если начало документа
				/*if($tree[$lv-1]['data_type'] == 'string/xml-fragment')
					$call += array(1, array('find_next_tag', 'tag2_end' => 0), 'root_tag_found');
				else*/
					$call += array(1, array('find_next_tag', 'tag2_end' => 0), 'next_tag_found');
					
				continue 2;
				
			case 'next_tag_found':
				if($call['called']['tag_start'] === false) 
					return array(
						'data' => $call['result_data'], 
						'data_type' => $call['result_data_type'],
						'data_tracking' => $call['result_data_tracking']);
					//return array('data' => null, 'data_type' => 'null');
			
				// <?xml...
				if(substr_compare($block, '<?xml', $call['called']['tag_start'], 5, true) == 0) {
					$call += array(1, array('find_next_tag', 'tag2_end' => $call['called']['tag_end']), 'next_tag_found');
					continue 2;
				} 
			
				$call['tag_start'] =  $call['called']['tag_start'];
				$call['tag_end'] =  $call['called']['tag_end'];
			
				$call += array(1, array('find_next_tag_close', 'step' => '', 'tag_start' => $tag_start, 'tag_end' => $tag_end), 'next_tag_close_found');
				continue 2;
			
			case 'next_tag_close_found':
				$nodeName_len = strcspn($block, " />\t\n", $call['tag_start']);
				$ns_len = strcspn($block, ':', $call['tag_start'], $nodeName_len);
				if($ns_len == $nodeName_len) $ns_len = 0;
//var_dump("nodeName_len = $nodeName_len, ns_len = $ns_len", substr($block, $call['tag_start']+ $ns_len+1, $nodeName_len - $ns_len - 1), $block[$call['tag_start']+$nodeName_len]);

				// если наш тег, то добавляем его в результат
				if($call['tag_name'] == '*' or 
					(substr_compare($block, $call['tag_name'], $call['tag_start']+($ns_len < $nodeName_len ? $ns_len+1 : 1), strlen($call['tag_name']), true) == 0 
					and in_array($block[$call['tag_start']+$nodeName_len], array(' ', '/', '>', "\t", "\n")))) {
					$tag_start = $call['tag_start'];
					$tag2_end = $call['called']['tag2_end'];
					$tag2_start = $call['called']['tag2_start'];

					$call['result_data'][] = $tag2_end == $call['tag_end'] ? '' : substr($block, $call['tag_end']+1, $tag2_start-$call['tag_end']-1);
					$call['result_data_tracking']['each_data_tracking'][] = array(
						"key()" => substr($block, $call['tag_start']+$ns_len+1, $nodeName_len - $ns_len - 1), //$call['tag_name'],
						"pos()" => count($call['result_data_tracking']['each_data_tracking']),
						"type()" => 'string/xml-fragment',
						"tag" => substr($block, $call['tag_start'], $tag_end - $tag_start+1),
						"start_offset" => $tag_start, 
						"end_offset" => $tag2_end);
					
/*					return array(
						'data' => array(substr($block, $call['tag_end']+1, $tag2_start-$call['tag_end']-1)), 
						'data_type' => 'array/xml-fragment',
						'data_tracking' => array(','.json_encode(array($tag_start, substr($block, $call['tag_start'], $tag_end - $tag_start+1), $tag2_start, substr($block, $tag2_start, $tag2_end - $tag2_start+1), $tag2_end))));*/
				} 
				
				// ищем дальше
				unset($call['tag_start'], $call['tag_end']);
				$call['tag2_end'] = $call['called']['tag2_end'];
				$call += array(1,array('find_next_tag', 'tag2_end' => $call['tag2_end']), 'next_tag_found');
				continue 2;
		}
		
		// find_next_tag
		if($call[0] == 'find_next_tag') switch($call['step']) {
			default:

				// найдём начало тега
				$tag_start = strpos($block, "<", $call['tag2_end']);

				// нет следующего тега!
				if($tag_start === false) {
					$call['tag_start'] = false;
					$call['tag_end'] = false;
					$call =& $call['caller'];
					continue 2;
				}

				// найдём конец тега <![CDATA[ ... ]]>
				if(isset($tag_start) and substr_compare($block, '<![CDATA', $tag_start, 8, true) == 0) { 
					$tag_end = strpos($block, "]]>", $tag_start);
				} else
				// найдём конец тега
				if(isset($tag_start))
					$tag_end = strpos($block, ">", $tag_start);
if(!empty($GLOBALS['unipath_debug'])) var_dump('open_tag = '.substr($block, $tag_start, $tag_end - $tag_start+1));
				
				$call += array('tag_start' => $tag_start, 'tag_end' => $tag_end);
				$call =& $call['caller'];
				continue 2;
		}
			
		// find_next_tag_close
		if($call[0] == 'find_next_tag_close') switch($call['step']) {
			default:
				$tag_start = $call['tag_start'];
				$tag_end = $call['tag_end'];
				
				// self closed tag
				if($block[$tag_end-1] == '/')
					list($tag2_start, $tag2_end) = array($tag_start, $tag_end);
					
				// найдём закрывающий тег
				else {
					$close_tag_len = strcspn($block, ' >', $tag_start, $tag_end - $tag_start)+1;
					$close_tag = '</'.substr($block, $tag_start+1, $close_tag_len-2);
if(!empty($GLOBALS['unipath_debug'])) var_dump('close_tag = '.$close_tag.' ('.$close_tag_len.')');

					// ищем правельный закрывающий тег
					$tag2_start = stripos($block, $close_tag, $tag_end);
					for($prt_cnt2 = 100; $prt_cnt2--;)
					if($tag2_start and isset($block[$tag2_start+$close_tag_len]) 
						and !in_array($block[$tag2_start+$close_tag_len], array('>', ' '))) {
//var_dump($block[$tag2_start+$close_tag_len]." == ".$tag2_start, substr($block, $tag2_start, 15));
						$tag2_start = stripos($block, $close_tag, $tag2_start+$close_tag_len);

					} else break;
					
					$tag2_end = strpos($block, ">", $tag2_start);
				}
if(!empty($GLOBALS['unipath_debug'])) var_dump('found_close_tag = '.substr($block, $tag2_start, $tag2_end - $tag2_start+1)/*, substr($block, $tag2_end), $tag2_end*/);
				$call += array('tag2_start' => $tag2_start, 'tag2_end' => $tag2_end);
				$call =& $call['caller'];
				continue 2;
		}
		

	}
	
	error_log('_uni_xml().prt_cnt!');
	return array('data' => null, 'data_type' => 'null');
}