<?php
/*
Plugin Name: PHP Grid Framework
Plugin URI: http://www.phpgrid.org/
Description: PHP Grid Framework to rapidly build CRUD or Report using table name or SQL query - By www.phpgrid.org
Author: Abu Ghufran
Version: 0.5.6
Author URI: http://www.phpgrid.org/
*/

//Important to place the including class available to usage inside theme and other plugins!
include_once( WP_PLUGIN_DIR . "/phpgrid/lib/inc/jqgrid_dist.php");

//Create an object instance of the class
$phpgrid_plugin = new PHPGrid_Plugin();

/**
 * The class puts the dependent scripts in the page loading and creates a hook for header.
 */
class PHPGrid_Plugin
{
	private $phpgrid_output;

	private $add = false;
	private $inlineadd = false;
	private $delete = false;
	private $edit = false;
	private $export = false;
	private $hidden = array();
	private $caption = '';
	private $lang = '';

	/**
	* Activates actions
	*/
	function __construct()
	{
		// load core lib at template_redirect because we need the post data!
		add_action( "template_redirect", array( &$this, 'phpgrid_header' ) );

		// load js and css files
		add_action( "wp_enqueue_scripts", array( &$this, 'wp_enqueue_scripts' ) );

		// added short code for display position
		add_shortcode( "phpgrid", array( &$this, 'shortcode_phpgrid' ) );

		// add an action for the output
		add_action('phpgrid_output', array($this, 'phpgrid_output' ) );

		// ajax
		add_action('wp_ajax_phpgrid_data', array($this, 'phpgrid_header' ) );
		add_action('wp_ajax_nopriv_phpgrid_data', array($this, 'phpgrid_header' ) );
	}

	/**
	* This is the custom action, placed in header at your theme before any html-output!
	* To be continued: hooks and filters to perform different grids on different tables and datasources.
	*/
	function phpgrid_header()
	{
		global $post;

		$ajax = false;
		$external_connection = false;

		if (isset($_REQUEST['action']) && esc_attr( $_REQUEST['action'] ) == 'phpgrid_data' ){
			$ajax = true;
		}

		$regex_pattern = get_shortcode_regex();
		preg_match_all ('/'.$regex_pattern.'/s', $post->post_content, $regex_matches);
		foreach($regex_matches[2] as $k=>$code)
		{
			if ($code == 'phpgrid') 
			{
				// set database table for CRUD operations, override with filter 'phpgrid_table'.

				$grid = array();
				$grid_columns = array();
				
				$table = '';
				$select_command = '';
				$column_names = array();
				$column_titles = array();
				$list_id = '';
				$sortname = '';
				$sortorder = '';
				$this->add = false;
				$this->inlineadd = false;
				$this->edit = false;
				$this->delete = false;
				$this->hidden = false;
				$this->caption = '';
				$this->export = false;
				$this->lang = 'en';
				
				$db_conf = apply_filters( 'phpgrid_connection', '' );

				if ( is_array( $db_conf ) )
				{
					$external_connection = true;
				}
				else
				{
					// if not set, connect to default wp database
					$db_conf = array( 	
									"type" 		=> 'mysqli', 
									"server" 	=> DB_HOST,
									"user" 		=> DB_USER,
									"password" 	=> DB_PASSWORD,
									"database" 	=> DB_NAME
								);
				}

				$g = new jqgrid( $db_conf );

				$attr_str = $regex_matches[3][$k];

				$re = "/([a-zA-Z_]+)=[\"]([^\"]+)[\"]/";
				preg_match_all($re, $attr_str, $matches);				

				$attributes = array();
				for($p=0;$p<count($matches[0]);$p++)
				{
					$attributes[$matches[1][$p]] = $matches[2][$p];
				}
				
				if (isset($attributes['table'])){
					$table = $attributes['table'];
				}
					
				if (isset($attributes['select_command'])){
					$select_command = $attributes['select_command'];
				}

				if (isset($attributes['columns'])){
					$column_names = $attributes['columns'];
				}

				if (isset($attributes['titles'])){
					$column_titles = $attributes['titles'];
				}

				if (isset($attributes['hidden'])){
					$this->hidden = $attributes['hidden'];
				}

				if (isset($attributes['add'])){
					$this->add = $attributes['add'];
				}

				if (isset($attributes['inlineadd'])){
					$this->inlineadd = $attributes['inlineadd'];
				}

				if (isset($attributes['delete'])){
					$this->delete = $attributes['delete'];
				}

				if (isset($attributes['edit'])){
					$this->edit = $attributes['edit'];
				}

				if (isset($attributes['caption'])){
					$this->caption = $attributes['caption'];
				}

				if (isset($attributes['export'])){
					$this->export = $attributes['export'];
				}

				if (isset($attributes['language'])){
					$this->lang = $attributes['language'];
				}

				if (isset($attributes['id'])){
					$list_id = $attributes['id'];
				}

				if (isset($attributes['sortname'])){
					$sortname = $attributes['sortname'];
				}

				if (isset($attributes['sortorder'])){
					$sortorder = $attributes['sortorder'];
				}

				if ( !empty($column_names) && !is_array( $column_names ) ) {

					$cols = array();
					$colnames_arr = explode( ",", $column_names );
					$coltitles = explode( ",", $column_titles );
					$this->hidden = explode( ",", $this->hidden );

					foreach( $colnames_arr as $key => $column ){

						$col = array();
						$col['name'] = $column;
						$col['editable'] = true;

						if ( $coltitles[$key] ) $col['title'] = $coltitles[$key]; // caption of column

						//if ( in_array( $column, $this->hidden ) ) $col['hidden'] = true;

						$cols[] = $col;

					}

					$grid_columns = $cols;
				}

				// set actions to the grid
				$actions = array(
					"add"				=> ($this->add === 'true'),
					"edit"				=> ($this->edit === 'true'),
					"delete"			=> ($this->delete === 'true'),
					"rowactions"		=> false,
					"export"			=> ($this->export === 'true'),
					"autofilter"		=> true,
					"search"			=> "simple",
					"inlineadd"			=> ($this->inlineadd === 'true'),
					"showhidecolumns"	=> false
				);

				// open actions for filters
				$actions = apply_filters( 'phpgrid_actions', $actions );
				$g->set_actions( $actions );

				if ( $ajax && isset( $_REQUEST['phpgrid_select_command'] ) ) $select_command = esc_attr( $_REQUEST['phpgrid_select_command'] );

				$select_command = apply_filters( 'phpgrid_select_command', $select_command );

				if ( $ajax && isset( $_REQUEST['phpgrid_table'] ) ) $table = esc_attr( $_REQUEST['phpgrid_table'] );

				$table = apply_filters( 'phpgrid_table', $table );

				if ( !empty( $table ) ) 
				{
					$g->table = $table;
				}
				
				if ( !empty( $select_command ) ) 
				{
					$g->select_command = $select_command;
				}
				
				// if not set, do not show grid
				if (empty($table) && empty($select_command))
				{
					return;
				}

				if (!empty($grid_columns))
					$g->set_columns( apply_filters( 'phpgrid_columns', $grid_columns ) );

				$caption = (!empty($table) ? $table : "");
				if ( empty($this->caption) ) $this->caption = ucwords(preg_replace("/[_-]/"," ",$caption));
				
				// if no table set - set default caption
				if ( empty($this->caption) ) $this->caption = "PHP Grid Framework | www.phpgrid.org";
				
				// set some standard options to grid. Override this with filter 'phpgrid_options'.
				$grid["caption"] = $this->caption;
				$grid["multiselect"] = false;
				$grid["autowidth"] = true;
				$grid["add_options"]["width"] = "500";
				$grid["edit_options"]["width"] = "500";

				if (!empty($sortname))
					$grid["sortname"] = $sortname;

				if (!empty($sortorder))
					$grid["sortorder"] = $sortorder;

				// fetch if filter is used otherwise use standard options
				$grid = apply_filters( 'phpgrid_options', $grid );

				// set the options
				$g->set_options( $grid );

				if ( !empty( $this->lang ) ){
				add_filter( 'phpgrid_lang', array($this, 'lang') );
				}

				// render grid, possible to override the name with filter 'phpgrid_name'.
				$list_id = apply_filters( 'phpgrid_name', $list_id );
				$this->phpgrid_output["$list_id"] = $g->render( $list_id );

			}
		}
		
		if ( $ajax )
		{
			die(0);
		}

	}

