<?php

/*  UniPath - XPath like access to DataBase, Files, XML, Arrays and any other data from PHP
 *  
 *  Version: 1.7.8dev
 *  Author: Saemon Zixel (saemon-zixel.moikrug.ru) on 2013-2015 year
 *  License: MIT
 *
 *	UniversalPath (uniPath.php) - универсальный доступ к любым ресурсам
 *  Задумывался как простой, компактный и удобный интерфейс ко всему. Идеологически похож на jQuery и XPath.
 *  Позваляет читать и манипулировать, в том числе и менять, как локальные переменные внутри программы,
 *  так и в файлы на диске, таблицы в базе данных, удалённые ресурсы и даже менять на удалённом сервере 
 *  параметры запущенного приложения или считать запись access.log по определённой дате и подстроке UserAgent и т.д.
 *  Но всё это в светлом будущем:) Сейчас реализованна только маленькая часть.
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

global $uni_flush_cache, $uni_cache_data, $uni_cache_data_type, $uni_cache_data_tracking, $uni_cache_data_timestamp; // for PHP 5.3 and upper

//$uni_flush_cache = array(); // (private) накопившееся данные для записи в базу данных

$uni_cache_data = array();
$uni_cache_data_type = array(); // типы закешированных данных
$uni_cache_data_tracking = array(); // источники закешированных данных
$uni_cache_data_timestamp = array(); // timestamp когда данные были закешированны

// режим присвоения значения
$__uni_assign_mode = false;

function uni($xpath) {

	// мы в режиме присвоения заняения?
	global $__uni_assign_mode;
	$__uni_assign_mode = func_num_args() > 1;
		
	// разберём путь в дерево
	$tree = __uni_parseUniPath($xpath);
	
	// выполним дерево
	$tree = __uni_evalUniPath($tree);
	
	// последний узел - резудьтат выполнения
	$lv = count($tree)-1;
	$tree_node = $tree[$lv];
	
	// надо присвоить значение?
	if(func_num_args() > 1) {
		$set_value = func_get_arg(1);

		if(isset($tree_node['data_tracking'])) {
			$track = $tree_node['data_tracking'];
			if(is_array($track)) $track = reset($tree_node['data_tracking']);

			if(strncmp($track, 'file://', 7) == 0)
				@file_put_contents($track, $set_value);

			elseif(sscanf($track, '%[^(]', $src_name)) {
				if(function_exists("_uni_{$src_name}_offsetSet"))
					call_user_func("_uni_{$src_name}_offsetSet", array($tree_node), 0, $set_value);
				else {
if(!empty($GLOBALS['unipath_debug'])) error_log("_uni_{$src_name}_offsetSet() - no exist! *** ".$track);
				}
			}
			
		} else { 
		
			// занесём значение в последний узел
			$tree[$lv]['data'] = $set_value;
			
			// пройдёмся по дереву к самому началу
			for($i = $lv-1; $i >= 0; $i--) {
				if(!isset($tree[$i]['data']))
					$tree[$i]['data'] = array();
			
				$tree[$i]['data'][$tree[$i+1]['name']] = $tree[$i+1]['data'];
				
				if(isset($tree[$i]['data_tracking']) and $tree[$i]['data_tracking'] != '$GLOBALS')
					if($tree[$i]['data_tracking'][0] == '$') {
						$GLOBALS[substr($tree[$i]['data_tracking'], 1)] = $tree[$i]['data'];
						continue;
					}
			}
		}
	}
		
	return $tree_node['data'];
}

// сброс накопившихся изменений, закрытие транзакций...
function uni_flush() {
	// пока что пусто
}

// главная функция (сердце UniPath)
function __uni_evalUniPath($tree) {
	global $uni_cache_data, $uni_cache_data_type, $uni_cache_data_tracking, $uni_cache_data_timestamp;

	for($lv = 0; $lv < count($tree); $lv++) {
		$name = isset($tree[$lv]['name']) ? strval($tree[$lv]['name']) : '';
		$filter = empty($tree[$lv]['filter']) ? array() : $tree[$lv]['filter'];
		$prev_data_type = $lv > 0 && isset($tree[$lv-1]['data_type']) ? $tree[$lv-1]['data_type'] : '';
		
		if($lv > 0 and !isset($tree[$lv-1]['data_type']))
			print_r($tree[$lv-1] + array('_err' => 'no data_type set!', '$lv' => $lv));

if(!empty($GLOBALS['unipath_debug'])) echo "<br>--- $lv ---<br>".print_r($tree[$lv], true);

		if(empty($name) and $lv == 0):
			/* var_dump('absolute path start...'); */
		
		elseif($name == '(initial_data_for_the_relative_path)'):
			if(array_key_exists('data', $tree[$lv]) == false) {
				$tree[$lv]['data'] =& $GLOBALS;
				$tree[$lv]['data_type'] = 'array';
				$tree[$lv]['data_tracking'] = '$GLOBALS';
			};
		
		// $variable ...
		elseif(strncmp($name, '$', 1) == 0):
			switch($name) {
				case '$_SESSION': $tree[$lv]['data'] = isset($_SESSION) ? $_SESSION : null; break;
				case '$_POST': $tree[$lv]['data'] = $_POST; break;
				case '$_GET': $tree[$lv]['data'] = $_GET; break;
				case '$_FILES': $tree[$lv]['data'] = $_FILES; break;
				default: 
					if(array_key_exists(trim($name,'$'), $GLOBALS))
						$tree[$lv]['data'] = $GLOBALS[trim($name,'$')];
					else 
						$tree[$lv]['data'] = null;
			}
			$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			$tree[$lv]['data_tracking'] = $name;
		
		// .../$variable/...
		/* elseif(strncmp($name, '$', 1) == 0 and $lv > 0):
			$GLOBALS[substr($name, 1)] = $tree[$lv] = $tree[$lv-1]; */
		
		// ifEmpty(<data>)
		elseif(strncmp($name, 'ifEmpty(', 8) == 0):
			if(empty($tree[$lv-1]['data']) and $tree[$lv-1]['data'] !== '0') {
				$func_and_args = __uni_parseFunc($name);
				if(!isset($func_and_args['arg1']))
					$arg1 = '';
				elseif($func_and_args['arg1_type'] == 'unipath')
					$arg1 = uni($func_and_args['arg1']);
				else
					$arg1 = $func_and_args['arg1'];
			
				$tree[$lv]['data'] = $arg1;
				$tree[$lv]['data_type'] = gettype($arg1);
			} else {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			};
			
		// toArray()
		elseif($name == 'toArray()'):
			if(empty($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = array();
				$tree[$lv]['data_type'] = 'array';
			} else {
				$tree[$lv]['data'] = (array) $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = is_array($tree[$lv-1]['data']) || strncmp($tree[$lv-1]['data_type'], 'array', 5) == 0 ? $tree[$lv-1]['data_type'] : 'array';
				if(array_key_exists('data_tracking', $tree[$lv-1]))
					$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};

		// toHash()
		elseif(strncmp($name, 'toHash(', 7) == 0):
			if(is_array($tree[$lv-1]['data']) and sscanf($name, "toHash('%[^']", $pkey) == 1) {
				$tree[$lv]['data'] = array();
				$tree[$lv]['data_type'] = 'array';
				$tree[$lv]['data_tracking'] = array();
				for(reset($tree[$lv-1]['data']); ($key = key($tree[$lv-1]['data'])) !== null; next($tree[$lv-1]['data'])) {
					if(is_array($tree[$lv-1]['data'][$key]) 
						and array_key_exists($pkey, $tree[$lv-1]['data'][$key])) {
						
						$new_key = $tree[$lv-1]['data'][$key][$pkey];
						$tree[$lv]['data'][$new_key] = $tree[$lv-1]['data'][$key];
						
						if(isset($tree[$lv-1]['data_tracking']) 
							and isset($tree[$lv-1]['data_tracking'][$key]))
							$tree[$lv]['data_tracking'][$new_key] = $tree[$lv-1]['data_tracking'][$key];
					}
				}
			} elseif(is_array($tree[$lv-1]['data'])){
				$tree[$lv]['data'] = array();
				$tree[$lv]['data_type'] = 'array';
				foreach($tree[$lv-1]['data'] as $num => $item) {
					if(isset($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking'][$num]) and strncmp($tree[$lv-1]['data_tracking'][$num], '{"', 2) == 0) {
						$metadata = json_decode(substr($tree[$lv-1]['data_tracking'][$num], 0, -1), true);
						$key = $metadata['key()'];
						
						if(isset($tree[$lv]['data'][$key]))
							$tree[$lv]['data'][$key][] = $item;
						else
							$tree[$lv]['data'][$key] = array($item);
					}
				}
			};

		// count()
		elseif($name == 'count()'):
			$tree[$lv]['data_type'] = 'integer';
			$tree[$lv]['data'] = count($tree[$lv-1]['data']);
		
		// sum()
		elseif($name == 'sum()'):
			$tree[$lv]['data'] = array_sum((array) $tree[$lv-1]['data']); 
			$tree[$lv]['data_type'] = gettype($tree[$lv-1]['data']);
			
		// assertEqu()
		elseif(strncmp($name, 'assertEqu(', 10) == 0):
			$tree[$lv]['data'] = $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(isset($tree[$lv]['data_tracking']) and is_array($tree[$lv]['data_tracking'])) {
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
			};
		

		// <PDO-object/odbc_link>/<table_name>[...]
		elseif($lv > 0 and is_string($name) and in_array($prev_data_type, array('object/PDO', 'resource/odbc-link'))):
				$tree[$lv]['data'] = array();
				$tree[$lv]['data_type'] = 'sql_table';
				$tree[$lv]['data_tracking'] = array();
				$db = $tree[$lv-1]['data'];
				
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
								$filter[$expr]['right_sql'] = "('".implode("','", $filter[$expr]['right'])."')";
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
								} else/*if(substr_compare($filter[$expr]['right'], 'like2(', 0, 5, true) == 0) {
									$filter[$expr]['right_sql'] = iconv('UTF-8', 'WINDOWS-1251', preg_replace("~like2\(([^,]+),  *(N)?'?([^)']+).*~ui", "$1 LIKE $2'$3'", $filter[$expr]['right']));
								} else*/
									$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'name':
								if($filter[$expr]['right'] == 'null') {
									$filter[$expr]['right_sql'] = 'NULL';
									$filter[$expr]['op'] = 'IS';
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
									case 'resource/odbc-link':
									default:
										$filter[$expr]['right_sql'] = "'".str_replace("'","''",$value)."'";
								}
								else
									$filter[$expr]['right_sql'] = $value;
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
if(!isset($filter[$expr]['right_sql'])) print_r($filter);
							} else
								$filter[$expr]['sql'] = $filter[$expr]['left_sql'];
						}
						
						// next
						$last_expr = $expr;
						$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
					}
					
					// alias()
					if(strlen($tree[$lv]["name$suffix"]) >= 6 and substr_compare($tree[$lv]["name$suffix"], 'alias(', 0, 6, true) === 0) {
						$tbl_name = rtrim(substr($tree[$lv]["name$suffix"], 6), "'\") ");
						$commar_pos = strrpos($tbl_name, ',');
						$alias_name = ltrim(substr($tbl_name, $commar_pos+1), "'\" ");
						$tbl_name = trim(substr($tree[$lv]["name$suffix"], 6, $commar_pos-1), $tbl_name[0]." ") . " AS $alias_name";
					} else
						$tbl_name = $tree[$lv]["name$suffix"];
				
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
						$arg1 = trim(substr($tree[$i]['name'], 9, -1), ' ');
						$char = empty($arg1) ? '' : $arg1[0];
						$arg1 = in_array($char, array('"', "'")) 
							? strtr(substr($arg1, 1, -1), array($char.$char => $char))
							: $arg1;
						$sql_order_by = "ORDER BY $arg1";
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 8 and substr_compare($tree[$i]['name'], 'columns(', 0, 8, true) == 0) {
						$func_args = __uni_parseFunc($tree[$i]['name']);
//var_dump($func_args, $tree[$i]['name']);
						$sql_select = '';
						$chunked_array = array();
						foreach($func_args as $key => $arg) if($key !== 'func_name' and strpos($key, '_type') === false) {
							if(substr_compare($arg, 'chunked(', 0, 8, true) == 0) {
								$chunked_args = __uni_parseFunc($arg);
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
							} else
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
						$func_args = __uni_parseFunc($tree[$i]['name']);
						if(isset($func_args['arg2'])) {
							$iconv_from = $func_args['arg1'];
							$iconv_to = $func_args['arg2'];
						} else {
							$iconv_from = $func_args['arg1'];
							$iconv_to = 'UTF-8';
						}
						$correct_lv++;
					} else
					if(strlen($tree[$i]['name']) >= 4 and substr_compare($tree[$i]['name'], 'top(', 0, 4, true) == 0) {
						$func_args = __uni_parseFunc($tree[$i]['name']);
						$sql_select = "TOP {$func_args['arg1']} $sql_select";
						$correct_lv++;
					} else
						break;
				}
				
				// конструируем окончательно запрос
				$sql_binds = array_merge($sql_join_binds, $sql_from_binds);
				$sql = rtrim("SELECT $sql_select FROM $sql_join $sql_where $sql_order_by $sql_group_by $sql_limit", ' ');
				
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
				} elseif($prev_data_type == 'resource/odbc-link') {
					$res = odbc_prepare($db, $sql) or print_r(array(odbc_error($db), odbc_errormsg($db)));
					odbc_setoption($res, 2, 0, 15); // time-out
					odbc_execute($res, $sql_binds) or print_r(array(odbc_error($db), odbc_errormsg($db)));
				} else {
					$res = $db->prepare($sql);
if(!$res) print_r($db->errorInfo());
					$res->execute($sql_binds);
				}

				// выбераем каждую строку
				if(!empty($res))
				while($row = $prev_data_type == 'resource/odbc-link' ? odbc_fetch_array($res) : $res->fetch(PDO::FETCH_ASSOC)) {
				
					// chunked()
					if(!empty($chunked_array)) 
					foreach($chunked_array as $orig_col_name => $group) {
						$row[$orig_col_name] = implode(array_intersect_key($row, $group));
						$row = array_diff_key($row, $group);
					}
				
					$tree[$correct_lv]['data'][] = $row;
					//$tree[$correct_lv]['data_tracking'][] = "sql_table($name)/row";
				};

				// освободим ресурсы/память
				if($prev_data_type == 'resource/odbc-link'
					and !empty($res) and is_resource($res)) 
					odbc_free_result($res);
				
				if(empty($tree[$correct_lv]['data'])) {
					$tree[$correct_lv]['data'] = null;
					$tree[$correct_lv]['data_type'] = 'null';
				} elseif(!isset($tree[$correct_lv]['data_type'])) {
					$tree[$correct_lv]['data_type'] = 'array/db-rows';
				};

				// корректируем уровень (если были /db1/.../order_by()/group_by()/limit()/...)
				$lv = $correct_lv;
				
		// cache()
		elseif(sscanf($name, 'cache(%[^)]', $arg1) == 1):
			if($arg1[0] == '"' or $arg1[0] == "'" or $arg1[0] == '$') {
				$arg1 = trim($arg1, '"\'');
				// save
				if($lv > 0 and $tree[$lv-1]['name'] != '(initial_data_for_the_relative_path)') {
					if($arg1[0] == '$')
						$GLOBALS[substr($arg1, 1)] = $tree[$lv]['data'] = $tree[$lv-1]['data'];
					else
						$uni_cache_data[$arg1] = $tree[$lv]['data'] = $tree[$lv-1]['data'];
					$uni_cache_data_type[$arg1] = $tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
					$uni_cache_data_timestamp[$arg1] = time();
					if(array_key_exists('data_tracking', $tree[$lv-1]))
						$uni_cache_data_tracking[$arg1] = $tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
				} 
				// restore
				else {
					if(isset($uni_cache_data[$arg1]) or $arg1[0] == '$') {
						if($arg1[0] == '$')
							$tree[$lv]['data'] = isset($GLOBALS[substr($arg1, 1)]) ? $GLOBALS[substr($arg1, 1)] : null;
						else
							$tree[$lv]['data'] = $uni_cache_data[$arg1];
						$tree[$lv]['data_type'] = $uni_cache_data_type[$arg1];
						if(array_key_exists($arg1, $uni_cache_data_tracking))
							$tree[$lv]['data_tracking'] = $uni_cache_data_tracking[$arg1];
					}
				}
			} else {
				// save
				if($lv > 0 and $tree[$lv-1]['name'] != '(initial_data_for_the_relative_path)') {
					uni($arg1, json_encode($tree[$lv-1]+array('cache_timestamp' => time())));
					$tree[$lv]['data'] = $tree[$lv-1]['data'];
					$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
					/*if(array_key_exists('data_tracking', $tree[$lv-1]))
						$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];*/
				} 
				// restore
				else {
					$json_string = uni($arg1);
					if(is_string($json_string)) {
						$json_string = json_decode($json_string, true);

						// проверим валидность и lifetime
						if(is_array($json_string)) {
							if(isset($json_string['cache_timestamp'])
							and $json_string['cache_timestamp'] < time() - 600 and strpos($_SERVER['HTTP_HOST'], '.loc') === false) {
								$tree[$lv]['data'] = null;
								$tree[$lv]['data_type'] = 'null';
							} else
								$tree[$lv] = $tree[$lv] + $json_string;
						}
					}

					if(!is_array($json_string)) {
						$tree[$lv]['data'] = $json_string;
						$tree[$lv]['data_type'] = gettype($json_string);
						//if($lv > 0 and array_key_exists('data_tracking', $tree[$lv-1]))
						//	$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
					}
				}
			};
		
		// url()
		elseif(strncmp($name, 'url(', 4) == 0):
			$func_and_args = __uni_parseFunc($name);
			if($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = $arg1;
			$tree[$lv]['data_type'] = 'string/url';
		
		// fs() [local-filesystem]
		elseif($name == 'file://' or $name == 'fs()'):
			$tree[$lv]['data'] = null;
			$tree[$lv]['data_type'] = 'local-filesystem';
			$tree[$lv]['data_tracking'] = 'file://';
				
		// [local-filesystem] start
		elseif($prev_data_type == 'local-filesystem'):
			if($name == '~') {
				$tree[$lv]['data'] = realpath('~');
				$tree[$lv]['data_type'] = 'local-directory';
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
			} else
			if($name == '') {
				$tree[$lv]['data'] = '';
				$tree[$lv]['data_type'] = 'local-directory';
			} else
			if($name == '.') {
				$tree[$lv]['data'] = realpath('.');
				$tree[$lv]['data_type'] = 'local-directory';
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
			} else {
				$path = realpath('.') . '/' . $name;
				$tree[$lv]['data'] = $path;
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];

				if(file_exists($path)) {
					if(is_dir($path))
						$tree[$lv]['data_type'] = 'local-directory';
					else
					if(is_file($path))
						$tree[$lv]['data_type'] = 'local-file';
					else
					if(is_link($path))
						$tree[$lv]['data_type'] = 'local-symlink';
					else
						$tree[$lv]['data'] = null;
				}
			};
			
		// asDirectory()
		elseif($name == 'asDirectory()'):
			$path = realpath($tree[$lv-1]['data']);
			if(file_exists($path)) {
				$tree[$lv]['data'] = $path;
				$tree[$lv]['data_type'] = 'local-directory';
			} else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			};
			
		// [local-direcory/local-filesystem]
		elseif(in_array($prev_data_type, array('local-directory', 'local-filesystem'))):
			if($name == '.') $path = realpath($tree[$lv-1]['data'].'/'.$name);
			else $path = $tree[$lv-1]['data'].'/'.$name;

			$tree[$lv]['data'] = $path;
			$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
				
			if(file_exists($path)) {
				if(is_dir($path))
					$tree[$lv]['data_type'] = 'local-directory';
				else
				/*if(is_file($path) and isset($tree[$lv+1]) and $tree[$lv+1]['name'] != 'contents')
					$tree[$lv]['data_type'] = 'local_file';
				else*/
				if(is_file($path)) {
					if(isset($tree[$lv+1]) and $tree[$lv+1]['name'] == 'asImageFile()') {
						$tree[$lv]['data'] = $path;
						$tree[$lv]['data_type'] = 'string/pathname';
					} else {
						$tree[$lv]['data'] = file_get_contents($path);
						$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
					}
				} else
				/*if(is_link($path) and isset($tree[$lv+1]) and $tree[$lv+1]['name'] != 'contents')
					$tree[$lv]['data_type'] = 'local_symlink';
				else*/
				if(is_link($path)) {
					$tree[$lv]['data'] = file_get_contents($path);
					$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				} else
					$tree[$lv]['data'] = null;
			} else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			};
			
		// asFile()
		elseif($name == 'asFile()'):
			$path = realpath($tree[$lv-1]['data']);
			if(file_exists($path)) {
				$tree[$lv]['data'] = file_get_contents($path);
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			} else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			};
			
		// local_file
		/*elseif(in_array($prev_data_type, array('local_file', 'local_symlink'))):
			if(file_exists($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = file_get_contents($tree[$lv-1]['data']);
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				//$tree[$lv]['data_tracking'] = "file_contents('".$tree[$lv-1]['data_tracking']."')";
			} else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
				//$tree[$lv]['data_tracking'] = "file_contents('".$tree[$lv-1]['data_tracking']."')";
			};*/
		
		// contents()
		elseif(($name == 'contents()' or $name == 'content()') and $prev_data_type == 'string/url'):
		
			$url_host = parse_url($tree[$lv-1]['data'], PHP_URL_HOST);
			$url_host_port = parse_url($tree[$lv-1]['data'], PHP_URL_PORT) or $url_host_port = 80;
			$url_path = parse_url($tree[$lv-1]['data'], PHP_URL_PATH);
			$url_query = parse_url($tree[$lv-1]['data'], PHP_URL_QUERY);
			empty($url_query) or $url_query = '?'.$url_query;
			
