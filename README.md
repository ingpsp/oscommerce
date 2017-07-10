osCommerce 2.3.3 ING PSP v 1.0.7
--------------------------------

osCommerce module using the ING PSP

Pre-requisites to install the plug-ins: 
------------

- PHP v5.4 and above
- MySQL v5.4 and above

Installation
------------

*** If you have installed the old version, named ingkassacompleet, please first remove it! ***

Copy the ext, ingpsp and includes folder into your osCommerce catalog directory (don't overwrite, but add files)

Edit /checkout_process.php

Right after:
if (isset($payment_class->email_footer)) {
      $email_order .= $payment_class->email_footer . "\n\n";
    }
  }

Add:
  if ($payment_modules->selected_module == "ingpsp_banktransfer") {
    $payment_modules->after_process();
  }

So it will become:
    if (isset($payment_class->email_footer)) {
      $email_order .= $payment_class->email_footer . "\n\n";
    }
  }

  if ($payment_modules->selected_module == "ingpsp_banktransfer") {
    $payment_modules->after_process();
  }

This needs to be done to add the reference to the email in case of banktransfer.

Configuration
-------------
1. Create an account at the portal and get an API Key
2. In your osCommerce admin panel under Modules > Payment, install the "ING PSP" module
3. Fill out all of the configuration information:
	- Verify that the module is enabled.
	- Copy/Paste the API key you created in step 1 into the API Key field
	- Choose a status for New, Pending, Complete, Cancelled and Error orders (or leave the default values as defined).
	- Choose a sort order for displaying this payment option to visitors.  Lowest is displayed first.
4. Configure the webhook in https://portal.kassacompleet.nl to: https://www.example.com/ext/modules/payment/ingpsp/notify.php

Usage
-----
In your Admin control panel, you can see the orders made just as you could see for any other payment mode.  The status you selected in the configuration steps above will indicate whether the order has been paid for.

Tested and validated against osCommerce 2.3.3.4.