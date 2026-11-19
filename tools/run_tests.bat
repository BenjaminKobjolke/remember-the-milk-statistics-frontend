@echo off
setlocal
REM Unit tests only - no database, works offline. tests.bat remains the full runner.
call "%~dp0tests.bat" --testsuite unit %*
set "TESTRESULT=%ERRORLEVEL%"
endlocal & exit /b %TESTRESULT%
