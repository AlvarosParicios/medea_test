<?php


/**
 * Execute a command and return it's output. Either wait until the command exits or the timeout has expired.
 * SOURCE: http://blog.dubbelboer.com/2012/08/24/execute-with-timeout.html
 *
 * @param string $cmd     Command to execute.
 * @param number $timeout Timeout in seconds.
 * @return string Output of the command.
 * @throws \Exception
 */
function exec_timeout($cmd, $timeout) {
  // File descriptors passed to the process.
  $descriptors = array(
    0 => array('pipe', 'r'),  // stdin
    1 => array('pipe', 'w'),  // stdout
    2 => array('pipe', 'w')   // stderr
  );

  // Start the process.
  $process = proc_open('exec ' . $cmd, $descriptors, $pipes);

  if (!is_resource($process)) {
    throw new Exception('Could not execute process');
  }

  // Set the stdout stream to none-blocking.
  stream_set_blocking($pipes[1], 0);

  // Turn the timeout into microseconds.
  $timeout = $timeout * 1000000;

  // Output buffer.
  $buffer = '';

  // While we have time to wait.
  while ($timeout > 0) {
    $start = microtime(true);

    // Wait until we have output or the timer expired.
    $read  = array($pipes[1]);
    $other = array();
    stream_select($read, $other, $other, 0, $timeout);

    // Get the status of the process.
    // Do this before we read from the stream,
    // this way we can't lose the last bit of output if the process dies between these functions.
    $status = proc_get_status($process);

    // Read the contents from the buffer.
    // This function will always return immediately as the stream is none-blocking.
    $buffer .= stream_get_contents($pipes[1]);

    if (!$status['running']) {
      // Break from this loop if the process exited before the timeout.
      break;
    }

    // Subtract the number of microseconds that we waited.
    $timeout -= (microtime(true) - $start) * 1000000;
  }

  // Check if there were any errors.
  $errors = stream_get_contents($pipes[2]);

  if (!empty($errors)) {
    throw new Exception($errors);
  }

  // Kill the process in case the timeout expired and it's still running.
  // If the process already exited this won't do anything.
  proc_terminate($process, 9);

  // Close all streams.
  fclose($pipes[0]);
  fclose($pipes[1]);
  fclose($pipes[2]);

  proc_close($process);

  return $buffer;
}


//FUNCIONES PARA SIMULAR GENERACION DE LOGS
function init_log($level)  {
}

function write_log($level, $message)  {
}

function end_log()  {
}


function file_check_integrity($filename, $path) {
  $OK=true;
  try {
    $file = file($path ."/".$filename);
    $nlines=count($file);
    $header_fields=explode(",",$file[1]);
    $nlines_header=$header_fields[0];
    echo "\ncabecera:$file[1] \n nlines:$nlines  nlines_head:$nlines_header \n\n";
    if ($nlines != $nlines_header+4)  {
      $OK=false;
    }
  }
  catch  (Exception $e) {
      $OK=false;
  }
  return $OK ;
}

function file_read($filename, $path) {
  $OK=true;
  try {
      $data=file_get_contents($path ."/" .$filename );
  }
  catch  (Exception $e) {
      $OK=false;
  }
  return $data ;
}

function file_delete($filename, $path) {
  $OK=true;
  try {
      $OK1=file_exists ($path ."/" .$filename);
      if ( $OK1 ) {
        $OK=unlink($path ."/" .$filename) ;
      }
  }
  catch  (Exception $e) {
      $OK=false;
  }
  return $OK ;
}

function file_move($filename, $pathorig, $pathdest) {
  $OK=true;
  try {
      $OK1=file_exists ($pathorig);
      if ( !$OK1 ) {
        $OK1=mkdir($pathorig);
      }
      $OK2=file_exists ($pathdest);
      if ( !$OK2 ) {
        $OK2=mkdir($pathdest);
      }
      if ($OK1 && $OK2)  {
        $OK=rename($pathorig ."/" .$filename, $pathdest."/" .$filename) ;
    echo "\nmoviendo " .$pathorig ."/" .$filename ." -> " .$pathdest ."/" .$filename ."\n";
      }
  }
  catch  (Exception $e) {
      $OK=false;
  }
  return $OK ;
}


 ?>