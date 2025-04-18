<?php
session_start();
echo "Reached logout.php"; // Add this for debugging
session_unset();
session_destroy();
header("Location: login.php");
exit();
