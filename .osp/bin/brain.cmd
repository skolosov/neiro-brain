@echo off
REM Helper available inside the OpenServer project environment.
REM Usage: brain sync | brain stats | brain search "query" | brain reindex
php "%~dp0..\..\artisan" brain:%* 
