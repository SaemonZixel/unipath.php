<?php

/**
 *  UniPath - XPath like access to DataBases, Files, XML, Arrays and any other data from PHP
 *  
 *  @version  2.4rc4
 *  @author   Saemon Zixel <saemonzixel@gmail.com>
 *  @link     https://github.com/SaemonZixel/unipath
 *
 *	UniversalPath (UniPath.php) - универсальный доступ к любым ресурсам
 *  Задумывался как простой, компактный и удобный интерфейс ко всему. Идеологически похож на jQuery и XPath.
 *  Позваляет читать и манипулировать, в том числе и менять, как локальные переменные внутри программы,
 *  так и в файлы на диске, таблицы в базе данных, удалённые ресурсы и даже менять на удалённом сервере 
 *  параметры запущенного приложения или считать запись access.log по определённой дате и подстроке UserAgent и т.д.
 *  Но всё это в светлом будущем:) Сейчас реализованна только основная небольшая часть всего этого.
 *
 *
 *  @license  MIT
 *
 *  Copyright (c) 2013-2023 Saemon Zixel
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of this software *  and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */

global $GLOBALS_metadata, $GLOBALS_data_timestamp, 
       $__uni_version, $__uni_prt_cnt, $__uni_benchmark, $__uni_optimize; // for PHP 5.3 and upper

$GLOBALS_metadata = array(); // источники некоторых переменных в $GLOBALS
$GLOBALS_data_timestamp = array(); // timestamp если были закешированны

$__unipath_php_file = __FILE__; // кто и где мы
$__uni_version = 2.4; // наша версия
$__uni_prt_cnt = 100000; // лимит интераций циклов, защитный счётчик от бесконечного зацикливания
$__uni_optimize = 1; // 0 - off; 1 - optimeze: /abc, ./abc, ./`abc`, .
$__uni_benchmark = array(); // для статистики скорости обработки unipath запросов

// режим присвоения значения (исключительно для внутренних нужд)
$__uni_assign_mode = false;

function uni($unipath) {

	// просят внутри использовать другой error_reporting режим
	if(isset($GLOBALS['unipath_error_reporting'])) {
		$old_error_reporting_level = error_reporting();
		error_reporting($GLOBALS['unipath_error_reporting']);
	}

	if(func_num_args() > 1) {
		$uni_result = __uni_with_start_data(null, null, null, $unipath, func_get_arg(1));
		
		// вернём обратно error_reporting
		if(isset($old_error_reporting_level))
			error_reporting($old_error_reporting_level);
			
		return $uni_result;
	}
		
	$uni_result = __uni_with_start_data(null, null, null, $unipath);
	
if(!empty($GLOBALS['unipath_debug']) and !isset($uni_result['metadata'][0])) var_dump(__FUNCTION__.": metadata[0] is empty!", $uni_result);

	// cursor() - вытаскиваем все данные тогда
	if(isset($uni_result['metadata']['cursor()'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni: Start all()...");
		
		$lv = 0;
		$tree = array($uni_result);
		$metadata = $uni_result['metadata'];
		$result = array();
		
		// REWIND - сначало перемотаем в начало
		// _cursor_database + array/db-row -> вернёт false, т.к. в data уже находятся конечные данные
		$call_result = call_user_func_array($metadata["cursor()"], array(&$tree, 0, 'rewind'));
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.": {$metadata['cursor()']}(rewind) = ", $call_result);
		
		// вернули данные? значит курсор указывал на одно значение, а не список значений
		if(is_array($call_result)) {
			$result = $call_result;
		} 
			
		// если перемоталось успешно, то начинаем запрашивать данные
		elseif($call_result) {

			// вытащим все данные
			global $__uni_prt_cnt;
			for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

				// NEXT
				$call_result = call_user_func_array($metadata['cursor()'], array(&$tree, 0, 'next', $__uni_prt_cnt));
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.": {$metadata['cursor()']}(next,$__uni_prt_cnt) = ", $call_result);
					
				// в случии с пустым массивом или false - делать нечего, заканчиваем извлечение
				if(empty($call_result))
					break;

				if($prt_cnt == 0)
					$result = $call_result;
				else
					$result['data'] += $call_result['data'];
			}
		}
		
		// перемотка неуспешная
		else {
			trigger_error(__FUNCTION__.": {$uni_result['metadata']['cursor()']}(REWIND) == false!");
		}
		
		// вернём обратно error_reporting
		if(isset($old_error_reporting_level))
			error_reporting($old_error_reporting_level);
			
		// все данные вытащены, возвращаем их
		return $result['data'];
		
	} // cursor()
	
	// вернём обратно error_reporting
	if(isset($old_error_reporting_level))
		error_reporting($old_error_reporting_level);
			
	return $uni_result['data'];
}

// тот-же uni(), но с указанием стартовых данных
// $metadata = array('<data_type>', 'pos()' => 1, 'key()' => 1, ...)
// $data_type - параметр остался для совместимости
function __uni_with_start_data($data, $data_type, $metadata, $unipath) {

	if(empty($unipath) and $unipath !== 0) {
		$stacktrace = debug_backtrace();
		trigger_error('UniPath.'.__FUNCTION__.': unipath is empty! ('.$stacktrace[1]['function'].')', E_USER_NOTICE);
		return array('data' => $data, 'metadata' => $metadata);
	}

	// возможно, просто просят взять определённое поле без присвоения? - тогда оптимизируем
	if(!empty($GLOBALS['__uni_optimize']) and func_num_args() < 5 and !empty($unipath) and !is_object($data) and ($unipath[0] == '/' or !is_array($metadata) or empty($metadata['cursor()']))) {
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.": optimization...");
		// простейшие варианты
		if($unipath == '.')
			return array('data' => $data, 'metadata' => $metadata);
			
		if($unipath == 'key()' and is_array($metadata) and isset($metadata['key()'])) 
			return array(
				'data' => $metadata['key()'], 
				'metadata' => array(gettype($metadata['key()'])));
			
		// более сложные
		$unipath_len = strlen($unipath);
		if(strspn($unipath, 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789') == $unipath_len) 
			$field_name = $unipath;
		elseif($unipath_len > 4 and $unipath[0] == '.' and $unipath[1] == '/'
			and strpos('\'"`', $unipath[2]) !== false 
			and strpos($unipath, $unipath[2], 3)+1 == $unipath_len)
			$field_name = substr($unipath, 3, $unipath_len-4);
		elseif($unipath[0] == '/' and strspn($unipath, 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789', 1) == $unipath_len - 1) {
			$field_name = substr($unipath, 1);
			$data = $metadata = null;
		}

		if(isset($field_name)) {
		
			// теперь вытаскиваем значение из переданной $data
			if(is_array($data) and array_key_exists($field_name, $data))
				return array(
					'data' => $data[$field_name], 
					'metadata' => array(gettype($data[$field_name])));

			// если не передали $data, то вытаскиваем из $GLOBALS
			if(is_null($data) and is_null($data_type) and array_key_exists($field_name, $GLOBALS)) {
				global $GLOBALS_metadata;
				return array(
					'data' => $GLOBALS[$field_name], 
					'metadata' => array(isset($GLOBALS_metadata[$field_name]) 
						? $GLOBALS_metadata[$field_name] 
						: 'null'));
			}
		
			// неоткуда вытаскивать, возврщаем NULL
// 			return array('data' => null, 'metadata' => array('NULL'));
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.": failed");
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
	$tree = __uni_parseUniPath($unipath, $data, $data_type, $metadata);

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
		if(isset($tree_node['metadata']['cursor()'])) {

			if(function_exists($tree_node['metadata']['cursor()']))
				call_user_func_array($tree_node['metadata']['cursor()'], array(&$tree, $lv, 'set', $set_value));
			else
				trigger_error('UniPath.'.__FUNCTION__.': '.$tree_node['metadata']['cursor()'].' - not defined! *** ', E_USER_ERROR);
			
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
	
			$metadata = $tree[$i]['metadata'] or print_r($tree[$i]);

			// этот шаг является просто копией другого шага
			// по этому переключаемся на него
			if(isset($metadata['this_step_is_copy_of_step'])) {
				if($metadata['this_step_is_copy_of_step'] > $i)
					trigger_error('UniPath: this_step_is_copy_of_step > current_lv!', E_USER_ERROR);
// var_dump(" === $i -> ".$metadata['this_step_is_copy_of_step'].' ===');
				$i = $metadata['this_step_is_copy_of_step'];
// 				$metadata = isset($tree[$i]['data_tracking']) ? $tree[$i]['data_tracking'] : array();
			}
// var_dump("=== $i ==="); print_r($tree[$i]);			

			// если это cursor()
			if(isset($metadata['cursor()'])) {
				if(function_exists($metadata['cursor()'])) {
					$tmp_tree = array($tree[$i]);
					call_user_func_array($metadata['cursor()'], array(&$tmp_tree, 0, 'set', $tree[$prev_i]['data'], $tree[$prev_i]['metadata'][0], 
					$tree[$prev_i]['metadata']));
				}
				else
					trigger_error('UniPath.'.__FUNCTION__.': function '.$metadata['cursor()'].'() not defined! *** ', E_USER_ERROR);
					
				break; // прерываем цикл, обработчик cursor() дальше сам справился
			}
			
			// array[key()] = ...
			elseif(isset($tree[$prev_i]['metadata']['key()'])) {
// var_dump('key = '.$tree[$prev_i]['data_tracking']['key()']);
				$tree[$i]['data'][$tree[$prev_i]['metadata']['key()']] = $tree[$prev_i]['data'];
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
			
			$tree[$lv]['data'] = $call_result['data'];
			$tree[$lv]['data_type'] = $call_result['data_type'];
			if(isset($call_result['data_tracking']))
				$tree[$lv]['data_tracking'] = $call_result['data_tracking'];
		}
	} */

	// если включен benchmark
	isset($GLOBALS['unipath_benchmark']) and $__uni_benchmark[$unipath] += microtime(true) - $mark1;
	
	if(array_key_exists('data', $tree_node) == false) {
		trigger_error("UniPath.".__FUNCTION__.": no data to return!\n".print_r($tree_node,true));
		return array('data' => null, array('null'));
	}
	
	return $tree_node;
}

class Uni extends ArrayIterator {
	public $tree = array(); // разобранное и выполненое дерево текущего unipath
	public $data = null; // текущие данные последнего узла в дереве
	public $metadata = array('null');

	function __construct($unipath_or_data, $metadata = null) {
	
		// попросили пустой объект
		if(func_num_args() == 0) {
			return $this;
		}
	
		// если передали данные, то обернём их в объект
		elseif(func_num_args() > 1 or is_string($unipath_or_data) == false) {
			$this->tree = array(
				array(
					'name' => '', 
					'data' => $unipath_or_data,
					'metadata' => isset($metadata) ? $metadata : array(gettype($unipath_or_data) . (is_object($unipath_or_data) ? '/'.get_class($unipath_or_data) : '')),
					'unipath' => null)
			);
		} 
		
		// если передали путь, то выполним его
		else {
			// разберём путь в дерево
			$this->tree = __uni_parseUniPath($unipath_or_data);
		
			// выполним дерево
			$this->tree = __uni_evalUniPath($this->tree);
		}
		
		// будем работать с последним узлом т.к. там конечный рзультат
		$lv = count($this->tree)-1;
		$this->unipath = $this->tree[$lv]['unipath'];
		$this->data =& $this->tree[$lv]['data'];
		$this->metadata =& $this->tree[$lv]['metadata'];
	}
	
	static function fromCursor($tree, $lv) {
		$result = new self();
		$result->tree = array();
		for($i = 0; $i <= $lv; $i++)
			$result->tree[] =& $tree[$i];
		$result->unipath = $tree[$lv]['unipath'];
		$result->data =& $tree[$lv]['data'];
		$result->metadata =& $tree[$lv]['metadata'];
		return $result;
	}
	
	function rewind() { 
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni->".__FUNCTION__.'()');

		// просят внутри использовать другой error_reporting режим
		if (!empty($GLOBALS['unipath_error_reporting'])) {
			$old_error_reporting_level = error_reporting();
			error_reporting($GLOBALS['unipath_error_reporting']);
		}

		// cursor()
		if(isset($this->metadata['cursor()'])) {
			
			// удалим результыты последнего обхода
			unset($this->current_cursor_result);

			// теперь перемотаем в начало
			/*$tree_node = array(
				'data' => & $this->data,
				'data_type' => & $this->data_type,
				'data_tracking' => & $this->data_tracking,
				'unipath' => $this->unipath
			);	*/		
			$call_result = call_user_func_array($this->metadata['cursor()'], 
				array(&$this->tree, count($this->tree)-1, 'rewind'));
			
			// вернём обратно error_reporting
			isset($old_error_reporting_level) 
				and error_reporting($old_error_reporting_level);
			
			return $call_result;
		}
		
		// вернём обратно error_reporting
		isset($old_error_reporting_level) 
			and error_reporting($old_error_reporting_level);
		
		// normal data
		if(is_array($this->data))
			return reset($this->data);
		
		return true;
	}
	
	function current() { 
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni->".__FUNCTION__.'()');
		// cursor()
		if(isset($this->current_cursor_result)) {
if(!empty($GLOBALS['unipath_debug']))  var_dump('Uni->current(): cursor()');

			if(array_key_exists('cursor()', $this->current_cursor_result['metadata']) != false)
				return new Uni(
					$this->current_cursor_result['data'], 
					$this->current_cursor_result['metadata']);
			else
				return $this->current_cursor_result['data'];
		} 
		elseif(property_exists($this, 'current_cursor_result')) {
			return null;
		}
		
		// normal data
		$result = current($this->data);
		return $result;
	}
	
	function key() { 
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni->".__FUNCTION__.'()');
		// cursor()
		if(isset($this->metadata['cursor()'])) {
			if(isset($this->current_cursor_result['metadata']['key()']))
				return $this->current_cursor_result['metadata']['key()'];
			else 
				return null;
		}
		
		// normal data
		if(is_array($this->data))
			return key($this->data);
		else
			return 0;
	}
	
	function next() { 
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni->".__FUNCTION__.'()');
		// cursor()
		if(isset($this->metadata['cursor()'])) {

			$call_result = call_user_func_array($this->metadata['cursor()'], 
				array(&$this->tree, count($this->tree)-1, 'next'));

			if(empty($call_result) or empty($call_result['data']))
				$this->current_cursor_result = null;
			else {
				$data = reset($call_result['data']);
				$this->current_cursor_result = array(
					'data' => $data, 
					'metadata' => isset($call_result['metadata']['each_metadata'], $call_result['metadata']['each_metadata'][key($call_result['data'])])
						? $call_result['metadata']['each_metadata'][key($call_result['data'])]
						: array(gettype($data), 'key()' => null));
			}
			return $this->current();
		}
	
		if(is_array($this->data))
			return next($this->data);
		else
			return false;
	}
	
	function valid() { 
if(!empty($GLOBALS['unipath_debug'])) var_dump("Uni->".__FUNCTION__."(), property_exists(\$this->current_cursor_result) = ".(property_exists($this, 'current_cursor_result')?'true':'false').', cursor() = '.$this->metadata['cursor()']);
		// cursor() - сразу возмём следующую строку
		if(isset($this->metadata['cursor()'])) {
		
			// ещё не начинали обход - запустим сами
			if(property_exists($this, 'current_cursor_result') == false)
				$this->next();

			return empty($this->current_cursor_result) == false;
		}
		
		// normal data
		if(is_array($this->data))
			$key = key($this->data);
		else
			$key = false;
			
		return ($key !== NULL && $key !== FALSE); 
	}
	
	function count() { 
if(!empty($GLOBALS['unipath_debug']))  var_dump("Uni->".__FUNCTION__.'()');
		// cursor()
		if(isset($this->metadata['cursor()'])) {
			trigger_error('Uni->count(): cursor() not countable!');
			return 0;
		}
	
		// normal data
		if(is_array($this->data))
			return count($this->data);
		else
			return 1;
	}
	
	function offsetExists($offset_as_unipath) { return true; }
	function offsetUnset($offset_as_unipath) { return true; }
	
	function offsetSet($offset_as_unipath, $set_value) {
	
		// просят внутри использовать другой error_reporting режим
		if (!empty($GLOBALS['unipath_error_reporting'])) {
			$old_error_reporting_level = error_reporting();
			error_reporting($GLOBALS['unipath_error_reporting']);
		}

		// (кастыль) для строки из базы нужно дозаполнить metadata
		if (strpos($this->metadata[0], '/db-row-value') > 0 or strpos($this->metadata[0], '/db-row') > 0) {
			$lv = count($this->tree)-1;
			$this->metadata['cursor()'] = $this->tree[$lv-1]['metadata']['cursor()'];
			$this->metadata['db'] = $this->tree[$lv-1]['metadata']['db'];
			$this->metadata['columns'] = $this->tree[$lv-1]['metadata']['columns'];
			$this->metadata['tables'] = $this->tree[$lv-1]['metadata']['tables'];
		}
	
		$result = __uni_with_start_data(
			$this->data, 
			$this->metadata[0],
			$this->metadata,
			$offset_as_unipath,
			$set_value
			);
		
		// вернём обратно error_reporting
		if(isset($old_error_reporting_level))
			error_reporting($old_error_reporting_level);
		
		return $result;
	}
	
	function offsetGet($offset_as_unipath) {
	
		// просят внутри использовать другой error_reporting режим
		if (!empty($GLOBALS['unipath_error_reporting'])) {
			$old_error_reporting_level = error_reporting();
			error_reporting($GLOBALS['unipath_error_reporting']);
		}
	
		$uni_result = __uni_with_start_data(
			$this->data, 
			$this->metadata[0],
			$this->metadata,
			$offset_as_unipath
			);

		// вернём обратно error_reporting
		if(isset($old_error_reporting_level))
			error_reporting($old_error_reporting_level);
			
		if(array_key_exists('cursor()', $uni_result['metadata']) != false)
			return new Uni($uni_result['data'], $uni_result['metadata']);
		else
			return $uni_result['data'];
	}
	
	function __call($name, $arguments) {
		trigger_error("Uni->$name() not exists! (".print_r($argumnets, true).')', E_USER_ERROR);
	}
	
	static public function __callStatic($uni_func_name, $arguments) {
		if(function_exists("_uni_{$uni_func_name}") == false) {
			trigger_error('Uni::'.__FUNCTION__."(): function _uni_{$uni_func_name}() does not exist!", E_USER_ERROR);
			return null;
		}

		// если один аргумент, то всё просто
		$tree = array(
			array('name' => '?start_data?', 
				  'data' => $arguments[0], 
				  'metadata' => array(gettype($arguments[0]))), 
			array('name' => $uni_func_name.'()', 
				  'data' => null));
		
	
		// если несколько, то надо их передать через $GLOBALS
		if(count($arguments) > 1) {
			$tree[1]['name'] = array();
			
			for($i = 1; $i < count($arguments); $i++) {
				switch(gettype($arguments[$i])) {
					case 'string':
						$tree[1]['name'][] = "```$arguments[$i]```";
						break;
					case 'number':
						$tree[1]['name'][] = $arguments[$i];
						break;
					default:
						for($tmp_num = 1; $tmp_num < 10000; $tmp_num++) {
							if(isset($GLOBALS["unipath_tmp_var$tmp_num"])) continue;
							
							$GLOBALS["unipath_tmp_var$tmp_num"] = $arguments[$i];
							$tree[1]['name'][] = "/unipath_tmp_var$tmp_num";
							break;
						}
				}
			}
					
			$tree[1]['name'] = $uni_func_name.'('.implode(', ', $tree[1]['name']).')';
		}

		$result = call_user_func("_uni_{$uni_func_name}", $tree, 1);
	
		return isset($result['data']) ? $result['data'] : null;
	}
}

abstract class UniPathExtension { 
	public $tree;
	public $lv;
	public $name;
	public $filter;
	public $data;
	public $metadata;

	function __invoke(&$tree, $lv = 0, $cursor_cmd = '', $tree_node1 = null, $tree_node_datatype = null, $tree_node_metadata = null) {
if(/*$cursor_cmd != 'next' or */!empty($GLOBALS['unipath_debug']))
var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($tree_node1)&&isset($tree_node1['name'])?$tree_node1['name']:'')); 

		if ($cursor_cmd == 'eval') {
			$this->tree = $tree;
			$this->lv = $lv;
			$this->name = &$tree[$lv]['name'];
			$this->metadata = &$tree[$lv]['metadata'];
			
			if (isset($tree[$lv]['filter']))
				$this->filter =&  $tree[$lv]['filter'];
			else 
				unset($this->filter);
			
			if (isset($tree[$lv]['data']))
				$this->data = &$tree[$lv]['data'];
			else 
				unset($this->data);
			
			return $this->evalute($tree_node1);
		}
		
		if ($cursor_cmd == 'rewind') {
			$this->metadata = &$tree[$lv]['metadata']
			;
			return $this->rewind();
		}
		
		if ($cursor_cmd == 'next') {
			$this->metadata = &$tree[$lv]['metadata'];
			
			return $this->next($tree_node1);
		}
		
		if ($cursor_cmd == 'set') {
			$this->metadata = &$tree[$lv]['metadata'];
			
			return $this->set($tree_node1, $tree_node_metadata);
		}
	}
	
	abstract function evalute($tree_node);

	abstract function rewind();
	
	abstract function next($count);
	
	abstract function set($value, $metadata = null, $is_unset = false);
}

// главная функция (сердце UniPath)
function __uni_evalUniPath($tree) {
	global $GLOBALS_metadata, $GLOBALS_data_timestamp;

if(!empty($GLOBALS['unipath_debug'])) echo "\n**** ".$tree[count($tree)-1]['unipath']." ****\n";

	if(count($tree) > 100)
		trigger_error('UniPath.__uni_evalUniPath: Too many steps! - '.count($tree), E_USER_ERROR);

	// Главный цикл
	for($lv = 1; $lv < count($tree); $lv++) {
		$name = isset($tree[$lv]['name']) ? strval($tree[$lv]['name']) : '';
		$filter = empty($tree[$lv]['filter']) ? array() : $tree[$lv]['filter'];
		$prev_data_type = $lv > 0 && isset($tree[$lv-1]['metadata'][0]) 
				? $tree[$lv-1]['metadata'][0] 
				: '';

if(!empty($GLOBALS['unipath_debug'])) { 
	// выводим конец предыдушего шага
	if($lv > 0) {
		echo "<br>------------<br>\n";
		if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data']) and isset($tree[$lv-1]['data']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS']['GLOBALS'])) 
			print_r(array_merge($tree[$lv-1], array('data' => '*** $GLOBALS ***')));
		else
			print_r($tree[$lv-1]);
	}

	// начало следующего шага
	echo "<br>--- $lv ---<br>\n";
	if(isset($tree[$lv]['data']) and isset($tree[$lv]['data']['GLOBALS'], $tree[$lv]['data']['GLOBALS']['GLOBALS'], $tree[$lv]['data']['GLOBALS']['GLOBALS']['GLOBALS'])) 
		print_r(array_merge($tree[$lv], array('data' => '*** $GLOBALS ***')));
	else
		print_r($tree[$lv]);
}

		// *** cursor() ***
		if($lv > 0 and isset($tree[$lv-1]['metadata']) and isset($tree[$lv-1]['metadata']['cursor()'])) {
	
			$call_result = call_user_func_array($tree[$lv-1]['metadata']['cursor()'], array(&$tree, $lv-1, 'eval', $tree[$lv]));

			// если ответ пришёл нормальный, то переходем к следующему узлу
			if(is_array($call_result)) {
				$tree[$lv] = array_merge($tree[$lv], $call_result);
				continue; 
			} else {
// var_dump("*** cursor(): ", $call_result);
			}
		}
		
		// data_type должен быть обязательно
		if($lv > 0 and !isset($tree[$lv-1]['metadata'][0]) and empty($current_tree_node_already_evaluted))
			trigger_error("UniPath.__uni_evalUniPath: no data_type set on step #$lv! \n".print_r($tree[$lv-1], true), E_USER_ERROR);

		// начинаем обрабатывать step
