<?php

/**
 * Gravity Forms Kitchen API Library.
 */
class GF_Kitchen_API {

	/**
	 * The api version.
	 *
	 * @var string
	 */
	const API_VERSION = 'v1';

	/**
	 * Account API token.
	 *
	 * @var string
	 */
	protected $api_token;

	/**
	 * Workspace.
	 *
	 * @var string
	 */
	protected $workspace;

	/**
	 * Initialize API library.
	 *
	 * @param string $api_key
	 * @param string $workspace
	 */
	public function __construct( $api_key, $workspace ) {
		$this->api_token = $api_key;
		$this->workspace = $workspace;
	}

	/**
	 * Create a project in the Kitchen workspace.
	 *
	 * @param array $data  The project submission data.
	 *
	 * @return array
	 */
	public function create_project( $data ) {

		return $this->process_request( 'app-integrations/gravity-forms', $data, 'POST' );

	}

	/**
	 * Check API connection status.
	 *
	 * @return bool
	 */
	public function get_status() {

		return $this->process_request( 'status', '', 'GET' );

	}


	/**
	 * Process Kitchen API request.
	 *
	 * @param string $path       Request path.
	 * @param array  $data       Request data.
	 * @param string $method     Request method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array
	 * @throws Exception If API request returns an error, exception is thrown.
	 */
	private function process_request( $path = '', $data = [], $method = 'GET', $return_key = null ) {

		// If API key is not set, throw exception.
		if ( rgblank( $this->api_token ) ) {
			throw new Exception( 'API token must be defined to process an API request.' );
		}

		// Build base request URL.
		$request_url = sprintf( '%s/api/%s/%s', $this->workspace, self::API_VERSION, $path ) ;

		// Add request URL parameters if needed.
		if ( 'GET' === $method && ! empty( $data ) ) {
			$request_url = add_query_arg( $data, $request_url );
		}

		// Build base request arguments.
		$args = [
			'method'   => $method,
			'headers'  => [
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type'  => 'application/json',
			],

			/**
			 * Filters if SSL verification should occur.
			 *
			 * @param bool false If the SSL certificate should be verified. Defaults to false.
			 *
			 * @return bool
			 */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),

			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int    30           The timeout limit, in seconds. Defaults to 30.
			 * @param string $request_url The request URL.
			 *
			 * @return int
			 */
			'timeout'   => apply_filters( 'http_request_timeout', 30, $request_url ),
		];

		// Add data to arguments if needed.
		if ( 'GET' !== $method ) {
			$args['body'] = json_encode( $data );
		}

		/**
		 * Filters the Kitchen request arguments.
		 *
		 * @param array  $args The request arguments sent to Kitchen.
		 * @param string $path The request path.
		 *
		 * @return array
		 */
		$args = apply_filters( 'gform_kitchen_request_args', $args, $path );

		// Get request response.
		$response = wp_remote_request( $request_url, $args );

		// If request was not successful, throw exception.
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		// Decode response body.
		$response['body'] = json_decode( $response['body'], true );

		// Get the response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $response_code, [ 200, 204 ] ) ) {

			if ( isset( $response['body']['message'] ) ) {
				throw new Exception( $response['body']['message'] );
			}

			throw new Exception( wp_remote_retrieve_response_message( $response ), $response_code );

		}

		// If a return key is defined and array item exists, return it.
		if ( ! empty( $return_key ) && isset( $response['body'][ $return_key ] ) ) {
			return $response['body'][ $return_key ];
		}

		return $response['body'];

	}

}
