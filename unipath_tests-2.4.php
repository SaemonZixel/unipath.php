<?php
// phpinfo(); exit;
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);

assert_options(ASSERT_ACTIVE, 1);
@ini_set('zend.assertions', 1);
if(ini_get('zend.assertions') === false) trigger_error('Can`t check zend.assertions flag!', E_USER_NOTICE);
elseif(ini_get('zend.assertions') != 1) die('Error! Set zend.assertions=1 in php.ini! All tests skiped.');

if(!empty($_GET['echo']))
	die($_GET['echo']);

// весь вывод, сразу отдаём в броузер (отлючение буферизации вывода)
if(empty($GLOBALS['test_mode'])) { 
	while(@ob_end_flush());
	ob_implicit_flush(true);
}

echo '<!doctype html>
<html><head><title>'.basename(__FILE__).'</title></head><body>
<div style="position:fixed;top:1px;right:1px;background:rgba(255,255,255,0.8);">Test: 
<a href="'.basename(__FILE__).'?test_parseUniPath=1">parser</a> 
<a href="'.basename(__FILE__).'?test_uniPath=1">unipath</a> 
<a href="'.basename(__FILE__).'?test_uniExtensions=1">extensions</a>
</div><pre style="white-space:pre-wrap">';
	
require_once 'unipath-2.4.php';

$__uni_prt_cnt = 500; // для тестов хватит
$__test_our_url = 'http://'. $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, -strlen($_SERVER['QUERY_STRING'])-1);

function _cu1rsor_assertEqu($tree, $lv = 0, $cursor_cmd = null, $cursor_arg1 = null) {
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

// -------------- TESTS ------------------------------

if(isset($_GET['test_parseUniPath'])) {

	$unipath = "/objs[1+2*3 > @obj_id and @obj_id = 7]"; 
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);

	assert("\$tree[1]['filter'] == 
		array(
		'start_expr' => 'expr2',
		'expr1' => array(
			'left' => 1, 
			'left_type' => 'number', 
			'op' => '+', 
			'right' => 'expr2', 
			'right_type' => 'expr', 
			'next' => 'expr3',
			'braket_level' => 0),
		'expr2' => array(
			'left' => 2,
			'left_type' => 'number',
			'op' => '*',
			'next' => 'expr1',
			'right' => 3,
			'right_type' => 'number',
			'braket_level' => 0
		),
		'expr3' => array(
			'left' => 'expr1',
			'left_type' => 'expr',
			'op' => '>',
			'next' => 'expr5',
			'right' => '@obj_id',
			'right_type' => 'name',
			'braket_level' => 0
		),
		'expr4' => array(
			'left' => 'expr3',
			'left_type' => 'expr',
			'op' => 'and',
			'next' => null,
			'right' => 'expr5',
			'right_type' => 'expr',
			'braket_level' => 0
		),
		'expr5' => array(
			'left' => '@obj_id',
			'left_type' => 'name',
			'op' => '=',
			'next' => 'expr4',
			'right' => '7',
			'right_type' => 'number',
			'braket_level' => 0
		)); /* ".print_r($tree[1]['filter'], true)." */");
	assert("\$tree[1]['filter']['expr1'] == array(
			'left' => 1, 
			'left_type' => 'number', 
			'op' => '+', 
			'right' => 'expr2', 
			'right_type' => 'expr', 
			'next' => 'expr3', 'braket_level' => 0); /* ".print_r($tree[1]['filter']['expr1'], true)." */");
	assert("\$tree[1]['filter']['expr2'] == array(
			'left' => 2,
			'left_type' => 'number',
			'op' => '*',
			'next' => 'expr1',
			'right' => 3,
			'right_type' => 'number', 'braket_level' => 0); /* ".print_r($tree[1]['filter']['expr2'], true)." */");
	assert("\$tree[1]['filter']['expr3'] == array(
			'left' => 'expr1',
			'left_type' => 'expr',
			'op' => '>',
			'next' => 'expr5',
			'right' => '@obj_id',
			'right_type' => 'name', 'braket_level' => 0); /* ".print_r($tree[1]['filter']['expr3'], true)." */");
	assert("\$tree[1]['filter']['expr4'] == array(
			'left' => 'expr3',
			'left_type' => 'expr',
			'op' => 'and',
			'next' => null,
			'right' => 'expr5',
			'right_type' => 'expr', 'braket_level' => 0); /* ".print_r($tree[1]['filter']['expr4'], true)." */");
	assert("\$tree[1]['filter']['expr5'] == array(
			'left' => '@obj_id',
			'left_type' => 'name',
			'op' => '=',
			'next' => 'expr4',
			'right' => '7',
			'right_type' => 'number', 'braket_level' => 0); /* ".print_r($tree[1]['filter']['expr5'], true)." */");
			
	//print_r($glob->_parseXPath("/objs[1+2*3+4 > @obj_id and @obj_id = 7]")); exit;
	//print_r($glob->_parseXPath("/objs[1+2*3 div 4+4 > @obj_id+4 and @obj_id = 7]")); exit;
	
	$unipath = "/objs[1+2*(3+4)]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug_parse'] = false;
	assert("\$tree[1]['filter'] == array(
		'start_expr' => 'expr3',
		'expr1' => array(
			'braket_level' => 0,
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
			'braket_level' => 0
			),
		'expr3' => array(
			'left' => 3,
			'left_type' => 'number',
			'op' => '+',
			'right' => 4,
			'right_type' => 'number',
			'next' => 'expr2',
			'braket_level' => 1
		)) /* ".print_r($tree[1]['filter'], true)." */");
		
	$unipath = "/objs[1+2*(3+4)*5]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug_parse'] = false;

	assert("\$tree[1]['filter'] == array (
		'start_expr' => 'expr3',
		'expr1' => array (
			'braket_level' => 0,
			'left' => '1',
			'left_type' => 'number',
			'op' => '+',
			'right' => 'expr2',
			'right_type' => 'expr',
		),
		'expr2' => array (
			'left' => '2',
			'left_type' => 'number',
			'op' => '*',
			'next' => 'expr1',
			'braket_level' => 0,
			'right' => 'expr3',
			'right_type' => 'expr',
		),
		'expr3' => array (
			'left' => '3',
			'left_type' => 'number',
			'op' => '+',
			'next' => 'expr4',
			'braket_level' => 1,
			'right' => '4',
			'right_type' => 'number',
		),
		'expr4' => array (
			'left' => 'expr3',
			'left_type' => 'expr',
			'op' => '*',
			'next' => 'expr2',
			'braket_level' => 0,
			'right' => '5',
			'right_type' => 'number',
		),
	) /* ".print_r($tree[1]['filter'], true)." */");
		
	$unipath = "/objs[1*2+3*4*5]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[1]['filter'] == array (
		'start_expr' => 'expr1',
		'expr1' => array (
			'braket_level' => 0,
			'left' => '1',
			'left_type' => 'number',
			'op' => '*',
			'right' => '2',
			'right_type' => 'number',
			'next' => 'expr3',
		),
		'expr2' => array (
			'left' => 'expr1',
			'left_type' => 'expr',
			'op' => '+',
			'next' => NULL,
			'braket_level' => 0,
			'right' => 'expr4',
			'right_type' => 'expr',
		),
		'expr3' => array (
			'left' => '3',
			'left_type' => 'number',
			'op' => '*',
			'next' => 'expr4',
			'braket_level' => 0,
			'right' => '4',
			'right_type' => 'number',
		),
		'expr4' => array (
			'left' => 'expr3',
			'left_type' => 'expr',
			'op' => '*',
			'next' => 'expr2',
			'braket_level' => 0,
			'right' => '5',
			'right_type' => 'number',
		),
		) /* ".print_r($tree[1]['filter'], true)." */");

	$unipath = "/default_Orchard_Framework_ContentItemRecord[ContentType_id=11,14 and Published=1 and (like(Data, N'%abc%') or ContentItemRecord_id = 'abc' or like(Title, N'%abc%'))]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug_parse'] = false;
// print_r($tree[1]);
	assert("\$tree[1] == array(
	'name' => 'default_Orchard_Framework_ContentItemRecord',
	'filter' => array(
		'start_expr' => 'expr1',
		'expr1' => array(
			'braket_level' => 0,
			'left' => 'ContentType_id', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => array(11,14), 
			'right_type' => 'list-of-number',
			'next' => 'expr3'), 
		'expr2' => array(
			'braket_level' => 0,
			'left' => 'expr1', 
			'left_type' => 'expr', 
			'op' => 'and', 
			'right' => 'expr3', 
			'right_type' => 'expr',
			'next' => 'expr5'),
		'expr3' => array(
			'braket_level' => 0,
			'left' => 'Published', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => 1, 
			'right_type' => 'number',
			'next' => 'expr2'),
		'expr4' => array(
			'braket_level' => 0,
			'left' => 'expr2', 
			'left_type' => 'expr', 
			'op' => 'and', 
			'right' => 'expr8', 
			'right_type' => 'expr',
			'next' => null),
		'expr5' => array(
			'braket_level' => 1,
			'left' => \"like(Data, N'%abc%')\", 
			'left_type' => 'function', 
			'op' => 'left_eval',
			'next' => 'expr7',
		),
		'expr6' => array(
			'braket_level' => 1,
			'left' => 'expr5', 
			'left_type' => 'expr', 
			'op' => 'or', 
			'right' => 'expr7', 
			'right_type' => 'expr',
			'next' => 'expr8'),
		'expr7' => array(
			'braket_level' => 1,
			'left' => 'ContentItemRecord_id', 
			'left_type' => 'name', 
			'op' => '=', 
			'right' => 'abc', 
			'right_type' => 'string',
			'next' => 'expr6'),
		'expr8' => array(
			'braket_level' => 1,
			'left' => 'expr6',
			'left_type' => 'expr',
			'op' => 'or',
			'right' => \"like(Title, N'%abc%')\",
			'right_type' => 'function',
			'next' => 'expr4')
		),
	'unipath' => \"/default_Orchard_Framework_ContentItemRecord[ContentType_id=11,14 and Published=1 and (like(Data, N'%abc%') or ContentItemRecord_id = 'abc' or like(Title, N'%abc%'))]\"); /* ".print_r($tree[1], true)." */");
	
	$unipath = "/objs[@name = 'dummy']"; 
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[1]['filter'] == array(
			'start_expr' => 'expr1',
			'expr1' => array('left' => '@name', 'left_type' => 'name', 'op' => '=', 'right' => 'dummy', 'right_type' => 'string', 'braket_level' => 0)); /* ".print_r($tree[1]['filter'], true).'*/ ');
	

	$unipath = "/objs[@id=basket_order_id()]/order_items[rel_val=1]/asObj()/item_link/asPage()/asObj()/.[@data_type='optioned']";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[7]['filter'] == array(
			'start_expr' => 'expr1',
			'expr1' => array('left' => 
				'@data_type', 
				'left_type' => 'name', 
				'op' => '=', 
				'right' => 'optioned', 
				'right_type' => 'string',
				'braket_level' => 0
			)); /* ".print_r($tree[7]['filter'], true).'*/ ');

		
	$unipath = "/objs[@name = 'Сочи, Чебрикова 7, кв. 32' and @owner_id = current_user_id()]/@id";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_rus_string')", 'data' => $tree[1]['filter'])), 0, 'set',
		array(
		'expr1' => array('left' => '@name', 'left_type' => 'name', 'op' => '=', 
			'right' => "Сочи, Чебрикова 7, кв. 32", 'right_type' => 'string', 'next' => 'expr3'),
		'expr2' => array('left' => 'expr1', 'left_type' => 'expr', 'op' => 'and', 'right' => 'expr3', 'right_type' => 'expr'),
		'expr3' => array('left' => '@owner_id', 'left_type' => 'name', 'op' => '=', 
			'right' => "current_user_id()", 'right_type' => 'function', 'next' => 'expr2')
		));
		
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_rus_string2')", 'data' => $tree[2])), 0, 'set',
		array('name' => '@id', 'unipath' => "/objs[@name = 'Сочи, Чебрикова 7, кв. 32' and @owner_id = current_user_id()]/@id")
		);

	
	$unipath = "/objs[@owner_id=10012 and @type_id=24 and status_id<>15 and domain_id=1]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_4and')", 'data' => $tree[1]['filter'])), 0,'set',
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
	assert('$tree[1]["name"] == "db1"; /* '.$tree[1]["name"].' */');
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_filter2_inner_unipath')", 'data' => $tree[2]['filter2'])),
		0, 'set',
		array(
			'expr1' => array('left' => "prd_name/regexp_match('abc')", 'left_type' => "unipath", 'op' => 'or', 'right' => "prd_articul/regexp_match('abc')", 'right_type' => 'unipath')
		));
		
		
	$unipath = "/db1/products[prd_deleted=0 and prd_hidden=0][prd_name/regexp_match('789') 
		or prd_articul/regexp_match('789') 
		or prd_data_json_encoded/asJSON()/descr/ifEmpty('')/regexp_match('789')
		or prd_data_json_encoded/asJSON()/content/ifEmpty('')/regexp_match('789') ]/sort(prd_sort_order)";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_filter2_inner_unipath2')", 'data' => $tree[2]['filter2'])),
		0, 'set',
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
		
	$unipath = '/db1/servicesimages[item_id=1 , 2
	, 3 AND service_id=5]
		+images[image_id=id]
		/columns(`servicesimages.item_id AS pn_id, image_id, name, type, weight`)
		/limit(2)/all()';
	echo "<h3>--- $unipath ---</h3>";