/*		if(empty($name) and $lv == 0) {
// 			var_dump('absolute path start...'); 
		}*/

		// /Class/... если начинается с названия класса
		if($lv == 1 and is_string($name) and class_exists($name) and ! array_key_exists($name, $GLOBALS)) {
			$tree[$lv]['data'] = $name;
			$tree[$lv]['metadata'] = array('class', 'key()' => $name);
		}
		
		// /CONST/... если начинается с названия константы
		elseif($lv == 1 and is_string($name) and defined($name) and ! array_key_exists($name, $GLOBALS)) {
			$tree[$lv]['data'] = constant($name);
			$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']), 'key()' => $name);
		}
		
		// <PDO-object/odbc-link/mysql-link/mysqli-object>/<table_name>[...]
		// TODO cache()
		elseif($lv > 0 and is_string($name)
			and in_array($prev_data_type, array('object/PDO', 'resource/odbc-link', 'resource/mysql-link', 'object/mysqli')) 
			|| ($prev_data_type == 'object' && get_class($tree[$lv-1]['data']) == 'mysqli')
			and strpos($name, 'last_error(') === false
			and strpos($name, 'last_affected_rows(') === false
			and strpos($name, 'last_insert_id(') === false
			and strpos($name, 'sql_table_prefix(') === false
			and !in_array($name, array('.', '..'))) {
			
				$db = $tree[$lv-1]['data'];
				$metadata = array('tables' => array(), 'columns' => array());
				$table_prefix = isset($tree[$lv-1]['metadata']['table_prefix']) ? $tree[$lv-1]['metadata']['table_prefix'] : '';

				// создадим сразу карту алиасов-таблиц
				$aliases = array();
				for($i = $lv; $i < count($tree); $i++) {
					// TODO cache() detection
				
					$suffix_len = $i > $lv ? strspn($tree[$i]['name'], '+,', 0) : 0;
					if($i > $lv and $suffix_len == 0) break;
					
					if(strlen($tree[$i]["name"]) >= (6+$suffix_len) and substr_compare($tree[$i]["name"], 'alias(', $suffix_len, 6, true) === 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]["name"]);
						if($args_types[0] != 'unipath')
							$aliases[$args[1]] = $table_prefix.$args[0];
						else {
							$uni_result = __uni_with_start_data(
								$tree[$lv]['data'], 
								$tree[$lv]['metadata'][0], 
								$tree[$lv]['metadata'], 
								$args[0]);
							if(is_array($uni_result['data']) == false)
								$aliases[$args[1]] = $uni_result['data'];
							else
								trigger_error("UniPath.".__FUNCTION__.": array result in first argument for function alias() not supported! (".print_r($args[0], true).")", E_USER_ERROR);
						}
					} 
					// table_name-as-alias_name
					elseif (stripos($tree[$i]["name"], '-as-') !== false) {
						$args = array(
							substr($tree[$i]["name"], 0, stripos($tree[$i]["name"], '-as-')), 
							substr($tree[$i]["name"], stripos($tree[$i]["name"], '-as-')+4));
						$aliases[$args[1]] = $table_prefix.ltrim($args[0], '+,');
					}
					else
						$aliases[$table_prefix.ltrim($tree[$i]["name"], '+,')] = $table_prefix.ltrim($tree[$i]["name"], '+,');
				}
				
				// FROM ... LEFT JOIN ... WHERE ...
				$sql_join = "";
				$sql_where = "";
				$sql_from_binds = array(); $sql_join_binds = array();
				for($i = $lv; $i < count($tree); $i++) {
					$suffix_len = $i > $lv ? strspn($tree[$i]['name'], '+,', 0) : 0;
					if($i > $lv and $suffix_len == 0) break;
					else $lv = $i; // передвигаем указатель текущего узла
					
					
					$separator = substr($tree[$i]['name'], 0, $suffix_len);
					
					$filter = isset($tree[$i]["filter"]) 
						? $tree[$i]["filter"]
						: array();
					$expr = isset($filter['start_expr']) ? $filter['start_expr'] : 'expr1';
					$curr_braket_level = 0;

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
								if(in_array(strpos(strtoupper($filter[$expr]['left']), 'LIKE('), array(0,1))) {
									list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['left']);
									
									if (count($args) < 2) trigger_error(__FUNCTION__.': Not found 1 and/or 2 argument in '.$filter[$expr]['left']);
									
									$filter[$expr]['left_sql'] = $args[0].
										(strtoupper($filter[$expr]['left'][0]) == 'I' ? " ILIKE " : " LIKE ").
										($args_types[1] == 'string-with-N' ? 'N' : '').
										"'{$args[1]}'";
										
									// незабываем про префикс таблицы
									if(!empty($table_prefix) 
										and ($dot_pos = strpos($args[0], "."))
										and array_key_exists($table_prefix.substr($args[0], 0, $dot_pos), $aliases))
										$filter[$expr]['left_sql'] = $table_prefix.$filter[$expr]['left_sql'];
								} 
								// кастыль для MS SQL Server
								elseif(substr_compare($filter[$expr]['left'], 'like2(', 0, 5, true) == 0) {
									$filter[$expr]['left_sql'] = iconv('UTF-8', 'WINDOWS-1251', preg_replace("~like2\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['left']));
								} else
									$filter[$expr]['left_sql'] = $filter[$expr]['left'];
								break;
								
							// поле таблицы
							case 'name':
								// добавить ли префикс таблицы?
								if(!empty($table_prefix) 
									and ($dot_pos = strpos($filter[$expr]['left'], "."))
									and array_key_exists($table_prefix.substr($filter[$expr]['left'], 0, $dot_pos), $aliases)
									) {
									$filter[$expr]['left_sql'] = $table_prefix.$filter[$expr]['left'];
								} else
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
							case 'list-of-number':
								$filter[$expr]['right_sql'] = "(".implode(',',$filter[$expr]['right']).")";
								switch($filter[$expr]['op']) {
									default:
									case '=': $filter[$expr]['op'] = 'IN'; break;
									case '<>':
									case '!=': $filter[$expr]['op'] = 'NOT IN'; break;
								}
								break;
							case 'list-of-string':
							case 'list-of-string-with-N':
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
										$filter[$expr]['right_sql'] = "('".implode("','",
											array_map(create_function('$a', 'return str_replace("\'","\'\'", trim($a));'),
											$filter[$expr]['right']))."')";
								}
								switch($filter[$expr]['op']) {
									default:
									case '=': $filter[$expr]['op'] = 'IN'; break;
									case '<>':
									case '!=': $filter[$expr]['op'] = 'NOT IN'; break;
								}
								break;
							case 'expr':
								$filter[$expr]['right_sql'] = $filter[$filter[$expr]['right']]['sql'];
								break;
							case 'function':
								if(in_array(strpos(strtoupper($filter[$expr]['right']), 'LIKE('), array(0,1), true)) {
									list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['right']);
									$filter[$expr]['right_sql'] = $args[0].
										(strtoupper($filter[$expr]['right'][0]) == 'I' ? " ILIKE " : " LIKE ").
										($args_types[1] == 'string-with-N' ? 'N' : '').
										"'{$args[1]}'";

									// незабываем про префикс таблицы
									if(!empty($table_prefix) 
										and ($dot_pos = strpos($args[0], "."))
										and array_key_exists($table_prefix.substr($args[0], 0, $dot_pos), $aliases))
										$filter[$expr]['right_sql'] = $table_prefix.$filter[$expr]['right_sql'];
								} 
								elseif(substr_compare($filter[$expr]['right'], 'sql_query(', 0, 10, true) == 0) {
									list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['right']);
									$filter[$expr]['right_sql'] = '('.$args[0].')';
									$filter[$expr]['op'] = 'IN';
								} 
								elseif(substr_compare($filter[$expr]['right'], 'sql(', 0, 4, true) == 0) {
									list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['right']);
									$filter[$expr]['right_sql'] = $args[0];
								}
								// кастыль для MS SQL Server
								elseif(substr_compare($filter[$expr]['right'], 'like2(', 0, 5, true) == 0) {
									$filter[$expr]['right_sql'] = iconv('UTF-8', 'WINDOWS-1251', preg_replace("~like2\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['right']));
								} 
								else
									$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'name':
								if(in_array($filter[$expr]['right'], array('NULL', 'Null', 'null'))) {
									$filter[$expr]['right_sql'] = 'NULL';
									$filter[$expr]['op'] = $filter[$expr]['op'] == '=' ? 'IS' : 'IS NOT';
								} elseif(!empty($table_prefix) // добавить ли префикс таблицы?
									and ($dot_pos = strpos($filter[$expr]['right'], "."))
									and array_key_exists($table_prefix.substr($filter[$expr]['right'], 0, $dot_pos), $aliases)
									) {
									$filter[$expr]['right_sql'] = $table_prefix.$filter[$expr]['right'];
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
											$filter[$expr]['right_sql'][] = "'".mysql_real_escape_string($val)."'";
											break;
										case 'resource/odbc-link':
										default:
											$filter[$expr]['right_sql'][] = "'".str_replace("'","''",$val)."'";
									}
									$filter[$expr]['right_sql'] = '(' . implode(',', $filter[$expr]['right_sql']) . ')';
									$filter[$expr]['op'] = 'IN';
								} 
								elseif(is_null($value)) {
											$filter[$expr]['right_sql'] = 'NULL';
											$filter[$expr]['op'] = $filter[$expr]['op'] == '=' ? 'IS' : 'IS NOT';
										}
								else
									$filter[$expr]['right_sql'] = strval($value);
								break;
							default:
								trigger_error("UniPath.".__FUNCTION__.": right part of expression is invalid in $name. \n".print_r($filter[$expr], true));
						}
						
						// op
						if(isset($filter[$expr]['sql']) == false) {
						
							// ( - открывающая скобка
							if($filter[$expr]['braket_level'] > $curr_braket_level) {
								$filter[$expr]['sql'] = str_repeat('(', $filter[$expr]['braket_level'] - $curr_braket_level);
								$curr_braket_level = $filter[$expr]['braket_level'];
							}
							else
								$filter[$expr]['sql'] = '';
							
							if(!isset($filter[$expr]['left'])) {
								// $filter[$expr]['sql'] = '';
							}
							elseif(isset($filter[$expr]['op']) and $filter[$expr]['op'] == 'left_eval') {
								$filter[$expr]['sql'] .= $filter[$expr]['left_sql'];
							}
							elseif(isset($filter[$expr]['op'])) {
								$filter[$expr]['sql'] .= "{$filter[$expr]['left_sql']} {$filter[$expr]['op']} ".(!isset($filter[$expr]['right_sql'])?'null':$filter[$expr]['right_sql']);
								
								if(!isset($filter[$expr]['right_sql'])) {
									$unipath_tag = mb_substr($tree[$lv]['unipath'], mb_strlen($tree[$lv-1]['unipath'], "iso-8859-1"));
									trigger_error(__FUNCTION__.": right_sql is not set in filter of `$unipath_tag`!\n".print_r($filter[$expr], true));
								}
							} 
							else
								$filter[$expr]['sql'] .= $filter[$expr]['left_sql'];
								
							// ) - закрывающая скобка
							if($filter[$expr]['braket_level'] < $curr_braket_level) {
								$filter[$expr]['sql'] .= str_repeat(')', $curr_braket_level - $filter[$expr]['braket_level']);
								$curr_braket_level = $filter[$expr]['braket_level'];
							}
						}

						// next
						$last_expr = $expr;
						$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
						
						// закроем скобки в конце выражения
						if (!$expr and $filter[$last_expr]['braket_level'] > 0) {
							$filter[$last_expr]['sql'] .= str_repeat(')', $filter[$last_expr]['braket_level']);
						}
					}
					
					// сохраним table => filter
					if($suffix_len > 0)
						$metadata['tables'][substr($tree[$i]["name"], $suffix_len)] = $filter;
					else
						$metadata['tables'][$tree[$i]["name"]] = $filter;

					// alias()
					if(strlen($tree[$i]["name"]) >= 6+$suffix_len and substr_compare($tree[$i]["name"], 'alias(', $suffix_len, 6, true) === 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]["name"]);
						$tbl_name = $aliases[$args[1]]." AS {$args[1]}"; 
					} 
					
					// table_name-as-alias_name
					elseif (stripos($tree[$i]["name"], '-as-') !== false) {
						$args = array(0, substr($tree[$i]["name"], stripos($tree[$i]["name"], '-as-')+4));
						$tbl_name = $aliases[$args[1]]." AS {$args[1]}"; 
					}
					
					// table1
					else {
						$tbl_name = $suffix_len > 0 
							? $table_prefix.substr($tree[$i]["name"], $suffix_len) 
							: $table_prefix.$tree[$i]["name"];
					}

					// FROM ... / JOIN ...
					if($suffix_len == 0) {
						$sql_join = $tbl_name; // FROM ...
						$sql_where = (isset($last_expr) and !empty($filter[$last_expr]['sql'])) ? "WHERE ".$filter[$last_expr]['sql'] : "";
					} else
					if(empty($filter[$last_expr]['sql']))
						$sql_join .= " NATURAL JOIN $tbl_name";
					elseif(strncmp($tree[$i]["name"], '++', 2) == 0)
						$sql_join .= " LEFT OUTER JOIN $tbl_name ON ".$filter[$last_expr]['sql'];
					elseif(strncmp($tree[$i]["name"], '+', 1) == 0)
						$sql_join .= " LEFT JOIN $tbl_name ON ".$filter[$last_expr]['sql'];
					else {
						$sql_join .= " INNER JOIN $tbl_name ON ".$filter[$last_expr]['sql'];
						trigger_error(__FUNCTION__.'(db): Unknown modifier - '.$tree[$i]["name"], E_USER_NOTICE);
					}
				}
				
				// SELECT ... GROUP BY ... ORDER BY ... LIMIT...
				$sql_select = "*";
				$sql_group_by = "";
				$sql_order_by = "";
				$sql_limit = "";
				$correct_lv = $lv;
				for($i = $lv+1; $i < count($tree); $i++) {
					if(strlen($tree[$i]['name']) >= 9 and substr_compare($tree[$i]['name'], 'order_by(', 0, 9, true) == 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
						foreach($args as $key => $val) {
							if($args_types[$key] == 'unipath')
								$val = uni($val);
							if(!empty($sql_order_by))
								$sql_order_by .= ', '.$val;
							else
								$sql_order_by = "ORDER BY $val";
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 9 and substr_compare($tree[$i]['name'], 'group_by(', 0, 9, true) == 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
						foreach($args as $key => $val) {
							if(!empty($sql_group_by))
								$sql_group_by .= ', '.$val;
							else
								$sql_group_by = "GROUP BY $val";
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 8 and substr_compare($tree[$i]['name'], 'columns(', 0, 8, true) == 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
// var_dump($args, $tree[$i]['name']);
						$sql_select = '';
						$chunked_array = array();
						foreach($args as $key => $arg) {
							if(substr_compare($arg, 'chunked(', 0, 8, true) == 0) {
								list($chunked_args, $chunked_args_types) = __uni_parseFuncArgs($arg);
//print_r($chunked_args);
								$chunked_array[$chunked_args[0]] = array();
								$col_name = str_replace('.', '_', $chunked_args[0]);
								$chunk_size = isset($chunked_args[2]) ? intval($chunked_args[2]) : 3000;
								for($ii = 0; $ii < ceil($chunked_args[1]/$chunk_size); $ii++) {
									if(isset($chunked_args[3]))
										$sql_select .= "CAST(SUBSTRING({$chunked_args[0]}, ".($ii*$chunk_size+1).", $chunk_size) as {$chunked_args[3]}) AS uni_chunk_{$ii}_$col_name, ";
									else
										$sql_select .= "SUBSTRING({$chunked_args[0]}, ".($ii*$chunk_size+1).", $chunk_size) AS uni_chunk_{$ii}_$col_name, ";
									$chunked_array[$chunked_args[0]]["uni_chunk_{$ii}_$col_name"] = true;
								}
							} 
							// именованыый аргумент? (название поля = тип поля)
							elseif(is_numeric($key) == false) {
								$sql_select .= "$key, ";
								$metadata['columns'][$key] = $arg;
							} 
							else {
								$sql_select .= "$arg, ";
								$metadata['columns'][$arg] = '';
							}
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
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
						if(isset($args[1])) {
							$iconv_from = $args[0];
							$iconv_to = $args[1];
						} else {
							$iconv_from = $args[0];
							$iconv_to = 'UTF-8';
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 4 and substr_compare($tree[$i]['name'], 'top(', 0, 4, true) == 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
						$sql_select = "TOP {$args[0]} $sql_select";
						$correct_lv++;
					} 
					elseif(strlen($tree[$i]['name']) >= 17 and substr_compare($tree[$i]['name'], 'sql_result_cache(', 0, 4, true) == 0) {
						list($args, $args_types) = __uni_parseFuncArgs($tree[$i]['name']);
						if($args_types[0] == 'unipath' and strspn($args[0], '0123456789qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_', 1)+1 == strlen($args[0])) {
							$key = ltrim($args[0], '/$');
							$GLOBALS[$key] = array();
							$metadata['result_cache'] =& $GLOBALS[$key];
 							$GLOBALS[$key.'_unipath_info'] = $args;
							$metadata['result_cache_info'] =& $GLOBALS[$key.'_unipath_info'];
							if(!empty($args['range']))
								$metadata['result_cache_info'] += array(
									'rows_count' => $args['range'][1],
									'rows_start_offset' => $args['range'][0]);
						} else {
							trigger_error('UniPath: only global variables enabled in sql_result_cache()!');
						}
						$correct_lv++;
					} 
					else
						break;
				}
				
				// конструируем окончательно запрос
				$sql_binds = array_merge($sql_join_binds, $sql_from_binds);
				$sql = rtrim("SELECT $sql_select FROM $sql_join $sql_where $sql_group_by $sql_order_by $sql_limit", ' ');
				
				// sql_iconv(...)
				if(isset($iconv_from, $iconv_to)) {
					$sql = iconv($iconv_from, $iconv_to, $sql);
					$sql_where = iconv($iconv_from, $iconv_to, $sql_where);
				}

// var_dump($sql, $sql_binds, isset($asSQLQuery));

				// попросили вернуть сконструированный запрос
				if(isset($asSQLQuery)) {
					$tree[$correct_lv]['data'] = array('sql_query' => $sql, 'sql_params' => $sql_binds);
					$tree[$correct_lv]['metadata'] = array("array/sql-query-with-params");
				} 
				
				// просто таблицу попросили
				// TODO descibe table?
				elseif($sql == "SELECT * FROM $name" and !isset($tree[$lv]['filter'])) {
					$tree[$correct_lv]['data'] = array($db, $name);
					$tree[$correct_lv]['metadata'] = array('array/db-table');
				}
				
				// не выполняем запрос, а сохраняем и возвращаем SQL запрос
				else {
					$tree[$correct_lv]['data'] = array('sql_query' => $sql, 'sql_params' => $sql_binds);;
				
					// сгенерируем и положим информацию для присвоения
					$tree[$correct_lv]['metadata'] = $metadata + array(
						"array/sql-query-with-params",
						'cursor()' => '__cursor_database',
						'db' => $db,
						'db_type' => $prev_data_type == 'object' ? 'object/'.get_class($db) : $prev_data_type,
						'sql_query' => $sql,
						'where' => $sql_where,
						'columns' => array()
						);
				}

				// корректируем уровень (если были /db1/.../order_by()/group_by()/limit()/etc...)
				$lv = $correct_lv;
		}
		
		// next(...), next_with_key(...)
		elseif(strpos($name, 'next(') === 0 || strpos($name, 'next_with_key(') === 0) {
			assert('isset($tree[$lv-1]["metadata"]["cursor()"]);') or var_dump($tree[$lv-1]);
			
			$call_result = call_user_func_array($tree[$lv-1]["metadata"]['cursor()'], array(&$tree, $lv-1, 'next'));
if(!empty($GLOBALS['unipath_debug'])) var_dump("next().\$call_result => ", $call_result);
				
			// если ответ это норамальные данные
			if(is_array($call_result) and !empty($call_result)) {
				$tree[$lv]['data'] = $call_result['data'];
				$tree[$lv]['metadata'] = $call_result['metadata'];
			}
			// иначе ничего нет
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');
			}
			
			// next_with_key()
			if(strpos($name, 'next_with_key(') === 0) {
				/*list($args, $args_types) = __uni_parseFuncArgs($name);
				foreach($args_types as $key => $arg_type) {
					$num = strpos($key, 'arg') === 0 ? intval(substr($key, 3))-1 : $key;
					
					// немного оптимизируем
					if($arg_type == 'unipath' and $args[$key] == '.') 
						$args[$num] = $tree[$lv]['data'];
					
					elseif($arg_type == 'unipath' and $args[$key] == 'key()')
						$args[$num] = isset($tree[$lv]['data_tracking']) 
							&& is_array($tree[$lv]['data_tracking']) 
							&& isset($tree[$lv]['data_tracking']['key()'])
							? $tree[$lv]['data_tracking']['key()']
							: null;
					
					elseif($arg_type == 'unipath') {
						$uni_result = __uni_with_start_data(
							$tree[$lv]['data'], 
							$tree[$lv]['data_type'], 
							empty($tree[$lv]['data_tracking']) ? null : $tree[$lv]['data_tracking'],
							$args[$key]);
						$args[$num] = $uni_result['data'];
					}
				}
				$tree[$lv]['data'] = $args;*/
				
				$tree[$lv]['data'] = array(
					$tree[$lv]['data'], 
					isset($tree[$lv]['metadata']['key()'])
					? $tree[$lv]['metadata']['key()']
					: null
				);
				$tree[$lv]['metadata'] = array('array');
			}
		}
		
		// all(), toArray() [cursor]
		elseif(($name == 'all()' || $name == 'toArray()') and isset($tree[$lv-1]['metadata']['cursor()'])) {
			assert('isset($tree[$lv-1]["metadata"]["cursor()"]);');

			// REWIND - перематаем в начало если это курсор
			$call_result = call_user_func_array($tree[$lv-1]["metadata"]['cursor()'], array(&$tree, $lv-1, 'rewind'));
			
			// вернули данные? значит курсор указывал на одно значение, а не список значений
			if(is_array($call_result)) {
				$tree[$lv]['data'] = $call_result['data'];
				$tree[$lv]['metadata'] = $call_result['metadata'];
			} 
			
			// выбераем все элементы из курсора
			else {
				$tree[$lv]['data'] = array();
				$tree[$lv]['metadata'] = array('array');
				$prev_metadata = $tree[$lv-1]["metadata"];
				
				global $__uni_prt_cnt;
				for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

					// NEXT
					$call_result = call_user_func_array($prev_metadata['cursor()'], array(&$tree, $lv-1, 'next', $__uni_prt_cnt));
					
					// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
					if(empty($call_result))
						break;

					if($prt_cnt == 0) {
						$tree[$lv]['metadata'] = $call_result['metadata'];
						$tree[$lv]['data'] = $call_result['data'];
					} 
					else {
						$tree[$lv]['data'] += $call_result['data'];
						foreach($call_result['metadata'] as $key => $val)
							if(is_array($val) and isset($tree[$lv]['metadata'][$key]))
								$tree[$lv]['metadata'][$key] += $val;
							else
								$tree[$lv]['metadata'][$key] = $val;
					}
					
				}
				
				unset($tree[$lv]['metadata']['cursor()']); // для списка курсор уже не нужен
			} // next
		}
		
		// fs() [local-filesystem]
		elseif($name == 'file://' or $name == 'fs()') {
			$tree[$lv]['data'] = 'file://';
			$tree[$lv]['metadata'] = array('string/local-filesystem', 'key()' => 'file://');
		}
				
		// [local-filesystem] start
		elseif($prev_data_type == 'string/local-filesystem') {
			if($name == '~') {
				$tree[$lv]['data'] = realpath('~');
				$tree[$lv]['metadata'] = array('string/local-directory', 'url' => 'file://'.$tree[$lv]['data'], 'key()' => $name);
			} else
			if($name == '') {
				$tree[$lv]['data'] = '';
				$tree[$lv]['metadata'] = array('string/local-directory', 'url' => 'file://', 'key()' => $name);
			} else
			if($name == '.') {
				$tree[$lv]['data'] = realpath('.');
				$tree[$lv]['metadata'] = array('string/local-directory', 'url' => 'file://'.$tree[$lv]['data'], 'key()' => $name);
			} else {
				$path = '/' . $name;
				$tree[$lv]['data'] = $path;
				$tree[$lv]['metadata'] = array('string/local-filesystem', 'url' => 'file://'.$path, 'key()' => $name);

				if(file_exists($path)) {
					if(is_dir($path))
						$tree[$lv]['metadata'][0] = 'string/local-directory';
					else
					if(is_file($path) or is_link($path)) {
						$tree[$lv]['metadata'][0] = 'string/local-pathname';
						$tree[$lv]['metadata']['cursor()'] = '_cursor_asFile';
					} else
						$tree[$lv]['metadata'][0] = 'string/local-entry';
				}
			};
		}
			
		// [string/local-directory, string/local-entry]
		elseif((strpos($name, '(') === false or strpos($tree[$lv]['unipath'], '`', -1) !== false) and in_array($prev_data_type, array('string/local-directory', 'string/local-entry'))) {
			if($name == '.') $path = realpath($tree[$lv-1]['data'].'/'.$name);
			else $path = $tree[$lv-1]['data'].'/'.$name;

			// пока-что незнаем что это
			$tree[$lv]['data'] = $path;
			$tree[$lv]['metadata'] = array(
				'string/local-entry', 
				'url' => 'file://'.$path, 
				'key()' => $name,
				'cursor()' => '_cursor_asFile');
			
			if(file_exists($path)) {
				if(is_dir($path)) {
					$tree[$lv]['metadata'][0] = 'string/local-directory';
					unset($tree[$lv]['metadata']['cursor()']);
				} else
				if(is_file($path) or is_link($path)) {
// 					if(isset($tree[$lv+1]) and $tree[$lv+1]['name'] == 'asImageFile()') {
						$tree[$lv]['data'] = $path;
						$tree[$lv]['metadata'][0] = 'string/local-pathname';
						$tree[$lv]['metadata']['cursor()'] = '_cursor_asFile';
// 					} else {
// 						$tree[$lv]['data'] = file_get_contents($path);
// 						$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
// 					}
				} else
					trigger_error('UniPath.'.__FUNCTION__.": $name - is not a directory, a file or a link!", E_USER_WARNING);
			}
		}
		
		// Class::<name>()
		elseif(strpos($name, '(') != false and $prev_data_type == 'class') {
			list($args, $args_types) = __uni_parseFuncArgs($name);
			// TODO $args ...
			
			$class_name = $tree[$lv-1]['data'];
			$func_name = substr($name, 0, strpos($name, '('));
			
			// создать объект?
			if($func_name == $class_name or $func_name == '__construct') {
				$func_src = empty($args) ? '' : "\$a['".implode("', \$a['", array_keys($args))."']";
				$func = create_function('$a', "return new $class_name($func_src);");
				$tree[$lv]['data'] = call_user_func($func, $args);
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''));
			}
			
			elseif(is_string($class_name) and !empty($class_name)) {
				$tree[$lv]['data'] = $class_name::$func_name();
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''));
			}
			
			else {
				trigger_error(__FUNCTION__.'(Class::<name>()): '.$tree[$lv-1]["unipath"], E_USER_NOTICE);
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');
			}
// var_dump($tree[$lv], $func_name, $tree[$lv-1]['data']);
		}
		
		// object-><name>()
		elseif(strpos($name, '(') != false and strpos($prev_data_type, 'object') === 0) {
			list($args, $args_types) = __uni_parseFuncArgs($name);
			$func_name = substr($name, 0, strpos($name, '('));

			// TODO type cast to number need
			if(method_exists($tree[$lv-1]['data'], $func_name)) {
				foreach($args_types as $key => $arg_type)
					if($arg_type == 'unipath') {
						$args[$key] = __uni_with_start_data(
							$tree[$lv-1]['data'],
							$tree[$lv-1]['metadata'][0],
							$tree[$lv-1]['metadata'],
							$args[$key]);
						$args[$key] = $args[$key]['data'];
					}
				
				$tree[$lv]['data'] = call_user_func_array(array($tree[$lv-1]['data'], $func_name), $args);
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''));
			} 
			
			// тогда поищем и вызовем _uni_<name>()
			elseif(function_exists("_uni_{$func_name}")) {
				$tree[$lv] = array_merge($tree[$lv], call_user_func_array("_uni_{$func_name}", array(&$tree, $lv)));
			} 
			
			// иначе NULL
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');
			}
