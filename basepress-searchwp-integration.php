<?php
/**
 * Plugin Name: BasePress + SearchWP Integration
 * Plug URI: https://www.codesavory.com
 * Description: Enhance BasePress Knowledge Base Premium with SearchWP
 * Version: 1.1.3
 * Requires PHP: 5.6
 * Author: codeSavory
 * Author URI: https://www.codesavory.com
 * Text Domain: basepress-swp
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! class_exists( 'Basepress_SWP_Integration' ) ){

	class Basepress_SWP_Integration{

		private $is_restricted;

		private $is_kb_search = true;

		private $is_swp_search = false;

		function __construct(){
			add_action( 'wp_loaded', array( $this, 'init' ) );
			add_action( 'wp_ajax_nopriv_basepress_smart_search', array( $this, 'init' ), 5 );
			add_action( 'wp_ajax_basepress_smart_search', array( $this, 'init' ), 5 );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}


		/**
		 * Initiate all filters
		 *
		 * @since 1.0.0
		 */
		public function init(){
			global $basepress_utils;

			if( ! class_exists( 'SearchWP' ) || ! class_exists( 'Basepress' ) ){
				return;
			}

			if( ! $basepress_utils->is_search && ( isset( $_REQUEST['action'] ) && 'basepress_smart_search' != $_REQUEST['action'] ) ){
				return;
			}

			if ( function_exists( 'SWP' ) ) {
				//SearchWP up to 3.x
				$swp_settings = SWP()->settings;
				$swp_engines = isset( $swp_settings['engines'] ) ? $swp_settings['engines'] : '';
				$this->is_swp_search = isset( $swp_engines['knowledge_base'] ) && $swp_engines['knowledge_base']['knowledgebase']['enabled'];
			} else if ( class_exists( 'SearchWP\Settings' ) ) {
				//SearchWP 4.0+
				$swp_engines = \SearchWP\Settings::get_engines();
				if( isset( $swp_engines['knowledge_base'] ) ){
					$kb_engine_post_types = $swp_engines['knowledge_base']->get_sources();
					$this->is_swp_search = array_key_exists( 'post' . SEARCHWP_SEPARATOR . 'knowledgebase', $kb_engine_post_types );
				}
			}

			$basepress_options = $basepress_utils->get_options();
			$this->is_restricted = isset( $basepress_options['activate_restrictions'] )	&&  class_exists( 'Basepress_Content_Restriction' );

			//Filter SearchWP engines according to passed arguments
			add_filter( 'searchwp_swp_query_args', array( $this, 'filter_swp_engines' ), 999 );
			add_filter( 'searchwp\swp_query\args', array( $this, 'filter_swp_engines' ), 999 );

			//Live smart search
			add_filter( 'basepress_custom_search', '__return_true' );
			add_filter( 'basepress_custom_smart_search_results', array( $this, 'smart_search_results'), 10, 4 );

			//Set the correct engine for BasePress searches
			add_filter( 'searchwp_search_args', array( $this, 'set_search_engine' ) );
			add_filter( 'searchwp\swp_query\args', array( $this, 'set_search_engine' ) );

			//Taxonomy query
			add_filter( 'searchwp_query_main_join', array( $this, 'tax_join' ), 10, 2 );
			add_filter( 'searchwp_where', array( $this, 'tax_where' ), 10, 2 );
			add_action( 'searchwp_swp_query_shutdown', array( $this, 'unset_tax_query' ) );
			add_action( 'searchwp\query\mods', array( $this, 'tax_mod' ), 10, 2 );

			//Meta Query
			add_filter( 'searchwp_query_main_join', array( $this, 'meta_join' ), 10, 2 );
			add_filter( 'searchwp_where', array( $this, 'meta_where' ), 10, 2 );
			add_action( 'searchwp_swp_query_shutdown', array( $this, 'unset_meta_query' ) );
			add_action( 'searchwp\query\mods', array( $this, 'meta_mod' ), 10, 2 );

			add_filter( 'pre_get_posts', array( $this, 'remove_tax_query_in_searches' ), 10 );
		}


		/**
		 * WordPress add the KB taxonomy on search queries. SearchWP 4.x adds it to its own query as well.
		 * We need to prevent this as we do add the taxonomies ourselves
		 *
		 * @since 1.1.0
		 *
		 * @param $query
		 * @return mixed
		 */
		public function remove_tax_query_in_searches( $query ){
			global $basepress_utils;
			if( $basepress_utils->is_search ){
				$query->set( 'knowledgebase_cat', '' );
			}
			return $query;
		}


		/**
		 * This function is run when SWP_Query is used and we can check if the knowledge base post type is included in the search
		 *
		 * @since 1.0.0
		 *
		 * @param $args
		 * @return mixed
		 */
		public function filter_swp_engines( $args ){
			if( ! empty( $args['post_type'] ) ){
				$post_types = is_array( $args['post_type'] ) ? $args['post_type'] : (array)$args['post_type'];
				if( ! in_array( 'knowledgebase', $post_types ) ){
					$this->is_kb_search = false;
				}
			}

			return $args;
		}


		/**
		 * Sets the SearchWP search engine for front end searches
		 *
		 * @since 1.0.0
		 *
		 * @param $args
		 * @return mixed
		 */
		public function set_search_engine( $args ){
			global $basepress_utils;
			if( $basepress_utils->is_search ){
				$args['engine'] = 'knowledge_base';
			}
			return $args;
		}


		/**
		 * Creates the tax query object
		 *
		 * @since 1.0.0
		 *
		 * @return WP_Tax_Query
		 */
		public function tax_query(){
			global $basepress_content_restriction;

			$knowledge_base = $this->get_product();
			$knowledge_base_id = $knowledge_base ? $knowledge_base->term_id : false;
			$section_id = isset( $_REQUEST['kb_section'] ) && ! empty( $_REQUEST['kb_section'] ) ? intval( $_REQUEST['kb_section'] ) : false;
			$term_id = is_numeric( $section_id ) ? $section_id : $knowledge_base_id;
			$include_tax_restrictions = ( isset( $_REQUEST['kb_include_terms_restrictions'] ) && intval( $_REQUEST['kb_include_terms_restrictions'] ) ) || ! is_admin() || ( is_admin() && wp_doing_ajax() );

			$tax_args = array();
			if( ! empty( $term_id ) ){
				$tax_args[] = array(
					'taxonomy' => 'knowledgebase_cat',
					'field'    => 'term_id',
					'terms'    => array( $term_id )
				);
			}

			if( $this->is_restricted && $include_tax_restrictions ){
				$restricted_tax_terms = $basepress_content_restriction->restricted_tax_terms( $knowledge_base );
				if( ! empty( $restricted_tax_terms ) ){
					$tax_args[] = array(
						'taxonomy' => 'knowledgebase_cat',
						'field'    => 'term_taxonomy_id',
						'terms'    => $restricted_tax_terms,
						'operator' => 'NOT IN'
					);
				};
			}
			return new WP_Tax_Query( $tax_args );
		}

		/**
		 * Adds taxonomy join clause to SearchWP 4.x query.
		 *
		 * @since 1.1.0
		 *
		 * @param $mods
		 * @return \SearchWP\Mod[]
		 */
		function tax_mod( $mods, $query ) {
			global $wpdb;

			if( ! $this->is_swp_search ){
				return $mods;
			}

			$tax_query = $this->tax_query();
			$post_alias     = 'basepress_post_alias';
			$tax_alias = 'kb_tax';
			$tq_sql    = $tax_query->get_sql( $post_alias, 'ID' );

			if( empty( $tq_sql['join'] ) ){
				return $mods;
			}

			$mod = new \SearchWP\Mod( \SearchWP\Utils::get_post_type_source_name( 'knowledgebase' ) );

			$mod->raw_join_sql( function( $runtime ) use ( $tq_sql, $tax_alias, $post_alias, $wpdb ) {
				//Add an alias for term_relationship table
				$tmp_join = str_replace( 'ON', $tax_alias . ' ON', $tq_sql['join'] );
				$tmp_join = str_replace( $wpdb->term_relationships . '.object_id', $tax_alias . '.object_id', $tmp_join );
				//Add an alias for posts table
				$tmp_join = str_replace( $post_alias, $runtime->get_local_table_alias(), $tmp_join );
				return $tmp_join;
			} );

			$mod->raw_where_sql( function( $runtime ) use ( $tq_sql, $tax_alias, $post_alias, $wpdb ) {
				//Add an aliases for term_relationship table
				$tmp_where = str_replace( $wpdb->term_relationships, $tax_alias, $tq_sql['where'] );
				//Add an alias for posts table
				$tmp_where = str_replace( $post_alias, $runtime->get_local_table_alias(), $tmp_where );
				return '1=1 ' . $tmp_where;
			} );


			$mods[] = $mod;

			return $mods;
		}


		/**
		 * Adds taxonomy join clause to SearchWP query
		 *
		 * @since 1.0.0
		 *
		 * @param $sql
		 * @param $engine
		 * @return string
		 */
		function tax_join( $sql, $engine ){
			global $wpdb;

			if( ! $this->is_swp_search ){
				return $sql;
			}

			$tax_query = $this->tax_query();

			$tq_sql = $tax_query->get_sql(
				$wpdb->posts,
				'ID'
			);

			return $sql . $tq_sql['join'];
		}


		/**
		 * Adds Taxonomy where clause to SearchWP query
		 *
		 * @since 1.0.0
		 *
		 * @param $sql
		 * @param $engine
		 * @return string
		 */
		function tax_where( $sql, $engine ) {
			global $wpdb;

			if( ! $this->is_swp_search ){
				return $sql;
			}

			$tax_query = $this->tax_query();

			$tq_sql = $tax_query->get_sql(
				$wpdb->posts,
				'ID'
			);

			return $sql . $tq_sql['where'];
		}


		/**
		 * Callback to unset the tax_query hooks
		 *
		 * @since 1.0.0
		 */
		function unset_tax_query() {
			remove_filter( 'searchwp_query_main_join', array( $this, 'tax_join' ), 10 );
			remove_filter( 'searchwp_where', array( $this, 'tax_where' ), 10 );
		}

		/**
		 * Adds meta join clause to SearchWP 4.x query.
		 *
		 * @since 1.1.0
		 *
		 * @param $mods
		 * @return \SearchWP\Mod[]
		 */
		function meta_mod( $mods, $query ) {
			$mod = new \SearchWP\Mod();
			$mod->raw_join_sql( $this->meta_join( '', '', $mod ) );
			$mod->raw_where_sql( '1=1' . $this->meta_where( '', '' ) );
			$mods[] = $mod;

			return $mods;
		}

		/**
		 * Adds metadata join clause to SearchWP query
		 *
		 * @since 1.0.0
		 *
		 * @param $sql
		 * @param $engine
		 * @return string
		 */
		public function meta_join( $sql, $engine, $mod = null ){
			global $wpdb;

			if( ! $this->is_swp_search ){
				return $sql;
			}

			if( $this->is_restricted ){

				//If SearchWP 4
				$table_alias = ! empty( $mod ) ? $mod->get_foreign_alias() : $wpdb->posts;

				return $sql . "	LEFT JOIN {$wpdb->postmeta} AS kb_restrictions ON ({$table_alias}.ID = kb_restrictions.post_id AND kb_restrictions.meta_key = 'basepress_restriction_roles')";
			}
			return $sql;
		}


		/**
		 * Adds metadata where clause to SearchWP query
		 *
		 * @since 1.0.0
		 *
		 * @param $sql
		 * @param $engine
		 * @return string
		 */
		function meta_where( $sql, $engine ) {
			global $basepress_content_restriction;

			if( ! $this->is_swp_search ){
				return $sql;
			}

			if( $this->is_restricted ){
				$user_roles = $basepress_content_restriction->get_user_role();
				if( $user_roles ){
					$word_boundary_before = '\\\\b' == $basepress_content_restriction->word_boundary['before'] ? '\\b' : $basepress_content_restriction->word_boundary['before'];
					$word_boundary_after = '\\\\b' == $basepress_content_restriction->word_boundary['after'] ? '\\b' : $basepress_content_restriction->word_boundary['after'];

					$user_role = $word_boundary_before . implode( "$word_boundary_after|$word_boundary_before", $user_roles ) . $word_boundary_after;

					return $sql . " AND (kb_restrictions.post_id IS NULL OR (kb_restrictions.meta_key = 'basepress_restriction_roles' AND kb_restrictions.meta_value REGEXP '{$user_role}'))";
				}
			}
			return $sql;
		}


		/**
		 * Callback to unset the meta_query hooks
		 *
		 * @since 1.0.0
		 */
		function unset_meta_query() {
			remove_filter( 'searchwp_query_main_join', array( $this, 'meta_join' ), 10 );
			remove_filter( 'searchwp_where', array( $this, 'meta_where' ), 10 );
		}


		/**
		 * Gets smart search results using SWP_Query
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 * @param $terms
		 * @param $knowledge_base
		 * @param $limit
		 * @return array
		 */
		public function smart_search_results( $data, $terms, $knowledge_base, $limit ){

			$args = array(
				's'              => $terms,
				'engine'         => 'knowledge_base',
				'fields'         => 'all',
				'posts_per_page' => $limit,
				'page'           => 1
			);

			$query = new SWP_Query( $args );

			return array( 'posts' => $query->posts, 'found_posts' => $query->found_posts );
		}


		/**
		 * Gets the product depending on the loaded page
		 *
		 * @since 1.0.0
		 *
		 * @return array|false|WP_Term
		 */
		public function get_product(){
			global $basepress_utils;

			if( is_admin() && ! wp_doing_ajax() && isset( $_REQUEST['kb_product'] ) ){
				$product = get_term_by( 'id', sanitize_title_for_query( $_REQUEST['kb_product'] ), 'knowledgebase_cat' );
			}
			elseif( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'basepress_smart_search' == $_REQUEST['action'] ){
				$product = get_term_by( 'slug', sanitize_title_for_query( $_REQUEST['product'] ), 'knowledgebase_cat' );
			}
			else{
				$product = $basepress_utils->get_product();
				$product->term_id = $product->id;
			}

			return $product;
		}


		/**
		 * Display Admin notces if the plugins are not active
		 *
		 * @since 1.0.0
		 */
		public function admin_notices(){
			if( ! class_exists( 'SearchWP' ) || ! class_exists( 'Basepress' ) ){
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . __( '<b>BasePress + SearchWP Integration</b> requires both BasePress Premium and SearchWP to be active. Please make sure that they are both installed and activated.', 'basepress-swp' ) . '</p>';
				echo '</div>';
			}
		}

	}
	
	global $basepress_swp_integration;
	$basepress_swp_integration = new Basepress_SWP_Integration();
}