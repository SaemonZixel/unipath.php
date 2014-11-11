<?php

/*  UniPath - XPath like access to any data
 *  
 *  Version: 1.4-alpha
 *  Author: Saemon Zixel (http://saemon-zixel.moikrug.ru/) on 2013-2014 year
 *  License: BSD
 *
 *	UniversalPath (uniPath.php) - универсальный доступ к любым ресурсам
 *  Задумывался как простой, компактный и удобный интерфейс ко всему. Идеологически похож на jQuery и XPath.
 *  Позваляет читать и манипулировать, в том числе и менять, как локальные переменные внутри программы,
 *  так и в файлы на диске, таблицы в базе данных, удалённые ресурсы и даже менять на удалённом сервере 
 *  параметры запущенного приложения или считать запись access.log по определённой дате и подстроке UserAgent и т.д.
 *  Но всё это в светлом будущем. Сейчас реализованна только маленькая часть.
 */
 
// $uni_to_flush - накопившееся данные для записи в базу данных
$uni_cache_data = array();
$uni_cache_data_type = array(); // типы закешированных данных
$uni_cache_data_tracking = array(); // источники закешированных данных
 
function uni($xpath) {
 
	$tree_node = __unipath_evalUniPath($xpath);
	
	// надо присвоить значение?
	if(func_num_args() > 1) {
		$set_value = func_get_arg(1);

		if(isset($tree_node['data_tracking'])) {
			$track = $tree_node['data_tracking'];
			if(is_array($track)) $track = reset($tree_node['data_tracking']);
			
			if(strncmp($track, 'file://', 7) == 0)
				file_put_contents($track, $set_value);
				
			elseif(sscanf($track, '%[^(]', $src_name) and function_exists("__unipath_{$src_name}_offsetSet"))
				call_user_func("__unipath_{$src_name}_offsetSet", array($tree_node), 0, $set_value);
			
		}
	}
		
	return $tree_node['data'];
}
 
function uni_flush() {

}

