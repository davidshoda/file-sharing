<?php
error_reporting(E_ALL);
error_reporting(0);

class DispersedSubstring {

  /* Dispersed substring Needle and Needle Length*/
  public $S;
  public $SL;

  /* Dispersed substring Haystack and Haystack Length */
  public $T;
  public $TL;

  /* Result Array of haystack T positions matching needle S: key "position" */
  /* Result Total of S dispersed substrings matched in T: key "count" */
  public $R;

  /* Matrix column spacing */
  public $matrix_space = 4;

  public function __construct($S, $T) {
    $this->S = $S;
    $this->T = trim($T,"\n\r");//trim($this->T,"\n\r")
    $this->SL = strlen($this->S);
    $this->TL = strlen($this->T);
    $this->R = array(
      "positions" => array(),
      "count" => 0,
    );
    $this->f();
  }

  public function f(){
    $_SR = $this->char_result_arr();
    $_S = $this->S;
    $_T = $this->T;

    $FO = strpos($_T,$_S[0]);//first char in S
    $LO = strrpos($_T,$_S[strlen($_S)-1]);//last char in S
    $_tj = $FO;
    $_ti = $LO;
    for($si=$this->SL-1,$sj=0;$si>=$sj;--$si){
      for($ti=$_ti,$tj=$_tj;$ti>=$si;--$ti){
        if($this->S[$si]!=$this->T[$ti])
          continue;
        for($tj=$_tj;$tj<=$ti;++$tj){
          for($sj=0;$sj<=$si;++$sj){
            if($this->S[$sj]!=$this->T[$tj])
              continue;
            $_SR[$sj][$tj] = $tj;
            $_ti=$ti;
            $_tj=$tj;
          }
        }
      }

      //right to left (one incr) unset if above prev max
      for($i=$si;$i>0;$i--){
        if(!isset($_SR[$i])||sizeof($_SR[$i])<=0)
          continue;
        $m = max($_SR[$i]);
        foreach($_SR[$i-1] as $k=>$v){
          if($v>$m){
            unset($_SR[$i-1][$k]);
          }
        }
      }
      //left to right (one incr) unset if below prev max
      for($i=0;$i<$si;$i++){
        if(!isset($_SR[$i])||sizeof($_SR[$i])<=0)
          continue;
        $m = min($_SR[$i]);
        foreach($_SR[$i+1] as $k=>$v){
          if($v<$m){
            unset($_SR[$i+1][$k]);
          }
        }
      }
    }

    $this->R["positions"] = $_SR;

    //product of position array sizes
    $SZ = array();
    foreach($_SR as $k=>$v){
      $SZ[] = sizeof($v);
    }
    $this->R["count"] = array_product($SZ);
  }

  /**
   * Returns array of all substrings from left to right in haystack T of needle char SSIC
   * Substrings keyed from position indices
   */
  public function char_position_substrings($SSIC){
    $o = 0; $arr = array();
    $pos = strpos($this->T,$SSIC,$o);
    while( $pos || $pos === 0){
      $arr[$pos] = substr($this->T,0,$pos+1);
      $o = $pos+1;
      $pos = strpos($this->T,$SSIC,$o);
    }
    return $arr;
  }
  /**
   * Sets search string character array to empty arrays
   */
  public function char_result_arr(){
    $sr = str_split($this->S,1);
    foreach($sr as $k=>$v){
    $sr[$k] = array();
    }
    return $sr;
  }

  /**
   * Output haystack T char matrix header row
   */
  public function matrix_trow(){
    $s = "";
    $tr = str_split(' ' . $this->T,1);
    for($i=0;$i<sizeof($tr);$i++){
      $s .= $tr[$i] ;
      for($j=0;$j<$this->matrix_space;$j++)
        $s .= " ";
    }
    $s .= "= " . number_format($this->R["count"], 0, '.', '') . " result(s) returned";
    return $s;
  }

  /**
   * Output needle S chars matrix rows R
   */
  public function matrix_nrows(){
    $s = "";
    $a = array();

    //set matrix multidim
    for($i=0;$i<$this->SL;$i++){ //S
      for($j=0;$j<$this->TL;$j++){ //T
        if( in_array($j,$this->R["positions"][$i]) ){
          $a[$i][$j] = $this->R["positions"][$i][$j];
        }else{
          $a[$i][$j] = 'x';
          //$a[$i][$j] = ' ';
        }
      }
    }
    //output matrix multidim
    $sr = str_split( $this->S,1);
    for($i=0;$i<sizeof($a);$i++){
      $s .= $sr[$i]; // S column
      for($k=0;$k<$this->matrix_space;$k++)
        $s .= " ";
      for($j=0;$j<sizeof($a[$i]);$j++){
        $s .= $a[$i][$j];
        $o = strlen($a[$i][$j])-1; //space offset
        for($k=0;$k<$this->matrix_space-$o;$k++)
          $s .= " ";
      }
      $s .= "= " . sizeof($this->R["positions"][$i]);
      $s .= "\n";
    }
    return $s;
  }
}
