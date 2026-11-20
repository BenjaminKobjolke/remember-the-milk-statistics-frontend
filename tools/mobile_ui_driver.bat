@echo off
setlocal
php "%~dp0mobile_ui_driver\driver.php" %*
set "DRIVERRESULT=%ERRORLEVEL%"
endlocal & exit /b %DRIVERRESULT%
