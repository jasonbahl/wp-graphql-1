<?php
class WP_GraphQL_Test_WPSchema extends WP_UnitTestCase {

	public $post;
	public $admin;
	public $editor;
	public $subscriber;
	public $global_id;

	public function setUp() {

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$this->editor = $this->factory->user->create( [
			'role' => 'editor',
		] );

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

		$this->post = $this->factory->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
		] );

		$this->global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $this->post );

		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public static function _add_fields( $fields ) {

		$fields['testIsPrivate'] = [
			'type' => \WPGraphQL\Types::string(),
			'isPrivate' => true,
			'resolve' => function() {
				return 'isPrivateValue';
			}
		];

		$fields['authCallback'] = [
			'type' => \WPGraphQL\Types::string(),
			'auth' => [
				'callback' => function( $resolver ) {
					/**
					 * If the current user doesn't have user meta "authCallbackTest" with value "secret"
					 * throw an error (do not resolve the field)
					 */
					if ( 'secret' !== get_user_meta( wp_get_current_user()->ID, 'authCallbackTest', true ) ) {
						throw new \GraphQL\Error\UserError( __( 'You need the secret!', 'wp-graphql' ) );
					}
					return $resolver;
				}
			],
			'resolve' => function() {
				return 'authCallbackValue';
			}
		];

		$fields['authRoles'] = [
			'type' => \WPGraphQL\Types::string(),
			'auth' => [
				'allowedRoles' => [ 'administrator', 'editor' ],
			],
			'resolve' => function() {
				return 'allowedRolesValue';
			}
		];

		$fields['authCaps'] = [
			'type' => \WPGraphQL\Types::string(),
			'auth' => [
				'allowedCaps' => [ 'manage_options', 'graphql_rocks' ],
			],
			'resolve' => function() {
				return 'allowedCapsValue';
			}
		];

		return $fields;

	}

	/**
	 * This tests to make sure a field marked isPrivate will return a null value for the resolver
	 */
	public function testIsPrivate() {

		add_filter( 'graphql_post_fields', [ 'WP_GraphQL_Test_WPSchema', '_add_fields' ], 10, 1 );

		/**
		 * Set the current user to nobody
		 */
		wp_set_current_user( 0 );

		$request = '
		query getPost( $id:ID! ) {
		  post( id:$id ) {
		    id
		    postId
		    testIsPrivate
		  }
		}
		';

		/**
		 * Run the request
		 */
		$variables = wp_json_encode( [ 'id' => $this->global_id ] );
		$actual = do_graphql_request( $request, 'getPost', $variables );

		var_dump( $actual );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertNull( $actual['data']['post']['testIsPrivate'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

		/**
		 * Set the user as an admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the request
		 */
		$actual = do_graphql_request( $request, 'getPost', $variables );

		/**
		 * The query should execute, and should NOT contain errors but should properly resolve the "isPrivateValue"
		 * for the "testIsPrivate" field
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertEquals( 'isPrivateValue', $actual['data']['post']['testIsPrivate'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

	}

	public function testAuthCallback() {

		add_filter( 'graphql_post_fields', [ 'WP_GraphQL_Test_WPSchema', '_add_fields' ], 10, 1 );

		/**
		 * Set the current user to nobody
		 */
		wp_set_current_user( $this->admin );

		$request = '
		query getPost( $id:ID! ) {
		  post( id:$id ) {
		    id
		    postId
		    authCallback
		  }
		}
		';

		/**
		 * Run the request
		 */
		$variables = wp_json_encode( [ 'id' => $this->global_id ] );
		$actual = do_graphql_request( $request, 'getPost', $variables );

		var_dump( $actual );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertNull( $actual['data']['post']['authCallback'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

		/**
		 * Add the "authCallbackTest" value to the user so the authCallback will not throw an error
		 */
		update_user_meta( $this->admin, 'authCallbackTest', 'secret' );

		/**
		 * Run the request
		 */
		$actual = do_graphql_request( $request, 'getPost', $variables );

		/**
		 * The query should execute, and should NOT contain errors, but should contain the value
		 * of the authCallback field
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertEquals( 'authCallbackValue', $actual['data']['post']['authCallback'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

	}

	public function testAuthRoles() {

		add_filter( 'graphql_post_fields', [ 'WP_GraphQL_Test_WPSchema', '_add_fields' ], 10, 1 );

		/**
		 * Set the current user to nobody
		 */
		wp_set_current_user( $this->admin );

		$request = '
		query getPost( $id:ID! ) {
		  post( id:$id ) {
		    id
		    postId
		    authRoles
		  }
		}
		';

		/**
		 * Run the request
		 */
		$variables = wp_json_encode( [ 'id' => $this->global_id ] );
		$actual = do_graphql_request( $request, 'getPost', $variables );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertEquals( 'allowedRolesValue', $actual['data']['post']['authRoles'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

		wp_set_current_user( $this->editor );

		$actual = do_graphql_request( $request, 'getPost', $variables );

		var_dump( $actual );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertEquals( 'allowedRolesValue', $actual['data']['post']['authRoles'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

		wp_set_current_user( $this->subscriber );

		$actual = do_graphql_request( $request, 'getPost', $variables );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertNull( $actual['data']['post']['authRoles'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

	}

	public function testAuthCaps() {

		add_filter( 'graphql_post_fields', [ 'WP_GraphQL_Test_WPSchema', '_add_fields' ], 10, 1 );

		/**
		 * Set the current user to nobody
		 */
		wp_set_current_user( $this->admin );

		$user = new WP_User( $this->admin );
		$user->add_cap( 'manage_options' );
		$user->add_cap( 'graphql_rocks' );

		$request = '
		query getPost( $id:ID! ) {
		  post( id:$id ) {
		    id
		    postId
		    authCaps
		  }
		}
		';

		/**
		 * Run the request
		 */
		$variables = wp_json_encode( [ 'id' => $this->global_id ] );
		$actual = do_graphql_request( $request, 'getPost', $variables );

		var_dump( $actual );

		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertEquals( 'allowedCapsValue', $actual['data']['post']['authCaps'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );


		/**
		 * Remove the caps from the user
		 */
		$user = new WP_User( $this->editor );
		$user->remove_cap( 'manage_options' );
		$user->remove_cap( 'graphql_rocks' );
		wp_set_current_user( $user->ID );

		/**
		 * Run the request, this time the value should be null and there should be an error
		 */
		$actual = do_graphql_request( $request, 'getPost', $variables );


		/**
		 * The query should execute, but should contain errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertNull( $actual['data']['post']['authCaps'] );
		$this->assertEquals( $this->post, $actual['data']['post']['postId'] );

	}

}