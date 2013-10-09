DELIMITER ||
/*  Flexviews for MySQL 
    Copyright 2008 Justin Swanhart

    FlexViews is free software: you can redistribute it and/or modify
    it under the terms of the Lesser GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FlexViews is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FlexViews in the file COPYING, and the Lesser extension to
    the GPL (the LGPL) in COPYING.LESSER.
    If not, see <http://www.gnu.org/licenses/>.
*/

/*
    This is a Test Case for STK/Unit:
    http://stk.wikidot.com/stk-unit
    
    "Official" way to run this Test Case:
    CALL stk_unit.tc('test_flexviews');
    Shortcut:
    flexviews.test()
    
    You can use the command line. Output is human-readable by default.
*/


SET @@session.SQL_MODE = 'ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,STRICT_TRANS_TABLES';


DROP PROCEDURE IF EXISTS `flexviews`.`test`;
CREATE PROCEDURE `flexviews`.`test`()
  MODIFIES SQL DATA
  COMMENT 'Shortcut for STK/Unit tests'
`whole_proc`:
BEGIN
  -- is STK/Unit installed?
  IF NOT EXISTS (SELECT TRUE FROM `information_schema`.`SCHEMATA` WHERE `SCHEMA_NAME` = 'stk_unit') THEN
    BEGIN
      DECLARE txt TEXT DEFAULT 'STK/Unit is not installed';
      /*!50404
        SIGNAL SQLSTATE '45000' SET
          CLASS_ORIGIN = 'FlexViews',
          MESSAGE_TEXT = txt;
      */
      SELECT '45000' AS `SQLSTATE`, txt AS `MESSAGE_TEXT`;
      LEAVE `whole_proc`;
    END;
  END IF;
  
  CALL `stk_unit`.`tc`('test_flexviews');
END;


DROP DATABASE IF EXISTS `test_flexviews`;
CREATE DATABASE `test_flexviews`
	DEFAULT CHARACTER SET = 'utf8';


CREATE PROCEDURE `test_flexviews`.`before_all_tests`()
  MODIFIES SQL DATA
BEGIN
  -- create a small in-memory relational db which can be used
  -- to create test mviews.
  -- tables cannot be TEMPORARY because I_S doesnt show temptables.
  
  CREATE DATABASE IF NOT EXISTS `test`;
  CREATE DATABASE IF NOT EXISTS `test2`;
  
  DROP TABLE IF EXISTS `test`.`customer`;
  CREATE TABLE `test`.`customer`
  (
    `customer_id` INTEGER UNSIGNED NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `city_id` INTEGER UNSIGNED NOT NULL
  )
    ENGINE = MEMORY;
  
  DROP TABLE IF EXISTS `test`.`city`;
  CREATE TABLE `test`.`city`
  (
    `city_id` INTEGER UNSIGNED NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `country_id` VARCHAR(100) NOT NULL,
    `is_capital` BOOLEAN NOT NULL,
    `population` INTEGER UNSIGNED NOT NULL
  )
    ENGINE = MEMORY;
  
  DROP TABLE IF EXISTS `test`.`country`;
  CREATE TABLE `test`.`country`
  (
    `country_id` INTEGER UNSIGNED NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL
  )
    ENGINE = MEMORY;
END;


CREATE PROCEDURE `test_flexviews`.`set_up`()
  MODIFIES SQL DATA
BEGIN
  SET @fv_force = NULL;
  TRUNCATE TABLE `flexviews`.`mview`;
END;


CREATE PROCEDURE `test_flexviews`.`test_signal`()
  MODIFIES SQL DATA
BEGIN
  DECLARE EXIT HANDLER
    FOR
        SQLSTATE '45000', -- 5.5+
        1054 -- before 5.5
  BEGIN
    CALL `stk_unit`.assert_true(TRUE, 'cannot fail, really');
  END;
  
  CALL `flexviews`.`signal`('test');
  -- if EXIT HANDLER is not triggered...
  CALL `stk_unit`.assert_true(FALSE, 'signal() generated no error');
END;


