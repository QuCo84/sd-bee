REM "& ^" to continue after error "&& ^" to stop after error
REM Unit tests
call test\test app\editor-view-model ud.php
call test\test app\editor-view-model\elements udelement.php
call test\test app\editor-view-model\elements udcommands.php
call test\test app\editor-view-model\helpers
call test\test app\editor-view-model\elements udtable.php
call test app\editor-view-model\elements udlist.php
call test app\editor-view-model\elements udtext.php
call test ..\app\editor-view-model\elements udgraphic.php
call test ..\app\editor-view-model\elements udhtml.php
call test ..\app\editor-view-model\elements udconnector.php
call test ..\app\editor-view-model\elements udcdropzone.php
call test ..\app\editor-view-model\elements udcdocument.php
call test ..\app\editor-view-model\elements udcservice.php
call test ..\app\editor-view-model\elements udvideo.php
call test ..\app\editor-view-model\elements udchart.php
call test ..\app\editor-view-model\helpers udutilities.php
call test ..\app\editor-view-model\helpers udutilityfunctions.php
call test ..\app\editor-view-model\helpersudresources.php
REM Integrated tests
call test tests udviewmodeltest.php
@echo off
setlocal
@set "rootFolder=test\test-reports"
@set "fileMask=*.txt"
@set uncompletedcount=0
@set nokocount=0
@set filecount=0
for %%F in ("%rootFolder%\%fileMask%") do (
    @set /a filecount=filecount+1
    findstr /l "Test completed" "%%F" >nul || @set /a uncompletedcount=uncompletedcount+1
    findstr /l "KO" "%%F" >nul || @set /a nokocount=nokocount+1
    findstr /l "Test completed" "%%F" >nul || echo %%F uncompleted!
    findstr /l "KO" "%%F" >null && echo %%F has KO

)
echo %filecount% reports found
echo %uncompletedcount% uncompleted reports
@set /a koreports=%filecount%-%nokocount%
echo %koreports% reports with KO
if %uncompletedcount% GTR 1 exit /b 3
if %nokocount% NEQ %filecount%  exit /b 4
exit /b 0