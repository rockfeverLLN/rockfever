<?php

namespace App\Photos;

use App\Photos\Actions\DeletionRequestAction;
use App\Photos\Actions\DeletionRequestDismissAction;
use App\Photos\Actions\PhotoAdminAction;
use App\Photos\Actions\PhotoDeleteAction;
use App\Photos\Actions\PhotoFileAction;
use App\Photos\Actions\PhotoIndexAction;
use App\Photos\Actions\PhotoPasswordAction;
use App\Photos\Actions\PhotoUploadAction;
use Framework\Module;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Container\ContainerInterface;

class PhotosModule extends Module
{
    const DEFINITIONS = __DIR__ . '/config.php';

    const MIGRATIONS = __DIR__ . '/db/migrations';

    public function __construct(ContainerInterface $container)
    {
        $container->get(RendererInterface::class)->addPath('photos', __DIR__ . '/views');
        /** @var Router $router */
        $router = $container->get(Router::class);

        // The gallery, behind its password (PhotoAccess)
        $router->get('/photos', PhotoIndexAction::class, 'photos');
        $router->post('/photos', PhotoIndexAction::class);
        $router->get('/photos/{id:\d+}/{size:480|960|1600|full}', PhotoFileAction::class, 'photos.file');
        $router->post('/photos/{id:\d+}/suppression', DeletionRequestAction::class, 'photos.request');

        if ($container->has('admin.prefix')) {
            $adminPrefix = $container->get('admin.prefix');

            $router->get($adminPrefix . '/photos', PhotoAdminAction::class, 'admin.photos.index');
            $router->post($adminPrefix . '/photos/upload', PhotoUploadAction::class, 'admin.photos.upload');
            $router->post($adminPrefix . '/photos/delete', PhotoDeleteAction::class, 'admin.photos.delete');
            $router->post($adminPrefix . '/photos/password', PhotoPasswordAction::class, 'admin.photos.password');
            $router->post(
                $adminPrefix . '/photos/requests/{id:\d+}/dismiss',
                DeletionRequestDismissAction::class,
                'admin.photos.request.dismiss'
            );
        }
    }
}
