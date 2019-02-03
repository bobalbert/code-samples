Mequoda Haven Recurly
=====================
Mequoda wanted to integrate with [Recurly](https://recurly.com/) for several of their clients - [Cabot Wealth Network](https://cabotwealth.com/) and [TSI Wealth Network](https://www.tsinetwork.ca/)

This plugin integrates the site's custom order process with Recurly as the credit card processor and subscription management system. 

[Recurly's API](https://github.com/recurly/recurly-client-php) was integrated to create new subscription purchases along with custom customer service tools in WordPress admin to manage subscriptions. 

Customer service tools allow CS agent to create, edit and delete Recurly Plans which define subscription price and term.  Further CS agents can fully administer subscriptions to create, cancel, refund and edit existing subscriptions keeping them in sync between the two sites. Some of the customer service tools are integrated in the [Haven Order Manager](https://bitbucket.org/balbert/code_samples/src/master/Mequoda/haven-order-manager/) plugin.

A custom REST API was created to receive and process Web Hook posts from Recurly mainly to manage auto renewals including dunning series along with other changes happening in Recurly to keep subscriptions in sync.

Custom conversion scripts were also created to facilitate large user credit card conversion from CyberSource to Recurly.