// 	$GLOBALS['unipath_debug'] = true;
// 	$GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug'] = false;
// 	$GLOBALS['unipath_debug_parse'] = false;
// print_r($tree[2]["filter"]);
	assert('$tree[2]["filter"] == array(
    "start_expr" => "expr1",
    "expr1" => array(
			"braket_level" => 0,
            "left" => "item_id",
            "left_type" => "name",
            "op" => "=",
            "right" => array(1, 2, 3),
            "right_type" => "list-of-number",
            "next" => "expr3"
        ),
    "expr2" => array(
			"braket_level" => 0,
            "left" => "expr1",
            "left_type" => "expr",
            "op" => "and",
            "next" => null,
            "right" => "expr3",
            "right_type" => "expr"
        ),
    "expr3" => array(
			"braket_level" => 0,
            "left" => "service_id",
            "left_type" => "name",
            "op" => "=",
            "next" => "expr2",
            "right" => "5",
            "right_type" => "number"
        ));') or print_r($tree[2]["filter"]);
        
	$unipath = '/servicesimages[item_id=/barray/items/*/pn_id AND service_id=5]';
	echo "<h3>--- $unipath ---</h3>";
// 	$GLOBALS['unipath_debug'] = true;
// 	$GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug'] = false;
// 	$GLOBALS['unipath_debug_parse'] = false;
// print_r($tree[1]["filter"]);
	assert('$tree[1]["filter"]["expr1"] == array(
			"braket_level" => 0,
            "left" => "item_id",
            "left_type" => "name",
            "op" => "=",
            "right" => "/barray/items/*/pn_id",
            "right_type" => "unipath",
            "next" => "expr3"
        ); /* '.strtr(print_r($tree[1]["filter"]["expr1"], true), array("*/" => "*_/")).' */');
		
	$unipath = "db1/alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[like(DisplayAlias, '404notfound%') and Published = 1 and Latest = 1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[urls.Id = vers.Id]/columns('vers.ContentItemRecord_id, urls.DisplayAlias, vers.Id as ver_id')";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug'] = false;
//print_r($tree);
/*	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_cache_to_file')", 'data' => $tree[2])),
		0, 'set',
		array(
			'name' => 'cache(file://./unipath_test.json)',
			'unipath' => $unipath
		));*/

	$unipath = '$_POST/cache(file://./unipath_test.json)';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => "assertEqu('test_cache_to_file')", 'data' => $tree[2])),
		0, 'set',
		array(
			'name' => 'cache(file://./unipath_test.json)',
			'unipath' => '/_POST/cache(file://./unipath_test.json)'
		));
		
	$unipath = '/cms3_object_content[@cms3_hierarchy.is_deleted=0 and @field_id=506] + alias(cms3_hierarchy,\'table1\')[@cms3_hierarchy.obj_id = @cms3_object_content.obj_id]';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert('$tree[1] == array("name" => "cms3_object_content",
            "filter" => Array
                (
                    "start_expr" => "expr1",
                    "expr1" => Array
                        (
                            "braket_level" => 0,
                            "left" => "@cms3_hierarchy.is_deleted",
                            "left_type" => "name",
                            "op" => "=",
                            "right" => 0,
                            "right_type" => "number",
                            "next" => "expr3"
                        ),

                    "expr2" => Array
                        (
                            "braket_level" => 0,
                            "left" => "expr1",
                            "left_type" => "expr",
                            "op" => "and",
                            "next" => null,
                            "right" => "expr3",
                            "right_type" => "expr"
                        ),
                    "expr3" => Array
                        (
                            "braket_level" => 0,
                            "left" => "@field_id",
                            "left_type" => "name",
                            "op" => "=",
                            "next" => "expr2",
                            "right" => 506,
                            "right_type" => "number"
                        )
                ),
            "unipath" => "/cms3_object_content[@cms3_hierarchy.is_deleted=0 and @field_id=506] "); /* /* '.print_r($tree[1], true)." */");
	assert("\$tree[2] == array (
		'name' => '+alias(cms3_hierarchy,\\'table1\\')',
		'filter' => 
		array (
			'start_expr' => 'expr1',
			'expr1' => 
			array (
            'braket_level' => 0,
			'left' => '@cms3_hierarchy.obj_id',
			'left_type' => 'name',
			'op' => '=',
			'right' => '@cms3_object_content.obj_id',
			'right_type' => 'name',
			),
		),
		'unipath' => '/cms3_object_content[@cms3_hierarchy.is_deleted=0 and @field_id=506] + alias(cms3_hierarchy,\\'table1\\')[@cms3_hierarchy.obj_id = @cms3_object_content.obj_id]',
		); /* ".print_r($tree[2], true)." */");
	assert('count($tree) == 3') or print_r($result);
	
	$unipath = "db1/alias('table1','tbl1')[id=1]+alias('table2','tbl2')[tbl1.id=tbl2.id]
		+table3
		+table4[tbl1.id=table4.id]
		+table5-as-t5_abc[t5_abc.id = table1.id]/order_by('tbl1.id')/columns(\"tbl1.*, tbl1.id as id1, tbl2.*, IFNULL(tbl1.name, '(null)') as name\")/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[2] == array (
  'name' => 'alias(\'table1\',\'tbl1\')',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
      'braket_level' => 0,
      'left' => 'id',
      'left_type' => 'name',
      'op' => '=',
      'right' => '1',
      'right_type' => 'number',
    ),
  ),
  'unipath' => 'db1/alias(\'table1\',\'tbl1\')[id=1]',
); /* ".print_r($tree[2], true)." */");
	assert("\$tree[3] == array (
  'name' => '+alias(\\'table2\\',\\'tbl2\\')',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
      'braket_level' => 0,
      'left' => 'tbl1.id',
      'left_type' => 'name',
      'op' => '=',
      'right' => 'tbl2.id',
      'right_type' => 'name',
    ),
  ),
  'unipath' => 'db1/alias(\\'table1\\',\\'tbl1\\')[id=1]+alias(\\'table2\\',\\'tbl2\\')[tbl1.id=tbl2.id]
		',
); /* ".print_r($tree[3], true)." */");
	assert("\$tree[4] == array (
  'name' => '+table3',
  'unipath' => 'db1/alias(\\'table1\\',\\'tbl1\\')[id=1]+alias(\\'table2\\',\\'tbl2\\')[tbl1.id=tbl2.id]
		+table3
		',
); /* ".print_r($tree[4], true)." */");
	assert("\$tree[5] == array (
  'name' => '+table4',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
      'braket_level' => 0,
      'left' => 'tbl1.id',
      'left_type' => 'name',
      'op' => '=',
      'right' => 'table4.id',
      'right_type' => 'name',
    ),
  ),
  'unipath' => 'db1/alias(\\'table1\\',\\'tbl1\\')[id=1]+alias(\\'table2\\',\\'tbl2\\')[tbl1.id=tbl2.id]
		+table3
		+table4[tbl1.id=table4.id]\n\t\t',
); /* ".print_r($tree[5], true)." */");
	assert("\$tree[6] == array (
  'name' => '+table5-as-t5_abc',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
      'braket_level' => 0,
      'left' => 't5_abc.id',
      'left_type' => 'name',
      'op' => '=',
      'right' => 'table1.id',
      'right_type' => 'name',
    ),
  ),
  'unipath' => 'db1/alias(\\'table1\\',\\'tbl1\\')[id=1]+alias(\\'table2\\',\\'tbl2\\')[tbl1.id=tbl2.id]
		+table3
		+table4[tbl1.id=table4.id]
		+table5-as-t5_abc[t5_abc.id = table1.id]',
); /* ".print_r($tree[6], true)." */");


	$unipath = "/cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))/0";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_func_brackets', 'data' => $tree[2])), 
		0, 'set',
		array(
			'name' => "ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))",
			'unipath' => "/cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]/cahe(file://./newbilding.json))"));
	
	$unipath = "/db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[3] == array('name' => \"columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')\",
		'unipath' => \"/db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')\"); /* ".print_r($tree[3], true).' */');
		
	$unipath = "/row_data/newbarea/add(', ')/add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))/remove_end(', ')/remove_start(', ')/ifEmpty('&mdash;')";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[4])), 
		0, 'set',
		array('name' => "add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))",
			'unipath' => "/row_data/newbarea/add(', ')/add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))"));
	
	$unipath = "\$_POST/site_map/Недвижимость/premium";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[3])), 
		0, 'set',
		array('name' => "Недвижимость",
			'unipath' => "/_POST/site_map/Недвижимость"));
	
	$unipath = "db1/users[u_login = /_POST/login and u_password_hash = /_POST/password]/0";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_inner_regexp_replace', 'data' => $tree[2])), 
		0, 'set',
		array('name' => 'users',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'braket_level' => 0,
					'left' => 'u_login',
					'left_type' => 'name',
					'op' => '=',
					'right' => '/_POST/login',
					'right_type' => 'unipath',
					'next' => 'expr3'
				),
				'expr2' => array(
					'braket_level' => 0,
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'next' => null,
					'right' => 'expr3',
					'right_type' => 'expr'
				),
				'expr3' => array(
					'braket_level' => 0,
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
	assert("\$tree[2] == array('name' => 'orders',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'braket_level' => 0,
					'left' => 'u_id',
					'left_type' => 'name',
					'op' => '=',
					'right' => '/_SESSION/user/u_id',
					'right_type' => 'unipath',
					'next' => 'expr3'
				),
				'expr2' => array(
					'braket_level' => 0,
					'left' => 'expr1',
					'left_type' => 'expr',
					'op' => 'and',
					'right' => 'expr3',
					'right_type' => 'expr',
					'next' => null
				),
				'expr3' => array(
					'braket_level' => 0,
					'left' => 'ord_deleted',
					'left_type' => 'name',
					'op' => '=',
					'right' => '0',
					'right_type' => 'number',
					'next' => 'expr2'
				)
			), 
			'unipath' => \"db1/orders[u_id = /_SESSION/user/u_id and ord_deleted = 0]\"
		); /* ".print_r($tree[2], true)." */");
		
	$unipath = "/db1/table1[id = -123]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[2] == array('name' => 'table1',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'braket_level' => 0,
					'left' => 'id',
					'left_type' => 'name',
					'op' => '=',
					'right' => -123,
					'right_type' => 'number',
				)),
			'unipath' => '/db1/table1[id = -123]'); /* ".print_r($tree[2], true)." */");
	
	$unipath = "/db1/table1[]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[2] == array('name' => 'table1',
			'filter' => array('start_expr' => 'expr1', 'expr1' => array('braket_level' => 0)), 'unipath' => '/db1/table1[]'); /* ".print_r($tree[2], true)." */");

	$unipath = "/db1/table1[1]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$tree = __uni_parseUniPath($unipath);
// print_r($tree);
	assert("\$tree[2] == array(
			'name' => 'table1',
			'filter' => array('start_expr' => 'expr1', 
				'expr1' => array('left' => '1', 'left_type' => 'number', 'braket_level' => 0)
			), 
			'unipath' => '/db1/table1[1]'); /* ".print_r($tree[2], true)." */");
			
	$unipath = "/.[1=1 and (DataXML/zemucharea = 'Лазаревский' or DataXML/zemucharea = 'Хостинский') and DataXML/priceuch <= 0]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
// $GLOBALS['unipath_debug_parse'] = false;
	assert("\$tree[1] == array('name' => '.',
			'filter' => array('start_expr' => 'expr1',
				'expr1' => array(
					'braket_level' => 0,
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
					'next' => 'expr7',
					'braket_level' => 0,
					'right' => 'expr4',
					'right_type' => 'expr'
				),
				'expr3' => array(
					'braket_level' => 1,
					'left' => 'DataXML/zemucharea',
					'left_type' => 'unipath',
					'op' => '=',
					'right' => 'Лазаревский',
					'right_type' => 'string',
					'next' => 'expr5'
				),
				'expr4' => array(
					'braket_level' => 1,
					'left' => 'expr3',
					'left_type' => 'expr',
					'op' => 'or',
					'right' => 'expr5',
					'right_type' => 'expr',
					'next' => 'expr2'
				),
				'expr5' => array(
					'braket_level' => 1,
					'left' => 'DataXML/zemucharea',
					'left_type' => 'unipath',
					'op' => '=',
					'right' => 'Хостинский',
					'right_type' => 'string',
					'next' => 'expr4'
				),
				'expr6' => array(
					'braket_level' => 0,
					'left' => 'expr2',
					'left_type' => 'expr',
					'op' => 'and',
					'right' => 'expr7',
					'right_type' => 'expr',
					'next' => null
				),
				'expr7' => array(
					'braket_level' => 0,
					'left' => 'DataXML/priceuch',
					'left_type' => 'unipath',
					'op' => '<=',
					'right' => 0,
					'right_type' => 'number',
					'next' => 'expr6'
				)
			),
		'unipath' => \"/.[1=1 and (DataXML/zemucharea = 'Лазаревский' or DataXML/zemucharea = 'Хостинский') and DataXML/priceuch <= 0]\"); /* ".print_r($tree[1], true)." */");
	
	$unipath = "/.[DataXML/ulstreet='Депутатская','Учительская', \"Лермонтова\", `Пирогова`, 'Грибоедова', 'Дмитриевой', 'Комсомольская', 'Лысая']";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[1] == array('name' => '.', 
			'filter' => array('start_expr' => 'expr1',
			'expr1' => array(
				'braket_level' => 0,
				'left' => 'DataXML/ulstreet',
				'left_type' => 'unipath',
				'op' => '=',
				'right' => array('Депутатская', 'Учительская', 'Лермонтова', 'Пирогова', 'Грибоедова', 'Дмитриевой', 'Комсомольская', 'Лысая'),
				'right_type' => 'list-of-string')
			), 
			'unipath' => '/.[DataXML/ulstreet=\\'Депутатская\\',\\'Учительская\\', \"Лермонтова\", `Пирогова`, \\'Грибоедова\', \\'Дмитриевой\', \\'Комсомольская\\', \\'Лысая\\']'); /* ".print_r($tree[1], true).' */');
// $GLOBALS['unipath_debug_parse'] = false;

	$unipath = "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/10597/Pictures/'фото 2-1.JPG'";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_filepath_with_rusname', 'data' => $tree[7])), 
		0, 'set',
		array('name' => 'фото 2-1.JPG', 
			'unipath' => "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/10597/Pictures/'фото 2-1.JPG'")
	);
