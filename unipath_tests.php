<?php

error_reporting(E_ALL);

if(!empty($_GET['echo']))
	die($_GET['echo']);

require_once('unipath.php');

// -------------- TESTS ------------------------------

if(isset($_GET['test_parseUniPath'])) {
	error_reporting(E_ALL); 
	echo '<pre style="white-space:pre-wrap">';

	$unipath = "/objs[1+2*3 > @obj_id and @obj_id = 7]"; 
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	$test = array(array('name' => "assertEqu('test_math')", 'data' => $tree[0]));
	_uni_assertEqu_offsetSet($test, 0, array('filter' => 
		array('expr1' => array(
			'left' => 1, 
			'left_type' => 'number', 
			'op' => '+', 
			'right' => 'expr2', 
			'right_type' => 'expr', 
			'next' => 'expr3'))));
	
	//print_r($glob->_parseXPath("/objs[1+2*3+4 > @obj_id and @obj_id = 7]")); exit;
	//print_r($glob->_parseXPath("/objs[1+2*3 div 4+4 > @obj_id+4 and @obj_id = 7]")); exit;
	
	$unipath = "/objs[1+2*(3+4)]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(array(array('name' => "assertEqu('test_barckets')", 'data' => $tree[0])), 
		0, 
		array('filter' => array(
		'start_expr' => 'expr3',
		'expr1' => array(
			'left' => 1, 
			'left_type' => 'number', 
			'op' => '+', 
			'right' => 'expr2', 
			'right_type' => 'expr'),
		'expr2' => array(
			'left' => 2,
			'left_type' => 'number',
			'op' => '*',
			'right' => 'expr3',
			'right_type' => 'expr',
			'next' => 'expr1',
			'open_braket' => true
			),
		'expr3' => array(
			'left' => 3,
			'left_type' => 'number',
			'op' => '+',
			'right' => 4,
			'right_type' => 'number',
			'next' => 'expr2',
			'close_braket' => true
		)
		)));
		
	$unipath = "/objs[1+2*(3+4)*5]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
//print_r($tree);
	_uni_assertEqu_offsetSet(array(array('name' => "assertEqu('test_barckets')", 'data' => $tree[0])), 
		0, 
		array('filter' => array(
		'start_expr' => 'expr3',
		'expr1' => array(
			'left' => 1, 
			'left_type' => 'number', 
			'op' => '+', 
			'right' => 'expr4', 
			'right_type' => 'expr'),
		'expr2' => array(
			'left' => 2,
			'left_type' => 'number',
			'op' => '*',
			'right' => 'expr3',
			'right_type' => 'expr',
			'next' => 'expr4',
			'open_braket' => true),
		'expr3' => array(
			'left' => 3,
			'left_type' => 'number',
			'op' => '+',
			'right' => 4,
			'right_type' => 'number',
			'next' => 'expr2',
			'close_braket' => true),
		'expr4' => array(
			'left' => 'expr2',
			'left_type' => 'expr',
			'op' => '*',
			'right' => 5,
			'right_type' => 'number',
			'next' => 'expr1',
		)
		)));
		
	$unipath = "/objs[1*2+3*4*5]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
