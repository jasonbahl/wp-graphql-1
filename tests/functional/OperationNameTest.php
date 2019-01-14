<?php

$I = new FunctionalTester( $scenario );
$I->wantTo('Send multiple queries as the query string, specify one with operationName and get the results for that test back');

/**
 * Make sure there's a post in the database to query for. If there was no data,
 * we'd have some issues.
 */
$I->havePostInDatabase([
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => 'test post',
	'post_content' => 'test post content'
]);

/**
 * Set the content-type so we get a proper response from the API
 */
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode([
	'query' => '
	query FirstQuery {
		firstQuery: posts {
			edges {
				node {
					id
					title
					link
					date
				}
			}
		}
	}
	query SecondQuery {
		secondQuery: posts {
			edges {
				node {
					id
					title
					link
					date
				}
			}
		}
	}',
	'operationName' => 'FirstQuery'
]) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response = $I->grabResponse();
$response_array = json_decode( $response, true );

/**
 * Make sure query is valid and has no errors
 */
$I->assertArrayNotHasKey( 'errors', $response_array  );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayHasKey( 'data', $response_array );

/**
 * Make sure there is a post returned with the data we requested
 */
$I->assertNotEmpty( $response_array['data']['firstQuery']['edges'][0]['node']['id'] );
$I->assertNotEmpty( $response_array['data']['firstQuery']['edges'][0]['node']['title'] );
$I->assertNotEmpty( $response_array['data']['firstQuery']['edges'][0]['node']['link'] );
$I->assertNotEmpty( $response_array['data']['firstQuery']['edges'][0]['node']['date'] );
