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
//	0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc'),
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
//	0 => array('id' => 1, 'field1' => 123, 'field2' => 'abc', 'ref_id' => 1, 'field_from_table2' => 1.23),
//  1 => array('id' => 2, 'field1' => 456, 'field2' => 'abc', 'ref_id' => 2, 'field_from_table2' => 4.56)
// );

uni("db1/table1[id = 1,2]+table2[table1.id = table2.ref_id]/order_by('table1.field1 DESC')/columns('id, field_from_table2, table2.id AS id2')")
// array(
//  0 => array('id' => 2, 'field_from_table2' => 4.56, 'id2' => 102),
//	1 => array('id' => 1, 'field_from_table2' => 1.23, 'id2' => 101)
// );