//print_r($tree);

	$unipath = "/default_Orchard_Framework_ContentItemRecord[ContentType_id=11,14 and Published=1 and (like(Data, N'%abc%') or ContentItemRecord_id = 'abc' or like(Title, N'%abc%'))]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(array(array('name' => "assertEqu('test_barckets')", 'data' => $tree[0])), 
		0, 
		array('filter' => array(
		'start_expr' => 'expr1',
		'expr1' => array(
			'left' => 'ContentType_id', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => array(11,14), 
			'right_type' => 'list_of_numbers',
			'next' => 'expr3'), 
		'expr2' => array(
			'left' => 'expr1', 
			'left_type' => 'expr', 
			'op' => 'and', 
			'right' => 'expr3', 
			'right_type' => 'expr',
			'next' => 'expr6'),
		'expr3' => array(
			'left' => 'Published', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => 1, 
			'right_type' => 'number',
			'next' => 'expr2'),
		'expr4' => array(
			'left' => 'expr2', 
			'left_type' => 'expr', 
			'op' => 'and', 
			'right' => 'expr7', 
			'right_type' => 'expr',
			'next' => null,
			'open_braket' => true),
		'expr5' => array(
			'left' => 'like(Data, N\'%abc%\')', 
			'left_type' => 'function', 
			'op' => 'or', 
			'right' => 'expr6', 
			'right_type' => 'expr',
			'next' => 'expr7'),
		'expr6' => array(
			'left' => 'ContentItemRecord_id', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => 'abc', 
			'right_type' => 'string',
			'next' => 'expr5'),
		'expr7' => array(
			'left' => 'expr5',
			'left_type' => 'expr',
			'op' => 'or',
			'right' => "like(Title, N'%abc%')",
			'right_type' => 'function',
			'next' => 'expr4',
			'close_braket' => true)
		)));
	
	$unipath = "/objs[@name = 'dummy']"; 
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet( 
		array(array('name' => "assertEqu('test_objs_dummy')", 'data' => $tree[0])), 0,
		array('filter' => array(
			'expr1' => array('left' => '@name', 'left_type' => 'name', 'op' => '=', 'right' => 'dummy', 'right_type' => 'string'))));
	

	$unipath = "/objs[@id=basket_order_id()]/order_items[rel_val=1]/asObj()/item_link/asPage()/asObj()/.[@data_type='optioned']";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_6level')", 'data' => $tree[6])), 0,
		array('filter' => array(
		'expr1' => array('left' => '@data_type', 'left_type' => 'name', 'op' => '=', 'right' => 'optioned', 'right_type' => 'string')))
	);

		
	$unipath = "/objs[@name = 'Сочи, Чебрикова 7, кв. 32' and @owner_id = current_user_id()]/@id";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_rus_string')", 'data' => $tree[0]['filter'])), 0,
		array(
		'expr1' => array('left' => '@name', 'left_type' => 'name', 'op' => '=', 
			'right' => "Сочи, Чебрикова 7, кв. 32", 'right_type' => 'string', 'next' => 'expr3'),
		'expr2' => array('left' => 'expr1', 'left_type' => 'expr', 'op' => 'and', 'right' => 'expr3', 'right_type' => 'expr'),
		'expr3' => array('left' => '@owner_id', 'left_type' => 'name', 'op' => '=', 
			'right' => "current_user_id()", 'right_type' => 'function', 'next' => 'expr2')
		));
		
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_rus_string2')", 'data' => $tree[1])), 0,
		array('name' => '@id', 'unipath' => "/objs[@name = 'Сочи, Чебрикова 7, кв. 32' and @owner_id = current_user_id()]/@id")
		);

	
	$unipath = "/objs[@owner_id=10012 and @type_id=24 and status_id<>15 and domain_id=1]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_4and')", 'data' => $tree[0]['filter'])), 0,
		array(
		'expr1' => array('left' => '@owner_id', 'left_type' => 'name', 'op' => '=', 
			'right' => "10012", 'right_type' => 'number', 'next' => 'expr3'),
		'expr2' => array('left' => 'expr1', 'left_type' => 'expr', 'op' => 'and', 
			'right' => "expr3", 'right_type' => 'expr', 'next' => 'expr5'),
		'expr3' => array('left' => '@type_id', 'left_type' => 'name', 'op' => '=', 
			'right' => "24", 'right_type' => 'number', 'next' => 'expr2'),
		'expr4' => array('left' => 'expr2', 'left_type' => 'expr', 'op' => 'and', 
			'right' => "expr5", 'right_type' => 'expr', 'next' => 'expr7'),
		'expr5' => array('left' => 'status_id', 'left_type' => 'name', 'op' => '<>', 
			'right' => "15", 'right_type' => 'number', 'next' => 'expr4'),
		'expr6' => array('left' => 'expr4', 'left_type' => 'expr', 'op' => 'and', 
			'right' => "expr7", 'right_type' => 'expr'),
		'expr7' => array('left' => 'domain_id', 'left_type' => 'name', 'op' => '=', 
			'right' => "1", 'right_type' => 'number', 'next' => 'expr6')));
			
	
	$unipath = "/db1/products[prd_deleted=0 and prd_hidden=0][prd_name/regexp_match('abc') or prd_articul/regexp_match('abc')]/sort(prd_sort_order)";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert('$tree[0]["name"] == "db1"; /* '.$tree[0]["name"].' */');
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_filter2_inner_unipath')", 'data' => $tree[1]['filter2'])),
		0,
		array(
			'expr1' => array('left' => "prd_name/regexp_match('abc')", 'left_type' => "unipath", 'op' => 'or', 'right' => "prd_articul/regexp_match('abc')", 'right_type' => 'unipath')
		));
		
		
	$unipath = "/db1/products[prd_deleted=0 and prd_hidden=0][prd_name/regexp_match('789') 
		or prd_articul/regexp_match('789') 
		or prd_data_json_encoded/asJSON()/descr/ifEmpty('')/regexp_match('789')
		or prd_data_json_encoded/asJSON()/content/ifEmpty('')/regexp_match('789') ]/sort(prd_sort_order)";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_filter2_inner_unipath2')", 'data' => $tree[1]['filter2'])),
		0,
		array(
			'expr1' => array(
				'left' => "prd_name/regexp_match('789')", 
				'left_type' => "unipath", 
				'op' => 'or', 
				'right' => "prd_articul/regexp_match('789')", 
				'right_type' => 'unipath',
				'next' => 'expr2'),
			'expr2' => array(
				'left' => 'expr1',
				'left_type' => "expr",
				'op' => 'or',
				'right' => "prd_data_json_encoded/asJSON()/descr/ifEmpty('')/regexp_match('789')",
				'right_type' => 'unipath',
				'next' => 'expr3'),
			'expr3' => array(
				'left' => 'expr2',
				'left_type' => 'expr',
				'op' => 'or',
				'right' => "prd_data_json_encoded/asJSON()/content/ifEmpty('')/regexp_match('789')",
				'right_type' => 'unipath',
				'next' => null)
		));
		
	$unipath = "db1/alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[like(DisplayAlias, '404notfound%') and Published = 1 and Latest = 1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[urls.Id = vers.Id]/columns('vers.ContentItemRecord_id, urls.DisplayAlias, vers.Id as ver_id')";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
