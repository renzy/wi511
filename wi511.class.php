<?php
// documentation & api key requests > http://www.511wi.gov/web/extras/developer/apidocumentation.aspx

class wi511 {
	private $base = 'http://www.511wi.gov/web/api/';
	private $key = false;
	public $format = 'json';

	public function __construct($key=false){
		if(!$key) return;
		$this->key = $key;
		$this->root = realpath(dirname(__FILE__)).'/';
		$this->endpoints = $this->file_get($this->root.'endpoints.json');
		$this->curl_timeout = 30;
	}

	public function get($endpoint=false){
		if(!$endpoint) return false;
		elseif($this->endpoints && empty($this->endpoints->$endpoint)) return false;
		$uri = $this->base.$endpoint.'?key='.$this->key.'&format='.$this->format;
		$data = $this->curl_get($uri);
		$this->file_write($this->root.'data/'.$endpoint.'.json',$data);
		return $data;
	}

	public function all(){
		if(!$this->endpoints) return false;
		$set = [];
		foreach($this->endpoints as $key=>$val)
			$set[$key] = $this->base.$key.'?key='.$this->key.'&format='.$this->format;
		$data = $this->curl_get($set);
		foreach($data as $key=>$val) $this->file_write($this->root.'data/'.$key.'.json',$val);
		return $data;
	}

	public function curl_head($set=[]){
		return $this->curl_fetch($set,true);
	}

	public function curl_get($set=[]){
		return $this->curl_fetch($set,false);
	}

	public function curl_fetch($set=[],$nobody=false){
		if(gettype($set)=='string') $set = [$set];

		$ch = array();
		$mh = curl_multi_init();
		foreach($set as $key=>$i){
			$ch[$key] = curl_init();
			curl_setopt($ch[$key],CURLOPT_URL,$i);
			curl_setopt($ch[$key],CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch[$key],CURLOPT_CONNECTTIMEOUT,$this->curl_timeout);
			curl_setopt($ch[$key],CURLOPT_TIMEOUT,$this->curl_timeout);
			curl_setopt($ch[$key],CURLOPT_HEADER,1);
			curl_setopt($ch[$key],CURLOPT_SSL_VERIFYHOST,0);
			curl_setopt($ch[$key],CURLOPT_SSL_VERIFYPEER,0);
			curl_setopt($ch[$key],CURLOPT_NOBODY,$nobody);
			curl_multi_add_handle($mh,$ch[$key]);
		}

		$running = null;
		do {
			curl_multi_exec($mh,$running);
			curl_multi_select($mh);
		} while ($running>0);

		foreach($ch as $key=>$val) curl_multi_remove_handle($mh,$ch[$key]);
		curl_multi_close($mh);

		$response = [];
		foreach($ch as $key=>$val){
			$data = $this->curl_response($ch[$key]);
			$response[$key] = $data;
		}
		return (count($response)==1 && !empty($response[0])) ? $response[0] : $response;
	}

	public function curl_response($ch){
		$info = (object) curl_getinfo($ch);
		$raw = curl_multi_getcontent($ch);
		$header = new stdClass();
		foreach(explode("\n",substr($raw,0,$info->header_size)) as $i){
			if(empty($i) || preg_match('/^\s+$/',$i)) continue;
			elseif(preg_match('/\:\ /',$i)){
				$pair = explode(': ',$i,2);
				$header->{str_replace('-','_',strtolower($pair[0]))} = preg_replace('/(^\")|(\"$)/','',trim($pair[1]));
			}
		}
		$content = substr($raw,$info->header_size);

		return (object)[
			'info' => $info,
			'header' => $header,
			'content' => (strlen($content)>0) ? $this->string_json($content) : false
		];
	}

	public function file_write($path,$data){
		if(preg_match('/object|array/i',gettype($data))) $data = json_encode($data,JSON_PRETTY_PRINT);
		if(!file_exists(dirname($path))) mkdir(dirname($path),0777,true);
		$fh = fopen($path,'w');
		fwrite($fh,$data);
		fclose($fh);
	}

	public function file_get($path,$check=true){
		if(file_exists($path)){
			$data = file_get_contents($path);
			return ($check)
				? $this->string_json($data)
				: $data;
		} else return false;
	}

	public function string_json($inbound){
		$json = json_decode($inbound);
		return (json_last_error()==JSON_ERROR_NONE)
			? $json
			: $inbound;
	}
}
?>