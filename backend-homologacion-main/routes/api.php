<?php

use App\Http\Controllers\Api\Admin\CareerController;
use App\Http\Controllers\Api\Admin\CoordinatorCareerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ResolutionController;
use App\Http\Controllers\Api\Admin\SolicitudController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Coordinator\AnalysisResultController as CoordinatorAnalysisResultController;
use App\Http\Controllers\Api\Coordinator\CatalogController as CoordinatorCatalogController;
use App\Http\Controllers\Api\Coordinator\ComparisonController as CoordinatorComparisonController;
use App\Http\Controllers\Api\Coordinator\CurriculumController as CoordinatorCurriculumController;
use App\Http\Controllers\Api\Coordinator\DocumentController as CoordinatorDocumentController;
use App\Http\Controllers\Api\Coordinator\ReportController as CoordinatorReportController;
use App\Http\Controllers\Api\Coordinator\ResolutionController as CoordinatorResolutionController;
use App\Http\Controllers\Api\Coordinator\SolicitudController as CoordinatorSolicitudController;
use App\Http\Controllers\Api\Coordinator\SolicitudStateController as CoordinatorSolicitudStateController;
use App\Http\Controllers\Api\Coordinator\StudentController as CoordinatorStudentController;
use App\Http\Controllers\Api\Coordinator\SubjectController as CoordinatorSubjectController;
use App\Http\Controllers\Api\Coordinator\SyllabusTopicController as CoordinatorSyllabusTopicController;
use App\Http\Controllers\Api\Coordinator\TechnicalReportController as CoordinatorTechnicalReportController;
use App\Http\Controllers\Api\Student\AcademicBackgroundController;
use App\Http\Controllers\Api\Student\CatalogController;
use App\Http\Controllers\Api\Student\DocumentController;
use App\Http\Controllers\Api\Student\NotificationController;
use App\Http\Controllers\Api\Student\ProfileController;
use App\Http\Controllers\Api\Student\ResolutionController as StudentResolutionController;
use App\Http\Controllers\Api\Student\SolicitudController as StudentSolicitudController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API backend operativa',
        'data' => [
            'service' => 'backend-homologacion',
            'status' => 'ok',
        ],
    ]);
});

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/roles', [AuthController::class, 'roles'])->middleware('role:administrador');

        Route::prefix('student')->name('student.')->middleware('role:estudiante')->group(function (): void {
            Route::get('/catalogo', CatalogController::class)->name('catalogo');
            Route::get('/solicitudes', [StudentSolicitudController::class, 'index'])->name('solicitudes.index');
            Route::post('/solicitudes', [StudentSolicitudController::class, 'store'])->middleware('throttle:20,1')->name('solicitudes.store');
            Route::get('/solicitudes/{solicitud}', [StudentSolicitudController::class, 'show'])->whereNumber('solicitud')->name('solicitudes.show');
            Route::patch('/solicitudes/{solicitud}', [StudentSolicitudController::class, 'update'])->whereNumber('solicitud')->name('solicitudes.update');
            Route::post('/solicitudes/{solicitud}/enviar', [StudentSolicitudController::class, 'submit'])->whereNumber('solicitud')->name('solicitudes.submit');
            Route::post('/solicitudes/{solicitud}/documentos/{documento}', [DocumentController::class, 'store'])->whereNumber(['solicitud', 'documento'])->middleware('throttle:30,1')->name('documentos.store');
            Route::get('/solicitudes/{solicitud}/documentos/{documento}/download', [DocumentController::class, 'download'])->whereNumber(['solicitud', 'documento'])->name('documentos.download');
            Route::get('/solicitudes/{solicitud}/resolucion/download', [StudentResolutionController::class, 'download'])->whereNumber('solicitud')->name('resolucion.download');
            Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notificaciones.index');
            Route::patch('/notificaciones/{notificacion}/leer', [NotificationController::class, 'update'])->whereUuid('notificacion')->name('notificaciones.read');
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::get('/antecedentes', [AcademicBackgroundController::class, 'index'])->name('antecedentes.index');
            Route::post('/antecedentes', [AcademicBackgroundController::class, 'store'])->name('antecedentes.store');
            Route::get('/antecedentes/{antecedente}', [AcademicBackgroundController::class, 'show'])->whereNumber('antecedente')->name('antecedentes.show');
            Route::patch('/antecedentes/{antecedente}', [AcademicBackgroundController::class, 'update'])->whereNumber('antecedente')->name('antecedentes.update');
        });

        Route::prefix('admin')->name('admin.')->middleware('role:administrador')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');

            Route::get('/coordinators/{coordinator}/careers', [CoordinatorCareerController::class, 'index'])->name('coordinators.careers.index');
            Route::put('/coordinators/{coordinator}/careers', [CoordinatorCareerController::class, 'update'])->name('coordinators.careers.update');
            Route::get('/careers', CareerController::class)->name('careers.index');

            Route::get('/students', [StudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

            Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
            Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show'])->name('solicitudes.show');
            Route::post('/solicitudes/{solicitud}/resolucion', [ResolutionController::class, 'store'])->name('solicitudes.resolucion.store');
            Route::get('/solicitudes/{solicitud}/resolucion/download', [ResolutionController::class, 'download'])->name('solicitudes.resolucion.download');

            Route::get('/reports/solicitudes', [ReportController::class, 'solicitudes'])->name('reports.solicitudes');
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
        });

        Route::prefix('coordinator')->name('coordinator.')->middleware('role:coordinador')->group(function (): void {
            Route::get('/catalogo', CoordinatorCatalogController::class)->name('catalogo');
            Route::get('/students', [CoordinatorStudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [CoordinatorStudentController::class, 'show'])->whereNumber('student')->name('students.show');

            Route::get('/solicitudes', [CoordinatorSolicitudController::class, 'index'])->name('solicitudes.index');
            Route::get('/solicitudes/{solicitud}', [CoordinatorSolicitudController::class, 'show'])->whereNumber('solicitud')->name('solicitudes.show');
            Route::get('/solicitudes/{solicitud}/documents', [CoordinatorDocumentController::class, 'index'])->whereNumber('solicitud')->name('solicitudes.documents.index');
            Route::get('/documents/{documento}', [CoordinatorDocumentController::class, 'show'])->whereNumber('documento')->name('documents.show');
            Route::get('/documents/{documento}/download', [CoordinatorDocumentController::class, 'download'])->whereNumber('documento')->name('documents.download');
            Route::patch('/documents/{documento}/review', [CoordinatorDocumentController::class, 'review'])->whereNumber('documento')->name('documents.review');
            Route::post('/documents/{documento}/verification', [CoordinatorDocumentController::class, 'verify'])->whereNumber('documento')->name('documents.verification');

            Route::get('/curricula', [CoordinatorCurriculumController::class, 'index'])->name('curricula.index');
            Route::post('/curricula', [CoordinatorCurriculumController::class, 'store'])->name('curricula.store');
            Route::get('/curricula/{malla}', [CoordinatorCurriculumController::class, 'show'])->whereNumber('malla')->name('curricula.show');
            Route::match(['put', 'patch'], '/curricula/{malla}', [CoordinatorCurriculumController::class, 'update'])->whereNumber('malla')->name('curricula.update');
            Route::patch('/curricula/{malla}/status', [CoordinatorCurriculumController::class, 'updateStatus'])->whereNumber('malla')->name('curricula.status');
            Route::get('/curricula/{malla}/subjects', [CoordinatorSubjectController::class, 'index'])->whereNumber('malla')->name('curricula.subjects.index');
            Route::post('/curricula/{malla}/subjects', [CoordinatorSubjectController::class, 'store'])->whereNumber('malla')->name('curricula.subjects.store');
            Route::get('/subjects/{asignatura}', [CoordinatorSubjectController::class, 'show'])->whereNumber('asignatura')->name('subjects.show');
            Route::match(['put', 'patch'], '/subjects/{asignatura}', [CoordinatorSubjectController::class, 'update'])->whereNumber('asignatura')->name('subjects.update');
            Route::get('/subjects/{asignatura}/syllabus-topics', [CoordinatorSyllabusTopicController::class, 'index'])->whereNumber('asignatura')->name('subjects.syllabus.index');
            Route::post('/subjects/{asignatura}/syllabus-topics', [CoordinatorSyllabusTopicController::class, 'store'])->whereNumber('asignatura')->name('subjects.syllabus.store');
            Route::put('/syllabus-topics/{tema}', [CoordinatorSyllabusTopicController::class, 'update'])->whereNumber('tema')->name('syllabus.update');

            Route::get('/solicitudes/{solicitud}/comparisons', [CoordinatorComparisonController::class, 'index'])->whereNumber('solicitud')->name('solicitudes.comparisons.index');
            Route::post('/solicitudes/{solicitud}/comparisons', [CoordinatorComparisonController::class, 'store'])->whereNumber('solicitud')->name('solicitudes.comparisons.store');
            Route::put('/comparisons/{comparacion}', [CoordinatorComparisonController::class, 'update'])->whereNumber('comparacion')->name('comparisons.update');
            Route::delete('/comparisons/{comparacion}', [CoordinatorComparisonController::class, 'destroy'])->whereNumber('comparacion')->name('comparisons.destroy');
            Route::get('/solicitudes/{solicitud}/result', [CoordinatorAnalysisResultController::class, 'show'])->whereNumber('solicitud')->name('solicitudes.result.show');
            Route::post('/solicitudes/{solicitud}/result', [CoordinatorAnalysisResultController::class, 'store'])->whereNumber('solicitud')->name('solicitudes.result.store');
            Route::post('/solicitudes/{solicitud}/technical-report', [CoordinatorTechnicalReportController::class, 'store'])->whereNumber('solicitud')->name('solicitudes.technical_report.store');
            Route::get('/solicitudes/{solicitud}/technical-report', [CoordinatorTechnicalReportController::class, 'download'])->whereNumber('solicitud')->name('solicitudes.technical_report.download');
            Route::post('/solicitudes/{solicitud}/state', [CoordinatorSolicitudStateController::class, 'store'])->whereNumber('solicitud')->name('solicitudes.state.store');
            Route::post('/solicitudes/{solicitud}/resolution', [CoordinatorResolutionController::class, 'store'])->whereNumber('solicitud')->name('solicitudes.resolucion.store');
            Route::get('/solicitudes/{solicitud}/resolution/download', [CoordinatorResolutionController::class, 'download'])->whereNumber('solicitud')->name('solicitudes.resolucion.download');
            Route::get('/reports/solicitudes', [CoordinatorReportController::class, 'solicitudes'])->name('reports.solicitudes');
        });
    });
});
