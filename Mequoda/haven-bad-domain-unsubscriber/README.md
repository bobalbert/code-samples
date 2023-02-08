Haven Bad Domain Unsubscriber
=====================
One of Mequoda's clients wanted to remove users with badly entered emails when signing up from being marketed/emailed to, thus cutting down on bounces and bad email delivery in general. Most of these emails are potentially typos like: 029gmail.com, gimal.com, gmai.com etc. Others are known "spamy" domains.

The tool allows for a list of domains to be uploaded. I cron runs once a day early morning EST and looks at all the users that have registered in the last day and compares their email to the domains in the list. If a match is found, those users are unsubscribed from Mequoda's marketing email provider, WhatCounts, so that daily and weekly emails will not be sent to them.

At this point, they wanted very simple functionality so editing of the bad domain list, specifically deleting one off the list has to be done manually in the DB. Otherwise, additions are just done by uploaded a new list. Also, one can download the current list to review for offline editing.