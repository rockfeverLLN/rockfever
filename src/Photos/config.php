<?php
return [
    'admin.widgets' => \DI\add([
        \DI\get(\App\Photos\PhotosWidget::class)
    ])
];