//print_r($tree);
/*	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_cache_to_file')", 'data' => $tree[2])),
		0,
		array(
			'name' => 'cache(file://./unipath_test.json)',
			'unipath' => $unipath
		));*/

	$unipath = '$_POST/cache(file://./unipath_test.json)';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => "assertEqu('test_cache_to_file')", 'data' => $tree[2])),
		0,
		array(
			'name' => 'cache(file://./unipath_test.json)',
			'unipath' => $unipath
		));
		
	$unipath = '/cms3_object_content[@cms3_hierarchy.is_deleted=0 and @field_id=506] + cms3_hierarchy[@cms3_hierarchy.obj_id = @cms3_object_content.obj_id]';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_leftjoin1', 'data' => $tree[0])), 
		0, 
		array(
				'name' => 'cms3_object_content',
				'filter' => array('expr1' => array(
					'left' => '@cms3_hierarchy.is_deleted',
					'left_type' => 'name',
					'op' => '=',
					'right' => 0,
					'right_type' => 'number',
					'next' => 'expr3',
					), 'expr2' => array(
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'next' => null,
					'right' => 'expr3',
					'right_type' => 'expr'
					), 'expr3' => array(
					'left' => '@field_id',
					'left_type' => 'name',
					'op' => '=',
					'next' => 'expr2',
					'right' => 506,
					'right_type' => 'number'
					)),
				'separator_1' => '+',
				'name_1' => 'cms3_hierarchy',
				'filter_1' => array(
                    'expr1' => array(
                    'left' => '@cms3_hierarchy.obj_id',
                    'left_type' => 'name',
                    'op' => '=',
                    'right' => '@cms3_object_content.obj_id',
                    'right_type' => 'name')
                )
			));
	
			//$result = $glob['/cms3_object_content[@cms3_hierarchy.is_deleted=0 and @field_id=506] + cms3_hierarchy[@cms3_hierarchy.obj_id = @cms3_object_content.obj_id]/limit(2)'];
			
			//assert('count($result) == 2') or print_r($result);
	
	$unipath = "/cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))/0";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_func_brackets', 'data' => $tree[1])), 
		0, 
		array(
			'name' => "ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))",
			'unipath' => "/cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))"));
	
	$unipath = "/db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_columns_chunked', 'data' => $tree[2])), 
		0, 
		array('name' => "columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')",
		'unipath' => "/db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')"));
		
	$unipath = "/row_data/newbarea/add(', ')/add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))/remove_end(', ')/remove_start(', ')/ifEmpty('&mdash;')";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[3])), 
		0, 
		array('name' => "add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))",
			'unipath' => "/row_data/newbarea/add(', ')/add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))"));
	
	$unipath = "\$_POST/site_map/Недвижимость/premium";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[3])), 
		0, 
		array('name' => "Недвижимость",
			'unipath' => "\$_POST/site_map/Недвижимость"));
	
	$unipath = "db1/users[u_login = /_POST/login and u_password_hash = /_POST/password]/0";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[2])), 
		0, 
		array('name' => 'users',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'left' => 'u_login',
					'left_type' => 'name',
					'op' => '=',
					'right' => '/_POST/login',
					'right_type' => 'unipath',
					'next' => 'expr3'
				),
				'expr2' => array(
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'next' => null,
					'right' => 'expr3',
					'right_type' => 'expr'
				),
				'expr3' => array(
					'left' => 'u_password_hash',
					'left_type' => 'name',
					'op' => '=',
					'next' => 'expr2',
					'right' => '/_POST/password',
					'right_type' => 'unipath'
				)
			),
			'unipath' => "db1/users[u_login = /_POST/login and u_password_hash = /_POST/password]"
		));
		
	$unipath = 'db1/orders[u_id = /_SESSION/user/u_id and ord_deleted = 0]';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[2])), 
		0, 
		array('name' => 'orders',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'left' => 'u_id',
					'left_type' => 'name',
					'op' => '=',
					'right' => '/_SESSION/user/u_id',
					'right_type' => 'unipath',
					'next' => 'expr3'
				),
				'expr2' => array(
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'right' => 'expr3',
					'right_type' => 'expr',
					'next' => null
				),
				'expr3' => array(
					'left' => 'ord_deleted',
					'left_type' => 'name',
					'op' => '=',
					'right' => '0',
					'right_type' => 'number',
					'next' => 'expr2'
				)
			), 
			'unipath' => "db1/orders[u_id = /_SESSION/user/u_id and ord_deleted = 0]"
		));
		
	$unipath = "/.[1=1 and (DataXML/zemucharea = 'Лазаревский' or DataXML/zemucharea = 'Хостинский') and DataXML/priceuch <= 0]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
//print_r($tree); 
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_brackets_or', 'data' => $tree[0])), 
		0, 
		array('name' => '.',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'left' => 1,
					'left_type' => 'number',
					'op' => '=',
					'right' => 1,
					'right_type' => 'number',
					'next' => 'expr3'
				),
				'expr2' => array(
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'right' => 'expr6',
					'right_type' => 'expr',
					'next' => null,
					'open_braket' => true
				),
				'expr3' => array(
					'left' => 'DataXML/zemucharea',
					'left_type' => 'unipath',
					'op' => '=',
					'right' => 'Лазаревский',
					'right_type' => 'string',
					'next' => 'expr5'
				),
				'expr4' => array(
					'left' => 'expr3',
					'left_type' => 'expr',
					'op' => 'or',
					'right' => 'expr5',
					'right_type' => 'expr',
					'next' => 'expr7'
				),
				'expr5' => array(
					'left' => 'DataXML/zemucharea',
					'left_type' => 'unipath',
					'op' => '=',
					'right' => 'Хостинский',
					'right_type' => 'string',
					'next' => 'expr4',
					'close_braket' => true
				),
				'expr6' => array(
					'left' => 'expr4',
					'left_type' => 'expr',
					'op' => 'and',
					'right' => 'expr7',
					'right_type' => 'expr',
					'next' => 'expr2'
				),
				'expr7' => array(
					'left' => 'DataXML/priceuch',
					'left_type' => 'unipath',
					'op' => '<=',
					'right' => 0,
					'right_type' => 'number',
					'next' => 'expr6'
				)
			)
		)
	);
	
	$unipath = "/.[DataXML/ulstreet='Депутатская','Учительская', \"Лермонтова\", `Пирогова`, 'Грибоедова', 'Дмитриевой', 'Комсомольская', 'Лысая']";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_filter_list_of_strings', 'data' => $tree[0])), 
		0, 
		array('name' => '.', 
			'filter' => array('start_expr' => 'expr1',
			'expr1' => array(
				'left' => 'DataXML/ulstreet',
				'left_type' => 'unipath',
				'op' => '=',
				'right' => array('Депутатская', 'Учительская', 'Лермонтова', 'Пирогова', 'Грибоедова', 'Дмитриевой', 'Комсомольская', 'Лысая'),
				'right_type' => 'list_of_strings')), 
			'unipath' => "/.[DataXML/ulstreet='Депутатская','Учительская', \"Лермонтова\", `Пирогова`, 'Грибоедова', 'Дмитриевой', 'Комсомольская', 'Лысая']")
	);
