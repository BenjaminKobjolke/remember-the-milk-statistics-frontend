@echo off
setlocal
REM Serves the statistics page locally with PHP's built-in server.
cd /d "%~dp0"
if not exist config\app.php (
    echo ERROR: config\app.php missing. Copy config\app.php.example to config\app.php and edit db_path.
    exit /b 1
)
if "%PORT%"=="" set "PORT=8080"
echo Serving http://localhost:%PORT%  (Ctrl+C to stop)
php -S localhost:%PORT% -t public
set "RESULT=%ERRORLEVEL%"
endlocal & exit /b %RESULT%
