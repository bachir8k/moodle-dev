<?php
for ($i = 0; $i < 100; $i++) {
    if (file_exists('/var/www/html/testfile_' . $i . '.txt')) {
        unlink('/var/www/html/testfile_' . $i . '.txt');
    }
}
if (file_exists('/var/www/html/create_fs_files.php')) {
    unlink('/var/www/html/create_fs_files.php');
}
if (file_exists('/var/www/html/fs_test.php')) {
    unlink('/var/www/html/fs_test.php');
}
if (file_exists('/var/www/html/db_test.php')) {
    unlink('/var/www/html/db_test.php');
}
echo "Cleanup complete.";
