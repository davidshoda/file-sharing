<?php
namespace Drupal\import_files\Controller;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\EntityFieldManager;
use Drupal\Core\Language\Language;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;

ini_set('auto_detect_line_endings',TRUE); //file newlines for mac files

class ImportFiles extends ControllerBase {

  public $file_path;
  public $file_specs;
  public $file_count;
  public $field_defaults;
  public $default_column_name;
  public $vocabulary_id;
  public $vocabulary_description;
  public $field_vocabulary_id;

  //todo: add to _install hook
  /**
   * Create vocabulary for this module
   */
  public function create_vocabulary() {
    $this->vocabulary_id = "import_files";//machine name
    $this->vocabulary_name = "Import Files Vocabulary";
    $this->vocabulary_description = "Import Files Vocabulary: Type Terms";
    $this->field_vocabulary_id = "field_" . $this->vocabulary_id; // type field across all imported/created types
    $vocabularies = \Drupal\taxonomy\Entity\Vocabulary::loadMultiple();
    if (!isset($vocabularies[$this->vocabulary_id])) {
      $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::create(array(
        'vid' => $this->vocabulary_id, //machine name
        'description' => $this->vocabulary_description,
        'name' => $this->vocabulary_name,
      ));
      $vocabulary->save();
    }
  }
  //create type as term if doesnt already exist
  public function create_vocabulary_term($type_name){
    //$field_machine_name = 'field_' . $this->name_to_machine_name($type_name);
	  //$term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);//todo: looks for tid
	  $term = taxonomy_term_load_multiple_by_name($type_name, $this->vocabulary_id);
	  if( !isset($term) || sizeof($term)<=0 ) {
  	  $term = Term::create(array(
	      'parent' => array(),
	      'name' => $type_name,
	      'description' => "Import Files Data Node Term: " . ($type_name),
	      'vid' => $this->vocabulary_id,
	    ))->save();
      $term = taxonomy_term_load_multiple_by_name($type_name, $this->vocabulary_id);
	  }
    foreach($term as $tid=>$obj) {
      return $tid;
      //return $term->id();
    }
    return null;
  }
  //create type field
  public function create_vocabulary_term_field()
  {
    $_field = \Drupal::entityManager()->getStorage('field_storage_config')->load('node.' . $this->field_vocabulary_id);
    if(isset($_field)){
      return;
    }
	  //https://www.drupal.org/node/2456869
      //https://www.drupal8.ovh/en/tutoriels/283/create-a-field-a-node-entity-programmatically-on-drupal-8
	  \Drupal\field\Entity\FieldStorageConfig::create(array(
	    'field_name' => $this->field_vocabulary_id,
	    'entity_type' => 'node',
	    'type' => 'entity_reference',
	    'settings' => array(
		    'target_type' => 'taxonomy_term',
	    ),
	    'cardinality' => -1,
	  ))->save();
  }
  //create type field instance
  public function create_vocabulary_term_instance($bundle)
  {
	$_field = \Drupal::entityManager()->getStorage('field_config')->load(
	  'node.' . $bundle . '.' . $this->field_vocabulary_id
	);
	if(isset($_field)){
      return;
	}
	//https://www.drupal.org/node/2456869
    //https://www.drupal8.ovh/en/tutoriels/283/create-a-field-a-node-entity-programmatically-on-drupal-8
	//Step 2 : Attach an instance of the field to the page content type.
	\Drupal\field\Entity\FieldConfig::create([
	  'field_name' => $this->field_vocabulary_id,
	  'entity_type' => 'node',
	  'bundle' => $bundle,
	  'label' => 'Taxonomy Terms (Import Files Data Nodes)',
	  'settings' => array(
		'handler_settings' => array(
		  'target_bundles' => array(
			  $this->vocabulary_id => $this->vocabulary_id,
		  ),
		  'auto_create' => TRUE,
		),
	  ),
	])->save();
  }
  public function create_vocabulary_term_form_display($bundle,$weight)
  {
	//Step 3 : Set From Display
    //https://hotexamples.com/examples/-/-/entity_get_form_display/php-entity_get_form_display-function-examples.html
	entity_get_form_display('node', $bundle, 'default')
	  ->setComponent($this->field_vocabulary_id, array(
		  //'type' => 'text_textfield',
		  'type' => 'entity_reference_autocomplete_tags',
	  ))
	  ->save();
  }
  public function create_vocabulary_term_display($bundle,$weight)
  {
	//Step 4 : Set Display
    //https://hotexamples.com/examples/-/-/entity_get_form_display/php-entity_get_form_display-function-examples.html
	entity_get_display('node', $bundle, 'default')
	  ->setComponent($this->field_vocabulary_id, array(
		  //'type' => 'text_textfield',
		  'type' => 'entity_reference_label', //taxonomy_term_reference_link,entity_reference_label
		  'weight' => $weight,
	  ))
	  ->save();
  }