// var_dump($tree[$lv-1]['data'], $func_name, method_exists($tree[$lv-1]['data'], $func_name));
		}
		
		// object-><prop>
		elseif(strpos($name, '(') === false and $prev_data_type != 'object/DOMElement' and strpos($prev_data_type, 'object') === 0 and stripos('qwfpgjluyarstdhneizxcvbkm012345679_', $name[0]) !== false) {
			if(property_exists($tree[$lv-1]['data'], $name)) {
				$tree[$lv]['data'] = $tree[$lv-1]['data']->$name;
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''), 'key()' => $name);
			}
		
			// иначе NULL
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');
			}
		}
		
		// asHTML()
		elseif(stripos($name, 'asHTML(') !== false) {
			list($args, $args_types) = __uni_parseFuncArgs($name);
			
			if(!isset($args[0]) and !isset($args['encoding']))
				$encoding = 'UTF-8';
			elseif((isset($args_types['encoding']) ? $args_types['encoding'] : $args_types[0]) == 'unipath')
				$encoding = __uni_with_start_data(
					$tree[$lv-1]["data"], 
					$tree[$lv-1]["metadata"][0], 
					$tree[$lv-1]["metadata"], 
					isset($args['encoding']) ? $args['encoding'] : $args[0]);
			else
				$encoding = isset($args['encoding']) ? $args['encoding'] : $args[0];
				
			$old_value = libxml_use_internal_errors(true);
			libxml_clear_errors();
			$doc = new DOMDocument('1.0');
			if(in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8')))
				$doc->loadHTML($tree[$lv-1]['data']);
			else
				$doc->loadHTML('<?xml version="1.0" encoding="'.$encoding.'" ?>'.$tree[$lv-1]['data']);
				
			foreach(libxml_get_errors() as $error) {
				// xml-фрагмент без корневого узла?
				if($error->message == "Extra content at the end of the document\n")
					$doc->loadXML((in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8'))?'<root>':'<?xml version="1.0" encoding="'.$encoding.'" ?><root>').$tree[$lv-1]['data'].'</root>');
				else
					trigger_error("XMLError: ".trim($error->message).", code: {$error->level}, level: {$error->level}, file: ".(empty($error->file)?'[string]':$error->file).":{$error->line}:{$error->column}");
			}
			libxml_use_internal_errors($old_value);
// 			var_dump(__FILE__.':'.__LINE__, $doc->documentElement);

			$tree[$lv]['data'] = $doc->documentElement;
			$tree[$lv]['metadata'] = array('object/DOMElement');
		}
		
		// asXML()
		elseif(stripos($name, 'asXML(') !== false) {
			list($args, $args_types) = __uni_parseFuncArgs($name);

			if(!isset($args[0]) and !isset($args['encoding']))
				$encoding = 'UTF-8';
			elseif((isset($args_types['encoding']) ? $args_types['encoding'] : $args_types[0]) == 'unipath')
				$encoding = __uni_with_start_data(
					$tree[$lv-1]["data"], 
					$tree[$lv-1]["metadata"][0], 
					$tree[$lv-1]["metadata"], 
					isset($args['encoding']) ? $args['encoding'] : $args[0]);
			else
				$encoding = isset($args['encoding']) ? $args['encoding'] : $args[0];
		
			
			$old_value = libxml_use_internal_errors(true);
			libxml_clear_errors();
			$doc = new DOMDocument('1.0');
			if(in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8')))
				$doc->loadXML($tree[$lv-1]['data']);
			else
				$doc->loadXML('<?xml version="1.0" encoding="'.$encoding.'" ?>'.$tree[$lv-1]['data']);
				
			foreach(libxml_get_errors() as $error) {
				// xml-фрагмент без корневого узла?
				if($error->message == "Extra content at the end of the document\n")
					$doc->loadXML((in_array($encoding, array('utf8', 'utf-8', 'UTF8', 'UTF-8'))?'<root>':'<?xml version="1.0" encoding="'.$encoding.'" ?><root>').$tree[$lv-1]['data'].'</root>');
				else
					trigger_error("XMLError: ".trim($error->message).", code: {$error->level}, level: {$error->level}, file: ".(empty($error->file)?'[string]':$error->file).":{$error->line}:{$error->column}");
			}
			libxml_use_internal_errors($old_value);
// 			var_dump(__FILE__.':'.__LINE__, $doc->documentElement);

			$tree[$lv]['data'] = $doc->documentElement;
			$tree[$lv]['metadata'] = array('object/DOMElement');
		}
		
		// _uni_<name>()
		elseif(strpos($name, '(') != false and sscanf($name, '%[^(]', $src_name)
			and function_exists("_uni_{$src_name}")) {
			$tree[$lv] = array_merge($tree[$lv], call_user_func_array("_uni_{$src_name}", array(&$tree, $lv)));
			
			// если попросили перескочить на другой шаг, перескочим
			if(isset($tree[$lv]['metadata']['jump_to_lv'])) {
				$lv = $tree[$lv]['metadata']['jump_to_lv'] - 1; // for(...$lv++)
			}
		}
		
		// php:*(), php-foreach:*(...), php_*()
		// TODO cursor()
		elseif(strpos($name, '(') > 5 and (strncmp($name, 'php_', 4) == 0 or strncmp($name, 'php:', 4) == 0 or strncmp($name, 'php-foreach:', 12) == 0)) {
		
			$func_name = substr($name, 4, strpos($name, '(')-4);
			list($args, $args_types) = __uni_parseFuncArgs($name);

			// php_<func()> - для совместимости со старым кодом
			if(empty($args) and strncmp($name, 'php_', 4) == 0) {
				$args = array($tree[$lv-1]['data']);
				$tree[$lv]['data'] = call_user_func_array($func_name, $args);
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']));
			}
			
			// php:func(...)
			elseif(strncmp($name, 'php:', 4) == 0) {
				for($i=0; $i < count($args); $i++)
					if($args[$i] == '.') 
						$args[$i] = $tree[$lv-1]['data'];
					elseif($args_types[$i] == 'unipath') {
						$args[$i] = __uni_with_start_data(
							$tree[$lv-1]['data'],
							$tree[$lv-1]['metadata'][0],
							$tree[$lv-1]['metadata'],
							$args[$i]
						);
						$args[$i] = $args[$i]['data'];
					}
					
				$tree[$lv]['data'] = call_user_func_array($func_name, $args);
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']));
			}
			
			// php-foreach:func(...)
			elseif(strncmp($name, 'php-foreach:', 12) == 0) {
				$func_name = substr($name, 12, strpos($name, '(')-12);
				list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
				$tree[$lv]['data'] = array();
				$tree[$lv]['metadata'] = array('array');

				// подготавливаем cursor() если это он
				global $__uni_prt_cnt;
				if(isset($tree[$lv-1]["metadata"]['cursor()'])) {
					$data = new SplFixedArray($__uni_prt_cnt);
					$cursor_ok = call_user_func_array(
						$tree[$lv-1]["metadata"]['cursor()'],
						array(&$tree, $lv-1, 'rewind'));

					if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.'(php-foreach): $cursor_ok = '.var_export($cursor_ok, true));
					
					if($cursor_ok === false) {
						trigger_error(__FUNCTION__.'(php-foreach): '.$tree[$lv-1]["metadata"]['cursor()'].'(rewind) return '.var_export($cursor_ok, true), E_USER_NOTICE);
						return array('data' => null, 'metadata' => array('null'));
					}
					
					// вернули одиночное значение
					elseif($cursor_ok !== true)
						$data = (array) $cursor_ok;
				}
		
				// любой другой тип данных приводим к массиву
				else
					$data = (array) $tree[$lv-1]["data"];

				foreach($data as $key => $item) {

					// если это курсор, то берём следующий элемент
					if(isset($cursor_ok) and $cursor_ok = call_user_func_array($tree[$lv-1]["metadata"]['cursor()'], array(&$tree, $lv-1, 'next')) and isset($cursor_ok['data'][0])) {
						$item = $cursor_ok['data'][0];
						$key = isset($cursor_ok['metadata']['key()'])
							? $cursor_ok['metadata']['key()']
							: $key;
					}
					elseif(isset($cursor_ok))
						break;
					
					$args2 = $args;
					for($i=0; $i < count($args2); $i++)
						if($args2[$i] == '.') 
							$args2[$i] = $item;
						elseif($args_types[$i] == 'unipath') {
							$args2[$i] = __uni_with_start_data(
								$item, null,
								array(gettype($item), 'key()' => $key),
								$args2[$i]
							);
							$args2[$i] = $args2[$i]['data'];
						}

					$tree[$lv]['data'][$key] = call_user_func_array($func_name, $args2);
// var_dump(__FUNCTION__.'(php-foreach): ', $tree[$lv]['data'][$key], $func_name, $args2);

					// сообщим про лимит
					if($i == $__uni_prt_cnt-1 && is_object($data) && $data instanceof SplFixedArray) {
						trigger_error('UniPath.php-foreach: $__uni_prt_cnt excedded! (break data process)', E_USER_WARNING);
					}
				}

				// одиночное значение преобразуем обратно (?)
				if(!is_array($tree[$lv-1]["data"]) and !isset($tree[$lv-1]["metadata"]['cursor()'])) {
					$tree[$lv]['data'] = current($tree[$lv]['data']);
					$tree[$lv]['metadata'][0] = gettype($tree[$lv]['data']);
				}
// var_dump($tree[$lv]['data'], $tree[$lv]['metadata']);
			}
		}
		
		// .[]/...%s...[] - повторная фильтрация данных с шаблоном ключя или без
		elseif(($name == '.' and !empty($tree[$lv]["filter"])) or strpos($name, '%') !== false) {
			$tree[$lv]['data'] = array();
			$tree[$lv]['metadata'] = array($tree[$lv-1]['metadata'][0]);
			$metadata = $tree[$lv-1]['metadata'];

			// вернём себя как курсор если предыдущий cursor()
			if(isset($metadata['cursor()'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array(
					'cursor' . (strpos($tree[$lv]['metadata'][0], '/') !== false 
					? substr($tree[$lv]['metadata'][0], strpos($tree[$lv]['metadata'][0], '/'))
					: ''),
					'cursor()' => '__cursor_filtration', 
					'was_rewinded' => false, // ещё не перемотан источник
					'name' => $tree[$lv]['name'],
					'filter' => isset($tree[$lv]['filter']) ? $tree[$lv]['filter'] : null,
					'tree_node' => & $tree[$lv-1]);
				continue;
			}
			
			// REWIND - перематаем в начало если это курсор
			/*if(isset($data_tracking['cursor()'])) {
				$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'rewind');
				
				$tree[$lv]['data_tracking'] = & $tree[$lv-1]['data_tracking'];
			}*/
			
			$to_filter = null;

			if(strpos($name, '%') !== false)
				$sscanf_format = $name;
			
			global $__uni_prt_cnt;
			for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
				
				// если предыдушие данные надо получать через cursor()
				/* if(isset($data_tracking['cursor()'])) {
			
					$call_result = call_user_func($data_tracking['cursor()'], $tree, $lv-1, 'next', 10);
// var_dump($call_result);
				
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
				else*/ 
				if(!isset($to_filter) and is_array($tree[$lv-1]['data']))
					$to_filter = $tree[$lv-1]['data'];
		
				// string, number...
				elseif(!isset($to_filter) and !is_array($tree[$lv-1]['data'])) {
					$key_for_not_array = isset($tree[$lv-1]['metadata']['key()']) 
						? $tree[$lv-1]['metadata']['key()'] 
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
							$left_result = __uni_with_start_data($data, gettype($data), array(gettype($data), 'key()' => $key), $filter[$expr]['left']);
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
								list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['left']);
								if($args_types[0] == 'unipath')
									$left_result = __uni_with_start_data($data, gettype($data), array(gettype($data)), $args[0]);
								else
									$left_result = $filter[$expr]['left'];
//var_dump($left_result, trim($func_and_args[1], '%'), strpos($left_result, trim($func_and_args[1], '%')));
								$left_result = strpos($left_result, trim($args[1], '%')) !== false;
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
							$right_result = __uni_with_start_data($data, gettype($data), array(gettype($data), 'key()' => $key), $filter[$expr]['right']);
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
						case 'list-of-string':
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
// var_dump($left_result, $right_result, $tree[$lv]['unipath']);
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
						$tree[$lv]['metadata'] = $tree[$lv-1]['metadata'];
						if(isset($tree[$lv-1]['metadata']['this_step_is_copy_of_step']) == false)
							$tree[$lv]['metadata']['this_step_is_copy_of_step'] = $lv-1;
					} 
					else {
						$tree[$lv]['data'] = null;
						$tree[$lv]['metadata'] = array('null');
					}
				}
			} // for(prt_cnt)
		}
		
		// * [array]
		// TODO add filter
		// TODO cursor()
		elseif($name == '*' and (is_array($tree[$lv-1]['data']) || $prev_data_type == 'object/DOMElement')) {
			$tree[$lv]['data'] = array();
			$tree[$lv]['metadata'] = array('array');
			
			// node->childNodes -> array(children)
			if ($prev_data_type == 'object/DOMElement') {
				for($i = 0; $i < $tree[$lv-1]['data']->childNodes->length; $i++)
					$tree[$lv]['data'][] = $tree[$lv-1]['data']->childNodes->item($i);
			}
			else
			foreach($tree[$lv-1]['data'] as $key => $val) {
				if ($val instanceof DOMElement) {
					foreach(array('nodeName', 'tagName', 'nodeValue', 'localName', 'textContent', 'prefix') as $prop)
						if(!isset($tree[$lv]['data'][$prop]))
							$tree[$lv]['data'][$prop] = array($key => $val->$prop);
						else
							$tree[$lv]['data'][$prop][$key] = $val->$prop;
						
					// attributes
					foreach($val->attributes as $attribute)
					if(!isset($tree[$lv]['data']['@'.$attribute->name]))
						$tree[$lv]['data']['@'.$attribute->name] = array($key => $attribute->value);
					else
						$tree[$lv]['data']['@'.$attribute->name][$key] = $attribute->value;
						
					// childNodes
					for($i = 0; $i < $val->childNodes->length; $i++) {
						$nodeName = $val->childNodes->item($i)->nodeName;
						if(!isset($tree[$lv]['data'][$nodeName])) 
							$tree[$lv]['data'][$nodeName] = array();
						$tree[$lv]['data'][$nodeName][] = $val->childNodes->item($i);
					}
				}
				else
				foreach($val as $key2 => $val2)
					if(!isset($tree[$lv]['data'][$key2]))
						$tree[$lv]['data'][$key2] = array($key => $val2);
					else
						$tree[$lv]['data'][$key2][$key] = $val2;
			}
		}
		
		// array/field, array/NNN
		// DOMElement/field, DOMElement/NNN
		elseif(in_array($name, array('', '.', '..', '*')) == false and strpos($name, '(') === false and (strpos($name, ':') === false || $prev_data_type == 'object/DOMElement') and strpos($name, '%') === false) {
// 			and strncmp($prev_data_type, 'array', 5) == 0 and strpos($name, '(') === false):

			// предыдущий это cursor()
			if(isset($tree[$lv-1]['metadata']) and isset($tree[$lv-1]['metadata']['cursor()'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');

				// REWIND - перематаем в начало если это курсор
				$call_result = call_user_func_array($tree[$lv-1]['metadata']['cursor()'], array(&$tree, $lv-1, 'rewind'));

				global $__uni_prt_cnt;
				for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
					
					$call_result = call_user_func_array($tree[$lv-1]['metadata']['cursor()'], array(&$tree, $lv-1, 'next', 10));
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__."(array/field, array/NNN): ".$tree[$lv-1]['metadata']['cursor()']."(next,10) = ",$call_result);
					
					if(!empty($call_result)) {
						if(array_key_exists($name, $call_result['data'])) {
							$tree[$lv]['data'] = $call_result['data'][$name];
							$tree[$lv]['metadata'][0] = gettype($tree[$lv]['data']);
							if(isset($call_result['metadata']['each_metadata'])
							&& isset($call_result['metadata']['each_metadata'][$name]))
								$tree[$lv]['metadata'] = $call_result['metadata']['each_metadata'][$name];
							else
								$tree[$lv]['metadata']['key()'] = $name;
							break;
						}
					} 
					
					// в случии с пустым массивом или false - делать нечего, заканчиваем обработку
					else
						break;
				}
			} 
			
			// может object?
			/* elseif(strpos($prev_data_type, 'object') === 0 and property_exists($tree[$lv-1]['data'], $name)) {
// var_dump(property_exists($tree[$lv-1]['data'], $name), $name);
				$tree[$lv]['data'] = $tree[$lv-1]['data']->{$name};
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''));
			} */
			
			// object/DOMElement
			elseif($prev_data_type == 'object/DOMElement') {
				$tree[$lv]['data'] = array();
				$tree[$lv]['metadata'] = array('array/DOMElement', 'key()' => $name);
				
				// node/childNodes[$name]
				if(is_numeric($name)) {
					if($tree[$lv-1]['data']->childNodes->length > $name) {
						$tree[$lv]['data'] = $tree[$lv-1]['data']->childNodes->item($name);
						$tree[$lv]['metadata'][0] = 'object/DOMElement';
					}
					else {
						$tree[$lv]['data'] = null;
						$tree[$lv]['metadata'][0] = 'null';
					}
				}
				else {
				
					// @attribute
					if($name[0] == '@') {
						$name = substr($name, 1);
							
						if($tree[$lv-1]['data']->hasAttribute($name))
							$tree[$lv]['data'] = $tree[$lv-1]['data']->getAttribute($name);
							
						// если не нашли атрибут и указан namespace, то попробуем без него
						elseif(strpos($name, ':') === false) {
							$name = substr($name, strpos($name, ':'));
							for($i = 0; $i < $tree[$lv-1]['data']->attributes->length; $i++) {
								if($tree[$lv-1]['data']->attributes->item($i)->localName == $name)
									$tree[$lv]['data'] = $tree[$lv-1]['data']->attributes->item($i)->nodeValue;
							}
						}
								
						$tree[$lv]['metadata'][0] = gettype($tree[$lv]['data']);
					}
					// node->property
					// TODO childNodes -> array?
					elseif(property_exists($tree[$lv-1]['data'], $name)) {
						$tree[$lv]['data'] = $tree[$lv-1]['data']->$name;
						$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']) . (is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''), 'key()' => $name);
					}
					// node/child
					else
					for($i = 0; $i < $tree[$lv-1]['data']->childNodes->length; $i++) {
						$node = $tree[$lv-1]['data']->childNodes->item($i);
						if(stripos($node->nodeName, $name) === 0 and strlen($node->nodeName) == strlen($name))
							$tree[$lv]['data'][] = $node;
					}
				}
			}
			
			// array/DOMElement
			elseif($prev_data_type == 'array/DOMElement') {
				// nodes/N
				if(is_numeric($name)) {
					$tree[$lv]['data'] = array_key_exists($name, $tree[$lv-1]['data']) 
					? $tree[$lv-1]['data'][$name] 
					: null;
					$tree[$lv]['metadata'] = array(
						gettype($tree[$lv]['data']).(is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''), 
						'key()' => $name);
				}
				else {
					$first_node = reset($tree[$lv-1]['data']);
					// (empty)
					if(empty($first_node) or $first_node instanceof DOMElement == false) {
						$tree[$lv]['data'] = $first_node;
						$tree[$lv]['metadata'] = array(gettype($first_node), 'key()' => $name);
					}
					// nodes[0]->@attribute
					elseif($name[0] == '@') {
						$tree[$lv]['data'] = $first_node->getAttribute(substr($name, 1));
						$tree[$lv]['metadata'] = array(
							gettype($tree[$lv]['data']).(is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''), 
							'key()' => $name);
					}
					// nodes[0]->property
					// TODO childNodes -> array?
					elseif(property_exists($first_node, $name)) {
						$tree[$lv]['data'] = $first_node->$name;
						$tree[$lv]['metadata'] = array(
							gettype($tree[$lv]['data']).(is_object($tree[$lv]['data']) ? '/'.get_class($tree[$lv]['data']) : ''), 
							'key()' => $name);
					}
					// nodes[*]/childNodes/$name
					else {
						foreach($tree[$lv-1]['data'] as $elem)
						for($i = 0; $i < $elem->childNodes->length; $i++) {
							$node = $elem->childNodes->item($i);
							if(stripos($node->nodeName, $name) === 0 and strlen($node->nodeName) == strlen($name))
								$tree[$lv]['data'][] = $node;
						}
						$tree[$lv]['metadata'] = array(
							'array/DOMElement', 
							'key()' => $name);
					}
				}
			}
			
			// просто array()
			elseif(is_array($tree[$lv-1]['data'])) {
			
				// проверим сначало на NameSpace
				if(array_key_exists($name, $tree[$lv-1]['data']) == false and empty($tree[$lv]['filter'])) {
					$class_name = $name;
					for($l = $lv+1; $l < count($tree); $l++)
						if(!empty($tree[$l]['name']) and strspn($tree[$l]['name'], '0123456789', 0, 1) == 0 and strpbrk($tree[$l]['name'], '[]().,+-="`\'') == false) {
							// попробуем на класс
							$class_name .= '\\'.$tree[$l]['name'];
// var_dump(__FILE__.':'.__LINE__, $class_name, class_exists($class_name));
							if(class_exists($class_name)) {
								$lv = $l; // перемотаем
								$tree[$lv]['data'] = $class_name;
								$tree[$lv]['metadata'] = array('class', 'key()' => $class_name);
								continue 2;
							}
						} 
						else
							break;
				}
				
				$tree[$lv]['data'] = array_key_exists($name, $tree[$lv-1]['data']) 
					? $tree[$lv-1]['data'][$name] 
					: null;
				$tree[$lv]['metadata'] = array(gettype($tree[$lv]['data']), 'key()' => $name);
				
				if($tree[$lv-1]['name'] == '?start_data?' and isset($tree[$lv-1]['data']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS']['GLOBALS']) && isset($GLOBALS_metadata[$name]))
						$tree[$lv]['metadata'] = & $GLOBALS_metadata[$name];
			} 
			
			// неизвестно что и поле не можем взять -> null
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null', 'key()' => $name);
			};
			
			switch($tree[$lv]['metadata'][0]) {
				case 'object':
					$tree[$lv]['metadata'][0] = "object/".get_class($tree[$lv]['data']);
					break;
				case 'resource':
					$tree[$lv]['metadata'][0] = "resource/".str_replace(' ', '-', get_resource_type($tree[$lv]['data']));
					break;
			};
			
			// незабываем про data_tracking
			//$tree[$lv]['metadata']['key()'] = $name;
		
			// filter1
			if(!empty($filter) and strpos($tree[$lv]['metadata'][0], 'array') === 0) {
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
								} 
								elseif(is_array($tree[$lv]['data'][$key]) 
									and array_key_exists($filter['expr1']['left'], $tree[$lv]['data'][$key])
									and strval($tree[$lv]['data'][$key][$filter['expr1']['left']]) == $filter['expr1']['right']) {
										$filter_pass = true;
								}
								elseif($val instanceof DOMElement) {
									if($filter['expr1']['left_type'] == 'name' and $filter['expr1']['left'][0] == '@') {
										$left = $val->getAttribute(substr($filter['expr1']['left'], 1));
										$filter_pass = $left == $filter['expr1']['right'];
									}
									else {
										// TODO will be implemented...
									}
								}
								else
									$filter_pass = false;
								break;
							case '<>':
							case '!=':
								if($val instanceof DOMElement) {
									if($filter['expr1']['left_type'] == 'name' and $filter['expr1']['left'][0] == '@') {
										$left = $val->getAttribute(substr($filter['expr1']['left'], 1));
										$filter_pass = $left != $filter['expr1']['right'];
									}
									else {
										// TODO will be implemented...
									}
								}
								elseif(is_array($tree[$lv]['data'][$key]) 
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
			}
			
			// если список DOMElement отдаётся как результат, то вернём как строку для удобства
			/*if($tree[$lv]['metadata'][0] == 'array/DOMElement' and count($tree) == $lv+1) {
				switch(count($tree[$lv]['data'])) {
					case 0:
						$tree[$lv]['data'] = null; 
						$tree[$lv]['metadata'][0] = 'null';
						break;
					case 1:
						$tree[$lv]['data'] = $tree[$lv]['data'][0]->textContent; 
						$tree[$lv]['metadata'][0] = 'string';
						break;
					default:
						foreach($tree[$lv]['data'] as $i => $node)
							$tree[$lv]['data'][$i] = $node->textContent;
						$tree[$lv]['data'] = implode('', $tree[$lv]['data']);
						$tree[$lv]['metadata'][0] = 'string';
				}
			}*/
		}
		
		// [...] - пропустить или нет дальше
		elseif($name == '' and !empty($tree[$lv]['filter'])) {
		
			$data = $tree[$lv-1]['data'];
			$key = isset($tree[$lv-1]['metadata']['key()']) ? $tree[$lv-1]['metadata']['key()'] : null;
		
			$expr = $tree[$lv]['filter']['start_expr'];
			$filter = $tree[$lv]['filter'];
			while($expr && isset($filter[$expr])) {

				// left
				switch($filter[$expr]['left_type']) {
					case 'unipath':
						$left_result = __uni_with_start_data(
							$tree[$lv-1]['data'], 
							$tree[$lv-1]['metadata'][0], 
							$tree[$lv-1]['metadata'], 
							$filter[$expr]['left']);
						$left_result = $left_result['data'];
						break;
					case 'expr':
// if(!isset($filter[$filter[$expr]['left']]['result'])) print_r($filter);
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
						} 
						elseif(strncmp($filter[$expr]['left'], 'like(', 5) == 0) {
							list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['left']);
							if($args_types[0] == 'unipath')
								$left_result = __uni_with_start_data(
									$tree[$lv-1]['data'], 
									$tree[$lv-1]['metadata'][0], 
									$tree[$lv-1]['metadata'], 
									$args[0]);
							else
								$left_result = $filter[$expr]['left'];
//var_dump($left_result, trim($func_and_args[1], '%'), strpos($left_result, trim($func_and_args[1], '%')));
							$left_result = strpos($left_result, trim($args[1], '%')) !== false;
						} 
						// иначе обробатываем как unipath
						else {
							$left_result = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['metadata'][0], $tree[$lv-1]['metadata'], $filter[$expr]['left']);
							$left_result = $left_result['data'];
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
						$right_result = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['metadata'][0], $tree[$lv-1]['metadata'], $filter[$expr]['right']);
						$right_result = $right_result['data'];
						break;
					case 'expr':
						$right_result = $filter[$filter[$expr]['right']]['result'];
						break;
					case 'name':
						if(in_array($filter[$expr]['right'], array('null', 'NULL')))
							$right_result = null;
						else
							$right_result = isset($data[$filter[$expr]['right']]) ? $data[$filter[$expr]['right']] : null;
						break;
					case 'list-of-string':
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
						// 0 == 'abc' or 'abc' == 0 -> true!!!
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
// var_dump($left_result, $right_result, $tree[$lv]['unipath']);
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
			// если прошёл фильтрацию копируем дальше
			if($filter[$last_expr]['result']) {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['metadata'] = $tree[$lv-1]['metadata'];
is_array($tree[$lv-1]['metadata']) or var_dump($tree);
				// специально пометим, что это копия предыдущего шага
				if( ! isset($tree[$lv-1]['metadata']['this_step_is_copy_of_step']))
					$tree[$lv]['metadata']['this_step_is_copy_of_step'] = $lv-1;
			} 
			
			// не прошёл фильтрацию!
			else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['metadata'] = array('null');
			}
		}
		
		// /..
		elseif($name == '..') {
			$tree[$lv]['data'] = $lv > 1 ? $tree[$lv-2]['data'] : null;
			$tree[$lv]['metadata'] = $lv > 1 && isset($tree[$lv-2]['metadata']) 
				? $tree[$lv]['metadata'] = $tree[$lv-2]['metadata']
				: array();
			
			// специально пометим, что это копия предыдущего шага
			if( ! isset($tree[$lv]['metadata']['this_step_is_copy_of_step']))
				$tree[$lv]['metadata']['this_step_is_copy_of_step'] = $lv-2;
		}
		
		// если не понятно что делать, тогда просто копируем данные
		else {
			if($name == 'all()') {
				/* all() - обычно используется вместе с cursor() */
			}
			elseif($name == '.') {
				/* "./" - не является ошибкой */
			}
			else
				trigger_error("UniPath.".__FUNCTION__.": unknown - ".$name.' (skip)', E_USER_WARNING);
		
			$tree[$lv]['data'] = $lv > 0 ? $tree[$lv-1]['data'] : array();
			$tree[$lv]['metadata'] = $lv > 0 && isset($tree[$lv-1]['metadata']) 
				? $tree[$lv-1]['metadata']
				: array('array');
			
			// специально пометим, что это копия предыдущего шага
			if( ! isset($tree[$lv]['metadata']['this_step_is_copy_of_step']))
				$tree[$lv]['metadata']['this_step_is_copy_of_step'] = $lv-1;
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
	echo "<br>------------<br>\n";
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data']) and isset($tree[$lv-1]['data']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS'], $tree[$lv-1]['data']['GLOBALS']['GLOBALS']['GLOBALS'])) 
		print_r(array_merge($tree[$lv-1], array('data' => '*** $GLOBALS ***')));
	else
		print_r($tree[$lv-1]);
}
		return $tree;
	} 
	
	// обработка xpath была прервана?
	trigger_error("UniPath: evaluation interrupted on step #$lv! (".$tree[$lv-1]['unipath'].")", E_USER_ERROR);
	return $tree;
}