//print_r($tree);

	$unipath = '_SERVER/DOCUMENT_ROOT/asDirectory()/objs/8506/Pictures/`image (3)-7.jpg`/asImageFile()';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_filepath_with_rusname', 'data' => $tree[7])), 
		0, 'set',
		array('name' => 'image (3)-7.jpg', 
			'unipath' => "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/8506/Pictures/`image (3)-7.jpg`")
	);
//print_r($tree);

	$unipath = '/_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	_cu1rsor_assertEqu(
		array(array('name' => 'parse_ifEmpty', 'data' => $tree[3])), 
		0, 'set',
		array('name' => 'ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')', 
			'unipath' => '/_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')')
	);
// print_r($tree);

	$unipath = "/row/prd_data_json_encoded/asJSON()/.[./key()/preg_match('image[0-9]$')]";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[4] == array (
  'name' => '.',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
      'braket_level' => 0,
      'left' => './key()/preg_match(\'image[0-9]$\')',
      'left_type' => 'unipath',
    ),
  ),
  'unipath' => '/row/prd_data_json_encoded/asJSON()/.[./key()/preg_match(\'image[0-9]$\')]',
); /* ".print_r($tree[4], true).' */');
// var_export($tree[4]);

	$fs_filename = mb_convert_encoding('123лаущыцлвв-1.jpg', 'WINDOWS-1251', 'UTF-8');
	$unipath = "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/123/Pictures/$fs_filename/asImageFile()/resize(`100`, `100`, `inbox`)/saveAs(`/tmp/$fs_filename`)";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[10] == array('name' => 'saveAs(`/tmp/$fs_filename`)', 'unipath' => '_SERVER/DOCUMENT_ROOT/asDirectory()/objs/123/Pictures/$fs_filename/asImageFile()/resize(`100`, `100`, `inbox`)/saveAs(`/tmp/$fs_filename`)')");
// print_r($tree);

	$unipath = 'file://./unipath_test.txt';
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[1] == array('name' => 'file://', /* 'data' => 'file://', 'data_type' => 'string/local-filesystem', 'data_tracking' => array('key()' => 'file://'),*/ 'unipath' => 'file://'); /* ".print_r($tree[1], true).' */');
	assert('$tree[2] == array("name" => ".", "unipath" => "file://."); /* '.print_r($tree[1], true).' */');

	$fs_filename = "2,_3,_4этаж[1]-1.JPG";
	$unipath = "_SERVER/DOCUMENT_ROOT/asDirectory()/objs/123/Pictures/'$fs_filename'/asImageFile()/resize(`100`, `100`, `fill`)/crop(`100`, `100`)/saveAs(`/tmp/$fs_filename`)";
	echo "<h3>--- $unipath ---</h3>";
	$tree = __uni_parseUniPath($unipath);
	assert("\$tree[7] == array('name' => '$fs_filename', 'unipath' => '_SERVER/DOCUMENT_ROOT/asDirectory()/objs/123/Pictures/\'$fs_filename\''); /* ".print_r($tree[7], true).' */');
// print_r($tree);

	$func_string = $tree[11]['name'];
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(array(0 => '/tmp/$fs_filename'), array(0 => 'string')); /* ".print_r($result, true).' */');
// print_r($result);
	
	$func_string = "add(row_data/newbadress/regexp_replace('^[0-9]+\.',''))";
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
	assert('$result == array(
		array("row_data/newbadress/regexp_replace(\'^[0-9]+\.\',\'\')"),
		array("unipath")
		); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = '/content/regexp_all(`<a.*?href=[\'"]([^"\']+)`)';
	echo "<h3><xmp>--- $unipath ---</xmp></h3><xmp>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseUniPath($unipath);
	assert("\$result[2] == array('name' => 'regexp_all(`<a.*?href=[\'\"]([^\"\']+)`)', 'unipath' => '/content/regexp_all(`<a.*?href=[\'\"]([^\"\']+)`)'); /* ".print_r($result[2], true)." */");
	echo '</xmp>';
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = '/row/sprintf1(`<a href="/%s/" onclick="return load_album_into_player(%i, \'%s\', \'%s\');"`, post_name, ID, post_title, guid)';
	echo "<h3><xmp>--- $unipath ---</xmp></h3><xmp>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// print_r($result);
	assert("\$result[2] == array('name' => 'sprintf1(`<a href=\"/%s/\" onclick=\"return load_album_into_player(%i, \'%s\', \'%s\');\"`, post_name, ID, post_title, guid)', 'unipath' => '/row/sprintf1(`<a href=\"/%s/\" onclick=\"return load_album_into_player(%i, \'%s\', \'%s\');\"`, post_name, ID, post_title, guid)'); /* ".print_r($result[2], true)." */");
	echo '</xmp>';
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = "/wp/db/posts[post_parent = /post/ID and like(post_mime_type, `audio/%`)]";
	echo "<h3><xmp>--- $unipath ---</xmp></h3><xmp>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_export($result[3]);
	assert("\$result[3] == array (
  'name' => 'posts',
  'filter' => 
  array (
    'start_expr' => 'expr1',
    'expr1' => 
    array (
	  'braket_level' => 0,
      'left' => 'post_parent',
      'left_type' => 'name',
      'op' => '=',
      'right' => '/post/ID',
      'right_type' => 'unipath',
      'next' => 'expr2',
    ),
    'expr2' => 
    array (
	  'braket_level' => 0,
      'left' => 'expr1',
      'left_type' => 'expr',
      'op' => 'and',
      'next' => NULL,
      'right' => 'like(post_mime_type, `audio/%`)',
      'right_type' => 'function',
    ),
  ),
  'unipath' => '/wp/db/posts[post_parent = /post/ID and like(post_mime_type, `audio/%`)]',
); /* ".print_r($result[3], true)." */");
	echo '</xmp>';
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = '/soxp_db()/prm_news[pn_author=/item/fio and pn_ispub=1 and pn_datepublic < /php:date(`Y-m:d H:i:s`)]/order_by(`pn_datepublic DESC`)/limit(1)';
	echo "<h3><xmp>--- $unipath ---</xmp></h3><xmp>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_export($result[2]);
	assert("\$result[2] == array(
		'name' => 'prm_news', 
		'filter' => array(
			'start_expr' => 'expr1',
			'expr1' => array(
				'braket_level' => 0,
				'left' => 'pn_author',
                'left_type' => 'name',
                'op' => '=',
                'right' => '/item/fio',
                'right_type' => 'unipath',
                'next' => 'expr3'
            ),
            'expr2' => array(
				'braket_level' => 0,
				'left' => 'expr1',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => 'expr5',
				'right' => 'expr3',
				'right_type' => 'expr'
			),
            'expr3' => array(
				'braket_level' => 0,
				'left' => 'pn_ispub',
				'left_type' => 'name',
				'op' => '=',
				'next' => 'expr2',
				'right' => 1,
				'right_type' => 'number'
			),
			'expr4' => array (
				'braket_level' => 0,
				'left' => 'expr2',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => NULL,
				'right' => 'expr5',
				'right_type' => 'expr',
			),
			'expr5' => array (
				'braket_level' => 0,
				'left' => 'pn_datepublic',
				'left_type' => 'name',
				'op' => '<',
				'next' => 'expr4',
				'right' => '/php:date(`Y-m:d H:i:s`)',
				'right_type' => 'unipath',
			)
		),
		'unipath' => '/soxp_db()/prm_news[pn_author=/item/fio and pn_ispub=1 and pn_datepublic < /php:date(`Y-m:d H:i:s`)]'); /* ".print_r($result[2], true)." */");
	echo '</xmp>';
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = "/soxp_db()\r/prm_news[pn_theme=pt_id]";
	echo "<h3>--- $unipath ---</h3>";
	$result = __uni_parseUniPath($unipath);
	assert("\$result[1]['name'] == 'soxp_db()'; /* ".print_r($result[1], true)." */");
	
	$unipath = "/soxp_db()/tbl_categories[id <> 1,2,3 and id != 4,5,6 and cat_name != `aaa`,`bbb`,`ccc` and cat_name <> `eee`,`fff`]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
	assert("\$result[2] == array(
		'name' => 'tbl_categories',
		'filter' => array(
			'start_expr' => 'expr1',
			'expr1' => array(
				'braket_level' => 0,
				'left' => 'id',
				'left_type' => 'name',
				'op' => '<>',
				'right' => array('1', '2', '3'),
				'right_type' => 'list-of-number',
				'next' => 'expr3'
			),
			'expr2' => array(
				'braket_level' => 0,
				'left' => 'expr1',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => 'expr5',
				'right' => 'expr3',
				'right_type' => 'expr'
			),
			'expr3' => array(
				'braket_level' => 0,
				'left' => 'id',
				'left_type' => 'name',
				'op' => '!=',
				'next' => 'expr2',
				'right' => array('4', '5', '6'),
				'right_type' => 'list-of-number'
			),
			'expr4' => array(
				'braket_level' => 0,
				'left' => 'expr2',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => 'expr7',
				'right' => 'expr5',
				'right_type' => 'expr'
			),
			'expr5' => array(
				'braket_level' => 0,
				'left' => 'cat_name',
				'left_type' => 'name',
				'op' => '!=',
				'next' => 'expr4',
				'right_type' => 'list-of-string',
				'right' => array('aaa', 'bbb', 'ccc')
			),
			'expr6' => array(
				'braket_level' => 0,
				'left' => 'expr4',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => NULL,
				'right' => 'expr7',
				'right_type' => 'expr'
			),
			'expr7' => array(
				'braket_level' => 0,
				'left' => 'cat_name',
				'left_type' => 'name',
				'op' => '<>',
				'next' => 'expr6',
				'right_type' => 'list-of-string',
				'right' => array('eee', 'fff'),
			)
		),
		'unipath' => '/soxp_db()/tbl_categories[id <> 1,2,3 and id != 4,5,6 and cat_name != `aaa`,`bbb`,`ccc` and cat_name <> `eee`,`fff`]'
	); /* ".print_r($result[1], true)." */");
	
	$unipath = "/_SERVER/```````HTTP_HOST````````/```\"1\"`2'3````/`0`";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_dump($result[1]);
	assert("\$result[2]['name'] == 'HTTP_HOST`'; /* ".print_r($result[2]['name'], true)." */");
	assert("\$result[3]['name'] == '\"1\"`2\'3`'; /* ".print_r($result[3]['name'], true)." */");
	assert("\$result[4]['name'] == '0'; /* ".print_r($result[4]['name'], true)." */");
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = "/sprintf1(`````````it`s only test %s!```````, /if(['`1'=```1```` and /array(``, ```1]][2))`3```, '\"]')]/```test````,`1`,```2```)/0)/0";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_dump($result[1]);
	assert("\$result[1]['name'] == 'sprintf1(`````````it`s only test %s!```````, /if([\'`1\'=```1```` and /array(``, ```1]][2))`3```, \'\"]\')]/```test````,`1`,```2```)/0)'; /* ".print_r($result[1]['name'], true)." */");
// $GLOBALS['unipath_debug_parse'] = false;

	$unipath = "/[```````1`````````=```1```` and /array(``, ```1]][2))`3```, '\"]') = NULL]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_export($result[1]);
	assert("\$result[1]['filter'] == array(
			'start_expr' => 'expr1',
			'expr1' => array (
				'braket_level' => 0,
				'left_type' => 'string',
				'left' => '1``',
				'op' => '=',
				'right_type' => 'string',
				'right' => '1`',
				'next' => 'expr3',
				),
			'expr2' => array (
				'braket_level' => 0,
				'left' => 'expr1',
				'left_type' => 'expr',
				'op' => 'and',
				'next' => NULL,
				'right' => 'expr3',
				'right_type' => 'expr',
				),
			'expr3' => array (
				'braket_level' => 0,
				'left' => '/array(``, ```1]][2))`3```, \'\"]\')',
				'left_type' => 'unipath',
				'op' => '=',
				'next' => 'expr2',
				'right' => 'NULL',
				'right_type' => 'name',
				)
		); /* ".print_r($result[1]['filter'], true)." */");
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = "/[a=`111`, ```222````, ```````333````````, N'444', N'555' or 1]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
// var_export($result[1]);
	assert("\$result[1]['filter'] == array(
			'start_expr' => 'expr1',
			'expr1' => array (
				'braket_level' => 0,
				'left_type' => 'name',
				'left' => 'a',
				'op' => '=',
				'right_type' => 'list-of-string',
				'right' => array('111', '222`', '333`', '444', '555'),
				'next' => 'expr2',
				),
			'expr2' => array (
				'braket_level' => 0,
				'left' => 'expr1',
				'left_type' => 'expr',
				'op' => 'or',
				'next' => NULL,
				'right' => '1',
				'right_type' => 'number',
				),
		); /* ".print_r($result[1]['filter'], true)." */");
