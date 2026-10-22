<?php

namespace App\Photos;

use App\Photos\Actions\AlbumAdminAction;
use App\Photos\Actions\AlbumCreateAction;
use App\Photos\Actions\AlbumDeleteAction;
use App\Photos\Actions\AlbumShowAction;
use App\Photos\Actions\AlbumZipAction;
use App\Photos\Actions\DeletionRequestAction;
use App\Photos\Actions\DeletionRequestDismissAction;
use App\Photos\Actions\PhotoAdminAction;
use App\Photos\Actions\PhotoDeleteAction;
use App\Photos\Actions\PhotoFileAction;
use App\Photos\Actions\PhotoIndexAction;
use App\Photos\Actions\PhotoUploadAction;
use Framework\Module;
use Framework\Renderer\RendererInterface;
use Framework\Router;
use Psr\Container\ContainerInterface;

class PhotosModule extends Module
{
    const DEFINITIONS = __DIR__ . '/config.php';

    const MIGRATIONS = __DIR__ . '/db/migrations';

    const SEEDS = __DIR__ . '/db/seeds';

    public function __construct(ContainerInterface $container)
    {
        $container->get(RendererInterface::class)->addPath('photos', __DIR__ . '/views');
        /** @var Router $router */
        $router = $container->get(Router::class);

        // The gallery's albums and photos, each behind its soirée's password (PhotoAccess)
        $router->get('/photos', PhotoIndexAction::class, 'photos');
        $router->get('/photos/{id:\d+}/{size:480|960|1600|full}', PhotoFileAction::class, 'photos.file');
        $router->post('/photos/{id:\d+}/suppression', DeletionRequestAction::class, 'photos.request');
        $router->get('/photos/{slug:[a-z0-9-]+}', AlbumShowAction::class, 'photos.album');
        $router->post('/photos/{slug:[a-z0-9-]+}', AlbumShowAction::class);
        $router->get('/photos/{slug:[a-z0-9-]+}/zip', AlbumZipAction::class, 'photos.album.zip');

        if ($container->has('admin.prefix')) {
            $adminPrefix = $container->get('admin.prefix');

            $router->get($adminPrefix . '/photos', PhotoAdminAction::class, 'admin.photos.index');
            $router->post($adminPrefix . '/photos/albums/new', AlbumCreateAction::class, 'admin.photos.album.create');
            $router->get($adminPrefix . '/photos/albums/{id:\d+}', AlbumAdminAction::class, 'admin.photos.album');
            $router->post($adminPrefix . '/photos/albums/{id:\d+}', AlbumAdminAction::class);
            $router->post(
                $adminPrefix . '/photos/albums/{id:\d+}/delete',
                AlbumDeleteAction::class,
                'admin.photos.album.delete'
            );
            $router->post(
                $adminPrefix . '/photos/albums/{id:\d+}/upload',
                PhotoUploadAction::class,
                'admin.photos.upload'
            );
            $router->post($adminPrefix . '/photos/delete', PhotoDeleteAction::class, 'admin.photos.delete');
            $router->post(
                $adminPrefix . '/photos/requests/{id:\d+}/dismiss',
                DeletionRequestDismissAction::class,
                'admin.photos.request.dismiss'
            );
        }
    }
}
