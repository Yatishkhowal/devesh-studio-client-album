<?php
/** Logs the client out and goes back to the login page. */
require_once __DIR__ . '/album-core/bootstrap.php';

album_session_start();
logout_client();
session_regenerate_id(true);
redirect('client-login.php?bye=1');
