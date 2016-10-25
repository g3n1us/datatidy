<?php
	if ( !function_exists('str_contains') || !function_exists('collect') || !class_exists('Michelf\Markdown') ){
		require_once(__DIR__.'/vendor/autoload.php');
	}
		

if(!function_exists('dir_exists')){
	function dir_exists($dir){
		return count(Storage::allFiles($dir)) > 0;
	}		
}

if(!function_exists('copy_directory')){
	function copy_directory($from, $to){
		$from = str_finish(ltrim($from, "/"), "/");
		$to = str_finish(ltrim($to, "/"), "/");
		foreach(Storage::allFiles($from) as $fromfile){
			$tofile = str_replace($from, $to, $fromfile);
// 			echo("$fromfile || $tofile<br>");
			if(!Storage::exists($tofile)) Storage::copy($fromfile, $tofile);
		}
		return count(Storage::allFiles($from)) == count(Storage::allFiles($to));
	}
}

if(!function_exists('cache_key')){
	function cache_key($request_path_or_classname_at_notation){
		// Explanation: if caching a url response, use the request path. Otherwise use the Class method that is producing the cached value, eg. 'Lep@getdata'. The base url will be prefixed onto both of these this to keep it unique to a domain/project. Will be returned in md5
		// Todo find a way to target all aliases
		$base = url('/');
		$string = str_finish($base, '/') . trim($request_path_or_classname_at_notation);

		return md5(rtrim($string, "/"));
	}
}



// returns only letters and numbers
if(!function_exists('alphanumeric')){
	function alphanumeric($string){
		return (string) preg_replace('/[^ \w]+/', '', $string);
	}
}

if(!function_exists('normalize_date')){
	function normalize_date($datestring, $inctime = false){
		$timeint = strtotime($datestring);
		if($inctime) return date("Y-m-d g:i:s A", $timeint);
		else return date("Y-m-d", $timeint);		
	}
}

if(!function_exists('tidy_time')){
	function tidy_time($timeint, $inctime = true){
		if($inctime) return date("M d, Y g:i:s A", $timeint);
		else return date("M d, Y", $timeint);
		
	}
}

if(!function_exists('timestamp')){
	function timestamp(){
		return date("M d, Y g:i:s A");
	}
}

if(!function_exists('db_timestamp')){
	function db_timestamp(){
		return date("Y-m-d H:i:s");
	}
}

if(!function_exists('tidy_snake')){
	function tidy_snake($string){
		return ucwords(str_replace("_", " ", $string));
	}
}
//aliases
if(!function_exists('title_case')){
	if(!function_exists('title_case')){
		function title_case($string){
			return tidy_snake($string);
		}		
	}
}

if(!function_exists('fallback_base_domain')){
	function fallback_base_domain(){
		if(isset($_SERVER['BASE_DOMAIN']))
			$fallback_base_domain = $_SERVER['BASE_DOMAIN'];
		else if(isset($_SERVER['HTTP_HOST'])){
			$fallback_base_domain = $_SERVER['HTTP_HOST'];
			$fallback_base_domain_parts = explode(".", $fallback_base_domain);
			$fallback_base_domain = $fallback_base_domain_parts[count($fallback_base_domain_parts) - 2] . "." . $fallback_base_domain_parts[count($fallback_base_domain_parts) - 1];
		}
		else $fallback_base_domain = "washingtonexaminer.com";
		return $fallback_base_domain;
	}
}

// snippet: us states select fields
if(!function_exists('us_states_options')){
	function us_states_options(){
		return @file_get_contents(dirname(__FILE__) . "/_snippets/us_states_select.php");
	}
}
		
// function mdc_simple_data($url, $resultsas = 'collection', $sort = false, $ascending = false, $nomd = false){
		
	
// if the second argument is an array, it will override the other arguments (remaining args are deprecated)

if(!function_exists('mdc_simple_data')){
	function mdc_simple_data($url, Array $useroptions = []){
		$defaultoptions = [
			'resultsas' => 'collection',
			'sort' => false,
			'ascending' => false,
			'nomd' => false,
			'paginate' => false,
			'show_pagination' => true,
			'results_per_page' => 12,
			'fallback_document' => __DIR__.'/etc/mdc_simple_data.txt',
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
			$filecontents = gproxy($key, false, 'json');
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
						$rows[$index][$index2] = selective_md($part);
					}
				}				
			}
		}
		else{
			$rows = explode("\n\n\n\n\n", $filecontents);
			foreach($rows as $index => $row){
				$rows[$index] = collect([]);
				foreach(explode("\n\n\n", trim($row)) as $index2 => $part){
					$rows[$index]->push(selective_md($part));
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
			if($options['show_pagination']) echo('<div class="mdc_simple_data_pagination center-block text-center">' . $results->render() . '</div>');
			return $results;
			
		}
		
			
		else return $rows->toArray();
		
	}	
} 

