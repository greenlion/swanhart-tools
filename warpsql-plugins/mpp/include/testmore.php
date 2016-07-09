<?php

/*******************************************************************\
*                        PROJECT INFORMATION                        *
*                                                                   *
*  Project:  Apache-Test                                            *
*  URL:      http://perl.apache.org/Apache-Test/                    *
*  Notice:   Copyright (c) 2006 The Apache Software Foundation      *
*                                                                   *
*********************************************************************
*                        LICENSE INFORMATION                        *
*                                                                   *
*  Licensed under the Apache License, Version 2.0 (the "License");  *
*  you may not use this file except in compliance with the          *
*  License. You may obtain a copy of the License at:                *
*                                                                   *
*  http://www.apache.org/licenses/LICENSE-2.0                       *
*                                                                   *
*  Unless required by applicable law or agreed to in writing,       *
*  software distributed under the License is distributed on an "AS  *
*  IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either  *
*  express or implied. See the License for the specific language    *
*  governing permissions and limitations under the License.         *
*                                                                   *
*********************************************************************
*                        MODULE INFORMATION                         *
*                                                                   *
*  This is a PHP implementation of Test::More:                      *
*                                                                   *
*  http://search.cpan.org/dist/Test-Simple/lib/Test/More.pm         *
*                                                                   *
*********************************************************************
*                              CREDITS                              *
*                                                                   *
*  Originally inspired by work from Andy Lester. Written and        *
*  maintained by Chris Shiflett. For contact information, see:      *
*                                                                   *
*  http://shiflett.org/contact                                      *
*                                                                   *
\*******************************************************************/

header('Content-Type: text/plain');
register_shutdown_function('_test_end');

$_no_plan = FALSE;
$_num_failures = 0;
$_num_skips = 0;
$_test_num = 0;

function plan($plan)
{
    /*
    plan('no_plan');
    plan('skip_all');
    plan(array('skip_all' => 'My reason is...'));
    plan(23);
    */

    global $_no_plan;
    global $_skip_all;

    switch ($plan) {
        case 'no_plan':
            $_no_plan = TRUE;
            break;

        case 'skip_all':
            echo "1..0\n";
            break;

        default:
            if (is_array($plan)) {
                echo "1..0 # Skip {$plan['skip_all']}\n";
                exit;
            }

            echo "1..$plan\n";
            break;
    }
}

function todo($why, $todo_tests) {
    todo_begin($why);
    call_user_func($todo_tests);
    todo_end();
}

function todo_begin($why = '')
{
    global $_testmore_todo;
    if (! isset($_testmore_todo)) {
        $_testmore_todo = array();
    }
    array_push($_testmore_todo, $why);
}

function todo_end()
{
    global $_testmore_todo;
    assert('is_array($_testmore_todo)');
    array_pop($_testmore_todo);
}

function ok($pass, $test_name = '')
{
    global $_test_num;
    global $_num_failures;
    global $_num_skips;
    global $_testmore_todo;

    $_test_num++; 

    if ($_num_skips) {
        $_num_skips--;
        return TRUE;
    }

    if (!empty($test_name) && $test_name[0] != '#') {
        $test_name = "- $test_name";
    }
    
    if ($test_name[0] != '#' and isset($_testmore_todo) and count($_testmore_todo)) {
        $msg = array_pop( array_values( $_testmore_todo ) );
        $test_name .= " # TODO $msg";
    }

    if ($pass) {
        echo "ok $_test_num $test_name\n";
    } else {
        echo "not ok $_test_num $test_name\n";

        $_num_failures++;
        $caller = debug_backtrace();

        if (strstr($caller['0']['file'], $_SERVER['PHP_SELF'])) {
            $file = $caller['0']['file'];
            $line = $caller['0']['line'];
        } else {
            $file = $caller['1']['file'];
            $line = $caller['1']['line'];
        }

        if (isset($_SERVER['SERVER_ROOT'])){
            $file = str_replace($_SERVER['SERVER_ROOT'], 't', $file);
        }

        diag("    Failed test ($file at line $line)");
    }

    return $pass;
}