  public function set_params()
  {
    $csv_file_count = $this->set_header_csv();
    $txt_file_count = $this->set_header_txt();
    $this->file_count = $csv_file_count + $csv_file_count;
    if( $this->file_count <= 0 )
      return $this->file_count;
    $this->set_header_default_columns($this->default_column_name); //if no header row, use $this->default_column_name

    foreach($this->file_specs as $k=>$v){
      $this->field_defaults = $this->set_field_defaults();
      //$type_as_file_basename = $this->get_file_basename($k);//filename without extension
      foreach($v["header_row"] as $_k=>$_v){
        $field_machine_name = $this->name_to_machine_name($_v);
        $type_name = $this->filename_to_typename($k);
        $this->file_specs[$k]["field_default_overrides"][$type_name][$field_machine_name]["field"] = array();
        $this->file_specs[$k]["field_default_overrides"][$type_name][$field_machine_name]["instance"] = array();
        $this->file_specs[$k]["field_default_overrides"][$type_name][$field_machine_name]["component"] = array();
      }
    }
    foreach($this->file_specs as $k=>$v){
      $this->file_specs[$k]["field_definitions"] = $this->set_type_field_definitions($this->file_specs[$k]["field_default_overrides"],$this->field_defaults);
    }
    return $this->file_count;
  }

  /**
   * Set file header, absolute path, and number of columns
   */
  public function set_header_csv()
  {
    $file_count = 0;
    foreach (glob($this->get_file_path() . "*.csv") as $filename) {
      if(!in_array(basename($filename),array_keys($this->file_specs))) // file_specs not specified
        continue;
      $r = file($filename);
      $_column_count = 0;
      $is_set = 0;
      foreach($r as $k=>$v){
        if(stripos($v,',')==FALSE) continue;
        $_r = $this->trim_values(explode(',',$v));
        if(sizeof($_r)>$_column_count){ // set column count to max number of columns when reading lines
          $_column_count = sizeof($_r);
        }
        if(sizeof($_r)<1+substr_count( $v , ','))//not header if any columns missing
          continue;
        if( $this->has_a_number($_r) == 1 ) //not header if any columns are numeric
          continue;
        if( in_array("",$_r) ) //not header if any columns are numeric
          continue;

        $this->file_specs[basename($filename)]["header_row"] = $_r;
        $is_set = 1;
        break;
      }
      $this->file_specs[basename($filename)]["path"] = $filename;
      $this->file_specs[basename($filename)]["column_count"] = $_column_count;
      $file_count++;
    }
    return $file_count;
  }

  /**
   * Set file header and absolute path
   */
  public function set_header_txt()
  {
    //txt
    foreach (glob($this->get_file_path() . "*.txt") as $filename) {
      if(!in_array(basename($filename),array_keys($this->file_specs))) // file_specs not specified
        continue;
      $file_count = 0;
      $r = file($filename);
      $_column_count = 0;
      $is_set = 0;
      foreach($r as $k=>$v){
        $_r = $this->fixed_width_explode($v, $this->file_specs[basename($filename)]["positions"]["header"]);
        if(sizeof($_r)>$_column_count){ // set column count to max number of columns when reading lines
          $_column_count = sizeof($_r);
        }
        if( in_array("",$_r) )
          continue;
        //if all keys are set to a value, set and return header
        if( sizeof($_r)<sizeof(preg_split( "/(\s){2,}/i", $v)) )//not header if any columns missing
          continue;
        if( $this->has_a_number($_r) == 1 ) //not header if any columns are numeric
          continue;
        if( in_array("",$_r) ) //not header if any columns are numeric
          continue;
        /**
         * loop through all $_r keys, if any does not begin with a-zA-Z, continue,
         */
        $is_header = 1;
        foreach($_r as $_k=>$_v){
          if( preg_match('/^[a-zA-Z]/i', $_v) === 1 ){
            continue;
          }
          $is_header = 0;
        }
        if( $is_header == 0 )
          continue;

        $this->file_specs[basename($filename)]["header_row"] = $_r;
        break;
      }
      $this->file_specs[basename($filename)]["path"] = $filename;
      $this->file_specs[basename($filename)]["column_count"] = $_column_count;
      $file_count++;
    }
    return $file_count;
  }
  /**
   * Set header default columns if header_row is not set
   */
  public function set_header_default_columns($col_basename = "column")
  {
    foreach($this->file_specs as $k=>$v){
      if( isset($this->file_specs[$k]["header_row"]) && sizeof($this->file_specs[$k]["header_row"])>0 )
        continue;
      $this->file_specs[$k]["header_row"] = array();
      for($i=0;$i<$this->file_specs[$k]["column_count"];$i++){
        $this->file_specs[$k]["header_row"][] = $col_basename . $i;
      }
      if( isset($this->file_specs[$k]["type_name_column"]) ) //enforce filename as type column: no header for setting column name
        unset($this->file_specs[$k]["type_name_column"]);
    }
  }