//print_r($tree);

	$unipath = "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/10597/Pictures/фото 2-1.JPG";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_filepath_with_rusname', 'data' => $tree[7])), 
		0, 
		array('name' => 'фото 2-1.JPG', 
			'unipath' => "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/10597/Pictures/фото 2-1.JPG")
	);
//print_r($tree);

	$unipath = '_SERVER/DOCUMENT_ROOT/asDirectory()/objs/8506/Pictures/image (3)-7.jpg/asImageFile()';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_filepath_with_rusname', 'data' => $tree[7])), 
		0,
		array('name' => 'image (3)-7.jpg', 
			'unipath' => "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/8506/Pictures/image (3)-7.jpg")
	);
//print_r($tree);

	$unipath = '/$_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_ifEmpty', 'data' => $tree[2])), 
		0,
		array('name' => 'ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')', 
			'unipath' => '/$_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')')
	);
// print_r($tree);

	$func_string = "add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_funcargs_regexp_replace', 'data' => $result)), 
		0, 
		array(
			'func_name' => 'add', 
			'arg1' => "row_data/newbadress/regexp_replace('^[0-9]+\.','')",
			'arg1_type' => 'unipath'));
	
	$func_string = "alias('table1', 'tbl1')";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_columns_chunked', 'data' => $result)), 
		0, 
		array('func_name' => 'alias', 'arg1' => 'table1', 'arg2' => 'tbl1'));
	
	$func_string = "chunked('table1.Data',10000,3000)";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_columns_chunked', 'data' => $result)), 
		0, 
		array('func_name' => 'chunked', 'arg1' => 'table1.Data', 'arg2' => '10000', 'arg3' => '3000'));
		
	$func_string = "columns(chunked(table1.Data,10000,3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	_uni_assertEqu_offsetSet(
		array(array('name' => 'parse_columns_chunked', 'data' => $result)), 
		0, 
		array('func_name' => 'columns', 'arg1' => 'chunked(table1.Data,10000,3000)', 'arg2' => "alias('table1.Id', 'id1')", 'arg3' => "table1.ContentType_id, REPLACE('abcd)asd', ')', '(')"));
	
	$func_string = 'join()';
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	assert('$result == array(\'func_name\' => \'join\'); /* '.json_encode($result).' */');
	
	$func_string = 'formatQuantity(`тов\'ар`, `тов"ара`,`тов\'\'ар"""ов`)';
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	assert('$result == array(\'func_name\' => \'formatQuantity\', "arg1" => "тов\'ар", "arg1_type" => "string", "arg2" => "тов\"ара", "arg2_type" => "string", "arg3" => "тов\'\'ар\"\"\"ов", "arg3_type" => "string"); /* '.json_encode($result).' */');
	
	$func_string = "ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=14 and Published=1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1]
	+alias('default_Common_CommonPartRecord', 'comm')[comm.Id = cont.Id]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/columns('vers.ContentItemRecord_id, cont.ContentType_id, urls.DisplayAlias, vers.Id as ver_id, comm.OwnerId', chunked(cont.Data, 2000, 3000, `nvarchar(3000)`), chunked(vers.Data, 21000, 3000, 'nvarchar(3000)'), 'titles.Title')/sql_iconv('UTF-8')/iconv('WINDOWS-1251', 'UTF-8'))";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFunc($func_string);
	assert("\$result == array('func_name' => 'ifEmpty', 'arg1' => \"db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=14 and Published=1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1]
	+alias('default_Common_CommonPartRecord', 'comm')[comm.Id = cont.Id]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/columns('vers.ContentItemRecord_id, cont.ContentType_id, urls.DisplayAlias, vers.Id as ver_id, comm.OwnerId', chunked(cont.Data, 2000, 3000, `nvarchar(3000)`), chunked(vers.Data, 21000, 3000, 'nvarchar(3000)'), 'titles.Title')/sql_iconv('UTF-8')/iconv('WINDOWS-1251', 'UTF-8')\", 'arg1_type' => 'unipath'); /* ".print_r($result, true).' */');
	
/*print_r(__uni_parseUniPath("cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]
	+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1 and Published=1]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/cache(file://./newbilding.json))"));*/
	
	exit;
}

