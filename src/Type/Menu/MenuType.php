<?php

namespace WPGraphQL\Type\Menu;

use GraphQLRelay\Relay;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class MenuType
 *
 * @package WPGraphQL\Type
 */
class MenuType extends WPObjectType {

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * MenuType constructor.
	 */
	public function __construct() {

		self::$type_name = 'menu';

		$config = [
			'name'        => self::$type_name,
			'description' => __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields that make up the MenuLocationType
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {
				$fields = [
					'id'                    => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'ID of the nav menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $menu ) {
							return ! empty( $menu->term_id ) ? Relay::toGlobalId( self::$type_name, $menu->term_id ) : null;
						},
					],
					self::$type_name . 'Id'              => array(
						'type'        =>  Types::non_null( Types::id() ),
						'description' => esc_html__( 'ID of the menu. Equivalent to WP_Term->term_id.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $menu ) {
							return ! empty( $menu->term_id ) ? $menu->term_id : null;
						},
					),
					'name'            => array(
						'type'        => Types::string(),
						'description' => esc_html__( 'Display name of the menu. Equivalent to WP_Term->name.', 'wp-graphql' ),
					),
					'slug'            => array(
						'type'        => Types::string(),
						'description' => esc_html__( 'The url friendly name of the menu. Equivalent to WP_Term->slug', 'wp-graphql' ),
					),
					'count' => [
						'type' => Types::int(),
						'description' => __( 'The number of items in the menu', 'wp-graphql' ),
					],
					'group'           => array(
						'type'        => Types::string(),
						'description' => esc_html__( 'Group of the menu. Groups are useful as secondary indexes for SQL. Equivalent to WP_Term->term_group.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $menu ) {
							return ! empty( $menu->term_group ) ? $menu->term_group : null;
						},
					),
					'items'           => array(
						'type'        => Types::list_of( Types::menu_item() ),
						'description' => esc_html__( 'The nav menu items assigned to the menu.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $menu ) {

							$menu_args = [
								'post_type' => 'nav_menu_item',
								'posts_per_page' => 100,
								'meta_key' => '_menu_item_menu_item_parent',
								'meta_value' => 0,
								'tax_query' => [
									[
										'taxonomy' => 'nav_menu',
										'field' => 'slug',
										'terms' => [ $menu->slug ],
									],
								],
								'order' => 'ASC',
								'orderby' => 'menu_order',
							];

							$menu_items = new \WP_Query( $menu_args );
							$menu_items_output = [];

							if ( ! empty( $menu_items->posts ) && is_array( $menu_items->posts ) ) {
								foreach ( $menu_items->posts as $menu_item ) {
									$menu_item->menu = $menu;
									$menu_items_output[] = wp_setup_nav_menu_item( $menu_item );
								}
							}

							return ! empty( $menu_items_output ) ? $menu_items_output : null;
						},
					),
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}