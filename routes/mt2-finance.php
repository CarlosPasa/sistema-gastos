<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FinanceController;
// Requiere autenticación activa. No expone información financiera en modo demostración.
Route::middleware(['auth', 'active.user'])->prefix('mt2')->name('mt2.finance.')->group(function(){
  Route::get('/reportes',[FinanceController::class,'report'])->name('report');
  Route::get('/{modulo}',[FinanceController::class,'index'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->name('index');
  Route::get('/{modulo}/crear',[FinanceController::class,'create'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->name('create');
  Route::post('/{modulo}',[FinanceController::class,'store'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->name('store');
  Route::get('/{modulo}/{id}/editar',[FinanceController::class,'edit'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->whereNumber('id')->name('edit');
  Route::put('/{modulo}/{id}',[FinanceController::class,'update'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->whereNumber('id')->name('update');
  Route::delete('/{modulo}/{id}',[FinanceController::class,'destroy'])->whereIn('modulo',['categorias','gastos','ingresos','presupuestos'])->whereNumber('id')->name('destroy');
  Route::patch('/{modulo}/{id}/activar', [FinanceController::class, 'activate'])
    ->whereIn('modulo', ['categorias', 'gastos', 'ingresos', 'presupuestos'])
    ->whereNumber('id')
    ->name('activate');
});