  /**
   * Sets default field attributes if field name does not include field attributes
   */
  public function set_field_defaults()
  {
    $defaults = array(
    'field' =>
      array(
        'entity_type' => 'node',
        'type' => 'text',
        'cardinality' => 1, //-1 unlimited
        'max_length' => 64,
      ),
    'instance' =>
      array(
        'entity_type' => 'node',
        //'bundle' => 'mybundle',//dynamically created
        //'label' => 'My default title',//dynamically created
        'description' => 'My default description',
        'required' => FALSE, //put in field???????????????
      ),
    'component' =>
      array(
        'form_display_type' => 'text_textfield',
        'display' => 'text_default',
      ),
    );
    return $defaults;
  }
  /**
   * Custom field attributes: default field required in set_field_defaults
   */
  public function set_type_fields()
  {
    $ts = time();
    //specify minimum field name with type name
    $r = array();
    $r = array(
      'SalesJan2009.csv' => array( //type name from filename, or overriden type name
        'name' => //field name
          array(
            'field' =>
              array(),
            'instance' =>
              array(
                'label' => $ts . ', My Title for Name Field',
                'description' => $ts . ', My Description for Name Field',
              ),
            'component' =>
              array(),
          ),
        'description' =>
          array(),
      ),
    );
    $custom_fields = $r;
    return $custom_fields;
  }

  /**
   * Returns array of field definitions from defaults and custom field
   */
  public function set_type_field_definitions($custom_fields,$field_defaults)
  {
    $_r = array();
    foreach($custom_fields as $file_basename=>$fields){

      //check if type name is overriden, if isn't set from filename,
      //function filename_to_typename overrides with type_name_override if is set
      $type_name = $this->filename_to_typename($file_basename);

      foreach($fields as $field_name => $field_instance){ //field_instance = field & instance keys
        foreach($field_defaults['field'] as $k=>$v){
          if( isset($field_instance['field'][$k]) && trim($field_instance['field'][$k])!=''  ){ //int,textfield,textarea?
            $_r[$type_name][$field_name]['field'][$k] = $field_instance['field'][$k];
          }else{
            $_r[$type_name][$field_name]['field'][$k] = $field_defaults['field'][$k];
          }
        }
        foreach($field_defaults['instance'] as $k=>$v){
          if( isset($field_instance['instance'][$k]) && trim($field_instance['instance'][$k])!='' ){ //int,textfield,textarea?
            $_r[$type_name][$field_name]['instance'][$k] = $field_instance['instance'][$k];
          }else{
            $_r[$type_name][$field_name]['instance'][$k] = $field_defaults['instance'][$k];
          }
        }
        foreach($field_defaults['component'] as $k=>$v){
          if( isset($field_component['component'][$k]) && trim($field_component['component'][$k])!='' ){ //int,textfield,textarea?
            $_r[$type_name][$field_name]['component'][$k] = $field_component['component'][$k];
          }else{
            $_r[$type_name][$field_name]['component'][$k] = $field_defaults['component'][$k];
          }
        }
      }
    }
    $field_definitions = $_r;
    return $field_definitions;
  }

