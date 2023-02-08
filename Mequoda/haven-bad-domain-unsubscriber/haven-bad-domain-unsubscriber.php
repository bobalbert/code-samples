<?php
/**
 * Plugin Name: Haven Bad Domain Unsubscriber
 * Plugin URI: http://www.mequoda.com
 * Description: Unsubscribe users based on uploaded domain list
 * Version: 1.0.0
 * Author: Mequoda - Bob Albert
 * Author URI: http://www.mequoda.com
 */

/**
 * Change log
 *  2022-12-22 - 1.0.0 - Bob Albert
 *      - TECHOHPS-845 initial version
 *      "Plugin to Block spammy domains on a global level"
 */

class Haven_Bad_Domain_Unsubscriber {

	/**
	 * Static property to hold our singleton instance
	 * @var $mq_bad_domain_unsubscriber
	 */
	static $instance = false;

	/**
	 * Table name to hold uploaded domain records
	 *
	 * @var string
	 */
	private $_uploadTable = 'mequoda_bad_domain_unsubscriber';

	/**
	 * @access private
	 *
	 * @var string Admin Page
	 */
	private $_admin_page = 'haven-bad-domain-unsubscriber';

	/**
	 * @var array Plugin settings
	 */
	protected $_settings;

	/**
	 * @var string Name used for options
	 */
	private $_optionsName = 'bad-domain-unsubscriber';

