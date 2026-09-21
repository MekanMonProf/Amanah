@echo off
title AMANAH - Demarrage
color 0A

echo ============================================
echo    Demarrage de la plateforme AMANAH
echo ============================================
echo.

REM Ouvre le panneau XAMPP si present, pour verifier/demarrer MySQL
if exist "C:\xampp\xampp-control.exe" (
    echo Ouverture du panneau XAMPP...
    echo -^> Verifiez que "MySQL" est bien demarre (bouton vert "Start")
    start "" "C:\xampp\xampp-control.exe"
    timeout /t 3 /nobreak >nul
) else (
    echo [ATTENTION] XAMPP non trouve a l'emplacement habituel.
    echo Demarrez MySQL manuellement si besoin.
)

echo.
echo Demarrage du serveur Laravel...
cd /d C:\Users\hp\amanah-platform
start "Serveur AMANAH - NE PAS FERMER" cmd /k "php artisan serve"

echo Attente du demarrage du serveur...
timeout /t 4 /nobreak >nul

echo Ouverture du navigateur...
start "" http://localhost:8000/investisseurs

echo.
echo ============================================
echo  Tout est lance ! Vous pouvez fermer cette
echo  fenetre (mais PAS celle du "Serveur AMANAH").
echo ============================================
timeout /t 6
