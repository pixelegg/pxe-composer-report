<?php

declare(strict_types=1);

use Pixelegg\PxeComposerReport\Hub\ComposerReportPanelProvider;
use Pixelegg\PxeHub\Panel\PanelProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    // Only register the hub panel when pxe_hub (and thus its interface) is installed.
    // interface_exists() does NOT autoload the provider class, so this stays safe when pxe_hub is absent.
    if (!interface_exists(PanelProvider::class)) {
        return;
    }

    $configurator->services()
        ->set(ComposerReportPanelProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('pxe.hub.panel');
};
