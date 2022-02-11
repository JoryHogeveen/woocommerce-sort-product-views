<?php
/**
 * Plugin Name:       WooCommerce Sort By Product Views
 * Plugin URI:        https://www.keraweb.nl
 * Description:       Sort products by number of views through the Post Views Counter plugin
 * Author:            Jory Hogeveen
 * Author URI:        https://www.keraweb.nl
 * Version:           1.1
 * Text Domain:       woocommerce-sort-product-views
 * GitHub Plugin URI: JoryHogeveen/woocommerce-sort-product-views
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * @return Ker_WC_SortProductViews
 */
function ker_wc_sortproductviews() {
	return Ker_WC_SortProductViews::get_instance();
}
ker_wc_sortproductviews();

class Ker_WC_SortProductViews
{
	/**
	 * @var self
	 */
	private static $_instance = null;

	/**
	 * The option name key.
	 * @var string
	 */
	public $sort_key = 'views';

	/**
	 * The period to filter/order by.
	 * See post_views table.
	 * @var string
	 */
	public $period = 'total';

	/**
	 * The order.
	 * @var string
	 */
	public $order = 'DESC';

	/**
	 * Ker_WC_SortProductViews constructor.
	 */
	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
	}

	/**
	 * Plugins loaded.
	 */
	public function action_plugins_loaded() {
		// Make sure the plugin Post Views Counter is installed & active.
		if ( ! class_exists( 'Post_Views_Counter' ) ) {
			return;
		}

		load_plugin_textdomain( 'woocommerce-sort-product-views', false, basename( dirname( __FILE__ ) ) . '/languages' );

		add_filter( 'woocommerce_catalog_orderby', array( $this, 'filter_woocommerce_catalog_orderby' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'filter_woocommerce_get_catalog_ordering_args' ), 10, 3 );
	}

	/**
	 * Add our custom sorting option.
	 * @param  array $options
	 * @return array
	 */
	public function filter_woocommerce_catalog_orderby( $options ) {
		$options[ $this->sort_key ] = __( 'Sorteren op meest bekeken', 'woocommerce-sort-product-views' );
		return $options;
	}

	/**
	 * WooCommerce order (query) parameters.
	 * @param  array  $args
	 * @param  string $orderby
	 * @param  string $order
	 * @return array
	 */
	public function filter_woocommerce_get_catalog_ordering_args( $args, $orderby, $order ) {
		if ( $this->sort_key === $orderby ) {
			$this->order = ( 'ASC' === $order ) ? 'ASC' : 'DESC';
			$args['orderby'] = ''; // Will be overwritten.
			$args['order']   = $this->order;
			add_filter( 'posts_clauses', array( $this, 'order_by_product_views' ) );
		}
		return $args;
	}

	/**
	 * Post query SQL parameters.
	 * @param  array $query_args
	 * @return array
	 */
	public function order_by_product_views( $query_args ) {
		global $wpdb;
		$posts = $wpdb->posts;
		$views = $wpdb->get_blog_prefix() . 'post_views';

		// JOIN
		$query_args['join'] .= " LEFT JOIN {$views} pv ON {$posts}.ID = pv.id AND pv.period = '{$this->period}'";
		$query_args['join'] .= " LEFT JOIN {$views} ppv ON {$posts}.post_parent = ppv.id AND ppv.period = '{$this->period}'";

		// ORDER BY
		$month_ago = date_i18n( 'Y-m-d', strtotime( '-1 month' ) );
		$cases = array(
			"CASE WHEN {$posts}.post_date >= '{$month_ago}' THEN {$posts}.post_date END {$this->order}",
			"CASE WHEN ppv.count IS NOT NULL AND {$posts}.post_date >= '{$month_ago}' THEN ppv.count END {$this->order}",
			"CASE WHEN ppv.count IS NOT NULL AND {$posts}.post_date < '{$month_ago}' THEN ppv.count END {$this->order}",
			"CASE WHEN {$posts}.post_date >= '{$month_ago}' THEN pv.count END {$this->order}",
			"CASE WHEN {$posts}.post_date < '{$month_ago}' THEN pv.count END {$this->order}",
			"CASE WHEN {$posts}.post_date < '{$month_ago}' THEN {$posts}.post_date END {$this->order}",
		);
		$query_args['orderby'] = implode( ', ' , $cases );

		return $query_args;
	}

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}
