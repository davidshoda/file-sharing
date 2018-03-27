<pre>
dispersed substrings count = product of 2d position array sizes
unit tests from question 4

<?php
error_reporting(E_ALL);

include_once("DispersedSubstring.php");
include_once("unit-test.php");
$ts = time();
$S = 'join the nmi team';
//$file = './sequences.txt'; //small file unit tests
$file = './sequences_orig.txt';
//unit_test_file($S,$file,"count");
unit_test_file($S,$file,"matrix");
$ms = time()-$ts;
echo "\n$ms second execution";
