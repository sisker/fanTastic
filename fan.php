<?PHP


function countGPUs(){
  //command line output from aticonfig --lsa
  $cmd = "aticonfig aticonfig --lsa";
  exec($cmd, $output);

  //find ati series number
  $match='/\d\d\d\d/';
  preg_match_all($match, implode($output), $gpus);
  $num = count($gpus[0]);

  return $num;
}

function getCores(&$gpus,$adapter){
  //command line output from aticonfig --odgc --adapter=0
  $cmd = "DISPLAY=:0.$adapter aticonfig --odgc --adapter=$adapter";
  exec($cmd, $output);

  //find core info
  $match='/\\d+/';
  preg_match_all($match, implode($output), $result);

  //core array  |0      |1   |2     |3     |4     |5     |6    |7    |8    |9    |10  |
  //            |adapter|name|cocucl|mecucl|cocupe|mecupe|comin|comax|memin|memax|load|

  //remap results into gpu array, has to be a better way to do this.
  $gpus[$adapter][0] = $result[0][0];
  $gpus[$adapter][1] = $result[0][1];
  $gpus[$adapter][2] = $result[0][2];
  $gpus[$adapter][3] = $result[0][3];
  $gpus[$adapter][4] = $result[0][4];
  $gpus[$adapter][5] = $result[0][5];
  $gpus[$adapter][6] = $result[0][6];
  $gpus[$adapter][7] = $result[0][7];
  $gpus[$adapter][8] = $result[0][8];
  $gpus[$adapter][9] = $result[0][9];
  $gpus[$adapter][10] = $result[0][10];
}

function getFan(&$gpus,$adapter){
  //command line output aticonfig --pplib-cmd "get fanspeed 0";
  $cmd = "DISPLAY=:0.$adapter aticonfig --pplib-cmd \"get fanspeed 0\"";
  exec($cmd, $output);

  //find fan speed
  $match='/\d+%/';
  preg_match_all($match, implode($output), $result);

  //strip out the % sign
  $result[0][0] = preg_replace("/%/",'',$result[0][0]);
  
  //put the result into gpu array
  $gpus[$adapter][11]=$result[0][0];
}

function getTemp(&$gpus,$adapter){
  //command line output from aticonfig --odgt --adapter=0
  $cmd = "DISPLAY=:0.$adapter aticonfig --odgt --adapter=$adapter";
  exec($cmd, $output);

  //find temp of gpu
  $match='/\d\d\.\d\d/';
  preg_match_all($match, implode($output), $result);

  //put the result into gpu array
  $gpus[$adapter][12]=$result[0][0];  
}

function queryGpus(&$gpus){
  //How many GPUs?
  $num = countGPUs();
  $adapter = 0;
  
  while ($adapter<$num){
    //Make an array for each gpu
    //gpus  array |0      |1   |2     |3     |4     |5     |6    |7    |8    |9    |10  |11 |12
    //            |adapter|name|cocucl|mecucl|cocupe|mecupe|comin|comax|memin|memax|load|fan|temp

    //get cores info [0..10]
    getCores($gpus,$adapter);
    //get fan info [11]
    getFan($gpus,$adapter);  
    //get temp info [12]
    getTemp($gpus,$adapter);
    $adapter++;
  }
}

function adjustFan($gpus,$adapter,$fan){
  $cmd_odsc = "DISPLAY=:0.$adapter aticonfig --pplib-cmd \"set fanspeed 0 $fan\"";
  exec($cmd_odsc, $output);
}

function printStats($gpus){
  $count = countGPUs();
  //echo ("Found $count GPUs\n");
  $adapter = 0;
  while ($adapter < $count){
    $name=$gpus[$adapter][1];
    $cocucl=$gpus[$adapter][2];
    $mecucl=$gpus[$adapter][3];
    $cocupe=$gpus[$adapter][4];
    $mecupe=$gpus[$adapter][5];
    $comin=$gpus[$adapter][6];
    $comax=$gpus[$adapter][7];
    $memin=$gpus[$adapter][8];
    $memax=$gpus[$adapter][9];
    $load=$gpus[$adapter][10];
    $fan=$gpus[$adapter][11];
    $temp=$gpus[$adapter][12];

    echo ("GPU $adapter: Series $name\n");
    echo ("Core: Current: $cocucl Peak: $cocupe Range: $comin $comax\n");
    echo ("Memory: Current: $mecucl Peak: $mecupe Range: $memin $memax\n");
    echo ("Load: $load% Fan: $fan% Temp: $temp C\n\n");
    $adapter++;
  }
}

function maintainGPUs($gpus){
  $optimalTemp = 75;
  $tolerance = 1;
  $count = countGPUs();
  $adapter=0;
  while ($adapter < $count){
    $temp=$gpus[$adapter][12];
    $fan=$gpus[$adapter][11];
    echo ("GPU:$adapter Temp: $temp Fan: str_pad($fan, 3);. ");
    if ($temp > ($optimalTemp+$tolerance)){
      if ($fan < 100){
        echo("Too Hot, Increasing Fan");
        adjustFan(&$gpus,$adapter,$fan+1);
      }
      if ($fan == 100){
        echo('ALERT! Fan at 100%');
      }
    }
    if ($temp < ($optimalTemp-$tolerance)){
      if ($fan > 30){
        echo("Too Cold, Decreasing Fan");
        adjustFan(&$gpus,$adapter,$fan-1);
      }
      if ($fan <= 30){
        echo("At Minimun Fan Speed 30%");
        adjustFan(&$gpus,$adapter,30);
      }
    }
    if ( ($optimalTemp-$tolerance <= $temp) && ($temp <= $optimalTemp+$tolerance) ){
      echo("Temp OK");
    }
    echo("\n");
    $adapter++;
  }
}

//////////////////////////////////////
//////////////////////////////////////

$gpus = Array();
//print all stats
queryGpus($gpus);
printStats($gpus);

while (true){
  //clear the screen
  //passthru('clear');
  queryGpus($gpus);
  maintainGPUs($gpus);
  sleep(3);
}


?>
