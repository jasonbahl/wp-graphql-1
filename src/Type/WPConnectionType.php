<?php
namespace WPGraphQL\Type;

use Exception;
use GraphQL\Exception\InvalidArgument;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPConnectionType
 *
 * @package WPGraphQL\Type
 */
class WPConnectionType {

	/**
	 * The config for the connection
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The args configured for the connection
	 *
	 * @var array
	 */
	protected $connection_args;

	/**
	 * The fields to show on the connection
	 *
	 * @var array
	 */
	protected $connection_fields;

	/**
	 * @var array|null
	 */
	protected $connection_interfaces;

	protected $connection_name;

	/**
	 * @var array
	 */
	protected $edge_fields;

	protected $from_field_name;

	protected $from_type;

	protected $one_to_one;

	protected $query_class;

	protected $resolve_connection;

	protected $resolve_cursor;

	protected $resolve_node;

	protected $to_type;

	protected $type_registry;

	protected $where_args;

	/**
	 * WPConnectionType constructor.
	 *
	 * @param array        $config
	 * @param TypeRegistry $type_registry
	 */
	public function __construct( array $config = [], TypeRegistry $type_registry ) {

		$this->validate_config( $config );

		$this->config                = $config;
		$this->type_registry         = $type_registry;
		$this->from_type             = $config['fromType'];
		$this->to_type               = $config['toType'];
		$this->from_field_name       = $config['fromFieldName'];
		$this->connection_fields     = ! empty( $config['connectionFields'] ) && is_array( $config['connectionFields'] ) ? $config['connectionFields'] : [];
		$this->connection_args       = ! empty( $config['connectionArgs'] ) && is_array( $config['connectionArgs'] ) ? $config['connectionArgs'] : [];
		$this->edge_fields           = ! empty( $config['edgeFields'] ) && is_array( $config['edgeFields'] ) ? $config['edgeFields'] : [];
		$this->resolve_node          = array_key_exists( 'resolveNode', $config ) && is_callable( $config['resolve'] ) ? $config['resolveNode'] : null;
		$this->resolve_cursor        = array_key_exists( 'resolveCursor', $config ) && is_callable( $config['resolve'] ) ? $config['resolveCursor'] : null;
		$this->resolve_connection    = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : function() {
			return null;
		};
		$this->connection_name       = ! empty( $config['connectionTypeName'] ) ? $config['connectionTypeName'] : $this->get_connection_name( $this->from_type, $this->to_type, $this->from_field_name );
		$this->where_args            = [];
		$this->one_to_one            = isset( $config['oneToOne'] ) && true === $config['oneToOne'];
		$this->connection_interfaces = isset( $config['connectionInterfaces'] ) && is_array( $config['connectionInterfaces'] ) ? $config['connectionInterfaces'] : [];

	}

