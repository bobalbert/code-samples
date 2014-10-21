tz_locale_migration
===================

Our application at work is very dependent on users having their timezone set. We store all our event times in UTC/GMT and thus we need the user's timezone to properly display it for them so it makes sense. Turns out we missed some spots where we weren't setting timezone plus we had a fair amount of early accounts that never had it set cuz timezone came later.

So most all of our accounts have an address so i figured we could look up the timezone based on the address. I first looked at Google's geocode api (see commented code) and we could use that to submit and get the timezone based on address but they only allow 25k requests per day for free. I had to convert some ~424K+ accounts so i could do a few every day for several days but I thought not.

Next I thought well i already know the address and based on state you can figure out the basic timezone. I know time is hard in programing cuz of all the strange geography across the us, but i figured i could covered 90% by just doing the basics.

This script uses a simple array that maps state to timezone to do a conversion of address to timezone and set it for users that don't have the xprofile_field set in the database.
