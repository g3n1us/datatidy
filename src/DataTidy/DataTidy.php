<?php
	namespace DataTidy;
	
	use Illuminate\Http\Response;
	
	require(dirname(dirname(__DIR__))."/helpers.php");
	
	/**
	* DataTidy
	*/
	class DataTidy {
	
		private $endpoint = null;
		private $options = null;
	
		public function __construct($endpoint, $options = []){
			$this->endpoint = $endpoint;
			$this->options = $options;
		}
		
		public static function __callStatic($name, $arguments = null){
			$args = (isset($arguments[1]) && is_array($arguments[1])) ? $arguments[1] : [];
			return (new DataTidy($arguments[0], $args))->response();
		}
	
		public function get(){
			return $this->simple_data($this->endpoint, $this->options);
		}
		
		public function response($type = "json"){
			$content = $this->simple_data($this->endpoint, $this->options);
			$response = new Response;
			$response->setContent($content)->send();
		}		
		
		public function __toString(){
			return $this->get()->toJSON();
		}
		
		private function simple_data($url, Array $useroptions = []){
			$defaultoptions = [
				'resultsas' => 'collection',
				'sort' => false,
				'ascending' => false,
				'nomd' => false,
				'paginate' => false,
				'show_pagination' => true,
				'results_per_page' => 12,
				'fallback_document' => __DIR__.'/etc/datatidy.txt',
			];
			if(is_array($useroptions)){
				$options = array_merge($defaultoptions, $useroptions);
			}
			else{
				$options = [];
				foreach($defaultoptions as $dkey => $dval) $options[$dkey] = isset($$dkey) ? $$dkey : $dval;
			}
			if($options['paginate']) $options['resultsas'] = 'paginate';
			
			if(starts_with($url, 'gproxy://')){
				$key = str_replace('gproxy://', '', $url);
				$filecontents = $this->gproxy($key, false, 'json');
			}
			else{
				if(!starts_with($url, 'http')) $url = url($url);
				$filecontents = trim(@file_get_contents($url));		
			}
			if(empty($filecontents)) $filecontents = trim(@file_get_contents($options['fallback_document']));
			if(is_array(json_decode($filecontents, true))){
				$rows = json_decode($filecontents, true);
				if(!$options['nomd']){
					foreach($rows as $index => $row){
						foreach($row as $index2 => $part){
							$rows[$index][$index2] = $this->selective_md($part);
						}
					}				
				}
			}
			else{
				$rows = explode("\n\n\n\n\n", $filecontents);
				foreach($rows as $index => $row){
					$rows[$index] = collect([]);
					foreach(explode("\n\n\n", trim($row)) as $index2 => $part){
						$rows[$index]->push($this->selective_md($part));
					}
				}
			}
			$rows = collect($rows);
			if($rows->count() == $rows->collapse()->count()) $rows = $rows->collapse();
			
			if($options['sort']){
				$rows = $options['ascending'] ? $rows->sortBy($options['sort'])->values() : $rows->sortByDesc($options['sort'])->values();
			} 
			
			if(str_contains($options['resultsas'], 'collect'))
				return $rows;
				
			else if(str_contains($options['resultsas'], 'pag') || $options['paginate']){
				$currentpage = array_get($_GET, 'page', 1);
				$resultsperpage = array_get($_GET, 'count', $options['results_per_page']);
			
				$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				$offset = ((int)$resultsperpage * ((int)$currentpage - 1));
				$slice = array_slice($rows->toArray(), $offset, $resultsperpage);
				$results = new \Illuminate\Pagination\LengthAwarePaginator($slice, count($rows), $resultsperpage, $currentpage, ['path' => $path, 'query' => ['count' => $resultsperpage]]);
				if($options['show_pagination']) echo('<div class="simple_data_pagination center-block text-center">' . $results->render() . '</div>');
				return $results;
				
			}
			
				
			else return $rows->toArray();
			
		}	
		
		
		
		
		private function gproxy($key, $sheetkey = 1, $format = 'collection'){
		
			$sheetarray = [];
				
			$iterate = true;
			if(!is_numeric($sheetkey)){
				$currentkeyindex = 1;
			}
			else 
				$currentkeyindex = $sheetkey;
					
				while($iterate){
					$endpoint = "https://spreadsheets.google.com/feeds/list/$key/$currentkeyindex/public/values?alt=json&sheetname=$sheetkey";
					$json = $this->json_cache($endpoint, 60);  // disabled!!!!
					
					if(empty($json)) {
						break;
					}
					// IMPORTANT INCREMENT!!!
					$currentkeyindex++;
					
					$array = json_decode($json, true);
		
					$sheetarraykey = $array['feed']['title']['$t'];
		
					$entry = array_get($array['feed'], 'entry', false);
					if($entry === false){
						continue;
					}
					$sanitized = json_encode($entry);
					
					$collection = collect($entry);
					
					$collection->transform(function ($item) {
						$array = array_where($item, function ($value, $key) {
						    return starts_with($key, 'gsx$');
						});	
						
						$new = [];
						foreach($array as $k => $v) {
							$k = str_replace('gsx$', '', $k);
							$new[$k] = $v['$t'];
						}
						return $new;
					});
					$collection = $collection->filter(function ($item) {
						return !empty(head($item));
					});
		
					if($format == 'json') $sheetarray[$sheetarraykey] = $collection->toArray();
					else if($format == 'array') $sheetarray[$sheetarraykey] = $collection->toArray();
					else $sheetarray[$sheetarraykey] = $collection;
					
					if($sheetarraykey == $sheetkey)
						break;
						
					// decide if we should continue
					if(is_numeric($sheetkey))
						$iterate = false;
				} // end of while
		
				if(is_numeric($sheetkey))
					$sheetarray = head($sheetarray);
				if(isset($sheetarray[$sheetkey])) $sheetarray = $sheetarray[$sheetkey];
				if($format == 'json') return json_encode($sheetarray);
				else if($format == 'array') return $sheetarray;
				else return collect($sheetarray);
			
		}
		
		
		

		
		private function selective_md(&$string){
			if(is_array($string)){
				foreach($string as $k => $v){
					$string[$k] = $this->selective_md($v);
				}
				return $string;
			}
			$string = trim($string);
			if(str_contains($string, ["\r\n", "\n"]) || starts_with($string, ['#', '*', '_', '!', '`', '[', '<' ])){
				$html = trim(\Michelf\MarkdownExtra::defaultTransform($string));
				// remove wrapping p if a single line
				if(!str_contains($string, "\n")){
					$html = ltrim($html, '<p');
					$html = ltrim($html, '>');
					$html = rtrim($html, '/p>');
					$html = rtrim($html, '<');
				} 
				$html = str_replace("\r\n", "\n", $html);
				$html = str_replace("\n\n", "\n", $html);
				$html = str_replace(">\n", ">", $html);
				$string = str_replace("\n", "<br />\n", $html);
				return $string;
			}
			else return $string;
		}
		
		
		
		private function json_cache($endpoint, $minutes = 0, $cachingadapter = null){
			if(!$minutes) Cache::forget($endpoint);
	
			$v = array_get($_GET, 'v', 1);
			if(class_exists('Cache')){
				$value = Cache::remember("$endpoint|$v", $minutes, function() use($endpoint){
				    return @file_get_contents($endpoint);
				});				
				return $value === false ? Cache::pull($endpoint) : $value;				
			}
			else return @file_get_contents($endpoint);
		}
		
		
		
		private function json_cache_gsheet_adapter($json){
			return strtotime( array_get(json_decode($json, true), 'feed.updated.$t', "January 1, 1970" ) );
		}
		
		
	
	
	} // close of class
	