// парсит unipath запрос на отдельные шаги (аргументы функций не парсит)
function __uni_parseUniPath($xpath = '', $start_data = null, $start_data_type = null, $start_data_tracking = null) {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('Parsing - '.$xpath);
		// временно переключим mbstring.func_overload в 1-байтовую кодировку
		if(ini_get('mbstring.func_overload') > 1) {
			if (version_compare(PHP_VERSION, '5.6.0') < 0) {
				$mbstring_internal_encoding = ini_get('mbstring.internal_encoding');
				ini_set('mbstring.internal_encoding', 'ISO-8859-1');
			}
			else {
// 				$mbstring_internal_encoding = ini_get('default_charset');
// 				ini_set('default_charset', 'ISO-8859-1');
				$mbstring_internal_encoding = mb_internal_encoding();
				mb_internal_encoding("iso-8859-1");
			}
		}

		$tree = array();
// 		$suffix = '';
		$p = 0;

		// временно для удобства
		if($xpath[0] == '$') $xpath[0] = '/';
		
		// абсалютный путь - стартовые данные это $GLOBALS
		// для относительного, если не передали стартовые данные, то тоже $GLOBALS
		if($xpath[0] == '/' or is_null($start_data) and is_null($start_data_type) and is_null($start_data_tracking))
			$tree[] = array('name' => '?start_data?', 'data' => &$GLOBALS, 'metadata' => array('array'), 'unipath' => ''); 
			
		// относительный путь - стартовые данные берём, которые передали
		else {
			$tree[] = array('name' => '?start_data?', 
				'data' => $start_data, 
				'metadata' => $start_data_tracking, 
				'unipath' => '');
			$tree[0]['metadata'][0] = isset($start_data_type) 
				? $start_data_type 
				: gettype($start_data);
		}
		
		// если первым указан протокол, то распарсим его заранее
		if(sscanf($xpath, '%[qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789]:%2[/]', $scheme, $trailing_component) == 2) {
			$scheme = strtolower($scheme);
			switch($scheme) {
				case 'file':
					$tree[] = array('name' => 'file://', /*'data' => 'file://', 'data_type' => 'string/local-filesystem', 'data_tracking' => array('key()' => 'file://'),*/ 'unipath' => 'file://'); 
					break;
				case 'http':
					$url = strpos($xpath, "??") > 0 ? substr($xpath, 0, strpos($xpath, "??")) : $xpath;
					$tree[] = array('name' => $url, 'data' => $url, 'metadata' => array('string/url', 'key()' => $url), 'unipath' => $url);
					break;
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

		global $__uni_prt_cnt; // защита от зацикливания
		for($prt_cnt = $__uni_prt_cnt; $p < strlen($xpath) and $prt_cnt > 0; $prt_cnt--) {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("0:$p => ".substr($xpath, 0, $p));

			// новая ось
			if($xpath[$p] == '/') {
			
				// укажем путь предыдушего уровня пути
				if(!empty($tree))
					$tree[count($tree)-1]['unipath'] = substr($xpath, 0, $p);
					
				$tree[] = array( 'name' => null );
// 				$suffix = '';
				$p++;
					
				continue;
			} 
			
			// разделитель (+,,)
			if($xpath[$p] == '+' or $xpath[$p] == ',') {
	
				// укажем путь предыдушего уровня пути
				if(!empty($tree))
					$tree[count($tree)-1]['unipath'] = substr($xpath, 0, $p);
					
				$separator_len = strspn($xpath, '+-,', $p);
				$tree[] = array( 'name' => substr($xpath, $p, $separator_len));
				$p += $separator_len;
				
				continue;
			}
			
			// названия поля/оси
			// strpos('qwertyuiopasdfghjklzxcvbnm_*@0123456789.$', strtolower($xpath[$p]))
			if(strpos("\\|/,+[](){}?!~`'\";#^&=- \n\t\r", $xpath[$p]) === false) {
				$start_p = $p;
				$len = strcspn($xpath, "\\|/,+[](){}?!~`'\";#^&= \n\t\r", $start_p);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("fieldname detected: $p,$len = ".substr($xpath, $start_p, $len));
				$p += $len;

				// может это function()?
				if(isset($xpath[$p]) and $xpath[$p] == '(') {
					
					$tmp_stack = new SplFixedArray(32); $tmp_num = 0; $tmp_stack[0] = 12;
					while($p < strlen($xpath)) {
					
						// ```````...
						if($xpath[$p] == "`" and $tmp_stack[$tmp_num] > 10 and isset($xpath[$p+6]) and $xpath[$p+1] == '`' and $xpath[$p+2] == '`' and $xpath[$p+3] == '`' and $xpath[$p+4] == '`' and $xpath[$p+5] == '`' and $xpath[$p+6] == '`') { 
							$p += 6;
							$tmp_stack[++$tmp_num] = 5;
						}
						
						// ...```````
						elseif($tmp_stack[$tmp_num] == 5 and isset($xpath[$p+6]) and $xpath[$p] == '`' and $xpath[$p+1] == '`' and $xpath[$p+2] == '`' and $xpath[$p+3] == '`' and $xpath[$p+4] == '`' and $xpath[$p+5] == '`' and $xpath[$p+6] == '`') {
							if(isset($xpath[$p+7]) and $xpath[$p+7] == '`') { $p++; continue; }
							$p += 6;
							$tmp_num--;
						}
					
						// ...```
						elseif($tmp_stack[$tmp_num] == 4 and isset($xpath[$p+2]) 
							and $xpath[$p] == '`' and $xpath[$p+1] == '`' and $xpath[$p+2] == '`') {
							if(isset($xpath[$p+3]) and $xpath[$p+3] == '`') { $p++; continue; }
							$p += 2;
							$tmp_num--;
						}
						
						// ```...
						elseif($xpath[$p] == "`" and $tmp_stack[$tmp_num] > 10 and isset($xpath[$p+2]) and $xpath[$p+1] == '`' and $xpath[$p+2] == '`') { 
							$p += 2;
							$tmp_stack[++$tmp_num] = 4;
						}
						
						// `...`
						elseif($xpath[$p] == "`" and $tmp_stack[$tmp_num] == 3) $tmp_num--;
						elseif($xpath[$p] == "`" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 3;
						
						elseif($xpath[$p] == '(' and  $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 12;
						elseif($xpath[$p] == ')' and $tmp_num == 1) { $p++; break; }
						elseif($xpath[$p] == ')' and $tmp_stack[$tmp_num] == 12) $tmp_num--;
						elseif($xpath[$p] == "'" and $tmp_stack[$tmp_num] == 1) $tmp_num--;
						elseif($xpath[$p] == "'" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 1;
						elseif($xpath[$p] == "\"" and $tmp_stack[$tmp_num] == 2) $tmp_num--;
						elseif($xpath[$p] == "\"" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 2;
						
						
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("function_parse - {$xpath[$p]}".str_repeat(' ',$tmp_num*3)." tmp_stack[{$tmp_num}] = ".$tmp_stack[$tmp_num]);
						$p++;
					}
					
/*					$inner_brakets = 1; $inner_string = false;
					while($inner_brakets > 0 and isset($xpath[$p])) { 
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("braket_mode: xpath[p] = {$xpath[$p]}, inner_string =  $inner_string");
						$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
						if(!isset($xpath[$p]))
							trigger_error("UniPath: __uni_parseUniPath(): Not found close braket in $xpath!", E_USER_ERROR);
						
						if($xpath[$p] == "'" and $inner_string == false)  $inner_string = "'";
						elseif($xpath[$p] == "'" and $inner_string == "'")  $inner_string = false;
						elseif($xpath[$p] == "`" and $inner_string == false)  $inner_string = "`";
						elseif($xpath[$p] == "`" and $inner_string == "`")  $inner_string = false;
						elseif($xpath[$p] == '(' and !$inner_string) $inner_brakets++;
						elseif($xpath[$p] == ')' and !$inner_string) $inner_brakets--;
					}
					$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++]; */
					
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("braket_mode_result = ".substr($xpath, $start_p, $p - $start_p));
				}
				
				$tree[count($tree)-1]['name'] .= substr($xpath, $start_p, $p - $start_p);
			
				continue;
			}
			
			// [] фильтрация
			if($xpath[$p] == '[') {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filtration start - $p");
				$p++; // [

				// разбираем фильтр
				$filter = array('start_expr' => 'expr1', 'expr1' => array('braket_level' => 0));
				$next_expr_num = 2;
				$expr = 'expr1';
				$expr_key = 'left';
				$curr_braket_level = 0;
				while($p < strlen($xpath) and $xpath[$p] != ']') {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("--- $expr --- ".(isset($filter[$expr]['op'])?$filter[$expr]['op']:''));
					while(strpos(" \n\t", $xpath[$p]) !== false) $p++;
//print_r(array(substr($xpath, 0, $p), $filter));
					// до конца фильтрации были пробелы?
					if($xpath[$p] == ']') continue;
if(!empty($GLOBALS['unipath_debug_parse'])) print_r($filter);
					// (
					if($xpath[$p] == '(') {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_braket_opened on $p");
						$curr_braket_level += 1;

						// если начало фильтра, то просто повысем braket_level
						if(isset($filter[$expr]['left']) == false) {
							$filter[$expr]['braket_level'] = $curr_braket_level;
							$expr_key = "left";
							$p++;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("increase_braket_level - $expr");
							continue;
						}
						
						// создаём новый узел выражения и вклиниваем его в цепочку
						$old_expr = $expr;
						$expr = 'expr'.($next_expr_num++);
						$filter[$expr] = array(
							'left' => null,
							'left_type' => null,
							'op' => null,
							'next' => $old_expr,
							'braket_level' => $curr_braket_level
						);
						$filter[$old_expr]['right'] = $expr;
						$filter[$old_expr]['right_type'] = "expr";
						$expr_key = "left";
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("inserted_expr - $expr");

						// корректируем порядок выполнения
						foreach($filter as $_expr_key => $_expr)
						if(is_array($_expr) and isset($_expr['next']) and $_expr["next"] == $old_expr and $_expr_key != $expr) {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("  go-up-backpatch ({$_expr['next']}, {$_expr['op']})");
							$filter[$_expr_key]["next"] = $expr;
							break;
						}
						
						// если мы вклиниваемся, то и начало цепочки корректируем
						if($filter['start_expr'] == $old_expr)
							$filter['start_expr'] = $expr;
						
// 						$filter[$expr]['braket_level'] = true;
						$p++;
						continue;
					}
					
					// )
					if($xpath[$p] == ')') {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_braket_closed - $p");
						
						// всплываем из групировки
						$curr_braket_level -= 1;
						
						// корректируем next на случай если это последнее expr ?
// 						var_dump($filter[$expr]);
						
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
						while(in_array($filter[$old_expr]['op'], array('*','div','mod','+','-','left_eval')) and $filter[$old_expr]['braket_level'] == 0)
							if( empty($filter[$old_expr]['next']) ) break;
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','div','mod','+','-','left_eval')) 
						&& $filter[$old_expr]['braket_level'] == 0) {
							$expr = 'expr'.($next_expr_num++);
							$filter[$expr] = array(
								'left' => $old_expr,
								'left_type' => 'expr',
								'op' => $op,
								'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null,
								'braket_level' => $curr_braket_level
							);
							$filter[$old_expr]['next'] = $expr;
						} 
						
						// или отбираем правую часть
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
								'next' => $old_expr, // [мы] -> (старое) теущее звено
								'braket_level' => $curr_braket_level
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
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod', 'left_eval')) and $filter[$old_expr]['braket_level'] == $curr_braket_level)
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];

							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod', 'left_eval')) or $filter[$old_expr]['braket_level'] != $curr_braket_level) {
								$expr = 'expr'.($next_expr_num++);
								$filter[$expr] = array(
									'left' => $old_expr,
									'left_type' => 'expr',
									'op' => $op,
									'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null,
									'braket_level' => $curr_braket_level
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
									'next' => $old_expr, // [мы] -> (старое) теущее звено
									'braket_level' => $curr_braket_level
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
						$op = ($xpath[$p] == 'a' or $xpath[$p] == 'A') ? 'and' : 'or';
						$p += ($xpath[$p] == 'a' or $xpath[$p] == 'A') ? 3 : 2;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('AND/OR detected! - '.$op);

						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}

						// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
						$old_expr = $expr;
						while((in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', '<>', '!=', 'and', 'or', 'left_eval')) && $filter[$old_expr]["braket_level"] == $curr_braket_level) || ($filter[$old_expr]["braket_level"] > $curr_braket_level && isset($filter[$old_expr]["next"])))
							if( empty($filter[$old_expr]['next']) ) break;
							elseif ($filter[$filter[$old_expr]['next']]['braket_level'] < $curr_braket_level) break; // выходим за приделы групировки вниз
							elseif ($filter[$filter[$old_expr]['next']]['braket_level'] > $curr_braket_level) $old_expr = $filter[$old_expr]['next']; // выходим за приделы групировки наверх
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', '<>', '!=', 'and', 'or', 'left_eval')) && ($filter[$old_expr]['braket_level'] == $curr_braket_level
						or $filter[$old_expr]["braket_level"] > $curr_braket_level && empty($filter[$old_expr]["next"]))) {
							$expr = 'expr'.($next_expr_num++);
							$filter[$expr] = array(
								'left' => $old_expr,
								'left_type' => 'expr',
								'op' => $op,
								'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null,
								'braket_level' => $curr_braket_level
							);
							
							// при next выходящим за пределы групировки надо поправить right
							if (isset($filter[$expr]['next']) && $filter[$filter[$expr]['next']]['braket_level'] != $curr_braket_level)
								$filter[$filter[$expr]['next']]['right'] = $expr;
							
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
								'next' => $old_expr, // [мы] -> (старое) теущее звено
								'braket_level' => $curr_braket_level
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
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!=', 'left_eval')) || $filter[$old_expr]['braket_level'] != $curr_braket_level)
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];
//var_dump("$expr: $old_expr");
							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!=', 'left_eval')) || $filter[$old_expr]['braket_level'] != $curr_braket_level) {
								$expr = 'expr'.($next_expr_num++);
								$filter[$expr] = array(
									'left' => $old_expr,
									'left_type' => 'expr',
									'op' => $op,
									'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null,
									'braket_level' => $curr_braket_level
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
									'next' => $old_expr, // [мы] -> (старое) теущее звено
									'braket_level' => $curr_braket_level
								);
								
								// right = [мы]
								$filter[$old_expr]['right'] = $expr;
								$filter[$old_expr]['right_type'] = 'expr';

//var_dump("$old_expr.next = ".$filter[$old_expr]['next']);
							}
						
							continue;
						}
					};

					// число
					if(strpos('0123456789', $xpath[$p]) !== false or ($xpath[$p] == '-' 
						and isset($xpath[$p+1]) and strpos('0123456789,', $xpath[$p+1]) !== false )) {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('number detected!');
						$len = strspn($xpath, '0123456789-.', $p);
						$val = substr($xpath, $p, $len);
						$p += $len;
						
						$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'number';

						// возможно это список чисел
						while(strpos(" \n\t", $xpath[$p]) !== false) $p++;
						if($xpath[$p] == ',') {
							$filter[$expr][$expr_key.'_type'] = 'list-of-number';
							$filter[$expr][$expr_key] = array($filter[$expr][$expr_key]);
							
							$len = strspn($xpath, "0123456789,\n\t ", $p);
							foreach(array_map('trim', explode(',', substr($xpath, $p, $len))) as $item)
								if(is_numeric($item))
									$filter[$expr][$expr_key][] = $item;
							$p += $len;
						}
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('number = '.(is_array($filter[$expr][$expr_key]) ? implode(',', $filter[$expr][$expr_key]) : $filter[$expr][$expr_key]));
						continue;
					}
					
					// строка
					if(strpos('\'`"', $xpath[$p]) !== false or ($xpath[$p] == 'N' and isset($xpath[$p+1]) and strpos('\'`"', $xpath[$p+1]) !== false)) {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_string_start - $p");
						// подкоректируем начало MSSQL UnicodeString
						if($xpath[$p] == 'N') { 
							$p++; 
							$filter[$expr][$expr_key.'_type'] = 'string-with-N'; 
						} else
							$filter[$expr][$expr_key.'_type'] = 'string'; 
							
							
						$start_p = $p++;

						// ```````...```````
						if(isset($xpath[$p+5]) and strspn($xpath, "`", $p, 5) == 5) {
							$p = strpos($xpath, '```````', $p+6);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_string_type = ```````, end = ".$p);
							if($p == false)
								trigger_error("UniPath.__uni_parseUniPath(): Not found end ``````` in filter! (".substr($xpath, $start_p+7).")", E_USER_ERROR);
							while(isset($xpath[$p+7]) and $xpath[$p+7] == '`') $p++;
							$val = substr($xpath, $start_p+7, $p-$start_p-7);
							$p += 7;
						}
						
						// ```...```
						elseif(isset($xpath[$p+2]) and strspn($xpath, "`", $p, 2) == 2) {
							$p = strpos($xpath, '```', $p+2);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_string_type = ```, end = ".$p);
							if($p == false)
								trigger_error("UniPath.__uni_parseUniPath(): Not found end ``` in filter! (".substr($xpath, $start_p).")", E_USER_ERROR);
							while(isset($xpath[$p+3]) and $xpath[$p+3] == '`') $p++;
							$val = substr($xpath, $start_p+3, $p-$start_p-3);
							$p += 3;
						}
						
						// `...`, '...', "..."
						else {
							$p = strcspn($xpath, $xpath[$start_p], $p);
							$val = substr($xpath, $start_p+1, $p);
							$p = $start_p + $p + 2;
						}

						$filter[$expr][$expr_key] = $val;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_string_end - $val");

						// возможно это список строк
						$space = strspn($xpath, " \t\n", $p);
						if($xpath[$p+$space] == ',') {
							$filter[$expr][$expr_key.'_type'] = 'list-of-'.$filter[$expr][$expr_key.'_type'];
							$filter[$expr][$expr_key] = array($filter[$expr][$expr_key]);
							$p += $space+1;

							$space = strspn($xpath, " \t\n", $p);
							while(isset($xpath[$p+$space]) and strpos("\"'`N", $xpath[$p+$space]) !== false) {
								$p += $space;
								
								// MSSQL UnicodeString
								if($xpath[$p] == 'N') $p++;
								
								$start_p = $p++;

								// ```````...```````
								if(isset($xpath[$p+5]) and strspn($xpath, "`", $p, 5) == 5) {
									$p = strpos($xpath, '```````', $p+6);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_list_string_type = ```````, end = ".$p);
									if($p == false)
										trigger_error("UniPath.__uni_parseUniPath(): Not found end ``````` in filter! (".substr($xpath, $start_p+7).")", E_USER_ERROR);
									while(isset($xpath[$p+7]) and $xpath[$p+7] == '`') $p++;
									$val = substr($xpath, $start_p+7, $p-$start_p-7);
									$p += 7;
								}
								
								// ```...```
								elseif(isset($xpath[$p+2]) and strspn($xpath, "`", $p, 2) == 2) {
									$p = strpos($xpath, '```', $p+2);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_list_string_type = ```, end = ".$p);
									if($p == false)
										trigger_error("UniPath.__uni_parseUniPath(): Not found end ``` in filter! (".substr($xpath, $start_p).")", E_USER_ERROR);
									while(isset($xpath[$p+3]) and $xpath[$p+3] == '`') $p++;
									$val = substr($xpath, $start_p+3, $p-$start_p-3);
									$p += 3;
								}
								
								// `...`, '...', "..."
								else {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_list_string_type = {$xpath[$start_p]}, end = ".$p);
									$p = strcspn($xpath, $xpath[$start_p], $p);
									$val = substr($xpath, $start_p+1, $p);
									$p = $start_p + $p + 2;
								}	
	
								$filter[$expr][$expr_key][] = $val;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_list_string = ".implode(',', $filter[$expr][$expr_key]));

								$space = strspn($xpath, " \t\n", $p);
								if($xpath[$p+$space] != ',') break;
								$space += strspn($xpath, " \t\n", $p+$space+1) + 1;
							}
						}
						continue;
					}
					
					// текущий элемент
					if($xpath[$p] == '.' and isset($xpath[$p+1]) and $xpath[$p+1] != '/') {
						$p++;
						$filter[$expr][$expr_key] = '.';
						$filter[$expr][$expr_key.'_type'] = 'dot';
						continue;
					}
					
					// вложенный unipath
					if(strpos('./@qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_', $xpath[$p]) !== false) {
						
						// N'...' -> MS SQL UnicodeString
						if($xpath[$p] == 'N' and isset($xpath[$p+1]) and $xpath[$p+1] == "'")
							continue;
					
						$start_p = $p;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_inner_unipath_detected - $p");

						
						$func_flag = false; $unipath_flag = $xpath[$p] == '/';
						$tmp_stack = new SplFixedArray(32); $tmp_num = 0; $tmp_stack[0] = 99;
						while($p < strlen($xpath)) {
						
							// ```````...
							if($xpath[$p] == "`" and $tmp_stack[$tmp_num] > 10 and isset($xpath[$p+6]) and $xpath[$p+1] == '`' and $xpath[$p+2] == '`' and $xpath[$p+3] == '`' and $xpath[$p+4] == '`' and $xpath[$p+5] == '`' and $xpath[$p+6] == '`') { 
								$p += 6;
								$tmp_stack[++$tmp_num] = 5;
							}
							
							// ...```````
							elseif($tmp_stack[$tmp_num] == 5 and isset($xpath[$p+6]) and $xpath[$p] == '`' and $xpath[$p+1] == '`' and $xpath[$p+2] == '`' and $xpath[$p+3] == '`' and $xpath[$p+4] == '`' and $xpath[$p+5] == '`' and $xpath[$p+6] == '`') {
								if(isset($xpath[$p+7]) and $xpath[$p+7] == '`') { $p++; continue; }
								$p += 6;
								$tmp_num--;
							}
						
							// ...```
							elseif($tmp_stack[$tmp_num] == 4 and isset($xpath[$p+2]) 
								and $xpath[$p] == '`' and $xpath[$p+1] == '`' and $xpath[$p+2] == '`') {
								if(isset($xpath[$p+3]) and $xpath[$p+3] == '`') { $p++; continue; }
								$p += 2;
								$tmp_num--;
							}
							
							// ```...
							elseif($tmp_stack[$tmp_num] > 10 and $xpath[$p] == "`" and isset($xpath[$p+2]) and $xpath[$p+1] == '`' and $xpath[$p+2] == '`') { 
								$p += 2;
								$tmp_stack[++$tmp_num] = 4;
							}
							
							// `...`
							elseif($xpath[$p] == "`" and $tmp_stack[$tmp_num] == 3) $tmp_num--;
							elseif($xpath[$p] == "`" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 3;
						
							elseif($xpath[$p] == '[' and $tmp_stack[$tmp_num] > 10) { $tmp_stack[++$tmp_num] = $unipath_flag = 11; }
							elseif($xpath[$p] == ']' and $tmp_stack[$tmp_num] == 11) $tmp_num--;
							elseif($xpath[$p] == '(') { $tmp_stack[++$tmp_num] = $func_flag = 12; }
							elseif($xpath[$p] == ')' and $tmp_num == 0) break;
							elseif($xpath[$p] == ')' and $tmp_stack[$tmp_num] == 12) $tmp_num--;
							elseif($xpath[$p] == "'" and $tmp_stack[$tmp_num] == 1) $tmp_num--;
							elseif($xpath[$p] == "'" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 1;
							elseif($xpath[$p] == "\"" and $tmp_stack[$tmp_num] == 2) $tmp_num--;
							elseif($xpath[$p] == "\"" and $tmp_stack[$tmp_num] > 10) $tmp_stack[++$tmp_num] = 2;
							elseif($xpath[$p] == "/" and $tmp_stack[$tmp_num] == 99) $unipath_flag = 99;
							elseif($xpath[$p] == '-' and $xpath[$p-1] == 'p' and $xpath[$p-2] == 'h' and $xpath[$p-3] == 'p') { /* кастыль для php-foreach */ }
							elseif(strpos(" \n\t]=<>-+!", $xpath[$p]) !== false and $tmp_num == 0) break;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_inner_unipath_skip - {$xpath[$p]} ".str_repeat(" ",$tmp_num*3)." tmp_stack[{$tmp_num}] = {$tmp_stack[$tmp_num]}");
							$p++;
						}
						
						$val = substr($xpath, $start_p, $p - $start_p);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("filter_inner_unipath_result = $val, func_flag = $func_flag, unipath_flag = $unipath_flag");

						/*if(isset($filter[$expr][$expr_key]))
							$filter[$expr][$expr_key] .= $val;
						else*/
						
						$filter[$expr][$expr_key] = $val;
						
						if($unipath_flag)
							$filter[$expr][$expr_key.'_type'] = 'unipath';
						elseif($func_flag) {
							$filter[$expr][$expr_key.'_type'] = 'function';
							if($expr_key == 'left') {
								$filter[$expr]['op'] = 'left_eval';
								$old_expr = $expr;
								$expr = 'expr'.($next_expr_num++);
								$filter[$expr] = array(
									'left' => $old_expr,
									'left_type' => 'expr',
									'op' => null,
									'next' => isset($filter[$old_expr]['next']) ? $filter[$old_expr]['next'] : null,
									'braket_level' => $curr_braket_level
								);
								
								// пофиксим right у предыдушего (?)
								if (isset($filter[$old_expr]['next']))
									$filter[$filter[$old_expr]['next']]['right'] = $expr;
									
								$filter[$old_expr]['next'] = $expr;
							}
						}
						else
							$filter[$expr][$expr_key.'_type'] = 'name';

						continue;
					}
					
					if(!empty($GLOBALS['unipath_debug_parse'])) var_dump("unknown - ".$xpath[$p]);
					$p++; // непонятно что -> пропустим
				}
				$p++; // ]
				
				if(isset($tree[count($tree)-1]['filter']))
					$tree[count($tree)-1]['filter2'] = $filter;
				else
					$tree[count($tree)-1]['filter'] = $filter;
					
				continue;
			} // фильтрация
			
			// `'" строка
			if($xpath[$p] == '`' or $xpath[$p] == '"' or $xpath[$p] == "'") {
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('string start - '.$p);
				//$start_string_p = $p++;
				
// var_dump($xpath[$p], substr($xpath, $p, 7));				
				switch($xpath[$p]) {
					case '`':
						if(isset($xpath[$p+6]) and substr_compare($xpath, '```````', $p, 7) == 0) {
							$p += 7;
							$end = strpos($xpath, $string_border = '```````', $p);
						} 
						elseif(isset($xpath[$p+2]) and substr_compare($xpath, '```', $p, 3) == 0) {
							$p += 3;
							$end = strpos($xpath, $string_border = '```', $p);
						} 
						else {
							$p += 1;
							$end = strpos($xpath, $string_border = '`', $p);
						}
						while($end and isset($xpath[$end+strlen($string_border)]) and $xpath[$end+strlen($string_border)] == '`')
							$end++;
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump('$string_border = '.$string_border.' , value = '.substr($xpath, $p, $end-$p));
						break;
					case "'":
					case '"':
						$string_border = $xpath[$p];
						$p += strlen($string_border);
						
						// поищем окончание строки
						$end = strpos($xpath, $string_border, $p);
				} 
				
				if($end === false) 
					trigger_error("UniPath.__uni_parseUniPath(): Not found end of string started on $p! (".substr($xpath, $p-strlen($string_border)).')', E_USER_ERROR);

				$tree[count($tree)-1]['name'] .= substr($xpath, $p, $end-$p);
				
				// передвинем указатель
				$p = $end + strlen($string_border);
if(!empty($GLOBALS['unipath_debug_parse'])) var_dump(substr($xpath, $p));
				continue;
			}
			
			$p++; // попробуем пропустить непонятные символы
			
		}; // while($xpath)
		
		// пропишем путь у последнего уровня пути
		if(!empty($tree))
			$tree[count($tree)-1]['unipath'] = $xpath;

		// вернём обратно кодировку mbstring если включена
		if(ini_get('mbstring.func_overload') > 1) {
			if (version_compare(PHP_VERSION, '5.6.0') < 0)
				ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
			else
// 				ini_set('default_charset', $mbstring_internal_encoding);
				mb_internal_encoding($mbstring_internal_encoding);
		}
			
		return $tree;
}

// вытаскивает список аргументов внутри функции
function __uni_parseFuncArgs($string) {
	
	// временно переключим mbstring.func_overload в 1-байтовую кодировку
	if(ini_get('mbstring.func_overload') > 1) {
		if (version_compare(PHP_VERSION, '5.6.0') < 0) {
			$mbstring_internal_encoding = ini_get('mbstring.internal_encoding');
			ini_set('mbstring.internal_encoding', 'ISO-8859-1');
		}
		else {
// 			$mbstring_internal_encoding = ini_get('default_charset');
// 			ini_set('default_charset', 'ISO-8859-1');
			$mbstring_internal_encoding = mb_internal_encoding();
			mb_internal_encoding("iso-8859-1");
		}
	}
	
	$result = array(); $result_types = array();
	$in_string = $in_binary_string = false; // внутри строки?
	$brakets_level = 0; // уровень вложенности скобок
	$arg_start = 0; $arg_key = 0;
	$mode = ''; $p = 0; $strlen = strlen($string);
	global $__uni_prt_cnt;
	for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
	
		if($in_binary_string)
			$len = strcspn($string, "`", $p);
		elseif($in_string)
			$len = strcspn($string, $in_string, $p);
		else
			$len = strcspn($string, ",()[]'\"`=", $p);
			
if(!empty($GLOBALS['unipath_debug'])) echo(" <-- $mode \n |".substr($string, $p, $len).'|'.substr($string, $p+$len)." -- p:len=$p:$len brakets_level=$brakets_level");

		if($len+$p == $strlen) break; // дошли до конца строки
		
		// выделим имя функции
		if($brakets_level == 0 and $string[$p+$len] == '(' and !$in_string and !$in_binary_string) {
// 			$result['func_name'] = ltrim(substr($string, 0, $len));
			$brakets_level++;
			$arg_start = $p = $len+1;
			$mode = 'func_name';
			continue;
		}
		
		// (,[
		if(strspn($string[$p+$len], '([') and !$in_string and !$in_binary_string) {
			$brakets_level++;
			$p += $len+1;
			$mode = 'open_braket';
			continue;
		}
		
		// ),[
		if(strspn($string[$p+$len], ')]') and !$in_string and !$in_binary_string) {
			$brakets_level--;
			
			if($brakets_level == 0) {
				if(isset($last_string)) {
					$result[$arg_key] = $last_string;
					$result_types[$arg_key] = $mode == 'in_string_end_N' ? 'string-with-N' : 'string';
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
		if(($string[$p+$len] == "'" or substr_compare($string, "N'", $p+$len-1, 2) == 0) 
			and !$in_string and !$in_binary_string) {
			if(isset($last_string) and is_null($last_string) == false)
				trigger_error("UniPath: __uni_parseFuncArgs(): unexpected single quote ' on position $p - ".substr($string, $p), E_USER_NOTICE);
			$in_string = "'";
			$last_string = '';
			$mode = 'in_string_start'.($string[$p+$len-1] == 'N' ? '_N' : '');
			$p += $len + 1;
			continue;
		}
		
		// '' and string end
		if($string[$p+$len] == "'" and $in_string == "'" and !$in_binary_string) {
			/*if(substr_compare($string, "''", $p+$len, 2) == 0) {
				$last_string .= substr($string, $p, $len+1);
				$p += $len+1;
				$mode = 'in_string_escp1';
				continue;
			} elseif($p+$len > 0 and $string[$p+$len-1] == "'" and $last_string != '') {
				$p += $len+1;
				$mode = 'in_string_escp2';
				continue;
			} else {*/
				$last_string .= substr($string, $p, $len);
				$in_string = false;
				$p += $len+1;
				$mode = $mode == 'in_string_start_N' ? 'in_string_end_N' : 'in_string_end';
				continue;
			//}
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
			$last_string = null; // чтоб '' не вызывал Notice
			$mode = 'inner_commar_skip';
			continue;
		}
		
		// , - argument
		if($string[$p+$len] == "," and !$in_string and !$in_binary_string and $brakets_level == 1) {
				
			if(isset($last_string)) {
				$result[$arg_key] = $last_string;
				$result_types[$arg_key] = $mode == 'in_string_end_N' ? 'string-with-N' : 'string';
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
			for($arg_num = 0; $arg_num < $__uni_prt_cnt; $arg_num++)
				if(!isset($result[$arg_num])) { 
					$arg_key = $arg_num;
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
		if (version_compare(PHP_VERSION, '5.6.0') < 0)
			ini_set('mbstring.internal_encoding', $mbstring_internal_encoding);
		else
// 			ini_set('default_charset', $mbstring_internal_encoding);
			mb_internal_encoding($mbstring_internal_encoding);
	}
	
	return array($result, $result_types);
}

function __cursor_filtration(&$tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_data_type = null, $cursor_arg1_data_tracking = null) {
if(/*$cursor_cmd != 'next' or */!empty($GLOBALS['unipath_debug']))
var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:'')); 

	if(in_array($cursor_cmd, array('rewind', 'next')) == false) return false;

	// проверим корректность
	assert('function_exists($tree[$lv]["metadata"]["tree_node"]["metadata"]["cursor()"]);') or var_dump($tree[$lv]["metadata"]['tree_node']);

	// наше искуственное внутреннее дерево
	$tree2 = array( &$tree[$lv]['metadata']['tree_node'] );

	// REWIND - перематаем в начало
	if($cursor_cmd == 'rewind' or ($cursor_cmd == 'next' and empty($tree[$lv]['metadata']['was_rewinded']))) {
		
		$call_result = call_user_func_array($tree2[0]['metadata']['cursor()'], 
			array(&$tree2, 0, 'rewind'));

		$tree[$lv]['metadata']['was_rewinded'] = true;
		$tree[$lv]['metadata']['current_pos'] = 0; // сбросим позицию

		// если попросили только перемотать, то вернём результат
		if($cursor_cmd == 'rewind')
			return $call_result;
	}
	
	// NEXT
	if(strpos($tree[$lv]['metadata']['name'], '%') !== false)
		$sscanf_format = $tree[$lv]['metadata']['name'];
	
	global $__uni_prt_cnt;
	for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

		$call_result = call_user_func_array($tree2[0]['metadata']['cursor()'], 
			array(&$tree2, 0, 'next'/*, $cursor_arg1*/));
if(!empty($GLOBALS['unipath_debug'])) var_dump(__FUNCTION__.".\$call_result => ", $call_result);

		// пустой или отрицательный ответ - возвращаем как есть.
		if(empty($call_result) or !is_array($call_result)) {
			return $call_result;
		}
		
		// теперь фильтруем
		$key = $call_result['metadata']['each_metadata'][0]['key()'];
		$data = $call_result['data'][0];

		// если указана sscanf-маска то проверяем ею сначало ключ
		if(isset($sscanf_format)) {
			$found = sscanf($key, $sscanf_format);

			// если фильтрацию не прошёл - следующий
			if(is_null($found) or is_null($found[0]))
				continue;
		}
				
		// возможно фильтра нет и фильтровать не надо
		if(empty($tree[$lv]['metadata']['filter'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('no filter - PASS');
			return $call_result;
		}
				
		$expr = $tree[$lv]['metadata']['filter']['start_expr'];
		$filter = $tree[$lv]['metadata']['filter'];
		while($expr && isset($filter[$expr])) {
			// left
			switch($filter[$expr]['left_type']) {
				case 'unipath':
					$left_result = __uni_with_start_data(
						$call_result['data'], 
						$call_result['metadata'][0], 
						$call_result['metadata'], 
						$filter[$expr]['left']);
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
						list($args, $args_types) = __uni_parseFuncArgs($filter[$expr]['left']);
						if($args_types[0] == 'unipath')
							$left_result = __uni_with_start_data(
								$call_result['data'], 
								$call_result['metadata'][0], 
								$call_result['metadata'], 
								$args_types[0]);
						else
							$left_result = $filter[$expr]['left'];
						$left_result = strpos($left_result, trim($args[1], '%')) !== false;
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
					$right_result = __uni_with_start_data(
						$call_result['data'], 
						$call_result['metadata'][0], 
						$call_result['metadata'], 
						$filter[$expr]['right']);
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
				case 'list-of-string':
				case 'list-of-string-with-N':
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
		
			// если специально указали, не сохранять ключи, то считаем по своему
			if(isset($call_result['metadata']['preserve_keys']) and $call_result['metadata']['preserve_keys'] == false) {
				$call_result['metadata']['key()'] = $tree[$lv]['metadata']['current_pos']++;
			}
			
			return $call_result;
		} 
	}
	
	return false; // ничего не нашлось подходящего, либо закончились данные
}

/* function __cursor_grouping($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_data_type = null, $cursor_arg1_data_tracking = null) {

	if($cursor_cmd == 'eval') return false;
	assert('$tree');
} */

function __cursor_database(&$tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null, $cursor_arg1_metadata = null) {
if(/*$cursor_cmd != 'next' or*/ !empty($GLOBALS['unipath_debug']))
var_dump((isset($tree[$lv]['name'])?$tree[$lv]['name']:'?')." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&array_key_exists('name', $cursor_arg1)?$cursor_arg1['name']:print_r($cursor_arg1, true)));

	global $__uni_assign_mode;

	// SET - перенаправим в специальный обработчик
	if($cursor_cmd == 'set') {
		return __cursor_database_set($tree, $lv, $cursor_arg1, $cursor_arg1_metadata);
	}

	// db-row - не надо перематывать и обрабатывать курсором
	if($cursor_cmd == 'rewind' and $tree[$lv]['metadata'][0] == 'array/db-row')
		return false;
		
	// дополнительная информация о базе хранится в кеше - она нам пригодится
	if($cursor_cmd == 'rewind' or $cursor_cmd == 'next') {
if(!empty($GLOBALS['unipath_debug'])) var_dump("start \$metadata = ", $tree[$lv]['metadata']);
		if(!isset($GLOBALS['__cursor_database']))
			$GLOBALS['__cursor_database'] = array(array('db' => $tree[$lv]['metadata']['db']));
		foreach($GLOBALS['__cursor_database'] as $num => $item) 
			if($item['db'] == $tree[$lv]['metadata']['db']) 
				$cache_item =& $GLOBALS['__cursor_database'][$num];
		
		$metadata = &$tree[$lv]['metadata'];
	}
	
	// REWIND - попросили перемотать для следующих next()
	if($cursor_cmd == 'rewind') {
// var_dump($tree[$lv]);

		$db = $metadata['db'];
		$sql_query = $metadata['sql_query'];
		$sql_binds = empty($metadata['sql_binds']) ? array() : $metadata['sql_binds'];
		
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql_query;
			elseif (error_reporting() & E_USER_NOTICE)
				trigger_error('UniPath: '.__FUNCTION__.': '.$sql_query, E_USER_NOTICE);
			else
				echo "\nUniPath.".__FUNCTION__.': '.$sql_query; // error_reporting(0);
		}
		
		// ещё не выполнянли запрос - выполним
		if(array_key_exists('stmt', $metadata) == false) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('First rewind');
			switch($metadata['db_type']) {
			case 'object/PDO':
				$res = $db->prepare($sql_query);
// if(!empty($GLOBALS['unipath_debug_sql'])) {
// 				if($GLOBALS['unipath_debug_sql'] == 'simulate')
// 					$res_execute_result = false;
// } else {
				if($res) $res_execute_result = $res->execute($sql_binds);
// }
					
				// сообщим об ошибке в запросе
				if(!$res or isset($res_execute_result) and !$res_execute_result) {
					$err_info = $db->errorInfo();
					if($err_info[0] == '00000')
						trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): PDO: execute() return false! ($sql_query)", E_USER_NOTICE);
					else
						trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): PDO: ".implode(';',$err_info)." ($sql_query)", E_USER_NOTICE);
					$cache_item['last_error()'] = $err_info;
				} 
				
				// успешно выполнен запрос
				else {
					$metadata['stmt'] = $res;
					$metadata['current_pos'] = 0;
					$cache_item['last_affected_rows()'] = $res->rowCount();
// 					if(isset($data_tracking['result_cache']))
// 						$data_tracking['result_cache'] =& $data_tracking['result_cache'];
				}
				break;
			case 'resource/mysql-link':
				$res = mysql_query($sql_query, $db);
				if(empty($res)) {
					trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql_query)", E_USER_NOTICE);
					$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
				} else {
					$metadata['stmt'] = $res;
					$metadata['current_pos'] = 0;
					$cache_item['last_affected_rows()'] = mysql_num_rows($res);
// 					if(isset($data_tracking['result_cache']))
// 						$data_tracking['result_cache'] =& $data_tracking['result_cache'];
				}
				break;
			case 'resource/odbc-link':
				$res = odbc_prepare($db, $sql_query);
				if(empty($res)) {
					trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): ODBC Prepare: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db)." ($sql_query)", E_USER_NOTICE);
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
						trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db)." ($sql_query)", E_USER_NOTICE);
						$cache_item['last_error()'] = array(odbc_error($db), odbc_errormsg($db));
					} else {
						$metadata['stmt'] = $res;
						$metadata['current_pos'] = 0;
						$cache_item['last_affected_rows()'] = odbc_num_rows($res);
// 						if(isset($data_tracking['result_cache']))
// 							$data_tracking['result_cache'] =& $data_tracking['result_cache'];
					}
				} 
				break;
				
			case 'object/mysqli':
				$res = $db->query($sql_query);
				if(empty($res)) {
					trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): MySQLi Prepare: error={$db->errno}, errormsg={$db->error} ($sql_query)", E_USER_NOTICE);
					$cache_item['last_error()'] = array($db->errno, $db->error);
				} else {
					$metadata['stmt'] = $res;
					$metadata['current_pos'] = 0;
					$cache_item['last_affected_rows()'] = $res->num_rows;
				}
			
				break;
				
			default:
				trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): Don`t know how-to work with '".gettype($db)."' of type '".(is_resource($db)?get_resource_type($db):(is_object($db)?get_class($db):'unknown'))."'");
			}
if(!empty($GLOBALS['unipath_debug'])) var_dump($metadata);
			return true;
		} 
		
		// запрос уже выполнен, данные выгружены, ресурс освобждён
		elseif(is_null($metadata['stmt'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('Second rewind');
			if(isset($metadata['result_cache']) and is_array($metadata['result_cache'])) {
// 				reset($data_tracking['result_cache']['stmt_result_rows']);
				$metadata['current_pos'] = 0;
				return true;
			} else {
				trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): stmt_result_rows is not set or not array!", E_USER_NOTICE);
				return false;
			}
		} 
		
		// запрос не смог нормально выполниться?
		elseif($metadata['stmt'] == false) {
if(!empty($GLOBALS['unipath_debug'])) var_dump("stmt = ", $tree[$lv]['metadata']['stmt']);
			return false;
		} 
		
		// не все данные выгружены, а нас просят перемотать - перемотаем выгруженные тогда
		// у result_cache нет своего current_pos! используется общий для запроса
		elseif(!empty($metadata['stmt']) and !empty($metadata['result_cache'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('Rewind result_cache');
			$metadata['current_pos'] = 0;
			return true;
		}
		
		else {
if(!empty($GLOBALS['unipath_debug'])) var_dump("UniPath.".__FUNCTION__."($cursor_cmd): Unknown what to do... ", $metadata);
			trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): Unknown what to do ???");
			return false;
		}
	}

	// NEXT - попросили следующую строку из результата запроса
	if($cursor_cmd == 'next') {
// var_dump($tree[$lv]);

		if(array_key_exists('stmt', $metadata) == false) 
			trigger_error('UniPath.'.__FUNCTION__.": SQL Query not executed! Do rewind at first ({$metadata['sql_query']})", E_USER_NOTICE);
		
		// текущая позиция
		$pos = isset($metadata['current_pos']) ? $metadata['current_pos'] : 0;
			
		// RESULT_CACHE - сначало вытаскиваем из кеша уже выгруженных строк
		if(isset($metadata['result_cache']) && is_array($metadata['result_cache'])
		&& count($metadata['result_cache']) > $pos 
		|| isset($metadata['result_cache_info']['rows_count']) && $metadata['result_cache_info']['rows_count'] == 'all') {
			
			$result = array(
				'data' => array(),
				'metadata' => array('array/db-rows',
					'preserve_keys' => false, // ненадо сохранять значения ключей
					'each_metadata' => array()));
			
			// начнём вытаскивать строки из кеша
			for($i = 1; $i <= (is_null($cursor_arg1) ? 1 : intval($cursor_arg1)); $i++) {
				if(isset($metadata['result_cache'][$pos])) {
					$result['data'][] = $metadata['result_cache'][$pos];
					
					// в режиме присвоения, сохраним дополнительную информацию
// 					if($__uni_assign_mode)
						$result['metadata']['each_metadata'][] = array(
							'array/db-row', 
							'key()' => $pos);
						
					$metadata['current_pos'] = ++$pos;
				} else
					break;
			}
			
			if(empty($result['data']))
				return array();
				
			return $result;
		} 
		
		// STMT - если результат есть, выбераем следующую строку результата
		if(!empty($metadata['stmt'])) {
			$result = array(
				'data' => array(),
				'metadata' => array(
					'array/db-rows',
					'cursor()' => __FUNCTION__,
					'each_metadata' => new SplFixedArray(is_null($cursor_arg1) ? 1 : intval($cursor_arg1))));
					
			for($i = 1; $i <= (is_null($cursor_arg1) ? 1 : intval($cursor_arg1)); $i++) {
			
				// все строки выбраны и запрос уничтожен - прекращаем выбирать строки
				if($metadata['stmt'] === false or is_null($metadata['stmt'])) 
					break;

				// вытаскиваем $row
				switch($metadata['db_type']) {
					case 'object/PDO':
						$row = $metadata['stmt']->fetch(PDO::FETCH_ASSOC);

						// закончились строки, закрываем и освобождаем
						if($row == false) {
							$metadata['stmt']->closeCursor();
							$metadata['stmt'] = null;
if(!empty($GLOBALS['unipath_debug'])) var_dump('UniPath.'.__FUNCTION__.': result_set ended. $metadata = ', $metadata);
						} 
						break;
					case 'resource/mysql-link':
						$row = mysql_fetch_assoc($metadata['stmt']);
						// закончились строки, закрываем и освобождаем
						if($row == false) {
							mysql_free_result($metadata['stmt']);
							$metadata['stmt'] = null;
						}
						break;
					case 'resource/odbc-link':
						$row = odbc_fetch_array($metadata['stmt']);
						// закончились строки, закрываем и освобождаем
						if($row == false) {
							odbc_free_result($metadata['stmt']);
							$metadata['stmt'] = null;
						}
						break;
					case 'object/mysqli':
						$row = $metadata['stmt']->fetch_assoc();
						// закончились строки, закрываем и освобождаем
						if(is_null($row)) {
							$metadata['stmt']->free();
							$metadata['stmt'] = null;
						}
						break;
					default:
						trigger_error("UniPath: ".__FUNCTION__.": Result resource has unknown type! ".gettype($res));
				}
				
				// если есть строка, добавляем к себе
				if($row != false and $row != null) {
				
					$result['data'][$i-1] = $row;
					
					// если подключен кеш, то добавим и туда
					if(isset($metadata['result_cache']))
						$metadata['result_cache'][] = $row;

					// в режиме присвоения, сохраним дополнительную информацию
					if($__uni_assign_mode)
						$result['metadata']['each_metadata'][$i-1] = array(
							'array/db-row',
							'key()' => $metadata['current_pos'], 
							'cursor()' => __FUNCTION__,
							'db' => &$metadata['db'],
// 							'where' => &$metadata['where'],
							'columns' => &$metadata['columns'],
							'tables' => &$metadata['tables']);
					else
						$result['metadata']['each_metadata'][$i-1] = array(
							'array/db-row',
							'key()' => $metadata['current_pos']);
							
					$metadata['current_pos']++;
				}
			}
			
			// если подключен кеш, то обновим информацию о количестви строк в нём
			if(isset($metadata['result_cache']))
				$metadata['result_cache_info']['rows_count'] = count($metadata['result_cache']);
			
			return $result;
		}
		
		// все строки уже были выгружены из результата и запрос закрыт
		elseif(is_null($metadata['stmt'])) {
if(!empty($GLOBALS['unipath_debug'])) var_dump('UniPath.'.__FUNCTION__.": result_set ended. stmt == null.");
			if(isset($metadata['result_cache'])) {
				$metadata['result_cache_info']['rows_count'] = 'all';
			}
			
			return array();
		}
		
		// запрос был неудачный и выгружать из него невозможно
		elseif($metadata['stmt'] === false) {
			trigger_error('UniPath.'.__FUNCTION__.": Result set == false!");
			return array();
		}
		
		// что-то не так
		else {
			trigger_error('UniPath.'.__FUNCTION__.": nothing to return!");
var_dump(__FUNCTION__.':'.__LINE__.': metadata = ', $metadata);
			return array();
		}
	}
	
	// EVAL
	if($cursor_cmd == 'eval') {
// 		if(strpos($cursor_arg1['name'], 'cache(') !== false) return false;
		if(strpos($cursor_arg1['name'], '(') !== false
		|| $cursor_arg1['name'] == '.'
		|| is_numeric($cursor_arg1['name'])) { 
if(!empty($GLOBALS['unipath_debug'])) var_dump((isset($tree[$lv]['name'])?$tree[$lv]['name']:'?')." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:'').' - cant process! (return false)');
			return false;
		}
		
		// все строки (lazy evaluation)
		if($cursor_arg1['name'] == '*') {
			$result = array(
				'data' => null, 
				'metadata' => $tree[$lv]['metadata']);
			$result['metadata'][0] = 'null/db-all-rows';
			return $result;
		}
		
		// колонка из всех строк (lazy evaluation)
		elseif($tree[$lv]['metadata'][0] == 'null/db-all-rows') {
			$result = array(
				'data' => null, 
				'metadata' => $tree[$lv]['metadata']);
			$result['metadata'][0] = 'null/db-all-rows-column';
			return $result;
		}
		
		// несколько полей/колонок
		elseif(is_array($tree[$lv]['data']) and isset($cursor_arg1['separator_1'])) {
			$result = array(
				'data' => array(), 
				'metadata' => $tree[$lv]['metadata']);
			$result['metadata'] = 'array/db-row';
				
			foreach($tree[$lv] as $key => $val) { 
				if(strncmp($key, 'name', 4) != 0) continue;
				$result['data'][$val] = isset($tree[$lv]['data'][$val]) ? $tree[$lv]['data'][$val] : null;
			}
			
			return $result;
		}

		// одинарное поле
		elseif(is_array($tree[$lv]['data']) and array_key_exists($cursor_arg1['name'], $tree[$lv]['data'])) {
			$result = array(
				'data' => $tree[$lv]['data'][$cursor_arg1['name']], 
				'metadata' => array(
						0 => gettype($tree[$lv]['data'][$cursor_arg1['name']]).'/db-row-value',
						'key()' => $cursor_arg1['name']
					) + $tree[$lv]['metadata']);

			/*$db = isset($result['metadata']['db']) ? $result['metadata']['db'] : null;
			$db_type = is_resource($db) 
				? 'resource/'.str_replace(' ', '-', get_resource_type($db))
				: (is_object($db) ? 'object/'.get_class($db) : gettype($db));
			
			// сохраним значение ключевых колонок в трекинге если было .../columns('id KEY'...)/...
			if(isset($result['metadata']['columns'])) 
				$columns = array_filter(
					$result['metadata']['columns'], 
					create_function('$a','return stripos($descr, "KEY") !== false;')
				);
				
			// неуказаны были ключевые поля, тогда добавим все колонки как ключевые
			if(empty($columns))
				$columns = $tree[$lv]['data'];

			if(!isset($result['metadata']['where'])) 
				$result['metadata']['where'] = array();
					
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
				
				$val = $tree[$lv]['data'][$key];
				
				// float значения нельзя сравнивать (из-за бинарной природы) - используем диапазон
				if(is_numeric($val) and substr_count($val, '.') == 1 and strspn($val, '123456789') > 0)
					$result['metadata']['where'][$col_name] = sprintf(" BETWEEN %.3F AND %.3F", $val-0.005, $val+0.005);
				
				// перенесём в where значение экранировав и поставив оператор
				else
				switch($db_type) {
					case 'object/PDO':
						$result['metadata']['where'][$col_name] = "= ".$db->quote(strval($val));
						break;
					case 'resource/mysql-link':
						$result['metadata']['where'][$col_name] = "= '".mysql_real_escape_string(strval($val))."'";
						break;
					case 'resource/odbc-link':
					default:
						$result['metadata']['where'][$col_name] = "= '".strtr(strval($val), array("'" => "''", "\\" => "\\\\"))."'";
				}
					
			}*/

			return $result;
		}
		
		// мы не можем это выполнить -> пусть стандартный алгоритм отработает
		else
			return false;
	}

	trigger_error("UniPath.".__FUNCTION__."($cursor_cmd): Unknown cursor command '{$cursor_arg1['name']}'!", E_USER_NOTICE);
	$result = array(
		'data' => null, 
		'metadata' => array('null', 'cursor()' => __FUNCTION__)); // set?
	return $result;
}

function __cursor_database_describe_tables($tables, $db, &$cache_item) {
	
	if(!isset($cache_item['table_structure'])) 
		$cache_item['table_structure'] = array();
	
	$result = array(); $need_describe_table = array();
	foreach($tables as $table => $where) {
		if(isset($cache_item['table_structure'][$table]))
			$result[$table] = $cache_item['table_structure'][$table];
		else
			$need_describe_table[] = $table;
	}

	// всё, что нужно, было взято из кеша
	if(empty($need_describe_table)) return $result;
	
	$db_type = is_resource($db) 
		? 'resource/'.str_replace(' ', '-', get_resource_type($db))
		: (is_object($db) ? 'object/'.get_class($db) : gettype($db));
		
	// Список специфичных запросов по определению структуры таблиц для каждой СУБД
	$known_describe_table_sql = array(
		'mysql' => 'SHOW COLUMNS FROM ?', // 'DESCRIBE ?',
		'mysqli' => 'SHOW COLUMNS FROM ?', // 'DESCRIBE ?',
		'pgsql' => "SELECT attrelid::regclass, attnum, attname, typname, atttypmod, attnotnull, adsrc, indisprimary, indisunique FROM pg_attribute LEFT OUTER JOIN pg_type ON pg_type.oid = atttypid LEFT OUTER JOIN pg_attrdef ON adrelid = attrelid AND adnum = attnum LEFT OUTER JOIN pg_index ON indrelid = attrelid AND attnum = ANY(indkey) WHERE attrelid = '?'::regclass AND attnum > 0 AND NOT attisdropped ORDER  BY attnum",
		'sqlite3' => "PRAGMA table_info(?)", // 'SELECT * FROM SQLITE_MASTER WHERE tbl_nam = ?'
		'db2' => "SELECT * FROM SYSIBM.COLUMNS WHERE TABLE_NAME = '?' ORDER BY COLNO",
		'oracle' => "SELECT * FROM user_tab_columns WHERE table_name = '?' ORDER column_id", // need strtoupper(table_name)!!!
		'mssql' => "SELECT * FROM information_schema.columns WHERE table_name = '?' ORDER BY ordinal_position" // SQL-92
	);
	
	foreach($known_describe_table_sql as $dbms => $sql) {
	
		// в зависимости от типа соединения с базой выполним запрос
		switch($db_type) {
			case 'object/PDO':
				$first_table = $need_describe_table[0];
				$res = $db->query(str_replace('?', $dbms == 'oracle' ? strtoupper($first_table) : $first_table, $sql));
				
				// неподошло
				if(!$res) {
if(!empty($GLOBALS['unipath_debug'])) var_dump(implode(';',$db->errorInfo()).' => '.str_replace('?', $dbms == 'oracle' ? strtoupper($first_table) : $first_table, $sql));
					continue;
				} 
				
				// подошло
				else {
					$cache_item['dbms'] = $dbms;

					$rows = array();
					while($row = $res->fetch(PDO::FETCH_NUM)) {
						switch($dbms) {
							case 'sqlite3': 
								$rows[$row[1]] = array('type' => $row[2], 'null' => (bool) $row[3], 'default' => $row[4], 'pkey' => (bool) $row[5]);
								break;
							case 'mysql':
								$rows[$row[0]] = array('type' => $row[1], 'null' => (bool) $row[2], 'default' => $row[4], 'pkey' => $row[3] == 'PRI', 'extra' => $row[5]);
								break;
							case 'pgsql':
								$rows[$row[2]] = array('type' => $row[3].($row[4] > 0 ? "({$row[4]})" : ''), 'null' => $row[5] == 1, 'default' => $row[6], 'pkey' => $row[7] == 1);
								break;
							default:
								trigger_error('UniPath.'.__FUNCTION__.": Describe table for $dbms over PDO not implemented yet!", E_USER_ERROR);
						}
						$rows["$first_table.$row[1]"] =& $rows[$row[1]];
					}
					$result[$table] = $cache_item['table_structure'][$first_table] = $rows;
					
					foreach($need_describe_table as $table) if($table != $first_table) {
						if($res = $db->query(
							str_replace('?', $dbms == 'oracle' 
								? strtoupper($first_table) 
								: $first_table, $sql)))
							
							
								
							$result[$table] = $cache_item['table_structure'][$table] = $res->fetchAll();
						else {
							$err_info = $db->errorInfo();
							trigger_error("UniPath: PDO: ".implode(';',$err_info)." (".str_replace('?', $table, $sql).")", E_USER_NOTICE);
						}
					}
					
					break 2;
				}
				break;
				
			case 'resource/odbc-link':
				$first_table = $need_describe_table[0];
				
				$res = odbc_prepare($db, str_replace('?', $dbms == 'oracle' ? strtoupper($first_table) : $first_table, $sql));
				if(!empty($res)) {
					odbc_setoption($res, 2, 0, 15); // time-out
					$res_execute_result = odbc_execute($res, array());
				} 
				
				// неподошло
				if(!$res or empty($res_execute_result)) {
					// $err_info = $db->errorInfo();
					continue;
				} 
				
				// подошло
				else {
					$cache_item['dbms'] = $dbms;
					
					// извлечём текущие строки и полижим в кеш и результат
					for($rows = array(); $row = odbc_fetch_array($res); $rows[] = $row);
					$result[$table] = $cache_item['table_structure'][$table] = $rows;
					odbc_free_result($res);
					
					foreach($need_describe_table as $table) if($table != $first_table) {
						$res = odbc_prepare($db, 
							str_replace('?', $dbms == 'oracle' 
								? strtoupper($table) 
								: $table, $sql));
						if(!empty($res)) {
							odbc_setoption($res, 2, 0, 15); // time-out
							$res_execute_result = odbc_execute($res, array());
						}
						
						if(!empty($res) and !empty($res_execute_result)) {
							for($rows = array(); $row = odbc_fetch_array($res);) $rows[] = $row;
							$result[$table] = $cache_item['table_structure'][$table] = $rows;
							odbc_free_result($res);
						} 
						
						else {
							trigger_error("UniPath: ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
						}
					}
					
					break 2;
				}
				break;
			case 'resource/mysql-link':
				if($dbms != 'mysql') continue;
			
				foreach($need_describe_table as $table) {
					$res = mysql_query(str_replace('?', $table, $sql), $db);
					
					if(!empty($res)) {
						for($rows = array(); $row = mysql_fetch_array($res);) {
							$rows[$row[0]] = array('type' => $row[1], 'null' => (bool) $row[2], 'default' => $row[4], 'pkey' => $row[3] == 'PRI', 'extra' => $row[5]);
							$rows["$table.$row[0]"] =& $rows[$row[0]];
						}
						$result[$table] = $cache_item['table_structure'][$table] = $rows;
						mysql_free_result($res);
					} 
						
					else {
						trigger_error("UniPath: ".__FUNCTION__.": MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." (".str_replace('?', $table, $sql).")");
					}
				}
				break 2;
			case 'object/mysqli':
				if($dbms != 'mysqli') continue;
				
				foreach($need_describe_table as $table) {
					$res = $db->query(str_replace('?', $table, $sql));
					
					if(!empty($res)) {
						for($rows = array(); $row = $res->fetch_array();) {
							$rows[$row[0]] = array('type' => $row[1], 'null' => (bool) $row[2], 'default' => $row[4], 'pkey' => $row[3] == 'PRI', 'extra' => $row[5]);
							$rows["$table.$row[0]"] =& $rows[$row[0]];
						}
						$result[$table] = $cache_item['table_structure'][$table] = $rows;
						$res->free();
					} 
						
					else {
						trigger_error("UniPath: ".__FUNCTION__.": MySQLi Query: errno={$db->errno}, error={$db->error} (".str_replace('?', $table, $sql).")");
					}
				}
				break 2;
			default:
				trigger_error("UniPath: ".__FUNCTION__.": Don`t know how-to work with $db_type");
		}
	}
	
	return $result;
}