function is($this, $that, $test_name = '')
{
    $pass = ($this == $that);

    ok($pass, $test_name);

    if (!$pass) {
        diag("         got: '$this'");
        diag("    expected: '$that'");
    }

    return $pass;
}

function isnt($this, $that, $test_name = '')
{
    $pass = ($this != $that);

    ok($pass, $test_name);

    if (!$pass) {
        diag("    '$this'");
        diag('        !=');
        diag("    '$that'");
    }

    return $pass;
}

function like($string, $pattern, $test_name = '')
{
    $pass = preg_match($pattern, $string);

    ok($pass, $test_name);

    if (!$pass) {
        diag("                  '$string'");
        diag("    doesn't match '$pattern'");
    }

    return $pass;
}

function unlike($string, $pattern, $test_name = '')
{
    $pass = !preg_match($pattern, $string);

    ok($pass, $test_name);

    if (!$pass) {
        diag("                  '$string'");
        diag("          matches '$pattern'");
    }

    return $pass;
}

function cmp_ok($this, $operator, $that, $test_name = '')
{
    eval("\$pass = (\$this $operator \$that);");

    ob_start();
    var_dump($this);
    $_this = trim(ob_get_clean());

    ob_start();
    var_dump($that);
    $_that = trim(ob_get_clean());

    ok($pass, $test_name);

    if (!$pass) {
        diag("         got: $_this");
        diag("    expected: $_that");
    }

    return $pass;
}

function can_ok($object, $methods)
{
    $pass = TRUE;
    $errors = array();

    foreach ($methods as $method) {
        if (!method_exists($object, $method)) {
            $pass = FALSE;
            $errors[] = "    method_exists(\$object, $method) failed";
        }
    }

    if ($pass) {
        ok(TRUE, "method_exists(\$object, ...)");
    } else {
        ok(FALSE, "method_exists(\$object, ...)");
        diag($errors);
    }

    return $pass;
}

function isa_ok($object, $expected_class, $object_name = 'The object')
{
    $got_class = get_class($object);

    if (version_compare(phpversion(), '5', '>=')) {
        $pass = ($got_class == $expected_class);
    } else {
        $pass = ($got_class == strtolower($expected_class));
    }

    if ($pass) {
        ok(TRUE, "$object_name isa $expected_class");
    } else {
        ok(FALSE, "$object_name isn't a '$expected_class' it's a '$got_class'");
    }

    return $pass;
}

function pass($test_name = '')
{
    return ok(TRUE, $test_name);
}

function fail($test_name = '')
{
    return ok(FALSE, $test_name);
}

function diag($message)
{
    if (is_array($message)) {
        foreach($message as $current) {
            echo "# $current\n";
        }
    } else {
        echo "# $message\n";
    }
}

function include_ok($module)
{
    $pass = ((include $module) == 1);
    return ok($pass, "include $module");
}

function require_ok($module)
{
    $pass = ((require $module) == 1);
    return ok($pass, "require $module");
} 

function skip($message, $num)
{
    global $_num_skips;

    if ($num < 0) {
        $num = 0;
    }

    for ($i = 0; $i < $num; $i++) {
        pass("# SKIP $message");
    }

    $_num_skips = $num;
}

function is_deeply($got_struct, $expected_struct, $test_name = '')
{
    $got_flat = var_export($got_struct, true);
    $expected_flat = var_export($expected_struct, true);

    is($got_flat, $expected_flat, $test_name);
}

function _test_end()
{
    global $_no_plan;
    global $_num_failures;
    global $_test_num;

    if ($_no_plan) {
        echo "1..$_test_num\n";
    }

    if ($_num_failures) {
        diag("Looks like you failed $_num_failures tests of $_test_num.");
    }
}

?>