  /**
   * Creates new type and type fields from default and custom specifications
   */
  public function create_types_fields($field_definitions,$column_name)
  {
    //create type as term if doesnt already exist
    $this->create_vocabulary();

    $weight = 200;
    $types_created = 0;
    $types = array();
    foreach($field_definitions as $file_name=>$fields){
      if(isset($column_name) && trim($column_name)!="")
        $type_name = trim($column_name, ' .,');
      else
        $type_name = $this->filename_to_typename($file_name);

      if( isset($this->file_specs[$file_name]["type_name_override"]) && trim($this->file_specs[$file_name]["type_name_override"])!="")
        $type_name = $this->file_specs[$file_name]["type_name_override"];

      if( $this->create_content_type($type_name,$column_name) == 1 ) { // deletes and creates
        $types[$type_name] = $type_name;
        $types_created++;
      }

      foreach($fields as $field_name=>$field_instance){ //field_instance as field and instance keys, loop for accessing
        $field = array();
        //create field if not already created
        //$field_machine_name = 'field_' . $type_name . '_' . $field_name;
        $field_machine_name = 'field_' . $field_name;
        $field["field_name"] = $field_machine_name;
        //$field["title"] = strtoupper($type_name . '_' . $field_name);
        $field["title"] = $field_name;
        $field["type"] = $field_instance["field"]["type"];
        //$field["weight"] = $weight;
        $dynamic_keys = array_keys($field_instance["field"]);
        foreach($dynamic_keys as $k=>$v){
          if(!strcmp("field_name",$v)||!strcmp("type",$v))
            continue;
          $field[$v] = $field_instance["field"][$v];
        }
        //https://www.drupal8.ovh/en/tutoriels/283/create-a-field-a-node-entity-programmatically-on-drupal-8
        $_field = \Drupal::entityManager()->getStorage('field_storage_config')->load($field_instance["field"]["entity_type"] . '.' . $field_machine_name);
        if(!isset($_field)){
          \Drupal\field\Entity\FieldStorageConfig::create($field)->save();
        }
        //create field instance in type
        $field = array();
        $field["field_name"] = $field_machine_name;
        $field["entity_type"] = $field_instance["instance"]["entity_type"];
        $field["bundle"] = $type_name;
        //$field["label"] = strtoupper($type_name . '_' . $field_name);
        $field["label"] = str_replace("_"," ",strtoupper($field_name));
        $dynamic_keys = array_keys($field_instance["instance"]);
        foreach($dynamic_keys as $k=>$v){
          if(!strcmp("field_name",$v)||!strcmp("type",$v)||!strcmp("bundle",$v))
            continue;
          $field[$v] = $field_instance["instance"][$v];
        }
        //https://api.drupal.org/api/drupal/core!modules!field!src!Entity!FieldConfig.php/function/FieldConfig%3A%3AloadByName/8.2.x
        $_field = \Drupal::entityManager()->getStorage('field_config')->load(
          $field_instance["instance"]["entity_type"] . '.' .
          $field["bundle"] . '.' .
          $field_machine_name
        );
        if(!isset($_field)){
          \Drupal\field\Entity\FieldConfig::create($field)->save();
        }

        //create field form display and display
        entity_get_form_display($field_instance["instance"]["entity_type"], $type_name, 'default')
          ->setComponent($field_machine_name, array(
          'type' => $field_instance["component"]["form_display_type"],
          'weight' => $weight,
          ))->save();
        entity_get_display($field_instance["instance"]["entity_type"], $type_name, 'default')
          ->setComponent($field_machine_name, array(
          'type' => $field_instance["component"]["display"],
          'weight' => $weight,
          ))->save();
        $weight++;
      }
      //create type field, instance, form_display, and form as term on type if doesnt already exist
      $this->create_vocabulary_term_field();
      $this->create_vocabulary_term_instance($type_name);
      $this->create_vocabulary_term_form_display($type_name,$weight);
      $this->create_vocabulary_term_display($type_name,$weight);
      $weight++;
    }
    return array(
      "types" => $types,
      "types_created" => $types_created,
    );
  }
  /**
   * Creates new content type
   */
  public function create_content_type($type,$column_name)
  {
    $content_type = \Drupal::entityManager()->getStorage('node_type')->load($type);

    if( isset($content_type) && isset($column_name) && trim($column_name)!="" ){
      return 0;
    }

    if( isset($content_type) && (!isset($column_name) || trim($column_name)=="") ){
      $content_type->delete();
    }
    //core\lib\Drupal\Core\Entity
    $content_type = array(
      'type' => t($type),//machine name
      'name' => t(strtoupper($type)),
      'description' => t('Create a new ' . $type),
      'title_label' => t(strtoupper($type) . ' title'),
      'base' => 'node_content',
      'custom' => TRUE
    );
    //core\modules\node\tests\src\Kernel\Migrate
    NodeType::create($content_type)->save();
    return 1;
  }

