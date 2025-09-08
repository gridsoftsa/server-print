@echo off
echo ========================================
echo    REINICIAR SERVICIO GRIDPOS
echo ========================================
echo.

echo [1/2] Reiniciando servicio gridpos con NSSM...
c:\nssm restart gridpos
if %errorlevel% neq 0 (
    echo ❌ Error al reiniciar el servicio gridpos
    echo.
    echo Verificando estado del servicio...
    c:\nssm status gridpos
    echo.
    echo Presiona cualquier tecla para salir...
    pause >nul
    exit /b 1
) else (
    echo ✅ Servicio reiniciado correctamente
)

echo.
echo [2/2] Verificando estado del servicio...
c:\nssm status gridpos

echo.
echo ========================================
echo    SERVICIO REINICIADO EXITOSAMENTE
echo ========================================
echo.
echo Estado del servicio:
sc query gridpos | findstr "STATE"
echo.
echo Presiona cualquier tecla para salir...
pause >nul
