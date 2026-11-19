@echo off
setlocal
REM Full test suite (unit + integration). Integration tests build a temporary SQLite
REM database from contract\schema.json, so there is no live DB mirror to pull.

cd /d "%~dp0.."

if not exist contract\schema.json (
    echo WARNING: contract\ submodule missing - contract tests will be skipped.
    echo          Run: git submodule update --init contract
)

REM Xdebug's develop mode slows every function call; the suite does not need it.
set "XDEBUG_MODE=off"

REM No redirect and no pipe: output streams live and the exit code stays phpunit's.
REM --debug prints every test as it starts and finishes; --log-events-text keeps a
REM trustworthy pass/fail artifact for callers whose stdout capture is unreliable.
call "vendor\bin\phpunit.bat" --colors=never --debug --log-events-text "%~dp0_last_test_run.log" %*
set "TESTRESULT=%ERRORLEVEL%"

cd /d "%~dp0"
endlocal & exit /b %TESTRESULT%
