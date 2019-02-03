/**
 * GA and TestFlight Tracking
 *
 */
function doTrackLink( type, info, obj, event_type ) {

    var userdata = obj.serializeMeta(obj.data.user);
	
    var user_id     = obj.data.user_id;
    var active_page = $.mobile.activePage[0].id;
	
	// debug
    // console.log('*****  TRACKING  *****');
    // console.log('user_id');
    // console.log(user_id);
    // console.log('active_page');
    // console.log(active_page);
    // console.log("type: "+ type);

    // call dimensions for everyone:
    ga('set', 'dimension1', userdata.gender);
    ga('set', 'dimension2', userdata.age);
    ga('set', 'dimension3', userdata.role);
    
    switch ( type ){
        case 'pageview':
            ga('send', 'pageview', {'page': '/' + info +'/' + user_id});
        break;

        case 'event':
            if(event_type == 'button'){
                ga('send', 'event', 'button', 'click', info + '/' + user_id );
            }
            else if(event_type == 'link'){
                ga('send', 'event', 'link', 'click', info + '/' + active_page + '/' + user_id );
            }
            else if(event_type == 'swipe'){
                // console.log("event: 'send', 'event', 'swipe', 'click', " + info + '/' + active_page + '/' + user_id);
                ga('send', 'event', 'swipe', 'click', info + '/' + active_page + '/' + user_id );
            }
        break;
    }
    
    // out of beta, no more testflight for now.
    // todo - look at new Apple version now that they bought them ;-)
    /* // console.log('TF: '+ info);*/
    /* TF.passCheckpoint(function(){
        // console.log('Pass Checkpoint Success');
    }, function(){
        // console.log('Pass Checkpoint Fail');
    }, info);
    // console.log('*****  TRACKING END  *****');*/
}