// главная функция (сердце UniPath)
function __unipath_evalUniPath($xpath) {
	global $uni_cache_data, $uni_cache_data_type, $uni_cache_data_tracking;

	$tree = __unipath_parseUniPath($xpath);
	
	for($lv = 0; $lv < count($tree); $lv++) {
		$name = isset($tree[$lv]['name']) ? strval($tree[$lv]['name']) : '';
		$filter = empty($tree[$lv]['filter']) ? array() : $tree[$lv]['filter'];
		$prev_data_type = $lv > 0 && isset($tree[$lv-1]['data_type']) ? $tree[$lv-1]['data_type'] : '';
		
		if($lv > 0 and !isset($tree[$lv-1]['data_type']))
			print_r($tree[$lv-1] + array('_err' => 'no data_type set!', '$lv' => $lv));

if(!empty($GLOBALS['unipath_debug'])) echo "<br>--- $lv ---<br>".print_r($tree[$lv], true);

		if(empty($name) and $lv == 0):
			/* var_dump('absolute path start...'); */
		
		// $variable ...
		elseif(strncmp($name, '$', 1) == 0 and $lv == 0):
			switch($name) {
				case '$_SESSION': $tree[$lv]['data'] = $_SESSION; break;
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
			$tree[$lv]['data_tracking'] = '/'.$name;
		
		// var1
		elseif($lv == 0 and is_string($name) and strpos($name, '(') === false and strpos($name, ':') === false):
			if(isset($GLOBALS[$name])) {
				$tree[$lv]['data'] = $GLOBALS[$name];
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				
				switch($tree[$lv]['data_type']) {
					case 'object':
						$tree[$lv]['data_type'] = "object/".get_class($tree[$lv]['data']);
						break;
					case 'resource':
						$tree[$lv]['data_type'] = "resource/".str_replace(' ', '_', get_resource_type($tree[$lv]['data']));
						break;
				}
			};
			
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

		// .../$variable/...
		elseif(strncmp($name, '$', 1) == 0 and $lv > 0):
			$GLOBALS[substr($name, 1)] = $tree[$lv] = $tree[$lv-1];
		
		// 012345...
		elseif(is_numeric($name)):
			if(is_array($tree[$lv-1]['data']) and array_key_exists($name, $tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = $tree[$lv-1]['data'][$name];
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			} else {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			};
			
		// array/field
		elseif($name != '' and strncmp($prev_data_type, 'array', 5) == 0 and strpos($name, '(') === false):
			if(is_array($tree[$lv-1]['data'])) {
				$tree[$lv]['data'] = array_key_exists($name, $tree[$lv-1]['data']) ? $tree[$lv-1]['data'][$name] : null;
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
			} else {
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
				$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			};
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
		
		// ifEmpty(<data>)
		elseif(strncmp($name, 'ifEmpty(', 8) == 0):
			if(empty($tree[$lv-1]['data']) and $tree[$lv-1]['data'] !== '0') {
				$arg1 = substr($name, 8, mb_strlen($name) - 9);
				if(empty($arg1)) 
					$arg1 = null;
				elseif($arg1[0] == '"' or $arg1[0] == "'")
					$arg1 = trim($arg1, $arg1[0]);
				else
					$arg1 = uni($arg1);
			
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
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
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
			};

			
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
		elseif($lv > 0 and is_string($name) and in_array($prev_data_type, array('object/PDO', 'resource/odbc_link'))):
				$tree[$lv]['data'] = array();
				$tree[$lv]['data_type'] = '/sql_table';
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
					$expr = 'expr1';
					
					// SQL-expression
					while($expr && isset($filter[$expr])) {
						// left
						switch($filter[$expr]['left_type']) {
							case 'expr':
								$filter[$expr]['left_sql'] = $filter[$filter[$expr]['left']]['sql'];
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
									case 'resource/odbc_link':
									default:
										$filter[$expr]['right_sql'] = "'".str_replace("'","''",$filter[$expr]['right'])."'";
								}
								if(empty($sql_join))
									$sql_from_binds[] = $filter[$expr]['right'];
								else
									$sql_join_binds[] = $filter[$expr]['right'];
								break;
							case 'number':
								$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
							case 'list_of_numbers':
								$filter[$expr]['right_sql'] = "(".implode(',',$filter[$expr]['right']).")";
								$filter[$expr]['op'] = 'IN';
								break;
							case 'expr':
								$filter[$expr]['right_sql'] = $filter[$filter[$expr]['right']]['sql'];
								break;
							case 'name':
								if($filter[$expr]['right'] == 'null') {
									$filter[$expr]['right_sql'] = 'NULL';
									$filter[$expr]['op'] = 'IS';
								} else
									$filter[$expr]['right_sql'] = $filter[$expr]['right'];
								break;
						}
						
						// op
						if(isset($filter[$expr]['sql']) == false) {
							$filter[$expr]['sql'] = "{$filter[$expr]['left_sql']} {$filter[$expr]['op']} {$filter[$expr]['right_sql']}";
if(!isset($filter[$expr]['right_sql'])) print_r($filter);
						}
						
						// next
						$last_expr = $expr;
						$expr = empty($filter[$expr]['next']) ? false : $filter[$expr]['next'];
					}
					
					// alias()
					if(substr_compare($tree[$lv]["name$suffix"], 'alias(', 0, 6, true) === 0) {
						$tbl_name = rtrim(substr($tree[$lv]["name$suffix"], 6), "'\") ");
						$commar_pos = strrpos($tbl_name, ',');
						$alias_name = ltrim(substr($tbl_name, $commar_pos+1), "'\" ");
						$tbl_name = trim(substr($tree[$lv]["name$suffix"], 6, $commar_pos-1), $tbl_name[0]." ") . " AS $alias_name";
					} else
						$tbl_name = $tree[$lv]["name$suffix"];
				
					// FROM ... / JOIN ...
					if(empty($suffix)) {
						$sql_join = $tbl_name; // FROM ...
						$sql_where = isset($last_expr) ? "WHERE ".$filter[$last_expr]['sql'] : "";
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
					if(substr_compare($tree[$i]['name'], 'order_by(', 0, 9, true) == 0) {
						$arg1 = trim(substr($tree[$i]['name'], 9, -1), ' ');
						$char = empty($arg1) ? '' : $arg1[0];
						$arg1 = in_array($char, array('"', "'")) 
							? strtr(substr($arg1, 1, -1), array($char.$char => $char))
							: $arg1;
						$sql_order_by = "ORDER BY $arg1";
						$correct_lv++;
					} else
					if(substr_compare($tree[$i]['name'], 'columns(', 0, 8, true) == 0) {
						$func_args = __unipath_parseFunc($tree[$i]['name']);
//var_dump($func_args, $tree[$i]['name']);
						$sql_select = '';
						$chunked_array = array();
						foreach($func_args as $key => $arg) if($key !== 'func_name') {
							if(substr_compare($arg, 'chunked(', 0, 8, true) == 0) {
								$chunked_args = __unipath_parseFunc($arg);
//print_r($chunked_args);
								$chunked_array[$chunked_args['arg1']] = array();
								$col_name = str_replace('.', '_', $chunked_args['arg1']);
								$chunk_size = isset($chunked_args['arg3']) ? intval($chunked_args['arg3']) : 3000;
								for($ii = 0; $ii < ceil($chunked_args['arg2']/$chunk_size); $ii++) {
									$sql_select .= "SUBSTRING({$chunked_args['arg1']}, ".($ii*$chunk_size+1).", $chunk_size) AS uni_chunk_{$ii}_$col_name, ";
									$chunked_array[$chunked_args['arg1']]["uni_chunk_{$ii}_$col_name"] = true;
								}
							} else
								$sql_select .= "$arg, ";
						}
						$sql_select = rtrim($sql_select, ', ');
						$correct_lv++;
					} else
					if(substr_compare($tree[$i]['name'], 'limit(', 0, 6, true) == 0) {
						$sql_limit = trim(substr($tree[$i]['name'], 7, -1), ' ');
						$correct_lv++;
					} else
					if(substr_compare($tree[$i]['name'], 'asSQLQuery(', 0, 11, true) == 0) {
						$asSQLQuery = true;
						$correct_lv++;
					} else
						break;
				}
				
				// конструируем окончательно запрос
				$sql_binds = array_merge($sql_join_binds, $sql_from_binds);
				$sql = rtrim("SELECT $sql_select FROM $sql_join $sql_where $sql_order_by $sql_group_by $sql_limit", ' ');

				// выполняем запрос или возвращаем
var_dump($sql, $sql_binds, isset($asSQLQuery));
				if(isset($asSQLQuery)) {
					$tree[$correct_lv]['data'] = array('query' => $sql, 'params' => $sql_binds);
					$tree[$correct_lv]['data_type'] = "array/sql_query_with_params";
				} elseif($prev_data_type == 'resource/odbc_link') {
					$res = odbc_prepare($db, $sql);
if(!$res) print_r(array(odbc_error($db), odbc_errormsg($db)));
					odbc_execute($res, $sql_binds);
				} else {
					$res = $db->prepare($sql);
if(!$res) print_r($db->errorInfo());
					$res->execute($sql_binds);
				}

				// выбераем каждую строку
				if(!empty($res))
				while($row = $prev_data_type == 'resource/odbc_link' ? odbc_fetch_array($res) : $res->fetch(PDO::FETCH_ASSOC)) {
				
					// chunked()
					if(!empty($chunked_array)) 
					foreach($chunked_array as $orig_col_name => $group) {
						$row[$orig_col_name] = implode(array_intersect_key($row, $group));
						$row = array_diff_key($row, $group);
					}
				
					// делаем пост-фильтрацию если есть
					if(isset($tree[$lv]['filter2'])) {
						$filter2 = $tree[$lv]['filter2'];
						$expr = 'expr1';
						while($expr && isset($filter2[$expr])) {
							// left
							switch($filter2[$expr]['left_type']) {
								case 'unipath':
									$GLOBALS['unipath_current_db_row'] = $row;
									$left_result = uni("unipath_current_db_row/".$filter2[$expr]['left']);
									break;
								case 'expr':
									$left_result = $filter2[$filter2[$expr]['left']]['result'];
									break;
								case 'name':
									if(in_array($filter2[$expr]['left'], array('null', 'NULL')))
										$left_result = null;
									else
										$left_result = isset($row[$filter2[$expr]['left']]) ? $row[$filter2[$expr]['left']] : null;
									break;
								case 'string':
								case 'number':
								default:
									$left_result = $filter2[$expr]['left'];
									break;
							}

							// right
							if(isset($filter2[$expr]['right_type'])) // если не просто [basket]
							switch($filter2[$expr]['right_type']) { 
								case 'unipath':
									$GLOBALS['unipath_current_db_row'] = $row;
									$right_result = uni("unipath_current_db_row/".$filter2[$expr]['right']);
									break;
								case 'expr':
									$right_result = $filter2[$filter2[$expr]['right']]['result'];
									break;
								case 'name':
									if(in_array($filter2[$expr]['right'], array('null', 'NULL')))
										$right_result = null;
									else
										$right_result = isset($row[$filter2[$expr]['right']]) ? $row[$filter2[$expr]['right']] : null;
								case 'string':
								case 'number':
								default:
									$right_result = $filter2[$expr]['right'];
									break;
							}
					
							// op
							switch($filter2[$expr]['op']) {
								case 'or':
									$filter2[$expr]['result'] = $left_result || $right_result;
									break;
								case 'and':
									$filter2[$expr]['result'] = $left_result && $right_result;
									break;
								default:
									$filter2[$expr]['result'] = $left_result;
							}

							// next
							$last_expr = $expr;
							$expr = empty($filter2[$expr]['next']) ? false : $filter2[$expr]['next'];
						}

						if( ! $filter2[$last_expr]['result'] ) continue;
					} // filter2
					
					/* нет пост-фильтрации либо строка прошла фильтрацию */
					$tree[$correct_lv]['data'][] = $row;
					$tree[$correct_lv]['data_tracking'][] = "sql_table($name)/row";
				};
				
				if($prev_data_type == 'resource/odbc_link'
					and !empty($res) and is_resource($res)) 
					odbc_free_result($res);
				
				if(empty($tree[$correct_lv]['data'])) {
					$tree[$correct_lv]['data'] = null;
					$tree[$correct_lv]['data_type'] = 'null';
					$tree[$correct_lv]['data_tracking'] = "sql_table($name)/zero_rows";
				} else {
					$tree[$correct_lv]['data_type'] = 'array/sql_rows';
				};
				
				// корректируем уровень (если были /db1/.../order_by()/group_by()/limit()/...)
				$lv = $correct_lv;
				
		// cache()
		elseif(sscanf($name, 'cache(%[^)]', $arg1) == 1):
			if($arg1[0] == '"' or $arg1[0] == "'" or $arg1[0] == '$') {
				$arg1 = trim($arg1, '"\'');
				if($lv > 0) {
					if($arg1[0] == '$')
						$GLOBALS[substr($arg1, 1)] = $tree[$lv]['data'] = $tree[$lv-1]['data'];
					else
						$uni_cache_data[$arg1] = $tree[$lv]['data'] = $tree[$lv-1]['data'];
					$uni_cache_data_type[$arg1] = $tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
					if(array_key_exists('data_tracking', $tree[$lv-1])) {
						$uni_cache_data_tracking[$arg1] = $tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
					}
				} else {
					if(isset($uni_cache_data[$arg1]) or $arg1[0] == '$') {
						if($arg1[0] == '$')
							$tree[$lv]['data'] = isset($GLOBALS[substr($arg1, 1)]) ? $GLOBALS[substr($arg1, 1)] : null;
						else
							$tree[$lv]['data'] = $uni_cache_data[$arg1];
						$tree[$lv]['data_type'] = $uni_cache_data_type[$arg1];
						if(array_key_exists($arg1, $uni_cache_data_tracking)) {
							$tree[$lv]['data_tracking'] = $uni_cache_data_tracking[$arg1];
						}
					}
				}
			} else {
				if($lv > 0) {
					uni($arg1, json_encode($tree[$lv-1]));
					$tree[$lv]['data'] = $tree[$lv-1]['data'];
					$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
					if(array_key_exists('data_tracking', $tree[$lv-1]))
						$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
				} else {
					$json_string = uni($arg1);
					if(is_string($json_string)) {
						$json_string = json_decode($json_string, true);
						if(is_array($json_string))
							$tree[$lv] = $tree[$lv] + $json_string;
					}

					if(!is_array($json_string)) {
						$tree[$lv]['data'] = $json_string;
						$tree[$lv]['data_type'] = gettype($json_string);
						//if($lv > 0 and array_key_exists('data_tracking', $tree[$lv-1]))
						//	$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
					}
				}
			};
				
		// local_filesystem
		elseif($name == 'file://'):
			$tree[$lv]['data'] = null;
			$tree[$lv]['data_type'] = 'local_filesystem';
			$tree[$lv]['data_tracking'] = 'file://';
				
		// local_filesystem start
		elseif($prev_data_type == 'local_filesystem'):
			if($name == '~') {
				$tree[$lv]['data'] = realpath('~');
				$tree[$lv]['data_type'] = 'local_directory';
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
			} else
			if($name == '') {
				$tree[$lv]['data'] = '';
				$tree[$lv]['data_type'] = 'local_directory';
			} else
			if($name == '.') {
				$tree[$lv]['data'] = realpath('.');
				$tree[$lv]['data_type'] = 'local_directory';
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
			} else {
				$path = realpath('.') . '/' . $name;
				$tree[$lv]['data'] = $path;
				$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];

				if(file_exists($path)) {
					if(is_dir($path))
						$tree[$lv]['data_type'] = 'local_directory';
					else
					if(is_file($path))
						$tree[$lv]['data_type'] = 'local_file';
					else
					if(is_link($path))
						$tree[$lv]['data_type'] = 'local_symlink';
					else
						$tree[$lv]['data'] = null;
				}
			};
			
		// local_direcory
		elseif(in_array($prev_data_type, array('local_directory', 'local_filesystem'))):
			if($name[0] == '.') $path = realpath($tree[$lv-1]['data'].'/'.$name);
			else $path = $tree[$lv-1]['data'].'/'.$name;

			$tree[$lv]['data'] = $path;
			$tree[$lv]['data_tracking'] = 'file://'.$tree[$lv]['data'];
				
			if(file_exists($path)) {
				if(is_dir($path))
					$tree[$lv]['data_type'] = 'local_directory';
				else
				if(is_file($path))
					$tree[$lv]['data_type'] = 'local_file';
				else
				if(is_link($path))
					$tree[$lv]['data_type'] = 'local_symlink';
				else
					$tree[$lv]['data'] = null;
			} else {
				$tree[$lv]['data_type'] = 'local_file';
			};
			
		// local_file
		elseif(in_array($prev_data_type, array('local_file', 'local_symlink'))):
			if(file_exists($tree[$lv-1]['data_tracking'])) {
				$tree[$lv]['data'] = file_get_contents($tree[$lv-1]['data_tracking']);
				$tree[$lv]['data_type'] = gettype($tree[$lv]['data']);
				$tree[$lv]['data_tracking'] = "file_contents('".$tree[$lv-1]['data_tracking']."')";
			} else {
				$tree[$lv]['data'] = null;
				$tree[$lv]['data_type'] = 'null';
				$tree[$lv]['data_tracking'] = "file_contents('".$tree[$lv-1]['data_tracking']."')";
			};
		
		// XMLtoArray()
		elseif($name == 'XMLtoArray()'):
			$tree[$lv]['data'] = simplexml_load_string((string) $tree[$lv-1]['data']);
			$tree[$lv]['data'] = array(
				$tree[$lv]['data']->getName() => 
				array_map(create_function('$a', 'return (array) $a;'), (array) $tree[$lv]['data']));
			$tree[$lv]['data_type'] = 'array';
		
		elseif(strncmp($name, 'add(', 4) == 0 and is_string($tree[$lv-1]['data'])):
			$func_and_args = __unipath_parseFunc($name);
			if($func_and_args['arg1_type'] == 'unipath')
				$arg1 = uni($func_and_args['arg1']);
			else
				$arg1 = $func_and_args['arg1'];
				
			$tree[$lv]['data'] = $tree[$lv-1]['data'] . $arg1;
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
		
		elseif(sscanf($name, 'remove_start(%[^)]', $arg1) === 1 and is_string($tree[$lv-1]['data'])):
			if(!empty($arg1) && in_array($arg1[0], array('"', "'"))) 
				$arg1 = substr($arg1, 1, -1);
			if(strncmp($tree[$lv-1]['data'], $arg1, strlen($arg1)) == 0)
				$tree[$lv]['data'] = substr($tree[$lv-1]['data'], strlen($arg1));
			else
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
			
		elseif(sscanf($name, 'remove_end(%[^)]', $arg1) === 1 and is_string($tree[$lv-1]['data'])):
			if(!empty($arg1) && in_array($arg1[0], array('"', "'"))) 
				$arg1 = substr($arg1, 1, -1);
			if(strrpos($tree[$lv-1]['data'], $arg1, -strlen($arg1)) !== false)
				$tree[$lv]['data'] = substr($tree[$lv-1]['data'], 0, -strlen($arg1));
			else
				$tree[$lv]['data'] = $tree[$lv-1]['data'];
			$tree[$lv]['data_type'] = $tree[$lv-1]['data_type'];
			if(array_key_exists('data_tracking', $tree[$lv-1])) {
				$tree[$lv]['data_tracking'] = $tree[$lv-1]['data_tracking'];
			};
		
		// __unipath_<name>()
		elseif(strpos($name, '(') != false and sscanf($name, '%[^(]', $src_name) 
			and function_exists("__unipath_{$src_name}")):
			$tree[$lv] = array_merge($tree[$lv], call_user_func("__unipath_{$src_name}", $tree, $lv));
			
		// если не понятно что делать, тогда просто копируем данные
		else:
			$tree[$lv]['data'] = $lv > 0 ? $tree[$lv-1]['data'] : array();
			$tree[$lv]['data_type'] = $lv > 0 ? $tree[$lv-1]['data_type'] : '/array';
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
			return $tree[$lv];
		}
		
	} // for($lv = ...)
	
	// обработка xpath была прервана?
	throw new Exception('UniPath evaluation interrupted!');
	return $tree[$lv];
}