if(isset($_GET['test_uniPath'])) {
	global $uni_cache_data, $uni_cache_data_type, $uni_cache_data_tracking;
	error_reporting(E_ALL); 
	echo '<pre style="white-space:pre-wrap">';

	$unipath = 'test1[a = 1]';
	echo "<h3>--- $unipath ---</h3>";
	$GLOBALS['test1'] = array(
		array('a' => 1),
		array('a' => 2, 'b' => 1),
		array('a' => 1, 'c' => 3)
	);

	$tmp = uni($unipath);
	assert('count($tmp) == 2 /* '.json_encode($tmp).' */');
	_uni_assertEqu_offsetSet(
		array(
			array('name' => "assertEqu('test_var_filter1')", 'data' => $tmp)),
			0,
			array(0 => array('a' => 1), 1 => array('a' => 1, 'c' => 3))
	);
	
//$GLOBALS['unipath_debug'] = true;
	$unipath = '/test1[a = 1]';
	echo "<h3>--- $unipath ---</h3>";
	$tmp = uni($unipath);
	assert('count($tmp) == 2 /* '.json_encode($tmp).' */');
	_uni_assertEqu_offsetSet(
		array(
			array('name' => "assertEqu('test_var_filter1b')", 'data' => $tmp)),
			0,
			array(0 => array('a' => 1), 1 => array('a' => 1, 'c' => 3))
	);

	$unipath = 'test1[a = 0]/toArray()';
	echo "<h3>--- $unipath ---</h3>";
	$tmp = uni($unipath);
	assert('count($tmp) == 0; /* '.json_encode($tmp).' */');
	assert('$tmp == array(); /* '.json_encode($tmp).' */');
//$GLOBALS['unipath_debug'] = true;
	$unipath = '$_POST/file1/name/translit()';
	echo "<h3>--- $unipath ---</h3>";
	$_POST['file1'] = array('name' => 'РСВ-1 (2013 - 2 квартал)(001).ods');
	uni($unipath.'/assertEqu()', 'RSV_1_2013___2_kvartal001.ods');

	$unipath = '$_POST/cache("post_cache1")/abc';
	echo "<h3>--- $unipath ---</h3>";
	$_POST = array('abc' => 123);
	uni($unipath.'/assertEqu()', 123);
	assert('$uni_cache_data["post_cache1"] == array("abc" => 123); /* '.@json_encode($uni_cache_data['post_cache1']).' */'); 
	assert('$uni_cache_data_type["post_cache1"] == "array" /* '.$uni_cache_data_type["post_cache1"].' */');

	$unipath = 'cache("post_cache1")';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('abc' => 123));
//$GLOBALS['unipath_debug'] = false;

//$GLOBALS['unipath_debug'] = true;
	$unipath = 'file://./unipath_test.txt';
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath, '123');
	assert('file_get_contents("unipath_test.txt") == "123" /* '.@file_get_contents("unipath_test.txt").' */');
	uni($unipath.'/assertEqu()', '123');
//$GLOBALS['unipath_debug'] = false;
	
	@unlink('unipath_test.json');
	$unipath = '$_POST/cache(file://./unipath_test.json)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$_POST = array('abc' => 123);
	uni($unipath.'/assertEqu()', array('abc' => 123));
	assert('file_get_contents("unipath_test.json") == \'{"name":"$_POST","unipath":"$_POST","data":{"abc":123},"data_type":"array","data_tracking":"$_POST","cache_timestamp":'.time().'}\' /* '.@file_get_contents("unipath_test.json").' */');

	$unipath = 'file://./file_not_exists.json';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == NULL; /* '.json_encode($result).' */');
	
	$unipath = 'cache(file://./unipath_test.json)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('abc' => 123));
	
	$unipath = '$_POST/cache($_post)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('abc' => 123));
	assert('isset($uni_cache_data["$_post"]) == false; /* '.@json_encode($uni_cache_data['$_post']).' */'); 
	assert('$uni_cache_data_type[\'$_post\'] == "array" /* '.@$uni_cache_data_type['$_post'].' */');
	
	$unipath = 'cache($_post)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('abc' => 123));
	
	$unipath = "\$_POST/replace_in('<h1>abc</h1>
	<h2>abc</h2>')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', '<h1>123</h1>
	<h2>123</h2>');
//$GLOBALS['unipath_debug'] = false;

	$unipath = '/$_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')';
	echo "<h3>--- $unipath ---</h3>";
