UniPath
=======

Library for universal access to any data from PHP script (similar to xpath with jquery philosophy).

DataBase
--------

	$db1 = new PDO(...);
	uni('db1/table1[id = 1]'); 
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'));

	uni("db1/table1[id = 1 and field2 = 'abc']")
	// array(0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'));

	uni("db1/table1[field2 = 'abc']")
	// array(
	//  0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
	//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc')
	// );

	uni("db1/table1[field2 = 'abc']/1")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc');

	uni("db1/table1[field2 = 'abc']/1/id")
	// 1

	uni("db1/table1[field1 >= 400 and field2 < 700 and id = 1,2,3]/0")
	// array('id' => 1, 'field1' => 456, 'field2' => 'abc');

	uni("db1/table1/id")
	// array(1, 2);

	uni("db1/table1[id = 1,2]+table2[table1.id = table2.ref_id]")
	// array(
	//   0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc', 'ref_id' => 1, 'field_from_table2' => 1.23),
	//   1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc', 'ref_id' => 2, 'field_from_table2' => 4.56)
	// );

	uni("db1/table1[id = 1,2]+table2[table1.id = table2.ref_id]/order_by('table1.field1 DESC')/columns('id, field_from_table2, table2.id AS id2')")
	// array(
	//   0 => array('id' => 2, 'field_from_table2' => 4.56, 'id2' => 102),
	//   1 => array('id' => 1, 'field_from_table2' => 1.23, 'id2' => 101)
	// );
	
Array
-----

	$array1 = array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("array1/aaa")
	// 111
	
	uni("array1/xxx")
	// null
	
	uni("array1/xxx/ifEmpty(999)")
	// 999
	
	uni("array1/count()")
	// 3
	
	uni("array1/.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("array1/./././././.")
	// array('aaa' => 111, 'bbb' => 222, 'ccc' => 333);
	
	uni("array1/.[. > 200]")
	// array('bbb' => 222, 'ccc' => 333);
	
	uni("array1/.[. > 200 and . < 300]")
	// array('bbb' => 222);
	
XML
---

	$xml = '<rows>
		<row id="1"><cell index="1">1a</cell><cell index="2">2a</cell></row>
		<row id="2"><cell index="1">1b</cell><cell index="2">2b</cell></row>
	</rows>';
	uni("xml/asXML()/rows/0/row/1/cell/0")
	// '1b'
	
	uni("xml/asXML()/rows/0/row/1/cell")
	// array('1b', '2b');
	
	uni("xml/asXML()/rows/0/row/1")
	// '<cell index="1">1b</cell><cell index="2">2b</cell>'
	
	uni("xml/asXML()/rows/0/row")
	// array(
	//   '<cell index="1">1a</cell><cell index="2">2a</cell>',
	//   '<cell index="1">1b</cell><cell index="2">2b</cell>'
	// );
	
	$array1 = array('items' => array('aaa' => 111, 'bbb' => 222, 'ccc' => 333));
	uni('array1/ArrayToXML()')
	// '<items><aaa>111</aaa><bbb>222</bbb><ccc>333</ccc></items>'
	
Files
-----

	uni("fs()/etc/passwd/contents()")
	// ... contents of the file 'passwd' ...
	
	$filename = '/etc/passwd';
	uni("filename/asFile()/contents()")
	// ... contents of the file 'passwd' ...
	
	$dirname = '/etc';
	uni("dirname/asDirectory()/passwd/contents()")
	// ... contents of the file 'passwd' ...
	
Image
-----

	uni("fs()/images/image1.jpg/asImageFile()/resize(400, 500, 'fill')/crop(400, 500)/saveAs('/images/image1b.png')")
	// (save resized and croped image as '/images/image1b.png')