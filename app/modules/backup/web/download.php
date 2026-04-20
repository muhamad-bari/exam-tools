<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request();

header('Location: index.php?api=db_backup&action=download_full');
exit;