function selective_md(&$string){
	if(is_array($string)){
		foreach($string as $k => $v){
			$string[$k] = selective_md($v);
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
	
	

// Some notes: the GET key: v, will always be applied to the cache name to bust caching.
// Implement, modification time implementations for commonly used json endpoints, eg. Google Spreadsheets, Twitter etc. Adapter should return UNIX time for mod time or 0 if fail
if(!function_exists('json_cache')){
	
	function json_cache($endpoint, $minutes = 0, $cachingadapter = null){
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
	
}

if(!function_exists('json_cache_gsheet_adapter')){
	
	function json_cache_gsheet_adapter($json){
		return strtotime( array_get(json_decode($json, true), 'feed.updated.$t', "January 1, 1970" ) );
	}
	
}


// set $sheetkey to false in order to receive an array of data with sheet names as keys
function gproxy($key, $sheetkey = 1, $format = 'collection'){
	// https://spreadsheets.google.com/feeds/list/1azLEgJQ9XqkOsNo5VTDcWok2AIeUute-8Ie0vTRf-L0/1/public/values?alt=json

	$sheetarray = [];
		
	$iterate = true;
	if(!is_numeric($sheetkey)){
		$currentkeyindex = 1;
	}
	else 
		$currentkeyindex = $sheetkey;
			
		while($iterate){
			$endpoint = "https://spreadsheets.google.com/feeds/list/$key/$currentkeyindex/public/values?alt=json&sheetname=$sheetkey";
			$json = json_cache($endpoint, 60);  // disabled!!!!
			
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
				$array = array_where($item, function ($value) {
				    return starts_with($value, 'gsx$');
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

if(!function_exists('simple_template')){
	function simple_template($data, $template = ""){
		if(!is_object($data)) $data = collect($data);
		if(!$template) $template = "hello";
		$data->transform(function($row, $key) use($template){
			foreach($row as $k => $v){
				if(!is_string($v)) continue;
				$template = str_replace('{{'.$k.'}}', $v, $template);
			}
			return $template;
		});
		return $data->implode("\n");		
	}
}


if(!function_exists('mime')){
	function mime($path){
		$path = strtolower($path);
		if(ends_with($path, ".css")) $mime = "text/css";
		else if(ends_with($path, ".less")) $mime = "text/css";
		else if(ends_with($path, ".sass")) $mime = "text/css";
		else if(ends_with($path, ".scss")) $mime = "text/css";
		else if(ends_with($path, ".mp4")) $mime = "video/mp4";
		else if(ends_with($path, ".mov")) $mime = "video/quicktime";
		else if(ends_with($path, ".js")) $mime = "application/javascript";
		else if(ends_with($path, ".pdf")) $mime = "application/pdf";
		else if(ends_with($path, ".svg")) $mime = "image/svg+xml";
		else if(ends_with($path, ".jpg")) $mime = "image/jpeg";
		else if(ends_with($path, ".jpeg")) $mime = "image/jpeg";
		else if(ends_with($path, ".png")) $mime = "image/png";
		else if(ends_with($path, ".gif")) $mime = "image/gif";
		else if(ends_with($path, ".ico")) $mime = "image/vnd.microsoft.icon";
		else if(ends_with($path, ".json")) $mime = "application/json";
		else if(ends_with($path, ".ttf")) $mime = "application/x-font-truetype";
		else if(ends_with($path, ".woff")) $mime = "application/font-woff";
		else if(ends_with($path, ".woff2")) $mime = "application/font-woff2";
		else if(ends_with($path, ".otf")) $mime = "application/x-font-opentype";
		else if(ends_with($path, ".eot")) $mime = "application/vnd.ms-fontobject";
		else if(ends_with($path, ".md")) $mime = "text/markdown; charset=UTF-8";
		else if(ends_with($path, ".swf")) $mime = "application/x-shockwave-flash";
		else if(ends_with($path, ".php")) $mime = "text/plain";
			
		else{
			$mime = "text/html";
		}
		return $mime;
	}		
}