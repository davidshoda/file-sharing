<?php
namespace Drupal\import_files\Controller;

include_once("ImportFilesController.php");

/**
 * Tests CSV and FW files with headers, and type from column specified values, override type name(same as test 2 with override),
 * 11/1/2017 verified no errors/issues after adding taxonomy term functionality
 */
class ImportFilesTest3Controller extends ImportFilesController{
  public function test3()
  {
	  return $this->import_files();
  }
  /**
   * Entry point
   */
  public function import_files()
  {
    $this->set_directory_path("files-test/test3");
    $this->set_default_column_name("column");
    $this->set_file_params();
    $this->set_params();
    foreach($this->file_specs as $k=>$v){
      if( !isset($this->file_specs[$k]["type_name_column"]) || trim($this->file_specs[$k]["type_name_column"])==""){
       $_create_types_fields = $this->create_types_fields($this->file_specs[$k]["field_definitions"],null);//param 2 as column name
        foreach( $_create_types_fields["types"] as $_k=>$_v) {
          $this->file_specs[$k]["import_stats"]["types"][$_k] = $_v;
        }
        $this->file_specs[$k]["import_stats"]["types_created"] += $_create_types_fields["types_created"];
      }
    }
    $this->create_nodes(); //data nodes
    $import_report = $this->get_import_report();
    return array(
      "#title" => t("import complete"),
      "#markup" => t(nl2br($import_report)),
    );
  }

  public function set_file_params()
  {
    $this->file_specs = array(
      "FixedWidthExample2.txt" 	=> array(
        "path" => "", //dynamically set by reading files
        "column_count" => -1, //dynamically set by reading files
        "type_name_column" => "Srce", //case sensitive, no punctuation: sets type, filename as type if not set: overrides filename as type name, sets to filename if empty
        //"type_name_override" => "MyFWFilenameType", //overrides type name from filename or from column
        "taxonomy_terms" => "GL Account", //comma delimited terms in addition to type name
        "min_fields"	=> array( //skip line or include
          "header" => NULL, // for min_fields for setting header_row, null or not set is all kv's req
          "data" => 3, // for min_fields for setting data rows, null or not set is all kv's req
        ),
        "header_row" => array(), //dynamically set by reading files
        "field_default_overrides" 		=> 		array(), //manually set, overrides set_type_fields()
        "field_definitions" 			=>		array(), //dynamically set from defaults and overrides
        "import_stats" => array(
          "types" => array(),
          "types_created" 			=>		0, //dynamically set while importing
          "data_nodes_created" 			=>		0, //dynamically set while importing
        ),
        "positions"	=> array( // required for txt (both)
          "header" 						=> 		"1,9,13,25,38,64,70,75,83,87,107,125",
          "data" 						=> 		"1,9,13,25,38,64,70,74,83,87,107,125",
        ),
      ),
      "SalesJan2009.csv" 	=> array(
        "path" => "", //dynamically set by reading files
        "column_count" => -1, //dynamically set by reading files
        "type_name_column" => "product", //case sensitive, no punctuation: sets type, filename as type if not set: overrides filename as type name, todo: override column as data value as content type
        //"type_name_override" => "MyCSVFilenameType", //overrides type name from filename or from column
        "taxonomy_terms" => "Sales,2009,product", //comma delimited terms in addition to type name
        "min_fields"	=> array( //skip line or include
          "header" => NULL, // for min_fields for setting header_row, null or not set is all kv's req
          "data" => 3, // for min_fields for setting data rows, null or not set is all kv's req
        ),
        "header_row" => array(), //dynamically set by reading files
        "field_default_overrides" 		=> 		array(), //manually set, overrides set_type_fields()
        "field_definitions" 			=>		array(), //dynamically set from defaults and overrides
        "import_stats" => array(
          "types" => array(),
          "types_created" 			=>		0, //dynamically set while importing
          "data_nodes_created" 			=>		0, //dynamically set while importing
        ),
      ),
    );
  }
}



