<?php
/*
Plugin Name: Users Dashboard for ads-wordpress
Description: шорткод [asdb_dashboard] Добавляет список объявлений пользователя с возможностью редактировать и удалять их.
Version: 0.0.3
Author: Mikhail "kitassa" Tkacheff
Author URI: http://wpbuild.ru/
Plugin URI: http://tkacheff.ru/844/asdb-dashboard/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Copyright (C) 2015, WPBuild - www@artstorm.su
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE .*/

define('ASDB_DASHBOARD_URL', plugins_url('', __FILE__)); define('ADS_WORDPRESS_DIR', WP_PLUGIN_DIR.'/ads-wordpress/');

load_plugin_textdomain('asdb-dashboard', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/lang/');

function active_post_status( $status ) { if ( $status == 'publish' ) { $title = __( 'Live', 'asdb-dashboard' );$fontcolor = '#33CC33';
	  } else if ( $status == 'draft' ) { $title		= __( 'Offline', 'asdb-dashboard' ); $fontcolor = '#bbbbbb';
      } else if ( $status == 'pending' ) { $title	= __( 'Awaiting Approval', 'asdb-dashboard' ); $fontcolor = '#C00202';
      } else if ( $status == 'future' ) { $title	= __( 'Scheduled', 'asdb-dashboard' ); $fontcolor = '#bbbbbb';
      }
    echo '<span style="color:' . $fontcolor . ';">' . $title . '</span>';
}

class ASDB_Dashboard {

    function __construct() { add_shortcode( 'asdb_dashboard', array($this, 'shortcode') ); }

    function shortcode( $atts ) {

        $post_type = get_option( 'post_type', 'asdb_dashboard', 'post' );
        $default   = array('post_type' => $post_type);

        extract( shortcode_atts( $default, $atts ) );
        ob_start();

        if ( is_user_logged_in() ) {
            $this->post_listing( $post_type );
        } else {
            printf( __( "This page is restricted. Please %s to view this page.", 'asdb-dashboard' ), wp_loginout( '', false ) );
        }

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    function post_listing( $post_type ) {
        global $wpdb, $userdata, $post;

        $userdata = get_userdata( $userdata->ID );
        $pagenum = isset( $_GET['pagenum'] ) ? intval( $_GET['pagenum'] ) : 1;

        if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == "del" ) {
            $this->delete_post();
        }

        if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'deleted' ) {
            echo '<div class="success">' . __( 'Post Deleted', 'asdb-dashboard' ) . '</div>';
        }

        $args = array(
            'author' => get_current_user_id(),
            'post_status' => array('draft', 'future', 'pending', 'publish'),
            'post_type' => $post_type,
            'posts_per_page' => 25 ,
            'paged' => $pagenum
        );

        $dashboard_query = new WP_Query( $args );
        $post_type_obj = get_post_type_object( $post_type );
        ?>

        <h2 class="page-head">
            <span class="colour"><?php printf( __( "%s's Dashboard", 'asdb-dashboard' ), $userdata->user_login ); ?></span>
        </h2>
            <div class="post_count"><?php printf( __( 'You have created <span>%d</span> %s', 'asdb-dashboard' ), $dashboard_query->found_posts, $post_type_obj->label ); ?></div>

        <?php do_action( 'asdb_dashboard_top', $userdata->ID, $post_type_obj ) ?>

        <?php if ( $dashboard_query->have_posts() ) { ?>
            <style>
            table.ads-table td {padding: 5px 10px;text-align: left;vertical-align: middle;background:#fafafa;border:2px solid #fff;}
            table.ads-table th {text-align: left;padding-left:6px;}
            </style>
            <table class="ads-table" cellpadding="0" cellspacing="0" style="width:100%">
                <thead>
                    <tr>
                        <th><?php _e( 'Featured Image', 'asdb-dashboard' ); ?></th>
                        <th><?php _e( 'Title', 'asdb-dashboard' ); ?></th>
                        <th><?php _e( 'Status', 'asdb-dashboard' ); ?></th>
                        <th><?php _e( 'Options', 'asdb-dashboard' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($dashboard_query->have_posts()) {
                        $dashboard_query->the_post();
                        ?>
				<tr>
					<td>
<?php  	     if ( has_post_thumbnail() ) { the_post_thumbnail( 'thumbnail' );
       			} else {
           		printf( '<img src="%1$s" style="width:90px;" class="attachment-thumbnail wp-post-image" alt="%2$s" title="%2$s" />', apply_filters( 'asdb_no_futured_image', plugins_url( '/img/thumb-small.png', __FILE__ ) ), __( 'No Image', 'asdb-dashboard' ) );
       			} ?>
					</td>
				<td>
                                <?php if ( in_array( $post->post_status, array('draft', 'future', 'pending') ) ) { ?>

                                    <?php the_title(); ?>

                                <?php } else { ?>

                                    <a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'asdb-dashboard' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php the_title(); ?></a>

                                <?php } ?>
				</td>
					<td><?php active_post_status( $post->post_status ) ?></td>
					<td>
						<?php $url = get_permalink( $post->ID );
						global $ads_options;
						$ads_edit = get_bloginfo('wpurl').'/?ADS_ACTION=EDIT&amp;ID='.$post->ID .'&amp;page_id='.$ads_options['ads_edit_page']; //$ads_edit = get_edit_post_link ();
						?>
						<a href="<?php echo wp_nonce_url( $ads_edit, 'asdb_edit' ); ?>"><?php _e( 'Edit', 'asdb-dashboard' ); ?></a>
						<a href="<?php echo wp_nonce_url( "?action=del&pid=" . $post->ID, 'asdb_del' ) ?>" onclick="return confirm('<?php _e( 'Are you sure to delete this post?', 'asdb-dashboard' ); ?>');"><span style="color: red;"><?php _e( 'Delete', 'asdb-dashboard' ); ?></span></a>
					</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php
                $pagination = paginate_links( array(
                    'base' => add_query_arg( 'pagenum', '%#%' ),
                    'format' => '',
                    'prev_text' => __( '&laquo;', 'asdb-dashboard' ),
                    'next_text' => __( '&raquo;', 'asdb-dashboard' ),
                    'total' => $dashboard_query->max_num_pages,
                    'current' => $pagenum
                        ) );

                if ( $pagination ) { echo $pagination;} ?>
            </div>

            <?php
        } else {
            printf( __( 'No %s found', 'asdb-dashboard' ), $post_type_obj->label );
            do_action( 'asdb_dashboard_nopost', $userdata->ID, $post_type_obj );
        }

        do_action( 'asdb_dashboard_bottom', $userdata->ID, $post_type_obj );
        ?>

        <?php
         wp_reset_query();
    }

     function delete_post() {
        global $userdata;

        $nonce = $_REQUEST['_wpnonce'];
        if ( !wp_verify_nonce( $nonce, 'asdb_del' ) ) {
            die( "mission impossible" );
        }

        $maybe_delete = get_post( $_REQUEST['pid'] );

        if ( ($maybe_delete->post_author == $userdata->ID) || current_user_can( 'delete_others_pages' ) ) {
            wp_delete_post( $_REQUEST['pid'] );

            $redirect = add_query_arg( array('msg' => 'deleted'), get_permalink() );
            wp_redirect( $redirect );
        } else {
            echo '<div class="error">' . __( 'You are not the post author.', 'asdb-dashboard' ) . '</div>';
        }
    }
}

$asdb_dashboard = new ASDB_Dashboard();