function __unipath_parseUniPath($xpath = '') {
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
			//$tree[] = array('name' => null);
			$p = strlen($scheme) + 3;
		} else
		
		// если относительный путь, то подключим стартовые данные
		if($xpath[0] == '/') {
			$tree[] = array('name' => '', 'unipath' => ''); 
		}
		$tree[] = array('name' => null);

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
			if(strpos('qwertyuiopasdfghjklzxcvbnm_*@0123456789.$', strtolower($xpath[$p])) !== false) {
				$start_p = $p;
				while(strpos('qwertyuiopasdfghjklzxcvbnm_-*@0123456789.$', strtolower($xpath[$p])) !== false) 
					if(strlen($xpath) <= ++$p) break;
				
				$tree[count($tree)-1]['name'.$suffix] = substr($xpath, $start_p, $p - $start_p);
				
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
				
				continue;
			}
			
			// [] фильтрация
			if($xpath[$p] == '[') {
				$p++; // [
								
				// разбираем фильтр
				$filter = array( 'expr1' => array() );
				$next_expr_num = 2;
				$expr = 'expr1';
				$expr_key = 'left';
				while($p < strlen($xpath) and $xpath[$p] != ']') {
					while($xpath[$p] == ' ' or $xpath[$p] == "\n") $p++;
					
					// до конца фильтрации были пробелы?
					if($xpath[$p] == ']') continue;
					
					// +,-
					if($xpath[$p] == '+' or $xpath[$p] == '-'):
						$op = $xpath[$p++];
						
						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}

						// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
						$old_expr = $expr;
						while(in_array($filter[$old_expr]['op'], array('*','/','div','mod','+','-')))
							if( empty($filter[$old_expr]['next']) ) break;
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','/','div','mod','+','-'))) {
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
						}
						
						continue;
					endif;
					
					// *,div,mod
					if($xpath[$p] == '*' or stripos($xpath, 'div', $p) == $p or stripos($xpath, 'mod', $p) == $p):
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
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod')))
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];

							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod'))) {
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
						}
					endif;
					
					// and, or
					if(stripos($xpath, 'and', $p) == $p or stripos($xpath, 'or', $p-1) == $p):
						$op = $xpath[$p] == 'a' ? 'and' : 'or';
						$p += $xpath[$p] == 'a' ? 3 : 2;
						
						if($expr_key == 'left') {
							$filter[$expr]['op'] = $op;
							$expr_key = 'right';
							continue;
						}

						// поднимемся наверх в поисках того, у кого мы сможе отобрать правую часть
						$old_expr = $expr;
						while(in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', '<>', '!=', 'and', 'or')))
							if( empty($filter[$old_expr]['next']) ) break;
							else $old_expr = $filter[$old_expr]['next'];

						// прикрепляемся справа (продолжаем цепочку)
						if(in_array($filter[$old_expr]['op'], array('*','div','mod', '+','-', '=','>','<','>=','<=', 'and', 'or'))) {
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
							while(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!=')))
								if( empty($filter[$old_expr]['next']) ) break;
								else $old_expr = $filter[$old_expr]['next'];

							// прикрепляемся справа (продолжаем цепочку)
							if(in_array($filter[$old_expr]['op'], array('*','/','div','mod', '+','-', '=','<','>','<=','>=', '<>', '!='))) {
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
					if(strpos('0123456789', $xpath[$p]) !== false) {
						$start_p = $p;
						while(strpos('0123456789-.', $xpath[$p]) !== false) 
							$p++;
						$val = substr($xpath, $start_p, $p - $start_p);
						
						$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'number';
						
						// возможно это список чисел
						while($xpath[$p] == ',') {
							$filter[$expr][$expr_key.'_type'] = 'list_of_numbers';
							$filter[$expr][$expr_key] = array($filter[$expr][$expr_key]);
							$p++;
							
							while(strpos('0123456789,', $xpath[$p]) !== false) 
								$p++;
								
							foreach(explode(',', substr($xpath, $start_p, $p - $start_p)) as $val)
								$filter[$expr][$expr_key][] = $val;
						}
							
						continue;
					}
					
					// строка
					if($xpath[$p] == "'") {
						$start_p = $p++;

						while($p < strlen($xpath) and $xpath[$p] != "'") $p++;
						$val = substr($xpath, $start_p+1, $p - $start_p-1);
						$p++;

						$filter[$expr][$expr_key] = $val;
						$filter[$expr][$expr_key.'_type'] = 'string';
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
					
						while(!in_array($xpath[$p], array(' ', ']', "\n")) and $p < strlen($xpath)) {
//echo "$p = $xpath[$p]\n";
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
			
			$p++; // попробуем пропустить непонятные символы
			
		}; // while($xpath)
		
		// пропишем путь у последнего уровня пути
		if(!empty($tree))
			$tree[count($tree)-1]['xpath'] = $xpath;

		return $tree;
}

// вытаскивает список аргументов внутри функции
function __unipath_parseFunc($string) {
	
	$result = array();
	$in_string = false; // внутри строки?
	$brakets_level = 0; // уровень вложенности скобок
	$arg_start = 0; $mode = ''; $p = 0;
	for($prt_cnt = 0; $prt_cnt < 50; $prt_cnt++) {
	
		if($in_string)
			$len = strcspn($string, "'", $p);
		else
			$len = strcspn($string, ",()'", $p);
			
//echo(" --- $mode\n ".substr($string, $p+$len).' -- p='.$p.' brakets_level='.$brakets_level);

		if($len+$p == strlen($string)) break; // дошли до конца строки
		
		// выделим имя функции
		if($brakets_level == 0 and !$in_string and $string[$p+$len] == '(') {
			$result['func_name'] = ltrim(substr($string, 0, $len));
			$brakets_level++;
			$arg_start = $p = $len+1;
			$mode = 'func_name';
			continue;
		}
		
		// (
		if($string[$p+$len] == '(' and !$in_string) {
			$brakets_level++;
			$p += $len+1;
			$mode = 'open_braket';
			continue;
		}
		
		// )
		if($string[$p+$len] == ')' and !$in_string) {
			$brakets_level--;
			
			if($brakets_level == 0) {
				for($arg_num = 1; $arg_num < 10; $arg_num++)
					if(!isset($result["arg$arg_num"])) break;
					
				if(isset($last_string)) {
					$result["arg$arg_num"] = $last_string;
					$result["arg$arg_num"."_type"] = 'string';
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
				$mode = 'close_braket_end';
			} else {
				$p += $len+1;
				$mode = 'close_braket';
			}
			
			$last_string = null; // на всяк случай
			continue;
		}
		
		// ' - string
		if($string[$p+$len] == "'" and !$in_string) {
			$in_string = true;
			$last_string = '';
			$p += $len+1;
			$mode = 'in_string_start';
			continue;
		}
		
		// '' and string end
		if($string[$p+$len] == "'" and $in_string) {
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

		// , - skip
		if($string[$p+$len] == "," and !$in_string and $brakets_level > 1) {
			$p = $p + $len + 1;
			$mode = 'inner_commar';
			continue;
		}
		
		// , - argument
		if($string[$p+$len] == "," and !$in_string and $brakets_level == 1) {
			for($arg_num = 1; $arg_num < 10; $arg_num++)
				if(!isset($result["arg$arg_num"])) break;
				
			if(isset($last_string)) {
				$result["arg$arg_num"] = $last_string;
				$result["arg$arg_num"."_type"] = 'string';
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

function __unipath_assertEqu_offsetSet($tree, $lv = 0, $test) {
	$data = $tree[$lv]['data'];

	if(sscanf($tree[$lv]['name'], 'assertEqu(%[^)]', $test_name) == 1)
		$test_name = trim($test_name, "'").' - ';
	else
		$test_name = '';
	
	if(!is_array($test))
		return assert('$data == $test; /* '.$test_name.json_encode($data).' != '.json_encode($test).' */');
		
	
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
				if(array_intersect_assoc($val, $test_val) == $test_val) 
					$found = $key;
			}
		}
		
		if(is_null($found)) 
			assert('in_array($test["'.$test_key.'"], $data); /* '.$test_name.'NOT FOUND - '.json_encode($test_val).' - IN - '.json_encode($data).' */');
		else
			$skip[] = $found;
	}
	
}

function __unipath_regexp_match($tree, $lv = 0) {
// print_r($tree[$lv]);
	
	if(sscanf($tree[$lv]['name'], "regexp_match('%[^']'", $regexp) == 0)  
		return array('data' => false);
		
// var_dump("~$regexp~ui", $tree[$lv-1]['data'], preg_match("~$regexp~ui", $tree[$lv-1]['data']));
	return array('data' => preg_match("~$regexp~ui", $tree[$lv-1]['data']));
}

function __unipath_regexp_replace($tree, $lv = 0) {
	if(!isset($tree[$lv-1]['data']))
		return array('data' => null, 'data_type' => 'null');
		
	$func_and_args = __unipath_parseFunc($tree[$lv]['name']);
//var_dump("/{$func_and_args['arg1']}/");
	return array('data' => preg_replace("/{$func_and_args['arg1']}/", $func_and_args['arg2'], $tree[$lv-1]['data']), 'data_type' => 'string');
}

function __unipath_asJSON($tree, $lv = 0) {
	$data = json_decode($tree[$lv-1]['data'], true);
	return array('data' => $data, 'data_type' => gettype($data));
}

function __unipath_translit($tree, $lv = 0) {
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

function __unipath_formatPrice($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		$func_and_args = __unipath_parseFunc($tree[$lv]['name']);
		$price = floatval($tree[$lv-1]['data']);
		$price_formated = number_format($price, 0, ',', ' ');
		
		if(!empty($func_and_args['arg1']))
			$price_formated .= " ".$func_and_args['arg1'];
		
		return array('data' => $price_formated, 'data_type' => 'null');
	}
	
	return array('data' => null, 'data_type' => 'null');
}

function __unipath_substr($tree, $lv = 0) {
	if(isset($tree[$lv-1]['data'])) {
		$func_and_args = __unipath_parseFunc($tree[$lv]['name']);
		return array('data' => substr($tree[$lv-1]['data'], $func_and_args['arg1']), 'data_type' => 'string');
	}
	
	return array('data' => null, 'data_type' => 'null');
}
