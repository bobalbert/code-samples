/**
 * Created by balbert on 9/10/16.
 */

jQuery(document).ready(function( $ ) {
    jQuery(".cancelatrenewal").click( function( ) {
        var order_id = $(this).data("orderid");

        $(this).next('.spinner').addClass('is-active');

        $.post(
            ajaxurl,
            {
                'action': 'cancel_at_renewal',
                'order_id': order_id
            },
            function(response){
                //alert('The server responded: ' + response);

                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>Subscription Cancelled</strong></p>' +
                        '</div>' +
                        '</div>');

                    $("#cancelbuttons-" + order_id).html('Subscription Cancelled at Renewal');
                    $("#cancelbuttons-" + order_id).parent().prev('td').html('');
                } else {
                    //alert('The server responded: ' + response);

                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>' + response + '</strong></p>' +
                        '</div>' +
                        '</div>'
                    );

                }

            }
        );

    });


    jQuery(".terminateimmediately").click( function() {
        var order_id = $(this).data("orderid");
        var refund_type = $('input[name=refund_type-'+order_id+']:checked').val();

        //alert(order_id + ' ' + refund_type);

        $(this).next('.spinner').addClass('is-active');

        $.post(
            ajaxurl,
            {
                'action': 'terminate_immediately',
                'order_id': order_id,
                'refund_type': refund_type
            },
            function(response) {
                //alert('The server responded: ' + response);

                if (response == 'success') {
                    //alert('The server responded: ' + response);
                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>Subscription Cancelled</strong></p>' +
                        '</div>' +
                        '</div>');

                    $("#cancelbuttons-" + order_id).html('Subscription Terminated');
                    $("#cancelbuttons-" + order_id).parent().prev('td').html('');

                } else {
                    //alert('The server responded: ' + response);

                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>' + response + '</strong></p>' +
                        '</div>' +
                        '</div>'
                    );
                }
            }
        );

    });

    jQuery(".cancellegacy").click( function( ) {
        var order_id = $(this).data("orderid");

        $(this).next('.spinner').addClass('is-active');

        $.post(
            ajaxurl,
            {
                'action': 'cancel_legacy',
                'order_id': order_id
            },
            function(response){
                //alert('The server responded: ' + response);

                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>Subscription Cancelled</strong></p>' +
                        '</div>' +
                        '</div>');

                    $("#cancelbuttons-" + order_id).html('Subscription Cancelled');
                    $("#cancelbuttons-" + order_id).parent().prev('td').html('');
                } else {
                    //alert('The server responded: ' + response);

                    jQuery("#TB_ajaxContent").html('<div class="ui-widget" style="margin-top: 10px">' +
                        '<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">' +
                        '<p><strong>' + response + '</strong></p>' +
                        '</div>' +
                        '</div>'
                    );

                }

            }
        );

    });

    jQuery(".removeprint").click( function( ) {
        var pub_id = $(this).data("pubid");
        var user_id = $(this).data("userid");

        // debug
        // alert( pub_id + ' ' + user_id );
        $(this).next('.spinner').addClass('is-active');

        $.post(
            ajaxurl,
            {
                'action': 'remove_postal',
                'pub_id': pub_id,
                'user_id': user_id,
            },
            function(response){
                //alert('The server responded: ' + response);
                $(this).next('.spinner').removeClass('is-active');
                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    location.reload();
                } else {
                    alert('The server responded: ' + response);
                }

            }
        );

    });

    jQuery(".addprint").click( function( ) {
        var pub_id = $(this).data("pubid");
        var user_id = $(this).data("userid");

        // debug
        // alert( pub_id + ' ' + user_id );
        $(this).next('.spinner').addClass('is-active');

        $.post(
            ajaxurl,
            {
                'action': 'add_postal',
                'pub_id': pub_id,
                'user_id': user_id,
            },
            function(response){
                $(this).next('.spinner').removeClass('is-active');
                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    location.reload();
                } else {
                    alert('The server responded: ' + response);
                }

            }
        );

    });

    jQuery(".editexpiredate").click( function( ) {

        $(this).next('.spinner').addClass('is-active');
        var pub_id = $(this).data("pubid");
        var user_id = $(this).data("userid");
        var expire_date = $(this).prevAll('input').datepicker({ dateFormat: 'yy-mm-dd' }).val();

        // debug
        // alert( pub_id + ' ' + user_id + ' ' + expire_date );

        $.post(
            ajaxurl,
            {
                'action': 'edit_expire_date',
                'pub_id': pub_id,
                'user_id': user_id,
                'expire_date': expire_date
            },
            function(response){
                $(this).next('.spinner').removeClass('is-active');
                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    location.reload();
                } else {
                    alert('The server responded: ' + response);
                }

            }
        );

    });

    jQuery(".editnextrenewaldate").click( function( ) {

        $(this).next('.spinner').addClass('is-active');
        var order_id = $(this).data("orderid");
        var renewal_date = $(this).prevAll('input').datepicker({ dateFormat: 'yy-mm-dd' }).val();

        // debug
        // alert( pub_id + ' ' + user_id + ' ' + expire_date );

        $.post(
            ajaxurl,
            {
                'action': 'edit_renewal_date',
                'order_id': order_id,
                'renewal_date': renewal_date
            },
            function(response){
                $(this).next('.spinner').removeClass('is-active');
                if( response == 'success' ){
                    //alert('The server responded: ' + response);
                    location.reload();
                } else {
                    alert('The server responded: ' + response);
                }

            }
        );

    });

    jQuery('.expire_date_edit').datepicker({dateFormat: 'yy-mm-dd'});

    jQuery('#comp_expire_date').datepicker({dateFormat: 'yy-mm-dd'});

    jQuery('.renewal_date_edit').datepicker({dateFormat: 'yy-mm-dd'});

});
