<?php
error_reporting(E_ALL);
function unit_test_file($S, $file = "./sequences.txt", $cmd = "count"){
  //read file by line
  $f = file($file);
  $handle = @fopen($file, "r");
  if ($handle) {
      $line_number = 1;
      while (($T = fgets($handle, 4096)) !== false) {
        //if($line_number>10) return; //small file unit tests
        if( !strcmp($cmd,'matrix') )
          unit_test_matrix($S,$T);
        else
          unit_test_count($S,$T,$line_number++);
      }
      if (!feof($handle)) {
          echo "Error: unexpected fgets() fail\n";
      }
      fclose($handle);
  }
}

function unit_test_count($S,$T,$line_number){
  $o = new DispersedSubstring($S, $T);
  echo sprintf("Line %02d:  ", $line_number);
  //echo sprintf("%05d ", $o->R["count"]); //zeros on large numbers
  //echo $o->R["count"]; //scientific
  if( $o->R["count"] < 5000)
    echo sprintf("%05d ", $o->R["count"]);
  else
    echo number_format($o->R["count"], 0, '.', '');//force integers
  echo "\n";
}

function unit_test_matrix($S,$T,$RE = NULL){
  $o = new DispersedSubstring($S, $T);
  echo "S:".$o->S."<br/>";
  echo "T:".$o->T."<br/>";
  if(isset($RE)&&$RE>=0)
    echo "$RE = result(s) expected<br/>";
  echo $o->matrix_trow();
  echo "<br/>";
  echo $o->matrix_nrows();
  echo "<hr/>";
}