CREATE PROCEDURE `test_flexviews`.`test_fvrand`()
  MODIFIES SQL DATA
BEGIN
  -- there is no clean way to test a random function,
  -- so we do `i` sanity tests
  DECLARE i TINYINT UNSIGNED DEFAULT 10; -- number of random tests
  -- random value's bounds
  DECLARE v_min TINYINT UNSIGNED DEFAULT 1;
  DECLARE v_max TINYINT UNSIGNED DEFAULT 3;
  
  -- test NULLs
  CALL `stk_unit`.assert_null(`flexviews`.`fvrand`(NULL, v_max), 'One param was NULL; expected NULL');
  CALL `stk_unit`.assert_null(`flexviews`.`fvrand`(v_min, NULL), 'One param was NULL; expected NULL');
  CALL `stk_unit`.assert_null(`flexviews`.`fvrand`(NULL, NULL), 'One param was NULL; expected NULL');
  
  -- sanity tests
  WHILE i > 0 DO
    CALL `stk_unit`.assert_between_integer(`flexviews`.`fvrand`(v_min, v_max), v_min, v_max, NULL);
    SET i := i - 1;
  END WHILE;
END;


CREATE PROCEDURE `test_flexviews`.`test_get_setting`()
  MODIFIES SQL DATA
BEGIN
  -- WHITE BOX
  
  -- test option is needed, because the table seems to be unused now
  DECLARE o_key TEXT DEFAULT 'my_key';
  DECLARE o_val TEXT DEFAULT 'test value';
  
  CALL `stk_unit`.assert_null(`flexviews`.`get_setting`('not-exists'), 'NULL expected for non-existing options');
  
  INSERT INTO `flexviews`.`mview_settings` SET `setting_key` = o_key, `setting_value` = o_val;
  CALL `stk_unit`.assert_equal(`flexviews`.`get_setting`(o_key), o_val, NULL);
  DELETE FROM `flexviews`.`mview_settings` WHERE `setting_key` = o_key;
END;


CREATE PROCEDURE `test_flexviews`.`test_schema_exists`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.assert_true(`flexviews`.`schema_exists`('information_schema'), 'information_schema exists');
  CALL `stk_unit`.assert_false(`flexviews`.`schema_exists`('not-exists'), NULL);
  CALL `stk_unit`.assert_false(`flexviews`.`schema_exists`(NULL), 'wrong result with NULL');
END;


CREATE PROCEDURE `test_flexviews`.`test_table_exists`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.assert_true(`flexviews`.`table_exists`('test', 'customer'), 'mysql.user exists');
  CALL `stk_unit`.assert_false(`flexviews`.`table_exists`('test', 'not-exists'), NULL);
  CALL `stk_unit`.assert_false(`flexviews`.`table_exists`('not-exists', 'customer'), NULL);
  CALL `stk_unit`.assert_false(`flexviews`.`table_exists`('not-exists', 'not-exists'), NULL);
  CALL `stk_unit`.assert_false(`flexviews`.`table_exists`(NULL, NULL), 'wrong result with NULL');
END;


CREATE PROCEDURE `test_flexviews`.`test_quote_name`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.assert_equal(`flexviews`.`quote_name`('test'), '`test`', NULL);
  CALL `stk_unit`.assert_equal(`flexviews`.`quote_name`('my`backtick'), '`my``backtick`', 'doesnt properly escape backticks');
  
  -- unusual input
  CALL `stk_unit`.assert_equal(`flexviews`.`quote_name`(''), '``', 'wrong result with empty string');
  CALL `stk_unit`.assert_null(`flexviews`.`quote_name`(NULL), 'wrong result with NULL');
END;


