<?php

return [
    'blog.prefix' => '/blog',
    'admin.widgets'=> \DI\add([
        \DI\get(\App\Blog\BlogWidget::class)
    ])
];
