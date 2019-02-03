Mequoda THINK framework
=====================
When hired by Swift Communications, Inc. to create [Countryside Network](https://countrysidenetwork.com/) site to take over the websites for their magazines Countryside & Small Stock Journal, Backyard Poultry, Goat Journal, and sheep! one of the tasks was to integrate with their new fulfilment system THINK Subscription.

I created the this plugin to integrate the current site order process to with THINK. This order process was a bit tricky as the custom order pages on the site interacted with SFG for credit card processing and then those orders needed to be synced with THINK Subscription for reporting and fulfilment. 

It kind of acts as the customer/subscription manager between the three systems... SFG, the website itself and THINK. Processing renewals in SFG to THINK and cancels and new orders in THINK to the site as well as cancels and new orders from the site to SFG and THINK.

The interaction from THINK is handled by a custom REST API that was created to accept and process THINK Subscription Event posts. This API processes changes in THINK that are posted to the site. These include new manually orders created in THINK subscription tools, along with cancels and deposit credits.

This was one of my first projects for Mequoda and I had never worked with SOAP or created a SOAP REST plugin. Given that no one on the team had any experience with this, I had to figure this out on my own. THINK support helped but they are all .Net based and coudln't really guide me on the PHP site. 

Not only does this have a client component, using PHP SOAP client to communicate to THINK, but a PHP SOAP Server was also created to handle communication from THINK. I also did a small bit of custom WSDL editing.