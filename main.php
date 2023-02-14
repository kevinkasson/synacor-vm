<?php
/*
 * Synacor Challenge Virtual Machine
 *
 * Kevin Kasson, April 2016
 *
*/

class SynacorVM {
	private $register = array(0,0,0,0,0,0,0,0);
	private $stack = array();
	private $ins = array();
	private $i = 0;
	private $input='';
	private $paused = false;
	private $pauseat = -1;
	private $willdebug = false;
	
	function __construct($data,$input='') {
		$this->input=$input;
		if (is_array($data)) $this->ins = $data;
		else if (is_string($data)) {
			if (file_exists($data)) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
                if (substr(finfo_file($finfo, $data),0,4) == 'text')  $this->createFromTextFile($data);
                else $this->createFromBinaryFile($data);
                finfo_close($finfo);
			}
			else {
  			  $this->ins = $this->normalizeData(explode(',',$data));
  			}
		}
	}
	function createFromTextFile($f) {
		if (($input=file_get_contents($f)) == false) throw new Exception ('Error loading input file.');
		return $this->ins = explode(',',str_replace(array(PHP_EOL,' '),array(',',','),$input));
	}
	function createFromBinaryFile($f) {
		if (($input=file_get_contents($f)) == false) throw new Exception ('Error loading input file.');
		return $this->ins = array_values(unpack('v*', $input));
	}
	function normalizeData($data) {
		$search=array('halt','set','push','pop','eq','gt','jmp','jt','jf','add','mult','mod','and','or','not','rmem','wmem','call','ret','out','in','noop');
		$replace=array('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21');
		return str_replace($search,$replace,$data);
	}
	function unnormalizeData($data) {
		$return='';
		for($i=0;$i<count($data);$i++) {
		  $instruction=$data[$i];
		  $return.= $i . ': ';
		  switch ($instruction) {
  		    case '0':
  		      $instruction='halt';
  		      break;
  		    case '1':
  		      $instruction='set ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '2':
  		      $instruction='push ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '3':
  		      $instruction='pop ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '4':
  		      $instruction='eq ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '5':
  		      $instruction='gt ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '6':
  		      $instruction='jmp ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '7':
  		      $instruction='jt ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '8':
  		      $instruction='jf ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '9':
  		      $instruction='add ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '10':
  		      $instruction='mult ' . $data[$i+1] . ' ' . $data[$i+2] . ' '. $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '11':
  		      $instruction='mod ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '12':
  		      $instruction='and ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '13':
  		      $instruction='or ' . $data[$i+1] . ' ' . $data[$i+2] . ' ' . $data[$i+3];
  		      $i+=3;
  		      break;
  		    case '14':
  		      $instruction='not ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '15':
  		      $instruction='rmem ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '16':
  		      $instruction='wmem ' . $data[$i+1] . ' ' . $data[$i+2];
  		      $i+=2;
  		      break;
  		    case '17':
  		      $instruction='call ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '18':
  		      $instruction='ret';
  		      break;
  		    case '19':
  		      $instruction='out ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '20':
  		      $instruction='in ' . $data[$i+1];
  		      $i+=1;
  		      break;
  		    case '21':
  		      $instruction='noop';
  		      break;
		  }
		  $return .= $instruction . "\r\n";
		}
		return trim($return);
	}
	function getMemoryPointerPosition() {
		return $this->i;
	}
	function getAllMemory($assembly=false) {
		return $assembly ? $this->unnormalizeData($this->ins) : $this->ins;
	}
	function getRegister($r) {
		if (($r >= 0) && ($r < 8)) return $this->register[$r];
		if (($r >= 32768) && ($r < 32776)) return $this->register[$r - 32768];
		throw new Exception('Invalid register ' . $r);
	}
	function isValue($v) {
		return (($v >= 0) && ($v < 32768));
    }
    function isRegister($v) {
    	return (($v >= 32768) && ($v < 32776));
    }
    function getValue($v) {
    	if ($this->isValue($v)) return $v;
    	if ($this->isRegister($v)) return $this->getRegister($v);
    	throw new Exception('Invalid value ' . $v);
    }
    function setRegister($r,$v) {
    	if (($r >= 0) && ($r < 8)) $this->register[$r] = $v;
		elseif (($r >= 32768) && ($r < 32776)) $this->register[$r - 32768] = $v;
		else throw new Exception('Invalid register ' . $r);
    	return ($this->getRegister($r) == $v);
    }
    function getMemory($i) {
    	return $this->ins[$i];
    }
    function setMemory($i,$d) {
    	$this->ins[$i] = $d;
    }
    function doInstruction() {
    	if ($this->i == $this->pauseat) { $this->paused=true; }    	
    	if ($this->paused) {
    		print "\r\nDebugger: paused at instruction " . $this->i . ' -> ';
    		$f = fgets(STDIN);
    		if (substr($f,0,4) == 'help') { print "\r\nSynacorVM Debugger Help\r\n\r\nWhile the debugger is running, it will print the memory location of the current instrution being executed.\r\nType 'stack' to view the stack, 'registers' to view the registers, or a memory location (i.e. 1458) to execute code until that memory location.\r\nUse 'set x y' to set register x (0-7) to y.\r\nEnter a memory address (i.e. 6027) to continue execution until that point is reached.\r\nYou can also type show xx to show the next xx instructions.  xx must be a two-digit number, such as 14 or 05.\r\nType 'stop' to exit the debugger, and 'next' to execute the next instruction while still in the debugger.\r\n"; return; }
    		if (substr($f,0,4) == 'show') { 
    		  for ($j=0;$j<substr($f,5,2);$j++) {
    		    print $this->i+$j . ': ' . $this->ins[$this->i+$j] . ' ';
    		    if ($j % 6 == 5) print "\r\n";
    		  }
    	  	  return;
    		}
    		if (substr($f,0,4) == 'next') {
    			switch ($this->ins[$this->i]) {
    			case '0':
    				print "halt\r\n";
    				$this->pauseat+=1;
    				break;
    			case '1':
    				print 'set ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '2':
    				print 'push ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '3':
    				print 'pop ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '4':
    				print 'eq ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '5':
				 	print 'gt ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '6':
    				print 'jmp ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '7':
    				print 'jt ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '8':
    				print 'jf ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '9':
    				print 'add ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '10':
    				print 'mult ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '11':
    				print 'mod ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '12':
    				print 'and ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '13':
    				print 'or ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . ' ' . $this->ins[$this->i+3] . "\r\n";
    				$this->pauseat+=4;
    				break;
    			case '14':
    				print 'not ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '15':
    				print 'rmem ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '16':
    				print 'wmem ' . $this->ins[$this->i+1] . ' ' . $this->ins[$this->i+2] . "\r\n";
    				$this->pauseat+=3;
    				break;
    			case '17':
    				print 'call ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '18':
    				print "ret\r\n";
    				$this->pauseat+=1;
    				break;
    			case '19':
    				print 'out ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '20':
    				print 'in ' . $this->ins[$this->i+1] . "\r\n";
    				$this->pauseat+=2;
    				break;
    			case '21':
    				print "noop\r\n";
    				$this->pauseat+=1;
    				break;
    			}
    		}
    		if (substr($f,0,3) == 'set') { $this->setRegister($f[4],substr($f,6,strlen($f)-8)); return; }
    		if (substr($f,0,5) == 'stack') { print str_replace(array(chr(10),chr(13)),array(' ',' '),print_r($this->stack,true)); return; }
    		if (substr($f,0,9) == 'registers') { print str_replace(array(chr(10),chr(13)),array(' ',' '),print_r($this->register,true)); return; }
    		if (substr($f,0,4) == 'stop') { $this->paused=false; $this->pauseat=-1; }
    		if (is_numeric(substr($f,0,strlen($f)-2))) { $this->pauseat=intval(substr($f,0,strlen($f) - 2)); $this->paused=false; }
    		if (strlen($f) < 3) { $this->paused=false; }
    	}
    	
      try {
        switch ($this->ins[$this->i]) {
          case '0':
            die("\r\nExecution ended by halt.");
            break;
          case '1':
            $this->setRegister($this->ins[$this->i+1],$this->getValue($this->ins[$this->i+2]));
            $this->i+=3;
            break;
          case '2':
            $this->stack[]=$this->getValue($this->ins[$this->i+1]);
            $this->i+=2;
            break;
          case '3':
            if (count($this->stack) < 1) throw new Exception('Error: empty stack');
            $this->setRegister($this->ins[$this->i+1], array_pop($this->stack));
            $this->i+=2;
            break;
          case '4':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i+2]) == $this->getValue($this->ins[$this->i+3])) ? 1 : 0);
            $this->i+=4;
            break;
          case '5':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i+2]) > $this->getValue($this->ins[$this->i+3])) ? 1 : 0);
            $this->i+=4;
            break;
          case '6':
            $this->i = $this->getValue($this->ins[$this->i + 1]);
            break;
          case '7':
          	$this->i = ($this->getValue($this->ins[$this->i + 1]) > 0 ) ? $this->getValue($this->ins[$this->i + 2]) : $this->i + 3; 
            break;
          case '8':
            $this->i = ($this->getValue($this->ins[$this->i + 1]) == 0 ) ? $this->getValue($this->ins[$this->i + 2]) : $this->i + 3; 
            break;
          case '9':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i + 2]) + $this->getValue($this->ins[$this->i + 3])) % 32768);
            $this->i+=4;
            break;
          case '10':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i + 2]) * $this->getValue($this->ins[$this->i + 3])) % 32768);
            $this->i+=4;
            break;
          case '11':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i + 2]) % $this->getValue($this->ins[$this->i + 3])) % 32768);
            $this->i+=4;
            break;
          case '12':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i + 2]) & $this->getValue($this->ins[$this->i + 3])));
            $this->i+=4;
            break;
          case '13':
            $this->setRegister($this->ins[$this->i+1],($this->getValue($this->ins[$this->i + 2]) | $this->getValue($this->ins[$this->i + 3])));
            $this->i+=4;
            break;
          case '14':
            $this->setRegister($this->ins[$this->i+1],32767 - $this->getValue($this->ins[$this->i + 2]));
            $this->i+=3;
            break;
          case '15':
            $this->setRegister($this->ins[$this->i+1],$this->getMemory($this->getValue($this->ins[$this->i+2])));
            $this->i+=3;
            break;
          case '16':
            $this->setMemory($this->getValue($this->ins[$this->i+1]),$this->getValue($this->ins[$this->i+2]));
            $this->i+=3;
          break;
          case '17':
            $this->stack[] = $this->i+2;
            $this->i = $this->getValue($this->ins[$this->i + 1]);
            break;
          case '18':
            if (count($this->stack) < 0) throw new Exception('Error: empty stack');
            $this->i = array_pop($this->stack);
            break;
          case '19':
            print chr($this->getValue($this->ins[$this->i+1]));
            $this->i+=2;
            break;
          case '20':
            if (strlen($this->input) < 1) $this->input = str_replace(chr(13),'',fgets(STDIN));
            if (substr($this->input,0,7) == '-debug ') { $this->input=substr($this->input,7); $this->willdebug=true; }
            if (substr($this->input,0,7) == '-output') { print 'Writing assembly file...'; file_put_contents('assembly.txt',$this->getAllMemory(true)); print "done!\n"; $this->input = str_replace(chr(13),'',fgets(STDIN)); }
            if ((substr($this->input,0,1) == chr(10)) && ($this->willdebug)) { $this->paused=true; $this->willdebug=false; }
            if (substr($this->input,0,14) == 'use teleporter') {
              $this->register[7] = 25734;
              $this->ins[5489] = '21';
              $this->ins[5490] = '21';
              $this->ins[5491] = '1';
              $this->ins[5493] = '1';
              $this->ins[5494] = '21';
            }
            $this->setRegister($this->ins[$this->i+1],ord($this->input[0]));
            $this->input=substr($this->input,1);
            $this->i+=2;
            break;
          case '21':
            $this->i++;
            break;
          default: {
            throw new Exception("\r\nInvalid instruction at ". $this->i . ': ' . $this->ins[$this->i]);
          }
        }
      }
      catch (Exception $ex) {
        die ($ex->getMessage());
      }
	}
	function execute() {
		while ($this->i <= count($this->ins)) {
  	      $this->doInstruction();
	  }
	}
}
$filename='challenge.bin';
$input='';


$vm = new SynacorVM($filename,$input);
$vm->execute();
?>