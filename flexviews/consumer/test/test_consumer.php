<?php 
require_once('../flexcdc.php');
/*
$cdc = new FlexCDC(	parse_ini_file('./test_consumer.ini', true));
$cdc->capture_changes();
exit;
*/

$settings = parse_ini_file('./test_consumer.ini', true);
class ConsumerTest extends PHPUnit_Framework_TestCase
{   
    public function testConstructor() {
	global $settings;
    
        $cdc = new FlexCDC($settings);
        $this->assertEquals('FlexCDC', get_class($cdc));
 
        $this->assertTrue($cdc->get_source() && $cdc->get_dest());
        $cdc->raiseWarnings = false;
        $conn = $cdc->get_source();
        #RESET THE DB TO GET TO KNOWN GOOD STATE
    	$this->assertTrue(mysql_query('DROP DATABASE IF EXISTS test', $conn));
    	$this->assertFalse(!mysql_query('RESET MASTER', $conn));
    	return $cdc;
        
    }
    
    /**
     * @depends testConstructor
     */
    public function testSetup($cdc) {
    	$cdc->setup();
    	$stmt = mysql_query('SELECT * FROM test.binlog_consumer_status', $cdc->get_dest());
		$row  = mysql_fetch_assoc($stmt);
	  	$this->assertTrue(!empty($row));
    	return $cdc;
    }
 
    /**
     * @depends testSetup
     */
    public function testAutochangelog($cdc)
    {   global $settings; 
    	$conn = $cdc->get_source();
    	   	
    	$this->assertTrue(mysql_query('CREATE TABLE test.t1(c1 int primary key) engine=innodb', $conn));                
        $this->assertTrue(mysql_query('INSERT INTO test.t1(c1) values (1),(2),(3)', $conn));
        $cdc->capture_changes();
                
        $stmt = mysql_query('SELECT * FROM test.binlog_consumer_status', $conn);
		$row  = mysql_fetch_assoc($stmt);
	  	$this->assertTrue(!empty($row));
	  		

		$sql = "select sum(dml_type),sum(c1), count(*) from test.test_t1";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 3);
		$this->assertEquals($row[1], 6);
		$this->assertEquals($row[2], 3);
		$this->assertTrue(mysql_query('UPDATE test.t1 set c1 = c1 + 10', $conn));
        $stmt = mysql_query('SHOW MASTER STATUS', $conn);
		$row  = mysql_fetch_assoc($stmt);		
        
		$cdc->capture_changes();

		$sql = "select sum(dml_type), sum(c1), count(*) from test.test_t1";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 3);
		$this->assertEquals($row[1], 48);
		$this->assertEquals($row[2], 9);

        $this->assertTrue(mysql_query('DELETE FROM test.t1', $conn));

        $cdc->capture_changes();
        
		$sql = "select sum(dml_type), sum(c1), count(*) from test.test_t1";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 0);
		$this->assertEquals($row[1], 84);
    	$this->assertEquals($row[2], 12);
    	
    	$this->assertTrue(mysql_query("truncate table test.test_t1", $cdc->get_dest()));
    	$this->assertTrue(mysql_query('alter table test.t1 modify c1 bigint, add c2 int, add key (c1,c2)', $conn));
    	$this->assertTrue(mysql_query('INSERT INTO test.t1(c1,c2) values (1,1),(2,2),(3,NULL)', $conn));
        $cdc->capture_changes();
        
       	$sql = "select sum(dml_type), sum(c1), count(*) from test.test_t1";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 3);
		$this->assertEquals($row[1], 6);
    	$this->assertEquals($row[2], 3);
		
		
        #test a complex altertation.  Add columns befoer other columns, change data types and rename the table
        #the changelog should contain the right columns, in the proper order, still prefixed by the special CDC columns
        #the changelog should be renamed test.test_t2
        $this->assertTrue(mysql_query('alter table 
                                             test.t1 modify c1 bigint, add c3 int first', $conn));
        
    	
        #this should result in changelog entries into test.test_t2
        $this->assertTrue(mysql_query('delete from test.t1', $conn));
        $cdc->capture_changes();
        
        $sql = "select sum(dml_type), sum(c1), count(*) from test.test_t1";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 0);
		$this->assertEquals($row[1], 12);
    	$this->assertEquals($row[2], 6);
        
        
        $this->assertTrue(mysql_select_db('test',$conn));
        $this->assertTrue(mysql_query('RENAME TABLE t1 to t2 ',$conn));
        $this->assertTrue(mysql_query('INSERT INTO test.t2(c1,c2,c3) values (1,1,1),(2,2,2),(3,NULL,1)', $conn));
        $cdc->capture_changes();

        $sql = "select sum(dml_type), sum(c1), sum(c3), count(*) from test.test_t2";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 3);
		$this->assertEquals($row[1], 18);
		$this->assertEquals($row[2], 4);
    	$this->assertEquals($row[3], 9);
        
		$this->assertTrue(mysql_query('alter table test.t2 drop c3, rename to t3',$conn));
		$cdc->capture_changes();
		
		$sql = "select sum(dml_type), sum(c1),  count(*) from test.test_t3";
		$stmt = mysql_query($sql, $conn);
		$row = mysql_fetch_array($stmt);
		$this->assertEquals($row[0], 3);
		$this->assertEquals($row[1], 18);
		$this->assertEquals($row[2], 9);
		
		$this->assertTrue(mysql_query('create table t1 (c1 int primary key)',$conn));
		$fh = fopen('test.txt', 'w');
		if(!$fh) die('could not open file!\n');
		if (!fputs($fh, "1\n2\n3\n")) die('could not write to file\n');
		
		fclose($fh);
		
		$this->assertTrue(mysql_query('load data local infile "test.txt" into table t1',$conn));
    	$cdc->capture_changes();
    }
 
    
}

?>