// $GLOBALS['unipath_debug_parse'] = false;
	
	$unipath = "bx()/db/
			b_iblock_element_prop_s3[IBLOCK_ELEMENT_ID=/_POST/offers/*/id/php-foreach:intval(.)]
			/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug_parse'] = true;
	$result = __uni_parseUniPath($unipath);
	assert("\$result[3]['filter'] == array (
		'start_expr' => 'expr1',
		'expr1' => 
			array (
			'braket_level' => 0,
			'left' => 'IBLOCK_ELEMENT_ID',
			'left_type' => 'name',
			'op' => '=',
			'right' => '/_POST/offers/'.'*'.'/id/php-foreach:intval(.)',
			'right_type' => 'unipath',
			),
		); /* ".strtr(print_r($result[3]['filter'], true), array('*' => '!'))." */");
// $GLOBALS['unipath_debug_parse'] = false;
	
	$func_string = "sprintf1(`````````it`s only test %s!```````, /if(['`1'=```1```` and /array(``, ```1]][2))`3```, '\"]')]/```test````,`1`,```2```)/0)";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(
		array('``it`s only test %s!', '/if([\'`1\'=```1```` and /array(``, ```1]][2))`3```, \'\"]\')]/```test````,`1`,```2```)/0'),
		array('string', 'unipath')
	); /* ".print_r($result, true)." */");
	
	$func_string = "alias('table1', 'tbl1')";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(
		array('table1', 'tbl1'),
		array('string', 'string')
	); /* ".print_r($result, true)." */");
		
	
	$func_string = "chunked('table1.Data',10000,3000)";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(
		array('table1.Data',10000,3000),
		array('string', 'number', 'number')
	); /* ".print_r($result, true)." */");
		
	$func_string = "columns(chunked(table1.Data,10000,3000), alias('table1.Id', 'id1'), 'table1.ContentType_id, REPLACE(''abcd)asd'', '')'', ''('')')";
	echo "<h3>--- $func_string ---</h3>";
	error_reporting(E_ALL & ~E_USER_NOTICE);
	$result = __uni_parseFuncArgs($func_string);
	error_reporting(E_ALL);
	assert("\$result == array(
		array('chunked(table1.Data,10000,3000)', \"alias('table1.Id', 'id1')\", \")\"),
		array('unipath', 'unipath', 'string')
	); /* ".print_r($result, true)." */");
	
	$func_string = 'join()';
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert('$result == array(array(), array()); /* '.print_r($result, true).' */');
	
	$func_string = 'formatQuantity(`тов\'ар`, `тов"ара`,`тов\'\'ар"""ов`, ````тов\'\'ар"""ов````, `````тов\'\'ар"""о``в`````, ```````тов\'\'ар"""о``в````````, \'```товар```\', "товар")';
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
// $GLOBALS['unipath_debug'] = false;
	assert('$result == array(array("тов\'ар", "тов\"ара", "тов\'\'ар\"\"\"ов", "`тов\'\'ар\"\"\"ов`", "``тов\'\'ар\"\"\"о``в``", "тов\'\'ар\"\"\"о``в`", "```товар```", "товар"), array("string", "string", "string", "string", "string", "string", "string", "string")); /* '.print_r($result, true).' */');

	$func_string = 'url(`file://./unipath_test.json`)';
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
	assert('$result == array(array("file://./unipath_test.json"), array("string")); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$func_string = "ifEmpty(tmp_dir/asDirectory()/`tm_send.tmp`)";
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
	assert('$result == array(array("tmp_dir/asDirectory()/`tm_send.tmp`"), array("unipath")); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$func_string = "ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=14 and Published=1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1]
	+alias('default_Common_CommonPartRecord', 'comm')[comm.Id = cont.Id]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/columns('vers.ContentItemRecord_id, cont.ContentType_id, urls.DisplayAlias, vers.Id as ver_id, comm.OwnerId', chunked(cont.Data, 2000, 3000, `nvarchar(3000)`), chunked(vers.Data, 21000, 3000, 'nvarchar(3000)'), 'titles.Title')/sql_iconv('UTF-8')/iconv('WINDOWS-1251', 'UTF-8'))";
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(array(\"db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=14 and Published=1]+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1]
	+alias('default_Common_CommonPartRecord', 'comm')[comm.Id = cont.Id]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/columns('vers.ContentItemRecord_id, cont.ContentType_id, urls.DisplayAlias, vers.Id as ver_id, comm.OwnerId', chunked(cont.Data, 2000, 3000, `nvarchar(3000)`), chunked(vers.Data, 21000, 3000, 'nvarchar(3000)'), 'titles.Title')/sql_iconv('UTF-8')/iconv('WINDOWS-1251', 'UTF-8')\"), array('unipath')); /* ".print_r($result, true).' */');
	
	$func_string = 'columns(`pb_realty.pbr_id` = `PRIMARY KEY INTEGER`, `pb_realty.pbr_act`=integer, `pb_realty.pbr_closedate` = DATETIME, ads_lifecycle_log.id = ```KEY INTEGER```, ads_lifecycle_log.action, ads_lifecycle_log.date, ```````t_balance_log.id```````="KEY",t_balance_log.value)';
	echo "<h3>--- $func_string ---</h3>";
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(array('pb_realty.pbr_id' => 'PRIMARY KEY INTEGER', 'pb_realty.pbr_act' => 'integer', 'pb_realty.pbr_closedate' => 'DATETIME',  'ads_lifecycle_log.id' => 'KEY INTEGER', 0 => 'ads_lifecycle_log.action', 1 => 'ads_lifecycle_log.date', 't_balance_log.id' => 'KEY', 2 => 't_balance_log.value'), array('pb_realty.pbr_id' => 'string', 'pb_realty.pbr_act' => 'unipath', 'pb_realty.pbr_closedate' => 'unipath', 'ads_lifecycle_log.id' => 'string', 0 => 'unipath', 1 => 'unipath', 't_balance_log.id' => 'string', 2 => 'unipath')); /* ".print_r($result, true).' */');
	
	$func_string = "replace_string(`60`=66, `Хостинский`=`Хостинский район`, tmp=./tmp)";
	echo "<h3>--- $func_string ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
	assert("\$result == array(array('60' => 66, 'Хостинский' => 'Хостинский район', 'tmp' => './tmp'), array('60' => 'number', 'Хостинский' => 'string', 'tmp' => 'unipath')); /* ".print_r($result, true).' */');
	
	$func_string = "array(
		`prd_code1c` = Code, 
		`prd_name` = Description, 
		`parent_code1c` = ./`Родитель--Код`, 
		`prd_deleted` = DeletionMark/replace_string(`ложь`=0,`истина`=1)/php_intval(),
		`prd_url` = array(Description/toURLTranslit(), `_`, Code)/join(),
		`cat_id` = /db_cats/.[cat_code1c=/row/`Родитель--Код`]/first(),
		`cat_code1c` = ./`Родитель--Код`,
		`prd_articul` = ./`Артикул`,
		`prd_data` = array(
			`prd_short_description` = ./`КраткоеОписание`,
			`prd_specifications_raw` = ./`ДополнительноеОписаниеНоменклатуры`
			),
		`prd_price2` = ./'Цены--ИнтернетЦена'/normalize_float(),
        `prd_price1` = ./'Цены--КраснодарFunBit'/normalize_float(),
        `prd_price3` = ./'Цены--Закупочный'/normalize_float(),
        `prd_price4` = ./'Цены--Безнал'/normalize_float(),
        'prd_price5' = ./'Цены--Карта-интернет'/normalize_float(),
        'prd_price6' = ./'Цены--Оптовая'/normalize_float(), ".
//         'prd_price7' = ./'Цены--СтараяДляСайта'/normalize_float() // только дли сайтовых нужд!
//         'prd_price8' = ./'Цены--Staten'/normalize_float(), // только дли сайтовых нужд!
        "'prd_stock1' = ./'ОстаткиНаСкладе--Сочи'/normalize_float(),
        'prd_stock2' = ./'ВыгружатьНаСайт'/normalize_float(),
        'prd_stock3' = ./'ОстаткиНаСкладе--Краснодар'/normalize_float(),
        'prd_stock4' = ./'ОстаткиНаСкладе--Ростов-на-Дону'/normalize_float(),
        'prd_stock5' = ./'ОстаткиНаСкладе--Воронеж'/normalize_float(),
        'prd_stock6' = ./'ОстаткиНаСкладе--Москва'/normalize_float(),
        'prd_stock7' = ./'ОстаткиНаСкладе--Пятигорск'/normalize_float()".
//         ,'prd_stock8' = /'ОстаткиНаСкладе--МоскваStaten' // только дли сайтовых нужд!
//         ,'prd_stock9' = ./'ОстаткиНаСкладе--Ростов-на-ДонуStaten' // только дли сайтовых нужд!
		")";
	echo "<h3>--- $func_string ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
// var_export($result);
	assert("\$result == array(0 => array (
    'prd_code1c' => 'Code',
    'prd_name' => 'Description',
    'parent_code1c' => './`Родитель--Код`',
    'prd_deleted' => 'DeletionMark/replace_string(`ложь`=0,`истина`=1)/php_intval()',
    'prd_url' => 'array(Description/toURLTranslit(), `_`, Code)/join()',
    'cat_id' => '/db_cats/.[cat_code1c=/row/`Родитель--Код`]/first()',
    'cat_code1c' => './`Родитель--Код`',
    'prd_articul' => './`Артикул`',
    'prd_data' => 'array(
			`prd_short_description` = ./`КраткоеОписание`,
			`prd_specifications_raw` = ./`ДополнительноеОписаниеНоменклатуры`
			)',
    'prd_price2' => './\'Цены--ИнтернетЦена\'/normalize_float()',
    'prd_price1' => './\'Цены--КраснодарFunBit\'/normalize_float()',
    'prd_price3' => './\'Цены--Закупочный\'/normalize_float()',
    'prd_price4' => './\'Цены--Безнал\'/normalize_float()',
    'prd_price5' => './\'Цены--Карта-интернет\'/normalize_float()',
    'prd_price6' => './\'Цены--Оптовая\'/normalize_float()',
    'prd_stock1' => './\'ОстаткиНаСкладе--Сочи\'/normalize_float()',
    'prd_stock2' => './\'ВыгружатьНаСайт\'/normalize_float()',
    'prd_stock3' => './\'ОстаткиНаСкладе--Краснодар\'/normalize_float()',
    'prd_stock4' => './\'ОстаткиНаСкладе--Ростов-на-Дону\'/normalize_float()',
    'prd_stock5' => './\'ОстаткиНаСкладе--Воронеж\'/normalize_float()',
    'prd_stock6' => './\'ОстаткиНаСкладе--Москва\'/normalize_float()',
    'prd_stock7' => './\'ОстаткиНаСкладе--Пятигорск\'/normalize_float()',
  ),
  1 => array (
    'prd_code1c' => 'unipath',
    'prd_name' => 'unipath',
    'parent_code1c' => 'unipath',
    'prd_deleted' => 'unipath',
    'prd_url' => 'unipath',
    'cat_id' => 'unipath',
    'cat_code1c' => 'unipath',
    'prd_articul' => 'unipath',
    'prd_data' => 'unipath',
    'prd_price2' => 'unipath',
    'prd_price1' => 'unipath',
    'prd_price3' => 'unipath',
    'prd_price4' => 'unipath',
    'prd_price5' => 'unipath',
    'prd_price6' => 'unipath',
    'prd_stock1' => 'unipath',
    'prd_stock2' => 'unipath',
    'prd_stock3' => 'unipath',
    'prd_stock4' => 'unipath',
    'prd_stock5' => 'unipath',
    'prd_stock6' => 'unipath',
    'prd_stock7' => 'unipath',
  )
); /* ".print_r($result, true).' */');
		
	$func_string = 'like(Data, test1=N\'%abc%\', N\'%abc%\')';
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
// var_export($result);
	assert("\$result == array(array(0 => 'Data', 'test1' => '%abc%', 1 => '%abc%'), array(0 => 'unipath', 'test1' => 'string-with-N', 1 => 'string-with-N')); /* ".var_export($result, true)." */");
	
	$func_string = "if([sprintf1(`%s %s`, name, last_name) = `Тест Тестович`], ' selected=\"selected\"', '')";
	echo "<h3>--- $func_string ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_parseFuncArgs($func_string);
// var_export($result);
	assert("\$result == array(array(0 => '[sprintf1(`%s %s`, name, last_name) = `Тест Тестович`]', 1 => ' selected=\"selected\"', 2 => ''), array(0 => 'unipath', 1 => 'string', 2 => 'string')); /* ".var_export($result, true)." */");
// $GLOBALS['unipath_debug'] = false;
	
/*print_r(__uni_parseUniPath("cache(file://./newbilding.json/contents)/ifEmpty(db1/alias('default_Orchard_Framework_ContentItemRecord','cont')[ContentType_id=11]
	+alias('default_Orchard_Framework_ContentItemVersionRecord','vers')[cont.Id = vers.ContentItemRecord_id and Latest=1 and Published=1]
	+alias('default_Orchard_Autoroute_AutoroutePartRecord', 'urls')[urls.Id = vers.Id]
	+alias('default_Title_TitlePartRecord', 'titles')[titles.Id = vers.Id]/cache(file://./newbilding.json))"));*/
	
	exit;
}

if(isset($_GET['test_uniPath'])) {
	global $GLOBALS_metadata, $GLOBALS_data_timestamp, $__uni_prt_cnt, $__test_our_url;

	$unipath = 'test1[a = 1]';
	echo "<h3>--- $unipath ---</h3>";
	$GLOBALS['test1'] = array(
		array('a' => 1),
		array('a' => 2, 'b' => 1),
		array('a' => 1, 'c' => 3)
	);

	$tmp = uni($unipath);
	assert('count($tmp) == 2 /* '.json_encode($tmp).' */');
	assert("\$tmp = array(0 => array('a' => 1), 1 => array('a' => 1, 'c' => 3)); /* ".print_r($tmp, true)." */");
	
// $GLOBALS['unipath_debug'] = true;
	$unipath = '/test1[a = 1]';
	echo "<h3>--- $unipath ---</h3>";
	$tmp = uni($unipath);
	assert('count($tmp) == 2 /* '.print_r($tmp, true).' */');
	assert("\$tmp = array(0 => array('a' => 1), 1 => array('a' => 1, 'c' => 3)); /* ".print_r($tmp, true)." */");
// $GLOBALS['unipath_debug'] = false;

	$unipath = 'test1[a = 0]/toArray()';
	echo "<h3>--- $unipath ---</h3>";
	$tmp = uni($unipath);
	assert('count($tmp) == 0; /* '.json_encode($tmp).' */');
	assert('$tmp == array(); /* '.json_encode($tmp).' */');
	
	$unipath = '/test1/0/no_function()/a';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$ret = uni($unipath, 999);
	assert('$ret == 999; /* '.print_r($ret, true).' */');
	assert("\$GLOBALS['test1'][0]['a'] == 999; /* ".print_r($GLOBALS['test1'], true).' */');
// $GLOBALS['unipath_debug'] = false;
	
//$GLOBALS['unipath_debug'] = true;
	$unipath = '$_POST/file1/name/translit()';
	echo "<h3>--- $unipath ---</h3>";
	$_POST['file1'] = array('name' => 'РСВ-1 (2013 - 2 квартал)(001).ods');
	$result = uni($unipath);
	assert('$result == "RSV-1_(2013_-_2_kvartal)(001).ods"; /* '.var_export($result,true).' */');
	
	$unipath = '$_POST/file1/tmp_name/untranslit()';
	echo "<h3>--- $unipath ---</h3>";
	$_POST['file1']['tmp_name'] = 'RSV-1_(2013-2_kvartal)_001.ods;%20';
	$result = uni($unipath);
	assert('$result == "РСВ-1 (2013-2 квартал) 001.одс;%20"; /* '.var_export($result,true).' */');

	$unipath = 'formatDate()';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == "'.strtr(date('j-m-Y, H:i'), array('-01-' => ' января ','-02-' => ' февраля ','-03-' => ' марта ', '-04-' => ' апреля ', '-05-' => ' мая ', '-06-' => ' июня ', '-07-' => ' июля ', '-08-' => ' августа ', '-09-' => ' сентября ', '-10-' => ' октября ', '-11-' => ' ноября ', '-12-' => ' декабря ')).'"; /* '.var_export($result,true).' */');

	$_POST = array('abc' => 123);
	
	$unipath = '_POST';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_with_start_data(null, null, null, $unipath);
	assert('$result = array("abc" => 123); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = '/_POST/cache(/post_cache1)/abc';
	echo "<h3>--- $unipath = 123 ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$ret = uni($unipath, 123);
	assert('$ret == 123; /* '.print_r($ret, true).' */');
	assert('$GLOBALS["post_cache1"] == array("abc" => 123); /* '.@print_r($GLOBALS['post_cache1'], true).' */'); 
	assert('$GLOBALS_metadata["post_cache1"][0] == "array"; /* '.@print_r($GLOBALS_metadata["post_cache1"],true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = '$_POST/cache("post_cache1")/abc';
	echo "<h3>--- $unipath ---</h3>";
	$_POST = array('abc' => 123);
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == 123; /* '.print_r($result, true).' */');
	assert('$GLOBALS["post_cache1"] == array("abc" => 123); /* '.@print_r($GLOBALS['post_cache1'], true).' */'); 
	assert('$GLOBALS_metadata["post_cache1"][0] == "array"; /* '.print_r($GLOBALS_metadata["post_cache1"],true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = 'cache("post_cache1")';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("abc" => 123); /* '.print_r($result, true).' */');
//$GLOBALS['unipath_debug'] = false;

// $GLOBALS['unipath_debug'] = true;
	if(!file_exists('unipath_test.txt')) file_put_contents('unipath_test.txt', '123');
	$unipath = 'url(file://./unipath_test.txt)';
	echo "<h3>--- $unipath = 123 ---</h3>";
	uni($unipath, '123');
	assert('file_get_contents("unipath_test.txt") == "123" /* '.@file_get_contents("unipath_test.txt").' */');
	$file_content = uni($unipath.'/contents()');
	assert('$file_content == "123"; /* '.print_r($file_content,true).' */');
// $GLOBALS['unipath_debug'] = false;

	// $GLOBALS['unipath_debug'] = true;
	@unlink('file_not_exists.json');
	$unipath = 'url(file://.)/file_not_exists.json/contents()';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == NULL; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$_POST = array('abc' => 123);

	@unlink('unipath_test.json');
	$unipath = '/_POST/cache(url(`file://./unipath_test.json`))';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("abc" => 123); /* '.print_r($result, true).' */');
	assert('file_get_contents("unipath_test.json") == \'{"name":"_POST","unipath":"\/_POST","data":{"abc":123},"metadata":{"0":"array","key()":"_POST"},"cache_timestamp":'.time().'}\' /* '.@file_get_contents("unipath_test.json").' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = 'url(file://./unipath_test.json)/contents()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == \'{"name":"_POST","unipath":"\/_POST","data":{"abc":123},"metadata":{"0":"array","key()":"_POST"},"cache_timestamp":'.time().'}\'; /* '.print_r($result, true).' */');
	
	$unipath = 'cache(url(file://./unipath_test.json)/contents())';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("abc" => 123); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = 'cached($uncached_var1, array(1,2,3))/2';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == 3; /* '.print_r($result, true).' */');
	assert('isset($GLOBALS["uncached_var1"]); /* '.print_r($GLOBALS['uncached_var1'], true).' */');
	assert('$GLOBALS_metadata[\'uncached_var1\'][0] == "array"; /* '.@print_r($GLOBALS_metadata['uncached_var1'], true).' */');

	$unipath = 'cached($uncached_var1)/1';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == 2; /* '.print_r($result, true).' */');
	
	$unipath = 'cached(/fs()/srv/vhosts/soxp2_html/cache/blogger_block.json, /soxp_db()/admins[adminroles.name = `Блогер` and status = 1]+adminroles[adminroles.id = role_id]++objects[obj_type = `admins-extradata` and obj_int1 = admins.id]/columns(`objects.*, admins.*`))';
	echo "<h3>--- $unipath ---</h3>";
	assert('1==2; /* Запись курсора в кеш не поддерживается! Запись в файл-курсор? */');
	
	// 
	$unipath = "uni('cached(/fs()/cache/news_author_123.json/contents(), /soxp_db()/objects[obj_type = `admins-extradata` and obj_int1 = admins.id]+admins[name = /news/pn_author/split(` `)/0/ifEmpty(``) and last_name = /news/pn_author/split(` `)/1/ifEmpty(``)]++objects_text1[objects_text1.obj_id=objects.obj_id]/0)');";
	echo "<h3>--- $unipath ---</h3>";
	assert('1==2; /* после успешного кеширования не возвращает рузультат! */');
	
	$unipath = '$_POST/cache($_post)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("abc" => 123); /* '.print_r($result, true).' */');
	assert('isset($GLOBALS["_post"]); /* '.print_r($GLOBALS['_post'], true).' */'); 
	assert('$GLOBALS_metadata[\'_post\'][0] == "array"; /* '.@print_r($GLOBALS_metadata['_post'], true).' */');
	
	$unipath = 'cache($_post)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result = array('abc' => 123); /* ".print_r($result, true)." */");
	
	$unipath = "\$_POST/replace_in('<h1>abc</h1>
	<h2>abc</h2>')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result = '<h1>123</h1>
	<h2>123</h2>'; /* ".print_r($result, true)." */");
//$GLOBALS['unipath_debug'] = false;

	$unipath = '/$_POST/empty_value/ifEmpty(\'<span class="obj-nophoto">Нет фото</span>\')';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
	assert("\$result = '<span class=\"obj-nophoto\">Нет фото</span>'; /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';
	
	$unipath = '$_POST/abc/formatPrice(\' руб.\')';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result = '123 руб.'; /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;
	
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
	$result = uni($unipath);
	assert("\$result == '<span class=\"obj-photo\"><img src=\"/Media/Default/newbilding/mainf/Фотография3-1.jpg\" alt=\"\" width=\"80\" /></span>'; /* ".print_r($result, true).' */');

	$_GET['test_str2'] = '123';
	$unipath = "\$_GET/test_str/add('.jpg')/remove_end('.jpg')/remove_start('~/')/add(\$_GET/test_str2)";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == 'Media/Default/newbilding/mainf/Фотография3-1.jpg123'; /* ".print_r($result, true).' */');
	
	
	$_GET['test_str3'] = 'Хостинский, 60 лет ВЛКСМ';
	$unipath = "\$_GET/test_str3/remove_end(', ')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == 'Хостинский, 60 лет ВЛКСМ'; /* ".print_r($result, true).' */');
	
	$unipath = "/_GET/test_str3/replace_string(`60`=66, `Хостинский`=`Хостинский район`)";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result = "Хостинский район, 66 лет ВЛКСМ"; /* '.print_r($result, true).' */');

	define('TEST_CONST', 'test123');
	$unipath = '/TEST_CONST';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("defined('TEST_CONST');");
	assert("is_object(\$result) == false; /* ".var_export($result, true).' */');
	assert("\$result == 'test123'; /* ".var_export($result, true).' */');
	assert("class_exists(TEST_CONST) == false; /* ".var_export(class_exists(TEST_CONST), true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = 'url(`file://./IMG_5020.JPG`)/asImageFile()/fixOrientation()/cache(/tmp)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$GLOBALS_metadata['tmp']['img_info'][0] == 1936");
	assert("\$GLOBALS_metadata['tmp']['img_info'][1] == 2592") or var_dump($result, $GLOBALS['tmp'], $GLOBALS_metadata['tmp']);
// 	uni("url(`file://./IMG_5020.JPG`)/asImageFile()/fixOrientation()/saveAs('temp.jpg')");
	
	$unipath = '/PDO/PDO(`sqlite::memory:`)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$GLOBALS['db1'] = uni($unipath);
	assert("is_object(\$GLOBALS['db1']); /* ".print_r($GLOBALS['db1'], true).' */');
	assert("get_class(\$GLOBALS['db1']) == 'PDO'; /* ".print_r($GLOBALS['db1'], true).' */');
	
	$unipath = '/PDO/__construct(`sqlite::memory:`)';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$GLOBALS['db1'] = uni($unipath);
	assert("is_object(\$GLOBALS['db1']); /* ".print_r($GLOBALS['db1'], true).' */');
	assert("get_class(\$GLOBALS['db1']) == 'PDO'; /* ".print_r($GLOBALS['db1'], true).' */');
	
	echo "<h3>--- (new Uni('/PDO/__construct(`sqlite::memory:`)'))['.'] ---</h3>";
	$result = new Uni("/PDO/__construct(`sqlite::memory:`)");
	$result = $result['.'];
	assert('get_class($result) == "PDO"; /* '.print_r(get_class($result), true).' */');
	
	$unipath = "db1/alias('table1','tbl1')[id=1]+alias('table2','tbl2')[tbl1.id=tbl2.id]
		+table3
		+table5-AS-t5_abc[t5_abc.id = tbl1.id]
		+table4[tbl1.id=table4.id]/order_by('tbl1.id')/columns(\"tbl1.*, tbl1.id as id1, tbl2.*, IFNULL(tbl1.name, '(null)') as name\")/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT tbl1.*, tbl1.id as id1, tbl2.*, IFNULL(tbl1.name, \'(null)\') as name FROM table1 AS tbl1 LEFT JOIN table2 AS tbl2 ON tbl1.id = tbl2.id NATURAL JOIN table3 LEFT JOIN table5 AS t5_abc ON t5_abc.id = tbl1.id LEFT JOIN table4 ON tbl1.id = table4.id WHERE id = 1  ORDER BY tbl1.id', 'sql_params' => array()); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;

	$unipath = "db1/ads_lifecycle_log[item_id = pbr_id and item_type = 34 and ads_lifecycle_log.date > `2015-10-00` and pbr_puid = 74112 and pbr_act = 1] + pb_realty + ads_pay_log[lifecycle_log_id = ads_lifecycle_log.id] + t_balance_log[balance_log_id = t_balance_log.id]/columns(`pbr_id, pbr_act, pbr_closedate, ads_lifecycle_log.id, action, ads_lifecycle_log.date, t_balance_log.value`)/order_by(`pbr_id, ads_lifecycle_log.date DESC`)/limit(100)/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT pbr_id, pbr_act, pbr_closedate, ads_lifecycle_log.id, action, ads_lifecycle_log.date, t_balance_log.value FROM ads_lifecycle_log NATURAL JOIN pb_realty LEFT JOIN ads_pay_log ON lifecycle_log_id = ads_lifecycle_log.id LEFT JOIN t_balance_log ON balance_log_id = t_balance_log.id WHERE item_id = pbr_id and item_type = 34 and ads_lifecycle_log.date > \'2015-10-00\' and pbr_puid = 74112 and pbr_act = 1  ORDER BY pbr_id, ads_lifecycle_log.date DESC LIMIT 100', 'sql_params' => array()); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "db1/table1[id = -123]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$sql = uni($unipath);
	assert('$sql == array("sql_query" =>"SELECT * FROM table1 WHERE id = -123", "sql_params" => array()); /* '.print_r($sql, true).' */');
	
	$unipath = "db1/table1[1]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$sql = uni($unipath);
	assert('$sql == array("sql_query" =>"SELECT * FROM table1 WHERE 1", "sql_params" => array()); /* '.print_r($sql, true).' */');

	$unipath = "db1/table1[]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$sql = uni($unipath);
	assert('$sql == array("sql_query" => "SELECT * FROM table1", "sql_params" => array()); /* '.print_r($sql, true).' */');
	
	$unipath = "db1/table1[like(val, '%123%')]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$sql = uni($unipath);
	assert('$sql == array("sql_query" => "SELECT * FROM table1 WHERE val LIKE \'%123%\'", "sql_params" => array()); /* '.print_r($sql, true).' */');
	
	$unipath = "db1/table1[val = /none_var]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$sql = uni($unipath);
	assert('$sql == array("sql_query" => "SELECT * FROM table1 WHERE val IS NULL", "sql_params" => array()); /* '.print_r($sql, true).' */');
	
	$GLOBALS['db1']->exec("CREATE TABLE tbl_categories (
        cat_id INTEGER PRIMARY KEY,
        cat_parent_id INTEGER NOT NULL DEFAULT 0,
        cat_name TEXT NOT NULL,
        cat_url TEXT,
        cat_type TEXT,
        cat_code1c TEXT NOT NULL,
        cat_deleted INTEGER NOT NULL DEFAULT 0,
        cat_hidden INTEGER NOT NULL DEFAULT 0,
        cat_sort_order REAL NOT NULL DEFAULT 1000.0,
        cat_modified_stamp INTEGER,
        cat_data_json_encoded TEXT)");
    $GLOBALS['db1']->exec("INSERT INTO tbl_categories (cat_id, cat_name, cat_code1c) VALUES (1, 'Category 1', 'CODE0000001')");
    $unipath = "db1/tbl_categories[1]/all()";
	echo "<h3>--- $unipath ---</h3><xmp>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(array(
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	)); /* ".print_r($result, true).' */');
	echo '</xmp>';
// $GLOBALS['unipath_debug'] = false;
    
    //     $GLOBALS['db1']->exec("INSERT INTO categories (cat_id, cat_name, cat_code1c) VALUES (2, 'Category 2', 'CODE0000002')");
    $unipath = "/db1/tbl_categories/new_row()";
    echo "<h3>--- $unipath = array(2, 'Category 2', 'CODE0000002', 'cat_sort_order' => 1000.1) ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath, array(
		'cat_parent_id' => 0,
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => 0,
		'cat_hidden' => 0,
		'cat_sort_order' => 1000.1,
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	));
    $result = $GLOBALS['db1']->query("SELECT * FROM tbl_categories WHERE cat_id = 2")->fetchAll(PDO::FETCH_ASSOC);
assert("\$result == array(array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	)); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_code1c = 'CODE0000002' WHERE cat_id = 2");

    $unipath = "db1/tbl_categories[1]/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(array(
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	),
	array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000002',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	)); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "db1/tbl_categories[1]/toArray()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(array(
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	),
	array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000002',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	)); /* ".var_export($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(array(
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	),
	array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000002',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	)); /* ".var_export($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[cat_id=2]/0/cat_code1c";
	echo "<h3>--- $unipath = '00000000002' ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_with_start_data(null, null, null, $unipath, '00000000002');
	$result = $GLOBALS['db1']->query("SELECT * FROM tbl_categories WHERE cat_id = 2")->fetchAll(PDO::FETCH_ASSOC);
	assert('$result[0]["cat_code1c"] === "00000000002"; /* '.print_r($result[0], true).' */');
// $GLOBALS['unipath_debug'] = false;

	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_code1c = 'CODE0000002' WHERE cat_id = 2");
	$unipath = "/db1/tbl_categories[cat_id=2]/*/cat_code1c";
	echo "<h3>--- $unipath = '00000000002' ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$GLOBALS['unipath_debug_sql'] = array();
	$result = __uni_with_start_data(null, null, null, $unipath, '00000000002');
	$result = $GLOBALS['db1']->query("SELECT * FROM tbl_categories WHERE cat_id = 2")->fetchAll(PDO::FETCH_ASSOC);
	assert('$result[0]["cat_code1c"] === "00000000002"; /* '.print_r($result[0], true).' */');
	assert('$GLOBALS["unipath_debug_sql"] == array("UPDATE tbl_categories SET cat_code1c = \'00000000002\' WHERE cat_id = 2"); /* '.print_r($GLOBALS['unipath_debug_sql'], true).' */');
	unset($GLOBALS['unipath_debug_sql']);
// $GLOBALS['unipath_debug'] = false;

	// TODO uni('/soxp_db()/pb_realty[pbr_id=430582,581582,581742,692892,735712,744922,748362,782532,830822,847352,852102,862612,876992,925892,938092,1006012,1018942,1066252,1159302,1185042,1200062,1222062,1323342,1355012,1387742,1391792,1392592,1393072,1395202,1586462,1613002,1700762,1784242,1798642,1865522,1890142,1935122,1938712,2074362,2089282,2098332,2106792,2119682,2127802,2131082,2149082,2299522,2321732,2332042,2362222,2362232,2382502,2392612,2413322,2416972,2438642,2451692,2453692,2456212,2457862,2516052,2541462,2544042,2550152,2553382,2590892,2592172,2592672,2625352,2626782,2627902,2747002,2762482,2762492,2769332,2802802,2932002,2942432,2949182,2986532,3059452,3060102,3071772,3092612,3118162,3124032,3215392,3215402,3223822,3227152,3265692,3284492,3320752,3326192,3339622,3359312,3366862,3384982,3390112,3395012,3401552,3404592,3411752,3414362,3444102,3446282,3461942,4217043]/*', array('pbr_closedate' => null));

	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_code1c = 'CODE0000002' WHERE cat_id = 2");
	$unipath = "/db1/tbl_categories[cat_id=2]/*";
	echo "<h3>--- $unipath = array('cat_code1c' => '00000000002') ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$GLOBALS['unipath_debug_sql'] = array();
	$result = __uni_with_start_data(null, null, null, $unipath, array('cat_code1c' => '00000000002'));
	$result = $GLOBALS['db1']->query("SELECT * FROM tbl_categories WHERE cat_id = 2")->fetchAll(PDO::FETCH_ASSOC);
	assert('$result[0]["cat_code1c"] === "00000000002"; /* '.print_r($result[0], true).' */');
	assert('$GLOBALS["unipath_debug_sql"] == array("UPDATE tbl_categories SET cat_code1c = \'00000000002\' WHERE cat_id = 2"); /* '.print_r($GLOBALS['unipath_debug_sql'], true).' */');
	unset($GLOBALS['unipath_debug_sql']);
// $GLOBALS['unipath_debug'] = false;

// 	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_code1c = 'CODE0000002' WHERE cat_id = 2");
// 	$unipath = "/db1/tbl_categories[cat_id=2]/cat_code1c";
// 	echo "<h3>--- $unipath = '00000000002' Notice! ---</h3>";
// $GLOBALS['unipath_debug_sql'] = true; $GLOBALS['unipath_debug'] = true;
// 	error_clear_last();
// 	$result = @__uni_with_start_data(null, null, null, $unipath, '00000000002');
// 	$error = error_get_last();
// 	assert('strpos($error["message"], "Data not changed.") > 0; /* '.print_r($error, true).' */');
// 	$rows = $GLOBALS['db1']->query("SELECT * FROM tbl_categories WHERE cat_id = 2")->fetchAll(PDO::FETCH_ASSOC);
// 	assert('$rows[0]["cat_code1c"] === "CODE0000002"; /* '.print_r($rows[0], true).' */');
// $GLOBALS['unipath_debug_sql'] = false; $GLOBALS['unipath_debug'] = false;

	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_code1c = '00000000002' WHERE cat_id = 2");

	$unipath = "/db1/tbl_categories[cat_id=2]/0/cat_deleted";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = __uni_with_start_data(null, null, null, $unipath);
	assert('$result["data"] === "0"; /* '.print_r($result, true).' */');
	assert('$result["metadata"][0] === "string"; /* '.print_r($result, true).' */');
	assert('$result["metadata"]["key()"] === "cat_deleted"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[1=1]/1/cat_deleted";
	echo "<h3>--- $unipath = 1 ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$GLOBALS['unipath_debug_sql'] = array();
	uni($unipath, 1);
	assert('$GLOBALS["unipath_debug_sql"] == array(
		"SELECT * FROM tbl_categories WHERE 1 = 1", 
		"UPDATE tbl_categories SET cat_deleted = 1 WHERE cat_id = 2"); /* '.print_r($GLOBALS["unipath_debug_sql"], true).' */');
	
	$GLOBALS['db1']->exec("UPDATE tbl_categories SET cat_deleted = 0 WHERE cat_id = 2");

	$unipath = "/db1/tbl_categories[1=1]/columns(`cat_parent_id, cat_name, cat_code1c, cat_deleted, cat_hidden, cat_sort_order`)/1/cat_deleted";
	echo "<h3>--- $unipath = 1 ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$GLOBALS['unipath_debug_sql'] = array();
	uni($unipath, 1);
	assert('$GLOBALS["unipath_debug_sql"] == array("SELECT cat_parent_id, cat_name, cat_code1c, cat_deleted, cat_hidden, cat_sort_order FROM tbl_categories WHERE 1 = 1", "UPDATE tbl_categories SET cat_deleted = 1 WHERE cat_parent_id = 0 AND cat_name = \'Category 2\' AND cat_code1c = \'00000000002\' AND cat_deleted = 0 AND cat_hidden = 0 AND cat_sort_order BETWEEN 1000.095 AND 1000.105"); /* '.print_r($GLOBALS["unipath_debug_sql"], true).' */');
	
	$rows = $GLOBALS['db1']->query("SELECT cat_deleted FROM tbl_categories")->fetchAll(PDO::FETCH_ASSOC);
	assert('$rows == array(array("cat_deleted" => 0), array("cat_deleted" => 1)); /* '.print_r($rows, true).' */');
	unset($GLOBALS['unipath_debug_sql']);
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[cat_id=2]";
	echo "<h3>--- $unipath = array('cat_deleted' => 2) ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$GLOBALS['unipath_debug_sql'] = array();
	uni($unipath, array('cat_deleted' => 2));
	assert('$GLOBALS["unipath_debug_sql"] == array("UPDATE tbl_categories SET cat_deleted = 2 WHERE cat_id = 2"); /* '.print_r($GLOBALS["unipath_debug_sql"], true).' */');
	
	$rows = $GLOBALS['db1']->query("SELECT cat_deleted FROM tbl_categories")->fetchAll(PDO::FETCH_ASSOC);
	assert('$rows == array(array("cat_deleted" => 0), array("cat_deleted" => 2)); /* '.print_r($rows, true).' */');
	unset($GLOBALS['unipath_debug_sql']);
// $GLOBALS['unipath_debug'] = false;

	$GLOBALS['db1']->exec("CREATE TABLE tbl_categories_stat (
		cat_id INTEGER PRIMARY KEY,
		cat_stat TEXT
	);");
	
	$unipath = "/db1/tbl_categories[cat_hidden=0]++tbl_categories_stat[tbl_categories_stat.cat_id=tbl_categories.cat_id]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT * FROM tbl_categories LEFT OUTER JOIN tbl_categories_stat ON tbl_categories_stat.cat_id = tbl_categories.cat_id WHERE cat_hidden = 0', 'sql_params' => array()); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "/db1/tbl_categories[cat_hidden=0]++tbl_categories_stat[tbl_categories_stat.cat_id=tbl_categories.cat_id]/all()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
// var_export($result);
	assert("\$result == array(array(
		'cat_id' => NULL,
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
		'cat_stat' => NULL,
	),
	array(
		'cat_id' => NULL,
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
		'cat_stat' => NULL,
	)); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/sql_table_prefix(`tbl_`)/categories[cat_hidden=0 and like(categories.cat_name, 'C%')]++categories_stat[categories_stat.cat_id=categories.cat_id]/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
// var_export($result);
	assert("\$result == array(array(
		'cat_id' => NULL,
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
		'cat_stat' => NULL,
	),
	array(
		'cat_id' => NULL,
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
		'cat_stat' => NULL,
	)); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[1]/sql_result_cache(/tmp_rows_cache1)/cache(/db1_cats)";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	uni($unipath);
// $GLOBALS['unipath_debug'] = false;
	assert("is_array(\$GLOBALS_metadata['db1_cats']); /* ".print_r($GLOBALS_metadata['db1_cats'], true).' */');
	assert("!isset(\$GLOBALS_metadata['db1_cats']['stmt']);") or print_r($GLOBALS_metadata['db1_cats']);
	// ???
	assert("!empty(\$GLOBALS['tmp_rows_cache1']); /* ".@print_r($GLOBALS['tmp_rows_cache1'], true).' */');
	
	$unipath = "/db1/tbl_categories[date < NOW()]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT * FROM tbl_categories WHERE date < NOW()', 'sql_params' => array()); /* ".@print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "/db1/tbl_categories[date < sql(`NOW()`)]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT * FROM tbl_categories WHERE date < NOW()', 'sql_params' => array()) /* ".@print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[id <> 1,2,3 and id != 4,5,6 and cat_name != `aaa`,`bbb`,`ccc` and cat_name <> `eee`,`fff`]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => 'SELECT * FROM tbl_categories WHERE id NOT IN (1,2,3) and id NOT IN (4,5,6) and cat_name NOT IN (\'aaa\', \'bbb\', \'ccc\') and cat_name NOT IN (\'eee\', \'fff\')', 'sql_params' => array()) /* ".@print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "/db1_cats/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('count($result) == 2; /* '.print_r($result, true).' */');
// print_r($GLOBALS_metadata['db1_cats']);
	assert("count(\$GLOBALS_metadata['db1_cats']['result_cache']) == 2; /* ".print_r($GLOBALS_metadata['db1_cats']['result_cache'], true).' */');
	assert("\$GLOBALS['tmp_rows_cache1'] == array (
	0 => 
	array (
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	),
	1 => 
	array (
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	),
	); /* ".var_export($GLOBALS['tmp_rows_cache1'], true)." */");
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1_cats/.[cat_id = 2]/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("current(\$result) == array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1_cats/.[cat_id = 2]/0";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.1',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[cat_id = 1]/toHash(`cat_code1c`, cat_name)";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('CODE0000001' => 'Category 1'); /* ".print_r($result, true).' */');
	
	$_GET['test_list'] = '~/Media/Default/newbilding/gallery/фото-1.jpg; ~/Media/Default/newbilding/gallery/фото-2.jpg; ;~/Media/Default/newbilding/gallery/фото-3.jpg ';
	$unipath = "\$_GET/test_list/split(';')/trim()/remove_empty()/regexp_replace('^~(\/.*)$', '<span class=\"obj_page-photo-wrp\"><img class=\"obj_page-photo-img\" src=\"$1\" alt=\"\" /></span>')/join(' ')";
	echo "<h3>--- $unipath ---</h3><xmp>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == \'<span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-1.jpg" alt="" /></span> <span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-2.jpg" alt="" /></span> <span class="obj_page-photo-wrp"><img class="obj_page-photo-img" src="/Media/Default/newbilding/gallery/фото-3.jpg" alt="" /></span>\'; /* '.print_r($result, true).' */');
	echo "</xmp>";
	
	$db1->exec("UPDATE tbl_categories SET cat_code1c = '00000000002', cat_deleted = 2, cat_sort_order = 1000.0 WHERE cat_id = 2");
	
	$unipath = "/db1/tbl_categories[1]/sql_result_cache(/tmp_rows_cache1)";
	echo "<h3>--- new Uni($unipath) ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = new Uni($unipath);
	assert('get_class($result) == "Uni"; /* '.print_r(get_class($result), true).' */');
	assert('empty($GLOBALS["tmp_rows_cache1"]); /* '.@print_r($GLOBALS["tmp_rows_cache1"], true).' */');
	
	echo "<h3>--- \$uni->rewind() ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result_rewind = $result->rewind();
	assert("!empty(\$result_rewind); /* ".var_export($result_rewind, true).' */');
// $GLOBALS['unipath_debug'] = false;

	echo "<h3>--- \$uni->valid() ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result_valid = $result->valid();
// $GLOBALS['unipath_debug'] = false;
	assert("\$result_valid == true; /* ".var_export($result_valid, true).' */');

	echo "<h3>--- \$uni->current() ---</h3>";
	$result_current = $result->current();
	assert("\$result_current == array(
		'cat_id' => '1',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 1',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => 'CODE0000001',
		'cat_deleted' => '0',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	); /* ".print_r($result_current, true).' */');
	
	echo "<h3>--- \$uni->key() ---</h3>";
	$result_key = $result->key();
	assert("\$result_key === 0; /* ".print_r($result_key, true).' */');
	
	echo "<h3>--- \$uni->next() ---</h3>";
	$result_next = $result->next();
	assert("\$result_next == array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	); /* ".var_export($result_next, true).' */');
	
	echo "<h3>--- \$uni->valid() ---</h3>";
	$result_valid = $result->valid();
	assert("\$result_valid == true; /* ".var_export($result_valid, true).' */');

	echo "<h3>--- \$uni->current() ---</h3>";
	$result_current = $result->current();
	assert("\$result_current == array(
		'cat_id' => '2',
		'cat_parent_id' => '0',
		'cat_name' => 'Category 2',
		'cat_url' => NULL,
		'cat_type' => NULL,
		'cat_code1c' => '00000000002',
		'cat_deleted' => '2',
		'cat_hidden' => '0',
		'cat_sort_order' => '1000.0',
		'cat_modified_stamp' => NULL,
		'cat_data_json_encoded' => NULL,
	); /* ".var_export($result_current, true).' */');
	
	$result_key = $result->key();
	assert("\$result_key === 1; /* ".var_export($result_key, true).' ('.var_export($result->current_cursor_result['metadata'], true).') */');
	
	echo "<h3>\$result->next()</h3>";
// 	$GLOBALS['unipath_debug'] = true;
	$result_next = $result->next();
	assert("\$result_next == null /* ".var_export($result_next, true).' */');
// 	$GLOBALS['unipath_debug'] = false;
	
	$result_current = $result->current();
	assert("\$result_current == null /* ".var_export($result_current, true).' */');
	
	$result_key = $result->key();
	assert("\$result_key === null; /* ".var_export($result_key, true).' */');
	
	$result_rewind = $result->rewind();
	assert("!empty(\$result_rewind); /* ".print_r($result_rewind, true).' */');

// 	$GLOBALS['unipath_debug'] = true;
	foreach($result as $key => $val) {
		switch($key) {
			case 0:
				$key0_found = true;
				assert("/* $key */ \$val == array(
					'cat_id' => '1',
					'cat_parent_id' => '0',
					'cat_name' => 'Category 1',
					'cat_url' => NULL,
					'cat_type' => NULL,
					'cat_code1c' => 'CODE0000001',
					'cat_deleted' => '0',
					'cat_hidden' => '0',
					'cat_sort_order' => '1000.0',
					'cat_modified_stamp' => NULL,
					'cat_data_json_encoded' => NULL,
				); /* ".print_r($val, true).' */');
				
				$val2 = $val['cat_name'];
				assert('is_string($val2); /* '.print_r($val2, true).' */');
				break;
			case 1:
				$key1_found = true;
				assert("/* $key */ \$val == array(
					'cat_id' => '2',
					'cat_parent_id' => '0',
					'cat_name' => 'Category 2',
					'cat_url' => NULL,
					'cat_type' => NULL,
					'cat_code1c' => '00000000002',
					'cat_deleted' => '2',
					'cat_hidden' => '0',
					'cat_sort_order' => '1000.0',
					'cat_modified_stamp' => NULL,
					'cat_data_json_encoded' => NULL,
				); /* ".var_export($val, true).' */');
				break;
			default:
				var_dump($key, $val);
		}
	}
	assert('$key0_found == true and $key1_found == true; /* '.var_export(array('key0_found' => $key0_found, 'key1_found' => $key1_found), true).' */');
// $GLOBALS['unipath_debug'] = true;
	
	$unipath = "/db1/tbl_categories[]/first()/if([cat_id = 2], `cat_id == 2`, `cat_id != 2`)";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "cat_id != 2"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "/db1/tbl_categories[cat_parent_id=0 and like(`cat_name`, `Category 1`)]/delete()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	$rows = $GLOBALS['db1']->query("SELECT cat_id, cat_parent_id FROM tbl_categories")->fetchAll(PDO::FETCH_ASSOC);
	assert('$rows == array(array("cat_id" => 2, "cat_parent_id" => 0)); /* '.print_r($rows, true).' */');
// 	assert('$result == "cat_id != 2"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = "Uni(/db1/tbl_categories[cat_id=2]/0)[cat_parent_id] = 999";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = new Uni("/db1/tbl_categories[cat_id=2]/0");
	$result['cat_parent_id'] = 999;
	$rows = $GLOBALS['db1']->query("SELECT cat_id, cat_parent_id FROM tbl_categories")->fetchAll(PDO::FETCH_ASSOC);
	assert('$rows == array(array("cat_id" => 2, "cat_parent_id" => 999)); /* '.print_r($rows, true).' */');

	$unipath = "/db1/tbl_categories[like(`cat_name`, `Category 2`)]/delete()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	$rows = $GLOBALS['db1']->query("SELECT cat_id FROM tbl_categories")->fetchAll(PDO::FETCH_ASSOC);
	assert('$rows == array(); /* '.print_r($rows, true).' */');
// // $GLOBALS['unipath_debug'] = false;

	// TODO Fatal Error! UniPath.__cursor_database(eval): Unknown cursor command! (/db/users[u_id=7123])
	$unipath = "/db/users[u_id=7123]/u_info"; // need ../0/u_info
	
	// TODO isagency = '' (boolean)
	// <b>Notice</b>:  UniPath: PDO: execute() return false! 00000;; (UPDATE users SET u_info = '{"publication_price":50}' WHERE u_id = '72312' AND u_login = 'Kati25vaxr' AND u_password = '182384' AND u_address = 'Адлер' AND u_fio = 'Вахрамова Екатерина Николаевна' AND u_email = 'vahramova1984@mail.ru' AND u_actived = '1' AND u_mailsended = '1' AND u_regdate = '2012-03-31 23:55:50.146315' AND u_isagency = '' AND acc_id = '72312' AND u_type = 'standart' AND u_info = 'a:1:{s:17:"publication_price";i:50;}') in <b>/srv/yaws/www_sozhi/unipath.php</b> on line <b>3939</b><br />
	
	$unipath = "/_GET/split(';')";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result["test_list"] == array("~/Media/Default/newbilding/gallery/фото-1.jpg"," ~/Media/Default/newbilding/gallery/фото-2.jpg"," ","~/Media/Default/newbilding/gallery/фото-3.jpg "); /* '.print_r($result['test_list'], true).' */');
// $GLOBALS['unipath_debug'] = false;

	$GLOBALS['row_data'] = array('houseref' => ';;открытый поселок;', 'empty_string' => '', 'zero_value' => 0);
	$unipath = '/row_data/houseref/split(`;`)/remove_empty()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("открытый поселок"); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = '/row_data/not_exist/ifNull(`(none)`)';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "(none)"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = '/row_data/if([houseref = `;;открытый поселок;` and `;;открытый поселок;` = houseref], `yes`, `(none)`)';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "yes"; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = '/row_data/empty_string/ifNull(`(none)`)';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == ""; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;

	$unipath = '/row_data/zero_value/ifNull(`(none)`)';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == 0; /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "db1/table1[id=1]/columns(chunked(table1.Data, 10000, 3000), `table1.Id AS id1, table1.ContentType_id, REPLACE('abcd)asd', ')', '(')`)/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
// $GLOBALS['unipath_debug_parse'] = true;
	$result = uni($unipath);
	assert("\$result = array('sql_query' => \"SELECT SUBSTRING(table1.Data, 1, 3000) AS uni_chunk_0_table1_Data, SUBSTRING(table1.Data, 3001, 3000) AS uni_chunk_1_table1_Data, SUBSTRING(table1.Data, 6001, 3000) AS uni_chunk_2_table1_Data, SUBSTRING(table1.Data, 9001, 3000) AS uni_chunk_3_table1_Data, table1.Id AS id1, table1.ContentType_id, REPLACE('abcd)asd', ')', '(') FROM table1 WHERE id = 1\", 'sql_binds' => array()); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
// $GLOBALS['unipath_debug_parse'] = false;
	
	$_POST['site_map'] = $GLOBALS['site_map'] = array('Недвижимость' => array(
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
	$result = uni($unipath);
	assert("\$result == array('title' => 'Элитная недвижимость'); /* ".print_r($result, true)." */"); 
	
	$GLOBALS['site_map'] = $_POST['site_map'];
	$unipath = "/site_map/Недвижимость/.[cont_type_id = 28]/first()/key()/wrap('/')";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == '/hotel/'; /* ".print_r($result, true)." */");
//$GLOBALS['unipath_debug'] = false;

	$unipath = '$_POST/site_map/Недвижимость/replace(i%s, array(`Название`=title, `ID`=cont_type_id, `URL`=key(), Number=pos()))';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
// print_r(uni($unipath));
	$result = uni($unipath);
	assert("\$result == array(
		'invproject' => array('Название' => 'Инвестиционные проекты', 'ID' => 29, 'URL' => 'invproject', 'Number' => 9),
		'ipoteka' => array('Название' => 'Ипотека', 'ID' => null, 'URL' => 'ipoteka', 'Number' => 10)
		); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;

	$unipath = '/_POST/site_map/\'Недвижимость\'/i%s';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
// print_r(uni($unipath));
	$result = uni($unipath);
	assert("\$result == array(
		'invproject' => array('title' => 'Инвестиционные проекты', 'cont_type_id' => 29, 'versioned' => true),
		'ipoteka' => array('title' => 'Ипотека')
	); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/default_Orchard_Framework_ContentItemRecord[ContentType_id=11,14 and Published=1 and (like(Data, N'%abc%') or ContentItemRecord_id = 'abc' or like(Title, N'%abc%'))]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => \"SELECT * FROM default_Orchard_Framework_ContentItemRecord WHERE ContentType_id IN (11,14) and Published = 1 and (Data LIKE N'%abc%' or ContentItemRecord_id = 'abc' or Title LIKE N'%abc%')\", 'sql_params' => array()); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;
	
	$search_query = 'abc123';
	$unipath = "/db1/objects_for_admin[obj_deleted = 0 and (ilike(title, '%{$search_query}%') or ilike(url, '%{$search_query}%') or ilike(city_part, '%{$search_query}%') or ilike(street, '%{$search_query}%'))]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('sql_query' => \"SELECT * FROM objects_for_admin WHERE obj_deleted = 0 and (title ILIKE '%{$search_query}%' or url ILIKE '%{$search_query}%' or city_part ILIKE '%{$search_query}%' or street ILIKE '%{$search_query}%')\", 'sql_params' => array()); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$unipath = "site_map/Недвижимость/*/cont_type_id";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('newbilding' => 11, 'room' => 18, 'house' => 15, 'zemuch' => 14, 'hotel' => 28, 'office' => 27, 'invproject' => 29); /* ".print_r($result, true).' */');
	
	$_POST = array(array (
		'Code' => 'УТ0001039',
		'Description' => 'Бытовая техника',
		'Родитель' => 'FunBit',
		'Родитель--Код' => 'УТ0000883',
		'DeletionMark' => 'ложь',
		));
	$unipath = '/_POST/toHash(`Code`, array(`cat_code1c`=Code, `cat_name`=Description, `parent_code1c`=./`Родитель--Код`, `parent_name`=./`Родитель`, `cat_hidden` = DeletionMark/replace_string(`ложь`=0,`истина`=1)/php_intval()))';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result = array("УТ0001039" => array("cat_code1c" => "УТ0001039", "cat_name" => "Бытовая техника", "parent_code1c" => "УТ0000883", "parent_name" => "FunBit", "cat_hidden" => 0)); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false;
	
	$_POST = array('login' => 'test', 'password' => '123');
	$unipath = "db1/users[u_login = /_POST/login and u_password_hash = /_POST/password]/asSQLQuery()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == array('sql_query' => \"SELECT * FROM users WHERE u_login = 'test' and u_password_hash = '123'\", 'sql_params' => array()); /* ".print_r($result, true).' */');
	
    $_POST = array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1);
	$unipath = '$_POST/unset(`password2`)/unset(`submitted`)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == array('u_login' => 'test', 'u_password_hash' => 123); /* ".print_r($result, true).' */');
	
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
    $unipath = '$_POST/unset(`password2`)/unset(`submitted`)/insert_into(/db1/users)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == 1; /* ".print_r($result, true).' */');
	
	$unipath = "/_POST/u_login/php_intval()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == 0; /* ".print_r($result, true).' */');
	
	$_POST = array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1);
	$unipath = "/_POST/.[1]";
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1); /* ".print_r($result, true).' */');
// echo '<xmp>';

	$_POST = array('u_login' => 'test', 'u_password_hash' => 123, 'password2' => '123', 'submitted' => 1);
	$unipath = "/_POST/.[. > 100 and . < 124]";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == array("u_password_hash" => 123, "password2" => "123"); /* '.json_encode($result).' */');
// $GLOBALS['unipath_debug'] = false;
// echo '<xmp>';

	// TODO '/current_model/asClass()/singleton()/rubricnums/.[key()=1]/first()/.[.=128]'

	$_POST[] = array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 0));
	$_POST[] = array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 123));
	$unipath = "/_POST/.[1=1 and (DataXML/zemucharea = 'Лазаревский' or DataXML/zemucharea = 'Хостинский') and DataXML/priceuch <= 0]";
	echo "<h3>--- $unipath ---</h3><xmp>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(0 => array('DataXML' => array('zemucharea' => 'Лазаревский', 'priceuch' => 0))); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;
	echo '</xmp>';


	$_POST = array(
		array('vers.Data' => '<Data><zemuch><a>123</a></zemuch></Data>'), 
		array('vers.Data' => '<Data><zemuch><a>321</a></zemuch></Data>'));
	$unipath = "/_POST/set(*/DataXML, */vers.Data/XMLtoArray()/Data/zemuch)";
	echo "<h3>--- $unipath ---</h3><xmp>";
