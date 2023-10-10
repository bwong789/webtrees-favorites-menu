Favorites menu module for webtrees
==================================

Description
------------
This exposes the Webtrees Favorites list as a menu item. It is displayed on all non-admin pages. 
The submenu provides a list of actions and favorites in the current group. The group name is
stored in the Favorites Note field. The default group is always available and it has any 
empty group name. 

Favorites currently supported include individuals, families, media and URLs. The submenu provides
a way to add, move or remove a favorite when viewing on of these pages. 

The Favorites menu management page allows groups to be renamed and to select the current group. 
This allows multiple group lists so the number of entries in a group can be more managable. 
It is possible to merge two groups by providing them with the same name. The current group may be empty.

This menu item is displayed on entries that can be 
marked as favorites including individuals, families and media. It is possible to toggle the status from 
the menu. The module uses the Note section to hold the group name. 

The favorites list is per user and per tree.

Installation & upgrading
------------------------
Download and unpack the zip file and place the folder favorites-menu in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default. Go to the control panel, click in the module section on 'Menus' where you can find the newly added menu item. You can move it up or down to change the order. Click on the tools icon next to the title of the newly added menu item. This will open the settings page where you can set a menu title and add the page title and text.

-------------------------
This is a simple module and provided as is. However, if you experience any bugs you can create a new issue on GitHub.


To Do List
----------
Support for the remaining favorites will be added in the future. 
