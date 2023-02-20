<?php
/**
 * Plugin Name: Haven Post Data Exporter
 * Plugin URI: http://www.mequoda.com
 * Description: Exports a list of posts by selected category and date range.
 * Version: 0.2
 * Author: Mequoda - Bob Albert
 * Author URI: http://www.mequoda.com
 */

/**
 * Class mqExportPostData
 */
class mqExportPostData
{
	static $instance = false;

	private function __construct() {

		if( isset( $_POST['action'] ) && $_POST['action'] == 'mqexportpostdata'  ) {
			add_action( 'init', array( $this, 'csvexport' ) );
		}
		add_action( 'admin_menu', array($this,'adminMenu') );

		if ( is_admin() ) {
			// add blockbuster box
			add_action('add_meta_boxes_post', array($this, 'add_blockbuster_meta_box'));
			add_action('save_post', array($this, 'save_blockbuster_metabox'), 10, 2);
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 10, 1 );
	}

	public static function getInstance() {
		if (!self::$instance) { self::$instance = new self; }
		return self::$instance;
	}

	public function adminMenu() {
		add_submenu_page('edit.php',__('Haven Post Data Exporter'), __('Haven Post Data Exporter'), 'manage_categories', basename(__FILE__), array($this, 'options'));
	}

	public function add_admin_scripts(){
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('mqexporter-admin-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.0/themes/base/jquery-ui.css',false,"1.12.0",false);
	}

	public function add_blockbuster_meta_box( $post ) {
		add_meta_box(
			'blockbuster-meta-box',
			__( 'Blockbuster Post' ),
			array($this, 'render_blockbuster_meta_box'),
			'post',
			'side',
			'high'
		);
	}

    public  function render_blockbuster_meta_box( $post ){

		// Add nonce for security and authentication.
		wp_nonce_field( 'blockbuster_nonce_action', 'blockbuster_nonce' );

		$blockbuster_post = get_post_meta( $post->ID, 'blockbusterpost', true );
        ?>
        <label for="blockbusterpost">Is this a Blockbuster Post?</label><br/>
		<input type="checkbox" name="blockbusterpost" value="1" <?php checked( $blockbuster_post, '1' ); ?> /> Yes
        <?php
    }

	public function save_blockbuster_metabox( $post_id, $post ) {
		// Add nonce for security and authentication.
		$nonce_name   = isset( $_POST['blockbuster_nonce'] ) ? $_POST['blockbuster_nonce'] : '';
		$nonce_action = 'blockbuster_nonce_action';

		// Check if nonce is set.
		if ( ! isset( $nonce_name ) ) {
			return;
		}

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if( isset( $_POST['blockbusterpost'] ) ){
		    update_post_meta( $post_id, 'blockbusterpost', 1 );
        } else {
		    delete_post_meta( $post_id, 'blockbusterpost' );
        }
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
		global $wpdb;
		$plugin_data = get_plugin_data(__FILE__, 0, 0);

		?>

		<div class="wrap">

			<h2><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h2>

			<?php $args = array(
			'show_option_all'    => '',
			'show_option_none'   => '',
			'option_none_value'  => '-1',
			'orderby'            => 'ID',
			'order'              => 'ASC',
			'show_count'         => 0,
			'hide_empty'         => 1,
			'child_of'           => 0,
			'exclude'            => '',
			'include'            => '',
			'echo'               => 0,
			'selected'           => 0,
			'hierarchical'       => 1,
			'name'               => 'cat',
			'id'                 => '',
			'class'              => 'postform',
			'depth'              => 1,
			'tab_index'          => 0,
			'taxonomy'           => 'category',
			'hide_if_empty'      => false,
			'value_field'	     => 'term_id',
			);

			$dropdown = wp_dropdown_categories( $args );
			?>

            <script>
                jQuery(function() {
                    jQuery( ".datepicker" ).datepicker({
                        dateFormat : "mm-dd-yy"
                    });
                });
            </script>

            <form method="post" action="/wp-admin/edit.php?page=haven-post-data-exporter.php">
                <div class="search-filter">
					<?php echo "<p>Category:<br/>" . $dropdown . "</p>"; ?>

                    <p>
                        <label for="date_from">Date Range to Export (MM-DD-YYYY):</label>
                        <input id="date_from" class="datepicker" type="text" value="" name="date_from"/>
                        <strong>to</strong>
                        <input id="date_to" class="datepicker" type="text" value="" name="date_to"/>
                    </p>
                    <p>
                        <input type=submit name="mqpostdataexport" value="Download CSV" class="button">
                        <input type="hidden" name="action" value="mqexportpostdata"/>

                    </p>
                </div>
            </form>

		</div>

		<?php
	}

	public function csvexport(){

			$cat = $_POST['cat'];

			$start_date = $_POST['date_from'];
			$end_date = $_POST['date_to'];

			$date_query = false;
			if( !empty( $start_date ) && !empty( $end_date ) ){

				$start_date = explode( '-', $start_date );
				$end_date = explode( '-', $end_date );

				$date_query = array(
					array(
						'after' => array(
							'year' => $start_date[2],
							'day' => $start_date[1],
							'month' => $start_date[0]

						),
						'before' => array(
							'year' => $end_date[2],
							'day' => $end_date[1],
							'month' => $end_date[0]

						),
						'inclusive' => true,
					),
				);
			} else if( !empty( $start_date ) ){
				$start_date = explode( '-', $start_date );

				$date_query = array(
					array(
						'after' => array(
							'year' => $start_date[2],
							'day' => $start_date[1],
							'month' => $start_date[0]

						),
						'inclusive' => true,
					),
				);

			} else if( !empty( $end_date ) ){
				$end_date = explode( '-', $end_date );
				$date_query = array(
					array(
						'before' => array(
							'year' => $end_date[2],
							'day' => $end_date[1],
							'month' => $end_date[0]

						),
						'inclusive' => true,
					),
				);

			}

			$query_args = array(
				'cat' => $cat,
				'post_status' => 'published',
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'ASC'
			);// find the posts

			if( $date_query ){
				$query_args['date_query'] = $date_query;
			}

			$query = new WP_Query($query_args);

			$csv_body = 'Publish Date, Post Title, Post Category, URL, Blockbuster' . "\r\n";


		if ( $query->have_posts() ) {
			foreach ($query->posts as $post) {

				$csv_body .= $post->post_date . ',';
				$csv_body .= '"' . $post->post_title . '",';

				$categories = get_the_category($post->ID);

				$i = 0;
				$thelist = '';
				foreach ($categories as $category) {
					if (0 < $i)
						$thelist .= ',';

					$thelist .= $category->name;

					$i++;
				}

				$csv_body .= '"' . $thelist . '",';

				$permalink = get_permalink($post->ID);

				$csv_body .= $permalink .",";

				$blockbuster = get_post_meta( $post->ID, 'blockbusterpost', true );
				if( $blockbuster ){
					$csv_body .= 'Yes' . "\r\n";
				} else {
					$csv_body .= "\r\n";
				}
			}

			header("Content-type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"post-data-export.csv\"");
			echo $csv_body;
			exit();
		} else {

			add_action( 'admin_notices', array( $this, 'mq_exporter_error_notice' ) );

		}


	}

	public function mq_exporter_error_notice() {
		?>
        <div class="error notice">
            <p><?php _e( 'No Posts Found. Bummer!', 'mq_exporter' ); ?></p>
        </div>
		<?php
	}

}

// Instantiate our class
$mqExportPostData = mqExportPostData::getInstance();