	/**
	 * Validates that essential key/value pairs are passed to the connection config.
	 *
	 * @param array $config
	 */
	protected function validate_config( array $config ) {

		if ( ! array_key_exists( 'fromType', $config ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have at least a fromType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'toType', $config ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have at least a toType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'fromFieldName', $config ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have at least a fromFieldName defined', 'wp-graphql' ) );
		}

	}

	/**
	 * Utility method that formats the connection name given the name of the from Type and the to
	 * Type
	 *
	 * @param string $from_type        Name of the Type the connection is coming from
	 * @param string $to_type          Name of the Type the connection is going to
	 * @param string $from_field_name  Acts as an alternative "toType" if connection type already defined using $to_type.
	 *
	 * @return string
	 */
	public function get_connection_name( $from_type, $to_type, $from_field_name ) {
		// Create connection name using $from_type + To + $to_type + Connection.
		$connection_name = ucfirst( $from_type ) . 'To' . ucfirst( $to_type ) . 'Connection';

		// If connection type already exists with that connection name. Set connection name using
		// $from_field_name + To + $to_type + Connection.
		if ( ! empty( $this->type_registry->get_type( $connection_name ) ) ) {
			$connection_name = ucfirst( $from_type ) . 'To' . ucfirst( $from_field_name ) . 'Connection';
		}

		return $connection_name;
	}

	/**
	 * If the connection includes connection args in the config, this registers the input args
	 * for the connection
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function register_connection_input() {

		if ( empty( $this->connection_args ) ) {
			return;
		}

		$input_name = $this->connection_name . 'WhereArgs';

		if ( $this->type_registry->get_type( $input_name ) ) {
			return;
		}

		$this->type_registry->register_input_type(
			$input_name,
			[
				// Translators: Placeholder is the name of the connection
				'description' => sprintf( __( 'Arguments for filtering the %s connection', 'wp-graphql' ), $this->connection_name ),
				'fields'      => $this->connection_args,
				'queryClass'  => ! empty( $this->config['queryClass'] ) ? $this->config['queryClass'] : null,
			]
		);

		$this->where_args = [
			'where' => [
				'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
				'type'        => $this->connection_name . 'WhereArgs',
			],
		];

	}

	/**
	 * Registers the Connection Edge type to the Schema
	 *
	 * @throws Exception
	 */
	protected function register_connection_edge_type() {

		if ( true === $this->one_to_one ) {

			$interfaces = [ 'SingleNodeConnectionEdge', 'Edge' ];

			if ( ! empty( $this->connection_interfaces ) ) {
				foreach ( $this->connection_interfaces as $connection_interface ) {
					$interfaces[] = $connection_interface . 'Edge';
				}
			}

			$this->type_registry->register_object_type(
				$this->connection_name . 'Edge',
				[
					'interfaces'  => $interfaces,
					// Translators: Placeholders are for the name of the Type the connection is coming from and the name of the Type the connection is going to
					'description' => sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
					'fields'      => array_merge(
						[
							'node' => [
								'type'        => [ 'non_null' => $this->to_type ],
								'description' => __( 'The node of the connection, without the edges', 'wp-graphql' ),
							],
						],
						$this->edge_fields
					),
				]
			);

		} else {

			$interfaces = [ 'Edge' ];

			if ( ! empty( $this->connection_interfaces ) ) {
				foreach ( $this->connection_interfaces as $connection_interface ) {
					$interfaces[] = $connection_interface . 'Edge';
				}
			}

			$this->type_registry->register_object_type(
				$this->connection_name . 'Edge',
				[
					'description' => __( 'An edge in a connection', 'wp-graphql' ),
					'interfaces'  => $interfaces,
					'fields'      => array_merge(
						[
							'cursor' => [
								'type'        => 'String',
								'description' => __( 'A cursor for use in pagination', 'wp-graphql' ),
								'resolve'     => $this->resolve_cursor,
							],
							'node'   => [
								'type'        => [ 'non_null' => $this->to_type ],
								'description' => __( 'The item at the end of the edge', 'wp-graphql' ),
								'resolve'     => function( $source, $args, $context, ResolveInfo $info ) {
									if ( ! empty( $this->resolve_node ) && is_callable( $this->resolve_node ) ) {
										$resolve_node = $this->resolve_node;
										return ! empty( $source['node'] ) ? $resolve_node( $source['node'], $args, $context, $info ) : null;
									} else {
										return $source['node'];
									}
								},
							],
						],
						$this->edge_fields
					),
				]
			);

		}

	}

	/**
	 * Registers the Connection Type to the Schema
	 *
	 * @throws Exception
	 */
	protected function register_connection_type() {

		$interfaces = [ 'Connection' ];

		if ( ! empty( $this->connection_interfaces ) ) {
			foreach ( $this->connection_interfaces as $connection_interface ) {
				$interfaces[] = $connection_interface;
			}
		}

		$this->type_registry->register_object_type(
			$this->connection_name,
			[
				// Translators: the placeholders are the name of the Types the connection is between.
				'description' => sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
				'interfaces'  => $interfaces,
				'fields'      => array_merge(
					[
						'pageInfo' => [
							// @todo: change to PageInfo when/if the Relay lib is deprecated
							'type'        => [ 'non_null' => 'WPPageInfo' ],
							'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
						],
						'edges'    => [
							'type'        => [
								'non_null' => [
									'list_of' => [ 'non_null' => $this->connection_name . 'Edge' ],
								],
							],
							// Translators: Placeholder is the name of the connection
							'description' => sprintf( __( 'Edges for the %s connection', 'wp-graphql' ), $this->connection_name ),
						],
						'nodes'    => [
							'type'        => [
								'non_null' => [
									'list_of' => [ 'non_null' => $this->to_type ],
								],
							],
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function( $source, $args, $context, $info ) {
								$nodes = [];
								if ( ! empty( $source['nodes'] ) && is_array( $source['nodes'] ) ) {
									if ( ! empty( $this->resolve_node ) && is_callable( $this->resolve_node ) ) {
										$resolve_node = $this->resolve_node;
										foreach ( $source['nodes'] as $node ) {
											$nodes[] = $resolve_node( $node, $args, $context, $info );
										}
									} else {
										return $source['nodes'];
									}
								}

								return $nodes;
							},
						],
					],
					$this->connection_fields
				),
			]
		);

	}

	/**
	 * Get the args used for pagination on connections
	 *
	 * @return array|array[]
	 */
	protected function get_pagination_args() {

		if ( true === $this->one_to_one ) {

			$pagination_args = [];

		} else {

			$pagination_args = [
				'first'  => [
					'type'        => 'Int',
					'description' => __( 'The number of items to return after the referenced "after" cursor', 'wp-graphql' ),
				],
				'last'   => [
					'type'        => 'Int',
					'description' => __( 'The number of items to return before the referenced "before" cursor', 'wp-graphql' ),
				],
				'after'  => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "first" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
				'before' => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "last" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
			];

		}

		return $pagination_args;
	}

	/**
	 * Registers the connection in the Graph
	 */
	public function register_connection_field() {

		$this->type_registry->register_field(
			$this->from_type,
			$this->from_field_name,
			[
				'type'        => true === $this->one_to_one ? $this->connection_name . 'Edge' : $this->connection_name,
				'args'        => array_merge( $this->get_pagination_args(), $this->where_args ),
				'description' => ! empty( $config['description'] ) ? $config['description'] : sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
				'resolve'     => function( $root, $args, $context, $info ) {

					if ( ! isset( $this->resolve_connection ) || ! is_callable( $this->resolve_connection ) ) {
						return null;
					}

					$resolve_connection = $this->resolve_connection;

					/**
					 * Return the results
					 */
					return $resolve_connection( $root, $args, $context, $info );
				},
			]
		);

	}

	/**
	 * Registers the connection Types and field to the Schema
	 *
	 * @throws Exception
	 */
	public function register_connection() {

		$this->register_connection_input();
		$this->register_connection_edge_type();
		$this->register_connection_type();
		$this->register_connection_field();

	}

}