// print_r(uni($unipath));
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert("\$result == array(
		array('vers.Data' => '<Data><zemuch><a>123</a></zemuch></Data>', 'DataXML' => array('a' => 123)), 
		array('vers.Data' => '<Data><zemuch><a>321</a></zemuch></Data>', 'DataXML' => array('a' => 321))
		); /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false;
	echo '</xmp>';

	$unipath = "/_POST/0/vers.Data/asXML()/zemuch";
	echo "<h3>--- $unipath ---</h3><xmp>";
// $GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
// print_r($result);
	assert('$result[0] instanceof DOMElement; /* '.var_export($result, true).' */');
	assert('$result[0]->nodeName == "zemuch"; /* '.var_export($result, true).' */');
	echo '</xmp>';
// unset($GLOBALS['unipath_debug']);

	$_POST[0] = array('u_id' => 6842,'u_data_json_encoded' => '{"FileExtension":"png","Avatar":"\/Media\/Default\/Avatars\/6842.png"}');
//$GLOBALS['unipath_debug'] = true;
	$unipath = "/_POST/0/set(main_photo, u_data_json_encoded/asJSON()/Avatar)/main_photo";
	echo "<h3>--- $unipath ---</h3>";
//echo '<xmp>';
	$result = uni($unipath);
// print_r($result);
	assert('$result == "/Media/Default/Avatars/6842.png"; /* '.print_r($result, true).' */');
