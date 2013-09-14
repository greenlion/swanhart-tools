<?php 
require_once('../flexcdc.php');

#this test uses the FlexCDC object to make a connection to the destination MySQL server
#this test DOES NOT RUN FlexCDC!!!! You must run an external consumer connected to the
#sandbox database
$settings = parse_ini_file('./test_flexviews.ini', true);
$settings['flexcdc']['database']='flexviews';

class ConsumerTest extends PHPUnit_Framework_TestCase
{   
    public function testInstaller() {
	global $settings;
        $cdc = new FlexCDC($settings);
        $this->assertTrue($cdc->get_source() && $cdc->get_dest());
        $conn = $cdc->get_source();
        mysql_query('DROP DATABASE IF EXISTS test') or die(mysql_error() . "\n");
        
	#keep moving up directories looking for the installer.  error out when we can move no further up
	while(1) {
		$this->assertTrue(chdir('..')); 
		if(file_exists('./install.sql')) break;
	}
	$output = `test/sandbox/use -uroot -pmsandbox < install.sql`;
	echo md5($output);
	$this->assertTrue(md5($output) == "b83cfd9e5fd29f1c458f8d4070c86511");
		
    	$sql = "CREATE DATABASE IF NOT EXISTS test";
    	mysql_query($sql,$conn);
    	$sql = "CREATE TABLE test.t1 (c1 int primary key)";
    	mysql_query($sql, $conn);

    	$sql = "CALL flexviews.create_mvlog('test','t1')";
    	mysql_query($sql, $conn);

    	$sql = "INSERT INTO test.t1 values (1),(2),(3)";
    	mysql_query($sql, $conn);
    	$sql = "CREATE TABLE test.t2 (c1 int, c2 int, primary key(c2), key(c1))";
    	mysql_query($sql, $conn);

    	$sql = "CALL flexviews.create_mvlog('test','t2')";
    	mysql_query($sql, $conn);

    	$sql = "INSERT INTO test.t2 values (1,1),(2,2),(3,3),(1,4)";
    	mysql_query($sql, $conn);

    	#VERIFY THAT FLEXCDC IS WORKING - The consumer is going to run in the background 
	sleep(1);
    	$sql = "select count(*) from flexviews.test_t1";
    	$stmt = mysql_query($sql, $conn) or die(mysql_error() . "\n");
        return $cdc;
        
    }
    
    /**
     * @depends testInstaller
     */
    #This test tests the distributive aggregate functions COUNT, SUM and AVG
    public function testIncrementalDistributive($cdc) {
		$conn = $cdc->get_source();
    	$sql = "CALL flexviews.create('test','test_mv','INCREMENTAL');";
    	if(!mysql_query($sql)) {
    		echo "COULD NOT CREATE MATERIALIZED VIEW HANDLE\n";
    		echo "ERROR: " . mysql_error($conn) . "\n";
    		exit;
    	} 
    	
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
