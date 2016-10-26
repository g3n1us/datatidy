# datatidy
Get data from Google Sheets or some other sources, and return standardized, structured data

## Usage

#### Options
```php
$defaultoptions = [
	'allow_origin' => null, // If a response is to be generated, this value will be applied to an Access-Control-Allow-Origin header
	'resultsas' => 'collection', // or paginate or array
	'sort' => false, // key by which to sort
	'ascending' => false, // applies to the sort order
	'nomd' => false, // my default, content will be converted to HTML with Markdown, this option disables this
	'paginate' => false,
	'show_pagination' => true,
	'results_per_page' => 12,
];
```
### Arguments
```php
##### URI or path to retrieve data. 
To retrieve a Google Sheets spreadsheet, use the format: gproxy://<spreadsheet-key>
note: The spreadsheet must be published publicly!

For other JSON formatted URIs, specify either a relative or absolute endpoint to retrieve

##### OPTIONS: An array of options as shown above

```
#### Call via static methods
```php
DataTidy::response("gproxy://1R4ZW6fw7EggY6AsmBtVGWdjny-UYDgv3au6_VarHBMk", ['allow_origin' => '*']);
// Returns a full Response

DataTidy::get("gproxy://1R4ZW6fw7EggY6AsmBtVGWdjny-UYDgv3au6_VarHBMk");
// Returns the data as an instance of Illuminate\Collection
```

#### Call via constructor
```php
use G3n1us\DataTidy;

$datatidy = new Datatidy("gproxy://1R4ZW6fw7EggY6AsmBtVGWdjny-UYDgv3au6_VarHBMk");
$datatidy->get();
// or
$datatidy->response();
```