//echo '</xmp>';
//unset($GLOBALS['unipath_debug']

	$_POST['test_xml'] = '<?xml version="1.0" encoding="UTF-8"?><html>
		<head><title>test_xml</title></head>
		<body>
			<h1>H1</h1>
			<p>paragraph 1</p>
			<h1>H1 second</h1>
			<p>paragraph 2</p>
		</body></html>';
	$unipath = '/_POST/test_xml/asXML()/head/title/nodeValue';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "test_xml"; /* '.var_export($result, true).' */');
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$_POST['test_xml'] = '<text:h text:style-name="Heading_20_1" outline-level="1">3-комн квартира на ул Труда</text:h><text:p text:style-name="Text_20_body">Улица: Труда</text:p><text:p text:style-name="Text_20_body">Общая площадь: 85 м² </text:p><text:p text:style-name="Text_20_body">Этажность: 12 </text:p><text:p text:style-name="Text_20_body">Этаж: 6 </text:p><text:p text:style-name="Text_20_body">Цена: <text:span text:style-name="T1">5800000</text:span> руб. </text:p><text:p text:style-name="Standard"/><table:table table:name="ХарактеристикиОбъекта" table:style-name="ХарактеристикиОбъекта"><table:table-column table:style-name="ХарактеристикиОбъекта.A"/><table:table-column table:style-name="ХарактеристикиОбъекта.B"/><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell 
	office:value-type="string"><text:p text:style-name="P1">Улица</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Театральная 11</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Класс</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">премиум</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">9</text:p></table:table-cell></table:table-row></table:table><text:p/>';
	$unipath = '/_POST/test_xml/asXML()/h/0/nodeValue';
	echo "<h3>--- $unipath ---</h3><xmp>";
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "3-комн квартира на ул Труда"; /* '.var_export($result, true).' */');
// $GLOBALS['unipath_debug'] = false; 
	echo '</xmp>';

	$unipath = '/_POST/test_xml/asXML()/p/4/nodeValue';
	echo "<h3>--- $unipath ---</h3>";
