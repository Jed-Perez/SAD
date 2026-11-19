# Gmail password-reset email on Hostinger

1. Sign in to the Gmail account that will send TechTrack reset links.
2. Enable Google 2-Step Verification: https://myaccount.google.com/security
3. Create an App Password named TechTrack: https://myaccount.google.com/apppasswords
   Google requires 2-Step Verification. Some managed accounts and Advanced Protection accounts do not offer App Passwords.
4. Upload the included `app/Auth/recovery.php` and entire `app/Mail` directory into Hostinger's `public_html`, keeping their folder structure.
5. Edit `public_html/config.local.php`. Add/update the entries below **inside its existing return array**. If it does not exist, create it with `<?php return [` followed by these entries and `];`.

```php
'app_url' => 'https://ncpcs.shop',
'mail_from' => 'YOUR_ACCOUNT@gmail.com',
'mail_transport' => 'smtp',
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => 465,
'smtp_encryption' => 'ssl',
'smtp_username' => 'YOUR_ACCOUNT@gmail.com',
'smtp_password' => 'YOUR_GOOGLE_APP_PASSWORD',
```

Use your actual Gmail address in both email fields. Paste the App Password without the display spaces. Do not use your normal Google password. Preserve your database and AI configuration; do not replace the entire file or copy the local database settings from config.example.php. Keep config.local.php out of Git and never share its secrets in chat.

For STARTTLS instead, use port 587 and `smtp_encryption` set to `tls`. Certificate verification stays enabled. The app does not silently fall back to PHP mail() after SMTP failure.

6. Visit Forgot password, enter an active TechTrack account's real email address, and check that inbox and spam folder. The recipient is the address registered to that TechTrack account, which can differ from the sender Gmail account.
7. Open the newest reset link within 30 minutes and choose a new password. Links work once. Wait at least one minute before requesting another.

The confirmation message deliberately does not reveal whether an account exists and is not proof of email delivery. If delivery fails, check Hostinger's `storage/php-error.log` for the generic email-delivery message, then check the App Password, sender address, SMTP settings and outbound connection. Never enable SMTP debug output on the public website. Changing your Google account password revokes its App Passwords.

This SMTP configuration applies to password-reset emails. Scheduled notification delivery remains unchanged.

The SMTP library is bundled, so Hostinger does not need Composer. The source version and hashes are recorded in `app/Mail/PHPMailer/UPSTREAM.json`.

References: https://support.google.com/accounts/answer/185833 and https://developers.google.com/workspace/gmail/imap/imap-smtp
