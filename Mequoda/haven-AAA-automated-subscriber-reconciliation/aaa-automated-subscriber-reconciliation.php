<?php
/**
 * Plugin Name: Haven AAA Automated Subscriber Reconciliation
 * Plugin URL: http://mequoda.com
 * Description: A simple plugin that provides a list of users that should be removed from receiving print and a list that should start receiving print magazine.
 * Version: 1.1
 * Author: Bob Albert <bob@superstan.com>
 * Author URI: http://mequoda.com
 * Contributors: Michael Wendell
 *
 * JIRA references:
 * 		AAA-240 ( https://mequoda.atlassian.net/browse/AAA-240 )
 * 		AAA-356 ( https://mequoda.atlassian.net/browse/AAA-356 )
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class AAA_Automated_Subscriber_Reconciliation
{
	/**
	 * @var string Name used for options
	 */
	private $_optionsName = 'print-subscriber-reconciliation';

	/**
	 * @var string Name used for options
	 */
	private $_optionsGroup = 'print-subscriber-reconciliation-options';


	public function __construct() {
		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function registerOptions() {
		register_setting( $this->_optionsGroup, $this->_optionsName );
	}

	public function  admin_menu(){
		add_submenu_page( 'users.php', __('Print Subscriber Reconciliation'), __('Print Subscriber Reconciliation'), 'manage_options', 'aaa-subscriber-reconciliation',  array( $this, 'admin_options') );
	}

	public function admin_options() {
		$plugin_data = get_plugin_data(__FILE__, 0, 0);

        $reports_path = str_replace(  '/products/', '/reconciliation_reports',DOWNLOAD_PATH);

		$add_files = array_diff( scandir( $reports_path.'/add', 1 ), array( '.', '..' ) );
		$remove_files = array_diff( scandir( $reports_path.'/remove', 1 ), array( '.', '..' ) );

		$download_url = '/wp-content/plugins/haven-AAA-automated-subscriber-reconciliation/download-report.php';

		$settings = get_option( $this->_optionsName );

		?>

		<div class="wrap">
			<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>

            <form action="options.php" method="post">

				<?php settings_fields( $this->_optionsGroup ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="">
								<?php _e( 'Send Reports To:', '' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $this->_optionsName; ?>[sendtoemail]" value="<?php echo $settings['sendtoemail'];?>" id="mq_aaa_subscriber_reconciliation_email" class="regular-text code" /><br/>
                            Enter email the reports should be sent to.
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="Submit" value="<?php _e( 'Update Options' ); ?>" class="button button-primary button-large" />
                </p>
            </form>

            <hr>
            <h3>Download Reports</h3>
            <table class="wp-list-table widefat">
                <thead>
                    <tr><th>Add to Print</th><th>Remove from Print</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php
                            foreach( $add_files as $add_file ){
                                echo '<a href="'. $download_url . '?type=add&report='. $add_file .'">' . $add_file . '</a>';
                                echo "</br>";
                            }

                            //print_r( $add_files ); ?>
                        </td>
                        <td>
                            <?php
                            foreach( $remove_files as $remove_file ){
                                echo '<a href="'. $download_url . '?type=remove&report='. $remove_file .'">' . $remove_file . '</a>';
                                echo "</br>";
                            }
                            //print_r( $remove_files ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>


		</div>

		<?php
	}
}

$AAASubscriberReconciliation = new AAA_Automated_Subscriber_Reconciliation();