//$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$result = uni($unipath);
//print_r($result);
	assert('$result == "Цена: 5800000 руб. "; /* '.print_r($result, true).' */');
//$GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = "/_POST/test_xml/asXML()/*/.[key()='h','table']/all()";
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
echo '<xmp>';
	$result = uni($unipath);
print_r($result);
	assert('$result == array("3-комн квартира на ул Труда", 1 => \'<table:table-column table:style-name="ХарактеристикиОбъекта.A"/><table:table-column table:style-name="ХарактеристикиОбъекта.B"/><table:table-row><table:table-cell office:value-type="string"><text:p text:style-name="P1">Район</text:p></table:table-cell><table:table-cell office:value-type="string"><text:p text:style-name="P2">Центральный</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell 
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
	assert("\$result == \$_POST['test_xml']; /* ".print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

$GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$GLOBALS['filename'] = 'unipath_test.xml';
	file_put_contents('unipath_test.xml', $_POST['test_xml']);
	$unipath = '$filename/asFile()/contents()/asXML()/cache($odt)';
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath);
	assert("\$GLOBALS['odt'] == \$_POST['test_xml']; /* ".json_encode($GLOBALS['odt'])." */");
$GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = 'cache($odt)/p';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == array("Улица: Труда", "Общая площадь: 85 м² ", "Этажность: 12 ", "Этаж: 6 ", "Цена: <text:span text:style-name=\\"T1\\">5800000</text:span> руб. ", "", ""); /* '.print_r($result, true).' */');
	//print_r($result);
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$unipath = '/odt/table/table-row/toArray()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
echo '<xmp>';
	$result = uni($unipath);
	assert("\$result == array('<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Район</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">Центральный</text:p></table:table-cell>', 
	'<table:table-cell 
	office:value-type=\"string\"><text:p text:style-name=\"P1\">Улица</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">Театральная 11</text:p></table:table-cell>', 
'<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Класс</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">премиум</text:p></table:table-cell>', 
'<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">9</text:p></table:table-cell>'); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; 
echo '</xmp>';

	$unipath = 'cache($odt)/table/0/*/toHash()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
// echo '<xmp>';
	$result = uni($unipath);
	assert("\$result == array('table-column' => array('', ''), 'table-row' => array('<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Район</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">Центральный</text:p></table:table-cell>', 
	'<table:table-cell 
	office:value-type=\"string\"><text:p text:style-name=\"P1\">Улица</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">Театральная 11</text:p></table:table-cell>', 
'<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Класс</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">премиум</text:p></table:table-cell>', 
'<table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P1\">Кол-во этажей</text:p></table:table-cell><table:table-cell office:value-type=\"string\"><text:p text:style-name=\"P2\">9</text:p></table:table-cell>')); /* ".print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; 
// echo '</xmp>';

	$unipath = 'cache($odt)/h/0/attrs()';
	echo "<h3>--- $unipath ---</h3>";
// $GLOBALS['unipath_debug'] = true; 
	echo '<xmp>';
	$result = uni($unipath);
	assert('$result == array("style-name"=>"Heading_20_1", "outline-level"=>"1"); /* '.print_r($result, true).' */');
