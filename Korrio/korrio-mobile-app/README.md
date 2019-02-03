Korrio Mobile App
============

Samples of Korrio's companion mobile app to the web platform application. This is the js code that builds the help screen. Pulls custom post data for each help type from our corporate wp site and renders them in sections. Each click/view of a faq gets logged to track issues people might be having.

This is a Cordova app for both ios and android built using jQuery, jQuery Mobile and Handlebars.js with help from underscore.js

Cordova plugins:
- cordova.console
- cordova.inappbrowser
- cordova.contacts
- cordova.statusbar
- cordova.splashscreen
- cordova.geolocation
- cordova.camera

WordPress Plugins:
- Custom REST API plugin based on [Tonic](http://www.peej.co.uk/tonic/)
- [JSON API](https://wordpress.org/plugins/json-api/)