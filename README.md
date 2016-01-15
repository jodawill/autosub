# AutoSub
A WordPress plugin to automatically subscribe users to every BBPress topic and forum.

## Method
By default, BBPress stores subscriptions by adding every topic ID to a string in the WordPress database. If we were to stick with this method for automatically subscribing users to every topic, we would take a major performance hit as the forum grows larger. AutoSub avoids this problem by inverting the logic, storing only the topics a user is unsubscribed from.

## Deactivation
AutoSub creates a backup of every user's subscriptions upon activation. When the plugin is deactivated, it restores the subscriptions they had when the plugin was activated. For this reason, users will have to manually subscribe to their current topics if you deactivate the plugin.

## Exemptions
The autosub\_get\_exceptions() function stores an array of every user that you would like to be exempt from the inversion logic. In the future, I will make this an option in the user's configuration page.

## Theme
When a user clicks "Subscriptions" from their profile page, it will erroneously show the topics and forums they have unsubscribed from. You can fix this by adding the files in the 'theme' directory to your WordPress theme's directory. Note, however, that this will apply to all users, including those who are exempt from the inversion logic.
