Simple Contact Form For Bolt CMS
================================

This is a very simple contact form for the Bolt CMS - [link](https://bolt.cm/)

## Usage

1. Review your SMTP settings in the main config.yml file
2. Install the extension "salidasoftware/boltcontact"
3. Add the text "[contact]" anywhere you'd like the contact form to appear.

By default, messages are sent to the first admin user's email address.  You can customize this by seting a "to" array in the boltcontact.salidasoftware.yml file (found in the extensions folder).  For example

    to: ["sponge@bob.sqarepants", "patrick@st.ar"]

The contact form includes a honeypot field to reduce spam submissions.
