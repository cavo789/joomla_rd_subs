# RD-Subscriptions

Scripts for the [RD-Subs subscription management component for Joomla!](https://rd-media.org/joomla-subscriptions-management.html).

## Before using it

This script will migrate records (i.e. subscriptions) from CBSubs to RD-Subs. You need first, manually, create your products in RD-Subs.

So, let's take an example :

* you wish to move your current records for your plan "Premium" (which has the `ID 8` in CBSubs)
* you need to create a new product in RD-Subs (let's say "Premium" too which has the `ID 1` in RD-Subs)
* Then you can use the script

The script won't create any product, will just move subscriptions from `CBSubs plan #8` to `RD-Subs product #1`.

## Use it

Just copy the script in the root folder of your Joomla website. Use your FTP client to do this.

1. Get a raw version of the script : click on the raw button or go to this URL : https://raw.githubusercontent.com/cavo789/rd-subs/master/Migrate_from_cbsubs.php
2. On your computer, start a text editor like Notepad or Notepad++ and copy/paste there the code
3. Save the file (if you're using Notepad++, check in the Encoding menu that you've selected UTF8 NoBom as encoding)
4. Put the saved file in your Joomla's root folder

## Run it
Start a browser and run the file i.e go to f.i. http://site/Migrate_from_cbsubs.php.	A form will be displayed, follow the instructions.

## Remarks
Be sure to check the code and verify if the logic match your needs.  I've developed the script for myself, if it can help other, it's nice. I've not covered every aspect of such migration, for sure.

## Images
<img src="https://github.com/cavo789/rd-subs/blob/master/images/result.png" />

## Credits

Christophe Avonture | [https://www.aesecure.com](https://www.aesecure.com)