function __cursor_database_set($tree, $lv, $set_value, $cursor_arg1_metadata = null) {
// print_r(array($tree[$lv]['data'], $set_value));
	if(in_array($tree[$lv]["metadata"][0], array('null', 'NULL'))) {
		trigger_error("UniPath.".__FUNCTION__.": metadata[0] == 'null'! Data not changed.", E_USER_NOTICE);
		return false;
	}
	
	assert('isset($tree[$lv]["metadata"]["tables"])');

	$data_type = $tree[$lv]['metadata'][0];
	$metadata = $tree[$lv]['metadata'];
	$db = $metadata['db'];
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
	elseif(strpos($data_type, '/db-row') > 0)
		foreach($set_value as $key => $val)
			if(is_numeric($key)) { // при присвоении не обязательно указывать название колонок
				isset($col_names) or $col_names = array_keys($tree[$lv]['data']);
				$names_and_values[$col_names[$key]] = $val;
			} else
				$names_and_values[$key] = $val;
	
	// (lazy evaluation) все строки меняем
	elseif($data_type == 'null/db-all-rows') {
		foreach($set_value as $key => $val)
			$names_and_values[$key] = $val;
	}
	
	// (lazy evaluation) колонка из всех строк меняем
	elseif(strpos($data_type, 'null/db-') !== false) {
// print_r($tree); print_r($set_value);
		$names_and_values[$tree[$lv]['name']] = $set_value;
	}
	
	elseif($data_type == 'array/sql-query-with-params') {
		$names_and_values = $set_value;
	}

	else
		trigger_error('UniPath.'.__FUNCTION__.': Unknown data_type! ('.$data_type.')', E_USER_ERROR);

	// сначало соберём информацию о структуре таблиц
	$tables_descr = __cursor_database_describe_tables($metadata['tables'], $db, $cache_item);
// print_r($tables_descr);

	// формируем UPDATE SET ...
	$sql_upd = array(/* 
		table1 => array(
			col1 => new_value1, 
			col2 => new_value1, 
			...), 
		table2 => ...etc... */);
	foreach($names_and_values as $name => $set_value) {
	
		// иногда название таблицы встречается в ключе
		switch(substr_count($name, '.')) {
			case 3: 
				list($name_db_schema, $name_tbl, $name_col) = explode('.', $name); 
				$tables_descr_for_search = array($name_tbl => $tables_descr[$name_tbl]);
				break;
			case 2: 
				list($name_tbl, $name_col) = explode('.', $name); 
				$tables_descr_for_search = array($name_tbl => $tables_descr[$name_tbl]);
				break;
			default: 
				$name_col = $name; 
				$tables_descr_for_search = $tables_descr;
				break;
		}
	
		// ищем нужную одноимённую колонку по таблицам
		foreach($tables_descr_for_search as $table => $cols) {
		
			// если в этой таблице нашли искомое поле
			if(isset($cols[$name_col])) {
				
				// начнём таблицу
				isset($sql_upd_set[$table]) or $sql_upd_set[$table] = array();
						
				// подготовим новое значение
				if(is_null($set_value))
					$sql_upd_set[$table][$name_col] = "NULL";
								
				// если числовой
				elseif(is_numeric($set_value) and $set_value[0] !== '0' and $set_value[0] != '+') 
					$sql_upd_set[$table][$name_col] = "$set_value";

				// это мы необрабатываем!
				elseif(is_array($set_value) or is_object($set_value) or is_resource($set_value)) {
					trigger_error('UniPath.database_set: '.$name_col.' == '.gettype($set_value).'!!! ('.print_r($set_value, true).')');
				} 

				// всё остальное преобразуем в строку и экранируем
				else
				switch($db_type) {
					case 'object/PDO':
						$sql_upd_set[$table][$name_col] = $db->quote($set_value);
						break;
					case 'resource/mysql-link':
						$sql_upd_set[$table][$name_col] = "'".mysql_real_escape_string($set_value)."'";
						break;
					case 'resource/odbc-link':
					default:
						$sql_upd_set[$table][$name_col] = "'".str_replace("'","''",$set_value)."'";
				}
				
				break;
			}
		}
	}

if(!empty($GLOBALS['unipath_debug'])) var_dump($sql_upd_set);

	// теперь добавим WHERE
	$sql_upd_where = array(); $sql_upd_where_pkey_used = array();
	foreach($sql_upd_set as $table => $values) {
		$table_prefix = $table.'.'; $table_prefix_len = strlen($table)+1;

		// если работаем только с одной таблицой, то можно все условия использовать
// var_dump(__FILE__.':'.__LINE__, $data_type);
		if(count($metadata['tables']) == 1 and strpos($data_type, '/db-row-value') === false) {
			// ищем полное вырожение
			for($expr = $metadata['tables'][$table]['start_expr']; !empty($metadata['tables'][$table][$expr]['next']);)
				$expr = $metadata['tables'][$table][$expr]['next'];
		
			$sql_upd_where[$table][] = $metadata['tables'][$table][$expr]['sql'];
			continue;
		}
		
// var_dump($metadata['tables'][$table]);
		foreach($metadata['tables'][$table] as $expr_id => $expr) if(is_array($expr)) {
// var_dump($expr);

			// пока что только col_name =... обрабатываем
			if(empty($expr['op']) or in_array($expr['op'], array('=', '!=', '<>', '<', '>', '>=', '<=')) == false and $expr['left_type'] = 'name') {
				trigger_error(__FUNCTION__.": can't use condition -> ".print_r($expr, true), E_USER_WARNING);
				$sql_upd_where[$table][] = "'this_update_is_blocked'='because_a_bad_condition_was_detected'"; // забракуем этот update
				continue;
			}
			
			if(substr_compare($expr['left'], $table_prefix, 0, $table_prefix_len, true) == 0
			|| isset($tables_descr[$table], $tables_descr[$table][$expr['left']])) {
// var_dump($expr['left'], $expr['sql']);
				isset($sql_upd_where[$table]) or $sql_upd_where[$table] = array();
				$sql_upd_where[$table][] = $expr['sql'];
				
				// если это PRIMARY KEY колонка, то этого достаточно.
				if($tables_descr[$table][$expr['left']]['pkey'])
					$sql_upd_where_pkey_used[] = $table;
			}
		}
		
		if(isset($sql_upd_where[$table]))
		$sql_upd_where[$table] = array(implode(' AND ', $sql_upd_where[$table]));
	}
// if(isset($GLOBALS['unipath_debug_sql'])) var_dump(__FILE__.':'.__LINE__, $sql_upd_where_pkey_used);

	// есть ли уже полученные строки из базы?
	if($tree[$lv]['metadata'][0] == 'array/db-row')
		$rows = array($tree[$lv]['data']);
	elseif($tree[$lv]['metadata'][0] == 'array/sql-query-with-params')
		$rows = array();
	elseif(strpos($tree[$lv]['metadata'][0], '/db-row-value') > 0)
		$rows = array($tree[$lv-1]['data']);
	else // пока что всё остальное в том числе и 'null/db-all-rows'
		$rows = (array) $tree[$lv]['data'];

	// попробуем задействовать уже полученные строки из базы для WHERE
	foreach($rows as $row) {
		assert('is_array($row)') or var_dump('not array -> ', $row);
		
		// соберём where для выбранной строки
		$sql_where = array();
		foreach($row as $col_name => $val) {

			// поищем чья колонка
			$table = null;
			foreach($tables_descr as $descr_table => $descr_cols)
				if(isset($descr_cols[$col_name])) {
					if(in_array($descr_table, $sql_upd_where_pkey_used)) 
						continue 2; // уже используется PRIMARY KEY -> пропускаем

					$table = $descr_table;
					break;
				}
			
			// колонка ничейная -> пропускаем (либо добавить всем?)
			if(empty($table)) continue;

			// float значения нельзя сравнивать (из-за бинарной природы) -> используем диапазон
			if(is_numeric($val) and substr_count($val, '.') == 1 and strspn($val, '123456789') > 0)
				$sql_where_expr = $col_name . sprintf(" BETWEEN %.3F AND %.3F", $val-0.005, $val+0.005);
			
			// числовые не экранируем
			elseif(is_numeric($val) and $val[0] !== '0' or $val === '0')
				$sql_where_expr = $col_name . ' = '. strval($val);
			
			// всё остальное преобразуем в строку и экранируем
			else
			switch($db_type) {
				case 'object/PDO':
					$sql_where_expr = $col_name . ' = '. $db->quote(strval($val));
					break;
				case 'resource/mysql-link':
					$sql_where_expr = "$col_name = '".mysql_real_escape_string($val)."'";
					break;
				case 'resource/odbc-link':
				default:
					$sql_where_expr = "$col_name = '".str_replace("'","''",strval($val))."'";
			}
			
			// это ключевая колонка оказывается -> ограничемся ею тогда только
			if(!empty($tables_descr[$table][$col_name]['pkey'])) {
// if(isset($GLOBALS['unipath_debug_sql'])) var_dump("KEY $col_name = ".json_encode($tables_descr[$table][$col_name]));
				$sql_upd_where_pkey_used[] = $table;
				$sql_where[$table] = array($sql_where_expr);
			}
			
			// добавим в WHERE
			else {
				isset($sql_where[$table]) or $sql_where[$table] = array();
				$sql_where[$table][] = $sql_where_expr;
			}
		}
		
		// рассартируем по отдельным запросам для каждой таблицы
		foreach($sql_upd_set as $table => $values) 
			if(isset($sql_where[$table])) {
				isset($sql_upd_where[$table]) or $sql_upd_where[$table] = array();
				$sql_upd_where[$table][] = implode(' AND ', $sql_where[$table]);
			}
	}
		
	// выполним запросы
	foreach($sql_upd_set as $table => $new_values) {
	
		// сформируем конечный запрос
		$sql_upd = "UPDATE $table SET "
			.implode(', ', array_map(create_function('$a, $b', 'return "$b = $a";'), $new_values, array_keys($new_values)))
			.(isset($sql_upd_where[$table]) ? " WHERE ".implode(' OR ',$sql_upd_where[$table]) : '');
if(!empty($GLOBALS['unipath_debug'])) var_dump($sql_upd);
		// отладка SQL-запросов
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql_upd;
			elseif (error_reporting() & E_USER_NOTICE)
				trigger_error('UniPath: '.__FUNCTION__.': '.$sql_upd, E_USER_NOTICE);
			else
				echo "\nUniPath: ".__FUNCTION__.': '.$sql_upd; // error_reporting(0);
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
				// отладочный вывод о результате запроса
				if(isset($GLOBALS['unipath_debug_sql']) and !is_array($GLOBALS['unipath_debug_sql'])) {
					error_reporting() & E_USER_NOTICE
						? trigger_error("\$res = ".print_r($res, true).", \$res_execute_result = ".(isset($res_execute_result)?$res_execute_result:'undefined').", rowCount = ".(is_object($res) ? $res->rowCount(): 'NULL'), E_USER_NOTICE)
						: var_dump("\$res = ".print_r($res, true).", \$res_execute_result = ".(isset($res_execute_result)?$res_execute_result:'undefined').", rowCount = ".(is_object($res) ? $res->rowCount(): 'NULL'));
				}
				
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
					trigger_error("UniPath: ".__FUNCTION__.": ODBC Prepare: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
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
					trigger_error("UniPath: ".__FUNCTION__.": ODBC: unipath_debug_sql = simulate! ($sql_upd)", E_USER_NOTICE);
				elseif($res and !$res_execute_result) {
					trigger_error("UniPath: ".__FUNCTION__.": ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = array(odbc_error($db), odbc_errormsg($db));
				} else {
					$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = odbc_num_rows($res);
				}
				break;
			case 'resource/mysql-link':
				$stmt = mysql_query($sql_upd, $db);
				if(empty($stmt)) {
					trigger_error("UniPath: ".__FUNCTION__.": MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql_upd)");
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = array(mysql_errno($db), mysql_error($db));
				} else {
					$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = mysql_affected_rows($db);
				}
				
				// отладочный вывод
				if(isset($GLOBALS['unipath_debug_sql']) and !is_array($GLOBALS['unipath_debug_sql'])) {
					error_reporting() & E_USER_NOTICE
						? trigger_error("\$db = ".print_r($db, true).", mysql_query() = $stmt, mysql_affected_rows() = ".mysql_affected_rows($db).", mysql_errno() = ".mysql_errno($db).", mysql_error() = ".mysql_error($db), E_USER_NOTICE)
						: var_dump("\$db = ".print_r($db, true).", mysql_query() = $stmt, mysql_affected_rows() = ".mysql_affected_rows($db).", mysql_errno() = ".mysql_errno($db).", mysql_error() = ".mysql_error($db));
				}
				break;
			case 'object/mysqli':
				$stmt = $db->query($sql_upd);
				if(empty($stmt)) {
					trigger_error("UniPath: ".__FUNCTION__.": MySQLi Query: errno={$db->errno}, error={$db->error} ($sql_upd)");
					$cache_item['last_error()'] = $cache_item["last_error($table)"] = array($db->errno, $db->error);
				} 
				else {
					$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $db->affected_rows;
				}
				break;
			default:
				trigger_error("UniPath: ".__FUNCTION__.": We don`t know how-to work with $db_type");
		}
	}
if(function_exists('mark')) mark("database_set(UPDATE)");
}

function _uni_sql_table_prefix($tree, $lv) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	$tree[$lv-1]['metadata']['table_prefix'] = $args[0];

	return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
}

function _uni_last_affected_rows($tree, $lv = 0) { return _uni_last_error($tree, $lv); }
function _uni_last_insert_id($tree, $lv = 0) { return _uni_last_error($tree, $lv); }
function _uni_last_error($tree, $lv = 0) {
	$key = stripos($tree[$lv]['name'], 'last_error(') === 0 
		? 'last_error()'
		: (stripos($tree[$lv]['name'], 'last_insert_id(') === 0
			? 'last_insert_id()'
			: 'last_affected_rows()');

	// для базданных используется специальный кеш
	if(isset($GLOBALS['__cursor_database']))
	foreach($GLOBALS['__cursor_database'] as $num => $item)
		if($item['db'] == $tree[$lv-1]['data'])
			$cache_item =& $GLOBALS['__cursor_database'][$num];

	if(isset($cache_item) and is_array($cache_item) and array_key_exists($key, $cache_item)) {
		$result = array(
			'data' => $cache_item[$key],
			'metadata' => array(gettype($cache_item[$key])));
	} 
	elseif(array_key_exists($key, $tree[$lv-1]['metadata'])) {
		$result = array(
			'data' => $tree[$lv-1]['metadata'][$key], 
			'metadata' => array(gettype($tree[$lv-1]['metadata'][$key])));
	}
	else 
		$result = array('data' => null, 'metadata' => array('null'));

	return $result;
}