$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	uni($unipath.'/assertEqu()', '<span class="obj-nophoto">Нет фото</span>');
$GLOBALS['unipath_debug'] = false; echo '</xmp>';

	
	$unipath = '$_POST/abc/formatPrice(\' руб.\')';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', '123 руб.');
	
	$unipath = '$_POST/abc/plus_procents(`900`)/formatPrice(` руб.`, `999>50`)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == '1 250 руб.'; /* ".print_r($result, true).' */');
	
	$unipath = '$_POST/abc/plus_procents(`null`)/formatPrice(` руб.`, `999>50`)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == '123 руб.'; /* ".print_r($result, true).' */');
	
	$_GET['test_str'] = '~/Media/Default/newbilding/mainf/Фотография3-1.jpg';
	$unipath = '$_GET/test_str/regexp_replace(\'^~(\/.*)$\', \'<span class="obj-photo"><img src="$1" alt="" width="80" /></span>\')';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', '<span class="obj-photo"><img src="/Media/Default/newbilding/mainf/Фотография3-1.jpg" alt="" width="80" /></span>');

	$_GET['test_str2'] = '123';
	$unipath = "\$_GET/test_str/add('.jpg')/remove_end('.jpg')/remove_start('~/')/add(\$_GET/test_str2)";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', 'Media/Default/newbilding/mainf/Фотография3-1.jpg123');
	
	
	$_GET['test_str3'] = 'Хостинский, 60 лет ВЛКСМ';
	$unipath = "\$_GET/test_str3/remove_end(', ')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', 'Хостинский, 60 лет ВЛКСМ');
	

	$GLOBALS['db1'] = new PDO('sqlite::memory:');
	$unipath = "db1/alias('table1','tbl1')[id=1]+alias('table2','tbl2')[tbl1.id=tbl2.id]
		+table3
		+table4[tbl1.id=table4.id]/order_by('tbl1.id')/columns('tbl1.*, tbl1.id as id1, tbl2.*, IFNULL(tbl1.name, ''(null)'') as name')/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('query' => 'SELECT tbl1.*, tbl1.id as id1, tbl2.*, IFNULL(tbl1.name, \'(null)\') as name FROM table1 AS tbl1 LEFT JOIN table2 AS tbl2 ON tbl1.id = tbl2.id NATURAL JOIN table3 LEFT JOIN table4 ON tbl1.id = table4.id WHERE id = 1 ORDER BY tbl1.id', 'params' => array()));
	
	$_GET['test_list'] = '~/Media/Default/newbilding/gallery/фото-1.jpg; ~/Media/Default/newbilding/gallery/фото-2.jpg; ;~/Media/Default/newbilding/gallery/фото-3.jpg ';
	$unipath = "\$_GET/test_list/split(';')/trim()/remove_empty()/regexp_replace('^~(\/.*)$', '<span class=\"obj_page-photo-wrp\"><img class=\"obj_page-photo-img\" src=\"$1\" alt=\"\" /></span>')/join(' ')";
	echo "<h3>--- $unipath ---</h3><xmp>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', '<span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-1.jpg" alt="" /></span> <span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-2.jpg" alt="" /></span> <span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-3.jpg" alt="" /></span>');
	echo "</xmp>";
	
	$unipath = "/_GET/split(';')";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result["test_list"] == array("~/Media/Default/newbilding/gallery/фото-1.jpg"," ~/Media/Default/newbilding/gallery/фото-2.jpg"," ","~/Media/Default/newbilding/gallery/фото-3.jpg "); /* '.print_r($result['test_list'], true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), 'table1.Id AS id1, table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('query' => "SELECT SUBSTRING(table1.Data, 1, 3000) AS uni_chunk_0_table1_Data, SUBSTRING(table1.Data, 3001, 3000) AS uni_chunk_1_table1_Data, SUBSTRING(table1.Data, 6001, 3000) AS uni_chunk_2_table1_Data, SUBSTRING(table1.Data, 9001, 3000) AS uni_chunk_3_table1_Data, table1.Id AS id1, table1.ContentType_id, REPLACE('abcd)asd', ')', '(') FROM table1 WHERE id = 1", 'binds' => array()));
	
	$_POST['site_map'] = array('Недвижимость' => array(
	'premium' => array('title' => 'Элитная недвижимость'),
	'newbilding' => array('title' => 'Новостройки', 'cont_type_id' => 11, 'versioned' => true),
	'room' => array('title' => 'Квартиры', 'cont_type_id' => 18, 'versioned' => true),
	'house' => array('title' => 'Коттеджи/Дома', 'cont_type_id' => 15, 'versioned' => true),
	'zemuch' => array('title' => 'Земельные участки', 'cont_type_id' => 14, 'versioned' => true),
	'hotel' => array('title' => 'Гостиницы', 'cont_type_id' => 28, 'versioned' => true),
	'renta' => array('title' => 'Аренда квартир'),
	'office' => array('title' => 'Офисы, Склады, Магазины', 'cont_type_id' => 27, 'versioned' => true),
	'invproject' => array('title' => 'Инвестиционные проекты', 'cont_type_id' => 29, 'versioned' => true),
	'ipoteka' => array('title' => 'Ипотека')), 'Недвижимость1' => array('abc' => 1));
	
	$unipath = "\$_POST/site_map/Недвижимость/premium";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('title' => 'Элитная недвижимость')); 
	
	$GLOBALS['site_map'] = $_POST['site_map'];
	$unipath = "/site_map/Недвижимость/.[cont_type_id = 28]/first()/key()/wrap('/')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', '/hotel/');
//$GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/default_Orchard_Framework_ContentItemRecord[ContentType_id=11,14 and Published=1 and (like(Data, N'%abc%') or ContentItemRecord_id = 'abc' or like(Title, N'%abc%'))]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('query' => "SELECT * FROM default_Orchard_Framework_ContentItemRecord WHERE ContentType_id IN (11,14) and Published = 1 and (Data LIKE N'%abc%' or ContentItemRecord_id = 'abc' or Title LIKE N'%abc%')", 'params' => array()));
	
	$search_query = 'abc123';
	$unipath = "/db1/objects_for_admin[obj_deleted = 0 and (ilike(title, '%{$search_query}%') or ilike(url, '%{$search_query}%') or ilike(city_part, '%{$search_query}%') or ilike(street, '%{$search_query}%'))]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('query' => "SELECT * FROM objects_for_admin WHERE obj_deleted = 0 and (title ILIKE '%{$search_query}%' or url ILIKE '%{$search_query}%' or city_part ILIKE '%{$search_query}%' or street ILIKE '%{$search_query}%')", 'params' => array()));
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "site_map/Недвижимость/*/cont_type_id";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('newbilding' => 11, 'room' => 18, 'house' => 15, 'zemuch' => 14,        'hotel' => 28, 'office' => 27, 'invproject' => 29));
	
	$_POST = array('login' => 'test', 'password' => '123');
	$unipath = "db1/users[u_login = /_POST/login and u_password_hash = /_POST/password]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath.'/assertEqu()', array('query' => "SELECT * FROM users WHERE u_login = 'test' and u_password_hash = '123'", 'params' => array()));
	
    $_POST = array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1);
	$unipath = '$_POST/unset(`password2`)/unset(`submitted`)';
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath.'/assertEqu()', array('u_login' => 'test', 'u_password_hash' => 123));
	
	$GLOBALS['db1']->exec("CREATE TABLE IF NOT EXISTS users (
    u_id INTEGER PRIMARY KEY,
    u_login TEXT NOT NULL,
    u_login2 TEXT NOT NULL DEFAULT '',
    u_password_hash TEXT NOT NULL DEFAULT '',
    s_group TEXT NOT NULL DEFAULT 'funbit',
    u_banned INTEGER NOT NULL DEFAULT 0,
    u_created TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    u_modified TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    u_deleted INTEGER NOT NULL DEFAULT 0,
    u_data_json_encoded TEXT NOT NULL DEFAULT 'null')");
    $unipath = '$_POST/unset(`password2`)/unset(`submitted`)/insert_into(db1/users)';
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath.'/assertEqu()', 1); 
	
	$unipath = "/_POST/u_login/php_intval()";
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath.'/assertEqu()', 0);
	
	$unipath = "/_POST/.[1]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	uni($unipath.'/assertEqu()', array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1));
