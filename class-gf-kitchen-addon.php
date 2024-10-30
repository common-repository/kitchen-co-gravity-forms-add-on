<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Kitchen Add-On.
 */
class GFKitchenAddOn extends GFFeedAddOn {

    /**
     * Contains an instance of this class, if available.
     *
     * @var GFKitchenAddOn If available, contains an instance of this class.
     */
    private static $_instance = null;

    /**
     * @var string Version number of the Add-On
     */
    protected $_version = GF_KITCHEN_ADDON_VERSION;

    /**
     * @var string Gravity Forms minimum version requirement
     */
    protected $_min_gravityforms_version = '1.9';

    /**
     * @var string URL-friendly identifier used for form settings, add-on settings, text domain localization...
     */
    protected $_slug = 'gravity-forms-kitchen';

    /**
     * @var string Relative path to the plugin from the plugins folder. Example "gravityforms/gravityforms.php"
     */
    protected $_path = 'gravity-forms-kitchen/gravity-forms-kitchen.php';

    /**
     * @var string Full path the the plugin. Example: __FILE__
     */
    protected $_full_path = __FILE__;

    /**
     * @var string Title of the plugin to be used on the settings page, form settings and plugins page. Example: 'Gravity Forms MailChimp Add-On'
     */
    protected $_title = 'Gravity Forms Kitchen Add-On';

    /**
     * @var string Short version of the plugin title to be used on menus and other places where a less verbose string is useful. Example: 'MailChimp'
     */
    protected $_short_title = 'Kitchen';

    /**
     * If set to true, Add-On can have multiple feeds configured. If set to false, feed list page doesn't exist and only one feed can be configured.
     *
     * @var bool
     */
    protected $_multiple_feeds = false;

    /**
     * Contains an instance of the Kitchen API library, if available.
     *
     * @var GF_Kitchen_API If available, contains an instance of the Kitchen API library.
     */
    public $api = null;

    /**
     * Get an instance of this class.
     *
     * @return GFKitchenAddOn
     */
    public static function get_instance() {

        if ( self::$_instance == null ) {
            self::$_instance = new GFKitchenAddOn();
        }

        return self::$_instance;

    }

    /**
     * Autoload the required libraries.
     */
    public function pre_init() {

        parent::pre_init();

        if ( $this->is_gravityforms_supported() ) {
            // Load the Kitchen API library.
            if ( ! class_exists( 'GF_Kitchen_API' ) ) {
                require_once( 'includes/class-gf-kitchen-api.php' );
            }
        }

    }

    // # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields() {

