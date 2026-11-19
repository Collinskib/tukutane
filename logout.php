<?php
require_once __DIR__ . '/includes/bootstrap.php';
logout();
flash('Logged out. See you soon!');
redirect('index.php');
