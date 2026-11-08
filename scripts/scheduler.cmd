@echo off
REM Neiro Brain scheduler for Windows Task Scheduler.
REM Run every minute. Adjust PHP_PATH if OpenServer uses another PHP version.

set "PHP_PATH=C:\OSPanel65\modules\PHP-8.4\php.exe"
set "PROJECT=%~dp0.."

"%PHP_PATH%" "%PROJECT%\artisan" schedule:run >> "%PROJECT%\storage\logs\scheduler.log" 2>&1