	/**
	 * @var string Name used for options
	 */
	private $_optionsGroup = 'bad-domain-unsubscriber-options';


	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'admin_init', array($this,'register_options') );
		add_action( 'admin_menu', array($this,'admin_menu') );
		add_action( 'admin_init', array($this, 'handleUpload') );

		if ( ! empty($_GET['page']) && $this->_admin_page == $_GET['page'] && isset($_POST['process_download_file']) ){
			add_action( 'init', array( $this, 'handle_download' ) );
		}
	}

	public function register_options() {
		register_setting( $this->_optionsGroup, $this->_optionsName );
	}

	public function activate() {
		global $wpdb;

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

		// Create Bad Domain Unsubscriber table if needed
		dbDelta( "CREATE TABLE " . $wpdb->prefix . $this->_uploadTable . " (
			`domain` varchar(255) NOT NULL,
			PRIMARY KEY (`domain`)
			);"
		);

	}

	public function admin_menu() {
		//add_options_page(__('Haven Bad Domain Unsubscriber'), __('Haven Bad Domain Unsubscriber'), 'manage_options', 'mq-bad-domain-blocker', array($this, 'options'));
		add_submenu_page('users.php', __('Haven Bad Domain Unsubscriber'), __('Haven Bad Domain Unsubscriber'), 'manage_options', $this->_admin_page, array($this, 'options'));
	}

	public static function getInstance() {
		if (!self::$instance) { self::$instance = new self; }
		return self::$instance;
	}

	/**
	 * Handle upload of email file
	 */
	public function handleUpload() {
		global $wpdb;
		$debug = false;
		if ( ! empty( $_GET['page']) && $this->_admin_page == $_GET['page'] && isset( $_POST['process_uploaded_file'] ) ) {
			if ( isset( $_FILES['mq_upload_file'] ) ) {
				check_admin_referer( 'mq-upload' );
				// check that uploaded file mime is accepted mime type (not really to be trusted, but preliminary check)
				$accepted_types = array(
					'text/plain',
					'text/html',
					'text/csv',
					'text/comma-separated-values',
					'application/vnd.ms-excel'
				);
				$notice = '';
				$results_msg = '';

				if ( ! in_array($_FILES['mq_upload_file']['type'], $accepted_types) ) {
					$notice = 'Problem: Uploaded file type is ' . $_FILES['mq_upload_file']['type'] . '.';
				} else {
					$plugin_path = plugin_dir_path( __FILE__ );
					$uploads_dir = $plugin_path . 'uploads/';
					if ( ! file_exists( $uploads_dir ) ) {
						mkdir( $uploads_dir, 0755 );
					}
					$processing_dir = $plugin_path . 'processing/';
					if ( ! file_exists( $processing_dir ) ) {
						mkdir( $processing_dir, 0755 );
					}

					$uploaded_file = $plugin_path . 'uploads/' . basename($_FILES['mq_upload_file']['name']);
					if ( move_uploaded_file($_FILES['mq_upload_file']['tmp_name'], $uploaded_file) ) {
						// $notice = 'Uploaded file saved as ' . basename($_FILES['mq_upload_file']['name']) . '.';

						$dir = opendir($uploads_dir);

						// debugging
						$results_msg .= '$uploads_dir: ' . $dir . "<br />\n";
						if ( ! $dir ) {
							$results_msg .= 'Unable to open uploads directory.' . "<br />\n";
						}

						while ( $file = readdir($dir) ) {

							if ( $file != '.' && $file != '..' && $file != '.DS_Store' && ! is_dir($file) ) {

								// debugging
								$results_msg .= '$file from readdir($dir): ' . $file . "<br />\n";

								ini_set('auto_detect_line_endings', true);

								// move file then open it
								$file_move = rename( $uploads_dir . $file, $processing_dir . $file );

								$email_file = @fopen($processing_dir . $file, 'rb');

								if ( $email_file ) {
									global $import_data, $records_added;
									$import_data = array();
									$records_added = 0;
									$records_uploaded = 0;

									while ( ($data = fgets($email_file)) !== FALSE ) {
										$records_uploaded++;
										$import_data[] = trim($data);
									}

									// debugging
									$results_msg .= 'count($import_data): ' . count($import_data) . "<br />\n";

									for ($i = 0; $i < count($import_data); $i++) {
										$insert_result = $wpdb->replace(
											$wpdb->prefix . $this->_uploadTable,
											array(
												'domain' => $import_data[$i]
											),
											array(
												'%s'
											)
										);
										if ( $insert_result ) {
											$records_added++;
										}
									}

									$notice .= 'Added ' . $records_added . ' records from file ' . $file . ".";

									$results_msg .= 'File ' . $uploads_dir . $file . " was opened successfully.<br />\n";
									$results_msg .= 'Number of items in file: ' . $records_uploaded . "<br />\n";
									$results_msg .= 'Last MySQL error: ' . $wpdb->last_error . "<br />\n";

								} else {
									$results_msg .= 'Unable to open file ' . $file . ".<br />\n";
								}

								// delete file
								unlink($processing_dir . $file);
							}
						}
						closedir($dir);

					} else {
						$notice = 'Unable to save uploaded file.';
					}
				}
				if ( $debug ) {
					$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p><p>$results_msg</p></div>" );
				} else {
					$notice = str_replace( "'", "\'", "<div class='updated'><p>$notice</p></div>" );
				}

				add_action('admin_notices', function( $args ) use ( $notice ) { echo $notice; } );
			}
		}
	}

	/**
     * Download a file of the domains in the table
	 * @return void
	 */
    public function handle_download(){
	    check_admin_referer( 'mq-download' );

        global $wpdb;

        $bad_domains = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$this->_uploadTable};");

        $data = array();
	    foreach ( $bad_domains as $bad_domain ) {
		    $data[] = [$bad_domain->domain];
	    }

	    $filedate = wp_date( 'Y-m-d' );
	    $filename = $filedate . "-bad-domains-list.csv";

	    header( 'Content-Type: application/csv' );
	    header( "Content-Disposition: attachment; filename=$filename" );

	    ob_end_clean();
	    $output = fopen( "php://output", "w" );

	    // fwrite( $output, "sep=\t" . PHP_EOL );
	    foreach ( $data as $row ) {
		    fputcsv( $output, $row, "," );
	    }

	    fclose( $output );
	    ob_flush();
	    exit();
    }

	/**
	 * Admin Menu for the Tool
	 * @return void
	 */
	public function options( ) {

		$plugin_data = get_plugin_data(__FILE__, 0, 0);
		$settings = get_option( $this->_optionsName );

		?>

		<div class="wrap">
			<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>
			<hr class="wp-header-end">

            <h3><?php _e( 'Settings' ) ?></h3>
            <p>Enter the email addresses (separated by commas) where report notifications should be sent.</p>
            <form action="options.php" method="post">

				<?php settings_fields( $this->_optionsGroup ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="notification_email">Notification Email:</label></th><td><input class="regular-text code" type="input" name="<?php echo $this->_optionsName; ?>[sendtoemail]" value="<?php echo $settings['sendtoemail'];?>"/></td>
                    </tr>
                </table>

                <p class="submit">
                <input type="submit" name="Submit" value="<?php _e( 'Save Settings' ); ?>" class="button button-primary button-large" />
                </p>
            </form>


            <hr/>
            <h3><?php _e( 'Upload Bad Domains List' ) ?></h3>

            <form enctype="multipart/form-data" action="" method="post">

				<?php wp_nonce_field( 'mq-upload' ); ?>

                <input type="hidden" name="process_uploaded_file" value="1"/>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="mq_upload_file"><?php _e( 'File to Upload:' ); ?></label>
                        </th>
                        <td>
                            <input type="file" name="mq_upload_file" id="mq_upload_file" />
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="Submit" value="<?php _e( 'Upload' ); ?>" class="button button-primary button-large" />
                </p>
            </form>

            <hr/>
            <h3><?php _e( 'Download Current Bad Domains List' ) ?></h3>
            <form enctype="multipart/form-data" action="" method="post">

				<?php wp_nonce_field( 'mq-download' ); ?>

                <input type="hidden" name="process_download_file" value="1"/>

                <p class="submit">
                    <input type="submit" name="Submit" value="<?php _e( 'Download' ); ?>" class="button button-primary button-large" />
                </p>
            </form>

		</div>

	<?php
	}
}

// Instantiate our class
$mq_bad_domain_unsubscriber = Haven_Bad_Domain_Unsubscriber::getInstance();