CREATE PROCEDURE `test_flexviews`.`test_create`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'new_mv';
  DECLARE id BIGINT;
  
  -- test incremental
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
  SET id := LAST_INSERT_ID();
  CALL `stk_unit`.assert_equal(`flexviews`.`get_id`(t_db, t_tab), id, NULL);
  TRUNCATE TABLE `flexviews`.`mview`;
  
  -- test complete
  CALL `flexviews`.`create`(t_db, t_tab, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  CALL `stk_unit`.assert_equal(`flexviews`.`get_id`(t_db, t_tab), id, NULL);
  TRUNCATE TABLE `flexviews`.`mview`;
END;


CREATE PROCEDURE `test_flexviews`.`test_create_reset_force`()
  MODIFIES SQL DATA
BEGIN
  -- check if create() resets @fv_force to NULL

  -- existing db.table
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'new_mv';

  SET @fv_force = TRUE;
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
  CALL `stk_unit`.assert_null(@fv_force, '@fv_force was not reset');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_duplicate`()
  MODIFIES SQL DATA
BEGIN
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'new_mv';
  
  -- add twice, expect error
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_flush_method_strict_mode`()
  MODIFIES SQL DATA
BEGIN
  SET @@session.sql_mode = 'STRICT_ALL_TABLES,STRICT_TRANS_TABLES';
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`create`('test', 'new_mv', 'not-exists');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_flush_method_not_strict`()
  MODIFIES SQL DATA
BEGIN
  SET @@session.sql_mode = '';
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`create`('test', 'new_mv', 'not-exists');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_db`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`create`('not-exists', 'new_md', 'INCREMENTAL');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_db_force`()
  MODIFIES SQL DATA
BEGIN
  SET @fv_force = TRUE;
  CALL `flexviews`.`create`('not-exists', 'new_md', 'INCREMENTAL');
  -- no error? pass!
  CALL `stk_unit`.`assert_true`(TRUE, NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_table`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.`expect_any_exception`();
  -- cannot create: already exists
  CALL `flexviews`.`create`('test', 'customer', 'INCREMENTAL');
END;


CREATE PROCEDURE `test_flexviews`.`test_create_with_invalid_table_force`()
  MODIFIES SQL DATA
BEGIN
  SET @fv_force = TRUE;
  -- already exists, but we force skeleton creation
  CALL `flexviews`.`create`('test', 'customer', 'INCREMENTAL');
  -- no error? pass!
  CALL `stk_unit`.`assert_true`(TRUE, NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_rename`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db_old TEXT DEFAULT 'test';
  DECLARE t_tab_old TEXT DEFAULT 'old_mv';
  DECLARE id BIGINT;
  DECLARE t_db_new TEXT DEFAULT 'test';
  DECLARE t_tab_new TEXT DEFAULT 'new_mv';
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db_old, t_tab_old, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  
  -- rename
  CALL `flexviews`.`rename`(id, t_db_new, t_tab_new);
  
  -- asserts
  CALL `stk_unit`.assert_null(`flexviews`.`get_id`(t_db_old, t_tab_old), 'old skeleton should not exist');
  CALL `stk_unit`.assert_equal(`flexviews`.`get_id`(t_db_new, t_tab_new), id, 'new skeleton with same id should exist');
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_reset_force`()
  MODIFIES SQL DATA
BEGIN
  -- test that rename() resets @fv_force to NULL

  -- existing db.table
  DECLARE t_db_old TEXT DEFAULT 'test';
  DECLARE t_tab_old TEXT DEFAULT 'old_mv';
  DECLARE id BIGINT;
  DECLARE t_db_new TEXT DEFAULT 'test';
  DECLARE t_tab_new TEXT DEFAULT 'new_mv';
  
  -- create & rename
  CALL `flexviews`.`create`(t_db_old, t_tab_old, 'COMPLETE');
  SET @fv_force = TRUE;
  CALL `flexviews`.`rename`(LAST_INSERT_ID(), t_db_new, t_tab_new);
  
  CALL `stk_unit`.assert_null(@fv_force, '@fv_force was not reset');
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_duplicate`()
  MODIFIES SQL DATA
BEGIN
  -- create 2 mviews, than try to rename mv2 like mv1; expect error
  CALL `flexviews`.`create`('test', 'mv1', 'COMPLETE');
  CALL `flexviews`.`create`('test', 'mv2', 'COMPLETE');
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`rename`(LAST_INSERT_ID(), 'test', 'mv1');
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_empty_table`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db_old TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'old_mv';
  DECLARE id BIGINT;
  DECLARE t_db_new TEXT DEFAULT 'test2';
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db_old, t_tab, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  
  -- rename with '' table (change db)
  CALL `flexviews`.`rename`(id, t_db_new, '');
  CALL `stk_unit`.assert_null(`flexviews`.`get_id`(t_db_old, t_tab), 'old skeleton should not exist');
  CALL `stk_unit`.assert_equal(id, `flexviews`.`get_id`(t_db_new, t_tab), NULL);
  
  -- switch back to old db, this time use NULL
  CALL `flexviews`.`rename`(id, t_db_old, NULL);
  CALL `stk_unit`.assert_null(`flexviews`.`get_id`(t_db_new, t_tab), 'old skeleton should not exist');
  CALL `stk_unit`.assert_equal(id, `flexviews`.`get_id`(t_db_old, t_tab), NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_empty_db`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab_old TEXT DEFAULT 'old_mv';
  DECLARE t_tab_new TEXT DEFAULT 'new_mv';
  DECLARE id BIGINT;
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db, t_tab_old, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  
  -- rename with '' db (change only table)
  CALL `flexviews`.`rename`(id, '', t_tab_new);
  CALL `stk_unit`.assert_null(`flexviews`.`get_id`(t_db, t_tab_old), NULL);
  CALL `stk_unit`.assert_equal(id, `flexviews`.`get_id`(t_db, t_tab_new), NULL);
  
  -- switch back to old table, this time use NULL
  CALL `flexviews`.`rename`(id, NULL, t_tab_old);
  CALL `stk_unit`.assert_null(`flexviews`.`get_id`(t_db, t_tab_new), NULL);
  CALL `stk_unit`.assert_equal(id, `flexviews`.`get_id`(t_db, t_tab_old), NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_invalid_mvid`()
  MODIFIES SQL DATA
BEGIN
  -- rename non-existing mview
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`rename`(999, 'test', 'new_mv');
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_invalid_db`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db_old TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'old_mv';
  DECLARE id BIGINT;
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db_old, t_tab, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  
  -- rename with non-existing db
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`rename`(id, 'not-exists', t_tab);
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_invalid_db_force`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db_old TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'old_mv';
  
  -- create, then rename trying to move to a non-existing db; force skeleton creation
  CALL `flexviews`.`create`(t_db_old, t_tab, 'COMPLETE');
  SET @fv_force = TRUE;
  CALL `flexviews`.`rename`(LAST_INSERT_ID(), 'not-exists', t_tab);
  CALL `stk_unit`.`assert_true`(TRUE, NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_invalid_table`()
  MODIFIES SQL DATA
BEGIN
 -- existing db.table
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab_old TEXT DEFAULT 'old_mv';
  DECLARE id BIGINT;
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db, t_tab_old, 'COMPLETE');
  SET id := LAST_INSERT_ID();
  
  -- rename with existing table
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`rename`(id, t_db, 'customer');
END;


CREATE PROCEDURE `test_flexviews`.`test_rename_with_invalid_table_force`()
  MODIFIES SQL DATA
BEGIN
  -- existing db.table
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab_old TEXT DEFAULT 'old_mv';
  
  -- add old mv, NOT enabled
  CALL `flexviews`.`create`(t_db, t_tab_old, 'COMPLETE');
  SET @fv_force = TRUE;
  CALL `flexviews`.`rename`(LAST_INSERT_ID(), t_db, 'customer');
  CALL `stk_unit`.`assert_true`(TRUE, NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_drop_on_disabled_mview`()
  MODIFIES SQL DATA
BEGIN
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'new_mv';
  DECLARE id BIGINT;
  
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
  SET id := LAST_INSERT_ID();
  CALL `stk_unit`.expect_any_exception();
  CALL `flexviews`.`drop`(id);
END;


CREATE PROCEDURE `test_flexviews`.`test_drop_on_disabled_mview_force`()
  MODIFIES SQL DATA
BEGIN
  DECLARE t_db TEXT DEFAULT 'test';
  DECLARE t_tab TEXT DEFAULT 'new_mv';
  
  SET @fv_force = TRUE;
  CALL `flexviews`.`create`(t_db, t_tab, 'INCREMENTAL');
  CALL `flexviews`.`drop`(LAST_INSERT_ID());
  CALL `stk_unit`.assert_null(@fv_force);
END;


CREATE PROCEDURE `test_flexviews`.`test_drop_on_invalid_mview`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.expect_any_exception();
  CALL `flexviews`.`drop`(999);
END;


CREATE PROCEDURE `test_flexviews`.`test_drop_on_invalid_mview_force`()
  MODIFIES SQL DATA
BEGIN
  SET @fv_force = TRUE;
  CALL `stk_unit`.expect_any_exception();
  CALL `flexviews`.`drop`(999);
  CALL `stk_unit`.assert_null(@fv_force);
END;


CREATE PROCEDURE `test_flexviews`.`test_set_definition`()
  MODIFIES SQL DATA
BEGIN
  -- WHITE BOX
  
  -- test option is needed, because the table seems to be unused now
  DECLARE t_mview_id TINYINT UNSIGNED;
  DECLARE t_def TEXT DEFAULT 'SELECT xxx FROM yyy WHERE zzz';
  
  -- create a mview and set definition
  CALL `flexviews`.`create`('test', 'mv', 'COMPLETE');
  SET t_mview_id = LAST_INSERT_ID();
  CALL `flexviews`.`set_definition`(t_mview_id, t_def);
  CALL `stk_unit`.assert_equal(
      (SELECT `mview_definition` FROM `flexviews`.`mview` WHERE `mview_id` = t_mview_id), t_def, NULL
    );
END;


CREATE PROCEDURE `test_flexviews`.`test_set_definition_with_non_existing_mview`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`set_definition`(999, 'SELECT 1');
END;


CREATE PROCEDURE `test_flexviews`.`test_set_definition_with_non_complete_mview`()
  MODIFIES SQL DATA
BEGIN
  DECLARE id BIGINT;
  
  CALL `flexviews`.`create`('test', 'mv', 'INCREMENTAL');
  SET id := LAST_INSERT_ID();
  
  CALL `stk_unit`.`expect_any_exception`();
  CALL `flexviews`.`set_definition`(id, 'SELECT 1');
END;


CREATE PROCEDURE `test_flexviews`.`test_get_sql_complete`()
  MODIFIES SQL DATA
BEGIN
  -- test option is needed, because the table seems to be unused now
  DECLARE t_mview_id BIGINT;
  DECLARE t_def TEXT DEFAULT 'SELECT xxx FROM yyy WHERE zzz';
  
  -- create a mview and set definition
  CALL `flexviews`.`create`('test', 'mv', 'COMPLETE');
  SET t_mview_id = LAST_INSERT_ID();
  CALL `flexviews`.`set_definition`(t_mview_id, t_def);
  CALL `stk_unit`.assert_equal(
      `flexviews`.`get_sql`(t_mview_id), t_def, NULL
    );
END;


CREATE PROCEDURE `test_flexviews`.`test_get_sql_with_bare_skeleton`()
  MODIFIES SQL DATA
BEGIN
  -- we create a mview but DONT set definition.
  -- get_sql() should return NULL.
  DECLARE t_mview_id BIGINT;
  CALL `flexviews`.`create`('test', 'mv', 'COMPLETE');
  SET t_mview_id = LAST_INSERT_ID();
  CALL `stk_unit`.assert_null(`flexviews`.`get_sql`(t_mview_id), NULL);
END;


CREATE PROCEDURE `test_flexviews`.`test_get_sql_with_invalid_mview_id`()
  MODIFIES SQL DATA
BEGIN
  CALL `stk_unit`.expect_any_exception();
  CALL `stk_unit`.assert_null(`flexviews`.`get_sql`(999), NULL);
END;


||
DELIMITER ;

