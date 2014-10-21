<?php
/**
 * korrio-google-analytics.php
 *
 * Description: Adds Google Analytics Universal Tracking code
 * Created by User: Bob Albert, Date: 10/3/14
 *
 */

/******************************************************************************
 * Google recommends that the async analytics loader be the last
 * thing present in the <head> of the document.
 * Otherwise, this would be included similarly to all other javascript.
 ******************************************************************************/
function korrio_theme_print_google_analytics()
{
    if (!is_admin()) {

        global $bp;

        // Get customer's GA id if set
        $group_id = $bp->groups->current_group->id;
        $ga_id = ( korrio_groups_get_groupmeta($group_id, 'ga_id') ? korrio_groups_get_groupmeta($group_id, 'ga_id') : false );

        // add in club specific GA code if present.
        if ( $ga_id ){
            $other_group_ga_id  = "ga('create', '{$ga_id}', {'name': 'kclub'}); ga('kclub.send', 'pageview');\n";
        }

        ?>
        <!-- Google Analytics Code -->
        <script>
            (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
            })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

            ga('create', 'UA-15944218-1', {
                'cookieDomain': 'korrio.com'
            });

            <?php
            if( korrio_is_logged_in() ) {

                // get bunch of data about loggedin user to track ;-)
                $userdata = korrio_get_all_profile_field_data( $bp->loggedin_user->id );
                $userdata['age'] = korrio_get_age( $userdata['date_of_birth'] );
                $user_role_dimension = korrio_get_ga_userroles( $bp->loggedin_user->id );

                // set loggedin user's gender
                if( !empty( $userdata['gender'] ) ) {
                    echo "ga('set', 'dimension1', '" . $userdata['gender'] . "');";
                }else{
                    echo "ga('set', 'dimension1', 'na');";
                }

                // set loggedin user's age
                if( !empty( $userdata['age'] ) ){
                    echo "ga('set', 'dimension2', '" . $userdata['age'] . "');";
                } else {
                    echo "ga('set', 'dimension2', 'na');";
                }

                // set various korrio roles of user
                echo "ga('set', 'dimension3', '" . $user_role_dimension . "');";

             } ?>

            ga('send', 'pageview');

            <?php echo $other_group_ga_id; ?>

            // debuging
            // ga('send', 'pageview', '/bob-new-ga-universal');

        </script>
        <!-- end Google Analytics Code -->

        <?php
    }
}
add_action('wp_head', 'korrio_theme_print_google_analytics', 999);

function korrio_get_ga_userroles ( $user_id ) {

    $the_roles = array('admin', 'coach', 'assistantcoach', 'manager', 'other');

// check to see what type of role the user is
    foreach ( $the_roles as $role ){
        if( korrio_is_type( $role, $user_id ) ){
            $user_roles[] = $role;
        }
    }
    if( korrio_has_kids( $user_id ) ){
        $user_roles[] = 'parent-';
    }
    $repo = new Korrio_UR_Fans_Repository();
    if( $repo->get_users_following_and_info( $user_id ) ){
        $user_roles[] = 'fan';
    }

    // write out/set the "role" ga dimension3
    if( is_array( $user_roles ) ) {
        foreach ( $user_roles as $role ){
            $user_role_dimension .= $role . '-';
        }
    } else {
        $user_role_dimension = 'child';
    }
    $user_role_dimension = rtrim( $user_role_dimension, "-" );

    return $user_role_dimension;
}
