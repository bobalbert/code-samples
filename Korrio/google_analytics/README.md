korrio-google-analytics.php
===================

We updated from classic analytics to Universal analyitcs but hadn't updated our application implementation. For our latest release we wanted to start using some Custom Dimensions thus it was time to update to the new version.

I created this mu-plugin for our site that updates the version of the js, and all our calls to the new Universal syntax. Further, we added custom dimensions so we can start so get a better idea who is using what features.

mobile-analytics.js
===================

This is the corresponding sample from our cordova mobile app. We were already using univeral analytics on our mobile app, but I added in the custom dimensions to be in sync.

This shows the helper funciton, doTrackLink( type, info, obj, event_type ), that is called on page/screen view and events. This function makes it easy to call generically and just pass in the type of tracking.

Example, for a page view the call is:
````
doTrackLink( 'pageview', 'help', obj);
````

Examples for an event:
````
doTrackLink( 'event', 'help-fanshelp', obj, 'link' );
doTrackLink( 'event', 'message-inbox', obj, 'button' );
````
