<?php
// Copy this file to config.php and fill in your database credentials.
// On HostPinnacle cPanel these come from "MySQL Databases" (prefixed
// names like cpaneluser_tukutane).
//
// Even better: put the constants in nipange-config.php one level above
// public_html so credentials sit outside the document root — config.php
// loads it automatically.

const DB_HOST = 'localhost';
const DB_NAME = 'your_db_name';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';

// Optional: "Continue with Google" sign-in.
// Create an OAuth 2.0 "Web application" client in Google Cloud Console and
// register the redirect URI  https://your-domain/oauth-google.php
// When these are defined, the button shows on the login/signup page.
const GOOGLE_CLIENT_ID     = 'your-client-id.apps.googleusercontent.com';
const GOOGLE_CLIENT_SECRET = 'your-client-secret';
// Mobile-app Google client IDs (Android + iOS) for the JSON API, comma-separated.
const GOOGLE_APP_CLIENT_IDS = '';