  /**
   * Delete all nodes in definition types w/out deleting other nodes
   */
  public function delete_all_nodes()
  {
    $types = $this->get_definition_types();
    if(sizeof($types)<=0)
      return array();
    $query = 'SELECT nid ';
    $query .= 'FROM {node} ';
    $query .= 'WHERE type IN (:types[]) ';
    $result = db_query($query,
      array(
        ":types[]" => $types,
      )
    );
    $nids   = array();
    foreach ($result as $row) {
      $nids[] = $row->nid;
    }
    //deletes menu links with nodes
    entity_delete_multiple('node', $nids);
  }

  public function create_nodes()
  {
    global $user;

    $this->delete_all_nodes(); //update to delete all via content type
    foreach($this->file_specs as $k=>$v) {
      $file_handle = fopen($v["path"], "r");
      while (isset($file_handle) && is_resource($file_handle) && !feof($file_handle)) {
          $line = fgets($file_handle);

          if(stripos($line,',')==FALSE) continue;

          if( $this->is_fixed_width($k) == 1 )
            $line_r = $this->fixed_width_explode($line, $this->file_specs[basename($k)]["positions"]["data"]);
          else
            $line_r = $this->trim_values(explode(',',$line));

          //min_fields data set, req min_fields+ fields
          if( isset($this->file_specs[$k]["min_fields"]["data"]) && trim($this->file_specs[$k]["min_fields"]["data"])!="" ){

          if(sizeof($line_r)<$this->file_specs[$k]["min_fields"]["data"])
              continue;
          }else{ // min_fields data not set, req all fields
            if(sizeof($line_r)<1+substr_count( $line , ','))//not header if any columns missing
              continue;
          }

          if(stripos($v["path"],".txt")){
            $line_r = $this->fixed_width_explode($line,$v["positions"]["data"]);
          }else{ //csv
            $line_r = $this->trim_values(explode(',',$line));
          }

          if(
            sizeof($line_r)>=sizeof($this->file_specs[$k]["header_row"]) ||
            ( isset( $this->file_specs[$k]["min_fields"] ) && sizeof($line_r)>=sizeof($this->file_specs[$k]["min_fields"]) )
          ){ // header_row and min_fields for including or skipping line
            $is_header_line = 0;
            if( isset($this->file_specs[$k]["type_name_column"]) && trim($this->file_specs[$k]["type_name_column"])!=""){
              $r = array();
              foreach( $this->file_specs[$k]["header_row"] as $_k=>$_v){
                if( trim($_v) == trim($line_r[$_k]) ) { // skip if is header row if any column data values equal any header value
                  $is_header_line = 1;
                  break;
                }
                $r[strtolower($_v)] = strtolower($line_r[$_k]);
              }
              if($is_header_line==0){
                if( isset($this->file_specs[$k]["type_name_override"]) && trim($this->file_specs[$k]["type_name_override"])!=""){
                  $type_name = $this->file_specs[$k]["type_name_override"];
                } else {
                  if( isset($r[strtolower($this->file_specs[$k]["type_name_column"])]) && trim($r[strtolower($this->file_specs[$k]["type_name_column"])])!="") {
                    $type_name = $r[strtolower(trim($this->file_specs[$k]["type_name_column"]," ."))];
                  }else{
                    $type_name = $this->filename_to_typename($k);
                  }
                }
                $_create_types_fields = $this->create_types_fields($this->file_specs[$k]["field_definitions"],$type_name) ;
                foreach( $_create_types_fields["types"] as $_k=>$_v) {
                  $this->file_specs[$k]["import_stats"]["types"][$_k] = $_v;
                }
                $this->file_specs[$k]["import_stats"]["types_created"] += $_create_types_fields["types_created"];
              }
            }else{
              if( isset($this->file_specs[$k]["type_name_override"]) && trim($this->file_specs[$k]["type_name_override"])!="")
                $type_name = $this->file_specs[$k]["type_name_override"];
              else
                $type_name = $this->filename_to_typename($k);
            }
            if($is_header_line == 1)
              continue;

            $node = Node::create(['type' => $type_name]);
            $node->set('title', 'Data Node: ' . $type_name);
            //$node->set('langcode', LANGUAGE_NOT_SPECIFIED);
            $node->set('langcode', 'und');
            //$node->set('body', "");
            $node->set('uid', $user->uid);
            $node->set('status', 1); //NODE_PUBLISHED==1
            $node->set('promote', 0); //NODE_PROMOTED==1
            //$node->set('comment', 0);
            $is_header_line = 0;
            //iterate fields: header_row = $_v, file line data = value
            foreach( $this->file_specs[$k]["header_row"] as $_k=>$_v){
              if( trim($_v) == trim($line_r[$_k]) ) { // skip if is header row
                $is_header_line = 1;
                break;
              }
              $node->set("field_" . $this->name_to_machine_name($_v), $line_r[$_k]);
            }
            if($is_header_line == 1)
              continue;

            //create term if not already exists
            $tids = array();
            $terms = array($type_name=>$type_name);
            $_terms_r = explode(",",$this->file_specs[$k]["taxonomy_terms"]);
            foreach($_terms_r as $_term) {
              $terms[trim($_term)] = trim($_term);
            }
            foreach($terms as $term) {
              $tids[] = $this->create_vocabulary_term($term);
            }
            //add terms to node before saving
            $node->set($this->field_vocabulary_id, $tids);

            $node->enforceIsNew();
            $node->save();
            $this->file_specs[$k]["import_stats"]["data_nodes_created"]++;
        }
      }
      fclose($file_handle);
    }
  }

