UniPath
=======

The library for universal access to any data from a PHP script (similar to xpath with jquery philosophy).

Current version: [unipath-2.4rc2.php](https://github.com/SaemonZixel/unipath.php/raw/master/unipath-2.4.php)
Old version: [unipath-2.2.3.php](https://github.com/SaemonZixel/unipath.php/raw/master/unipath.php)\

DataBase
--------

	global $db1; // for 5.3 and upper

	$db1 = new PDO(...);
	
	uni('/db1/table1[]') 
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc' ),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// )
	
	uni('/db1/table1[]/0')
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc')
	
	uni('/db1/table1[]/1')
	// array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	
	uni('/db1/table1[]/first()')
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc')
	
	uni('/db1/table1[id = 1]') 
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'))
	
	uni("/db1/table1[id = 1 and field2 = 'abc']")
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'))

	uni("/db1/table1[field2 = 'abc']")
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// )
	
	uni("/db1/table1[field2 = 'abc']/1")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc')

	uni("/db1/table1[field2 = 'abc']/1/id")
	// 1

	uni("/db1/table1[field1 >= 400 and field2 < 700 and id = 1,2,3]/0")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc')

	uni("/db1/table1/id")
	// array(1, 2)

	uni("/db1/table1[table1.id = 1,2]+table2[table1.id = table2.ref_id])")
	// array(
	//   0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc', 'ref_id' => 1, 'field_from_table2' => 1.23),
	//   1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc', 'ref_id' => 2, 'field_from_table2' => 4.56)
	// )

	uni("/db1/table1[table1.id = 1,2]+table2[table1.id = table2.ref_id]/order_by('table1.field1 DESC')/columns('table1.id, field_from_table2, table2.id AS id2')/limit(0, 2)")
	// array(
	//   0 => array('id' => 2, 'field_from_table2' => 4.56, 'id2' => 102),
	//   1 => array('id' => 1, 'field_from_table2' => 1.23, 'id2' => 101)
	// )

	uni("/db1/table1[table1.id = 1,2]+table2[table1.id = table2.ref_id]/order_by('table1.field1 DESC')/columns('table1.id, field_from_table2, table2.id AS id2, ifNULL(field1, 999) as field1')/limit(0, 2)/asSQLQuery()")
	// array(
	//   'sql_query' => 'SELECT table1.id, field_from_table2, table2.id AS id2, ifNULL(field1, 999) as field1 FROM table1 LEFT JOIN table2 ON table1.id = table2.ref_id WHERE table1.id IN (1,2) ORDER BY table1.field1 DESC LIMIT 0, 2',
	//   'sql_params' => array()
	// )
	
	uni("/db1/alias('table1', 't1')[t1.id = 1,2]+table2-as-t2[t1.id = t2.ref_id]/asSQLQuery()")
	// array(
	//   'sql_query' => 'SELECT * FROM table1 AS t1 LEFT JOIN table2 AS t2 ON t1.id = t2.ref_id WHERE t1.id IN (1,2)',
	//   'sql_params' => array()
	// )
	// table_name-as-alias_name is syntactical sugar for alias() function
	
	uni("/db1/sql_query('SELECT * FROM table1')/0")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc')
	
	uni("/db1/table1[]/toHash('field1')")
	// array(
	//  123 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  456 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// )
	
	Bracket grouping of the WHERE-clauses is supported partially yet.
	
	
DataBase (UPDATE, INSERT, DELETE)
---------------------------------

	Warning: the beta version of the library may contain bugs, that can delete or damage your data in the database.

	Supported database drivers: ODBC, PDO, mysql_*, mysqli_*.
	
	For MS SQL Server there are special functions top(), sql_iconv(), like2(), chunked(), which have not been described yet.

	uni("/db1/table1[id = 1]", array('field1' => 999))
	// array('field1' => 999)
	
	uni("/db1/table1[id = 2]/0/field1", 888)
	// 888
	
	uni('/db1/table1[]')
	// array(
	//  0 => array('id' => 1, 'field1' => 999, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 888, 'field2' => 'abc')
	// )
	
	uni('/db1/table1[field2 = `abc`]', array('field1' => 123))
	// array('field1' => 123)
	
	uni('/db1/table1[]'); 
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 123, 'field2' => 'abc')
	// )
	
	uni('/db1/table1[]/1', array('field1' => 456))
	// array('field1' => 456)
	
	uni('/db1/table1[]/2', array('field1' => 789))
	// array('field1' => 789)
	//
	// Notice: the uni() will do a silent assignment! no Notice, no Warning!
	
	uni("/db1/table1/new_row()", array('id' => 3, 'field1' => 999, 'field2' => 'xxx'))
	// array('id' => 3, 'field1' => 999, 'field2' => 'xxx')
	
	uni("/db1/last_error()")
	// NULL
	
	uni("/db1/last_affected_rows()")
	// 1
	
	uni("/db1/last_insert_id()")
	// 3
	
	uni("/db1/table1[id = 3]/delete()")
	// array()
	
	uni("/db1/last_affected_rows()")
	// 1
	
Array
-----
	
	global $array1; // for 5.3 and upper

	$array1 = array('aaa' => 111, 'bbb' => 222, 'ccc' => 333)
	
	uni("/array1")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333)
	
	uni("/array1/aaa")
	// 111
	
	uni("/array1/xxx")
	// null
	
	uni("/array1/xxx/ifEmpty(999)")
	// 999
	
	uni("/array1/count()")
	// 3
	
	uni("/array1/.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333)
	//
	// Notice: dot "." works like self() in XPath
	
	uni("/array1/./././././.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("/array1/.[. > 200]")
	// array('bbb' => 222, 'ccc' => 333)
	
	uni("/array1/.[. > 200 and . < 300]")
	// array('bbb' => 222)
	
	uni("/array1/aab", 112)
	// 112
	
	uni("/array1/aa%s")
	// array('aaa' => 111, 'aab' => 112)
	
	uni("/array1/aa%s[. > 111]")
	// array('aab' => 112)
	
	// condition: to skip or not
	uni("/array1/[aaa > 200]")
	// null
	
Objects
-------
	global $mysqli_obj; // for 5.3 and upper
	
	$mysqli_obj = uni("/MySQLi/MySQLi('127.0.0.1', 'user', 'password', 'database1')")
	// object(mysqli)#1 { ... }
	
	uni("/mysqli_obj/host_info")
	// "127.0.0.1 via TCP/IP"
	
	uni("/mysqli_obj/query('SELECT * FROM `users` WHERE `user_banned` = 1')")
	// object(mysqli_result) {
	//		'current_field' => 0,
    //		'field_count' => 3,
	//		'lengths' => null,
    //		'num_rows' => 129006,
    //		'type' => 0,
    //	}

    uni("/mysqli_obj/query(```SELECT * FROM `users` WHERE `user_banned` = 1```)/fetch_assoc()")
    // array(
	//	'user_id' => 1,
	//	'user_name' => 'test',
	//	'user_banned' => 1
    // )

	
XML
---

	global $xml; // for 5.3 and upper

	$xml = '<rows>
		<row id="1"><cell index="1">111_AAA</cell><cell index="2">111_BBB</cell></row>
		<row id="2"><cell index="1">222_AAA</cell><cell index="2">222_BBB</cell></row>
	</rows>';
	uni("/xml/asXML()")
	// object(DOMElement)#7 (18) {
	//    ["nodeName"]=> 
	//    string(4) "rows"
	//    ["nodeValue"]=>
	//    string(16) "
	//        111_AAA111_BBB
	//        222_AAA222_BBB
	//    "
	//    ...
	// }
	
	uni("/xml/asXML()/nodeName")
	// 'rows'
	
	uni("/xml/asXML()/row")
	// array(
	//    0 =>
	//    object(DOMElement)#7 (18) {
	//       ["nodeName"]=>
	//       string(3) "row"
	//       ...
	//    },
	//    1 =>
	//    object(DOMElement)#3 (18) {
    //       ["nodeName"]=>
    //       string(3) "row"
	//       ...
	//    }
	// )
	
	
	uni("/xml/asXML()row/0")
	// object(DOMElement)#7 (18) {
	//    ["nodeName"]=> 
	//    string(4) "row"
	//    ["nodeValue"]=>
	//    string(16) "111_AAA111_BBB"
	//    ...
	// }
	
	
	uni("/xml/asXML()/row/0/cell")
	// array(
	//    0 =>
	//    object(DOMElement)#9 (18) {
	//       ["nodeName"]=>
	//       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "111_AAA"
	//       ...
	//    },
	//    1 =>
	//    object(DOMElement)#11 (18) {
    //       ["nodeName"]=>
    //       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "111_BBB"
    //       ...
	//    }
	// )
	
	uni("/xml/asXML()/row/cell")
	// array(
	//    0 =>
	//    object(DOMElement)#9 (18) {
	//       ["nodeName"]=>
	//       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "111_AAA"
	//       ...
	//    },
	//    1 =>
	//    object(DOMElement)#11 (18) {
    //       ["nodeName"]=>
    //       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "111_BBB"
    //       ...
	//    },
	//    2 =>
	//    object(DOMElement)#14 (18) {
    //       ["nodeName"]=>
    //       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "222_AAA"
    //       ...
	//    },
	//    3 =>
	//    object(DOMElement)#17 (18) {
    //       ["nodeName"]=>
    //       string(3) "cell"
	//       ["nodeValue"]=>
    //       string(2) "222_BBB"
    //       ...
	//    }
	// )
	
	uni("/xml/asXML()/row/0/cell/0/nodeValue")
	// '111_AAA'
	
	uni("/xml/asXML()/row/@id")
	// '1'
	
	uni("/xml/asXML()/row/*/@id")
	// array('1', '2')
	
	uni("/xml/asXML()/row/cell/*/nodeValue")
	// array('111_AAA', '111_BBB', '222_AAA', '222_BBB')
	
	global $array1;
	$array1 = array('items' => array('aaa' => 111, 'bbb' => 222, 'ccc' => 333));
	uni('/array1/ArrayToXML()')
	// '<items><aaa>111</aaa><bbb>222</bbb><ccc>333</ccc></items>'
	
Files
-----

	uni("/fs()/etc/passwd/contents()")
	// ... contents of the 'passwd' file ...
	
	$filename = '/etc/passwd';
	uni("/filename/asFile()/contents()")
	// ... contents of the 'passwd' file ...
	
	$dirname = '/etc';
	uni("/dirname/asDirectory()/passwd/contents()")
	// ... contents of the 'passwd' file ...
	
Image
-----

	uni("/fs()/images/image1.jpg/asImageFile()/resize(400, 500, 'fill')/crop(400, 500)/saveAs('/images/image1b.png')")
	// (resized and croped image saved as '/images/image1b.png')
	
class Uni
---------

Class Uni extends ArrayIterator and works like Array.

	$rows = new Uni('/db1/table1[]')
	// object(Uni)
	
	$rows[0]
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc')
	
	$rows[0] = array('field1' => 0)
	// array('field1' => 0)
	
	$rows[0]
	// array('id' => 1, 'field1' => 0, 'field2' => 'abc')
	
	$rows['0/field2'] = 'ddd'
	// 'ddd'
	
	$rows[0]
	// array('id' => 1, 'field1' => 0, 'field2' => 'ddd')
	
	$rows['0/field1,field2'] = array('field1' => 111, 'field1' => 'ccc');
	// array('field1' => 111, 'field1' => 'ccc')
	
	$rows[0]
	// array('id' => 1, 'field1' => 111, 'field2' => 'ccc')
	
	$rows['*/field2'] = 'eee';
	// 'eee'
	
	$rows['all()']
	// array(
	//  0 => array('id' => 1, 'field1' => 111, 'field2' => 'eee' ),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'eee')
	// )
	
	foreach($rows as $row) var_dump($row);
	// array('id' => 1, 'field1' => 111, 'field2' => 'ccc')
	// array('id' => 2, 'field1' => 123, 'field2' => 'abc')
	
Cache
-----

	uni('cached(/cached_var1, /db1/table1[id=1])/0')
	// array('id' => 1, 'field1' => 111, 'field2' => 'ccc');
	//
	// You can write cached($cached_var1, ...) it is equal
	
	uni('cached(/cached_var1)/0/id')
	// 1

	var_dump($GLOBALS['cached_var1'])
	// array('id' => 1, 'field1' => 111, 'field2' => 'ccc');
	
	uni('/cached_var1/0/id')
	// 1
	
Templating
----------
	
	uni('/db1/table1[]/ssprintf(`<option value="%s"%s>%s</option>`, id, if([id=2], ` selected`, ``), field2)/join(`\n`)')
	// <option value="1">ccc</option>
	// <option value="2" selected>abc</option>
	
Debugging
---------

	$GLOBALS['unipath_debug_sql'] = true; // show all sql queres
	
	$GLOBALS['unipath_debug'] = true; // activate debugging of execution process
	
	$GLOBALS['unipath_debug_parse'] = true; // activate debugging of parser process
	
	