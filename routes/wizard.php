<?php

use Biteslot\Connector\Http\Controllers\SetupWizardController;
use Illuminate\Support\Facades\Route;

/*
| Setup-wizard routes.
|
| Mounted under config('biteslot-connector.wizard.prefix') with the configured
| middleware. The default middleware is just ['web']; a merchant should add their
| own auth/authorization (e.g. ['web','auth','can:manage-biteslot']) so only an
| admin can reach the mapping screens.
*/

$config = config('biteslot-connector.wizard', []);

Route::prefix($config['prefix'] ?? 'biteslot/setup')
    ->middleware((array) ($config['middleware'] ?? ['web']))
    ->group(function () {
        // Pages
        Route::get('/', [SetupWizardController::class, 'index'])->name('biteslot.setup');
        Route::get('/step-1', [SetupWizardController::class, 'step1'])->name('biteslot.setup.step1');
        Route::get('/step-2', [SetupWizardController::class, 'step2'])->name('biteslot.setup.step2');
        Route::get('/step-3', [SetupWizardController::class, 'step3'])->name('biteslot.setup.step3');

        // JSON endpoints used by the wizard UI
        Route::get('/api/tables', [SetupWizardController::class, 'tables'])->name('biteslot.setup.tables');
        Route::get('/api/columns', [SetupWizardController::class, 'columns'])->name('biteslot.setup.columns');
        Route::post('/api/source', [SetupWizardController::class, 'saveSource'])->name('biteslot.setup.source');
        Route::post('/api/sync-catalog', [SetupWizardController::class, 'syncCatalog'])->name('biteslot.setup.sync');
        Route::get('/api/pos-items', [SetupWizardController::class, 'posItems'])->name('biteslot.setup.pos-items');
        Route::get('/api/products', [SetupWizardController::class, 'products'])->name('biteslot.setup.products');
        Route::post('/api/map', [SetupWizardController::class, 'map'])->name('biteslot.setup.map');
        Route::post('/api/auto-map', [SetupWizardController::class, 'autoMap'])->name('biteslot.setup.auto-map');
        Route::get('/api/summary', [SetupWizardController::class, 'summary'])->name('biteslot.setup.summary');
    });