// TODO odbc support
function _uni_add_row($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) { return _uni_new_row($tree, $lv); }
function _uni_new($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) { return _uni_new_row($tree, $lv); }
function _uni_new_row($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
// var_dump($tree[$lv]['name']." -- $cursor_cmd");

	// .../new_row()
	if(is_null($cursor_cmd) and is_null($cursor_arg1))
		return array(
			'data' => null, 
			'metadata' => array(
				'array/db-row',
				'cursor()' => __FUNCTION__,
				'db' => isset($tree[$lv-1]['metadata']['db']) 
					? $tree[$lv-1]['metadata']['db'] 
					: $tree[$lv-1]['data'][0], 
				'from_tables' => isset($tree[$lv-1]['metadata']['tables']) 
					? array_keys($tree[$lv-1]['metadata']['tables'])
					: array($tree[$lv-1]['data'][1])
				)
		);
	
	if($cursor_cmd == 'set') {
		if(is_array($cursor_arg1) == false) {
			trigger_error('UniPath.'.__FUNCTION__.': $cursor_arg1 is not an array! ('.$tree[$lv]['unipath'].')');
			return false;
		}
	
		$metadata = $tree[$lv]['metadata'];
		$db = $metadata['db'];
		$db_type = is_resource($db) 
			? 'resource/'.str_replace(' ', '-', get_resource_type($db))
			: (is_object($db) ? 'object/'.get_class($db) : gettype($db));
		$table = $metadata['from_tables'][0];
		
		// проверим подключение к базе данных
		if(!is_resource($db) and !is_object($db)) {
			trigger_error('UniPath.'.__FUNCTION__.': $db is not resource or object! ('.$tree[$lv]['unipath'].')');
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
			elseif(is_array($val)) {
				trigger_error(__FUNCTION__.": Bad value for $key - ".print_r($val, true));
			}
			elseif(is_bool($val))
				$sql_vals[] = $val ? 1 : "''";
			else
				$sql_vals[] = strval($val);

		$sql = "INSERT INTO {$table} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")";
		
		if(isset($GLOBALS['unipath_debug_sql'])) {
			if(is_array($GLOBALS['unipath_debug_sql']))
				$GLOBALS['unipath_debug_sql'][] = $sql;
			elseif(error_reporting() & E_USER_NOTICE)
 				trigger_error('UniPath: '.__FUNCTION__.': '.$sql, E_USER_NOTICE);
 			else
				echo "\nUniPath.".__FUNCTION__.': '.$sql; // error_reporting(0);
		}

		// MySQL
		if($db_type == 'resource/mysql-link') {
			$stmt = mysql_query($sql, $db);
			if(empty($stmt)) {
				trigger_error("UniPath.__cursor_database: MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql)");
				$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
			} else {
				$cache_item['last_affected_rows()'] = mysql_affected_rows($db);
				$cache_item['last_insert_id()'] = $cache_item["last_insert_id($table)"] = mysql_insert_id();
			}
		}
		
		// MySQLi
		elseif ($db_type == 'object/mysqli') {
			$stmt = $db->prepare($sql);
			
			// режим симуляции выполнения INSERT
			if(!empty($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate')
				$db_execute_result = false;
			elseif($stmt) 
				$db_execute_result = $stmt->execute();
			
			if($stmt and !empty($db_execute_result)) {
				$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $stmt->affected_rows;
				$cache_item['last_insert_id()'] = $cache_item["last_insert_id($table)"] = $stmt->insert_id;
			} 
			else {
				if($db->sqlstate == '00000' and isset($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
					/* skip ??? */
					trigger_error("UniPath.".__FUNCTION__.": MySQLi: unipath_debug_sql = simulate! ($sql)", E_USER_NOTICE);
					var_dump($GLOBALS['unipath_debug_sql']);
				}
				elseif($db->sqlstate == '00000' and empty($db_execute_result))
					trigger_error("UniPath.".__FUNCTION__.": MySQLi: execute() return false! ($sql)", E_USER_NOTICE);
				else
					trigger_error("UniPath.".__FUNCTION__.": MySQLi: {$db->sqlstate};{$db->error};{$db->errno} ($sql)", E_USER_NOTICE);
			
				$cache_item['last_error()'] = $cache_item["last_error($table)"] = array($db->sqlstate, $db->error, $db->errno);
			}
		}
		
		// PDO
		else {
			$stmt = $db->prepare($sql);
	
			// режим симуляции выполнения INSERT
			if(!empty($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate')
				$db_execute_result = false;
			elseif($stmt) 
				$db_execute_result = $stmt->execute();

			if(isset($GLOBALS['unipath_debug_sql'])) {
				error_reporting() & E_USER_NOTICE
					? trigger_error("\$stmt = ".print_r($stmt, true).", \$db_execute_result = ".(isset($db_execute_result)?$db_execute_result:'undefined').", rowCount = ".(is_object($stmt)?$stmt->rowCount():$stmt), E_USER_NOTICE)
					: var_dump("\$stmt = ".print_r($stmt, true).", \$db_execute_result = ".(isset($db_execute_result)?$db_execute_result:'undefined').", rowCount = ".(is_object($stmt)?$stmt->rowCount():$stmt));
			}
	
			if($stmt and !empty($db_execute_result)) {
				$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $stmt->rowCount();
				$cache_item['last_insert_id()'] = $cache_item["last_insert_id($table)"] = $db->lastInsertId();
			} else {
				$err_info = $db->errorInfo();

				if($err_info[0] == '00000' and isset($GLOBALS['unipath_debug_sql']) and $GLOBALS['unipath_debug_sql'] === 'simulate') {
					/* skip ??? */
					trigger_error("UniPath.".__FUNCTION__.": PDO: unipath_debug_sql = simulate! ($sql)", E_USER_NOTICE);
					var_dump($GLOBALS['unipath_debug_sql']);
				}
				elseif($err_info[0] == '00000' and empty($db_execute_result))
					trigger_error("UniPath.".__FUNCTION__.": PDO: execute() return false! ($sql)", E_USER_NOTICE);
				else
					trigger_error("UniPath.".__FUNCTION__.": PDO: ".implode(';',$err_info)." ($sql)", E_USER_NOTICE);
			
				$cache_item['last_error()'] = $cache_item["last_error($table)"] = $err_info;
			}
		}
		
		return ($stmt or !empty($db_exec_result));
	}
}

function _uni_delete($tree, $lv = 0) {
	assert('isset($tree[$lv-1]["metadata"]["db"])');
	assert('isset($tree[$lv-1]["metadata"]["tables"])');
	assert('isset($tree[$lv-1]["metadata"]["where"])');
	
	$data_type = $tree[$lv-1]['metadata'][0];
	$metadata = $tree[$lv-1]['metadata'];
	$db = $metadata['db'];
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

	// может быть просто список таблиц, а может быть [table_name=>$filter, ...]
	foreach($metadata['tables'] as $key => $val) {
		$table = is_numeric($key) ? $val : $key;
	}
	
	$table_prefix = "$table.";
	$sql_where = "WHERE ";
	if (is_array($metadata['where']))
	foreach($metadata['where'] as $col_name => $val) {
	
		// если поле относится к нашей таблице, то добавляем к нашему WHERE
		if(strpos($col_name, $table_prefix) === 0 or strpos($col_name, '.') === false) {
		
			/* ! правая часть уже экранирована ! */
			if($col_name[0] == '-')
				$sql_where .= $sql_where == "WHERE " ? $val : " AND $val";
			else
				$sql_where .= $sql_where == "WHERE " ? "$col_name $val" : " AND $col_name $val";
		}
	}
	else
		$sql_where = stripos($metadata['where'], 'WHERE ') === 0 
			? $metadata['where'] 
			: $sql_where.$metadata['where'];
	
	$sql = "DELETE FROM $table $sql_where";
	
	$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = null;
	$cache_item['last_error()'] = $cache_item["last_error($table)"] = null;
	
	// в зависимости от типа соединения с базой выполним
	switch($db_type) {
		case 'object/PDO':
			$res = $db->prepare($sql);
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
					trigger_error("UniPath: ".__FUNCTION__.": PDO: unipath_debug_sql = simulate! ($sql)", E_USER_NOTICE);
				elseif($err_info[0] == '00000')
					trigger_error("UniPath: ".__FUNCTION__.": PDO: execute() return false! ($sql)", E_USER_NOTICE);
				else
					trigger_error("UniPath: ".__FUNCTION__.": PDO: ".implode(';',$err_info)." ($sql)", E_USER_NOTICE);
					
				$cache_item['last_error()'] = $cache_item["last_error($table)"] = $err_info;
			} else {
				$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = $res->rowCount();
			}
			break;
		case 'resource/odbc-link':
			$res = odbc_prepare($db, $sql);
			if(empty($res)) {
				trigger_error("UniPath: ".__FUNCTION__.": ODBC Prepare: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
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
				trigger_error("UniPath: ".__FUNCTION__.": ODBC: unipath_debug_sql = simulate! ($sql)", E_USER_NOTICE);
			elseif($res and !$res_execute_result) {
				trigger_error("UniPath: ".__FUNCTION__.": ODBC Execute: odbc_error=".odbc_error($db).", odbc_errormsg=".odbc_errormsg($db));
				$cache_item['last_error()'] = $cache_item["last_error($table)"] = array(odbc_error($db), odbc_errormsg($db));
			} else {
				$cache_item['last_affected_rows()'] = $cache_item["last_affected_rows($table)"] = odbc_num_rows($res);
			}
			break;
		case 'resource/mysql-link':
			if(isset($GLOBALS['unipath_debug_sql'])) {
				if(is_array($GLOBALS['unipath_debug_sql']))
					$GLOBALS['unipath_debug_sql'][] = $sql;
				else
					trigger_error('UniPath: '.__FUNCTION__.': '.$sql, E_USER_NOTICE);
// 					echo "\nUniPath: ".__FUNCTION__.': '.$sql; // error_reporting(0);
			}
		
			$stmt = mysql_query($sql, $db);
			if(empty($stmt)) {
				trigger_error("UniPath: ".__FUNCTION__.": MySQL Query: mysql_errno=".mysql_errno($db).", mysql_error=".mysql_error($db)." ($sql)");
				$cache_item['last_error()'] = array(mysql_errno($db), mysql_error($db));
			} else {
				$cache_item['last_affected_rows()'] = mysql_affected_rows($db);
			}
			break;
		case 'object/mysqli':
			if(isset($GLOBALS['unipath_debug_sql'])) {
				if(is_array($GLOBALS['unipath_debug_sql']))
					$GLOBALS['unipath_debug_sql'][] = $sql;
				else
					trigger_error('UniPath: '.__FUNCTION__.': '.$sql, E_USER_NOTICE);
// 					echo "\nUniPath.".__FUNCTION__.': '.$sql; // error_reporting(0);
			}
			
			$stmt = mysqli_query($db, $sql);
			if(empty($stmt)) {
				trigger_error("UniPath: ".__FUNCTION__.": MySQLi Query: mysqli_errno=".mysqli_errno($db).", mysqli_error=".mysqli_error($db)." ($sql)");
				$cache_item['last_error()'] = array(mysqli_errno($db), mysqli_error($db));
			} else {
				$cache_item['last_affected_rows()'] = mysqli_affected_rows($db);
			}
			break;
		default:
			trigger_error("UniPath: ".__FUNCTION__.": We don`t know how-to work with $db_type");
	}

if(!empty($GLOBALS['unipath_debug'])) var_dump($sql, $cache_item["last_error($table)"]);
	
	return array('data' => $tree[$lv-1]['data'], 'metadata' => $metadata );
}

function _uni_insert_into($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

		$dst = __uni_with_start_data(
			$tree[$lv-1]['data'], 
			$tree[$lv-1]['metadata'][0], 
			$tree[$lv-1]['metadata'], $args[0]);
		
		if(empty($dst['data'])) {
			trigger_error('UniPath: '.__FUNCTION__.': arg1 is not database table description! (arg1 = '.json_encode($args[0]).')');
			return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
		}
		
		$db = $dst['data'][0];

		$sql_fileds = array_keys($tree[$lv-1]['data']); 
		$sql_vals = array();
		foreach($tree[$lv-1]['data'] as $key => $val)
			if(is_null($val))
				$sql_vals[] = 'NULL';
			elseif(is_string($val))
				$sql_vals[] = is_object($db) && method_exists($db, 'quote') // PDO
					? $db->quote($val)
					: (is_object($db) && method_exists($db, 'real_escape_string') // mysqli
					? $db->real_escape_string($val)
					: (function_exists('mysql_real_escape_string') // mysql
					? mysql_real_escape_string(strval($val))
					: "'".str_replace("'", "''", strval($val))."'")); // odbc
			else
				$sql_vals[] = $val;
//echo "INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")";
		if (is_object($db) && method_exists($db, 'exec')) { // PDO
			if($db->exec("INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")") == false)
				trigger_error("UniPath: ".__FUNCTION__.": ".$db->errorInfo());
			$result = $db->lastInsertId();
		}
		elseif (is_object($db) && method_exists($db, 'query')) { // mysqli
			if($db->query("INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")") == false)
				trigger_error("UniPath: ".__FUNCTION__.": {$db->error} ({$db->errno})");
			$result = $db->insert_id;
		}
		elseif (function_exists('mysql_query')) { // mysql
			if(mysql_query("INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")", $db) == false)
				trigger_error("UniPath: ".__FUNCTION__.": ".mysql_error()." (".mysql_errno().")");
			$result = mysql_insert_id($db);
		}
		elseif (function_exists('odbc_query')) { // odbc
			if(odbc_exec($db, "INSERT INTO {$dst['data'][1]} (".implode(', ', $sql_fileds).") VALUES (".implode(', ', $sql_vals).")") == false)
				trigger_error("UniPath: ".__FUNCTION__.": ".odbc_errormsg()." (".odbc_error().")");
			$result = odbc_exec("SELECT @@IDENTITY AS LastID", $db);
			$result = odbc_fetch_array($result);
		}
		else {
			trigger_error("UniPath: ".__FUNCTION__.": We dont know how-to work with $db!");
			$result = null;
		}
		
		return array('data' => $result, 'metadata' => array('integer'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_cached($tree, $lv) { return _uni_cache($tree, $lv); }
function _uni_cache($tree, $lv = 0) {
	global $GLOBALS_metadata, $GLOBALS_data_timestamp;

	$result = array('data' => null, 'metadata' => array('null'));
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	$arg1 = $args[0];
	$arg1_type = $args_types[0];
	if($arg1_type == 'string') $arg1 = '/'.$arg1; // временно, для совместимости со старым кодом
	$lifetime = isset($args['lifetime']) ? $args['lifetime'] : 2147483647;

// var_dump("cache key = ".$arg1);
	/* --------  cache(/var1) ---------- */
	if(sscanf($arg1, '%[/$]%[qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789]', $first_char, $var_name) == 2 and strlen($arg1) == strlen($var_name)+1) {

		// save
		if($lv > 0 and $tree[$lv-1]['name'] != '?start_data?') {
			$GLOBALS[$var_name] = $result['data'] = $tree[$lv-1]['data'];
			$GLOBALS_metadata[$var_name] = $result['metadata'] = $tree[$lv-1]['metadata'];
			$GLOBALS_data_timestamp[$var_name] = time();
// var_dump("save to cache - \$GLOBALS[$var_name]", $result);
		} 
		
		// save+restore
		// если ничего ещё небыло закешировано и указан 2ой аргумент, то закешируем его
		elseif(!isset($GLOBALS[$var_name]) and isset($args[1])) {
			if($args_types[1] == 'unipath') {
				$uni_result = __uni_with_start_data(
					$tree[$lv-1]['data'], 
					$tree[$lv-1]['metadata'][0], 
					$tree[$lv-1]['metadata'], 
					$args[1]);
				$result['data'] = $GLOBALS[$var_name] = $uni_result['data'];
				$GLOBALS_metadata[$var_name] = $result['metadata'] = $uni_result['metadata'];
				$GLOBALS_data_timestamp[$var_name] = time();
			}
			else {
				$result['data'] = $GLOBALS[$var_name] = $args[2];
				$GLOBALS_metadata[$var_name] = $result['metadata'] = array(gettype($args[2]));
			}
		}
			
		// restore
		else {
// var_dump("restore from cache - \$GLOBALS[$var_name]");
			$result['data'] = isset($GLOBALS[$var_name]) ? $GLOBALS[$var_name] : null;
			if(array_key_exists($var_name, $GLOBALS_metadata))
				$result['metadata'] = & $GLOBALS_metadata[$var_name];
			else
				$result['metadata'] = array(gettype($result['data']));
				
			// проверим lifetime
			if(isset($GLOBALS_data_timestamp[$var_name])
				and $GLOBALS_data_timestamp[$var_name] < time() - $lifetime
				/* and strpos($_SERVER['HTTP_HOST'], '.loc') === false*/) {
				$result['data'] = null;
				$result['metadata'] = array('null');
			}
		}
		
		return $result;
 	} 
	
	/* ---------- cache(/.../...) - json_encode/json_decode ---------- */
	// save
	if($lv > 0 and $tree[$lv-1]['name'] != '?start_data?') {
// var_dump("save to cache - uni({$arg1}, ...)");
		__uni_with_start_data(
			$tree[$lv-1]['data'], 
			null, 
			$tree[$lv-1]['metadata'], 
			$arg1, 
			json_encode($tree[$lv-1]+array('cache_timestamp' => time())));
		$result['data'] = $tree[$lv-1]['data'];
		$result['metadata'] = $tree[$lv-1]['metadata'];
	} 
	
	// restore
	else {
		$cached_data = __uni_with_start_data($tree[$lv-1]['data'], $tree[$lv-1]['metadata'][0], $tree[$lv-1]['metadata'], $arg1);
		
		// если ничего ещё небыло закешировано и указан 2ой аргумент, то закешируем его
		if(!isset($cached_data['data']) and isset($args[1])) {
			$uni_result_for_caching = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				$tree[$lv-1]['metadata'][0], 
				$tree[$lv-1]['metadata'],
				$args[1]);
			$result = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				$tree[$lv-1]['metadata'][0], 
				$tree[$lv-1]['data_tracking'], 
				$arg1,
				json_encode($uni_result_for_caching + array('cache_timestamp' => time())));
		}
		
// var_dump("restore from cache - uni({$arg1})", $json_string);
		if(is_string($cached_data['data'])) {
			$json_string = json_decode($cached_data['data'], true);
			
			// проверим валидность и lifetime
			if(is_array($json_string)) {
				if(isset($json_string['cache_timestamp'])
				and $json_string['cache_timestamp'] < time() - $lifetime
				/* and strpos($_SERVER['HTTP_HOST'], '.loc') === false*/) {
					$result['data'] = null;
					$result['metadata'] = array('null');
				} else
					$result = array_merge($result, $json_string);
			}
		}

		// либо раскодировать не удалось, либо простые данные...
		if(!is_array($json_string)) {
			$result['data'] = $json_string;
			$result['metadata'] = array(gettype($json_string));
// 			if($lv > 0 and array_key_exists('data_tracking', $tree[$lv-1]))
// 				$result['data_tracking'] = $tree[$lv-1]['data_tracking'];
		}
	}
	
	// возвращаем что получилось
	return $result;
}

function _uni_if($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
if(!empty($GLOBALS['unipath_debug'])) var_dump("if start_data --- ", $tree[$lv-1]['data']);
	if($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], null,
			$tree[$lv-1]['metadata'],
			$args[0]);
		$arg1 = (bool) $arg1['data'];
	} else
		$arg1 = (bool) $args[0];
if(!empty($GLOBALS['unipath_debug'])) var_dump("--- if(".var_export($arg1,true)."):", $args, $args_types, '---');
	// TRUE
	if($arg1 and $args_types[1] == 'unipath')
		$result = __uni_with_start_data(
			$tree[$lv-1]['data'], null,
			$tree[$lv-1]['metadata'],
			$args[1]);
	elseif($arg1 and $args_types[1] != 'unipath')
		$result = array('data' => $args[1], 'metadata' => array(gettype($args[1])));

	// FALSE
	elseif(!$arg1 and isset($args_types[2]) and $args_types[2] == 'unipath')
		$result = __uni_with_start_data(
			$tree[$lv-1]['data'], null,
			$tree[$lv-1]['metadata'],
			$args[2]);
	elseif(!$arg1 and isset($args_types[2]) and $args_types[2] != 'unipath')
		$result = array('data' => $args[2], 'metadata' => array(gettype($args[2])));
	else
		$result = array('data' => null, 'metadata' => array('null'));

	return $result;
}

function _uni_ifEmpty($tree, $lv = 0) {
	$result = array();
	if(empty($tree[$lv-1]['data']) and $tree[$lv-1]['data'] !== '0') {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		if(!isset($args[0])) {
			$result['data'] = null;
			$result['metadata'] = array('null');
		} 
		elseif($args_types[0] == 'unipath') {
			$arg1 = __uni_with_start_data(null, null, null, $args[0]);
			$result = array_merge($result, $arg1);
		} 
		else {
			$result['data'] = $args[0];
			$result['metadata'] = array(gettype($args[0]));
		}
	
	} else {
		$result['data'] = $tree[$lv-1]['data'];
		$result['metadata'] = array($tree[$lv-1]['metadata'][0]);
	};
	
	return $result;
}

function _uni_ifNull($tree, $lv = 0) {
	$result = array();
	if(isset($tree[$lv-1]['data']) == false) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		if(!isset($args[0])) {
			$result['data'] = null;
			$result['metadata'] = array('null');
		} 
		elseif($args_types[0] == 'unipath') {
			$arg1 = __uni_with_start_data(null, null, null, $args[0]);
			$result = array_merge($result, $arg1);
		} 
		else {
			$result['data'] = $args[0];
			$result['metadata'] = array(gettype($args[0]));
		}
	
	} else {
		$result['data'] = $tree[$lv-1]['data'];
		$result['metadata'] = $tree[$lv-1]['metadata'];
	};
	
	return $result;
}

function _uni_unset($tree, $lv = 0) {
	$result = array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(!isset($args[0]))
		$arg1 = '';
	elseif($args_types[0] == 'unipath')
		$arg1 = __uni_with_start_data($result['data'], $result['metadata'][0], $result['metadata'], $args[0]);
	else
		$arg1 = $args[0];
		
	unset($result['data'][$arg1]);
	
	return $result;
}

function _uni_set($tree, $lv = 0) { 
	assert('is_array($tree[$lv-1]["metadata"])');

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
// var_dump($args, $result['data']);
	assert('isset($args[0], $args[1])');

	__uni_with_start_data(
		$tree[$lv-1]['data'], null, 
		$tree[$lv-1]['metadata'], 
		$args[0], 
		__uni_with_start_data($tree[$lv-1]['data'], null, $tree[$lv-1]['metadata'], $args[1]) 
	);
	
	return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
}

function _uni_let($tree, $lv = 0) { 
	assert('is_array($tree[$lv-1]["data"])');
	
	$result = array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
// var_dump($args, $result['data']);
	if(strncmp($args[0], './', 2) == 0)
		$args[0] = substr($args[0], 2);
	
	if(strncmp($args[1], './', 2) == 0)
		$args[1] = substr($args[1], 2);
	
	if(strncmp($args[0], '*/', 2) == 0) {
		$arg1 = substr($args[0], 2);
		$arg2 = substr($args[1], 2);
		
		foreach($result['data'] as $key => $row) {
			$uni_result = __uni_with_start_data($row, null, null, $arg2);
			$result['data'][$key][$arg1] = $uni_result['data'];
		}
	} 
	else {
		$uni_result = __uni_with_start_data($result['data'], null, null, $args[1]);
		$result['data'][$args[0]] = $uni_result['data'];
	};
	
	return $result;
}

function _uni_asClass($tree, $lv = 0) {
	return array(
		'data' => $tree[$lv-1]['data'], 
		'metadata' => array(is_null($tree[$lv-1]['data']) ? 'null' : 'class', 'key()' => $tree[$lv-1]['data'])
	);
}

function _uni_toArray($tree, $lv = 0) {
	if(empty($tree[$lv-1]['data']))
		return array('data' => array(), 'metadata' => array('array'));
		
	$result = array(
		'data' => (array) $tree[$lv-1]['data'],
		'metadata' => $tree[$lv-1]['metadata']);
	
	$result['metadata'][0] = is_array($tree[$lv-1]['data']) 
			|| strncmp($tree[$lv-1]['metadata'][0], 'array', 5) == 0 
			? $tree[$lv-1]['metadata'][0] 
			: 'array';
		
	return $result;
}

function _uni_replace($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	assert('isset($args[0])');
	assert('isset($args[1])');
// var_dump($args, $args_types);

	// если это курсор - перемотаем сначало, а потом выберем все данные
	if(isset($tree[$lv-1]['metadata']['cursor()'])) {
		$call_result = call_user_func($tree[$lv-1]['metadata']['cursor()'], $tree, $lv-1, 'rewind');
		
		// несмог перемотатся курсор!
		if($call_result === false) {
			error_log("UniPath.".__FUNCTION__.": cursor '{$tree[$lv-1]['metadata']['cursor()']}' rewind fail!", E_USER_NOTICE);
			return array('data' => null, 'metadata' => array('null'));
		} 
		
		// курсор при перемотки вернул данные
		if($call_result !== true) {
			$data = $call_result['data'];
		}
		
		// вытаскиваем все данные из курсора тогда
		else {
			global $__uni_prt_cnt;
			for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {
				$call_result = call_user_func($tree[$lv-1]['metadata']['cursor()'], $tree, $lv-1, 'next', $__uni_prt_cnt);
				if(is_array($call_result) and isset($call_result['data'], $call_result['metadata'])) {
					if(!isset($data))
						$data = $call_result['data'];
					else
						$data += $call_result['data'];
				}
			}
		}
	}
	
	// стандартный массив данных
	else 
		$data = $tree[$lv-1]['data'];
	
	// делаем преобразование элементов массива
	$arg1_sscanf = strpos($args[0], '%') !== false;
	$result = array('data' => array(), 'metadata' => 'array');
	$pos = 0;
	foreach($data as $key => $val) { 
		$pos++;
	
		// ключ является маской
		if($arg1_sscanf) {
			$sscanf_result = sscanf($key, $args[0]);
			if(is_null($sscanf_result[0])) continue;
		} 
		
		// неподходит по ключу
		elseif($key != $args[0])
			continue;

		$uni_result = __uni_with_start_data($val, gettype($val), array(gettype($val), 'key()' => $key, 'pos()' => $pos), $args[1]);
		$result['data'][$key] = $uni_result['data'];
// var_dump($uni_result, 'key='.$key.', pos='.$pos);
	}
	
	return $result;
}

function _uni_array($tree, $lv = 0) {
	
	$result = array('data' => array(), 'metadata' => array('array'));
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	foreach($args as $key => $val) {
	
		// возможно элементы массива без имён, т.е. по порядку
		if(strncmp($key, 'arg', 3) == 0 and is_numeric($key[3])) 
			$i = intval(substr($key, 3))-1;
		else
			$i = $key;
				
		if($args_types[$key] == 'unipath') {
			$uni_result = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				$tree[$lv-1]['metadata'][0], 
				$tree[$lv-1]['metadata'], 
				$val);
			$result['data'][$i] = $uni_result['data'];
		}
		else
			$result['data'][$i] = $val;
	}
	
	return $result;
}

function _uni_toHash($tree, $lv = 0) {
	
	// вытащим из аргументов название ключевого поля
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(empty($args[0])) {
		trigger_error('UniPath.toHash: need arg1 as field name wich will be key field!');
		return array('data' => null, 'metadata' => array('null'));
	} 
	elseif($args_types[0] == 'unipath') {
			$uni_result = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				$tree[$lv-1]['metadata'][0], 
				$tree[$lv-1]['metadata'], 
				$args[0]);
			$pkey = $uni_result['data'];
	}
	else
		$pkey = $args[0];
// var_dump('toHash(): key = '.$pkey);
	
	// --- Вариант с cursor()
	if(isset($tree[$lv-1]['metadata']['cursor()'])) {
		assert('function_exists($tree[$lv-1]["metadata"]["cursor()"]) or is_callable($tree[$lv-1]["metadata"]["cursor()"]);') or var_dump($tree[$lv-1]);
	
		$metadata = & $tree[$lv-1]["metadata"];
		$next_limit = 10;
	
		// подготовим результат
		$result = array(
			'data' => array(), 
			'metadata' => array(/*strncmp($tree[$lv-1]['data_type'], 'array', 5) == 0 ? $tree[$lv-1]['data_type'] :*/ 'array'));
	
		// REWIND - перематаем в начало
		$call_result = call_user_func_array($metadata['cursor()'], array(&$tree, $lv-1, 'rewind'));
		
		global $__uni_prt_cnt;
		for($prt_cnt = 0; $prt_cnt < $__uni_prt_cnt; $prt_cnt++) {

			// постепенно наращиваем лимит запрашиваемых порций
			$next_limit = $next_limit < 20 ? $next_limit+1 : ($next_limit < 150 ? $next_limit+10 : 1000);
			
			$call_result = call_user_func_array($metadata['cursor()'], array(&$tree, $lv-1, 'next', $next_limit));
// var_dump($call_result); exit;
			
			// если ответ это набор записей next(100)
			if(is_array($call_result) and !empty($call_result)) {
				$data = $call_result['data'];
			} 
		
			// пустой или отрицательный ответ, возвращаем как есть.
			else
				return $result;
		
			// построим hash
			foreach($data as $curr_key => $item) {
				if(array_key_exists($pkey, $item)) {
					$new_key = $item[$pkey];
					
					if(isset($args[1]) and $args_types[1] == 'unipath') {
						$uni_result = __uni_with_start_data(
							$item, 
							$call_result['metadata'][0], 
							$call_result['metadata'], 
							$args[1]);
						$result['data'][$new_key] = $uni_result['data'];
					} else
						$result['data'][$new_key] = $item;

				} 
				else
					$result['data'][$curr_key] = $item;
			}
// print_r($result); exit;
		} 

		trigger_error('UniPath.'.__FUNCTION__.': protection counter $__uni_prt_cnt exhausted!');
		return $result;
	}

	// --- Класический вариант
	if(is_array($tree[$lv-1]['data'])) {
	
		// подготовим результат
		$result = array(
			'data' => array(), 
			'metadata' => $tree[$lv-1]['metadata']
		);
		$result['metadata'][0] = strncmp($tree[$lv-1]['metadata'][0], 'array', 5) == 0 ? $tree[$lv-1]['metadata'][0] : 'array';

		// построим hash
		foreach($tree[$lv-1]['data'] as $key => $val) {
			if(is_array($val) and array_key_exists($pkey, $val)) {
				$new_key = $val[$pkey];
				
				if(isset($args[1]) and $args_types[1] == 'unipath') {
					$uni_result = __uni_with_start_data($val, gettype($val), array(gettype($val), 'key()' => $key), $args[1]);
					$result['data'][$new_key] = $uni_result['data'];
				} else
					$result['data'][$new_key] = $val;

			}
			elseif (is_object($val) and $val instanceof DOMElement) {
				$new_key = __uni_with_start_data($val, null, array(gettype($val), 'key()' => $key), $args[0]);
				
				if(!is_array($new_key) or !isset($new_key['data'])) {
					trigger_error('UniPath.'.__FUNCTION__.': Invalid key - '.(is_array($new_key)?$new_key['data']:$new_key).'! (skip)');
					continue;
				}

				if(isset($args[1]) and $args_types[1] == 'unipath') {
					$uni_result = __uni_with_start_data($val, gettype($val), array(gettype($val), 'key()' => $key), $args[1]);
					$result['data'][$new_key['data']] = $uni_result['data'];
				} else
					$result['data'][$new_key['data']] = $val;

			}
		} 

		return $result;
	}
	
	// если не массив, но есть ключ, то просто оборачиваем в массив
	if(isset($tree[$lv-1]['metadata']['key()']))
		return array(
			'data' => array($tree[$lv-1]['metadata']['key()'] => $tree[$lv-1]['data']), 
			'metadata' => array('array'));
	
	// иначе просто оборачиваем в массив
	return array('data' => (array) $tree[$lv-1]['data'], 'metadata' => array('array'));
}

function _uni_count($tree, $lv = 0) {
	return array('data' => count($tree[$lv-1]['data']), 'metadata' => array('integer'));
}
		
function _uni_sum($tree, $lv = 0) {
	return array('data' => array_sum((array) $tree[$lv-1]['data']), 'metadata' => array(gettype($tree[$lv-1]['data'])));
}

function _uni_asFile($tree, $lv = 0) {
	assert('isset($tree[$lv-1], $tree[$lv-1]["data"])');

	$path = realpath($tree[$lv-1]['data']);
// 	if(file_exists($path)) {
		$result = array(
			'data' => $path, 
			'metadata' => array(
				'string/local-pathname',
				'url' => 'file://'.$path, 
				'key()' => $tree[$lv-1]['data'], 
				'cursor()' => '_cursor_asFile'));
// 	}
	return $result;
}

function _cursor_asFile($tree, $lv = 0, $cursor_cmd = '', $cursor_arg1 = null) {
	if($cursor_cmd == 'all') {
		return $tree[$lv];
	}
// var_dump($cursor_cmd, $cursor_arg1);

	if($cursor_cmd == 'set') {
		$track = $tree[$lv]['metadata'];
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
		if($tree[$lv]['metadata'][0] == "string/local-pathname")
			return false;
	}
}

function _uni_asDir($tree, $lv = 0) { return _uni_asDirectory($tree, $lv); }
function _uni_asDirectory($tree, $lv = 0) {
	$path = realpath($tree[$lv-1]['data']);
	$result = array(
		'data' => $path,
		'metadata' => array('string/local-directory', 'url' => 'file://'.$path, 'key()' => $tree[$lv-1]['data'])
	);
	return $result;
}

function _uni_asZIP($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) { return _uni_asZIPFile($tree, $lv); }
function _uni_asZIPFile($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {

	// zip/NNN запись в Zip-архиве
	if($cursor_cmd == 'eval' and $tree[$lv]['metadata'][0] == 'object/zip') {
		if(is_numeric($cursor_arg1['name'])) {
			$entry = $tree[$lv]['data']->statIndex($cursor_arg1['name']);
			$result = array(
				'data' => $tree[$lv]['data']->getStream($entry['name']),
				'metadata' => array(
					'null/zip-file',
					'key()' => $cursor_arg1['name'], 
					'cursor()' => __FUNCTION__,
					'zip' => &$tree[$lv]['data'],
					'zip_entry' => $entry
					)
				);
			if($result['data'])
				$result['metadata'][0] = gettype($result['data']).'/zip-file';

			return $result;
		} 
		elseif(is_string($cursor_arg1['name'])) {
			$entry = $tree[$lv]['data']->statName($cursor_arg1['name']);
			$result = array(
				'data' => $tree[$lv]['data']->getStream($entry['name']),
				'metadata' => array(
					'null/zip-file',
					'key()' => $cursor_arg1['name'], 
					'cursor()' => __FUNCTION__,
					'zip' => &$tree[$lv]['data'],
					'zip_entry' => $entry
					)
				);
			if($result['data'])
				$result['metadata'][0] = gettype($result['data']).'/zip-file';

			return $result;
		}
		else {
			trigger_error('UniPath.'.__FUNCTION__.': can`t get file by '.$tree[$lv]['name']);
		}
		
		return array('data' => null, 'metadata' => array('null/zip-file'));
	}

	if($cursor_cmd == 'eval' and in_array($tree[$lv]['metadata'][0], array('object/zip-file', 'resource/zip-file'))) {
	
		// zip/NNN/contents()
		if(strpos($cursor_arg1['name'], 'contents(') === 0) {
			$data = stream_get_contents($tree[$lv]['data']);
			return array('data' => $data, 'metadata' => array(gettype($data).'/zip-file-contents'));
		} 
		
		// zip/NNN/saveAs()
		elseif(strpos($cursor_arg1['name'], 'saveAs(') === 0) {
			list($args, $args_types) = __uni_parseFuncArgs($cursor_arg1['name']);
			if(empty($args[0]))
				trigger_error('UniPath.'.__FUNCTION__.': arg1 must be filename!');
			elseif($args_types[0] == 'unipath')
				$arg1 = strval(uni($args[0]));
			else
				$arg1 = $args[0];
				
			$dst = fopen($arg1, 'wb');
			stream_copy_to_stream($tree[$lv]['data'], $dst);
			fclose($dst);
			
			return array(
				'data' => &$tree[$lv]['data'], 
				'metadata' => &$tree[$lv]['metadata']);
		}
		
		return false;
	}
	
	// пусть обрабатывается стандартным обработчиком UniPath
	if($cursor_cmd == 'eval' and strpos($cursor_arg1['name'], '(') !== false) {
		trigger_error('UniPath.'.__FUNCTION__.': reject this -> '.$cursor_arg1['name']);
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
			'metadata' => array(
				'object/zip',
				'key()' => $tree[$lv-1]['data'],
				'cursor()' => __FUNCTION__));
	else
		return array(
			'data' => null, 
			'metadata' => array(
				'null/zip',
				'key()' => $tree[$lv-1]['data']/*,
				'cursor()' => '_uni_asZIPFile'*/));
}

function _uni_uri($tree, $lv = 0) { return _uni_url($tree, $lv); }
function _uni_url($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	if($args_types[0] == 'unipath') {
// $GLOBALS['unipath_debug'] = true;
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], $tree[$lv-1]['metadata'], 
			$tree[$lv-1]['metadata'], 
			$args[0]);
// $GLOBALS['unipath_debug'] = false;
// print_r($arg1);
		return $arg1;
	} 
	
	if(strpos($args[0], 'file://') === 0) {
		if(strpos($args[0], '/./') !== false)
			$args[0] = str_replace('/./', '/'.realpath('.').'/', $args[0]);
// var_dump($args[0]);
		$result = array(
			'data' => $args[0], 
			'metadata' => array(
				'string/local-pathname', 
				'key()' => $args[0],
				'url' => $args[0],
				'cursor()' => '_cursor_asFile'));
	}
// var_dump($result);
	return isset($result) ? $result : array('data' => $args[0], 'metadata' => array('string/url'));
}

function _uni_open($tree, $lv = 0) {
	if(in_array($tree[$lv-1]['metadata'][0], array('string/local-pathname', 'string/local-entry'))) {
		$result = array(
			'data' => fopen($tree[$lv-1]['data'], file_exists($tree[$lv-1]['data']) ? 'rb+' : 'cb+')
		);
		
	} else
		$result = array('data' => null);
		
	return $result + array('metadata' => array(gettype($result['data'])));
}

function _uni_content($tree, $lv = 0) { return _uni_contents($tree, $lv); }
function _uni_contents($tree, $lv = 0) {
// var_dump($tree[$lv-1]);
	// содержимое интернет ресурса
	if($tree[$lv-1]['metadata'][0] == 'string/url') {

		$url_host = parse_url($tree[$lv-1]['data'], PHP_URL_HOST);
		$url_host_port = parse_url($tree[$lv-1]['data'], PHP_URL_PORT) or $url_host_port = 80;
		$url_path = parse_url($tree[$lv-1]['data'], PHP_URL_PATH);
		$url_query = parse_url($tree[$lv-1]['data'], PHP_URL_QUERY);
		empty($url_query) or $url_query = '?'.$url_query;
		
if(!empty($GLOBALS['unipath_debug'])) var_dump($url_host, $url_path.$url_query, $url_host_port);
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($socket === false) {
			trigger_error("UniPath".__FUNCTION__.": socket_create() failed: reason: " . socket_strerror(socket_last_error()), E_USER_ERROR);
		} 
		else {
		
			$conn = socket_connect($socket, gethostbyname($url_host), $url_host_port);
			
			if($conn === false) {
				trigger_error("UniPath.".__FUNCTION__.": socket_connect() failed.\nReason: ($conn) " . socket_strerror(socket_last_error($socket)));
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
					$result = array('data' => implode('', $resp), 'metadata' => array('string/binary'));
			
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
	elseif($tree[$lv-1]['metadata'][0] == 'string/local-pathname') {
		$result = array(
			'data' => file_get_contents($tree[$lv-1]['data']), 
			'metadata' => array(
				'string/binary', 
				'url' => $tree[$lv-1]['data'], 
				'key()' => $tree[$lv-1]['data'])
			);
// var_dump('*** readed '.$tree[$lv-1]['data']);
	}
	
	// неудача
	if(!isset($result))
		return array(
			'data' => null, 
			'metadata' => array('null/binary', 'url' => $tree[$lv-1]['data'], 'key()' => $tree[$lv-1]['data']));
	
	return $result;
}

function _uni_key($tree, $lv = 0) {

	$result = array('data' => null);
	if(isset($tree[$lv-1]['metadata']['key()']))
		$result['data'] = $tree[$lv-1]['metadata']['key()'];
	else
		$result['data'] = $tree[$lv-1]['name'];
	
	// key('name%i') -- если указана маска, то название ключа должно совпадать с ней!
	if(strlen($tree[$lv]['name']) > 5) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		$found = sscanf($result['data'], $args[0]);
		if(is_null($found) or is_null($found[0]))
			$result['data'] = null;
	}
	
	$result['metadata'] = array(gettype($result['data']));
		
	return $result;
}
		
function _uni_pos($tree, $lv = 0) {

	if(isset($tree[$lv-1]['metadata']['pos()']))
		$result = array(
			'data' => intval($tree[$lv-1]['metadata']['pos()']), 
			'metadata' => array('number'));
	else
		$result = array('data' => null, 'metadata' => array('null'));
	
	
	return $result;
}

function _uni_first($tree, $lv = 0) {
// 	assert('is_array($tree[$lv-1]["data"])') or var_dump($tree[$lv-1]);

	// если это cursor() то запросим один элемент и вернём что получиться (либо элемент либо false)
	if(isset($tree[$lv-1]['metadata']['cursor()'])) {
		$metadata = & $tree[$lv-1]['metadata'];
	
		// 1) перед запросом первого элемента, перемотаем в начало - REWIND
		$call_result = call_user_func_array($metadata['cursor()'], array(&$tree, $lv-1, 'rewind'));
	
		// 2) запросим первый элемент - NEXT
		$call_result = call_user_func_array($metadata['cursor()'], array(&$tree, $lv-1, 'next'));
// var_dump($call_result);
		
		// 3) вернём первый элемент или false/empty_array()
		return array(
			'data' => isset($call_result['data']) ? $call_result['data'] : null,
			'metadata' => isset($call_result['metadata']) ? $call_result['metadata'] : array('null'),
			);
	}
	
	$result = array();
	if(is_array($tree[$lv-1]['data']) and !empty($tree[$lv-1]['data'])) {
		$key = current(array_keys($tree[$lv-1]['data']));
		$result['data'] = $tree[$lv-1]['data'][$key];
		$result['metadata'] = array(gettype($result['data']), 'key()' => $key, 'pos()' => $key);
	} 
	elseif(!is_array($tree[$lv-1]['data'])) {
		$result['data'] = null;
		$result['metadata'] = array('null');
	} 
	else {
		$result['data'] =& $tree[$lv-1]['data'];
		$result['metadata'] =& $tree[$lv-1]['metadata'];
	};
	
	return $result;
}

function _uni_regexp($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	if(empty($args[0]))
		return array('data' => false);
		
	elseif($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], 
			$tree[$lv-1]['metadata'][0], 
			$tree[$lv-1]['metadata'], 
			$args[0]);
		$arg1 = strval($arg1['data']);
	} else
		$arg1 = $args[0];

	if(preg_match("~$arg1~u", $tree[$lv-1]['data'], $matches)) 
		return array('data' => $matches, 'metadata' => array('array'));
	else
		return array('data' => null, 'metadata' => array('null'));
}

function _uni_regexp_all($tree, $lv = 0) {

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(empty($args[0]))
		return array('data' => false);
		
	elseif($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], 
			$tree[$lv-1]['metadata'][0], 
			$tree[$lv-1]['metadata'], 
			$args[0]);
		$arg1 = strval($arg1['data']);
	} else
		$arg1 = $args[0];

	if(preg_match_all("~$arg1~ui", $tree[$lv-1]['data'], $matches, PREG_SET_ORDER)) {
// var_dump($matches);
		return array('data' => $matches, 'metadata' => array('array'));
	} else
		return array('data' => null, 'metadata' => array('null'));
}

function _uni_regexp_match($tree, $lv = 0) {
// print_r($tree[$lv]);
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(empty($args[0]))
		return array('data' => false);
	elseif($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], 
			$tree[$lv-1]['metadata'][0], 
			$tree[$lv-1]['metadata'], 
			$args[0]);
		$arg1 = strval($arg1['data']);
	} else
		$arg1 = $args[0];
		
// var_dump("~$arg1~ui", $tree[$lv-1]['data'], preg_match("~$arg1~ui", $tree[$lv-1]['data']));
	return array('data' => preg_match("~$arg1~ui", $tree[$lv-1]['data']) ? 1 : 0, 'metadata' => array('integer'));
}

