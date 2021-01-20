

# Biploma Moodle Activity Plugin

## Overview

The Biploma platform enables organizations to create, manage and distribute digital credentials as digital certificates.

This plugin enables you to issue dynamic, digital certificates on your Moodle instance. They act as a replacement for the PDF certificates normally generated for your courses.

## Compatability

This plugin has been tested and is working on Moodle 2.7+ and Moodle 3.1+.

## Plugin Installation

Log into your Moodle site as an administrator.

1. Visit https://github.com/quickom-foundation/moodle-mod_biploma and download the zip.
2. Go to "Install plugin" via Site administration > Plugins > Install plugins
3. Click "Install plugin from the Zip file"
4. Follow the next steps to complete the installation.

#### Get your API key

Make sure you have your API key from Biploma. 
It's available from the API Key Management page on our dashboard: 
[https://biploma.com/dashboard/corporate-profile](https://biploma.com/dashboard/corporate-profile).

#### Continue Moodle set up

Start by installing the new plugin (go to Site Administration > Notifications if your Moodle doesn't ask you to install automatically).

After clicking 'Upgrade Moodle database now', this is when you'll enter your API key from Biploma.

## Creating a Certificate

#### Add an Activity

Go to the course you want to issue certificates for and add an Biploma activity.

Issuing a certificate is easy - choose from 3 issuing options:

- Pick student names and manually issue credentials. Only students that don't already have a credential will show a checkbox.
- Choose the Quiz Activity that represents the **final exam**, and set a minimum grade requirement. Certificates will get issued as soon as the student receives a grade above the threshold.
- Choose for a student to receive their certificate when they complete the course if you've setup completion tracking.

_Note: if you set both types of auto-issue criteria, completing either will issue a certificate._

_Note: Make sure you don't allow students to set their completion of the Biploma activity or they'll be able to issue their own certificates._

Once you've added the activity to your course we'll auto-create a Group on your Biploma account where these credentials will belong. 

Then select a certificate design to be able to send out credentails in this group.

From now on new certificates will be automatically sent to recipients based upon the criteria you chose.

You are able to add, edit and remove your certificates at any time through the platform.

**Contact us at contact@beowulfchain.com if you have issues or ideas on how we can make this integration better.**

### Bug reports

If you discover any bugs, feel free to create an issue on GitHub. Please add as much information as possible to help us fixing the possible bug. We also encourage you to help even more by forking and sending us a pull request.

https://github.com/quickom-foundation/moodle-mod_biploma/issues

