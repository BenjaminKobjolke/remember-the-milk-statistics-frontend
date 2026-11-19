@echo off
setlocal
REM Integration tests - repository and full page render against a temporary SQLite
REM database created from contract\schema.json. tests.bat remains the full runner.
call "%~dp0tests.bat" --testsuite integration %*
set "TESTRESULT=%ERRORLEVEL%"
endlocal & exit /b %TESTRESULT%