	function lang(){
		return $this->lang;
	}

	/**
	* Register styles and scripts. The scripts are placed in the footer for compability issues.
	*/
	function wp_enqueue_scripts()
	{
		wp_enqueue_script( 'jquery' );
		//wp_enqueue_script( 'jquery-ui-core' );

		$theme = apply_filters( 'phpgrid_theme', 'redmond' );
		$theme_script = apply_filters( 'phpgrid_theme_script', WP_PLUGIN_URL . '/phpgrid/lib/js/themes/' . $theme . '/jquery-ui.custom.css' );
		wp_register_style( 'phpgrid_theme', $theme_script );
		wp_enqueue_style( 'phpgrid_theme' );

		wp_register_style( 'jqgrid_css', WP_PLUGIN_URL . '/phpgrid/lib/js/jqgrid/css/ui.jqgrid.css' );
		wp_enqueue_style( 'jqgrid_css' );

		$lang = apply_filters( 'phpgrid_lang', 'en' );
		$localization = apply_filters( 'phpgrid_lang_script', WP_PLUGIN_URL . '/phpgrid/lib/js/jqgrid/js/i18n/grid.locale-' . $lang . '.js' );
		wp_register_script( 'jqgrid_localization', $localization, array('jquery'), false, true);
		wp_enqueue_script( 'jqgrid_localization' );

		wp_register_script( 'jqgrid', WP_PLUGIN_URL . '/phpgrid/lib/js/jqgrid/js/jquery.jqGrid.min.js', array('jquery'), false, true);
		wp_enqueue_script( 'jqgrid' );

		wp_register_script( 'jqquery-ui-theme', WP_PLUGIN_URL . '/phpgrid/lib/js/themes/jquery-ui.custom.min.js', array('jquery'), false, true);
		wp_enqueue_script( 'jqquery-ui-theme' );

	}

	/*
	* Output the shortcode
	*/
	function shortcode_phpgrid( $attr )
	{
		// theme fixes for wp
		$style = "<style>.ui-jqdialog-content .DataTD { white-space: nowrap; } .ui-jqdialog-content .CaptionTD { width: 35%; padding-top: 0px; } .ui-widget td, .ui-widget table {border-width:0px} .ui-jqgrid td, .ui-jqgrid th, .ui-jqgrid table { border-width:0px; padding:0px; } .ui-jqgrid input, .ui-widget input { width: inherit; height: 20px !important; padding: 0 4px !important; }</style>";
		return $style.$this->phpgrid_output[$attr["id"]];
	}

	/*
	* Output the shortcode
	*/
	function phpgrid_output($id)
	{
		echo $this->phpgrid_output[$id];
	}
}