// $GLOBALS['unipath_debug'] = false; 
	echo '</xmp>';

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
	echo "<h3>--- $unipath = 321 ---</h3>";
	uni($unipath, 321);
	assert('$_POST[123] == 321; /* '.print_r($_POST, true).' */');
	
	$unipath = '/_POST/favorites/123';
	unset($_POST['favorites']);
	echo "<h3>--- $unipath ---</h3>";
	uni($unipath, array('name' => 'test'));
	assert('$_POST["favorites"][123] == array("name" => "test"); /* '.print_r($_POST, true).' */');
	
	$unipath = '_POST/favorites/123';
	unset($_POST['favorites']);
	echo "<h3>--- $unipath = array('name' => 'test2') ---</h3>";
	uni($unipath, array('name' => 'test2'));
	assert('$_POST["favorites"][123] == array("name" => "test2"); /* '.print_r($GLOBALS['_POST'], true).' */');

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
// $GLOBALS['unipath_debug'] = true; 
	echo '<xmp>';
	$result = uni($unipath);
	assert('$result == "<favorites><name>test2</name></favorites>"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; 
	echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '_POST/favorites/123/name/prepend(`name = `)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == "name = test2"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '_POST/favorites/123/name/append(` (name)`)';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == "test2 (name)"; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

	$_POST['favorites'] = array('6.jpg', '87.jpg');
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = '_POST/favorites/regexp_replace(`.+`, `<img class="c-image_viewer-preload-image c-image_viewer-switcher" data-src="/images/940x350/$0" alt="">`)/join()';
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert('$result == \'<img class="c-image_viewer-preload-image c-image_viewer-switcher" data-src="/images/940x350/6.jpg" alt=""><img class="c-image_viewer-preload-image c-image_viewer-switcher" data-src="/images/940x350/87.jpg" alt="">\'; /* '.print_r($result, true)." */");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';


// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = "url(`$__test_our_url?echo=abc123`)";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == '$__test_our_url?echo=abc123'; /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$unipath = "url(`$__test_our_url?echo=abc123`)/contents()";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == 'abc123'; /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
// 	$GLOBALS['url1'] = '/nedvizhimost/prodazha/1-komnatnie_kvartiry';
// 	$unipath = "/url1/url_seg(3)";
// 	echo "<h3>--- $unipath ---</h3>";
// 	$result = uni($unipath);
// 	assert("\$result == '1-komnatnie_kvartiry'; /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$GLOBALS['row'] = array('prd_id' => 1, 'prd_data_json_encoded' => '{"content_image":"","image0":"123.jpg","image0_alt":"xxx","image5_alt":"555","image":"imageX","image999":"image999.png"}');
	$unipath = "/row/prd_data_json_encoded/asJSON()/.[./key()/regexp_match('image[0-9]+$')]";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == array('image0' => '123.jpg', 'image999' => 'image999.png'); /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';
	
// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$GLOBALS['row'] = array('prd_id' => 1, 'prd_data_json_encoded' => '{"content_image":"","image0":"123.jpg","image0_alt":"xxx","image5_alt":"555","image":"imageX","image999":"image999.png"}');
	$unipath = "/row/prd_data_json_encoded/asJSON()/.[key()/regexp_match(`image[0-9]+$`)]";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == array('image0' => '123.jpg', 'image999' => 'image999.png'); /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

// $GLOBALS['unipath_debug'] = true; echo '<xmp>';
	$GLOBALS['row'] = array('prd_id' => 1, 'prd_data_json_encoded' => '{"content_image":"","image0":"123.jpg","image0_alt":"xxx","image5_alt":"555","image":"imageX","image999":"image999.png"}');
	$unipath = "/row/prd_data_json_encoded/asJSON()/.[key()/regexp_match(`image[0-9]+$`)]/regexp_replace(`.+`, `<b>$0</b>`)";
	echo "<h3>--- $unipath ---</h3>";
	$result = uni($unipath);
	assert("\$result == array('image0' => '<b>123.jpg</b>', 'image999' => '<b>image999.png</b>'); /* ".print_r($result, true)." */ ");
// $GLOBALS['unipath_debug'] = false; echo '</xmp>';

/*	$xml = '<rows>
		<row id="1"><cell index="1">1a</cell><cell index="2">2a</cell></row>
		<row id="2"><cell index="1">1b</cell><cell index="2">2b</cell></row>
	</rows>';
	var_dump(uni("xml/asXML()")); */
	
	$array1 = array('xml' => array('aaa' => 111, 'bbb' => 222, 'ccc' => 333));
	$unipath = 'array1/ArrayToXML()';
	echo "<h3>--- $unipath ---</h3><xmp>";
	$result = uni($unipath);
	assert('$result == "<xml><aaa>111</aaa><bbb>222</bbb><ccc>333</ccc></xml>"; /* '.print_r($result, true).' */');
	echo '</xmp>';

	$unipath = '/php:get_declared_classes()/0';
	echo "<h3>--- $unipath ---</h3>";
// 	$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "stdClass"; /* '.print_r($result, true).' */');
// 	$GLOBALS['unipath_debug'] = false;

	$unipath = '/php:get_declared_classes()/php-foreach:sprintf(`class %s;`, .)/0';
	echo "<h3>--- $unipath ---</h3>";
// 	$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "class stdClass;"; /* '.print_r($result, true).' */');
// 	$GLOBALS['unipath_debug'] = false;

	$unipath = "/db1/tbl_categories[]/php-foreach:sprintf(`%s (ID:%s), `, cat_name, cat_id)/join(``)";
	echo "<h3>--- $unipath ---</h3>";
// 	$GLOBALS['unipath_debug'] = true;
	$result = uni($unipath);
	assert('$result == "Category 1 (ID:1), Category 2 (ID:2), "; /* '.print_r($result, true).' */');
// 	$GLOBALS['unipath_debug'] = false;
}

if(isset($_GET['test_uniExtensions'])) {

	$all_funcs = get_defined_functions();
	foreach(scandir('.') as $filename) {
		if(preg_match('~^unipath\..+?\.php$~', $filename) == false
		|| $filename == 'unipath_tests.php') continue;

		echo "<h2>$filename</h2>";
		include_once $filename;
		
		$all_funcs2 = get_defined_functions();
		$new_funcs = array_diff($all_funcs2['user'], $all_funcs['user']);
// 	$GLOBALS['unipath_debug'] = true;
		foreach($new_funcs as $func_name)
		if(preg_match('~^_tests_~', $func_name))
			call_user_func($func_name);
			
		$all_funcs = $all_funcs2;
	}
}