  /**
   * Helper functions
   */
  public function set_directory_path($directory = "files-test")
  {
    $this->file_path = DRUPAL_ROOT . "/" . drupal_get_path("module", "import_files") . "/".$directory."/"; //csv or txt(fixed width) files
  }
  public function set_default_column_name($default_column_name = "column")
  {
    $this->default_column_name = $default_column_name;
  }
  public function get_file_path()
  {
    return $this->file_path;
  }
  public function trim_values($r)
  {
    foreach($r as $_k=>$_v){
      $r[$_k] = trim($_v," \n\r");
    }
    return $r;
  }
  public function name_to_machine_name($file_field_name){
    return strtolower(preg_replace("/\W+/", '', str_replace(" ","_",$file_field_name)));
  }

  public function has_a_number($r)
  {
    $is_numeric = 0;
    foreach($r as $_v){
  	  if(is_numeric(trim($_v))){
        return 1;
      }
    }
    return 0;
  }

  public function get_file_basename($filename)
  {
    $_r = explode(".",basename($filename));
    return $_r[0];//filename without extension;
  }
  public function filename_to_typename($file_name){
    if( isset($this->file_specs[$file_name]["type_name_override"]) && trim($this->file_specs[$file_name]["type_name_override"])!=""){
      $type_name = $this->file_specs[$file_name]["type_name_override"];
    }else if( isset($this->file_specs[$file_name]["type_name_column"]) && trim($this->file_specs[$file_name]["type_name_column"])!=""){
      $type_name = trim($this->file_specs[$file_name]["type_name_column"]," .");
    }else{
      $type_name = ($this->get_file_basename($file_name));
    }
    return $type_name;
  }

  public function get_definition_types()
  {
    $types = array();
    foreach($this->file_specs as $k=>$v){
      foreach($v["field_definitions"] as $file_name=>$definition){
        $types[] = $this->filename_to_typename($file_name);
      }
    }
    return $types;
  }
  //explode by psitions for fixed width
  public function fixed_width_explode($fline,$positions){
    $r = array();
    $positions_r = explode(',',$positions);
    foreach($positions_r as $k=>$v){
      if(isset($positions_r[$k+1])) {
        $length =  $positions_r[$k+1] - $positions_r[$k] ;
        $r[] = trim( substr($fline, $positions_r[$k]-1, $length)," \n\r" );
      } else { //last entry
        $r[] = trim( substr($fline, $positions_r[$k]-1)," \n\r" );
      }
    }
    return $r;
  }
  public function is_fixed_width($file_specs_key){
    if( isset($this->file_specs[$file_specs_key]["positions"]) && trim($this->file_specs[$file_specs_key]["positions"]["data"]) != "" )
      return 1;
    return 0;
  }
  public function get_import_report(){
    $str = "";
    foreach($this->file_specs as $k=>$v){
      $str .= $k . " :\n\r";
      $str .= "&nbsp;&nbsp;" . $v["import_stats"]["types_created"] . " types created\n\r";
      foreach($v["import_stats"]["types"] as $_k=>$_v) {
        $str .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $_k . "\n\r";
      }
      $str .= "&nbsp;&nbsp;" . $v["import_stats"]["data_nodes_created"] . " data nodes created\n\r";
    }
    return $str;
  }
}