        return [
            [
                'fields' => [
                    [
                        'name'              => 'workspace',
                        'label'             => esc_html__( 'Workspace (e.g. https://acme.kitchen.co)', 'gfkitchen' ),
                        'type'              => 'text',
                        'class'             => 'small',
                        'feedback_callback' => [ $this, 'is_valid_setting' ],
                    ],
                    [
                        'name'              => 'api_token',
                        'label'             => esc_html__( 'API Token', 'gfkitchen' ),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => [ $this, 'is_valid_setting' ],
                        'after_input'       => function () {
                            ob_start();

                            echo wpautop( sprintf( esc_html__( 'You can get this from your %s', 'gfkitchen' ),
                                '<strong>Kitchen Workspace -> Settings -> API -> Generate new API token</strong>'
                            ) );

                            try {
                                $isInitialized = $this->initialize_api();

                                if (! $isInitialized) {
                                    throw new \Exception( __( 'Unable to initialize', 'gfkitchen' ) );
                                }

                                $this->api->get_status();
                            } catch ( \Exception $e ) {
                                ?>
                                <div class="alert gforms_note_error">
                                    <?php printf( esc_html__( 'Unable to connect to Kitchen.co: %s', 'gfkitchen' ), $e->getMessage() ); ?>
                                </div>
                                <?php
                                return ob_get_clean();
                            }

                            ?>
                            <div class="alert gforms_note_success">
                                <?php esc_html_e( 'Successfully connected to Kitchen.co', 'gfkitchen' ); ?>
                            </div>
                            <?php

                            return ob_get_clean();
                        },
                    ]
                ]
            ]
        ];

    }

    // # FEED SETTINGS -------------------------------------------------------------------------------------------------

    /**
     * Configures the settings which should be rendered on the feed edit page.
     *
     * @return array
     */
    public function feed_settings_fields() {

        return [
            [
                'fields' => [
                    [
                        'name'    => 'enabled',
                        'label'   => esc_html__( 'Feed Status', 'gfkitchen' ),
                        'type'    => 'checkbox',
                        'choices' => [
                            [
                                'label'         => esc_html__( 'Enabled', 'gfkitchen' ),
                                'name'          => 'enabled',
                                'default_value' => 1,
                            ],
                        ],
                    ],
                    [
                        'name'      => 'base_fields',
                        'label'     => esc_html__( 'Base Fields', 'gfkitchen' ),
                        'type'      => 'field_map',
                        'field_map' => [
                            'name' => [
                                'name'       => 'name',
                                'label'      => esc_html__( 'Name', 'gfkitchen' ),
                                'required'   => true,
                                'field_type' => [ 'name', 'text', 'hidden' ],
                            ],
                            'email' => [
                                'name'       => 'email',
                                'label'      => esc_html__( 'Email Address', 'gfkitchen' ),
                                'required'   => true,
                                'field_type' => [ 'email', 'hidden' ],
                            ]
                        ],
                        'tooltip'   => sprintf(
                            '<h6>%s</h6>%s',
                            esc_html__( 'Base Fields', 'gfkitchen' ),
                            esc_html__( 'Associate the Kitchen base fields to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gfkitchen' )
                        ),
                    ]
                ],
            ],
        ];

    }

    /**
     * Prevent feeds being listed or created if the API key isn't valid.
     *
     * @return bool
     */
    public function can_create_feed() {

        return $this->initialize_api();

    }

    // # FEED PROCESSING -----------------------------------------------------------------------------------------------

    /**
     * Process the feed, subscribe the user to the list/audience.
     *
     * @param array $feed  The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form  The form object currently being processed.
     *
     * @return array
     */
    public function process_feed( $feed, $entry, $form ) {

        // Check if the feed is enabled
        if ( ! rgars( $feed, 'meta/enabled' ) ) {
            return $entry;
        }

        // If unable to initialize API, log error and return.
        if ( ! $this->initialize_api() ) {
            $this->add_feed_error( esc_html__( 'Unable to process feed because API could not be initialized.', 'gfkitchen' ), $feed, $entry, $form );
            return $entry;
        }


        $base_fields_map = $this->get_field_map_fields( $feed, 'base_fields' );
        $name = $this->get_field_value( $form, $entry, $base_fields_map['name'] );
        $email = $this->get_field_value( $form, $entry, $base_fields_map['email'] );

        // If email is invalid, log error and return.
        if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
            $this->add_feed_error( esc_html__( 'A valid Email address must be provided.', 'gfkitchen' ), $feed, $entry, $form );
            return $entry;
        }

        // If name is invalid, log error and return.
        if ( rgblank( $name ) ) {
            $this->add_feed_error( esc_html__( 'A valid name must be provided.', 'gfkitchen' ), $feed, $entry, $form );
            return $entry;
        }

        $meta_fields = [];
        $index = 0;

        foreach ( $form['fields'] as $field ) {
            if ( array_search( $field['id'], array_values( $base_fields_map ) ) !== false ) {
                continue;
            }

            $index++;

            $meta_fields[] = [
                'type' => 'long_text',
                'label' => $field->label,
                'value' => $this->get_field_value( $form, $entry, $field->id ),
                'position' => count( $base_fields_map ) + $index,
            ];
        }

        $project_data = [
            'name' => $form['title'],
            'referer' => $entry['source_url'],
            'fields' => array_merge(
                [
                    [
                        'type' => 'name',
                        'label' => 'Name',
                        'value' => $name,
                        'position' => 1,
                    ],
                    [
                        'type' => 'email',
                        'label' => 'Email',
                        'value' => $email,
                        'position' => 2,
                    ],
                ],
                $meta_fields
            )
        ];

        try {
            $this->api->create_project( $project_data );
        } catch ( Exception $e ) {
            $this->add_feed_error( sprintf( esc_html__( 'Unable to create project: %s', 'gfkitchen' ), $e->getMessage() ), $feed, $entry, $form );
            return $entry;
        }

    }

    // # HELPERS -------------------------------------------------------------------------------------------------------

    /**
     * Initializes Kitchen API if credentials are valid.
     *
     * @param string|null $api_token
     * @param string|null $workspace
     *
     * @return bool|null
     */
    public function initialize_api( $api_token = null, $workspace = null ) {

        // If API is already initialized, return true.
        if ( ! is_null( $this->api ) ) {
            return true;
        }

        // Get the API token.
        if ( rgblank( $api_token ) ) {
            $api_token = $this->get_plugin_setting( 'api_token' );
        }

        // Get the workspace.
        if ( rgblank( $workspace ) ) {
            $workspace = untrailingslashit( $this->get_plugin_setting( 'workspace' ) );
        }

        if ( rgblank( $api_token ) || rgblank( $workspace ) ) {
            return false;
        }

        // Setup a new Kitchen object with the API credentials.
        $this->api = new GF_Kitchen_API( $api_token, $workspace );

        try {
            $this->api->get_status();
        } catch ( \Exception $e ) {
            return false;
        }

        return true;

    }

    /**
     * The feedback callback validation.
     *
     * @param string $value The setting value.
     *
     * @return bool
     */
    public function is_valid_setting( $value ) {

        return strlen( $value ) > 0;

    }

}