function _uni_regexp_replace($tree, $lv = 0) {

	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'metadata' => array('null'));
		
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
// var_dump($args);
	if(empty($args[0]))
		return array(
			'data' => $tree[$lv-1]['data'], 
			'metadata' => array($tree[$lv-1]['metadata']));
			
	elseif($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'], 
			$tree[$lv-1]['metadata'][0], 
			$tree[$lv-1]['metadata'], 
			$args[0]);
		$arg1 = strval($arg1['data']);
	} else
		$arg1 = $args[0];

	// есть ньюанс с регулярками и надо его обойти для удобства
	// (описан - http://stackoverflow.com/questions/13705170/preg-replace-double-replacement)
	if($arg1 == '.*') $arg1 = '(.+|^$)';
		
	return array(
		'data' => preg_replace("/{$arg1}/u", $args[1], $tree[$lv-1]['data']), 
		'metadata' => array('string'));
}

function _uni_replace_string($tree, $lv = 0) {

	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'metadata' => array('null'));
		
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	// старый простой вариант
	if(isset($args[0], $args[1]))
		return array(
			'data' => str_replace($args[0], $args[1], $tree[$lv-1]['data']), 
			'metadata' => array('string'));
		
	$result_string = $tree[$lv-1]['data'];
	foreach($args as $old => $new) {
	
		if($args_types[$old] == 'unipath') {
			$uni_result = __uni_with_start_data(
				$tree[$lv-1]['data'], 
				$tree[$lv-1]['metadata'][0], 
				$tree[$lv-1]['metadata'], 
				$new);
			$new = $uni_result['data'];
		}
		
		$result_string = str_replace($old, $new, $result_string);
	}
	
	return array('data' => $result_string, 'metadata' => array('string'));
}

function _uni_remove_start($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"])');
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if($args_types[0] == 'unipath')
		$arg1 = uni($args[0]);
	else
		$arg1 = $args[0];

	if(strncmp($tree[$lv-1]['data'], $arg1, strlen($arg1)) == 0) {
		$result = array('data' => substr((string) $tree[$lv-1]['data'], strlen($arg1)));
		$result['data'] === false and $result['data'] = null;
	} else
		$result = array('data' => $tree[$lv-1]['data']);

	$result['metadata'] = $tree[$lv-1]['metadata'];
	
	return $result;
}

function _uni_remove_end($tree, $lv = 0) {
	assert('is_string($tree[$lv-1]["data"])');
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(!isset($args[0]))
		$arg1 = ';';
	elseif($args_types[0] == 'unipath')
		$arg1 = uni($args[0]);
	else
		$arg1 = $args[0];
				
	if(mb_strpos($tree[$lv-1]['data'], $arg1, mb_strlen($tree[$lv-1]['data'])-mb_strlen($arg1)) !== false)
		$result = array('data' => mb_substr($tree[$lv-1]['data'], 0, -mb_strlen($arg1)));
	else
		$result = array('data' => $tree[$lv-1]['data']);
		
	$result['metadata'] = $tree[$lv-1]['metadata'];

	return $result;
}
			
function _uni_remove_empty($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"])');
	
// 	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	$result = array('data' => array(), 'metadata' => array($tree[$lv-1]["metadata"][0]));
	
	foreach($tree[$lv-1]['data'] as $key => $item) 
		if(empty($item) == false)
			if(is_string($key)) {
				$result['data'][$key] = $item;
			} else {
				$result['data'][] = $item;
			}

	return $result;
}
			
function _uni_trim($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"])');
	
	$result = array('data' => array(), 'metadata' => array($tree[$lv-1]['metadata'][0]));
	
	foreach($tree[$lv-1]['data'] as $key => $item) 
		if(is_string($key)) {
			$result['data'][$key] = trim($item);
		} else {
			$result['data'][] = trim($item);
		}
		
	return $result;
}

function _uni_append($tree, $lv = 0){ return _uni_add($tree, $lv); }
function _uni_add($tree, $lv = 0) {
// 	var_dump($tree[$lv-1]['metadata']);
	if(in_array($tree[$lv-1]['metadata'][0], array("array/db-table", "cursor/db-cursor", "array/sql-query-with-params")))
		return _uni_new_row($tree, $lv);

	assert('is_string($tree[$lv-1]["data"]) or is_numeric($tree[$lv-1]["data"]) or is_array($tree[$lv-1]["data"]);');

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	foreach($args_types as $key => $arg_type)
		if($arg_type == 'unipath') {
			$args[$key] = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[$key]);
			$args[$key] = $args[$key]['data'];
		}
		
	if(is_array($tree[$lv-1]["data"])) {
		$result = array('data' => array(), 'metadata' => array('array'));
		foreach($tree[$lv-1]["data"] as $key => $val)
			$result['data'][$key] = $val.$args[0];
	} 
	else {
		$result = array(
			'data' => $tree[$lv-1]['data'].$args[0], 
			'metadata' => $tree[$lv-1]['metadata']);
	}
		
	return $result;
}

function _uni_prepand($tree, $lv = 0) { return _uni_prepend($tree, $lv); }
function _uni_prepend($tree, $lv = 0) { 
// 	assert('is_string($tree[$lv-1]["data"]) or is_numeric($tree[$lv-1]["data"]) or is_array($tree[$lv-1]["data"]); /* '.print_r($tree[$lv-1], true).' */');

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	foreach($args_types as $key => $arg_type)
		if($arg_type == 'unipath') {
			$args[$key] = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[$key]);
			$args[$key] = $args[$key]['data'];
		}
		
				
	if(is_array($tree[$lv-1]["data"])) {
		$result = array('data' => array(), 'metadata' => array('array'));
		foreach($tree[$lv-1]["data"] as $key => $val)
			$result['data'][$key] = $args[0].$val;
	} 
	else {
		$result = array(
			'data' => $args[0].$tree[$lv-1]['data'], 
			'metadata' => $tree[$lv-1]['metadata']);
	}
	
	return $result;
}

function _uni_split($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(!isset($args[0]))
		$arg1 = ';';
	elseif($args_types[0] == 'unipath')
		$arg1 =__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]);
	else
		$arg1 = $args[0];
			
	$result = array('metadata' => array('array'));
	if(!is_array($tree[$lv-1]['data']))
		$result['data'] = explode($arg1, strval($tree[$lv-1]['data']));
	else
		$result['data'] = array_map(create_function('$a','return explode(\''.$arg1.'\', $a);'), $tree[$lv-1]['data']);

	return $result;
}

function _uni_join($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(!isset($args[0]))
		$arg1 = '';
	elseif($args_types[0] == 'unipath')
		$arg1 =__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]);
	else
		$arg1 = $args[0];
				
	$result = array('data' => implode($arg1, (array) $tree[$lv-1]['data']), 'metadata' => array('string'));
	
	return $result;
}

function _uni_wrap($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	if(!isset($args[0]))
		$arg1 = ';';
	elseif($args_types[0] == 'unipath') {
		$arg1 = __uni_with_start_data(
			$tree[$lv-1]['data'],
			$tree[$lv-1]['metadata'][0],
			$tree[$lv-1]['metadata'],
			$args[0]);
		$arg1 = $arg1['data'];
	}
	else
		$arg1 = $args[0];
		
	if(!isset($args[1]))
		$arg2 = $arg1;
	elseif($args_types[1] == 'unipath') {
		$arg2 = __uni_with_start_data(
			$tree[$lv-1]['data'],
			$tree[$lv-1]['metadata'][0],
			$tree[$lv-1]['metadata'],
			$args[1]);
		$arg2 = $arg2['data'];
	}
	else
		$arg2 = $args[1];
	
	$result = array('metadata' => $tree[$lv-1]['metadata']);
	
	if(is_array($tree[$lv-1]['data'])) {
		$result['data'] = array();
		foreach($tree[$lv-1]['data'] as $key => $val)
			if(!empty($tree[$lv-1]['data'][$key]))
				$result['data'][$key] = $arg1.strval($tree[$lv-1]['data']).$arg2;
			else
				$result['data'][$key] = count($args) == 1 ? $arg1 : '';
	} 
	
	// для всех остальных типов относимся как к строкам
	else {
		if(!empty($tree[$lv-1]['data']))
			$result['data'] = $arg1.strval($tree[$lv-1]['data']).$arg2;
		else
			$result['data'] = count($args) == 1 ? $arg1 : '';
	} 
	
	return $result;
}

function _uni_asJSON($tree, $lv = 0) {
	$data = json_decode($tree[$lv-1]['data'], true);
	return array('data' => $data, 'metadata' => array(gettype($data)));
}

function _uni_translit($tree, $lv = 0) {
	// карта обратимого транслита (взята из http://habrahabr.ru/post/265455/)
	$translit_ru_en = array('А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'JO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'KH', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => 'JHH', 'Ы' => 'IH', 'Ь' => 'JH', 'Э' => 'EH', 'Ю' => 'JU', 'Я' => 'JA', 
	'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => 'jhh', 'ы' => 'ih', 'ь' => 'jh', 'э' => 'eh', 'ю' => 'ju', 'я' => 'ja', 
	' ' => '_', '-' => '-', ',' => ',', '.' => '.', '(' => '(', ')' => ')', ';' => ';');

	$str = $tree[$lv-1]['data'];
	$str_len = mb_strlen($str, 'UTF-8');
	$result = array('data' => '', 'metadata' => array('string'));
	for($i = 0; $i < $str_len; $i++) { 
		$char = mb_substr($str, $i, 1, 'UTF-8');
		
		if(isset($translit_ru_en[$char])) 
			$result['data'] .= $translit_ru_en[$char];  
		else 
		if(strpos('0123456789qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM', $char) !== false) 
			$result['data'] .= $char;
	} 
    
    return $result;
}

function _uni_untranslit($tree, $lv = 0) {
	// карта обратимого транслита (взята из http://habrahabr.ru/post/265455/)
	$translit_en_ru = array_flip(array('А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'JO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'KH', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => 'JHH', 'Ы' => 'IH', 'Ь' => 'JH', 'Э' => 'EH', 'Ю' => 'JU', 'Я' => 'JA', 
	'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => 'jhh', 'ы' => 'ih', 'ь' => 'jh', 'э' => 'eh', 'ю' => 'ju', 'я' => 'ja', 
	' ' => '_', '-' => '-', ',' => ',', '.' => '.', '(' => '(', ')' => ')', ';' => ';'));
	
	$str = $tree[$lv-1]['data'];
	$str_len = mb_strlen($str, 'UTF-8');
	$result = array('data' => '', 'metadata' => array('string'));
	for($i = 0; $i < $str_len; $i++) { 
		$char = mb_substr($str, $i, 1, 'UTF-8');
		switch($char) {
			/*case '-':
				if(mb_substr($str, $i, 2, 'UTF-8') == '--') {
					$result['data'] .= '-';
					$i++;
				} else
					$result['data'] .= ' ';
				break; */
			case 'j': case 'J':
				switch(mb_substr($str, $i, 3, 'UTF-8')) {
					case 'jhh':
						$result['data'] .= 'ъ'; $i+=2; break;
					case 'JHH':
						$result['data'] .= 'Ъ'; $i+=2; break;
					default:
						switch(mb_substr($str, $i, 2, 'UTF-8')) {
							case 'jh':
								$result['data'] .= 'ь'; $i++; break;
							case 'JH':
								$result['data'] .= 'Ь'; $i++; break;
							case 'ja':
								$result['data'] .= 'я'; $i++; break;
							case 'JA':
								$result['data'] .= 'Я'; $i++; break;
							case 'ju':
								$result['data'] .= 'ю'; $i++; break;
							case 'JU':
								$result['data'] .= 'Ю'; $i++; break;
							case 'jo':
								$result['data'] .= 'ё'; $i++; break;
							case 'JO':
								$result['data'] .= 'Ё'; $i++; break;
							default: 
								$result['data'] .= $char; 
						}
				}
				break;
			case 's': case 'S':
				switch(mb_substr($str, $i, 3, 'UTF-8')) {
					case 'shh':
						$result['data'] .= 'щ'; $i+=2; break;
					case 'SHH':
						$result['data'] .= 'Щ'; $i+=2; break;
					default:
						switch(mb_substr($str, $i, 2, 'UTF-8')) {
							case 'sh':
								$result['data'] .= 'ш'; $i++; break;
							case 'SH':
								$result['data'] .= 'Ш'; $i++; break;
							default:
								$result['data'] .= $char == 's' ? 'с' : 'С';
						}
				}
				break;
			case 'z': case 'Z':
				switch(mb_substr($str, $i, 2, 'UTF-8')) {
					case 'zh':
						$result['data'] .= 'ж'; $i++; break;
					case 'ZH':
						$result['data'] .= 'Ж'; $i++; break;
					default:
						$result['data'] .= $char == 'z' ? 'з' : 'З';
				}
				break;
			case 'k': case 'K':
				switch(mb_substr($str, $i, 2, 'UTF-8')) {
					case 'kh':
						$result['data'] .= 'х'; $i++; break;
					case 'KH':
						$result['data'] .= 'Х'; $i++; break;
					default:
						$result['data'] .= $char == 'k' ? 'к' : 'К';
				}
				break;
			case 'c': case 'C':
				switch(mb_substr($str, $i, 2, 'UTF-8')) {
					case 'ch':
						$result['data'] .= 'ч'; $i++; break;
					case 'CH':
						$result['data'] .= 'Ч'; $i++; break;
					default:
						$result['data'] .= $char == 'c' ? 'ц' : 'Ц';
				}
				break;
			case 'e': case 'e':
				switch(mb_substr($str, $i, 2, 'UTF-8')) {
					case 'eh':
						$result['data'] .= 'э'; $i++; break;
					case 'EH':
						$result['data'] .= 'Э'; $i++; break;
					default:
						$result['data'] .= $char == 'e' ? 'е' : 'Е';
				}
				break;
			case 'i': case 'I':
				switch(mb_substr($str, $i, 2, 'UTF-8')) {
					case 'ih':
						$result['data'] .= 'ы'; $i++; break;
					case 'IH':
						$result['data'] .= 'Ы'; $i++; break;
					default:
						$result['data'] .= $char == 'i' ? 'и' : 'И';
				}
				break;
			default:
				if(isset($translit_en_ru[$char]))
					$result['data'] .= $translit_en_ru[$char];
				else
					$result['data'] .= $char;
		}
	}

	return $result;
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
    
    return array('data' => $newname, 'metadata' => array('string'));
}

function _uni_decode_url($tree, $lv = 0) {
	if(is_null($tree[$lv-1]['data'])) {
		 return array('data' => null, 'metadata' => 'null');
	} elseif(is_array($tree[$lv-1]['data'])) {
		$result = array();
		foreach($tree[$lv-1]['data'] as $key => $str) 
			$result[$key] = urldecode($str);
		return array('data' => $result, 'metadata' => $tree[$lv-1]['metadata']);
	} else
		return array('data' => urldecode($tree[$lv-1]['data']), 'metadata' => $tree[$lv-1]['metadata']);
}

function _uni_formatPrice($tree, $lv = 0) {
	assert('!is_array($tree[$lv-1]["data"])');

	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

		if(!isset($args[0]))
			$arg1 = '';
		elseif($args_types[0] == 'unipath') {
			$arg1 = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]);
			if(is_array($arg1) and isset($arg1['data']))
				$arg1 = $arg1['data'];
			assert('is_array($arg1) == false; /* '.var_export($arg1, true).' */');
			$arg1 = (string) $arg1;
		}
		else
			$arg1 = $args[0];
		
		$price = floatval($tree[$lv-1]['data']);

		// если указали округлять до 50руб
		if(isset($args[1]) and $args[1] == '999>50') {
			if($price > 999.999)
				$price = ceil($price / 50) * 50;
		}

		// отформатируем число
		$price_formated = number_format($price, 0, ',', ' ') . $arg1;

		return array('data' => $price_formated, 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_normalize_float($tree, $lv = 0) {
	assert('is_array($tree[$lv-1]["data"]) == false');
	return array(
		'data' => floatval(strtr($tree[$lv-1]['data'], array(' '=>'',','=>'.', 'ложь'=>'0', 'истина'=>'1'))), 
		'metadata' => $tree[$lv-1]['data_tracking']);
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
		'metadata' => $tree[$lv-1]['metadata']);
}

