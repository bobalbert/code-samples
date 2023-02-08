Email Exchange Compare Tool
=====================
Mequoda has recently started to try a new marketing and potential revenue stream by doing email exchanges with 3rd party companies with large email lists like AARP. The arrangements are either a straight exchange of marketing... we'll send your email to our list and in exchange you send ours to yours. Or sometimes this is a bounty for those that signup to the 3rd party offer sent to the Mequoda user base.

Either way, emails still need to be suppressed based on user's mailto settings in the 3rd party site. The 3rd party site provides a suppression list of md5 hashed emails that needs to be compared to the emails in Mequoda's list to create a suppression list for mailing to Mequoda's list. 

I was asked to create an internal only solution and/or tool that could compare the very large 3rd party lists with large site specific lists. Folks were trying to do this in Excel, but the files were too large thus it wouldn't handle them. The first few 3rd party lists were some 63 and 72 million lines comparing against Mequoda sites lists between 800K to 1 million lines.

I tried a few solutions that worked for me on my local machine but these were too technical for staff to set up, thus I was asked to create something on an internal server that folks could use by simply uploading files via SFTP.

The tool takes two files, one from the 3rd party which is a list of md5 hashed emails, and another list from a Mequoda site export that is a csv of email address to md5 version. The script reads each file into arrays and then uses array_intersect() to get the matches and then outputs a new csv file with the matching email addresses to be suppressed on the Mequoda side.

A cron runs every 5 minutes to check if things should be processed by checking a simple DB value that is set by a very simple front end web page that Mequoda staff can use to "queue" the tool to run.