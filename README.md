UniPath
=======

Library for universal access to any data from PHP script (similar to xpath with jquery philosophy).

DataBase
--------

	global $db1; // for 5.3 and upper

	$db1 = new PDO(...);
	
	uni('/db1/table1[]/all()'); 
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc' ),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// );
	
	uni('/db1/table1[]/0'); 
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc');
	
	uni('/db1/table1[]/1'); 
	// array('id' => 2, 'field1' => 456, 'field2' => 'abc');
	
	uni('/db1/table1[]/first()'); 
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc');
	
	uni('/db1/table1[id = 1]/all()'); 
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'));
	
	uni('/db1/table1[id = 1]'); 
	// object(Uni) (
	//	0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc')
	// );
	//
	// Notice: class Uni extends ArrayIterator and work like Array
	
	uni("/db1/table1[id = 1 and field2 = 'abc']/all()")
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'));

	uni("/db1/table1[field2 = 'abc']/all()")
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// );
	
	uni("/db1/table1[field2 = 'abc']/1")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc');

	uni("/db1/table1[field2 = 'abc']/1/id")
	// 1

	uni("/db1/table1[field1 >= 400 and field2 < 700 and id = 1,2,3]/0")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc');

	uni("/db1/table1/id")
	// array(1, 2);

	uni("/db1/table1[id = 1,2]+table2[table1.id = table2.ref_id]/all()")
	// array(
	//   0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc', 'ref_id' => 1, 'field_from_table2' => 1.23),
	//   1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc', 'ref_id' => 2, 'field_from_table2' => 4.56)
	// );

	uni("/db1/table1[id = 1,2]+table2[table1.id = table2.ref_id]/order_by('table1.field1 DESC')/columns('id, field_from_table2, table2.id AS id2')/all()")
	// array(
	//   0 => array('id' => 2, 'field_from_table2' => 4.56, 'id2' => 102),
	//   1 => array('id' => 1, 'field_from_table2' => 1.23, 'id2' => 101)
	// );
	
	uni("/db1/table1[]/toHash('field1')")
	// array(
	//  123 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  456 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// );
	
	uni("/db1/table1[id = 1]", array('field1' => 999));
	// array('field1' => 999);
	
	uni("/db1/table1[id = 2]/0/field1", 888)
	// 888
	
	uni('/db1/table1[]/all()'); 
	// array(
	//  0 => array('id' => 1, 'field1' => 999, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 888, 'field2' => 'abc')
	// );
	
	uni('/db1/table1[field2 = `abc`]', array('field1' => 123))
	// array('field1' => 123);
	
	uni('/db1/table1[]/all()'); 
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 123, 'field2' => 'abc')
	// );
	
	uni('/db1/table1[]/1', array('field1' => 456))
	// array('field1' => 456);
	
	uni('/db1/table1[]/2', array('field1' => 789))
	// array('field1' => 789);
	//
	// Notice: the uni will do silent assignment! no Notice, no Warning!
	
	$rows = uni('/db1/table1[1=1]'); 

	print_r($rows[0]);
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'));
	
	print_r($rows[1]);
	// array(1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc'));
	
	print_r($rows[2]);
	// null
	
	print_r($rows['0/field1'])
	// 123
	
	
Array
-----
	
	global $array1; // for 5.3 and upper

	$array1 = array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("/array1")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("/array1/aaa")
	// 111
	
	uni("/array1/xxx")
	// null
	
	uni("/array1/xxx/ifEmpty(999)")
	// 999
	
	uni("/array1/count()")
	// 3
	
	uni("/array1/.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	//
	// Notice: dot "." is work like self() in XPath
	
	uni("/array1/./././././.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("/array1/.[. > 200]")
	// array('bbb' => 222, 'ccc' => 333);
	
	uni("/array1/.[. > 200 and . < 300]")
	// array('bbb' => 222);
	
	uni("/array1/aab", 112);
	// 112
	
	uni("/array1/aa%s")
	// array('aaa' => 111, 'aab' => 112);
	
	uni("/array1/aa%s[. > 111]")
	// array('aab' => 112);
	
XML
---

	global $xml; // for 5.3 and upper

	$xml = '<rows>
		<row id="1"><cell index="1">1a</cell><cell index="2">2a</cell></row>
		<row id="2"><cell index="1">1b</cell><cell index="2">2b</cell></row>
	</rows>';
	uni("/xml/asXML()/rows/0/row/1/cell/0")
	// '1b'
	
	uni("/xml/asXML()/rows/0/row/1/cell")
	// array('1b', '2b');
	
	uni("/xml/asXML()/rows/0/row/1")
	// '<cell index="1">1b</cell><cell index="2">2b</cell>'
	
	uni("/xml/asXML()/rows/0/row")
	// array(
	//   '<cell index="1">1a</cell><cell index="2">2a</cell>',
	//   '<cell index="1">1b</cell><cell index="2">2b</cell>'
	// );
	
	$array1 = array('items' => array('aaa' => 111, 'bbb' => 222, 'ccc' => 333));
	uni('/array1/ArrayToXML()')
	// '<items><aaa>111</aaa><bbb>222</bbb><ccc>333</ccc></items>'
	
Files
-----

	uni("/fs()/etc/passwd/contents()")
	// ... contents of the file 'passwd' ...
	
	$filename = '/etc/passwd';
	uni("/filename/asFile()/contents()")
	// ... contents of the file 'passwd' ...
	
	$dirname = '/etc';
	uni("/dirname/asDirectory()/passwd/contents()")
	// ... contents of the file 'passwd' ...
	
Image
-----

	uni("/fs()/images/image1.jpg/asImageFile()/resize(400, 500, 'fill')/crop(400, 500)/saveAs('/images/image1b.png')")
	// (save resized and croped image as '/images/image1b.png')
	
class Uni
---------

	$rows = new Uni('/db1/table1[]');
	// object(Uni)
	
	$rows[0]
	// array('id' => 1, 'field1' => 123, 'field2' => 'abc');
	
	$rows[0] = array('field1' => 0);
	// array('field1' => 0);
	
	$rows[0]
	// array('id' => 1, 'field1' => 0, 'field2' => 'abc');
	
	$rows['0/field2'] = 'ddd';
	// 'ddd';
	
	$rows[0]
	// array('id' => 1, 'field1' => 0, 'field2' => 'ddd');
	
	$rows['0/field1,field2'] = array('field1' => 111, 'field1' => 'ccc');
	// array('field1' => 111, 'field1' => 'ccc');
	
	$rows[0]
	// array('id' => 1, 'field1' => 111, 'field2' => 'ccc');
	
	$rows['*/field2'] = 'eee';
	// 'eee';
	
	$rows['all()']
	// array(
	//  0 => array('id' => 1, 'field1' => 111, 'field2' => 'eee' ),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'eee')
	// );
	
	