function _uni_substr($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		return array('data' => substr($tree[$lv-1]['data'], $args[0]), 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_array_flat($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		return array('data' => call_user_func_array('array_merge', $tree[$lv-1]['data']), 'metadata' => array('array'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_replace_in($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		return array('data' => strtr($args[0], $tree[$lv-1]['data']), 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_iconv($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
		
		$result = array('data' => array(), 'metadata' => $tree[$lv-1]['metadata']);
		foreach($tree[$lv-1]['data'] as $key => $val) 
			if(is_array($val)) {
				$result['data'][$key] = array();
				foreach($val as $key2 => $val2)
					$result['data'][$key][$key2] = mb_convert_encoding($val2, $args[1], $args[0]);
			} else {
				$result['data'][$key] = mb_convert_encoding($val, $args[1], $args[0]);
			}
		
		/*if(array_key_exists('data_tracking', $tree[$lv-1]))
				$result['data_tracking'] = $tree[$lv-1]['data_tracking'];*/

		return $result;
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_formatQuantity($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

		$founded = $tree[$lv-1]['data'];
		$suffix = isset($args[2]) ? $args[2] : 'штук';
		if($founded % 10 == 1 and $founded != 11)
			$suffix = isset($args[0]) ? $args[0] : 'штука';
		elseif(in_array($founded % 10, array(2,3,4)) && floor($founded / 10) != 1/* or $founded == 0*/)
			$suffix = isset($args[1]) ? $args[1] : 'штуки';
		
		return array('data' => $founded." ".$suffix, 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_formatDate($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

		if(is_string($tree[$lv-1]['data']) and strpos($tree[$lv-1]['data'], '-') != false) {
			$str = date(isset($args[0]) ? $args[0] : 'j-m-Y, H:i', strtotime($tree[$lv-1]['data']));
		} else
			$str = date(isset($args[0]) ? $args[0] : 'j-m-Y, H:i');
			
        $str = strtr($str, array(
		'-01-' => ' января ', ' Jan ' => ' янв ', ' January ' => ' января ',
		'-02-' => ' февраля ', ' Feb ' => ' фев ', ' February ' => ' февраля ',
		'-03-' => ' марта ', ' Mar ' => ' мар ', ' March ' => ' марта ',
		'-04-' => ' апреля ', ' Apr ' => ' апр ', ' April ' => ' апреля ',
		'-05-' => ' мая ', ' May ' => ' мая ',
        '-06-' => ' июня ', ' Jun ' => ' июн ', ' June ' => ' июня ',
        '-07-' => ' июля ', ' Jul ' => ' июля ', ' July ' => ' июля ',
        '-08-' => ' августа ', ' Aug ' => ' авг ', ' August ' => ' августа ',
        '-09-' => ' сентября ', ' Sep ' => ' сен ', ' September ' => ' сентября ',
        '-10-' => ' октября ', ' Oct ' => ' окт ', ' October ' => ' октября ',
        '-11-' => ' ноября ', ' Nov ' => ' ноя ', ' November ' => ' ноября ',
        '-12-' => ' декабря ', ' Dec ' => ' дек ', ' November ' =>' декабря '
        ));
		
		return array('data' => $str, 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_html_safe($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array('data' => htmlspecialchars($tree[$lv-1]['data']), 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_html_attr_safe($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array('data' => htmlspecialchars($tree[$lv-1]['data'], ENT_QUOTES), 'metadata' => array('string'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_sprintf(&$tree, $lv = 0) {
// 	assert('is_array($tree[$lv-1]["data"]) or isset($tree[$lv-1]["metadata"], $tree[$lv-1]["metadata"]["cursor()"]);');
if(!empty($GLOBALS['unipath_debug'])) var_dump("sprintf start_data ---", $tree[$lv-1]);
	// подготавливаем cursor() если это он
	if(isset($tree[$lv-1]["metadata"]['cursor()'])) {
		global $__uni_prt_cnt;
		$data = new SplFixedArray($__uni_prt_cnt);
		$cursor_ok = call_user_func_array(
			$tree[$lv-1]["metadata"]['cursor()'],
			array(&$tree, $lv-1, 'rewind'));

if(!empty($GLOBALS['unipath_debug'])) var_dump('UniPath.'.__FUNCTION__.': $cursor_ok = '.var_export($cursor_ok, true));
		if($cursor_ok != true) {
			trigger_error('UniPath.'.__FUNCTION__.': '.$tree[$lv-1]["metadata"]['cursor()'].'(rewind) return '.var_export($cursor_ok, true), E_USER_NOTICE);
			return array('data' => null, 'metadata' => array('null'));
		}
	}
	
	// любой другой тип данных приводим к массиву
	else
		$data = (array) $tree[$lv-1]["data"];

	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	$result = array('data' => array(), 'metadata' => array('array'));
	foreach($data as $key => $item) {

		// если это курсор, то берём следующий элемент
		if(isset($cursor_ok) and $cursor_ok = call_user_func_array($tree[$lv-1]["metadata"]['cursor()'], array(&$tree, $lv-1, 'next'))) {
				$item = $cursor_ok['data'];
				$key = isset($cursor_ok['metadata']['key()'])
					? $cursor_ok['metadata']['key()']
					: $key;
		}
		elseif(isset($cursor_ok))
			break;
			
	
		$sprintf_args = $args;
		foreach($args_types as $arg_name => $arg_type)
			if($arg_type == 'unipath') {
				$uni_result = __uni_with_start_data(
					$item, gettype($item), array(gettype($item), 'key()' => $key),
					$args[$arg_name]);
				$sprintf_args[$arg_name] = $uni_result['data'];
			}

		$result['data'][$key] = call_user_func_array('sprintf', $sprintf_args);
// var_dump($result['data'][$key], $sprintf_args);
	}
// var_dump($result);
	// скалярные данные преобразуем обратно
	if(!is_array($tree[$lv-1]["data"]) and !isset($tree[$lv-1]["metadata"]['cursor()']))
		return array(
			'data' => current($result['data']),
			'metadata' => array('string'));
	
	return $result;
}

function _uni_sprintf1(&$tree, $lv = 0) {

	if(!isset($tree[$lv-1]['data'])) 
		return array('data' => null, 'metadata' => array('null'));

	// упакуем в массив
	$tree[$lv-1]['data'] = array($tree[$lv-1]['data']);

	// вызовем
	$uni_result = _uni_sprintf($tree, $lv);
	$uni_result['data'] = $uni_result['data'][0];
	$uni_result['metadata'] = array('string');

	// распакуем обратно
	$tree[$lv-1]['data'] = $tree[$lv-1]['data'];
	
	return $uni_result;
}

function _uni_asImage($tree, $lv = 0) {
	$im1 = imagecreatefromstring($tree[$lv-1]['data']);
	$img_info = getimagesizefromstring($tree[$lv-1]['data']);
	return array(
		'data' => $im1, 
		'metadata' => array('resource/gd', 
			'img_info' => $img_info, 
			'exif_info' => isset($img_exif_info) ? $img_exif_info : null));
}

function _uni_asImageFile($tree, $lv = 0) {

		if (!in_array($tree[$lv-1]['metadata'][0], array('string/pathname', 'string/local-pathname', 'string/url'))) {
			trigger_error('UniPath:'.__FUNCTION__.': Unsupported data_type - '.$tree[$lv-1]['metadata'][0], E_USER_WARNING);
			return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
		}
		
		// если http/https то сначала скачаем во временный файл
		if ($tree[$lv-1]['metadata'][0] == 'string/url') {
			$temp_file = $img_filename = tempnam(sys_get_temp_dir(), 'unipath');
			if (copy($tree[$lv-1]['data'], $temp_file, stream_context_create(array('ssl' => array('verify_peer' => false)))) == 0) {
				trigger_error('UniPath:'.__FUNCTION__.': Temp file is empty - '.$img_filename, E_USER_WARNING);
				return array('data' => null, 'metadata' => array('null'));
			}
		}
		else {
			$img_filename = $tree[$lv-1]['data'];
		}
		
		// определим тип 
		$img_info = getimagesize($img_filename);

		// не удалось определить тип, это не изображение!
		if ($img_info === false) {
			trigger_error('UniPath:'.__FUNCTION__.': Unknown image type in - '.$img_filename, E_USER_WARNING);
			return array('data' => null, 'metadata' => array('null'));
		}

		// загрузим файл в память
		switch($img_info[2]) {
			case IMAGETYPE_JPEG: 
				// подавим "libjpeg: recoverable error: Invalid SOS parameters for sequential JPEG"
				@ini_set('gd.jpeg_ignore_warning', 1);
				$im1 = @imagecreatefromjpeg($img_filename); 
				if (function_exists('exif_read_data'))
					$img_exif_info = @exif_read_data($img_filename);
					
				// найдём вручную тогда только Orientation
				if(!isset($img_exif_info) || empty($img_exif_info)) {
					$first4K = file_get_contents($img_filename, NULL, NULL, 0, 4096);
					if(preg_match('~\x12\x01\x03\x00\x01\x00\x00\x00(.)\x00~', $first4K, $bytes)
					|| preg_match('~\x01\x12\x00\x03\x00\x00\x00\x01\x00(.)~', $first4K, $bytes))
						$img_exif_info = array('Orientation' => ord($bytes[1]));
				}
				break;
			case IMAGETYPE_GIF: 
				$im1 = imagecreatefromgif($img_filename); 
				break;
			case IMAGETYPE_PNG: 
				if(!isset($im1)) $im1 = imagecreatefrompng($img_filename); 
				$im0 = imagecreatetruecolor($img_info[0], $img_info[1]);
				imagealphablending($im0, false);
				imagesavealpha($im0,true);
				$transparent = imagecolorallocatealpha($im0, 255, 255, 255, 127);
				imagefilledrectangle($im0, 0, 0, $img_info[0], $img_info[1], $transparent);
				imagecopy($im0, $im1, 0, 0, 0, 0, $img_info[0], $img_info[1]);
				imagedestroy($im1);
				$im1 = $im0;
				break;
			default: $im1 = imagecreatefromstring(file_get_contents($img_filename));
		}
		
		// если скачивали во временный файл, то удалим его
		if (isset($temp_file)) @unlink($temp_file);
		
		return array(
			'data' => $im1, 
			'metadata' => array('resource/gd', 
				'img_info' => $img_info, 
				'exif_info' => isset($img_exif_info) ? $img_exif_info : null));
}

function _uni_resize($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(!isset($args[0]))
		$new_width = '';
	elseif($args_types[0] == 'unipath')
		$new_width = __uni_with_start_data(
			$tree[$lv-1]["data"], 
			$tree[$lv-1]["metadata"][0], 
			$tree[$lv-1]["metadata"], 
			$args[0]);
	else
		$new_width = $args[0];
		
	if(!isset($args[1]))
		$new_height = '';
	elseif($args_types[1] == 'unipath')
		$new_height = __uni_with_start_data(
			$tree[$lv-1]["data"], 
			$tree[$lv-1]["metadata"][0], 
			$tree[$lv-1]["metadata"], 
			$args[1]);
	else
		$new_height = $args[1];
		
	if(empty($args[2]))
		$resize_mode = '';
	elseif($args_types[2] == 'unipath')
		$resize_mode = __uni_with_start_data(
			$tree[$lv-1]["data"], 
			$tree[$lv-1]["metadata"][0], 
			$tree[$lv-1]["metadata"], 
			$args[2]);
	else
		$resize_mode = $args[2];
	
	if (is_resource($tree[$lv-1]["data"]) == false) {
		trigger_error('UniPath:'.__FUNCTION__.': Not a resource!', E_USER_WARNING);
		return array('data' => null, 'metadata' => $tree[$lv-1]['metadata']);
	}
	
	// расчитываем новые размеры в соответствии с указанным режимом
	$height = 0; 
	$width = 0;
	$img_info = $tree[$lv-1]['metadata']['img_info'];
	switch($resize_mode) {
		default:
		case "": // only larger
			$dst_x = $dst_y = $src_x = $src_y = 0;
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
			$im2 = imagecreatetruecolor($width, $height);
			break;
		case "box":
			// сразу создаём указаного размера
			$im2 = imagecreatetruecolor($new_width, $new_height);
			
			// заливаем цветом
			if(isset($args['bgcolor']) and $im2) {
				list($r, $g, $b) = sscanf(ltrim($args['bgcolor'],'#'), "%02x%02x%02x");
				$bgcolor = imagecolorallocate($im2, $r, $g, $b);
				imagefill($im2, 0, 0, $bgcolor);
			} 
			elseif($im2) {
				$bgcolor = imagecolorallocate($im2, 0, 0, 0);
				imagefill($im2, 0, 0, $bgcolor);
			}

			// пропорционально уменьшаем/увеличиваем
			if($new_width) {
				$width = $new_width;
				$height = $img_info[1] / ($img_info[0] / $new_width);
			}
			if($new_height and $height > $new_height) {
				$height = $new_height;
				$width = $img_info[0] / ($img_info[1] / $new_height);
			}
			$src_x = $src_y = 0;
			$dst_x = ($new_width - $width) / 2;
			$dst_y = ($new_height - $height) / 2;
			break;
		case "inbox":
			$dst_x = $dst_y = $src_x = $src_y = 0;
			if($new_width) {
				$width = $new_width;
				$height = $img_info[1] / ($img_info[0] / $new_width);
			}
			if($new_height and $height > $new_height) {
				$height = $new_height;
				$width = $img_info[0] / ($img_info[1] / $new_height);
			}
			$im2 = imagecreatetruecolor($width, $height);
			break;
		case "fill":
			$dst_x = $dst_y = $src_x = $src_y = 0;
			if($new_width) {
				$width = $new_width;
				$height = $img_info[1] / ($img_info[0] / $new_width);
			}
			if($new_height and $height < $new_height) {
				$height = $new_height;
				$width = $img_info[0] / ($img_info[1] / $new_height);
			}
			$im2 = imagecreatetruecolor($width, $height);
			break;
	}
if(!empty($GLOBALS['unipath_debug'])) var_dump($resize_mode, $width, $height);

	// Alpha channel
	if($im2) {
		imagealphablending($im2, false);
        imagesavealpha($im2,true);
    }

	// уменьшаем/увеличиваем
	if($im2 and imagecopyresampled($im2, $tree[$lv-1]['data'], $dst_x, $dst_y, $src_x, $src_y, $width, $height, $img_info[0], $img_info[1])) {
		imagedestroy($tree[$lv-1]['data']);
		return array(
			'data' => $im2, 
			'metadata' => array($tree[$lv-1]['metadata'][0],
				'img_info' => array($width, $height, 3=> "height=\"{$height}\" width=\"{$width}\"") + $tree[$lv-1]['metadata']));
	} else
		return array('data' => null, 'metadata' => array('null'));
}

function _uni_crop($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(!isset($args[0]))
		$new_width = '';
	elseif($args_types[0] == 'unipath')
		$new_width = strval(__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]));
	else
		$new_width = $args[0];
		
	if(!isset($args[1]))
		$new_height = '';
	elseif($args_types[1] == 'unipath')
		$new_height = strval(__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[1]));
	else
		$new_height = $args[1];
		
	if(!isset($args[2]))
		$gravity = 'auto';
	elseif($args_types[1] == 'unipath')
		$gravity = strval(__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[2]));
	else
		$gravity = $args[2];
	
	$img_info = $tree[$lv-1]['metadata']['img_info'];
	
	// теперь вырежем центральную область
	$src_x = $src_y = 0;
//	if(in_array($gravity, array("", 'auto', 'center'))) {
		$src_x = round(($img_info[0] - $new_width) / 2);
		$src_y = round(($img_info[1] - $new_height) / 2);

		$im2 = imagecreatetruecolor($new_width, $new_height);
		imagecopy($im2, $tree[$lv-1]['data'], 0, 0, $src_x, $src_y, $img_info[0], $img_info[1]);
		imagedestroy($tree[$lv-1]['data']);
//	}
	
	return array(
		'data' => $im2, 
		'metadata' => array('img_info' => array($new_width, $new_height, 3=> "height=\"{$new_height}\" width=\"{$new_width}\"")) + $tree[$lv-1]['metadata']);
}

function _uni_watermark($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(!isset($args[0]))
		return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
	elseif($args_types[0] == 'unipath') {
		$wm_file = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]);
		$wm_file = $wm_file['data'];
	} else
		$wm_file = $args[0];
	
	// определим тип и загрузим фото
	if(is_resource($wm_file)) {
		$wm_info = array(imagesx($wm_file), imagesy($wm_file));
		$wm = $wm_file;
	} 
	else {
		$wm_info = getimagesize($wm_file);
		switch($wm_info[2]) {
			case IMAGETYPE_JPEG: 
				// подавим "libjpeg: recoverable error: ..."
				@ini_set('gd.jpeg_ignore_warning', 1);
				$wm = imagecreatefromjpeg($wm_file); 
				break;
			case IMAGETYPE_GIF: $wm = imagecreatefromgif($wm_file); break;
			case IMAGETYPE_PNG: $wm = imagecreatefrompng($wm_file); break;
			default: $wm = imagecreatefromstring(file_get_contents($wm_file));
		}
	}

	$img_info = $tree[$lv-1]['metadata']['img_info'];
	
	// уменьшим пропорционально водяной знак если он больше фото
	/*if($img_info[0] < $wm_info[0]) {
		$dest_width = $img_info[0];
		$dest_height = $wm_info[1] / ($wm_info[0] / $dest_width);
	}
	if($img_info[1] < $dest_height) {
		$dest_height = $img_info[1];
		$dest_width = $wm_info[0] / ($wm_info[1] / $dest_height);
	}*/
	
	// расчитаем положение водиного знака
	$dest_width = $wm_info[0];
	$dest_height = $wm_info[1];	
	switch(strtolower(empty($args[1]) ? 'center' : $args[1])) {
		case 'left': $dest_x = 0; break;
		case 'right': $dest_x = $img_info[0] - $dest_width; break;
		case 'center': case 'middle': $dest_x = ($img_info[0] - $dest_width) / 2; break;
		default: $dest_x = intval($args[1]);
	}
	switch(strtolower(empty($args[2]) ? 'center' : $args[2])) {
		case 'top': $dest_y = 0; break;
		case 'bottom': $dest_y = $img_info[1] - $dest_height; break;
		case 'center': case 'middle': $dest_y = ($img_info[1] - $dest_height) / 2; break;
		default: $dest_y = intval($args[2]);
	}
	
	imagealphablending($tree[$lv-1]['data'], true);
	imagealphablending($wm, true);
		
	imagecopyresampled(
		$tree[$lv-1]['data'], $wm, 
		$dest_x, $dest_y, 0, 0, 
		$dest_width, $dest_height, $wm_info[0], $wm_info[1]);

//header('Content-Type: image/jpeg'); imagejpeg($tree[$lv-1]['data']);

	return array('data' => $tree[$lv-1]['data'], 'metadata' => $tree[$lv-1]['metadata']);
}

function _uni_fixOrientation($tree, $lv = 0) {
	if(isset($tree[$lv-1]['metadata']['exif_info'])) {
		$new_meta = $tree[$lv-1]['metadata'];
		switch(isset($new_meta['exif_info']['Orientation']) ? $new_meta['exif_info']['Orientation'] : 0){
			case 3:
				$tree[$lv-1]['data'] = imagerotate($tree[$lv-1]['data'], 180, 0);
				$new_meta['exif_info']['Orientation'] = 0;
				break;
			case 6:
				$tree[$lv-1]['data'] = imagerotate($tree[$lv-1]['data'], -90, 0);
				$new_meta['exif_info']['Orientation'] = 0;
				$new_meta['img_info'][0] = $tree[$lv-1]['metadata']['img_info'][1];
				$new_meta['img_info'][1] = $tree[$lv-1]['metadata']['img_info'][0];
				break;
			case 8:
				$tree[$lv-1]['data'] = imagerotate($tree[$lv-1]['data'], 90, 0);
				$new_meta['exif_info']['Orientation'] = 0;
				$new_meta['img_info'][0] = $tree[$lv-1]['metadata']['img_info'][1];
				$new_meta['img_info'][1] = $tree[$lv-1]['metadata']['img_info'][0];
				break;
		}
	}
	
	return array('data' => $tree[$lv-1]['data'], 'metadata' => isset($new_meta) ? $new_meta : $tree[$lv-1]['metadata']);
}

function _uni_toJPEG($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(!isset($args['quality']))
		$quality = 83;
	elseif($args_types['quality'] == 'unipath')
		$quality = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args['quality']);
	else
		$quality = $args['quality'];
	
	$stream = fopen("php://memory", "w+");
	imagejpeg($tree[$lv-1]['data'], $stream, $quality);
	rewind($stream);
	$result = stream_get_contents($stream);
	fclose($stream);
	
	return array('data' => $result, 'metadata' => array('string'));
}

function _uni_saveAs($tree, $lv = 0) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
	if(!isset($args[0]))
		$filepath = '';
	elseif($args_types[0] == 'unipath')
		$filepath = __uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]);
	else
		$filepath = $args[0];
	
	$quality = 83;
	
	switch($tree[$lv-1]['metadata'][0]) {
		case 'resource/gd':
			$file_ext = substr($filepath, -4);
			if(stripos($file_ext, 'gif') !== false)
				$result = imagegif($tree[$lv-1]['data'], $filepath);
			elseif(stripos($file_ext, 'png') !== false)
				$result = imagepng($tree[$lv-1]['data'], $filepath, round($quality/10));
			else 
				$result = imagejpeg($tree[$lv-1]['data'], $filepath, $quality);
			
			if($result === false) {
				trigger_error('Image not saved into - '.$filepath.'!', E_USER_WARNING);
				return array(
					'data' => $tree[$lv-1]['data'], 
					'metadata' => $tree[$lv-1]['metadata'] + array(
						'last_error()' => array(E_USER_WARNING, 'Image not saved into - '.$filepath.'!', __FILE__, __LINE__)));
			}
			break;
		default:
			$result = file_put_contents($filepath, $tree[$lv-1]['data']);
			if($result === false) {
				trigger_error('Data not saved into - '.$filepath.'!', E_USER_WARNING);
				return array(
					'data' => $tree[$lv-1]['data'], 
					'metadata' => $tree[$lv-1]['metadata'] + array(
						'last_error()' => array(E_USER_WARNING, 'Data not saved into - '.$filepath.'!', __FILE__, __LINE__)));
			}
			break;
	}
	
	return array(
		'data' => $tree[$lv-1]['data'], 
		'metadata' => $tree[$lv-1]['metadata']
	);
}

function _uni_basename($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_string($tree[$lv-1]['data'])) {
		return array(
			'data' => substr($tree[$lv-1]['data'], max(strrpos($tree[$lv-1]['data'], '/'), -1) + 1), 
			'metadata' => array('string'));
	}
	
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		$result = array();
		foreach($tree[$lv-1]['data'] as $filename)
		if(is_string($filename))
			$result = substr($filename, max(strrpos($filename, '/'), -1) + 1);
			
		return array(
			'data' => $result, 
			'metadata' => array('array'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_plus_procents($tree, $lv = 0) {
	if(is_numeric($tree[$lv-1]['data'])) {
		list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
	
		if(!isset($args[0]))
			$arg1 = 0;
		elseif($args_types[0] == 'unipath')
			$arg1 = floatval(__uni_with_start_data(
				$tree[$lv-1]['data'],
				$tree[$lv-1]['metadata'][0],
				$tree[$lv-1]['metadata'],
				$args[0]));
		else
			$arg1 = floatval($args[0]);
			
		return array('data' => floatval($tree[$lv-1]['data']) + floatval($tree[$lv-1]['data']) * $arg1 / 100, 'metadata' => array('number'));
	}
	
	return array('data' => null, 'metadata' => array('null'));
}

function _uni_XMLtoArray($tree, $lv = 0) {
	$result = array(
		'data' => @simplexml_load_string((string) $tree[$lv-1]['data']),
		'metadata' => array('array')
	);
	
	if(is_object($result['data'])) {
		$result['data'] = array($result['data']->getName() => 
			array_map(create_function('$a', 'return (array) $a;'), (array) $result['data']));
	} else 
		$result['data'] = array();
		
	return $result;
}

function _uni_ArrayToXML($tree, $lv = 0) {

	$result = array('data' => '', 'metadata' => array('string/xml-fragment'));
	
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

function _uni_asHTML($tree, $lv = 0) { 
	$dom = new DOMDocument();
	$dom->loadHTML($tree[$lv-1]['data'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	return array(
		'data' => $dom,
		'metadata' => array('object/'.get_class($dom), 'cursor()' => '_cursor_asDOM'));
}

function _uni_asXML($tree, $lv = 0) { 
// 	return _uni_asSimpleXML($tree, $lv); 
// 	return _uni_asDOM($tree, $lv); 

	$old_value = libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadXML($tree[$lv-1]['data']); var_dump($dom->documentElement->childNodes);
	libxml_use_internal_errors($old_value);
	
	// TODO: проверить: создалась ли html структура?
	// если да, то предупредить?

	return array(
		'data' => $dom->documentElement,
		'metadata' => array('object/DOMElement', 'cursor()' => '_cursor_asXML'));
}

function _uni_asDOM($tree, $lv = 0) { 
	
	if(in_array($tree[$lv-1]["metadata"], array('object/SimpleXMLIterator', 'object/SimpleXMLElement'))) {
		return array(
		'data' => dom_import_simplexml($$tree[$lv-1]["data"]),
		'metadata' => array('object/DOMNode', 'cursor()' => '_cursor_asXML'));
	}
	
// libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadXML($tree[$lv-1]['data']);
	return array(
		'data' => $dom,
		'metadata' => array('object/'.get_class($dom), 'cursor()' => '_cursor_asDOM'));
}

function _uni_asSimpleXML($tree, $lv = 0) { 
	assert('is_string($tree[$lv-1]["data"]);');

	return array(
		'data' => new SimpleXMLIterator($tree[$lv-1]['data'], /* LIBXML_NOERROR | */ LIBXML_NONET | LIBXML_NOXMLDECL), 
		'metadata' => array('object/SimpleXMLIterator', 'cursor()' => '_cursor_asXML'));
}

function _cursor_asXML(&$tree, $lv = 0, $cursor_cmd = '', $cursor_arg1 = null) {
// if($cursor_cmd != 'next')
// var_dump($tree[$lv]['name']." -- ".__FUNCTION__.".$cursor_cmd ".(is_array($cursor_arg1)&&isset($cursor_arg1['name'])?$cursor_arg1['name']:''));
	
	// REWIND
	if($cursor_cmd == 'rewind') {
		if($tree[$lv]['metadata'][0] == "object/DOMElement") {
			return array('data' => $result['data']->textContent, 'metadata' => array('string'));
		}
		elseif($tree[$lv]['metadata'][0] == 'array/DOMElement') {
			$tree[$lv]['metadata']['current_pos'] = 0;
			return true;
		}
		elseif($tree[$lv]['metadata'][0] == 'object/SimpleXMLIterator') {
			return $tree[$lv]['data']->rewind();
		}
		elseif($tree[$lv]['metadata'][0] == 'object/DOMNamedNodeMap') {
			$result = array(
				'data' => array(), 
				'metadata' => array($tree[$lv]['data']->length 
					? 'array/'.get_class($tree[$lv]['data']->item(0))
					: 'array')
			);
				
			for($i = 0; $i < $tree[$lv]['data']->length; $i++) {
				$result['data'][$tree[$lv]['data']->item($i)->nodeName] = $tree[$lv]['data']->item($i);
			}
			
			return $result;
		}
		
		return false;
	}
	
	// NEXT
	if($cursor_cmd == 'next') {
		if($tree[$lv]['metadata'][0] == "object/DOMElement") {
			$xml_elem = $tree[$lv]['data']->childNodes->item($tree[$lv]['metadata']['current_pos']);
			$key = $tree[$lv]['metadata']['current_pos']++;
		}
		elseif($tree[$lv]['metadata'] == 'object/SimpleXMLIterator') {
			$xml_elem = $tree[$lv]['data']->current();
			$key = $tree[$lv]['data']->key();
			$tree[$lv]['data']->next();
		}
		
		if(is_null($xml_elem)) 
			return array();
		
		$result = array(
			'data' => array($xml_elem),
			'metadata' => array('array',
				'cursor()' => __FUNCTION__,
				'each_metadata' => array(array(
					gettype($xml_elem).(is_object($xml_elem)?'/'.get_class($xml_elem):''), 
					'key()' => $key)))
		);
		
		return $result;
	}
	
	// EVAL
	if($cursor_cmd == 'eval' and strpos($cursor_arg1['name'], '(') === false) {
		$name = $cursor_arg1['name'];
// var_dump($name, $tree[$lv]['data_type']);

		if($name == '.') return false;

		
		// asXML()/nodeValue
		if($name == 'nodeValue') {
			$result = array(
				'data' => $tree[$lv]['data']->nodeValue, 
				'metadata' => array(
					gettype($tree[$lv]['data']->nodeValue), 
					'key()' => $name
				)
			);
			return $result;
		}
		
		// asXML()/NNN
		if(is_numeric($name)) {
			if(is_array($tree[$lv]['data']) and isset($tree[$lv]['data'][$name])) {
				$result = array(
					'data' => $tree[$lv]['data'][$name], 
					'metadata' => array(
						gettype($tree[$lv]['data'][$name]).(is_object($tree[$lv]['data'][$name]) ? '/'.get_class($tree[$lv]['data'][$name]) : ''), 
						'key()' => $name, 
						'cursor()' => __FUNCTION__));
				return $result;
			}
			elseif($tree[$lv]['metadata'][0] == 'object/DOMElement' and $name < count($tree[$lv]['data']->childNodes)) {
				$xml_elem = $tree[$lv]['data']->childNodes->item($name);
				$result = array(
					'data' => $xml_elem, 
					'metadata' => array(
						gettype($xml_elem).(is_object($xml_elem) ? '/'.get_class($xml_elem) : ''), 
						'key()' => $name, 
						'cursor()' => __FUNCTION__));

				return $result;
			}
			elseif($tree[$lv]['metadata'][0] == 'object/SimpleXMLIterator' and $name < count($tree[$lv]['data'])) {

				$num = 0;
				$children = $tree[$lv]['data']->children();
				for($children->rewind(); $children->valid(); $children->next())
					if($num != $name) $num++;
					else {
						$xml_elem = $children->current();
						$result = array(
							'data' => $xml_elem, 
							'metadata' => array(
								gettype($xml_elem).(is_object($xml_elem) ? '/'.get_class($xml_elem) : ''), 
								'key()' => $name, 
								'cursor()' => __FUNCTION__));
						return $result;
					}
			} 
			
			return array('data' => null, 'metadata' => array('null'));
		}

		// asXML()/*
		if($name == '*' and strncmp($tree[$lv]['metadata'][0], 'string/xml', 10) == 0 and is_string($tree[$lv]['data'])) {
			$uni_result = _cursor_asXml_parseXML($tree[$lv]['data'], $name);
			$uni_result['metadata'] = &$uni_result['metadata'];
			$uni_result['metadata']['cursor()'] = __FUNCTION__;
// var_dump($uni_result);
			return $uni_result;

			// теперь соберём 2 уровень и сгрупируем по key()
/*			$result = array(
				'data' => array(), 
				'metadata' => array('array'), 
				'data_tracking' => array('key()' => '*', "each_metadata" => array()));
			foreach($uni_result['data'] as $item) {
				$uni_result2 = _cursor_asXml_parseXML($item, '*');
var_dump($uni_result2);
				if(empty($uni_result2['data'])) continue;
				
				foreach($uni_result2['data_tracking']["each_metadata"] as $num => $track)
					if(isset($result['data'][$track['key()']])) {
						$result['data'][$track['key()']][] = $uni_result2['data'][$num];
					} else {
						$result['data'][$track['key()']] = array($uni_result2['data'][$num]);
						$result['data_tracking']['each_metadata'][$track['key()']] = array('key()' => $track['key()']);
					}
						
			}		
print_r($result);
			return $result; */
		}
		
		// asXML()/tag_name
		if($name != '' and $name != '.') {
			if($tree[$lv]['metadata'][0] == 'object/DOMElement') {
				$result = array('data' => array(), 'metadata' => array('array/DOMElement', 
					'key()' => $name, 
					'cursor()' => __FUNCTION__));

				$name = strtolower($name); // почемуто все nodeName в нижнем регистре(?)
				for($i = 0; $i < $tree[$lv]['data']->childNodes->length; $i++) {
					$node = $tree[$lv]['data']->childNodes->item($i);
					if($node->nodeName == $name)
						$result['data'][] = $node;
				}
			}
			elseif($tree[$lv]['metadata'][0] == 'object/SimpleXMLIterator') {
				$result = array(
				'data' => $tree[$lv]['data']->{$name}, 
				'metadata' => array('object/SimpleXMLIterator', 
					'key()' => $name, 
					'cursor()' => __FUNCTION__));
			}
			else
				trigger_error('UniPath.'.__FUNCTION__.': unknown type '.$tree[$lv]['metadata'][0]);
// var_dump($result);

			return false;
		}
	}
	
	// EVAL - toHash()
	if($cursor_cmd == 'eval' and $cursor_arg1['name'] == 'toHash()') {
		$result = array();
		$result_metadata = array(
			'array',
			'key()' => $tree[$lv]['metadata']['key()'],
			'each_metadata' => array());
		for($tree[$lv]['data']->rewind(); $tree[$lv]['data']->valid(); $tree[$lv]['data']->next()) {
			$key = $tree[$lv]['data']->key();
			$xml_elem = $tree[$lv]['data']->current();
			if(isset($result[$key])) {
				$result[$key][] = $xml_elem;
				$result_metadata['each_metadata'][$key][] = array(
					gettype($xml_elem).(is_object($xml_elem) ? '/'.get_class($xml_elem) : ''),
					'key()' => $key,
					'cursor()' => __FUNCTION__);
			} 
			else {
				$result[$key] = array($xml_elem);
				$result_metadata['each_metadata'] = array(array(
					gettype($xml_elem).(is_object($xml_elem) ? '/'.get_class($xml_elem) : ''),
					'key()' => $key,
					'cursor()' => __FUNCTION__
				));
			}
		}
		return array('data' => $result, 'metadata' => $result_metadata);
	}
			
	// EVAL - attrs()
	if($cursor_cmd == 'eval' and $cursor_arg1['name'] == 'attrs()') {
		$xml_attrs = $tree[$lv]['data']->attributes;
		
		// может вернуть NULL если это уже и есть список атрибутов
		if(is_null($xml_attrs)) $xml_attrs = $tree[$lv]['data'];
	
		$result = array(
			'data' => $xml_attrs,
			'metadata' => array(
				gettype($xml_attrs).(is_object($xml_attrs)?'/'.get_class($xml_attrs):''),
				'key()' => $cursor_arg1['name'],
				'cursor()' => __FUNCTION__)
		);
				
		return $result;
	}
}

// для совместимости со старым кодом
function _uni_attrs($tree, $lv = 0) {
	assert('$tree[$lv-1]["data"] instanceof DOMElement');

	$xml_attrs = $tree[$lv-1]['data']->attributes;
		
	// может вернуть NULL если это уже и есть список атрибутов
	if(is_null($xml_attrs)) $xml_attrs = $tree[$lv-1]['data'];
	
	$result = array(
		'data' => $xml_attrs,
		'metadata' => array(
			gettype($xml_attrs).(is_object($xml_attrs)?'/'.get_class($xml_attrs):''),
			'key()' => 'attributes',
			'cursor()' => '_cursor_asXML')
	);
			
	return $result;
}