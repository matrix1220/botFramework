<?php
require 'Telegrambot.php';
header("Content-Type: application/json");

const ADMIN = 108268232; // Telegram User ID

set_error_handler(function($errno,$errstr,$errfile,$errline) {
	bot()->sendMessage(ADMIN,'Error: '.$errstr."\n".basename($errfile).' '.$errline);
	exit();
});
set_exception_handler(function($e) {
	try {
		throw $e;		
	} catch(SuccesException $e) {
		if($e->handler->type==0) {
			// TODO:  Hechnarsa bajarilmasligi karak
		} elseif($e->handler->type==1) {
			Telegrambot::staticSendMessage($e->value);
			//bot()->sendMessage(input()->message->chat->id,$e->value);
		} elseif($e->handler->type==2) {
		} elseif($e->handler->type==3) {
		}
	} catch(Exception $e) {
		bot()->sendMessage(ADMIN,'Exception: '.$e->getMessage()."\n".basename($e->getFile())." ".$e->getLine());
	}
	exit();
});


class SuccesException extends Exception {
	public function __construct($value,$handler) {
		$this->handler=$handler;
		$this->value=$value;
        parent::__construct('The code have been successfully done');
    }
}

class Filter {
	function __construct($id,$func=null) {
		global $filters;
		$this->id=$id;
		$this->func=$func;
		$this->filters=[];
		$filters[$id]=&$this;
	}
	function filter($value,&$handler) {
		foreach ($this->filters as $filter) {
			$handler->input=$filter[0]->filter($filter[1],$handler);
			if($handler->input===false) return false;
		}
		$temp=call_user_func($this->func,$value,$handler);
		if(isset($temp)) $handler->input=$temp;
		return $handler->input;
	}
	function addFilter($id,$value) {
		$this->filters[]=[&$GLOBALS['filters'][$id],$value];
		return $this;
	}
	function defineMainFilter($id) {
		$this->func=&$GLOBALS['filters'][$id]->func;
		return $this;
	}
}

class Handler {
	function __construct($func) {
		global $input;
		$this->input=$input;
		$this->func=$func;
		$this->type=0;
	}
	function __destruct() {
		if($this->input!==false) {
			$temp=call_user_func($this->func,$this);
			if($temp!==false) throw new SuccesException($temp,$this);
		}
	}
	function addFilter($id,$value) {
		if($this->input!==false) $this->input=filters($id)->filter($value,$this);
		return $this;
	}
	function filter($id,$value) {
		$temp=$this->input;
		$r=filters($id)->filter($value,$this);
		$this->input=$temp;
		return $r;
	}
}

global $bot;
$bot=new Telegrambot('TOKEN'); // TOKEN ni almashtiring
function bot($method=null,$data=null) {
	global $bot;
	if(isset($method)) return $bot->method($method,$data);
	else return $bot;
}

global $input;
$input=json_decode(file_get_contents('php://input'));
function input() {
	global $input;
	return $input;
}

global $filters;
$filters=[];
function filters($i) {
	global $filters;
	return $filters[$i];
}


(new Filter(0,function($value,&$handler){ //
	$handler->type=$value;
}));

(new Filter(1,function($value,&$handler){ // universal filter
	if(isset($handler->input->{$value})) return $handler->input->{$value};
	else return false;
}));

(new Filter(2 // message type
))->defineMainFilter(1)->addFilter(1,'message');

(new Filter(3,function($value,&$handler){// text regexp
	if(preg_match('/'.$value.'/', $handler->input)!==1) return false;
}))->addFilter(2,'text');

(new Filter(4,function($value,&$handler){// commands
	if(preg_match('/^\\/'.$value.'( (.+))?$/', $handler->input,$temp)!==1) return false;
	return isset($temp[2])?$temp[2]:null;
}))->addFilter(2,'text');

(new Filter(5,function($value,&$handler){ // callback_query
	$temp=explode(":",$handler->input->data);
	if($value==array_shift($temp)) return $temp;
	return false;
}))->addFilter(1,'callback_query');


	// (new Handler(function($handler){
	// 	return "Salom";
	// }))->addFilter(4,'start')->addFilter(0,1);