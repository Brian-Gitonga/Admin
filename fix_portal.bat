@echo off
echo Fixing portal.php...
echo.

REM Create backup
copy portal.php portal_backup_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%.php
echo Backup created.

REM Read first 1695 lines and write to temp file
powershell -Command "$content = Get-Content 'portal.php' -TotalCount 1695; $content | Set-Content 'portal_temp.php' -Encoding UTF8"

REM Replace original with temp
move /Y portal_temp.php portal.php

echo.
echo SUCCESS! portal.php has been fixed.
echo Please refresh your browser and test the modal.
pause