// echo '<xmp>';

	$unipath = "/_POST/.[. > 100 and . < 124]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("u_password_hash" => 123, "password2" => "123"); /* '.json_encode($result).' */');
// echo '<xmp>';


	$_POST[] = array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 0));
	$_POST[] = array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 123));
	$unipath = "/_POST/.[1=1 and (DataXML/zemucharea = 'Лазаревский' or DataXML/zemucharea = 'Хостинский') and DataXML/priceuch <= 0]";
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath.'/assertEqu()', array(array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 0))));

// $GLOBALS['unipath_debug'] = true;
	$_POST = array(array('vers.Data' => '<Data><zemuch><a>123</a></zemuch></Data>'), array('vers.Data' => '<Data><zemuch><a>321</a></zemuch></Data>'));
	$unipath = "/_POST/let(*/DataXML, */vers.Data/XMLtoArray()/Data/zemuch)";
	echo "<h3>--- $unipath ---</h3><xmp>";
// print_r(uni($unipath));
	uni($unipath.'/assertEqu()', array(
		array('vers.Data' => '<Data><zemuch><a>123</a></zemuch></Data>', 'DataXML' => array('a' => 123)), 
		array('vers.Data' => '<Data><zemuch><a>321</a></zemuch></Data>', 'DataXML' => array('a' => 321))
		)
	);
echo '</xmp>';

//$GLOBALS['unipath_debug'] = true;
	$unipath = "/_POST/0/vers.Data/asXML()/Data/0/zemuch/0";
	echo "<h3>--- $unipath ---</h3>";
//echo '<xmp>';
	$result = uni($unipath);
// print_r($result);
	assert('$result == "<a>123</a>"; /* '.print_r($result, true).' */');
//echo '</xmp>';
//unset($GLOBALS['unipath_debug']);

	$_POST['test_xml'] = '<?xml version="1.0" encoding="UTF-8"?><html>
		<head><title>test_xml</title></head>
		<body>
			<h1>H1</h1>
			<p>paragraph 1</p>
			<h1>H1 second</h1>
			<p>paragraph 2</p>
		</body></html>';
	$unipath = '/_POST/test_xml/asXML()/html/0/head/0/title/0';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "test_xml"; /* '.json_encode($result).' */');
//$GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$_POST['test_xml'] = '<text:h text:style-name="Heading_20_1" outline-level=1>3-комн квартира на ул Труда</text:h><text:p text:style-name="Text_20_body">Улица: Труда</text:p><text:p text:style-name="Text_20_body">Общая площадь: 85 м² </text:p><text:p text:style-name="Text_20_body">Этажность: 12 </text:p><text:p text:style-name="Text_20_body">Этаж: 6 </text:p><text:p text:style-name="Text_20_body">Цена: <text:span text:style-name="T1">5800000</text:span> руб. </text:p><text:p text:style-name="Standard"/><table:table table:name="ХарактеристикиОбъекта" table:style-name="ХарактеристикиОбъекта"><table:table-column table:style-name="ХарактеристикиОбъекта.A"/><table:table-column table:style-name="ХарактеристикиОбъекта.B"/><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell 
	office:value-type="string"><text:p text:style-name="P1">Улица</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Театральная 11</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Класс</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">премиум</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">9</text:p></table:table-cell></table:table-row></table:table><text:p/>';
	$unipath = '/_POST/test_xml/asXML()/h/0';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "3-комн квартира на ул Труда"; /* '.json_encode($result).' */');
//$GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = '/_POST/test_xml/asXML()/p/4';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "Цена: <text:span text:style-name=\"T1\">5800000</text:span> руб. "; /* '.print_r($result, true).' */');
//$GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = "/_POST/test_xml/asXML()/*/.[key()='h','table']";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
echo '<xmp>';
	$result = uni($unipath);
