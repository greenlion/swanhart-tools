#!/usr/bin/perl 
#===============================================================================
#
#         FILE: echo.pl
#
#        USAGE: ./echo.pl  
#
#  DESCRIPTION: Echo back the workload.
#
#      OPTIONS: ---
# REQUIREMENTS: ---
#         BUGS: ---
#        NOTES: ---
#       AUTHOR: YOUR NAME (), 
# ORGANIZATION: 
#      VERSION: 1.0
#      CREATED: 07/11/2012 04:02:42 PM
#     REVISION: ---
#===============================================================================

use Gearman::Worker;

sub echo_worker 
{
  my $job = $_[0];

  my $workload= $job->arg;

  return $workload;
}

use strict;
use warnings;

my $host = 'localhost';
my $port = '4730';

my $servers = $host . ':' . $port;

my $worker = Gearman::Worker->new;
$worker->job_servers($servers);
$worker->register_function( 'echo', \&echo_worker);
$worker->work while 1;