if(!empty($GLOBALS['unipath_debug'])) var_dump($url_host, $url_path.$url_query, $url_host_port);
			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			if($socket === false) {
				error_log("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
			} else {

				$result = socket_connect($socket, gethostbyname($url_host), $url_host_port);
	
				if($result === false) {
					error_log("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)));
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
						$tree[$lv]['data'] = implode('', $resp);
						$tree[$lv]['data_type'] = 'string/data';
				
						// отделим заголовок
						$tree[$lv]['data'] = substr($tree[$lv]['data'], strpos($tree[$lv]['data'], "\r\n\r\n")+4);
//var_dump($tree[$lv]['data']);
						break;
					case 404:
					default:
						break;
				}
			}
			
			// неудача
			if(!isset($tree[$lv]['data'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null/data';
			};
		
		// XMLtoArray()
		elseif($name == 'XMLtoArray()'):
			$tree[$lv]['data'] = @simplexml_load_string((string) $tree[$lv-1]['data']);
			if(is_object($tree[$lv]['data'] )) {
				$tree[$lv]['data'] = array(
				$tree[$lv]['data']->getName() => 
				array_map(create_function('$a', 'return (array) $a;'), (array) $tree[$lv]['data']));
			} else 
				$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = 'array';
		
		// ArrayToXML() [array]
		elseif($name == 'ArrayToXML()' and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = '';
			$tree[$lv]['data_type'] = 'string/xml-fragment';
			foreach($tree[$lv-1]['data'] as $nodeName => $nodeValue) {
				if(!is_numeric($nodeName)) {
					$tree[$lv]['data'] .= "<$nodeName";
					if(is_array($nodeValue) and isset($nodeValue['attrs()']))
					foreach($nodeValue['attrs()'] as $attr => $val)
						$tree[$lv]['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
					$tree[$lv]['data'] .= ">";
				}
					
				if(is_array($nodeValue))
				foreach($nodeValue as $nodeName2 => $nodeValue2) {
					if(!is_numeric($nodeName2)) {
						$tree[$lv]['data'] .= "<$nodeName2";
						if(is_array($nodeValue2) and isset($nodeValue2['attrs()']))
						foreach($nodeValue2['attrs()'] as $attr => $val)
							$tree[$lv]['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
						$tree[$lv]['data'] .= ">";
					}
				
					if(is_array($nodeValue2))
					foreach($nodeValue2 as $nodeName3 => $nodeValue3) {
						if(!is_numeric($nodeName3)) {
							$tree[$lv]['data'] .= "<$nodeName3";
							if(is_array($nodeValue3) and isset($nodeValue3['attrs()']))
							foreach($nodeValue3['attrs()'] as $attr => $val)
								$tree[$lv]['data'] .= " $attr=\"".strtr($val, array('"' => '&quot;', '<' => '&lt;', '>' => '&gt;', '&' => '&amp;', "'" => '&apos;'))."\"";
							$tree[$lv]['data'] .= ">";
						}
					
						if(is_array($nodeValue3))
						foreach($nodeValue3 as $attr => $val) {
							if($attr !== 'attrs()') $tree[$lv]['data'] .= $val;
						} else
							$tree[$lv]['data'] .= $nodeValue3;
							
						$tree[$lv]['data'] .= "</$nodeName3>";
					} else 
						$tree[$lv]['data'] .= $nodeValue2;
					
					if(!is_numeric($nodeName2))
						$tree[$lv]['data'] .= "</$nodeName2>";
				}
				else 
					$tree[$lv]['data'] .= $nodeValue2;
				
				if(!is_numeric($nodeName))
				$tree[$lv]['data'] .= "</$nodeName>";
			};
		
		// add() [string]
		elseif(strncmp($name, 'add(', 4) == 0 and (is_string($tree[$lv-1]['data']) or is_numeric($tree[$lv-1]['data']))):
			$func_and_args = __uni_parseFunc($name);
			if($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = $tree[$lv-1]['data'] . $arg1;
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
			
		// prepand() [string]
		elseif((strncmp($name, 'prepand(', 8) == 0 or strncmp($name, 'prepend(', 8) == 0) and is_string($tree[$lv-1]['data'])):
			$func_and_args = __uni_parseFunc($name);
			if($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = $arg1 . $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
		
		// remove_start() [string]
		elseif(strncmp($name, 'remove_start(', 13) == 0 and is_string($tree[$lv-1]['data'])):
			$func_and_args = __uni_parseFunc($name);
			if($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];

			if(strncmp($tree[$lv-1]['data'], $arg1, strlen($arg1)) == 0) {
				$tree[$lv]['data'] = substr((string) $tree[$lv-1]['data'], strlen($arg1));
				$tree[$lv]['data'] === false and $tree[$lv]['data'] = null;
			} else
				$tree[$lv]['data'] = $tree[$lv-1]['data'];

			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			
		// remove_end() [string]
		elseif(sscanf($name, 'remove_end(%[^)]', $arg1) === 1 and is_string($tree[$lv-1]['data'])):
			$func_and_args = __uni_parseFunc($name);
			if(!isset($func_and_args['arg1']))
				$arg1 = ';';
			elseif($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			if(mb_strpos($tree[$lv-1]['data'], $arg1, mb_strlen($tree[$lv-1]['data'])-mb_strlen($arg1)) !== false)
				$tree[$lv]['data'] = mb_substr($tree[$lv-1]['data'], 0, -mb_strlen($arg1));
			else
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
			
		// remove_empty() [array]
		elseif(strncmp($name, 'remove_empty(', 13) == 0 and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = array(); 
			$tree[$lv]['data_tracking'] = array();
			foreach($tree[$lv-1]['data'] as $key => $item) 
				if(empty($item) == false)
					if(is_string($key)) {
						$tree[$lv]['data'][$key] = $item;
						$tree[$lv]['data_tracking'][$key] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
					} else {
						$tree[$lv]['data'][] = $item;
						$tree[$lv]['data_tracking'][] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
					}

			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			
		// remove_empty() [array]
		elseif(strncmp($name, 'trim(', 5) == 0 and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = array(); 
			$tree[$lv]['data_tracking'] = array();
			foreach($tree[$lv-1]['data'] as $key => $item) 
				if(is_string($key)) {
					$tree[$lv]['data'][$key] = trim($item);
					$tree[$lv]['data_tracking'][$key] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
				} else {
					$tree[$lv]['data'][] = trim($item);
					$tree[$lv]['data_tracking'][] = isset($tree[$lv-1]['data_tracking']) && isset($tree[$lv-1]['data_tracking'][$key]) ? $tree[$lv-1]['data_tracking'][$key] : null;
				}

			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
		
		// wrap() [string]
		elseif(strncmp($name, 'wrap(', 5) == 0):
			$func_and_args = __uni_parseFunc($name);
			if(!isset($func_and_args['arg1']))
				$arg1 = ';';
			elseif($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
			
			if(is_string($tree[$lv-1]['data'])) {
				if(!empty($tree[$lv-1]['data']))
					$tree[$lv]['data'] = "$arg1{$tree[$lv-1]['data']}$arg1";
				else
					$tree[$lv]['data'] = $arg1;
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			};
			
				
		// split() [string]
		elseif(strncmp($name, 'split(', 6) == 0):
			$func_and_args = __uni_parseFunc($name);
			if(!isset($func_and_args['arg1']))
				$arg1 = ';';
			elseif($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
			
			$tree[$lv]['data_type'] = 'array';
			if(!is_array($tree[$lv-1]['data']))
				$tree[$lv]['data'] = explode($arg1, strval($tree[$lv-1]['data']));
			else
				$tree[$lv]['data'] = array_map(create_function('$a','return explode(\''.$arg1.'\', $a);'), $tree[$lv-1]['data']);
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
			
		// join() [string]
		elseif(strncmp($name, 'join(', 5) == 0):
			$func_and_args = __uni_parseFunc($name);
			if(!isset($func_and_args['arg1']))
				$arg1 = '';
			elseif($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = implode($arg1, (array) $tree[$lv-1]['data']);
			$tree[$lv]['data_type'] = 'string';
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};

		// first()
		elseif(strncmp($name, 'first(', 6) == 0 and is_array($tree[$lv-1]['data'])):
			if(is_array($tree[$lv-1]['data']) and !empty($tree[$lv-1]['data'])) {
				$tree[$lv]['data_tracking'] = current(array_keys($tree[$lv-1]['data']));
				$tree[$lv]['data'] = $tree[$lv-1]['data'][$tree[$lv]['data_tracking']];
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			} elseif(!is_array($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			} else {
				$tree[$lv]['data'] =& $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] =& $tree[$lv-1]['data_type'];
				if(isset($tree[$lv-1]['data_tracking'])) 
				$tree[$lv]['data_tracking'] =& $tree[$lv-1]['data_tracking'];
			}
			
		// key()
		elseif(strncmp($name, 'key(', 4) == 0):
			$tree[$lv]['data'] = null;
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				if(is_string($tree[$lv-1]['data_tracking']) and strncmp($tree[$lv-1]['data_tracking'], '{"', 2) == 0) {
					$metadata = json_decode(substr($tree[$lv-1]['data_tracking'], 0, -1), true);
					$tree[$lv]['data'] = $metadata['key()'];
				} else
				if(is_array($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking']['key()'])) {
					$tree[$lv]['data'] = $tree[$lv-1]['data_tracking']['key()'];
				} else
					$tree[$lv]['data'] = $tree[$lv-1]['data_tracking'];
			} else
				$tree[$lv]['data'] = $tree[$lv-1]['name'];
			$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
		
		// unset()
		elseif(strncmp($name, 'unset(', 6) == 0):
			$func_and_args = __uni_parseFunc($name);
			if(!isset($func_and_args['arg1']))
				$arg1 = '';
			elseif($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = $tree[$lv-1]['data'];
			unset($tree[$lv]['data'][$arg1]);
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
		
		// set() or let()
		elseif((strncmp($name, 'set(', 4) == 0 or strncmp($name, 'let(', 4) == 0) 
			and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			
			$func_and_args = __uni_parseFunc($name);
//var_dump($func_and_args, $tree[$lv]['data']);
			if(strncmp($func_and_args['arg1'], './', 2) == 0)
				$func_and_args['arg1'] = substr($func_and_args['arg1'], 2);
			
			if(strncmp($func_and_args['arg2'], './', 2) == 0)
				$func_and_args['arg2'] = substr($func_and_args['arg2'], 2);
			
			if(strncmp($func_and_args['arg1'], '*/', 2) == 0) {
				$arg1 = substr($func_and_args['arg1'], 2);
				$arg2 = substr($func_and_args['arg2'], 2);
				
				foreach($tree[$lv]['data'] as $key => $row)
					$tree[$lv]['data'][$key][$arg1] = __uni_with_start_data($row, $arg2);
				
			} 
			else {
				$tree[$lv]['data'][$func_and_args['arg1']] = __uni_with_start_data($tree[$lv]['data'], $func_and_args['arg2']);
			};
			
		// asXML() [string]
		elseif(strncmp($name, 'asXML(', 6) == 0 and is_string($tree[$lv-1]['data'])):
			$tree[$lv]['data'] =& $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = 'string/xml';
			
		// uni_lastTreeNode()
		elseif($name == 'uni_lastTreeNode()'):
			$tree[$lv]['data'] = $tree[$lv-1];
			$tree[$lv]['data_type'] = 'array/unipath-tree-node';
		
		// _uni_<name>()
		elseif(strpos($name, '(') != false and sscanf($name, '%[^(]', $src_name) 
			and function_exists("_uni_{$src_name}")):
			$tree[$lv] = array_merge($tree[$lv], call_user_func("_uni_{$src_name}", $tree, $lv));
		
		// php_*()
		elseif(strpos($name, '(') !== false and strncmp($name, 'php_', 4) == 0):
			//$func_and_args = __uni_parseFunc($name);
			$tree[$lv]['data'] = call_user_func_array(substr($name, 4, strpos($name, '(')-4), array($tree[$lv-1]['data']));
			$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			
		// .[] - повторная фильтрация данных
		elseif($name == '.' and !empty($filter) and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];

			$cnt = -1;
			foreach($tree[$lv-1]['data'] as $key => $data) {
				$cnt++;

				$expr = $tree[$lv]['filter']['start_expr'];
				$filter = $tree[$lv]['filter'];
				while($expr && isset($filter[$expr])) {
					// left
					switch($filter[$expr]['left_type']) {
						case 'unipath':
							$left_result = __uni_with_start_data($data, $filter[$expr]['left'], array('key()' => $key, 'pos()' => $cnt));
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
								if(isset($tree[$lv-1]['data_tracking']) and is_array($tree[$lv-1]['data_tracking']) and isset($tree[$lv-1]['data_tracking'][$key])) {
								$meta = json_decode(substr($tree[$lv-1]['data_tracking'][$key], 0, -1), true);
								$left_result = $meta['key()'];
								} else $left_result = $key;
							} elseif(strncmp($filter[$expr]['left'], 'like(', 5) == 0) {
								$func_and_args = __uni_parseFunc($filter[$expr]['left']);
								if($func_and_args['arg1_type'] == 'unipath')
									$left_result = __uni_with_start_data($data, $filter[$expr]['left']);
								else
									$left_result = $filter[$expr]['left'];
//var_dump($left_result, trim($func_and_args['arg2'], '%'), strpos($left_result, trim($func_and_args['arg2'], '%')));
								$left_result = strpos($left_result, trim($func_and_args['arg2'], '%')) !== false;
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

					// right
					if(isset($filter[$expr]['right_type']))
					switch($filter[$expr]['right_type']) { 
						case 'unipath':
							$right_result = __uni_with_start_data($data, $filter[$expr]['right']);
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
//var_dump(array($key, $filter[$last_expr]['result'], $data));
				// если прошёл фильтр
				if($filter[$last_expr]['result'])
					$tree[$lv]['data'][$key] = $data;
			};
		
		// * [array]
		elseif($name == '*' and is_array($tree[$lv-1]['data'])):
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = 'array';
			foreach($tree[$lv-1]['data'] as $key => $val) {
			foreach($val as $key2 => $val2)
				if(!isset($tree[$lv]['data'][$key2]))
					$tree[$lv]['data'][$key2] = array($key => $val2);
				else
					$tree[$lv]['data'][$key2][$key] = $val2;
			};

		// 012345...
		elseif(is_numeric($name)):
			if(is_array($tree[$lv-1]['data']) and array_key_exists($name, $tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = $tree[$lv-1]['data'][$name];
				
				// теперь тип элемента
				if(is_array($tree[$lv-1]['data_type']) and array_key_exists($name, $tree[$lv-1]['data_type']))
					$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'][$name];
				else
					$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				// data_tracking может пригодится
				if(isset($tree[$lv-1]['data_tracking']) and is_array($tree[$lv-1]['data_tracking']) and array_key_exists($name, $tree[$lv-1]['data_tracking']))
					$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'][$name];
				
			} else/* if(is_array($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			} else*/ {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
			};

		// asXML()/tag_name [string]
		elseif($name != '' and strpos($name, '(') === false and is_string($prev_data_type)
			and (strncmp($prev_data_type, 'string/xml', 10) == 0 or strncmp($prev_data_type, 'array/xml', 9) == 0) and is_string($tree[$lv-1]['data'])):
			$result = _uni_xml($tree, $lv);
			$tree[$lv] = array_merge($tree[$lv], $result);
			
		// asXML()/tag_name [array]
		/*elseif($name != '' and strpos($name, '(') === false  and is_array($tree[$lv-1]['data'])
			and (strncmp($prev_data_type[0], 'string/xml', 10) == 0 or strncmp($prev_data_type[0], 'array/xml', 9) == 0)):
			$result = array();
			foreach($tree[$lv-1]['data'] as $num => $data) {
				
				$result[] = _uni_xml(array('name' => $name, 'data' => $data, 'data_type' => $tree[$lv-1]['data_type'][$num], 'data_tracking' => $tree[$lv-1]['data_tracking'][$num]), 0);
			}
			$tree[$lv] = array_merge($tree[$lv], $result);*/
			
		// asXML()/attrs() [string]
		elseif(strncmp($name, 'attrs(', 6) == 0 and is_string($tree[$lv-1]['data']) and strncmp($prev_data_type, 'string/xml-fragment', 19) == 0):
			$tree[$lv]['data'] = array();
			$tree[$lv]['data_type'] = array();

			// извлекаем атрибуты из data_tracking-а
			if(isset($tree[$lv-1]['data_tracking']) and is_string($tree[$lv-1]['data_tracking'])) {
				$data_tracking = json_decode(substr($tree[$lv-1]['data_tracking'], 0, -1), true);
				if(preg_match_all('~([^: ]+:)?([^= ]+)=("[^"]+"|[^ >])+~u', $data_tracking['tag'], $matches, PREG_SET_ORDER))
					foreach($matches as $match) {
						$tree[$lv]['data'][$match[2]] = trim($match[3], '"');
						$tree[$lv]['data_type'][$match[2]] = 'string/xml-attribute';
					}
			};
			
		// array/field
		elseif(in_array($name, array('', '.', '*')) == false and is_string($name)
			and strpos($name, '(') === false and strpos($name, ':') === false):
// 			and strncmp($prev_data_type, 'array', 5) == 0 and strpos($name, '(') === false):
			if($lv == 0) {
				if(array_key_exists($name, $GLOBALS)) {
					$tree[$lv]['data'] = $GLOBALS[$name];
				} else {
					$tree[$lv]['data'] = null;
				}
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				$tree[$lv]['data_tracking'] = "$$name";
			} elseif(is_array($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = array_key_exists($name, $tree[$lv-1]['data']) ? $tree[$lv-1]['data'][$name] : null;
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			} else {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
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
		
		// var1
/*		elseif($lv == 0 and is_string($name)
			and strpos($name, '(') === false and strpos($name, ':') === false):
			if(array_key_exists($name, $GLOBALS)) {
				$tree[$lv]['data'] = $GLOBALS[$name];
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				$tree[$lv]['data_tracking'] = '/'.$name;
				
				switch($tree[$lv]['data_type']) {
					case 'object':
						$tree[$lv]['data_type'] = "object/".get_class($tree[$lv]['data']);
						break;
					case 'resource':
						$tree[$lv]['data_type'] = "resource/".str_replace(' ', '_', get_resource_type($tree[$lv]['data']));
						break;
				}
			};*/
			
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
		
		// если не понятно что делать, тогда просто копируем данные
		else:
			$tree[$lv]['data'] = $lv > 0 ? $tree[$lv-1]['data'] : array();
			$tree[$lv]['data_type'] = $lv > 0 ? $tree[$lv-1]['data_type'] : 'array';
			if($lv > 0 && isset($tree[$lv-1]['data_tracking']))
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
		endif;
		
		// сохраним для отладки
if(!empty($GLOBALS['unipath_debug'])) {
		if(!empty($filter)) $tree[$lv]['filter'] = $filter;
		$GLOBALS['unipath_last_tree'] = $tree;
}
		
		// закончился unipath?
		if(isset($tree[$lv+1]) == false) { 
if(!empty($GLOBALS['unipath_debug'])) print_r($tree);
			return $tree;
		}
		
	} // for($lv = ...)
	
	// обработка xpath была прервана?
	throw new Exception('UniPath evaluation interrupted!');
	return $tree[$lv];
}

function __uni_parseUniPath($xpath = '') {
		$tree = array();
		$suffix = '';
		$p = 0;

		// если первым указан протокол
		if(sscanf($xpath, '%[qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM_0123456789]:%2[/]', $scheme, $trailing_component) == 2) {
			$scheme = strtolower($scheme);
			switch($scheme) {
				case 'file':
					$tree[] = array('name' => 'file://', 'unipath' => 'file://'); 
					break;
				case 'http':
					$scheme = strpos($xpath, "??/") > 0 ? substr($xpath, 0, strpos($xpath, "??/")) : $xpath;
					$tree[] = array('name' => "$scheme://", 'unipath' => "$scheme://", 'data' => $scheme, 'data_type' => '/url');
					break;
				default:
					$tree[] = array('name' => "$scheme://", 'unipath' => "$scheme://"); 
			}
			$tree[] = array('name' => null);
			$p = strlen($scheme) + 3;
		} else
		
		// если относительный путь, то подключим стартовые данные
		if($xpath[0] != '/') {
			$tree[] = array('name' => '(initial_data_for_the_relative_path)', 'unipath' => ''); 
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
			if(strpos("\\|/,+[](){}?!~`'\";#%^&=- \n\t", $xpath[$p]) === false) {
				$start_p = $p;
				$len = strcspn($xpath, "\\|/,+[](){}?!~`'\";#%^&=\t\n", $start_p);
				$tree[count($tree)-1]['name'.$suffix] = substr($xpath, $start_p, $len);
				$p += $len;

				// может это function() ?
				if(isset($xpath[$p]) and $xpath[$p] == '(') {
					$inner_brakets = 1; $inner_string = false;
					while($inner_brakets > 0) {
						$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
						if(!isset($xpath[$p])) throw new Exception("Not found close braket in $xpath!");
						if($xpath[$p] == "'") $inner_string = !$inner_string;
						if($xpath[$p] == '(' and !$inner_string) $inner_brakets++;
						if($xpath[$p] == ')' and !$inner_string) $inner_brakets--;
					}
					$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
				}
				
				// Кастыль: возможно это название файла со скобками
				while(isset($xpath[$p]) and !in_array($xpath[$p], array('/', '[', "\n", "\t")))
					$tree[count($tree)-1]['name'.$suffix] .= $xpath[$p++];
				
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
						and isset($xpath[$p+1]) and strpos('0123456789', $xpath[$p+1]) === false)):
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
					endif;
					
					// *,div,mod
					if($xpath[$p] == '*' or stripos($xpath, 'div ', $p) == $p or stripos($xpath, 'mod ', $p) == $p):
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
					endif;
					
					// and, or
					if(stripos($xpath, 'and ', $p) == $p or stripos($xpath, 'or ', $p-1) == $p):
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
					endif;
					
					// =, >, <, <=, >=, <>, !=
					if($xpath[$p] == '=' or $xpath[$p] == '>' or $xpath[$p] == '<' or $xpath[$p] == '!'):
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
					endif;
				
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

		return $tree;
}

// вытаскивает список аргументов внутри функции
function __uni_parseFunc($string) {

	// временный кастыль для ifEmpty()
	if(strncmp($string, 'ifEmpty(', 8) == 0 and strpos($string, '/') !== false 
	  and in_array($string[8], array("'", "`", "\"")) == false)
		return array('func_name' => 'ifEmpty', 'arg1' => substr($string, 8, strlen($string)-9), 'arg1_type' => 'unipath');
	
	$result = array();
	$in_string = $in_binary_string = false; // внутри строки?
	$brakets_level = 0; // уровень вложенности скобок
	$arg_start = 0; $mode = ''; $p = 0;
	for($prt_cnt = 0; $prt_cnt < 50; $prt_cnt++) {
	
		if($in_binary_string)
			$len = strcspn($string, "`", $p);
		elseif($in_string)
			$len = strcspn($string, "'", $p);
		else
			$len = strcspn($string, ",()'`", $p);
			
//echo(" --- $mode\n ".substr($string, $p+$len).' -- p='.$p.' brakets_level='.$brakets_level);

		if($len+$p == strlen($string)) break; // дошли до конца строки
		
		// выделим имя функции
		if($brakets_level == 0 and $string[$p+$len] == '(' and !$in_string and !$in_binary_string) {
			$result['func_name'] = ltrim(substr($string, 0, $len));
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
				for($arg_num = 1; $arg_num < 10; $arg_num++)
					if(!isset($result["arg$arg_num"])) break;
					
				if(isset($last_string)) {
					$result["arg$arg_num"] = $last_string;
					$result["arg$arg_num"."_type"] = 'string';
					$last_string = null;
				} elseif($p+$len > $arg_start) {
					$result["arg$arg_num"] = trim(substr($string, $arg_start, $p+$len-$arg_start));
					if($result["arg$arg_num"] == 'null') {
						$result["arg$arg_num"] = NULL;
						$result["arg$arg_num"."_type"] = 'null';
					} elseif(is_numeric($result["arg$arg_num"]))
						$result["arg$arg_num"."_type"] = 'number';
					else
						$result["arg$arg_num"."_type"] = 'unipath';
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
			$in_string = true;
			$last_string = '';
			$p += $len+1;
			$mode = 'in_string_start';
			continue;
		}
		
		// '' and string end
		if($string[$p+$len] == "'" and $in_string and !$in_binary_string) {
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
		
		// ` - binary-string
		if($string[$p+$len] == "`" and !$in_string and !$in_binary_string) {
			$in_binary_string = true;
			$last_string = '';
			$p += $len+1;
			$mode = 'in_binary_string_start';
			continue;
		}
		
		// ` - binary-string end
		if($string[$p+$len] == "`" and !$in_string and $in_binary_string) {
			$last_string .= substr($string, $p, $len);
			$in_binary_string = false;
			$p += $len+1;
			$mode = 'in_binary_string_end';
			continue;
		}

		// , - skip
		if($string[$p+$len] == "," and !$in_string and !$in_binary_string and $brakets_level > 1) {
			$p = $p + $len + 1;
			$mode = 'inner_commar';
			continue;
		}
		
		// , - argument
		if($string[$p+$len] == "," and !$in_string and !$in_binary_string and $brakets_level == 1) {
			for($arg_num = 1; $arg_num < 10; $arg_num++)
				if(!isset($result["arg$arg_num"])) break;
				
			if(isset($last_string)) {
				$result["arg$arg_num"] = $last_string;
				$result["arg$arg_num"."_type"] = 'string';
				$last_string = null;
			} else {
				$result["arg$arg_num"] = trim(substr($string, $arg_start, $p+$len-$arg_start));
				if($result["arg$arg_num"] == 'null') {
					$result["arg$arg_num"] = NULL;
					$result["arg$arg_num"."_type"] = 'null';
				} elseif(is_numeric($result["arg$arg_num"]))
					$result["arg$arg_num"."_type"] = 'number';
				else
					$result["arg$arg_num"."_type"] = 'unipath';
			}
				
			$p = $arg_start = $p + $len + 1;
			$mode = "arg$arg_num";
			continue;
		}
		
	}
	
	return $result;
}

// тот-же uni(), но с указанием стартовых данных
// $metadata = array('position()' => 1, 'key()' => 1, ...)
function __uni_with_start_data($data, $unipath, $metadata = array()) {

	// разберём путь в дерево
	$tree = __uni_parseUniPath($unipath);
	
	// пропишем стартовые данные
	$tree[0]['data'] = $data;
	$tree[0]['data_type'] = gettype($data);
	$tree[0]['data_tracking'] = $metadata;
	
	// выполним дерево
	$tree = __uni_evalUniPath($tree);
	
	// последний узел - резудьтат выполнения
	$lv = count($tree)-1;
	$tree_node = $tree[$lv];
	
	return $tree_node['data'];
}

function _uni_assertEqu_offsetSet($tree, $lv = 0, $test) {
	$data = $tree[$lv]['data'];

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
			assert('in_array($test["'.$test_key.'"], $data); /* '.$test_name.'NOT FOUND $test["'.$test_key.'"] = '.print_r($test_val, true)."\n --- IN \$data --- \n".print_r($data, true).' */');
		else
			$skip[] = $found;
	}
	
}

function _uni_regexp_match($tree, $lv = 0) {
// print_r($tree[$lv]);
	
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);

	if(empty($func_and_args['arg1']))
		return array('data' => false);
	elseif($func_and_args['arg1_type'] == 'unipath')
		$arg1 = strval(uni($func_and_args['arg1']));
	else
		$arg1 = $func_and_args['arg1'];
		
// var_dump("~$arg1~ui", $tree[$lv-1]['data'], preg_match("~$arg1~ui", $tree[$lv-1]['data']));
	return array('data' => preg_match("~$arg1~ui", $tree[$lv-1]['data']));
}

function _uni_regexp_replace($tree, $lv = 0) {
	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'data_type' => 'null');
		
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
// var_dump($func_and_args);
	if(empty($func_and_args['arg1']))
		return array(
			'data' => $tree[$lv-1]['data'], 
			'data_type' => $tree[$lv-1]['data_type']);
	elseif($func_and_args['arg1_type'] == 'unipath')
		$arg1 = strval(uni($func_and_args['arg1']));
	else
		$arg1 = $func_and_args['arg1'];

	// есть ньюанс с регулярками и надо его обойти для удобства
	// (описан - http://stackoverflow.com/questions/13705170/preg-replace-double-replacement)
	if($arg1 == '.*') $arg1 = '(.+|^$)';
		
	return array(
		'data' => preg_replace("/{$arg1}/u", $func_and_args['arg2'], $tree[$lv-1]['data']), 
		'data_type' => 'string');
}

function _uni_replace_string($tree, $lv = 0) {
	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'data_type' => 'null');
		
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
//var_dump("/{$func_and_args['arg1']}/");
	return array('data' => str_replace($func_and_args['arg1'], $func_and_args['arg2'], $tree[$lv-1]['data']), 'data_type' => 'string');
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
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);

		if(!isset($func_and_args['arg1']))
			$arg1 = '';
		elseif($func_and_args['arg1_type'] == 'unipath')
			$arg1 = strval(uni($func_and_args['arg1']));
		else
			$arg1 = $func_and_args['arg1'];
		
		$price = floatval($tree[$lv-1]['data']);

		// если указали округлять до 50руб
		if(isset($func_and_args['arg2']) and $func_and_args['arg2'] == '999>50') {
			if($price > 999.999)
				$price = ceil($price / 50) * 50;
		}

		// отформатируем число
		$price_formated = number_format($price, 0, ',', ' ') . $arg1;

		return array('data' => $price_formated, 'data_type' => 'null');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_substr($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);
		return array('data' => substr($tree[$lv-1]['data'], $func_and_args['arg1']), 'data_type' => 'string');
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
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);
		return array('data' => strtr($func_and_args['arg1'], $tree[$lv-1]['data']), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_iconv($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);
		
		$result = array('data' => array(), 'data_type' => $tree[$lv-1]['data_type']);
		foreach($tree[$lv-1]['data'] as $key => $val) 
			if(is_array($val)) {
				$result['data'][$key] = array();
				foreach($val as $key2 => $val2)
					$result['data'][$key][$key2] = mb_convert_encoding($val2, $func_and_args['arg2'], $func_and_args['arg1']);
			} else {
				$result['data'][$key] = mb_convert_encoding($val, $func_and_args['arg2'], $func_and_args['arg1']);
			}
		
		/*if(array_key_exists('data_tracking', $tree[$lv-1]))
				$result['data_tracking'] = $tree[$lv-1]['data_tracking'];*/

		return $result;
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_formatQuantity($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);

		$founded = $tree[$lv-1]['data'];
		$suffix = isset($func_and_args['arg3']) ? $func_and_args['arg3'] : 'штук';
		if($founded % 10 == 1 and $founded != 11)
			$suffix = isset($func_and_args['arg1']) ? $func_and_args['arg1'] : 'штука';
		elseif(in_array($founded % 10, array(2,3,4)) && floor($founded / 10) != 1 or $founded == 0)
			$suffix = isset($func_and_args['arg2']) ? $func_and_args['arg2'] : 'штуки';
		
		return array('data' => $founded." ".$suffix, 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_formatDate($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);

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

function _uni_insert_into($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data']) and is_array($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);

		$dst = uni($func_and_args['arg1'].'/uni_lastTreeNode()');
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
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($func_and_args['arg1']))
		$new_width = '';
	elseif($func_and_args['arg1_type'] == 'unipath')
		$new_width = uni($func_and_args['arg1']);
	else
		$new_width = $func_and_args['arg1'];
		
	if(!isset($func_and_args['arg2']))
		$new_height = '';
	elseif($func_and_args['arg2_type'] == 'unipath')
		$new_height = uni($func_and_args['arg2']);
	else
		$new_height = $func_and_args['arg2'];
		
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
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($func_and_args['arg1']))
		$new_width = '';
	elseif($func_and_args['arg1_type'] == 'unipath')
		$new_width = uni($func_and_args['arg1']);
	else
		$new_width = $func_and_args['arg1'];
		
	if(!isset($func_and_args['arg2']))
		$new_height = '';
	elseif($func_and_args['arg2_type'] == 'unipath')
		$new_height = uni($func_and_args['arg2']);
	else
		$new_height = $func_and_args['arg2'];
		
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
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($func_and_args['arg1']))
		return array('data' => $tree[$lv-1]['data'], 'data_type' => $tree[$lv-1]['data_type'], 'data_tracking' => $tree[$lv-1]['data_tracking']);
	elseif($func_and_args['arg1_type'] == 'unipath')
		$wm_file = uni($func_and_args['arg1']);
	else
		$wm_file = $func_and_args['arg1'];
	
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
	$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
	if(!isset($func_and_args['arg1']))
		$filepath = '';
	elseif($func_and_args['arg1_type'] == 'unipath')
		$filepath = uni($func_and_args['arg1']);
	else
		$filepath = $func_and_args['arg1'];
	
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
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
		if(!isset($func_and_args['arg1']))
			$arg1 = 0;
		elseif($func_and_args['arg1_type'] == 'unipath')
			$arg1 = floatval(uni($func_and_args['arg1']));
		else
			$arg1 = floatval($func_and_args['arg1']);
			
		return array('data' => floatval($tree[$lv-1]['data']) + floatval($tree[$lv-1]['data']) * $arg1 / 100, 'data_type' => 'number');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_url_seg($tree, $lv = 0) {
	if(is_string($tree[$lv-1]['data'])) {
		$func_and_args = __uni_parseFunc($tree[$lv]['name']);
	
		if(!isset($func_and_args['arg1']))
			$arg1 = 0;
		elseif($func_and_args['arg1_type'] == 'unipath')
			$arg1 = intval(uni($func_and_args['arg1']));
		else
			$arg1 = intval($func_and_args['arg1']);
			
		$start_offset = /*$arg1[0] == '/' ? 1 :*/ -1;
		if($arg1 > 0)
			for($i = 0; $i < $arg1; $i++) {
				$start_offset = strpos($tree[$lv-1]['data'], '/', $start_offset+1);
var_dump(substr($tree[$lv-1]['data'], $start_offset, 3), $start_offset);
				if($start_offset === false) break;
			}
		
		// если 
		if($start_offset !== false) {
			$offset_end = strpos($tree[$lv-1]['data'], '/', $start_offset+1) 
			or $offset_end = strlen($tree[$lv-1]['data']);
if(!empty($GLOBALS['unipath_debug']))  var_dump('* result offsets *',$start_offset, $offset_end);
			$result = trim(substr($tree[$lv-1]['data'], $start_offset, $offset_end - $start_offset), '/');
		}
		
		return array('data' => isset($result) ? $result : null, 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function _uni_xml($tree, $lv = 0) {
	
	$call = array('find_node', 'xml_as_string' => $tree[$lv-1]['data'], 'tag_name' => $tree[$lv]['name'], 'result_data' => array(), 'result_data_type' => array(), 'result_data_tracking' => array());
	for($prt_cnt = 100; $prt_cnt; $prt_cnt--) {
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
					$call['result_data_type'][] = 'string/xml-fragment';
					$call['result_data_tracking'][] = json_encode(array('string' => 'xml-fragment',
						"key()" => substr($block, $call['tag_start']+$ns_len+1, $nodeName_len - $ns_len - 1), //$call['tag_name'],
						"pos()" => count($call['result_data_tracking']),
						"tag" => substr($block, $call['tag_start'], $tag_end - $tag_start+1),
						"start_offset" => $tag_start, 
						"end_offset" => $tag2_end)) . ',';
					
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