// print_r($result);
	assert('$result == array("3-комн квартира на ул Труда", 7 => \'<table:table-column table:style-name="ХарактеристикиОбъекта.A"/><table:table-column table:style-name="ХарактеристикиОбъекта.B"/><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell 
	office:value-type="string"><text:p text:style-name="P1">Улица</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Театральная 11</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Класс</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">премиум</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">9</text:p></table:table-cell></table:table-row>\'); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; 
echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	@mkdir('unipath_test');
	$GLOBALS['dirname'] = 'unipath_test';
	file_put_contents('unipath_test/unipath_test.xml', $_POST['test_xml']);
	$unipath = '$dirname/asDirectory()/unipath_test.xml/contents()';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == \$_POST['test_xml']; /* ".json_encode($result)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$GLOBALS['filename'] = 'unipath_test.xml';
	file_put_contents('unipath_test.xml', $_POST['test_xml']);
	$unipath = '$filename/asFile()/contents()/asXML()/cache($odt)';
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath);
	assert("\$GLOBALS['odt'] == \$_POST['test_xml']; /* ".json_encode($GLOBALS['odt'])." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = 'cache($odt)/p';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == array("Улица: Труда", "Общая площадь: 85 м² ", "Этажность: 12 ", "Этаж: 6 ", "Цена: <text:span text:style-name=\\"T1\\">5800000</text:span> руб. ", "", ""); /* '.print_r($result, true).' */');
	//print_r($result);
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = 'cache($odt)/table/0/table-row/toArray()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
echo '<xmp>';
	$result = uni($unipath.'/assertEqu()', array('<table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell>', 
	'<table:table-cell 
	office:value-type="string"><text:p text:style-name="P1">Улица</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Театральная 11</text:p></table:table-cell>', 
'<table:table-cell office:value-type="string"><text:p text:style-name="P1">Класс</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">премиум</text:p></table:table-cell>', 
'<table:table-cell office:value-type="string"><text:p text:style-name="P1">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">9</text:p></table:table-cell>'));
// $GLOBALS['unipath_debug'] = false; 
echo '</xmp>';

	$unipath = 'cache($odt)/table/0/*/toHash()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
echo '<xmp>';
	$result = uni($unipath.'/assertEqu()', array('table-column' => array('', ''), 'table-row' => array('<table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell>', 
	'<table:table-cell 
	office:value-type="string"><text:p text:style-name="P1">Улица</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Театральная 11</text:p></table:table-cell>', 
'<table:table-cell office:value-type="string"><text:p text:style-name="P1">Класс</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">премиум</text:p></table:table-cell>', 
'<table:table-cell office:value-type="string"><text:p text:style-name="P1">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">9</text:p></table:table-cell>')));
// $GLOBALS['unipath_debug'] = false; 
echo '</xmp>';

	$unipath = 'cache($odt)/h/0/attrs()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
	assert('$result == array("style-name"=>"Heading_20_1", "outline-level"=>"1"); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = 'cache($odt)/*';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('count($result) == 9; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$_POST['test_xml'] = '<Data><newbilding><newbfotogal>/Media/Default/newbilding/gallery/DSC02130.jpg;/Media/Default/newbilding/gallery/DSC02129.jpg;/Media/Default/newbilding/gallery/DSC02132.jpg;/Media/Default/newbilding/gallery/DSC02164.jpg;/Media/Default/newbilding/gallery/DSC02135.jpg;/Media/Default/newbilding/gallery/DSC02134.jpg;/Media/Default/newbilding/gallery/DSC02156.jpg;/Media/Default/newbilding/gallery/DSC02165.jpg;/Media/Default/newbilding/gallery/DSC02166.jpg</newbfotogal><mainfnewb Width="200" Height="150" AlternateText="">~/Media/Default/newbilding/mainf/DSC02130-1.JPG</mainfnewb><ulstreet>Молодогвардейцев</ulstreet><minsq>33</minsq><maxsq>100</maxsq><koletajn>5</koletajn><maxmetr>70000</maxmetr><minimetr>48000</minimetr><minprroom>1695000</minprroom><sedestnewb>1</sedestnewb><newbdate>2013</newbdate><newtehhor>;;автономное отопление;</newtehhor><infranewb>;;Аптека;Магазин;Школа;</infranewb><newbarea>Центральный</newbarea><newbklass>эконом</newbklass></newbilding></Data>';
	$unipath = '/_POST/test_xml/asXML()/Data/key()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
	assert('$result == "Data"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';


// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '/_POST/123/ArrayToXML()';
	$_POST[123] = array('Data' => array('newbilding' => array(
		'mainfnewb' => array('~/Media/Default/newbilding/mainf/SAM_1591-5.JPG',
			'attrs()' => array('Width' => 200, "Height" => "150", "AlternateText" => "SAM_1591.JPG")),
		'newbfotogal' => '/Media/Default/newbilding/gallery/SAM_1591-1.jpg'
		)));
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == \'<Data><newbilding><mainfnewb Width="200" Height="150" AlternateText="SAM_1591.JPG">~/Media/Default/newbilding/mainf/SAM_1591-5.JPG</mainfnewb><newbfotogal>/Media/Default/newbilding/gallery/SAM_1591-1.jpg</newbfotogal></newbilding></Data>\'; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';
	
	$unipath = '/_POST/123';
	unset($_POST[123]);
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath, 321);
	assert('$_POST[123] == 321; /* '.json_encode($_POST).' */');
	
	$unipath = '/_POST/favorites/123';
	unset($_POST['favorites']);
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath, array('name' => 'test'));
	assert('$_POST["favorites"][123] == array("name" => "test"); /* '.json_encode($_POST).' */');
	
	$unipath = '_POST/favorites/123';
	unset($_POST['favorites']);
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath, array('name' => 'test2'));
	assert('$_POST["favorites"][123] == array("name" => "test2"); /* '.json_encode($_POST).' */');

	$unipath = '_POST/favorites/333';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('is_null($result); /* '.json_encode($result).' */');	
	
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '_POST/favorites/ArrayToXML()';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == "<name>test2</name>"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = '/_POST/.[key() = `favorites`]/ArrayToXML()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
	assert('$result == "<favorites><name>test2</name></favorites>"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '_POST/favorites/123/name/prepend(`name = `)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == "name = test2"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = "url(`http://{$_SERVER['HTTP_HOST']}/unipath_test.php?echo=abc123`)";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == 'http://{$_SERVER['HTTP_HOST']}/unipath_test.php?echo=abc123'; /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = "url(`http://{$_SERVER['HTTP_HOST']}/".basename(__FILE__)."?echo=abc123`)/contents()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == 'abc123'; /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';
}