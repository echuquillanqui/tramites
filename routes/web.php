<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome', ['trackAnalytics' => ! auth()->user()?->is_admin]);
});

Route::post('/analytics/events', [AnalyticsController::class, 'event'])->middleware('throttle:120,1')->name('analytics.event');
Route::post('/analytics/satisfaction', [AnalyticsController::class, 'satisfaction'])->middleware('throttle:30,1')->name('analytics.satisfaction');

Auth::routes(['register' => false]);

Route::get('/admin/login', fn () => redirect()->route('login'))->name('admin.login');
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'data'])->middleware('throttle:60,1')->name('analytics');
    Route::get('/export.csv', [AnalyticsController::class, 'export'])->defaults('report', 'events')->middleware('throttle:10,1')->name('export');
    Route::get('/export/{report}.csv', [AnalyticsController::class, 'export'])->whereIn('report', ['summary', 'cases'])->middleware('throttle:10,1')->name('export.report');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
